<?php
use WHMCS\Database\Capsule;

add_hook('ClientAreaPageViewInvoice', 1, function($vars) {
    if (isset($vars['invoiceitems']) && is_array($vars['invoiceitems'])) {
        // Log the start of hook execution for this invoice
        if (isset($vars['invoiceid'])) {
            logActivity("ServiceUsernameHook: Processing invoice ID " . $vars['invoiceid'], 0);
        } else {
            logActivity("ServiceUsernameHook: Processing invoice (ID not found in vars)", 0);
        }

        foreach ($vars['invoiceitems'] as $key => $item) {
            $foundUsername = null;
            $itemDescription = isset($item['description']) ? $item['description'] : 'N/A';
            $itemType = isset($item['type']) ? $item['type'] : 'N/A'; // Get item type if available
            $itemRelid = isset($item['relid']) ? $item['relid'] : 'N/A';

            logActivity("ServiceUsernameHook: Item: '{$itemDescription}', Type: '{$itemType}', RelID: {$itemRelid}", 0);

            // Scenario 1: Item has a relid pointing directly to tblhosting
            if (!empty($item['relid'])) {
                $service = Capsule::table('tblhosting')
                    ->where('id', $item['relid'])
                    ->first();
                if ($service && !empty($service->username)) {
                    $foundUsername = $service->username;
                    logActivity("ServiceUsernameHook: Found username '{$foundUsername}' via direct relid for item '{$itemDescription}'", 0);
                }
            }

            // Scenario 2: Item's relid might be an upgrade ID
            if (!$foundUsername && !empty($item['relid'])) {
                $upgradeRecord = Capsule::table('tblupgrades')
                                    ->where('id', $item['relid'])
                                    ->first();
                if ($upgradeRecord && !empty($upgradeRecord->relid)) {
                    $service = Capsule::table('tblhosting')
                        ->where('id', $upgradeRecord->relid)
                        ->first();
                    if ($service && !empty($service->username)) {
                        $foundUsername = $service->username;
                        logActivity("ServiceUsernameHook: Found username '{$foundUsername}' via upgrade relid for item '{$itemDescription}'", 0);
                    }
                }
            }
            
            if ($foundUsername) {
                $vars['invoiceitems'][$key]['master_username'] = $foundUsername;
            } else {
                logActivity("ServiceUsernameHook: No username found for item '{$itemDescription}'", 0);
            }
        }
    } else {
        logActivity("ServiceUsernameHook: No invoice items found to process.", 0);
    }
    return $vars;
});