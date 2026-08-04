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

use CometBilling\CreditLedgerRebuilder;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$from = null;
$to = null;

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $from = substr($arg, 7);
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    }
}

$coverage = CreditLedgerRebuilder::purchaseCoverage();
$opening = $from ?? $coverage['earliest'] ?? date('Y-m-d', strtotime('-365 days'));
$closing = $to ?? date('Y-m-d');

echo "Rebuilding ledger {$opening} to {$closing}" . ($dryRun ? ' (dry-run)' : '') . "\n";

$result = CreditLedgerRebuilder::rebuild($opening, $closing, $dryRun);
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
