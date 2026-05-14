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
    // Phase B test seams — these protect the unit-test suite from regressing.
    'StripeService Phase B markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/lib/PartnerHub/StripeService.php',
        'markers' => [
            "request() is protected (test seam)" => 'protected function request(',
            "fee cascade helper signature" => 'public static function resolveApplicationFeePercent(',
            "fee cascade module default lookup" => "'partnerhub_default_fee_percent'",
        ],
    ],
    'CatalogService Phase B markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/lib/PartnerHub/CatalogService.php',
        'markers' => [
            "request() is protected (test seam)" => 'protected function request(',
        ],
    ],
    'SubscriptionsController fee cascade delegation' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/pages/partnerhub/SubscriptionsController.php',
        'markers' => [
            "delegates fee cascade to StripeService" => 'StripeService::resolveApplicationFeePercent(',
        ],
    ],
    // Phase C webhook test seams — these helpers are independently invokable so unit
    // tests can drive every event handler without going through curl + signing the
    // wire. Removing them silently reverts the whole integration suite.
    'StripeWebhookController Phase C markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/pages/partnerhub/StripeWebhookController.php',
        'markers' => [
            "verify_signature helper" => 'function eb_ph_webhook_verify_signature(',
            "record_idempotent helper" => 'function eb_ph_webhook_record_idempotent(',
            "dispatch_event helper" => 'function eb_ph_webhook_dispatch_event(',
            "tenant_id NULL-on-no-match contract" => "'tenant_id' => \$effectiveTenantId,",
        ],
    ],
    // Phase C2 webhook seams — extra injection points so capability.updated, the
    // HTTP entry-point glue, and email dispatch can be exercised in tests.
    'StripeWebhookController Phase C2 markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/pages/partnerhub/StripeWebhookController.php',
        'markers' => [
            "dispatch_event accepts optional StripeService" => '?\PartnerHub\StripeService $stripeService = null',
            "capability.updated uses injected service" => '$svc = $stripeService ?? new StripeService();',
            "pure-function entry-point handler" => 'function eb_ph_stripe_webhook_handle(',
            "entry-point handler returns status+body array" => "return ['status' =>",
            "thin entry point delegates to handler" => 'eb_ph_stripe_webhook_handle($payload, $sig, $secret);',
        ],
    ],
    'MailService Phase C2 transport seam' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/lib/PartnerHub/MailService.php',
        'markers' => [
            "transport setter" => 'public static function setTransport(',
            "transport clearer" => 'public static function clearTransport(',
            "transport short-circuit in sendTemplate" => 'if (self::$transport !== null)',
        ],
    ],
    // Phase F seams — public signup validators extracted so abuse controls can be
    // unit-tested without going through $_POST + $_SERVER + Turnstile + localAPI.
    'PublicSignupController Phase F markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/pages/whitelabel/PublicSignupController.php',
        'markers' => [
            "validate_basic_input helper" => 'function eb_signup_validate_basic_input(',
            "domain filter helper" => 'function eb_signup_check_domain_filters(',
            "rate limit helper" => 'function eb_signup_check_rate_limits(',
            "existing event state helper" => 'function eb_signup_existing_event_state(',
            "controller delegates to validate helper" => 'eb_signup_validate_basic_input(',
            "controller delegates to domain filter helper" => 'eb_signup_check_domain_filters(',
            "controller delegates to rate limit helper" => 'eb_signup_check_rate_limits(',
            "controller delegates to existing event helper" => 'eb_signup_existing_event_state(',
        ],
    ],
    // Phase G seams — pure-function backends for assign-plan + cancel-subscription
    // so the canonical billing state machine can be tested with injected services.
    'CatalogPlansController Phase G markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/pages/partnerhub/CatalogPlansController.php',
        'markers' => [
            "assign-for-msp helper signature" => 'function eb_ph_plan_assign_for_msp(',
            "assign-for-msp accepts injected StripeService" => '?\PartnerHub\StripeService $stripeService = null',
            "assign-for-msp accepts injected CatalogService" => '?\PartnerHub\CatalogService $catalogService = null',
            "assign HTTP wrapper delegates" => 'eb_ph_plan_assign_for_msp(',
            "cancel-for-msp helper signature" => 'function eb_ph_plan_subscription_cancel_for_msp(',
            "cancel HTTP wrapper delegates" => 'eb_ph_plan_subscription_cancel_for_msp(',
        ],
    ],
    // Phase H seams — usage push orchestration extracted so allowance + Stripe
    // dispatch can be exercised in isolation.
    'UsageController Phase H markers' => [
        'path' => $repoRoot . '/accounts/modules/addons/eazybackup/pages/partnerhub/UsageController.php',
        'markers' => [
            "push-for-tenant helper signature" => 'function eb_ph_usage_push_for_tenant(',
            "push-for-tenant accepts injected StripeService" => '?\PartnerHub\StripeService $stripeService = null',
            "push HTTP wrapper delegates" => 'eb_ph_usage_push_for_tenant(',
            "ledger upsert by idempotency key" => "['idempotency_key' => \$idKey]",
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

// PHPUnit unit suite (Phase A onwards). Skipped when EB_GATE_SKIP_PHPUNIT=1 to avoid
// recursion when the gate is itself invoked from inside PHPUnit (see tests/Unit/
// MspBillingReleaseGateRunsCleanTest.php). Also skipped cleanly when phpunit isn't
// installed (e.g. fresh checkout without `composer install --dev`).
$skipPhpunit = (string) getenv('EB_GATE_SKIP_PHPUNIT') === '1';
$addonRoot = $repoRoot . '/accounts/modules/addons/eazybackup';
$phpunitBinary = $addonRoot . '/vendor/bin/phpunit';
if (!$skipPhpunit && is_file($phpunitBinary) && is_executable($phpunitBinary)) {
    $phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
    $phpunitCommand = sprintf(
        '%s %s --testsuite unit --no-coverage --colors=never 2>&1',
        escapeshellarg($phpBinary),
        escapeshellarg($phpunitBinary)
    );

    $cwd = getcwd();
    chdir($addonRoot);
    $output = [];
    $exitCode = 0;
    exec($phpunitCommand, $output, $exitCode);
    if ($cwd !== false) {
        chdir($cwd);
    }

    if ($exitCode !== 0) {
        $detail = trim(implode(PHP_EOL, $output));
        $failures[] = "FAIL: phpunit unit suite failed (exit {$exitCode}):\n{$detail}";
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
