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
    $agentToken = bin2hex(random_bytes(20)); // 40 hex chars
    $agentId = Capsule::table('s3_cloudbackup_agents')->insertGetId([
        'client_id' => $clientId,
        'tenant_id' => null,
        'hostname' => $hostname,
        'agent_type' => 'workstation',
        'status' => 'active',
        'agent_token' => $agentToken,
        'created_at' => Capsule::raw('NOW()'),
        'updated_at' => Capsule::raw('NOW()'),
    ]);

    $systemUrl = rtrim(\WHMCS\Config\Setting::getValue('SystemURL'), '/');

    respond([
        'status' => 'success',
        'agent_id' => (string) $agentId,
        'client_id' => (string) $clientId,
        'agent_token' => $agentToken,
        'api_base_url' => $systemUrl . '/modules/addons/cloudstorage/api',
        'message' => 'Agent enrolled successfully',
    ], 200);
} catch (\Throwable $e) {
    respond(['status' => 'fail', 'message' => 'Enrollment failed'], 500);
}

