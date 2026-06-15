<?php
/**
 * e3 Agent Deploy Sync (production consumer)
 *
 * Polls the dev server's deployment manifest and installs new artifacts into
 * the local client_installer/ directory. Recommended: systemd timer every 5 min.
 */

require __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\DeploySync;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;

$lockPath = JobStore::storageRoot() . '/.deploy_sync.lock';
$lockDir = dirname($lockPath);
if (!is_dir($lockDir)) {
    if (!@mkdir($lockDir, 0750, true) && !is_dir($lockDir)) {
        fwrite(STDERR, "agent_deploy_sync: cannot create lock directory: $lockDir\n");
        exit(1);
    }
}
$lockHandle = @fopen($lockPath, 'c');
if ($lockHandle === false) {
    fwrite(STDERR, "agent_deploy_sync: cannot open lock file: $lockPath (check permissions for www-data)\n");
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "agent_deploy_sync: another instance is running, exiting\n");
    exit(0);
}

try {
    $result = DeploySync::runOnce();
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $result['status'] . ': ' . $result['message'] . "\n";
    fwrite(STDOUT, $line);
    if ($result['status'] === 'failed') {
        exit(1);
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "agent_deploy_sync: " . $e->getMessage() . "\n");
    try {
        logModuleCall('cloudstorage', 'agent_deploy_sync', [], $e->getMessage(), [], []);
    } catch (\Throwable $_) {}
    exit(1);
} finally {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
}
