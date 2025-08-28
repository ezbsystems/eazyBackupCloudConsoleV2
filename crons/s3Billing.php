<?php

require __DIR__ . '/../init.php';

use WHMCS\Module\Addon\CloudStorage\Admin\S3Billing;

$packageId = 48;
$s3billingObject = new S3Billing();
$result = $s3billingObject->gatherBillingData($packageId);

// echo "<pre>";
// print_r($result['updateResults']);
// echo "</pre>";
