<?php
/**
 * Harness for cloud backup UUIDv7 job/run contract tests.
 * Requires both schema and route contract tests; prints success marker if both pass.
 * Run: php accounts/modules/addons/cloudstorage/tests/run_cloudbackup_uuid_tests.php
 */

$baseDir = __DIR__;
$schemaTest = $baseDir . '/cloudbackup_uuid_schema_contract_test.php';
$routeTest = $baseDir . '/cloudbackup_uuid_route_contract_test.php';

$allPass = true;
$output = [];

// Run schema contract test
ob_start();
try {
    require $schemaTest;
    $out = ob_get_clean();
    if (strpos($out, 'schema-contract-ok') !== false) {
        $output[] = 'schema: pass';
    } else {
        $output[] = 'schema: fail (no schema-contract-ok)';
        $allPass = false;
    }
} catch (Throwable $e) {
    ob_end_clean();
    $output[] = 'schema: fail - ' . $e->getMessage();
    $allPass = false;
}

// Run route contract test
ob_start();
try {
    require $routeTest;
    $out = ob_get_clean();
    if (strpos($out, 'route-contract-ok') !== false) {
        $output[] = 'route: pass';
    } else {
        $output[] = 'route: fail (no route-contract-ok)';
        $allPass = false;
    }
} catch (Throwable $e) {
    ob_end_clean();
    $output[] = 'route: fail - ' . $e->getMessage();
    $allPass = false;
}

foreach ($output as $line) {
    echo $line . "\n";
}

if (!$allPass) {
    exit(1);
}

echo "CLOUDBACKUP_UUID_CONTRACT_ALL_PASS\n";
exit(0);
