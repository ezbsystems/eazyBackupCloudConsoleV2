<?php

declare(strict_types=1);

/**
 * Contract test: grouped Partner Hub submenu wrappers include consistent
 * vertical spacing so active and hover backgrounds do not touch.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_spacing_contract_test.php
 */

$root = dirname(__DIR__, 2);
$sidebarFile = $root . '/templates/whitelabel/partials/sidebar_partner_hub.tpl';
$source = @file_get_contents($sidebarFile);

if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read sidebar partial\n");
    exit(1);
}

$markers = [
    'catalog submenu spacing marker' => "class=\"mt-2 space-y-1 transition-all duration-300\" :class=\"sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'\">",
    'billing submenu spacing marker' => "class=\"mt-2 space-y-1 transition-all duration-300\" :class=\"sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'\">",
    'money submenu spacing marker' => "class=\"mt-2 space-y-1 transition-all duration-300\" :class=\"sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'\">",
    'stripe submenu spacing marker' => "class=\"mt-2 space-y-1 transition-all duration-300\" :class=\"sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'\">",
    'settings submenu spacing marker' => "class=\"mt-2 space-y-1 transition-all duration-300\" :class=\"sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'\">",
];

$expectedCount = 5;
$needle = "class=\"mt-2 space-y-1 transition-all duration-300\" :class=\"sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'\">";
$actualCount = substr_count($source, $needle);

$failures = [];
if ($actualCount !== $expectedCount) {
    $failures[] = "FAIL: expected {$expectedCount} grouped submenu spacing wrappers, found {$actualCount}";
}

foreach ($markers as $name => $marker) {
    if (strpos($source, $marker) === false) {
        $failures[] = "FAIL: missing {$name}";
        break;
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-sidebar-group-spacing-contract-ok\n";
exit(0);
