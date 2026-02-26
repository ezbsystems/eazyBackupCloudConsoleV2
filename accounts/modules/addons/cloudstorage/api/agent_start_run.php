<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupController;
use WHMCS\Module\Addon\CloudStorage\Client\HelperController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    $response = new JsonResponse($data, $httpCode);
    $response->send();
    exit;
}

function authenticateAgent(): object
{
    $agentUuid = $_SERVER['HTTP_X_AGENT_UUID'] ?? ($_POST['agent_uuid'] ?? null);
    $agentToken = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? ($_POST['agent_token'] ?? null);
    if (!$agentUuid || !$agentToken) {
        respond(['status' => 'fail', 'message' => 'Missing agent headers'], 401);
    }

    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->first();

    if (!$agent || $agent->status !== 'active' || $agent->agent_token !== $agentToken) {
        respond(['status' => 'fail', 'message' => 'Unauthorized'], 401);
    }

    Capsule::table('s3_cloudbackup_agents')
        ->where('agent_uuid', $agentUuid)
        ->update(['last_seen_at' => Capsule::raw('NOW()')]);

    return $agent;
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

$body = getBodyJson();
$jobId = $_POST['job_id'] ?? ($body['job_id'] ?? null);
if (!$jobId) {
    respond(['status' => 'fail', 'message' => 'Job ID is required'], 400);
}

$agent = authenticateAgent();

$result = CloudBackupController::startRun($jobId, $agent->client_id, 'agent');

if (isset($result['status']) && $result['status'] === 'success') {
    // Fetch job and destination details to return to the agent
    $job = Capsule::table('s3_cloudbackup_jobs')->where('id', $jobId)->first();
    $bucket = $job ? Capsule::table('s3_buckets')->where('id', $job->dest_bucket_id)->first() : null;
    $keys = $job ? Capsule::table('s3_user_access_keys')
        ->where('user_id', $job->s3_user_id ?? 0)
        ->orderByDesc('id')
        ->first() : null;

    // Addon settings for endpoint/region
    $settings = Capsule::table('tbladdonmodules')
        ->where('module', 'cloudstorage')
        ->pluck('value', 'setting');
    $settingsMap = [];
    foreach ($settings as $k => $v) {
        $settingsMap[$k] = $v;
    }

    $result['source_path'] = $job->source_path ?? '';
    $result['dest_bucket_name'] = $bucket->name ?? '';
    $result['dest_prefix'] = $job->dest_prefix ?? '';
    $agentEndpoint = $settingsMap['cloudbackup_agent_s3_endpoint'] ?? '';
    if (empty($agentEndpoint)) {
        $agentEndpoint = $settingsMap['s3_endpoint'] ?? '';
    }
    if (array_key_exists('cloudbackup_agent_s3_region', $settingsMap)) {
        $agentRegion = $settingsMap['cloudbackup_agent_s3_region'];
    } else {
        $agentRegion = $settingsMap['s3_region'] ?? '';
    }
    $keyUserId = $job->s3_user_id ?? 0;
    $keyRowId = $keys->id ?? null;
    $encKeyPrimary = $settingsMap['cloudbackup_encryption_key'] ?? '';
    $encKeySecondary = $settingsMap['encryption_key'] ?? '';
    $usedEncKey = '';
    $accessKeyRaw = $keys->access_key ?? '';
    $secretKeyRaw = $keys->secret_key ?? '';
    $decryptWith = function (?string $key) use ($accessKeyRaw, $secretKeyRaw) {
        $ak = $accessKeyRaw;
        $sk = $secretKeyRaw;
        if ($key && $ak) {
            $ak = HelperController::decryptKey($ak, $key);
        }
        if ($key && $sk) {
            $sk = HelperController::decryptKey($sk, $key);
        }
        return [
            is_string($ak) ? $ak : '',
            is_string($sk) ? $sk : '',
        ];
    };

    [$decAkPrimary, $decSkPrimary] = $decryptWith($encKeyPrimary);
    [$decAkSecondary, $decSkSecondary] = $decryptWith($encKeySecondary);

    $accessKeyRawDec = $decAkPrimary;
    $secretKeyRawDec = $decSkPrimary;
    if ($accessKeyRawDec !== '' && $secretKeyRawDec !== '') {
        $usedEncKey = 'cloudbackup_encryption_key';
    } elseif ($decAkSecondary !== '' && $decSkSecondary !== '') {
        $accessKeyRawDec = $decAkSecondary;
        $secretKeyRawDec = $decSkSecondary;
        $usedEncKey = 'encryption_key';
    } else {
        // partial or failed decrypt
        if ($decAkPrimary !== '' || $decSkPrimary !== '') {
            $usedEncKey = 'cloudbackup_encryption_key_partial';
        } elseif ($decAkSecondary !== '' || $decSkSecondary !== '') {
            $usedEncKey = 'encryption_key_partial';
        }
    }

    $accessKeyRawDec = is_string($accessKeyRawDec) ? $accessKeyRawDec : '';
    $secretKeyRawDec = is_string($secretKeyRawDec) ? $secretKeyRawDec : '';

    $result['dest_access_key'] = $accessKeyRawDec;
    $result['dest_secret_key'] = $secretKeyRawDec;
    $result['dest_endpoint'] = $agentEndpoint;
    $result['dest_region'] = $agentRegion;
    $result['debug'] = [
        'settings_keys' => array_keys($settingsMap),
        'agent_endpoint' => $agentEndpoint,
        'agent_region' => $agentRegion,
        'bucket' => $result['dest_bucket_name'] ?? '',
        'prefix' => $result['dest_prefix'] ?? '',
        'has_access_key' => !empty($result['dest_access_key'] ?? ''),
        'has_secret_key' => !empty($result['dest_secret_key'] ?? ''),
        'access_key_suffix' => $accessKeyRawDec ? substr($accessKeyRawDec, -4) : '',
        'access_key_len' => strlen($accessKeyRawDec),
        'secret_key_len' => strlen($secretKeyRawDec),
        'key_user_id' => $keyUserId,
        'key_row_id' => $keyRowId,
        'used_encryption_key' => $usedEncKey,
        'enc_key_present' => !empty($encKeyPrimary) || !empty($encKeySecondary),
    ];
    $result['dest_access_key'] = $keys->access_key ?? '';
    $result['dest_secret_key'] = $keys->secret_key ?? '';
}

respond($result);

