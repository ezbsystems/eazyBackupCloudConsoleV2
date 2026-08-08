<?php

namespace WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Steps;

use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\ProcRunner;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\Settings;
use WHMCS\Module\Addon\CloudStorage\Admin\AgentBuild\WindowsRemote;

/**
 * Build e3-cloudnas.exe on the Windows host (CGO + WinFsp) and ensure the
 * WinFsp MSI redistributable is present for WindowsStage / Inno.
 *
 * Agent Builds run windows_build on Linux with CGO_ENABLED=0; the sidecar
 * cannot be cross-compiled, so this step uploads sources, compiles remotely,
 * and copies the resulting exe back into the Linux e3-cloudnas tree.
 */
class WindowsBuildCloudNAS extends StepBase
{
    public const WINFSP_MSI_URL = 'https://github.com/winfsp/winfsp/releases/download/v2.2B3/winfsp-2.2.26194.msi';
    public const WINFSP_MSI_SHA256 = '7b41020618cdcc33d699d0e15c1df660f0762a09b57080049c565857ac00bd9d';

    public function execute(array $job, ProcRunner $runner, string $logPath): int
    {
        $s = Settings::all();
        $repo = (string) $s['repo_path'];
        $cloudNasRepo = dirname($repo) . '/e3-cloudnas';
        $jobId = (int) $job['id'];
        $remote = WindowsRemote::fromSettings();
        $remoteRoot = rtrim((string) $s['win_work_dir'], '\\') . '\\' . $jobId;
        $remoteSrc = $remoteRoot . '\\cloudnas-src';

        // #region agent log
        $this->debugLog('H1', 'WindowsBuildCloudNAS.php:entry', 'cloudnas build step start', [
            'jobId' => $jobId,
            'cloudNasRepo' => $cloudNasRepo,
            'repoExists' => is_dir($cloudNasRepo),
            'hasGoMod' => is_file($cloudNasRepo . '/go.mod'),
        ]);
        // #endregion

        if (!is_dir($cloudNasRepo) || !is_file($cloudNasRepo . '/go.mod')) {
            $this->appendLog($logPath, "[error] e3-cloudnas source missing at $cloudNasRepo (git sync a ref that includes e3-cloudnas/)");
            // #region agent log
            $this->debugLog('H1', 'WindowsBuildCloudNAS.php:missing-src', 'e3-cloudnas source missing', [
                'cloudNasRepo' => $cloudNasRepo,
            ]);
            // #endregion
            return 2;
        }

        $msiRc = $this->ensureWinfspMsi($cloudNasRepo, $logPath);
        if ($msiRc !== 0) {
            return $msiRc;
        }

        $goBin = Settings::get('agent_build_windows_go_bin', 'C:\\Go\\bin');
        $mingwBin = Settings::get('agent_build_windows_mingw_bin', 'C:\\Tools\\mingw64\\bin');
        $winfspInc = Settings::get(
            'agent_build_windows_winfsp_inc',
            'C:\\Program Files (x86)\\WinFsp\\inc\\fuse'
        );
        $localMsi = $cloudNasRepo . '/installer/redist/winfsp.msi';
        $remoteMsi = $remoteRoot . '\\winfsp-build.msi';

        // Fresh remote source tree for this job
        $mkdir = "Remove-Item -Recurse -Force -ErrorAction SilentlyContinue '$remoteSrc'; "
            . "New-Item -ItemType Directory -Force -Path '$remoteSrc','$remoteSrc\\bin','$remoteRoot' | Out-Null";
        $rc = $runner->run($remote->powershell($mkdir), $logPath);
        if ($rc !== 0) {
            return $rc;
        }

        // Ensure WinFsp Developer headers exist on the Windows build host (F.Developer).
        // Core-only installs leave inc\fuse missing and break CGO.
        $rc = $runner->run($remote->scpUp($localMsi, $remoteMsi), $logPath);
        if ($rc !== 0) {
            $this->appendLog($logPath, '[error] failed uploading WinFsp MSI for Developer install');
            return $rc;
        }
        $ensureDev = <<<'PS'
$ErrorActionPreference = 'Stop'
$winfspInc = '__WINFSP_INC__'
$msi = '__REMOTE_MSI__'
$candidates = @(
  $winfspInc,
  'C:\Program Files (x86)\WinFsp\inc\fuse',
  'C:\Program Files\WinFsp\inc\fuse'
)
function Find-WinFspInc {
  param([string[]]$Paths)
  foreach ($p in $Paths) {
    if ($p -and (Test-Path (Join-Path $p 'fuse.h'))) { return $p }
    if ($p -and (Test-Path $p) -and (Test-Path (Join-Path $p 'fuse.h'))) { return $p }
  }
  # WinFsp 2.x may place headers under the install root inc\ directly.
  foreach ($root in @('C:\Program Files (x86)\WinFsp','C:\Program Files\WinFsp')) {
    $fuse = Join-Path $root 'inc\fuse'
    if (Test-Path (Join-Path $fuse 'fuse.h')) { return $fuse }
    $inc = Join-Path $root 'inc'
    if (Test-Path (Join-Path $inc 'fuse.h')) { return $inc }
  }
  return $null
}
$found = Find-WinFspInc $candidates
if ($found) {
  Write-Output "WINFSP_HEADERS_OK $found"
  exit 0
}
if (-not (Test-Path $msi)) { throw "WinFsp MSI missing at $msi" }
$log = 'C:\E3Build\winfsp-developer-msi.log'
Write-Output "Installing WinFsp Core+Developer via MSI (INSTALLLEVEL=1000)..."
$p = Start-Process -FilePath msiexec.exe -ArgumentList @('/i', $msi, '/qn', '/norestart', 'INSTALLLEVEL=1000', '/l*v', $log) -Wait -PassThru
Write-Output ("msiexec INSTALLLEVEL=1000 exit=" + $p.ExitCode)
$found = Find-WinFspInc $candidates
if (-not $found) {
  Write-Output 'Headers still missing; trying ADDLOCAL=F.User,F.Developer...'
  $p2 = Start-Process -FilePath msiexec.exe -ArgumentList @('/i', $msi, '/qn', '/norestart', 'ADDLOCAL=F.User,F.Developer', '/l*v', $log) -Wait -PassThru
  Write-Output ("msiexec ADDLOCAL=F.User,F.Developer exit=" + $p2.ExitCode)
  $found = Find-WinFspInc $candidates
}
if (-not $found) {
  Write-Output 'Headers still missing; trying ADDLOCAL=F.Developer only...'
  $p3 = Start-Process -FilePath msiexec.exe -ArgumentList @('/i', $msi, '/qn', '/norestart', 'ADDLOCAL=F.Developer', '/l*v', $log) -Wait -PassThru
  Write-Output ("msiexec ADDLOCAL=F.Developer exit=" + $p3.ExitCode)
  $found = Find-WinFspInc $candidates
}
if (-not $found) {
  throw "WinFsp fuse headers still missing after MSI install (expected under WinFsp\inc\fuse); see $log"
}
Write-Output "WINFSP_HEADERS_OK $found"
PS;
        $ensureDev = str_replace(
            ['__WINFSP_INC__', '__REMOTE_MSI__'],
            [
                str_replace("'", "''", $winfspInc),
                str_replace("'", "''", $remoteMsi),
            ],
            $ensureDev
        );
        // #region agent log
        $this->debugLog('H5', 'WindowsBuildCloudNAS.php:ensure-dev-headers', 'ensuring WinFsp Developer headers on build host', [
            'winfspInc' => $winfspInc,
            'remoteMsi' => $remoteMsi,
        ]);
        // #endregion
        $rc = $runner->run($remote->powershell($ensureDev), $logPath, null, null, 900);
        if ($rc !== 0) {
            // #region agent log
            $this->debugLog('H5', 'WindowsBuildCloudNAS.php:ensure-dev-failed', 'WinFsp Developer ensure failed', [
                'exitCode' => $rc,
            ]);
            // #endregion
            return $rc;
        }

        // Upload build inputs (not the whole redist MSI twice if huge — still needed for Stage from Linux).
        $uploads = [
            $cloudNasRepo . '/go.mod' => $remoteSrc . '\\go.mod',
            $cloudNasRepo . '/go.sum' => $remoteSrc . '\\go.sum',
        ];
        foreach ($uploads as $local => $remotePath) {
            if (!is_file($local)) {
                $this->appendLog($logPath, "[error] missing $local");
                return 2;
            }
            $rc = $runner->run($remote->scpUp($local, $remotePath), $logPath);
            if ($rc !== 0) {
                return $rc;
            }
        }

        foreach (['cmd', 'internal'] as $dir) {
            $localDir = $cloudNasRepo . '/' . $dir;
            if (!is_dir($localDir)) {
                $this->appendLog($logPath, "[error] missing directory $localDir");
                return 2;
            }
            $rc = $runner->run($remote->scpUp($localDir, $remoteSrc . '\\' . $dir, true), $logPath);
            if ($rc !== 0) {
                return $rc;
            }
        }

        // Build with CGO; junction avoids spaces in WinFsp include path breaking the toolchain.
        $ps = <<<'PS'
$ErrorActionPreference = 'Stop'
$goBin = '__GO_BIN__'
$mingwBin = '__MINGW_BIN__'
$winfspIncPreferred = '__WINFSP_INC__'
$src = '__REMOTE_SRC__'
$env:Path = "$goBin;$mingwBin;" + $env:Path
if (-not (Test-Path "$goBin\go.exe")) { throw "go.exe not found under $goBin" }
if (-not (Test-Path "$mingwBin\gcc.exe")) { throw "gcc.exe not found under $mingwBin" }
$winfspInc = $null
foreach ($p in @($winfspIncPreferred, 'C:\Program Files (x86)\WinFsp\inc\fuse', 'C:\Program Files\WinFsp\inc\fuse', 'C:\Program Files (x86)\WinFsp\inc', 'C:\Program Files\WinFsp\inc')) {
  if ($p -and (Test-Path (Join-Path $p 'fuse.h'))) { $winfspInc = $p; break }
}
if (-not $winfspInc) { throw "WinFsp fuse headers missing after ensure step (fuse.h not found)" }
if (Test-Path 'C:\WinFspInc') { cmd /c 'rmdir C:\WinFspInc' | Out-Null }
cmd /c "mklink /J C:\WinFspInc `"$winfspInc`""
if (-not (Test-Path 'C:\WinFspInc\fuse.h')) { throw "C:\WinFspInc junction missing fuse.h (from $winfspInc)" }
Write-Output "Using WinFsp headers: $winfspInc"
Set-Location $src
New-Item -ItemType Directory -Force -Path bin | Out-Null
Remove-Item -Force -ErrorAction SilentlyContinue 'bin\e3-cloudnas.exe'
$cmd = 'set CGO_ENABLED=1&& set CPATH=C:\WinFspInc&& set CGO_CFLAGS=-IC:\WinFspInc&& set PATH=' + $goBin + ';' + $mingwBin + ';%PATH%&& go build -trimpath -ldflags="-s -w" -o bin\e3-cloudnas.exe .\cmd\e3-cloudnas'
Write-Output "Running: $cmd"
cmd /c $cmd
if ($LASTEXITCODE -ne 0) { throw "go build failed exit=$LASTEXITCODE" }
if (-not (Test-Path 'bin\e3-cloudnas.exe')) { throw 'e3-cloudnas.exe missing after build' }
Get-Item 'bin\e3-cloudnas.exe' | Format-List FullName,Length,LastWriteTime
Write-Output 'CLOUDNAS_BUILD_OK'
PS;
        $ps = str_replace(
            ['__GO_BIN__', '__MINGW_BIN__', '__WINFSP_INC__', '__REMOTE_SRC__'],
            [
                str_replace("'", "''", $goBin),
                str_replace("'", "''", $mingwBin),
                str_replace("'", "''", $winfspInc),
                str_replace("'", "''", $remoteSrc),
            ],
            $ps
        );

        // #region agent log
        $this->debugLog('H2', 'WindowsBuildCloudNAS.php:before-remote-build', 'starting remote CGO build', [
            'remoteSrc' => $remoteSrc,
            'goBin' => $goBin,
            'mingwBin' => $mingwBin,
        ]);
        // #endregion

        $rc = $runner->run($remote->powershell($ps), $logPath, null, null, 3600);
        if ($rc !== 0) {
            // #region agent log
            $this->debugLog('H2', 'WindowsBuildCloudNAS.php:remote-build-failed', 'remote CGO build failed', [
                'exitCode' => $rc,
            ]);
            // #endregion
            return $rc;
        }

        $localBinDir = $cloudNasRepo . '/bin';
        if (!is_dir($localBinDir) && !@mkdir($localBinDir, 0755, true)) {
            $this->appendLog($logPath, "[error] cannot create $localBinDir");
            return 2;
        }
        $localExe = $localBinDir . '/e3-cloudnas.exe';
        @unlink($localExe);
        $rc = $runner->run($remote->scpDown($remoteSrc . '\\bin\\e3-cloudnas.exe', $localExe), $logPath);
        if ($rc !== 0) {
            return $rc;
        }
        if (!is_file($localExe) || filesize($localExe) < 1024) {
            $this->appendLog($logPath, "[error] downloaded e3-cloudnas.exe missing or too small: $localExe");
            // #region agent log
            $this->debugLog('H3', 'WindowsBuildCloudNAS.php:exe-bad', 'downloaded exe invalid', [
                'localExe' => $localExe,
                'exists' => is_file($localExe),
                'size' => is_file($localExe) ? filesize($localExe) : 0,
            ]);
            // #endregion
            return 2;
        }

        $this->appendLog($logPath, '[ok] e3-cloudnas.exe ready (' . filesize($localExe) . ' bytes); WinFsp MSI present');
        // #region agent log
        $this->debugLog('H3', 'WindowsBuildCloudNAS.php:success', 'cloudnas build artifacts ready', [
            'exeBytes' => filesize($localExe),
            'msiBytes' => filesize($cloudNasRepo . '/installer/redist/winfsp.msi'),
        ]);
        // #endregion
        return 0;
    }

    private function ensureWinfspMsi(string $cloudNasRepo, string $logPath): int
    {
        $redistDir = $cloudNasRepo . '/installer/redist';
        $msiPath = $redistDir . '/winfsp.msi';
        $cachePath = '/var/cache/e3-agent-build/winfsp.msi';

        if (!is_dir($redistDir) && !@mkdir($redistDir, 0755, true)) {
            $this->appendLog($logPath, "[error] cannot create $redistDir");
            return 2;
        }

        if ($this->msiChecksumOk($msiPath)) {
            $this->appendLog($logPath, '[ok] WinFsp MSI already present with expected SHA-256');
            // #region agent log
            $this->debugLog('H4', 'WindowsBuildCloudNAS.php:msi-cached', 'local MSI checksum OK', [
                'msiPath' => $msiPath,
            ]);
            // #endregion
            return 0;
        }

        if ($this->msiChecksumOk($cachePath)) {
            if (!@copy($cachePath, $msiPath)) {
                $this->appendLog($logPath, "[error] failed copying cached MSI $cachePath -> $msiPath");
                return 2;
            }
            $this->appendLog($logPath, '[ok] WinFsp MSI copied from build-host cache');
            return 0;
        }

        $this->appendLog($logPath, '[info] fetching WinFsp MSI from ' . self::WINFSP_MSI_URL);
        $tmp = $msiPath . '.tmp';
        @unlink($tmp);
        $cmd = [
            'curl', '-fsSL',
            '--connect-timeout', '30',
            '--max-time', '300',
            '-o', $tmp,
            self::WINFSP_MSI_URL,
        ];
        $runner = new ProcRunner();
        $rc = $runner->run($cmd, $logPath);
        if ($rc !== 0 || !is_file($tmp)) {
            $this->appendLog($logPath, '[error] WinFsp MSI download failed');
            // #region agent log
            $this->debugLog('H4', 'WindowsBuildCloudNAS.php:msi-download-failed', 'MSI download failed', [
                'exitCode' => $rc,
            ]);
            // #endregion
            @unlink($tmp);
            return $rc !== 0 ? $rc : 2;
        }

        $sha = hash_file('sha256', $tmp);
        if (!hash_equals(self::WINFSP_MSI_SHA256, (string) $sha)) {
            $this->appendLog($logPath, "[error] WinFsp MSI SHA-256 mismatch: got $sha expected " . self::WINFSP_MSI_SHA256);
            @unlink($tmp);
            // #region agent log
            $this->debugLog('H4', 'WindowsBuildCloudNAS.php:msi-sha-fail', 'MSI checksum mismatch', [
                'got' => $sha,
            ]);
            // #endregion
            return 2;
        }

        if (!@rename($tmp, $msiPath)) {
            $this->appendLog($logPath, "[error] failed to move MSI into place at $msiPath");
            @unlink($tmp);
            return 2;
        }

        if (!is_dir(dirname($cachePath))) {
            @mkdir(dirname($cachePath), 0755, true);
        }
        @copy($msiPath, $cachePath);

        $this->appendLog($logPath, '[ok] WinFsp MSI downloaded and verified');
        // #region agent log
        $this->debugLog('H4', 'WindowsBuildCloudNAS.php:msi-ok', 'MSI downloaded and verified', [
            'bytes' => filesize($msiPath),
        ]);
        // #endregion
        return 0;
    }

    private function msiChecksumOk(string $path): bool
    {
        if (!is_file($path) || filesize($path) < 1024) {
            return false;
        }
        $sha = hash_file('sha256', $path);
        return is_string($sha) && hash_equals(self::WINFSP_MSI_SHA256, $sha);
    }

    /** @param array<string,mixed> $data */
    private function debugLog(string $hypothesisId, string $location, string $message, array $data): void
    {
        $line = json_encode([
            'sessionId' => 'acfd10',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
            'runId' => 'cloudnas-agentbuild',
        ], JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }
        @file_put_contents('/var/www/eazybackup.ca/.cursor/debug-acfd10.log', $line . "\n", FILE_APPEND);
    }
}
