<?php

// Require the autoloader first, before including functions.php which has use statements
require_once __DIR__ . '/vendor/autoload.php';

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . "/functions.php";

function getBackupSummary($clientId) {
    // Get all products for this client in one query.
    $products = Capsule::table('tblhosting')
        ->select('id', 'username', 'packageid')
        ->where('userid', $clientId)
        ->get();

    $totalAccounts = $products->count();
    $totalDevices = 0;
    $totalProtectedItems = 0;
    $totalStorageUsed = 0;
    $storageVaults = [];
    $accounts = [];
    $devices = [];
    $backupAccounts = []; // For the Backup Accounts section

    // Cache for server parameters keyed by packageid.
    $paramsCache = [];
    // Cache for API responses keyed by username.
    $userProfileCache = [];
    // Cache for active connections per username.
    $activeConnectionsCache = [];

    foreach ($products as $product) {
        $username = $product->username;

        // Get or cache the product parameters.
        if (!isset($paramsCache[$product->packageid])) {
            $paramsCache[$product->packageid] = comet_ProductParams($product->packageid);
        }
        $params = $paramsCache[$product->packageid];
        $params['username'] = $username;

        if ($params['serverhostname'] === null || $params['serverusername'] === null) {
            // Skip this product if server details are missing.
            continue;
        }

        // Get or cache the user profile from the Comet API.
        if (!isset($userProfileCache[$username])) {
            $user = comet_User($params);
            if (is_string($user)) {
                // Skip if API call failed.
                continue;
            }
            $userProfileCache[$username] = $user;
        }
        $user = $userProfileCache[$username];

        $totalDevices += count($user->Devices);
        $totalProtectedItems += count($user->Sources);

        $accountVaults = [];
        foreach ($user->Destinations as $destination) {
            // Only include S3-compatible (1000) and Comet (1003) vaults
            $destType = null;
            if (isset($destination->DestinationType)) {
                $destType = (int)$destination->DestinationType;
            } elseif (isset($destination->Type)) {
                $destType = (int)$destination->Type;
            }
            if ($destType !== 1000 && $destType !== 1003) {
                continue;
            }

            $size = isset($destination->Statistics->ClientProvidedSize->Size)
                ? (int)$destination->Statistics->ClientProvidedSize->Size
                : 0;

            $totalStorageUsed += $size;
            $storageVaults[] = [
                'account'    => $username,
                'vault_name' => isset($destination->Description) ? $destination->Description : '',
                'size'       => comet_HumanFileSize($size),
            ];
            $accountVaults[] = comet_HumanFileSize($size);
        }

        $accounts[] = [
            'username'              => $username,
            'id'                    => $product->id, // Service ID
            'total_devices'         => count($user->Devices),
            'total_protected_items' => count($user->Sources),
            'total_storage_vaults'  => count($user->Destinations),
            'storage_vault_size'    => implode(', ', $accountVaults),
        ];

        // Retrieve job logs for the current account.
        $accountJobLogs = getAccountJobDetails($username);
        if (!is_array($accountJobLogs)) {
            $accountJobLogs = [];
        } else {
            $accountJobLogs = json_decode(json_encode($accountJobLogs), true);
        }

        // Get or cache active connections for this username.
        if (!isset($activeConnectionsCache[$username])) {
            $activeConnectionsCache[$username] = comet_Server($params)->AdminDispatcherListActive();
        }
        $activeConnections = $activeConnectionsCache[$username];
        $activeDevices = [];
        foreach ($activeConnections as $connection) {
            $activeDevices[$connection->DeviceID] = [
                'ReportedVersion'         => $connection->ReportedVersion,
                'ReportedPlatform'        => $connection->ReportedPlatform,
                'ReportedPlatformVersion' => $connection->ReportedPlatformVersion
            ];
        }

        // Process each device for this account.
        foreach ($user->Devices as $deviceId => $device) {
            $isOnline = isset($activeDevices[$deviceId]);
            $platformDetails = $isOnline && isset($activeDevices[$deviceId]['ReportedPlatformVersion'])
                ? $activeDevices[$deviceId]['ReportedPlatformVersion']
                : null;
            $platform = $platformDetails && !empty($platformDetails->Distribution)
                ? $platformDetails->Distribution
                : $device->Platform;

            // Add device info for Devices tab.
            $devices[] = [
                'status'      => $isOnline ? 'Online' : 'Offline',
                'device_name' => $device->FriendlyName,
                'version'     => $isOnline ? $activeDevices[$deviceId]['ReportedVersion'] : $device->Version,
                'platform'    => $platform,
            ];

            // Filter job logs for this particular device.
            $deviceJobs = array_filter($accountJobLogs, function($job) use ($deviceId) {
                return isset($job['DeviceID']) && $job['DeviceID'] == $deviceId;
            });

            // Sort the device's job logs by StartTime descending.
            if (!empty($deviceJobs) && isset(reset($deviceJobs)['StartTime'])) {
                usort($deviceJobs, function($a, $b) {
                    return $b['StartTime'] <=> $a['StartTime'];
                });
            }

            // Limit to the last 15 jobs.
            $deviceJobs = array_slice($deviceJobs, 0, 15);

            // Map each job for display.
            $jobDetails = array_map(function($job) {
                $startTimeFormatted = date("Y-m-d H:i:s", $job['StartTime']);
                $endTimeFormatted = date("Y-m-d H:i:s", $job['EndTime']);
                $durationSeconds = $job['EndTime'] - $job['StartTime'];
                $durationFormatted = gmdate("i:s", $durationSeconds) . "m";
                $uploaded = comet_HumanFileSize($job['UploadSize']);
                $selectedDataSize = comet_HumanFileSize($job['TotalSize']);
                return [
                    'FriendlyStatus'    => $job['FriendlyStatus'],
                    'StartTime'         => $startTimeFormatted,
                    'EndTime'           => $endTimeFormatted,
                    'Duration'          => $durationFormatted,
                    'StorageVault'      => $job['ProtectedItemDescription'],
                    'Uploaded'          => $uploaded,
                    'SelectedDataSize'  => $selectedDataSize,
                    'Files'             => $job['TotalFiles'],
                    'Directories'       => $job['TotalDirectories'],
                ];
            }, $deviceJobs);

            // Add entry for the Backup Accounts section.
            $backupAccounts[] = [
                'device_name' => $device->FriendlyName,
                'username'    => $username,
                'status'      => $isOnline ? 'Online' : 'Offline',
                'jobs'        => $jobDetails
            ];
        }
    }

    return [
        'totalAccounts'       => $totalAccounts,
        'totalDevices'        => $totalDevices,
        'totalProtectedItems' => $totalProtectedItems,
        'totalStorageUsed'    => comet_HumanFileSize($totalStorageUsed, 2),
        'storageVaults'       => $storageVaults,
        'accounts'            => $accounts,
        'devices'             => $devices,
        'backupAccounts'      => $backupAccounts,
    ];
}
function getUserStorage($username) {
    $totalStorageUsed = 0;

    $product = Capsule::table('tblhosting')
        ->where('username', $username)
        ->first(['username', 'packageid']);

    if (!$product) {
        return comet_HumanFileSize($totalStorageUsed, 2);
    }

    $params = comet_ProductParams($product->packageid); // Pass the package ID to the function
    $params['username'] = $product->username;

    if ($params['serverhostname'] === null || $params['serverusername'] === null) {
        // Skip if server details are not found
        return comet_HumanFileSize($totalStorageUsed, 2);
    }

    $user = comet_User($params);

    if (is_string($user)) {
        // Skip if user retrieval failed
        return comet_HumanFileSize($totalStorageUsed, 2);
    }

    foreach ($user->Destinations as $destination) {
        // Only include S3-compatible (1000) and Comet (1003) vaults
        $destType = null;
        if (isset($destination->DestinationType)) {
            $destType = (int)$destination->DestinationType;
        } elseif (isset($destination->Type)) {
            $destType = (int)$destination->Type;
        }
        if ($destType !== 1000 && $destType !== 1003) {
            continue;
        }

        $size = isset($destination->Statistics->ClientProvidedSize->Size)
            ? (int)$destination->Statistics->ClientProvidedSize->Size
            : 0;

        $totalStorageUsed += $size;
    }

    return comet_HumanFileSize($totalStorageUsed, 2);
}


function getProtectedItemsSummary($clientId) {
    $products = Capsule::table('tblhosting')
        ->where('userid', $clientId)
        ->get(['username', 'packageid']); 

    $combinedSourceInfo = [];
    $totalVmCountsByUser = []; 

    foreach ($products as $product) {
        $params = comet_ProductParams($product->packageid); 
        $params['username'] = $product->username;

        if ($params['serverhostname'] === null || $params['serverusername'] === null) {            
            continue;
        }

        $user = comet_User($params);

        if (is_string($user)) {            
            continue;
        }
        
        $totalVmCountsByUser[$product->username] = 0;
        foreach ($user->Sources as $source) {
            // Use the maximum of LastBackupJob and LastSuccessfulBackupJob for VM count
            $lastBackupVmCount = $source->Statistics->LastBackupJob->TotalVmCount ?? 0;
            $lastSuccessfulVmCount = $source->Statistics->LastSuccessfulBackupJob->TotalVmCount ?? 0;
            $TotalVmCount = max($lastBackupVmCount, $lastSuccessfulVmCount);
            $totalVmCountsByUser[$product->username] += $TotalVmCount;
        }
        
        $devices = $user->Devices ?? [];
        foreach ($user->Sources as $key => $source) {
            $itemid = htmlspecialchars($key);
            $engine = htmlspecialchars($source->Engine);
            $description = htmlspecialchars($source->Description);
            $ownerDevice = htmlspecialchars($source->OwnerDevice);

            if (isset($devices[$ownerDevice])) {
                $deviceObject = $devices[$ownerDevice];
                $deviceFriendlyName = htmlspecialchars($deviceObject->FriendlyName);
            } else {
                $deviceFriendlyName = 'N/A'; 
            }

            $engineProps_INCLUDE = [];
            foreach ($source->EngineProps as $propKey => $propValue) {
                if (strpos($propKey, 'INCLUDE') === 0) {
                    $engineProps_INCLUDE[] = htmlspecialchars($propValue);
                }
            }

            $totalAccountsCount = isset($source->Statistics->LastBackupJob->TotalAccountsCount) ? $source->Statistics->LastBackupJob->TotalAccountsCount : 'N/A';
            
            // Use the maximum of LastBackupJob and LastSuccessfulBackupJob for VM count
            $lastBackupVmCount = isset($source->Statistics->LastBackupJob->TotalVmCount) ? $source->Statistics->LastBackupJob->TotalVmCount : 0;
            $lastSuccessfulVmCount = isset($source->Statistics->LastSuccessfulBackupJob->TotalVmCount) ? $source->Statistics->LastSuccessfulBackupJob->TotalVmCount : 0;
            $TotalVmCount = max($lastBackupVmCount, $lastSuccessfulVmCount);
            $TotalVmCount = $TotalVmCount > 0 ? $TotalVmCount : 'N/A';
            $engineProps_INCLUDE_str = implode(', ', $engineProps_INCLUDE);

            //Last Backup Job Start Time
            $startTime = $source->Statistics->LastBackupJob->StartTime;
            $lastBackupJob = $startTime == 0 ? 'N/A' : htmlspecialchars(gmdate('Y-m-d H:i:s', $startTime));

            //Total Size of Last Backup Job
            $totalSize = $source->Statistics->LastBackupJob->TotalSize;
            $lastJobTotalSize = $totalSize == 0 ? 'N/A' : comet_HumanFileSize($totalSize);

            //Upload Size of Last Backup Job
            $lastUploadSize = $source->Statistics->LastBackupJob->UploadSize;
            $lastBackupUploadSize = $lastUploadSize == 0 ? 'N/A' : comet_HumanFileSize($lastUploadSize);

            //Download Size of Last Backup Job
            $lastDownloadSize = $source->Statistics->LastBackupJob->DownloadSize;
            $lastJobDownloadSize = $lastDownloadSize == 0 ? 'N/A' : comet_HumanFileSize($lastDownloadSize);

            //Last Successful Backup Job Start Time
            $startTime = $source->Statistics->LastSuccessfulBackupJob->StartTime;
            $lastSuccessJob = $startTime == 0 ? 'N/A' : htmlspecialchars(gmdate('Y-m-d H:i:s', $startTime));

            //Total Size of Last Successful Backup Job
            $totalSuccessSize = $source->Statistics->LastSuccessfulBackupJob->TotalSize;
            $lastSuccessJobTotalSize = $totalSuccessSize == 0 ? 'N/A' : comet_HumanFileSize($totalSuccessSize);

            //Upload Size of Last Successful Backup Job
            $successUploadSize = $source->Statistics->LastSuccessfulBackupJob->UploadSize;
            $lastsuccessUploadSize = $successUploadSize == 0 ? 'N/A' : comet_HumanFileSize($successUploadSize);

            //Download Size of Last Successful Backup Job
            $successDownloadSize = $source->Statistics->LastSuccessfulBackupJob->DownloadSize;
            $lastsuccessDownloadSize = $successDownloadSize == 0 ? 'N/A' : comet_HumanFileSize($successDownloadSize);

            $sourceInfo = [
                'Username' => $product->username,
                'itemid' => $itemid,
                'engine' => $engine,
                'description' => $description,
                'ownerDevice' => $ownerDevice,
                'deviceFriendlyName' => $deviceFriendlyName,
                'engineProps_INCLUDE_str' => $engineProps_INCLUDE_str,
                'totalAccountsCount' => $totalAccountsCount,
                'TotalVmCount' => $TotalVmCount,
                'TotalVmCountForUser' => $totalVmCountsByUser[$product->username],
                'lastBackupJob' => $lastBackupJob,
                'lastJobTotalSize' => $lastJobTotalSize,
                'lastBackupUploadSize' => $lastBackupUploadSize,
                'lastJobDownloadSize' => $lastJobDownloadSize,
                'lastSuccessJob' => $lastSuccessJob,
                'lastSuccessJobTotalSize' => $lastSuccessJobTotalSize,
                'lastsuccessUploadSize' => $lastsuccessUploadSize,
                'lastsuccessDownloadSize' => $lastsuccessDownloadSize,
            ];
            $combinedSourceInfo[] = $sourceInfo;
        }
    }

    return $combinedSourceInfo;
}

function getUserProtectedItemsSummary($username) {
    $totalVmCount = 0;
    $totalAccountsCount = 0;

    $product = Capsule::table('tblhosting')
        ->where('username', $username)
        ->first(['username', 'packageid']);

    if (!$product) {
        return [
            'totalVmCount' => $totalVmCount,
            'totalAccountsCount' => $totalAccountsCount
        ];
    }

    $params = comet_ProductParams($product->packageid); // Pass the package ID to the function
    $params['username'] = $product->username;

    if ($params['serverhostname'] === null || $params['serverusername'] === null) {
        // Skip if server details are not found
        return [
            'totalVmCount' => $totalVmCount,
            'totalAccountsCount' => $totalAccountsCount
        ];
    }

    $user = comet_User($params);

    if (is_string($user)) {
        // Skip if user retrieval failed
        return [
            'totalVmCount' => $totalVmCount,
            'totalAccountsCount' => $totalAccountsCount
        ];
    }

    foreach ($user->Sources as $source) {
        // Use the maximum of LastBackupJob and LastSuccessfulBackupJob for VM count
        $lastBackupVmCount = $source->Statistics->LastBackupJob->TotalVmCount ?? 0;
        $lastSuccessfulVmCount = $source->Statistics->LastSuccessfulBackupJob->TotalVmCount ?? 0;
        $totalVmCount += max($lastBackupVmCount, $lastSuccessfulVmCount);
        
        $totalAccountsCount += $source->Statistics->LastBackupJob->TotalAccountsCount ?? 0;
    }

    return [
        'totalVmCount' => $totalVmCount,
        'totalAccountsCount' => $totalAccountsCount
    ];
}

/**
 * Sum the TotalSize of the last-successful job for every protected item
 * owned by the WHMCS service username.
 *
 * @return array  [ 'bytes' => int, 'human' => string ]
 */
function comet_getUserTotalSize($username)
{
    $sumBytes = 0;

    $product = Capsule::table('tblhosting')
        ->where('username', $username)
        ->first(['username', 'packageid']);

    if (!$product) {
        return ['bytes' => 0, 'human' => 'N/A'];
    }

    $params = comet_ProductParams($product->packageid);
    $params['username'] = $product->username;

    if (!$params['serverhostname'] || !$params['serverusername']) {
        return ['bytes' => 0, 'human' => 'N/A'];
    }

    $user = comet_User($params);
    if (is_string($user)) {
        return ['bytes' => 0, 'human' => 'N/A'];
    }

    foreach ($user->Sources as $source) {
        // last *successful* job only
        $sumBytes += $source->Statistics->LastSuccessfulBackupJob->TotalSize ?? 0;
    }

    return [
        'bytes' => $sumBytes,
        'human' => $sumBytes ? comet_HumanFileSize($sumBytes) : 'N/A',
    ];
}


/**
 * Retrieves protected items details for a single Comet account.
 *
 * This function fetches only the necessary protected item information (such as the item name,
 * size, date created, and last successful backup) for the specified Comet account. It is optimized
 * to query only the specific service record rather than processing all products for the client.
 *
 * @param string $username The Comet account username.
 * @param int    $serviceid The service ID associated with the account.
 * @return array An array of protected item details, or an empty array if no data is found.
 */
function getUserProtectedItemsDetails($username, $serviceid) {
    // Query the specific hosting record for this account
    $product = Capsule::table('tblhosting')
        ->where('id', $serviceid)
        ->where('username', $username)
        ->first(['username', 'packageid']);

    if (!$product) {
        return [];
    }

    // Prepare the parameters for the Comet API call
    $params = comet_ProductParams($product->packageid);
    $params['username'] = $username;

    // Retrieve Comet user data for the account
    $user = comet_User($params);
    if (is_string($user)) {
        return [];
    }

    // Build the protected items array from the Comet API data
    $protectedItems = [];
    if (isset($user->Sources)) {
        foreach ($user->Sources as $source) {
            $protectedItems[] = [
                'name'                => $source->Description ?? 'N/A',
                'size'                => isset($source->Statistics->LastBackupJob->TotalSize)
                                         ? comet_HumanFileSize($source->Statistics->LastBackupJob->TotalSize)
                                         : '0',
                'dateCreated'         => isset($source->CreateTime)
                                         ? date('Y-m-d H:i:s', $source->CreateTime)
                                         : 'N/A',
                'lastSuccessfulBackup'=> isset($source->Statistics->LastSuccessfulBackupJob->StartTime)
                                         ? date('Y-m-d H:i:s', $source->Statistics->LastSuccessfulBackupJob->StartTime)
                                         : 'N/A',
            ];
        }
    }

    return $protectedItems;
}

/**
 * Retrieves device details for a single Comet account.
 *
 * This function fetches only the necessary device information for the specified account.
 * It queries the hosting record, calls the Comet API, and builds an array of devices with details
 * such as device name, device ID, registration date, version, platform, remote file access, IP address,
 * timezone, connection time, number of protected items, and online status.
 *
 * @param string $username The Comet account username.
 * @param int    $serviceid The service ID associated with the account.
 * @return array An array of device details, or an empty array if no data is found.
 */
function getUserDevicesDetails($username, $serviceid) {
    // Query the hosting record for this account
    $product = Capsule::table('tblhosting')
        ->where('id', $serviceid)
        ->where('username', $username)
        ->first(['username', 'packageid']);
    
    if (!$product) {
        return [];
    }
    
    // Prepare parameters for the Comet API call
    $params = comet_ProductParams($product->packageid);
    $params['username'] = $username;
    
    // Retrieve Comet user data
    $user = comet_User($params);
    if (is_string($user)) {
        return [];
    }
    
    // (Optional) Fetch active connections to determine online status
    $activeConnections = comet_Server($params)->AdminDispatcherListActive();
    $activeDevices = [];
    foreach ($activeConnections as $connection) {
        $activeDevices[$connection->DeviceID] = $connection; // You can store the full connection object
    }
    
    // Build the devices array with required details
    $devices = [];
    // Determine remote file access from policy (ModeAdminViewFilenames)
    $remoteAccessEnabled = 'Disabled';
    try {
        if (isset($user->Policy) && isset($user->Policy->ModeAdminViewFilenames)) {
            $remoteAccessEnabled = ((int)$user->Policy->ModeAdminViewFilenames > 0) ? 'Enabled' : 'Disabled';
        }
    } catch (\Throwable $e) { /* ignore */ }

    if (isset($user->Devices)) {
        foreach ($user->Devices as $deviceId => $device) {
            $isOnline = isset($activeDevices[$deviceId]);
            $platform = 'N/A';
            if (isset($device->PlatformVersion) && isset($device->PlatformVersion->Distribution)) {
                $platform = $device->PlatformVersion->Distribution;
            }
            $registered = isset($device->RegistrationTime) && $device->RegistrationTime > 0
                ? date('Y-m-d H:i:s', $device->RegistrationTime)
                : 'N/A';
            $version = isset($device->ClientVersion) && $device->ClientVersion !== ''
                ? $device->ClientVersion
                : ($isOnline && isset($activeDevices[$deviceId]->ReportedVersion) ? $activeDevices[$deviceId]->ReportedVersion : 'N/A');
            // Count protected items for this device from global Sources by OwnerDevice
            $protectedCount = 0;
            if (isset($user->Sources)) {
                foreach ((array)$user->Sources as $sid => $src) {
                    // $src may be an object; access property safely
                    if (is_object($src) && isset($src->OwnerDevice) && (string)$src->OwnerDevice === (string)$deviceId) {
                        $protectedCount++;
                    }
                }
            }

            $devices[] = [
                'device_id'          => $deviceId,
                'device_name'        => $device->FriendlyName ?? 'N/A',
                'registered'         => $registered,
                'version'            => $version,
                'platform'           => $platform,
                'remote_file_access' => $remoteAccessEnabled,
                'protected_items'    => $protectedCount,
                'status'             => $isOnline ? 'Online' : 'Offline',
            ];
        }
    }
    
    return $devices;
}

/**
 * Retrieves job logs for a single Comet account.
 *
 * This function fetches only the necessary job details for all devices under the specified Comet account.
 * It returns an array of job logs with details such as Username, Job ID, Device, Protected Item,
 * Storage Vault, Client Version, Type (Backup/Restore), Status, number of directories and files,
 * Size, Storage Vault Size, Uploaded, Downloaded, Start and End times, Duration, Total VM Count,
 * and Total Accounts Count.
 *
 * @param string $username The Comet account username.
 * @param int    $serviceid The service ID associated with the account.
 * @return array An array of job log details, or an empty array if none found.
 */
/**
 * Retrieves job logs for a single Comet account.
 *
 * This function fetches only the necessary job details for all devices under the specified Comet account.
 * It returns an array of job logs with details such as Username, Job ID, Device, Protected Item,
 * Storage Vault, Client Version, Type (Backup/Restore), Status, number of directories and files,
 * Size, Storage Vault Size, Uploaded, Downloaded, Start and End times, Duration, Total VM Count,
 * and Total Accounts Count.
 *
 * @param string $username The Comet account username.
 * @param int    $serviceid The service ID associated with the account.
 * @return array An array of job log details, or an empty array if none found.
 */
function getUserJobLogsDetails($username, $serviceid) {
    // Get the hosting product details for the given account
    $product = Capsule::table('tblhosting')
        ->where('id', $serviceid)
        ->where('username', $username)
        ->first(['username', 'packageid']);
        
    if (!$product) {
        return [];
    }
    
    // Prepare the parameters for the Comet API call
    $params = comet_ProductParams($product->packageid);
    $params['username'] = $username;
    
    // Fetch job details from the Comet API.
    // Assuming getAccountJobDetails returns an array of objects
    $jobDetails = getAccountJobDetails($username);
    
    // Convert each job object into an associative array
    $jobArray = [];
    foreach ($jobDetails as $job) {
        $jobArray[] = json_decode(json_encode($job), true);
    }
    
    return $jobArray;
}


/**
 * Calculate VM guest counts per engine from a loaded Comet user profile.
 *
 * Hyper-V, VMware, and Proxmox all report usage via TotalVmCount on the
 * last job statistics. We prefer LastBackupJob->TotalVmCount and fall back to
 * LastSuccessfulBackupJob->TotalVmCount if needed.
 *
 * @param object $user  The Comet user profile (result of comet_User).
 * @return array { 'hyperv' => int, 'vmware' => int, 'proxmox' => int, 'total' => int }
 */
function comet_getVmCountsByEngineFromUser($user) {
    $counts = [
        'hyperv'  => 0,
        'vmware'  => 0,
        'proxmox' => 0,
        'total'   => 0,
    ];

    if (!isset($user->Sources) || empty($user->Sources)) {
        return $counts;
    }

    foreach ($user->Sources as $source) {
        $engine = isset($source->Engine) ? strtolower((string)$source->Engine) : '';
        if ($engine !== 'engine1/hyperv' && $engine !== 'engine1/vmware' && $engine !== 'engine1/proxmox') {
            continue;
        }

        $vmCount = 0;
        $lastBackupVmCount = 0;
        $lastSuccessfulVmCount = 0;
        
        if (isset($source->Statistics) && isset($source->Statistics->LastBackupJob) && isset($source->Statistics->LastBackupJob->TotalVmCount)) {
            $lastBackupVmCount = (int)$source->Statistics->LastBackupJob->TotalVmCount;
        }
        if (isset($source->Statistics) && isset($source->Statistics->LastSuccessfulBackupJob) && isset($source->Statistics->LastSuccessfulBackupJob->TotalVmCount)) {
            $lastSuccessfulVmCount = (int)$source->Statistics->LastSuccessfulBackupJob->TotalVmCount;
        }
        
        // Use the maximum of both values
        $vmCount = max($lastBackupVmCount, $lastSuccessfulVmCount);

        if ($engine === 'engine1/hyperv') {
            $counts['hyperv'] += $vmCount;
        } elseif ($engine === 'engine1/vmware') {
            $counts['vmware'] += $vmCount;
        } elseif ($engine === 'engine1/proxmox') {
            $counts['proxmox'] += $vmCount;
        }

        $counts['total'] += $vmCount;
    }

    return $counts;
}

/**
 * Convenience wrapper to compute VM guest counts per engine for a WHMCS
 * service username by fetching its Comet user profile first.
 *
 * @param string $username  The Comet/WHMCS service username.
 * @return array { 'hyperv' => int, 'vmware' => int, 'proxmox' => int, 'total' => int }
 */
function comet_getUserVmCountsByEngine($username) {
    $product = Capsule::table('tblhosting')
        ->where('username', $username)
        ->first(['username', 'packageid']);

    if (!$product) {
        return [ 'hyperv' => 0, 'vmware' => 0, 'proxmox' => 0, 'total' => 0 ];
    }

    $params = comet_ProductParams($product->packageid);
    $params['username'] = $product->username;

    if (empty($params['serverhostname']) || empty($params['serverusername'])) {
        return [ 'hyperv' => 0, 'vmware' => 0, 'proxmox' => 0, 'total' => 0 ];
    }

    $user = comet_User($params);
    if (is_string($user)) {
        return [ 'hyperv' => 0, 'vmware' => 0, 'proxmox' => 0, 'total' => 0 ];
    }

    return comet_getVmCountsByEngineFromUser($user);
}

// Helper function used by cometUsage_ClientServices.php that calls comet_getAllUserEngineCounts
// and filters and tallies the counts for the current user.

function getUserEngineCounts($username, $params, $organizationId) {
    // Retrieve all engine usage data
    $combinedSourceInfo = comet_getAllUserEngineCounts($params, $organizationId);

    // Initialize counts for the desired engine types
    $userEngineCounts = [
        "engine1/windisk" => 0,
        "engine1/file"    => 0,
    ];

    // Loop through each record and tally counts using a case-insensitive username comparison
    foreach ($combinedSourceInfo as $sourceInfo) {
        if (isset($sourceInfo['Username']) && strtolower($sourceInfo['Username']) === strtolower($username)) {
            $engine = $sourceInfo['engine'] ?? '';
            if (array_key_exists($engine, $userEngineCounts)) {
                $userEngineCounts[$engine]++;
            }
        }
    }

    return $userEngineCounts;
}
