<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Fleet\WorkerConfigService;
use Ms365Backup\Ms365WorkerApiAuth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$request = Request::createFromGlobals();
if ($auth = Ms365WorkerApiAuth::authenticate($request)) {
    $auth->send();
    exit;
}

$version = (int) ($request->query->get('version') ?? 0);
$nodeId = trim((string) ($request->query->get('node') ?? ''));
$nonce = trim((string) ($request->query->get('nonce') ?? ''));

if ($version <= 0 || $nodeId === '' || $nonce === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'version, node, and nonce required'], 400))->send();
    exit;
}

$verified = WorkerConfigService::verifyConfigNonce($nonce);
if ($verified === null || $verified['version'] !== $version || $verified['node_id'] !== $nodeId) {
    (new JsonResponse(['status' => 'error', 'message' => 'invalid or expired nonce'], 403))->send();
    exit;
}

$config = WorkerConfigService::getVersion($version);
if ($config === null) {
    (new JsonResponse(['status' => 'error', 'message' => 'config version not found'], 404))->send();
    exit;
}

$yaml = (string) ($config['yaml'] ?? '');
if ($yaml === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'config missing'], 404))->send();
    exit;
}

$ip = (string) ($request->getClientIp() ?? '');
WorkerConfigService::logConfigDownload($version, $nodeId, $nonce, $ip);

$response = new Response($yaml, 200, [
    'Content-Type' => 'application/x-yaml',
    'Content-Disposition' => 'attachment; filename="config.yaml"',
    'X-MS365-Config-Version' => (string) $version,
    'X-MS365-Config-Sha256' => (string) ($config['sha256'] ?? ''),
]);
$response->send();
