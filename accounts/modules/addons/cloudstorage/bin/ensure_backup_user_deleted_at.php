#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Apply cloudstorage upgrade + ensure s3_backup_users.deleted_at exists.
 *
 * Usage: php accounts/modules/addons/cloudstorage/bin/ensure_backup_user_deleted_at.php
 */

$repoRoot = dirname(__DIR__, 4);
require_once $repoRoot . '/init.php';
require_once dirname(__DIR__) . '/cloudstorage.php';
require_once dirname(__DIR__) . '/lib/Client/E3BackupUserScope.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserScope;

$currentVersion = (string) Capsule::table('tbladdonmodules')
    ->where('module', 'cloudstorage')
    ->where('setting', 'version')
    ->value('value');

echo 'cloudstorage version before: ' . ($currentVersion !== '' ? $currentVersion : 'unknown') . PHP_EOL;

if (function_exists('cloudstorage_upgrade')) {
    cloudstorage_upgrade(['version' => $currentVersion]);
}

Capsule::table('tbladdonmodules')
    ->where('module', 'cloudstorage')
    ->where('setting', 'version')
    ->update(['value' => '2.2.1']);

E3BackupUserScope::ensureDeletedAtColumn();

$has = Capsule::schema()->hasColumn('s3_backup_users', 'deleted_at');
echo 'deleted_at present: ' . ($has ? 'yes' : 'no') . PHP_EOL;
exit($has ? 0 : 1);
