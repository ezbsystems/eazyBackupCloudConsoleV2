<?php
/**
 * Debug helper: evaluate Bill History rows for a device and write NDJSON debug logs.
 *
 * Usage:
 *   php bin/debug_eval_device.php --device=32789ae06f748489ff6e0c6352e8fada6513915f --from=2026-08-01 --to=2026-08-07
 */

$root = dirname(__DIR__, 4);
if (!defined('WHMCS')) {
    require_once $root . '/init.php';
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

spl_autoload_register(function ($class) {
    $prefix = 'CometBilling\\';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative = substr($class, $len);
    $file = __DIR__ . '/../lib/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use CometBilling\BillingCadenceResolver;
use CometBilling\CanonicalUsage;
use CometBilling\LifecycleResolver;
use CometBilling\OverbillEvidenceEvaluator;
use CometBilling\ReversalIndex;
use CometBilling\ServiceIdentityResolver;
use WHMCS\Database\Capsule;

$device = null;
$from = '2026-08-01';
$to = date('Y-m-d');
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--device=')) {
        $device = substr($arg, strlen('--device='));
    } elseif (str_starts_with($arg, '--from=')) {
        $from = substr($arg, strlen('--from='));
    } elseif (str_starts_with($arg, '--to=')) {
        $to = substr($arg, strlen('--to='));
    }
}

if ($device === null || $device === '') {
    fwrite(STDERR, "Missing --device=\n");
    exit(2);
}

echo "[debug_eval_device] device={$device} range={$from}..{$to}\n";

ServiceIdentityResolver::loadIndex();
BillingCadenceResolver::clearCache();
LifecycleResolver::clearCache();
ReversalIndex::warm($from, $to);

$dev = Capsule::table('comet_devices')->where('hash', 'like', $device . '%')->orWhere('hash', $device)->first();
echo '[debug_eval_device] comet_devices=' . json_encode($dev ? [
    'hash' => $dev->hash,
    'name' => $dev->name,
    'is_active' => $dev->is_active,
    'revoked_at' => $dev->revoked_at,
] : null) . "\n";

$inv = Capsule::table('cb_server_device_inventory')
    ->where('device_id', 'like', substr($device, 0, 12) . '%')
    ->orderBy('snapshot_date')
    ->get(['snapshot_date', 'device_id', 'hyperv_vms']);
echo "[debug_eval_device] inventory_rows=" . count($inv) . "\n";
foreach ($inv as $row) {
    echo "  {$row->snapshot_date} hv={$row->hyperv_vms} id={$row->device_id}\n";
}

$query = CanonicalUsage::query()
    ->whereBetween('usage_date', [$from, $to])
    ->where(function ($q) use ($device) {
        $q->where('device_id', 'like', substr($device, 0, 12) . '%')
            ->orWhere('device_id', $device);
    })
    ->orderBy('usage_date');

$rows = $query->get();
$rows = is_array($rows) ? $rows : $rows->all();
echo '[debug_eval_device] usage_rows=' . count($rows) . "\n";

foreach ($rows as $row) {
    $evaluated = OverbillEvidenceEvaluator::evaluate($row, false);
    echo sprintf(
        "  %s %s verdict=%s billing=%s end=%s revoked=%s remove=%s as_qty=%s cycle=%s\n",
        $evaluated['usage_date'],
        $evaluated['item_desc'],
        $evaluated['verdict'],
        $evaluated['billing_verdict'],
        $evaluated['expected_billing_end'] ?? 'null',
        $evaluated['revoked_at'] ?? 'null',
        $evaluated['evidence']['lifecycle']['remove_date'] ?? 'null',
        $evaluated['evidence']['cadence']['service_quantity'] ?? 'null',
        $evaluated['cycle']
    );
}

echo "[debug_eval_device] done — logs at /var/www/eazybackup.ca/.cursor/debug-d2324a.log\n";
