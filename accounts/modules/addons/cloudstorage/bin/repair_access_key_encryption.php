#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Step 4 self-heal: re-encrypt stored credentials under the canonical module key.
 *
 * Usage:
 *   php repair_access_key_encryption.php --user-id=42 [--dry-run]
 *   php repair_access_key_encryption.php --all-backup-owners [--dry-run]
 *   php repair_access_key_encryption.php --all-ms365 [--client-id=123] [--dry-run]
 */

require_once __DIR__ . '/bootstrap.php';

use WHMCS\Module\Addon\CloudStorage\Client\AccessKeyEncryptionService;

function usage(): void
{
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php repair_access_key_encryption.php --user-id=ID [--dry-run]\n");
    fwrite(STDERR, "  php repair_access_key_encryption.php --all-backup-owners [--dry-run]\n");
    fwrite(STDERR, "  php repair_access_key_encryption.php --all-ms365 [--client-id=ID] [--dry-run]\n");
    exit(1);
}

function parseArgs(array $argv): array
{
    $opts = [
        'user_id' => 0,
        'all_backup_owners' => false,
        'all_ms365' => false,
        'client_id' => 0,
        'dry_run' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--all-backup-owners') {
            $opts['all_backup_owners'] = true;
            continue;
        }
        if ($arg === '--all-ms365') {
            $opts['all_ms365'] = true;
            continue;
        }
        if ($arg === '--dry-run') {
            $opts['dry_run'] = true;
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
$selected = (int) ($opts['user_id'] > 0)
    + (int) $opts['all_backup_owners']
    + (int) $opts['all_ms365'];

if ($selected !== 1) {
    usage();
}

$dryRun = (bool) $opts['dry_run'];

if ($opts['user_id'] > 0) {
    $report = AccessKeyEncryptionService::normalizeStoredAccessKeyEncryption($opts['user_id'], $dryRun);
} elseif ($opts['all_backup_owners']) {
    $report = AccessKeyEncryptionService::normalizeBackupOwners($dryRun);
} else {
    $clientId = $opts['client_id'] > 0 ? $opts['client_id'] : null;
    $report = AccessKeyEncryptionService::normalizeMs365BucketOwners($clientId, $dryRun);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

$summary = $report['summary'] ?? null;
if (is_array($summary) && !empty($summary['failed'])) {
    exit(2);
}

if (($report['status'] ?? '') === 'fail') {
    exit(2);
}

exit(0);
