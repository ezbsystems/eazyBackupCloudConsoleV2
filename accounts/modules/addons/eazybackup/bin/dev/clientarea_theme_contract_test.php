<?php

declare(strict_types=1);

/**
 * Contract test: client-area theme migration markers.
 *
 * Run:
 * php accounts/modules/addons/eazybackup/bin/dev/clientarea_theme_contract_test.php
 */

$root = dirname(__DIR__, 5);

$targets = [
    'head.tpl' => [
        'path' => $root . '/templates/eazyBackup/includes/head.tpl',
        'markers' => [
            'google fonts include' => 'family=Outfit',
            'token include' => '{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}',
        ],
    ],
    'header.tpl' => [
        'path' => $root . '/templates/eazyBackup/header.tpl',
        'markers' => [
            'dark theme attribute' => 'data-theme="dark"',
            'shell body class' => 'class="eb-shell-body"',
        ],
    ],
    '_ui-tokens.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/partials/_ui-tokens.tpl',
        'markers' => [
            'light theme root selector' => '[data-theme="light"]',
            'dark theme selector' => '[data-theme="dark"]',
            'chrome elevation token' => '--eb-bg-chrome',
            'overlay elevation token' => '--eb-bg-overlay',
            'faint border token' => '--eb-border-faint',
            'brand border token' => '--eb-border-brand',
            'premium semantic token' => '--eb-premium-bg',
            'hero type token' => '--eb-type-hero-size',
            'modal backdrop token' => '--eb-backdrop-modal',
            'drawer width token' => '--eb-drawer-width-wide',
        ],
    ],
    'tailwind.src.css' => [
        'path' => $root . '/templates/eazyBackup/css/tailwind.src.css',
        'markers' => [
            'cloudstorage source' => '../../../modules/addons/cloudstorage/templates/**/*.tpl',
            'page class' => '.eb-page',
            'panel class' => '.eb-panel',
            'app shell class' => '.eb-app-shell',
            'app header class' => '.eb-app-header',
            'auth shell class' => '.eb-auth-shell',
            'button class' => '.eb-btn-primary',
            'danger button class' => '.eb-btn-danger',
            'hero type class' => '.eb-type-hero',
            'raised card class' => '.eb-card-raised',
            'sidebar link class' => '.eb-sidebar-link',
            'outline button class' => '.eb-btn-outline',
            'upgrade button class' => '.eb-btn-upgrade',
            'premium button class' => '.eb-btn-premium',
            'pill class' => '.eb-pill',
            'icon box class' => '.eb-icon-box',
            'status dot class' => '.eb-status-dot',
            'toast class' => '.eb-toast',
            'menu class' => '.eb-menu',
            'modal class' => '.eb-modal',
            'drawer class' => '.eb-drawer',
        ],
    ],
    'profile-nav.tpl' => [
        'path' => $root . '/templates/eazyBackup/includes/profile-nav.tpl',
        'markers' => [
            'semantic nav tab marker' => 'class="eb-tab',
            'payment details label' => '<span>Payment Details</span>',
        ],
        'forbidden' => [
            'legacy mobile menu toggle' => 'profile-menu-toggle',
            'legacy mobile flyout' => 'mobile-menu',
        ],
    ],
    'supportticketslist.tpl' => [
        'path' => $root . '/templates/eazyBackup/supportticketslist.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'table toolbar include' => 'includes/ui/table-toolbar.tpl',
            'semantic button marker' => 'class="eb-btn eb-btn-primary"',
        ],
        'forbidden' => [
            'legacy page shell bundle' => 'rounded-3xl border border-slate-800/80 bg-slate-950/80',
            'legacy amber cta bundle' => 'bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500',
        ],
    ],
    'clientareainvoices.tpl' => [
        'path' => $root . '/templates/eazyBackup/clientareainvoices.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'table toolbar include' => 'includes/ui/table-toolbar.tpl',
            'semantic badge marker' => 'class="eb-badge eb-badge--success"',
        ],
        'forbidden' => [
            'legacy page shell bundle' => 'rounded-3xl border border-slate-800/80 bg-slate-950/80',
        ],
    ],
    'clientareaproductdetails.tpl' => [
        'path' => $root . '/templates/eazyBackup/clientareaproductdetails.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'stat card marker' => 'class="eb-stat-card"',
        ],
        'forbidden' => [
            'legacy light shell' => 'bg-white rounded-lg shadow p-8',
        ],
    ],
    'login.tpl' => [
        'path' => $root . '/templates/eazyBackup/login.tpl',
        'markers' => [
            'auth shell include' => 'includes/ui/auth-shell.tpl',
            'auth title marker' => 'class="eb-auth-title"',
            'semantic submit button' => 'class="eb-btn eb-btn-primary w-full rounded-full py-2.5"',
        ],
        'forbidden' => [
            'legacy auth card bundle' => 'rounded-3xl border border-slate-800/80 bg-slate-950/80',
        ],
    ],
    'clientareahome.tpl' => [
        'path' => $root . '/templates/eazyBackup/clientareahome.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'home tile marker' => 'class="eb-home-tile"',
            'home panel marker' => 'class="eb-home-panel',
        ],
    ],
    'clientareadomains.tpl' => [
        'path' => $root . '/templates/eazyBackup/clientareadomains.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'domains toolbar marker' => 'setBulkAction',
            'domains table marker' => 'class="eb-table"',
        ],
        'forbidden' => [
            'legacy table class' => 'class="table table-list',
            'legacy bootstrap buttons' => 'class="btn btn-default',
        ],
    ],
    'clientareadomaindns.tpl' => [
        'path' => $root . '/templates/eazyBackup/clientareadomaindns.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'dns table marker' => 'class="eb-table"',
            'dns input marker' => 'class="eb-input"',
        ],
        'forbidden' => [
            'legacy striped table' => 'class="table table-striped"',
        ],
    ],
    'clientareaemails.tpl' => [
        'path' => $root . '/templates/eazyBackup/clientareaemails.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'emails table marker' => 'class="eb-table"',
            'view message button marker' => 'class="eb-btn eb-btn-info eb-btn-xs',
        ],
        'forbidden' => [
            'legacy info button' => 'class="btn btn-info btn-sm',
        ],
    ],
    'announcements.tpl' => [
        'path' => $root . '/templates/eazyBackup/announcements.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'content card marker' => 'class="eb-content-card"',
        ],
        'forbidden' => [
            'legacy bootstrap card' => 'class="card"',
        ],
    ],
    'downloads.tpl' => [
        'path' => $root . '/templates/eazyBackup/downloads.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'downloads search marker' => 'class="eb-input"',
            'list item marker' => 'class="eb-list-item"',
        ],
        'forbidden' => [
            'legacy input group' => 'input-group input-group-lg',
            'legacy bootstrap card' => 'class="card"',
        ],
    ],
    'downloadscat.tpl' => [
        'path' => $root . '/templates/eazyBackup/downloadscat.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'downloads category list marker' => 'class="eb-list-item"',
        ],
        'forbidden' => [
            'legacy bootstrap card' => 'class="card"',
            'legacy default button' => 'class="btn btn-default',
        ],
    ],
    'password-reset-container.tpl' => [
        'path' => $root . '/templates/eazyBackup/password-reset-container.tpl',
        'markers' => [
            'auth shell include' => 'includes/ui/auth-shell.tpl',
        ],
    ],
    'addon nfr-apply.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/clientarea/nfr-apply.tpl',
        'markers' => [
            'page shell include' => 'templates/eazyBackup/includes/ui/page-shell.tpl',
            'section title marker' => 'class="eb-section-title"',
            'input marker' => 'class="eb-input',
            'primary button marker' => 'class="eb-btn eb-btn-primary"',
        ],
        'forbidden' => [
            'legacy raw token shell' => 'min-h-screen bg-[rgb(var(--bg-page))]',
            'legacy raw accent button' => 'bg-[rgb(var(--accent))]',
        ],
    ],
    'addon notify-settings.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/clientarea/notify-settings.tpl',
        'markers' => [
            'page shell include' => 'templates/eazyBackup/includes/ui/page-shell.tpl',
            'profile nav include' => 'includes/profile-nav.tpl',
            'toggle track marker' => 'class="eb-toggle-track',
            'primary button marker' => 'class="eb-btn eb-btn-primary"',
        ],
        'forbidden' => [
            'legacy gray shell' => 'min-h-screen bg-gray-700 text-gray-100',
            'legacy inline toggle color' => "btn.style.backgroundColor",
        ],
    ],
    'addon sidebar.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/clientarea/partials/sidebar.tpl',
        'markers' => [
            'semantic sidebar root' => 'class="eb-sidebar',
            'semantic sidebar link' => 'class="eb-sidebar-link',
            'sidebar divider' => 'class="eb-sidebar-divider"',
        ],
        'forbidden' => [
            'legacy sidebar shell bundle' => 'border-r border-slate-800/80 bg-slate-900/50',
        ],
    ],
    'addon dashboard.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/clientarea/dashboard.tpl',
        'markers' => [
            'page shell marker' => 'class="eb-page"',
            'panel shell marker' => 'class="eb-panel !p-0"',
            'app shell marker' => 'class="eb-app-shell"',
            'app header title' => 'class="eb-app-header-title"',
        ],
        'forbidden' => [
            'legacy page shell bundle' => 'min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden',
        ],
    ],
    'addon vaults.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/clientarea/vaults.tpl',
        'markers' => [
            'page shell marker' => 'class="eb-page"',
            'panel shell marker' => 'class="eb-panel !p-0"',
            'app shell marker' => 'class="eb-app-shell"',
            'app header title' => 'class="eb-app-header-title"',
        ],
        'forbidden' => [
            'legacy page shell bundle' => 'min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden',
        ],
    ],
    'addon includes job-report-modal.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/includes/job-report-modal.tpl',
        'markers' => [
            'compatibility note' => 'Legacy path retained for compatibility',
            'console modal include' => 'templates/console/partials/job-report-modal.tpl',
        ],
    ],
    'addon console job-logs-global.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/console/job-logs-global.tpl',
        'markers' => [
            'page shell marker' => 'class="eb-page"',
            'panel shell marker' => 'class="eb-panel !p-0"',
            'table shell marker' => 'eb-table-shell',
            'modal include' => 'templates/console/partials/job-report-modal.tpl',
        ],
        'forbidden' => [
            'legacy page shell bundle' => 'min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden',
        ],
    ],
    'addon console user-profile.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/console/user-profile.tpl',
        'markers' => [
            'page shell marker' => 'class="eb-page"',
            'panel shell marker' => 'class="eb-panel !p-0"',
            'app shell marker' => 'class="eb-app-shell"',
            'drawer marker' => 'eb-drawer eb-drawer--narrow',
            'modal marker' => 'eb-modal eb-modal--confirm',
        ],
        'forbidden' => [
            'legacy page shell bundle' => 'min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden',
            'legacy authless reset drawer' => 'class="fixed inset-y-0 right-0 z-[10060] w-full sm:max-w-[440px] bg-slate-950/95',
        ],
    ],
    'addon console partial job-report-modal.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/console/partials/job-report-modal.tpl',
        'markers' => [
            'modal surface marker' => 'class="eb-modal',
            'table shell marker' => 'class="eb-table-shell',
            'select marker' => 'class="eb-select',
        ],
    ],
    'addon console partial upcoming-charges.tpl' => [
        'path' => $root . '/modules/addons/eazybackup/templates/console/partials/upcoming-charges.tpl',
        'markers' => [
            'subpanel marker' => 'class="eb-subpanel',
            'section title marker' => 'class="eb-section-title"',
            'ghost dismiss button' => 'class="eb-btn eb-btn-ghost eb-btn-xs',
        ],
    ],
    'cloudstorage core_nav.tpl' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/partials/core_nav.tpl',
        'markers' => [
            'semantic nav tab marker' => 'class="eb-tab',
            'users nav href' => 'page=users',
        ],
    ],
    'cloudstorage dashboard.tpl' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/dashboard.tpl',
        'markers' => [
            'page shell marker' => 'class="eb-page"',
            'core nav include' => 'core_nav.tpl',
            'page title marker' => 'class="eb-page-title"',
            'app card marker' => 'class="eb-app-card"',
        ],
        'forbidden' => [
            'legacy shell bundle' => 'min-h-screen bg-slate-950 text-gray-300',
        ],
    ],
    'cloudstorage access_keys.tpl' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/access_keys.tpl',
        'markers' => [
            'page shell marker' => 'class="eb-page"',
            'page title marker' => 'class="eb-page-title"',
            'subpanel marker' => 'class="eb-subpanel',
            'modal marker' => 'class="eb-modal',
            'drawer marker' => 'class="absolute right-0 top-0 h-full eb-drawer',
        ],
        'forbidden' => [
            'legacy shell bundle' => 'min-h-screen bg-slate-950 text-gray-300',
            'legacy create modal shell' => 'bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6',
        ],
    ],
    'cloudstorage billing.tpl' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/billing.tpl',
        'markers' => [
            'page shell marker' => 'class="eb-page"',
            'page title marker' => 'class="eb-page-title"',
            'app card marker' => 'class="eb-app-card',
            'secondary icon button marker' => 'class="eb-btn eb-btn-secondary eb-btn-icon"',
        ],
        'forbidden' => [
            'legacy shell bundle' => 'min-h-screen bg-slate-950 text-gray-300',
        ],
    ],
    'cloudstorage history.tpl' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/history.tpl',
        'markers' => [
            'page shell marker' => 'class="eb-page"',
            'page title marker' => 'class="eb-page-title"',
            'subpanel marker' => 'class="eb-subpanel',
            'export menu marker' => 'class="eb-menu absolute right-0',
        ],
        'forbidden' => [
            'stray rewritten marker' => '</rewritten_file>',
        ],
    ],
    'cloudstorage users.tpl' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/users.tpl',
        'markers' => [
            'page shell marker' => 'class="eb-page"',
            'page title marker' => 'class="eb-page-title"',
            'drawer marker' => 'class="absolute right-0 top-0 h-full eb-drawer',
            'table shell marker' => 'class="eb-table-shell',
        ],
        'forbidden' => [
            'legacy shell bundle' => 'min-h-screen bg-slate-950 text-gray-300',
            'legacy nav pills' => 'inline-flex rounded-full bg-slate-900/80 p-1',
        ],
    ],
    'cloudstorage buckets.tpl' => [
        'path' => $root . '/modules/addons/cloudstorage/templates/buckets.tpl',
        'markers' => [
            'page shell marker' => 'class="eb-page"',
            'page title marker' => 'class="eb-page-title"',
            'core nav include' => 'core_nav.tpl',
            'toolbar search marker' => 'class="eb-input w-full"',
        ],
        'forbidden' => [
            'legacy shell bundle' => 'min-h-screen bg-slate-950 text-gray-300 overflow-x-hidden',
        ],
    ],
    'supportticketsubmit-steptwo.tpl' => [
        'path' => $root . '/templates/eazyBackup/supportticketsubmit-steptwo.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'textarea marker' => 'class="eb-textarea"',
            'customfields include' => 'supportticketsubmit-customfields.tpl',
        ],
        'forbidden' => [
            'legacy page shell bundle' => 'rounded-3xl border border-slate-800/80 bg-slate-950/80',
        ],
    ],
    'viewticket.tpl' => [
        'path' => $root . '/templates/eazyBackup/viewticket.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'danger close button' => 'class="eb-btn eb-btn-danger"',
            'subpanel marker' => 'class="eb-subpanel"',
        ],
        'forbidden' => [
            'legacy page shell bundle' => 'rounded-3xl border border-slate-800/80 bg-slate-950/80',
        ],
    ],
    'clientareaquotes.tpl' => [
        'path' => $root . '/templates/eazyBackup/clientareaquotes.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'table toolbar include' => 'includes/ui/table-toolbar.tpl',
            'badge marker' => 'class="eb-badge',
        ],
        'forbidden' => [
            'legacy page shell bundle' => 'rounded-3xl border border-slate-800/80 bg-slate-950/80',
        ],
    ],
    'clientareadetails.tpl' => [
        'path' => $root . '/templates/eazyBackup/clientareadetails.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'profile nav include' => 'includes/profile-nav.tpl',
            'select marker' => 'class="eb-select"',
        ],
        'forbidden' => [
            'legacy gray shell' => 'bg-gray-700 text-gray-100',
            'legacy content card' => 'bg-slate-800 shadow rounded-b-xl',
        ],
    ],
    'user-password.tpl' => [
        'path' => $root . '/templates/eazyBackup/user-password.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'flash message include' => 'includes/flashmessage-darkmode.tpl',
            'primary button marker' => 'class="eb-btn eb-btn-primary"',
        ],
        'forbidden' => [
            'legacy gray shell' => 'bg-gray-700 text-gray-100',
        ],
    ],
    'user-security.tpl' => [
        'path' => $root . '/templates/eazyBackup/user-security.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'client area security include' => 'clientareasecurity.tpl',
            'modal marker' => 'id="modalAjax"',
        ],
        'forbidden' => [
            'legacy gray shell' => 'bg-gray-700 text-gray-100',
            'legacy btn css block' => '.btn-success {',
        ],
    ],
    'account-paymentmethods.tpl' => [
        'path' => $root . '/templates/eazyBackup/account-paymentmethods.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'delete button class' => 'class="btn-delete eb-btn eb-btn-danger',
            'confirmation modal marker' => 'id="modalPaymentMethodDeleteConfirmation"',
        ],
        'forbidden' => [
            'legacy gray shell' => 'bg-gray-700 text-gray-100',
            'legacy content card' => 'bg-slate-800 shadow rounded-b-xl',
        ],
    ],
    'account-paymentmethods-manage.tpl' => [
        'path' => $root . '/templates/eazyBackup/account-paymentmethods-manage.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'profile nav include' => 'includes/profile-nav.tpl',
            'payment method title capture' => 'capture name=ebPaymentMethodTitle',
        ],
        'forbidden' => [
            'legacy min-h screen shell' => 'min-h-screen bg-slate-950 text-slate-200',
        ],
    ],
    'account-contacts-manage.tpl' => [
        'path' => $root . '/templates/eazyBackup/account-contacts-manage.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'delete modal marker' => 'id="modalDeleteContact"',
            'profile nav include' => 'includes/profile-nav.tpl',
        ],
        'forbidden' => [
            'legacy gray shell' => 'bg-gray-700 text-gray-100',
        ],
    ],
    'account-user-management.tpl' => [
        'path' => $root . '/templates/eazyBackup/account-user-management.tpl',
        'markers' => [
            'page shell include' => 'includes/ui/page-shell.tpl',
            'alpine state marker' => 'invitePermissions: false',
            'remove modal marker' => 'removeUserModal',
        ],
        'forbidden' => [
            'legacy gray shell' => 'bg-gray-700 text-gray-100',
            'legacy user modal show call' => ".modal('show')",
        ],
    ],
    'starter-full-page.tpl' => [
        'path' => $root . '/templates/eazyBackup/includes/ui/starter-full-page.tpl',
        'markers' => [
            'starter page-shell usage' => 'includes/ui/page-shell.tpl',
            'starter page-header usage' => 'includes/ui/page-header.tpl',
        ],
    ],
];

$failures = [];
foreach ($targets as $name => $target) {
    $source = @file_get_contents($target['path']);
    if ($source === false) {
        $failures[] = "FAIL: unable to read {$name}";
        continue;
    }

    foreach ($target['markers'] as $markerName => $needle) {
        if (strpos($source, $needle) === false) {
            $failures[] = "FAIL: {$name} missing {$markerName}";
        }
    }

    foreach (($target['forbidden'] ?? []) as $markerName => $needle) {
        if (strpos($source, $needle) !== false) {
            $failures[] = "FAIL: {$name} still contains {$markerName}";
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . PHP_EOL;
    }
    exit(1);
}

echo "clientarea-theme-contract-ok\n";
exit(0);
