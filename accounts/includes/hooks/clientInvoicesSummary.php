<?php

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Expose lastInvoiceTotal, lastInvoiceDate, defaultPaymentMethodLabel,
 * and currentBalanceFormatted to client area templates on the Invoices page.
 */
add_hook('ClientAreaPageInvoices', 1, function (array $vars) {
    $clientId = 0;

    // Resolve current client ID
    if (isset($_SESSION['uid']) && (int) $_SESSION['uid'] > 0) {
        $clientId = (int) $_SESSION['uid'];
    } elseif (!empty($vars['clientsdetails']['id'])) {
        $clientId = (int) $vars['clientsdetails']['id'];
    }

    $lastInvoiceTotal           = '';
    $lastInvoiceDate            = '';
    $defaultPaymentMethodLabel  = '';
    $currentBalanceRaw          = 0.0;
    $currentBalanceFormatted    = '';

    if ($clientId > 0) {
        try {
            // Most recent non-draft, non-cancelled invoice for this client
            $last = Capsule::table('tblinvoices')
                ->where('userid', $clientId)
                ->whereNotIn('status', ['Draft', 'Cancelled'])
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first(['id', 'invoicenum', 'total', 'date']);

            if ($last) {
                // Total – use WHMCS formatter if available, fallback otherwise
                if (function_exists('formatCurrency')) {
                    $lastInvoiceTotal = formatCurrency($last->total);
                } else {
                    $lastInvoiceTotal = number_format((float) $last->total, 2);
                }

                // Friendly date string, e.g. "March 5, 2024"
                $ts = strtotime((string) $last->date);
                if ($ts > 0) {
                    $lastInvoiceDate = date('F j, Y', $ts);
                }
            }

            // Current outstanding balance
            // Prefer stats from WHMCS core if available; otherwise fall back to a direct query.
            if (!empty($vars['clientsstats']) && is_array($vars['clientsstats']) && isset($vars['clientsstats']['totalbalance'])) {
                $currentBalanceRaw = (float) $vars['clientsstats']['totalbalance'];
            } else {
                $currentBalanceRaw = (float) Capsule::table('tblinvoices')
                    ->where('userid', $clientId)
                    ->where('balance', '>', 0)
                    ->whereNotIn('status', ['Draft', 'Cancelled'])
                    ->sum('balance');
            }

            if (function_exists('formatCurrency')) {
                $currentBalanceFormatted = formatCurrency($currentBalanceRaw);
            } else {
                $currentBalanceFormatted = number_format($currentBalanceRaw, 2);
            }

            // Default payment method label
            $gatewaySystemName = Capsule::table('tblclients')
                ->where('id', $clientId)
                ->value('defaultgateway');

            if ($gatewaySystemName) {
                // Try to resolve friendly name from payment gateway settings
                $gatewayName = Capsule::table('tblpaymentgateways')
                    ->where('gateway', $gatewaySystemName)
                    ->where('setting', 'name')
                    ->value('value');

                if (is_string($gatewayName) && $gatewayName !== '') {
                    $defaultPaymentMethodLabel = $gatewayName;
                } else {
                    $defaultPaymentMethodLabel = ucfirst($gatewaySystemName);
                }
            } else {
                // Fallback when no specific default gateway is set
                $defaultPaymentMethodLabel = 'On invoice / manual payment';
            }
        } catch (\Throwable $e) {
            // Fail silently; template will just render empty values
        }
    }

    return [
        'lastInvoiceTotal'           => $lastInvoiceTotal,
        'lastInvoiceDate'            => $lastInvoiceDate,
        'defaultPaymentMethodLabel'  => $defaultPaymentMethodLabel,
        'currentBalanceFormatted'    => $currentBalanceFormatted,
        'currentBalanceRaw'          => $currentBalanceRaw,
    ];
});


