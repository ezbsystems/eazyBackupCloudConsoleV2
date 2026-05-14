<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\AzureSigner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\WindowsRemote;

class AzureSign extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $s = Settings::all();
        if (empty($s['signing_enabled'])) {
            $this->appendLog($logPath, '[skip] signing disabled in settings');
            return 0;
        }
        $secret = Settings::decryptedSecret('agent_build_azure_client_secret');
        $required = [
            'azure_tenant_id', 'azure_client_id', 'azure_kv_url', 'azure_kv_cert',
        ];
        foreach ($required as $k) {
            if (empty($s[$k])) {
                $this->appendLog($logPath, "[error] missing required setting: $k");
                return 2;
            }
        }
        if ($secret === null || $secret === '') {
            $this->appendLog($logPath, '[error] azure client secret not configured');
            return 2;
        }
        $runner->addSecret($secret);

        $signer = new AzureSigner(
            (string) $s['azuresigntool'],
            (string) $s['azure_tenant_id'],
            (string) $s['azure_client_id'],
            $secret,
            (string) $s['azure_kv_url'],
            (string) $s['azure_kv_cert'],
            (string) $s['azure_ts_url']
        );

        $remote = WindowsRemote::fromSettings();
        $jobId = (int) $job['id'];
        $remoteRoot = rtrim((string) $s['win_work_dir'], '\\') . '\\' . $jobId;

        // Sign the binaries that ended up inside Inno's output too. We sign:
        //  - bin\e3-backup-agent.exe, bin\e3-backup-tray.exe, bin\e3-recovery-agent.exe
        //  - Output\e3-backup-agent-setup.exe (and any other Output\*.exe)
        $targets = [
            $remoteRoot . '\\bin\\e3-backup-agent.exe',
            $remoteRoot . '\\bin\\e3-backup-tray.exe',
        ];
        if ($this->flag($job, 'include_recovery')) {
            $targets[] = $remoteRoot . '\\bin\\e3-recovery-agent.exe';
        }
        $targets[] = $remoteRoot . '\\Output\\e3-backup-agent-setup.exe';

        foreach ($targets as $remoteFile) {
            $existsCmd = "if (Test-Path -LiteralPath '"
                . str_replace("'", "''", $remoteFile)
                . "') { 'OK' } else { 'MISSING' }";
            [$rc, $out] = ProcRunner::capture($remote->powershell($existsCmd));
            if (stripos($out, 'MISSING') !== false) {
                $this->appendLog($logPath, "[skip] not present on remote: $remoteFile");
                continue;
            }
            $cmd = $signer->buildSignPowerShell($remoteFile);
            $rc = $runner->run($remote->powershell($cmd), $logPath);
            if ($rc !== 0) return $rc;
        }
        return 0;
    }
}
