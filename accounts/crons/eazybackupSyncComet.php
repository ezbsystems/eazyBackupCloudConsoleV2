<?php

require __DIR__ . '/../init.php';

use Comet\Server;
use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\Eazybackup\Comet;

// File logging function for debugging (commented out for production)
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/eazybackup_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}";
    
    if ($data !== null) {
        $logEntry .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT);
    }
    
    $logEntry .= "\n" . str_repeat('-', 80) . "\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}


// 1) Log start time
$startTime = time();
logModuleCall(
    'eazybackup',
    'eazyBackupSyncComet cron Start',
    ['start' => date('Y-m-d H:i:s', $startTime)],
    'Cron execution started'
);


$excludeProductgroupIds = [2, 11];
$products = Capsule::table('tblproducts')
    ->select('id', 'servergroup')
    ->whereNotIn('gid', $excludeProductgroupIds)
    ->get();

$productServerGroupMap = [];
foreach ($products as $product) {
    if ($product->servergroup > 0) {
        $productServerGroupMap[$product->id] = $product->servergroup;
    }
}

$productIds = array_keys($productServerGroupMap);
$groupIds = array_unique(array_values($productServerGroupMap));

$productCount = count($productIds);
$groupCount = count($groupIds);
logModuleCall(
    'eazybackup',
    'cron ProductsAndGroups',
    [
        'products_considered' => $productCount,
        'groups_considered' => $groupCount,
        'excluded_group_ids' => $excludeProductgroupIds,
    ],
    'Mapped products to server groups'
);

$servergroups = Capsule::table("tblservergroups")
    ->select('id', 'name')
    ->whereIn('id', $groupIds)
    ->pluck('name', 'id')
    ->toArray();

// Map group IDs to server IDs using WHMCS group relationships
$groupToServerIds = Capsule::table('tblservergroupsrel')
    ->select('groupid', 'serverid')
    ->whereIn('groupid', $groupIds)
    ->get()
    ->groupBy('groupid');

// Fetch all referenced servers by ID
$allServerIds = [];
foreach ($groupToServerIds as $gid => $rels) {
    // $rels may be a Collection
    if ($rels instanceof \Illuminate\Support\Collection) {
        foreach ($rels as $rel) {
            $allServerIds[] = $rel->serverid;
        }
    } elseif (is_array($rels)) {
        foreach ($rels as $rel) {
            $allServerIds[] = $rel->serverid;
        }
    }
}
$allServerIds = array_values(array_unique($allServerIds));

$serversById = Capsule::table('tblservers')
    ->select('id', 'name', 'hostname', 'secure', 'port', 'username', 'password')
    ->whereIn('id', $allServerIds)
    ->get()
    ->keyBy('id');


// ✨ OPTIMIZATION: Create an array of reusable Server API clients
$serverClients = [];
$serverBaseUrls = [];
foreach ($groupIds as $gid) {
    // Choose the first server in the group (or any selection logic you prefer)
    $relList = isset($groupToServerIds[$gid]) ? $groupToServerIds[$gid] : null;
    if ($relList instanceof \Illuminate\Support\Collection) {
        $relFirst = $relList->first();
    } elseif (is_array($relList) && !empty($relList)) {
        $relFirst = reset($relList);
    } else {
        $relFirst = null;
    }
    if (!$relFirst) {
        logModuleCall('eazybackup', 'cron ServerClients missing', ['group_id' => $gid], 'No servers related to group');
        continue;
    }
    // $serversById may be a Collection keyed by id
    if ($serversById instanceof \Illuminate\Support\Collection) {
        $server = $serversById->get($relFirst->serverid);
    } else {
        $server = $serversById[$relFirst->serverid] ?? null;
    }
    if (!$server) {
        logModuleCall('eazybackup', 'cron ServerClients missingServer', ['group_id' => $gid, 'server_id' => $relFirst->serverid], 'Server not found by ID');
        continue;
    }

    $password = localAPI("DecryptPassword", ['password2' => $server->password]);
    $host = $server->secure ? 'https' : 'http';
    $port = empty($server->port) ? "" : ":" . $server->port;

    // Clean up the hostname and build the URL
    $hostname = preg_replace(["/^http:\/\//i", "/^https:\/\//i", "/\/$/"], "", $server->hostname);
    $url = $host . "://" . $hostname . ($port ? $port : '') . "/";

    $serverClients[$gid] = new Server($url, $server->username, $password['password']);
    $serverBaseUrls[$gid] = $url;
}

logModuleCall(
    'eazybackup',
    'cron ServerClients',
    [
        'servergroups' => $servergroups,
        'group_to_server_ids' => $groupToServerIds,
        'client_keys' => array_keys($serverClients),
        'client_count' => count($serverClients),
    ],
    'Initialized server API clients'
);

$deviceStatuses = [];
$cometObject = new Comet();

// Get device statuses using the reusable clients
foreach ($serverClients as $packageId => $serverClient) {
    try {
        $deviceStatuses[$packageId] = $serverClient->AdminDispatcherListActive();
    } catch (\Throwable $th) {
        // Initialize empty array if the call fails
        $deviceStatuses[$packageId] = [];
        logModuleCall(
            'eazybackup',
            'cron AdminDispatcherListActive error',
            ['package_id' => $packageId, 'trace' => $th->getTraceAsString()],
            $th->getMessage()
        );
    }
}

logModuleCall(
    'eazybackup',
    'cron DeviceStatuses',
    [
        'groups_with_status' => array_keys($deviceStatuses),
        'status_counts' => array_map(function ($v) { return is_array($v) ? count($v) : 0; }, $deviceStatuses),
    ],
    'Collected active device statuses'
);

// ✨ OPTIMIZATION: Fetch ALL user profiles in bulk using AdminListUsersFull()
// This replaces ~1,475 individual AdminGetUserProfile() calls with ~3 bulk calls
$allUserProfiles = []; // Map: gid => [username => UserProfileConfig]
foreach ($serverClients as $gid => $serverClient) {
    try {
        $profiles = $serverClient->AdminListUsersFull();
        $allUserProfiles[$gid] = $profiles;
        $profileCount = is_array($profiles) ? count($profiles) : 0;
        logModuleCall(
            'eazybackup',
            'cron AdminListUsersFull',
            [
                'group_id' => $gid,
                'profiles_fetched' => $profileCount,
            ],
            'Fetched all user profiles in bulk'
        );
    } catch (\Throwable $e) {
        $allUserProfiles[$gid] = [];
        logModuleCall(
            'eazybackup',
            'cron AdminListUsersFull error',
            ['group_id' => $gid, 'trace' => $e->getTraceAsString()],
            $e->getMessage()
        );
    }
}

// Process jobs and upsert into comet_jobs (nightly safety net)
// NOTE: We upsert per-server to avoid keeping a massive $allJobs array in memory.
$jobsUpsertedTotal = 0;
foreach ($serverClients as $packageId => $serverClient) {
    try {
        $endTimestamp = time();
        // $startTimestamp = strtotime('-5 minutes');
        $startTimestamp = strtotime('-14 days');
        $jobs = $serverClient->AdminGetJobsForDateRange($startTimestamp, $endTimestamp);
        $jobCount = is_array($jobs) ? count($jobs) : 0;

        logModuleCall(
            'eazybackup',
            'cron JobsFetched',
            [
                'package_id' => $packageId,
                'start' => $startTimestamp,
                'end' => $endTimestamp,
                'jobs_count' => $jobCount,
            ],
            'Fetched jobs for date range'
        );

        if ($jobCount > 0) {
            // upsertJobs() is internally defensive; it will skip unknown usernames.
            $cometObject->upsertJobs($jobs);
            $jobsUpsertedTotal += $jobCount;
        }
    } catch (\Throwable $e) {
        logModuleCall(
            'eazybackup',
            'cron AdminGetJobsForDateRange error',
            ['package_id' => $packageId, 'trace' => $e->getTraceAsString()],
            $e->getMessage()
        );
    }
}

logModuleCall(
    'eazybackup',
    'cron JobsUpserted',
    ['total_jobs' => (int)$jobsUpsertedTotal],
    'Upserted jobs'
);

// // Prune old job logs (disabled for testing)
// try {
//     $fourWeeksAgo = date('Y-m-d H:i:s', strtotime('-4 weeks'));
//     Capsule::table('comet_jobs')->where('started_at', '<', $fourWeeksAgo)->delete(); 
//     logModuleCall(
//         "eazybackup",
//         'cron pruneJobs',
//         ['delete_before' => $fourWeeksAgo],
//         'Successfully pruned old job logs'
//     );
// 
// } catch (\Exception $e) {
//     logModuleCall(
//         "eazybackup",
//         'cron pruneJobs',
//         ['delete_before' => $fourWeeksAgo, 'trace' => $e->getTraceAsString()],
//         $e->getMessage()
//     );
// }

// Track vault summary across run
$__vaultAgg = ['seen'=>0,'updated'=>0,'skipped'=>0,'errors'=>0];

$hostings = Capsule::table('tblhosting')
    ->select('id', 'userid', 'username', 'packageid')
    ->where('domainstatus', 'Active')
    ->whereIn('packageid', $productIds)
    ->orderBy('id', 'ASC')
    ->get();

// Convert Collection to array for batch processing
$hostingsArray = $hostings->toArray();

logModuleCall(
    'eazybackup',
    'cron HostingsSelected',
    [
        'total_hostings' => count($hostingsArray),
        'batch_size' => 50,
    ],
    'Selected active hostings for processing'
);

// Process hostings using pre-fetched profiles (no more N+1 API calls)
$processedCount = 0;
$skippedCount = 0;

foreach ($hostingsArray as $hosting) {
    $packageId = $hosting->packageid;
    $gid = $productServerGroupMap[$packageId] ?? null;

    // Check if a client exists for this group to avoid errors
    if (!$gid || !isset($serverClients[$gid])) {
        $skippedCount++;
        continue;
    }

    // ✨ OPTIMIZATION: Look up profile from pre-fetched bulk data instead of API call
    $profilesForGroup = $allUserProfiles[$gid] ?? [];
    $userProfile = $profilesForGroup[$hosting->username] ?? null;

    if ($userProfile === null) {
        // User not found on Comet server (may have been deleted or never existed)
        $skippedCount++;
        continue;
    }
    
    try {
        $server = $serverClients[$gid];
        
        // upsert devices
        $deviceStatusesForGid = $deviceStatuses[$gid] ?? [];
        $cometObject->upsertDevices($userProfile, $hosting, $deviceStatusesForGid);
        
        // upsert protected items
        $cometObject->upsertItems($userProfile, $hosting);
        
        // upsert vaults
        $serverUrl = $serverBaseUrls[$gid] ?? '';
        $vaultStats = $cometObject->upsertVaults($userProfile, $hosting, $serverUrl, $server);
        if ((getenv('EB_VAULT_LOG') === '1') && is_array($vaultStats)) {
            logModuleCall('eazybackup','cron upsertVaultsSummary',[ 'username'=>$hosting->username, 'server'=>$serverUrl ], $vaultStats);
        }
        if (is_array($vaultStats)) {
            foreach ($__vaultAgg as $k=>$_) { $__vaultAgg[$k] += (int)($vaultStats[$k] ?? 0); }
        }
        
        $processedCount++;
        
    } catch (\Exception $e) {
        logModuleCall(
            'eazybackup',
            'cron UserProfile error',
            [
                'hosting_id' => $hosting->id,
                'username' => $hosting->username,
                'gid' => $gid,
                'trace' => $e->getTraceAsString(),
            ],
            $e->getMessage()
        );
        continue;
    }
}

// 2) Log end time and duration
$endTime  = time();
$duration = $endTime - $startTime;
logModuleCall(
    'eazybackup',
    'cron Complete',
    [
        'end'              => date('Y-m-d H:i:s', $endTime),
        'duration_seconds' => $duration,
        'hostings_total'   => count($hostingsArray),
        'hostings_processed' => $processedCount,
        'hostings_skipped' => $skippedCount,
        'vaults_seen'      => $__vaultAgg['seen'] ?? 0,
        'vaults_updated'   => $__vaultAgg['updated'] ?? 0,
        'vaults_skipped'   => $__vaultAgg['skipped'] ?? 0,
        'vaults_errors'    => $__vaultAgg['errors'] ?? 0,
    ],
    "Cron finished in {$duration} seconds"
);
