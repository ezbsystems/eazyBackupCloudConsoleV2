//go:build windows
// +build windows

package hyperv

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
)

// RCTEngine handles Resilient Change Tracking operations.
type RCTEngine struct {
	manager *Manager
}

// NewRCTEngine creates a new RCT engine.
func NewRCTEngine(mgr *Manager) *RCTEngine {
	return &RCTEngine{manager: mgr}
}

// GetChangedBlocks returns changed block ranges since the reference checkpoint.
// Returns RCTInfo for each disk with changed blocks.
func (e *RCTEngine) GetChangedBlocks(ctx context.Context, vmName string, baseCheckpointID string) ([]RCTInfo, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop
$baseCheckpoint = Get-VMSnapshot -VM $vm | Where-Object { $_.Id.ToString() -eq '%s' }

if (-not $baseCheckpoint) {
    throw "Base checkpoint not found: %s"
}

$results = @()

Get-VMHardDiskDrive -VM $vm | ForEach-Object {
    $disk = $_
    $vhdPath = $disk.Path
    
    # Get changed regions using RCT
    try {
        $changes = Get-VMHardDiskDriveChangedBlockInformation -VMHardDiskDrive $disk -BaseSnapshot $baseCheckpoint -ErrorAction Stop
        
        $changedBlocks = @()
        $totalChanged = 0
        
        if ($changes.ChangedByteRanges) {
            foreach ($region in $changes.ChangedByteRanges) {
                $changedBlocks += @{
                    Offset = $region.Offset
                    Length = $region.Length
                }
                $totalChanged += $region.Length
            }
        }
        
        $results += @{
            DiskPath = $vhdPath
            RCTID = if ($changes.ResilientChangeTrackingId) { $changes.ResilientChangeTrackingId } else { "" }
            ChangedBlocks = $changedBlocks
            TotalChanged = $totalChanged
            Valid = $true
        }
    } catch {
        # If RCT query fails, return invalid to indicate full backup needed
        $results += @{
            DiskPath = $vhdPath
            RCTID = ""
            ChangedBlocks = @()
            TotalChanged = -1
            Valid = $false
        }
    }
}

$results | ConvertTo-Json -Depth 4 -Compress
`, escapePSString(vmName), baseCheckpointID, baseCheckpointID)

	out, err := e.manager.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("get changed blocks for %s: %w", vmName, err)
	}

	out = strings.TrimSpace(out)
	if out == "" || out == "null" {
		return []RCTInfo{}, nil
	}

	// Handle single object vs array
	if !strings.HasPrefix(out, "[") {
		out = "[" + out + "]"
	}

	var results []RCTInfo
	if err := json.Unmarshal([]byte(out), &results); err != nil {
		return nil, fmt.Errorf("parse rct json: %w", err)
	}

	return results, nil
}

// GetCurrentRCTIDs returns current RCT tracking IDs for all disks of a VM.
// Returns a map of disk path to RCT ID.
func (e *RCTEngine) GetCurrentRCTIDs(ctx context.Context, vmName string) (map[string]string, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop
$result = @{}

Get-VMHardDiskDrive -VM $vm | ForEach-Object {
    try {
        $vhd = Get-VHD -Path $_.Path -ErrorAction SilentlyContinue
        if ($vhd -and $vhd.ResilientChangeTrackingId) {
            $result[$_.Path] = $vhd.ResilientChangeTrackingId
        } else {
            $result[$_.Path] = ""
        }
    } catch {
        $result[$_.Path] = ""
    }
}

$result | ConvertTo-Json -Compress
`, escapePSString(vmName))

	out, err := e.manager.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("get rct ids for %s: %w", vmName, err)
	}

	out = strings.TrimSpace(out)
	if out == "" || out == "null" || out == "{}" {
		return map[string]string{}, nil
	}

	var result map[string]string
	if err := json.Unmarshal([]byte(out), &result); err != nil {
		return nil, fmt.Errorf("parse rct ids json: %w", err)
	}

	return result, nil
}

// ValidateRCTChain validates that RCT tracking is continuous (no gaps).
// Returns true if all disks have matching RCT IDs, false if chain is broken.
func (e *RCTEngine) ValidateRCTChain(ctx context.Context, vmName string, expectedRCTIDs map[string]string) (bool, error) {
	if len(expectedRCTIDs) == 0 {
		// No previous RCT IDs means this is a first backup - chain is "valid" (will do full)
		return false, nil
	}

	currentIDs, err := e.GetCurrentRCTIDs(ctx, vmName)
	if err != nil {
		return false, err
	}

	// Check each expected disk
	for diskPath, expectedID := range expectedRCTIDs {
		if expectedID == "" {
			// No RCT ID was tracked for this disk - need full backup
			return false, nil
		}

		currentID, exists := currentIDs[diskPath]
		if !exists {
			// Disk no longer exists or path changed
			return false, nil
		}

		if currentID != expectedID {
			// RCT chain broken - IDs don't match
			return false, nil
		}
	}

	return true, nil
}

// IsRCTEnabled checks if RCT is enabled on all disks of a VM.
func (e *RCTEngine) IsRCTEnabled(ctx context.Context, vmName string) (bool, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop
$allEnabled = $true

Get-VMHardDiskDrive -VM $vm | ForEach-Object {
    if (-not $_.SupportPersistentReservations) {
        $allEnabled = $false
    }
}

$allEnabled
`, escapePSString(vmName))

	out, err := e.manager.runPS(ctx, script)
	if err != nil {
		return false, fmt.Errorf("check rct enabled for %s: %w", vmName, err)
	}

	out = strings.TrimSpace(strings.ToLower(out))
	return out == "true", nil
}

// GetDiskRCTStatus returns RCT status for each disk of a VM.
func (e *RCTEngine) GetDiskRCTStatus(ctx context.Context, vmName string) (map[string]bool, error) {
	script := fmt.Sprintf(`
$ErrorActionPreference = 'Stop'
$vm = Get-VM -Name '%s' -ErrorAction Stop
$result = @{}

Get-VMHardDiskDrive -VM $vm | ForEach-Object {
    $result[$_.Path] = $_.SupportPersistentReservations
}

$result | ConvertTo-Json -Compress
`, escapePSString(vmName))

	out, err := e.manager.runPS(ctx, script)
	if err != nil {
		return nil, fmt.Errorf("get disk rct status for %s: %w", vmName, err)
	}

	out = strings.TrimSpace(out)
	if out == "" || out == "null" || out == "{}" {
		return map[string]bool{}, nil
	}

	var result map[string]bool
	if err := json.Unmarshal([]byte(out), &result); err != nil {
		return nil, fmt.Errorf("parse disk rct status json: %w", err)
	}

	return result, nil
}

// CalculateTotalChangedBytes sums up the total changed bytes from RCT info.
func CalculateTotalChangedBytes(rctInfos []RCTInfo) int64 {
	var total int64
	for _, info := range rctInfos {
		if info.Valid && info.TotalChanged > 0 {
			total += info.TotalChanged
		}
	}
	return total
}

// FindDiskRCTInfo finds RCTInfo for a specific disk path.
func FindDiskRCTInfo(rctInfos []RCTInfo, diskPath string) *RCTInfo {
	// Normalize path comparison (case-insensitive on Windows)
	diskPathLower := strings.ToLower(diskPath)
	for i := range rctInfos {
		if strings.ToLower(rctInfos[i].DiskPath) == diskPathLower {
			return &rctInfos[i]
		}
	}
	return nil
}

// AreAllDisksRCTValid checks if all disks have valid RCT data.
func AreAllDisksRCTValid(rctInfos []RCTInfo) bool {
	if len(rctInfos) == 0 {
		return false
	}
	for _, info := range rctInfos {
		if !info.Valid {
			return false
		}
	}
	return true
}

