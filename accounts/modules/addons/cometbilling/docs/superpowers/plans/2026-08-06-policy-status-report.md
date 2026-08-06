# Policy Status Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Comet Billing admin **Policy Status** report that lists accounts on two fixed CSW Policy IDs whose last backup is warning, then shows billed Active Services accounts in that set with warning or error last status.

**Architecture:** Pure helpers in `PolicyStatusReport` aggregate worst `LastBackupJob` status per username and classify Section A/B. Live scan uses `ServerUsageCollector::openServer()` + `AdminListUsersFull()` for both CSWs and joins the latest `cb_active_services` snapshot by normalized account name. Admin UI mirrors Period Compare (session release, summary + tables).

**Tech Stack:** PHP 8.x, Comet PHP SDK (`\Comet\Server`), WHMCS Capsule, standalone `php tests/*.php` tests.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-08-06-policy-status-report-design.md`
- Fixed Policy IDs: `obc` → `9005920f-fa54-4a22-8844-533bda81da4c`; `cometbackup` → `0e545d31-e0b3-4b38-8456-0999fa46f588`
- Account row = one Comet username; worst status across Sources with a LastBackupJob
- Section A = warning only (7001); Section B = billed + warning or error family
- No CSV, no PolicyID picker, no cron cache in v1
- Do not edit the Cursor plan file under `.cursor/plans/`

---

### Task 1: Status helpers + unit tests

**Files:**
- Create: `accounts/modules/addons/cometbilling/lib/PolicyStatusReport.php`
- Create: `accounts/modules/addons/cometbilling/tests/PolicyStatusReportTest.php`

**Interfaces:**
- Produces: `PolicyStatusReport::POLICY_MAP` (serverKey → policyId)
- Produces: `PolicyStatusReport::severityRank(int $status): int`
- Produces: `PolicyStatusReport::statusLabel(int $status): string`
- Produces: `PolicyStatusReport::isWarning(int $status): bool`
- Produces: `PolicyStatusReport::isWarningOrError(int $status): bool`
- Produces: `PolicyStatusReport::aggregateAccountFromSources(array $sources): ?array`
- Produces: `PolicyStatusReport::normalizeAccountKey(string $name): string`
- Produces: `PolicyStatusReport::buildSections(array $accounts, array $billedByAccount): array`

- [ ] **Step 1: Write failing tests**

```php
<?php
require_once __DIR__ . '/../lib/PolicyStatusReport.php';
use CometBilling\PolicyStatusReport;

function assertEq($e, $a, string $m): void {
    if ($e != $a) { fwrite(STDERR, "FAIL $m: expected ".var_export($e,true)." got ".var_export($a,true)."\n"); exit(1); }
    echo "PASS: $m\n";
}

assertEq(3, PolicyStatusReport::severityRank(7001), 'warning rank');
assertEq(4, PolicyStatusReport::severityRank(7002), 'error rank');
assertEq(true, PolicyStatusReport::isWarning(7001), 'is warning');
assertEq(false, PolicyStatusReport::isWarning(7002), 'error not warning-only');
assertEq(true, PolicyStatusReport::isWarningOrError(7001), 'warning in warn/error');
assertEq(true, PolicyStatusReport::isWarningOrError(7002), 'error in warn/error');
assertEq(false, PolicyStatusReport::isWarningOrError(5000), 'success excluded');

$agg = PolicyStatusReport::aggregateAccountFromSources([
    ['status' => 5000, 'end_time' => 100, 'source_id' => 'a'],
    ['status' => 7001, 'end_time' => 90, 'source_id' => 'b'],
    ['status' => 7002, 'end_time' => 80, 'source_id' => 'c'],
]);
assertEq(7002, $agg['status'], 'worst status wins');
assertEq(1, $agg['warning_source_count'], 'one warning source');
assertEq(1, $agg['error_source_count'], 'one error source');

assertEq('acme', PolicyStatusReport::normalizeAccountKey(' Acme '), 'normalize account');

$accounts = [
    ['server_key'=>'obc','policy_id'=>'9005920f-fa54-4a22-8844-533bda81da4c','username'=>'WarnOnly','status'=>7001,'last_job_time'=>1,'source_count'=>1,'warning_source_count'=>1,'error_source_count'=>0],
    ['server_key'=>'obc','policy_id'=>'9005920f-fa54-4a22-8844-533bda81da4c','username'=>'ErrBilled','status'=>7002,'last_job_time'=>2,'source_count'=>1,'warning_source_count'=>0,'error_source_count'=>1],
    ['server_key'=>'cometbackup','policy_id'=>'0e545d31-e0b3-4b38-8456-0999fa46f588','username'=>'Ok','status'=>5000,'last_job_time'=>3,'source_count'=>1,'warning_source_count'=>0,'error_source_count'=>0],
];
$billed = [
    'errbilled' => ['categories'=>['devices'],'amount'=>2.0,'line_count'=>1],
];
$sections = PolicyStatusReport::buildSections($accounts, $billed);
assertEq(1, count($sections['warning_accounts']), 'section A one warning');
assertEq('WarnOnly', $sections['warning_accounts'][0]['username'], 'section A username');
assertEq(1, count($sections['billed_unhealthy']), 'section B one billed unhealthy');
assertEq('ErrBilled', $sections['billed_unhealthy'][0]['username'], 'section B username');

echo "All PolicyStatusReport tests passed.\n";
```

- [ ] **Step 2: Run RED**

Run: `cd accounts/modules/addons/cometbilling && php tests/PolicyStatusReportTest.php`  
Expected: FAIL (class missing)

- [ ] **Step 3: Implement helpers in `PolicyStatusReport.php`**

```php
<?php
namespace CometBilling;

final class PolicyStatusReport
{
    public const POLICY_MAP = [
        'obc' => '9005920f-fa54-4a22-8844-533bda81da4c',
        'cometbackup' => '0e545d31-e0b3-4b38-8456-0999fa46f588',
    ];

    public const SERVER_LABELS = [
        'obc' => 'csw.obcbackup.com',
        'cometbackup' => 'csw.eazybackup.ca',
    ];

    public static function severityRank(int $status): int
    {
        if ($status === 5000) return 0;
        if ($status >= 6000 && $status <= 6002) return 1;
        if ($status === 7004) return 2;
        if ($status === 7001) return 3;
        if (in_array($status, [7000, 7002, 7003, 7005, 7006, 7007], true)) return 4;
        return -1;
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            5000 => 'success',
            6000, 6001, 6002 => 'running',
            7000 => 'timeout',
            7001 => 'warning',
            7002 => 'error',
            7003 => 'quota',
            7004 => 'missed',
            7005 => 'cancelled',
            7006 => 'already_running',
            7007 => 'abandoned',
            default => 'unknown',
        };
    }

    public static function isWarning(int $status): bool
    {
        return $status === 7001;
    }

    public static function isWarningOrError(int $status): bool
    {
        return self::severityRank($status) >= 3;
    }

    public static function normalizeAccountKey(string $name): string
    {
        return strtolower(trim($name));
    }

    /** @param list<array{status:int,end_time?:int,start_time?:int,source_id?:string}> $sources */
    public static function aggregateAccountFromSources(array $sources): ?array
    {
        $best = null;
        $warn = 0;
        $err = 0;
        $withJob = 0;
        foreach ($sources as $src) {
            $status = (int) ($src['status'] ?? 0);
            if ($status <= 0) {
                continue;
            }
            $withJob++;
            if ($status === 7001) {
                $warn++;
            }
            if (self::severityRank($status) === 4) {
                $err++;
            }
            $end = (int) ($src['end_time'] ?? $src['start_time'] ?? 0);
            if ($best === null
                || self::severityRank($status) > self::severityRank((int) $best['status'])
                || (self::severityRank($status) === self::severityRank((int) $best['status']) && $end > (int) $best['last_job_time'])
            ) {
                $best = [
                    'status' => $status,
                    'last_job_time' => $end,
                    'source_id' => $src['source_id'] ?? null,
                ];
            }
        }
        if ($best === null) {
            return null;
        }
        return [
            'status' => $best['status'],
            'status_label' => self::statusLabel((int) $best['status']),
            'last_job_time' => $best['last_job_time'],
            'source_count' => $withJob,
            'warning_source_count' => $warn,
            'error_source_count' => $err,
        ];
    }

    /**
     * @param list<array<string,mixed>> $accounts
     * @param array<string, array{categories: list<string>, amount: float, line_count: int}> $billedByAccount
     */
    public static function buildSections(array $accounts, array $billedByAccount): array
    {
        $warning = [];
        $billedUnhealthy = [];
        foreach ($accounts as $acct) {
            $status = (int) ($acct['status'] ?? 0);
            if (self::isWarning($status)) {
                $warning[] = $acct;
            }
            $key = self::normalizeAccountKey((string) ($acct['username'] ?? ''));
            if ($key !== '' && isset($billedByAccount[$key]) && self::isWarningOrError($status)) {
                $billedUnhealthy[] = $acct + [
                    'billed_categories' => $billedByAccount[$key]['categories'],
                    'billed_amount' => $billedByAccount[$key]['amount'],
                    'billed_line_count' => $billedByAccount[$key]['line_count'],
                ];
            }
        }
        return [
            'warning_accounts' => $warning,
            'billed_unhealthy' => $billedUnhealthy,
        ];
    }
}
```

- [ ] **Step 4: Run GREEN**

Run: `php tests/PolicyStatusReportTest.php`  
Expected: `All PolicyStatusReport tests passed.`

- [ ] **Step 5: Commit**

```bash
git add accounts/modules/addons/cometbilling/lib/PolicyStatusReport.php \
  accounts/modules/addons/cometbilling/tests/PolicyStatusReportTest.php
git commit -m "Add PolicyStatusReport status helpers and section membership tests."
```

---

### Task 2: Expose Comet server client + live scan + Active Services index

**Files:**
- Modify: `accounts/modules/addons/cometbilling/lib/ServerUsageCollector.php` (make server open public)
- Modify: `accounts/modules/addons/cometbilling/lib/PolicyStatusReport.php`
- Modify: `accounts/modules/addons/cometbilling/tests/PolicyStatusReportTest.php`

**Interfaces:**
- Produces: `ServerUsageCollector::openServer(string $serverKey): ?\Comet\Server`
- Produces: `PolicyStatusReport::indexLatestActiveServicesByAccount(): array`
- Produces: `PolicyStatusReport::report(): array` (live scan)

- [ ] **Step 1: Add public `openServer`**

In `ServerUsageCollector`, add:

```php
public static function openServer(string $serverKey): ?\Comet\Server
{
    return self::getCometServer($serverKey);
}
```

- [ ] **Step 2: Implement Active Services account index**

```php
public static function indexLatestActiveServicesByAccount(): array
{
    $latest = Capsule::table('cb_active_services')->max('pulled_at');
    if (!$latest) {
        return ['pulled_at' => null, 'by_account' => []];
    }
    $rows = Capsule::table('cb_active_services')->where('pulled_at', $latest)->get();
    $by = [];
    foreach ($rows as $row) {
        $account = trim((string) ($row->tenant_id ?? ''));
        if ($account === '') {
            $sn = (string) ($row->service_name ?? '');
            if (preg_match('/Account\s+([^\-]+)/i', $sn, $m)) {
                $account = trim($m[1]);
            }
        }
        if ($account === '') {
            continue;
        }
        $key = self::normalizeAccountKey($account);
        if (!isset($by[$key])) {
            $by[$key] = ['categories' => [], 'amount' => 0.0, 'line_count' => 0, 'display_name' => $account];
        }
        $cat = ChargeCategoryResolver::fromServiceName((string) ($row->service_name ?? ''));
        if (!in_array($cat, $by[$key]['categories'], true)) {
            $by[$key]['categories'][] = $cat;
        }
        $by[$key]['amount'] += (float) ($row->amount ?? 0);
        $by[$key]['line_count']++;
    }
    return ['pulled_at' => $latest, 'by_account' => $by];
}
```

- [ ] **Step 3: Implement `report()` live scan**

For each `POLICY_MAP` entry: `openServer($key)`; if null, record server error and continue. Call `AdminListUsersFull()`. For each profile where `(string)$profile->PolicyID === $policyId`, collect source last jobs:

```php
$sources = [];
foreach (($profile->Sources ?? []) as $sourceId => $source) {
    $job = $source->Statistics->LastBackupJob ?? null;
    if ($job === null) continue;
    $status = (int) ($job->Status ?? 0);
    if ($status <= 0) continue;
    $sources[] = [
        'status' => $status,
        'end_time' => (int) ($job->EndTime ?? 0),
        'start_time' => (int) ($job->StartTime ?? 0),
        'source_id' => (string) $sourceId,
    ];
}
$agg = self::aggregateAccountFromSources($sources);
if ($agg === null) continue;
$accounts[] = [
    'server_key' => $key,
    'server_label' => self::SERVER_LABELS[$key] ?? $key,
    'policy_id' => $policyId,
    'username' => (string) $profile->Username,
    'status' => $agg['status'],
    'status_label' => $agg['status_label'],
    'last_job_time' => $agg['last_job_time'],
    'source_count' => $agg['source_count'],
    'warning_source_count' => $agg['warning_source_count'],
    'error_source_count' => $agg['error_source_count'],
];
```

Then `$as = self::indexLatestActiveServicesByAccount();` `$sections = self::buildSections($accounts, $as['by_account']);` return sections + `active_services_pulled_at` + `server_errors` + counts.

Handle profile objects that may be arrays (SDK sometimes returns associative arrays): normalize Username/PolicyID/Sources via property or array access.

- [ ] **Step 4: Add a pure unit test for empty AS index shape** (optional offline mock of `buildSections` already covered). No live CSW in CI.

- [ ] **Step 5: Commit**

```bash
git add accounts/modules/addons/cometbilling/lib/ServerUsageCollector.php \
  accounts/modules/addons/cometbilling/lib/PolicyStatusReport.php \
  accounts/modules/addons/cometbilling/tests/PolicyStatusReportTest.php
git commit -m "Wire PolicyStatusReport live CSW scan and Active Services account index."
```

---

### Task 3: Admin UI + nav + README

**Files:**
- Create: `accounts/modules/addons/cometbilling/templates/admin/policy_status.tpl.php`
- Modify: `accounts/modules/addons/cometbilling/cometbilling.php` (nav + `case 'policy_status'`)
- Modify: `accounts/modules/addons/cometbilling/README.md`

**Interfaces:**
- Consumes: `PolicyStatusReport::report(): array`

- [ ] **Step 1: Add nav + action**

In nav (near Period Compare / Active Services):

```php
. '<a href="'.$baseUrl.'&action=policy_status" class="btn btn-default">Policy Status</a> '
```

```php
case 'policy_status':
    cometbilling_releaseSession();
    if (function_exists('set_time_limit')) {
        @set_time_limit(600);
    }
    include __DIR__ . '/templates/admin/policy_status.tpl.php';
    break;
```

- [ ] **Step 2: Template**

Call `$report = PolicyStatusReport::report();`. Show:

1. Intro + configured Policy IDs / servers
2. Summary cards: warning count, billed unhealthy count, AS snapshot time, any server errors
3. Section A table: Server, Policy ID, Username, Sources (warn/total), Last job (UTC from unix), Status
4. Section B table: same + Billed categories + Billed amount

Format `last_job_time` with `gmdate('Y-m-d H:i:s', $ts)` when `> 0`, else `—`.

Style with the same `cb-box` / `cb-stat` patterns as `period_compare.tpl.php`.

- [ ] **Step 3: README bullet** under Admin UI Pages:

`- **Policy Status**: Accounts on configured CSW Policy IDs with last backup warning; cross-ref Active Services for billed warning/error accounts`

- [ ] **Step 4: Syntax check**

Run: `php -l lib/PolicyStatusReport.php && php tests/PolicyStatusReportTest.php`

- [ ] **Step 5: Commit**

```bash
git add accounts/modules/addons/cometbilling/templates/admin/policy_status.tpl.php \
  accounts/modules/addons/cometbilling/cometbilling.php \
  accounts/modules/addons/cometbilling/README.md
git commit -m "Add Policy Status admin page for PolicyID warning and billed unhealthy accounts."
```

---

### Task 4: Deploy and spot-check production

**Files:** deploy only the touched cometbilling files above.

- [ ] **Step 1: Push commits** (`git push origin main`)
- [ ] **Step 2: rsync** `PolicyStatusReport.php`, `policy_status.tpl.php`, `PolicyStatusReportTest.php`, `ServerUsageCollector.php`, `cometbilling.php`, `README.md` to prod under `accounts/modules/addons/cometbilling/`; `chown www-data`
- [ ] **Step 3: Run** `php tests/PolicyStatusReportTest.php` on prod
- [ ] **Step 4: Spot-check** open Comet Billing → Policy Status; confirm both servers return data or a clear connection error; Section A only warnings; Section B only AS-matched warning/error accounts

---

## Spec coverage

| Spec requirement | Task |
|------------------|------|
| Fixed PolicyIDs / two CSWs | 1–2 |
| Account-level worst LastBackupJob | 1–2 |
| Section A warning-only | 1 |
| Section B billed + warning/error | 1–2 |
| Live AdminListUsersFull | 2 |
| Latest Active Services join | 2 |
| Admin nav + page | 3 |
| Tests without live CSW | 1 |
| Deploy | 4 |

## Placeholder scan

No TBD/TODO left; signatures and Policy IDs are concrete.
