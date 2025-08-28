<?php

use Comet\CometDevice;
use Comet\CometUser;
use WHMCS\Database\Capsule;

// Ensure Composer autoloader is included to load dependencies and custom classes
require_once __DIR__ . '/../modules/servers/comet/vendor/autoload.php';
require_once __DIR__ . '/../init.php';

// Include necessary classes directly
require_once __DIR__ . '/../modules/servers/comet/JobType.php';
require_once __DIR__ . '/../modules/servers/comet/JobStatus.php';
require_once __DIR__ . '/../modules/servers/comet/CometDevice.php';
require_once __DIR__ . '/../modules/servers/comet/CometUser.php';

$startTime = microtime(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit('Method Not Allowed');
}

$rawData = file_get_contents("php://input");
logEvent('Raw Data', $rawData);
$eventData = json_decode($rawData);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    logEvent('Invalid JSON', json_last_error_msg());
    exit('Invalid JSON');
}

$typeString = $eventData->TypeString ?? null;
$data = $eventData->Data ?? null;

if (!$typeString) {
    http_response_code(400); // Bad Request
    logEvent('Invalid Event Data', $eventData);
    exit('Invalid Event Data');
}

$eventsToProcess = ['SEVT_DEVICE_NEW', 'SEVT_DEVICE_REMOVED'];

if (!in_array($typeString, $eventsToProcess)) {
    http_response_code(200); // OK
    logEvent('Event Ignored', $typeString);
    exit('Event Ignored');
}

$action = $typeString === 'SEVT_DEVICE_NEW' ? 'ADD' : ($typeString === 'SEVT_DEVICE_REMOVED' ? 'REMOVE' : null);

if ($action === null) {
    http_response_code(400); // Bad Request
    logEvent('Invalid Event Type', $typeString);
    exit('Invalid Event Type');
}

function getClientAndProduct($username) {
    logEvent('Mapping Start', $username);
    $product = Capsule::table('tblhosting')
        ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
        ->where('tblhosting.username', $username)
        ->where('tblhosting.domainstatus', 'Active')
        ->first(['tblhosting.userid as clientId', 'tblhosting.id as productId']);
    
    if ($product) {
        logEvent('Mapping Success', ['clientId' => $product->clientId, 'productId' => $product->productId]);
        return [
            'clientId' => $product->clientId,
            'productId' => $product->productId,
        ];
    }

    logEvent('Mapping Failure', $username);
    return null;
}

$clientAndProduct = getClientAndProduct($eventData->Actor);
if (!$clientAndProduct) {
    http_response_code(400); // Bad Request
    logEvent('Client and Product Mapping Not Found', $eventData->Actor);
    exit('Client and Product mapping not found');
}

$clientId = $clientAndProduct['clientId'];
$productId = $clientAndProduct['productId'];

$cometDevice = new CometDevice();
$cometDevice->comet_user_id = $eventData->Actor; // Use the Actor as the comet_user_id
$cometDevice->hash = $eventData->ResourceID; // Assuming ResourceID is used as hash
$deviceId = $cometDevice->hash; // Use the hash as the device_id

if ($action === 'ADD') {
    if (!$data) {
        http_response_code(400); // Bad Request
        logEvent('Invalid Device Data', $eventData);
        exit('Invalid Device Data');
    }
    $cometDevice->content = json_encode($data);
    $cometDevice->name = $data->FriendlyName ?? ''; // Get the device name
    $cometDevice->platform = $data->PlatformVersion ?? null; // Get the platform data
    $cometDevice = CometDevice::setDevice($cometDevice);

    // Insert or update the device in the comet_devices table
    try {
        Capsule::table('comet_devices')->updateOrInsert(
            ['hash' => $cometDevice->hash],
            [
                'id' => $deviceId,
                'comet_user_id' => $cometDevice->comet_user_id,
                'content' => $cometDevice->content,
                'name' => $cometDevice->name,
                'platform' => json_encode($cometDevice->platform),
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        logEvent('Device Table Update Success', $cometDevice);
    } catch (\Exception $e) {
        logEvent('Device Table Update Error', $e->getMessage());
    }

    // Insert or update the user in the comet_users table
    try {
        Capsule::table('comet_users')->updateOrInsert(
            ['username' => $eventData->Actor],
            [
                'user_id' => $clientId,
                'product_id' => $productId,
                'username' => $eventData->Actor,
                'comet_server_id' => 'your_comet_server_id', // Adjust as needed
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'is_active' => 1,
            ]
        );
        logEvent('User Table Update Success', $eventData->Actor);
    } catch (\Exception $e) {
        logEvent('User Table Update Error', $e->getMessage());
    }

    // Insert the history into the comet_device_histories table
    try {
        Capsule::table('comet_device_histories')->insert([
            'device_id' => $deviceId, // Use the hash as the device_id
            'device_name' => $cometDevice->name, // Add the device name
            'comet_user_id' => $cometDevice->comet_user_id,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        logEvent('Device History Insert Success', $cometDevice);
    } catch (\Exception $e) {
        logEvent('Device History Insert Error', $e->getMessage());
    }
}

http_response_code(200); // OK
logEvent('Webhook Processed Successfully', $typeString);
echo 'Webhook processed successfully';

$endTime = microtime(true);
$executionTime = $endTime - $startTime;
logEvent('Execution Time', $executionTime);

/**
 * Log event data to a file for monitoring
 *
 * @param string $eventType
 * @param mixed $eventData
 * @return void
 */
function logEvent($eventType, $eventData) {
    $logFile = '/var/www/eazybackup.ca/accounts/hooks/webhook.log'; // Specify the path to your log file
    $currentTime = date('Y-m-d H:i:s');
    $logEntry = "[$currentTime] EventType: $eventType, EventData: " . json_encode($eventData) . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
