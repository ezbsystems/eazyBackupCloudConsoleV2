param(
  [string]$Arch = "amd64",
  [string]$Version = "",
  [string]$WorkDir = "$PSScriptRoot\\work",
  [string]$OutDir = "$PSScriptRoot\\out",
  [string]$RecoveryExe = "",
  [string]$ApiBase = "",
  [string]$BuildMode = "",
  [string]$AdkRoot = "C:\\Program Files (x86)\\Windows Kits\\10\\Assessment and Deployment Kit",
  [string]$WinPERoot = "C:\\Program Files (x86)\\Windows Kits\\10\\Assessment and Deployment Kit\\Windows Preinstallation Environment",
  [string]$DriversDir = "$PSScriptRoot\\drivers",
  [string]$DriverModel = "",
  [string]$DriverMachine = "",
  [string[]]$ExtraDriverDirs = @(),
  [switch]$ForceUnsignedDrivers,
  [int]$DriverInstallTimeoutSeconds = 120,
  [string[]]$ExcludeDriverPathPatterns = @(),
  [switch]$StrictDriverInjection,
  [switch]$IncludePowerShell
)

$ErrorActionPreference = "Stop"

function Assert-Admin {
  $current = [Security.Principal.WindowsIdentity]::GetCurrent()
  $principal = New-Object Security.Principal.WindowsPrincipal($current)
  if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Please run this script in an elevated PowerShell session."
  }
}

function Assert-Exists($Path, $Message) {
  if (-not (Test-Path $Path)) { throw $Message }
}

function Add-DriverSource {
  param(
    [System.Collections.ArrayList]$List,
    [hashtable]$Seen,
    [string]$Path,
    [string]$Source
  )
  if (-not $Path) {
    return
  }
  if (-not (Test-Path $Path)) {
    Write-Warning "Driver source not found ($Source): $Path"
    return
  }
  $resolved = (Resolve-Path $Path).Path
  $key = $resolved.ToLowerInvariant()
  if ($Seen.ContainsKey($key)) {
    return
  }
  $Seen[$key] = $true
  [void]$List.Add([PSCustomObject]@{
    Path   = $resolved
    Source = $Source
  })
}

function Is-X86Path {
  param([string]$PathValue)
  return ($PathValue -match '(?i)\\x86(\\|$)') -or
         ($PathValue -match '(?i)\\i386(\\|$)') -or
         ($PathValue -match '(?i)\\win32(\\|$)')
}

function Is-ExcludedDriverPath {
  param(
    [string]$InfPath,
    [string[]]$Patterns
  )
  if (-not $Patterns -or $Patterns.Count -eq 0) {
    return $false
  }
  foreach ($pattern in $Patterns) {
    if ([string]::IsNullOrWhiteSpace($pattern)) {
      continue
    }
    if ($InfPath -like $pattern) {
      return $true
    }
  }
  return $false
}

function Invoke-DismAddDriverWithTimeout {
  param(
    [string]$MountPath,
    [string]$InfPath,
    [switch]$ForceUnsigned,
    [int]$TimeoutSeconds
  )

  $args = @(
    "/English",
    "/NoRestart",
    "/Image:$MountPath",
    "/Add-Driver",
    "/Driver:$InfPath"
  )
  if ($ForceUnsigned) {
    $args += "/ForceUnsigned"
  }

  $proc = Start-Process -FilePath "dism.exe" -ArgumentList $args -PassThru -WindowStyle Hidden
  $timedOut = $false
  try {
    Wait-Process -Id $proc.Id -Timeout $TimeoutSeconds -ErrorAction Stop
  } catch {
    $timedOut = $true
    try {
      Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
    } catch {
    }
  }

  if ($timedOut) {
    return [PSCustomObject]@{
      Success  = $false
      TimedOut = $true
      ExitCode = $null
    }
  }

  return [PSCustomObject]@{
    Success  = ($proc.ExitCode -eq 0)
    TimedOut = $false
    ExitCode = $proc.ExitCode
  }
}

Assert-Admin

if (-not $Version) {
  $Version = (Get-Date).ToString("yyyy.MM.dd")
}

if (-not $BuildMode) {
  $BuildMode = "prod"
}

if (-not $ApiBase) {
  $mode = $BuildMode.ToLower()
  if ($mode -eq "dev") {
    $ApiBase = "https://dev.eazybackup.ca/modules/addons/cloudstorage/api"
  } else {
    $ApiBase = "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"
  }
}

if (-not $RecoveryExe) {
  $defaultExe = Join-Path (Split-Path $PSScriptRoot -Parent) "e3-recovery-agent.exe"
  if (Test-Path $defaultExe) {
    $RecoveryExe = $defaultExe
  }
}

Assert-Exists $WinPERoot "WinPE path not found: $WinPERoot"
Assert-Exists $AdkRoot "ADK path not found: $AdkRoot"
Assert-Exists $RecoveryExe "Recovery executable not found. Build e3-recovery-agent.exe and pass -RecoveryExe."

$Copype = Join-Path $WinPERoot "copype.cmd"
$MakeWinPEMedia = Join-Path $WinPERoot "MakeWinPEMedia.cmd"

Assert-Exists $Copype "copype.cmd not found at $Copype"
Assert-Exists $MakeWinPEMedia "MakeWinPEMedia.cmd not found at $MakeWinPEMedia"

New-Item -ItemType Directory -Force -Path $WorkDir | Out-Null
New-Item -ItemType Directory -Force -Path $OutDir | Out-Null

if (Test-Path (Join-Path $WorkDir "media")) {
  $cleanupMount = Join-Path $WorkDir "mount"
  $bootWim = Join-Path $WorkDir "media\sources\boot.wim"
  try {
    $mountedImages = Get-WindowsImage -Mounted | Where-Object {
      ($_.MountPath -and ($_.MountPath -like "$cleanupMount*")) -or
      ($_.ImagePath -and ($_.ImagePath -ieq $bootWim))
    }
    foreach ($img in $mountedImages) {
      Dismount-WindowsImage -Path $img.MountPath -Discard -ErrorAction SilentlyContinue | Out-Null
    }
  } catch {
    Write-Warning "Cleanup: failed to dismount previous WinPE image. Error: $($_.Exception.Message)"
  }

  $removed = $false
  for ($i = 0; $i -lt 3; $i++) {
    try {
      Remove-Item -Recurse -Force $WorkDir
      $removed = $true
      break
    } catch {
      Start-Sleep -Seconds 2
    }
  }
  if (-not $removed) {
    throw "Failed to remove $WorkDir. A previous WinPE mount may still be active. Try running: Dismount-WindowsImage -Path `"$cleanupMount`" -Discard"
  }
  New-Item -ItemType Directory -Force -Path $WorkDir | Out-Null
}

$archNormalized = $Arch.ToLower()
$archCandidates = @($archNormalized)
if ($archNormalized -eq "amd64") {
  $archCandidates += "x64"
} elseif ($archNormalized -eq "x64") {
  $archCandidates += "amd64"
}

$ResolvedArch = $null
$copypeOutput = $null
$WinPERootResolved = (Resolve-Path $WinPERoot).Path
foreach ($cand in $archCandidates) {
  if (-not (Test-Path (Join-Path $WinPERootResolved $cand))) {
    continue
  }
  $copypeCmd = "set WinPERoot=$WinPERootResolved && `"$Copype`" $cand `"$WorkDir`""
  $copypeOutput = & cmd.exe /c $copypeCmd 2>&1
  if ($LASTEXITCODE -eq 0) {
    $ResolvedArch = $cand
    break
  }
  if ($copypeOutput -match "processor architecture was not found") {
    continue
  }
  throw "copype failed (exit $LASTEXITCODE). Output:`n$copypeOutput"
}

if (-not $ResolvedArch) {
  $available = Get-ChildItem -Path $WinPERootResolved -Directory -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Name
  $details = if ($copypeOutput) { "`nLast copype output:`n$copypeOutput" } else { "" }
  $fallbackArch = $archCandidates | Where-Object { Test-Path (Join-Path $WinPERootResolved $_) } | Select-Object -First 1
  if (-not $fallbackArch) {
    throw "WinPE architecture '$Arch' not found under $WinPERootResolved. Available: $($available -join ', '). Try -Arch x64 or -Arch amd64.$details"
  }

  $mediaSource = Join-Path $WinPERootResolved "$fallbackArch\media"
  $wimCandidates = Get-ChildItem -Path (Join-Path $WinPERootResolved $fallbackArch) -Filter winpe.wim -Recurse -ErrorAction SilentlyContinue
  if (-not (Test-Path $mediaSource) -or -not $wimCandidates) {
    throw "copype failed and manual staging is not possible. Missing $mediaSource or winpe.wim. Output:`n$copypeOutput"
  }

  Write-Host "copype failed; falling back to manual WinPE staging with architecture '$fallbackArch'."
  $mediaDest = Join-Path $WorkDir "media"
  $sourcesDest = Join-Path $mediaDest "sources"
  $bootDest = Join-Path $mediaDest "boot"
  $efiDest = Join-Path $mediaDest "efi\microsoft\boot"
  New-Item -ItemType Directory -Force -Path $mediaDest | Out-Null
  New-Item -ItemType Directory -Force -Path $sourcesDest | Out-Null
  Copy-Item -Recurse -Force (Join-Path $mediaSource "*") $mediaDest
  Copy-Item -Force $wimCandidates[0].FullName (Join-Path $sourcesDest "boot.wim")
  New-Item -ItemType Directory -Force -Path $bootDest | Out-Null
  New-Item -ItemType Directory -Force -Path $efiDest | Out-Null

  $etfs = Join-Path $bootDest "etfsboot.com"
  if (-not (Test-Path $etfs)) {
    $etfsCandidate = Get-ChildItem -Path $AdkRoot -Filter etfsboot.com -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($etfsCandidate) {
      Copy-Item -Force $etfsCandidate.FullName $etfs
    }
  }

  $efisys = Join-Path $efiDest "efisys.bin"
  if (-not (Test-Path $efisys)) {
    $efiCandidate = Get-ChildItem -Path $WinPERootResolved -Filter efisys.bin -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $efiCandidate) {
      $efiCandidate = Get-ChildItem -Path $AdkRoot -Filter efisys.bin -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    }
    if ($efiCandidate) {
      Copy-Item -Force $efiCandidate.FullName $efisys
    }
  }
  $ResolvedArch = $fallbackArch
}

$MountDir = Join-Path $WorkDir "mount"
New-Item -ItemType Directory -Force -Path $MountDir | Out-Null

$WimPath = Join-Path $WorkDir "media\sources\boot.wim"
if (-not (Test-Path $WimPath)) {
  $candidates = Get-ChildItem -Path $WorkDir -Filter boot.wim -Recurse -ErrorAction SilentlyContinue
  if ($candidates -and $candidates.Count -gt 0) {
    $WimPath = $candidates[0].FullName
  } else {
    throw "WinPE boot.wim not found under $WorkDir. Check that copype completed and WinPE add-on is installed. Output:`n$copypeOutput"
  }
}
$ResolvedWim = (Resolve-Path $WimPath).Path
$ResolvedMount = (Resolve-Path $MountDir).Path
Mount-WindowsImage -ImagePath $ResolvedWim -Index 1 -Path $ResolvedMount

$OcRoot = Join-Path $WinPERoot "$ResolvedArch\\WinPE_OCs"
$Ocs = @(
  "WinPE-WMI.cab",
  "WinPE-StorageWMI.cab",
  "WinPE-Scripting.cab",
  "WinPE-NetFx.cab",
  "WinPE-HTA.cab"
)
if ($IncludePowerShell) {
  $Ocs += "WinPE-PowerShell.cab"
}

foreach ($oc in $Ocs) {
  $pkg = Join-Path $OcRoot $oc
  if (Test-Path $pkg) {
    try {
      Add-WindowsPackage -Path $MountDir -PackagePath $pkg | Out-Null
    } catch {
      Write-Warning "Failed to add package $pkg. Skipping. Error: $($_.Exception.Message)"
    }
  }
}

$langCandidates = @()
try {
  $uiLang = [System.Globalization.CultureInfo]::InstalledUICulture.Name
  if ($uiLang) { $langCandidates += $uiLang.ToLower() }
} catch {
}
if (-not $langCandidates.Contains("en-us")) {
  $langCandidates += "en-us"
}

$langRoot = $null
foreach ($lang in $langCandidates) {
  $candidate = Join-Path $OcRoot $lang
  if (Test-Path $candidate) {
    $langRoot = $candidate
    break
  }
}

if ($langRoot) {
  $langCabs = @(
    "WinPE-HTA",
    "WinPE-Scripting",
    "WinPE-WMI",
    "WinPE-NetFx"
  )
  if ($IncludePowerShell) {
    $langCabs += "WinPE-PowerShell"
  }
  foreach ($base in $langCabs) {
    $cabName = "${base}_$([IO.Path]::GetFileName($langRoot)).cab"
    $cabPath = Join-Path $langRoot $cabName
    if (Test-Path $cabPath) {
      try {
        Add-WindowsPackage -Path $MountDir -PackagePath $cabPath | Out-Null
      } catch {
        Write-Warning "Failed to add language pack $cabPath. Skipping. Error: $($_.Exception.Message)"
      }
    }
  }
} else {
  Write-Warning "No WinPE language pack folder found under $OcRoot. HTA may not render without a language pack."
}

#
# Driver injection strategy:
#  1) Common NIC pack:    drivers\common-nic\
#  2) Model overlay:      drivers\models\<DriverModel>\
#  3) Machine overlay:    drivers\machines\<DriverMachine>\
#  4) Extra overlays:     -ExtraDriverDirs C:\path\to\driversA,C:\path\to\driversB
#
# Backward compatibility: if no layered dirs/params are provided, inject DriversDir as before.
#
$driverSources = [System.Collections.ArrayList]::new()
$seenDriverSources = @{}
$hasLayeredLayout = (Test-Path (Join-Path $DriversDir "common-nic")) -or
                    (Test-Path (Join-Path $DriversDir "models")) -or
                    (Test-Path (Join-Path $DriversDir "machines"))
$usingOverlays = $hasLayeredLayout -or
                 (-not [string]::IsNullOrWhiteSpace($DriverModel)) -or
                 (-not [string]::IsNullOrWhiteSpace($DriverMachine)) -or
                 ($ExtraDriverDirs.Count -gt 0)

if ($usingOverlays) {
  Add-DriverSource -List $driverSources -Seen $seenDriverSources -Path (Join-Path $DriversDir "common-nic") -Source "common-nic"

  if (-not [string]::IsNullOrWhiteSpace($DriverModel)) {
    Add-DriverSource -List $driverSources -Seen $seenDriverSources -Path (Join-Path (Join-Path $DriversDir "models") $DriverModel) -Source ("model:" + $DriverModel)
  }

  if (-not [string]::IsNullOrWhiteSpace($DriverMachine)) {
    Add-DriverSource -List $driverSources -Seen $seenDriverSources -Path (Join-Path (Join-Path $DriversDir "machines") $DriverMachine) -Source ("machine:" + $DriverMachine)
  }

  foreach ($extraDir in $ExtraDriverDirs) {
    if (-not [string]::IsNullOrWhiteSpace($extraDir)) {
      Add-DriverSource -List $driverSources -Seen $seenDriverSources -Path $extraDir -Source "extra"
    }
  }

  # Allow legacy root-level driver packs to still work in overlay mode.
  if (Test-Path $DriversDir) {
    $rootInf = @(Get-ChildItem -Path $DriversDir -Filter "*.inf" -File -ErrorAction SilentlyContinue)
    if ($rootInf.Count -gt 0) {
      Add-DriverSource -List $driverSources -Seen $seenDriverSources -Path $DriversDir -Source "root-legacy"
    }
  }
} else {
  Add-DriverSource -List $driverSources -Seen $seenDriverSources -Path $DriversDir -Source "drivers-dir"
}

if ($driverSources.Count -gt 0) {
  $driverInjectionAttempts = 0
  $driverInjectionSuccess = 0
  $driverInjectionSkipped = 0
  $driverInjectionExcluded = 0
  $driverInjectionFailures = [System.Collections.ArrayList]::new()
  $driverInjectQueue = [System.Collections.ArrayList]::new()

  foreach ($entry in $driverSources) {
    Write-Host "Scanning driver source [$($entry.Source)] from $($entry.Path)"
    $infFiles = @(Get-ChildItem -Path $entry.Path -Recurse -File -Filter "*.inf" -ErrorAction SilentlyContinue)
    if ($infFiles.Count -eq 0) {
      Write-Warning "No INF files found in driver source: $($entry.Path)"
      continue
    }

    foreach ($inf in $infFiles) {
      if ((($ResolvedArch -eq "amd64") -or ($ResolvedArch -eq "x64")) -and (Is-X86Path -PathValue $inf.FullName)) {
        $driverInjectionSkipped++
        continue
      }
      if (Is-ExcludedDriverPath -InfPath $inf.FullName -Patterns $ExcludeDriverPathPatterns) {
        $driverInjectionExcluded++
        continue
      }
      [void]$driverInjectQueue.Add([PSCustomObject]@{
        Source = $entry.Source
        Inf    = $inf.FullName
      })
    }
  }

  $driverInjectionPlanned = $driverInjectQueue.Count
  Write-Host "Injecting WinPE drivers: planned=$driverInjectionPlanned skipped_x86=$driverInjectionSkipped excluded=$driverInjectionExcluded"

  $progressStep = 25
  $current = 0
  foreach ($driverItem in $driverInjectQueue) {
    $current++
    $pct = 0
    if ($driverInjectionPlanned -gt 0) {
      $pct = [int](($current * 100.0) / $driverInjectionPlanned)
    }
    if (($current -eq 1) -or ($current % $progressStep -eq 0) -or ($current -eq $driverInjectionPlanned)) {
      $name = Split-Path -Path $driverItem.Inf -Leaf
      Write-Host ("Driver inject progress: {0}/{1} ({2}%%) - {3}" -f $current, $driverInjectionPlanned, $pct, $name)
    }
    Write-Progress -Activity "Injecting WinPE drivers" -Status ("{0}/{1} - {2}" -f $current, $driverInjectionPlanned, (Split-Path -Path $driverItem.Inf -Leaf)) -PercentComplete $pct

      $driverInjectionAttempts++
      try {
        $injectResult = Invoke-DismAddDriverWithTimeout -MountPath $MountDir -InfPath $driverItem.Inf -ForceUnsigned:$ForceUnsignedDrivers -TimeoutSeconds $DriverInstallTimeoutSeconds
        if ($injectResult.Success) {
          $driverInjectionSuccess++
        } else {
          $errText = if ($injectResult.TimedOut) {
            "Timed out after $DriverInstallTimeoutSeconds seconds"
          } else {
            "DISM exit code $($injectResult.ExitCode)"
          }
          [void]$driverInjectionFailures.Add([PSCustomObject]@{
            Source = $driverItem.Source
            Inf    = $driverItem.Inf
            Error  = $errText
          })
        }
      } catch {
        [void]$driverInjectionFailures.Add([PSCustomObject]@{
          Source = $driverItem.Source
          Inf    = $driverItem.Inf
          Error  = $_.Exception.Message
        })
      }
  }
  Write-Progress -Activity "Injecting WinPE drivers" -Completed

  $driverReportPath = Join-Path $OutDir ("driver-injection-" + $Version + ".log")
  $driverReport = [System.Collections.ArrayList]::new()
  [void]$driverReport.Add("Driver injection summary")
  [void]$driverReport.Add("BuildVersion=$Version")
  [void]$driverReport.Add("Planned=$driverInjectionPlanned")
  [void]$driverReport.Add("Attempts=$driverInjectionAttempts")
  [void]$driverReport.Add("Success=$driverInjectionSuccess")
  [void]$driverReport.Add("SkippedX86=$driverInjectionSkipped")
  [void]$driverReport.Add("ExcludedByPattern=$driverInjectionExcluded")
  [void]$driverReport.Add("Failed=$($driverInjectionFailures.Count)")
  [void]$driverReport.Add("ForceUnsigned=$ForceUnsignedDrivers")
  [void]$driverReport.Add("DriverInstallTimeoutSeconds=$DriverInstallTimeoutSeconds")
  [void]$driverReport.Add("ExcludeDriverPathPatterns=$($ExcludeDriverPathPatterns -join ';')")
  [void]$driverReport.Add("StrictDriverInjection=$StrictDriverInjection")
  [void]$driverReport.Add("GeneratedAt=$((Get-Date).ToString('s'))")
  if ($driverInjectionFailures.Count -gt 0) {
    [void]$driverReport.Add("")
    [void]$driverReport.Add("Failed INF packages:")
    foreach ($failure in $driverInjectionFailures) {
      [void]$driverReport.Add("[$($failure.Source)] $($failure.Inf)")
      [void]$driverReport.Add("  Error: $($failure.Error)")
    }
  }
  $driverReport | Set-Content -Path $driverReportPath -Encoding UTF8

  Write-Host "Driver injection summary: attempts=$driverInjectionAttempts success=$driverInjectionSuccess skipped_x86=$driverInjectionSkipped excluded=$driverInjectionExcluded failed=$($driverInjectionFailures.Count)"
  Write-Host "Driver injection log: $driverReportPath"

  if ($driverInjectionFailures.Count -gt 0) {
    $failureMessage = "Some driver packages could not be installed. See $driverReportPath"
    if ($StrictDriverInjection) {
      throw $failureMessage
    }
    Write-Warning $failureMessage
  }
} else {
  Write-Host "No optional driver sources found. Building with inbox WinPE drivers only."
}

$RecoveryDir = Join-Path $MountDir "Program Files\\E3Recovery"
New-Item -ItemType Directory -Force -Path $RecoveryDir | Out-Null
Copy-Item -Force $RecoveryExe (Join-Path $RecoveryDir "e3-recovery-agent.exe")

$driverSummary = "inbox-only"
if ($driverSources.Count -gt 0) {
  $driverSummary = ($driverSources | ForEach-Object { "$($_.Source)=$($_.Path)" }) -join "; "
}
$BuildLabel = "BuildMode=$BuildMode`nApiBase=$ApiBase`nVersion=$Version`nBuiltAt=$((Get-Date).ToString('s'))`nDriverModel=$DriverModel`nDriverMachine=$DriverMachine`nDriverSources=$driverSummary"
Set-Content -Path (Join-Path $RecoveryDir "build-info.txt") -Value $BuildLabel

$MshtaPath = Join-Path $MountDir "Windows\\System32\\mshta.exe"
if (-not (Test-Path $MshtaPath)) {
  throw "WinPE HTA component missing. mshta.exe not found in image. Ensure WinPE-HTA optional component installs successfully."
}

$LauncherSource = Join-Path $PSScriptRoot "launcher.hta"
if (-not (Test-Path $LauncherSource)) {
  throw "Launcher HTA not found: $LauncherSource"
}
$LauncherDest = Join-Path $MountDir "Windows\\System32\\e3-launcher.hta"
Copy-Item -Force $LauncherSource $LauncherDest

$LoaderSource = Join-Path $PSScriptRoot "loader.hta"
if (-not (Test-Path $LoaderSource)) {
  throw "Loader HTA not found: $LoaderSource"
}
$LoaderDest = Join-Path $MountDir "Windows\\System32\\e3-loader.hta"
Copy-Item -Force $LoaderSource $LoaderDest

$Launcher = Join-Path $MountDir "Windows\\System32\\e3-recovery-shell.cmd"
@"
@echo off
setlocal enabledelayedexpansion

echo Starting E3 Recovery Environment...
echo.
set "API_BASE=$ApiBase"
set "DRIVER_LOG=%SystemRoot%\Temp\e3-driver-load.log"
echo [%date% %time%] Driver load start>"%DRIVER_LOG%"

set "SOURCE_DRIVER_DIR="
set "BROAD_DRIVER_DIR="
for %%D in (C D E F G H I J K L M N O P Q R S T U V W Y Z) do (
  if exist "%%D:\e3\drivers\source" set "SOURCE_DRIVER_DIR=%%D:\e3\drivers\source"
  if exist "%%D:\e3\drivers\broad" set "BROAD_DRIVER_DIR=%%D:\e3\drivers\broad"
)

if not "%SOURCE_DRIVER_DIR%"=="" (
  echo [%date% %time%] Loading source drivers from %SOURCE_DRIVER_DIR%>>"%DRIVER_LOG%"
  pnputil /add-driver "%SOURCE_DRIVER_DIR%\*.inf" /subdirs /install >>"%DRIVER_LOG%" 2>&1
) else (
  echo [%date% %time%] Source driver directory not found.>>"%DRIVER_LOG%"
)

if not "%BROAD_DRIVER_DIR%"=="" (
  echo [%date% %time%] Loading broad driver pack from %BROAD_DRIVER_DIR%>>"%DRIVER_LOG%"
  pnputil /add-driver "%BROAD_DRIVER_DIR%\*.inf" /subdirs /install >>"%DRIVER_LOG%" 2>&1
) else (
  echo [%date% %time%] Broad driver directory not found.>>"%DRIVER_LOG%"
)
echo [%date% %time%] Running network re-initialization after driver load.>>"%DRIVER_LOG%"
wpeutil InitializeNetwork >>"%DRIVER_LOG%" 2>&1

REM Best-effort clock sync from API host to avoid S3 signature skew errors in WinPE.
echo Syncing system time from API host...
powershell -NoProfile -ExecutionPolicy Bypass -Command "try { $u=[Uri]'$ApiBase'; $origin=($u.Scheme + '://' + $u.Authority); $resp=Invoke-WebRequest -Method Head -Uri $origin -UseBasicParsing; if(-not $resp.Headers.Date){ throw 'Date header missing' }; $d=[DateTime]::Parse($resp.Headers.Date).ToUniversalTime(); Set-Date -Date $d | Out-Null; Write-Host ('Time synced from ' + $origin + ' to ' + $d.ToString('o')); exit 0 } catch { Write-Warning ('Time sync skipped: ' + $_.Exception.Message); exit 1 }"
if errorlevel 1 (
  echo Continuing without time sync.
)
echo.

REM Start the recovery agent in background
echo Starting recovery agent...
echo API base: $ApiBase
start "" /min "%ProgramFiles%\E3Recovery\e3-recovery-agent.exe" --listen 0.0.0.0:8080 --api "$ApiBase"

REM Wait for agent to start listening (up to 10 seconds)
echo Waiting for recovery agent to initialize...
set READY=0
for /l %%i in (1,1,10) do (
  if !READY!==0 (
    timeout /t 1 >nul 2>&1
    netstat -an 2>nul | find ":8080" >nul 2>&1 && set READY=1
  )
)
if !READY!==1 (
  echo Recovery agent started on http://127.0.0.1:8080/
) else (
  echo Warning: Recovery agent may not be ready yet.
)

echo.
echo Launching E3 Recovery Controls...

REM Launch HTA - retry a few times if it fails to start
set HTA_STARTED=0
for /l %%i in (1,1,3) do (
  if !HTA_STARTED!==0 (
    start "" "%SystemRoot%\System32\mshta.exe" "%SystemRoot%\System32\e3-launcher.hta"
    timeout /t 2 >nul 2>&1
    tasklist /fi "imagename eq mshta.exe" 2>nul | find /i "mshta.exe" >nul 2>&1 && set HTA_STARTED=1
    if !HTA_STARTED!==0 (
      echo Retrying HTA launch... attempt %%i
    )
  )
)

if !HTA_STARTED!==1 (
  echo E3 Recovery Controls launched successfully.
) else (
  echo.
  echo Warning: HTA launcher may not have started.
  echo You can manually open: mshta "%SystemRoot%\System32\e3-launcher.hta"
  echo Or access the web UI directly: http://127.0.0.1:8080/
)

echo.
echo ============================================================
echo E3 Recovery Environment Ready
echo Recovery UI: http://127.0.0.1:8080/
echo ============================================================
echo.

REM Keep cmd.exe open for manual commands
cmd.exe /k "echo Type 'mshta %SystemRoot%\System32\e3-launcher.hta' to reopen controls"
"@ | Set-Content -Path $Launcher -Encoding ASCII

$StartNet = Join-Path $MountDir "Windows\\System32\\startnet.cmd"
@"
@echo off
setlocal enabledelayedexpansion

set "_E3_LOADER_FLAG=%SystemRoot%\System32\e3-loader-ready.flag"
if exist "%_E3_LOADER_FLAG%" del /f /q "%_E3_LOADER_FLAG%" >nul 2>&1

echo ============================================================
echo eazyBackup Recovery Environment
echo ============================================================
echo Initializing WinPE services and hardware drivers...
echo.
start "" "%SystemRoot%\System32\mshta.exe" "%SystemRoot%\System32\e3-loader.hta"

start "" /min cmd.exe /c "wpeinit > ""%SystemRoot%\Temp\e3-wpeinit.log"" 2>&1"

set /a E3_DOT=0
:wait_wpeinit
tasklist /fi "imagename eq wpeinit.exe" 2>nul | find /i "wpeinit.exe" >nul
if errorlevel 1 goto wpeinit_done

set /a E3_DOT=(E3_DOT+1)%%4
set "E3_MSG=Initializing recovery environment"
if !E3_DOT!==1 set "E3_MSG=Initializing recovery environment."
if !E3_DOT!==2 set "E3_MSG=Initializing recovery environment.."
if !E3_DOT!==3 set "E3_MSG=Initializing recovery environment..."
<nul set /p "=%E3_MSG%`r"
ping 127.0.0.1 -n 2 >nul
goto wait_wpeinit

:wpeinit_done
echo.
echo WinPE initialization complete.
echo ready>"%_E3_LOADER_FLAG%"
timeout /t 1 >nul 2>&1

call "%SystemRoot%\\System32\\e3-recovery-shell.cmd"
"@ | Set-Content -Path $StartNet -Encoding ASCII

# Note: We do NOT create winpeshl.ini. WinPE's default behavior:
# 1. Boot -> winpeshl.exe runs -> cmd.exe starts
# 2. cmd.exe auto-executes startnet.cmd
# If we set [LaunchApp] AppPath=cmd.exe, it bypasses startnet.cmd execution!

Dismount-WindowsImage -Path $MountDir -Save

$IsoPath = Join-Path $OutDir ("e3-recovery-winpe-$Version.iso")
$OscdimgExe = $null
try {
  $OscdimgExe = (Get-ChildItem -Path $AdkRoot -Filter oscdimg.exe -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1).FullName
} catch {
  $OscdimgExe = $null
}
if ($OscdimgExe) {
  $oscdimgDir = Split-Path $OscdimgExe -Parent
  $env:Path = "$oscdimgDir;$env:Path"
} else {
  Write-Warning "oscdimg.exe not found under $AdkRoot. Install ADK Deployment Tools if ISO creation fails."
}

$null = New-Item -ItemType Directory -Force -Path $OutDir
if (Test-Path $IsoPath) {
  Remove-Item -Force $IsoPath
}

try {
  $probe = Join-Path $OutDir "write_test.tmp"
  Set-Content -Path $probe -Value "test" -Encoding ASCII
  Remove-Item -Force $probe
} catch {
  throw "Output directory is not writable: $OutDir. Error: $($_.Exception.Message)"
}

$makeCmd = "`"$MakeWinPEMedia`" /ISO `"$WorkDir`" `"$IsoPath`""
$makeOutput = & cmd.exe /c $makeCmd 2>&1
$isoCreated = $false
if ($LASTEXITCODE -ne 0) {
  if (Test-Path $IsoPath) {
    Write-Host "MakeWinPEMedia reported failure, but ISO exists at $IsoPath. Continuing."
    $isoCreated = $true
  } elseif ($OscdimgExe) {
    Write-Warning "MakeWinPEMedia failed (exit $LASTEXITCODE). Output:`n$makeOutput"
    $mediaRoot = Join-Path $WorkDir "media"
    $etfsboot = Join-Path $mediaRoot "boot\etfsboot.com"
    $efisys = Join-Path $mediaRoot "efi\microsoft\boot\efisys.bin"
    if (-not (Test-Path $etfsboot)) {
      throw "oscdimg fallback failed: missing boot sector file $etfsboot"
    }
    if (-not (Test-Path $efisys)) {
      throw "oscdimg fallback failed: missing EFI boot file $efisys"
    }
    $oscCmd = "`"$OscdimgExe`" -m -o -u2 -udfver102 -bootdata:2#p0,e,b`"$etfsboot`"#pEF,e,b`"$efisys`" `"$mediaRoot`" `"$IsoPath`""
    $oldEap = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    $oscOutput = & cmd.exe /c $oscCmd 2>&1
    $oscExit = $LASTEXITCODE
    $ErrorActionPreference = $oldEap
    if ($oscExit -ne 0) {
      throw "oscdimg failed (exit $oscExit). Output:`n$oscOutput"
    }
    $isoCreated = $true
  } else {
    throw "MakeWinPEMedia failed and oscdimg.exe was not found. Output:`n$makeOutput"
  }
} else {
  $isoCreated = $true
}

if (-not $isoCreated) {
  throw "WinPE ISO was not created."
}

Copy-Item -Force $IsoPath (Join-Path $OutDir "e3-recovery-winpe.iso")

Write-Host "WinPE ISO created:"
Write-Host "  $IsoPath"
