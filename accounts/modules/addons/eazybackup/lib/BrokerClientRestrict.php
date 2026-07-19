<?php

/**
 * Broker client-group page restrictions.
 *
 * Clients in admin-configured broker groups cannot access services, billing,
 * create-order, or e3 billing/history pages.
 */

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Configured broker client group IDs from addon setting brokergroups.
 *
 * @return int[]
 */
function eazybackup_broker_group_ids(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $cached = [];
    try {
        $csv = (string)(Capsule::table('tbladdonmodules')
            ->where('module', 'eazybackup')
            ->where('setting', 'brokergroups')
            ->value('value') ?? '');
        if ($csv === '') {
            return $cached;
        }
        foreach (explode(',', $csv) as $v) {
            $id = (int)trim($v);
            if ($id > 0) {
                $cached[$id] = $id;
            }
        }
        $cached = array_values($cached);
    } catch (\Throwable $__) {
        $cached = [];
    }

    return $cached;
}

/**
 * Whether the given WHMCS client belongs to a broker group.
 */
function eazybackup_client_is_broker(int $clientId): bool
{
    if ($clientId <= 0) {
        return false;
    }

    $allowed = eazybackup_broker_group_ids();
    if (empty($allowed)) {
        return false;
    }

    try {
        $gid = (int)(Capsule::table('tblclients')->where('id', $clientId)->value('groupid') ?? 0);
    } catch (\Throwable $__) {
        return false;
    }

    if ($gid <= 0) {
        return false;
    }

    return in_array($gid, $allowed, true);
}

/**
 * Client-area actions blocked for broker clients (services family).
 *
 * @return string[]
 */
function eazybackup_broker_denied_clientarea_actions(): array
{
    return [
        'services',
        'products',
        'productdetails',
        'cancel',
        'upgrades',
    ];
}

/**
 * Client-area actions blocked for broker clients (billing family).
 *
 * @return string[]
 */
function eazybackup_broker_denied_billing_actions(): array
{
    return [
        'invoices',
        'masspay',
        'quotes',
        'addfunds',
        'creditcard',
        'paymentmethods',
    ];
}

/**
 * Whether the current HTTP request targets a broker-restricted page.
 */
function eazybackup_broker_request_is_denied(): bool
{
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

    if ($script === 'viewinvoice.php') {
        return true;
    }

    $module = isset($_GET['m']) ? (string)$_GET['m'] : '';
    $action = isset($_GET['a']) ? (string)$_GET['a'] : '';
    $page = isset($_GET['page']) ? (string)$_GET['page'] : '';

    if ($module === 'eazybackup' && $action === 'createorder') {
        return true;
    }

    if ($module === 'cloudstorage' && in_array($page, ['billing', 'history'], true)) {
        return true;
    }

    if ($script === 'clientarea.php' || $script === 'index.php') {
        $clientAction = isset($_GET['action']) ? strtolower((string)$_GET['action']) : '';
        if ($clientAction === '') {
            return false;
        }

        $denied = array_merge(
            eazybackup_broker_denied_clientarea_actions(),
            eazybackup_broker_denied_billing_actions()
        );

        return in_array($clientAction, $denied, true);
    }

    return false;
}

/**
 * Redirect broker clients to the eazyBackup dashboard.
 */
function eazybackup_broker_redirect_dashboard(): void
{
    header('Location: index.php?m=eazybackup&a=dashboard');
    exit;
}
