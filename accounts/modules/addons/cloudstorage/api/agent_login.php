<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/CloudBackupBootstrapService.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\CloudBackupBootstrapService;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// #region agent log
function debugLog(string $message, array $data, string $hypothesisId): void
{
    $entry = [
        'id' => uniqid('log_', true),
        'timestamp' => (int) round(microtime(true) * 1000),
        'location' => 'agent_login.php:debug',
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
    debugLog('agent_login_output_state', [
        'headers_sent' => $headersSent,
        'headers_file' => $headersSent ? $headersFile : '',
        'headers_line' => $headersSent ? $headersLine : 0,
        'ob_level' => $obLevel,
        'ob_len' => $obLen === false ? null : $obLen,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    ], 'H5');
    debugLog('agent_login_response', [
        'http_code' => $httpCode,
        'status' => $data['status'] ?? null,
        'message' => $data['message'] ?? null,
        'has_agent_uuid' => !empty($data['agent_uuid']),
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

function generateAgentUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$hostname = trim($_POST['hostname'] ?? '');
$deviceId = trim($_POST['device_id'] ?? '');
$installId = trim($_POST['install_id'] ?? '');
$deviceName = trim($_POST['device_name'] ?? '');
$agentVersion = trim($_POST['agent_version'] ?? '');
$agentOs = trim($_POST['agent_os'] ?? '');
$agentArch = trim($_POST['agent_arch'] ?? '');
$agentBuild = trim($_POST['agent_build'] ?? '');

// #region agent log
debugLog('agent_login_request', [
    'has_email' => $email !== '',
    'email_len' => $email !== '' ? strlen($email) : 0,
    'has_password' => $password !== '',
    'password_len' => $password !== '' ? strlen($password) : 0,
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

if ($email === '' || $password === '' || $hostname === '') {
    respond(['status' => 'fail', 'message' => 'Missing email, password, or hostname'], 400);
}

// Validate credentials via WHMCS localAPI
$loginResult = localAPI('ValidateLogin', [
    'email' => $email,
    'password2' => $password,
]);

if (($loginResult['result'] ?? '') !== 'success') {
    respond(['status' => 'fail', 'message' => 'Invalid credentials'], 401);
}

// Resolve client_id from email (client owner preferred, then contact)
$clientId = null;
$clientRow = Capsule::table('tblclients')
    ->whereRaw('LOWER(email) = LOWER(?)', [$email])
    ->first();

if ($clientRow) {
    $clientId = (int) $clientRow->id;
} else {
    $contact = Capsule::table('tblcontacts as c')
        ->join('tblclients as cl', 'c.userid', '=', 'cl.id')
        ->whereRaw('LOWER(c.email) = LOWER(?)', [$email])
        ->first(['cl.id']);
    if ($contact) {
        $clientId = (int) $contact->id;
    }
}

if (!$clientId) {
    respond(['status' => 'fail', 'message' => 'Client not found for this email'], 403);
}

// Optional: ensure client has active e3 Cloud Backup product
$product = DBController::getProduct($clientId, ProductConfig::$E3_PRODUCT_ID);
if (is_null($product) || is_null($product->username)) {
    respond(['status' => 'fail', 'message' => 'No active e3 Cloud Backup product'], 403);
}

try {
    $hasAgentVersion = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_version');
    $hasAgentOs = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_os');
    $hasAgentArch = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_arch');
    $hasAgentBuild = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_build');
    $hasMetadataUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'metadata_updated_at');

    $result = Capsule::connection()->transaction(function () use ($clientId, $hostname, $deviceId, $installId, $deviceName, $agentVersion, $agentOs, $agentArch, $agentBuild, $hasAgentVersion, $hasAgentOs, $hasAgentArch, $hasAgentBuild, $hasMetadataUpdatedAt) {
        $agentToken = bin2hex(random_bytes(20)); // 40 hex chars

        // If device_id is provided, attempt to reuse/rekey an existing agent for this client.
        $agentId = null;
        if ($deviceId !== '') {
            $existing = Capsule::table('s3_cloudbackup_agents')
                ->where('client_id', $clientId)
                ->whereNull('tenant_id')
                ->where('device_id', $deviceId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $agentUuid = trim((string) ($existing->agent_uuid ?? ''));
                if ($agentUuid === '') {
                    $agentUuid = generateAgentUuid();
                }
                $update = [
                    'agent_uuid' => $agentUuid,
                    'agent_token' => $agentToken,
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
            }
        }

        if (!$agentId) {
            $agentUuid = generateAgentUuid();
            $insert = [
                'agent_uuid' => $agentUuid,
                'client_id' => $clientId,
                'tenant_id' => null,
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

        return [
            'agent_uuid' => $agentUuid,
            'agent_token' => $agentToken,
        ];
    });

    $destResult = CloudBackupBootstrapService::ensureAgentDestination((string) $result['agent_uuid']);
    if (($destResult['status'] ?? 'fail') !== 'success') {
        logModuleCall('cloudstorage', 'agent_login_ensure_destination_failed', [
            'agent_uuid' => $result['agent_uuid'],
            'client_id' => $clientId,
        ], $destResult);
        respond([
            'status' => 'fail',
            'message' => $destResult['message'] ?? 'Failed to initialize agent destination',
        ], 500);
    }

    $systemUrl = rtrim(detectBaseUrl(), '/');

    respond([
        'status' => 'success',
        'agent_uuid' => $result['agent_uuid'],
        'client_id' => (string) $clientId,
        'agent_token' => $result['agent_token'],
        'api_base_url' => $systemUrl . '/modules/addons/cloudstorage/api',
        'message' => 'Agent enrolled successfully',
    ], 200);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Enrollment failed'], 500);
}

