<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

/** Compute sha256 + (when osslsigncode is present) verify Authenticode chain. */
class Verify extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $jobId = (int) $job['id'];
        $artDir = JobStore::jobLogDir($jobId) . '/artifacts';

        // Linux artifact
        $linux = (string) Settings::get('agent_build_repo_path', '/var/www/eazybackup.ca/e3-backup-agent') . '/bin/e3-backup-agent';
        if (file_exists($linux)) {
            $runner->run(['sha256sum', $linux], $logPath);
        }

        if (!is_dir($artDir)) {
            return 0;
        }
        foreach (glob($artDir . '/*') as $f) {
            $runner->run(['sha256sum', $f], $logPath);
            if (preg_match('/\.exe$/i', $f) && self::commandExists('osslsigncode')) {
                $runner->run(['osslsigncode', 'verify', '-in', $f], $logPath);
            }
        }
        return 0;
    }

    private static function commandExists(string $cmd): bool
    {
        [$rc, ] = ProcRunner::capture(['bash', '-c', "command -v " . escapeshellarg($cmd)]);
        return $rc === 0;
    }
}
