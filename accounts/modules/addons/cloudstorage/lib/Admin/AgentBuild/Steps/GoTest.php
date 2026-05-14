<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

class GoTest extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $repo = (string) Settings::get('agent_build_repo_path', '/var/www/eazybackup.ca/e3-backup-agent');
        return $runner->run(['go', 'test', './...'], $logPath, $repo);
    }
}
