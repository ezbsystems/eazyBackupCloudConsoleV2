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
            $canonicalUsername = (string)$client->username;
            $clientId = (int)$client->userid;
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
                    'id' => hash('sha256', $clientId . $deviceId),
                    'client_id' => $clientId,
                    'username' => $canonicalUsername,
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
                    ->where('client_id', $clientId)
                    ->where('username', $canonicalUsername)
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
            $canonicalUsername = (string)$client->username;
            $clientId = (int)$client->userid;
            // Normalize Sources to an array to handle stdClass safely
            $sources = isset($userProfile->Sources)
                ? (is_array($userProfile->Sources) ? $userProfile->Sources : (is_object($userProfile->Sources) ? get_object_vars($userProfile->Sources) : []))
                : [];

            // Wrap upsert + prune in a transaction for consistency
            Capsule::connection()->transaction(function () use ($sources, $clientId, $canonicalUsername) {
                $seenIds = [];

                foreach ($sources as $itemId => $item) {
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
                    'client_id' => $clientId,
                    'username' => $canonicalUsername,
                    'content' => json_encode($item),
                    'comet_device_id' => hash('sha256', $clientId . $item->OwnerDevice),
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
                    $seenIds[] = (string)$itemId;
                }

                // Prune rows not present upstream for this user
                if (empty($seenIds)) {
                    Capsule::table('comet_items')
                        ->where('client_id', $clientId)
                        ->where('username', $canonicalUsername)
                        ->delete();
                } else {
                    Capsule::table('comet_items')
                        ->where('client_id', $clientId)
                        ->where('username', $canonicalUsername)
                        ->whereNotIn('id', $seenIds)
                        ->delete();
                }
            });
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
     * Add or Update Protected Vaults with defensive writes.
     * - Never clobber bucket fields or total_bytes with empty/NULL/0 on failure
     * - Provider-aware parsing (Comet Server, B2, S3/S3-compatible)
     * - Track last_success_at / last_error
     *
     * @param object $userProfile
     * @param object $client  tblhosting row-like (id, userid, username)
     * @param string $serverUrl Normalized Comet server base URL (e.g., https://csw.example.com/)
     * @return array{seen:int,updated:int,skipped:int,errors:int}
     */
    public function upsertVaults($userProfile, $client, $serverUrl = '', $serverClient = null)
    {
        $stats = ['seen' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $logVerbose = getenv('EB_VAULT_LOG') === '1';
        try {
            $canonicalUsername = (string)$client->username;
            $clientId = (int)$client->userid;

            $destinations = isset($userProfile->Destinations)
                ? (is_array($userProfile->Destinations) ? $userProfile->Destinations : get_object_vars($userProfile->Destinations))
                : [];

            // Cache recent jobs per user once per run to avoid repeated API calls
            $recentJobs = null;
            $jobsWindowEnd = time();
            $jobsWindowStart = strtotime('-2 days', $jobsWindowEnd);

            foreach ($destinations as $vaultId => $vault) {
                $stats['seen']++;

                $arr = is_array($vault) ? $vault : (is_object($vault) ? get_object_vars($vault) : []);

                // Times
                $normalizeTs = function ($v) {
                    if ($v === null || $v === '' || $v === 'Unknown') return null;
                    if (is_numeric($v)) { $n = (int)$v; if ($n > 1000000000000) { $n = (int) floor($n / 1000); } return $n > 0 ? $n : null; }
                    $ts = @strtotime((string)$v); return $ts !== false ? (int)$ts : null;
                };
                $ctTs = $normalizeTs($arr['CreateTime'] ?? null) ?? time();
                $createdAt = date('Y-m-d H:i:s', $ctTs);
                $now = date('Y-m-d H:i:s');

                // Core fields
                $name = trim((string)($arr['DisplayName'] ?? $arr['Description'] ?? ''));
                $type = (int)($arr['DestinationType'] ?? $arr['Type'] ?? 0);
                $hasLimit   = (int) (!empty($arr['LimitStorage']) || !empty($arr['StorageLimitEnabled']));
                $limitBytes = (int)   ($arr['StorageLimitBytes'] ?? $arr['LimitStorageBytes'] ?? 0);

                // ---------- BYTES: read it correctly, do NOT coalesce to zero ----------
                $bytes = null;
                $bytesSource = null;           // 'CPS', 'JOB_END', 'JOB_START'
                $bytesTrustedZero = false;     // true only when zero is explicitly measured

                // 1) Primary source: Destinations[].Statistics.ClientProvidedSize.Size
                if (isset($arr['Statistics'])) {
                    $statsArr = is_array($arr['Statistics'])
                        ? $arr['Statistics']
                        : (is_object($arr['Statistics']) ? get_object_vars($arr['Statistics']) : []);

                    if (isset($statsArr['ClientProvidedSize'])) {
                        $cps = is_array($statsArr['ClientProvidedSize'])
                            ? $statsArr['ClientProvidedSize']
                            : (is_object($statsArr['ClientProvidedSize']) ? get_object_vars($statsArr['ClientProvidedSize']) : []);

                        if (array_key_exists('Size', $cps) && $cps['Size'] !== null) {
                            $n = (int)$cps['Size'];
                            if ($n > 0) {
                                $bytes = $n;
                                $bytesSource = 'CPS';
                            } elseif ($n === 0) {
                                // Trust an explicit zero only if there was a measurement (prevents "missing" → 0)
                                $ms = isset($cps['MeasureStarted']) ? (int)$cps['MeasureStarted'] : 0;
                                $mc = isset($cps['MeasureCompleted']) ? (int)$cps['MeasureCompleted'] : 0;
                                if ($ms > 0 || $mc > 0) {
                                    $bytes = 0;
                                    $bytesSource = 'CPS';
                                    $bytesTrustedZero = true;
                                }
                            }
                        }
                    }
                }

                // 2) Helpful fallback: pull from the user profile's last job DestinationSizeEnd for this vault
                if ($bytes === null && isset($userProfile->Sources)) {

                    // Normalize Sources to an array (stdClass-safe)
                    $sourcesArr = is_array($userProfile->Sources)
                        ? $userProfile->Sources
                        : (is_object($userProfile->Sources) ? get_object_vars($userProfile->Sources) : []);

                    foreach ($sourcesArr as $srcGuid => $srcObj) {
                        $s = is_array($srcObj) ? $srcObj : (is_object($srcObj) ? get_object_vars($srcObj) : []);
                        if (!isset($s['Statistics'])) continue;

                        $sStats = is_array($s['Statistics']) ? $s['Statistics']
                               : (is_object($s['Statistics']) ? get_object_vars($s['Statistics']) : []);

                        // Prefer LastSuccessfulBackupJob, then LastBackupJob
                        foreach (['LastSuccessfulBackupJob','LastBackupJob'] as $jobKey) {
                            if (!isset($sStats[$jobKey])) continue;

                            $job = is_array($sStats[$jobKey]) ? $sStats[$jobKey]
                                 : (is_object($sStats[$jobKey]) ? get_object_vars($sStats[$jobKey]) : []);

                            // Only consider jobs that wrote to THIS vault GUID
                            if (($job['DestinationGUID'] ?? '') !== (string)$vaultId) continue;

                            // Normalize nested DestinationSizeEnd/Start in case they are objects
                            $dstEnd   = isset($job['DestinationSizeEnd'])
                                        ? (is_array($job['DestinationSizeEnd']) ? $job['DestinationSizeEnd'] : (is_object($job['DestinationSizeEnd']) ? get_object_vars($job['DestinationSizeEnd']) : []))
                                        : null;
                            $dstStart = isset($job['DestinationSizeStart'])
                                        ? (is_array($job['DestinationSizeStart']) ? $job['DestinationSizeStart'] : (is_object($job['DestinationSizeStart']) ? get_object_vars($job['DestinationSizeStart']) : []))
                                        : null;

                            if ($dstEnd && array_key_exists('Size', $dstEnd) && $dstEnd['Size'] !== null) {
                                $n = (int)$dstEnd['Size'];
                                $bytes = $n;                // allow zero here: it is an explicit measured value
                                $bytesSource = 'JOB_END';
                                $bytesTrustedZero = ($n === 0);
                                break 2;
                            }
                            if ($dstStart && array_key_exists('Size', $dstStart) && $dstStart['Size'] !== null) {
                                $n = (int)$dstStart['Size'];
                                $bytes = $n;
                                $bytesSource = 'JOB_START';
                                $bytesTrustedZero = ($n === 0);
                                break 2;
                            }
                        }
                    }
                }

                // Optional on-demand fallback: query recent jobs if stats are inconclusive
                if ($bytes === null && $serverClient) {
                    try {
                        if ($recentJobs === null) {
                            $recentJobs = $serverClient->AdminGetJobsForDateRange($jobsWindowStart, $jobsWindowEnd);
                        }
                        if (is_array($recentJobs)) {
                            foreach ($recentJobs as $job) {
                                // normalize job array
                                $j = is_array($job) ? $job : (is_object($job) ? get_object_vars($job) : []);
                                if (($j['Username'] ?? '') !== (string)$client->username) continue;
                                if (($j['DestinationGUID'] ?? '') !== (string)$vaultId) continue;
                                // prefer DestinationSizeEnd, then Start
                                $dstEnd   = isset($j['DestinationSizeEnd']) ? (is_array($j['DestinationSizeEnd']) ? $j['DestinationSizeEnd'] : (is_object($j['DestinationSizeEnd']) ? get_object_vars($j['DestinationSizeEnd']) : [])) : null;
                                $dstStart = isset($j['DestinationSizeStart']) ? (is_array($j['DestinationSizeStart']) ? $j['DestinationSizeStart'] : (is_object($j['DestinationSizeStart']) ? get_object_vars($j['DestinationSizeStart']) : [])) : null;
                                if ($dstEnd && array_key_exists('Size', $dstEnd) && $dstEnd['Size'] !== null) {
                                    $n = (int)$dstEnd['Size'];
                                    $bytes = $n; $bytesSource='JOB_END'; $bytesTrustedZero = ($n === 0);
                                    break;
                                }
                                if ($dstStart && array_key_exists('Size', $dstStart) && $dstStart['Size'] !== null) {
                                    $n = (int)$dstStart['Size'];
                                    $bytes = $n; $bytesSource='JOB_START'; $bytesTrustedZero = ($n === 0);
                                    break;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore API fallback errors
                    }
                }

                // Log untrusted zeros (optional, only when verbose)
                if ($bytes === null && $logVerbose) {
                    logModuleCall('eazybackup', 'vaultSizeMissing', 
                        ['username' => $client->username, 'vault_id' => (string)$vaultId, 'source' => $bytesSource],
                        'No trustworthy size in CPS or job fallback. Statistics: ' . substr(json_encode($arr['Statistics'] ?? [], JSON_UNESCAPED_SLASHES), 0, 2000)
                    );
                }
                if ($bytes === 0 && !$bytesTrustedZero && $logVerbose) {
                    logModuleCall('eazybackup', 'vaultSizeZeroUntrusted', 
                        ['username' => $client->username, 'vault_id' => (string)$vaultId, 'source' => $bytesSource],
                        'Zero size present but not explicitly measured; not writing to DB'
                    );
                }

                // Provider-aware parsing
                [$bucketName, $bucketKey, $endpoint] = $this->extractBucketFields($arr, $type, (string)$serverUrl);

                // Decide success vs error
                $parsedAny = ($bucketName !== '' || $bucketKey !== '' || $endpoint !== '' || $bytes !== null);
                $errorMsg = $parsedAny ? null : ('parse-failed: type=' . (string)$type);

                // Insert or update safely
                $exists = Capsule::table('comet_vaults')->where('id', (string)$vaultId)->exists();

                if (!$exists) {
                    // Insert: fill known fields; allow empty strings for bucket fields
                    $row = [
                        'id' => (string)$vaultId,
                        'client_id' => $clientId,
                        'username' => $canonicalUsername,
                        'content' => json_encode($arr, JSON_UNESCAPED_SLASHES),
                        'name' => $name,
                        'type' => $type,
                        'bucket_server' => $endpoint,
                        'bucket_name' => $bucketName,
                        'bucket_key' => $bucketKey,
                        'has_storage_limit' => $hasLimit,
                        'storage_limit_bytes' => $limitBytes,
                        'is_active' => 1,
                        'created_at' => $createdAt,
                        'updated_at' => $now,
                        'removed_at' => null,
                        'last_success_at' => $parsedAny ? date('Y-m-d H:i:s') : null,
                        'last_error' => $errorMsg,
                    ];
                    if ($bytes !== null && ($bytes > 0 || $bytesTrustedZero)) {
                        $row['total_bytes'] = $bytes;
                    }
                    Capsule::table('comet_vaults')->insert($row);
                    $stats['updated']++;
                    if ($logVerbose) {
                        logModuleCall('eazybackup', 'cron VaultUpsert(insert)', [ 'id'=>(string)$vaultId, 'type'=>$type ], [ 'endpoint'=>$this->maskEndpoint($endpoint), 'bucket'=>$bucketName, 'key'=>$this->maskKey($bucketKey), 'bytes'=>$bytes, 'success'=>$parsedAny, 'error'=>$errorMsg ]);
                    }
                    continue;
                }

                // Update path: only write good fields; always bump updated_at, content, name/type/limits
                $existing = Capsule::table('comet_vaults')->where('id', (string)$vaultId)->first(['bucket_server','bucket_name','bucket_key','total_bytes']) ?? (object)[];
                $existing_server = $existing->bucket_server ?? '';
                $existing_bucket = $existing->bucket_name ?? '';
                $existing_key    = $existing->bucket_key ?? '';
                $existing_total = isset($existing->total_bytes) ? (int)$existing->total_bytes : null;

                // Prefer not to stomp another server's fields in the same run
                $sameServer = ($existing_server !== '' && $endpoint !== '' && rtrim($existing_server,'/') === rtrim($endpoint,'/'));

                $updates = [
                    'client_id' => $clientId,
                    'username' => $canonicalUsername,
                    'content' => json_encode($arr, JSON_UNESCAPED_SLASHES),
                    'name' => $name,
                    'type' => $type,
                    'has_storage_limit' => $hasLimit,
                    'storage_limit_bytes' => $limitBytes,
                    'is_active' => 1,
                    'removed_at' => null,
                    'updated_at' => $now,
                ];
                // Only update bucket_server if empty or same server; avoid cross-server stomp
                if ($endpoint !== '') {
                    if ($existing_server === '' || $sameServer || rtrim($endpoint,'/') === rtrim($existing_server,'/')) {
                        $updates['bucket_server'] = $endpoint;
                    }
                }
                // Only update bucket fields if empty or sameServer (do not override different non-empty)
                if ($bucketName !== '' && ($existing_bucket === '' || $sameServer || $bucketName === $existing_bucket)) { $updates['bucket_name'] = $bucketName; }
                if ($bucketKey !== '' && ($existing_key === '' || $sameServer)) { $updates['bucket_key'] = $bucketKey; }
                
                // Write total_bytes exactly as measured.
                // - Accept any positive size.
                // - Accept zero only if it was explicitly measured (bytesTrustedZero).
                if ($bytes !== null && ($bytes > 0 || $bytesTrustedZero)) {
                    $updates['total_bytes'] = $bytes;  // allow decreases; always reflect reality
                }
                if ($parsedAny) {
                    $updates['last_success_at'] = date('Y-m-d H:i:s');
                    $updates['last_error'] = null;
                } else {
                    $updates['last_error'] = $errorMsg;
                }

                $rc = Capsule::table('comet_vaults')->where('id', (string)$vaultId)->update($updates);
                $stats[$parsedAny ? 'updated' : 'skipped']++;
                if (!$parsedAny) { $stats['errors']++; }
                if ($logVerbose) {
                    logModuleCall('eazybackup', 'cron VaultUpsert(update)', [ 'id'=>(string)$vaultId, 'type'=>$type ], [ 'endpoint'=>$this->maskEndpoint($endpoint), 'bucket'=>$bucketName, 'key'=>$this->maskKey($bucketKey), 'bytes'=>$bytes, 'success'=>$parsedAny, 'error'=>$errorMsg, 'rc'=>$rc ]);
                }
            }
        } catch (\Exception $e) {
            $stats['errors']++;
            // leave global error log minimal to avoid noise; toggle EB_VAULT_LOG for details
            if ($logVerbose) {
                logModuleCall('eazybackup', 'upsertVaults EXCEPTION', [], $e->getMessage());
            }
        }
        return $stats;
    }

    /**
     * Extract bucket fields for known providers.
     * Returns [bucketName, bucketKey, endpoint]
     *
     * @param array $content DestinationConfig as array
     * @param int   $type    Provider type (e.g., 1003 Comet, 1008 B2, 1000 S3-compatible)
     * @param string $serverUrl Active Comet server URL for type 1003 preference
     * @return array{0:string,1:string,2:string}
     */
    private function extractBucketFields(array $content, int $type, string $serverUrl): array
    {
        $endpoint = '';
        $bucket = '';
        $key = '';

        $normUrl = function (string $u) use ($serverUrl): string {
            if ($u === '') return '';
            $u = preg_replace(["/^http:\/\//i","/^https:\/\//i"], ['', ''], $u);
            $u = rtrim($u, '/');
            // we will re-prepend scheme if it was present in input
            if (stripos((string)$serverUrl, 'https://') === 0) return 'https://' . $u;
            if (stripos((string)$serverUrl, 'http://') === 0) return 'http://' . $u;
            return $u;
        };

        // Helper to read nested keys
        $get = function ($arr, $keys, $default = '') {
            if (!is_array($keys)) { $keys = [$keys]; }
            foreach ($keys as $k) {
                if (is_array($k)) {
                    $v = $arr;
                    foreach ($k as $kk) {
                        if (is_array($v) && array_key_exists($kk, $v)) { $v = $v[$kk]; } else { $v = null; break; }
                    }
                    if ($v !== null && $v !== '') return (string)$v;
                } else {
                    if (is_array($arr) && array_key_exists($k, $arr) && $arr[$k] !== '' && $arr[$k] !== null) return (string)$arr[$k];
                }
            }
            return $default;
        };

        if ($type === 1003) {
            // Comet Server
            $bucket = $get($content, [['Bucket'], ['BucketID'], ['CometBucket']]);
            $key    = $get($content, [['Key'], ['BucketKey'], ['CometBucketKey']]);
            // Prefer active server URL
            $endpoint = $serverUrl !== '' ? rtrim($serverUrl, '/') : $get($content, [['CometServer'], ['Server']]);
            $endpoint = $normUrl($endpoint);
            return [$bucket, $key, $endpoint];
        }

        if ($type === 1008) {
            // Backblaze B2
            $b2 = isset($content['B2']) ? (is_array($content['B2']) ? $content['B2'] : (is_object($content['B2']) ? get_object_vars($content['B2']) : [])) : [];
            $bucket = $get($b2, ['Bucket']);
            $key    = $get($b2, ['Key', 'KeyID', 'ApplicationKeyId']);
            $endpoint = $get($b2, ['Endpoint']);
            return [$bucket, $key, $endpoint];
        }

        if ($type === 1000) {
            // S3-compatible (including AWS S3)
            $s3 = isset($content['S3']) ? (is_array($content['S3']) ? $content['S3'] : (is_object($content['S3']) ? get_object_vars($content['S3']) : [])) : [];
            $s3c = isset($content['S3Compatible']) ? (is_array($content['S3Compatible']) ? $content['S3Compatible'] : (is_object($content['S3Compatible']) ? get_object_vars($content['S3Compatible']) : [])) : [];
            $bucket = $get($s3, ['Bucket', 'S3BucketName']) ?: $get($content, ['S3BucketName']);
            if ($bucket === '') { $bucket = $get($s3c, ['Bucket']); }
            $key = $get($s3, ['AccessKey', 'Key', 'S3AccessKey']) ?: $get($s3c, ['AccessKey', 'Key']);
            $endpoint = $get($s3, ['Endpoint', 'Hostname', 'S3Hostname', 'S3CustomHostName']) ?: $get($s3c, ['Endpoint', 'Hostname']);
            return [$bucket, $key, $endpoint];
        }

        // Fallback: try generic keys
        $bucket = $get($content, [['Bucket'], ['BucketID'], ['S3BucketName'], ['CometBucket']]);
        $key    = $get($content, [['Key'], ['BucketKey'], ['S3AccessKey'], ['CometBucketKey']]);
        $endpoint = $get($content, [['Endpoint'], ['Hostname'], ['S3Hostname'], ['S3CustomHostName'], ['CometServer']]);
        return [$bucket, $key, $endpoint];
    }

    /**
     * Mask bucket key for logs.
     */
    private function maskKey(string $k): string
    {
        if ($k === '') return '';
        $len = strlen($k);
        if ($len <= 4) return str_repeat('*', $len);
        return str_repeat('*', $len - 4) . substr($k, -4);
    }

    /**
     * Mask endpoint minimally (hide scheme-only details is not necessary; return as-is)
     */
    private function maskEndpoint(string $e): string
    {
        return $e;
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