<?php
use WHMCS\Database\Capsule;

add_hook('ClientAreaPageViewInvoice', 1, function($vars) {
    $invoiceId = (int)($vars['invoiceid'] ?? 0);
    $invoiceUserId = 0;
    if ($invoiceId > 0) {
        try {
            $invoiceUserId = (int) Capsule::table('tblinvoices')->where('id', $invoiceId)->value('userid');
        } catch (\Throwable $e) {
            $invoiceUserId = 0;
        }
    }

    if (isset($vars['invoiceitems']) && is_array($vars['invoiceitems'])) {
        // Log the start of hook execution for this invoice
        if ($invoiceId > 0) {
            logActivity("ServiceUsernameHook: Processing invoice ID " . $invoiceId, 0);
        }

        foreach ($vars['invoiceitems'] as $key => $item) {
            $foundUsername = null;

            $itemType = (string)($item['type'] ?? '');
            $relidRaw = $item['relid'] ?? null;
            $serviceId = null;

            // Only attempt to resolve usernames for Hosting/Upgrade items
            if (is_scalar($relidRaw) && ctype_digit((string)$relidRaw)) {
                $relidInt = (int)$relidRaw;

                if ($itemType === 'Hosting') {
                    $serviceId = $relidInt;
                } elseif ($itemType === 'Upgrade') {
                    // For Upgrade items, relid is tblupgrades.id; map to the underlying service id.
                    $serviceRelid = Capsule::table('tblupgrades')
                        ->where('id', $relidInt)
                        ->where('type', 'configoptions')
                        ->value('relid');
                    if ($serviceRelid && ctype_digit((string)$serviceRelid)) {
                        $serviceId = (int)$serviceRelid;
                    }
                }
            }

            if ($serviceId) {
                $q = Capsule::table('tblhosting')->where('id', $serviceId);
                // Critical: enforce invoice owner match to prevent cross-client username leakage
                if ($invoiceUserId > 0) {
                    $q->where('userid', $invoiceUserId);
                }
                $foundUsername = (string)($q->value('username') ?? '');
                if ($foundUsername === '') {
                    $foundUsername = null;
                }
            }
            
            if ($foundUsername) {
                $vars['invoiceitems'][$key]['master_username'] = $foundUsername;
            }
        }
    }
    return $vars;
});