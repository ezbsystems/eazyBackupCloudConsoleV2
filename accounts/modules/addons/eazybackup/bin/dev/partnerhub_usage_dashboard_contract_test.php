<?php

declare(strict_types=1);

$moduleRoot = dirname(__DIR__, 2);

$targets = [
    'module routing file' => [
        'path' => $moduleRoot . '/eazybackup.php',
        'markers' => [
            'usage dashboard route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-usage-dashboard'",
            'usage dashboard live route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-usage-dashboard-stripe-live'",
            'usage dashboard push route marker' => "\$_REQUEST['a']) && \$_REQUEST['a'] === 'ph-usage-dashboard-push-now'",
            'usage dashboard controller include marker' => "require_once __DIR__ . '/pages/partnerhub/UsageDashboardController.php';",
        ],
    ],
    'usage dashboard controller file' => [
        'path' => $moduleRoot . '/pages/partnerhub/UsageDashboardController.php',
        'markers' => [
            'usage dashboard page function marker' => 'function eb_ph_usage_dashboard(array $vars)',
            'usage dashboard live function marker' => 'function eb_ph_usage_dashboard_stripe_live(array $vars): void',
            'usage dashboard push function marker' => 'function eb_ph_usage_dashboard_push_now(array $vars): void',
            'usage dashboard template marker' => "'templatefile' => 'whitelabel/usage-dashboard'",
            'usage dashboard summary marker' => "'active_metered_subscriptions' =>",
            'usage dashboard rows marker' => "'usage_rows' =>",
        ],
    ],
    'usage dashboard template file' => [
        'path' => $moduleRoot . '/templates/whitelabel/usage-dashboard.tpl',
        'markers' => [
            'usage dashboard shell marker' => 'partials/partner_hub_shell.tpl',
            'usage dashboard title marker' => "ebPhTitle='Usage Dashboard'",
            'usage dashboard card marker' => 'Active Metered Subscriptions',
            'usage dashboard quota marker' => 'Tenants Over Included Quota',
            'usage dashboard last push marker' => 'Last Usage Push',
            'usage dashboard table marker' => 'Latest Metered Usage',
        ],
    ],
    'usage dashboard sidebar marker file' => [
        'path' => $moduleRoot . '/templates/whitelabel/partials/sidebar_partner_hub.tpl',
        'markers' => [
            'usage dashboard sidebar route marker' => 'ph-usage-dashboard',
            'usage dashboard sidebar label marker' => '>Usage</span>',
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

echo "partnerhub-usage-dashboard-contract-ok\n";
