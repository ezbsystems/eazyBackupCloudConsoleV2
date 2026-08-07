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
 *   THIRD_PARTY_LICENSES.txt    (referenced by .iss as ..\THIRD_PARTY_LICENSES.txt)
 *   assets\tray_logo-drk-orange120x120.png
 *   assets\tray_logo-drk-orange.svg
 *   assets\tray_logo.ico
 *   assets\wizard_large.bmp
 *   assets\wizard_small.bmp
 *   CloudNAS\bin\e3-cloudnas.exe               (when built on Windows)
 *   CloudNAS\LICENSE
 *   CloudNAS\installer\e3-cloudnas.iss
 *   CloudNAS\installer\README.md
 *   CloudNAS\installer\redist\README.md
 *   CloudNAS\installer\redist\winfsp.msi       (when cached locally)
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
        $cloudNasRepo = dirname($repo) . '/e3-cloudnas';

        // Ensure remote dirs
        $mkdirCmd =
            "New-Item -ItemType Directory -Force -Path " .
            "'$remoteRoot\\bin','$remoteRoot\\installer','$remoteRoot\\assets'," .
            "'$remoteRoot\\CloudNAS\\bin','$remoteRoot\\CloudNAS\\installer\\redist'," .
            "'$remoteRoot\\Output' | Out-Null";
        $rc = $runner->run($remote->powershell($mkdirCmd), $logPath);
        if ($rc !== 0) return $rc;

        // Files to upload
        $uploads = [
            $repo . '/bin/e3-backup-agent.exe' => $remoteRoot . '\\bin\\e3-backup-agent.exe',
            $repo . '/bin/e3-backup-tray.exe'  => $remoteRoot . '\\bin\\e3-backup-tray.exe',
            $repo . '/installer/e3-backup-agent.iss' => $remoteRoot . '\\installer\\e3-backup-agent.iss',
            // Required by installer/e3-backup-agent.iss [Files] Source: "..\THIRD_PARTY_LICENSES.txt"
            $repo . '/THIRD_PARTY_LICENSES.txt' => $remoteRoot . '\\THIRD_PARTY_LICENSES.txt',
        ];

        // Cloud NAS is optional. The agent Inno script includes its checked-by-
        // default task only when both the Windows CGO build and WinFsp MSI are
        // present in this staged tree.
        $cloudNasUploads = [
            $cloudNasRepo . '/bin/e3-cloudnas.exe' => $remoteRoot . '\\CloudNAS\\bin\\e3-cloudnas.exe',
            $cloudNasRepo . '/LICENSE' => $remoteRoot . '\\CloudNAS\\LICENSE',
            $cloudNasRepo . '/installer/e3-cloudnas.iss' => $remoteRoot . '\\CloudNAS\\installer\\e3-cloudnas.iss',
            $cloudNasRepo . '/installer/README.md' => $remoteRoot . '\\CloudNAS\\installer\\README.md',
            $cloudNasRepo . '/installer/redist/README.md' => $remoteRoot . '\\CloudNAS\\installer\\redist\\README.md',
            $cloudNasRepo . '/installer/redist/winfsp.msi' => $remoteRoot . '\\CloudNAS\\installer\\redist\\winfsp.msi',
        ];
        $uploads = array_merge($uploads, $cloudNasUploads);

        if ($this->flag($job, 'include_recovery') && file_exists($repo . '/bin/e3-recovery-agent.exe')) {
            $uploads[$repo . '/bin/e3-recovery-agent.exe'] = $remoteRoot . '\\bin\\e3-recovery-agent.exe';
        }

        $assetsDir = '/var/www/eazybackup.ca/e3-cloudbackup-worker/assets';
        foreach (['tray_logo-drk-orange120x120.png', 'tray_logo-drk-orange.svg', 'tray_logo.ico', 'wizard_large.bmp', 'wizard_small.bmp'] as $a) {
            if (file_exists($assetsDir . '/' . $a)) {
                $uploads[$assetsDir . '/' . $a] = $remoteRoot . '\\assets\\' . $a;
            }
        }

        $licensesLocal = $repo . '/THIRD_PARTY_LICENSES.txt';
        if (!is_file($licensesLocal)) {
            $this->appendLog($logPath, "[error] missing required file for Inno: $licensesLocal");
            return 2;
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
