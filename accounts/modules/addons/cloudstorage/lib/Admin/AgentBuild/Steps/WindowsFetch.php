<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\WindowsRemote;

class WindowsFetch extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $s = Settings::all();
        $remote = WindowsRemote::fromSettings();
        $jobId = (int) $job['id'];
        $remoteRoot = rtrim((string) $s['win_work_dir'], '\\') . '\\' . $jobId;
        $localDir = JobStore::jobLogDir($jobId) . '/artifacts';
        if (!is_dir($localDir)) {
            @mkdir($localDir, 0750, true);
        }

        $remoteFiles = [
            $remoteRoot . '\\Output\\e3-backup-agent-setup.exe' => $localDir . '/e3-backup-agent-setup.exe',
            $remoteRoot . '\\bin\\e3-backup-agent.exe'          => $localDir . '/e3-backup-agent.exe',
            $remoteRoot . '\\bin\\e3-backup-tray.exe'           => $localDir . '/e3-backup-tray.exe',
        ];
        if ($this->flag($job, 'include_recovery')) {
            $remoteFiles[$remoteRoot . '\\bin\\e3-recovery-agent.exe'] = $localDir . '/e3-recovery-agent.exe';
        }
        foreach ($remoteFiles as $rPath => $lPath) {
            $rc = $runner->run($remote->scpDown($rPath, $lPath), $logPath);
            if ($rc !== 0) {
                $this->appendLog($logPath, "[warn] could not fetch $rPath");
            }
        }
        return 0;
    }
}
