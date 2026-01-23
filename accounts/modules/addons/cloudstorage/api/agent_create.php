<?php

require_once __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
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

function randomToken($len = 40)
{
    return bin2hex(random_bytes($len / 2));
}

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout'], 200))->send();
    exit;
}
$clientId = $ca->getUserID();
$hostname = $_POST['hostname'] ?? '';

$token = randomToken();
$id = Capsule::table('s3_cloudbackup_agents')->insertGetId([
    'client_id' => $clientId,
    'agent_token' => $token,
    'hostname' => $hostname,
    'status' => 'active',
    'created_at' => Capsule::raw('NOW()'),
    'updated_at' => Capsule::raw('NOW()'),
]);

$systemUrl = rtrim(detectBaseUrl(), '/');
$config = [
    'client_id' => (string)$clientId,
    'agent_id' => (string)$id,
    'agent_token' => $token,
    'api_base_url' => $systemUrl . '/modules/addons/cloudstorage/api',
];

(new JsonResponse([
    'status' => 'success',
    'agent' => [
        'id' => $id,
        'client_id' => $clientId,
        'hostname' => $hostname,
        'status' => 'active',
        'agent_token' => $token,
    ],
    'agent_conf' => $config,
], 200))->send();
exit;

