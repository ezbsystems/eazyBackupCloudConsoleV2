<?php

use WHMCS\Database\Capsule;

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

    $account = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->where('userid', Auth::client()->id)
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

            // Paths (for now not selecting subpaths; pass null to restore all)
            $paths = null;

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


