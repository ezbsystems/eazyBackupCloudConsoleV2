<?php

declare(strict_types=1);

/**
 * Contract test: Signup Approvals surfacing changes.
 *
 * Static-marker test (no DB) that verifies the three surfacing paths added
 * for pending signup approvals stay wired together:
 *
 *   1) Pending-count helper exists and is consumed by the ClientAreaPage hook.
 *   2) Sidebar bell is rendered (and the standalone sidebar entry is gone).
 *   3) Per-tenant Signup Approvals button is wired into the slide-over.
 *   4) SignupApprovalsController honours ?tid= and rejects foreign tids.
 *   5) PublicSignupController fires the MSP notice on pending_approval, and
 *      the WHMCS email template "EazyBackup Pending Signup Notice" is seeded.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/signup_approvals_surfacing_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$repoRoot = dirname($moduleRoot, 4);

$countHelperFile      = $moduleRoot . '/lib/Whitelabel/SignupApprovalsCount.php';
$hooksFile            = $moduleRoot . '/hooks.php';
$sidebarTplFile       = $moduleRoot . '/templates/whitelabel/partials/sidebar_partner_hub.tpl';
$brandingListTplFile  = $moduleRoot . '/templates/whitelabel/branding-list.tpl';
$buildControllerFile  = $moduleRoot . '/pages/whitelabel/BuildController.php';
$approvalsCtrlFile    = $moduleRoot . '/pages/partnerhub/SignupApprovalsController.php';
$approvalsTplFile     = $moduleRoot . '/templates/whitelabel/signup-approvals.tpl';
$publicSignupCtrlFile = $moduleRoot . '/pages/whitelabel/PublicSignupController.php';
$moduleFile           = $moduleRoot . '/eazybackup.php';

$targets = [
    'pending-count helper' => [
        'path' => $countHelperFile,
        'markers' => [
            'function defined' => 'function eb_ph_pending_signups_summary_for_client(int $clientId): array',
            'scoped by client_id' => "->where('t.client_id', \$clientId)",
            'scoped to pending_approval' => "->where('e.status', 'pending_approval')",
            'returns total + by_tenant_tid' => "'by_tenant_tid' =>",
        ],
    ],
    'ClientAreaPage hook injection' => [
        'path' => $hooksFile,
        'markers' => [
            'requires count helper' => "require_once __DIR__ . '/lib/Whitelabel/SignupApprovalsCount.php';",
            'invokes summary helper' => 'eb_ph_pending_signups_summary_for_client($clientId)',
            'exposes total to templates' => "'eb_ph_pending_signups_count' =>",
            'exposes by-tenant map to templates' => "'eb_ph_pending_signups_by_tenant' =>",
        ],
    ],
    'sidebar bell + removal of standalone entry' => [
        'path' => $sidebarTplFile,
        'markers' => [
            'bell visibility gate' => '$ebPhBellVisible',
            'bell anchor target' => 'a=ph-signup-approvals',
            'badge cap' => '99+',
        ],
        'forbidden' => [
            // The legacy standalone sidebar entry must not come back.
            'legacy standalone signup-approvals link' => "ebPhSidebarPage eq 'signup-approvals'",
        ],
    ],
    'branding-list slide-over per-tenant button' => [
        'path' => $brandingListTplFile,
        'markers' => [
            'pending-approvals data attr' => 'data-pending-approvals="{$t.pending_approvals_count|default:0}"',
            'slide-over button' => 'btn-tenant-signup-approvals',
            'slide-over badge' => 'btn-tenant-signup-approvals-badge',
            'js sets per-tenant href' => "modulelink + '&a=ph-signup-approvals&tid='",
        ],
    ],
    'BuildController decorates rows with count' => [
        'path' => $buildControllerFile,
        'markers' => [
            'requires count helper' => "require_once __DIR__ . '/../../lib/Whitelabel/SignupApprovalsCount.php';",
            'invokes summary helper' => 'eb_ph_pending_signups_summary_for_client($clientId)',
            'writes per-row count field' => "\$tenant['pending_approvals_count'] = (int)(\$pendingByTid[\$tid] ?? 0);",
        ],
    ],
    'SignupApprovalsController honours ?tid' => [
        'path' => $approvalsCtrlFile,
        'markers' => [
            'reads tid from query' => "\$_GET['tid'] ?? ''",
            'validates ULID format' => "preg_match('/^[0-9A-HJ-NP-TV-Z]{26}\$/', \$tidRaw)",
            'enforces MSP ownership' => "->where('client_id', \$clientId)",
            'applies tenant filter to query' => "->where('t.id', \$tenantFilterId)",
            'passes tenant_context to template' => "'tenant_context' => \$tenantContext,",
        ],
    ],
    'signup-approvals.tpl renders tenant context' => [
        'path' => $approvalsTplFile,
        'markers' => [
            'reads tenant_context' => 'isset($tenant_context) && $tenant_context',
            'show-all-tenants link' => '&a=ph-signup-approvals',
        ],
    ],
    'PublicSignupController fires MSP notice' => [
        'path' => $publicSignupCtrlFile,
        'markers' => [
            'helper defined' => 'function eb_signup_send_msp_pending_notice',
            'reads addon toggle' => "eazybackup_addon_bool_setting('partnerhub_signup_notice_enabled', true)",
            'sends WHMCS template' => "'messagename' => 'EazyBackup Pending Signup Notice'",
            'targets MSP client' => "'id' => \$mspClientId",
        ],
    ],
    'addon module: setting toggle + email template seed' => [
        'path' => $moduleFile,
        'markers' => [
            'addon setting registered' => "'partnerhub_signup_notice_enabled' =>",
            'email template seed: name' => "'name' => 'EazyBackup Pending Signup Notice',",
            'email template seed: subject' => '[Action needed] New signup awaiting approval',
            'email template seed: idempotent guard' => "->where('name', 'EazyBackup Pending Signup Notice')",
        ],
    ],
];

$failures = [];
foreach ($targets as $label => $spec) {
    $path = $spec['path'];
    if (!is_file($path)) {
        $failures[] = sprintf('[%s] missing file: %s', $label, $path);
        continue;
    }
    $contents = (string)file_get_contents($path);
    foreach (($spec['markers'] ?? []) as $name => $needle) {
        if (strpos($contents, $needle) === false) {
            $failures[] = sprintf('[%s] missing marker "%s" in %s', $label, $name, $path);
        }
    }
    foreach (($spec['forbidden'] ?? []) as $name => $needle) {
        if (strpos($contents, $needle) !== false) {
            $failures[] = sprintf('[%s] forbidden marker "%s" still present in %s', $label, $name, $path);
        }
    }
}

if ($failures) {
    fwrite(STDERR, "FAIL signup_approvals_surfacing_contract_test\n");
    foreach ($failures as $f) { fwrite(STDERR, ' - ' . $f . "\n"); }
    exit(1);
}

echo "PASS signup_approvals_surfacing_contract_test\n";
exit(0);
