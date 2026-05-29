<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

class LinuxBuild extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $repo = (string) Settings::get('agent_build_repo_path', '/var/www/eazybackup.ca/e3-backup-agent');
        $version = (string) ($job['version_label'] ?: 'dev');
        // Pass VERSION as a Make variable so we don't have to build an explicit
        // $env array (which would mask the systemd/inherited env including
        // PATH, HOME, GOCACHE, GOMODCACHE, GOTMPDIR).
        return $runner->run(['make', 'VERSION=' . $version, 'build'], $logPath, $repo);
    }
}
