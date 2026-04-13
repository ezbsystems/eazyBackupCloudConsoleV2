package agent

import (
	"context"
	"fmt"
	"io"
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
	Persistent  bool
	Status      string
	MountedAt   time.Time

	// WebDAV server components
	WebDAV     *http.Server
	VFS        *vfs.VFS
	ServerPort int // Port the WebDAV server is listening on (for debugging/status)
	TargetURL  string
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

// mapDriveInUserSession maps the WebDAV share through the tray helper running in
// the logged-in Explorer session. The mount is only considered successful once
// the tray verifies the drive is reachable in that session.
func (r *Runner) mapDriveInUserSession(ctx context.Context, driveLetter, target, bucketName string, webdavPort int) error {
	return mapNASDriveViaTray(ctx, driveLetter, target, bucketName, webdavPort)
}

// setDriveLabelInUserSession is no longer needed — the tray process now sets
// the drive label directly via the registry in the user's HKCU hive (see
// setCloudNASDriveLabel in cmd/tray/cloudnas_control_windows.go). This stub
// is kept so the signature remains available if called from other code paths.
func (r *Runner) setDriveLabelInUserSession(_ context.Context, driveLetter, bucketName string, _ int) {
	log.Printf("agent: drive label for %s: is set by the tray helper (bucket=%s)", driveLetter, bucketName)
}

// notifyShellDriveChange is no longer needed — the tray process now calls
// SHChangeNotify directly via Win32 syscalls in the user's desktop session.
// Shell notifications from Session 0 (SYSTEM) cannot affect the user's Explorer.
func (r *Runner) notifyShellDriveChange(driveLetter string) {
	log.Printf("agent: shell notification for %s: is handled by the tray helper", driveLetter)
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

	configureWebClientForLargeFiles()

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
			log.Printf("agent: failed to prepare Cloud NAS WebDAV for %s: %v", driveLetter, err)
			if payload.MountID > 0 {
				_ = r.client.UpdateNASMountStatus(payload.MountID, "error", err.Error())
			}
			continue
		}

		trayStatus := payload.Status
		if strings.EqualFold(strings.TrimSpace(payload.Status), "pending") {
			if payload.MountID > 0 {
				_ = r.client.UpdateNASMountStatus(payload.MountID, "mounting", "")
			}
			log.Printf("agent: auto-mapping prepared Cloud NAS drive %s: to %s", prepared.DriveLetter, prepared.TargetURL)
			if err := r.mapDriveInUserSession(ctx, prepared.DriveLetter, prepared.TargetURL, prepared.BucketName, prepared.ServerPort); err != nil {
				log.Printf("agent: auto-mapping Cloud NAS drive %s failed: %v", prepared.DriveLetter, err)
				r.stopPreparedNASMount(prepared.DriveLetter, true)
				if payload.MountID > 0 {
					_ = r.client.UpdateNASMountStatus(payload.MountID, "error", err.Error())
				}
				continue
			}
			if payload.MountID > 0 {
				_ = r.client.UpdateNASMountStatus(payload.MountID, "mounted", "")
			}
			prepared.Status = "mounted"
			trayStatus = "mounted"
		}

		regCtx, regCancel := context.WithTimeout(ctx, 5*time.Second)
		if err := registerPreparedNASDriveViaTray(regCtx, prepared.MountID, prepared.DriveLetter, prepared.TargetURL, prepared.BucketName, prepared.ServerPort, trayStatus); err != nil {
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
		log.Printf("agent: Cloud NAS mount %s no longer desired; stopping local WebDAV", letter)
		r.stopPreparedNASMount(letter, true)
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
		r.stopPreparedNASMount(driveLetter, true)
	}

	mount, err := r.startNASWebDAV(ctx, payload)
	if err != nil {
		return nil, err
	}

	nasManager.mu.Lock()
	nasManager.mounts[driveLetter] = mount
	nasManager.mu.Unlock()
	return mount, nil
}

func (r *Runner) stopPreparedNASMount(driveLetter string, removeUserMapping bool) {
	driveLetter = strings.ToUpper(strings.TrimSuffix(strings.TrimSpace(driveLetter), ":"))

	nasManager.mu.Lock()
	mount := nasManager.mounts[driveLetter]
	if mount != nil {
		delete(nasManager.mounts, driveLetter)
	}
	nasManager.mu.Unlock()

	if removeUserMapping {
		r.unmapDriveInUserSession(driveLetter)
	}

	if mount != nil {
		if mount.WebDAV != nil {
			ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
			_ = mount.WebDAV.Shutdown(ctx)
			cancel()
		}
		if mount.VFS != nil {
			mount.VFS.Shutdown()
		}
	}

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	_ = unregisterPreparedNASDriveViaTray(ctx, driveLetter)
	cancel()

	if removeUserMapping {
		r.notifyShellDriveRemoved(driveLetter)
	}
}

func (r *Runner) startNASWebDAV(ctx context.Context, payload MountNASPayload) (*NASMount, error) {
	if payload.Bucket == "" || payload.DriveLetter == "" || payload.AccessKey == "" || payload.SecretKey == "" {
		return nil, fmt.Errorf("missing required mount parameters")
	}

	driveLetter, err := normalizeNASDriveLetter(payload.DriveLetter)
	if err != nil {
		return nil, err
	}

	root := payload.Bucket
	if payload.Prefix != "" {
		root = payload.Bucket + "/" + strings.TrimPrefix(payload.Prefix, "/")
	}

	opt := configmap.Simple{
		"provider":          "Ceph",
		"access_key_id":     payload.AccessKey,
		"secret_access_key": payload.SecretKey,
		"endpoint":          payload.Endpoint,
		"chunk_size":        "5Mi",
		"copy_cutoff":       "5Gi",
		"upload_cutoff":     "200Mi",
		"force_path_style":  "true",
		"disable_http2":     "true",
		"no_check_bucket":   "true",
		"list_chunk":        "1000",
	}
	if payload.Region != "" {
		opt["region"] = payload.Region
	} else {
		opt["region"] = ""
	}

	log.Printf("agent: creating S3 filesystem - endpoint=%s, bucket=%s, prefix=%s, root=%s",
		payload.Endpoint, payload.Bucket, payload.Prefix, root)
	log.Printf("agent: S3 config - provider=Ceph, access_key=%s..., force_path_style=true",
		payload.AccessKey[:8])

	f, err := s3.NewFs(ctx, "cloudnas", root, opt)
	if err != nil {
		return nil, fmt.Errorf("failed to create s3 fs: %w", err)
	}

	log.Printf("agent: S3 filesystem type: %s, root: %s", f.Name(), f.Root())
	entries, listErr := f.List(ctx, "")
	if listErr != nil {
		log.Printf("agent: WARNING - S3 list root failed: %v", listErr)
		log.Printf("agent: Attempting directory check...")
	} else {
		log.Printf("agent: S3 root has %d entries", len(entries))
		for i, entry := range entries {
			if i < 10 {
				log.Printf("agent:   - %s (size=%d, isDir=%v)", entry.Remote(), entry.Size(), entry.Size() == -1)
			}
		}
		if len(entries) > 10 {
			log.Printf("agent:   ... and %d more", len(entries)-10)
		}
	}

	vfsOpt := vfscommon.DefaultOpt
	switch strings.ToLower(payload.CacheMode) {
	case "off":
		vfsOpt.CacheMode = vfscommon.CacheModeOff
	case "minimal":
		vfsOpt.CacheMode = vfscommon.CacheModeMinimal
	case "full":
		vfsOpt.CacheMode = vfscommon.CacheModeFull
	default:
		vfsOpt.CacheMode = vfscommon.CacheModeWrites
	}
	if payload.ReadOnly {
		vfsOpt.ReadOnly = true
	}
	vfsInst := vfs.New(f, &vfsOpt)

	fsWrapper := &vfsWebDAVFS{vfs: vfsInst}
	handler := &webdav.Handler{
		Prefix:     "/",
		FileSystem: fsWrapper,
		LockSystem: &noopLockSystem{},
	}

	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		vfsInst.Shutdown()
		return nil, fmt.Errorf("failed to listen for webdav: %w", err)
	}
	srv := &http.Server{Handler: handler}
	go func() {
		if serveErr := srv.Serve(ln); serveErr != nil && serveErr != http.ErrServerClosed {
			log.Printf("agent: webdav serve error: %v", serveErr)
		}
	}()

	port := ln.Addr().(*net.TCPAddr).Port
	time.Sleep(500 * time.Millisecond)

	startSvc := exec.CommandContext(ctx, "net", "start", "webclient")
	_ = startSvc.Run()
	time.Sleep(300 * time.Millisecond)

	httpTarget := fmt.Sprintf("http://127.0.0.1:%d/", port)
	httpClient := &http.Client{Timeout: 10 * time.Second}
	resp, httpErr := httpClient.Get(httpTarget)
	if httpErr != nil {
		log.Printf("agent: WARNING - WebDAV server not reachable via HTTP: %v", httpErr)
	} else {
		io.Copy(io.Discard, resp.Body)
		resp.Body.Close()
		log.Printf("agent: WebDAV server responding on port %d (status %d)", port, resp.StatusCode)
	}

	return &NASMount{
		MountID:     payload.MountID,
		DriveLetter: driveLetter,
		BucketName:  payload.Bucket,
		Prefix:      payload.Prefix,
		ReadOnly:    payload.ReadOnly,
		CacheMode:   payload.CacheMode,
		Persistent:  payload.Persistent,
		Status:      payload.Status,
		MountedAt:   time.Now(),
		WebDAV:      srv,
		VFS:         vfsInst,
		ServerPort:  port,
		TargetURL:   httpTarget,
	}, nil
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

// mountNASDrive keeps compatibility with the legacy command-driven Cloud NAS
// flow. It first prepares the local WebDAV server, then asks the tray helper
// to attempt the Windows drive mapping immediately.
func (r *Runner) mountNASDrive(ctx context.Context, payload MountNASPayload) error {
	if runtime.GOOS != "windows" {
		return fmt.Errorf("NAS mount is only supported on Windows")
	}

	prepared, err := r.ensurePreparedNASMount(ctx, payload)
	if err != nil {
		return err
	}

	log.Printf("agent: mapping drive %s: to %s", prepared.DriveLetter, prepared.TargetURL)
	if err := r.mapDriveInUserSession(ctx, prepared.DriveLetter, prepared.TargetURL, prepared.BucketName, prepared.ServerPort); err != nil {
		log.Printf("agent: user-session tray mapping failed: %v", err)
		r.stopPreparedNASMount(prepared.DriveLetter, true)
		return fmt.Errorf("interactive Explorer-session mapping failed: %w", err)
	}

	prepared.Status = "mounted"
	regCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	if err := registerPreparedNASDriveViaTray(regCtx, prepared.MountID, prepared.DriveLetter, prepared.TargetURL, prepared.BucketName, prepared.ServerPort, "mounted"); err != nil {
		log.Printf("agent: Cloud NAS tray register warning for %s after mount: %v", prepared.DriveLetter, err)
	}
	cancel()

	log.Printf("agent: drive %s: mapped in user session for Explorer visibility", prepared.DriveLetter)
	log.Printf("agent: *** IMPORTANT: Keep this agent running! The WebDAV server will stop if the agent exits. ***")
	log.Printf("agent: mount successful - %s: -> %s (WebDAV port: %d)", prepared.DriveLetter, prepared.TargetURL, prepared.ServerPort)
	return nil
}

// unmountNASDrive unmounts a NAS drive
func (r *Runner) unmountNASDrive(driveLetter string) error {
	driveLetter = strings.ToUpper(strings.TrimSuffix(driveLetter, ":"))

	r.stopPreparedNASMount(driveLetter, true)

	// Legacy cleanup in the service session in case an older build created a
	// hidden mapping there.
	unmountScript := fmt.Sprintf(`
net use %s: /delete /y 2>$null
Stop-Service WebClient -Force -ErrorAction SilentlyContinue
Start-Sleep -Milliseconds 300
Start-Service WebClient -ErrorAction SilentlyContinue
`, driveLetter)

	unmountCmd := exec.Command("powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", unmountScript)
	_ = unmountCmd.Run()

	log.Printf("agent: unmounted %s:", driveLetter)
	return nil
}

// notifyShellDriveRemoved is handled by the tray process via direct Win32
// SHChangeNotify syscalls. The agent service (Session 0) cannot influence
// the user's Explorer shell, so this is a no-op here.
func (r *Runner) notifyShellDriveRemoved(driveLetter string) {
	log.Printf("agent: shell removal notification for %s: is handled by the tray helper", driveLetter)
}

// unmapDriveInUserSession removes the mapping from the tray-owned Explorer
// session and then performs best-effort legacy cleanup for older builds.
func (r *Runner) unmapDriveInUserSession(driveLetter string) {
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	if err := unmapNASDriveViaTray(ctx, driveLetter); err != nil {
		log.Printf("agent: tray user-session unmount (non-critical): %v", err)
	}
	if runtime.GOOS == "windows" {
		unmapNASDriveInteractiveUserWTS(driveLetter)
	}
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

// noopLockSystem implements webdav.LockSystem as a permissive no-op.  S3 has
// no concept of byte-range or exclusive file locks.  The default in-memory
// lock system (webdav.NewMemLS) causes "0x80070021" lock-conflict errors
// during large file uploads because Windows Explorer's LOCK requests can
// expire before the upload/flush cycle completes.  A no-op lock system
// lets every LOCK succeed immediately and never blocks concurrent access.
type noopLockSystem struct{}

func (*noopLockSystem) Confirm(time.Time, string, string, ...webdav.Condition) (func(), error) {
	return func() {}, nil
}

func (*noopLockSystem) Create(time.Time, webdav.LockDetails) (string, error) {
	return "opaquelocktoken:cloudnas-noop", nil
}

func (*noopLockSystem) Refresh(time.Time, string, time.Duration) (webdav.LockDetails, error) {
	return webdav.LockDetails{}, nil
}

func (*noopLockSystem) Unlock(time.Time, string) error {
	return nil
}
