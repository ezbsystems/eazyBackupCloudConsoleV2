//go:build windows

package recoverymedia

import (
	"archive/zip"
	"bufio"
	"bytes"
	"crypto/sha1"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"os/exec"
	"path"
	"path/filepath"
	"strconv"
	"strings"
	"syscall"
	"time"
)

type Disk struct {
	Number         int64  `json:"number"`
	Name           string `json:"name"`
	Model          string `json:"model,omitempty"`
	DriveLetters   string `json:"drive_letters,omitempty"`
	SizeBytes      int64  `json:"size_bytes"`
	PartitionStyle string `json:"partition_style,omitempty"`
}

func DownloadWithProgress(sourceURL, dest string, update func(int, int64, int64)) error {
	resp, err := http.Get(sourceURL)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("download failed: %s", resp.Status)
	}
	out, err := os.Create(dest)
	if err != nil {
		return err
	}
	defer out.Close()

	var written int64
	total := resp.ContentLength
	started := time.Now()
	buf := make([]byte, 4*1024*1024)
	for {
		n, err := resp.Body.Read(buf)
		if n > 0 {
			if _, werr := out.Write(buf[:n]); werr != nil {
				return werr
			}
			written += int64(n)
			var speed int64
			var eta int64
			elapsed := time.Since(started).Seconds()
			if elapsed > 0 {
				speed = int64(float64(written) / elapsed)
				if speed > 0 && total > 0 && total > written {
					eta = (total - written) / speed
				}
			}
			if update != nil {
				if total > 0 {
					update(int(float64(written)/float64(total)*100), speed, eta)
				} else {
					update(0, speed, 0)
				}
			}
		}
		if err == io.EOF {
			break
		}
		if err != nil {
			return err
		}
	}
	return nil
}

func VerifyFileChecksum(path, expected string) error {
	f, err := os.Open(path)
	if err != nil {
		return err
	}
	defer f.Close()
	h := sha256.New()
	if _, err := io.Copy(h, f); err != nil {
		return err
	}
	sum := hex.EncodeToString(h.Sum(nil))
	if !strings.EqualFold(sum, expected) {
		return fmt.Errorf("checksum mismatch")
	}
	return nil
}

func WriteWinPEISOToDisk(isoPath string, diskNumber int64, update func(int, int64, int64)) error {
	if update != nil {
		update(2, 0, 0)
	}
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$diskNumber = %d
$isoPath = %s
$targetVolumeLabel = 'eazyBackup_Recovery_Media'

$disk = Get-Disk -Number $diskNumber -ErrorAction Stop
if ($disk.IsOffline) {
  try { Set-Disk -Number $diskNumber -IsOffline $false -ErrorAction Stop | Out-Null } catch { throw ("Unable to bring disk online. " + $_.Exception.Message) }
}
$disk = Get-Disk -Number $diskNumber -ErrorAction Stop
if ($disk.IsReadOnly) {
  try { Set-Disk -Number $diskNumber -IsReadOnly $false -ErrorAction Stop | Out-Null } catch {
    try {
      Get-Partition -DiskNumber $diskNumber -ErrorAction SilentlyContinue | ForEach-Object {
        Set-Partition -DiskNumber $diskNumber -PartitionNumber $_.PartitionNumber -IsReadOnly $false -ErrorAction SilentlyContinue | Out-Null
      }
    } catch {}
  }
  $disk = Get-Disk -Number $diskNumber -ErrorAction Stop
  if ($disk.IsReadOnly) { throw 'USB device is read-only. Disable write protection and try again.' }
}

Write-Output '__STAGE__|10'
$usbLetter = $null
try {
  Clear-Disk -Number $diskNumber -RemoveData -Confirm:$false -ErrorAction Stop | Out-Null
  Initialize-Disk -Number $diskNumber -PartitionStyle MBR -ErrorAction Stop | Out-Null
  $part = New-Partition -DiskNumber $diskNumber -UseMaximumSize -AssignDriveLetter -ErrorAction Stop
  $vol = Format-Volume -Partition $part -FileSystem FAT32 -NewFileSystemLabel $targetVolumeLabel -Confirm:$false -ErrorAction Stop
  $usbLetter = $vol.DriveLetter
  if (-not $usbLetter) { $usbLetter = (Get-Partition -DiskNumber $diskNumber | Get-Volume | Select-Object -First 1 -ExpandProperty DriveLetter) }
} catch {
  $prepErr = $_
  $existing = $null
  try { $existing = Get-Partition -DiskNumber $diskNumber -ErrorAction SilentlyContinue | Where-Object { $_.DriveLetter } | Select-Object -First 1 } catch {}
  if (-not $existing) { throw ('Administrator privileges are required to prepare this USB drive. Original error: ' + $prepErr.Exception.Message) }
  $usbLetter = $existing.DriveLetter
  if (-not $usbLetter) { throw ('Failed to determine USB drive letter. Original error: ' + $prepErr.Exception.Message) }
  cmd /c ('format ' + $usbLetter + ': /FS:FAT32 /Q /V:' + $targetVolumeLabel + ' /Y') | Out-Null
}
if (-not $usbLetter) { throw 'Failed to determine USB drive letter' }
try { Set-Volume -DriveLetter $usbLetter -NewFileSystemLabel $targetVolumeLabel -ErrorAction SilentlyContinue | Out-Null } catch {}
try { Get-ChildItem -Path ($usbLetter + ':\*') -Force -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue } catch {}

$img = $null
try {
  Write-Output '__STAGE__|30'
  $img = Mount-DiskImage -ImagePath $isoPath -PassThru
  Start-Sleep -Milliseconds 500
  $isoLetter = (($img | Get-Volume | Select-Object -First 1).DriveLetter)
  if (-not $isoLetter) { throw 'Failed to determine ISO drive letter' }
  $srcRoot = $isoLetter + ':\'
  $dstRoot = $usbLetter + ':\'
  $files = Get-ChildItem -LiteralPath $srcRoot -Recurse -File -Force -ErrorAction Stop
  $totalBytes = 0
  foreach ($f in $files) { $totalBytes += [int64]$f.Length }
  if ($totalBytes -le 0) { $totalBytes = 1 }
  Write-Output '__STAGE__|50'
  $copiedBytes = 0
  foreach ($f in $files) {
    $relPath = $f.FullName.Substring($srcRoot.Length).TrimStart('\')
    $destFile = Join-Path $dstRoot $relPath
    $destDir = [System.IO.Path]::GetDirectoryName($destFile)
    if ($destDir -and -not (Test-Path -LiteralPath $destDir)) { New-Item -ItemType Directory -Path $destDir -Force -ErrorAction Stop | Out-Null }
    Copy-Item -LiteralPath $f.FullName -Destination $destFile -Force -ErrorAction Stop
    $copiedBytes += [int64]$f.Length
    Write-Output ('__PROGRESS__|' + $copiedBytes + '|' + $totalBytes)
  }
  Write-Output '__STAGE__|95'
} finally {
  if ($img) { Dismount-DiskImage -ImagePath $isoPath -ErrorAction SilentlyContinue | Out-Null }
}
`, diskNumber, psQuote(isoPath))

	if update != nil {
		update(10, 0, 0)
	}
	started := time.Now()
	err := runPowerShellWithOutput(script, func(line string) {
		if update == nil {
			return
		}
		if strings.HasPrefix(line, "__STAGE__|") {
			stageText := strings.TrimSpace(strings.TrimPrefix(line, "__STAGE__|"))
			stage, convErr := strconv.Atoi(stageText)
			if convErr == nil {
				update(stage, 0, 0)
			}
			return
		}
		if strings.HasPrefix(line, "__PROGRESS__|") {
			parts := strings.Split(line, "|")
			if len(parts) != 3 {
				return
			}
			copied, cErr := strconv.ParseInt(parts[1], 10, 64)
			total, tErr := strconv.ParseInt(parts[2], 10, 64)
			if cErr != nil || tErr != nil || total <= 0 {
				return
			}
			elapsed := time.Since(started).Seconds()
			var speed int64
			var eta int64
			if elapsed > 0 {
				speed = int64(float64(copied) / elapsed)
				if speed > 0 && copied < total {
					eta = (total - copied) / speed
				}
			}
			pct := 50 + int((float64(copied)/float64(total))*45.0)
			if pct < 50 {
				pct = 50
			}
			if pct > 95 {
				pct = 95
			}
			update(pct, speed, eta)
		}
	})
	if err != nil {
		return err
	}
	if update != nil {
		update(100, 0, 0)
	}
	return nil
}

func WriteImageToDisk(imagePath string, diskNumber int64, update func(int, int64, int64)) error {
	{
		cmd := exec.Command("powershell.exe", "-NoProfile", "-Command", fmt.Sprintf("Set-Disk -Number %d -IsOffline $true; Set-Disk -Number %d -IsReadOnly $false", diskNumber, diskNumber))
		cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
		_ = cmd.Run()
	}
	imageFile, err := os.Open(imagePath)
	if err != nil {
		return err
	}
	defer imageFile.Close()

	diskPath := fmt.Sprintf(`\\.\PhysicalDrive%d`, diskNumber)
	diskFile, err := os.OpenFile(diskPath, os.O_WRONLY, 0)
	if err != nil {
		return fmt.Errorf("open target disk: %w", err)
	}
	defer diskFile.Close()

	info, _ := imageFile.Stat()
	total := info.Size()
	var written int64
	started := time.Now()
	buf := make([]byte, 4*1024*1024)
	for {
		n, err := imageFile.Read(buf)
		if n > 0 {
			if _, werr := diskFile.Write(buf[:n]); werr != nil {
				return werr
			}
			written += int64(n)
			var speed int64
			var eta int64
			elapsed := time.Since(started).Seconds()
			if elapsed > 0 {
				speed = int64(float64(written) / elapsed)
				if speed > 0 && total > 0 && total > written {
					eta = (total - written) / speed
				}
			}
			if update != nil {
				if total > 0 {
					update(int(float64(written)/float64(total)*100), speed, eta)
				} else {
					update(0, speed, 0)
				}
			}
		}
		if err == io.EOF {
			break
		}
		if err != nil {
			return err
		}
	}
	{
		cmd := exec.Command("powershell.exe", "-NoProfile", "-Command", fmt.Sprintf("Set-Disk -Number %d -IsOffline $false", diskNumber))
		cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
		_ = cmd.Run()
	}
	return nil
}

func EjectUSBDisk(diskNumber int64) (string, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$disk = %d
$letters = @()
try { $letters = @(Get-Partition -DiskNumber $disk -ErrorAction SilentlyContinue | Where-Object { $_.DriveLetter } | ForEach-Object { $_.DriveLetter + ':' }) } catch {}
$ejected = $false
if ($letters.Count -gt 0) {
  try {
    $shell = New-Object -ComObject Shell.Application
    foreach ($letter in $letters) {
      $item = $shell.Namespace(17).ParseName($letter)
      if ($item) { $item.InvokeVerb('Eject'); $ejected = $true }
    }
  } catch {}
}
if (-not $ejected) { Set-Disk -Number $disk -IsOffline $true | Out-Null; $ejected = $true }
if (-not $ejected) { throw 'Unable to eject USB drive.' }
Write-Output 'USB is safe to remove.'
`, diskNumber)
	out, err := runPowerShell(script)
	if err != nil {
		return "", err
	}
	msg := strings.TrimSpace(out)
	if msg == "" {
		msg = "USB is safe to remove."
	}
	return msg, nil
}

func ResolveUSBDiskRoot(diskNumber int64) (string, error) {
	ps := fmt.Sprintf(`
$p = Get-Partition -DiskNumber %d -ErrorAction SilentlyContinue | Where-Object { $_.DriveLetter } | Select-Object -First 1
if (-not $p) { throw 'Drive letter not found' }
Write-Output ($p.DriveLetter + ':\')
`, diskNumber)
	out, err := runPowerShell(ps)
	if err != nil {
		return "", err
	}
	root := strings.TrimSpace(out)
	if root == "" {
		return "", fmt.Errorf("unable to resolve USB drive root")
	}
	return root, nil
}

func ListRemovableDisks() ([]Disk, error) {
	ps := `
$disks = Get-Disk | Where-Object { $_.BusType -eq 'USB' -and $_.OperationalStatus -eq 'Online' } | ForEach-Object {
    $letters = @()
    try { $letters = @(Get-Partition -DiskNumber $_.Number -ErrorAction SilentlyContinue | Where-Object { $_.DriveLetter } | ForEach-Object { $_.DriveLetter + ':' }) } catch {}
    [pscustomobject]@{
        number = $_.Number
        name = $_.FriendlyName
        model = $_.Model
        drive_letters = ($letters -join ', ')
        size_bytes = $_.Size
        partition_style = $_.PartitionStyle
    }
}
$disks | ConvertTo-Json -Depth 3
`
	out, err := runPowerShell(ps)
	if err != nil {
		return nil, err
	}
	var disks []Disk
	if err := json.Unmarshal([]byte(out), &disks); err != nil {
		var single Disk
		if err := json.Unmarshal([]byte(out), &single); err == nil && single.Name != "" {
			disks = append(disks, single)
		} else {
			return nil, fmt.Errorf("failed to parse disk list: %w", err)
		}
	}
	return disks, nil
}

func UnzipFile(zipPath, destDir string) error {
	r, err := zip.OpenReader(zipPath)
	if err != nil {
		return err
	}
	defer r.Close()
	absDest, err := filepath.Abs(destDir)
	if err != nil {
		return err
	}
	cleanDest := filepath.Clean(absDest)
	if err := os.MkdirAll(cleanDest, 0o755); err != nil {
		return err
	}
	cleanPrefix := cleanDest + string(os.PathSeparator)
	writtenFiles := 0
	for _, f := range r.File {
		if f.FileInfo().IsDir() {
			continue
		}
		targetPath := normalizeZipTargetPath(f.Name, cleanDest)
		if targetPath == "" {
			continue
		}
		if targetPath != cleanDest && !strings.HasPrefix(targetPath, cleanPrefix) {
			continue
		}
		targetPath = shortenPathIfNeeded(targetPath, cleanDest)
		if err := os.MkdirAll(filepath.Dir(targetPath), 0o755); err != nil {
			return err
		}
		rc, err := f.Open()
		if err != nil {
			return err
		}
		out, err := os.Create(targetPath)
		if err != nil {
			rc.Close()
			return err
		}
		if _, err := io.Copy(out, rc); err != nil {
			out.Close()
			rc.Close()
			return err
		}
		out.Close()
		rc.Close()
		writtenFiles++
	}
	if writtenFiles == 0 {
		return fmt.Errorf("archive did not contain any extractable files")
	}
	return nil
}

func normalizeZipTargetPath(rawName, cleanDest string) string {
	name := strings.TrimSpace(strings.ReplaceAll(rawName, "\\", "/"))
	if name == "" {
		return ""
	}
	if idx := strings.Index(name, ":"); idx >= 0 {
		// Drop Windows volume prefix from archive entries, eg "E:\e3\drivers\broad\..."
		name = name[idx+1:]
	}
	name = strings.TrimLeft(name, "/")
	clean := path.Clean(name)
	if clean == "." || clean == "/" || clean == "" {
		return ""
	}
	parts := strings.Split(clean, "/")
	filtered := make([]string, 0, len(parts))
	for _, p := range parts {
		p = sanitizeWindowsPathComponent(strings.TrimSpace(p))
		if p == "" || p == "." {
			continue
		}
		if p == ".." {
			return ""
		}
		filtered = append(filtered, p)
	}
	if len(filtered) == 0 {
		return ""
	}

	// If archive entries include absolute workstation path components, trim known prefixes.
	destLeaf := strings.ToLower(filepath.Base(cleanDest)) // source|broad
	if len(filtered) >= 3 &&
		strings.EqualFold(filtered[0], "e3") &&
		strings.EqualFold(filtered[1], "drivers") &&
		strings.EqualFold(filtered[2], destLeaf) {
		filtered = filtered[3:]
	}
	if len(filtered) > 0 && strings.EqualFold(filtered[0], destLeaf) {
		filtered = filtered[1:]
	}
	if len(filtered) == 0 {
		return ""
	}

	return filepath.Join(cleanDest, filepath.Join(filtered...))
}

func sanitizeWindowsPathComponent(part string) string {
	part = strings.TrimSpace(part)
	if part == "" {
		return ""
	}
	replacer := strings.NewReplacer(
		":", "_",
		"*", "_",
		"?", "_",
		"\"", "_",
		"<", "_",
		">", "_",
		"|", "_",
	)
	part = replacer.Replace(part)
	part = strings.TrimRight(part, ". ")
	if part == "" {
		return ""
	}
	// Guard reserved device names.
	l := strings.ToLower(part)
	reserved := map[string]bool{
		"con": true, "prn": true, "aux": true, "nul": true,
		"com1": true, "com2": true, "com3": true, "com4": true, "com5": true, "com6": true, "com7": true, "com8": true, "com9": true,
		"lpt1": true, "lpt2": true, "lpt3": true, "lpt4": true, "lpt5": true, "lpt6": true, "lpt7": true, "lpt8": true, "lpt9": true,
	}
	if reserved[l] {
		part = "_" + part
	}
	return part
}

func shortenPathIfNeeded(targetPath, cleanDest string) string {
	// Keep path under a conservative threshold to avoid Windows/FAT32 path issues.
	const maxPathLen = 235
	if len(targetPath) <= maxPathLen {
		return targetPath
	}
	rel, err := filepath.Rel(cleanDest, targetPath)
	if err != nil {
		return targetPath
	}
	base := filepath.Base(rel)
	ext := filepath.Ext(base)
	name := strings.TrimSuffix(base, ext)
	if len(name) > 48 {
		name = name[:48]
	}
	dirKey := strings.ToLower(filepath.ToSlash(filepath.Dir(rel)))
	sum := sha1.Sum([]byte(dirKey))
	shortDir := filepath.Join(cleanDest, "_drv", hex.EncodeToString(sum[:8]))
	shortName := name + "-" + hex.EncodeToString(sum[8:12]) + ext
	return filepath.Join(shortDir, shortName)
}

func runPowerShell(script string) (string, error) {
	cmd := exec.Command("powershell.exe", "-NoProfile", "-Command", script)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	var buf bytes.Buffer
	cmd.Stdout = &buf
	cmd.Stderr = &buf
	if err := cmd.Run(); err != nil {
		return "", fmt.Errorf("powershell failed: %v (%s)", err, buf.String())
	}
	return strings.TrimSpace(buf.String()), nil
}

func runPowerShellWithOutput(script string, onLine func(string)) error {
	cmd := exec.Command("powershell.exe", "-NoProfile", "-Command", script)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	stdout, err := cmd.StdoutPipe()
	if err != nil {
		return err
	}
	stderr, err := cmd.StderrPipe()
	if err != nil {
		return err
	}
	if err := cmd.Start(); err != nil {
		return err
	}

	done := make(chan struct{})
	var outMu bytes.Buffer
	readPipe := func(r io.Reader) {
		sc := bufio.NewScanner(r)
		buf := make([]byte, 0, 64*1024)
		sc.Buffer(buf, 2*1024*1024)
		for sc.Scan() {
			line := sc.Text()
			if onLine != nil {
				onLine(strings.TrimSpace(line))
			}
			outMu.WriteString(line)
			outMu.WriteByte('\n')
		}
	}
	go func() { readPipe(stdout); done <- struct{}{} }()
	go func() { readPipe(stderr); done <- struct{}{} }()
	<-done
	<-done
	if err := cmd.Wait(); err != nil {
		return fmt.Errorf("powershell failed: %v (%s)", err, strings.TrimSpace(outMu.String()))
	}
	return nil
}

func psQuote(s string) string {
	return "'" + strings.ReplaceAll(s, "'", "''") + "'"
}
