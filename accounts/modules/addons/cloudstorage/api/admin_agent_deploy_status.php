<?php

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use Symfony\Component\HttpFoundation\JsonResponse;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\DeployPublisher;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\DeployStore;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['adminid'])) {
    (new JsonResponse(['status' => 'fail', 'message' => 'Admin authentication required']))->send();
    exit;
}

$active = DeployPublisher::activeManifestPayload();
$history = DeployStore::listDeployments(25);
$syncRuns = DeployStore::listSyncRuns(10);
$settings = Settings::all();

(new JsonResponse([
    'status' => 'success',
    'active' => $active,
    'history' => $history,
    'sync_runs' => $syncRuns,
    'settings' => [
        'deploy_role' => $settings['deploy_role'] ?? 'publisher',
        'deploy_manifest_api_url' => $settings['deploy_manifest_api_url'] ?? '',
        'deploy_manifest_url' => $settings['deploy_manifest_url'] ?? '',
        'deploy_sync_enabled' => !empty($settings['deploy_sync_enabled']),
        'deploy_last_sync_id' => (int) ($settings['deploy_last_sync_id'] ?? 0),
    ],
]))->send();
