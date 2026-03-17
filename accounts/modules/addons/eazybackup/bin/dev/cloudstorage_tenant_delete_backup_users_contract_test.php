<?php

declare(strict_types=1);

/**
 * Contract test: cloudstorage tenant delete disables scoped backup-user accounts.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/cloudstorage_tenant_delete_backup_users_contract_test.php
 */

$repoRoot = dirname(__DIR__, 6);
$target = $repoRoot . '/accounts/modules/addons/cloudstorage/api/e3backup_tenant_delete.php';
$source = @file_get_contents($target);

if ($source === false) {
    echo "FAIL: unable to read cloudstorage tenant delete API file\n";
    exit(1);
}

$markers = [
    'tenant public id resolution marker' => "MspController::getTenantByPublicId(\$tenantPublicId, \$clientId)",
    'backup users table guard marker' => "if (Capsule::schema()->hasTable('s3_backup_users')) {",
    'backup users tenant scope marker' => "->where('tenant_id', \$tenantId)",
    'backup users client scope marker' => "->where('client_id', \$clientId)",
    'backup users disabled status marker' => "'status' => 'disabled',",
];

$failures = [];
foreach ($markers as $name => $needle) {
    if (strpos($source, $needle) === false) {
        $failures[] = "FAIL: missing {$name}";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "cloudstorage-tenant-delete-backup-users-contract-ok\n";
exit(0);
