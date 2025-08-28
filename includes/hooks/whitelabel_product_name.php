<?php
use WHMCS\Database\Capsule;

add_hook('ClientAreaPage', 1, function($vars) {
    $whitelabel_product_name = "OBC"; // default label

    if (isset($_SESSION['uid'])) {
        $clientid = $_SESSION['uid'];
        // Query custom mapping table for the client
        $mapping = Capsule::table('tbl_client_productgroup_map')
            ->where('client_id', $clientid)
            ->first();
        if ($mapping) {
            // Retrieve product group name from tblproductgroups
            $group = Capsule::table('tblproductgroups')
                ->where('id', $mapping->product_group_id)
                ->first();
            if ($group && !empty($group->name)) {
                $whitelabel_product_name = $group->name;
            }
        }
    }
    return [
        "whitelabel_product_name" => $whitelabel_product_name,
    ];
});
