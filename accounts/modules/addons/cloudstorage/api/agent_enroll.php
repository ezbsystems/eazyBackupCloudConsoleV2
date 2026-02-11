<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// #region agent log
function debugLog(string $message, array $data, string $hypothesisId): void
{
    $entry = [
        'id' => uniqid('log_', true),
        'timestamp' => (int) round(microtime(true) * 1000),
        'location' => 'agent_enroll.php:debug',
        'message' => $message,
        'data' => $data,
        'runId' => 'enroll',
        'hypothesisId' => $hypothesisId,
    ];
    @file_put_contents('/var/www/eazybackup.ca/.cursor/debug.log', json_encode($entry) . PHP_EOL, FILE_APPEND);
}
// #endregion

function respond(array $data, int $httpCode = 200): void
{
    // #region agent log
    $headersFile = '';
    $headersLine = 0;
    $headersSent = headers_sent($headersFile, $headersLine);
    $obLevel = ob_get_level();
    $obLen = ob_get_length();
    debugLog('agent_enroll_output_state', [
        'headers_sent' => $headersSent,
        'headers_file' => $headersSent ? $headersFile : '',
        'headers_line' => $headersSent ? $headersLine : 0,
        'ob_level' => $obLevel,
        'ob_len' => $obLen === false ? null : $obLen,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    ], 'H5');
    debugLog('agent_enroll_response', [
        'http_code' => $httpCode,
        'status' => $data['status'] ?? null,
        'message' => $data['message'] ?? null,
        'has_agent_id' => !empty($data['agent_id']),
        'has_agent_token' => !empty($data['agent_token']),
    ], 'H2');
    // #endregion
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

function detectBaseUrl(): string
{
    $systemUrl = rtrim(\WHMCS\Config\Setting::getValue('SystemURL'), '/');
    $scheme = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    }

    $host = '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
        $host = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
    } elseif (!empty($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
    }

    if ($host !== '') {
        return $scheme . '://' . $host;
    }

    return $systemUrl;
}

$token = trim($_POST['token'] ?? '');
$hostname = trim($_POST['hostname'] ?? '');
$deviceId = trim($_POST['device_id'] ?? '');
$installId = trim($_POST['install_id'] ?? '');
$deviceName = trim($_POST['device_name'] ?? '');
$agentVersion = trim($_POST['agent_version'] ?? '');
$agentOs = trim($_POST['agent_os'] ?? '');
$agentArch = trim($_POST['agent_arch'] ?? '');
$agentBuild = trim($_POST['agent_build'] ?? '');

// #region agent log
debugLog('agent_enroll_request', [
    'has_token' => $token !== '',
    'token_len' => $token !== '' ? strlen($token) : 0,
    'has_hostname' => $hostname !== '',
    'hostname_len' => $hostname !== '' ? strlen($hostname) : 0,
    'has_device_id' => $deviceId !== '',
    'has_install_id' => $installId !== '',
    'has_device_name' => $deviceName !== '',
    'has_agent_version' => $agentVersion !== '',
    'has_agent_os' => $agentOs !== '',
    'has_agent_arch' => $agentArch !== '',
    'has_agent_build' => $agentBuild !== '',
    'host' => $_SERVER['HTTP_HOST'] ?? '',
    'forwarded_proto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '',
    'https' => $_SERVER['HTTPS'] ?? '',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
], 'H1');
// #endregion

if ($token === '' || $hostname === '') {
    respond(['status' => 'fail', 'message' => 'Missing token or hostname'], 400);
}

// Basic token format validation (40 hex chars expected)
if (!ctype_xdigit($token) || strlen($token) < 32) {
    respond(['status' => 'fail', 'message' => 'Invalid token'], 401);
}

try {
    $hasAgentVersion = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_version');
    $hasAgentOs = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_os');
    $hasAgentArch = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_arch');
    $hasAgentBuild = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_build');
    $hasMetadataUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'metadata_updated_at');

    $result = Capsule::connection()->transaction(function () use ($token, $hostname, $deviceId, $installId, $deviceName, $agentVersion, $agentOs, $agentArch, $agentBuild, $hasAgentVersion, $hasAgentOs, $hasAgentArch, $hasAgentBuild, $hasMetadataUpdatedAt) {
        // Lock the token row to enforce max_uses and revoke/expiry checks atomically
        $tok = Capsule::table('s3_agent_enrollment_tokens')
            ->where('token', $token)
            ->lockForUpdate()
            ->first();

        if (!$tok) {
            respond(['status' => 'fail', 'message' => 'Invalid token'], 401);
        }

        // Revoked?
        if (!is_null($tok->revoked_at)) {
            respond(['status' => 'fail', 'message' => 'Token revoked'], 401);
        }

        // Expired?
        if (!is_null($tok->expires_at) && strtotime($tok->expires_at) <= time()) {
            respond(['status' => 'fail', 'message' => 'Token expired'], 401);
        }

        // Max uses?
        if ((int) $tok->max_uses > 0 && (int) $tok->use_count >= (int) $tok->max_uses) {
            respond(['status' => 'fail', 'message' => 'Token usage exhausted'], 401);
        }

        // Increment use_count atomically
        Capsule::table('s3_agent_enrollment_tokens')
            ->where('id', $tok->id)
            ->update([
                'use_count' => Capsule::raw('use_count + 1'),
                'updated_at' => Capsule::raw('NOW()'),
            ]);

        $agentToken = bin2hex(random_bytes(20)); // 40 hex chars

        // If device_id is provided, attempt to reuse/rekey an existing agent for this scope.
        $existing = null;
        if ($deviceId !== '') {
            $q = Capsule::table('s3_cloudbackup_agents')
                ->where('client_id', $tok->client_id)
                ->where('device_id', $deviceId);
            if (!empty($tok->tenant_id)) {
                $q->where('tenant_id', (int)$tok->tenant_id);
            } else {
                $q->whereNull('tenant_id');
            }
            $existing = $q->lockForUpdate()->first();
        }

        if ($existing) {
            $update = [
                'agent_token' => $agentToken,
                'enrollment_token_id' => $tok->id,
                'hostname' => $hostname,
                'device_name' => $deviceName !== '' ? $deviceName : ($existing->device_name ?? null),
                'install_id' => $installId !== '' ? $installId : ($existing->install_id ?? null),
                'status' => 'active',
                'last_seen_at' => Capsule::raw('NOW()'),
                'updated_at' => Capsule::raw('NOW()'),
            ];
            if ($hasAgentVersion) {
                $update['agent_version'] = $agentVersion !== '' ? $agentVersion : ($existing->agent_version ?? null);
            }
            if ($hasAgentOs) {
                $update['agent_os'] = $agentOs !== '' ? $agentOs : ($existing->agent_os ?? null);
            }
            if ($hasAgentArch) {
                $update['agent_arch'] = $agentArch !== '' ? $agentArch : ($existing->agent_arch ?? null);
            }
            if ($hasAgentBuild) {
                $update['agent_build'] = $agentBuild !== '' ? $agentBuild : ($existing->agent_build ?? null);
            }
            if ($hasMetadataUpdatedAt) {
                $update['metadata_updated_at'] = Capsule::raw('NOW()');
            }
            Capsule::table('s3_cloudbackup_agents')
                ->where('id', $existing->id)
                ->update($update);
            $agentId = (int)$existing->id;
        } else {
            $insert = [
                'client_id' => $tok->client_id,
                'tenant_id' => $tok->tenant_id,
                'enrollment_token_id' => $tok->id,
                'hostname' => $hostname,
                'device_id' => $deviceId !== '' ? $deviceId : null,
                'install_id' => $installId !== '' ? $installId : null,
                'device_name' => $deviceName !== '' ? $deviceName : null,
                'agent_type' => 'workstation',
                'status' => 'active',
                'agent_token' => $agentToken,
                'created_at' => Capsule::raw('NOW()'),
                'updated_at' => Capsule::raw('NOW()'),
            ];
            if ($hasAgentVersion) {
                $insert['agent_version'] = $agentVersion !== '' ? $agentVersion : null;
            }
            if ($hasAgentOs) {
                $insert['agent_os'] = $agentOs !== '' ? $agentOs : null;
            }
            if ($hasAgentArch) {
                $insert['agent_arch'] = $agentArch !== '' ? $agentArch : null;
            }
            if ($hasAgentBuild) {
                $insert['agent_build'] = $agentBuild !== '' ? $agentBuild : null;
            }
            if ($hasMetadataUpdatedAt) {
                $insert['metadata_updated_at'] = Capsule::raw('NOW()');
            }
            $agentId = Capsule::table('s3_cloudbackup_agents')->insertGetId($insert);
        }

        $systemUrl = rtrim(detectBaseUrl(), '/');

        return [
            'agent_id' => (string) $agentId,
            'client_id' => (string) $tok->client_id,
            'tenant_id' => $tok->tenant_id ? (int) $tok->tenant_id : null,
            'agent_token' => $agentToken,
            'api_base_url' => $systemUrl . '/modules/addons/cloudstorage/api',
        ];
    });

    respond([
        'status' => 'success',
        'agent_id' => $result['agent_id'],
        'client_id' => $result['client_id'],
        'tenant_id' => $result['tenant_id'],
        'agent_token' => $result['agent_token'],
        'api_base_url' => $result['api_base_url'],
        'message' => 'Agent enrolled successfully',
    ], 200);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Enrollment failed'], 500);
}

