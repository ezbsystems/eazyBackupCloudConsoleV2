<?php

use WHMCS\Database\Capsule;

// Expose $isMspClient to all client area templates (e.g., header.tpl)
add_hook('ClientAreaPage', 1, function(array $vars) {
    $isMspClient = false;

    try {
        // Determine current client id
        $clientId = 0;
        if (isset($_SESSION['uid']) && (int)$_SESSION['uid'] > 0) {
            $clientId = (int)$_SESSION['uid'];
        } elseif (!empty($vars['clientsdetails']['id'])) {
            $clientId = (int)$vars['clientsdetails']['id'];
        }

        if ($clientId > 0) {
            // Resolve client's group id
            $gid = (int)(Capsule::table('tblclients')->where('id', $clientId)->value('groupid') ?? 0);

            if ($gid > 0) {
                // Load MSP group ids from addon setting 'msp_client_groups'
                $mspGroupsCsv = (string)(Capsule::table('tbladdonmodules')
                    ->where('module', 'cloudstorage')
                    ->where('setting', 'msp_client_groups')
                    ->value('value') ?? '');

                if ($mspGroupsCsv !== '') {
                    $ids = array_filter(array_map('trim', explode(',', $mspGroupsCsv)), function ($v) { return $v !== ''; });
                    $ids = array_map('intval', $ids);
                    $isMspClient = in_array($gid, $ids, true);
                }
            }
        }
    } catch (\Throwable $__) {
        // Swallow errors; default false is safe
    }

    return [ 'isMspClient' => $isMspClient ];
});


