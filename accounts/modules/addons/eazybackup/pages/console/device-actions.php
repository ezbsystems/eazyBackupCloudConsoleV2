<?php

use WHMCS\Database\Capsule;
use WHMCS\Session;

require_once __DIR__ . '/../../../../../modules/servers/comet/functions.php';

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

header('Content-Type: application/json');

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
                try { unset($profile->Destinations[$vaultId]->RetentionPolicy); } catch (\Throwable $_) {}
            } else {
                $rp = new \stdClass();
                $rp->Mode = $mode;
                $rp->Ranges = [];
                $ranges = is_array($rangesRaw) ? $rangesRaw : [];
                foreach ($ranges as $r) {
                    $ra = is_array($r) ? $r : (is_object($r) ? (array)$r : null);
                    if (!is_array($ra)) { continue; }
                    $type = (int)($ra['Type'] ?? 0);
                    if ($type <= 0) { continue; }
                    $o = new \stdClass();
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
                $profile->Destinations[$vaultId]->RetentionPolicy = $rp;
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
                            if (!array_key_exists('RetentionPolicy', $destCfg)) { continue; }
                            $rpRaw = $destCfg['RetentionPolicy'];
                            if ($rpRaw === null || $rpRaw === false) {
                                // Explicitly clearing override falls back to destination default policy.
                                try { unset($live->Destinations[$destId]->RetentionPolicy); } catch (\Throwable $_) {}
                                $patched = true;
                                continue;
                            }
                            $rpArr = is_array($rpRaw) ? $rpRaw : (is_object($rpRaw) ? (array)$rpRaw : null);
                            if (!is_array($rpArr)) { continue; }
                            $rp = new \Comet\RetentionPolicy();
                            $rp->fromArray($rpArr);
                            $live->Destinations[$destId]->RetentionPolicy = $rp;
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
            if ($deviceId === '') { echo json_encode(['status'=>'error','message'=>'deviceId required']); break; }
            $ph = $server->AdminGetUserProfileAndHash($username);
            if (!$ph || !$ph->Profile) { echo json_encode(['status'=>'error','message'=>'Profile not found']); break; }
            $profile = $ph->Profile;
            $out = [];
            // Prefer global Sources filtered by OwnerDevice
            if (isset($profile->Sources)) {
                foreach ((array)$profile->Sources as $sid => $src) {
                    if (is_object($src)) {
                        $owner = isset($src->OwnerDevice) ? (string)$src->OwnerDevice : '';
                        if ($owner === $deviceId) {
                            $name = isset($src->Description) ? $src->Description : (string)$sid;
                            $out[] = [ 'id' => (string)$sid, 'name' => (string)$name ];
                        }
                    }
                }
            }
            // Fallback: device-local Sources if present
            if (empty($out) && isset($profile->Devices[$deviceId]) && isset($profile->Devices[$deviceId]->Sources)) {
                foreach ((array)$profile->Devices[$deviceId]->Sources as $sourceId => $sourceInfo) {
                    if (is_object($sourceInfo)) {
                        $name = isset($sourceInfo->Description) ? $sourceInfo->Description : (string)$sourceId;
                        $out[] = [ 'id' => (string)$sourceId, 'name' => (string)$name ];
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
            // Response contains Snapshots array; return as-is for the UI to group by SourceGUID
            echo json_encode(['status'=>'success','snapshots' => $resp ? $resp->toArray(true) : []]);
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
            if ($resp && is_array($resp->StoredObjects)) {
                foreach ($resp->StoredObjects as $obj) {
                    $isDir = false;
                    try {
                        $isDir = (isset($obj->Subtree) && (string)$obj->Subtree !== '');
                    } catch (\Throwable $e) { $isDir = false; }
                    $entries[] = [
                        'name' => (string)($obj->DisplayName ?: $obj->Name),
                        'rawName' => (string)$obj->Name,
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
            $type      = isset($post['type']) ? (int)$post['type'] : null; // RESTORETYPE_
            $destPath  = (string)($post['destPath'] ?? '');
            $overwrite = (string)($post['overwrite'] ?? 'none'); // none|ifNewer|ifDifferent|always
            if ($deviceId === '' || $sourceId === '' || $vaultId === '' || $type === null) {
                echo json_encode(['status'=>'error','message'=>'deviceId, sourceId, vaultId and type are required']); break;
            }
            $targetId = $findTarget($server, $username, $deviceId);
            if (!$targetId) { echo json_encode(['status'=>'error','message'=>'Device is not online (no live connection)']); break; }

            // Build RestoreJobAdvancedOptions
            $opt = new \Comet\RestoreJobAdvancedOptions();
            $opt->Type = $type;
            if ($destPath !== '') { $opt->DestPath = $destPath; }
            // Overwrite mapping
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
            } else { // none
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
                        if ($p !== '') { $clean[] = $p; }
                    }
                    if (!empty($clean)) { $paths = array_values(array_unique($clean)); }
                }
            }

            $resp = $server->AdminDispatcherRunRestoreCustom($targetId, $sourceId, $vaultId, $opt, ($snapshot !== '' ? $snapshot : null), $paths, null, null, null);
            echo json_encode(['status'=> ($resp->Status < 400 ? 'success' : 'error'), 'message'=>$resp->Message, 'code'=>$resp->Status]);
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


