<?php

use WHMCS\Database\Capsule;
use WHMCS\Session;

require_once __DIR__ . '/../../../../../init.php';
require_once __DIR__ . '/../../../../../modules/servers/comet/functions.php';

if (!defined('WHMCS')) { die('This file cannot be accessed directly'); }

header('Content-Type: application/json');

try {
    $post = json_decode(file_get_contents('php://input'), true);
    if (!is_array($post)) { echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']); exit; }

    $action    = (string)($post['action'] ?? '');
    $serviceId = (int)($post['serviceId'] ?? 0);
    $jobId     = (string)($post['jobId'] ?? '');
    if (!$action) { echo json_encode(['status' => 'error', 'message' => 'Missing action']); exit; }
    if ($action !== 'listAllJobs' && $serviceId <= 0) { echo json_encode(['status' => 'error', 'message' => 'Missing serviceId']); exit; }

    $clientId = 0;
    try { $clientId = (int) (Session::get('uid') ?: 0); } catch (\Throwable $e) { $clientId = 0; }
    if ($clientId <= 0) { $clientId = (int) ($_SESSION['uid'] ?? 0); }
    if ($clientId <= 0) { echo json_encode(['status' => 'error', 'message' => 'Not authenticated']); exit; }

    $formatJobs = function($rows) {
        $out = [];
        foreach ($rows as $r) {
            $content = [];
            if (isset($r->content)) {
                try { $content = is_array($r->content) ? $r->content : json_decode($r->content, true); } catch (\Throwable $e) { $content = []; }
            }
            $startedAtTs = $r->started_at ? strtotime($r->started_at) : 0;
            $endedAtTs   = $r->ended_at ? strtotime($r->ended_at) : 0;
            $durationSec = ($endedAtTs > 0 && $startedAtTs > 0) ? max(0, $endedAtTs - $startedAtTs) : 0;
            $vaultEndSize = 0;
            if (isset($content['DestinationSizeEnd']['Size'])) { $vaultEndSize = (int)$content['DestinationSizeEnd']['Size']; }
            $typeLabel   = \Comet\JobType::toString((int)$r->type);
            $statusLabel = \Comet\JobStatus::toString((int)$r->status);
            $clientVersion = $content['ClientVersion'] ?? '';
            $out[] = [
                'username'               => $r->username,
                'id'                     => $r->id,
                'service_id'             => isset($r->service_id) ? (int)$r->service_id : 0,
                'device_name'            => $r->device_name ?? ($content['DeviceID'] ?? ''),
                'protected_item_name'    => $r->protected_item_name ?? '',
                'vault_name'             => $r->vault_name ?? '',
                'client_version'         => $clientVersion,
                'type_label'             => $typeLabel,
                'status_label'           => $statusLabel,
                'total_directories'      => (int)$r->total_directories,
                'total_files'            => (int)$r->total_files,
                'total_bytes'            => (int)$r->total_bytes,
                'vault_size_end'         => (int)$vaultEndSize,
                'upload_bytes'           => (int)$r->upload_bytes,
                'download_bytes'         => (int)$r->download_bytes,
                'started_at'             => $r->started_at,
                'ended_at'               => $r->ended_at,
                'duration_sec'           => $durationSec,
            ];
        }
        return $out;
    };

    switch ($action) {
        case 'getJobReport': {
            $account = Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->where('userid', $clientId)
                ->select('id', 'packageid', 'username')
                ->first();
            if (!$account) { echo json_encode(['status' => 'error', 'message' => 'Service not found or access denied']); break; }
            $params = comet_ServiceParams($serviceId);
            $params['username'] = $account->username;
            $server = comet_Server($params);
            if ($jobId === '') { echo json_encode(['status'=>'error','message'=>'jobId required']); break; }
            try {
                $props   = $server->AdminGetJobProperties($jobId);
                $entries = $server->AdminGetJobLogEntries($jobId);
                echo json_encode([
                    'status'     => 'success',
                    'properties' => $props ? $props->toArray(true) : null,
                    'entries'    => is_array($entries) ? array_map(function($e){ return is_object($e) && method_exists($e,'toArray') ? $e->toArray(true) : (array)$e; }, $entries) : [],
                ]);
            } catch (\Throwable $e) {
                echo json_encode(['status'=>'error','message'=>'Failed to load job report']);
            }
            break;
        }
        case 'listJobs': {
            // Jobs for a single service (scoped to this account/username)
            $account = Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->where('userid', $clientId)
                ->select('id', 'packageid', 'username')
                ->first();
            if (!$account) { echo json_encode(['status' => 'error', 'message' => 'Service not found or access denied']); break; }
            $rows = Capsule::table('comet_jobs')
                ->leftJoin('comet_devices', 'comet_devices.id', '=', 'comet_jobs.comet_device_id')
                ->leftJoin('comet_items', 'comet_items.id', '=', 'comet_jobs.comet_item_id')
                ->leftJoin('comet_vaults','comet_vaults.id','=', 'comet_jobs.comet_vault_id')
                ->where('comet_jobs.client_id', (int)$account->userid)
                ->where('comet_jobs.username', $account->username)
                ->orderBy('comet_jobs.started_at', 'desc')
                ->limit(500)
                ->get([
                    'comet_jobs.*',
                    'comet_devices.name as device_name',
                    'comet_items.name as protected_item_name',
                    'comet_vaults.name as vault_name',
                ]);
            echo json_encode(['status'=>'success','jobs'=>$formatJobs($rows)]);
            break;
        }
        case 'listAllJobs': {
            // Aggregate jobs for this client across all active usernames
            $activeUsernames = Capsule::table('tblhosting')
                ->select('username')
                ->where('domainstatus', 'Active')
                ->where('userid', $clientId)
                ->pluck('username')
                ->toArray();
            if (empty($activeUsernames)) { echo json_encode(['status'=>'success','jobs'=>[]]); break; }
            $rows = Capsule::table('comet_jobs')
                ->leftJoin('comet_devices', 'comet_devices.id', '=', 'comet_jobs.comet_device_id')
                ->leftJoin('comet_items', 'comet_items.id', '=', 'comet_jobs.comet_item_id')
                ->leftJoin('comet_vaults','comet_vaults.id','=', 'comet_jobs.comet_vault_id')
                ->leftJoin('tblhosting', 'tblhosting.username', '=', 'comet_jobs.username')
                ->where('comet_jobs.client_id', (int)$clientId)
                ->whereIn('comet_jobs.username', $activeUsernames)
                ->orderBy('comet_jobs.started_at', 'desc')
                ->limit(1000)
                ->get([
                    'comet_jobs.*',
                    'comet_devices.name as device_name',
                    'comet_items.name as protected_item_name',
                    'comet_vaults.name as vault_name',
                    'tblhosting.id as service_id',
                ]);
            echo json_encode(['status'=>'success','jobs'=>$formatJobs($rows)]);
            break;
        }
        case 'getJobLogRaw': {
            $account = Capsule::table('tblhosting')
                ->where('id', $serviceId)
                ->where('userid', $clientId)
                ->select('id', 'packageid', 'username')
                ->first();
            if (!$account) { echo json_encode(['status' => 'error', 'message' => 'Service not found or access denied']); break; }
            $params = comet_ServiceParams($serviceId);
            $params['username'] = $account->username;
            $server = comet_Server($params);
            if ($jobId === '') { echo json_encode(['status'=>'error','message'=>'jobId required']); break; }
            try {
                $log = $server->AdminGetJobLog($jobId);
                echo json_encode(['status'=>'success','log'=>$log]);
            } catch (\Throwable $e) {
                echo json_encode(['status'=>'error','message'=>'Failed to load raw log']);
            }
            break;
        }
        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
            break;
    }
} catch (\Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }

exit;


