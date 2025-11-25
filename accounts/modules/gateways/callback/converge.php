<?php

if (file_exists(dirname(dirname(dirname(__DIR__))) . "/init.php"))
    require_once dirname(dirname(dirname(__DIR__))) . "/init.php";

if (file_exists(dirname(dirname(dirname(__DIR__))) . "/includes/gatewayfunctions.php"))
    require_once dirname(dirname(dirname(__DIR__))) . "/includes/gatewayfunctions.php";

if (file_exists(dirname(dirname(dirname(__DIR__))) . "/includes/invoicefunctions.php"))
    require_once dirname(dirname(dirname(__DIR__))) . "/includes/invoicefunctions.php";

use Illuminate\Database\Capsule\Manager as Capsule;

$gatewaymodule = basename(__FILE__, '.php'); # your gateway module name

$GATEWAY = getGatewayVariables($gatewaymodule);

if (!$GATEWAY['type']) {
    exit("Module Not Activated");
}

$amount = $_REQUEST['ssl_amount'];
$invoiceid = $_REQUEST["ssl_invoice_number"];
$fee = 0;

if ($_REQUEST['ssl_result'] == 0) {
    $success = 'Success';
}

$transactionStatus = $success ? 'Success' : 'Failure';

logTransaction($GATEWAY["name"], $_REQUEST, $transactionStatus);

$paymentSuccess = false;

if ($_REQUEST['ssl_result'] == 0) {

    $userID = Capsule::table('tblinvoices')->select('userid')->where('id', $invoiceid)->get();
    $userID = (array) $userID[0];

    try {
        Capsule::table('tblclients')
                ->where('id', $userID['userid'])
                ->update(
                        [
                            'gatewayid' => $_REQUEST['ssl_token']
                        ]
        );
    } catch (Exception $ex) {
        logActivity("couldn't update tblclients: {$e->getMessage()}");
    }

    $invoiceid = checkCbInvoiceID($invoiceid, $GATEWAY['name']);

    $transid = $_REQUEST["ssl_txn_id"];

//    checkCbTransID($transid);

    addInvoicePayment($invoiceid, $transid, $amount, $fee, $gatewaymodule);

    header('Location:' . $GATEWAY['systemurl'] . '/cart.php?a=complete');
} else {
    header('Location:' . $GATEWAY['systemurl'] . '/viewinvoice.php?id=' . $invoiceid);
}
?>