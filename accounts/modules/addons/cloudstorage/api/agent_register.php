<?php

require_once __DIR__ . '/../../../../init.php';

use Symfony\Component\HttpFoundation\JsonResponse;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Placeholder for future dynamic pairing flow.
$response = new JsonResponse([
    'status' => 'fail',
    'message' => 'agent_register not implemented yet. Provision tokens via the portal.',
], 501);
$response->send();
exit;

