<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\WindowsRemote;

class InnoCompile extends StepBase
{
    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $s = Settings::all();
        $remote = WindowsRemote::fromSettings();
        $jobId = (int) $job['id'];
        $remoteRoot = rtrim((string) $s['win_work_dir'], '\\') . '\\' . $jobId;
        $iscc = (string) $s['iscc_path'];
        $version = (string) ($job['version_label'] ?: 'dev');

        $iss = $remoteRoot . '\\installer\\e3-backup-agent.iss';
        $outDir = $remoteRoot . '\\Output';

        $assetsDir = $remoteRoot . '\\assets';
        $cmd = sprintf(
            "& '%s' /Qp \"/DAppVersion=%s\" \"/DAssetsDir=%s\" \"/O%s\" '%s'",
            str_replace("'", "''", $iscc),
            $version,
            $assetsDir,
            $outDir,
            str_replace("'", "''", $iss)
        );
        return $runner->run($remote->powershell($cmd), $logPath);
    }
}
