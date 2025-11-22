<?php

use \WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . "/../functions.php";

if (!empty($_POST)) {

    $id = $_POST['serviceId'];
    $type = $_POST['package'];

    //$ProductUpgrades = "not-available";
    $ProductUpgrades = [
        'status' => 'not-available'
    ];

    $service = new WHMCS\Service($id, WHMCS\Session::get("uid"));
    //var_dump($service->getAllowProductUpgrades());

    if ($service->getAllowProductUpgrades() == true) {
        //$ProductUpgrades = "available";
        $ProductUpgrades = [
            'status' => 'available'
        ];
    }




    echo json_encode($ProductUpgrades);
}
