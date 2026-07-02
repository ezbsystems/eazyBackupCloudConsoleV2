<?php

use \WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . "/../functions.php";

if (!empty($_POST)) {
    $serviceId = $_POST['serviceId'];
    $emailRecord = $_POST['email'] ?? [];
    if (!is_array($emailRecord)) {
        // Ensure it's an array, in case there's only one string
        $emailRecord = (array)$emailRecord;
    }

    error_log("Service ID: " . $serviceId);
    error_log("Email records received: " . json_encode($emailRecord));

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

        error_log("User profile before update: " . json_encode($data));

        $data->Emails = $emailRecord;
        $updateData = comet_Server($params)->AdminSetUserProfile($params['username'], $data);

        error_log("User profile after update: " . json_encode($updateData));

        comet_ClearUserCache();
        echo json_encode($updateData);
    }
}
