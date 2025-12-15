<?php
/**
 * Backfill configurable option group links for already-created white-label products.
 *
 * Why: older tenants were created before we attached required config option groups to the cloned product.
 * WHMCS uses tblproductconfiglinks (pid<->gid) to associate configurable option groups to products.
 *
 * Usage:
 *   php whitelabel_backfill_configoptions.php --dry-run
 *   php whitelabel_backfill_configoptions.php
 *   php whitelabel_backfill_configoptions.php --tenant-id=17 --dry-run
 *   php whitelabel_backfill_configoptions.php --product-id=100
 *   php whitelabel_backfill_configoptions.php --limit=50 --dry-run
 *
 * Options:
 *   --dry-run                 Don't write to DB; print planned changes.
 *   --tenant-id=<id[,id..]>   Only process specific eb_whitelabel_tenants.id values.
 *   --product-id=<id[,id..]>  Only process specific tblproducts.id values.
 *   --limit=<n>               Limit number of rows processed (default: 0 = no limit).
 *   --cids=<csv>              Override required cids (default: 67,88,91,97,99,102).
 */

declare(strict_types=1);

use EazyBackup\Whitelabel\WhmcsOps;
use WHMCS\Database\Capsule;

// ---- Helpers ---------------------------------------------------------------

function ebwl_arg(string $name, array $argv): ?string {
    foreach ($argv as $a) {
        if (strpos($a, $name . '=') === 0) {
            return substr($a, strlen($name) + 1);
        }
        if ($a === $name) {
            return '1';
        }
    }
    return null;
}

function ebwl_csv_ints(?string $csv): array {
    if ($csv === null || trim($csv) === '') return [];
    $out = [];
    foreach (preg_split('/\s*,\s*/', trim($csv)) as $p) {
        $n = (int)$p;
        if ($n > 0) $out[$n] = true;
    }
    return array_keys($out);
}

function ebwl_stderr(string $msg): void {
    fwrite(STDERR, $msg . PHP_EOL);
}

// ---- Bootstrap WHMCS -------------------------------------------------------

$init = '/var/www/eazybackup.ca/accounts/init.php';
if (!file_exists($init)) {
    ebwl_stderr("ERROR: WHMCS init.php not found at {$init}");
    exit(2);
}
require_once $init;

// Ensure our class is loadable even if addon autoloader is not in CLI context
$opsFile = '/var/www/eazybackup.ca/accounts/modules/addons/eazybackup/lib/Whitelabel/WhmcsOps.php';
if (file_exists($opsFile)) {
    require_once $opsFile;
}

// ---- Parse args ------------------------------------------------------------

$dryRun = ebwl_arg('--dry-run', $argv) === '1';
$limit = (int)(ebwl_arg('--limit', $argv) ?? 0);
$tenantIds = ebwl_csv_ints(ebwl_arg('--tenant-id', $argv));
$productIds = ebwl_csv_ints(ebwl_arg('--product-id', $argv));
$cids = ebwl_csv_ints(ebwl_arg('--cids', $argv));
if (empty($cids)) {
    $cids = [67, 88, 91, 97, 99, 102];
}

// ---- Resolve worklist ------------------------------------------------------

$work = [];

if (!empty($productIds)) {
    foreach ($productIds as $pid) {
        $work[] = [
            'tenant_id' => null,
            'client_id' => null,
            'fqdn' => null,
            'product_id' => (int)$pid,
        ];
    }
} else {
    $q = Capsule::table('eb_whitelabel_tenants')
        ->select(['id as tenant_id', 'client_id', 'fqdn', 'product_id'])
        ->where('product_id', '>', 0)
        ->orderBy('id', 'asc');

    if (!empty($tenantIds)) {
        $q->whereIn('id', $tenantIds);
    }

    if ($limit > 0) {
        $q->limit($limit);
    }

    foreach ($q->get() as $r) {
        $work[] = [
            'tenant_id' => isset($r->tenant_id) ? (int)$r->tenant_id : null,
            'client_id' => isset($r->client_id) ? (int)$r->client_id : null,
            'fqdn' => isset($r->fqdn) ? (string)$r->fqdn : null,
            'product_id' => isset($r->product_id) ? (int)$r->product_id : 0,
        ];
    }
}

if (empty($work)) {
    ebwl_stderr("No matching products/tenants found.");
    exit(0);
}

// ---- Precompute gid mapping for cids --------------------------------------

$cidRows = Capsule::table('tblproductconfigoptions')
    ->select(['id', 'gid'])
    ->whereIn('id', $cids)
    ->get();
$cidToGid = [];
$requiredGids = [];
foreach ($cidRows as $r) {
    $cid = (int)($r->id ?? 0);
    $gid = (int)($r->gid ?? 0);
    if ($cid > 0 && $gid > 0) {
        $cidToGid[$cid] = $gid;
        $requiredGids[$gid] = true;
    }
}
$requiredGids = array_keys($requiredGids);
sort($requiredGids);

$missingCids = [];
foreach ($cids as $cid) {
    if (!isset($cidToGid[$cid])) $missingCids[] = (int)$cid;
}
if (!empty($missingCids)) {
    ebwl_stderr("WARNING: Some cids were not found in tblproductconfigoptions: " . implode(',', $missingCids));
}

// ---- Execute ---------------------------------------------------------------

$ops = new WhmcsOps();

ebwl_stderr("Backfill required cids: " . implode(',', $cids));
ebwl_stderr("Required gids resolved: " . implode(',', $requiredGids));
ebwl_stderr($dryRun ? "Mode: DRY RUN (no writes)" : "Mode: APPLY (writes to tblproductconfiglinks)");

$changed = 0;
$skipped = 0;
$errors = 0;

foreach ($work as $w) {
    $pid = (int)($w['product_id'] ?? 0);
    if ($pid <= 0) {
        $skipped++;
        continue;
    }

    // Ensure product exists
    $exists = Capsule::table('tblproducts')->where('id', $pid)->exists();
    if (!$exists) {
        ebwl_stderr("SKIP pid={$pid}: product not found");
        $skipped++;
        continue;
    }

    if ($dryRun) {
        $existing = Capsule::table('tblproductconfiglinks')
            ->where('pid', $pid)
            ->pluck('gid')
            ->all();
        $existingSet = [];
        foreach ($existing as $gid) $existingSet[(int)$gid] = true;
        $toAdd = [];
        foreach ($requiredGids as $gid) {
            if (!isset($existingSet[(int)$gid])) $toAdd[] = (int)$gid;
        }
        $label = "pid={$pid}";
        if (!empty($w['tenant_id'])) $label .= " tenant_id=" . (int)$w['tenant_id'];
        if (!empty($w['fqdn'])) $label .= " fqdn=" . (string)$w['fqdn'];

        if (empty($toAdd)) {
            ebwl_stderr("OK   {$label}: already linked");
        } else {
            ebwl_stderr("PLAN {$label}: add gids [" . implode(',', $toAdd) . "]");
            $changed++;
        }
        continue;
    }

    try {
        $res = $ops->attachConfigOptionsToProductByCid($pid, $cids);
        $ok = (bool)($res['ok'] ?? false);
        if ($ok) {
            $changed++;
            $label = "pid={$pid}";
            if (!empty($w['tenant_id'])) $label .= " tenant_id=" . (int)$w['tenant_id'];
            if (!empty($w['fqdn'])) $label .= " fqdn=" . (string)$w['fqdn'];
            $lg = $res['linked_gids'] ?? [];
            ebwl_stderr("APPLY {$label}: linked_gids=[" . implode(',', array_map('intval', (array)$lg)) . "]");
        } else {
            ebwl_stderr("ERROR pid={$pid}: attach failed");
            $errors++;
        }
    } catch (\Throwable $e) {
        ebwl_stderr("ERROR pid={$pid}: " . $e->getMessage());
        $errors++;
    }
}

ebwl_stderr("Done. processed=" . count($work) . " changed={$changed} skipped={$skipped} errors={$errors}");
exit($errors > 0 ? 1 : 0);


