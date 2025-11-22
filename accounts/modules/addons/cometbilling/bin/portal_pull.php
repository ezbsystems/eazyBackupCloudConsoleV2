<?php

$root = dirname(__DIR__, 4); // up to WHMCS root (accounts/)
if (!defined('WHMCS')) {
    require_once $root . '/init.php';
}

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

// Fallback autoloader when composer isn't available
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

use WHMCS\Database\Capsule;
use CometBilling\PortalClient;
use CometBilling\UsageNormalizer;
use CometBilling\ActiveServicesNormalizer;

if (!class_exists(PortalClient::class)) {
    echo "[cometbilling] Missing dependencies. Please run composer install in modules/addons/cometbilling.\n";
    if (defined('COMETBILLING_INLINE')) { return; }
    exit(3);
}

// Load addon config
$settings = Capsule::table('tbladdonmodules')
    ->where('module', 'cometbilling')->pluck('value', 'setting');

$base = $settings['PortalBaseUrl'] ?? 'https://account.cometbackup.com';
$auth = $settings['PortalAuthType'] ?? 'token';
$tok  = $settings['PortalToken'] ?? '';

if (!$tok) {
    echo "[cometbilling] No PortalToken configured; aborting.\n";
    if (defined('COMETBILLING_INLINE')) {
        return;
    }
    exit(2);
}

$timeout = (int)($settings['HttpTimeoutSeconds'] ?? 180);
if ($timeout < 60) { $timeout = 60; }
$client = new PortalClient($base, $auth, $tok, $timeout);

/** 1) Billing History → cb_credit_usage */
// allow script to run sufficiently long
if (function_exists('set_time_limit')) { @set_time_limit($timeout + 60); }
ini_set('default_socket_timeout', (string)$timeout);

$usage = $client->reportBillingHistory();
$insU  = 0; $skU = 0;

foreach ($usage as $row) {
    if (!is_array($row)) { continue; }
    $n = UsageNormalizer::normalizeRow($row);
    if (!$n['usage_date']) continue;

    $exists = Capsule::table('cb_credit_usage')
        ->where('row_fingerprint', $n['row_fingerprint'])->exists();

    if ($exists) { $skU++; continue; }

    Capsule::table('cb_credit_usage')->insert([
        'usage_date'      => $n['usage_date'],
        'posted_at'       => $n['posted_at'],
        'tenant_id'       => $n['tenant_id'],
        'device_id'       => $n['device_id'],
        'item_type'       => $n['item_type'],
        'item_desc'       => $n['item_desc'],
        'quantity'        => $n['quantity'],
        'unit_cost'       => $n['unit_cost'],
        'amount'          => $n['amount'],
        'packs_used'      => $n['packs_used'],
        'raw_row'         => json_encode($n['raw_row']),
        'row_fingerprint' => $n['row_fingerprint'],
        'created_at'      => date('Y-m-d H:i:s'),
    ]);
    $insU++;
}

echo "[cometbilling] Usage rows: inserted=$insU skipped=$skU\n";

/** 2) Active Services → cb_active_services (snapshot) */
$services = $client->reportActiveServices();
$pulledAt = gmdate('Y-m-d H:i:s');
$insS = 0; $skS = 0;

foreach ($services as $row) {
    if (!is_array($row)) { continue; }
    $n = ActiveServicesNormalizer::normalizeRow($row, $pulledAt);
    $exists = Capsule::table('cb_active_services')
        ->where('pulled_at', $pulledAt)
        ->where('row_fingerprint', $n['row_fingerprint'])
        ->exists();

    if ($exists) { $skS++; continue; }

    Capsule::table('cb_active_services')->insert([
        'pulled_at'          => $n['pulled_at'],
        'service_name'       => $n['service_name'],
        'billing_cycle_days' => $n['billing_cycle_days'],
        'next_due_date'      => $n['next_due_date'],
        'unit_cost'          => $n['unit_cost'],
        'quantity'           => $n['quantity'],
        'amount'             => $n['amount'],
        'tenant_id'          => $n['tenant_id'],
        'device_id'          => $n['device_id'],
        'extra'              => json_encode($n['extra']),
        'row_fingerprint'    => $n['row_fingerprint'],
    ]);
    $insS++;
}

echo "[cometbilling] Active services snapshot: inserted=$insS skipped=$skS\n";

/** 3) Recompute daily balance roll-forward (simple recompute of last 120 days) */
$start = date('Y-m-d', strtotime('-120 days'));
$end   = date('Y-m-d');

$dates = new DatePeriod(
    new DateTime($start),
    new DateInterval('P1D'),
    (new DateTime($end))->modify('+1 day')
);

$running = 0.0;
foreach ($dates as $d) {
    $day = $d->format('Y-m-d');
    $purchases = (float) (Capsule::table('cb_credit_purchases')
        ->whereDate('purchased_at', $day)
        ->sum(Capsule::raw('credit_amount + bonus_credit')));
    $usageAmt = (float) (Capsule::table('cb_credit_usage')
        ->whereDate('usage_date', $day)
        ->sum('amount'));

    $opening = $running;
    $closing = $opening + $purchases - $usageAmt;
    $running = $closing;

    Capsule::table('cb_daily_balance')->updateOrInsert(
        ['balance_date' => $day],
        [
            'opening_credit'   => number_format($opening, 4, '.', ''),
            'purchases_credit' => number_format($purchases, 4, '.', ''),
            'usage_amount'     => number_format($usageAmt, 4, '.', ''),
            'closing_credit'   => number_format($closing, 4, '.', ''),
            'recomputed_at'    => date('Y-m-d H:i:s')
        ]
    );
}

echo "[cometbilling] Balance recompute complete through $end\n";


