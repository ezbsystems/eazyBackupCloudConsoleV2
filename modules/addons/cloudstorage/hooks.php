<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;

add_hook('ClientAreaHeadOutput', 1, function($vars) {
    if (isset($_GET['m']) && $_GET['m'] == 'cloudstorage') {
        $webRoot = $vars['WEB_ROOT'] ?? '';
        return <<<HTML
        <link href="{$webRoot}/modules/addons/cloudstorage/assets/css/datatables.css" rel="stylesheet" type="text/css" />
        <link href="{$webRoot}/modules/addons/cloudstorage/assets/css/responsive.css" rel="stylesheet" type="text/css" />
        <link href="{$webRoot}/modules/addons/cloudstorage/assets/css/scrollbar.css" rel="stylesheet" type="text/css" />
        <script src="{$webRoot}/modules/addons/cloudstorage/assets/js/tailwind.min.js"></script>
        <script src="{$webRoot}/modules/addons/cloudstorage/assets/js/moment.min.js"></script>
        <script src="{$webRoot}/modules/addons/cloudstorage/assets/js/popper.min.js"></script>
        <script src="{$webRoot}/modules/addons/cloudstorage/assets/js/jquery.dataTables.min.js"></script>
        <script src="{$webRoot}/modules/addons/cloudstorage/assets/js/custom.js"></script>
    HTML;
    }

});

add_hook('EmailPreSend132', 1, function($vars) {
    $serviceId = $vars['relid'];
    $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->first();

    if (is_null($service)) {
        return;
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $merge_fields = [];
    if ($vars['messagename'] == 'Cancellation Request Confirmation' && $service->packageid == $packageId) {
        $merge_fields['abortsend'] = true;
    }
    return $merge_fields;
});

add_hook('CancellationRequest132', 1, function($vars) {
    $serviceId = $vars['relid'];

    $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->first();

    if (is_null($service)) {
        return;
    }

    $packageId = ProductConfig::$E3_PRODUCT_ID;

    // Check if the specific product is cancelled
    if ($service->packageid == $packageId) {
        // Fetch client details
        $client = Capsule::table('tblclients')
            ->where('id', $service->userid)
            ->first();

        if (is_null($client)) {
            return;
        }

        $product = Capsule::table('tblproducts')
            ->where('id', $service->packageid)
            ->first();

        if (is_null($product)) {
            return;
        }

        // Define email merge fields
        // $mergeFields = [
        //     'client_name' => $client->firstname . ' ' . $client->lastname,
        //     'client_email' => $client->email,
        //     'service_product' => $product->name,
        //     'service_status' => $vars['status'],
        // ];

        localAPI('SendAdminEmail', [
            'messagename' => "E3 Cloud Storage Service Cancelled Email",
            // 'customvars' => base64_encode(serialize($mergeFields)),
        ]);
    }
});

