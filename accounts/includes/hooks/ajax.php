<?php

require_once(__DIR__ . "/../../init.php");

use WHMCS\Database\Capsule;

global $whmcs;

try {
    if (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'apply_discount') {

        if ((isset($_POST['discount_per']) && $_POST['discount_per'] == "" && !isset($_POST['token'])) || $_POST['discount_per'] < 0 || !$_POST['discount_per'] || !is_numeric($_POST['discount_per'])) {
            $respose = [
                'status' => false,
                'message' => 'Invalid Discount percentage.'
            ];

            echo json_encode($respose);
            exit;
        }

        if (isset($_POST['discount_per']) && !empty($_POST['discount_per']) && isset($_POST['service_id']) && !empty($_POST['service_id'])) {

            $respose = [
                'status' => false,
                'message' => ''
            ];
            $discount_per = (float) $_POST['discount_per'];
            $service_id = $_POST['service_id'];
            $service = Capsule::table('tblhosting')->find($service_id);

            $new_amount = $service->amount - (($discount_per / 100) * $service->amount);

            if ($new_amount >= 0) {
                $insertData = [
                    "userid" => $service->userid,
                    "serviceid" => $service_id,
                    "beforeDisAmt" => $service->amount,
                    "discount_per" => $discount_per,
                    "nextduedate" => $service->nextduedate
                ];
                $res =  rd_addService($insertData);
                if ($res) {
                    $respose = [
                        'status' => true,
                        'message' => 'Recurring Discount Applied Successfully.'
                    ];
                } else {
                    $respose['message'] = "Recurring Discount Applied Failed, Nothing to Update";
                }
            } else {
                $respose['message'] = "Recurring Amount will be less than zero, it's not acceptable.";
            }

            echo json_encode($respose);
            exit;
        }
    } elseif (isset($_POST['ajax_action']) && $_POST['ajax_action'] == 'remove_discount') {
        $respose = [
            'status' => false,
            'message' => 'Invalid Data.'
        ];
        $nextduedate = $_POST['nextduedate'];

        $timestamp = explode("/", $nextduedate);

        $nextduedate = $timestamp[2] . '-' . $timestamp[1] . '-' . $timestamp[0];
        $serviceid = $_POST['service_id'];


        $whereClause = [
            ['serviceid', '=', $serviceid],
            ['nextduedate', '=', $nextduedate]
        ];

        $discount_data = Capsule::table('mod_rd_discountServices')->where($whereClause)->first();
        if (!empty($discount_data)) {
            $whereClause2 = [
                ['id', '=', $serviceid],
                ['nextduedate', '=', $nextduedate]
            ];

            $updateHosting = Capsule::table('tblhosting')->where($whereClause2)->update(['amount' => $discount_data->beforeDisAmt]);
            $deleteDiscount = Capsule::table('mod_rd_discountServices')->where($whereClause)->delete();

            if ($deleteDiscount) {
                $respose = [
                    'status' => true,
                    'message' => 'Discount Remove.'
                ];
            } else {
                $respose = [
                    'status' => false,
                    'message' => 'Discount remove failed.'
                ];
            }
        }
        echo json_encode($respose);
        exit;
    }
} catch (\Exception $e) {
    logActivity('Error on Recurring Discount: ' . $e->getMessage());
}





function rd_addService($data)
{
    $whereClause = [
        ['serviceid', '=', $data['serviceid']],
        ['nextduedate', '=', $data['nextduedate']]
    ];
    $whereClause2 = [
        ['id', '=', $data['serviceid']],
        ['nextduedate', '=', $data['nextduedate']]
    ];

    $is_apply = Capsule::table('mod_rd_discountServices')->where($whereClause)->first();

    if (empty($is_apply)) {
        $res = Capsule::table('mod_rd_discountServices')->insertGetId($data);

        #update_tblhosting
        global $new_amount;
        $updateHosting = Capsule::table('tblhosting')->where($whereClause2)->update(['amount' => $new_amount]);
    } else {
        $res = Capsule::table('mod_rd_discountServices')->where($whereClause)->update(['discount_per' => $data['discount_per']]);

        global $discount_per;
        $update_amount = $is_apply->beforeDisAmt - (($discount_per / 100) * $is_apply->beforeDisAmt);
        $updateHosting = Capsule::table('tblhosting')->where($whereClause2)->update(['amount' => $update_amount]);
    }

    return $updateHosting;
}
