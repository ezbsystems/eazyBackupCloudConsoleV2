<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
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

function base64urlDecode(string $input): string
{
    $padLen = 4 - (strlen($input) % 4);
    if ($padLen < 4) {
        $input .= str_repeat('=', $padLen);
    }
    $decoded = base64_decode(strtr($input, '-_', '+/'), true);
    return $decoded === false ? '' : $decoded;
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

function parseAndVerifyToken(string $token): array
{
    $parts = explode('.', trim($token));
    if (count($parts) !== 3) {
        throw new \RuntimeException('invalid token format');
    }
    [$headerB64, $payloadB64, $sigB64] = $parts;
    $headerJson = base64urlDecode($headerB64);
    $payloadJson = base64urlDecode($payloadB64);
    $sigRaw = base64urlDecode($sigB64);
    if ($headerJson === '' || $payloadJson === '' || $sigRaw === '') {
        throw new \RuntimeException('invalid token encoding');
    }
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) {
        throw new \RuntimeException('invalid token payload');
    }
    if (($header['alg'] ?? '') !== 'HS256') {
        throw new \RuntimeException('unsupported token algorithm');
    }
    $expected = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, getSigningKey(), true);
    if (!hash_equals($expected, $sigRaw)) {
        throw new \RuntimeException('invalid token signature');
    }
    $now = time();
    $exp = (int) ($payload['exp'] ?? 0);
    if ($exp <= 0 || $now >= $exp) {
        throw new \RuntimeException('token expired');
    }
    if (($payload['typ'] ?? '') !== 'media_build') {
        throw new \RuntimeException('invalid token type');
    }
    return $payload;
}

$body = getBodyJson();
$token = trim((string) ($_POST['token'] ?? ($body['token'] ?? '')));
if ($token === '') {
    respondError('invalid_request', 'token is required', 400);
}

try {
    $claims = parseAndVerifyToken($token);
} catch (\Throwable $e) {
    respondError('invalid_token', 'Invalid or expired media build token', 403);
}

$clientId = (int) ($claims['client_id'] ?? 0);
$sourceAgentUuid = trim((string) ($claims['source_agent_uuid'] ?? ''));
$mode = RecoveryMediaBundleService::normalizeMode((string) ($claims['mode'] ?? 'fast'));
if ($clientId <= 0 || $sourceAgentUuid === '') {
    respondError('invalid_token', 'Token payload is incomplete', 403);
}

$agent = Capsule::table('s3_cloudbackup_agents')
    ->where('agent_uuid', $sourceAgentUuid)
    ->where('client_id', $clientId)
    ->first();
if (!$agent) {
    respondError('not_found', 'Source agent not found', 404);
}
$sourceAgentId = (int) ($agent->id ?? 0);
if ($sourceAgentId <= 0) {
    respondError('not_found', 'Source agent not found', 404);
}

try {
    $selection = RecoveryMediaBundleService::resolveMediaBuildSelection($clientId, $sourceAgentId, $mode);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'cloudbackup_media_build_token_exchange', [
        'client_id' => $clientId,
        'source_agent_uuid' => $sourceAgentUuid,
        'mode' => $mode,
    ], $e->getMessage());
    respondError('server_error', 'Failed to resolve media build manifest', 500);
}

respond([
    'status' => 'success',
    'manifest' => [
        'mode' => $selection['mode'],
        'source_agent_uuid' => $sourceAgentUuid,
        'source_agent_hostname' => (string) ($agent->hostname ?? ''),
        'base_iso_url' => $selection['base_iso_url'],
        'base_iso_sha256' => $selection['base_iso_sha256'],
        'source_bundle_url' => $selection['selected_bundle_url'],
        'source_bundle_sha256' => $selection['selected_bundle_sha256'],
        'source_bundle_profile' => $selection['selected_bundle_profile'],
        'broad_extras_url' => $selection['broad_extras_url'],
        'broad_extras_sha256' => $selection['broad_extras_sha256'],
        'has_source_bundle' => (bool) $selection['has_source_bundle'],
        'warning' => $selection['warning'],
    ],
]);

