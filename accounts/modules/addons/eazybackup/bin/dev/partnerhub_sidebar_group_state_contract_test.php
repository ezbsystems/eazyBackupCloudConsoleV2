<?php

declare(strict_types=1);

/**
 * Contract test: grouped Partner Hub sidebar sections auto-open on active child
 * pages and only child rows receive active-state styling.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_state_contract_test.php
 */

$root = dirname(__DIR__, 2);
$sidebarFile = $root . '/templates/whitelabel/partials/sidebar_partner_hub.tpl';
$source = @file_get_contents($sidebarFile);

if ($source === false) {
    fwrite(STDERR, "FAIL: unable to read sidebar partial\n");
    exit(1);
}

$markers = [
    'catalog active-group marker' => "{assign var=catalogGroupActive value=\$ebPhSidebarPage eq 'catalog-products' || \$ebPhSidebarPage eq 'catalog-plans'}",
    'billing active-group marker' => "{assign var=billingGroupActive value=\$ebPhSidebarPage eq 'billing-subscriptions' || \$ebPhSidebarPage eq 'billing-invoices' || \$ebPhSidebarPage eq 'billing-payments'}",
    'money active-group marker' => "{assign var=moneyGroupActive value=\$ebPhSidebarPage eq 'money-payouts' || \$ebPhSidebarPage eq 'money-disputes' || \$ebPhSidebarPage eq 'money-balance'}",
    'stripe active-group marker' => "{assign var=stripeGroupActive value=\$ebPhSidebarPage eq 'stripe-connect' || \$ebPhSidebarPage eq 'stripe-manage'}",
    'settings active-group marker' => "{assign var=settingsGroupActive value=\$ebPhSidebarPage eq 'settings-checkout' || \$ebPhSidebarPage eq 'settings-tax' || \$ebPhSidebarPage eq 'settings-email'}",
    'catalog open-state marker' => "<div x-data=\"{ catalogOpen: {if \$catalogGroupActive}true{else}false{/if} }\" class=\"space-y-0\">",
    'billing open-state marker' => "<div x-data=\"{ billingOpen: {if \$billingGroupActive}true{else}false{/if} }\" class=\"space-y-0\">",
    'money open-state marker' => "<div x-data=\"{ moneyOpen: {if \$moneyGroupActive}true{else}false{/if} }\" class=\"space-y-0\">",
    'stripe open-state marker' => "<div x-data=\"{ stripeOpen: {if \$stripeGroupActive}true{else}false{/if} }\" class=\"space-y-0\">",
    'settings open-state marker' => "<div x-data=\"{ settingsOpen: {if \$settingsGroupActive}true{else}false{/if} }\" class=\"space-y-0\">",
];

$failures = [];
foreach ($markers as $name => $needle) {
    if (strpos($source, $needle) === false) {
        $failures[] = "FAIL: missing {$name}";
    }
}

$forbidden = [
    'catalog header active-state marker' => "{if \$ebPhSidebarPage eq 'catalog-products' || \$ebPhSidebarPage eq 'catalog-plans'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}",
    'billing header active-state marker' => "{if \$ebPhSidebarPage eq 'billing-subscriptions' || \$ebPhSidebarPage eq 'billing-invoices' || \$ebPhSidebarPage eq 'billing-payments'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}",
    'money header active-state marker' => "{if \$ebPhSidebarPage eq 'money-payouts' || \$ebPhSidebarPage eq 'money-disputes' || \$ebPhSidebarPage eq 'money-balance'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}",
    'stripe header active-state marker' => "{if \$ebPhSidebarPage eq 'stripe-connect' || \$ebPhSidebarPage eq 'stripe-manage'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}",
    'settings header active-state marker' => "{if \$ebPhSidebarPage eq 'settings-checkout' || \$ebPhSidebarPage eq 'settings-tax' || \$ebPhSidebarPage eq 'settings-email'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}",
];

foreach ($forbidden as $name => $needle) {
    if (strpos($source, $needle) !== false) {
        $failures[] = "FAIL: found {$name}";
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "partnerhub-sidebar-group-state-contract-ok\n";
exit(0);
