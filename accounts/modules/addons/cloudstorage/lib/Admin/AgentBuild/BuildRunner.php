<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\AzureSign;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\GitSync;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\GoTest;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\InnoCompile;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\LinuxBuild;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\Publish;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\RecoveryBuild;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\StepBase;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\Verify;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\WindowsBuild;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\WindowsFetch;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps\WindowsStage;

class BuildRunner
{
    /** @return array<string,StepBase> */
    public static function steps(): array
    {
        return [
            'git_sync'       => new GitSync('git_sync'),
            'go_test'        => new GoTest('go_test'),
            'linux_build'    => new LinuxBuild('linux_build'),
            'windows_build'  => new WindowsBuild('windows_build'),
            'recovery_build' => new RecoveryBuild('recovery_build'),
            'windows_stage'  => new WindowsStage('windows_stage'),
            'windows_inno'   => new InnoCompile('windows_inno'),
            'windows_sign'   => new AzureSign('windows_sign'),
            'windows_fetch'  => new WindowsFetch('windows_fetch'),
            'verify'         => new Verify('verify'),
            'publish'        => new Publish('publish'),
        ];
    }

    public function run(array $job): void
    {
        $jobId = (int) $job['id'];
        $logDir = JobStore::jobLogDir($jobId);
        $steps = self::steps();
        $stepRows = JobStore::steps($jobId);

        $finalStatus = 'succeeded';
        $errMsg = null;

        try {
            foreach ($stepRows as $row) {
                $key = (string) $row['step_key'];
                if ($row['status'] === 'skipped') {
                    continue;
                }
                if (JobStore::isCancelRequested($jobId)) {
                    $finalStatus = 'cancelled';
                    break;
                }
                if (!isset($steps[$key])) {
                    JobStore::updateStep($jobId, $key, [
                        'status' => 'failed', 'exit_code' => -1, 'summary' => 'unknown step',
                    ]);
                    $finalStatus = 'failed';
                    $errMsg = "unknown step: $key";
                    break;
                }

                $logPath = $logDir . '/' . $key . '.log';
                JobStore::updateJob($jobId, ['current_step' => $key]);
                JobStore::updateStep($jobId, $key, [
                    'status'     => 'running',
                    'started_at' => date('Y-m-d H:i:s'),
                    'log_path'   => $logPath,
                ]);

                $runner = new ProcRunner();
                // Always redact the Azure secret from logs even outside the sign step
                $secret = Settings::decryptedSecret('agent_build_azure_client_secret');
                $runner->addSecret($secret);

                $rc = -1;
                try {
                    $rc = $steps[$key]->execute($job, $runner, $logPath);
                } catch (\Throwable $e) {
                    @file_put_contents($logPath, "\n[exception] " . $e->getMessage() . "\n", FILE_APPEND);
                    $rc = 99;
                }

                $bytes = file_exists($logPath) ? (int) filesize($logPath) : 0;
                JobStore::updateStep($jobId, $key, [
                    'status'      => $rc === 0 ? 'succeeded' : 'failed',
                    'exit_code'   => $rc,
                    'ended_at'    => date('Y-m-d H:i:s'),
                    'bytes_logged'=> $bytes,
                ]);

                if ($rc !== 0) {
                    $finalStatus = 'failed';
                    $errMsg = "step $key failed (exit=$rc)";
                    // Mark remaining pending steps skipped
                    foreach ($stepRows as $rr) {
                        if ($rr['status'] === 'pending' && $rr['seq'] > $row['seq']) {
                            JobStore::updateStep($jobId, $rr['step_key'], ['status' => 'skipped']);
                        }
                    }
                    break;
                }

                // Refresh the in-memory job (git_commit may have been set)
                $job = JobStore::getJob($jobId) ?: $job;
            }
        } catch (\Throwable $e) {
            $finalStatus = 'failed';
            $errMsg = $e->getMessage();
        }

        JobStore::updateJob($jobId, [
            'status'        => $finalStatus,
            'ended_at'      => date('Y-m-d H:i:s'),
            'error_message' => $errMsg,
        ]);
    }
}
