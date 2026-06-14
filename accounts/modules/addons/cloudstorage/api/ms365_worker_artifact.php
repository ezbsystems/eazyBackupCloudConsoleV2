<?php

require_once __DIR__ . '/../../../../init.php';
require_once dirname(__DIR__) . '/../ms365backup/ms365backup_autoload.php';

use Ms365Backup\Fleet\ArtifactService;
use Ms365Backup\Fleet\ReleaseRepository;
use Ms365Backup\Ms365WorkerApiAuth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$request = Request::createFromGlobals();
if ($auth = Ms365WorkerApiAuth::authenticate($request)) {
    $auth->send();
    exit;
}

$releaseId = (int) ($request->query->get('release_id') ?? 0);
$nonce = trim((string) ($request->query->get('nonce') ?? ''));

if ($releaseId <= 0 || $nonce === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'release_id and nonce required'], 400))->send();
    exit;
}

$verified = ArtifactService::verifyNonce($nonce);
if ($verified === null || $verified['release_id'] !== $releaseId) {
    (new JsonResponse(['status' => 'error', 'message' => 'invalid or expired nonce'], 403))->send();
    exit;
}

$release = ReleaseRepository::get($releaseId);
if ($release === null) {
    (new JsonResponse(['status' => 'error', 'message' => 'release not found'], 404))->send();
    exit;
}

$path = (string) ($release['artifact_path'] ?? '');
if ($path === '' || !is_file($path)) {
    (new JsonResponse(['status' => 'error', 'message' => 'artifact missing'], 404))->send();
    exit;
}

$ip = (string) ($request->getClientIp() ?? '');
ArtifactService::logDownload($releaseId, $verified['node_id'], $nonce, $ip);

$response = new Response(file_get_contents($path) ?: '', 200, [
    'Content-Type' => 'application/octet-stream',
    'Content-Disposition' => 'attachment; filename="ms365-backup-worker"',
    'X-MS365-Release-Version' => (string) $release['version'],
    'X-MS365-Release-Sha256' => (string) $release['sha256'],
]);
$response->send();
