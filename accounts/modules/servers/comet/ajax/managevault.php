<?php

use \WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . "/../functions.php";

if (!empty($_POST)) {

    $serviceId               = $_POST['serviceId'];
    $storageVaultId          = $_POST['storageVaultId'];
    $storageVaultSize        = $_POST['storageVaultSize'];
    $storageVaultName        = $_POST['storageVaultName'];
    $storageVaultstandardSize= $_POST['storageVaultstandardSize'];
    $storageUnlimited        = $_POST['storageUnlimited'];

    // If "1", unlimited => StorageLimitEnabled = false
    // If "0", limited => StorageLimitEnabled = true
    $storageunlimitedenable  = ($storageUnlimited == '1') ? false : true;

    /**
     * 1kB = 1024 BYTES
     * 1MB = 1024*1024 = 1048576 BYTES
     * 1GB = 1024*1024*1024 = 1073741824 BYTES
     * 1TB = 1024*1024*1024*1024 = 1099511627776 BYTES
     */
    $sizeconversion = 0;
    switch ($storageVaultstandardSize) {
        case "B":
            $sizeconversion = 1;
            break;
        case "KB":
            $sizeconversion = 1024;
            break;
        case "MB":
            $sizeconversion = 1048576;
            break;
        case "GB":
            $sizeconversion = 1073741824;
            break;
        case "TB":
            $sizeconversion = 1099511627776;
            break;
        default:
            // Optional: Return an error or set default
            // echo "Invalid or empty storage unit specified.";
            // exit;
            $sizeconversion = 1; // fallback
    }

    if ($storageunlimitedenable === false) {
        // "Unlimited" scenario
        $actualVaultsizeinBytes = 0;  // or omit setting if your server knows "unlimited" differently
    } else {
        // "Limited" scenario - do normal size math
        // Safely parse $storageVaultSize as float (or int)
        if (!is_numeric($storageVaultSize)) {
            $storageVaultSize = 0; // Or handle as error
        }

        $storageVaultSize = floatval($storageVaultSize);
        $actualVaultsizeinBytes = $sizeconversion * $storageVaultSize;
    }

    // ... The rest is the same ...
    $pid = Capsule::table("tblhosting")
                 ->where(["id" => $serviceId])
                 ->value('packageid');

    if ($pid) {
        $serverDetail = comet_ProductParams($pid);

        $params = [];
        $params['serverhttpprefix'] = $serverDetail['serverhttpprefix'];
        $params['serverhostname']   = $serverDetail['serverhostname'];
        $params['serverusername']   = $serverDetail['serverusername'];
        $params['serverpassword']   = $serverDetail['serverpassword'];

        $username = Capsule::table("tblhosting")
                           ->where(["id" => $serviceId])
                           ->value('username');
        $params['username'] = $username;

        $data = comet_Server($params)->AdminGetUserProfile($params['username']);

        // Update destination details
        $data->Destinations[$storageVaultId]->Description         = $storageVaultName;
        $data->Destinations[$storageVaultId]->StorageLimitEnabled = $storageunlimitedenable;
        $data->Destinations[$storageVaultId]->StorageLimitBytes   = $actualVaultsizeinBytes;

        $updateData = comet_Server($params)
            ->AdminSetUserProfile($params['username'],  $data);

        $testing = comet_Server($params)->AdminGetUserProfile($params['username']);
        comet_ClearUserCache();

        echo json_encode($updateData);
    }
}
