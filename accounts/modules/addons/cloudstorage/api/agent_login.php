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
        'mode' => $data['mode'] ?? null,
        'message' => $data['message'] ?? null,
        'has_agent_uuid' => !empty($data['agent_uuid']),
        'has_agent_token' => !empty($data['agent_token']),
    ], 'H2');
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

function generateBackupUserPublicId(): string
{
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    try {
        $time = (int) floor(microtime(true) * 1000);
    } catch (\Throwable $__) {
        $time = (int) (time() * 1000);
    }
    $timeBytes = '';
    for ($i = 5; $i >= 0; $i--) {
        $timeBytes .= chr(($time >> ($i * 8)) & 0xFF);
    }
    try {
        $rand = random_bytes(10);
    } catch (\Throwable $__) {
        $rand = substr(hash('sha256', uniqid('', true), true), 0, 10);
    }
    $bin = $timeBytes . $rand;
    $bits = '';
    for ($i = 0; $i < 16; $i++) {
        $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    for ($i = 0; $i < 26; $i++) {
        $chunk = substr($bits, $i * 5, 5);
        if ($chunk === '') {
            $chunk = '00000';
        }
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $out;
}

function normalizeAutoBackupUsername(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9._-]+/', '-', $value);
    $value = trim((string) $value, '-._');
    if ($value === '') {
        $value = 'device';
    }
    if (strlen($value) < 3) {
        $value = str_pad($value, 3, 'x');
    }
    if (strlen($value) > 64) {
        $value = substr($value, 0, 64);
        $value = rtrim($value, '-._');
    }
    return $value !== '' ? $value : 'device';
}

function resolveClientIdByEmail(string $email): ?int
{
    $clientRow = Capsule::table('tblclients')
        ->whereRaw('LOWER(email) = LOWER(?)', [$email])
        ->first();
    if ($clientRow) {
        return (int) $clientRow->id;
    }

    $contact = Capsule::table('tblcontacts as c')
        ->join('tblclients as cl', 'c.userid', '=', 'cl.id')
        ->whereRaw('LOWER(c.email) = LOWER(?)', [$email])
        ->first(['cl.id']);
    if ($contact) {
        return (int) $contact->id;
    }

    return null;
}

function ensureClientHasBackupProduct(int $clientId): void
{
    $product = DBController::getProduct($clientId, ProductConfig::$E3_PRODUCT_ID);
    if (is_null($product) || is_null($product->username)) {
        respond(['status' => 'fail', 'message' => 'No active e3 Cloud Backup product'], 403);
    }
}

function ensureAgentLoginSessionStorage(): bool
{
    try {
        if (!Capsule::schema()->hasTable('s3_agent_login_sessions')) {
            Capsule::schema()->create('s3_agent_login_sessions', function ($table) {
                $table->increments('id');
                $table->string('session_token', 64);
                $table->unsignedInteger('client_id');
                $table->string('hostname', 255)->nullable();
                $table->string('device_id', 128)->nullable();
                $table->string('install_id', 128)->nullable();
                $table->string('device_name', 255)->nullable();
                $table->string('agent_version', 64)->nullable();
                $table->string('agent_os', 32)->nullable();
                $table->string('agent_arch', 32)->nullable();
                $table->string('agent_build', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->dateTime('expires_at');
                $table->dateTime('consumed_at')->nullable();
                $table->unique('session_token');
                $table->index('client_id');
                $table->index('expires_at');
                $table->index('consumed_at');
            });
            logModuleCall('cloudstorage', 'agent_login_runtime_schema', [], 'Created s3_agent_login_sessions table', [], []);
        }

        $columnDefs = [
            'session_token' => function ($table) { $table->string('session_token', 64); },
            'client_id' => function ($table) { $table->unsignedInteger('client_id'); },
            'hostname' => function ($table) { $table->string('hostname', 255)->nullable(); },
            'device_id' => function ($table) { $table->string('device_id', 128)->nullable(); },
            'install_id' => function ($table) { $table->string('install_id', 128)->nullable(); },
            'device_name' => function ($table) { $table->string('device_name', 255)->nullable(); },
            'agent_version' => function ($table) { $table->string('agent_version', 64)->nullable(); },
            'agent_os' => function ($table) { $table->string('agent_os', 32)->nullable(); },
            'agent_arch' => function ($table) { $table->string('agent_arch', 32)->nullable(); },
            'agent_build' => function ($table) { $table->string('agent_build', 64)->nullable(); },
            'created_at' => function ($table) { $table->timestamp('created_at')->useCurrent(); },
            'expires_at' => function ($table) { $table->dateTime('expires_at'); },
            'consumed_at' => function ($table) { $table->dateTime('consumed_at')->nullable(); },
        ];

        foreach ($columnDefs as $column => $adder) {
            if (!Capsule::schema()->hasColumn('s3_agent_login_sessions', $column)) {
                Capsule::schema()->table('s3_agent_login_sessions', function ($table) use ($adder) {
                    $adder($table);
                });
            }
        }

        try {
            Capsule::schema()->table('s3_agent_login_sessions', function ($table) {
                $table->unique('session_token');
            });
        } catch (\Throwable $__) {
        }
        try {
            Capsule::schema()->table('s3_agent_login_sessions', function ($table) {
                $table->index('client_id');
            });
        } catch (\Throwable $__) {
        }
        try {
            Capsule::schema()->table('s3_agent_login_sessions', function ($table) {
                $table->index('expires_at');
            });
        } catch (\Throwable $__) {
        }
        try {
            Capsule::schema()->table('s3_agent_login_sessions', function ($table) {
                $table->index('consumed_at');
            });
        } catch (\Throwable $__) {
        }

        return true;
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'agent_login_runtime_schema_fail', [], $e->getMessage(), [], []);
        return false;
    }
}

function formatBackupUserForResponse(object $user, bool $hasPublicId): array
{
    return [
        'id' => (int) $user->id,
        'public_id' => $hasPublicId ? (string) ($user->public_id ?? '') : '',
        'username' => (string) ($user->username ?? ''),
        'email' => (string) ($user->email ?? ''),
        'backup_type' => (string) ($user->backup_type ?? 'both'),
        'tenant_id' => !empty($user->tenant_id) ? (int) $user->tenant_id : null,
    ];
}

function loadEligibleBackupUsers(int $clientId): array
{
    if (!Capsule::schema()->hasTable('s3_backup_users')) {
        return [];
    }

    $hasBackupType = Capsule::schema()->hasColumn('s3_backup_users', 'backup_type');
    $hasPublicId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');

    $select = ['id', 'username', 'email', 'tenant_id'];
    if ($hasBackupType) {
        $select[] = 'backup_type';
    }
    if ($hasPublicId) {
        $select[] = 'public_id';
    }

    $query = Capsule::table('s3_backup_users')
        ->where('client_id', $clientId)
        ->where('status', 'active');
    if ($hasBackupType) {
        $query->whereIn('backup_type', ['local', 'both']);
    }

    $rows = $query->orderBy('username', 'asc')->get($select);
    $out = [];
    foreach ($rows as $row) {
        $out[] = formatBackupUserForResponse($row, $hasPublicId);
    }

    return $out;
}

function createAutoBackupUser(int $clientId, object $session): object
{
    $client = Capsule::table('tblclients')
        ->where('id', $clientId)
        ->first(['email']);
    if (!$client) {
        respond(['status' => 'fail', 'message' => 'Client not found for auto-created backup user'], 404);
    }

    $candidate = trim((string) ($session->device_name ?? ''));
    if ($candidate === '') {
        $candidate = trim((string) ($session->hostname ?? ''));
    }
    if ($candidate === '') {
        $clientEmail = (string) ($client->email ?? '');
        $candidate = strstr($clientEmail, '@', true) ?: $clientEmail;
    }

    $baseUsername = normalizeAutoBackupUsername($candidate);
    $username = $baseUsername;
    for ($attempt = 2; $attempt <= 200; $attempt++) {
        $exists = Capsule::table('s3_backup_users')
            ->where('client_id', $clientId)
            ->whereNull('tenant_id')
            ->where('username', $username)
            ->exists();
        if (!$exists) {
            break;
        }
        $username = normalizeAutoBackupUsername(substr($baseUsername, 0, 58) . '-' . $attempt);
    }

    $password = bin2hex(random_bytes(32));
    $hasPublicId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
    $hasBackupType = Capsule::schema()->hasColumn('s3_backup_users', 'backup_type');

    $insert = [
        'client_id' => $clientId,
        'tenant_id' => null,
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'email' => strtolower(trim((string) ($client->email ?? ''))),
        'status' => 'active',
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ];
    if ($hasPublicId) {
        $insert['public_id'] = generateBackupUserPublicId();
    }
    if ($hasBackupType) {
        $insert['backup_type'] = 'local';
    }

    $userId = (int) Capsule::table('s3_backup_users')->insertGetId($insert);
    $select = ['id', 'username', 'email', 'tenant_id', 'status'];
    if ($hasBackupType) {
        $select[] = 'backup_type';
    }
    if ($hasPublicId) {
        $select[] = 'public_id';
    }

    $user = Capsule::table('s3_backup_users')->where('id', $userId)->first($select);
    if (!$user) {
        respond(['status' => 'fail', 'message' => 'Failed to load auto-created backup user'], 500);
    }

    return $user;
}

$mode = strtolower(trim((string) ($_POST['mode'] ?? '')));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$hostname = trim((string) ($_POST['hostname'] ?? ''));
$deviceId = trim((string) ($_POST['device_id'] ?? ''));
$installId = trim((string) ($_POST['install_id'] ?? ''));
$deviceName = trim((string) ($_POST['device_name'] ?? ''));
$agentVersion = trim((string) ($_POST['agent_version'] ?? ''));
$agentOs = trim((string) ($_POST['agent_os'] ?? ''));
$agentArch = trim((string) ($_POST['agent_arch'] ?? ''));
$agentBuild = trim((string) ($_POST['agent_build'] ?? ''));
$sessionToken = trim((string) ($_POST['session_token'] ?? ''));
$backupUserIdRaw = trim((string) ($_POST['backup_user_id'] ?? ''));
$autoCreateUser = filter_var($_POST['auto_create_user'] ?? false, FILTER_VALIDATE_BOOLEAN);

debugLog('agent_login_request', [
    'mode' => $mode,
    'has_email' => $email !== '',
    'has_password' => $password !== '',
    'has_hostname' => $hostname !== '',
    'has_device_id' => $deviceId !== '',
    'has_install_id' => $installId !== '',
    'has_device_name' => $deviceName !== '',
    'has_agent_version' => $agentVersion !== '',
    'has_agent_os' => $agentOs !== '',
    'has_agent_arch' => $agentArch !== '',
    'has_agent_build' => $agentBuild !== '',
    'has_session_token' => $sessionToken !== '',
    'has_backup_user_id' => $backupUserIdRaw !== '',
    'auto_create_user' => $autoCreateUser,
    'host' => $_SERVER['HTTP_HOST'] ?? '',
    'forwarded_proto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '',
    'https' => $_SERVER['HTTPS'] ?? '',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
], 'H1');

if (!ensureAgentLoginSessionStorage()) {
    respond(['status' => 'fail', 'message' => 'Agent login session storage is not available'], 500);
}

if ($mode === 'authenticate') {
    if ($email === '' || $password === '' || $hostname === '') {
        respond(['status' => 'fail', 'message' => 'Missing email, password, or hostname'], 400);
    }

    $loginResult = localAPI('ValidateLogin', [
        'email' => $email,
        'password2' => $password,
    ]);
    if (($loginResult['result'] ?? '') !== 'success') {
        respond(['status' => 'fail', 'message' => 'Invalid credentials'], 401);
    }

    $clientId = resolveClientIdByEmail($email);
    if (!$clientId) {
        respond(['status' => 'fail', 'message' => 'Client not found for this email'], 403);
    }

    ensureClientHasBackupProduct($clientId);

    try {
        $users = loadEligibleBackupUsers($clientId);
        $newSessionToken = bin2hex(random_bytes(32));
        Capsule::table('s3_agent_login_sessions')->insert([
            'session_token' => $newSessionToken,
            'client_id' => $clientId,
            'hostname' => $hostname !== '' ? $hostname : null,
            'device_id' => $deviceId !== '' ? $deviceId : null,
            'install_id' => $installId !== '' ? $installId : null,
            'device_name' => $deviceName !== '' ? $deviceName : null,
            'agent_version' => $agentVersion !== '' ? $agentVersion : null,
            'agent_os' => $agentOs !== '' ? $agentOs : null,
            'agent_arch' => $agentArch !== '' ? $agentArch : null,
            'agent_build' => $agentBuild !== '' ? $agentBuild : null,
            'created_at' => Capsule::raw('NOW()'),
            'expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);

        respond([
            'status' => 'success',
            'mode' => 'authenticate',
            'client_id' => (string) $clientId,
            'session_token' => $newSessionToken,
            'users' => $users,
            'message' => 'Authentication successful',
        ], 200);
    } catch (\Throwable $e) {
        logModuleCall('cloudstorage', 'agent_login_authenticate', [
            'client_id' => $clientId,
            'hostname' => $hostname,
        ], $e->getMessage());
        respond(['status' => 'fail', 'message' => 'Authentication failed'], 500);
    }
}

if ($mode !== 'complete') {
    respond(['status' => 'fail', 'message' => 'Invalid mode'], 400);
}

if ($sessionToken === '') {
    respond(['status' => 'fail', 'message' => 'Missing session token'], 400);
}

try {
    $hasPublicId = Capsule::schema()->hasColumn('s3_backup_users', 'public_id');
    $hasBackupType = Capsule::schema()->hasColumn('s3_backup_users', 'backup_type');
    $hasAgentBackupUserId = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'backup_user_id');
    $hasAgentVersion = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_version');
    $hasAgentOs = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_os');
    $hasAgentArch = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_arch');
    $hasAgentBuild = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'agent_build');
    $hasMetadataUpdatedAt = Capsule::schema()->hasColumn('s3_cloudbackup_agents', 'metadata_updated_at');

    $result = Capsule::connection()->transaction(function () use (
        $sessionToken,
        $backupUserIdRaw,
        $autoCreateUser,
        $hasPublicId,
        $hasBackupType,
        $hasAgentBackupUserId,
        $hasAgentVersion,
        $hasAgentOs,
        $hasAgentArch,
        $hasAgentBuild,
        $hasMetadataUpdatedAt
    ) {
        $session = Capsule::table('s3_agent_login_sessions')
            ->where('session_token', $sessionToken)
            ->lockForUpdate()
            ->first();
        if (!$session) {
            respond(['status' => 'fail', 'message' => 'Enrollment session not found'], 404);
        }
        if (!empty($session->consumed_at)) {
            respond(['status' => 'fail', 'message' => 'Enrollment session has already been used'], 409);
        }
        if (!empty($session->expires_at) && strtotime((string) $session->expires_at) <= time()) {
            respond(['status' => 'fail', 'message' => 'Enrollment session expired'], 401);
        }

        $clientId = (int) $session->client_id;
        ensureClientHasBackupProduct($clientId);

        $backupUser = null;
        if ($autoCreateUser) {
            $backupUser = createAutoBackupUser($clientId, $session);
        } else {
            if ($backupUserIdRaw === '' || !ctype_digit($backupUserIdRaw)) {
                respond(['status' => 'fail', 'message' => 'A backup user must be selected'], 400);
            }

            $select = ['id', 'username', 'email', 'tenant_id', 'status'];
            if ($hasBackupType) {
                $select[] = 'backup_type';
            }
            if ($hasPublicId) {
                $select[] = 'public_id';
            }

            $backupUser = Capsule::table('s3_backup_users')
                ->where('id', (int) $backupUserIdRaw)
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first($select);
            if (!$backupUser) {
                respond(['status' => 'fail', 'message' => 'Backup user not found'], 404);
            }
            if ($hasBackupType && (($backupUser->backup_type ?? 'both') === 'cloud_only')) {
                respond(['status' => 'fail', 'message' => 'Selected user is not enabled for local agent backups'], 400);
            }
        }

        $tenantId = !empty($backupUser->tenant_id) ? (int) $backupUser->tenant_id : null;
        $agentToken = bin2hex(random_bytes(20));
        $deviceId = trim((string) ($session->device_id ?? ''));
        $installId = trim((string) ($session->install_id ?? ''));
        $deviceName = trim((string) ($session->device_name ?? ''));
        $hostname = trim((string) ($session->hostname ?? ''));
        $agentVersion = trim((string) ($session->agent_version ?? ''));
        $agentOs = trim((string) ($session->agent_os ?? ''));
        $agentArch = trim((string) ($session->agent_arch ?? ''));
        $agentBuild = trim((string) ($session->agent_build ?? ''));

        $existing = null;
        if ($deviceId !== '') {
            $existing = Capsule::table('s3_cloudbackup_agents')
                ->where('client_id', $clientId)
                ->where('device_id', $deviceId)
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();
        }

        if ($existing) {
            $agentUuid = trim((string) ($existing->agent_uuid ?? ''));
            if ($agentUuid === '') {
                $agentUuid = generateAgentUuid();
            }

            $update = [
                'agent_uuid' => $agentUuid,
                'tenant_id' => $tenantId,
                'hostname' => $hostname,
                'device_name' => $deviceName !== '' ? $deviceName : ($existing->device_name ?? null),
                'install_id' => $installId !== '' ? $installId : ($existing->install_id ?? null),
                'status' => 'active',
                'agent_token' => $agentToken,
                'last_seen_at' => Capsule::raw('NOW()'),
                'updated_at' => Capsule::raw('NOW()'),
            ];
            if ($hasAgentBackupUserId) {
                $update['backup_user_id'] = (int) $backupUser->id;
            }
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
                ->where('id', (int) $existing->id)
                ->update($update);
        } else {
            $agentUuid = generateAgentUuid();
            $insert = [
                'agent_uuid' => $agentUuid,
                'client_id' => $clientId,
                'tenant_id' => $tenantId,
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
            if ($hasAgentBackupUserId) {
                $insert['backup_user_id'] = (int) $backupUser->id;
            }
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
            Capsule::table('s3_cloudbackup_agents')->insertGetId($insert);
        }

        return [
            'session_id' => (int) $session->id,
            'client_id' => $clientId,
            'agent_uuid' => $agentUuid,
            'agent_token' => $agentToken,
            'user' => formatBackupUserForResponse($backupUser, $hasPublicId),
        ];
    });

    $destResult = CloudBackupBootstrapService::ensureAgentDestination((string) $result['agent_uuid']);
    if (($destResult['status'] ?? 'fail') !== 'success') {
        logModuleCall('cloudstorage', 'agent_login_ensure_destination_failed', [
            'agent_uuid' => $result['agent_uuid'],
            'client_id' => $result['client_id'],
        ], $destResult);
        respond([
            'status' => 'fail',
            'message' => $destResult['message'] ?? 'Failed to initialize agent destination',
        ], 500);
    }

    Capsule::table('s3_agent_login_sessions')
        ->where('id', (int) $result['session_id'])
        ->whereNull('consumed_at')
        ->update(['consumed_at' => Capsule::raw('NOW()')]);

    $systemUrl = rtrim(detectBaseUrl(), '/');
    respond([
        'status' => 'success',
        'mode' => 'complete',
        'agent_uuid' => $result['agent_uuid'],
        'client_id' => (string) $result['client_id'],
        'agent_token' => $result['agent_token'],
        'api_base_url' => $systemUrl . '/modules/addons/cloudstorage/api',
        'user' => $result['user'],
        'message' => 'Agent enrolled successfully',
    ], 200);
} catch (\Throwable $e) {
    logModuleCall('cloudstorage', 'agent_login_complete', [
        'mode' => $mode,
        'session_token' => substr($sessionToken, 0, 12),
    ], $e->getMessage());
    respond(['status' => 'fail', 'message' => 'Enrollment failed'], 500);
}

