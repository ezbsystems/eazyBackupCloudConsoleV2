<?php

use \WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . "/../functions.php";

if (!empty($_POST)) {
    $ids = [];


    $optionName = $_POST['optionname'];
    if (!empty($optionName)) {

        $AdditionalDeviceID = Capsule::table('tblproductconfigoptions')->where([["optionname", "=", $optionName]])->get('id');


        if (!empty($AdditionalDeviceID)) {

            foreach ($AdditionalDeviceID as $key => $item) {
                $ids['id'][] = $item->id;
            }
        }
    }

    echo json_encode($ids);
}
