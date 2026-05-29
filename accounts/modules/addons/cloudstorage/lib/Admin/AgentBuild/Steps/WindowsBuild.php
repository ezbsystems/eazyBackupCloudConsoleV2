<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

class WindowsBuild extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $repo = (string) Settings::get('agent_build_repo_path', '/var/www/eazybackup.ca/e3-backup-agent');
        $version = (string) ($job['version_label'] ?: 'dev');
        return $runner->run(['make', 'VERSION=' . $version, 'build-windows'], $logPath, $repo);
    }
}
