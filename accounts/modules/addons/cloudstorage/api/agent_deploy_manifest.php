<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\DeployAuth;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\DeployPublisher;

$request = Request::createFromGlobals();
if ($auth = DeployAuth::authenticate($request)) {
    $auth->send();
    exit;
}

$manifest = DeployPublisher::activeManifestPayload();
if ($manifest === null) {
    (new JsonResponse(['status' => 'success', 'manifest' => null]))->send();
    exit;
}

(new JsonResponse(['status' => 'success', 'manifest' => $manifest]))->send();
