<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\DeployAuth;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\DeploySync;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['adminid'])) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required']))->send();
    exit;
}

$settings = Settings::all();
$checks = [];

$checks['sync_enabled'] = [
    'name' => 'Sync enabled',
    'ok' => !empty($settings['deploy_sync_enabled']),
    'detail' => !empty($settings['deploy_sync_enabled']) ? 'yes' : 'no',
];
$checks['manifest_url'] = [
    'name' => 'Manifest URL',
    'ok' => trim((string) ($settings['deploy_manifest_url'] ?? '')) !== '',
    'detail' => (string) ($settings['deploy_manifest_url'] ?? ''),
];
$secretLen = strlen(DeployAuth::sharedToken());
$checks['shared_secret'] = [
    'name' => 'Shared deploy secret',
    'ok' => $secretLen > 0,
    'detail' => $secretLen > 0 ? ('configured (len=' . $secretLen . ')') : 'not configured',
];

$result = DeploySync::runOnce();
$checks['sync_run'] = [
    'name' => 'Sync run (dry)',
    'ok' => in_array($result['status'], ['succeeded', 'skipped'], true),
    'detail' => $result['status'] . ': ' . $result['message'],
];

(new JsonResponse(['status' => 'success', 'checks' => $checks, 'result' => $result]))->send();
