<?php

declare(strict_types=1);

/**
 * Contract test: public signup must stop at pending approval.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/public_signup_pending_approval_contract_test.php
 */

$moduleRoot = dirname(__DIR__, 2);
$controllerFile = $moduleRoot . '/pages/whitelabel/PublicSignupController.php';
$templateFile = $moduleRoot . '/templates/whitelabel/public-signup.tpl';
$schemaFile = $moduleRoot . '/eazybackup.php';
$schemaSqlFile = $moduleRoot . '/sql/partner_hub_phase1.sql';

$targets = [
    'public signup controller file' => [
        'path' => $controllerFile,
        'markers' => [
            'addorder call marker' => "localAPI('AddOrder', \$orderPayload, \$adminUser)",
            'pending approval status marker' => "'status' => 'pending_approval'",
            'pending approval response marker' => "'signup_state' => 'pending_approval'",
        ],
        'forbidden' => [
            'inline acceptorder call marker' => "localAPI('AcceptOrder'",
        ],
    ],
    'public signup template file' => [
        'path' => $templateFile,
        'markers' => [
            'pending approval ui guard marker' => "{if \$signup_state == 'pending_approval'}",
            'pending approval ui copy marker' => 'Your signup is pending MSP approval',
        ],
    ],
    'runtime schema migration source file' => [
        'path' => $schemaFile,
        'markers' => [
            'runtime pending approval enum alter marker' => "ALTER TABLE eb_whitelabel_signup_events MODIFY COLUMN status ENUM('received','validated','ordered','pending_approval','accepted','provisioned','emailed','completed','failed') NOT NULL DEFAULT 'received'",
        ],
    ],
    'phase1 schema sql file' => [
        'path' => $schemaSqlFile,
        'markers' => [
            'phase1 pending approval enum marker' => "status ENUM('received','validated','ordered','pending_approval','accepted','provisioned','emailed','completed','failed') NOT NULL DEFAULT 'received'",
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

$controllerSource = @file_get_contents($controllerFile);
if ($controllerSource === false) {
    $failures[] = 'FAIL: unable to read controller source for ordering check';
} else {
    $idempotencyPos = strpos($controllerSource, '// Idempotency: if an event already exists for this tenant+email, avoid duplicate order');
    $rateLimitPos = strpos($controllerSource, '// Rate limiting: per-IP and per-email in last hour');
    if ($idempotencyPos === false || $rateLimitPos === false) {
        $failures[] = 'FAIL: missing idempotency/rate-limit ordering markers';
    } elseif ($idempotencyPos > $rateLimitPos) {
        $failures[] = 'FAIL: pending-approval idempotency check must run before rate limiting';
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "public-signup-pending-approval-contract-ok\n";
exit(0);
