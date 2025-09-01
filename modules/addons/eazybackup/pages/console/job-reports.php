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

    switch ($action) {
        case 'listJobs': {
            // Optional filters
            $page = max(1, (int)($post['page'] ?? 1));
            $pageSize = min(200, max(10, (int)($post['pageSize'] ?? 25)));
            $sortBy = (string)($post['sortBy'] ?? 'StartTime');
            $sortDir = strtolower((string)($post['sortDir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
            $q = trim((string)($post['q'] ?? ''));

            $jobs = $server->AdminGetJobsForUser($username);

            if (!is_array($jobs)) { echo json_encode(['status' => 'success', 'total' => 0, 'rows' => []]); break; }

            // Build description map for sources
            $sourceGUIDs = array_values(array_unique(array_map(function($j){ return $j->SourceGUID ?? ''; }, $jobs)));
            $descriptions = \Comet\CometItem::getProtectedItemDescriptions($server, $username, $sourceGUIDs);

            // Enrich + normalize rows
            $rows = array_map(function($j) use ($descriptions) {
                $start = (int)($j->StartTime ?? 0);
                $end   = (int)($j->EndTime ?? 0);
                $dur   = $end > 0 && $start > 0 ? max(0, $end - $start) : 0;
                $friendlyType = \Comet\JobType::toString($j->Classification ?? 0);
                $friendlyStatus = \Comet\JobStatus::toString($j->Status ?? 0);
                $itemDesc = $descriptions[$j->SourceGUID ?? ''] ?? 'Unknown Item';
                return [
                    'Username'           => (string)($j->Username ?? ''),
                    'JobID'              => (string)($j->GUID ?? ''),
                    'Device'             => (string)($j->Device ?? ''),
                    'ProtectedItem'      => (string)$itemDesc,
                    'StorageVault'       => (string)($j->DestinationLocation ?? ''),
                    'Version'            => (string)($j->ClientVersion ?? ''),
                    'Type'               => (string)$friendlyType,
                    'Status'             => (string)$friendlyStatus,
                    'Directories'        => (int)($j->TotalDirectories ?? 0),
                    'Files'              => (int)($j->TotalFiles ?? 0),
                    'Size'               => (int)($j->TotalSize ?? 0),
                    'VaultSize'          => (int)($j->DestinationSize ?? 0),
                    'Uploaded'           => (int)($j->UploadSize ?? 0),
                    'Downloaded'         => (int)($j->DownloadSize ?? 0),
                    'Started'            => $start,
                    'Ended'              => $end,
                    'Duration'           => $dur,
                    'Classification'     => (int)($j->Classification ?? 0),
                    'StatusCode'         => (int)($j->Status ?? 0),
                    'SourceGUID'         => (string)($j->SourceGUID ?? ''),
                    'DestinationGUID'    => (string)($j->Destination ?? ''),
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

            echo json_encode(['status' => 'success', 'total' => $total, 'rows' => $paged]);
            break;
        }
        case 'jobDetail': {
            $jobId = (string)($post['jobId'] ?? '');
            if ($jobId === '') { echo json_encode(['status'=>'error','message'=>'jobId required']); break; }
            $job = $server->AdminGetJobProperties($jobId);
            if (!$job) { echo json_encode(['status'=>'error','message'=>'Job not found']); break; }
            // Add protected item description
            $descMap = \Comet\CometItem::getProtectedItemDescriptions($server, $username, [ $job->SourceGUID ?? '' ]);
            $job->ProtectedItemDescription = $descMap[$job->SourceGUID ?? ''] ?? 'Unknown Item';
            echo json_encode(['status'=>'success','job' => $job->toArray(true)]);
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


