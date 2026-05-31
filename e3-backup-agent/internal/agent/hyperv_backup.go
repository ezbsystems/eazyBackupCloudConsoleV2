//go:build windows
// +build windows

package agent

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"math"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/your-org/e3-backup-agent/internal/agent/hyperv"
)

// guidPattern matches a standard GUID format (xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)
var guidPattern = regexp.MustCompile(`^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$`)

// isGUID returns true if the string looks like a GUID
func isGUID(s string) bool {
	return guidPattern.MatchString(s)
}

// getVMByNameOrGUID tries to get a VM by name first, and if the name looks like a GUID, uses GetVMByGUID
func getVMByNameOrGUID(ctx context.Context, mgr *hyperv.Manager, vmName, vmGUID string) (*hyperv.VMInfo, error) {
	// If we have a valid GUID, prefer using it for lookup
	if vmGUID != "" && isGUID(vmGUID) {
		vm, err := mgr.GetVMByGUID(ctx, vmGUID)
		if err == nil {
			return vm, nil
		}
		log.Printf("agent: GetVMByGUID(%s) failed: %v, trying by name", vmGUID, err)
	}

	// If vmName looks like a GUID, try GUID lookup first
	if isGUID(vmName) {
		vm, err := mgr.GetVMByGUID(ctx, vmName)
		if err == nil {
			return vm, nil
		}
		// Fall through to try by name anyway (in case it's actually a VM named with a GUID-like string)
	}

	// Try by name
	return mgr.GetVM(ctx, vmName)
}

// hypervProgressTracker tracks cumulative progress across multiple VMs.
type hypervProgressTracker struct {
	runner                 *Runner
	runID                  string
	totalBytes             int64
	completedBytes         int64 // Bytes from fully completed VMs/disks
	currentBytes           int64 // Bytes processed in current disk (atomic)
	completedUploadedBytes int64 // Uploaded bytes from completed disks
	currentUploaded        int64 // Uploaded bytes for current disk (atomic)
	startTime              time.Time
	currentVM              string
	currentVMIndex         int
	totalVMs               int
	completedVMs           int
	mu                     sync.Mutex
	lastReportAt           time.Time
}

// addCompletedBytes adds bytes from a completed disk to the cumulative total.
func (p *hypervProgressTracker) addCompletedBytes(bytes int64) {
	p.mu.Lock()
	defer p.mu.Unlock()
	p.completedBytes += bytes
	atomic.StoreInt64(&p.currentBytes, 0) // Reset current disk counter
}

// finalizeCurrentDiskUpload moves current uploaded bytes into completed total.
func (p *hypervProgressTracker) finalizeCurrentDiskUpload() {
	p.mu.Lock()
	defer p.mu.Unlock()
	p.completedUploadedBytes += atomic.LoadInt64(&p.currentUploaded)
	atomic.StoreInt64(&p.currentUploaded, 0)
}

// updateCurrentBytes updates the bytes processed/uploaded in the current disk (called frequently).
func (p *hypervProgressTracker) updateCurrentBytes(bytesProcessed int64, bytesUploaded int64) {
	atomic.StoreInt64(&p.currentBytes, bytesProcessed)
	atomic.StoreInt64(&p.currentUploaded, bytesUploaded)
	p.reportProgress(false)
}

// vmCompleted marks a VM as completed.
func (p *hypervProgressTracker) vmCompleted() {
	p.mu.Lock()
	defer p.mu.Unlock()
	p.completedVMs++
}

// reportProgress sends a progress update to the server.
func (p *hypervProgressTracker) reportProgress(force bool) {
	p.mu.Lock()
	defer p.mu.Unlock()

	now := time.Now()
	if !force && now.Sub(p.lastReportAt) < 2*time.Second {
		return
	}
	p.lastReportAt = now

	currentDiskBytes := atomic.LoadInt64(&p.currentBytes)
	totalProcessed := p.completedBytes + currentDiskBytes
	currentUploaded := atomic.LoadInt64(&p.currentUploaded)
	totalUploaded := p.completedUploadedBytes + currentUploaded

	var progressPct float64
	if p.totalBytes > 0 {
		progressPct = math.Min(99.9, (float64(totalProcessed)/float64(p.totalBytes))*100.0)
	}

	// Calculate ETA based on elapsed time and progress
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
		BytesTransferred: Int64Ptr(totalUploaded),
		BytesProcessed:   Int64Ptr(totalProcessed),
		BytesTotal:       Int64Ptr(p.totalBytes),
		SpeedBytesPerSec: speedBps,
		EtaSeconds:       etaSeconds,
		CurrentItem:      fmt.Sprintf("VM %d/%d: %s", p.currentVMIndex+1, p.totalVMs, p.currentVM),
	})
}

// startHyperVFinalizeHeartbeat keeps the run "alive" in the UI during the
// post-upload finalization steps (checkpoint merge, reference-point pinning).
// These steps can run for several minutes — the online AVHDX merge of a
// running VM in particular scales with the guest writes accumulated during
// the upload window — yet they emit no upload progress, so without this the
// UI appears frozen at ~99.9%.
//
// The heartbeat refreshes the run's CurrentItem with an elapsed-time label
// and emits a lightweight progress event on a fixed cadence. It deliberately
// does NOT send ProgressPct (left zero so omitempty drops it), preserving the
// progress value the upload tracker last reported. Returns a stop function
// that must be called (typically via defer) when the step finishes.
func (r *Runner) startHyperVFinalizeHeartbeat(ctx context.Context, runID, vmName, phase string) func() {
	start := time.Now()
	done := make(chan struct{})
	var once sync.Once

	// Immediate first update so the UI flips to the finalize label without
	// waiting for the first tick.
	r.reportHyperVFinalize(runID, vmName, phase, 0)

	go func() {
		ticker := time.NewTicker(15 * time.Second)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-done:
				return
			case <-ticker.C:
				r.reportHyperVFinalize(runID, vmName, phase, time.Since(start))
			}
		}
	}()

	return func() { once.Do(func() { close(done) }) }
}

// startHyperVMergeHeartbeat is a merge-specific finalize heartbeat that
// reports REAL progress: it samples the attached AVHDX size (which shrinks as
// Hyper-V folds the differencing disk back into its parent) and surfaces the
// remaining megabytes plus a derived percentage. This turns the previously
// opaque post-upload "stall" at ~99.9% into an observable "merging checkpoint
// — N MB left (P%)" status. Falls back to an elapsed-time label whenever the
// AVHDX size cannot be sampled. Returns a stop function to call when done.
func (r *Runner) startHyperVMergeHeartbeat(ctx context.Context, mgr *hyperv.Manager, runID, vmName string) func() {
	start := time.Now()
	done := make(chan struct{})
	var once sync.Once

	// Capture the starting AVHDX size so we can express progress as a
	// percentage of what needs to be merged.
	initialBytes, _ := mgr.GetAttachedAvhdxBytes(ctx, vmName)

	report := func() {
		elapsed := time.Since(start)
		remaining, err := mgr.GetAttachedAvhdxBytes(ctx, vmName)
		if err != nil || initialBytes <= 0 {
			r.reportHyperVFinalize(runID, vmName, "merging backup checkpoint", elapsed)
			return
		}
		mergedPct := 0.0
		if initialBytes > 0 {
			mergedPct = math.Min(100.0, float64(initialBytes-remaining)/float64(initialBytes)*100.0)
			if mergedPct < 0 {
				mergedPct = 0
			}
		}
		item := fmt.Sprintf("Finalizing %s: merging backup checkpoint — %d MB left (%.0f%%, %ds)",
			vmName, remaining/(1024*1024), mergedPct, int(elapsed.Seconds()))
		_ = r.client.UpdateRun(RunUpdate{RunID: runID, Status: "running", CurrentItem: item})
		r.pushEvents(runID, RunEvent{
			Type:      "progress",
			Level:     "info",
			MessageID: "HYPERV_FINALIZING",
			ParamsJSON: map[string]any{
				"vm_name":           vmName,
				"phase":             "merging backup checkpoint",
				"elapsed_seconds":   int(elapsed.Seconds()),
				"avhdx_bytes_left":  remaining,
				"avhdx_bytes_total": initialBytes,
				"merged_pct":        mergedPct,
				"message":           item,
			},
		})
	}

	report() // immediate first update
	go func() {
		ticker := time.NewTicker(10 * time.Second)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-done:
				return
			case <-ticker.C:
				report()
			}
		}
	}()
	return func() { once.Do(func() { close(done) }) }
}

// reportHyperVFinalize pushes a single finalization status update + event.
func (r *Runner) reportHyperVFinalize(runID, vmName, phase string, elapsed time.Duration) {
	item := fmt.Sprintf("Finalizing %s: %s", vmName, phase)
	elapsedSecs := int(elapsed.Seconds())
	if elapsedSecs >= 1 {
		item = fmt.Sprintf("Finalizing %s: %s (%ds)", vmName, phase, elapsedSecs)
	}
	// ProgressPct intentionally omitted (0 -> dropped by omitempty) so we keep
	// the last upload progress value instead of resetting the bar.
	_ = r.client.UpdateRun(RunUpdate{
		RunID:       runID,
		Status:      "running",
		CurrentItem: item,
	})
	r.pushEvents(runID, RunEvent{
		Type:      "progress",
		Level:     "info",
		MessageID: "HYPERV_FINALIZING",
		ParamsJSON: map[string]any{
			"vm_name":         vmName,
			"phase":           phase,
			"elapsed_seconds": elapsedSecs,
			"message":         item,
		},
	})
}

// runHyperV executes a Hyper-V VM backup job.
func (r *Runner) runHyperV(run *NextRunResponse) error {
	startedAt := time.Now().UTC()
	_ = r.client.UpdateRun(RunUpdate{
		RunID:     run.RunID,
		Status:    "running",
		StartedAt: startedAt.Format(time.RFC3339),
	})
	resetParallelReads := setParallelDiskReadsOverride(policyBool(run.PolicyJSON, "parallel_disk_reads"))
	resetStrictReadErrors := setStrictReadErrorsOverride(policyBool(run.PolicyJSON, "disk_image_strict_read_errors"))
	defer resetParallelReads()
	defer resetStrictReadErrors()

	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "BACKUP_STARTING",
		ParamsJSON: map[string]any{
			"engine": "hyperv",
		},
	})

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	// Start cancel polling goroutine
	cancelPollDone := make(chan struct{})
	go func() {
		defer close(cancelPollDone)
		ticker := time.NewTicker(3 * time.Second)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-ticker.C:
				cancelReq, _, errCmd := r.pollCommands(run.RunID)
				if errCmd != nil {
					log.Printf("agent: hyperv cancel poll error: %v", errCmd)
					continue
				}
				if cancelReq {
					log.Printf("agent: hyperv cancel requested for run %s", run.RunID)
					r.pushEvents(run.RunID, RunEvent{
						Type:      "cancelled",
						Level:     "warn",
						MessageID: "CANCEL_REQUESTED",
						ParamsJSON: map[string]any{
							"message": "Backup cancellation requested by user",
						},
					})
					cancel()
					return
				}
			}
		}
	}()
	defer func() {
		cancel()         // Ensure context is cancelled to stop polling goroutine
		<-cancelPollDone // Wait for polling goroutine to finish
	}()

	// Validate Hyper-V configuration
	if run.HyperVConfig == nil {
		run.HyperVConfig = &HyperVConfig{
			EnableRCT:        true,
			ConsistencyLevel: "application",
		}
	}

	if len(run.HyperVVMs) == 0 {
		err := fmt.Errorf("hyperv: no VMs configured for backup")
		r.pushEvents(run.RunID, RunEvent{
			Type:      "error",
			Level:     "error",
			MessageID: "HYPERV_NO_VMS",
			ParamsJSON: map[string]any{
				"error": err.Error(),
			},
		})
		_ = r.client.UpdateRun(RunUpdate{
			RunID:        run.RunID,
			Status:       "failed",
			ErrorSummary: err.Error(),
			FinishedAt:   time.Now().UTC().Format(time.RFC3339),
		})
		return err
	}

	// Initialize Hyper-V manager
	mgr := hyperv.NewManager()
	rct := hyperv.NewRCTEngine(mgr)

	// Calculate total bytes across all VMs for accurate progress tracking
	var totalBytesAllVMs int64
	vmInfoCache := make(map[string]*hyperv.VMInfo)
	for _, vmRun := range run.HyperVVMs {
		vm, err := getVMByNameOrGUID(ctx, mgr, vmRun.VMName, vmRun.VMGUID)
		if err != nil {
			log.Printf("agent: hyperv pre-scan failed for %s (GUID: %s): %v", vmRun.VMName, vmRun.VMGUID, err)
			continue
		}
		// Cache by GUID if available for reliable lookup later
		cacheKey := vmRun.VMName
		if vmRun.VMGUID != "" {
			cacheKey = vmRun.VMGUID
		}
		vmInfoCache[cacheKey] = vm
		for _, disk := range vm.Disks {
			totalBytesAllVMs += disk.SizeBytes
		}
	}

	// Report total size for progress calculation
	if totalBytesAllVMs > 0 {
		_ = r.client.UpdateRun(RunUpdate{
			RunID:        run.RunID,
			BytesTotal:   Int64Ptr(totalBytesAllVMs),
			ObjectsTotal: int64(len(run.HyperVVMs)),
		})
		log.Printf("agent: hyperv total backup size: %d bytes across %d VMs", totalBytesAllVMs, len(run.HyperVVMs))
	}

	// Create cumulative progress tracker
	progressTracker := &hypervProgressTracker{
		runner:         r,
		runID:          run.RunID,
		totalBytes:     totalBytesAllVMs,
		completedBytes: 0,
		startTime:      startedAt,
		currentVM:      "",
		totalVMs:       len(run.HyperVVMs),
		completedVMs:   0,
	}

	// Process each VM
	var allResults []HyperVVMResult
	diskManifests := make(map[string]string)
	var lastErr error
	successCount := 0
	failCount := 0
	warningCount := 0
	var failedVMs []string
	var warningVMs []string
	var successVMs []string

	for vmIdx, vmRun := range run.HyperVVMs {
		// Check for cancellation before starting each VM
		if ctx.Err() != nil {
			log.Printf("agent: hyperv backup cancelled before starting VM=%s", vmRun.VMName)
			break
		}

		progressTracker.currentVM = vmRun.VMName
		progressTracker.currentVMIndex = vmIdx
		progressTracker.reportProgress(true) // Force initial progress report for this VM

		log.Printf("agent: hyperv backup starting VM=%s (id=%d)", vmRun.VMName, vmRun.VMID)
		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "HYPERV_VM_STARTING",
			ParamsJSON: map[string]any{
				"vm_name": vmRun.VMName,
				"vm_id":   vmRun.VMID,
				"message": fmt.Sprintf("Starting backup of virtual machine '%s' (%d of %d)", vmRun.VMName, vmIdx+1, len(run.HyperVVMs)),
			},
		})

		_ = r.client.UpdateRun(RunUpdate{
			RunID:       run.RunID,
			CurrentItem: fmt.Sprintf("VM %d/%d: %s", vmIdx+1, len(run.HyperVVMs), vmRun.VMName),
		})

		// Use cached VM info if available (try GUID first, then name), otherwise fetch
		var cachedVM *hyperv.VMInfo
		if vmRun.VMGUID != "" {
			cachedVM = vmInfoCache[vmRun.VMGUID]
		}
		if cachedVM == nil {
			cachedVM = vmInfoCache[vmRun.VMName]
		}
		result, err := r.backupHyperVVM(ctx, run, vmRun, mgr, rct, progressTracker, cachedVM)
		if err != nil {
			// Check if this is a cancellation - don't treat as failure
			if isCancellationError(err) {
				log.Printf("agent: hyperv VM %s backup cancelled", vmRun.VMName)
				// Don't increment failCount or add to failedVMs for cancellation
				lastErr = err
				r.pushEvents(run.RunID, RunEvent{
					Type:      "cancelled",
					Level:     "info",
					MessageID: "CANCELLED",
					ParamsJSON: map[string]any{
						"vm_name": vmRun.VMName,
						"message": fmt.Sprintf("Backup of VM '%s' was cancelled.", vmRun.VMName),
					},
				})
			} else {
				log.Printf("agent: hyperv VM %s backup failed: %v", vmRun.VMName, err)
				result.Error = simplifyError(err)
				lastErr = err
				failCount++
				failedVMs = append(failedVMs, vmRun.VMName)

				// Generate user-friendly error message
				userMessage := generateUserFriendlyVMError(vmRun.VMName, err)

				r.pushEvents(run.RunID, RunEvent{
					Type:      "error",
					Level:     "error",
					MessageID: "HYPERV_VM_FAILED",
					ParamsJSON: map[string]any{
						"vm_name":       vmRun.VMName,
						"error":         simplifyError(err),
						"message":       userMessage,
						"technical_log": simplifyError(err),
					},
				})
			}
		} else {
			log.Printf("agent: hyperv VM %s backup complete: type=%s checkpoint=%s",
				vmRun.VMName, result.BackupType, result.CheckpointID)
			successCount++
			successVMs = append(successVMs, vmRun.VMName)

			// Mark VM as complete. Do NOT re-add result.TotalBytes here: the
			// per-disk loop already folded each disk's bytes into the tracker's
			// cumulative total via addCompletedBytes(effectiveSize) as it
			// finished. Adding result.TotalBytes (the sum of those same disk
			// sizes) a second time double-counts the VM, which made the bar
			// saturate at ~99.9% after the first VM instead of advancing across
			// the whole multi-VM backup.
			progressTracker.vmCompleted()
			progressTracker.reportProgress(true) // Force progress update

			// Track VMs with warnings
			if len(result.Warnings) > 0 {
				warningCount++
				warningVMs = append(warningVMs, vmRun.VMName)
			}

			// Generate user-friendly success message
			consistencyMsg := "application-consistent"
			if result.ConsistencyLevel == string(hyperv.ConsistencyCrash) {
				consistencyMsg = "crash-consistent"
			} else if result.ConsistencyLevel == string(hyperv.ConsistencyCrashNoCheckpoint) {
				consistencyMsg = "crash-consistent (live backup, no checkpoint)"
			}

			r.pushEvents(run.RunID, RunEvent{
				Type:      "info",
				Level:     "info",
				MessageID: "HYPERV_VM_COMPLETE",
				ParamsJSON: map[string]any{
					"vm_name":       vmRun.VMName,
					"backup_type":   result.BackupType,
					"changed_bytes": result.ChangedBytes,
					"total_bytes":   result.TotalBytes,
					"message": fmt.Sprintf("VM '%s' backed up successfully (%s, %s)",
						vmRun.VMName, result.BackupType, consistencyMsg),
					"consistency": consistencyMsg,
					"warnings":    result.Warnings,
				},
			})

			// Collect disk manifests
			for diskPath, manifestID := range result.DiskManifests {
				diskManifests[diskPath] = manifestID
			}
		}
		allResults = append(allResults, result)
	}

	// Determine final status
	status := "success"
	errMsg := ""
	var summaryMessage string
	summaryLevel := "info"

	// Check if cancelled
	wasCancelled := ctx.Err() != nil

	if wasCancelled {
		status = "cancelled"
		remainingVMs := len(run.HyperVVMs) - successCount - failCount
		if successCount > 0 {
			summaryMessage = fmt.Sprintf("Backup cancelled: %d VM(s) completed before cancellation (%s), %d VM(s) not started.",
				successCount, strings.Join(successVMs, ", "), remainingVMs)
		} else {
			summaryMessage = "Backup cancelled by user."
		}
		summaryLevel = "warn"
		log.Printf("agent: hyperv backup cancelled - %d completed, %d failed, %d not started",
			successCount, failCount, remainingVMs)
	} else if failCount > 0 && successCount > 0 {
		status = "partial_success"
		errMsg = fmt.Sprintf("%d of %d VMs failed", failCount, len(run.HyperVVMs))
		summaryMessage = fmt.Sprintf("Partial backup: %d VM(s) completed (%s), %d VM(s) failed (%s). "+
			"Check the failed VMs for details.",
			successCount, strings.Join(successVMs, ", "),
			failCount, strings.Join(failedVMs, ", "))
		summaryLevel = "warning"
	} else if failCount > 0 {
		status = "failed"
		if lastErr != nil {
			errMsg = lastErr.Error()
		}
		summaryMessage = fmt.Sprintf("Backup failed: All %d VM(s) could not be backed up (%s). "+
			"Check the error details above for more information.",
			failCount, strings.Join(failedVMs, ", "))
		summaryLevel = "error"
	} else if warningCount > 0 {
		// All succeeded but with warnings
		summaryMessage = fmt.Sprintf("Backup completed with warnings: %d VM(s) backed up successfully, "+
			"%d VM(s) had warnings (%s). Review the warnings for potential issues.",
			successCount, warningCount, strings.Join(warningVMs, ", "))
		summaryLevel = "warning"
	} else {
		summaryMessage = fmt.Sprintf("Backup completed successfully: All %d VM(s) backed up (%s).",
			successCount, strings.Join(successVMs, ", "))
	}

	finishedAt := time.Now().UTC().Format(time.RFC3339)
	_ = r.client.UpdateRun(RunUpdate{
		RunID:             run.RunID,
		Status:            status,
		ErrorSummary:      errMsg,
		FinishedAt:        finishedAt,
		DiskManifestsJSON: diskManifests,
		HyperVResults:     allResults,
	})

	// Push summary event
	r.pushEvents(run.RunID, RunEvent{
		Type:      "summary",
		Level:     summaryLevel,
		MessageID: "HYPERV_BACKUP_COMPLETE",
		ParamsJSON: map[string]any{
			"success_count": successCount,
			"fail_count":    failCount,
			"warning_count": warningCount,
			"total_count":   len(run.HyperVVMs),
			"status":        status,
			"message":       summaryMessage,
			"success_vms":   successVMs,
			"failed_vms":    failedVMs,
			"warning_vms":   warningVMs,
		},
	})

	return lastErr
}

// backupHyperVVM backs up a single VM and returns the result.
func (r *Runner) backupHyperVVM(
	ctx context.Context,
	run *NextRunResponse,
	vmRun HyperVVMRun,
	mgr *hyperv.Manager,
	rct *hyperv.RCTEngine,
	progressTracker *hypervProgressTracker,
	cachedVM *hyperv.VMInfo,
) (HyperVVMResult, error) {
	startTime := time.Now()
	result := HyperVVMResult{
		VMID:          vmRun.VMID,
		VMName:        vmRun.VMName,
		RCTIDs:        make(map[string]string),
		DiskManifests: make(map[string]string),
	}

	// Use cached VM info if available, otherwise fetch
	var vm *hyperv.VMInfo
	var err error
	if cachedVM != nil {
		vm = cachedVM
	} else {
		vm, err = getVMByNameOrGUID(ctx, mgr, vmRun.VMName, vmRun.VMGUID)
	}
	if err != nil {
		return result, fmt.Errorf("get VM info: %w", err)
	}

	// Check VM state
	vmState := hyperv.VMState(vm.State)
	if !vmState.CanBackup() {
		return result, fmt.Errorf("VM in unsupported state: %s", vm.State)
	}

	// Determine backup type. We rely on Microsoft's RCT WMI surface here:
	// Msvm_VirtualSystemReferencePointService keeps the per-disk RCT
	// generation alive across backups, and Msvm_ImageManagementService::
	// GetVirtualDiskChanges (issued from internal/agent/hyperv/rct_wmi.go)
	// reports the changed byte ranges for each disk relative to the prior
	// reference point.
	//
	// On hosts without the reference-point service (Windows Server 2012R2
	// and older) we always do a Full backup; that is also the documented
	// upper bound of where RCT exists at all on the Hyper-V platform.
	backupType := hyperv.BackupTypeFull
	var rctInfos []hyperv.RCTInfo
	hostHasRefPoints := hyperv.HostHasReferencePointService(ctx)

	if !hostHasRefPoints {
		log.Printf("agent: hyperv host lacks Msvm_VirtualSystemReferencePointService for %s; performing full backup (RCT unavailable on this Windows version)", vmRun.VMName)
	} else if vmRun.LastCheckpointID != "" && run.HyperVConfig.EnableRCT && len(vmRun.LastRCTIDs) > 0 {
		// Confirm the previous reference point is still resident on the host.
		// If Hyper-V evicted it (live migration, host reboot edge cases,
		// administrator cleanup) the RCT chain is broken and we MUST fall
		// back to Full. Cheaper than a per-disk WMI round-trip just to
		// discover the same thing.
		priorAlive := false
		if rps, err := mgr.ListReferencePoints(ctx, vmRun.VMName); err != nil {
			log.Printf("agent: hyperv list reference points failed for %s, performing full: %v", vmRun.VMName, err)
		} else {
			for _, rp := range rps {
				if rp.InstanceID == vmRun.LastCheckpointID {
					priorAlive = true
					break
				}
			}
		}
		if !priorAlive {
			log.Printf("agent: hyperv prior reference point %s for %s is no longer resident on host; performing full", vmRun.LastCheckpointID, vmRun.VMName)
		} else {
			rctInfos, err = rct.GetChangedBlocks(ctx, vmRun.VMName, vmRun.LastRCTIDs)
			if err != nil {
				log.Printf("agent: hyperv GetVirtualDiskChanges failed for %s, performing full: %v", vmRun.VMName, err)
			} else if hyperv.AreAllDisksRCTValid(rctInfos) {
				backupType = hyperv.BackupTypeIncremental
				result.ChangedBytes = hyperv.CalculateTotalChangedBytes(rctInfos)
				log.Printf("agent: hyperv incremental backup for %s, changed bytes: %d", vmRun.VMName, result.ChangedBytes)
			} else {
				for _, info := range rctInfos {
					if !info.Valid {
						log.Printf("agent: hyperv RCT data invalid for disk %s of %s: %s", info.DiskPath, vmRun.VMName, info.Error)
					}
				}
				log.Printf("agent: hyperv RCT data invalid for some disks of %s, performing full", vmRun.VMName)
			}
		}
	}
	result.BackupType = string(backupType)

	// Create checkpoint for consistent backup
	var checkpoint *hyperv.CheckpointInfo
	consistencyLevel := hyperv.ConsistencyApplication
	checkpointDisabled := false

	if run.HyperVConfig.ConsistencyLevel == "crash" {
		// User requested crash-consistent
		checkpoint, err = mgr.CreateReferenceCheckpoint(ctx, vmRun.VMName)
		consistencyLevel = hyperv.ConsistencyCrash
	} else if vm.IsLinux {
		// Linux VM - use fsfreeze via integration services
		checkpoint, err = mgr.CreateLinuxConsistentCheckpoint(ctx, vmRun.VMName)
		if err != nil {
			log.Printf("agent: hyperv Linux checkpoint failed for %s, trying reference: %v", vmRun.VMName, err)
			checkpoint, err = mgr.CreateReferenceCheckpoint(ctx, vmRun.VMName)
			consistencyLevel = hyperv.ConsistencyCrash
		}
	} else {
		// Windows VM - use VSS
		checkpoint, err = mgr.CreateVSSCheckpoint(ctx, vmRun.VMName)
		if err != nil {
			log.Printf("agent: hyperv VSS checkpoint failed for %s, trying reference: %v", vmRun.VMName, err)
			checkpoint, err = mgr.CreateReferenceCheckpoint(ctx, vmRun.VMName)
			consistencyLevel = hyperv.ConsistencyCrash
		}
	}

	// Check if checkpoints are disabled for this VM - fall back to crash-consistent live backup
	if err != nil && isCheckpointsDisabledError(err) {
		log.Printf("agent: hyperv checkpoints disabled for %s, falling back to crash-consistent live backup", vmRun.VMName)
		checkpointDisabled = true
		consistencyLevel = hyperv.ConsistencyCrashNoCheckpoint
		result.Warnings = append(result.Warnings, fmt.Sprintf(
			"Checkpoints are disabled for VM '%s'. Performing crash-consistent backup without checkpoint. "+
				"This is less safe than application-consistent backup. To enable checkpoints, go to Hyper-V Manager → "+
				"VM Settings → Checkpoints → Enable checkpoints.", vmRun.VMName))
		result.WarningCode = "CHECKPOINTS_DISABLED"

		r.pushEvents(run.RunID, RunEvent{
			Type:      "warning",
			Level:     "warning",
			MessageID: "HYPERV_CHECKPOINTS_DISABLED",
			ParamsJSON: map[string]any{
				"vm_name": vmRun.VMName,
				"message": fmt.Sprintf("Checkpoints are disabled for '%s'. Using crash-consistent backup instead.", vmRun.VMName),
			},
		})
		err = nil // Clear error - we're proceeding with live backup
	}

	if err != nil {
		return result, fmt.Errorf("create checkpoint: %w", err)
	}

	if checkpoint != nil {
		result.CheckpointID = checkpoint.ID
		log.Printf("agent: hyperv checkpoint created for %s: id=%s type=%s", vmRun.VMName, checkpoint.ID, checkpoint.SnapshotType)
		// Reflect the ACTUAL checkpoint type in the reported consistency level
		// instead of assuming the production path succeeded. Checkpoint-VM
		// honours the VM's configured CheckpointType, so a VM set to "Standard"
		// (or a guest without working Hyper-V VSS integration, e.g. a storage
		// appliance) yields a crash-consistent snapshot even though we asked for
		// the production/application-consistent path. Only a "Production*"
		// snapshot is genuinely application-consistent. Don't override an
		// explicit crash request or the no-checkpoint live-backup path.
		if consistencyLevel == hyperv.ConsistencyApplication &&
			!strings.HasPrefix(strings.ToLower(strings.TrimSpace(checkpoint.SnapshotType)), "production") {
			log.Printf("agent: hyperv checkpoint for %s is type=%q (not Production); reporting crash-consistent", vmRun.VMName, checkpoint.SnapshotType)
			consistencyLevel = hyperv.ConsistencyCrash
		}
	} else if checkpointDisabled {
		log.Printf("agent: hyperv proceeding with live backup for %s (no checkpoint)", vmRun.VMName)
	}
	result.ConsistencyLevel = string(consistencyLevel)

	// Back up each disk
	for i := range vm.Disks {
		disk := &vm.Disks[i] // Use pointer to allow size correction

		if ctx.Err() != nil {
			return result, ctx.Err()
		}

		// Always prefer actual file size over the virtual disk size from Get-VHD.
		// Get-VHD returns the virtual capacity (e.g., 127 GB) which can be much
		// larger than the actual VHDX file on disk for dynamically expanding disks.
		effectiveSize := disk.SizeBytes
		if fi, err := os.Stat(disk.Path); err == nil && fi.Size() > 0 {
			actualFileSize := fi.Size()
			if effectiveSize == 0 {
				effectiveSize = actualFileSize
				log.Printf("agent: hyperv disk %s size was 0, using actual file size: %d bytes", disk.Path, effectiveSize)
				if progressTracker != nil {
					progressTracker.mu.Lock()
					progressTracker.totalBytes += effectiveSize
					progressTracker.mu.Unlock()
				}
			} else if actualFileSize < effectiveSize {
				log.Printf("agent: hyperv disk %s correcting size: virtual=%d actual=%d", disk.Path, effectiveSize, actualFileSize)
				if progressTracker != nil {
					progressTracker.mu.Lock()
					progressTracker.totalBytes -= (effectiveSize - actualFileSize)
					progressTracker.mu.Unlock()
				}
				effectiveSize = actualFileSize
			}
		} else if err != nil && effectiveSize == 0 {
			log.Printf("agent: hyperv warning: could not stat disk %s to get size: %v", disk.Path, err)
		}

		log.Printf("agent: hyperv backing up disk: %s (size=%d)", disk.Path, effectiveSize)

		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "HYPERV_DISK_STARTING",
			ParamsJSON: map[string]any{
				"vm_name":   vmRun.VMName,
				"disk_path": disk.Path,
				"size":      effectiveSize,
			},
		})

		var manifestID string

		// Progress callback for cumulative tracking
		progressCallback := func(bytesProcessed int64, bytesUploaded int64) {
			if progressTracker != nil {
				progressTracker.updateCurrentBytes(bytesProcessed, bytesUploaded)
			}
		}

		if backupType == hyperv.BackupTypeIncremental {
			// Find RCT info for this disk
			diskRCT := hyperv.FindDiskRCTInfo(rctInfos, disk.Path)
			if diskRCT != nil && diskRCT.Valid && len(diskRCT.ChangedBlocks) > 0 {
				manifestID, err = r.backupVHDXSparse(ctx, run, disk.Path, effectiveSize, diskRCT.ChangedBlocks, progressCallback, checkpointDisabled)
				if err == nil {
					result.RCTIDs[disk.Path] = diskRCT.RCTID
				}
			} else {
				// Fall back to full for this disk
				log.Printf("agent: hyperv no valid RCT data for disk %s, using full backup", disk.Path)
				manifestID, err = r.backupVHDXFull(ctx, run, disk.Path, effectiveSize, progressCallback, checkpointDisabled)
			}
		} else {
			manifestID, err = r.backupVHDXFull(ctx, run, disk.Path, effectiveSize, progressCallback, checkpointDisabled)
		}

		// After disk completes, add its bytes to cumulative total
		if err == nil && progressTracker != nil {
			progressTracker.addCompletedBytes(effectiveSize)
			progressTracker.finalizeCurrentDiskUpload()
		}

		if err != nil {
			// Cleanup checkpoint before returning (if we created one)
			if checkpoint != nil {
				_ = mgr.MergeCheckpoint(ctx, vmRun.VMName, checkpoint.ID)
			}
			return result, fmt.Errorf("backup disk %s: %w", disk.Path, err)
		}

		result.DiskManifests[disk.Path] = manifestID
		result.TotalBytes += effectiveSize

		log.Printf("agent: hyperv disk %s backed up, manifest=%s", disk.Path, manifestID)
	}

	priorRefPointID := vmRun.LastCheckpointID
	// Clear the production-checkpoint ID we recorded earlier; the only thing
	// that survives this backup is the RCT reference point we are about to
	// (try to) pin. If we cannot pin one we leave CheckpointID empty so the
	// server records null and the next run runs as Full.
	result.CheckpointID = ""

	// Merge the production checkpoint BEFORE creating the new reference
	// point. Hyper-V's Msvm_VirtualSystemReferencePointService refuses to
	// create a reference point while the VM has an active production
	// checkpoint with redirected writes (it returns ErrorCode 32775,
	// "Element Not Available"). After the merge the live VHDX contains
	// the backed-up state plus whatever guest writes happened during the
	// backup window; the RCT generation we anchor here therefore covers
	// "everything we just wrote to Kopia". The next incremental backup
	// uses GetVirtualDiskChanges with this RCT ID, picking up only
	// whatever changes occur from now on.
	if checkpoint != nil {
		// The online AVHDX merge of a running VM is the dominant cost of the
		// finalization phase and produces no upload progress; surface it with
		// a heartbeat so the UI does not appear frozen at ~99.9%, and time it
		// so the real per-step cost is measurable.
		stopHB := r.startHyperVMergeHeartbeat(ctx, mgr, run.RunID, vmRun.VMName)
		mergeStart := time.Now()
		mergeErr := mgr.MergeCheckpoint(ctx, vmRun.VMName, checkpoint.ID)
		stopHB()
		mergeMs := time.Since(mergeStart).Milliseconds()
		log.Printf("agent: hyperv checkpoint merge for %s completed in %dms", vmRun.VMName, mergeMs)
		debugLog(run.RunID, "hyperv_checkpoint_merge_done", map[string]any{
			"vm_name":     vmRun.VMName,
			"duration_ms": mergeMs,
			"error":       errString(mergeErr),
		}, "HV1")
		if mergeErr != nil {
			log.Printf("agent: hyperv warning: failed to merge checkpoint for %s: %v", vmRun.VMName, mergeErr)
		}
	}

	if hostHasRefPoints && run.HyperVConfig.EnableRCT && checkpoint != nil {
		// Try the application-consistent path first; this is what
		// Microsoft's RCT samples use and it carries any guest VSS state
		// captured during the just-merged production checkpoint into the
		// reference-point marker. If the guest can't quiesce (Linux
		// without a working hv_vss_daemon, Windows guests where the VSS
		// writer rejects the freeze, or Server 2025 hosts where the
		// Application path is occasionally rejected with state=10/no
		// detail), retry once with the crash-consistent ConsistencyLevel.
		// Either yields a usable RCT generation for GetVirtualDiskChanges,
		// so we can still pin the chain instead of forcing the next run
		// back to Full.
		//
		// (Measured on the Server 2025 lab host, the application path is the
		// one that succeeds — ~8s — while a standalone crash-consistent
		// reference point is rejected with JobState=10; do NOT reorder these
		// without re-measuring on the target host.) This step is wrapped with
		// a finalize heartbeat so the brief WMI quiesce does not look frozen,
		// and timed so its cost is visible relative to the checkpoint merge.
		stopHB := r.startHyperVFinalizeHeartbeat(ctx, run.RunID, vmRun.VMName, "enabling change tracking")
		rpStart := time.Now()
		newRP, rpErr := mgr.CreateReferencePointWithConsistency(ctx, vmRun.VMName, hyperv.RefPointApplication)
		if rpErr != nil {
			log.Printf("agent: hyperv CreateReferencePoint(application) failed for %s, retrying crash-consistent: %v", vmRun.VMName, rpErr)
			var rpErr2 error
			newRP, rpErr2 = mgr.CreateReferencePointWithConsistency(ctx, vmRun.VMName, hyperv.RefPointCrash)
			if rpErr2 != nil {
				log.Printf("agent: hyperv CreateReferencePoint(crash) also failed for %s; next run will be Full: %v", vmRun.VMName, rpErr2)
				rpErr = rpErr2
			} else {
				rpErr = nil
				log.Printf("agent: hyperv reference point pinned crash-consistent for %s: %s", vmRun.VMName, newRP.InstanceID)
			}
		}
		stopHB()
		rpMs := time.Since(rpStart).Milliseconds()
		log.Printf("agent: hyperv reference point step for %s completed in %dms (err=%v)", vmRun.VMName, rpMs, rpErr)
		debugLog(run.RunID, "hyperv_reference_point_done", map[string]any{
			"vm_name":     vmRun.VMName,
			"duration_ms": rpMs,
			"error":       errString(rpErr),
		}, "HV1")
		if rpErr != nil {
			// keep the existing log above as the final failure marker.
			//
			// Surface this to the customer. The backup itself is complete and
			// valid (all disks were already uploaded above), but without a pinned
			// RCT reference point the next run cannot run incrementally and will
			// read/re-scan every disk in full again. That is a performance /
			// efficiency notice, not a data-integrity failure, so the run still
			// completes with status "success" — the non-empty result.Warnings
			// just flips the summary to "completed with warnings".
			result.Warnings = append(result.Warnings, fmt.Sprintf(
				"Change tracking (CBT) could not be enabled for VM '%s': Hyper-V refused to create a "+
					"reference point. The backup completed successfully and is valid, but the next backup "+
					"will be a full scan of every disk instead of an incremental.", vmRun.VMName))
			if result.WarningCode == "" {
				result.WarningCode = "REFERENCE_POINT_FAILED"
			}
			r.pushEvents(run.RunID, RunEvent{
				Type:      "warning",
				Level:     "warning",
				MessageID: "HYPERV_REFERENCE_POINT_FAILED",
				ParamsJSON: map[string]any{
					"vm_name": vmRun.VMName,
					"message": fmt.Sprintf("Change tracking could not be enabled for '%s'. The backup "+
						"completed successfully, but the next backup will be a full scan instead of an "+
						"incremental.", vmRun.VMName),
				},
			})
		} else {
			result.CheckpointID = newRP.InstanceID
			// The reference point's per-disk RCT IDs are what
			// GetVirtualDiskChanges will demand as LimitId next time round;
			// these always supersede whatever Get-VHD reports against the
			// live VHDX (which may have advanced again since we read it).
			if rpRCTIDs, err := mgr.ReferencePointDiskRCTIDs(ctx, newRP.InstanceID); err == nil {
				for diskPath, id := range rpRCTIDs {
					result.RCTIDs[diskPath] = id
				}
			} else {
				log.Printf("agent: hyperv ReferencePointDiskRCTIDs(%s) failed for %s: %v", newRP.InstanceID, vmRun.VMName, err)
			}
			// Best-effort cleanup of the prior reference point. A leftover
			// reference point is harmless (Hyper-V keeps a tiny RCT marker,
			// no AVHDX), but accumulating them indefinitely is wasteful.
			if priorRefPointID != "" && priorRefPointID != newRP.InstanceID {
				if err := mgr.DestroyReferencePoint(ctx, priorRefPointID); err != nil {
					log.Printf("agent: hyperv DestroyReferencePoint(%s) for %s failed: %v", priorRefPointID, vmRun.VMName, err)
				}
			}
		}
	}

	// As a backstop, fill in any per-disk RCT IDs we did not capture from the
	// reference point (e.g. crash-consistent fallback path with no checkpoint).
	if run.HyperVConfig.EnableRCT && len(result.RCTIDs) == 0 {
		currentRCTIDs, err := rct.GetCurrentRCTIDs(ctx, vmRun.VMName)
		if err == nil {
			for diskPath, rctID := range currentRCTIDs {
				if _, exists := result.RCTIDs[diskPath]; !exists {
					result.RCTIDs[diskPath] = rctID
				}
			}
		}
	}

	result.DurationSeconds = int(time.Since(startTime).Seconds())
	return result, nil
}

// backupVHDXFull backs up an entire VHDX file using Kopia.
// ProgressCallback is called during backup to report bytes processed and uploaded.
type ProgressCallback func(bytesProcessed int64, bytesUploaded int64)

func (r *Runner) backupVHDXFull(ctx context.Context, run *NextRunResponse, vhdxPath string, size int64, progressCb ProgressCallback, liveDisk bool) (string, error) {
	reader, err := newFullVHDXReader(vhdxPath)
	if err != nil {
		return "", err
	}
	defer reader.Close()

	// Always prefer the actual file size for backup operations.
	// Get-VHD returns $vhd.Size (virtual disk capacity) which can be much larger
	// than the actual VHDX file on disk for dynamically expanding disks.
	// The parallel reader needs the real file size so isNearTail() correctly
	// identifies locked VHDX metadata regions at the physical end of the file.
	actualSize := reader.Size()
	if actualSize > 0 && actualSize != size {
		log.Printf("agent: hyperv correcting disk size: reported=%d actual_file=%d (using actual)", size, actualSize)
		size = actualSize
	} else if size == 0 && actualSize > 0 {
		log.Printf("agent: hyperv disk size was 0, using actual file size: %d bytes", actualSize)
		size = actualSize
	}

	return r.backupVHDXWithReader(ctx, run, vhdxPath, size, reader, progressCb, liveDisk)
}

// backupVHDXSparse backs up only changed blocks of a VHDX file.
func (r *Runner) backupVHDXSparse(ctx context.Context, run *NextRunResponse, vhdxPath string, size int64, changedBlocks []hyperv.ChangedBlockRange, progressCb ProgressCallback, liveDisk bool) (string, error) {
	// Always prefer the actual file size over the virtual disk size from Get-VHD.
	if fi, err := os.Stat(vhdxPath); err == nil && fi.Size() > 0 {
		actualSize := fi.Size()
		if actualSize != size {
			log.Printf("agent: hyperv correcting sparse disk size: reported=%d actual_file=%d (using actual)", size, actualSize)
			size = actualSize
		}
	} else if size == 0 {
		log.Printf("agent: hyperv warning: could not determine disk size for %s", vhdxPath)
	}

	reader, err := newSparseVHDXReader(vhdxPath, size, changedBlocks)
	if err != nil {
		return "", err
	}
	defer reader.Close()

	return r.backupVHDXWithReader(ctx, run, vhdxPath, size, reader, progressCb, liveDisk)
}

// backupVHDXWithReader streams a VHDX to Kopia using the provided reader.
// liveDisk indicates the VHDX is actively locked by Hyper-V (no checkpoint),
// so the parallel reader should skip tail reads entirely (zero-fill instead).
func (r *Runner) backupVHDXWithReader(ctx context.Context, run *NextRunResponse, vhdxPath string, size int64, reader interface {
	Read([]byte) (int, error)
	Seek(int64, int) (int64, error)
	Close() error
}, progressCb ProgressCallback, liveDisk bool) (string, error) {
	// Create a stable source identifier for Kopia deduplication
	// Use the disk path as the source so subsequent snapshots dedupe properly
	diskName := filepath.Base(vhdxPath)
	stablePath := strings.TrimSuffix(diskName, filepath.Ext(diskName))

	// Create warning callback to push read warnings to the UI
	warningCb := func(warning diskReadWarning) {
		level := "info"
		messageID := "HYPERV_DISK_READ_WARNING"
		if warning.NearTail {
			level = "warning"
		}
		// Check if this is a timeout warning
		if strings.Contains(warning.Error, "timeout") {
			level = "warning"
			messageID = "HYPERV_DISK_READ_TIMEOUT"
		}
		r.pushEvents(run.RunID, RunEvent{
			Type:      level,
			Level:     level,
			MessageID: messageID,
			ParamsJSON: map[string]any{
				"disk":           filepath.Base(warning.Path),
				"reader":         warning.Reader,
				"offset_bytes":   warning.OffsetBytes,
				"request_bytes":  warning.RequestedBytes,
				"read_bytes":     warning.ReadBytes,
				"zero_filled":    warning.ZeroFilledBytes,
				"near_tail":      warning.NearTail,
				"error":          warning.Error,
			},
		})
	}

	// Create stream entry for Kopia
	entry := &deviceEntry{
		name:            fmt.Sprintf("%s.vhdx", stablePath),
		path:            vhdxPath,
		size:            size,
		skipTailReads:   liveDisk,
		warningCallback: warningCb,
	}

	// Use the existing Kopia snapshot mechanism
	origEngine := run.Engine
	origSource := run.SourcePath
	run.Engine = "kopia"
	run.SourcePath = vhdxPath

	manifestID, err := r.kopiaSnapshotDiskImageWithProgress(ctx, run, entry, size, vhdxPath, progressCb, true)

	run.Engine = origEngine
	run.SourcePath = origSource

	if err != nil {
		return "", err
	}

	return manifestID, nil
}

// discoverHyperVVMs discovers VMs on the host and reports them to the server.
func (r *Runner) discoverHyperVVMs(ctx context.Context, jobID int64) error {
	mgr := hyperv.NewManager()

	vms, err := mgr.ListVMs(ctx)
	if err != nil {
		return fmt.Errorf("list vms: %w", err)
	}

	log.Printf("agent: discovered %d Hyper-V VMs", len(vms))

	// Report to server
	// This would call the agent_hyperv_discover.php endpoint
	// Implementation depends on adding the API client method

	return nil
}

// executeListHypervVMsCommand handles the list_hyperv_vms command from the server.
// This is called when the UI needs to discover VMs for job creation.
func (r *Runner) executeListHypervVMsCommand(ctx context.Context, cmd PendingCommand) {
	log.Printf("agent: executing list_hyperv_vms command %d", cmd.CommandID)

	mgr := hyperv.NewManager()

	vms, err := mgr.ListVMs(ctx)
	if err != nil {
		log.Printf("agent: list_hyperv_vms command %d failed: %v", cmd.CommandID, err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "Failed to list VMs: "+err.Error())
		return
	}

	log.Printf("agent: discovered %d Hyper-V VMs for command %d", len(vms), cmd.CommandID)

	// Convert to JSON-friendly format
	vmList := make([]map[string]interface{}, 0, len(vms))
	for _, vm := range vms {
		vmData := map[string]interface{}{
			"id":                   vm.ID,
			"name":                 vm.Name,
			"state":                vm.State,
			"generation":           vm.Generation,
			"cpu_count":            vm.CPUCount,
			"memory_mb":            vm.MemoryMB,
			"integration_services": vm.IntegrationSvcs,
			"is_linux":             vm.IsLinux,
			"rct_enabled":          vm.RCTEnabled,
			"disk_count":           len(vm.Disks),
		}

		vmList = append(vmList, vmData)
	}

	// Create result JSON
	result := map[string]interface{}{
		"vms":   vmList,
		"count": len(vmList),
	}

	if err := r.client.ReportBrowseResult(cmd.CommandID, result); err != nil {
		log.Printf("agent: list_hyperv_vms command %d failed to report: %v", cmd.CommandID, err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "report hyperv vms failed: "+err.Error())
		return
	}

	log.Printf("agent: list_hyperv_vms command %d completed successfully", cmd.CommandID)
}

// executeListHypervVMDetailsCommand handles the list_hyperv_vm_details command from the server.
// This returns disk details for selected VMs only.
func (r *Runner) executeListHypervVMDetailsCommand(ctx context.Context, cmd PendingCommand) {
	log.Printf("agent: executing list_hyperv_vm_details command %d", cmd.CommandID)

	vmIDs := []string{}
	if cmd.Payload != nil {
		if v, ok := cmd.Payload["vm_ids"]; ok {
			switch t := v.(type) {
			case []any:
				for _, item := range t {
					if s, ok := item.(string); ok && strings.TrimSpace(s) != "" {
						vmIDs = append(vmIDs, strings.TrimSpace(s))
					}
				}
			case []string:
				for _, s := range t {
					if strings.TrimSpace(s) != "" {
						vmIDs = append(vmIDs, strings.TrimSpace(s))
					}
				}
			case string:
				trimmed := strings.TrimSpace(t)
				if trimmed != "" {
					var decoded []string
					if err := json.Unmarshal([]byte(trimmed), &decoded); err == nil {
						for _, s := range decoded {
							if strings.TrimSpace(s) != "" {
								vmIDs = append(vmIDs, strings.TrimSpace(s))
							}
						}
					}
				}
			}
		}
	}

	if len(vmIDs) == 0 {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "vm_ids is required")
		return
	}

	mgr := hyperv.NewManager()
	details := make([]map[string]interface{}, 0, len(vmIDs))

	for _, vmID := range vmIDs {
		vm, err := getVMByNameOrGUID(ctx, mgr, vmID, vmID)
		if err != nil {
			log.Printf("agent: list_hyperv_vm_details command %d failed for %s: %v", cmd.CommandID, vmID, err)
			continue
		}

		disks := make([]map[string]interface{}, 0, len(vm.Disks))
		for _, disk := range vm.Disks {
			disks = append(disks, map[string]interface{}{
				"path":            disk.Path,
				"size_bytes":      disk.SizeBytes,
				"used_bytes":      disk.UsedBytes,
				"vhd_format":      disk.VHDFormat,
				"rct_enabled":     disk.RCTEnabled,
				"controller_type": disk.ControllerType,
			})
		}

		details = append(details, map[string]interface{}{
			"id":         vm.ID,
			"name":       vm.Name,
			"disk_count": len(vm.Disks),
			"disks":      disks,
		})
	}

	result := map[string]interface{}{
		"details": details,
		"count":   len(details),
	}

	if err := r.client.ReportBrowseResult(cmd.CommandID, result); err != nil {
		log.Printf("agent: list_hyperv_vm_details command %d failed to report: %v", cmd.CommandID, err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "report hyperv vm details failed: "+err.Error())
		return
	}

	log.Printf("agent: list_hyperv_vm_details command %d completed successfully", cmd.CommandID)
}

// checkContextCancelled checks if the context has been cancelled.
func checkContextCancelled(ctx context.Context) error {
	select {
	case <-ctx.Done():
		return errors.New("operation cancelled")
	default:
		return nil
	}
}

// isCheckpointsDisabledError checks if the error indicates checkpoints are disabled for the VM.
// This happens when Hyper-V settings for the VM have checkpoints disabled.
func isCheckpointsDisabledError(err error) bool {
	if err == nil {
		return false
	}
	errMsg := strings.ToLower(err.Error())
	return strings.Contains(errMsg, "checkpoints have been disabled") ||
		strings.Contains(errMsg, "checkpoint operation failed") && strings.Contains(errMsg, "disabled")
}

// generateUserFriendlyVMError converts technical errors into user-friendly messages.
func generateUserFriendlyVMError(vmName string, err error) string {
	if err == nil {
		return ""
	}

	errMsg := strings.ToLower(err.Error())

	// Check for cancellation - this is not an error
	if isCancellationError(err) {
		return fmt.Sprintf("Backup of VM '%s' was cancelled.", vmName)
	}

	// Checkpoint disabled
	if strings.Contains(errMsg, "checkpoints have been disabled") {
		return fmt.Sprintf("Checkpoints are disabled for VM '%s'. "+
			"Please enable checkpoints in Hyper-V Manager: Right-click VM → Settings → Checkpoints → Enable checkpoints. "+
			"Alternatively, the backup will use crash-consistent mode if available.", vmName)
	}

	// Permission errors
	if strings.Contains(errMsg, "permission") || strings.Contains(errMsg, "access denied") ||
		strings.Contains(errMsg, "required permission") {
		return fmt.Sprintf("Permission denied when backing up VM '%s'. "+
			"Ensure the backup agent is running as Administrator or the SYSTEM account, "+
			"and the user has Hyper-V Administrator rights.", vmName)
	}

	// VM not found
	if strings.Contains(errMsg, "not found") || strings.Contains(errMsg, "cannot find") {
		return fmt.Sprintf("Virtual machine '%s' was not found. "+
			"The VM may have been deleted, renamed, or moved to a different host.", vmName)
	}

	// VM state issues
	if strings.Contains(errMsg, "unsupported state") || strings.Contains(errMsg, "invalid state") {
		return fmt.Sprintf("VM '%s' is in a state that cannot be backed up. "+
			"VMs must be running, saved, or powered off to be backed up.", vmName)
	}

	// Disk access errors
	if strings.Contains(errMsg, "backup disk") || strings.Contains(errMsg, "vhdx") ||
		strings.Contains(errMsg, "virtual hard disk") {
		return fmt.Sprintf("Failed to backup virtual disk for VM '%s'. "+
			"The disk file may be locked, corrupted, or inaccessible. "+
			"Check that the VHD/VHDX file exists and is not being accessed by another process.", vmName)
	}

	// Timeout
	if strings.Contains(errMsg, "timeout") || strings.Contains(errMsg, "timed out") {
		return fmt.Sprintf("Backup of VM '%s' timed out. "+
			"This may happen with large VMs or slow storage. "+
			"Consider increasing timeout settings or backing up during lower activity periods.", vmName)
	}

	// VSS errors
	if strings.Contains(errMsg, "vss") || strings.Contains(errMsg, "volume shadow") {
		return fmt.Sprintf("VSS (Volume Shadow Copy) failed for VM '%s'. "+
			"The backup will use crash-consistent mode instead of application-consistent. "+
			"Check that VSS is working correctly on the Hyper-V host.", vmName)
	}

	// Integration services errors
	if strings.Contains(errMsg, "integration") || strings.Contains(errMsg, "kvp") {
		return fmt.Sprintf("Hyper-V Integration Services issue with VM '%s'. "+
			"For best backup consistency, ensure Integration Services are installed and running in the guest VM.", vmName)
	}

	// Storage errors
	if strings.Contains(errMsg, "storage") || strings.Contains(errMsg, "disk space") ||
		strings.Contains(errMsg, "no space") {
		return fmt.Sprintf("Storage error while backing up VM '%s'. "+
			"Check that there is sufficient disk space on the Hyper-V host for checkpoint operations.", vmName)
	}

	// Network errors (when uploading backup)
	if strings.Contains(errMsg, "network") || strings.Contains(errMsg, "connection") ||
		strings.Contains(errMsg, "upload") {
		return fmt.Sprintf("Network error while uploading backup for VM '%s'. "+
			"Check network connectivity and try again. Partial backups may resume automatically.", vmName)
	}

	// Fallback: return a cleaned-up version of the error
	return fmt.Sprintf("Backup of VM '%s' failed: %s. "+
		"Please review the technical details or contact support if the issue persists.", vmName, simplifyError(err))
}

// errString returns the error message, or "" for a nil error. Used for
// structured finalization timing logs.
func errString(err error) string {
	if err == nil {
		return ""
	}
	return err.Error()
}

// simplifyError extracts a simpler message from complex error chains.
func simplifyError(err error) string {
	if err == nil {
		return ""
	}

	msg := err.Error()

	// Remove PowerShell script noise
	if idx := strings.Index(msg, "At line:"); idx > 0 {
		msg = strings.TrimSpace(msg[:idx])
	}

	// Remove "powershell error:" prefix
	msg = strings.TrimPrefix(msg, "powershell error: ")
	msg = strings.TrimPrefix(msg, "create checkpoint: ")
	msg = strings.TrimPrefix(msg, "create vss checkpoint for ")
	msg = strings.TrimPrefix(msg, "create reference checkpoint for ")

	// Sanitize internal implementation names (kopia -> eazyBackup)
	msg = strings.ReplaceAll(msg, "kopia:", "backup engine:")
	msg = strings.ReplaceAll(msg, "Kopia", "eazyBackup")
	msg = strings.ReplaceAll(msg, "kopia", "eazyBackup")

	// Truncate if too long
	if len(msg) > 200 {
		msg = msg[:200] + "..."
	}

	return msg
}
