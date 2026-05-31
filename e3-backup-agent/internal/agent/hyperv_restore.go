//go:build windows
// +build windows

package agent

import (
	"context"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"strings"
	"sync/atomic"
	"time"
)

// HyperVRestorePayload represents the payload for a hyperv_restore command.
//
// Two shapes are supported for backward compatibility:
//   - Single-VM (legacy): the VM fields live at the top level (VMName, VMGUID,
//     DiskManifests, RestoreChain, BackupType) and TargetPath is the final
//     restore directory.
//   - Multi-VM: VMs holds one entry per guest VM and TargetPath is treated as a
//     BASE directory; each VM is restored into its own subfolder beneath it.
type HyperVRestorePayload struct {
	BackupPointID  int64               `json:"backup_point_id"`
	VMName         string              `json:"vm_name"`
	VMGUID         string              `json:"vm_guid"`
	TargetPath     string              `json:"target_path"`
	DiskManifests  map[string]string   `json:"disk_manifests"` // disk_path -> manifest_id
	RestoreChain   []RestoreChainEntry `json:"restore_chain"`
	BackupType     string              `json:"backup_type"`      // "Full" or "Incremental"
	RestoreRunID   int64               `json:"restore_run_id"`   // legacy numeric; prefer RestoreRunUUID
	RestoreRunUUID string              `json:"restore_run_uuid"` // UUID for progress tracking
	VMs            []HyperVRestoreVM   `json:"vms"`              // multi-VM restore (preferred); empty => single-VM legacy shape
}

// HyperVRestoreVM describes one guest VM to restore within a multi-VM restore
// command. Disks are restored into filepath.Join(<base target path>, Subfolder).
type HyperVRestoreVM struct {
	BackupPointID int64               `json:"backup_point_id"`
	VMName        string              `json:"vm_name"`
	VMGUID        string              `json:"vm_guid"`
	Subfolder     string              `json:"subfolder"` // optional; defaults to a sanitized VMName
	DiskManifests map[string]string   `json:"disk_manifests"`
	RestoreChain  []RestoreChainEntry `json:"restore_chain"`
	BackupType    string              `json:"backup_type"`
}

// RestoreChainEntry represents a single entry in the restore chain.
type RestoreChainEntry struct {
	BackupPointID int64             `json:"backup_point_id"`
	ManifestID    string            `json:"manifest_id"`
	BackupType    string            `json:"backup_type"`
	DiskManifests map[string]string `json:"disk_manifests"`
}

// hypervRestoreProgress tracks restore progress across multiple disks.
type hypervRestoreProgress struct {
	runner         *Runner
	runID          string
	totalBytes     int64
	completedBytes int64
	currentBytes   int64 // atomic
	startTime      time.Time
	currentVM      string
	currentDisk    string
	currentDiskIdx int
	totalDisks     int
	lastReportAt   time.Time
}

// currentItemLabel builds the human-readable status shown in the UI. When a VM
// name is set (multi-VM restore) it is prefixed so progress reflects the whole
// restore, e.g. "win10 — Disk 2/3: win10.vhdx".
func (p *hypervRestoreProgress) currentItemLabel() string {
	disk := fmt.Sprintf("Disk %d/%d: %s", p.currentDiskIdx+1, p.totalDisks, filepath.Base(p.currentDisk))
	if p.currentVM != "" {
		return p.currentVM + " — " + disk
	}
	return disk
}

func (p *hypervRestoreProgress) setCurrentBytes(bytes int64) {
	atomic.StoreInt64(&p.currentBytes, bytes)
	p.reportProgress(false)
}

func (p *hypervRestoreProgress) addCompletedBytes(bytes int64) {
	p.completedBytes += bytes
	atomic.StoreInt64(&p.currentBytes, 0)
}

func (p *hypervRestoreProgress) reportProgress(force bool) {
	now := time.Now()
	if !force && now.Sub(p.lastReportAt) < 2*time.Second {
		return
	}
	p.lastReportAt = now

	currentDiskBytes := atomic.LoadInt64(&p.currentBytes)
	totalProcessed := p.completedBytes + currentDiskBytes

	var progressPct float64
	if p.totalBytes > 0 {
		progressPct = float64(totalProcessed) / float64(p.totalBytes) * 100.0
		if progressPct > 99.9 {
			progressPct = 99.9
		}
	} else if p.totalDisks > 0 {
		// Fallback when manifest sizes could not be pre-scanned: advance by
		// completed disk index so multi-VM restores do not pin at ~99% after VM1.
		progressPct = float64(p.currentDiskIdx) / float64(p.totalDisks) * 99.9
		if currentDiskBytes > 0 {
			progressPct += (1.0 / float64(p.totalDisks)) * 50.0 // mid-disk estimate
		}
		if progressPct > 99.9 {
			progressPct = 99.9
		}
	}

	// Calculate ETA
	elapsed := now.Sub(p.startTime).Seconds()
	var etaSeconds int64
	var speedBps int64
	if elapsed > 0 && totalProcessed > 0 {
		speedBps = int64(float64(totalProcessed) / elapsed)
		if speedBps > 0 && p.totalBytes > totalProcessed {
			remaining := p.totalBytes - totalProcessed
			etaSeconds = int64(float64(remaining) / float64(speedBps))
		}
	}

	_ = p.runner.client.UpdateRun(RunUpdate{
		RunID:            p.runID,
		Status:           "running",
		ProgressPct:      progressPct,
		BytesTransferred: Int64Ptr(totalProcessed),
		BytesTotal:       Int64Ptr(p.totalBytes),
		SpeedBytesPerSec: speedBps,
		EtaSeconds:       etaSeconds,
		CurrentItem:      p.currentItemLabel(),
	})
}

// executeHyperVRestoreCommand handles a Hyper-V disk restore command.
func (r *Runner) executeHyperVRestoreCommand(ctx context.Context, cmd PendingCommand) {
	if cmd.JobContext == nil {
		log.Printf("agent: hyperv_restore command %d missing job context", cmd.CommandID)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing job context")
		return
	}

	// Parse payload
	payload := parseHyperVRestorePayload(cmd.Payload)
	if payload == nil {
		log.Printf("agent: hyperv_restore command %d invalid payload", cmd.CommandID)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "invalid payload")
		return
	}

	if payload.TargetPath == "" {
		log.Printf("agent: hyperv_restore command %d missing target_path", cmd.CommandID)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing target_path")
		return
	}

	// Normalize to a list of VMs. Multi-VM commands carry payload.VMs; legacy
	// single-VM commands carry the VM fields at the top level, which we wrap
	// into a one-element list with an empty subfolder so the disks land
	// directly in TargetPath (preserving the original behavior).
	vms := payload.VMs
	if len(vms) == 0 {
		vms = []HyperVRestoreVM{{
			BackupPointID: payload.BackupPointID,
			VMName:        payload.VMName,
			VMGUID:        payload.VMGUID,
			Subfolder:     "",
			DiskManifests: payload.DiskManifests,
			RestoreChain:  payload.RestoreChain,
			BackupType:    payload.BackupType,
		}}
	}

	totalDisks := 0
	for i := range vms {
		totalDisks += len(vms[i].DiskManifests)
	}
	if totalDisks == 0 {
		log.Printf("agent: hyperv_restore command %d no disk manifests", cmd.CommandID)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "no disk manifests to restore")
		return
	}

	// Use restore_run_uuid for progress tracking (UUID); fall back to RunID or legacy RestoreRunID
	runID := payload.RestoreRunUUID
	if runID == "" {
		runID = cmd.RunID
	}
	if runID == "" && payload.RestoreRunID != 0 {
		runID = fmt.Sprintf("%d", payload.RestoreRunID)
	}

	multiVM := len(vms) > 1
	log.Printf("agent: starting hyperv_restore for %d VM(s), %d disk(s) total, base=%s",
		len(vms), totalDisks, payload.TargetPath)

	// Create cancellable context and start cancel polling
	restoreCtx, cancelRestore := context.WithCancel(ctx)
	defer cancelRestore()

	// Start cancel polling goroutine
	cancelPollDone := make(chan struct{})
	go func() {
		defer close(cancelPollDone)
		ticker := time.NewTicker(3 * time.Second)
		defer ticker.Stop()
		for {
			select {
			case <-restoreCtx.Done():
				return
			case <-ticker.C:
				cancelReq, _, errCmd := r.pollCommands(runID)
				if errCmd != nil {
					log.Printf("agent: hyperv_restore cancel poll error: %v", errCmd)
					continue
				}
				if cancelReq {
					log.Printf("agent: hyperv_restore cancel requested for run %s", runID)
					r.pushEvents(runID, RunEvent{
						Type:      "info",
						Level:     "warn",
						MessageID: "CANCEL_REQUESTED",
						ParamsJSON: map[string]any{
							"message": "Restore cancellation requested by user",
						},
					})
					cancelRestore()
					return
				}
			}
		}
	}()
	defer func() {
		cancelRestore()  // Ensure context is cancelled to stop polling goroutine
		<-cancelPollDone // Wait for polling goroutine to finish
	}()

	// Update run status to running
	_ = r.client.UpdateRun(RunUpdate{
		RunID:     runID,
		Status:    "running",
		StartedAt: time.Now().UTC().Format(time.RFC3339),
	})

	// Push starting event (aggregate across all VMs)
	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "HYPERV_RESTORE_STARTING",
		ParamsJSON: map[string]any{
			"vm_name":     restoreVMNamesLabel(vms),
			"vm_count":    len(vms),
			"disk_count":  totalDisks,
			"target_path": payload.TargetPath,
			"backup_type": vms[0].BackupType,
		},
	})

	// Create base target directory
	if err := os.MkdirAll(payload.TargetPath, 0755); err != nil {
		log.Printf("agent: hyperv_restore failed to create target directory: %v", err)
		r.pushEvents(runID, RunEvent{
			Type:      "error",
			Level:     "error",
			MessageID: "HYPERV_RESTORE_FAILED",
			ParamsJSON: map[string]any{
				"message": fmt.Sprintf("Failed to create target directory: %v", err),
			},
		})
		_ = r.client.UpdateRun(RunUpdate{
			RunID:      runID,
			Status:     "failed",
			FinishedAt: time.Now().UTC().Format(time.RFC3339),
		})
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", fmt.Sprintf("mkdir failed: %v", err))
		return
	}

	// Build NextRunResponse for Kopia operations
	run := buildNextRunResponseFromJobContext(cmd.JobContext)

	// Pre-scan total bytes across every disk manifest so progress is aggregated
	// across all selected VMs (not per-VHDX, which pinned the bar at ~99% after VM1).
	totalRestoreBytes := int64(0)
	if run != nil {
		if rep, closeRepo, err := r.openKopiaRepoForRun(ctx, run); err == nil {
			defer closeRepo()
			for _, vm := range vms {
				for _, manifestID := range vm.DiskManifests {
					sz, err := r.kopiaManifestRootFileSize(ctx, rep, manifestID)
					if err != nil {
						log.Printf("agent: hyperv_restore manifest %s size lookup failed: %v", manifestID, err)
						continue
					}
					totalRestoreBytes += sz
				}
			}
		} else {
			log.Printf("agent: hyperv_restore could not pre-scan restore sizes: %v", err)
		}
	}
	log.Printf("agent: hyperv_restore total size across %d disk(s): %d bytes", totalDisks, totalRestoreBytes)

	progress := &hypervRestoreProgress{
		runner:     r,
		runID:      runID,
		startTime:  time.Now(),
		totalDisks: totalDisks,
		totalBytes: totalRestoreBytes,
	}
	progress.reportProgress(true)

	globalDiskIdx := 0
	restoredDisks := 0
	var failedVMs []string
	var lastErr error

	for vi := range vms {
		vm := vms[vi]

		// Resolve this VM's destination directory. Multi-VM restores land each
		// VM under its own subfolder; legacy single-VM restores keep the base
		// path as-is.
		vmTarget := payload.TargetPath
		subfolder := vm.Subfolder
		if subfolder == "" && multiVM {
			subfolder = sanitizeSubfolderName(vm.VMName)
		}
		if subfolder != "" {
			vmTarget = filepath.Join(payload.TargetPath, subfolder)
		}

		if err := os.MkdirAll(vmTarget, 0755); err != nil {
			log.Printf("agent: hyperv_restore failed to create VM directory %s: %v", vmTarget, err)
			r.pushEvents(runID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "HYPERV_RESTORE_DISK_FAILED",
				ParamsJSON: map[string]any{
					"vm_name": vm.VMName,
					"message": fmt.Sprintf("Failed to create target directory for %s: %v", vm.VMName, err),
				},
			})
			failedVMs = append(failedVMs, vm.VMName)
			lastErr = err
			globalDiskIdx += len(vm.DiskManifests)
			continue
		}

		progress.currentVM = vm.VMName

		vmRestored, canceled, vmErr := r.restoreHyperVVMDisks(
			restoreCtx, run, &vm, vmTarget, progress, runID, &globalDiskIdx,
		)
		if canceled {
			r.pushEvents(runID, RunEvent{
				Type:      "info",
				Level:     "info",
				MessageID: "CANCELLED",
				ParamsJSON: map[string]any{
					"message": "Hyper-V restore was cancelled.",
				},
			})
			_ = r.client.UpdateRun(RunUpdate{
				RunID:      runID,
				Status:     "cancelled",
				FinishedAt: time.Now().UTC().Format(time.RFC3339),
			})
			_ = r.client.CompleteCommand(cmd.CommandID, "cancelled", "restore cancelled")
			return
		}

		restoredDisks += vmRestored
		if vmErr != nil {
			lastErr = vmErr
		}
		if vmRestored < len(vm.DiskManifests) {
			failedVMs = append(failedVMs, vm.VMName)
		}
	}

	// Determine final status
	var status string
	var message string
	switch {
	case restoredDisks == 0:
		status = "failed"
		message = fmt.Sprintf("All disk restores failed. Last error: %v", lastErr)
	case len(failedVMs) > 0 || lastErr != nil:
		status = "warning"
		message = fmt.Sprintf("%d of %d disks restored across %d VM(s). Some disks failed (%s).",
			restoredDisks, totalDisks, len(vms), strings.Join(failedVMs, ", "))
	default:
		status = "success"
		message = fmt.Sprintf("All %d disks restored successfully across %d VM(s) to %s",
			restoredDisks, len(vms), payload.TargetPath)
	}

	log.Printf("agent: hyperv_restore completed with status %s: %s", status, message)

	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "HYPERV_RESTORE_COMPLETE",
		ParamsJSON: map[string]any{
			"status":         status,
			"message":        message,
			"restored_disks": restoredDisks,
			"total_disks":    totalDisks,
			"vm_count":       len(vms),
			"target_path":    payload.TargetPath,
		},
	})

	finalUpdate := RunUpdate{
		RunID:      runID,
		Status:     status,
		FinishedAt: time.Now().UTC().Format(time.RFC3339),
	}
	if status == "success" {
		finalUpdate.ProgressPct = 100
		if progress.totalBytes > 0 {
			finalUpdate.BytesTransferred = Int64Ptr(progress.completedBytes)
			finalUpdate.BytesTotal = Int64Ptr(progress.totalBytes)
		}
	}
	_ = r.client.UpdateRun(finalUpdate)

	if status == "failed" {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", message)
	} else {
		_ = r.client.CompleteCommand(cmd.CommandID, "completed", message)
	}
}

// restoreHyperVVMDisks restores all disks for a single VM into vmTarget. It
// advances *globalDiskIdx (the cross-VM disk counter used for progress) as it
// goes. Returns the number of disks successfully restored, whether the restore
// was cancelled, and the last non-cancellation error encountered (nil if none).
func (r *Runner) restoreHyperVVMDisks(
	ctx context.Context,
	run *NextRunResponse,
	vm *HyperVRestoreVM,
	vmTarget string,
	progress *hypervRestoreProgress,
	runID string,
	globalDiskIdx *int,
) (restored int, canceled bool, lastErr error) {
	for diskPath, manifestID := range vm.DiskManifests {
		select {
		case <-ctx.Done():
			return restored, true, lastErr
		default:
		}

		progress.currentDisk = diskPath
		progress.currentDiskIdx = *globalDiskIdx

		diskName := filepath.Base(diskPath)
		targetFilePath := filepath.Join(vmTarget, diskName)

		log.Printf("agent: hyperv_restore restoring %s disk %d/%d: %s -> %s (manifest: %s)",
			vm.VMName, *globalDiskIdx+1, progress.totalDisks, diskPath, targetFilePath, manifestID)

		r.pushEvents(runID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "HYPERV_RESTORE_DISK_STARTING",
			ParamsJSON: map[string]any{
				"vm_name":     vm.VMName,
				"disk_name":   diskName,
				"disk_index":  *globalDiskIdx + 1,
				"total_disks": progress.totalDisks,
				"manifest_id": manifestID,
			},
		})

		err := r.kopiaRestoreVHDX(ctx, run, manifestID, targetFilePath, diskName, runID, progress.setCurrentBytes)
		if err != nil {
			if isCancellationError(err) {
				return restored, true, lastErr
			}
			log.Printf("agent: hyperv_restore disk %s failed: %v", diskName, err)
			r.pushEvents(runID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "HYPERV_RESTORE_DISK_FAILED",
				ParamsJSON: map[string]any{
					"vm_name":   vm.VMName,
					"disk_name": diskName,
					"message":   err.Error(),
				},
			})
			lastErr = err
		} else {
			restored++
			log.Printf("agent: hyperv_restore disk %s completed", diskName)
			if stat, statErr := os.Stat(targetFilePath); statErr == nil {
				progress.addCompletedBytes(stat.Size())
			}
			progress.reportProgress(true)
			r.pushEvents(runID, RunEvent{
				Type:      "info",
				Level:     "info",
				MessageID: "HYPERV_RESTORE_DISK_COMPLETE",
				ParamsJSON: map[string]any{
					"vm_name":     vm.VMName,
					"disk_name":   diskName,
					"disk_index":  *globalDiskIdx + 1,
					"total_disks": progress.totalDisks,
				},
			})
		}

		*globalDiskIdx++
	}
	return restored, false, lastErr
}

// restoreVMNamesLabel builds a compact comma-separated list of VM names for
// event payloads (e.g. "ubuntu26, win10").
func restoreVMNamesLabel(vms []HyperVRestoreVM) string {
	names := make([]string, 0, len(vms))
	for i := range vms {
		if vms[i].VMName != "" {
			names = append(names, vms[i].VMName)
		}
	}
	return strings.Join(names, ", ")
}

// parseHyperVRestorePayload parses the command payload into a typed struct.
func parseHyperVRestorePayload(payload map[string]any) *HyperVRestorePayload {
	if payload == nil {
		return nil
	}

	result := &HyperVRestorePayload{}

	if v, ok := payload["backup_point_id"].(float64); ok {
		result.BackupPointID = int64(v)
	}
	if v, ok := payload["vm_name"].(string); ok {
		result.VMName = v
	}
	if v, ok := payload["vm_guid"].(string); ok {
		result.VMGUID = v
	}
	if v, ok := payload["target_path"].(string); ok {
		result.TargetPath = v
	}
	if v, ok := payload["backup_type"].(string); ok {
		result.BackupType = v
	}
	if v, ok := payload["restore_run_id"].(float64); ok {
		result.RestoreRunID = int64(v)
	}
	if v, ok := payload["restore_run_uuid"].(string); ok {
		result.RestoreRunUUID = v
	}

	// Parse disk_manifests
	if v, ok := payload["disk_manifests"].(map[string]any); ok {
		result.DiskManifests = make(map[string]string)
		for k, val := range v {
			if s, ok := val.(string); ok {
				result.DiskManifests[k] = s
			}
		}
	}

	// Parse restore_chain (for incremental restores)
	if v, ok := payload["restore_chain"].([]any); ok {
		result.RestoreChain = parseRestoreChain(v)
	}

	// Parse vms[] (multi-VM restore). Each entry mirrors the single-VM fields.
	if v, ok := payload["vms"].([]any); ok {
		for _, item := range v {
			m, ok := item.(map[string]any)
			if !ok {
				continue
			}
			vm := HyperVRestoreVM{}
			if id, ok := m["backup_point_id"].(float64); ok {
				vm.BackupPointID = int64(id)
			}
			if s, ok := m["vm_name"].(string); ok {
				vm.VMName = s
			}
			if s, ok := m["vm_guid"].(string); ok {
				vm.VMGUID = s
			}
			if s, ok := m["subfolder"].(string); ok {
				vm.Subfolder = s
			}
			if s, ok := m["backup_type"].(string); ok {
				vm.BackupType = s
			}
			if dm, ok := m["disk_manifests"].(map[string]any); ok {
				vm.DiskManifests = parseDiskManifests(dm)
			}
			if rc, ok := m["restore_chain"].([]any); ok {
				vm.RestoreChain = parseRestoreChain(rc)
			}
			result.VMs = append(result.VMs, vm)
		}
	}

	return result
}

// parseDiskManifests converts a generic disk_path->manifest_id map.
func parseDiskManifests(v map[string]any) map[string]string {
	out := make(map[string]string, len(v))
	for k, val := range v {
		if s, ok := val.(string); ok {
			out[k] = s
		}
	}
	return out
}

// parseRestoreChain converts a generic restore_chain array into typed entries.
func parseRestoreChain(v []any) []RestoreChainEntry {
	var chain []RestoreChainEntry
	for _, item := range v {
		m, ok := item.(map[string]any)
		if !ok {
			continue
		}
		entry := RestoreChainEntry{}
		if id, ok := m["backup_point_id"].(float64); ok {
			entry.BackupPointID = int64(id)
		}
		if mid, ok := m["manifest_id"].(string); ok {
			entry.ManifestID = mid
		}
		if bt, ok := m["backup_type"].(string); ok {
			entry.BackupType = bt
		}
		if dm, ok := m["disk_manifests"].(map[string]any); ok {
			entry.DiskManifests = parseDiskManifests(dm)
		}
		chain = append(chain, entry)
	}
	return chain
}

// sanitizeSubfolderName produces a filesystem-safe folder name from a VM name.
func sanitizeSubfolderName(name string) string {
	cleaned := strings.Map(func(r rune) rune {
		switch r {
		case '<', '>', ':', '"', '/', '\\', '|', '?', '*':
			return '_'
		}
		if r < 0x20 {
			return '_'
		}
		return r
	}, name)
	cleaned = strings.TrimSpace(cleaned)
	cleaned = strings.Trim(cleaned, ".")
	if cleaned == "" {
		return "vm"
	}
	return cleaned
}

// buildNextRunResponseFromJobContext creates a NextRunResponse from job context for Kopia operations.
func buildNextRunResponseFromJobContext(jc *JobContext) *NextRunResponse {
	if jc == nil {
		return nil
	}
	return &NextRunResponse{
		RunID:          jc.RunID,
		JobID:          jc.JobID,
		Engine:         jc.Engine,
		DestBucketName: jc.DestBucketName,
		DestPrefix:     jc.DestPrefix,
		DestAccessKey:  jc.DestAccessKey,
		DestSecretKey:  jc.DestSecretKey,
		DestEndpoint:   jc.DestEndpoint,
		DestRegion:     jc.DestRegion,
	}
}
