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

echo "public-signup-pending-approval-contract-ok\n";
exit(0);
