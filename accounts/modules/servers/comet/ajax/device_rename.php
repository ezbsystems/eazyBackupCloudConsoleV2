<?php

use \WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . "/../functions.php";


if (!empty($_POST)) {

    $serviceId = $_POST['serviceId'];
    $deviceId = $_POST['deviceId'];
    $devicename = $_POST['devicename'];
    $pid = Capsule::table("tblhosting")->where(["id" => $serviceId])->value('packageid');

    if ($pid) {
        $serverDetail = comet_ProductParams($pid);

        $params = [];
        $params['serverhttpprefix'] = $serverDetail['serverhttpprefix'];
        $params['serverhostname'] = $serverDetail['serverhostname'];
        $params['serverusername'] = $serverDetail['serverusername'];
        $params['serverpassword'] = $serverDetail['serverpassword'];

        $username = Capsule::table("tblhosting")->where(["id" => $serviceId])->value('username');
        $params['username'] = $username;


        $data = comet_Server($params)->AdminGetUserProfile($params['username']);

        $new_device_name = $devicename;
        $data->Devices[$deviceId]->FriendlyName = $new_device_name;
        $RenameDevice = comet_Server($params)->AdminSetUserProfile($params['username'],  $data);
        comet_ClearUserCache();
        echo json_encode($RenameDevice);
    }
}
