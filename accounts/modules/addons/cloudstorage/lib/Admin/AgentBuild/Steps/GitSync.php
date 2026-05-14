<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

class GitSync extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $repo = (string) Settings::get('agent_build_repo_path', '/var/www/eazybackup.ca/e3-backup-agent');
        $ref = (string) ($job['git_ref'] ?? 'main');

        $rc = $runner->run(['git', '-C', $repo, 'fetch', '--all', '--tags', '--prune'], $logPath);
        if ($rc !== 0) return $rc;

        $rc = $runner->run(['git', '-C', $repo, 'checkout', $ref], $logPath);
        if ($rc !== 0) return $rc;

        // If ref is a branch, fast-forward
        $runner->run(['git', '-C', $repo, 'pull', '--ff-only'], $logPath);

        // Capture commit
        [$exit, $sha] = ProcRunner::capture(['git', '-C', $repo, 'rev-parse', '--short', 'HEAD']);
        if ($exit === 0 && $sha !== '') {
            JobStore::updateJob((int) $job['id'], ['git_commit' => substr($sha, 0, 40)]);
        }
        return 0;
    }
}
