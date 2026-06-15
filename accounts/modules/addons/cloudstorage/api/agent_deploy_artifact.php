<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\DeployAuth;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\DeployStore;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

$request = Request::createFromGlobals();

$deploymentId = (int) ($request->query->get('deployment_id') ?? 0);
$artifactKey = trim((string) ($request->query->get('artifact_key') ?? ''));
$nonce = trim((string) ($request->query->get('nonce') ?? ''));

if ($deploymentId <= 0 || $artifactKey === '' || $nonce === '') {
    (new JsonResponse(['status' => 'error', 'message' => 'deployment_id, artifact_key, and nonce required'], 400))->send();
    exit;
}

$verified = DeployAuth::verifyNonce($nonce);
if ($verified === null
    || $verified['deployment_id'] !== $deploymentId
    || $verified['artifact_key'] !== $artifactKey) {
    (new JsonResponse(['status' => 'error', 'message' => 'invalid or expired nonce'], 403))->send();
    exit;
}

$deployment = DeployStore::getDeployment($deploymentId);
if (!$deployment || ($deployment['status'] ?? '') !== 'active') {
    (new JsonResponse(['status' => 'error', 'message' => 'deployment not found'], 404))->send();
    exit;
}

$artifact = Capsule::table('s3_agent_deploy_artifacts')
    ->where('deployment_id', $deploymentId)
    ->where('artifact_key', $artifactKey)
    ->first();
if (!$artifact) {
    (new JsonResponse(['status' => 'error', 'message' => 'artifact not found'], 404))->send();
    exit;
}

$publishDir = (string) Settings::get('agent_build_publish_dir', '/var/www/eazybackup.ca/accounts/client_installer');
$versioned = (string) $artifact->versioned_filename;
$latest = (string) $artifact->latest_filename;
$path = $publishDir . '/' . $versioned;
if (!is_file($path)) {
    $path = $publishDir . '/' . $latest;
}
if (!is_file($path)) {
    (new JsonResponse(['status' => 'error', 'message' => 'artifact file missing on publisher'], 404))->send();
    exit;
}

$ip = (string) ($request->getClientIp() ?? '');
DeployStore::logDownload($deploymentId, $artifactKey, $nonce, $ip);

$response = new Response(file_get_contents($path) ?: '', 200, [
    'Content-Type' => 'application/octet-stream',
    'Content-Disposition' => 'attachment; filename="' . basename($latest) . '"',
    'X-Agent-Deploy-Sha256' => (string) ($artifact->sha256 ?? ''),
    'X-Agent-Deploy-Version' => (string) ($deployment['version_label'] ?? ''),
]);
$response->send();
