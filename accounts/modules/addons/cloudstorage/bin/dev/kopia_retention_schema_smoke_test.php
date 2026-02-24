<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use WHMCS\Database\Capsule;

$required = [
    's3_kopia_policy_versions',
    's3_kopia_repos',
    's3_kopia_repo_sources',
    's3_kopia_repo_operations',
    's3_kopia_repo_locks',
];
$ok = true;
foreach ($required as $table) {
    if (!Capsule::schema()->hasTable($table)) {
        echo "FAIL missing table: {$table}\n";
        $ok = false;
    }
}
echo $ok ? "PASS\n" : "FAIL\n";
exit($ok ? 0 : 1);
