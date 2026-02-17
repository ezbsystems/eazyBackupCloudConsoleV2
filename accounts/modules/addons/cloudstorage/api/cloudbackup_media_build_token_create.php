<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/MspController.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\MspController;
use WHMCS\Module\Addon\CloudStorage\Client\RecoveryMediaBundleService;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function respondError(string $code, string $message, int $httpCode = 200): void
{
    respond([
        'status' => 'fail',
        'code' => $code,
        'message' => $message,
    ], $httpCode);
}

function getBodyJson(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function base64urlEncode(string $input): string
{
    return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
}

function getSigningKey(): string
{
    $raw = (string) Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->where('setting', 'encryption_key')
        ->value('value');
    $raw = trim($raw);
    if ($raw === '') {
        $raw = 'cloudstorage-media-build-token-fallback';
    }
    return hash('sha256', $raw, true);
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    respondError('auth_required', 'Session timeout', 401);
}
$clientId = (int) $ca->getUserID();
$body = getBodyJson();
$sourceAgentId = isset($_POST['source_agent_id']) ? (int) $_POST['source_agent_id'] : (int) ($body['source_agent_id'] ?? 0);
$mode = RecoveryMediaBundleService::normalizeMode((string) ($_POST['mode'] ?? ($body['mode'] ?? 'fast')));
$ttlMinutes = isset($_POST['ttl_minutes']) ? (int) $_POST['ttl_minutes'] : (int) ($body['ttl_minutes'] ?? 30);
if ($ttlMinutes <= 0 || $ttlMinutes > 120) {
    $ttlMinutes = 30;
}
if ($sourceAgentId <= 0) {
    respondError('invalid_request', 'source_agent_id is required', 400);
}

$sourceAgent = Capsule::table('s3_cloudbackup_agents')
    ->where('id', $sourceAgentId)
    ->where('client_id', $clientId)
    ->first();
if (!$sourceAgent) {
    respondError('not_found', 'Source agent not found', 404);
}

// MSP guard: ensure tenant access when tenant-scoped.
if (MspController::isMspClient($clientId) && !empty($sourceAgent->tenant_id)) {
    $tenant = MspController::getTenant((int) $sourceAgent->tenant_id, $clientId);
    if (!$tenant) {
        respondError('access_denied', 'Tenant not found or access denied', 403);
    }
}

$iat = time();
$exp = $iat + ($ttlMinutes * 60);
$payload = [
    'typ' => 'media_build',
    'iss' => 'cloudstorage',
    'client_id' => $clientId,
    'source_agent_id' => $sourceAgentId,
    'mode' => $mode,
    'iat' => $iat,
    'exp' => $exp,
    'jti' => bin2hex(random_bytes(8)),
];
$header = ['alg' => 'HS256', 'typ' => 'JWT'];

$headerB64 = base64urlEncode(json_encode($header));
$payloadB64 = base64urlEncode(json_encode($payload));
$signingInput = $headerB64 . '.' . $payloadB64;
$sig = hash_hmac('sha256', $signingInput, getSigningKey(), true);
$token = $signingInput . '.' . base64urlEncode($sig);

respond([
    'status' => 'success',
    'token' => $token,
    'expires_at' => gmdate('Y-m-d H:i:s', $exp),
    'source_agent_id' => $sourceAgentId,
    'mode' => $mode,
]);

