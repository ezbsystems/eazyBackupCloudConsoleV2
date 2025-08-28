<?php

use WHMCS\Database\Capsule;

add_hook('AdminAreaHeadOutput', 112222, function ($vars) {
    if ($vars["filename"] == "clientsservices") {
        global $whmcs;

        $return = "";
        $userid = $whmcs->get_req_var('userid');
        $service_id = null;

        if (empty($whmcs->get_req_var('id'))) {
            $service_id = $whmcs->get_req_var('productselect');
        } else {
            $service_id = $whmcs->get_req_var('id');
        }

        if (empty($whmcs->get_req_var('id')) && empty($whmcs->get_req_var('productselect'))) {
            $service = Capsule::table('tblhosting')->select('id')->where('userid', $userid)->first();
            $service_id = $service->id;
        }

        $service = Capsule::table('tblhosting')->find($service_id);

        if (!$service) {
            return $return;
        }

        $username = $service->username;
        if (!$username) {
            return $return;
        }

        require_once __DIR__ . "/../../modules/servers/comet/functions.php";
        require_once __DIR__ . "/../../modules/servers/comet/summary_functions.php";

        $params = comet_ProductParams($service->packageid);
        $params['username'] = $username;

        if ($params['serverhostname'] === null || $params['serverusername'] === null) {
            return $return;
        }

        $user = comet_User($params);
        if (is_string($user)) {
            return $return;
        }

        // Retrieve existing usage data
        $deviceCount = comet_DeviceCount($user);
        $totalStorageUsed = getUserStorage($username);
        $protectedItemsSummary = getUserProtectedItemsSummary($username);
        $totalVmCount = $protectedItemsSummary['totalVmCount'];
        $totalAccountsCount = $protectedItemsSummary['totalAccountsCount'];

        // New approach: count engine types directly from $user->Sources
        function countEngineTypesFromUser($user, $engineKey) {
            $count = 0;
            if (isset($user->Sources) && is_array($user->Sources)) {
                foreach ($user->Sources as $source) {
                    // Compare engine type in a case-insensitive manner
                    if (isset($source->Engine) && strtolower($source->Engine) === strtolower($engineKey)) {
                        $count++;
                    }
                }
            }
            return $count;
        }

        $diskImageCount = countEngineTypesFromUser($user, 'engine1/windisk');
        $fileFolderCount = countEngineTypesFromUser($user, 'engine1/file');

        $return .= '<script type="text/javascript">
            jQuery(document).ready(function() {
                var discountRow = $("#servicecontent table.form tr").filter(":nth-child(9)");
                discountRow.after("<tr><td class=\"fieldlabel\" width=\"20%\">Hyper-V VMs</td><td class=\"fieldarea\" width=\"30%\"><div style=\"width: 100%\"><div class=\"service-field-inline\"><input type=\"text\" name=\"total_vm_count\" size=\"20\" class=\"form-control input-100\" value=\"' . $totalVmCount . '\" readonly></div></div></td></tr>");
                discountRow.after("<tr><td class=\"fieldlabel\" width=\"20%\">MS365 Accounts</td><td class=\"fieldarea\" width=\"30%\"><div style=\"width: 100%\"><div class=\"service-field-inline\"><input type=\"text\" name=\"total_ms_365_accounts\" size=\"20\" class=\"form-control input-100\" value=\"' . $totalAccountsCount . '\" readonly></div></div></td></tr>");
                discountRow.after("<tr><td class=\"fieldlabel\" width=\"20%\">Storage Used</td><td class=\"fieldarea\" width=\"30%\"><div style=\"width: 100%\"><div class=\"service-field-inline\"><input type=\"text\" name=\"total_storage_used\" size=\"20\" class=\"form-control input-100\" value=\"' . $totalStorageUsed . '\" readonly></div></div></td></tr>");
                discountRow.after("<tr><td class=\"fieldlabel\" width=\"20%\">Device Count</td><td class=\"fieldarea\" width=\"30%\"><div style=\"width: 100%\"><div class=\"service-field-inline\"><input type=\"text\" name=\"comet_device_count\" size=\"20\" class=\"form-control input-100\" value=\"' . $deviceCount . '\" readonly></div></div></td></tr>");
                discountRow.after("<tr><td class=\"fieldlabel\" width=\"20%\">Disk Image Count</td><td class=\"fieldarea\" width=\"30%\"><div style=\"width: 100%\"><div class=\"service-field-inline\"><input type=\"text\" name=\"disk_image_count\" size=\"20\" class=\"form-control input-100\" value=\"' . $diskImageCount . '\" readonly></div></div></td></tr>");
                discountRow.after("<tr><td class=\"fieldlabel\" width=\"20%\">File Folder Count</td><td class=\"fieldarea\" width=\"30%\"><div style=\"width: 100%\"><div class=\"service-field-inline\"><input type=\"text\" name=\"file_folder_count\" size=\"20\" class=\"form-control input-100\" value=\"' . $fileFolderCount . '\" readonly></div></div></td></tr>");
            });
        </script>';

        return $return;
    }
});
