<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

class RecoveryBuild extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $repo = (string) Settings::get('agent_build_repo_path', '/var/www/eazybackup.ca/e3-backup-agent');
        $version = (string) ($job['version_label'] ?: 'dev');
        $rc1 = $runner->run(['make', 'VERSION=' . $version, 'build-recovery-windows'], $logPath, $repo);
        $rc2 = $runner->run(['make', 'VERSION=' . $version, 'build-recovery-media-creator'], $logPath, $repo);
        if ($rc1 !== 0) {
            return $rc1;
        }
        return $rc2;
    }
}
