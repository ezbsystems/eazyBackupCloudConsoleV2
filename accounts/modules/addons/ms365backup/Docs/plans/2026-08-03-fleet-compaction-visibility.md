# Fleet Compaction Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Dashboard panel on the MS365 Worker Fleet page so admins can view active/recent MS365 Kopia repo ops (compaction visibility) and enqueue maintenance/retention without using the server CLI.

**Architecture:** Thin `Ms365FleetRepoOpsService` reads local `s3_kopia_repo_operations` (MS365 repos only) and enqueues via `KopiaRetentionOperationService`. Fleet admin API exposes `fleet_repo_ops` / `fleet_repo_ops_enqueue`. `fleet.js` polls every 15s with the existing dashboard interval. No `fleet_remote` / dual-fleet proxy.

**Tech Stack:** PHP 8.x + WHMCS Capsule, existing `pages/admin/api.php` + `fleet.js` Bootstrap admin UI.

**Spec:** `accounts/modules/addons/ms365backup/Docs/specs/2026-08-03-fleet-compaction-visibility-design.md`

## Global Constraints

- Local WHMCS DB only — never route through `FleetFacade` / `fleet_remote` for this panel.
- Filter repos with `repository_id LIKE 'ms365:%'`.
- Active = `queued`/`running` (limit 25); recent = last 50 any status.
- Enqueue `op_type` ∈ `maintenance_quick` | `maintenance_full` | `retention_apply` only.
- Prefer attaching `e3_job_id` from the latest prior op payload for that repo when present.
- Enqueue tokens must be unique (`ms365-fleet-{op_type}-{repo_id}-{YmdHis}-{random4}`) — do not reuse weekly schedule tokens.
- Preserve existing fleet 15s poll pattern; do not add a separate timer.
- YAGNI: no filters, cancel/reap buttons, or cloudstorage Retention page changes.

## File structure

| File | Responsibility |
|------|----------------|
| `lib/Ms365Backup/Ms365FleetRepoOpsService.php` | List active/recent/repos; summarize rows; enqueue |
| `tests/ms365_fleet_repo_ops_test.php` | PHP assert-style coverage for list + enqueue |
| `pages/admin/api.php` | `fleet_repo_ops`, `fleet_repo_ops_enqueue` cases |
| `pages/admin/fleet.php` | Dashboard panel shell `#fleet-repo-ops` |
| `assets/js/fleet.js` | `renderRepoOps()` + poll + enqueue form |
| `Docs/PROGRESS.md` | Short ship note |

---

### Task 1: Ms365FleetRepoOpsService + tests

**Files:**
- Create: `accounts/modules/addons/ms365backup/lib/Ms365Backup/Ms365FleetRepoOpsService.php`
- Create: `accounts/modules/addons/ms365backup/tests/ms365_fleet_repo_ops_test.php`

**Interfaces:**
- Produces:
  - `Ms365FleetRepoOpsService::listForFleet(): array{active: list<array>, recent: list<array>, repos: list<array>}`
  - `Ms365FleetRepoOpsService::enqueue(int $repoId, string $opType): array{ok: bool, status?: string, operation_id?: int, message?: string, error?: string}`
  - OpRow keys: `id`, `repo_id`, `repository_id`, `op_type`, `status`, `attempt_count`, `claimed_by_node_id`, `phase`, `effective_mode`, `index_blobs_before`, `index_blobs_after`, `escalated`, `skipped`, `duration_seconds`, `created_at`, `updated_at`, `error`

- [ ] **Step 1: Write the failing test file**

Create `accounts/modules/addons/ms365backup/tests/ms365_fleet_repo_ops_test.php`:

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
require_once $root . '/init.php';
require_once dirname(__DIR__, 2) . '/cloudstorage/lib/Ms365BackupBootstrap.php';
cloudstorage_load_ms365backup();

use Ms365Backup\Ms365FleetRepoOpsService;
use WHMCS\Database\Capsule;

$failures = 0;
function assert_true(bool $cond, string $message): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

function insertMs365Repo(string $suffix): int
{
    $now = date('Y-m-d H:i:s');
    $row = [
        'repository_id' => 'ms365:fleet-ui-' . $suffix,
        'client_id' => 1,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if (Capsule::schema()->hasColumn('s3_kopia_repos', 'vault_policy_version_id')) {
        $policyId = Capsule::table('s3_kopia_policy_versions')->orderBy('id')->value('id');
        if ($policyId) {
            $row['vault_policy_version_id'] = (int) $policyId;
        }
    }
    return (int) Capsule::table('s3_kopia_repos')->insertGetId($row);
}

function insertOp(int $repoId, string $opType, string $status, array $payload = [], ?array $result = null): int
{
    $now = date('Y-m-d H:i:s');
    $row = [
        'repo_id' => $repoId,
        'op_type' => $opType,
        'status' => $status,
        'attempt_count' => 1,
        'operation_token' => 'fleet-test-' . bin2hex(random_bytes(8)),
        'payload_json' => json_encode($payload),
        'result_json' => $result !== null ? json_encode($result) : null,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if (Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id')) {
        $row['claimed_by_node_id'] = $status === 'running' ? '11111111-1111-1111-1111-111111111111' : null;
    }
    return (int) Capsule::table('s3_kopia_repo_operations')->insertGetId($row);
}

$repoId = insertMs365Repo(substr(md5((string) microtime(true)), 0, 8));
$runningId = insertOp($repoId, 'maintenance_full', 'running', [
    'engine' => 'ms365',
    'e3_job_id' => 'a98f9943-379a-4197-b63e-384aecbedbe7',
], ['phase' => 'pre_open', 'index_blobs_before' => 25000]);
$doneId = insertOp($repoId, 'maintenance_quick', 'success', ['engine' => 'ms365'], [
    'phase' => 'complete',
    'effective_mode' => 'quick',
    'index_blobs_before' => 100,
    'index_blobs_after' => 80,
]);

$list = Ms365FleetRepoOpsService::listForFleet();
assert_true(is_array($list['active'] ?? null) && is_array($list['recent'] ?? null) && is_array($list['repos'] ?? null), 'listForFleet shape');
$activeIds = array_column($list['active'], 'id');
assert_true(in_array($runningId, $activeIds, true), 'running op in active');
assert_true(!in_array($doneId, $activeIds, true), 'success op not in active');
$recentIds = array_column($list['recent'], 'id');
assert_true(in_array($runningId, $recentIds, true) && in_array($doneId, $recentIds, true), 'both in recent');
$runningRow = null;
foreach ($list['active'] as $row) {
    if ((int) $row['id'] === $runningId) {
        $runningRow = $row;
        break;
    }
}
assert_true(($runningRow['phase'] ?? '') === 'pre_open', 'phase summarized');
assert_true((int) ($runningRow['index_blobs_before'] ?? 0) === 25000, 'index_blobs_before summarized');

$bad = Ms365FleetRepoOpsService::enqueue(999999999, 'maintenance_full');
assert_true(($bad['ok'] ?? true) === false, 'enqueue rejects unknown repo');

$enq = Ms365FleetRepoOpsService::enqueue($repoId, 'maintenance_full');
assert_true(($enq['ok'] ?? false) === true, 'enqueue succeeds');
assert_true(($enq['operation_id'] ?? 0) > 0, 'enqueue returns operation_id');
$payload = Capsule::table('s3_kopia_repo_operations')->where('id', (int) $enq['operation_id'])->value('payload_json');
$decoded = json_decode((string) $payload, true);
assert_true(($decoded['e3_job_id'] ?? '') === 'a98f9943-379a-4197-b63e-384aecbedbe7', 'enqueue copies e3_job_id');
assert_true(($decoded['engine'] ?? '') === 'ms365', 'enqueue sets engine=ms365');

$nonMs = Capsule::table('s3_kopia_repos')->insertGetId([
    'repository_id' => 'other:fleet-ui-' . substr(md5((string) microtime(true)), 0, 6),
    'client_id' => 1,
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
]);
$rej = Ms365FleetRepoOpsService::enqueue((int) $nonMs, 'maintenance_quick');
assert_true(($rej['ok'] ?? true) === false, 'enqueue rejects non-ms365 repo');

echo PHP_EOL . ($failures === 0 ? "ALL TESTS PASSED\n" : "FAILURES: {$failures}\n");
exit($failures === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/ms365backup/tests/ms365_fleet_repo_ops_test.php`

Expected: FAIL with class `Ms365FleetRepoOpsService` not found (or similar).

- [ ] **Step 3: Implement Ms365FleetRepoOpsService**

Create `accounts/modules/addons/ms365backup/lib/Ms365Backup/Ms365FleetRepoOpsService.php`:

```php
<?php
declare(strict_types=1);

namespace Ms365Backup;

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\KopiaRetentionOperationService;

final class Ms365FleetRepoOpsService
{
    private const ACTIVE_LIMIT = 25;
    private const RECENT_LIMIT = 50;
    private const REPO_LIMIT = 100;

    /** @return array{active: list<array<string,mixed>>, recent: list<array<string,mixed>>, repos: list<array<string,mixed>>} */
    public static function listForFleet(): array
    {
        if (!Capsule::schema()->hasTable('s3_kopia_repo_operations')
            || !Capsule::schema()->hasTable('s3_kopia_repos')) {
            return ['active' => [], 'recent' => [], 'repos' => []];
        }

        $base = Capsule::table('s3_kopia_repo_operations as op')
            ->join('s3_kopia_repos as r', 'r.id', '=', 'op.repo_id')
            ->where('r.repository_id', 'like', 'ms365:%');

        $select = [
            'op.id', 'op.repo_id', 'op.op_type', 'op.status', 'op.attempt_count',
            'op.created_at', 'op.updated_at', 'op.result_json', 'op.payload_json',
            'r.repository_id', 'r.client_id',
        ];
        if (Capsule::schema()->hasColumn('s3_kopia_repo_operations', 'claimed_by_node_id')) {
            $select[] = 'op.claimed_by_node_id';
        }

        $activeRows = (clone $base)
            ->whereIn('op.status', ['queued', 'running'])
            ->orderByRaw("FIELD(op.status, 'running', 'queued')")
            ->orderBy('op.created_at')
            ->limit(self::ACTIVE_LIMIT)
            ->get($select);

        $recentRows = (clone $base)
            ->orderByDesc('op.id')
            ->limit(self::RECENT_LIMIT)
            ->get($select);

        $repos = Capsule::table('s3_kopia_repos')
            ->where('status', 'active')
            ->where('repository_id', 'like', 'ms365:%')
            ->orderByDesc('id')
            ->limit(self::REPO_LIMIT)
            ->get(['id', 'repository_id', 'client_id']);

        return [
            'active' => array_map([self::class, 'mapOpRow'], $activeRows->all()),
            'recent' => array_map([self::class, 'mapOpRow'], $recentRows->all()),
            'repos' => array_map(static fn ($r) => [
                'id' => (int) $r->id,
                'repository_id' => (string) $r->repository_id,
                'client_id' => (int) ($r->client_id ?? 0),
            ], $repos->all()),
        ];
    }

    /** @return array{ok: bool, status?: string, operation_id?: int, message?: string, error?: string} */
    public static function enqueue(int $repoId, string $opType): array
    {
        $allowed = ['maintenance_quick', 'maintenance_full', 'retention_apply'];
        if ($repoId <= 0 || !in_array($opType, $allowed, true)) {
            return ['ok' => false, 'error' => 'repo_id and valid op_type required'];
        }
        if (!Capsule::schema()->hasTable('s3_kopia_repos')
            || !Capsule::schema()->hasTable('s3_kopia_repo_operations')) {
            return ['ok' => false, 'error' => 'repo operations schema unavailable'];
        }

        $repo = Capsule::table('s3_kopia_repos')->where('id', $repoId)->first();
        if ($repo === null) {
            return ['ok' => false, 'error' => 'repo not found'];
        }
        $repositoryId = (string) ($repo->repository_id ?? '');
        if (!str_starts_with($repositoryId, 'ms365:')) {
            return ['ok' => false, 'error' => 'repo is not an MS365 repository'];
        }

        $payload = ['repo_id' => $repoId, 'engine' => 'ms365', 'reason' => 'fleet_dashboard_enqueue'];
        $prior = Capsule::table('s3_kopia_repo_operations')
            ->where('repo_id', $repoId)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['payload_json']);
        foreach ($prior as $row) {
            $decoded = json_decode((string) ($row->payload_json ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }
            $jobId = trim((string) ($decoded['e3_job_id'] ?? ''));
            if ($jobId !== '') {
                $payload['e3_job_id'] = $jobId;
                break;
            }
        }

        $token = sprintf(
            'ms365-fleet-%s-%d-%s-%s',
            $opType,
            $repoId,
            gmdate('YmdHis'),
            bin2hex(random_bytes(2))
        );

        $result = KopiaRetentionOperationService::enqueue($repoId, $opType, $payload, $token);
        $status = (string) ($result['status'] ?? 'error');
        if (!in_array($status, ['success', 'duplicate'], true)) {
            return ['ok' => false, 'error' => 'enqueue failed', 'status' => $status];
        }

        return [
            'ok' => true,
            'status' => $status,
            'operation_id' => (int) ($result['operation_id'] ?? 0),
            'message' => $status === 'duplicate'
                ? 'Operation already queued (duplicate token)'
                : 'Enqueued operation #' . (int) ($result['operation_id'] ?? 0),
        ];
    }

    /** @param object $row */
    private static function mapOpRow(object $row): array
    {
        $result = [];
        if (!empty($row->result_json)) {
            $decoded = json_decode((string) $row->result_json, true);
            if (is_array($decoded)) {
                $result = $decoded;
            }
        }
        $created = (string) ($row->created_at ?? '');
        $updated = (string) ($row->updated_at ?? '');
        $duration = null;
        $start = $created !== '' ? strtotime($created) : false;
        $end = $updated !== '' ? strtotime($updated) : false;
        if ($start !== false && $end !== false && $end >= $start) {
            $duration = $end - $start;
        }

        return [
            'id' => (int) $row->id,
            'repo_id' => (int) $row->repo_id,
            'repository_id' => (string) ($row->repository_id ?? ''),
            'op_type' => (string) ($row->op_type ?? ''),
            'status' => (string) ($row->status ?? ''),
            'attempt_count' => (int) ($row->attempt_count ?? 0),
            'claimed_by_node_id' => isset($row->claimed_by_node_id) ? (string) $row->claimed_by_node_id : '',
            'phase' => (string) ($result['phase'] ?? ''),
            'effective_mode' => (string) ($result['effective_mode'] ?? ''),
            'index_blobs_before' => array_key_exists('index_blobs_before', $result) ? (int) $result['index_blobs_before'] : null,
            'index_blobs_after' => array_key_exists('index_blobs_after', $result) ? (int) $result['index_blobs_after'] : null,
            'escalated' => !empty($result['escalated']),
            'skipped' => !empty($result['skipped']),
            'duration_seconds' => $duration,
            'created_at' => $created,
            'updated_at' => $updated,
            'error' => (string) ($result['error'] ?? ''),
        ];
    }
}
```

Note: if the runtime is PHP &lt; 8.0, replace `str_starts_with($repositoryId, 'ms365:')` with `strpos($repositoryId, 'ms365:') === 0`.

- [ ] **Step 4: Run tests and make sure they pass**

Run: `php accounts/modules/addons/ms365backup/tests/ms365_fleet_repo_ops_test.php`

Expected: `ALL TESTS PASSED`

- [ ] **Step 5: Commit** (only if the user requested commits)

```bash
git add accounts/modules/addons/ms365backup/lib/Ms365Backup/Ms365FleetRepoOpsService.php \
  accounts/modules/addons/ms365backup/tests/ms365_fleet_repo_ops_test.php
git commit -m "$(cat <<'EOF'
feat(ms365): add fleet repo ops list/enqueue service

EOF
)"
```

---

### Task 2: Admin API endpoints

**Files:**
- Modify: `accounts/modules/addons/ms365backup/pages/admin/api.php` (near `fleet_audit` / before `Unknown op`)

**Interfaces:**
- Consumes: `Ms365FleetRepoOpsService::listForFleet()`, `::enqueue(int, string)`
- Produces: JSON `{ok, active, recent, repos}` and `{ok, status, operation_id, message|error}`

- [ ] **Step 1: Add API cases**

Insert before `default:` / `Unknown op`:

```php
        case 'fleet_repo_ops':
            $data = \Ms365Backup\Ms365FleetRepoOpsService::listForFleet();
            echo json_encode(['ok' => true] + $data);
            break;

        case 'fleet_repo_ops_enqueue':
            $repoId = (int) ($_POST['repo_id'] ?? 0);
            $opType = trim((string) ($_POST['op_type'] ?? ''));
            echo json_encode(\Ms365Backup\Ms365FleetRepoOpsService::enqueue($repoId, $opType));
            break;
```

Confirm POST CSRF gate already wraps mutating ops (existing `check_token` at top of POST path) — do not bypass it.

- [ ] **Step 2: Smoke the GET endpoint via PHP CLI auth bypass is not available — instead unit-test already covers service; optionally:**

```bash
php -r 'require "accounts/init.php"; require "accounts/modules/addons/ms365backup/ms365backup_autoload.php"; echo json_encode(Ms365Backup\Ms365FleetRepoOpsService::listForFleet(), JSON_PRETTY_PRINT);' | head -40
```

Expected: JSON with `active`, `recent`, `repos` keys.

- [ ] **Step 3: Commit** (only if requested)

```bash
git add accounts/modules/addons/ms365backup/pages/admin/api.php
git commit -m "$(cat <<'EOF'
feat(ms365): expose fleet_repo_ops admin API

EOF
)"
```

---

### Task 3: Dashboard panel shell + JS

**Files:**
- Modify: `accounts/modules/addons/ms365backup/pages/admin/fleet.php` (dashboard section, before Recent audit)
- Modify: `accounts/modules/addons/ms365backup/assets/js/fleet.js`

**Interfaces:**
- Consumes: `GET fleet_repo_ops`, `POST fleet_repo_ops_enqueue`
- Produces: `#fleet-repo-ops` rendered HTML; bind enqueue once via `data-bound`

- [ ] **Step 1: Add panel HTML in fleet.php**

Inside `<?php if ($tab === 'dashboard'): ?>`, after `#fleet-dashboard` and **before** the Recent audit panel:

```php
<div class="panel panel-default" style="margin-top:15px" id="fleet-repo-ops-panel">
    <div class="panel-heading"><strong>Repo operations / compaction</strong></div>
    <div class="panel-body">
        <p class="text-muted" style="margin-top:0"><small>Shows operations for this WHMCS environment only.</small></p>
        <div id="fleet-repo-ops-active"><p class="text-muted">Loading…</p></div>
        <form id="fleet-repo-ops-enqueue" class="form-inline" style="margin:12px 0">
            <div class="form-group">
                <label for="fleet-repo-ops-repo">Repo</label>
                <select id="fleet-repo-ops-repo" name="repo_id" class="form-control input-sm" style="min-width:220px"></select>
            </div>
            <div class="form-group" style="margin-left:10px">
                <label for="fleet-repo-ops-type">Type</label>
                <select id="fleet-repo-ops-type" name="op_type" class="form-control input-sm">
                    <option value="maintenance_full" selected>maintenance_full</option>
                    <option value="maintenance_quick">maintenance_quick</option>
                    <option value="retention_apply">retention_apply</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-left:10px" id="fleet-repo-ops-enqueue-btn">Enqueue</button>
        </form>
        <p class="text-muted"><small>Attaches <code>e3_job_id</code> from the latest op for that repo when available (needed for the correct repo password).</small></p>
        <div id="fleet-repo-ops-notice"></div>
        <h5 style="margin-top:16px">Recent</h5>
        <div id="fleet-repo-ops-recent"><p class="text-muted">Loading…</p></div>
    </div>
</div>
```

- [ ] **Step 2: Add renderRepoOps + helpers in fleet.js**

Add helpers and `renderRepoOps` near `renderDashboard`. Call `renderRepoOps()` at the end of `renderDashboard` when `#fleet-repo-ops-panel` exists, and also from `setInterval` (same condition).

```javascript
  function statusLabel(status) {
    var s = String(status || 'queued');
    var cls = 'default';
    if (s === 'success') cls = 'success';
    else if (s === 'error' || s === 'failed') cls = 'danger';
    else if (s === 'running') cls = 'info';
    return '<span class="label label-' + cls + '">' + esc(s) + '</span>';
  }

  function indexBlobsCell(op) {
    if (op.index_blobs_before == null && op.index_blobs_after == null) return '—';
    var a = op.index_blobs_before != null ? op.index_blobs_before : '—';
    var b = op.index_blobs_after != null ? op.index_blobs_after : '—';
    return esc(a) + ' → ' + esc(b);
  }

  function phaseCell(op) {
    var phase = op.phase || '—';
    var extra = '';
    if (op.effective_mode) {
      extra = '<br><small class="text-muted">' + esc(op.effective_mode);
      if (op.escalated) extra += ' (escalated)';
      if (op.skipped) extra += ' (skipped)';
      extra += '</small>';
    }
    if (op.error) {
      extra += '<br><small class="text-danger">' + esc(op.error) + '</small>';
    }
    return esc(phase) + extra;
  }

  function repoOpsActiveTable(rows) {
    if (!rows || !rows.length) {
      return '<p class="text-muted">No active MS365 repo operations.</p>';
    }
    return '<table class="table table-condensed table-striped"><thead><tr>' +
      '<th>ID</th><th>Repo</th><th>Type</th><th>Status</th><th>Claimed node</th><th>Phase</th><th>Index blobs</th><th>Updated</th>' +
      '</tr></thead><tbody>' + rows.map(function (op) {
        var node = op.claimed_by_node_id ? '<code>' + esc(String(op.claimed_by_node_id).slice(0, 8)) + '…</code>' : '—';
        return '<tr>' +
          '<td>' + esc(op.id) + '</td>' +
          '<td>#' + esc(op.repo_id) + ' <small>(' + esc((op.repository_id || '').slice(0, 24)) + ')</small></td>' +
          '<td>' + esc(op.op_type) + '</td>' +
          '<td>' + statusLabel(op.status) + '</td>' +
          '<td>' + node + '</td>' +
          '<td>' + phaseCell(op) + '</td>' +
          '<td>' + indexBlobsCell(op) + '</td>' +
          '<td>' + esc(op.updated_at || '—') + '</td>' +
          '</tr>';
      }).join('') + '</tbody></table>';
  }

  function repoOpsRecentTable(rows) {
    if (!rows || !rows.length) {
      return '<p class="text-muted">No recent MS365 repo operations.</p>';
    }
    return '<table class="table table-condensed table-striped"><thead><tr>' +
      '<th>ID</th><th>Repo</th><th>Type</th><th>Status</th><th>Claimed node</th><th>Phase / Outcome</th><th>Index blobs</th><th>Attempts</th><th>Duration</th><th>Created</th><th>Updated</th>' +
      '</tr></thead><tbody>' + rows.map(function (op) {
        var node = op.claimed_by_node_id ? '<code>' + esc(String(op.claimed_by_node_id).slice(0, 8)) + '…</code>' : '—';
        var dur = op.duration_seconds != null ? esc(op.duration_seconds) + 's' : '—';
        return '<tr>' +
          '<td>' + esc(op.id) + '</td>' +
          '<td>#' + esc(op.repo_id) + '</td>' +
          '<td>' + esc(op.op_type) + '</td>' +
          '<td>' + statusLabel(op.status) + '</td>' +
          '<td>' + node + '</td>' +
          '<td>' + phaseCell(op) + '</td>' +
          '<td>' + indexBlobsCell(op) + '</td>' +
          '<td>' + esc(op.attempt_count || 0) + '</td>' +
          '<td>' + dur + '</td>' +
          '<td>' + esc(op.created_at || '—') + '</td>' +
          '<td>' + esc(op.updated_at || '—') + '</td>' +
          '</tr>';
      }).join('') + '</tbody></table>';
  }

  function fillRepoSelect(repos) {
    var sel = document.getElementById('fleet-repo-ops-repo');
    if (!sel) return;
    var prev = sel.value;
    sel.innerHTML = (repos || []).map(function (r) {
      return '<option value="' + esc(r.id) + '">#' + esc(r.id) + ' ' + esc(r.repository_id) + '</option>';
    }).join('') || '<option value="">No MS365 repos</option>';
    if (prev) sel.value = prev;
  }

  function bindRepoOpsEnqueue() {
    var form = document.getElementById('fleet-repo-ops-enqueue');
    if (!form || form.getAttribute('data-bound') === '1') return;
    form.setAttribute('data-bound', '1');
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = document.getElementById('fleet-repo-ops-enqueue-btn');
      var notice = document.getElementById('fleet-repo-ops-notice');
      var repoId = document.getElementById('fleet-repo-ops-repo').value;
      var opType = document.getElementById('fleet-repo-ops-type').value;
      if (btn) btn.disabled = true;
      post('fleet_repo_ops_enqueue', { repo_id: repoId, op_type: opType }).then(function (res) {
        if (btn) btn.disabled = false;
        if (!notice) return;
        if (!res.ok) {
          notice.innerHTML = '<div class="alert alert-danger">' + esc(res.error || 'Enqueue failed') + '</div>';
          return;
        }
        notice.innerHTML = '<div class="alert alert-success">' + esc(res.message || ('Enqueued #' + res.operation_id)) + '</div>';
        renderRepoOps();
      });
    });
  }

  function renderRepoOps() {
    var panel = document.getElementById('fleet-repo-ops-panel');
    if (!panel) return;
    bindRepoOpsEnqueue();
    get('fleet_repo_ops').then(function (res) {
      var activeEl = document.getElementById('fleet-repo-ops-active');
      var recentEl = document.getElementById('fleet-repo-ops-recent');
      if (!res.ok) {
        var msg = '<div class="alert alert-danger">' + esc(res.error || 'Failed to load repo ops') + '</div>';
        if (activeEl) activeEl.innerHTML = msg;
        return;
      }
      fillRepoSelect(res.repos || []);
      if (activeEl) activeEl.innerHTML = repoOpsActiveTable(res.active || []);
      if (recentEl) recentEl.innerHTML = repoOpsRecentTable(res.recent || []);
    });
  }
```

At end of `renderDashboard()` (after audit fetch kickoff), add:

```javascript
    if (document.getElementById('fleet-repo-ops-panel')) {
      renderRepoOps();
    }
```

In `setInterval`, add:

```javascript
      if (document.getElementById('fleet-repo-ops-panel')) renderRepoOps();
```

- [ ] **Step 3: Manual UI check**

Open `addonmodules.php?module=ms365backup&action=fleet&tab=dashboard` as admin.

Expected: panel loads; active/recent tables populate; enqueue form lists MS365 repos; after enqueue, new queued row appears within one poll.

- [ ] **Step 4: Commit** (only if requested)

```bash
git add accounts/modules/addons/ms365backup/pages/admin/fleet.php \
  accounts/modules/addons/ms365backup/assets/js/fleet.js
git commit -m "$(cat <<'EOF'
feat(ms365): show repo ops compaction panel on fleet dashboard

EOF
)"
```

---

### Task 4: PROGRESS note + verify

**Files:**
- Modify: `accounts/modules/addons/ms365backup/Docs/PROGRESS.md`

- [ ] **Step 1: Re-run PHP test**

Run: `php accounts/modules/addons/ms365backup/tests/ms365_fleet_repo_ops_test.php`  
Expected: `ALL TESTS PASSED`

- [ ] **Step 2: Add PROGRESS.md session bullet**

Prepend under Session log:

```markdown
### 2026-08-03 — Fleet Dashboard compaction visibility

- **Ship:** Dashboard panel “Repo operations / compaction” with active strip, recent table, enqueue (`maintenance_*` / `retention_apply`); API `fleet_repo_ops` / `fleet_repo_ops_enqueue`; `Ms365FleetRepoOpsService` (local DB only).
- **Verify:** `ms365_fleet_repo_ops_test.php` PASS; Fleet Dashboard shows phase/claimed node for running ops.
```

- [ ] **Step 3: Commit** (only if requested)

```bash
git add accounts/modules/addons/ms365backup/Docs/PROGRESS.md \
  accounts/modules/addons/ms365backup/Docs/specs/2026-08-03-fleet-compaction-visibility-design.md \
  accounts/modules/addons/ms365backup/Docs/plans/2026-08-03-fleet-compaction-visibility.md
git commit -m "$(cat <<'EOF'
docs(ms365): fleet compaction visibility spec, plan, progress

EOF
)"
```

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Dashboard placement before Recent audit | Task 3 |
| Active + recent + enqueue | Tasks 1–3 |
| Local DB only / no fleet_remote | Global + Task 1–2 |
| OpRow summary fields | Task 1 `mapOpRow` |
| e3_job_id from prior payload | Task 1 `enqueue` |
| Unique enqueue token | Task 1 |
| 15s poll with dashboard | Task 3 |
| Env-only muted note + e3_job_id helper | Task 3 |
| PHP tests | Task 1 |
| PROGRESS | Task 4 |

## Placeholder / consistency self-review

- No TBD/TODO left in steps.
- Method names consistent: `listForFleet`, `enqueue`, `fleet_repo_ops`, `fleet_repo_ops_enqueue`, `renderRepoOps`.
- Commit steps gated on user request (repo commit policy).
