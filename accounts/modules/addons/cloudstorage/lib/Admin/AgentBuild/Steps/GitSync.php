<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

class GitSync extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        // Use the dedicated git working tree when configured (monorepo layout
        // where the Go module lives in a subdirectory). Falls back to the
        // module root for the legacy single-repo layout.
        $s = Settings::all();
        $repo = (string) ($s['git_root'] ?? $s['repo_path']);
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
