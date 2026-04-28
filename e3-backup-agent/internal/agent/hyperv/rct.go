//go:build windows
// +build windows

package hyperv

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"strings"
)

// RCTEngine handles Resilient Change Tracking operations.
//
// As of Windows Server 2025 the legacy
// Get-VMHardDiskDriveChangedBlockInformation PowerShell cmdlet has been
// removed from the Hyper-V module (v2.0.0.0). All change-block queries are
// therefore issued directly to
// Msvm_ImageManagementService::GetVirtualDiskChanges via WMI; see rct_wmi.go.
//
// On Windows Server 2012R2 / older Windows 8.1 hosts where neither the
// reference-point service nor the WMI change-block API is present, callers
// should gate via HostHasReferencePointService() and stay on Full backups.
type RCTEngine struct {
	manager *Manager
}

// NewRCTEngine creates a new RCT engine.
func NewRCTEngine(mgr *Manager) *RCTEngine {
	return &RCTEngine{manager: mgr}
}

// GetChangedBlocks returns changed block ranges per disk since the prior
// per-disk RCT IDs captured at the previous backup's reference point.
//
// The map key is the disk's host-side path (matching VMInfo.Disks[].Path);
// each value is the RCT ID handed back to the server in the previous
// HyperVVMResult.RCTIDs.
//
// Disks whose prior RCT ID is empty, or for which GetVirtualDiskChanges
// fails (RCT chain broken, file moved, etc.), are returned with Valid=false
// so the caller can fall back to a full per-disk read.
func (e *RCTEngine) GetChangedBlocks(ctx context.Context, vmName string, perDiskPriorRCTIDs map[string]string) ([]RCTInfo, error) {
	vm, err := e.manager.GetVM(ctx, vmName)
	if err != nil {
		return nil, fmt.Errorf("get vm for changed blocks: %w", err)
	}

	out := make([]RCTInfo, 0, len(vm.Disks))
	for _, disk := range vm.Disks {
		info := RCTInfo{
			DiskPath: disk.Path,
		}
		priorID := lookupPriorRCTID(perDiskPriorRCTIDs, disk.Path)
		if priorID == "" {
			info.Valid = false
			info.Error = "no prior RCT ID for disk"
			out = append(out, info)
			continue
		}
		size := disk.SizeBytes
		if size <= 0 {
			if fi, statErr := os.Stat(disk.Path); statErr == nil {
				size = fi.Size()
			}
		}
		ranges, err := getVirtualDiskChangesWMI(ctx, disk.Path, priorID, size)
		if err != nil {
			info.Valid = false
			info.Error = err.Error()
			out = append(out, info)
			continue
		}
		var total int64
		for _, r := range ranges {
			total += r.Length
		}
		info.RCTID = priorID // input we used; the new ID is captured at reference-point time
		info.ChangedBlocks = ranges
		info.TotalChanged = total
		info.Valid = true
		out = append(out, info)
	}
	return out, nil
}

// lookupPriorRCTID resolves a per-disk prior RCT ID from the map handed
// back by the server. The match is case-insensitive on the disk path so we
// tolerate Windows path-casing drift between agent runs.
func lookupPriorRCTID(m map[string]string, diskPath string) string {
	if id, ok := m[diskPath]; ok {
		return id
	}
	lower := strings.ToLower(diskPath)
	for k, v := range m {
		if strings.ToLower(k) == lower {
			return v
		}
	}
	return ""
}

// GetCurrentRCTIDs returns current RCT tracking IDs for all disks of a VM.
// Returns a map of disk path to RCT ID.
//
// This still uses Get-VHD because the RCT ID property is exposed there on
// every Hyper-V version we care about (2012R2 onward, including 2025).
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

// ValidateRCTChain checks that every expected disk still has a non-empty,
// matching RCT ID on the live VHDX. A mismatch (or any unknown disk) means
// the chain is broken and the caller must do a Full backup.
//
// expectedRCTIDs is the per-disk map persisted by the server at the time of
// the last reference point; the live VHDX RCT ID changes whenever Hyper-V
// optimises, compacts, migrates or otherwise resets the disk's RCT
// generation, so this is a cheap pre-flight check before paying for the
// WMI GetVirtualDiskChanges round-trip per disk.
func (e *RCTEngine) ValidateRCTChain(ctx context.Context, vmName string, expectedRCTIDs map[string]string) (bool, error) {
	if len(expectedRCTIDs) == 0 {
		return false, nil
	}
	currentIDs, err := e.GetCurrentRCTIDs(ctx, vmName)
	if err != nil {
		return false, err
	}
	for diskPath, expected := range expectedRCTIDs {
		if expected == "" {
			return false, nil
		}
		current := currentIDs[diskPath]
		if current == "" {
			// Try case-insensitive lookup before declaring a break.
			lower := strings.ToLower(diskPath)
			for k, v := range currentIDs {
				if strings.ToLower(k) == lower {
					current = v
					break
				}
			}
		}
		if current == "" || current != expected {
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
