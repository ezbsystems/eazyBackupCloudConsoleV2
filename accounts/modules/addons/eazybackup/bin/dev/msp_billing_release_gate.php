<?php

declare(strict_types=1);

/**
 * MSP billing release gate for canonical tenant + Stripe Connect rollout.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/msp_billing_release_gate.php
 */

$moduleRoot = dirname(__DIR__, 2);
$repoRoot = dirname($moduleRoot, 4);

$checks = [
    'addon routes + schema markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/eazybackup.php',
        'markers' => [
            "ph-signup-approvals route" => "=== 'ph-signup-approvals'",
            "ph-tenants-manage route" => "=== 'ph-tenants-manage'",
            "ph-tenant-storage-links route" => "=== 'ph-tenant-storage-links'",
            "billing subscriptions route" => "=== 'ph-billing-subscriptions'",
            "billing invoices route" => "=== 'ph-billing-invoices'",
            "canonical storage links table" => "hasTable('eb_tenant_storage_links')",
            "usage ledger tenant column" => "eb_add_column_if_missing('eb_usage_ledger','tenant_id'",
            "whitelabel tenant public_id column" => "eb_add_column_if_missing('eb_whitelabel_tenants','public_id'",
            "whitelabel tenant public_id index" => "idx_eb_wl_tenants_public_id",
            "canonical tenant public_id column" => "eb_add_column_if_missing('eb_tenants','public_id'",
            "signup approval status enum" => "pending_approval",
        ],
    ],
    'signup approvals controller markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/pages/partnerhub/SignupApprovalsController.php',
        'markers' => [
            "claim processing helper" => "function eb_ph_signup_approvals_claim_processing(",
            "finalize helper" => "function eb_ph_signup_approvals_finalize_from_processing(",
            "rollback helper" => "function eb_ph_signup_approvals_rollback_to_pending(",
            "schema guard helper" => "function eb_ph_signup_approvals_require_processing_schema_or_redirect(",
        ],
    ],
    'portal route markers' => [
        'path' => $repoRoot . '/accounts/portal/index.php',
        'markers' => [
            "billing template route" => "\$template = 'billing.tpl';",
            "services template route" => "\$template = 'services.tpl';",
            "cloud storage template route" => "\$template = 'cloud_storage.tpl';",
            "invoices api route" => "'invoices' => __DIR__ . '/api/invoices.php'",
            "payment methods api route" => "'payment_methods' => __DIR__ . '/api/payment_methods.php'",
            "services api route" => "'services' => __DIR__ . '/api/services.php'",
        ],
    ],
    'partner hub doc markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/Docs/PARTNER_HUB.md',
        'markers' => [
            "canonical billing heading" => '## Canonical Tenant Billing Release Gate',
            "release gate command" => 'php accounts/modules/addons/eazybackup/bin/dev/msp_billing_release_gate.php',
            "portal sections mention" => 'Tenant Portal sections: Billing, Services, Cloud Storage',
        ],
    ],
    'cloudstorage architecture doc markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/cloudstorage/docs/E3_CLOUD_BACKUP_MSP_ARCHITECTURE.md',
        'markers' => [
            "canonical billing heading" => '## Canonical Tenant + Stripe Connect Billing',
            "tenant storage link mention" => 'eb_tenant_storage_links',
            "portal msp context mention" => 'portal/?msp=',
            "stripe authoritative invoices mention" => 'Stripe is the authoritative source of invoice state',
        ],
    ],
];

$contractChecks = [
    'tenant public id contract' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_public_id_contract_test.php',
        'expected' => 'partnerhub-tenant-public-id-contract-ok',
    ],
    'tenant detail tab routes contract' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/bin/dev/partnerhub_tenant_detail_tab_routes_contract_test.php',
        'expected' => 'partnerhub-tenant-detail-tab-routes-contract-ok',
    ],
    'billing payment new client picker contract' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php',
        'expected' => 'partnerhub-billing-payment-new-client-picker-contract-ok',
    ],
];

$failures = [];
foreach ($checks as $checkName => $check) {
    $source = @file_get_contents($check['path']);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$checkName} file at {$check['path']}";
        continue;
    }
    foreach ($check['markers'] as $label => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: {$checkName} missing {$label}";
        }
    }
}

foreach ($contractChecks as $checkName => $check) {
    $phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($check['path']) . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    $joinedOutput = trim(implode(PHP_EOL, $output));
    if ($exitCode !== 0) {
        $detail = $joinedOutput !== '' ? $joinedOutput : 'no output';
        $failures[] = "FAIL: {$checkName} command failed (exit {$exitCode}): {$detail}";
        continue;
    }

    if (strpos($joinedOutput, $check['expected']) === false) {
        $detail = $joinedOutput !== '' ? $joinedOutput : 'no output';
        $failures[] = "FAIL: {$checkName} command missing success marker {$check['expected']}: {$detail}";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "MSP_BILLING_RELEASE_GATE_PASS\n";
exit(0);
