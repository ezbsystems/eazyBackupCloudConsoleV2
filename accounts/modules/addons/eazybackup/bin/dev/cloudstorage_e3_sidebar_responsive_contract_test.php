<?php

declare(strict_types=1);

/**
 * Contract test: e3 responsive sidebar (drawer / rail / full).
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/cloudstorage_e3_sidebar_responsive_contract_test.php
 */

$root = dirname(__DIR__, 5);

$targets = [
    'legacy sidebar preserved' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/partials/e3backup_sidebar_legacy.tpl',
        'markers' => [
            'legacy width toggle' => ":class=\"sidebarCollapsed ? 'w-20' : 'w-56'\"",
            'legacy collapse title' => ":title=\"sidebarCollapsed ? 'Dashboard' : ''\"",
        ],
    ],
    'responsive sidebar' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/partials/e3backup_sidebar.tpl',
        'markers' => [
            'drawer id' => 'id="eb-e3-sidebar-drawer"',
            'drawer mode class' => 'eb-e3-sidebar--drawer',
            'rail mode class' => 'eb-e3-sidebar--rail',
            'full mode class' => 'eb-e3-sidebar--full',
            'tooltip metadata' => 'data-sidebar-label=',
            'rail link class' => 'eb-e3-sidebar-link--rail',
            'aria current static' => 'aria-current="page"',
            'sticky footer' => 'eb-e3-sidebar-footer',
        ],
    ],
    'user subnav responsive' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/partials/e3backup_sidebar_user_subnav.tpl',
        'markers' => [
            'contextual label prefix' => 'data-sidebar-label="{$ebE3UserLabelPrefix}Profile"',
            'rail subnav class' => 'eb-e3-sidebar-subnav--rail',
            'full subnav class' => 'eb-e3-sidebar-subnav--full',
            'sublink class' => 'eb-e3-sidebar-sublink',
            'username truncate' => 'eb-e3-sidebar-username',
            'username hidden in rail via labels' => 'x-show="sidebarLabelsVisible"',
        ],
    ],
    'shell responsive state' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl',
        'markers' => [
            'responsive factory' => 'x-data="ebE3SidebarResponsive()"',
            'responsive script include' => 'e3backup_sidebar_responsive_script.tpl',
            'drawer backdrop' => 'eb-e3-sidebar-backdrop',
            'tooltip portal' => 'eb-e3-sidebar-tooltip',
            'mobile nav bar' => 'eb-e3-mobile-nav-bar',
            'breakpoint shell class' => 'eb-app-shell--e3-responsive',
        ],
    ],
    'responsive script' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/partials/e3backup_sidebar_responsive_script.tpl',
        'markers' => [
            'mobile breakpoint' => 'var BP_MOBILE = 1024',
            'wide breakpoint' => 'var BP_WIDE = 1280',
            'desktop pref storage' => 'eb_e3_sidebar_desktop_pref',
            'focus trap' => 'installFocusTrap',
            'tooltip delegation' => 'bindTooltipDelegation',
            'drawer close on nav' => 'bindDrawerNavClose',
        ],
    ],
    'tailwind responsive css' => [
        'path' => $root . '/templates/eazyBackup/css/tailwind.src.css',
        'markers' => [
            'drawer css' => '.eb-e3-sidebar--drawer',
            'rail css' => '.eb-e3-sidebar--rail',
            'tooltip css' => '.eb-e3-sidebar-tooltip',
            'backdrop css' => '.eb-e3-sidebar-backdrop',
            'safe area footer' => 'safe-area-inset-bottom',
            'reduced motion' => 'prefers-reduced-motion',
            'desktop drawer reset' => '@media (min-width: 1024px)',
        ],
    ],
];

$failures = 0;

foreach ($targets as $label => $config) {
    $path = $config['path'];
    if (!is_file($path)) {
        echo "FAIL: missing file for {$label}: {$path}\n";
        ++$failures;
        continue;
    }
    $contents = (string) file_get_contents($path);
    foreach ($config['markers'] as $name => $needle) {
        if (!str_contains($contents, $needle)) {
            echo "FAIL: {$label} — missing {$name}: {$needle}\n";
            ++$failures;
            continue;
        }
        echo "OK: {$label} — {$name}\n";
    }
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} contract failure(s)\n");
    exit(1);
}

echo "\nAll e3 responsive sidebar contract checks passed.\n";
exit(0);
