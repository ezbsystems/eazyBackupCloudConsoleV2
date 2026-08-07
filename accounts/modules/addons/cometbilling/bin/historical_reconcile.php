<?php

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

use CometBilling\HistoricalReconciler;
use CometBilling\Settings;

$logLines = [];
$exitCode = 0;

function cbLog(string $msg): void
{
    global $logLines;
    $line = '[historical_reconcile] ' . $msg;
    $logLines[] = $line;
    echo $line . "\n";
}

function cbFinish(int $code = 0): void
{
    global $logLines, $exitCode;
    $exitCode = $code;

    Settings::markJobFinished(
        'historical_reconcile',
        $code === 0 ? 'ok' : 'error',
        implode("\n", array_slice($logLines, -30))
    );

    if (defined('COMETBILLING_INLINE')) {
        return;
    }
    exit($code);
}

$from = null;
$to = null;
$includeGrace = false;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
    } elseif (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    } elseif ($arg === '--include-grace') {
        $includeGrace = true;
    }
}

if ($from === null || $to === null) {
    $params = Settings::getHistoricalReconcileJobParams();
    if ($params['from'] !== '' && $params['to'] !== '') {
        $from = $params['from'];
        $to = $params['to'];
        $includeGrace = $params['include_grace'];
    }
}

if ($from === null || $to === null) {
    cbLog('Missing --from= and --to= (or job params in cb_settings).');
    cbFinish(2);
}

$started = microtime(true);
cbLog("Starting audit {$from} → {$to}" . ($includeGrace ? ' (include grace)' : ''));

try {
    $report = HistoricalReconciler::report($from, $to, $includeGrace, true);
    $elapsed = (int) round(microtime(true) - $started);
    cbLog(sprintf(
        'Done in %ds — scanned %d, confirmed %d ($%.2f), run #%s',
        $elapsed,
        (int) ($report['summary']['charges_scanned'] ?? 0),
        (int) ($report['summary']['confirmed_count'] ?? 0),
        (float) ($report['summary']['confirmed_amount'] ?? 0),
        (string) ($report['audit_run_id'] ?? '?')
    ));
    cbFinish(0);
} catch (\Throwable $e) {
    cbLog('ERROR: ' . $e->getMessage());
    cbFinish(1);
}
