<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

$token = trim($_POST['token'] ?? '');
$hostname = trim($_POST['hostname'] ?? '');
$deviceId = trim($_POST['device_id'] ?? '');
$installId = trim($_POST['install_id'] ?? '');
$deviceName = trim($_POST['device_name'] ?? '');

if ($token === '' || $hostname === '') {
    respond(['status' => 'fail', 'message' => 'Missing token or hostname'], 400);
}

// Basic token format validation (40 hex chars expected)
if (!ctype_xdigit($token) || strlen($token) < 32) {
    respond(['status' => 'fail', 'message' => 'Invalid token'], 401);
}

try {
    $result = Capsule::connection()->transaction(function () use ($token, $hostname, $deviceId, $installId, $deviceName) {
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
            Capsule::table('s3_cloudbackup_agents')
                ->where('id', $existing->id)
                ->update([
                    'agent_token' => $agentToken,
                    'enrollment_token_id' => $tok->id,
                    'hostname' => $hostname,
                    'device_name' => $deviceName !== '' ? $deviceName : ($existing->device_name ?? null),
                    'install_id' => $installId !== '' ? $installId : ($existing->install_id ?? null),
                    'status' => 'active',
                    'last_seen_at' => Capsule::raw('NOW()'),
                    'updated_at' => Capsule::raw('NOW()'),
                ]);
            $agentId = (int)$existing->id;
        } else {
            $agentId = Capsule::table('s3_cloudbackup_agents')->insertGetId([
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
            ]);
        }

        $systemUrl = rtrim(\WHMCS\Config\Setting::getValue('SystemURL'), '/');

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

