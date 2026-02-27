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

    // Local mapping for status codes → Comet status labels
    $mapStatus = function(int $code): string {
        switch ($code) {
            case 5000: return 'SUCCESS';
            case 6000: return 'ACTIVE';
            case 6001: return 'ACTIVE';
            case 6002: return 'REVIVED';
            case 7000: return 'TIMEOUT';
            case 7001: return 'WARNING';
            case 7002: return 'ERROR';
            case 7003: return 'QUOTA_EXCEEDED';
            case 7004: return 'MISSED';
            case 7005: return 'CANCELLED';
            case 7006: return 'ALREADY_RUNNING';
            case 7007: return 'ABANDONED';
            default:   return 'UNKNOWN';
        }
    };
    $statusLabels = ['Success', 'Running', 'Error', 'Warning', 'Missed', 'Timeout', 'Cancelled', 'Skipped'];
    $emptyStatusCounts = array_fill_keys($statusLabels, 0);
    $normalizeStatus = function($codeOrLabel) use ($mapStatus): string {
        if (is_numeric($codeOrLabel)) {
            $codeOrLabel = $mapStatus((int)$codeOrLabel);
        }
        $u = strtoupper(trim((string)$codeOrLabel));
        if ($u === 'SUCCESS') return 'Success';
        if ($u === 'RUNNING' || $u === 'ACTIVE' || $u === 'REVIVED' || $u === 'ALREADY_RUNNING') return 'Running';
        if ($u === 'TIMEOUT') return 'Timeout';
        if ($u === 'WARNING') return 'Warning';
        if ($u === 'ERROR' || $u === 'QUOTA_EXCEEDED' || $u === 'ABANDONED') return 'Error';
        if ($u === 'MISSED') return 'Missed';
        if ($u === 'SKIPPED') return 'Skipped';
        if ($u === 'CANCELLED' || $u === 'CANCELED') return 'Cancelled';
        return 'Unknown';
    };

    switch ($action) {
        case 'listJobs': {
            // Optional filters
            $page = max(1, (int)($post['page'] ?? 1));
            $pageSize = min(200, max(10, (int)($post['pageSize'] ?? 25)));
            $sortBy = (string)($post['sortBy'] ?? 'StartTime');
            $sortDir = strtolower((string)($post['sortDir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
            $q = trim((string)($post['q'] ?? ''));
            $statusFilters = [];
            if (!empty($post['statuses']) && is_array($post['statuses'])) {
                foreach ($post['statuses'] as $candidate) {
                    $label = $normalizeStatus($candidate);
                    if (in_array($label, $statusLabels, true)) {
                        $statusFilters[$label] = true;
                    }
                }
                $statusFilters = array_keys($statusFilters);
            }

            $jobs = $server->AdminGetJobsForUser($username);

            if (!is_array($jobs)) {
                echo json_encode(['status' => 'success', 'total' => 0, 'rows' => [], 'facets' => ['statusCounts' => $emptyStatusCounts]]);
                break;
            }

            // Build description map for sources
            $sourceGUIDs = array_values(array_unique(array_map(function($j){ return $j->SourceGUID ?? ''; }, $jobs)));
            $descriptions = \Comet\CometItem::getProtectedItemDescriptions($server, $username, $sourceGUIDs);

            // Load user profile maps: destinations (vaults) and device friendly names
            $ph = $server->AdminGetUserProfileAndHash($username);
            $destMap = [];
            $deviceMap = [];
            if ($ph && $ph->Profile) {
                $profile = $ph->Profile;
                if (!empty($profile->Destinations)) {
                    foreach ($profile->Destinations as $guid => $dest) {
                        $destMap[(string)$guid] = (string)($dest->Description ?? '');
                    }
                }
                if (!empty($profile->Devices)) {
                    foreach ($profile->Devices as $devId => $dev) {
                        $deviceMap[(string)$devId] = (string)($dev->FriendlyName ?? '');
                    }
                }
            }

            // Enrich + normalize rows
            $rows = array_map(function($j) use ($descriptions, $destMap, $deviceMap, $mapStatus, $normalizeStatus) {
                $start = (int)($j->StartTime ?? 0);
                $end   = (int)($j->EndTime ?? 0);
                $dur   = $end > 0 && $start > 0 ? max(0, $end - $start) : 0;
                $friendlyType = \Comet\JobType::toString($j->Classification ?? 0);
                $friendlyStatus = $normalizeStatus($mapStatus((int)($j->Status ?? 0)));
                $itemDesc = $descriptions[$j->SourceGUID ?? ''] ?? 'Unknown Item';
                $devId = (string)($j->DeviceID ?? $j->Device ?? '');
                $deviceFriendly = $deviceMap[$devId] ?? (string)($j->Device ?? $devId);
                $destGuid = (string)($j->Destination ?? $j->DestinationGUID ?? '');
                $vaultName = $destMap[$destGuid] ?? (string)($j->DestinationLocation ?? '');
                return [
                    'Username'           => (string)($j->Username ?? ''),
                    'JobID'              => (string)($j->GUID ?? ''),
                    'Device'             => (string)$deviceFriendly,
                    'ProtectedItem'      => (string)$itemDesc,
                    'StorageVault'       => (string)$vaultName,
                    'Version'            => (string)($j->ClientVersion ?? ''),
                    'Type'               => (string)$friendlyType,
                    'Status'             => (string)$friendlyStatus,
                    'Directories'        => (int)($j->TotalDirectories ?? 0),
                    'Files'              => (int)($j->TotalFiles ?? 0),
                    'Size'               => (int)($j->TotalSize ?? 0),
                    'VaultSize'          => 0,
                    'Uploaded'           => (int)($j->UploadSize ?? 0),
                    'Downloaded'         => (int)($j->DownloadSize ?? 0),
                    'Started'            => $start,
                    'Ended'              => $end,
                    'Duration'           => $dur,
                    'Classification'     => (int)($j->Classification ?? 0),
                    'StatusCode'         => (int)($j->Status ?? 0),
                    'SourceGUID'         => (string)($j->SourceGUID ?? ''),
                    'DestinationGUID'    => (string)$destGuid,
                ];
            }, $jobs);

            // Search filter (simple contains across some fields)
            if ($q !== '') {
                $qLower = mb_strtolower($q);
                $rows = array_values(array_filter($rows, function($r) use ($qLower) {
                    $blob = mb_strtolower(($r['Username'] ?? '') . ' ' . ($r['JobID'] ?? '') . ' ' . ($r['Device'] ?? '') . ' ' . ($r['ProtectedItem'] ?? '') . ' ' . ($r['StorageVault'] ?? '') . ' ' . ($r['Type'] ?? '') . ' ' . ($r['Status'] ?? ''));
                    return strpos($blob, $qLower) !== false;
                }));
            }
            $statusCounts = $emptyStatusCounts;
            foreach ($rows as $row) {
                $label = (string)($row['Status'] ?? '');
                if (isset($statusCounts[$label])) {
                    $statusCounts[$label]++;
                }
            }
            if (!empty($statusFilters)) {
                $activeSet = array_flip($statusFilters);
                $rows = array_values(array_filter($rows, function($row) use ($activeSet) {
                    $label = (string)($row['Status'] ?? '');
                    return isset($activeSet[$label]);
                }));
            }

            // Sorting
            usort($rows, function($a, $b) use ($sortBy, $sortDir) {
                $av = $a[$sortBy] ?? null; $bv = $b[$sortBy] ?? null;
                if ($av == $bv) return 0;
                $cmp = ($av < $bv) ? -1 : 1;
                return $sortDir === 'asc' ? $cmp : -$cmp;
            });

            $total = count($rows);
            $offset = ($page - 1) * $pageSize;
            $paged = array_slice($rows, $offset, $pageSize);

            // Enrich paged rows with vault size from detailed job properties
            foreach ($paged as &$r) {
                $jid = $r['JobID'] ?? '';
                if (!$jid) { continue; }
                try {
                    $jd = $server->AdminGetJobProperties($jid);
                    if ($jd) {
                        $r['VaultSize'] = (int)($jd->Size ?? ($jd->DestinationSizeEnd->Size ?? 0));
                        $devId = (string)($jd->DeviceID ?? '');
                        if ($devId && isset($deviceMap[$devId])) { $r['Device'] = $deviceMap[$devId]; }
                        $dGuid = (string)($jd->DestinationGUID ?? '');
                        if ($dGuid && isset($destMap[$dGuid])) { $r['StorageVault'] = $destMap[$dGuid]; }
                    }
                } catch (\Throwable $e) {}
            }

            echo json_encode([
                'status' => 'success',
                'total' => $total,
                'rows' => $paged,
                'facets' => ['statusCounts' => $statusCounts],
            ]);
            break;
        }
        case 'jobDetail': {
            $jobId = (string)($post['jobId'] ?? '');
            if ($jobId === '') { echo json_encode(['status'=>'error','message'=>'jobId required']); break; }
            $job = $server->AdminGetJobProperties($jobId);
            if (!$job) { echo json_encode(['status'=>'error','message'=>'Job not found']); break; }
            // Add protected item description
            $descMap = \Comet\CometItem::getProtectedItemDescriptions($server, $username, [ $job->SourceGUID ?? '' ]);
            $friendlyStatus = $normalizeStatus($mapStatus((int)($job->Status ?? 0)));
            $friendlyType = \Comet\JobType::toString($job->Classification ?? 0);
            // Build response array explicitly so our extra fields are included
            $out = $job->toArray(true);
            $out['ProtectedItemDescription'] = $descMap[$job->SourceGUID ?? ''] ?? 'Unknown Item';
            $out['FriendlyStatus'] = $friendlyStatus;
            $out['FriendlyJobType'] = $friendlyType;
            // Device and Vault friendly names
            $ph = $server->AdminGetUserProfileAndHash($username);
            if ($ph && $ph->Profile) {
                $profile = $ph->Profile;
                $devId = (string)($job->DeviceID ?? '');
                if ($devId && isset($profile->Devices[$devId])) {
                    $out['DeviceFriendlyName'] = (string)($profile->Devices[$devId]->FriendlyName ?? '');
                }
                $dGuid = (string)($job->DestinationGUID ?? '');
                if ($dGuid && isset($profile->Destinations[$dGuid])) {
                    $out['VaultDescription'] = (string)($profile->Destinations[$dGuid]->Description ?? '');
                }
            }
            echo json_encode(['status'=>'success','job' => $out]);
            break;
        }
        case 'jobLogEntries': {
            $jobId = (string)($post['jobId'] ?? '');
            if ($jobId === '') { echo json_encode(['status'=>'error','message'=>'jobId required']); break; }
            $entries = $server->AdminGetJobLogEntries($jobId);
            $rows = [];
            foreach ($entries as $e) {
                $rows[] = [
                    'Time' => (int)($e->Time ?? 0),
                    'Severity' => (string)($e->Severity ?? ''),
                    'Message' => (string)($e->Message ?? ''),
                ];
            }
            echo json_encode(['status'=>'success','rows'=>$rows]);
            break;
        }
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (\Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }

exit;


