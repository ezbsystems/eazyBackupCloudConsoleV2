<?php

declare(strict_types=1);

/**
 * Source-contract tests for Object Storage per-service billing exemption.
 *
 * Run:
 *   php accounts/modules/addons/cloudstorage/tests/s3billing_exempt_contract_test.php
 */

$moduleRoot = dirname(__DIR__);
$repoRoot = dirname($moduleRoot, 3);

$files = [
    's3billing' => $moduleRoot . '/lib/Admin/S3Billing.php',
    'cloudstorage' => $moduleRoot . '/cloudstorage.php',
    'billing_doc' => $moduleRoot . '/docs/E3_CLOUD_BACKUP_BILLING.md',
    'hook' => $repoRoot . '/includes/hooks/s3_billing_flags.php',
    'ajax' => $repoRoot . '/includes/hooks/s3_billing_flags_ajax.php',
    'usage_hook' => $repoRoot . '/includes/hooks/cloudstorageUsage_ClientServices.php',
];

$sources = [];
$failures = [];
foreach ($files as $key => $path) {
    $source = @file_get_contents($path);
    if ($source === false) {
        $failures[] = "could not read {$path}";
        $source = '';
    }
    $sources[$key] = $source;
}

function contract_assert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function contract_contains(string $source, string $needle, string $message): void
{
    contract_assert(strpos($source, $needle) !== false, $message);
}

// Schema ensure helpers exist and are invoked on activate/upgrade.
contract_contains(
    $sources['cloudstorage'],
    'function cloudstorage_ensure_s3_billing_flags_schema',
    'cloudstorage.php defines s3_billing_flags schema ensure'
);
contract_contains(
    $sources['cloudstorage'],
    "cloudstorage_ensure_s3_billing_flags_schema('activate')",
    'activate calls s3_billing_flags schema ensure'
);
contract_contains(
    $sources['cloudstorage'],
    "cloudstorage_ensure_s3_billing_flags_schema('upgrade')",
    'upgrade calls s3_billing_flags schema ensure'
);
contract_contains(
    $sources['cloudstorage'],
    "s3_billing_flags",
    'schema references s3_billing_flags table'
);

// S3Billing enforces exemption before metered MAX path.
contract_contains(
    $sources['s3billing'],
    'function isServiceBillingExempt',
    'S3Billing exposes isServiceBillingExempt'
);
contract_contains(
    $sources['s3billing'],
    'isServiceBillingExempt',
    'S3Billing references isServiceBillingExempt in billing path'
);
contract_contains(
    $sources['s3billing'],
    'updateProductPrice_billing_exempt',
    'exempt path logs updateProductPrice_billing_exempt'
);
contract_contains(
    $sources['s3billing'],
    'applyComplimentaryBilling',
    'S3Billing exposes applyComplimentaryBilling for immediate zero'
);

// Admin UI hooks.
contract_contains(
    $sources['hook'],
    "filename'] ?? '') !== 'clientsservices'",
    'billing flags hook is gated to clientsservices'
);
contract_contains(
    $sources['hook'],
    'Object storage billing exempt',
    'hook panel labels Object storage billing exempt'
);
contract_contains(
    $sources['hook'],
    'pid_cloud_storage',
    'hook resolves Cloud Storage PID from addon settings'
);
contract_contains(
    $sources['ajax'],
    'save_billing_flags',
    'ajax endpoint accepts save_billing_flags'
);
contract_contains(
    $sources['ajax'],
    'applyComplimentaryBilling',
    'ajax immediately zeros amounts via applyComplimentaryBilling when exempt'
);
contract_contains(
    $sources['usage_hook'],
    'billing_exempt',
    'usage summary hook surfaces billing_exempt badge state'
);

// Docs.
contract_contains(
    $sources['billing_doc'],
    's3_billing_flags',
    'billing docs mention s3_billing_flags'
);
contract_contains(
    $sources['billing_doc'],
    'billing exempt',
    'billing docs describe billing exempt flag'
);

if ($failures !== []) {
    fwrite(STDERR, "s3billing_exempt_contract_test FAIL:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "s3billing_exempt_contract_test: OK\n";
exit(0);
