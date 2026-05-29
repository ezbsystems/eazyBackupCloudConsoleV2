<?php
/**
 * Agent Update Watchdog
 *
 * Flips stale remote-update jobs to 'timeout' when an agent does not come back
 * online reporting the target version within a bounded window. Run from cron
 * every minute (or alongside the existing agent watchdog):
 *
 *   php /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/crons/agent_update_watchdog.php
 *
 * The timeout window is configurable via the module setting
 * cloudbackup_agent_update_timeout_seconds (default 600s / 10 minutes), counted
 * from the job's last update.
 */

require __DIR__ . '/../../../../init.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Module\Addon\CloudStorage\Client\AgentUpdateService;

require_once __DIR__ . '/../lib/Client/AgentUpdateService.php';

function getModuleSetting(string $key, $default = null)
{
    try {
        $val = Capsule::table('tbladdonmodules')
            ->where('module', 'cloudstorage')
            ->where('setting', $key)
            ->value('value');
        return ($val !== null && $val !== '') ? $val : $default;
    } catch (\Throwable $e) {
        return $default;
    }
}

if (!Capsule::schema()->hasTable('s3_agent_update_jobs')) {
    fwrite(STDOUT, "s3_agent_update_jobs table not present; nothing to do\n");
    exit(0);
}

$timeoutSeconds = (int) getModuleSetting('cloudbackup_agent_update_timeout_seconds', 600);
if ($timeoutSeconds <= 0) {
    $timeoutSeconds = 600;
}

try {
    // First, give online agents a last chance to finalize: if an active job's
    // agent already reports the target version, mark it success rather than
    // timing it out.
    $activeJobs = Capsule::table('s3_agent_update_jobs as u')
        ->join('s3_cloudbackup_agents as a', 'a.agent_uuid', '=', 'u.agent_uuid')
        ->whereIn('u.status', AgentUpdateService::ACTIVE_STATES)
        ->get(['u.id', 'u.agent_uuid', 'u.target_version', 'a.agent_version']);

    $finalized = 0;
    foreach ($activeJobs as $job) {
        if (AgentUpdateService::markSuccessIfUpgraded((string) $job->agent_uuid, (string) ($job->agent_version ?? ''))) {
            $finalized++;
        }
    }

    // Then time out anything still active and older than the window.
    $timedOut = Capsule::table('s3_agent_update_jobs')
        ->whereIn('status', AgentUpdateService::ACTIVE_STATES)
        ->whereRaw('TIMESTAMPDIFF(SECOND, updated_at, NOW()) > ?', [$timeoutSeconds])
        ->update([
            'status' => 'timeout',
            'detail' => 'Agent did not confirm the update within the timeout window',
            'finished_at' => Capsule::raw('NOW()'),
            'updated_at' => Capsule::raw('NOW()'),
        ]);

    fwrite(STDOUT, sprintf("agent_update_watchdog: finalized=%d timed_out=%d\n", $finalized, (int) $timedOut));
    exit(0);
} catch (\Throwable $e) {
    if (function_exists('logModuleCall')) {
        logModuleCall('cloudstorage', 'agent_update_watchdog_error', [], $e->getMessage());
    }
    fwrite(STDERR, 'agent_update_watchdog error: ' . $e->getMessage() . "\n");
    exit(1);
}
