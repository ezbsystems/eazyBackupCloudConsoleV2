<?php

use WHMCS\Database\Capsule;

// Expose $isResellerClient to all client area templates (e.g., header.tpl)
add_hook('ClientAreaPage', 1, function(array $vars) {
    $isResellerClient = false;

    try {
        // Determine current client id
        $clientId = 0;
        if (isset($_SESSION['uid']) && (int)$_SESSION['uid'] > 0) {
            $clientId = (int)$_SESSION['uid'];
        } else if (!empty($vars['clientsdetails']['id'])) {
            $clientId = (int)$vars['clientsdetails']['id'];
        }

        if ($clientId > 0) {
            // Resolve client's group id
            $gid = (int)(Capsule::table('tblclients')->where('id', $clientId)->value('groupid') ?? 0);

            if ($gid > 0) {
                // Load reseller group ids from addon setting 'resellergroups'
                $resellerGroupsCsv = (string)(Capsule::table('tbladdonmodules')
                    ->where('module', 'eazybackup')
                    ->where('setting', 'resellergroups')
                    ->value('value') ?? '');

                if ($resellerGroupsCsv !== '') {
                    $ids = array_filter(array_map('trim', explode(',', $resellerGroupsCsv)), function ($v) { return $v !== ''; });
                    $ids = array_map('intval', $ids);
                    $isResellerClient = in_array($gid, $ids, true);
                }
            }
        }
    } catch (\Throwable $__) {
        // Swallow errors; default false is safe
    }

    return [ 'isResellerClient' => $isResellerClient ];
});


