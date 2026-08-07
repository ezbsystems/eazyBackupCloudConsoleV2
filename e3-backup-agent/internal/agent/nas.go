package agent

import (
	"context"
	"fmt"
	"log"
	"os/exec"
	"runtime"
	"strings"
	"sync"
	"time"
)

// NASMount represents an active NAS mount
type NASMount struct {
	MountID     int64
	DriveLetter string
	BucketName  string
	Prefix      string
	ReadOnly    bool
	CacheMode   string
	Persistent  bool
	Status      string
	MountedAt   time.Time
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
	Persistent  bool   `json:"persistent"`
	Status      string `json:"status"`
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

func normalizeNASDriveLetter(raw string) (string, error) {
	driveLetter := strings.ToUpper(strings.TrimSuffix(strings.TrimSpace(raw), ":"))
	if len(driveLetter) != 1 || driveLetter[0] < 'A' || driveLetter[0] > 'Z' {
		return "", fmt.Errorf("invalid drive letter: %s", raw)
	}
	return driveLetter, nil
}

func activeNASMountIDs() []int64 {
	nasManager.mu.RLock()
	defer nasManager.mu.RUnlock()

	ids := make([]int64, 0, len(nasManager.mounts))
	for _, mount := range nasManager.mounts {
		if mount != nil && mount.MountID > 0 {
			ids = append(ids, mount.MountID)
		}
	}
	return ids
}

func (r *Runner) cloudNASPrepareLoop(stop <-chan struct{}) {
	if runtime.GOOS != "windows" {
		return
	}

	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Minute)
	if err := r.startPendingNASMounts(ctx); err != nil {
		log.Printf("agent: Cloud NAS prepare sync failed: %v", err)
	}
	cancel()

	t := time.NewTicker(30 * time.Second)
	defer t.Stop()
	for {
		select {
		case <-stop:
			return
		case <-t.C:
			ctx, cancel := context.WithTimeout(context.Background(), 2*time.Minute)
			if err := r.startPendingNASMounts(ctx); err != nil {
				log.Printf("agent: Cloud NAS prepare sync failed: %v", err)
			}
			cancel()
		}
	}
}

func (r *Runner) startPendingNASMounts(ctx context.Context) error {
	if runtime.GOOS != "windows" {
		return nil
	}

	mounts, err := r.client.PollPreparedNASMounts(activeNASMountIDs())
	if err != nil {
		return err
	}

	desired := make(map[string]bool, len(mounts))
	for _, mount := range mounts {
		payload := MountNASPayload{
			MountID:     mount.MountID,
			Bucket:      firstNonEmpty(mount.Bucket, mount.BucketName),
			Prefix:      mount.Prefix,
			DriveLetter: mount.DriveLetter,
			ReadOnly:    mount.ReadOnly,
			CacheMode:   mount.CacheMode,
			Persistent:  mount.Persistent,
			Status:      mount.Status,
			Endpoint:    mount.Endpoint,
			AccessKey:   mount.AccessKey,
			SecretKey:   mount.SecretKey,
			Region:      mount.Region,
		}

		driveLetter, err := normalizeNASDriveLetter(payload.DriveLetter)
		if err != nil {
			if payload.MountID > 0 {
				_ = r.client.UpdateNASMountStatus(payload.MountID, "error", err.Error())
			}
			continue
		}
		desired[driveLetter] = true

		prepared, err := r.ensurePreparedNASMount(ctx, payload)
		if err != nil {
			log.Printf("agent: failed to mount Cloud NAS sidecar drive %s: %v", driveLetter, err)
			if payload.MountID > 0 {
				_ = r.client.UpdateNASMountStatus(payload.MountID, "error", formatCloudNASError(err))
			}
			continue
		}

		trayStatus := payload.Status
		statusNorm := strings.ToLower(strings.TrimSpace(payload.Status))
		if statusNorm == "pending" || statusNorm == "mounting" {
			if payload.MountID > 0 {
				_ = r.client.UpdateNASMountStatus(payload.MountID, "mounted", "")
			}
			prepared.Status = "mounted"
			trayStatus = "mounted"
		}

		regCtx, regCancel := context.WithTimeout(ctx, 5*time.Second)
		if err := registerPreparedNASDriveViaTray(regCtx, prepared.MountID, prepared.DriveLetter, prepared.BucketName, trayStatus); err != nil {
			log.Printf("agent: Cloud NAS tray register warning for %s: %v", prepared.DriveLetter, err)
		}
		regCancel()
	}

	r.reconcilePreparedNASMounts(desired)
	return nil
}

func (r *Runner) reconcilePreparedNASMounts(desired map[string]bool) {
	gracePeriod := 2 * time.Minute
	cutoff := time.Now().Add(-gracePeriod)

	nasManager.mu.RLock()
	letters := make([]string, 0, len(nasManager.mounts))
	for letter, mount := range nasManager.mounts {
		if desired[letter] {
			continue
		}
		if mount != nil && mount.MountedAt.After(cutoff) {
			log.Printf("agent: Cloud NAS mount %s not in desired set but was created recently (%s ago); keeping", letter, time.Since(mount.MountedAt).Round(time.Second))
			continue
		}
		letters = append(letters, letter)
	}
	nasManager.mu.RUnlock()

	for _, letter := range letters {
		log.Printf("agent: Cloud NAS mount %s no longer desired; stopping sidecar mount", letter)
		if err := r.stopPreparedNASMount(letter, true); err != nil {
			log.Printf("agent: Cloud NAS sidecar unmount warning for %s: %v", letter, err)
		}
	}
}

func (r *Runner) ensurePreparedNASMount(ctx context.Context, payload MountNASPayload) (*NASMount, error) {
	driveLetter, err := normalizeNASDriveLetter(payload.DriveLetter)
	if err != nil {
		return nil, err
	}
	payload.DriveLetter = driveLetter

	nasManager.mu.RLock()
	existing := nasManager.mounts[driveLetter]
	nasManager.mu.RUnlock()

	if existing != nil {
		if existing.MountID == payload.MountID &&
			existing.BucketName == payload.Bucket &&
			existing.Prefix == payload.Prefix &&
			existing.CacheMode == payload.CacheMode &&
			existing.ReadOnly == payload.ReadOnly {
			nasManager.mu.Lock()
			existing.Status = payload.Status
			existing.Persistent = payload.Persistent
			nasManager.mu.Unlock()
			return existing, nil
		}
		if err := r.stopPreparedNASMount(driveLetter, true); err != nil {
			log.Printf("agent: Cloud NAS replacement unmount warning for %s: %v", driveLetter, err)
		}
	}

	if err := r.sidecarHealth(ctx); err != nil {
		return nil, err
	}
	label := fmt.Sprintf("Cloud NAS (%s)", payload.Bucket)
	if err := r.sidecarMount(ctx, payload, label); err != nil {
		return nil, err
	}
	mount := &NASMount{
		MountID:     payload.MountID,
		DriveLetter: driveLetter,
		BucketName:  payload.Bucket,
		Prefix:      payload.Prefix,
		ReadOnly:    payload.ReadOnly,
		CacheMode:   payload.CacheMode,
		Persistent:  payload.Persistent,
		Status:      payload.Status,
		MountedAt:   time.Now(),
	}

	nasManager.mu.Lock()
	nasManager.mounts[driveLetter] = mount
	nasManager.mu.Unlock()
	return mount, nil
}

func (r *Runner) stopPreparedNASMount(driveLetter string, _ bool) error {
	driveLetter = strings.ToUpper(strings.TrimSuffix(strings.TrimSpace(driveLetter), ":"))

	nasManager.mu.Lock()
	mount := nasManager.mounts[driveLetter]
	if mount != nil {
		delete(nasManager.mounts, driveLetter)
	}
	nasManager.mu.Unlock()

	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	unmountErr := r.sidecarUnmount(ctx, driveLetter)
	cancel()

	ctx, cancel = context.WithTimeout(context.Background(), 5*time.Second)
	_ = unregisterPreparedNASDriveViaTray(ctx, driveLetter)
	cancel()

	if mount != nil {
		log.Printf("agent: stopped Cloud NAS sidecar mount %s", driveLetter)
	}
	return unmountErr
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
		if v, ok := cmd.Payload["persistent"].(bool); ok {
			payload.Persistent = v
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
			_ = r.client.UpdateNASMountStatus(payload.MountID, "error", formatCloudNASError(err))
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
			_ = r.client.UpdateNASMountStatus(mountID, "error", formatCloudNASError(err))
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

// mountNASDrive keeps compatibility with the command-driven Cloud NAS flow.
// The sidecar performs and verifies the Windows drive mount directly.
func (r *Runner) mountNASDrive(ctx context.Context, payload MountNASPayload) error {
	if runtime.GOOS != "windows" {
		return fmt.Errorf("NAS mount is only supported on Windows")
	}

	prepared, err := r.ensurePreparedNASMount(ctx, payload)
	if err != nil {
		return err
	}

	prepared.Status = "mounted"
	regCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	if err := registerPreparedNASDriveViaTray(regCtx, prepared.MountID, prepared.DriveLetter, prepared.BucketName, "mounted"); err != nil {
		log.Printf("agent: Cloud NAS tray register warning for %s after mount: %v", prepared.DriveLetter, err)
	}
	cancel()

	log.Printf("agent: Cloud NAS sidecar mount successful - %s: -> %s/%s", prepared.DriveLetter, prepared.BucketName, prepared.Prefix)
	return nil
}

// unmountNASDrive unmounts a NAS drive
func (r *Runner) unmountNASDrive(driveLetter string) error {
	driveLetter = strings.ToUpper(strings.TrimSuffix(driveLetter, ":"))

	if err := r.stopPreparedNASMount(driveLetter, true); err != nil {
		return err
	}

	log.Printf("agent: unmounted %s:", driveLetter)
	return nil
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
	letters := make([]string, 0, len(nasManager.mounts))
	for letter := range nasManager.mounts {
		letters = append(letters, letter)
	}
	nasManager.mu.Unlock()

	r := &Runner{}
	for _, letter := range letters {
		log.Printf("agent: unmounting %s: on shutdown", letter)
		_ = r.stopPreparedNASMount(letter, true)
	}
}
