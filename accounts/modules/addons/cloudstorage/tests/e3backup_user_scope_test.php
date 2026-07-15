<?php

declare(strict_types=1);

/**
 * Unit-style tests for E3BackupUserScope phrase validation and rename helper.
 *
 * Run:
 *   php accounts/modules/addons/cloudstorage/tests/e3backup_user_scope_test.php
 */

$repoRoot = dirname(__DIR__, 4);
require_once $repoRoot . '/modules/addons/cloudstorage/lib/Client/E3BackupUserScope.php';

use WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserScope;

$failures = [];

if (!E3BackupUserScope::validateDeletePhrase('alice', 'DELETE alice')) {
    $failures[] = 'expected DELETE alice to validate';
}
if (E3BackupUserScope::validateDeletePhrase('alice', 'alice')) {
    $failures[] = 'expected bare username to fail';
}
if (E3BackupUserScope::validateDeletePhrase('alice', 'DELETE')) {
    $failures[] = 'expected bare DELETE to fail per-user validator';
}
if (!E3BackupUserScope::validateDeletePhrase('alice', 'delete ALICE')) {
    $failures[] = 'expected case-insensitive phrase';
}
if (!E3BackupUserScope::validateBulkDeletePhrase('DELETE')) {
    $failures[] = 'expected bulk DELETE phrase';
}

$renamed = E3BackupUserScope::deletedUsername('alice', 42);
if ($renamed !== 'alice__deleted_42') {
    $failures[] = 'expected deleted username suffix';
}
$long = str_repeat('a', 200);
$renamedLong = E3BackupUserScope::deletedUsername($long, 99);
if (strlen($renamedLong) > 191) {
    $failures[] = 'deleted username must fit 191 chars';
}
if (substr($renamedLong, -strlen('__deleted_99')) !== '__deleted_99') {
    $failures[] = 'truncated deleted username must keep suffix';
}

$active = (object) ['deleted_at' => null, 'status' => 'active'];
$deleted = (object) ['deleted_at' => '2026-07-15 12:00:00', 'status' => 'disabled'];
if (E3BackupUserScope::isDeletedUser($active)) {
    $failures[] = 'active user should not be deleted';
}
if (!E3BackupUserScope::isDeletedUser($deleted)) {
    $failures[] = 'deleted_at user should be deleted';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo 'FAIL: ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo "e3backup-user-scope-test-ok\n";
exit(0);
