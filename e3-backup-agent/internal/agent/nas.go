package agent

import (
	"context"
	"fmt"
	"log"
	"net"
	"net/http"
	"os"
	"os/exec"
	"runtime"
	"strings"
	"sync"
	"time"

	"github.com/rclone/rclone/backend/s3"
	"github.com/rclone/rclone/fs/config/configmap"
	"github.com/rclone/rclone/vfs"
	"github.com/rclone/rclone/vfs/vfscommon"
	"golang.org/x/net/webdav"
)

// NASMount represents an active NAS mount
type NASMount struct {
	MountID     int64
	DriveLetter string
	BucketName  string
	Prefix      string
	ReadOnly    bool
	CacheMode   string
	MountedAt   time.Time

	// WebDAV server components
	WebDAV     *http.Server
	VFS        *vfs.VFS
	ServerPort int // Port the WebDAV server is listening on (for debugging/status)
}

// NASManager manages active NAS mounts
type NASManager struct {
	mu     sync.RWMutex
	mounts map[string]*NASMount // keyed by drive letter
}

// Global NAS manager instance
var nasManager = &NASManager{
	mounts: make(map[string]*NASMount),
}

// MountNASPayload contains the payload for nas_mount command
type MountNASPayload struct {
	MountID     int64  `json:"mount_id"`
	Bucket      string `json:"bucket"`
	Prefix      string `json:"prefix"`
	DriveLetter string `json:"drive_letter"`
	ReadOnly    bool   `json:"read_only"`
	CacheMode   string `json:"cache_mode"`
	Endpoint    string `json:"endpoint"`
	AccessKey   string `json:"access_key"`
	SecretKey   string `json:"secret_key"`
	Region      string `json:"region"`
}

// UnmountNASPayload contains the payload for nas_unmount command
type UnmountNASPayload struct {
	MountID     int64  `json:"mount_id"`
	DriveLetter string `json:"drive_letter"`
}

// MountSnapshotPayload contains the payload for nas_mount_snapshot command
type MountSnapshotPayload struct {
	JobID       string `json:"job_id"`
	ManifestID  string `json:"manifest_id"`
	DriveLetter string `json:"drive_letter"`
	Bucket      string `json:"bucket"`
	Prefix      string `json:"prefix"`
	Endpoint    string `json:"endpoint"`
	AccessKey   string `json:"access_key"`
	SecretKey   string `json:"secret_key"`
	Region      string `json:"region"`
}

// mapDriveInUserSession creates a scheduled task that runs in the interactive user's session
// to map the network drive, ensuring it's visible in Explorer. This bypasses service session isolation.
func (r *Runner) mapDriveInUserSession(ctx context.Context, driveLetter string, target string) error {
	taskName := fmt.Sprintf("E3BackupNASMount_%s_%d", driveLetter, time.Now().UnixNano())

	// PowerShell script to create scheduled task in user's session
	// Note: Using [Environment]::NewLine instead of backtick-n for Go raw string compatibility
	psScript := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$taskName = '%s'
$drive = '%s:'
$target = '%s'

# Get the currently logged-in user from Win32_ComputerSystem (most reliable method)
$loggedUser = $null
try {
    $cs = Get-WmiObject -Class Win32_ComputerSystem -ErrorAction SilentlyContinue
    if ($cs -and $cs.UserName) {
        $loggedUser = $cs.UserName
    }
} catch { }

# Fallback: try explorer.exe owner (works when WMI returns null)
if (-not $loggedUser) {
    try {
        $explorer = Get-WmiObject Win32_Process -Filter "Name='explorer.exe'" -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($explorer) {
            $owner = $explorer.GetOwner()
            if ($owner.User) {
                $loggedUser = $owner.Domain + '\' + $owner.User
            }
        }
    } catch { }
}

# Final fallback: query user (may fail with access denied in service context)
if (-not $loggedUser) {
    try {
        $quserOutput = & query user 2>&1 | Out-String
        if ($quserOutput -and $quserOutput -notmatch 'Error') {
            $lines = $quserOutput -split [Environment]::NewLine | Where-Object { $_ -match 'Active' }
            if ($lines) {
                $parts = ($lines[0].Trim()) -split '\s+'
                $userName = $parts[0].TrimStart('>')
                if ($userName) {
                    $loggedUser = $env:USERDOMAIN + '\' + $userName
                }
            }
        }
    } catch { }
}

if (-not $loggedUser) {
    throw "No interactive user session found"
}

Write-Host "Mapping drive for user: $loggedUser"

# Create the scheduled task action
$action = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument "/c net use $drive $target /persistent:no"

# Create trigger for immediate execution
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddSeconds(2)

# Principal: run as the logged-in user in their interactive session
$principal = New-ScheduledTaskPrincipal -UserId $loggedUser -LogonType Interactive -RunLevel Limited

# Register the scheduled task
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Force | Out-Null

# Start the task immediately
Start-ScheduledTask -TaskName $taskName

# Wait for task completion with timeout
$maxWait = 20
$waited = 0
while ($waited -lt $maxWait) {
    Start-Sleep -Milliseconds 500
    $waited += 0.5
    $taskState = (Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue).State
    if ($taskState -eq 'Ready') {
        break
    }
}

# Check result
$taskInfo = Get-ScheduledTaskInfo -TaskName $taskName -ErrorAction SilentlyContinue
$exitCode = $taskInfo.LastTaskResult

# Cleanup the temporary task
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

if ($exitCode -ne 0) {
    throw "net use failed with exit code: $exitCode"
}

Write-Host "Drive $drive mapped successfully in user session"
`, taskName, driveLetter, target)

	cmd := exec.CommandContext(ctx, "powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", psScript)
	output, err := cmd.CombinedOutput()
	if err != nil {
		// Only log if there's useful output (not just whitespace)
		outputStr := strings.TrimSpace(string(output))
		if outputStr != "" {
			log.Printf("agent: user session mapping failed: %s", outputStr)
		}
		return fmt.Errorf("scheduled task mapping failed: %w", err)
	}
	log.Printf("agent: user session mapping output: %s", string(output))
	return nil
}

// notifyShellDriveChange sends comprehensive Windows Shell notifications to refresh Explorer
// This broadcasts multiple notification types to ensure Explorer updates its drive list
func (r *Runner) notifyShellDriveChange(driveLetter string) {
	psScript := fmt.Sprintf(`
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class ShellNotify {
    [DllImport("shell32.dll")]
    public static extern void SHChangeNotify(int wEventId, int uFlags, IntPtr dwItem1, IntPtr dwItem2);
    
    [DllImport("shell32.dll", CharSet = CharSet.Unicode)]
    public static extern void SHChangeNotify(int wEventId, int uFlags, string dwItem1, IntPtr dwItem2);
    
    [DllImport("user32.dll", SetLastError = true)]
    public static extern IntPtr SendMessageTimeout(IntPtr hWnd, uint Msg, UIntPtr wParam, IntPtr lParam, uint fuFlags, uint uTimeout, out UIntPtr lpdwResult);
    
    public static readonly IntPtr HWND_BROADCAST = new IntPtr(0xffff);
    public const uint WM_SETTINGCHANGE = 0x001A;
    public const uint SMTO_ABORTIFHUNG = 0x0002;
}
"@

$drivePath = '%s:\'

# SHCNE_DRIVEADD (0x100) - A drive has been added
[ShellNotify]::SHChangeNotify(0x00000100, 0, [IntPtr]::Zero, [IntPtr]::Zero)

# With specific drive path (SHCNF_PATH = 0x0005)
[ShellNotify]::SHChangeNotify(0x00000100, 0x0005, $drivePath, [IntPtr]::Zero)

# SHCNE_MEDIAINSERTED (0x20) - Media inserted in drive
[ShellNotify]::SHChangeNotify(0x00000020, 0x0005, $drivePath, [IntPtr]::Zero)

# SHCNE_UPDATEDIR (0x1000) - Contents of folder changed
[ShellNotify]::SHChangeNotify(0x00001000, 0x0005, $drivePath, [IntPtr]::Zero)

# Refresh "This PC" / Computer namespace
[ShellNotify]::SHChangeNotify(0x00001000, 0x0005, '::{20D04FE0-3AEA-1069-A2D8-08002B30309D}', [IntPtr]::Zero)

# SHCNE_ASSOCCHANGED (0x08000000) - Forces shell to flush and reload
[ShellNotify]::SHChangeNotify(0x08000000, 0, [IntPtr]::Zero, [IntPtr]::Zero)

# Broadcast WM_SETTINGCHANGE to all windows
$result = [UIntPtr]::Zero
[ShellNotify]::SendMessageTimeout([ShellNotify]::HWND_BROADCAST, [ShellNotify]::WM_SETTINGCHANGE, [UIntPtr]::Zero, [IntPtr]::Zero, [ShellNotify]::SMTO_ABORTIFHUNG, 1000, [ref]$result) | Out-Null

# Force Explorer to refresh by restarting its shell view (gentle method)
$shell = New-Object -ComObject Shell.Application
$shell.Windows() | ForEach-Object { 
    try { $_.Refresh() } catch { }
}
`, driveLetter)

	cmd := exec.Command("powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", psScript)
	output, err := cmd.CombinedOutput()
	if err != nil {
		log.Printf("agent: shell notify warning (non-critical): %v - %s", err, string(output))
	}
}

// executeNASMountCommand handles nas_mount commands
func (r *Runner) executeNASMountCommand(ctx context.Context, cmd PendingCommand) {
	log.Printf("agent: executing NAS mount command %d", cmd.CommandID)

	// Parse payload
	payload := MountNASPayload{}
	if cmd.Payload != nil {
		if v, ok := cmd.Payload["mount_id"].(float64); ok {
			payload.MountID = int64(v)
		}
		if v, ok := cmd.Payload["bucket"].(string); ok {
			payload.Bucket = v
		}
		if v, ok := cmd.Payload["prefix"].(string); ok {
			payload.Prefix = v
		}
		if v, ok := cmd.Payload["drive_letter"].(string); ok {
			payload.DriveLetter = v
		}
		if v, ok := cmd.Payload["read_only"].(bool); ok {
			payload.ReadOnly = v
		}
		if v, ok := cmd.Payload["cache_mode"].(string); ok {
			payload.CacheMode = v
		}
		if v, ok := cmd.Payload["endpoint"].(string); ok {
			payload.Endpoint = v
		}
		if v, ok := cmd.Payload["access_key"].(string); ok {
			payload.AccessKey = v
		}
		if v, ok := cmd.Payload["secret_key"].(string); ok {
			payload.SecretKey = v
		}
		if v, ok := cmd.Payload["region"].(string); ok {
			payload.Region = v
		}
	}

	// Validate required fields
	if payload.Bucket == "" || payload.DriveLetter == "" || payload.AccessKey == "" || payload.SecretKey == "" {
		log.Printf("agent: NAS mount missing required fields")
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing required mount parameters")
		if payload.MountID > 0 {
			_ = r.client.UpdateNASMountStatus(payload.MountID, "error", "missing required mount parameters")
		}
		return
	}

	// Perform mount
	err := r.mountNASDrive(ctx, payload)
	if err != nil {
		log.Printf("agent: NAS mount failed: %v", err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", err.Error())
		// Update mount status to error in dashboard
		if payload.MountID > 0 {
			_ = r.client.UpdateNASMountStatus(payload.MountID, "error", err.Error())
		}
		return
	}

	log.Printf("agent: NAS mount successful: %s: -> %s/%s", payload.DriveLetter, payload.Bucket, payload.Prefix)
	_ = r.client.CompleteCommand(cmd.CommandID, "completed", fmt.Sprintf("mounted %s: to %s", payload.DriveLetter, payload.Bucket))
	// Update mount status to mounted in dashboard
	if payload.MountID > 0 {
		_ = r.client.UpdateNASMountStatus(payload.MountID, "mounted", "")
	}
}

// executeNASUnmountCommand handles nas_unmount commands
func (r *Runner) executeNASUnmountCommand(ctx context.Context, cmd PendingCommand) {
	log.Printf("agent: executing NAS unmount command %d", cmd.CommandID)

	// Parse payload
	driveLetter := ""
	var mountID int64
	if cmd.Payload != nil {
		if v, ok := cmd.Payload["drive_letter"].(string); ok {
			driveLetter = v
		}
		if v, ok := cmd.Payload["mount_id"].(float64); ok {
			mountID = int64(v)
		}
	}

	if driveLetter == "" {
		log.Printf("agent: NAS unmount missing drive letter")
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing drive letter")
		return
	}

	// Perform unmount
	err := r.unmountNASDrive(driveLetter)
	if err != nil {
		log.Printf("agent: NAS unmount failed: %v", err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", err.Error())
		// Update mount status to error in dashboard
		if mountID > 0 {
			_ = r.client.UpdateNASMountStatus(mountID, "error", err.Error())
		}
		return
	}

	log.Printf("agent: NAS unmount successful: %s:", driveLetter)
	_ = r.client.CompleteCommand(cmd.CommandID, "completed", fmt.Sprintf("unmounted %s:", driveLetter))
	// Update mount status to unmounted in dashboard
	if mountID > 0 {
		_ = r.client.UpdateNASMountStatus(mountID, "unmounted", "")
	}
}

// executeNASMountSnapshotCommand handles nas_mount_snapshot commands (Kopia Time Machine)
func (r *Runner) executeNASMountSnapshotCommand(ctx context.Context, cmd PendingCommand) {
	log.Printf("agent: executing NAS mount snapshot command %d", cmd.CommandID)

	// Parse payload
	payload := MountSnapshotPayload{}
	if cmd.Payload != nil {
		if v, ok := cmd.Payload["job_id"].(string); ok {
			payload.JobID = strings.TrimSpace(v)
		}
		if v, ok := cmd.Payload["manifest_id"].(string); ok {
			payload.ManifestID = v
		}
		if v, ok := cmd.Payload["drive_letter"].(string); ok {
			payload.DriveLetter = v
		}
		if v, ok := cmd.Payload["bucket"].(string); ok {
			payload.Bucket = v
		}
		if v, ok := cmd.Payload["prefix"].(string); ok {
			payload.Prefix = v
		}
		if v, ok := cmd.Payload["endpoint"].(string); ok {
			payload.Endpoint = v
		}
		if v, ok := cmd.Payload["access_key"].(string); ok {
			payload.AccessKey = v
		}
		if v, ok := cmd.Payload["secret_key"].(string); ok {
			payload.SecretKey = v
		}
		if v, ok := cmd.Payload["region"].(string); ok {
			payload.Region = v
		}
	}

	if payload.ManifestID == "" || payload.DriveLetter == "" {
		log.Printf("agent: NAS mount snapshot missing required fields")
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing manifest_id or drive_letter")
		return
	}

	// Mount Kopia snapshot using kopia mount command
	err := r.mountKopiaSnapshot(ctx, payload)
	if err != nil {
		log.Printf("agent: NAS mount snapshot failed: %v", err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", err.Error())
		return
	}

	log.Printf("agent: NAS mount snapshot successful: %s: -> snapshot %s", payload.DriveLetter, payload.ManifestID[:12])
	_ = r.client.CompleteCommand(cmd.CommandID, "completed", fmt.Sprintf("mounted snapshot to %s:", payload.DriveLetter))
}

// executeNASUnmountSnapshotCommand handles nas_unmount_snapshot commands
func (r *Runner) executeNASUnmountSnapshotCommand(ctx context.Context, cmd PendingCommand) {
	log.Printf("agent: executing NAS unmount snapshot command %d", cmd.CommandID)

	// For now, use the same unmount logic
	manifestID := ""
	if cmd.Payload != nil {
		if v, ok := cmd.Payload["manifest_id"].(string); ok {
			manifestID = v
		}
	}

	if manifestID == "" {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing manifest_id")
		return
	}

	// Find and unmount by manifest ID
	err := r.unmountKopiaSnapshot(manifestID)
	if err != nil {
		log.Printf("agent: NAS unmount snapshot failed: %v", err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", err.Error())
		return
	}

	_ = r.client.CompleteCommand(cmd.CommandID, "completed", "snapshot unmounted")
}

// mountNASDrive mounts an S3 bucket by starting an embedded WebDAV server and mapping with Windows net use.
// Uses a two-phase approach: first tries to map the drive in the user's interactive session via a scheduled task
// (to ensure visibility in Explorer), then falls back to direct net use if no interactive user is available.
func (r *Runner) mountNASDrive(ctx context.Context, payload MountNASPayload) error {
	if runtime.GOOS != "windows" {
		return fmt.Errorf("NAS mount is only supported on Windows")
	}

	// Normalize drive letter
	driveLetter := strings.ToUpper(strings.TrimSuffix(payload.DriveLetter, ":"))
	if len(driveLetter) != 1 || driveLetter[0] < 'A' || driveLetter[0] > 'Z' {
		return fmt.Errorf("invalid drive letter: %s", payload.DriveLetter)
	}

	// Check if already mounted
	nasManager.mu.RLock()
	if _, exists := nasManager.mounts[driveLetter]; exists {
		nasManager.mu.RUnlock()
		return fmt.Errorf("drive %s: is already mounted", driveLetter)
	}
	nasManager.mu.RUnlock()

	// Build root (bucket + optional prefix)
	root := payload.Bucket
	if payload.Prefix != "" {
		root = payload.Bucket + "/" + strings.TrimPrefix(payload.Prefix, "/")
	}

	// Configure S3 backend programmatically
	// Use "Ceph" provider for Ceph RadosGW or "Minio" for MinIO
	// This affects how bucket listing and certain operations work
	opt := configmap.Simple{
		"provider":          "Ceph", // Better compatibility with Ceph RadosGW
		"access_key_id":     payload.AccessKey,
		"secret_access_key": payload.SecretKey,
		"endpoint":          payload.Endpoint,
		"chunk_size":        "5Mi",
		"copy_cutoff":       "5Gi",
		"upload_cutoff":     "200Mi",
		"force_path_style":  "true", // Required for Ceph/MinIO
		"disable_http2":     "true",
		"no_check_bucket":   "true",
		"list_chunk":        "1000", // Number of items per listing request
	}
	if payload.Region != "" {
		opt["region"] = payload.Region
	} else {
		opt["region"] = "" // Ceph doesn't always need a region
	}

	// Create filesystem
	log.Printf("agent: creating S3 filesystem - endpoint=%s, bucket=%s, prefix=%s, root=%s",
		payload.Endpoint, payload.Bucket, payload.Prefix, root)
	log.Printf("agent: S3 config - provider=Ceph, access_key=%s..., force_path_style=true",
		payload.AccessKey[:8])

	f, err := s3.NewFs(ctx, "cloudnas", root, opt)
	if err != nil {
		return fmt.Errorf("failed to create s3 fs: %w", err)
	}

	// Debug: Get filesystem info
	log.Printf("agent: S3 filesystem type: %s, root: %s", f.Name(), f.Root())

	// Debug: Try to list root directory
	entries, listErr := f.List(ctx, "")
	if listErr != nil {
		log.Printf("agent: WARNING - S3 list root failed: %v", listErr)
		// Try listing with different methods
		log.Printf("agent: Attempting directory check...")
	} else {
		log.Printf("agent: S3 root has %d entries", len(entries))
		for i, entry := range entries {
			if i < 10 { // Log first 10 entries
				log.Printf("agent:   - %s (size=%d, isDir=%v)", entry.Remote(), entry.Size(), entry.Size() == -1)
			}
		}
		if len(entries) > 10 {
			log.Printf("agent:   ... and %d more", len(entries)-10)
		}
	}

	// VFS options
	vfsOpt := vfscommon.DefaultOpt
	switch strings.ToLower(payload.CacheMode) {
	case "off":
		vfsOpt.CacheMode = vfscommon.CacheModeOff
	case "minimal":
		vfsOpt.CacheMode = vfscommon.CacheModeMinimal
	case "writes":
		vfsOpt.CacheMode = vfscommon.CacheModeWrites
	default:
		vfsOpt.CacheMode = vfscommon.CacheModeFull
	}
	if payload.ReadOnly {
		vfsOpt.ReadOnly = true
	}
	vfsInst := vfs.New(f, &vfsOpt)

	// Start WebDAV server backed by VFS
	fsWrapper := &vfsWebDAVFS{vfs: vfsInst}
	handler := &webdav.Handler{
		Prefix:     "/",
		FileSystem: fsWrapper,
		LockSystem: webdav.NewMemLS(),
	}

	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		return fmt.Errorf("failed to listen for webdav: %w", err)
	}
	srv := &http.Server{Handler: handler}

	// Serve in background
	go func() {
		if serveErr := srv.Serve(ln); serveErr != nil && serveErr != http.ErrServerClosed {
			log.Printf("agent: webdav serve error: %v", serveErr)
		}
	}()

	tcpAddr := ln.Addr().(*net.TCPAddr)
	port := tcpAddr.Port

	// Wait for WebDAV server to be ready
	time.Sleep(500 * time.Millisecond)

	// Build WebDAV URL and UNC path
	httpTarget := fmt.Sprintf("http://127.0.0.1:%d/", port)
	uncTarget := fmt.Sprintf("\\\\127.0.0.1@%d\\DavWWWRoot", port)
	log.Printf("agent: mapping drive %s: to %s (UNC: %s)", driveLetter, httpTarget, uncTarget)

	// Ensure WebClient service is running
	startSvc := exec.CommandContext(ctx, "net", "start", "webclient")
	_ = startSvc.Run()
	time.Sleep(300 * time.Millisecond)

	// Use WScript.Network COM object to map the drive
	// This method creates mappings that are visible across the Windows session
	mapScript := fmt.Sprintf(`
$ErrorActionPreference = 'Continue'

# First, remove any existing mapping
try {
    $net = New-Object -ComObject WScript.Network
    $net.RemoveNetworkDrive('%s:', $true, $true) 2>$null
} catch { }

# Also try net use delete
net use %s: /delete /y 2>$null

Start-Sleep -Milliseconds 500

# Map using net use with persistent flag
# Using net use directly is more reliable than WScript.Network for WebDAV
$result = & net use %s: "%s" /persistent:yes 2>&1
Write-Host "net use result: $result"

# If that failed, try WScript.Network as fallback
if ($LASTEXITCODE -ne 0) {
    Write-Host "net use failed, trying WScript.Network..."
    try {
        $network = New-Object -ComObject WScript.Network
        $network.MapNetworkDrive('%s:', '%s', $false)
        Write-Host "WScript.Network mapping succeeded"
    } catch {
        Write-Host "WScript.Network also failed: $_"
    }
}

# Verify the mapping
Start-Sleep -Milliseconds 500
$netuse = & net use 2>&1
Write-Host "Current mappings:"
Write-Host $netuse

# Test if drive is accessible
if (Test-Path '%s:\') {
    Write-Host "SUCCESS: Drive %s: is accessible"
    
    # Access the drive to establish the connection properly
    # This ensures the WebDAV connection is active, not just mapped
    $items = Get-ChildItem '%s:\' -ErrorAction SilentlyContinue | Select-Object -First 5
    foreach ($item in $items) { Write-Host "  - $($item.Name)" }
    
    # Set a friendly name for the drive in Explorer
    $regPath = "HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\MountPoints2\##127.0.0.1@%d#DavWWWRoot"
    try {
        if (!(Test-Path $regPath)) {
            New-Item -Path $regPath -Force | Out-Null
        }
        Set-ItemProperty -Path $regPath -Name "_LabelFromReg" -Value "Cloud NAS (%s)" -Type String -Force
        Write-Host "Drive label set to: Cloud NAS (%s)"
    } catch {
        Write-Host "Could not set drive label: $_"
    }
    
    # Force Explorer to refresh and recognize the drive
    # Broadcast shell notification to ALL Explorer windows
    Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class ShellRefresh {
    [DllImport("shell32.dll")]
    public static extern void SHChangeNotify(int wEventId, int uFlags, IntPtr dwItem1, IntPtr dwItem2);
    
    [DllImport("user32.dll", SetLastError = true)]
    public static extern IntPtr SendMessageTimeout(IntPtr hWnd, uint Msg, UIntPtr wParam, string lParam, uint fuFlags, uint uTimeout, out UIntPtr lpdwResult);
    
    public const int HWND_BROADCAST = 0xffff;
    public const uint WM_SETTINGCHANGE = 0x001A;
    public const uint SMTO_ABORTIFHUNG = 0x0002;
}
"@
    # Notify drive added
    [ShellRefresh]::SHChangeNotify(0x00000100, 0, [IntPtr]::Zero, [IntPtr]::Zero)
    # Notify association changed (forces full refresh)
    [ShellRefresh]::SHChangeNotify(0x08000000, 0, [IntPtr]::Zero, [IntPtr]::Zero)
    # Broadcast settings change
    $result = [UIntPtr]::Zero
    [ShellRefresh]::SendMessageTimeout([IntPtr]([ShellRefresh]::HWND_BROADCAST), [ShellRefresh]::WM_SETTINGCHANGE, [UIntPtr]::Zero, "Environment", [ShellRefresh]::SMTO_ABORTIFHUNG, 1000, [ref]$result)
    
    Write-Host "Shell notifications sent"
} else {
    Write-Host "FAILED: Drive %s: is NOT accessible"
}
`, driveLetter, driveLetter, driveLetter, httpTarget, driveLetter, uncTarget, driveLetter, driveLetter, driveLetter, port, payload.Bucket, payload.Bucket, driveLetter)

	cmd := exec.CommandContext(ctx, "powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", mapScript)
	output, err := cmd.CombinedOutput()
	log.Printf("agent: drive mapping output:\n%s", string(output))

	if err != nil {
		log.Printf("agent: mapping script error (may still work): %v", err)
	}

	// Store the HTTP target for reference
	target := httpTarget

	// Send comprehensive shell notifications to ensure Explorer refreshes its drive list
	r.notifyShellDriveChange(driveLetter)

	// Open the drive directly in Explorer - this forces Explorer to "discover" it
	go func(drv string) {
		time.Sleep(1 * time.Second)
		// Open the drive directly - this creates an Explorer window browsing the drive
		// which forces Windows to fully connect and register it
		openCmd := exec.Command("explorer.exe", drv+":\\")
		_ = openCmd.Start()
	}(driveLetter)

	// Verify the drive is accessible and WebDAV server is responding
	drivePath := driveLetter + ":\\"
	if _, statErr := os.Stat(drivePath); statErr != nil {
		log.Printf("agent: WARNING - drive %s:\\ not accessible: %v", driveLetter, statErr)
	} else {
		log.Printf("agent: drive %s:\\ verified accessible", driveLetter)
		// Also verify we can list directory (proves WebDAV is working)
		if entries, readErr := os.ReadDir(drivePath); readErr != nil {
			log.Printf("agent: WARNING - cannot list drive contents: %v", readErr)
		} else {
			log.Printf("agent: drive %s:\\ has %d entries - WebDAV working correctly", driveLetter, len(entries))
		}
	}

	log.Printf("agent: *** IMPORTANT: Keep this agent running! The WebDAV server will stop if the agent exits. ***")

	// Record mount
	nasManager.mu.Lock()
	nasManager.mounts[driveLetter] = &NASMount{
		MountID:     payload.MountID,
		DriveLetter: driveLetter,
		BucketName:  payload.Bucket,
		Prefix:      payload.Prefix,
		ReadOnly:    payload.ReadOnly,
		CacheMode:   payload.CacheMode,
		MountedAt:   time.Now(),
		WebDAV:      srv,
		VFS:         vfsInst,
		ServerPort:  port,
	}
	nasManager.mu.Unlock()

	log.Printf("agent: mount successful - %s: -> %s (WebDAV port: %d)", driveLetter, target, port)
	return nil
}

// unmountNASDrive unmounts a NAS drive
func (r *Runner) unmountNASDrive(driveLetter string) error {
	driveLetter = strings.ToUpper(strings.TrimSuffix(driveLetter, ":"))

	nasManager.mu.Lock()
	mount, exists := nasManager.mounts[driveLetter]
	if exists {
		delete(nasManager.mounts, driveLetter)
	}
	nasManager.mu.Unlock()

	// Shutdown WebDAV server and VFS first (before unmapping)
	// This ensures no active connections when we try to delete the mapping
	if exists && mount != nil {
		if mount.WebDAV != nil {
			ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
			_ = mount.WebDAV.Shutdown(ctx)
			cancel()
		}
		if mount.VFS != nil {
			mount.VFS.Shutdown()
		}
	}

	// Small delay to ensure server is fully stopped
	time.Sleep(300 * time.Millisecond)

	// Disconnect the drive and clear WebClient cache
	unmountScript := fmt.Sprintf(`
# Delete the drive mapping
net use %s: /delete /y 2>$null

# Restart WebClient to clear cached WebDAV connections
# This prevents stale port issues on remount
Stop-Service WebClient -Force -ErrorAction SilentlyContinue
Start-Sleep -Milliseconds 300
Start-Service WebClient -ErrorAction SilentlyContinue
`, driveLetter)

	unmountCmd := exec.Command("powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", unmountScript)
	_ = unmountCmd.Run()

	// Notify shell that drive was removed
	r.notifyShellDriveRemoved(driveLetter)

	log.Printf("agent: unmounted %s:", driveLetter)
	return nil
}

// notifyShellDriveRemoved notifies Explorer that a drive was removed
func (r *Runner) notifyShellDriveRemoved(driveLetter string) {
	psScript := fmt.Sprintf(`
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class ShellNotifyRemove {
    [DllImport("shell32.dll")]
    public static extern void SHChangeNotify(int wEventId, int uFlags, IntPtr dwItem1, IntPtr dwItem2);
    
    [DllImport("shell32.dll", CharSet = CharSet.Unicode)]
    public static extern void SHChangeNotify(int wEventId, int uFlags, string dwItem1, IntPtr dwItem2);
}
"@

$drivePath = '%s:\'

# SHCNE_DRIVEREMOVED (0x80) - A drive has been removed
[ShellNotifyRemove]::SHChangeNotify(0x00000080, 0, [IntPtr]::Zero, [IntPtr]::Zero)
[ShellNotifyRemove]::SHChangeNotify(0x00000080, 0x0005, $drivePath, [IntPtr]::Zero)

# SHCNE_MEDIAREMOVED (0x40) - Media removed from drive
[ShellNotifyRemove]::SHChangeNotify(0x00000040, 0x0005, $drivePath, [IntPtr]::Zero)

# Force refresh
[ShellNotifyRemove]::SHChangeNotify(0x08000000, 0, [IntPtr]::Zero, [IntPtr]::Zero)
`, driveLetter)

	cmd := exec.Command("powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", psScript)
	_ = cmd.Run()
}

// mountKopiaSnapshot mounts a Kopia snapshot using kopia's mount functionality
func (r *Runner) mountKopiaSnapshot(ctx context.Context, payload MountSnapshotPayload) error {
	// For Kopia snapshot mounting, we'll use the kopia CLI or library
	// This is a placeholder - actual implementation depends on Kopia mount support

	// Check if WinFSP is available (required for FUSE mounts on Windows)
	if runtime.GOOS == "windows" {
		// Check for WinFSP
		_, err := exec.LookPath("fsptool.exe")
		if err != nil {
			return fmt.Errorf("WinFSP not found - please install WinFSP from https://winfsp.dev/")
		}
	}

	// Build mount path
	driveLetter := strings.ToUpper(strings.TrimSuffix(payload.DriveLetter, ":"))
	mountPath := driveLetter + ":"

	log.Printf("agent: mounting Kopia snapshot %s to %s", payload.ManifestID[:12], mountPath)

	// TODO: Implement actual Kopia snapshot mount using kopia library
	// This requires integrating with Kopia's mount functionality
	// For now, return an informative error

	return fmt.Errorf("Kopia snapshot mounting not yet implemented - use standard restore instead")
}

// unmountKopiaSnapshot unmounts a mounted Kopia snapshot
func (r *Runner) unmountKopiaSnapshot(manifestID string) error {
	// TODO: Implement Kopia snapshot unmount
	return fmt.Errorf("Kopia snapshot unmounting not yet implemented")
}

// GetActiveMounts returns a list of currently mounted NAS drives
func GetActiveMounts() []NASMount {
	nasManager.mu.RLock()
	defer nasManager.mu.RUnlock()

	mounts := make([]NASMount, 0, len(nasManager.mounts))
	for _, m := range nasManager.mounts {
		mounts = append(mounts, *m)
	}
	return mounts
}

// UnmountAll unmounts all active NAS drives (called on shutdown)
func UnmountAll() {
	nasManager.mu.Lock()
	defer nasManager.mu.Unlock()

	for letter, mount := range nasManager.mounts {
		log.Printf("agent: unmounting %s: on shutdown", letter)
		_ = exec.Command("net", "use", letter+":", "/delete", "/y").Run()
		if mount.WebDAV != nil {
			ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
			_ = mount.WebDAV.Shutdown(ctx)
			cancel()
		}
		if mount.VFS != nil {
			mount.VFS.Shutdown()
		}
	}
	nasManager.mounts = make(map[string]*NASMount)
}

// vfsWebDAVFS adapts rclone VFS to x/net/webdav FileSystem
type vfsWebDAVFS struct {
	vfs *vfs.VFS
}

func (fsys *vfsWebDAVFS) Mkdir(ctx context.Context, name string, perm os.FileMode) error {
	dir, leaf, err := fsys.vfs.StatParent(name)
	if err != nil {
		return err
	}
	_, err = dir.Mkdir(leaf)
	return err
}

func (fsys *vfsWebDAVFS) OpenFile(ctx context.Context, name string, flags int, perm os.FileMode) (webdav.File, error) {
	return fsys.vfs.OpenFile(name, flags, perm)
}

func (fsys *vfsWebDAVFS) RemoveAll(ctx context.Context, name string) error {
	node, err := fsys.vfs.Stat(name)
	if err != nil {
		return err
	}
	return node.RemoveAll()
}

func (fsys *vfsWebDAVFS) Rename(ctx context.Context, oldName, newName string) error {
	return fsys.vfs.Rename(oldName, newName)
}

func (fsys *vfsWebDAVFS) Stat(ctx context.Context, name string) (os.FileInfo, error) {
	return fsys.vfs.Stat(name)
}
