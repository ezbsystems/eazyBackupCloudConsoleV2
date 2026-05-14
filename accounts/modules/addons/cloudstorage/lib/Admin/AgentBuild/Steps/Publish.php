<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\JobStore;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;

/**
 * Publish artifacts into client_installer/ with versioned filenames + 'latest'
 * aliases, and record an s3_agent_releases row per artifact.
 */
class Publish extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $jobId = (int) $job['id'];
        $version = (string) ($job['version_label'] ?: 'dev');
        $commit  = (string) ($job['git_commit'] ?? '');
        $publishDir = (string) Settings::get('agent_build_publish_dir', '/var/www/eazybackup.ca/accounts/client_installer');
        if (!is_dir($publishDir)) {
            @mkdir($publishDir, 0755, true);
        }
        $artDir = JobStore::jobLogDir($jobId) . '/artifacts';
        $repo = (string) Settings::get('agent_build_repo_path', '/var/www/eazybackup.ca/e3-backup-agent');

        $platform = (string) ($job['platform'] ?? 'both');

        $publishMap = [];

        if (in_array($platform, ['linux', 'both'], true)) {
            $publishMap[] = [
                'src'        => $repo . '/bin/e3-backup-agent',
                'latest'     => 'e3-backup-agent-linux',
                'versioned'  => 'e3-backup-agent-linux-' . $version,
                'platform'   => 'linux',
            ];
        }
        if (in_array($platform, ['windows', 'both'], true)) {
            $publishMap[] = [
                'src'        => $artDir . '/e3-backup-agent-setup.exe',
                'latest'     => 'e3-backup-agent-setup.exe',
                'versioned'  => 'e3-backup-agent-setup-' . $version . '.exe',
                'platform'   => 'windows',
            ];
        }
        if ($this->flag($job, 'include_recovery')) {
            $publishMap[] = [
                'src'        => $artDir . '/e3-recovery-agent.exe',
                'latest'     => 'e3-recovery-agent.exe',
                'versioned'  => 'e3-recovery-agent-' . $version . '.exe',
                'platform'   => 'recovery_iso',
            ];
        }

        $any = false;
        foreach ($publishMap as $pub) {
            if (!file_exists($pub['src'])) {
                $this->appendLog($logPath, "[skip] missing artifact: " . $pub['src']);
                continue;
            }
            $any = true;
            $verPath = $publishDir . '/' . $pub['versioned'];
            $latestPath = $publishDir . '/' . $pub['latest'];
            if (!@copy($pub['src'], $verPath)) {
                $this->appendLog($logPath, "[error] copy failed: {$pub['src']} -> $verPath");
                return 3;
            }
            @chmod($verPath, 0644);
            @copy($verPath, $latestPath);
            @chmod($latestPath, 0644);

            $sha = hash_file('sha256', $verPath) ?: null;
            $size = filesize($verPath) ?: 0;

            JobStore::clearLatest($pub['platform'], $pub['latest']);
            JobStore::recordRelease([
                'job_id'            => $jobId,
                'platform'          => $pub['platform'],
                'artifact_filename' => $pub['latest'],
                'version_label'     => $version,
                'git_commit'        => $commit,
                'sha256'            => $sha,
                'size_bytes'        => $size,
                'is_latest'         => 1,
                'download_url'      => '/client_installer/' . $pub['latest'],
                'published_at'      => date('Y-m-d H:i:s'),
            ]);

            $this->appendLog($logPath, "[ok] published $verPath (sha256=$sha, size=$size)");
        }

        return $any ? 0 : 4;
    }
}
