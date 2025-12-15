<?php

require_once __DIR__ . '/../../lib/Admin/AdminOps.php';
require_once __DIR__ . '/../../lib/Admin/DeprovisionHelper.php';
require_once __DIR__ . '/../../lib/Admin/ProductConfig.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\AdminOps;
use WHMCS\Module\Addon\CloudStorage\Admin\DeprovisionHelper;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;

/**
 * Admin Reconciliation Page
 *
 * Goal:
 * - List RGW buckets that do not map to any WHMCS e3 Storage service
 * - List RGW bucket owners (users) that do not map to any WHMCS e3 Storage service
 * - Handle tenant-qualified owners (<tenant>$<uid>)
 */
function cloudstorage_admin_reconcile($vars)
{
    // AJAX actions (module-specific param to avoid collisions)
    if (isset($_REQUEST['cs_action'])) {
        header('Content-Type: application/json');
        try {
            $action = $_REQUEST['cs_action'] ?? '';
            if ($action === 'scan') {
                echo json_encode(cloudstorage_reconcile_scan($vars, $_REQUEST));
                exit;
            }
            echo json_encode(['status' => 'fail', 'message' => 'Unknown action.']);
        } catch (\Throwable $e) {
            try { logModuleCall('cloudstorage', 'admin_reconcile_ajax_fatal', ['cs_action' => $_REQUEST['cs_action'] ?? null], $e->getMessage()); } catch (\Throwable $_) {}
            http_response_code(500);
            echo json_encode(['status' => 'fail', 'message' => 'Internal error: ' . $e->getMessage()]);
        }
        exit;
    }

    $csrfToken = function_exists('generate_token') ? generate_token('plain') : '';
    cloudstorage_reconcile_render($csrfToken);
}

/**
 * Normalize an RGW owner string.
 * - If owner contains "$", treat as tenant-qualified and extract base username after "$"
 */
function cloudstorage_reconcile_normalize_owner(string $owner): array
{
    $owner = trim($owner);
    $base = $owner;
    $tenant = null;

    if (strpos($owner, '$') !== false) {
        $parts = explode('$', $owner, 2);
        $tenant = $parts[0] !== '' ? $parts[0] : null;
        $base = $parts[1] ?? $owner;
    }

    return [
        'owner_raw' => $owner,
        'owner_base' => $base,
        'tenant_prefix' => $tenant,
    ];
}

/**
 * Get the WHMCS Product ID used for Cloud Storage services.
 * Prefer addon setting pid_cloud_storage if set, else fallback to ProductConfig::$E3_PRODUCT_ID.
 */
function cloudstorage_reconcile_get_storage_pid(): int
{
    try {
        $pid = (int) (Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', 'pid_cloud_storage')
            ->value('value') ?? 0);
        if ($pid > 0) {
            return $pid;
        }
    } catch (\Throwable $e) {
        // ignore
    }
    return (int) (ProductConfig::$E3_PRODUCT_ID ?? 0);
}

/**
 * Build a username->service mapping for WHMCS Cloud Storage services.
 */
function cloudstorage_reconcile_get_services(int $pid, bool $activeOnly = true): array
{
    $q = Capsule::table('tblhosting')
        ->select(['id', 'userid', 'username', 'domainstatus', 'packageid'])
        ->where('packageid', $pid);

    if ($activeOnly) {
        $q->where('domainstatus', 'Active');
    }

    $rows = $q->get();
    $map = [];
    foreach ($rows as $r) {
        if (!is_string($r->username) || $r->username === '') {
            continue;
        }
        // Prefer Active, else highest ID wins
        if (!isset($map[$r->username])) {
            $map[$r->username] = $r;
            continue;
        }
        $existing = $map[$r->username];
        $existingActive = ($existing->domainstatus ?? '') === 'Active';
        $newActive = ($r->domainstatus ?? '') === 'Active';
        if ($newActive && !$existingActive) {
            $map[$r->username] = $r;
        } elseif ($newActive === $existingActive && (int)$r->id > (int)$existing->id) {
            $map[$r->username] = $r;
        }
    }
    return $map;
}

/**
 * Perform reconciliation scan (live RGW -> WHMCS services).
 */
function cloudstorage_reconcile_scan($vars, array $request): array
{
    // CSRF (plain token)
    $token = $request['token'] ?? '';
    if (function_exists('check_token')) {
        if (!check_token('plain', $token)) {
            return ['status' => 'fail', 'message' => 'Invalid security token. Please refresh and try again.'];
        }
    }

    $activeOnly = !isset($request['include_non_active']) || $request['include_non_active'] !== '1';

    $endpoint = (string) ($vars['s3_endpoint'] ?? '');
    $adminAccessKey = (string) ($vars['ceph_access_key'] ?? '');
    $adminSecretKey = (string) ($vars['ceph_secret_key'] ?? '');

    if ($endpoint === '' || $adminAccessKey === '' || $adminSecretKey === '') {
        return ['status' => 'fail', 'message' => 'Module configuration missing: s3_endpoint / ceph_access_key / ceph_secret_key.'];
    }

    $pid = cloudstorage_reconcile_get_storage_pid();
    if ($pid <= 0) {
        return ['status' => 'fail', 'message' => 'Could not determine Cloud Storage Product ID (pid_cloud_storage / ProductConfig).'];
    }

    // Live RGW buckets
    $bucketRes = AdminOps::getBucketInfo($endpoint, $adminAccessKey, $adminSecretKey, ['stats' => true]);
    if (!is_array($bucketRes) || ($bucketRes['status'] ?? '') !== 'success') {
        return ['status' => 'fail', 'message' => 'Failed to fetch RGW buckets via AdminOps.', 'detail' => $bucketRes];
    }

    $rgwBucketsRaw = $bucketRes['data'] ?? [];
    if (!is_array($rgwBucketsRaw)) {
        $rgwBucketsRaw = [];
    }

    // WHMCS services mapping (username -> service)
    $serviceMap = cloudstorage_reconcile_get_services($pid, $activeOnly);

    // Build s3_users username -> primary username mapping (for tenant/sub-tenant ownership)
    $usernameById = [];
    $parentIdById = [];
    try {
        $rows = Capsule::table('s3_users')->select(['id', 'username', 'parent_id'])->get();
        foreach ($rows as $r) {
            $id = (int) ($r->id ?? 0);
            if ($id <= 0) { continue; }
            $usernameById[$id] = (string) ($r->username ?? '');
            $parentIdById[$id] = isset($r->parent_id) ? (int) $r->parent_id : null;
        }
    } catch (\Throwable $e) {
        // If s3_users is unavailable, we still reconcile using owner_base only.
        $usernameById = [];
        $parentIdById = [];
    }

    $primaryByUsername = [];
    if (!empty($usernameById)) {
        // Build a username->primaryUsername map by walking parent_id to root
        $idByUsername = [];
        foreach ($usernameById as $id => $uname) {
            if ($uname !== '') { $idByUsername[$uname] = $id; }
        }

        $resolvePrimaryForId = function (int $id) use (&$parentIdById): int {
            $seen = 0;
            $cur = $id;
            while ($seen < 8) {
                $seen++;
                $pid = $parentIdById[$cur] ?? null;
                if (empty($pid)) { break; }
                $pid = (int) $pid;
                if ($pid <= 0 || $pid === $cur) { break; }
                $cur = $pid;
            }
            return $cur;
        };

        foreach ($idByUsername as $uname => $id) {
            $rootId = $resolvePrimaryForId((int)$id);
            $primary = $usernameById[$rootId] ?? $uname;
            $primaryByUsername[$uname] = $primary;
        }
    }

    // Build results
    $orphBuckets = [];
    $ownerAgg = []; // owner_raw -> {owner_base, bucket_count, buckets[]}
    $rgwBucketCount = 0;

    foreach ($rgwBucketsRaw as $b) {
        if (!is_array($b) || !isset($b['bucket'])) {
            continue;
        }

        $bucketName = (string) $b['bucket'];
        $ownerRaw = (string) ($b['owner'] ?? 'Unknown');
        $norm = cloudstorage_reconcile_normalize_owner($ownerRaw);
        $ownerBase = $norm['owner_base'];

        $rgwBucketCount++;

        // Protected flags
        $bucketProtected = DeprovisionHelper::isProtectedBucket($bucketName);
        $ownerProtected = DeprovisionHelper::isProtectedUsername($ownerRaw) || DeprovisionHelper::isProtectedUsername($ownerBase);

        // Resolve owner to a primary username when possible (tenant/sub-tenant -> primary)
        $mappedPrimary = $primaryByUsername[$ownerBase] ?? $ownerBase;

        // Determine WHMCS service match:
        // - direct match on owner_base
        // - or match via mapped primary (parent) username
        $service = $serviceMap[$ownerBase] ?? ($serviceMap[$mappedPrimary] ?? null);
        $hasService = $service !== null;

        if (!$hasService) {
            $orphBuckets[] = [
                'bucket' => $bucketName,
                'owner_raw' => $ownerRaw,
                'owner_base' => $ownerBase,
                'mapped_primary' => $mappedPrimary,
                'tenant_prefix' => $norm['tenant_prefix'],
                'protected_bucket' => $bucketProtected,
                'protected_owner' => $ownerProtected,
            ];
        }

        // Track owners for owner reconciliation (only owners that have at least one bucket)
        // Aggregate by owner_base so that the same user isn't counted multiple times across different tenant prefixes.
        $ownerKey = $ownerBase;
        if (!isset($ownerAgg[$ownerKey])) {
            $ownerAgg[$ownerKey] = [
                'owner_base' => $ownerBase,
                'mapped_primary' => $mappedPrimary,
                'tenant_prefix_sample' => $norm['tenant_prefix'],
                'owner_raw_samples' => [],
                'bucket_count' => 0,
                'buckets' => [],
                'protected_owner' => $ownerProtected,
                'has_whmcs_service' => $hasService,
                'whmcs_service' => $hasService ? [
                    'id' => (int) $service->id,
                    'userid' => (int) $service->userid,
                    'domainstatus' => (string) $service->domainstatus,
                    'packageid' => (int) $service->packageid,
                ] : null,
            ];
        }
        // Store up to 3 raw owner variants seen for this base owner
        if (count($ownerAgg[$ownerKey]['owner_raw_samples']) < 3) {
            $ownerAgg[$ownerKey]['owner_raw_samples'][] = $ownerRaw;
        }
        $ownerAgg[$ownerKey]['bucket_count']++;
        if (count($ownerAgg[$ownerKey]['buckets']) < 10) {
            $ownerAgg[$ownerKey]['buckets'][] = $bucketName;
        }
    }

    // Owners with no WHMCS service
    $orphOwners = [];
    foreach ($ownerAgg as $o) {
        if (!$o['has_whmcs_service']) {
            $orphOwners[] = $o;
        }
    }

    // WHMCS services with no buckets in RGW (optional helpful list)
    $rgwOwnerBases = [];
    foreach ($ownerAgg as $o) {
        $rgwOwnerBases[$o['owner_base']] = true;
    }
    $servicesNoBuckets = [];
    foreach ($serviceMap as $uname => $svc) {
        if (!isset($rgwOwnerBases[$uname])) {
            $servicesNoBuckets[] = [
                'username' => $uname,
                'service_id' => (int) $svc->id,
                'client_id' => (int) $svc->userid,
                'domainstatus' => (string) $svc->domainstatus,
            ];
        }
    }

    return [
        'status' => 'success',
        'active_only' => $activeOnly,
        'product_id' => $pid,
        'stats' => [
            'rgw_bucket_count' => $rgwBucketCount,
            'rgw_unique_owners' => count($ownerAgg),
            'whmcs_service_count' => count($serviceMap),
            'orphan_bucket_count' => count($orphBuckets),
            'orphan_owner_count' => count($orphOwners),
            'whmcs_services_without_buckets' => count($servicesNoBuckets),
        ],
        'orphan_buckets' => $orphBuckets,
        'orphan_owners' => $orphOwners,
        'whmcs_services_without_buckets' => $servicesNoBuckets,
    ];
}

function cloudstorage_reconcile_render(string $csrfToken): void
{
    $moduleUrl = $_SERVER['PHP_SELF'] . '?module=cloudstorage&action=reconcile';
    $tokenPlain = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');

    echo <<<HTML
<style>
  .eb-recon-muted { color:#6c757d; }
  .eb-recon-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, \"Liberation Mono\", \"Courier New\", monospace; }
  .eb-recon-table td { vertical-align: middle; }
  .eb-pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; border:1px solid rgba(0,0,0,.15); }
  .eb-pill-ok { background:#d1e7dd; color:#0f5132; border-color:#badbcc; }
  .eb-pill-bad { background:#f8d7da; color:#842029; border-color:#f5c2c7; }
  .eb-pill-warn { background:#fff3cd; color:#664d03; border-color:#ffecb5; }
</style>

<div class="container-fluid mt-4">
  <div class="row mb-4">
    <div class="col">
      <div class="card">
        <div class="card-body">
          <h2 class="card-title mb-1"><i class="fa fa-exchange"></i> Reconciliation</h2>
          <div class="eb-recon-muted">
            Compare live Ceph RGW buckets/owners with WHMCS e3 Object Storage services to find orphaned resources.
            This handles tenant owners in the form <span class="eb-recon-mono">&lt;tenant&gt;$&lt;username&gt;</span>.
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><i class="fa fa-sliders"></i> Scan Options</div>
        <div class="card-body">
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="includeNonActive" value="1">
            <label class="form-check-label" for="includeNonActive">
              Include non-active WHMCS services (Cancelled/Terminated/Suspended). Default is Active-only.
            </label>
          </div>
          <button class="btn btn-primary" id="runScanBtn"><i class="fa fa-play"></i> Run Reconciliation Scan</button>
          <span class="eb-recon-muted ms-2" id="scanStatus"></span>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4" id="summaryRow" style="display:none;">
    <div class="col">
      <div class="card">
        <div class="card-header"><i class="fa fa-bar-chart"></i> Summary</div>
        <div class="card-body">
          <div class="row" id="summaryCards"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4" id="orphanBucketsRow" style="display:none;">
    <div class="col">
      <div class="card">
        <div class="card-header"><i class="fa fa-archive"></i> RGW Buckets With No Matching WHMCS Service</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped table-hover eb-recon-table">
              <thead>
                <tr>
                  <th>Bucket</th>
                  <th>Owner (raw)</th>
                  <th>Owner (base)</th>
                  <th>Mapped Primary</th>
                  <th>Protected</th>
                </tr>
              </thead>
              <tbody id="orphanBucketsBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4" id="orphanOwnersRow" style="display:none;">
    <div class="col">
      <div class="card">
        <div class="card-header"><i class="fa fa-user"></i> RGW Bucket Owners With No Matching WHMCS Service</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped table-hover eb-recon-table">
              <thead>
                <tr>
                  <th>Owner (base)</th>
                  <th>Mapped Primary</th>
                  <th>Owner (raw samples)</th>
                  <th>Bucket count</th>
                  <th>Sample buckets</th>
                  <th>Protected</th>
                </tr>
              </thead>
              <tbody id="orphanOwnersBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4" id="servicesNoBucketsRow" style="display:none;">
    <div class="col">
      <div class="card">
        <div class="card-header"><i class="fa fa-warning"></i> WHMCS Services With No RGW Buckets (FYI)</div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped table-hover eb-recon-table">
              <thead>
                <tr>
                  <th>Service ID</th>
                  <th>Client ID</th>
                  <th>Username</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody id="servicesNoBucketsBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var moduleUrl = '{$moduleUrl}';
  var tokenPlain = '{$tokenPlain}';

  function pill(text, kind) {
    var cls = 'eb-pill ' + (kind === 'ok' ? 'eb-pill-ok' : (kind === 'warn' ? 'eb-pill-warn' : 'eb-pill-bad'));
    return '<span class=\"' + cls + '\">' + escapeHtml(text) + '</span>';
  }

  function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
  }

  function setStatus(msg) {
    var el = document.getElementById('scanStatus');
    if (el) el.textContent = msg || '';
  }

  function renderSummary(stats, activeOnly, productId) {
    var cards = [
      { label: 'RGW Buckets', value: stats.rgw_bucket_count },
      { label: 'RGW Owners', value: stats.rgw_unique_owners },
      { label: 'WHMCS Services', value: stats.whmcs_service_count },
      { label: 'Orphan Buckets', value: stats.orphan_bucket_count },
      { label: 'Orphan Owners', value: stats.orphan_owner_count },
      { label: 'WHMCS Services w/o Buckets', value: stats.whmcs_services_without_buckets }
    ];
    var html = '';
    cards.forEach(function(c) {
      html += '<div class=\"col-md-2\"><div class=\"card\"><div class=\"card-body\">' +
        '<div class=\"eb-recon-muted\" style=\"font-size:12px;\">' + escapeHtml(c.label) + '</div>' +
        '<div style=\"font-size:20px;font-weight:600;\">' + escapeHtml(c.value) + '</div>' +
      '</div></div></div>';
    });
    html += '<div class=\"col-12 mt-3 eb-recon-muted\">' +
      'Mode: <strong>' + (activeOnly ? 'Active-only' : 'All statuses') + '</strong>' +
      ' | Product ID: <strong>' + escapeHtml(productId) + '</strong>' +
      '</div>';
    document.getElementById('summaryCards').innerHTML = html;
    document.getElementById('summaryRow').style.display = '';
  }

  function renderOrphanBuckets(rows) {
    var body = document.getElementById('orphanBucketsBody');
    if (!body) return;
    if (!rows || rows.length === 0) {
      body.innerHTML = '<tr><td colspan=\"5\" class=\"eb-recon-muted\">No orphan buckets found.</td></tr>';
      return;
    }
    var html = '';
    rows.forEach(function(r) {
      var prot = [];
      if (r.protected_bucket) prot.push('bucket');
      if (r.protected_owner) prot.push('owner');
      var protCell = prot.length ? pill('PROTECTED: ' + prot.join(','), 'warn') : pill('No', 'ok');
      html += '<tr>' +
        '<td class=\"eb-recon-mono\">' + escapeHtml(r.bucket) + '</td>' +
        '<td class=\"eb-recon-mono\">' + escapeHtml(r.owner_raw) + '</td>' +
        '<td class=\"eb-recon-mono\">' + escapeHtml(r.owner_base) + '</td>' +
        '<td class=\"eb-recon-mono\">' + escapeHtml(r.mapped_primary || r.owner_base) + '</td>' +
        '<td>' + protCell + '</td>' +
      '</tr>';
    });
    body.innerHTML = html;
    document.getElementById('orphanBucketsRow').style.display = '';
  }

  function renderOrphanOwners(rows) {
    var body = document.getElementById('orphanOwnersBody');
    if (!body) return;
    if (!rows || rows.length === 0) {
      body.innerHTML = '<tr><td colspan=\"6\" class=\"eb-recon-muted\">No orphan owners found.</td></tr>';
      return;
    }
    // Sort by bucket_count desc
    rows.sort(function(a,b){ return (b.bucket_count||0) - (a.bucket_count||0); });
    var html = '';
    rows.forEach(function(r) {
      var protCell = r.protected_owner ? pill('PROTECTED', 'warn') : pill('No', 'ok');
      html += '<tr>' +
        '<td class=\"eb-recon-mono\">' + escapeHtml(r.owner_base) + '</td>' +
        '<td class=\"eb-recon-mono\">' + escapeHtml(r.mapped_primary || r.owner_base) + '</td>' +
        '<td class=\"eb-recon-mono\">' + escapeHtml((r.owner_raw_samples || []).join(', ')) + '</td>' +
        '<td>' + escapeHtml(r.bucket_count) + '</td>' +
        '<td class=\"eb-recon-mono\">' + escapeHtml((r.buckets || []).join(', ')) + '</td>' +
        '<td>' + protCell + '</td>' +
      '</tr>';
    });
    body.innerHTML = html;
    document.getElementById('orphanOwnersRow').style.display = '';
  }

  function renderServicesNoBuckets(rows) {
    var body = document.getElementById('servicesNoBucketsBody');
    if (!body) return;
    if (!rows || rows.length === 0) {
      body.innerHTML = '<tr><td colspan=\"4\" class=\"eb-recon-muted\">None.</td></tr>';
      return;
    }
    var html = '';
    rows.forEach(function(r) {
      html += '<tr>' +
        '<td>' + escapeHtml(r.service_id) + '</td>' +
        '<td>' + escapeHtml(r.client_id) + '</td>' +
        '<td class=\"eb-recon-mono\">' + escapeHtml(r.username) + '</td>' +
        '<td>' + escapeHtml(r.domainstatus) + '</td>' +
      '</tr>';
    });
    body.innerHTML = html;
    document.getElementById('servicesNoBucketsRow').style.display = '';
  }

  function runScan() {
    document.getElementById('summaryRow').style.display = 'none';
    document.getElementById('orphanBucketsRow').style.display = 'none';
    document.getElementById('orphanOwnersRow').style.display = 'none';
    document.getElementById('servicesNoBucketsRow').style.display = 'none';

    setStatus('Scanning RGW and WHMCS…');
    var btn = document.getElementById('runScanBtn');
    btn.disabled = true;

    var includeNonActive = document.getElementById('includeNonActive').checked ? '1' : '0';

    var params = new URLSearchParams();
    params.append('cs_action', 'scan');
    params.append('token', tokenPlain);
    if (includeNonActive === '1') params.append('include_non_active', '1');

    fetch(moduleUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params.toString()
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
      btn.disabled = false;
      if (!data || data.status !== 'success') {
        setStatus('');
        alert((data && data.message) ? data.message : 'Scan failed.');
        return;
      }
      setStatus('Scan complete.');
      renderSummary(data.stats, data.active_only, data.product_id);
      renderOrphanBuckets(data.orphan_buckets);
      renderOrphanOwners(data.orphan_owners);
      renderServicesNoBuckets(data.whmcs_services_without_buckets);
    })
    .catch(function(err){
      btn.disabled = false;
      setStatus('');
      alert('Scan error: ' + err.message);
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('runScanBtn');
    if (btn) btn.addEventListener('click', runScan);
  });
})();
</script>
HTML;
}


