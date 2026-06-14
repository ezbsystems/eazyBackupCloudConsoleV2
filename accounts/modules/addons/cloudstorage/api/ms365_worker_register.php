<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Ms365WorkerApiAuth;
use Ms365Backup\WorkerNodeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

header('Content-Type: application/json');

$request = Request::createFromGlobals();
if ($auth = Ms365WorkerApiAuth::authenticate($request)) {
    $auth->send();
    exit;
}

$body = Ms365WorkerApiAuth::jsonBody($request);
$hostname = trim((string) ($body['hostname'] ?? ''));
$maxConcurrent = (int) ($body['max_concurrent_runs'] ?? 10);
$version = trim((string) ($body['version'] ?? ''));
$proxmoxVmid = isset($body['proxmox_vmid']) ? (int) $body['proxmox_vmid'] : null;

if ($hostname === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'hostname required'], 400))->send();
    exit;
}

try {
    $nodeId = WorkerNodeRepository::register($hostname, $maxConcurrent, $version, $proxmoxVmid > 0 ? $proxmoxVmid : null);
    (new JsonResponse([
        'status' => 'success',
        'data' => ['node_id' => $nodeId, 'status' => 'active'],
    ]))->send();
} catch (\Throwable $e) {
    (new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500))->send();
}
