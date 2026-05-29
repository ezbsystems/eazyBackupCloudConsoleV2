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

        // Each entry: [remote-path, local-path, required?]. The installer and
        // the signed exes are required; the recovery binary is only required
        // when the build was asked to include it.
        $remoteFiles = [
            [$remoteRoot . '\\Output\\e3-backup-agent-setup.exe', $localDir . '/e3-backup-agent-setup.exe', true],
            [$remoteRoot . '\\bin\\e3-backup-agent.exe',          $localDir . '/e3-backup-agent.exe',       true],
            [$remoteRoot . '\\bin\\e3-backup-tray.exe',           $localDir . '/e3-backup-tray.exe',        true],
        ];
        if ($this->flag($job, 'include_recovery')) {
            $remoteFiles[] = [$remoteRoot . '\\bin\\e3-recovery-agent.exe', $localDir . '/e3-recovery-agent.exe', true];
        }

        $hadRequiredFailure = false;
        foreach ($remoteFiles as [$rPath, $lPath, $required]) {
            $rc = $runner->run($remote->scpDown($rPath, $lPath), $logPath);
            if ($rc !== 0) {
                $level = $required ? '[error]' : '[warn]';
                $this->appendLog($logPath, "$level could not fetch $rPath (rc=$rc)");
                if ($required) {
                    $hadRequiredFailure = true;
                    continue;
                }
            }
            if ($required && (!is_file($lPath) || filesize($lPath) === 0)) {
                $this->appendLog($logPath, "[error] artifact missing or empty after fetch: $lPath");
                $hadRequiredFailure = true;
            }
        }
        return $hadRequiredFailure ? 1 : 0;
    }
}
