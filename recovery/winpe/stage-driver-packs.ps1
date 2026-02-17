[CmdletBinding()]
param(
  [string]$SourceRoot = "C:\e3\WinPE Drivers",
  [string]$OutputRoot = "C:\e3\drivers",
  [string[]]$Manufacturers = @(),
  [ValidateSet("full", "critical")]
  [string]$Profile = "full",
  [string[]]$CriticalClasses = @("Net", "SCSIAdapter", "HDC"),
  [string[]]$CriticalPathKeywords = @("network", "storage", "raid", "rst", "rste", "vmd", "nvme", "ahci", "sas", "scsi", "hba"),
  [switch]$CleanOutput,
  [switch]$IncludeX86,
  [switch]$InfOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Get-RequestedManufacturerDirs {
  param(
    [string]$Root,
    [string[]]$Requested
  )

  if (-not (Test-Path -LiteralPath $Root)) {
    throw "Source root not found: $Root"
  }

  if (-not $Requested -or $Requested.Count -eq 0) {
    return @(Get-ChildItem -LiteralPath $Root -Directory | Sort-Object Name)
  }

  $dirs = @()
  foreach ($name in $Requested) {
    if ([string]::IsNullOrWhiteSpace($name)) {
      continue
    }
    $path = Join-Path $Root $name
    if (Test-Path -LiteralPath $path) {
      $dirs += Get-Item -LiteralPath $path
    } else {
      Write-Warning "Manufacturer directory not found, skipping: $path"
    }
  }
  return $dirs
}

function Is-X86Path {
  param([string]$PathValue)
  return ($PathValue -match '(?i)\\x86(\\|$)') -or
         ($PathValue -match '(?i)\\i386(\\|$)') -or
         ($PathValue -match '(?i)\\win32(\\|$)')
}

function Get-InfClassName {
  param([string]$InfPath)

  try {
    $lines = Get-Content -LiteralPath $InfPath -Encoding Unicode -TotalCount 300 -ErrorAction Stop
  } catch {
    try {
      $lines = Get-Content -LiteralPath $InfPath -Encoding UTF8 -TotalCount 300 -ErrorAction Stop
    } catch {
      try {
        $lines = Get-Content -LiteralPath $InfPath -TotalCount 300 -ErrorAction Stop
      } catch {
        return ""
      }
    }
  }

  foreach ($line in $lines) {
    $trim = $line.Trim()
    if ($trim -match '^(?i)class\s*=\s*(.+)$') {
      return $matches[1].Trim()
    }
  }
  return ""
}

function Is-CriticalDriverInf {
  param(
    [string]$InfPath,
    [string]$ClassName,
    [string[]]$AllowedClasses,
    [string[]]$PathKeywords
  )

  foreach ($cls in $AllowedClasses) {
    if ([string]::Equals($ClassName, $cls, [System.StringComparison]::OrdinalIgnoreCase)) {
      return $true
    }
  }

  $pathLower = $InfPath.ToLowerInvariant()
  foreach ($kw in $PathKeywords) {
    if ([string]::IsNullOrWhiteSpace($kw)) {
      continue
    }
    if ($pathLower.Contains($kw.ToLowerInvariant())) {
      return $true
    }
  }

  return $false
}

function Should-SkipCompanionFile {
  param([System.IO.FileInfo]$FileInfo)
  # Keep typical driver payload files, skip bulky installer/archive artifacts.
  $skipExt = @(".exe", ".msi", ".cab", ".zip", ".7z", ".rar")
  return $skipExt -contains $FileInfo.Extension.ToLowerInvariant()
}

if ($CleanOutput -and (Test-Path -LiteralPath $OutputRoot)) {
  Write-Host "Removing existing output root: $OutputRoot"
  Remove-Item -LiteralPath $OutputRoot -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $OutputRoot | Out-Null

$manufacturerDirs = Get-RequestedManufacturerDirs -Root $SourceRoot -Requested $Manufacturers
if ($manufacturerDirs.Count -eq 0) {
  throw "No manufacturer directories found under: $SourceRoot"
}

$packageDirToVendor = @{}
$packageDirClasses = @{}
$packageDirSelectedInfNames = @{}
$totalInfScanned = 0
$skippedX86Inf = 0
$filteredNonCriticalInf = 0

foreach ($vendorDir in $manufacturerDirs) {
  $vendorName = $vendorDir.Name
  Write-Host "Scanning: $($vendorDir.FullName)"

  $infFiles = @(Get-ChildItem -LiteralPath $vendorDir.FullName -Recurse -File -Filter *.inf -ErrorAction SilentlyContinue)
  foreach ($inf in $infFiles) {
    $totalInfScanned++
    if ((-not $IncludeX86) -and (Is-X86Path -PathValue $inf.FullName)) {
      $skippedX86Inf++
      continue
    }
    $className = Get-InfClassName -InfPath $inf.FullName
    if ($Profile -eq "critical") {
      if (-not (Is-CriticalDriverInf -InfPath $inf.FullName -ClassName $className -AllowedClasses $CriticalClasses -PathKeywords $CriticalPathKeywords)) {
        $filteredNonCriticalInf++
        continue
      }
    }
    $dirKey = $inf.Directory.FullName.ToLowerInvariant()
    if (-not $packageDirToVendor.ContainsKey($dirKey)) {
      $packageDirToVendor[$dirKey] = [PSCustomObject]@{
        Vendor      = $vendorName
        PackagePath = $inf.Directory.FullName
      }
      $packageDirClasses[$dirKey] = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)
      $packageDirSelectedInfNames[$dirKey] = [System.Collections.Generic.HashSet[string]]::new([System.StringComparer]::OrdinalIgnoreCase)
    }
    [void]$packageDirSelectedInfNames[$dirKey].Add($inf.Name)
    if ($className -ne "") {
      [void]$packageDirClasses[$dirKey].Add($className)
    }
  }
}

$packageEntries = @($packageDirToVendor.Values | Sort-Object Vendor, PackagePath)
if ($packageEntries.Count -eq 0) {
  throw "No matching driver packages found (.inf). Check source paths and architecture filters."
}

$copiedPackages = 0
$copiedInfFiles = 0
$copiedCompanionFiles = 0

foreach ($entry in $packageEntries) {
  $dirKey = $entry.PackagePath.ToLowerInvariant()
  $selectedInfNames = $packageDirSelectedInfNames[$dirKey]
  if ($null -eq $selectedInfNames -or $selectedInfNames.Count -eq 0) {
    continue
  }

  $vendorRoot = Join-Path $SourceRoot $entry.Vendor
  $relative = $entry.PackagePath.Substring($vendorRoot.Length).TrimStart('\')
  if ([string]::IsNullOrWhiteSpace($relative)) {
    $relative = Split-Path -Path $entry.PackagePath -Leaf
  }

  $destDir = Join-Path (Join-Path $OutputRoot $entry.Vendor) $relative
  New-Item -ItemType Directory -Force -Path $destDir | Out-Null

  $files = @(Get-ChildItem -LiteralPath $entry.PackagePath -File -ErrorAction SilentlyContinue)
  if ($files.Count -eq 0) {
    continue
  }

  $copiedThisPackage = $false
  foreach ($file in $files) {
    if ($file.Extension.Equals(".inf", [System.StringComparison]::OrdinalIgnoreCase)) {
      if (-not $selectedInfNames.Contains($file.Name)) {
        continue
      }
      Copy-Item -LiteralPath $file.FullName -Destination (Join-Path $destDir $file.Name) -Force
      $copiedInfFiles++
      $copiedThisPackage = $true
      continue
    }

    if ($InfOnly) {
      continue
    }
    if (Should-SkipCompanionFile -FileInfo $file) {
      continue
    }

    Copy-Item -LiteralPath $file.FullName -Destination (Join-Path $destDir $file.Name) -Force
    $copiedCompanionFiles++
    $copiedThisPackage = $true
  }

  if ($copiedThisPackage) {
    $copiedPackages++
  }
}

$reportPath = Join-Path $OutputRoot "driver-stage-report.txt"
$effectiveClasses = [System.Collections.ArrayList]::new()
foreach ($classSet in $packageDirClasses.Values) {
  foreach ($cls in $classSet) {
    if (-not $effectiveClasses.Contains($cls)) {
      [void]$effectiveClasses.Add($cls)
    }
  }
}
$effectiveClassesSorted = @($effectiveClasses | Sort-Object)

$report = @(
  "SourceRoot=$SourceRoot"
  "OutputRoot=$OutputRoot"
  "Manufacturers=$($manufacturerDirs.Name -join ',')"
  "Profile=$Profile"
  "CriticalClasses=$($CriticalClasses -join ',')"
  "CriticalPathKeywords=$($CriticalPathKeywords -join ',')"
  "IncludeX86=$IncludeX86"
  "InfOnly=$InfOnly"
  "TotalInfScanned=$totalInfScanned"
  "SkippedX86Inf=$skippedX86Inf"
  "FilteredNonCriticalInf=$filteredNonCriticalInf"
  "CopiedPackages=$copiedPackages"
  "CopiedInfFiles=$copiedInfFiles"
  "CopiedCompanionFiles=$copiedCompanionFiles"
  "DetectedClasses=$($effectiveClassesSorted -join ',')"
  "GeneratedAt=$((Get-Date).ToString('s'))"
)
$report | Set-Content -Path $reportPath -Encoding UTF8

Write-Host ""
Write-Host "Driver staging completed."
Write-Host "  Profile:              $Profile"
Write-Host "  Packages staged:      $copiedPackages"
Write-Host "  INF files copied:     $copiedInfFiles"
Write-Host "  Companion files copy: $copiedCompanionFiles"
Write-Host "  Filtered non-critical: $filteredNonCriticalInf"
Write-Host "  Report:               $reportPath"
Write-Host ""

if ($InfOnly) {
  Write-Warning "InfOnly mode was used. WinPE injection may fail without companion files (.sys/.cat/.dll)."
}

Write-Host "Build command example (from recovery\\winpe):"
Write-Host "powershell -ExecutionPolicy Bypass -File .\build.ps1 -RecoveryExe .\e3-recovery-agent.exe -ApiBase `"https://accounts.eazybackup.ca/modules/addons/cloudstorage/api`" -Version `"prod-YYYY.MM.DD`" -DriversDir `"${OutputRoot}`""
