<?php

declare(strict_types=1);

/**
 * Vault list endpoints must allow MS365-capable clients with backup users
 * but no e3 Cloud Backup (PID 104) WHMCS product username.
 *
 * Run: php accounts/modules/addons/cloudstorage/tests/e3backup_vault_list_gate_test.php
 */

$failures = 0;

function vault_gate_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$message}\n";
        ++$failures;
        return;
    }
    echo "OK: {$message}\n";
}

$vaultApis = [
    'e3backup_vault_list.php',
    'ms365_vault_list.php',
    'ms365_vault_request_early_delete.php',
];
$apiDir = __DIR__ . '/../api';

foreach ($vaultApis as $file) {
    $path = $apiDir . '/' . $file;
    $src = file_get_contents($path);
    vault_gate_assert($src !== false, "{$file} is readable");
    if ($src === false) {
        continue;
    }
    vault_gate_assert(
        str_contains($src, "require_once __DIR__ . '/../lib/Client/E3BackupAccess.php'"),
        "{$file} requires E3BackupAccess"
    );
    vault_gate_assert(
        str_contains($src, 'E3BackupAccess::clientHasE3BackupAccess($clientId)'),
        "{$file} gates on clientHasE3BackupAccess"
    );
    vault_gate_assert(
        !preg_match('/ProductConfig::e3CloudBackupPid\(\)/', $src),
        "{$file} no longer gates on e3CloudBackupPid only"
    );
    vault_gate_assert(
        !preg_match('/DBController::getProduct\(\$clientId,\s*\$packageId\)/', $src),
        "{$file} no longer requires e3 product username"
    );
}

$accessPath = __DIR__ . '/../lib/Client/E3BackupAccess.php';
$accessSrc = file_get_contents($accessPath);
vault_gate_assert($accessSrc !== false, 'E3BackupAccess.php is readable');
if ($accessSrc !== false) {
    vault_gate_assert(
        str_contains($accessSrc, "Capsule::table('s3_backup_users')"),
        'E3BackupAccess grants access via s3_backup_users fallback'
    );
    vault_gate_assert(
        str_contains($accessSrc, 'clientHasE3BackupAccess'),
        'E3BackupAccess exposes clientHasE3BackupAccess helper'
    );
}

$vaultTpl = __DIR__ . '/../templates/e3backup_vaults.tpl';
$tplSrc = file_get_contents($vaultTpl);
vault_gate_assert($tplSrc !== false, 'e3backup_vaults.tpl is readable');
if ($tplSrc !== false) {
    vault_gate_assert(str_contains($tplSrc, 'loadError'), 'vaults template tracks loadError');
    vault_gate_assert(
        str_contains($tplSrc, 'data.message'),
        'vaults template surfaces API failure message'
    );
}

exit($failures > 0 ? 1 : 0);
