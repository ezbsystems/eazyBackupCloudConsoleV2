<?php

use Comet\CometDevice;
use WHMCS\Database\Capsule;

require_once __DIR__ . '/../modules/servers/comet/vendor/autoload.php';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../modules/servers/comet/JobType.php';
require_once __DIR__ . '/../modules/servers/comet/JobStatus.php';
require_once __DIR__ . '/../modules/servers/comet/CometDevice.php';
require_once __DIR__ . '/../modules/servers/comet/CometUser.php';

$startTime = microtime(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$rawData = file_get_contents("php://input");
logEvent('Raw Data', $rawData);
$eventData = json_decode($rawData);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    logEvent('Invalid JSON', json_last_error_msg());
    exit('Invalid JSON');
}

$typeString = $eventData->TypeString ?? null;
$resourceId = $eventData->ResourceID ?? null;

if (!$typeString || !$resourceId) {
    http_response_code(400);
    logEvent('Invalid Event Data', $eventData);
    exit('Invalid Event Data');
}

if ($typeString !== 'SEVT_DEVICE_REMOVED') {
    http_response_code(200);
    logEvent('Event Ignored', $typeString);
    exit('Event Ignored');
}

function getCometUserByDevice($resourceId) {
    logEvent('Mapping Start', $resourceId);
    $device = Capsule::table('comet_devices')->where('hash', $resourceId)->first();
    if ($device) {
        logEvent('Device Found', $device);
        $cometUser = Capsule::table('comet_users')->where('username', $device->comet_user_id)->first();
        if ($cometUser) {
            logEvent('Mapping Success', ['clientId' => $cometUser->user_id, 'productId' => $cometUser->product_id]);
            return $cometUser;
        }
    }
    logEvent('Mapping Failure', $resourceId);
    return null;
}

$cometUser = getCometUserByDevice($resourceId);
if (!$cometUser) {
    http_response_code(400);
    logEvent('Client and Product Mapping Not Found', $resourceId);
    exit('Client and Product mapping not found');
}

$clientId = $cometUser->user_id;
$productId = $cometUser->product_id;

// Mark the device as inactive
try {
    Capsule::table('comet_devices')->where('hash', $resourceId)->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
    logEvent('Device Inactivated', $resourceId);
} catch (\Exception $e) {
    logEvent('Device Inactivation Error', $e->getMessage());
}

// Update device history
try {
    $device = Capsule::table('comet_devices')->where('hash', $resourceId)->first(['id', 'name']);
    Capsule::table('comet_device_histories')->insert([
        'device_id' => $device->id,
        'device_name' => $device->name, // Add the device name
        'comet_user_id' => $cometUser->username,
        'action' => 'REMOVE',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    logEvent('Device History Insert Success', $resourceId);
} catch (\Exception $e) {
    logEvent('Device History Insert Error', $e->getMessage());
}

http_response_code(200);
logEvent('Webhook Processed Successfully', $typeString);
echo 'Webhook processed successfully';

$endTime = microtime(true);
$executionTime = $endTime - $startTime;
logEvent('Execution Time', $executionTime);

function logEvent($eventType, $eventData) {
    $logFile = '/var/www/eazybackup.ca/accounts/hooks/webhook.log';
    $currentTime = date('Y-m-d H:i:s');
    $logEntry = "[$currentTime] EventType: $eventType, EventData: " . json_encode($eventData) . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
