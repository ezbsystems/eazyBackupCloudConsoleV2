#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Step 3 diagnostic: verify module encryption keys and stored credential decrypt status.
 *
 * Usage:
 *   php diagnose_access_key_encryption.php --module-keys
 *   php diagnose_access_key_encryption.php --bucket=e3ms365-abc123
 *   php diagnose_access_key_encryption.php --user-id=42
 *   php diagnose_access_key_encryption.php --all-backup-owners
 *   php diagnose_access_key_encryption.php --all-ms365
 *   php diagnose_access_key_encryption.php --all-ms365 --client-id=123
 */

require_once __DIR__ . '/bootstrap.php';

use WHMCS\Module\Addon\CloudStorage\Client\AccessKeyEncryptionService;

function usage(): void
{
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php diagnose_access_key_encryption.php --module-keys\n");
    fwrite(STDERR, "  php diagnose_access_key_encryption.php --bucket=NAME\n");
    fwrite(STDERR, "  php diagnose_access_key_encryption.php --user-id=ID\n");
    fwrite(STDERR, "  php diagnose_access_key_encryption.php --all-backup-owners\n");
    fwrite(STDERR, "  php diagnose_access_key_encryption.php --all-ms365 [--client-id=ID]\n");
    exit(1);
}

function parseArgs(array $argv): array
{
    $opts = [
        'module_keys' => false,
        'bucket' => '',
        'user_id' => 0,
        'all_backup_owners' => false,
        'all_ms365' => false,
        'client_id' => 0,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--module-keys') {
            $opts['module_keys'] = true;
            continue;
        }
        if ($arg === '--all-backup-owners') {
            $opts['all_backup_owners'] = true;
            continue;
        }
        if ($arg === '--all-ms365') {
            $opts['all_ms365'] = true;
            continue;
        }
        if (strpos($arg, '--bucket=') === 0) {
            $opts['bucket'] = substr($arg, 9);
            continue;
        }
        if (strpos($arg, '--user-id=') === 0) {
            $opts['user_id'] = (int) substr($arg, 10);
            continue;
        }
        if (strpos($arg, '--client-id=') === 0) {
            $opts['client_id'] = (int) substr($arg, 12);
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            usage();
        }
    }

    return $opts;
}

$opts = parseArgs($argv);
$selected = (int) $opts['module_keys']
    + (int) ($opts['bucket'] !== '')
    + (int) ($opts['user_id'] > 0)
    + (int) $opts['all_backup_owners']
    + (int) $opts['all_ms365'];

if ($selected !== 1) {
    usage();
}

if ($opts['module_keys']) {
    $report = AccessKeyEncryptionService::diagnoseModuleKeys();
} elseif ($opts['bucket'] !== '') {
    $report = AccessKeyEncryptionService::diagnoseBucket($opts['bucket']);
} elseif ($opts['user_id'] > 0) {
    $report = AccessKeyEncryptionService::diagnoseUserKey($opts['user_id']);
} elseif ($opts['all_backup_owners']) {
    $report = AccessKeyEncryptionService::diagnoseBackupOwners();
} else {
    $clientId = $opts['client_id'] > 0 ? $opts['client_id'] : null;
    $report = AccessKeyEncryptionService::diagnoseMs365BucketOwners($clientId);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (($report['status'] ?? '') === 'fail' || !empty($report['summary']['cannot_decrypt'])) {
    exit(2);
}

if (!empty($report['needs_reencrypt']) || !empty($report['summary']['needs_reencrypt'])) {
    exit(1);
}

exit(0);
