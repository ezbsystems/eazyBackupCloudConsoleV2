<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Client/AgentIngestSupport.php';
require_once __DIR__ . '/../lib/Client/Ms365VaultLifecycleService.php';

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\ClientArea;
use WHMCS\Module\Addon\CloudStorage\Client\Ms365VaultLifecycleService;

$ca = new ClientArea();
if (!$ca->isLoggedIn()) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Session timeout.'], 200))->send();
    exit;
}

(new JsonResponse([
    'status' => 'success',
    'grace_days' => Ms365VaultLifecycleService::getGraceDays(),
], 200))->send();
exit();
