package agent

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"math"
	"runtime"
	"sort"
	"strings"
	"time"
)

type DiskRestorePayload struct {
	ManifestID       string `json:"manifest_id"`
	RestoreRunID     int64  `json:"restore_run_id"`
	TargetDisk       string `json:"target_disk"`
	TargetDiskBytes  int64  `json:"target_disk_bytes"`
	DiskLayoutJSON   any    `json:"disk_layout_json"`
	ShrinkEnabled    bool   `json:"shrink_enabled"`
	AllowBootMismatch bool  `json:"allow_boot_mismatch"`
}

type partitionPlan struct {
	Index      int
	StartBytes int64
	SizeBytes  int64
	FileSystem string
	PartType   string
	IsEFI      bool
	IsSystem   bool
	IsRecovery bool
}

func (r *Runner) executeDiskRestoreCommand(ctx context.Context, cmd PendingCommand) {
	if cmd.JobContext == nil {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing job context")
		return
	}

	payload := DiskRestorePayload{}
	if cmd.Payload != nil {
		if v, ok := cmd.Payload["manifest_id"].(string); ok {
			payload.ManifestID = v
		}
		if v, ok := cmd.Payload["restore_run_id"].(float64); ok {
			payload.RestoreRunID = int64(v)
		}
		if v, ok := cmd.Payload["target_disk"].(string); ok {
			payload.TargetDisk = v
		}
		if v, ok := cmd.Payload["target_disk_bytes"].(float64); ok {
			payload.TargetDiskBytes = int64(v)
		}
		if v, ok := cmd.Payload["disk_layout_json"]; ok {
			payload.DiskLayoutJSON = v
		}
		if v, ok := cmd.Payload["shrink_enabled"].(bool); ok {
			payload.ShrinkEnabled = v
		}
		if v, ok := cmd.Payload["allow_boot_mismatch"].(bool); ok {
			payload.AllowBootMismatch = v
		}
	}

	if payload.ManifestID == "" {
		payload.ManifestID = cmd.JobContext.ManifestID
	}

	if payload.ManifestID == "" || payload.TargetDisk == "" {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing manifest_id or target_disk")
		return
	}

	trackingRunID := cmd.RunID
	if payload.RestoreRunID > 0 {
		trackingRunID = payload.RestoreRunID
	}

	log.Printf("agent: disk_restore command=%d manifest=%s target=%s tracking_run=%d", cmd.CommandID, payload.ManifestID, payload.TargetDisk, trackingRunID)

	startedAt := time.Now().UTC()
	_ = r.client.UpdateRun(RunUpdate{
		RunID:     trackingRunID,
		Status:    "running",
		StartedAt: startedAt.Format(time.RFC3339),
	})

	r.pushEvents(trackingRunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "DISK_RESTORE_STARTING",
		ParamsJSON: map[string]any{
			"manifest_id": payload.ManifestID,
			"target_disk": payload.TargetDisk,
		},
	})

	restoreCtx, cancel := context.WithCancel(ctx)
	defer cancel()

	cancelPollDone := make(chan struct{})
	if r.client != nil {
		go func() {
			defer close(cancelPollDone)
			ticker := time.NewTicker(3 * time.Second)
			defer ticker.Stop()
			for {
				select {
				case <-restoreCtx.Done():
					return
				case <-ticker.C:
					var cancelReq bool
					var errCmd error
					if r.client.recoverySessionToken == "" {
						cancelReq, _, errCmd = r.pollCommands(trackingRunID)
					} else {
						cancelReq, errCmd = r.client.PollRecoveryCancel(trackingRunID)
					}
					if errCmd != nil {
						log.Printf("agent: disk restore cancel poll error: %v", errCmd)
						continue
					}
					if cancelReq {
						log.Printf("agent: disk restore cancel requested for run %d", trackingRunID)
						r.pushEvents(trackingRunID, RunEvent{
							Type:      "cancelled",
							Level:     "warn",
							MessageID: "CANCEL_REQUESTED",
						})
						cancel()
						return
					}
				}
			}
		}()
		defer func() {
			cancel()
			<-cancelPollDone
		}()
	} else {
		close(cancelPollDone)
	}

	layout := parseDiskLayoutPayload(payload.DiskLayoutJSON)
	if layout == nil {
		layout = &DiskLayout{}
	}

	if layout.BootMode != "" && layout.BootMode != "unknown" {
		mode := currentBootMode()
		if mode != "" && mode != "unknown" && !strings.EqualFold(mode, layout.BootMode) && !payload.AllowBootMismatch {
			err := fmt.Errorf("boot mode mismatch: source=%s target=%s", layout.BootMode, mode)
			r.finishDiskRestoreWithError(trackingRunID, cmd.CommandID, err)
			return
		}
	}

	targetSize := payload.TargetDiskBytes
	if targetSize <= 0 {
		if size, err := getDeviceSize(payload.TargetDisk); err == nil {
			targetSize = size
		}
	}

	if layout.UsedBytes > 0 && targetSize > 0 && targetSize < layout.UsedBytes {
		err := fmt.Errorf("target disk is smaller than total used bytes")
		r.finishDiskRestoreWithError(trackingRunID, cmd.CommandID, err)
		return
	}

	needsShrink := layout.TotalBytes > 0 && targetSize > 0 && targetSize < layout.TotalBytes
	if needsShrink && !payload.ShrinkEnabled {
		err := fmt.Errorf("target disk is smaller than source; shrink not enabled")
		r.finishDiskRestoreWithError(trackingRunID, cmd.CommandID, err)
		return
	}

	if needsShrink && runtime.GOOS == "windows" {
		err := fmt.Errorf("shrink restores are only supported in Linux recovery environment")
		r.finishDiskRestoreWithError(trackingRunID, cmd.CommandID, err)
		return
	}

	plan, planErr := buildShrinkPlan(restoreCtx, layout, targetSize)
	if needsShrink && planErr != nil {
		r.finishDiskRestoreWithError(trackingRunID, cmd.CommandID, planErr)
		return
	}

	if needsShrink && len(plan) > 0 {
		if err := applyDiskLayout(payload.TargetDisk, layout, plan); err != nil {
			r.finishDiskRestoreWithError(trackingRunID, cmd.CommandID, err)
			return
		}
	}

	var extents []DiskExtent
	if layoutHasUsedExtents(layout) {
		if needsShrink {
			extents, planErr = buildRestoreExtents(restoreCtx, layout, plan)
			if planErr != nil {
				r.finishDiskRestoreWithError(trackingRunID, cmd.CommandID, planErr)
				return
			}
		} else {
			extents = buildRestoreExtentsFromLayout(layout)
		}
		if len(extents) > 0 {
			diskBytes := layout.TotalBytes
			if targetSize > 0 {
				diskBytes = targetSize
			}
			extents = addDiskMetadataExtents(extents, diskBytes, layout.PartitionStyle)
			extents = normalizeDiskExtents(extents, diskBytes)
		}
		if needsShrink && len(extents) == 0 {
			r.finishDiskRestoreWithError(trackingRunID, cmd.CommandID, fmt.Errorf("no block map available for shrink restore"))
			return
		}
	}

	run := &NextRunResponse{
		RunID:                   cmd.JobContext.RunID,
		JobID:                   cmd.JobContext.JobID,
		Engine:                  cmd.JobContext.Engine,
		SourcePath:              cmd.JobContext.SourcePath,
		DestType:                cmd.JobContext.DestType,
		DestBucketName:          cmd.JobContext.DestBucketName,
		DestPrefix:              cmd.JobContext.DestPrefix,
		DestLocalPath:           cmd.JobContext.DestLocalPath,
		DestEndpoint:            cmd.JobContext.DestEndpoint,
		DestRegion:              cmd.JobContext.DestRegion,
		DestAccessKey:           cmd.JobContext.DestAccessKey,
		DestSecretKey:           cmd.JobContext.DestSecretKey,
		LocalBandwidthLimitKbps: cmd.JobContext.LocalBandwidthLimitKbps,
		PolicyJSON:              cmd.JobContext.PolicyJSON,
	}

	if err := r.kopiaRestoreDiskImageToDevice(restoreCtx, run, payload.ManifestID, payload.TargetDisk, extents, trackingRunID); err != nil {
		r.finishDiskRestoreWithError(trackingRunID, cmd.CommandID, err)
		return
	}

	if needsShrink {
		if err := resizeFileSystems(layout, plan); err != nil {
			log.Printf("agent: resize filesystems warning: %v", err)
		}
	}

	if err := repairBoot(payload.TargetDisk, layout, plan); err != nil {
		log.Printf("agent: disk restore boot repair warning: %v", err)
	}

	r.pushEvents(trackingRunID, RunEvent{
		Type:      "summary",
		Level:     "info",
		MessageID: "DISK_RESTORE_COMPLETED",
		ParamsJSON: map[string]any{
			"target_disk": payload.TargetDisk,
		},
	})

	_ = r.client.UpdateRun(RunUpdate{
		RunID:      trackingRunID,
		Status:     "success",
		FinishedAt: time.Now().UTC().Format(time.RFC3339),
	})

	_ = r.client.CompleteCommand(cmd.CommandID, "completed", "disk restore complete")
}

func (r *Runner) finishDiskRestoreWithError(runID int64, commandID int64, err error) {
	if errors.Is(err, context.Canceled) {
		msg := "Restore cancelled"
		r.pushEvents(runID, RunEvent{
			Type:      "cancelled",
			Level:     "warn",
			MessageID: "CANCELLED",
		})
		_ = r.client.UpdateRun(RunUpdate{
			RunID:        runID,
			Status:       "cancelled",
			ErrorSummary: msg,
			FinishedAt:   time.Now().UTC().Format(time.RFC3339),
		})
		_ = r.client.CompleteCommand(commandID, "cancelled", msg)
		return
	}
	msg := sanitizeErrorMessage(err)
	r.pushEvents(runID, RunEvent{
		Type:      "error",
		Level:     "error",
		MessageID: "DISK_RESTORE_FAILED",
		ParamsJSON: map[string]any{
			"error": msg,
		},
	})
	_ = r.client.UpdateRun(RunUpdate{
		RunID:        runID,
		Status:       "failed",
		ErrorSummary: msg,
		FinishedAt:   time.Now().UTC().Format(time.RFC3339),
	})
	_ = r.client.CompleteCommand(commandID, "failed", msg)
}

func parseDiskLayoutPayload(raw any) *DiskLayout {
	switch v := raw.(type) {
	case map[string]any:
		buf, _ := json.Marshal(v)
		var layout DiskLayout
		if err := json.Unmarshal(buf, &layout); err == nil {
			return &layout
		}
	case string:
		trimmed := strings.TrimSpace(v)
		if trimmed == "" {
			return nil
		}
		var layout DiskLayout
		if err := json.Unmarshal([]byte(trimmed), &layout); err == nil {
			return &layout
		}
	}
	return nil
}

func buildShrinkPlan(ctx context.Context, layout *DiskLayout, targetBytes int64) ([]partitionPlan, error) {
	if layout == nil || len(layout.Partitions) == 0 {
		return nil, fmt.Errorf("disk layout missing")
	}
	if targetBytes <= 0 {
		return nil, fmt.Errorf("target disk size missing")
	}
	var plans []partitionPlan
	var maxEnd int64
	for _, p := range layout.Partitions {
		if err := ctx.Err(); err != nil {
			return nil, err
		}
		plan := partitionPlan{
			Index:      p.Index,
			StartBytes: p.StartBytes,
			SizeBytes:  p.SizeBytes,
			FileSystem: p.FileSystem,
			PartType:   p.PartType,
			IsEFI:      p.IsEFI,
			IsSystem:   p.IsSystem,
			IsRecovery: p.IsRecovery,
		}
		if p.UsedBytes > 0 && (p.FileSystem == "ntfs" || p.FileSystem == "ext4") {
			margin := int64(256 * 1024 * 1024) // 256MB cushion
			minSize := p.UsedBytes + margin
			if len(p.UsedExtents) > 0 {
				var maxEnd int64
				for _, ext := range p.UsedExtents {
					end := ext.OffsetBytes + ext.LengthBytes
					if end > maxEnd {
						maxEnd = end
					}
				}
				if maxEnd > minSize {
					minSize = maxEnd + margin
				}
			}
			if minSize < 512*1024*1024 {
				minSize = 512 * 1024 * 1024
			}
			if minSize < p.SizeBytes {
				plan.SizeBytes = alignMiB(minSize)
			}
		}
		end := plan.StartBytes + plan.SizeBytes
		if end > maxEnd {
			maxEnd = end
		}
		plans = append(plans, plan)
	}
	if maxEnd > targetBytes {
		return nil, fmt.Errorf("target disk too small for shrink plan")
	}
	return plans, nil
}

func buildRestoreExtents(ctx context.Context, layout *DiskLayout, plan []partitionPlan) ([]DiskExtent, error) {
	if layout == nil {
		return nil, nil
	}
	planByIndex := map[int]partitionPlan{}
	for _, p := range plan {
		if err := ctx.Err(); err != nil {
			return nil, err
		}
		planByIndex[p.Index] = p
	}
	var extents []DiskExtent
	for _, p := range layout.Partitions {
		if err := ctx.Err(); err != nil {
			return nil, err
		}
		if len(p.UsedExtents) == 0 {
			continue
		}
		planPart, ok := planByIndex[p.Index]
		if !ok {
			continue
		}
		for _, e := range p.UsedExtents {
			if err := ctx.Err(); err != nil {
				return nil, err
			}
			globalOffset := planPart.StartBytes + e.OffsetBytes
			extents = append(extents, DiskExtent{
				OffsetBytes: globalOffset,
				LengthBytes: e.LengthBytes,
			})
		}
	}
	return extents, nil
}

func layoutHasUsedExtents(layout *DiskLayout) bool {
	if layout == nil {
		return false
	}
	for _, p := range layout.Partitions {
		if len(p.UsedExtents) > 0 {
			return true
		}
	}
	return false
}

func buildRestoreExtentsFromLayout(layout *DiskLayout) []DiskExtent {
	if layout == nil {
		return nil
	}
	var extents []DiskExtent
	for _, p := range layout.Partitions {
		if len(p.UsedExtents) == 0 {
			continue
		}
		for _, e := range p.UsedExtents {
			if e.LengthBytes <= 0 {
				continue
			}
			globalOffset := p.StartBytes + e.OffsetBytes
			extents = append(extents, DiskExtent{
				OffsetBytes: globalOffset,
				LengthBytes: e.LengthBytes,
			})
		}
	}
	return extents
}

func addDiskMetadataExtents(extents []DiskExtent, diskBytes int64, partitionStyle string) []DiskExtent {
	if diskBytes <= 0 {
		return extents
	}
	const headerBytes = int64(2 * 1024 * 1024)
	primaryLen := headerBytes
	if primaryLen > diskBytes {
		primaryLen = diskBytes
	}
	extents = append(extents, DiskExtent{OffsetBytes: 0, LengthBytes: primaryLen})

	if strings.EqualFold(partitionStyle, "gpt") {
		const sectorSize = int64(512)
		const gptBackupSectors = int64(34)
		tailBytes := gptBackupSectors * sectorSize
		if tailBytes < diskBytes {
			extents = append(extents, DiskExtent{
				OffsetBytes: diskBytes - tailBytes,
				LengthBytes: tailBytes,
			})
		}
	}
	return extents
}

func normalizeDiskExtents(extents []DiskExtent, diskBytes int64) []DiskExtent {
	cleaned := make([]DiskExtent, 0, len(extents))
	for _, e := range extents {
		offset := e.OffsetBytes
		length := e.LengthBytes
		if length <= 0 {
			continue
		}
		if offset < 0 {
			length += offset
			offset = 0
		}
		if diskBytes > 0 {
			if offset >= diskBytes {
				continue
			}
			if offset+length > diskBytes {
				length = diskBytes - offset
			}
		}
		if length <= 0 {
			continue
		}
		cleaned = append(cleaned, DiskExtent{
			OffsetBytes: offset,
			LengthBytes: length,
		})
	}
	if len(cleaned) == 0 {
		return cleaned
	}
	sort.Slice(cleaned, func(i, j int) bool {
		return cleaned[i].OffsetBytes < cleaned[j].OffsetBytes
	})
	merged := make([]DiskExtent, 0, len(cleaned))
	for _, e := range cleaned {
		if len(merged) == 0 {
			merged = append(merged, e)
			continue
		}
		last := &merged[len(merged)-1]
		lastEnd := last.OffsetBytes + last.LengthBytes
		if e.OffsetBytes <= lastEnd {
			end := e.OffsetBytes + e.LengthBytes
			if end > lastEnd {
				last.LengthBytes = end - last.OffsetBytes
			}
			continue
		}
		merged = append(merged, e)
	}
	return merged
}

func alignMiB(value int64) int64 {
	if value <= 0 {
		return value
	}
	const mib = int64(1024 * 1024)
	return int64(math.Ceil(float64(value)/float64(mib))) * mib
}

func getDeviceSize(path string) (int64, error) {
	if runtime.GOOS == "windows" {
		return getDeviceSizeWindows(path)
	}
	return getDeviceSizeLinux(path), nil
}
