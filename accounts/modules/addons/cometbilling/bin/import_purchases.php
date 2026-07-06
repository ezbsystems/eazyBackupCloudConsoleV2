<?php
/**
 * Import Comet purchase history from a CSV export.
 *
 * Usage:
 *   php import_purchases.php /path/to/purchases.csv
 *   php import_purchases.php --dry-run /path/to/purchases.csv
 */

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

use CometBilling\PurchaseCsvImporter;

$dryRun = false;
$csvPath = null;

foreach ($argv as $index => $arg) {
    if ($index === 0) {
        continue;
    }
    if ($arg === '--dry-run' || $arg === '-n') {
        $dryRun = true;
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage: php import_purchases.php [--dry-run] /path/to/purchases.csv\n";
        exit(0);
    }
    $csvPath = $arg;
}

if ($csvPath === null) {
    fwrite(STDERR, "Error: CSV file path is required.\n");
    fwrite(STDERR, "Usage: php import_purchases.php [--dry-run] /path/to/purchases.csv\n");
    exit(1);
}

if (!is_readable($csvPath)) {
    fwrite(STDERR, "Error: Cannot read CSV file: {$csvPath}\n");
    exit(1);
}

function logImport(string $msg): void
{
    echo '[import_purchases] ' . date('Y-m-d H:i:s') . " - {$msg}\n";
}

try {
    if ($dryRun) {
        logImport('Dry run — no database changes will be made.');
    }

    logImport('Importing from ' . $csvPath);
    $result = PurchaseCsvImporter::import($csvPath, $dryRun);

    logImport(
        'Imported ' . $result['imported']
        . ' purchases, skipped ' . $result['skipped']
        . ' duplicates, created ' . $result['lots'] . ' credit lots.'
    );

    if (!empty($result['errors'])) {
        logImport('Errors:');
        foreach ($result['errors'] as $error) {
            logImport('  ' . $error);
        }
        exit(2);
    }

    exit(0);
} catch (\Throwable $e) {
    logImport('FATAL ERROR: ' . $e->getMessage());
    exit(2);
}
