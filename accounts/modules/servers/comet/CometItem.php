<?php

namespace Comet;

use WHMCS\Database\Capsule;
use Carbon\Carbon;

class CometItem
{
    public $id;
    public $comet_user_id;
    public $content;
    public $comet_device_id;
    public $name;
    public $type;
    public $total_bytes;
    public $total_files;
    public $total_directories;
    public $created_at;
    public $updated_at;

    /**
     * Set the Comet item fields value
     */
    public static function setItem($item)
    {
        $content = json_decode($item->content);
        $item->comet_device_id = hash('sha256', $item->comet_user_id . $content->OwnerDevice);
        $item->name = $content->Description;
        $item->type = $content->Engine;
        $item->total_bytes = $content->Statistics->LastBackupJob->TotalSize;
        $item->total_files = $content->Statistics->LastBackupJob->TotalFiles;
        $item->total_directories = $content->Statistics->LastBackupJob->TotalDirectories;
        $item->created_at = Carbon::createFromTimestamp($content->CreateTime)->format('Y-m-d H:i:s');
        $item->updated_at = Carbon::createFromTimestamp($content->ModifyTime)->format('Y-m-d H:i:s');

        return $item;
    }

    /**
     * Record the Comet item history
     */
    public static function itemHistory($item, $action)
    {
        try {
            $cometUser = Capsule::table('comet_users')->where('id', $item->comet_user_id)->first();
            if (!$cometUser) {
                throw new \Exception('Comet user not found');
            }

            $user = Capsule::table('tblclients')->where('id', $cometUser->user_id)->first();
            if (!$user) {
                throw new \Exception('User not found');
            }

            $backup = Capsule::table('backup_plan_users')
                ->where('comet_username', $cometUser->username)
                ->where('comet_server_id', $cometUser->comet_server_id)
                ->first();
            if (!$backup) {
                throw new \Exception('Backup plan not found');
            }

            $deviceHistory = Capsule::table('comet_device_histories')
                ->where('backup_plan_id', $backup->id)
                ->where('expiry_date', $backup->expiry_date)
                ->where('comet_device_id', $item->comet_device_id)
                ->where('comet_user_id', $item->comet_user_id)
                ->first();
            if (!is_null($deviceHistory) && 0 == $deviceHistory->is_eligible_for_charge) {
                Capsule::table('comet_device_histories')->where('id', $deviceHistory->id)->update([
                    'is_eligible_for_charge' => '1',
                    'eligible_for_charge_date' => Carbon::now()
                ]);
            }

            Capsule::table('user_comet_histories')->insert([
                'content' => json_encode($item),
                'comet_user_id' => $item->comet_user_id,
                'user_id' => $cometUser->user_id,
                'parent_id' => $user->parent_id,
                'backup_plan_id' => $backup->id,
                'total_bytes' => $item->total_bytes,
                'total_files' => $item->total_files,
                'total_directories' => $item->total_directories,
                'type_id' => $item->id,
                'type' => 'BOOSTERS',
                'action' => $action
            ]);
        } catch (\Throwable $th) {
            // Minimal logging to avoid performance impact
            error_log('CometItem error: ' . $th->getMessage());
        }
    }

    /**
     * Get the device that owns the protected item.
     */
    public static function getDevice($comet_device_id)
    {
        return Capsule::table('comet_devices')->where('id', $comet_device_id)->first();
    }

    /**
     * Get the user that owns the protected item.
     */
    public static function getUser($comet_user_id)
    {
        return Capsule::table('comet_users')->where('id', $comet_user_id)->first();
    }

    /**
     * Get the jobs for the protected item.
     */
    public static function getJobs($comet_item_id)
    {
        return Capsule::table('comet_jobs')->where('comet_item_id', $comet_item_id)->get();
    }

    public static function hasActive($comet_item_id)
    {
        return Capsule::table('comet_jobs')->where('comet_item_id', $comet_item_id)->whereIn('status', ['ACTIVE', 'REVIVED'])->exists();
    }

    public static function getTypeAttribute($type)
    {
        return ItemType::toString($type);
    }

    public static function getRawTypeAttribute($type)
    {
        return $type;
    }

    /**
     * Get the recent jobs for the device grouped by status.
     */
    public static function recentStatusByDay($comet_item_id, int $days)
    {
        return Capsule::table('comet_jobs')
            ->where('comet_item_id', $comet_item_id)
            ->orderBy('started_at', 'desc')
            ->take($days)
            ->get();
    }

    /**
     * Fetch the Protected Item description by its GUID using the user profile
     */
    public static function getProtectedItemDescriptions($cometServer, $username, $sourceGUIDs)
    {
        $descriptions = [];
        try {
            $response = $cometServer->AdminGetUserProfile($username);

            foreach ($sourceGUIDs as $guid) {
                if (isset($response->Sources[$guid])) {
                    $descriptions[$guid] = $response->Sources[$guid]->Description;
                } else {
                    $descriptions[$guid] = 'Unknown Item';
                }
            }
        } catch (\Exception $e) {
            // Minimal logging here
            error_log('Error fetching protected item descriptions for user ' . $username . ': ' . $e->getMessage());
            foreach ($sourceGUIDs as $guid) {
                $descriptions[$guid] = 'Unknown Item';
            }
        }

        return $descriptions;
    }

    /**
     * Improved getProtectedItems(): Fetch protected items using cached package IDs and API responses.
     *
     * @param int $clientId
     * @return array
     */
    public static function getProtectedItems($clientId)
    {
        $protectedItems = [];
        $totalVmCountsByUser = [];
        $cachedProfiles = []; // Cache the API response per username
        $cachedParams = [];   // Cache parameters and package IDs per username

        // Retrieve all usernames for the client.
        $accounts = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->pluck('username');

        // Retrieve package IDs for all accounts once and key by username.
        $packageData = Capsule::table('tblhosting')
            ->where('userid', $clientId)
            ->select('username', 'packageid')
            ->get()
            ->keyBy('username');

        // Loop over each account and cache the API response.
        foreach ($accounts as $username) {
            if (!isset($packageData[$username]) || !$packageData[$username]->packageid) {
                continue;
            }
            $packageid = $packageData[$username]->packageid;
            $params = comet_ProductParams($packageid);
            $params['username'] = $username;
            $cachedParams[$username] = $params;

            if ($params['serverhostname'] === null || $params['serverusername'] === null) {
                continue;
            }

            try {
                $cometServer = comet_Server($params);
                $userProfile = $cometServer->AdminGetUserProfile($params['username']);
                $cachedProfiles[$username] = $userProfile;
            } catch (\Exception $e) {
                // Minimal logging; skip accounts with API errors.
                // error_log("Error fetching user profile for {$username}: " . $e->getMessage());
                continue;
            }
        }

        // Process the cached API responses to compute total VM counts and compile details.
        foreach ($cachedProfiles as $username => $userProfile) {
            $sources = isset($userProfile->Sources) ? $userProfile->Sources : [];
            $devices = isset($userProfile->Devices) ? $userProfile->Devices : [];

            // Calculate total VM count for this user.
            $totalVmCount = 0;
            foreach ($sources as $source) {
                $vmCount = isset($source->Statistics->LastSuccessfulBackupJob->TotalVmCount)
                    ? $source->Statistics->LastSuccessfulBackupJob->TotalVmCount
                    : 0;
                $totalVmCount += $vmCount;
            }
            $totalVmCountsByUser[$username] = $totalVmCount;

            // Compile protected item details.
            foreach ($sources as $key => $source) {
                $itemid = htmlspecialchars($key);
                $engine = htmlspecialchars($source->Engine);
                $description = htmlspecialchars($source->Description);
                $ownerDevice = htmlspecialchars($source->OwnerDevice);

                $deviceFriendlyName = isset($devices[$ownerDevice])
                    ? htmlspecialchars($devices[$ownerDevice]->FriendlyName)
                    : 'N/A';

                $engineProps_INCLUDE = [];
                if (isset($source->EngineProps) && is_object($source->EngineProps)) {
                    foreach ($source->EngineProps as $propKey => $propValue) {
                        if (strpos($propKey, 'INCLUDE') === 0) {
                            $engineProps_INCLUDE[] = htmlspecialchars($propValue);
                        }
                    }
                }

                $totalAccountsCount = isset($source->Statistics->LastSuccessfulBackupJob->TotalAccountsCount)
                    ? $source->Statistics->LastSuccessfulBackupJob->TotalAccountsCount
                    : 'N/A';
                $TotalVmCount = isset($source->Statistics->LastSuccessfulBackupJob->TotalVmCount)
                    ? $source->Statistics->LastSuccessfulBackupJob->TotalVmCount
                    : 'N/A';

                $engineProps_INCLUDE_str = implode(', ', $engineProps_INCLUDE);

                $lastBackupJob = (isset($source->Statistics->LastBackupJob->StartTime) && $source->Statistics->LastBackupJob->StartTime != 0)
                    ? htmlspecialchars(gmdate('Y-m-d H:i:s', $source->Statistics->LastBackupJob->StartTime))
                    : 'N/A';
                $lastJobTotalSize = (isset($source->Statistics->LastBackupJob->TotalSize) && $source->Statistics->LastBackupJob->TotalSize != 0)
                    ? comet_HumanFileSize($source->Statistics->LastBackupJob->TotalSize)
                    : 'N/A';
                $lastBackupUploadSize = (isset($source->Statistics->LastBackupJob->UploadSize) && $source->Statistics->LastBackupJob->UploadSize != 0)
                    ? comet_HumanFileSize($source->Statistics->LastBackupJob->UploadSize)
                    : 'N/A';
                $lastJobDownloadSize = (isset($source->Statistics->LastBackupJob->DownloadSize) && $source->Statistics->LastBackupJob->DownloadSize != 0)
                    ? comet_HumanFileSize($source->Statistics->LastBackupJob->DownloadSize)
                    : 'N/A';
                $lastSuccessJob = (isset($source->Statistics->LastSuccessfulBackupJob->StartTime) && $source->Statistics->LastSuccessfulBackupJob->StartTime != 0)
                    ? htmlspecialchars(gmdate('Y-m-d H:i:s', $source->Statistics->LastSuccessfulBackupJob->StartTime))
                    : 'N/A';
                $lastSuccessJobTotalSize = (isset($source->Statistics->LastSuccessfulBackupJob->TotalSize) && $source->Statistics->LastSuccessfulBackupJob->TotalSize != 0)
                    ? comet_HumanFileSize($source->Statistics->LastSuccessfulBackupJob->TotalSize)
                    : 'N/A';
                $lastsuccessUploadSize = (isset($source->Statistics->LastSuccessfulBackupJob->UploadSize) && $source->Statistics->LastSuccessfulBackupJob->UploadSize != 0)
                    ? comet_HumanFileSize($source->Statistics->LastSuccessfulBackupJob->UploadSize)
                    : 'N/A';
                $lastsuccessDownloadSize = (isset($source->Statistics->LastSuccessfulBackupJob->DownloadSize) && $source->Statistics->LastSuccessfulBackupJob->DownloadSize != 0)
                    ? comet_HumanFileSize($source->Statistics->LastSuccessfulBackupJob->DownloadSize)
                    : 'N/A';

                $sourceInfo = [
                    'Username' => $username,
                    'itemid' => $itemid,
                    'engine' => $engine,
                    'description' => $description,
                    'ownerDevice' => $ownerDevice,
                    'deviceFriendlyName' => $deviceFriendlyName,
                    'engineProps_INCLUDE_str' => $engineProps_INCLUDE_str,
                    'totalAccountsCount' => $totalAccountsCount,
                    'TotalVmCount' => $TotalVmCount,
                    'TotalVmCountForUser' => $totalVmCountsByUser[$username],
                    'lastBackupJob' => $lastBackupJob,
                    'lastJobTotalSize' => $lastJobTotalSize,
                    'lastBackupUploadSize' => $lastBackupUploadSize,
                    'lastJobDownloadSize' => $lastJobDownloadSize,
                    'lastSuccessJob' => $lastSuccessJob,
                    'lastSuccessJobTotalSize' => $lastSuccessJobTotalSize,
                    'lastsuccessUploadSize' => $lastsuccessUploadSize,
                    'lastsuccessDownloadSize' => $lastsuccessDownloadSize,
                ];

                $protectedItems[] = $sourceInfo;
            }
        }

        return $protectedItems;
    }
}
