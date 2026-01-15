//go:build windows
// +build windows

package agent

import (
	"context"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"sync/atomic"
	"time"
)

// HyperVRestorePayload represents the payload for a hyperv_restore command.
type HyperVRestorePayload struct {
	BackupPointID  int64             `json:"backup_point_id"`
	VMName         string            `json:"vm_name"`
	VMGUID         string            `json:"vm_guid"`
	TargetPath     string            `json:"target_path"`
	DiskManifests  map[string]string `json:"disk_manifests"`  // disk_path -> manifest_id
	RestoreChain   []RestoreChainEntry `json:"restore_chain"`
	BackupType     string            `json:"backup_type"`     // "Full" or "Incremental"
	RestoreRunID   int64             `json:"restore_run_id"`
	RestoreRunUUID string            `json:"restore_run_uuid"`
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
	runID          int64
	totalBytes     int64
	completedBytes int64
	currentBytes   int64 // atomic
	startTime      time.Time
	currentDisk    string
	currentDiskIdx int
	totalDisks     int
	lastReportAt   time.Time
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
		CurrentItem:      fmt.Sprintf("Disk %d/%d: %s", p.currentDiskIdx+1, p.totalDisks, filepath.Base(p.currentDisk)),
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

	if len(payload.DiskManifests) == 0 {
		log.Printf("agent: hyperv_restore command %d no disk manifests", cmd.CommandID)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "no disk manifests to restore")
		return
	}

	// Use restore_run_id for progress tracking
	runID := payload.RestoreRunID
	if runID == 0 {
		runID = cmd.RunID
	}

	log.Printf("agent: starting hyperv_restore for VM %s (%d disks) to %s",
		payload.VMName, len(payload.DiskManifests), payload.TargetPath)

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
					log.Printf("agent: hyperv_restore cancel requested for run %d", runID)
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
		cancelRestore() // Ensure context is cancelled to stop polling goroutine
		<-cancelPollDone // Wait for polling goroutine to finish
	}()

	// Update run status to running
	_ = r.client.UpdateRun(RunUpdate{
		RunID:     runID,
		Status:    "running",
		StartedAt: time.Now().UTC().Format(time.RFC3339),
	})

	// Push starting event
	r.pushEvents(runID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "HYPERV_RESTORE_STARTING",
		ParamsJSON: map[string]any{
			"vm_name":    payload.VMName,
			"disk_count": len(payload.DiskManifests),
			"target_path": payload.TargetPath,
			"backup_type": payload.BackupType,
		},
	})

	// Create target directory
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

	// Calculate total size for progress tracking
	var totalBytes int64
	for diskPath := range payload.DiskManifests {
		// Try to get disk size from the path name or estimate
		// In real implementation, this would come from backup metadata
		// For now, we'll rely on progress updates during restore
		_ = diskPath
	}

	// Create progress tracker
	progress := &hypervRestoreProgress{
		runner:     r,
		runID:      runID,
		totalBytes: totalBytes,
		startTime:  time.Now(),
		totalDisks: len(payload.DiskManifests),
	}

	// Build NextRunResponse for Kopia operations
	run := buildNextRunResponseFromJobContext(cmd.JobContext)

	// Restore each disk
	diskIdx := 0
	var lastErr error
	restoredDisks := 0

	for diskPath, manifestID := range payload.DiskManifests {
		select {
		case <-restoreCtx.Done():
			log.Printf("agent: hyperv_restore cancelled")
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
		default:
		}

		progress.currentDisk = diskPath
		progress.currentDiskIdx = diskIdx

		diskName := filepath.Base(diskPath)
		targetFilePath := filepath.Join(payload.TargetPath, diskName)

		log.Printf("agent: hyperv_restore restoring disk %d/%d: %s -> %s (manifest: %s)",
			diskIdx+1, len(payload.DiskManifests), diskPath, targetFilePath, manifestID)

		r.pushEvents(runID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "HYPERV_RESTORE_DISK_STARTING",
			ParamsJSON: map[string]any{
				"disk_name":   diskName,
				"disk_index":  diskIdx + 1,
				"total_disks": len(payload.DiskManifests),
				"manifest_id": manifestID,
			},
		})

		// Restore this disk using Kopia (use restoreCtx for cancellation support)
		err := r.kopiaRestoreVHDX(restoreCtx, run, manifestID, targetFilePath, diskName, runID)
		if err != nil {
			log.Printf("agent: hyperv_restore disk %s failed: %v", diskName, err)
			
			// Check if it's a cancellation
			if isCancellationError(err) {
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

			r.pushEvents(runID, RunEvent{
				Type:      "error",
				Level:     "error",
				MessageID: "HYPERV_RESTORE_DISK_FAILED",
				ParamsJSON: map[string]any{
					"disk_name": diskName,
					"message":   err.Error(),
				},
			})
			lastErr = err
			// Continue with other disks
		} else {
			restoredDisks++
			log.Printf("agent: hyperv_restore disk %s completed", diskName)

			// Get file size for progress
			if stat, statErr := os.Stat(targetFilePath); statErr == nil {
				progress.addCompletedBytes(stat.Size())
			}

			r.pushEvents(runID, RunEvent{
				Type:      "info",
				Level:     "info",
				MessageID: "HYPERV_RESTORE_DISK_COMPLETE",
				ParamsJSON: map[string]any{
					"disk_name":   diskName,
					"disk_index":  diskIdx + 1,
					"total_disks": len(payload.DiskManifests),
				},
			})
		}

		diskIdx++
	}

	// Determine final status
	var status string
	var message string
	if lastErr != nil && restoredDisks == 0 {
		status = "failed"
		message = fmt.Sprintf("All disk restores failed. Last error: %v", lastErr)
	} else if lastErr != nil {
		status = "warning"
		message = fmt.Sprintf("%d of %d disks restored. Some disks failed.", restoredDisks, len(payload.DiskManifests))
	} else {
		status = "success"
		message = fmt.Sprintf("All %d disks restored successfully to %s", restoredDisks, payload.TargetPath)
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
			"total_disks":    len(payload.DiskManifests),
			"target_path":    payload.TargetPath,
		},
	})

	_ = r.client.UpdateRun(RunUpdate{
		RunID:      runID,
		Status:     status,
		FinishedAt: time.Now().UTC().Format(time.RFC3339),
	})

	if status == "failed" {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", message)
	} else {
		_ = r.client.CompleteCommand(cmd.CommandID, "completed", message)
	}
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
		for _, item := range v {
			if m, ok := item.(map[string]any); ok {
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
					entry.DiskManifests = make(map[string]string)
					for k, val := range dm {
						if s, ok := val.(string); ok {
							entry.DiskManifests[k] = s
						}
					}
				}
				result.RestoreChain = append(result.RestoreChain, entry)
			}
		}
	}

	return result
}

// buildNextRunResponseFromJobContext creates a NextRunResponse from job context for Kopia operations.
func buildNextRunResponseFromJobContext(jc *JobContext) *NextRunResponse {
	if jc == nil {
		return nil
	}
	return &NextRunResponse{
		RunID:         jc.RunID,
		JobID:         jc.JobID,
		Engine:        jc.Engine,
		DestBucketName: jc.DestBucketName,
		DestPrefix:    jc.DestPrefix,
		DestAccessKey: jc.DestAccessKey,
		DestSecretKey: jc.DestSecretKey,
		DestEndpoint:  jc.DestEndpoint,
		DestRegion:    jc.DestRegion,
	}
}

