<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

$packageId = ProductConfig::$E3_PRODUCT_ID;
$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    header('Location: clientarea.php');
    exit;
}

$loggedInUserId = $ca->getUserID();
$product = DBController::getProduct($loggedInUserId, $packageId);
if (is_null($product) || is_null($product->username)) {
    header('Location: index.php?m=cloudstorage&page=s3storage');
    exit;
}

// Handle POST updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = Capsule::table('s3_cloudbackup_settings')
        ->where('client_id', $loggedInUserId)
        ->first();

    $settingsData = [
        'default_notify_emails' => $_POST['default_notify_emails'] ?? null,
        'default_notify_on_success' => isset($_POST['default_notify_on_success']) ? 1 : 0,
        'default_notify_on_warning' => isset($_POST['default_notify_on_warning']) ? 1 : 0,
        'default_notify_on_failure' => isset($_POST['default_notify_on_failure']) ? 1 : 0,
        'default_timezone' => $_POST['default_timezone'] ?? null,
        'per_client_max_concurrent_jobs' => isset($_POST['per_client_max_concurrent_jobs']) && $_POST['per_client_max_concurrent_jobs'] !== '' ? (int)$_POST['per_client_max_concurrent_jobs'] : null,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if ($settings) {
        Capsule::table('s3_cloudbackup_settings')
            ->where('client_id', $loggedInUserId)
            ->update($settingsData);
    } else {
        $settingsData['client_id'] = $loggedInUserId;
        $settingsData['created_at'] = date('Y-m-d H:i:s');
        Capsule::table('s3_cloudbackup_settings')->insert($settingsData);
    }

    $_SESSION['message'] = ['type' => 'success', 'text' => 'Settings saved successfully'];
    header('Location: index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_settings');
    exit;
}

// Load current settings
$settingsRow = Capsule::table('s3_cloudbackup_settings')
    ->where('client_id', $loggedInUserId)
    ->first();

if ($settingsRow) {
    // Convert stdClass to array for Smarty compatibility
    $settings = (array) $settingsRow;
} else {
    // Create default settings (as array for Smarty compatibility)
    $settings = [
        'default_notify_emails' => null,
        'default_notify_on_success' => 0,
        'default_notify_on_warning' => 1,
        'default_notify_on_failure' => 1,
        'default_timezone' => null,
        'per_client_max_concurrent_jobs' => null,
    ];
}

return [
    'settings' => $settings,
    'message' => isset($_SESSION['message']) ? $_SESSION['message'] : null,
];

