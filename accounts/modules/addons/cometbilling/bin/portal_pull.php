<?php

$root = dirname(__DIR__, 4); // up to WHMCS root (accounts/)
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

use WHMCS\Database\Capsule;
use CometBilling\PortalClient;
use CometBilling\UsageNormalizer;
use CometBilling\ActiveServicesNormalizer;
use CometBilling\Settings;
use CometBilling\CreditLedger;

$logLines = [];
$exitCode = 0;
$phaseTimings = [];

function cbPhaseStart(string $name): void
{
    global $phaseTimings;
    $phaseTimings[$name] = ['start' => microtime(true)];
}

function cbPhaseEnd(string $name, array $extra = []): void
{
    global $phaseTimings;
    $start = $phaseTimings[$name]['start'] ?? microtime(true);
    $phaseTimings[$name]['ms'] = (int) round((microtime(true) - $start) * 1000);
}

function cbLog(string $msg): void
{
    global $logLines;
    $line = '[cometbilling] ' . $msg;
    $logLines[] = $line;
    echo $line . "\n";
}

function cbFinish(int $code = 0): void
{
    global $logLines, $exitCode;
    $exitCode = $code;

    Settings::markJobFinished(
        'portal_pull',
        $code === 0 ? 'ok' : 'error',
        implode("\n", array_slice($logLines, -20))
    );

    if (defined('COMETBILLING_INLINE')) {
        return;
    }
    exit($code);
}

if (!class_exists(PortalClient::class)) {
    cbLog('Missing dependencies. Please run composer install in modules/addons/cometbilling.');
    cbFinish(3);
}

Settings::markJobRunning('portal_pull');

$config = Settings::getPortalConfig();
if (empty($config['token'])) {
    cbLog('No PortalToken configured; aborting.');
    cbFinish(2);
}

$timeout = $config['timeout'];
if (function_exists('set_time_limit')) {
    @set_time_limit($timeout + 120);
}
ini_set('default_socket_timeout', (string) $timeout);

try {
    $client = new PortalClient($config['baseUrl'], $config['authType'], $config['token'], $timeout);
} catch (\Throwable $e) {
    cbLog('Failed to initialize portal client: ' . $e->getMessage());
    cbFinish(1);
}

/** 1) Billing History → cb_credit_usage (bulk INSERT IGNORE) */
cbPhaseStart('billing_history_api');
try {
    $usage = $client->reportBillingHistory();
} catch (\Throwable $e) {
    cbLog('Billing history pull failed: ' . $e->getMessage());
    if (function_exists('logActivity')) {
        logActivity('[CometBilling] Portal billing history pull failed: ' . $e->getMessage());
    }
    cbFinish(1);
}
cbPhaseEnd('billing_history_api', ['rows' => is_countable($usage) ? count($usage) : 0]);

cbPhaseStart('billing_history_insert');
$insU = 0;
$batch = [];
$batchSize = 500;
$now = date('Y-m-d H:i:s');

foreach ($usage as $row) {
    if (!is_array($row)) {
        continue;
    }
    $n = UsageNormalizer::normalizeRow($row);
    if (!$n['usage_date']) {
        continue;
    }

    $batch[] = [
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
        'created_at'      => $now,
    ];

    if (count($batch) >= $batchSize) {
        $insU += cbBulkInsertIgnore('cb_credit_usage', $batch);
        $batch = [];
    }
}

if (!empty($batch)) {
    $insU += cbBulkInsertIgnore('cb_credit_usage', $batch);
}

cbLog("Usage rows: inserted={$insU}");
cbPhaseEnd('billing_history_insert', ['inserted' => $insU]);
Settings::setKv('last_billing_history_pull', gmdate('Y-m-d H:i:s'));

/** 2) Active Services → cb_active_services (bulk INSERT IGNORE) */
cbPhaseStart('active_services_api');
try {
    $services = $client->reportActiveServices();
} catch (\Throwable $e) {
    cbLog('Active services pull failed: ' . $e->getMessage());
    if (function_exists('logActivity')) {
        logActivity('[CometBilling] Portal active services pull failed: ' . $e->getMessage());
    }
    cbFinish(1);
}
cbPhaseEnd('active_services_api', ['rows' => is_countable($services) ? count($services) : 0]);

cbPhaseStart('active_services_insert');
$pulledAt = gmdate('Y-m-d H:i:s');
$insS = 0;
$svcBatch = [];

foreach ($services as $row) {
    if (!is_array($row)) {
        continue;
    }
    $n = ActiveServicesNormalizer::normalizeRow($row, $pulledAt);

    $svcBatch[] = [
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
    ];

    if (count($svcBatch) >= $batchSize) {
        $insS += cbBulkInsertIgnore('cb_active_services', $svcBatch);
        $svcBatch = [];
    }
}

if (!empty($svcBatch)) {
    $insS += cbBulkInsertIgnore('cb_active_services', $svcBatch);
}

cbLog("Active services snapshot: inserted={$insS} pulled_at={$pulledAt}");
cbPhaseEnd('active_services_insert', ['inserted' => $insS]);
Settings::setKv('last_active_services_pull', $pulledAt);

/** 3) Incremental daily balance roll-forward */
cbPhaseStart('balance_recompute');
$end = date('Y-m-d');
$lastBalanceDate = Settings::getKv('last_balance_recompute_date');
$lastBalRow = Capsule::table('cb_daily_balance')->orderBy('balance_date', 'desc')->first();

if ($lastBalanceDate && $lastBalRow) {
    $start = date('Y-m-d', strtotime($lastBalanceDate . ' +1 day'));
    $running = (float) $lastBalRow->closing_credit;
} elseif ($lastBalRow) {
    $start = date('Y-m-d', strtotime($lastBalRow->balance_date . ' +1 day'));
    $running = (float) $lastBalRow->closing_credit;
} else {
    // First run: seed from day before window or compute opening from history
    $start = date('Y-m-d', strtotime('-120 days'));
    $priorRow = Capsule::table('cb_daily_balance')
        ->where('balance_date', '<', $start)
        ->orderBy('balance_date', 'desc')
        ->first();

    if ($priorRow) {
        $running = (float) $priorRow->closing_credit;
    } else {
        $running = cbComputeOpeningBalance($start);
    }
}

if (strtotime($start) > strtotime($end)) {
    cbLog("Balance already up to date through {$end}");
} else {
    $dates = new DatePeriod(
        new DateTime($start),
        new DateInterval('P1D'),
        (new DateTime($end))->modify('+1 day')
    );

    foreach ($dates as $d) {
        $day = $d->format('Y-m-d');
        $purchases = (float) Capsule::table('cb_credit_purchases')
            ->whereDate('purchased_at', $day)
            ->sum(Capsule::raw('credit_amount + bonus_credit'));
        $usageAmt = (float) Capsule::table('cb_credit_usage')
            ->whereDate('usage_date', $day)
            ->sum('amount');

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
                'recomputed_at'    => date('Y-m-d H:i:s'),
            ]
        );

        // FIFO allocation for this day's usage
        if ($usageAmt > 0) {
            $alloc = CreditLedger::allocateUsage($usageAmt, $day, 'Portal billing usage');
            if (!empty($alloc['allocated']) && empty($alloc['skipped'])) {
                cbLog("FIFO allocated \${$alloc['allocated']} for {$day}");
            }
        }
    }

    Settings::setKv('last_balance_recompute_date', $end);
    cbLog("Balance recompute complete through {$end}");
}
cbPhaseEnd('balance_recompute');

cbFinish(0);

/**
 * Bulk INSERT IGNORE helper (no full-table COUNT scans).
 */
function cbBulkInsertIgnore(string $table, array $rows): int
{
    if (empty($rows)) {
        return 0;
    }

    $inserted = 0;
    foreach (array_chunk($rows, 500) as $chunk) {
        // insertOrIgnore returns affected-row count via affectingStatement
        $inserted += (int) Capsule::table($table)->insertOrIgnore($chunk);
    }

    return $inserted;
}

/**
 * Compute opening balance before a date from full purchase/usage history.
 */
function cbComputeOpeningBalance(string $beforeDate): float
{
    $purchases = (float) Capsule::table('cb_credit_purchases')
        ->where('purchased_at', '<', $beforeDate . ' 00:00:00')
        ->sum(Capsule::raw('credit_amount + bonus_credit'));
    $usage = (float) Capsule::table('cb_credit_usage')
        ->where('usage_date', '<', $beforeDate)
        ->sum('amount');

    return $purchases - $usage;
}
