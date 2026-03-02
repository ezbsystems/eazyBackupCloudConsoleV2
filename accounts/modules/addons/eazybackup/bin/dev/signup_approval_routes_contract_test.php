<?php

declare(strict_types=1);

/**
 * Contract test: Partner Hub signup approval routes and handlers.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/signup_approval_routes_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$repoRoot = dirname($moduleRoot, 4);

$controllerFile = $moduleRoot . '/pages/partnerhub/SignupApprovalsController.php';
$templateFile = $moduleRoot . '/templates/whitelabel/signup-approvals.tpl';
$moduleFile = $moduleRoot . '/eazybackup.php';
$navFile = $repoRoot . '/accounts/templates/eazyBackup/includes/nav_partner_hub.tpl';

$targets = [
    'module routing file' => [
        'path' => $moduleFile,
        'markers' => [
            'signup approvals route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-signup-approvals'",
            'signup approvals controller include marker' => "require_once __DIR__ . '/pages/partnerhub/SignupApprovalsController.php';",
            'signup approvals controller return marker' => 'return eb_ph_signup_approvals_index($vars);',
            'signup approve route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-signup-approve'",
            'signup approve controller exit marker' => 'eb_ph_signup_approve($vars); exit;',
            'signup reject route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-signup-reject'",
            'signup reject controller exit marker' => 'eb_ph_signup_reject($vars); exit;',
            'signup status enum migration marker' => "ALTER TABLE eb_whitelabel_signup_events MODIFY COLUMN status ENUM('received','validated','ordered','pending_approval','approved','rejected','accepted','provisioned','emailed','completed','failed') NOT NULL DEFAULT 'received'",
        ],
    ],
    'signup approvals controller file' => [
        'path' => $controllerFile,
        'markers' => [
            'index function marker' => 'function eb_ph_signup_approvals_index(array $vars)',
            'template marker' => "'templatefile' => 'whitelabel/signup-approvals'",
            'pending queue filter marker' => "->where('e.status', 'pending_approval')",
            'csrf token emit marker' => "'token' => function_exists('generate_token') ? generate_token('plain') : ''",
            'csrf check marker' => "check_token('plain', \$token)",
            'csrf fail-closed helper marker' => 'function eb_ph_signup_approvals_require_csrf_or_redirect(array $vars, string $token): void',
            'approve function marker' => 'function eb_ph_signup_approve(array $vars): void',
            'order ownership revalidation marker' => "localAPI('GetOrders'",
            'approve localapi marker' => "localAPI('AcceptOrder'",
            'approve status marker' => "'status' => 'approved'",
            'approve race guard marker' => "->where('status', 'pending_approval')",
            'reject function marker' => 'function eb_ph_signup_reject(array $vars): void',
            'reject cancel marker' => "localAPI('CancelOrder'",
            'reject void marker' => "localAPI('UpdateInvoice'",
            'reject status marker' => "'status' => 'rejected'",
            'reject cancel path success marker' => '$cancelPathSucceeded',
            'reject failure surfaced marker' => "eb_ph_signup_approvals_redirect(\$vars, 'error=cancel_failed')",
        ],
        'forbidden' => [
            'direct tblorders write marker' => "Capsule::table('tblorders')->where('id', \$orderId)->update(['status' => 'Cancelled'])",
            'direct tblinvoices write marker' => "Capsule::table('tblinvoices')->where('id', \$invoiceId)->update(['status' => 'Cancelled'])",
        ],
    ],
    'signup approvals template file' => [
        'path' => $templateFile,
        'markers' => [
            'page heading marker' => 'Pending Signup Approvals',
            'approve action marker' => '&a=ph-signup-approve',
            'reject action marker' => '&a=ph-signup-reject',
            'csrf hidden input marker' => 'name="token"',
        ],
    ],
    'partner hub nav file' => [
        'path' => $navFile,
        'markers' => [
            'signup approvals nav link marker' => '&a=ph-signup-approvals',
            'signup approvals nav label marker' => 'Signup Approvals',
        ],
    ],
];

$failures = [];
foreach ($targets as $targetName => $target) {
    $path = $target['path'];
    $source = @file_get_contents($path);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$targetName} at {$path}";
        continue;
    }

    foreach ($target['markers'] as $markerName => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: missing {$markerName}";
        }
    }

    foreach (($target['forbidden'] ?? []) as $markerName => $needle) {
        if (strpos($source, $needle) !== false) {
            $failures[] = "FAIL: forbidden {$markerName}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "signup-approval-routes-contract-ok\n";
exit(0);
