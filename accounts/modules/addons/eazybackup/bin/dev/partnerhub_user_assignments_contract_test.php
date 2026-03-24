<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__, 2);

$targets = [
    'module routing file' => [
        'path' => $moduleRoot . '/eazybackup.php',
        'markers' => [
            'user assignments route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-user-assignments'",
            'user assignments controller include marker' => "require_once __DIR__ . '/pages/partnerhub/UserAssignmentsController.php';",
        ],
    ],
    'user assignments controller file' => [
        'path' => $moduleRoot . '/pages/partnerhub/UserAssignmentsController.php',
        'markers' => [
            'user assignments page function marker' => 'function eb_ph_user_assignments(array $vars)',
            'user assignments template marker' => "'templatefile' => 'whitelabel/user-assignments'",
            'user assignments assigned rows marker' => "'assigned_rows' =>",
            'user assignments unassigned rows marker' => "'unassigned_rows' =>",
        ],
    ],
    'user assignments template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/user-assignments.tpl',
        'markers' => [
            'user assignments shell marker' => 'partials/partner_hub_shell.tpl',
            'user assignments title marker' => "ebPhTitle='User Assignments'",
            'assigned users table marker' => 'Assigned Backup Users',
            'unassigned users section marker' => 'Unassigned Backup Users',
        ],
    ],
    'user assignments sidebar marker file' => [
        'path' => $moduleRoot . '/templates/whitelabel/partials/sidebar_partner_hub.tpl',
        'markers' => [
            'user assignments sidebar route marker' => 'ph-user-assignments',
            'user assignments sidebar label marker' => '>User Assignments</span>',
        ],
    ],
];

$failures = [];
foreach ($targets as $targetName => $target) {
    $source = @file_get_contents($target['path']);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$targetName}";
        continue;
    }
    foreach ($target['markers'] as $markerName => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: missing {$markerName}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-user-assignments-contract-ok\n";
