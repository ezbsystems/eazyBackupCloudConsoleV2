<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\ProductConfig;
use WHMCS\Module\Addon\CloudStorage\Client\DBController;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function respond(array $data, int $httpCode = 200): void
{
    (new JsonResponse($data, $httpCode))->send();
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$hostname = trim($_POST['hostname'] ?? '');
$deviceId = trim($_POST['device_id'] ?? '');
$installId = trim($_POST['install_id'] ?? '');
$deviceName = trim($_POST['device_name'] ?? '');

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
    $result = Capsule::connection()->transaction(function () use ($clientId, $hostname, $deviceId, $installId, $deviceName) {
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
                Capsule::table('s3_cloudbackup_agents')
                    ->where('id', $existing->id)
                    ->update([
                        'agent_token' => $agentToken,
                        'hostname' => $hostname,
                        'device_name' => $deviceName !== '' ? $deviceName : ($existing->device_name ?? null),
                        'install_id' => $installId !== '' ? $installId : ($existing->install_id ?? null),
                        'status' => 'active',
                        'last_seen_at' => Capsule::raw('NOW()'),
                        'updated_at' => Capsule::raw('NOW()'),
                    ]);
                $agentId = (int)$existing->id;
            }
        }

        if (!$agentId) {
            $agentId = Capsule::table('s3_cloudbackup_agents')->insertGetId([
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
            ]);
        }

        return [
            'agent_id' => (string) $agentId,
            'agent_token' => $agentToken,
        ];
    });

    $systemUrl = rtrim(\WHMCS\Config\Setting::getValue('SystemURL'), '/');

    respond([
        'status' => 'success',
        'agent_id' => $result['agent_id'],
        'client_id' => (string) $clientId,
        'agent_token' => $result['agent_token'],
        'api_base_url' => $systemUrl . '/modules/addons/cloudstorage/api',
        'message' => 'Agent enrolled successfully',
    ], 200);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Enrollment failed'], 500);
}

