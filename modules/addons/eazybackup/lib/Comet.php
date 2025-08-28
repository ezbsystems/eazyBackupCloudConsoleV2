<?php

namespace WHMCS\Module\Addon\Eazybackup;

use WHMCS\Database\Capsule;

// File logging function for debugging (function definition is kept, but calls are removed)
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/../crons/eazybackup_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}";
    
    if ($data !== null) {
        $logEntry .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT);
    }
    
    $logEntry .= "\n" . str_repeat('-', 80) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

class Comet {

/**
     * Add or Update Devices
     * @param $userProfile
     * @param $client
     * @param $deviceStatuses
     * @return null
     */
    public function upsertDevices($userProfile, $client, $deviceStatuses)
    {
        try {
            // Extract active device IDs from deviceStatuses
            $activeDeviceIds = [];
            if (is_array($deviceStatuses)) {
                foreach ($deviceStatuses as $deviceStatus) {
                    if (is_object($deviceStatus) && isset($deviceStatus->DeviceID)) {
                        $activeDeviceIds[] = $deviceStatus->DeviceID;
                    } elseif (is_array($deviceStatus) && isset($deviceStatus['DeviceID'])) {
                        $activeDeviceIds[] = $deviceStatus['DeviceID'];
                    }
                }
            }

            // Get a list of ALL device hashes that exist for this user on the Comet Server.
            $allUpstreamDeviceHashes = array_keys((array) $userProfile->Devices);

            foreach ($userProfile->Devices as $deviceId => $device) {
                // Determine the live Online/Offline status.
                $isActive = in_array($deviceId, $activeDeviceIds);

                // Handle invalid creation dates
                $createdAt = null;
                if (isset($device->RegistrationTime) && $device->RegistrationTime && $device->RegistrationTime !== 'Unknown') {
                    $timestamp = is_numeric($device->RegistrationTime) ? $device->RegistrationTime : strtotime($device->RegistrationTime);
                    if ($timestamp !== false) {
                        $createdAt = date('Y-m-d H:i:s', $timestamp);
                    }
                }

                if (!$createdAt) {
                    $createdAt = date('Y-m-d H:i:s');
                }

                $cometDevice = [
                    'id' => hash('sha256', $client->userid . $deviceId),
                    'client_id' => $client->userid,
                    'username' => $client->username,
                    'hash' => $deviceId,
                    'content' => json_encode($device),
                    'name' => $device->FriendlyName,
                    'is_active' => $isActive ? 1 : 0,
                    'created_at' => $createdAt,
                    'revoked_at' => null // NEW: Ensure we are not un-revoking devices by re-syncing.
                ];

                Capsule::table('comet_devices')->updateOrInsert(
                    ['id' => $cometDevice['id']],
                    $cometDevice
                );
            }

            // NEW REVOCATION LOGIC:
            // Find any local device records that were NOT in the full list from Comet,
            // and mark them as revoked with the current timestamp.
            if (!empty($allUpstreamDeviceHashes)) {
                Capsule::table('comet_devices')
                    ->where('client_id', $client->userid)
                    ->where('username', $client->username)
                    ->whereNotIn('hash', $allUpstreamDeviceHashes)
                    ->whereNull('revoked_at') // Only update devices that are not already revoked
                    ->update([
                        'is_active' => 0, // A revoked device cannot be active
                        'revoked_at' => date('Y-m-d H:i:s')
                    ]);
            }

        } catch (\Exception $e) {
            // Error logging can be re-enabled here if needed.
        }
    }

    /**
     * Add or Update Protected Items
     * @param $userProfile
     * @param $client
     * @return null
     */
    public function upsertItems($userProfile, $client)
    {
        try {
            foreach ($userProfile->Sources as $itemId => $item) {
                // Backward compatibility: capture OwnerDevice if present for new schema
                $ownerDevice = property_exists($item, 'OwnerDevice') ? $item->OwnerDevice : (isset($item->OwnerDevice) ? $item->OwnerDevice : null);
                // Handle invalid creation dates
                $createdAt = null;
                if (isset($item->CreateTime) && $item->CreateTime && $item->CreateTime !== 'Unknown') {
                    $timestamp = is_numeric($item->CreateTime) ? $item->CreateTime : strtotime($item->CreateTime);
                    if ($timestamp !== false) {
                        $createdAt = date('Y-m-d H:i:s', $timestamp);
                    }
                }
                
                // Use current time as fallback for invalid dates
                if (!$createdAt) {
                    $createdAt = date('Y-m-d H:i:s');
                }

                // Handle invalid modification dates
                $updatedAt = null;
                if (isset($item->ModifyTime) && $item->ModifyTime && $item->ModifyTime !== 'Unknown') {
                    $timestamp = is_numeric($item->ModifyTime) ? $item->ModifyTime : strtotime($item->ModifyTime);
                    if ($timestamp !== false) {
                        $updatedAt = date('Y-m-d H:i:s', $timestamp);
                    }
                }
                
                // Use current time as fallback for invalid dates
                if (!$updatedAt) {
                    $updatedAt = date('Y-m-d H:i:s');
                }

                $protectedItem = [
                    'id' => $itemId,
                    'client_id' => $client->userid,
                    'username' => $client->username,
                    'content' => json_encode($item),
                    'comet_device_id' => hash('sha256', $client->userid . $item->OwnerDevice),
                    'owner_device' => $ownerDevice,
                    'name' => $item->Description,
                    'type' => $item->Engine,
                    'total_bytes' => $item->Statistics->LastBackupJob->TotalSize,
                    'total_files' => $item->Statistics->LastBackupJob->TotalFiles,
                    'total_directories' => $item->Statistics->LastBackupJob->TotalDirectories,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt
                ];

                Capsule::table('comet_items')->updateOrInsert(
                    ['id' => $protectedItem['id']],
                    $protectedItem
                );
            }
        } catch (\Exception $e) {
            /*
            logModuleCall(
                "eazybackup",
                'upsertItems',
                $userProfile,
                $e->getMessage()
            );
            */
        }
    }

    /**
     * Add or Update Protected Vaults
     * @param $userProfile
     * @param $client
     * @return null
     */
    public function upsertVaults($userProfile, $client)
    {
        try {
            foreach ($userProfile->Destinations as $vaultId => $vault) {
                // Handle invalid creation dates
                $bucketServer = $vault->CometServer ?? '';
                $bucketName = $vault->CometBucket ?? '';
                $bucketKey = $vault->CometBucketKey ?? '';
                $storageLimitEnabled = $vault->StorageLimitEnabled ?? 0;
                $createdAt = null;
                if (isset($vault->CreateTime) && $vault->CreateTime && $vault->CreateTime !== 'Unknown') {
                    $timestamp = is_numeric($vault->CreateTime) ? $vault->CreateTime : strtotime($vault->CreateTime);
                    if ($timestamp !== false) {
                        $createdAt = date('Y-m-d H:i:s', $timestamp);
                    }
                }
                
                // Use current time as fallback for invalid dates
                if (!$createdAt) {
                    $createdAt = date('Y-m-d H:i:s');
                }

                // Handle invalid modification dates
                $updatedAt = null;
                if (isset($vault->ModifyTime) && $vault->ModifyTime && $vault->ModifyTime !== 'Unknown') {
                    $timestamp = is_numeric($vault->ModifyTime) ? $vault->ModifyTime : strtotime($vault->ModifyTime);
                    if ($timestamp !== false) {
                        $updatedAt = date('Y-m-d H:i:s', $timestamp);
                    }
                }
                
                // Use current time as fallback for invalid dates
                if (!$updatedAt) {
                    $updatedAt = date('Y-m-d H:i:s');
                }

                $cometVault = [
                    'id' => $vaultId,
                    'client_id' => $client->userid,
                    'username' => $client->username,
                    'content' => json_encode($vault),
                    'name' => $vault->Description,
                    'type' => $vault->DestinationType ?? '',
                    'total_bytes' => $vault->Statistics->ClientProvidedSize->Size ?? 0,
                    'bucket_server' => $bucketServer,
                    'bucket_name' => $bucketName,
                    'bucket_key' => $bucketKey,
                    'has_storage_limit' => $storageLimitEnabled,
                    'storage_limit_bytes' => $vault->StorageLimitBytes,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt
                ];

                Capsule::table('comet_vaults')->updateOrInsert(
                    ['id' => $cometVault['id']],
                    $cometVault
                );
            }
        } catch (\Exception $e) {
            /*
            logModuleCall(
                "eazybackup",
                'upsertVaults',
                $userProfile,
                $e->getMessage()
            );
            */
        }
    }

    /**
     * Add or Update Jobs
     * @param $jobs
     * @return null
     */
    public function upsertJobs($jobs)
    {
        try {
            foreach ($jobs as $job) {
                // get the client id
                $hosting = Capsule::table('tblhosting')
                    ->select('userid')
                    ->where('domainstatus', 'Active')
                    ->where('username', $job->Username)
                    ->first();

                if (is_null($hosting)) {
                    continue;
                }
                $startedAt = date('Y-m-d H:i:s', $job->StartTime);
                $endedAt = $job->EndTime > 0 ? date('Y-m-d H:i:s', $job->EndTime) : null;
                $lastStatusAt = (!is_null($endedAt) && $job->EndTime >= $job->StartTime) ? $endedAt : $startedAt;

                $cometJob = [
                    'id' => $job->GUID,
                    'content' => json_encode($job),
                    'client_id' => $hosting->userid,
                    'username' => $job->Username,
                    'comet_vault_id' => $job->DestinationGUID,
                    'comet_device_id' => hash('sha256', $hosting->userid . $job->DeviceID),
                    'comet_item_id' => $job->SourceGUID,
                    'type' => $job->Classification,
                    'status' => $job->Status,
                    'comet_snapshot_id' => property_exists($job, 'SnapshotID') ? $job->SnapshotID : null,
                    'comet_cancellation_id' => $job->CancellationID ?? '',
                    'total_bytes' => $job->TotalSize,
                    'total_files' => $job->TotalFiles,
                    'total_directories' => $job->TotalDirectories,
                    'upload_bytes' => $job->UploadSize,
                    'download_bytes' => $job->DownloadSize,
                    'total_ms_accounts' => $job->TotalAccountsCount ?? 0,
                    'started_at' => $startedAt,
                    'ended_at' => $endedAt,
                    'last_status_at' => $lastStatusAt
                ];

                Capsule::table('comet_jobs')->updateOrInsert(
                    ['id' => $cometJob['id']],
                    $cometJob
                );
            }
        } catch (\Exception $e) {
            /*
            logModuleCall(
                "eazybackup",
                'upsertJobs',
                $jobs,
                $e->getMessage()
            );
            */
        }
    }
}