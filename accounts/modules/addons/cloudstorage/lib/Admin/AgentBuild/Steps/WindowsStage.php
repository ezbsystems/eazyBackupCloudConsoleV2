<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\WindowsRemote;

/**
 * Stage everything Inno Setup needs onto the Windows host.
 *
 * Layout on remote (under <work_dir>\<job_id>\):
 *   bin\e3-backup-agent.exe
 *   bin\e3-backup-tray.exe
 *   bin\e3-recovery-agent.exe   (only if include_recovery)
 *   installer\e3-backup-agent.iss
 *   assets\tray_logo-drk-orange120x120.png
 *   assets\tray_logo-drk-orange.svg
 *   assets\tray_logo.ico
 *   assets\wizard_large.bmp
 *   assets\wizard_small.bmp
 *   Output\
 */
class WindowsStage extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $s = Settings::all();
        $repo = (string) $s['repo_path'];
        $remote = WindowsRemote::fromSettings();
        $jobId = (int) $job['id'];
        $remoteRoot = rtrim((string) $s['win_work_dir'], '\\') . '\\' . $jobId;

        // Ensure remote dirs
        $mkdirCmd =
            "New-Item -ItemType Directory -Force -Path '$remoteRoot\\bin','$remoteRoot\\installer','$remoteRoot\\assets','$remoteRoot\\Output' | Out-Null";
        $rc = $runner->run($remote->powershell($mkdirCmd), $logPath);
        if ($rc !== 0) return $rc;

        // Files to upload
        $uploads = [
            $repo . '/bin/e3-backup-agent.exe' => $remoteRoot . '\\bin\\e3-backup-agent.exe',
            $repo . '/bin/e3-backup-tray.exe'  => $remoteRoot . '\\bin\\e3-backup-tray.exe',
            $repo . '/installer/e3-backup-agent.iss' => $remoteRoot . '\\installer\\e3-backup-agent.iss',
        ];

        if ($this->flag($job, 'include_recovery') && file_exists($repo . '/bin/e3-recovery-agent.exe')) {
            $uploads[$repo . '/bin/e3-recovery-agent.exe'] = $remoteRoot . '\\bin\\e3-recovery-agent.exe';
        }

        $assetsDir = '/var/www/eazybackup.ca/e3-cloudbackup-worker/assets';
        foreach (['tray_logo-drk-orange120x120.png', 'tray_logo-drk-orange.svg', 'tray_logo.ico', 'wizard_large.bmp', 'wizard_small.bmp'] as $a) {
            if (file_exists($assetsDir . '/' . $a)) {
                $uploads[$assetsDir . '/' . $a] = $remoteRoot . '\\assets\\' . $a;
            }
        }

        foreach ($uploads as $local => $remotePath) {
            if (!file_exists($local)) {
                $this->appendLog($logPath, "[skip] missing local file: $local");
                continue;
            }
            $rc = $runner->run($remote->scpUp($local, $remotePath), $logPath);
            if ($rc !== 0) return $rc;
        }
        return 0;
    }
}
