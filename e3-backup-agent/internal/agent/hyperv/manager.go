//go:build windows
// +build windows

package hyperv

import (
	"context"
	"encoding/json"
	"fmt"
	"os/exec"
	"strings"
	"time"
)

// Manager provides Hyper-V management operations via PowerShell.
type Manager struct {
	psPath string // Path to PowerShell executable
}

// NewManager creates a new Hyper-V manager.
func NewManager() *Manager {
	return &Manager{
		psPath: "powershell.exe",
	}
}

// ListVMs returns all VMs on the host.
func (m *Manager) ListVMs(ctx context.Context) ([]VMInfo, error) {
	script := `
$ErrorActionPreference = 'Stop'
$vms = Get-VM | ForEach-Object {
    $vm = $_
    $disks = Get-VMHardDiskDrive -VM $vm | ForEach-Object {
        @{
            ControllerType = $_.ControllerType.ToString()
            ControllerNumber = $_.ControllerNumber
            ControllerLocation = $_.ControllerLocation
            Path = $_.Path
            SizeBytes = 0
            UsedBytes = 0
            VHDFormat = ""
            RCTEnabled = $_.SupportPersistentReservations
            RCTID = ""
        }
    }
    
    $lis = Get-VMIntegrationService -VM $vm | Where-Object { $_.Enabled }
    
    # Detect Linux via guest OS name or integration services
    $guestOS = ""
    try {
        $guestOS = (Get-VMIntegrationService -VM $vm -Name "Guest Service Interface" -ErrorAction SilentlyContinue).PrimaryOperationalStatus
    } catch {}
    $isLinux = $vm.GuestServiceInterfaceComponentMode -eq "Linux" -or $guestOS -like "*Linux*"
    
    # Check if any disk has RCT enabled
    $rctEnabled = $false
    foreach ($d in $disks) {
        if ($d.RCTEnabled) { $rctEnabled = $true; break }
    }
    
    @{
        ID = $vm.VMId.ToString()
        Name = $vm.Name
        State = $vm.State.ToString()
        Generation = $vm.Generation
        CPUCount = $vm.ProcessorCount
        MemoryMB = [math]::Round($vm.MemoryStartup / 1MB)
        IntegrationServices = ($lis.Count -gt 0)
        Disks = @($disks)
        IsLinux = $isLinux
        RCTEnabled = $rctEnabled
        CheckpointFileDir = $vm.CheckpointFileLocation
    }
}
if ($vms -eq $null) { $vms = @() }
$vms | ConvertTo-Json -Depth 4 -Compress
`
	out, err := m.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("list vms: %w", err)
	}

	// Handle empty result
	out = strings.TrimSpace(out)
	if out == "" || out == "null" {
		return []VMInfo{}, nil
	}

	// PowerShell returns single object without array brackets
	if !strings.HasPrefix(out, "[") {
		out = "[" + out + "]"
	}

	var vms []VMInfo
	if err := json.Unmarshal([]byte(out), &vms); err != nil {
		return nil, fmt.Errorf("parse vms json: %w (output: %s)", err, truncate(out, 200))
	}
	return vms, nil
}

// GetVM returns info for a specific VM by name.
func (m *Manager) GetVM(ctx context.Context, vmName string) (*VMInfo, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop
$disks = Get-VMHardDiskDrive -VM $vm | ForEach-Object {
    $vhd = $null
    try {
        $vhd = Get-VHD -Path $_.Path -ErrorAction SilentlyContinue
    } catch {}
    @{
        ControllerType = $_.ControllerType.ToString()
        ControllerNumber = $_.ControllerNumber
        ControllerLocation = $_.ControllerLocation
        Path = $_.Path
        SizeBytes = if ($vhd) { $vhd.Size } else { 0 }
        UsedBytes = if ($vhd) { $vhd.FileSize } else { 0 }
        VHDFormat = if ($vhd) { $vhd.VhdFormat.ToString() } else { "Unknown" }
        RCTEnabled = $_.SupportPersistentReservations
        RCTID = ""
    }
}

$lis = Get-VMIntegrationService -VM $vm | Where-Object { $_.Enabled }
$isLinux = $vm.GuestServiceInterfaceComponentMode -eq "Linux"

$rctEnabled = $false
foreach ($d in $disks) {
    if ($d.RCTEnabled) { $rctEnabled = $true; break }
}

@{
    ID = $vm.VMId.ToString()
    Name = $vm.Name
    State = $vm.State.ToString()
    Generation = $vm.Generation
    CPUCount = $vm.ProcessorCount
    MemoryMB = [math]::Round($vm.MemoryStartup / 1MB)
    IntegrationServices = ($lis.Count -gt 0)
    Disks = @($disks)
    IsLinux = $isLinux
    RCTEnabled = $rctEnabled
    CheckpointFileDir = $vm.CheckpointFileLocation
} | ConvertTo-Json -Depth 4 -Compress
`, escapePSString(vmName))

	out, err := m.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("get vm %s: %w", vmName, err)
	}

	var vm VMInfo
	if err := json.Unmarshal([]byte(strings.TrimSpace(out)), &vm); err != nil {
		return nil, fmt.Errorf("parse vm json: %w", err)
	}
	return &vm, nil
}

// GetVMByGUID returns info for a specific VM by GUID.
func (m *Manager) GetVMByGUID(ctx context.Context, vmGUID string) (*VMInfo, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM | Where-Object { $_.VMId.ToString() -eq '%s' } | Select-Object -First 1
if (-not $vm) { throw "VM not found: %s" }
$disks = Get-VMHardDiskDrive -VM $vm | ForEach-Object {
    $vhd = $null
    try {
        $vhd = Get-VHD -Path $_.Path -ErrorAction SilentlyContinue
    } catch {}
    @{
        ControllerType = $_.ControllerType.ToString()
        ControllerNumber = $_.ControllerNumber
        ControllerLocation = $_.ControllerLocation
        Path = $_.Path
        SizeBytes = if ($vhd) { $vhd.Size } else { 0 }
        UsedBytes = if ($vhd) { $vhd.FileSize } else { 0 }
        VHDFormat = if ($vhd) { $vhd.VhdFormat.ToString() } else { "Unknown" }
        RCTEnabled = $_.SupportPersistentReservations
        RCTID = ""
    }
}

$lis = Get-VMIntegrationService -VM $vm | Where-Object { $_.Enabled }
$isLinux = $vm.GuestServiceInterfaceComponentMode -eq "Linux"

$rctEnabled = $false
foreach ($d in $disks) {
    if ($d.RCTEnabled) { $rctEnabled = $true; break }
}

@{
    ID = $vm.VMId.ToString()
    Name = $vm.Name
    State = $vm.State.ToString()
    Generation = $vm.Generation
    CPUCount = $vm.ProcessorCount
    MemoryMB = [math]::Round($vm.MemoryStartup / 1MB)
    IntegrationServices = ($lis.Count -gt 0)
    Disks = @($disks)
    IsLinux = $isLinux
    RCTEnabled = $rctEnabled
    CheckpointFileDir = $vm.CheckpointFileLocation
} | ConvertTo-Json -Depth 4 -Compress
`, vmGUID, vmGUID)

	out, err := m.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("get vm by guid %s: %w", vmGUID, err)
	}

	var vm VMInfo
	if err := json.Unmarshal([]byte(strings.TrimSpace(out)), &vm); err != nil {
		return nil, fmt.Errorf("parse vm json: %w", err)
	}
	return &vm, nil
}

// EnableRCT enables Resilient Change Tracking on a VM's disks.
// The VM must be off for this to work on some configurations.
func (m *Manager) EnableRCT(ctx context.Context, vmName string) error {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop
Get-VMHardDiskDrive -VM $vm | ForEach-Object {
    Set-VMHardDiskDrive -VMHardDiskDrive $_ -SupportPersistentReservations $true
}
`, escapePSString(vmName))
	_, err := m.runPS(ctx, script)
	if err != nil {
		return fmt.Errorf("enable rct for %s: %w", vmName, err)
	}
	return nil
}

// CreateVSSCheckpoint creates an application-consistent (Production) checkpoint.
// This triggers guest VSS writers for Windows VMs.
func (m *Manager) CreateVSSCheckpoint(ctx context.Context, vmName string) (*CheckpointInfo, error) {
	checkpointName := fmt.Sprintf("EazyBackup_VSS_%s", time.Now().Format("20060102_150405"))

	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop

# Verify integration services are running for VSS
$vss = Get-VMIntegrationService -VM $vm | Where-Object { $_.Name -eq "VSS" -or $_.Name -like "*Volume Shadow*" }
if ($vss -and -not $vss.Enabled) {
    Write-Warning "VSS integration service not enabled on VM, attempting standard checkpoint"
}

# Create production checkpoint (application-consistent)
# This triggers guest VSS writers
$cp = Checkpoint-VM -VM $vm -SnapshotName '%s' -Passthru -ErrorAction Stop

$snapshotType = "Standard"
if ($cp.SnapshotType) {
    $snapshotType = $cp.SnapshotType.ToString()
}

@{
    ID = $cp.Id.ToString()
    Name = $cp.Name
    VMName = $cp.VMName
    CreationTime = $cp.CreationTime.ToString("o")
    ParentID = if ($cp.ParentSnapshotId) { $cp.ParentSnapshotId.ToString() } else { "" }
    IsReference = $true
    SnapshotType = $snapshotType
} | ConvertTo-Json -Compress
`, escapePSString(vmName), checkpointName)

	out, err := m.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("create vss checkpoint for %s: %w", vmName, err)
	}

	var cp checkpointJSON
	if err := json.Unmarshal([]byte(strings.TrimSpace(out)), &cp); err != nil {
		return nil, fmt.Errorf("parse checkpoint json: %w", err)
	}

	creationTime, _ := time.Parse(time.RFC3339, cp.CreationTime)
	return &CheckpointInfo{
		ID:           cp.ID,
		Name:         cp.Name,
		VMName:       cp.VMName,
		CreationTime: creationTime,
		ParentID:     cp.ParentID,
		IsReference:  cp.IsReference,
		SnapshotType: cp.SnapshotType,
	}, nil
}

// CreateReferenceCheckpoint creates a standard checkpoint (crash-consistent).
// Used as fallback when VSS is not available.
func (m *Manager) CreateReferenceCheckpoint(ctx context.Context, vmName string) (*CheckpointInfo, error) {
	checkpointName := fmt.Sprintf("EazyBackup_Ref_%s", time.Now().Format("20060102_150405"))

	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop
$cp = Checkpoint-VM -VM $vm -SnapshotName '%s' -Passthru -ErrorAction Stop

$snapshotType = "Standard"
if ($cp.SnapshotType) {
    $snapshotType = $cp.SnapshotType.ToString()
}

@{
    ID = $cp.Id.ToString()
    Name = $cp.Name
    VMName = $cp.VMName
    CreationTime = $cp.CreationTime.ToString("o")
    ParentID = if ($cp.ParentSnapshotId) { $cp.ParentSnapshotId.ToString() } else { "" }
    IsReference = $true
    SnapshotType = $snapshotType
} | ConvertTo-Json -Compress
`, escapePSString(vmName), checkpointName)

	out, err := m.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("create reference checkpoint for %s: %w", vmName, err)
	}

	var cp checkpointJSON
	if err := json.Unmarshal([]byte(strings.TrimSpace(out)), &cp); err != nil {
		return nil, fmt.Errorf("parse checkpoint json: %w", err)
	}

	creationTime, _ := time.Parse(time.RFC3339, cp.CreationTime)
	return &CheckpointInfo{
		ID:           cp.ID,
		Name:         cp.Name,
		VMName:       cp.VMName,
		CreationTime: creationTime,
		ParentID:     cp.ParentID,
		IsReference:  cp.IsReference,
		SnapshotType: cp.SnapshotType,
	}, nil
}

// CreateLinuxConsistentCheckpoint creates a checkpoint for Linux VMs using fsfreeze.
// The Hyper-V integration services will trigger fsfreeze in the guest.
func (m *Manager) CreateLinuxConsistentCheckpoint(ctx context.Context, vmName string) (*CheckpointInfo, error) {
	// For Linux VMs, a production checkpoint will trigger hv_vss_daemon
	// which calls fsfreeze. We use the same mechanism as VSS checkpoint.
	return m.CreateVSSCheckpoint(ctx, vmName)
}

// MergeCheckpoint removes a checkpoint, merging it with its parent.
func (m *Manager) MergeCheckpoint(ctx context.Context, vmName, checkpointID string) error {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop
$cp = Get-VMSnapshot -VM $vm | Where-Object { $_.Id.ToString() -eq '%s' }
if ($cp) {
    Remove-VMSnapshot -VMSnapshot $cp -ErrorAction Stop
}
`, escapePSString(vmName), checkpointID)

	_, err := m.runPS(ctx, script)
	if err != nil {
		return fmt.Errorf("merge checkpoint %s for %s: %w", checkpointID, vmName, err)
	}
	return nil
}

// RemoveCheckpoint is an alias for MergeCheckpoint.
func (m *Manager) RemoveCheckpoint(ctx context.Context, vmName, checkpointID string) error {
	return m.MergeCheckpoint(ctx, vmName, checkpointID)
}

// GetCheckpoints returns all checkpoints for a VM.
func (m *Manager) GetCheckpoints(ctx context.Context, vmName string) ([]CheckpointInfo, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop
$cps = Get-VMSnapshot -VM $vm | ForEach-Object {
    @{
        ID = $_.Id.ToString()
        Name = $_.Name
        VMName = $_.VMName
        CreationTime = $_.CreationTime.ToString("o")
        ParentID = if ($_.ParentSnapshotId) { $_.ParentSnapshotId.ToString() } else { "" }
        IsReference = $false
        SnapshotType = if ($_.SnapshotType) { $_.SnapshotType.ToString() } else { "Standard" }
    }
}
if ($cps -eq $null) { $cps = @() }
$cps | ConvertTo-Json -Depth 2 -Compress
`, escapePSString(vmName))

	out, err := m.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("get checkpoints for %s: %w", vmName, err)
	}

	out = strings.TrimSpace(out)
	if out == "" || out == "null" {
		return []CheckpointInfo{}, nil
	}

	if !strings.HasPrefix(out, "[") {
		out = "[" + out + "]"
	}

	var cpsJSON []checkpointJSON
	if err := json.Unmarshal([]byte(out), &cpsJSON); err != nil {
		return nil, fmt.Errorf("parse checkpoints json: %w", err)
	}

	cps := make([]CheckpointInfo, len(cpsJSON))
	for i, cp := range cpsJSON {
		creationTime, _ := time.Parse(time.RFC3339, cp.CreationTime)
		cps[i] = CheckpointInfo{
			ID:           cp.ID,
			Name:         cp.Name,
			VMName:       cp.VMName,
			CreationTime: creationTime,
			ParentID:     cp.ParentID,
			IsReference:  cp.IsReference,
			SnapshotType: cp.SnapshotType,
		}
	}
	return cps, nil
}

// GetCheckpointDiskPaths returns the VHDX paths for a checkpoint.
func (m *Manager) GetCheckpointDiskPaths(ctx context.Context, vmName, checkpointID string) ([]string, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop
$cp = Get-VMSnapshot -VM $vm | Where-Object { $_.Id.ToString() -eq '%s' }
if (-not $cp) { throw "Checkpoint not found: %s" }

$paths = Get-VMHardDiskDrive -VMSnapshot $cp | ForEach-Object { $_.Path }
if ($paths -eq $null) { $paths = @() }
$paths | ConvertTo-Json -Compress
`, escapePSString(vmName), checkpointID, checkpointID)

	out, err := m.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("get checkpoint disk paths: %w", err)
	}

	out = strings.TrimSpace(out)
	if out == "" || out == "null" {
		return []string{}, nil
	}

	// Single path comes as a string, multiple as array
	if strings.HasPrefix(out, "\"") {
		var path string
		if err := json.Unmarshal([]byte(out), &path); err != nil {
			return nil, fmt.Errorf("parse path json: %w", err)
		}
		return []string{path}, nil
	}

	var paths []string
	if err := json.Unmarshal([]byte(out), &paths); err != nil {
		return nil, fmt.Errorf("parse paths json: %w", err)
	}
	return paths, nil
}

// runPS executes a PowerShell script and returns stdout.
func (m *Manager) runPS(ctx context.Context, script string) (string, error) {
	cmd := exec.CommandContext(ctx, m.psPath, "-NoProfile", "-NonInteractive", "-Command", script)
	out, err := cmd.Output()
	if err != nil {
		if ee, ok := err.(*exec.ExitError); ok {
			stderr := strings.TrimSpace(string(ee.Stderr))
			if stderr != "" {
				return "", fmt.Errorf("powershell error: %s", stderr)
			}
		}
		return "", err
	}
	return string(out), nil
}

// checkpointJSON is used for JSON unmarshaling of checkpoint data.
type checkpointJSON struct {
	ID           string `json:"ID"`
	Name         string `json:"Name"`
	VMName       string `json:"VMName"`
	CreationTime string `json:"CreationTime"`
	ParentID     string `json:"ParentID"`
	IsReference  bool   `json:"IsReference"`
	SnapshotType string `json:"SnapshotType"`
}

// escapePSString escapes single quotes for PowerShell strings.
func escapePSString(s string) string {
	return strings.ReplaceAll(s, "'", "''")
}

// truncate truncates a string to maxLen characters.
func truncate(s string, maxLen int) string {
	if len(s) <= maxLen {
		return s
	}
	return s[:maxLen] + "..."
}

