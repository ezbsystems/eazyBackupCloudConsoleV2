<?php

declare(strict_types=1);

/**
 * Schema contract: s3_backup_users.deleted_at must exist after upgrade/ensure.
 *
 * Run:
 *   php accounts/modules/addons/cloudstorage/tests/e3backup_user_deleted_at_schema_test.php
 */

$repoRoot = dirname(__DIR__, 4);
require_once $repoRoot . '/init.php';
require_once $repoRoot . '/modules/addons/cloudstorage/lib/Client/E3BackupUserScope.php';

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\E3BackupUserScope;

if (!Capsule::schema()->hasTable('s3_backup_users')) {
    echo "SKIP: s3_backup_users table missing\n";
    exit(0);
}

if (!E3BackupUserScope::hasDeletedAtColumn()) {
    E3BackupUserScope::ensureDeletedAtColumn();
}

if (!Capsule::schema()->hasColumn('s3_backup_users', 'deleted_at')) {
    echo "FAIL: s3_backup_users.deleted_at missing after ensure\n";
    exit(1);
}

echo "e3backup-user-deleted-at-schema-ok\n";
exit(0);
