<?php

use WHMCS\Database\Capsule;
use WHMCS\Session;

require_once __DIR__ . '/../../../../../modules/servers/comet/functions.php';

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

header('Content-Type: application/json');

/**
 * Async job store helpers (table: mod_eazybackup_async_jobs).
 *
 * The wizard uses these to fall back to a background job whenever a
 * synchronous Comet dispatcher request looks like it might exceed the
 * client's patience (e.g. snapshot listing for very large vaults).
 */
function eb_async_jobs_ensure_table() {
    static $checked = false;
    if ($checked) return;
    try {
        Capsule::statement("CREATE TABLE IF NOT EXISTS `mod_eazybackup_async_jobs` (
            `id` CHAR(36) NOT NULL PRIMARY KEY,
            `client_id` INT UNSIGNED NOT NULL,
            `service_id` INT UNSIGNED NOT NULL,
            `username` VARCHAR(190) NOT NULL,
            `action` VARCHAR(64) NOT NULL,
            `status` ENUM('queued','running','done','error') NOT NULL DEFAULT 'queued',
            `payload_json` MEDIUMTEXT NULL,
            `result_json` LONGTEXT NULL,
            `error_message` TEXT NULL,
            `created_at` INT UNSIGNED NOT NULL,
            `started_at` INT UNSIGNED NULL,
            `updated_at` INT UNSIGNED NOT NULL,
            `finished_at` INT UNSIGNED NULL,
            INDEX `idx_eb_jobs_client` (`client_id`, `created_at`),
            INDEX `idx_eb_jobs_status` (`status`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\Throwable $e) {}
    $checked = true;
}

function eb_async_job_create($clientId, $serviceId, $username, $action, array $payload) {
    eb_async_jobs_ensure_table();
    $id = strtolower(bin2hex(random_bytes(8))).'-'.dechex(time());
    // Pad to a UUID-ish length the column will accept
    $id = substr(str_pad($id, 36, '0'), 0, 36);
    $now = time();
    Capsule::table('mod_eazybackup_async_jobs')->insert([
        'id' => $id,
        'client_id' => (int)$clientId,
        'service_id' => (int)$serviceId,
        'username' => (string)$username,
        'action' => (string)$action,
        'status' => 'queued',
        'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    return $id;
}

function eb_async_job_get($id, $clientId) {
    eb_async_jobs_ensure_table();
    $row = Capsule::table('mod_eazybackup_async_jobs')
        ->where('id', $id)
        ->where('client_id', (int)$clientId)
        ->first();
    return $row;
}

function eb_async_job_update($id, array $fields) {
    $fields['updated_at'] = time();
    Capsule::table('mod_eazybackup_async_jobs')->where('id', $id)->update($fields);
}

function eb_async_job_run($id, callable $worker) {
    try {
        eb_async_job_update($id, [
            'status' => 'running',
            'started_at' => time(),
        ]);
        $result = $worker();
        eb_async_job_update($id, [
            'status' => 'done',
            'result_json' => json_encode($result, JSON_UNESCAPED_SLASHES),
            'finished_at' => time(),
        ]);
    } catch (\Throwable $e) {
        eb_async_job_update($id, [
            'status' => 'error',
            'error_message' => substr($e->getMessage(), 0, 65000),
            'finished_at' => time(),
        ]);
    }
}

/**
 * Send the JSON response, then keep PHP running in the background to do work.
 * Falls back to inline execution when fastcgi_finish_request is unavailable.
 */
function eb_send_and_continue(array $body, callable $afterResponse) {
    echo json_encode($body);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        try { $afterResponse(); } catch (\Throwable $e) {}
    } else {
        // No FastCGI - try to keep going after flushing
        @ob_end_flush();
        @flush();
        try { $afterResponse(); } catch (\Throwable $e) {}
    }
}

try {
    $post = json_decode(file_get_contents('php://input'), true);
    if (!is_array($post)) { echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']); exit; }

    $action    = (string)($post['action'] ?? '');
    $serviceId = (int)($post['serviceId'] ?? 0);
    $username  = (string)($post['username'] ?? '');
    if (!$action || $serviceId <= 0) { echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']); exit; }

    $clientId = 0;
    try { $clientId = (int) (Session::get('uid') ?: 0); } catch (\Throwable $e) { $clientId = 0; }
    if ($clientId <= 0) { $clientId = (int) ($_SESSION['uid'] ?? 0); }
    if ($clientId <= 0) { echo json_encode(['status' => 'error', 'message' => 'Not authenticated']); exit; }
    $account = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->where('userid', $clientId)
        ->select('id', 'packageid', 'username')
        ->first();
    if (!$account) { echo json_encode(['status' => 'error', 'message' => 'Service not found or access denied']); exit; }
    if ($username === '') { $username = $account->username; }
    if ($username !== $account->username) { echo json_encode(['status' => 'error', 'message' => 'Access denied']); exit; }

    $params = comet_ServiceParams($serviceId);
    $params['username'] = $username;
    $server = comet_Server($params);

    // Endpoints that may take a long time (large vaults / dispatcher round-trips).
    // Lift the PHP wall-clock and Guzzle HTTP timeouts for these, so the user gets
    // either a real result or a real Comet error instead of a silent 504.
    $LONG_ACTIONS = ['vaultSnapshots','browseSnapshot','browseFs','runRestore','vaultSnapshotsAsync','jobStatus','jobRun'];
    if (in_array($action, $LONG_ACTIONS, true)) {
        @set_time_limit(0);
        @ignore_user_abort(true);
        try {
            if ($server instanceof \Comet\Server) {
                $server->setClient(new \GuzzleHttp\Client([
                    'headers' => [
                        'User-Agent' => 'comet-php-sdk/1.x',
                        'Accept-Encoding' => 'gzip',
                    ],
                    'allow_redirects' => false,
                    'decode_content' => true,
                    'timeout' => 600,
                    'connect_timeout' => 15,
                ]));
            }
        } catch (\Throwable $e) {}
    }

    // Resolve live dispatcher TargetID (connection GUID) from a DeviceID
    $findTarget = function(\Comet\Server $server, string $username, string $deviceId): ?string {
        try {
            $active = $server->AdminDispatcherListActive($username);
            if (is_array($active)) {
                foreach ($active as $connId => $conn) {
                    $candidateId = (is_string($connId) && strlen($connId) > 0) ? $connId : (property_exists($conn, 'ConnectionID') ? $conn->ConnectionID : '');
                    if (isset($conn->DeviceID) && $conn->DeviceID === $deviceId) {
                        return $candidateId ?: (isset($conn->ConnectionID) ? $conn->ConnectionID : null);
                    }
                }
            }
        } catch (\Throwable $e) {}
        return null;
    };

    switch ($action) {
        case 'piProfileGet': {
            // Alias for getUserProfile with a stable name for Profile tab integrations
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            echo json_encode(['status'=>'success','profile'=>$ph->Profile->toArray(true), 'hash'=>$ph->ProfileHash]);
            break;
        }
        case 'piProfileUpdate': {
            $fields = [
                'MaximumDevices',
                'QuotaOffice365ProtectedAccounts',
                'QuotaHyperVGuests',
                'QuotaVMwareGuests',
            ];

            // Coerce incoming ints (0 = unlimited)
            $payload = [];
            foreach ($fields as $f) {
                if (isset($post[$f])) {
                    $payload[$f] = max(0, (int)$post[$f]);
                }
            }

            // Optional string field: AccountName (allow empty string to clear)
            $hasAccountName = array_key_exists('AccountName', $post);
            $accountNameVal = $hasAccountName ? (string)$post['AccountName'] : null;

            if (!$username || (empty($payload) && !$hasAccountName)) {
                echo json_encode(['status'=>'error','message'=>'Missing username or no updatable fields provided']);
                break;
            }

            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) {
                echo json_encode(['status'=>'error','message'=>'Profile not found']);
                break;
            }

            // ✅ Directly set quota fields on the live profile object
            foreach ($payload as $k => $v) {
                if (property_exists($ph->Profile, $k)) {
                    $ph->Profile->{$k} = $v;
                } else {
                    error_log("Comet SDK property missing: {$k}");
                }
            }

            // ✅ Set AccountName if provided (string, can be empty to clear)
            if ($hasAccountName) {
                // Guard with property_exists in case of older SDKs
                if (property_exists($ph->Profile, 'AccountName')) {
                    $ph->Profile->AccountName = $accountNameVal;
                } else {
                    // Fallback: set via array cast if property missing
                    try { $arr = $ph->Profile->toArray(true); $arr['AccountName'] = $accountNameVal; $ph->Profile->fromArray($arr); } catch (\Throwable $e) {}
                }
            }

            // (Optional debug) verify we’re sending what we think we’re sending:
            // error_log('UPDATING PROFILE: ' . json_encode($ph->Profile->toArray(true)));

            $resp = $server->AdminSetUserProfileHash($username, $ph->Profile, $ph->ProfileHash);

            if ($resp && $resp->Status < 400) {
                echo json_encode(['status'=>'success']);
            } else if ($resp && $resp->Status === 409) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed; please retry']);
            } else {
                echo json_encode(['status'=>'error','message'=>($resp ? $resp->Message : 'Failed to update profile')]);
            }
            break;
        }        
        case 'getUserProfile': {
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            echo json_encode(['status'=>'success','profile'=>$ph->Profile->toArray(true), 'hash'=>$ph->ProfileHash]);
            break;
        }
        case 'setVaultRetention': {
            $vaultId = isset($post['vaultId']) ? (string)$post['vaultId'] : '';
            $overrideRaw = $post['override'] ?? false;
            $mode = (int)($post['mode'] ?? 801);
            $rangesRaw = $post['ranges'] ?? [];
            $hash = (string)($post['hash'] ?? '');
            if ($vaultId === '') { echo json_encode(['status'=>'error','message'=>'vaultId required']); break; }

            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            if ($hash !== '' && $hash !== (string)$ph->ProfileHash) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed']);
                break;
            }

            $profile = $ph->Profile;
            if (!isset($profile->Destinations[$vaultId])) {
                echo json_encode(['status'=>'error','message'=>'Vault not found']); break;
            }

            $override = ($overrideRaw === true || $overrideRaw === 1 || $overrideRaw === '1' || $overrideRaw === 'true');
            if (!$override) {
                // Destination retention is stored in DefaultRetention (not RetentionPolicy).
                try { unset($profile->Destinations[$vaultId]->DefaultRetention); } catch (\Throwable $_) {}
            } else {
                $rp = new \Comet\RetentionPolicy();
                $rp->Mode = $mode;
                $rp->Ranges = [];
                $ranges = is_array($rangesRaw) ? $rangesRaw : [];
                foreach ($ranges as $r) {
                    $ra = is_array($r) ? $r : (is_object($r) ? (array)$r : null);
                    if (!is_array($ra)) { continue; }
                    $type = (int)($ra['Type'] ?? 0);
                    if ($type <= 0) { continue; }
                    $o = new \Comet\RetentionRange();
                    $o->Type = $type;
                    $o->Timestamp = (int)($ra['Timestamp'] ?? 0);
                    $o->Jobs = (int)($ra['Jobs'] ?? 0);
                    $o->Days = (int)($ra['Days'] ?? 0);
                    $o->Weeks = (int)($ra['Weeks'] ?? 0);
                    $o->Months = (int)($ra['Months'] ?? 0);
                    $o->Years = (int)($ra['Years'] ?? 0);
                    $o->WeekOffset = (int)($ra['WeekOffset'] ?? 0);
                    $o->MonthOffset = (int)($ra['MonthOffset'] ?? 1);
                    $o->YearOffset = (int)($ra['YearOffset'] ?? 1);
                    $rp->Ranges[] = $o;
                }
                $profile->Destinations[$vaultId]->DefaultRetention = $rp;
            }

            $resp = $server->AdminSetUserProfileHash($username, $profile, (string)$ph->ProfileHash);
            if ($resp && $resp->Status < 400) {
                $ph2 = $server->AdminGetUserProfileAndHash($username);
                echo json_encode(['status'=>'success','hash'=>($ph2 ? $ph2->ProfileHash : '')]);
            } else if ($resp && $resp->Status === 409) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed']);
            } else {
                echo json_encode(['status'=>'error','message'=>($resp ? $resp->Message : 'Failed to update retention')]);
            }
            break;
        }
        case 'setUserProfile': {
            $profileArr = isset($post['profile']) ? $post['profile'] : null;
            $hash = (string)($post['hash'] ?? '');
            if (!$profileArr || !$hash) { echo json_encode(['status'=>'error','message'=>'Missing profile or hash']); break; }
            $profile = null;
            try {
                // Re-hydrate into SDK model
                $tmp = new \Comet\UserProfileConfig();
                $tmp->fromArray($profileArr);
                $profile = $tmp;
            } catch (\Throwable $e) {
                // Fallback for UI retention saves: patch retention onto live profile when full hydration is too strict.
                try {
                    $phLive = $server->AdminGetUserProfileAndHash($username);
                    if (!$phLive || !$phLive->Profile) {
                        echo json_encode(['status'=>'error','message'=>'Invalid profile payload']); break;
                    }
                    $live = $phLive->Profile;
                    $patched = false;
                    $destinations = $profileArr['Destinations'] ?? null;
                    if (is_array($destinations)) {
                        foreach ($destinations as $destId => $destCfg) {
                            if (!is_string($destId) || !is_array($destCfg) || !isset($live->Destinations[$destId])) { continue; }
                            $hasDefaultRetention = array_key_exists('DefaultRetention', $destCfg);
                            $hasLegacyRetention = array_key_exists('RetentionPolicy', $destCfg);
                            if (!$hasDefaultRetention && !$hasLegacyRetention) { continue; }
                            $rpRaw = $hasDefaultRetention ? $destCfg['DefaultRetention'] : $destCfg['RetentionPolicy'];
                            if ($rpRaw === null || $rpRaw === false) {
                                // Explicitly clearing override falls back to destination default policy.
                                try { unset($live->Destinations[$destId]->DefaultRetention); } catch (\Throwable $_) {}
                                $patched = true;
                                continue;
                            }
                            $rpArr = is_array($rpRaw) ? $rpRaw : (is_object($rpRaw) ? (array)$rpRaw : null);
                            if (!is_array($rpArr)) { continue; }
                            $rp = new \Comet\RetentionPolicy();
                            $rp->fromArray($rpArr);
                            $live->Destinations[$destId]->DefaultRetention = $rp;
                            $patched = true;
                        }
                    }
                    if (!$patched) {
                        echo json_encode(['status'=>'error','message'=>'Invalid profile payload']); break;
                    }
                    $profile = $live;
                } catch (\Throwable $_) {
                    echo json_encode(['status'=>'error','message'=>'Invalid profile payload']); break;
                }
            }
            if (!$profile) { echo json_encode(['status'=>'error','message'=>'Invalid profile payload']); break; }
            $resp = $server->AdminSetUserProfileHash($username, $profile, $hash);
            if ($resp && $resp->Status < 400) {
                // Return new hash by re-reading profile
                $ph = $server->AdminGetUserProfileAndHash($username);
                echo json_encode(['status'=>'success','hash'=>($ph?$ph->ProfileHash:'')]);
            } else if ($resp && $resp->Status === 409) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed']);
            } else {
                echo json_encode(['status'=>'error','message'=>($resp?$resp->Message:'Failed')]);
            }
            break;
        }
        case 'listProtectedItems': {
            $deviceId = (string)($post['deviceId'] ?? '');
            $includeAll = !empty($post['includeAll']);
            if ($deviceId === '' && !$includeAll) { echo json_encode(['status'=>'error','message'=>'deviceId required']); break; }
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            $profile = $ph->Profile;

            // Build a deviceId -> friendly name map so callers can show
            // which device each Protected Item belongs to (used by the
            // restore wizard when restoring across devices).
            $deviceNames = [];
            if (isset($profile->Devices)) {
                foreach ((array)$profile->Devices as $did => $dev) {
                    if (is_object($dev)) {
                        $fn = isset($dev->FriendlyName) ? trim((string)$dev->FriendlyName) : '';
                        $deviceNames[(string)$did] = ($fn !== '') ? $fn : (string)$did;
                    }
                }
            }

            $out = [];
            // Prefer global Sources. When includeAll is set, return every
            // Protected Item in the account (so the restore wizard can offer
            // items that were backed up from a different/offline device).
            if (isset($profile->Sources)) {
                foreach ((array)$profile->Sources as $sid => $src) {
                    if (!is_object($src)) continue;
                    $owner = isset($src->OwnerDevice) ? (string)$src->OwnerDevice : '';
                    if (!$includeAll && $owner !== $deviceId) continue;
                    $name = isset($src->Description) ? (string)$src->Description : (string)$sid;
                    $out[] = [
                        'id' => (string)$sid,
                        'name' => $name,
                        'ownerDeviceId' => $owner,
                        'ownerDeviceName' => isset($deviceNames[$owner]) ? $deviceNames[$owner] : '',
                    ];
                }
            }
            // Fallback: device-local Sources if present (legacy profiles)
            if (empty($out) && !$includeAll && isset($profile->Devices[$deviceId]) && isset($profile->Devices[$deviceId]->Sources)) {
                foreach ((array)$profile->Devices[$deviceId]->Sources as $sourceId => $sourceInfo) {
                    if (is_object($sourceInfo)) {
                        $name = isset($sourceInfo->Description) ? (string)$sourceInfo->Description : (string)$sourceId;
                        $out[] = [
                            'id' => (string)$sourceId,
                            'name' => $name,
                            'ownerDeviceId' => $deviceId,
                            'ownerDeviceName' => isset($deviceNames[$deviceId]) ? $deviceNames[$deviceId] : '',
                        ];
                    }
                }
            }
            echo json_encode(['status'=>'success','items'=>$out]);
            break;
        }
        case 'listAllProtectedItems': {
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            $profile = $ph->Profile;
            $out = [];
            if (isset($profile->Sources)) {
                foreach ((array)$profile->Sources as $sid => $src) {
                    if (is_object($src)) {
                        $name = isset($src->Description) ? (string)$src->Description : (string)$sid;
                        $out[] = [ 'id' => (string)$sid, 'name' => $name ];
                    }
                }
            }
            echo json_encode(['status'=>'success','items'=>$out]);
            break;
        }
        case 'vaultSnapshots': {
            $deviceId = (string)($post['deviceId'] ?? '');
            $vaultId  = (string)($post['vaultId'] ?? '');
            if ($deviceId === '' || $vaultId === '') { echo json_encode(['status'=>'error','message'=>'deviceId and vaultId required']); break; }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }
            $resp = $server->AdminDispatcherRequestVaultSnapshots($targetId, $vaultId);
            echo json_encode(['status'=>'success','snapshots' => $resp ? $resp->toArray(true) : []]);
            break;
        }
        case 'vaultSnapshotsAsync': {
            $deviceId = (string)($post['deviceId'] ?? '');
            $vaultId  = (string)($post['vaultId'] ?? '');
            if ($deviceId === '' || $vaultId === '') { echo json_encode(['status'=>'error','message'=>'deviceId and vaultId required']); break; }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }
            $jobId = eb_async_job_create($clientId, $serviceId, $username, 'vaultSnapshots', [
                'deviceId' => $deviceId,
                'vaultId' => $vaultId,
                'targetId' => $targetId,
            ]);
            // Capture closures BEFORE we detach so they survive the request.
            $svc = $serviceId; $u = $username;
            eb_send_and_continue(
                ['status' => 'success', 'jobId' => $jobId],
                function() use ($jobId, $svc, $u, $targetId, $vaultId) {
                    eb_async_job_run($jobId, function() use ($svc, $u, $targetId, $vaultId) {
                        $params = comet_ServiceParams($svc);
                        $params['username'] = $u;
                        $srv = comet_Server($params);
                        if ($srv instanceof \Comet\Server) {
                            $srv->setClient(new \GuzzleHttp\Client([
                                'headers' => [ 'User-Agent' => 'comet-php-sdk/1.x', 'Accept-Encoding' => 'gzip' ],
                                'allow_redirects' => false,
                                'decode_content' => true,
                                'timeout' => 1800,
                                'connect_timeout' => 15,
                            ]));
                        }
                        $resp = $srv->AdminDispatcherRequestVaultSnapshots($targetId, $vaultId);
                        return ['snapshots' => $resp ? $resp->toArray(true) : []];
                    });
                }
            );
            return; // already responded
        }
        case 'jobStatus': {
            $jobId = (string)($post['jobId'] ?? '');
            if ($jobId === '') { echo json_encode(['status'=>'error','message'=>'jobId required']); break; }
            $row = eb_async_job_get($jobId, $clientId);
            if (!$row) { echo json_encode(['status'=>'error','message'=>'Job not found']); break; }
            $out = [
                'status' => 'success',
                'job' => [
                    'id' => $row->id,
                    'state' => $row->status,
                    'createdAt' => (int)$row->created_at,
                    'startedAt' => isset($row->started_at) ? (int)$row->started_at : null,
                    'finishedAt' => isset($row->finished_at) ? (int)$row->finished_at : null,
                    'elapsed' => time() - (int)$row->created_at,
                ],
            ];
            if ($row->status === 'done' && $row->result_json) {
                $out['result'] = json_decode($row->result_json, true);
            } else if ($row->status === 'error') {
                $out['error'] = (string)$row->error_message;
            }
            echo json_encode($out);
            break;
        }
        case 'browseFs': {
            $deviceId = (string)($post['deviceId'] ?? '');
            $path     = isset($post['path']) ? (string)$post['path'] : null; // null or string
            if ($deviceId === '') { echo json_encode(['status'=>'error','message'=>'deviceId required']); break; }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }
            $resp = $server->AdminDispatcherRequestFilesystemObjects($targetId, ($path === '' ? null : $path));
            $entries = [];
            if ($resp && is_array($resp->StoredObjects)) {
                foreach ($resp->StoredObjects as $obj) {
                    $isDir = (isset($obj->Subtree) && $obj->Subtree !== '');
                    $entries[] = [
                        'name' => (string)($obj->DisplayName ?: $obj->Name),
                        'rawName' => (string)$obj->Name,
                        'isDir' => $isDir,
                        'subtree' => (string)($obj->Subtree ?: ''),
                        'size' => (int)$obj->Size,
                        'mtime' => (int)$obj->ModifyTime,
                    ];
                }
            }
            echo json_encode(['status'=>'success','path'=>$path, 'entries'=>$entries]);
            break;
        }
        case 'browseSnapshot': {
            // Browse stored objects inside an existing backup snapshot (for "Select items" restore)
            $deviceId   = (string)($post['deviceId'] ?? '');
            $vaultId    = (string)($post['vaultId'] ?? '');
            $snapshotId = (string)($post['snapshotId'] ?? '');
            $treeId     = isset($post['treeId']) ? (string)$post['treeId'] : '';
            if ($deviceId === '' || $vaultId === '' || $snapshotId === '') {
                echo json_encode(['status'=>'error','message'=>'deviceId, vaultId and snapshotId are required']);
                break;
            }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }

            try {
                $resp = $server->AdminDispatcherRequestStoredObjects(
                    $targetId,
                    $vaultId,
                    $snapshotId,
                    ($treeId !== '' ? $treeId : null),
                    null
                );
            } catch (\Throwable $e) {
                echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
                break;
            }

            $entries = [];
            $isRootLevel = ($treeId === '');
            if ($resp && is_array($resp->StoredObjects)) {
                foreach ($resp->StoredObjects as $obj) {
                    $isDir = false;
                    try {
                        $isDir = (isset($obj->Subtree) && (string)$obj->Subtree !== '');
                    } catch (\Throwable $e) { $isDir = false; }
                    $rawName = (string)$obj->Name;
                    $displayName = (string)($obj->DisplayName ?: $obj->Name);
                    // At the snapshot root, Comet exposes Windows volumes with a
                    // sanitized name like "C__" (and an empty DisplayName).
                    // Translate that to the actual on-disk root "C:\" so the
                    // UI shows real paths and selections sent to the agent
                    // resolve against the snapshot index correctly.
                    if ($isRootLevel && preg_match('/^([A-Za-z])__$/', $displayName, $m)) {
                        $displayName = $m[1] . ':\\';
                    }
                    $entries[] = [
                        'name' => $displayName,
                        'rawName' => $rawName,
                        'isDir' => $isDir,
                        'subtree' => (string)($obj->Subtree ?: ''),
                        'type' => (string)($obj->Type ?? ''),
                        'size' => (int)($obj->Size ?? 0),
                        'mtime' => (int)($obj->ModifyTime ?? 0),
                        'recursiveKnown' => (bool)($obj->RecursiveCountKnown ?? false),
                        'recursiveFiles' => (int)($obj->RecursiveFiles ?? 0),
                        'recursiveBytes' => (int)($obj->RecursiveBytes ?? 0),
                        'recursiveFolders' => (int)($obj->RecursiveFolders ?? 0),
                    ];
                }
            }
            echo json_encode([
                'status'=>'success',
                'treeId' => ($treeId !== '' ? $treeId : ''),
                'entries'=>$entries
            ]);
            break;
        }
        case 'updateSoftware': {
            $deviceId = (string)($post['deviceId'] ?? '');
            if ($deviceId === '') { echo json_encode(['status'=>'error','message'=>'deviceId required']); break; }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }
            $resp = $server->AdminDispatcherUpdateSoftware($targetId);
            echo json_encode(['status' => ($resp->Status < 400 ? 'success' : 'error'), 'message' => $resp->Message, 'code' => $resp->Status]);
            break;
        }
        case 'uninstallSoftware': {
            $deviceId = (string)($post['deviceId'] ?? '');
            $removeCfg = !!($post['removeConfig'] ?? false);
            if ($deviceId === '') { echo json_encode(['status'=>'error','message'=>'deviceId required']); break; }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }
            $resp = $server->AdminDispatcherUninstallSoftware($targetId, $removeCfg);
            echo json_encode(['status' => ($resp->Status < 400 ? 'success' : 'error'), 'message' => $resp->Message, 'code' => $resp->Status]);
            break;
        }
        case 'revokeDevice': {
            $deviceId = (string)($post['deviceId'] ?? '');
            if ($deviceId === '') { echo json_encode(['status'=>'error','message'=>'deviceId required']); break; }
            $resp = $server->AdminRevokeDevice($username, $deviceId);
            echo json_encode(['status' => ($resp->Status < 400 ? 'success' : 'error'), 'message' => $resp->Message, 'code' => $resp->Status]);
            break;
        }
        case 'applyRetention': {
            $deviceId = (string)($post['deviceId'] ?? '');
            $vaultId  = (string)($post['vaultId'] ?? '');
            if ($deviceId === '' || $vaultId === '') { echo json_encode(['status'=>'error','message'=>'deviceId and vaultId required']); break; }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }
            $resp = $server->AdminDispatcherApplyRetentionRules($targetId, $vaultId);
            echo json_encode(['status' => ($resp->Status < 400 ? 'success' : 'error'), 'message' => $resp->Message, 'code' => $resp->Status]);
            break;
        }
        case 'reindexVault': {
            $deviceId = (string)($post['deviceId'] ?? '');
            $vaultId  = (string)($post['vaultId'] ?? '');
            if ($deviceId === '' || $vaultId === '') { echo json_encode(['status'=>'error','message'=>'deviceId and vaultId required']); break; }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }
            $resp = $server->AdminDispatcherReindexStorageVault($targetId, $vaultId);
            echo json_encode(['status' => ($resp->Status < 400 ? 'success' : 'error'), 'message' => $resp->Message, 'code' => $resp->Status]);
            break;
        }
        case 'runRestore': {
            $deviceId  = (string)($post['deviceId'] ?? '');
            $sourceId  = (string)($post['sourceId'] ?? '');
            $vaultId   = (string)($post['vaultId'] ?? '');
            $snapshot  = (string)($post['snapshot'] ?? '');
            $type      = isset($post['type']) ? (int)$post['type'] : null;
            $destPath  = (string)($post['destPath'] ?? '');
            $overwrite = (string)($post['overwrite'] ?? 'none');
            if ($deviceId === '' || $sourceId === '' || $vaultId === '' || $type === null) {
                echo json_encode(['status'=>'error','message'=>'deviceId, sourceId, vaultId and type are required']); break;
            }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }

            // Sanity-check the requested restore type against the protected item's engine.
            // Sending the wrong RESTORETYPE makes the agent abort with a generic engine error,
            // so we'd rather return a friendly message here.
            $engine = '';
            try {
                $ph = $server->AdminGetUserProfileAndHash($username);
                if ($ph && $ph->Profile && isset($ph->Profile->Sources) && isset($ph->Profile->Sources[$sourceId])) {
                    $engine = (string)($ph->Profile->Sources[$sourceId]->Engine ?? '');
                }
            } catch (\Throwable $e) { $engine = ''; }
            if ($engine !== '') {
                $eng = strtolower($engine);
                // RESTORETYPE_ values: 0=FILE, 1=NULL/SIMULATE, 4=WINDISK, 5=FILE_ARCHIVE, 6=PROCESS_PERFILE, 7=PROCESS_ARCHIVE
                $allowed = [0, 1, 5];
                if ($eng === 'engine1/windisk') { $allowed = [0, 1, 4, 5]; }
                if (!in_array($type, $allowed, true)) {
                    echo json_encode(['status'=>'error','message'=>"Restore type {$type} is not compatible with protected item type ({$engine})."]);
                    break;
                }
            }

            $opt = new \Comet\RestoreJobAdvancedOptions();
            $opt->Type = $type;

            // Empty destination path means "restore to original location" - the engine
            // requires this flag to be set, otherwise it aborts with a generic error.
            $destTrim = trim($destPath);
            if ($destTrim === '') {
                $opt->DestIsOriginalLocation = true;
                $opt->DestPath = '';
            } else {
                $opt->DestIsOriginalLocation = false;
                $opt->DestPath = $destTrim;
            }

            if ($overwrite === 'always') {
                $opt->OverwriteExistingFiles = true;
                $opt->OverwriteIfNewer = false;
                $opt->OverwriteIfDifferentContent = false;
            } else if ($overwrite === 'ifNewer') {
                $opt->OverwriteExistingFiles = true;
                $opt->OverwriteIfNewer = true;
                $opt->OverwriteIfDifferentContent = false;
            } else if ($overwrite === 'ifDifferent') {
                $opt->OverwriteExistingFiles = true;
                $opt->OverwriteIfNewer = false;
                $opt->OverwriteIfDifferentContent = true;
            } else {
                $opt->OverwriteExistingFiles = false;
                $opt->OverwriteIfNewer = false;
                $opt->OverwriteIfDifferentContent = false;
            }

            // Optional: restore selected sub-paths only. If omitted/empty -> restore all.
            $paths = null;
            if (isset($post['paths'])) {
                $incoming = $post['paths'];
                if (is_array($incoming)) {
                    $clean = [];
                    foreach ($incoming as $p) {
                        $p = trim((string)$p);
                        if ($p === '') continue;
                        // Comet's snapshot tree exposes Windows volume roots in a
                        // sanitized form ("C__" instead of "C:\"). The agent's
                        // restore engine, however, matches against the literal
                        // on-disk path. Translate the sanitized prefix back so
                        // selections like "C__\Users\Brian\Desktop" resolve to
                        // "C:\Users\Brian\Desktop".
                        if (preg_match('/^([A-Za-z])__(?:$|[\\\\\/])/', $p, $m)) {
                            $rest = substr($p, 3); // after "X__"
                            $rest = ltrim($rest, "/\\");
                            $p = $m[1] . ':\\' . $rest;
                        }
                        // Strip a single trailing path separator: Comet expects the folder
                        // entry without a trailing slash/backslash (it recurses by default).
                        $last = substr($p, -1);
                        if ($last === '/' || $last === '\\') {
                            // ...but keep "C:\" as-is so a drive root remains valid.
                            if (!preg_match('/^[A-Za-z]:[\\/]$/', $p) && $p !== '/') {
                                $p = rtrim($p, "/\\");
                            }
                        }
                        if ($p !== '') { $clean[] = $p; }
                    }
                    if (!empty($clean)) { $paths = array_values(array_unique($clean)); }
                }
            }

            // Capture full request payload + Comet response so we can diagnose
            // future agent-side aborts ("backup engine encountered a problem").
            $logRequest = [
                'targetId' => $targetId,
                'sourceId' => $sourceId,
                'vaultId'  => $vaultId,
                'snapshot' => $snapshot,
                'engine'   => $engine,
                'paths'    => $paths,
                'options'  => $opt->toArray(true),
            ];
            try {
                $resp = $server->AdminDispatcherRunRestoreCustom(
                    $targetId, $sourceId, $vaultId, $opt,
                    ($snapshot !== '' ? $snapshot : null),
                    $paths, null, null, null
                );
                $logResponse = ['status' => $resp->Status, 'message' => $resp->Message];
                if (function_exists('logModuleCall')) {
                    @logModuleCall('eazybackup', 'runRestore', $logRequest, $logResponse);
                }
                echo json_encode([
                    'status'  => ($resp->Status < 400 ? 'success' : 'error'),
                    'message' => $resp->Message,
                    'code'    => $resp->Status,
                ]);
            } catch (\Throwable $e) {
                if (function_exists('logModuleCall')) {
                    @logModuleCall('eazybackup', 'runRestore', $logRequest, [ 'exception' => $e->getMessage() ]);
                }
                echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
            }
            break;
        }
        case 'runBackup': {
            $deviceId = (string)($post['deviceId'] ?? '');
            $protectedItemId = (string)($post['protectedItemId'] ?? '');
            $vaultId  = (string)($post['vaultId'] ?? '');
            if ($deviceId === '' || $protectedItemId === '' || $vaultId === '') { echo json_encode(['status'=>'error','message'=>'deviceId, protectedItemId and vaultId required']); break; }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }
            $resp = $server->AdminDispatcherRunBackupCustom($targetId, $protectedItemId, $vaultId);
            echo json_encode(['status' => ($resp->Status < 400 ? 'success' : 'error'), 'message' => $resp->Message, 'code' => $resp->Status]);
            break;
        }
        case 'piListDevices': {
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            $profile = $ph->Profile;
            $devices = [];
            $online = [];
            try {
                $active = $server->AdminDispatcherListActive($username);
                if (is_array($active)) {
                    foreach ($active as $connId => $conn) {
                        if (isset($conn->DeviceID)) { $online[(string)$conn->DeviceID] = true; }
                    }
                }
            } catch (\Throwable $e) {}
            if (isset($profile->Devices)) {
                foreach ((array)$profile->Devices as $did => $dev) {
                    if (!is_object($dev)) continue;
                    $name = isset($dev->FriendlyName) ? trim((string)$dev->FriendlyName) : '';
                    $os = '';
                    if (isset($dev->PlatformVersion) && is_object($dev->PlatformVersion)) {
                        $pv = $dev->PlatformVersion;
                        $parts = [];
                        if (!empty($pv->os)) $parts[] = (string)$pv->os;
                        if (!empty($pv->arch)) $parts[] = (string)$pv->arch;
                        $os = implode(' / ', $parts);
                    }
                    $devices[] = [
                        'id' => (string)$did,
                        'friendlyName' => ($name !== '' ? $name : (string)$did),
                        'osInfo' => $os,
                        'online' => !empty($online[(string)$did]),
                    ];
                }
            }
            echo json_encode(['status'=>'success','devices'=>$devices]);
            break;
        }
        case 'piEngineCatalog': {
            // Resolve engine list this user is allowed to create.
            // v1 supports: engine1/file, engine1/hyperv, engine1/vmware, engine1/proxmox.
            $catalog = [
                ['id' => 'engine1/file',    'label' => 'Files and Folders',  'icon' => 'folder',   'category' => 'files'],
                ['id' => 'engine1/windisk', 'label' => 'Disk Image',         'icon' => 'disk',     'category' => 'disk',  'comingSoon' => true],
                ['id' => 'engine1/hyperv',  'label' => 'Microsoft Hyper-V',  'icon' => 'server',   'category' => 'vm'],
                ['id' => 'engine1/mssql',   'label' => 'Microsoft SQL Server','icon' => 'database','category' => 'db',    'comingSoon' => true],
                ['id' => 'engine1/mysql',   'label' => 'MySQL',              'icon' => 'database', 'category' => 'db',    'comingSoon' => true],
                ['id' => 'engine1/proxmox', 'label' => 'Proxmox',            'icon' => 'server',   'category' => 'vm'],
                ['id' => 'engine1/vmware',  'label' => 'VMware vSphere',     'icon' => 'server',   'category' => 'vm'],
            ];

            $restrict = false;
            $allowed = [];
            try {
                $ph = $server->AdminGetUserProfileAndHash($username);
                if ($ph && $ph->Profile) {
                    $policyId = isset($ph->Profile->PolicyID) ? (string)$ph->Profile->PolicyID : '';
                    if ($policyId !== '') {
                        $pr = $server->AdminPoliciesGet($policyId);
                        if ($pr && isset($pr->Policy) && isset($pr->Policy->ProtectedItemEngineTypePolicy)) {
                            $eep = $pr->Policy->ProtectedItemEngineTypePolicy;
                            $restrict = !empty($eep->ShouldRestrictEngineTypeList);
                            if ($restrict && is_array($eep->AllowedEngineTypeWhenRestricted)) {
                                foreach ($eep->AllowedEngineTypeWhenRestricted as $eid) {
                                    $allowed[(string)$eid] = true;
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {}

            $out = [];
            foreach ($catalog as $row) {
                $allowedByPolicy = !$restrict || isset($allowed[$row['id']]);
                $row['allowedByPolicy'] = $allowedByPolicy;
                $row['supported'] = !($row['comingSoon'] ?? false);
                $row['enabled'] = $allowedByPolicy && $row['supported'];
                $out[] = $row;
            }
            echo json_encode(['status'=>'success','engines'=>$out,'restrict'=>$restrict]);
            break;
        }
        case 'piBrowseVMs': {
            $deviceId = (string)($post['deviceId'] ?? '');
            $engine   = (string)($post['engine'] ?? '');
            if ($deviceId === '' || $engine === '') { echo json_encode(['status'=>'error','message'=>'deviceId and engine required']); break; }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }
            try {
                if ($engine === 'engine1/hyperv') {
                    $resp = $server->AdminDispatcherRequestBrowseHyperv($targetId);
                    $vms = [];
                    if ($resp && is_array($resp->VirtualMachines ?? null)) {
                        foreach ($resp->VirtualMachines as $vm) {
                            $vms[] = [
                                'id' => (string)($vm->ID ?? ''),
                                'name' => (string)($vm->DisplayName ?? $vm->ID ?? ''),
                            ];
                        }
                    }
                    echo json_encode(['status'=>'success','engine'=>$engine,'vms'=>$vms]);
                    break;
                }
                if ($engine === 'engine1/vmware') {
                    $host = (string)($post['host'] ?? '');
                    $user = (string)($post['user'] ?? '');
                    $pass = (string)($post['password'] ?? '');
                    if ($host === '' || $user === '') { echo json_encode(['status'=>'error','message'=>'VMware host and user are required to browse VMs']); break; }
                    $vsphere = new \Comet\VSphereConnection();
                    $vsphere->Hostname = $host;
                    $vsphere->Username = $user;
                    $vsphere->Password = $pass;
                    $vsphere->AllowInvalidCertificate = !empty($post['allowInvalidCert']);
                    $cred = new \Comet\VMwareConnection();
                    $cred->ConnectionType = 'vsphere';
                    $cred->VSphere = $vsphere;
                    $resp = $server->AdminDispatcherRequestBrowseVmware($targetId, $cred);
                    $vms = [];
                    if ($resp && is_array($resp->VirtualMachines ?? null)) {
                        foreach ($resp->VirtualMachines as $vm) {
                            $vms[] = [
                                'id' => (string)($vm->Name ?? ''),
                                'name' => (string)($vm->Name ?? ''),
                            ];
                        }
                    }
                    echo json_encode(['status'=>'success','engine'=>$engine,'vms'=>$vms]);
                    break;
                }
                if ($engine === 'engine1/proxmox') {
                    // Proxmox guest discovery is currently delegated to the agent; in v1 we fall back to manual VM IDs.
                    echo json_encode(['status'=>'success','engine'=>$engine,'vms'=>[],'manualOnly'=>true]);
                    break;
                }
                echo json_encode(['status'=>'error','message'=>'Engine not supported for VM browse']);
            } catch (\Throwable $e) {
                echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
            }
            break;
        }
        case 'piGet': {
            $itemId = (string)($post['itemId'] ?? '');
            if ($itemId === '') { echo json_encode(['status'=>'error','message'=>'itemId required']); break; }
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            $profile = $ph->Profile;
            if (!isset($profile->Sources[$itemId])) { echo json_encode(['status'=>'error','message'=>'Protected Item not found']); break; }
            $source = $profile->Sources[$itemId];

            // Find rules referencing this source so the wizard can show them in the Schedule step.
            $rules = [];
            if (isset($profile->BackupRules)) {
                foreach ((array)$profile->BackupRules as $rid => $rule) {
                    if (!is_object($rule)) continue;
                    if ((string)($rule->Source ?? '') !== $itemId) continue;
                    $rules[(string)$rid] = $rule->toArray(true);
                }
            }
            // Per-vault retention overrides on this item
            $overrides = [];
            if (isset($source->OverrideDestinationRetention) && is_array($source->OverrideDestinationRetention)) {
                foreach ($source->OverrideDestinationRetention as $vid => $rp) {
                    $overrides[(string)$vid] = is_object($rp) ? $rp->toArray(true) : $rp;
                }
            }
            echo json_encode([
                'status' => 'success',
                'item' => $source->toArray(true),
                'rules' => $rules,
                'retentionOverrides' => $overrides,
                'hash' => (string)$ph->ProfileHash,
            ]);
            break;
        }
        case 'piSave': {
            // Create or update a Protected Item (SourceConfig).
            $itemId      = (string)($post['itemId'] ?? '');
            $deviceId    = (string)($post['deviceId'] ?? '');
            $engine      = (string)($post['engine'] ?? '');
            $description = trim((string)($post['description'] ?? ''));
            $hash        = (string)($post['hash'] ?? '');
            if ($deviceId === '' || $engine === '' || $description === '') {
                echo json_encode(['status'=>'error','message'=>'deviceId, engine and description are required']);
                break;
            }

            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            if ($hash !== '' && $hash !== (string)$ph->ProfileHash) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed; please reload']);
                break;
            }
            $profile = $ph->Profile;

            $isNew = false;
            if ($itemId === '') {
                $isNew = true;
                try {
                    $itemId = strtoupper(bin2hex(random_bytes(8)).'-'.bin2hex(random_bytes(2)).'-'.bin2hex(random_bytes(2)).'-'.bin2hex(random_bytes(6)));
                } catch (\Throwable $e) {
                    $itemId = strtoupper(uniqid('', true));
                }
            }

            $source = isset($profile->Sources[$itemId]) ? $profile->Sources[$itemId] : new \Comet\SourceConfig();
            $source->Engine = $engine;
            $source->Description = $description;
            if ($isNew) {
                $source->OwnerDevice = $deviceId;
                if (empty($source->CreateTime)) { $source->CreateTime = time(); }
            } else if ($source->OwnerDevice === '') {
                $source->OwnerDevice = $deviceId;
            }
            $source->ModifyTime = time();

            // Engine-specific props/paths
            $existingProps = is_array($source->EngineProps) ? $source->EngineProps : [];
            // Drop any keys we manage so toggles can be cleared by absence.
            $managedFlags = ['USE_WIN_VSS','CONFIRM_EFS','RESCAN_UNCHANGED','EXTRA_ATTRIBUTES','BACKUP_TYPE','VMWARE_HOST','VMWARE_USER','VMWARE_ALLOW_INVALID_CERT'];
            foreach ($managedFlags as $k) { unset($existingProps[$k]); }
            // Drop INCLUDE/EXCLUDE keys when we are about to rebuild file selection.
            if ($engine === 'engine1/file') {
                foreach (array_keys($existingProps) as $k) {
                    if (strpos($k, 'INCLUDE') === 0 || strpos($k, 'EXCLUDE') === 0 || strpos($k, 'REXCLUDE') === 0) {
                        unset($existingProps[$k]);
                    }
                }
            }
            // Drop VM list keys when rebuilding VM selection
            if ($engine === 'engine1/hyperv' || $engine === 'engine1/vmware' || $engine === 'engine1/proxmox') {
                foreach (array_keys($existingProps) as $k) {
                    if (strpos($k, 'VM-') === 0) { unset($existingProps[$k]); }
                }
            }

            if ($engine === 'engine1/file') {
                $fileSel = $post['fileSelection'] ?? [];
                $includes = isset($fileSel['includes']) && is_array($fileSel['includes']) ? $fileSel['includes'] : [];
                $excludes = isset($fileSel['excludes']) && is_array($fileSel['excludes']) ? $fileSel['excludes'] : [];
                $i = 0;
                foreach ($includes as $p) {
                    $p = trim((string)$p); if ($p === '') continue;
                    $existingProps['INCLUDE-'.$i] = $p; $i++;
                }
                $i = 0;
                foreach ($excludes as $p) {
                    $p = trim((string)$p); if ($p === '') continue;
                    $existingProps['EXCLUDE-'.$i] = $p; $i++;
                }
                $opts = $post['fileOptions'] ?? [];
                if (!empty($opts['takeFilesystemSnapshot']))   { $existingProps['USE_WIN_VSS']      = '1'; }
                if (!empty($opts['rescanUnchanged']))          { $existingProps['RESCAN_UNCHANGED'] = '1'; }
                if (!empty($opts['dismissEFS']))               { $existingProps['CONFIRM_EFS']      = '1'; }
                if (!empty($opts['extraAttributes']))          { $existingProps['EXTRA_ATTRIBUTES'] = '1'; }
            } else if ($engine === 'engine1/hyperv' || $engine === 'engine1/vmware' || $engine === 'engine1/proxmox') {
                $vmSel = $post['vmSelection'] ?? [];
                $vms = isset($vmSel['vms']) && is_array($vmSel['vms']) ? $vmSel['vms'] : [];
                $i = 0;
                foreach ($vms as $vm) {
                    $vm = trim((string)$vm); if ($vm === '') continue;
                    $existingProps['VM-'.$i] = $vm; $i++;
                }
                $backupType = isset($vmSel['backupType']) ? (string)$vmSel['backupType'] : 'cbt';
                if (!in_array($backupType, ['cbt','standard','all'], true)) { $backupType = 'cbt'; }
                $existingProps['BACKUP_TYPE'] = $backupType;
                if ($engine === 'engine1/vmware') {
                    $cred = $post['vmwareCredentials'] ?? [];
                    if (is_array($cred)) {
                        if (!empty($cred['host'])) { $existingProps['VMWARE_HOST'] = (string)$cred['host']; }
                        if (!empty($cred['user'])) { $existingProps['VMWARE_USER'] = (string)$cred['user']; }
                        if (!empty($cred['allowInvalidCert'])) { $existingProps['VMWARE_ALLOW_INVALID_CERT'] = '1'; }
                        // Note: password is sent live to AdminDispatcherRequestBrowseVmware; not persisted here.
                    }
                }
            }

            $source->EngineProps = $existingProps;
            if (!is_array($profile->Sources)) { $profile->Sources = []; }
            $profile->Sources[$itemId] = $source;

            $resp = $server->AdminSetUserProfileHash($username, $profile, (string)$ph->ProfileHash);
            if ($resp && $resp->Status < 400) {
                $ph2 = $server->AdminGetUserProfileAndHash($username);
                echo json_encode(['status'=>'success','itemId'=>$itemId,'hash'=>($ph2 ? $ph2->ProfileHash : '')]);
            } else if ($resp && $resp->Status === 409) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed; please reload']);
            } else {
                echo json_encode(['status'=>'error','message'=>($resp ? $resp->Message : 'Failed to save Protected Item')]);
            }
            break;
        }
        case 'piDelete': {
            $itemId = (string)($post['itemId'] ?? '');
            $hash   = (string)($post['hash'] ?? '');
            if ($itemId === '') { echo json_encode(['status'=>'error','message'=>'itemId required']); break; }
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            if ($hash !== '' && $hash !== (string)$ph->ProfileHash) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed; please reload']); break;
            }
            $profile = $ph->Profile;
            if (isset($profile->Sources[$itemId])) { unset($profile->Sources[$itemId]); }
            // Remove rules referencing this source
            if (isset($profile->BackupRules) && is_array($profile->BackupRules)) {
                foreach ($profile->BackupRules as $rid => $rule) {
                    if (is_object($rule) && (string)($rule->Source ?? '') === $itemId) {
                        unset($profile->BackupRules[$rid]);
                    }
                }
            }
            $resp = $server->AdminSetUserProfileHash($username, $profile, (string)$ph->ProfileHash);
            if ($resp && $resp->Status < 400) {
                $ph2 = $server->AdminGetUserProfileAndHash($username);
                echo json_encode(['status'=>'success','hash'=>($ph2 ? $ph2->ProfileHash : '')]);
            } else if ($resp && $resp->Status === 409) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed; please reload']);
            } else {
                echo json_encode(['status'=>'error','message'=>($resp ? $resp->Message : 'Failed to delete Protected Item')]);
            }
            break;
        }
        case 'piScheduleSave': {
            // Create or update a single BackupRule for a Protected Item.
            $ruleId  = (string)($post['ruleId'] ?? '');
            $itemId  = (string)($post['itemId'] ?? '');
            $vaultId = (string)($post['vaultId'] ?? '');
            $name    = trim((string)($post['name'] ?? ''));
            $hash    = (string)($post['hash'] ?? '');
            $schedules = isset($post['schedules']) && is_array($post['schedules']) ? $post['schedules'] : [];
            $triggers  = isset($post['triggers']) && is_array($post['triggers']) ? $post['triggers'] : [];

            if ($itemId === '' || $vaultId === '' || $name === '') {
                echo json_encode(['status'=>'error','message'=>'itemId, vaultId and name are required']); break;
            }

            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            if ($hash !== '' && $hash !== (string)$ph->ProfileHash) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed']); break;
            }
            $profile = $ph->Profile;
            if (!isset($profile->Sources[$itemId])) { echo json_encode(['status'=>'error','message'=>'Protected Item not found']); break; }
            if (!isset($profile->Destinations[$vaultId])) { echo json_encode(['status'=>'error','message'=>'Storage Vault not found']); break; }

            if ($ruleId === '') {
                try {
                    $ruleId = strtoupper(bin2hex(random_bytes(8)).'-'.bin2hex(random_bytes(2)).'-'.bin2hex(random_bytes(2)).'-'.bin2hex(random_bytes(6)));
                } catch (\Throwable $e) { $ruleId = strtoupper(uniqid('', true)); }
            }
            $rule = isset($profile->BackupRules[$ruleId]) ? $profile->BackupRules[$ruleId] : new \Comet\BackupRuleConfig();
            $rule->Description = $name;
            $rule->Source = $itemId;
            $rule->Destination = $vaultId;
            if (empty($rule->CreateTime)) { $rule->CreateTime = time(); }
            $rule->ModifyTime = time();

            $sched = [];
            foreach ($schedules as $s) {
                if (!is_array($s)) continue;
                $sc = new \Comet\ScheduleConfig();
                $sc->FrequencyType = (int)($s['FrequencyType'] ?? 0);
                $sc->SecondsPast   = (int)($s['SecondsPast'] ?? 0);
                $sc->Offset        = (int)($s['Offset'] ?? 0);
                $sc->RandomDelaySecs = (int)($s['RandomDelaySecs'] ?? 0);
                if (isset($s['DaysSelect']) && is_array($s['DaysSelect'])) {
                    $dsc = new \Comet\DaysOfWeekConfig();
                    foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d) {
                        if (property_exists($dsc, $d)) { $dsc->{$d} = !empty($s['DaysSelect'][$d]); }
                    }
                    $sc->DaysSelect = $dsc;
                }
                if (!empty($s['SelectedDay']))   { $sc->SelectedDay   = (int)$s['SelectedDay']; }
                if (!empty($s['SelectedMonth'])) { $sc->SelectedMonth = (int)$s['SelectedMonth']; }
                $sched[] = $sc;
            }
            $rule->Schedules = $sched;

            $et = new \Comet\BackupRuleEventTriggers();
            $et->OnPCBoot = !empty($triggers['onPCBoot']);
            $et->OnPCBootIfLastJobMissed = !empty($triggers['ifLastMissed']);
            $et->OnLastJobFailDoRetry = !empty($triggers['retryOnFail']);
            $et->LastJobFailDoRetryCount = (int)($triggers['retryCount'] ?? 0);
            $et->LastJobFailDoRetryTime  = (int)($triggers['retryMinutes'] ?? 0);
            $rule->EventTriggers = $et;

            if (!is_array($profile->BackupRules)) { $profile->BackupRules = []; }
            $profile->BackupRules[$ruleId] = $rule;

            $resp = $server->AdminSetUserProfileHash($username, $profile, (string)$ph->ProfileHash);
            if ($resp && $resp->Status < 400) {
                $ph2 = $server->AdminGetUserProfileAndHash($username);
                echo json_encode(['status'=>'success','ruleId'=>$ruleId,'hash'=>($ph2 ? $ph2->ProfileHash : '')]);
            } else if ($resp && $resp->Status === 409) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed']);
            } else {
                echo json_encode(['status'=>'error','message'=>($resp ? $resp->Message : 'Failed to save schedule')]);
            }
            break;
        }
        case 'piScheduleDelete': {
            $ruleId = (string)($post['ruleId'] ?? '');
            $hash   = (string)($post['hash'] ?? '');
            if ($ruleId === '') { echo json_encode(['status'=>'error','message'=>'ruleId required']); break; }
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            if ($hash !== '' && $hash !== (string)$ph->ProfileHash) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed']); break;
            }
            $profile = $ph->Profile;
            if (isset($profile->BackupRules[$ruleId])) { unset($profile->BackupRules[$ruleId]); }
            $resp = $server->AdminSetUserProfileHash($username, $profile, (string)$ph->ProfileHash);
            if ($resp && $resp->Status < 400) {
                $ph2 = $server->AdminGetUserProfileAndHash($username);
                echo json_encode(['status'=>'success','hash'=>($ph2 ? $ph2->ProfileHash : '')]);
            } else if ($resp && $resp->Status === 409) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed']);
            } else {
                echo json_encode(['status'=>'error','message'=>($resp ? $resp->Message : 'Failed to delete schedule')]);
            }
            break;
        }
        case 'piRetentionSet': {
            // Per-Protected-Item retention override per Storage Vault.
            $itemId  = (string)($post['itemId'] ?? '');
            $vaultId = (string)($post['vaultId'] ?? '');
            $override = !empty($post['override']);
            $mode = (int)($post['mode'] ?? 801);
            $rangesRaw = $post['ranges'] ?? [];
            $hash = (string)($post['hash'] ?? '');
            if ($itemId === '' || $vaultId === '') { echo json_encode(['status'=>'error','message'=>'itemId and vaultId required']); break; }
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            if ($hash !== '' && $hash !== (string)$ph->ProfileHash) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed']); break;
            }
            $profile = $ph->Profile;
            if (!isset($profile->Sources[$itemId])) { echo json_encode(['status'=>'error','message'=>'Protected Item not found']); break; }
            $source = $profile->Sources[$itemId];
            if (!is_array($source->OverrideDestinationRetention)) { $source->OverrideDestinationRetention = []; }
            if (!$override) {
                if (isset($source->OverrideDestinationRetention[$vaultId])) { unset($source->OverrideDestinationRetention[$vaultId]); }
            } else {
                $rp = new \Comet\RetentionPolicy();
                $rp->Mode = $mode;
                $rp->Ranges = [];
                $ranges = is_array($rangesRaw) ? $rangesRaw : [];
                foreach ($ranges as $r) {
                    $ra = is_array($r) ? $r : (is_object($r) ? (array)$r : null);
                    if (!is_array($ra)) continue;
                    $type = (int)($ra['Type'] ?? 0); if ($type <= 0) continue;
                    $o = new \Comet\RetentionRange();
                    $o->Type = $type;
                    $o->Timestamp = (int)($ra['Timestamp'] ?? 0);
                    $o->Jobs = (int)($ra['Jobs'] ?? 0);
                    $o->Days = (int)($ra['Days'] ?? 0);
                    $o->Weeks = (int)($ra['Weeks'] ?? 0);
                    $o->Months = (int)($ra['Months'] ?? 0);
                    $o->Years = (int)($ra['Years'] ?? 0);
                    $o->WeekOffset = (int)($ra['WeekOffset'] ?? 0);
                    $o->MonthOffset = (int)($ra['MonthOffset'] ?? 1);
                    $o->YearOffset = (int)($ra['YearOffset'] ?? 1);
                    $rp->Ranges[] = $o;
                }
                $source->OverrideDestinationRetention[$vaultId] = $rp;
            }
            $profile->Sources[$itemId] = $source;
            $resp = $server->AdminSetUserProfileHash($username, $profile, (string)$ph->ProfileHash);
            if ($resp && $resp->Status < 400) {
                $ph2 = $server->AdminGetUserProfileAndHash($username);
                echo json_encode(['status'=>'success','hash'=>($ph2 ? $ph2->ProfileHash : '')]);
            } else if ($resp && $resp->Status === 409) {
                echo json_encode(['status'=>'error','code'=>'hash_mismatch','message'=>'Profile changed']);
            } else {
                echo json_encode(['status'=>'error','message'=>($resp ? $resp->Message : 'Failed to save retention')]);
            }
            break;
        }
        case 'renameDevice': {
            $deviceId = (string)($post['deviceId'] ?? '');
            $newName  = (string)($post['newName'] ?? '');
            if ($deviceId === '' || $newName === '') { echo json_encode(['status'=>'error','message'=>'deviceId and newName required']); break; }
            // Load profile+hash, change friendly name, save
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            $profile = $ph->Profile;
            // Devices is an associative array keyed by DeviceID
            if (!isset($profile->Devices[$deviceId])) { echo json_encode(['status'=>'error','message'=>'Device not found']); break; }
            $profile->Devices[$deviceId]->FriendlyName = $newName;
            $resp = $server->AdminSetUserProfileHash($username, $profile, $ph->ProfileHash);
            echo json_encode(['status' => ($resp->Status < 400 ? 'success' : 'error'), 'message' => $resp->Message, 'code' => $resp->Status]);
            break;
        }
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (\Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }

exit;


