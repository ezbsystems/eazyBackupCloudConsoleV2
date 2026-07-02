<?php

use \WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . "/../functions.php";

if (!empty($_POST)) {

    $emailReporting = boolval($_POST['emailreporting']) ? true : false;

    $serviceId = $_POST['serviceId'];
    $accountName = $_POST['accountname'];
    $MaximumDevices = $_POST['MaximumDevices'];
    $emailRecord = $_POST['email'];

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
        // echo '<pre>';
        // print_r($data);
        // echo '<pre>';
        // die;



        $data->Emails = $emailRecord;
        $data->AccountName = $accountName;
        $data->SendEmailReports = $emailReporting;
        $data->MaximumDevices = intval($MaximumDevices);
        //$data->RequirePasswordChange = true;

        $updateData = comet_Server($params)->AdminSetUserProfile($params['username'],  $data);
        comet_ClearUserCache();

        echo json_encode($updateData);
    }
}
