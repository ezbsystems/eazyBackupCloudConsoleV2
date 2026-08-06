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
        $agentRepo = (string) ($s['repo_path'] ?? '');

        $rc = $runner->run(['git', '-C', $repo, 'fetch', '--all', '--tags', '--prune'], $logPath);
        if ($rc !== 0) {
            return $rc;
        }

        // Disposable build tree: hard-reset local branch to origin/<ref> so a
        // rewritten/diverged local main cannot leave linux_build on a stale
        // Makefile. Fall back to plain checkout for tags / SHAs.
        $remoteRef = 'origin/' . $ref;
        [$originExit] = ProcRunner::capture(['git', '-C', $repo, 'rev-parse', '--verify', $remoteRef]);
        if ($originExit === 0) {
            $rc = $runner->run(['git', '-C', $repo, 'checkout', '-B', $ref, $remoteRef], $logPath);
            if ($rc !== 0) {
                return $rc;
            }
            $rc = $runner->run(['git', '-C', $repo, 'reset', '--hard', $remoteRef], $logPath);
            if ($rc !== 0) {
                return $rc;
            }
        } else {
            $rc = $runner->run(['git', '-C', $repo, 'checkout', '--detach', $ref], $logPath);
            if ($rc !== 0) {
                return $rc;
            }
        }

        // Capture commit
        [$exit, $sha] = ProcRunner::capture(['git', '-C', $repo, 'rev-parse', '--short', 'HEAD']);
        if ($exit === 0 && $sha !== '') {
            JobStore::updateJob((int) $job['id'], ['git_commit' => substr($sha, 0, 40)]);
        }

        $makefile = rtrim($agentRepo !== '' ? $agentRepo : $repo . '/e3-backup-agent', '/') . '/Makefile';
        $makefileHead = is_file($makefile) ? (string) @file_get_contents($makefile, false, null, 0, 400) : '';
        if (strpos($makefileHead, 'linux-installer') === false) {
            $this->appendLog($logPath, '[error] Makefile missing linux-installer target after sync (sha=' . $sha . ')');
            return 2;
        }

        return 0;
    }
}
