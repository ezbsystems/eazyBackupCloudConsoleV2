<?php
/**
 * e3 Agent Build Runner
 *
 * Single-instance daemon-tick. Picks up one queued s3_agent_build_jobs row and
 * runs it through the full pipeline (git -> tests -> linux/win build ->
 * stage -> Inno -> AzureSignTool -> fetch -> verify -> publish).
 *
 * Recommended scheduling: a systemd .timer firing every 60 seconds, or a
 * WHMCS cron `* * * * *`. The flock guarantees at most one runner at a time.
 */

require __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../lib/Admin/AgentBuild/bootstrap.php';

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\BuildRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;

$lockPath = JobStore::storageRoot() . '/.runner.lock';
if (!is_dir(dirname($lockPath))) {
    @mkdir(dirname($lockPath), 0750, true);
}
$lockHandle = fopen($lockPath, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "agent_build_runner: another instance is running, exiting\n");
    exit(0);
}

try {
    $maxJobs = (int) (getenv('AGENT_BUILD_RUNNER_MAX_JOBS') ?: 1);
    $processed = 0;
    while ($processed < $maxJobs) {
        $job = JobStore::claimNextQueuedJob();
        if (!$job) {
            break;
        }
        try {
            (new BuildRunner())->run($job);
        } catch (\Throwable $e) {
            JobStore::updateJob((int) $job['id'], [
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'ended_at'      => date('Y-m-d H:i:s'),
            ]);
            try {
                logModuleCall('cloudstorage', 'agent_build_runner', $job, $e->getMessage(), [], []);
            } catch (\Throwable $_) {}
        }
        $processed++;
    }
} finally {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
}
