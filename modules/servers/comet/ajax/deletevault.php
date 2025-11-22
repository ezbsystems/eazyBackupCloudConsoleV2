<?php

use \WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . "/../functions.php";

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['Status' => 405, 'Message' => 'Method Not Allowed']);
    exit;
}

// Check if required parameters are provided
if (empty($_POST['serviceId']) || empty($_POST['vaultId'])) {
    echo json_encode(['Status' => 400, 'Message' => 'Missing required parameters']);
    exit;
}

$serviceId = $_POST['serviceId'];
$vaultId = $_POST['vaultId'];

try {
    // Get the service details to build comet server parameters
    $pid = Capsule::table("tblhosting")->where(["id" => $serviceId])->value('packageid');
    
    if (!$pid) {
        echo json_encode(['Status' => 404, 'Message' => 'Service not found']);
        exit;
    }
    
    // Get server parameters - following the same pattern as managevault.php
    $serverDetail = comet_ProductParams($pid);
    $params = [];
    $params['serverhttpprefix'] = $serverDetail['serverhttpprefix'];
    $params['serverhostname'] = $serverDetail['serverhostname'];
    $params['serverusername'] = $serverDetail['serverusername'];
    $params['serverpassword'] = $serverDetail['serverpassword'];
    
    // Get the username for this service
    $username = Capsule::table("tblhosting")->where(["id" => $serviceId])->value('username');
    if (!$username) {
        echo json_encode(['Status' => 404, 'Message' => 'Username not found for this service']);
        exit;
    }
    
    $params['username'] = $username;
    
    // Get the user profile using AdminGetUserProfile (same as managevault.php)
    $userProfile = comet_Server($params)->AdminGetUserProfile($params['username']);
    if (!$userProfile) {
        echo json_encode(['Status' => 404, 'Message' => 'User profile not found']);
        exit;
    }
    
    // Check if the vault exists in the user's destinations
    if (!isset($userProfile->Destinations[$vaultId])) {
        echo json_encode(['Status' => 404, 'Message' => 'Vault not found in user profile']);
        exit;
    }
    
    // Store the bucket ID for deletion (if it's a comet-managed bucket)
    $bucketId = null;
    if (isset($userProfile->Destinations[$vaultId]->CometBucket)) {
        $bucketId = $userProfile->Destinations[$vaultId]->CometBucket;
    }
    
    // Remove the destination from the user profile
    unset($userProfile->Destinations[$vaultId]);
    
    // Update the user profile to remove the vault
    $updateResponse = comet_Server($params)->AdminSetUserProfile($params['username'], $userProfile);
    
    // Check if the profile update was successful
    if ($updateResponse->Status !== 200 || $updateResponse->Message !== 'OK') {
        echo json_encode([
            'Status' => $updateResponse->Status ?? 500, 
            'Message' => 'Failed to update user profile: ' . ($updateResponse->Message ?? 'Unknown error')
        ]);
        exit;
    }
    
    // If there's a comet bucket, try to delete it from storage
    if ($bucketId) {
        try {
            $deleteResponse = comet_Server($params)->AdminStorageDeleteBucket($bucketId);
            // We don't fail the entire operation if bucket deletion fails,
            // as the vault has already been removed from the user profile
            if ($deleteResponse->Status !== 200) {
                error_log("Warning: Failed to delete storage bucket $bucketId: " . ($deleteResponse->Message ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            // Log the warning but don't fail the operation
            error_log("Warning: Exception while deleting storage bucket $bucketId: " . $e->getMessage());
        }
    }
    
    // Clear the user cache to ensure fresh data on next load
    comet_ClearUserCache();
    
    echo json_encode(['Status' => 200, 'Message' => 'OK']);
    
} catch (\Exception $e) {
    // Log the error for debugging
    error_log("Delete vault error: " . $e->getMessage());
    
    echo json_encode([
        'Status' => 500, 
        'Message' => 'Internal server error: ' . $e->getMessage()
    ]);
}