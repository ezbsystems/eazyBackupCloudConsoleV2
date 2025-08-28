<?php

    use WHMCS\Database\Capsule;
    use WHMCS\ClientArea;
    use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;

    $ca = new ClientArea();
    if (!$ca->isLoggedIn()) {
        header('Location: clientarea.php');
        exit;
    }
    $packageId = ProductConfig::$E3_PRODUCT_ID;
    $loggedInUserId = $ca->getUserID();
    $services = Capsule::table('tblhosting')
        ->where('tblhosting.userid', $loggedInUserId)
        ->where('tblhosting.packageid', $packageId)
        ->where('tblhosting.domainstatus', 'Active')
        ->select(
            'tblhosting.id',
            'tblhosting.userid',
            'tblhosting.packageid',
            'tblhosting.username',
            'tblhosting.regdate',
            'tblhosting.nextduedate',
            'tblhosting.amount',
            'tblhosting.domainstatus',
            'tblproducts.name as productname'
        )
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->get();

    return [
        'services' => $services
    ];