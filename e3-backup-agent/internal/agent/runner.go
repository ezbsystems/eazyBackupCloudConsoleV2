package agent

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/rclone/rclone/fs"
	"github.com/rclone/rclone/fs/accounting"
	"github.com/rclone/rclone/fs/config"
	rfilter "github.com/rclone/rclone/fs/filter"
	"github.com/rclone/rclone/fs/sync"
)

// Runner executes jobs, spawns rclone, and streams progress/events.
type Runner struct {
	client *Client
	cfg    *AgentConfig
	// configPath is where agent.conf is stored; used to persist enrollment results.
	configPath string
}

func NewRunner(cfg *AgentConfig, configPath string) *Runner {
	return &Runner{
		client:     NewClient(cfg),
		cfg:        cfg,
		configPath: configPath,
	}
}

// NewRunnerWithClient allows custom clients (e.g., recovery session client).
func NewRunnerWithClient(cfg *AgentConfig, client *Client, configPath string) *Runner {
	return &Runner{
		client:     client,
		cfg:        cfg,
		configPath: configPath,
	}
}

// RunDiskRestoreCommand exposes disk restore execution for recovery environments.
func (r *Runner) RunDiskRestoreCommand(ctx context.Context, cmd PendingCommand) {
	r.executeDiskRestoreCommand(ctx, cmd)
}

// Start begins the polling loop (single concurrent run for now).
func (r *Runner) Start(stop <-chan struct{}) {
	// Ensure stable device identity exists (for re-enroll/rekey/reuse).
	r.ensureDeviceIdentity()

	if err := r.waitForEnrollmentIfNeeded(stop); err != nil {
		log.Printf("agent: enrollment wait failed: %v", err)
		return
	}

	if err := r.enrollIfNeeded(); err != nil {
		log.Printf("agent: enrollment failed: %v", err)
		return
	}

	interval := time.Duration(r.cfg.PollIntervalSecs) * time.Second
	if interval <= 0 {
		interval = 5 * time.Second
	}
	t := time.NewTicker(interval)
	defer t.Stop()

	go r.reportVolumesLoop(stop)
	go r.commandLoop(stop)

	for {
		if err := r.pollOnce(); err != nil {
			log.Printf("agent: poll error: %v", err)
		}
		select {
		case <-stop:
			log.Printf("agent: stopping")
			return
		case <-t.C:
		}
	}
}

func (r *Runner) waitForEnrollmentIfNeeded(stop <-chan struct{}) error {
	if r.cfg == nil {
		return fmt.Errorf("config is nil")
	}
	enrolled := strings.TrimSpace(r.cfg.AgentUUID) != "" && strings.TrimSpace(r.cfg.AgentToken) != ""
	hasEnrollCreds := strings.TrimSpace(r.cfg.EnrollmentToken) != "" ||
		(strings.TrimSpace(r.cfg.EnrollEmail) != "" && strings.TrimSpace(r.cfg.EnrollPassword) != "")
	if enrolled || hasEnrollCreds {
		return nil
	}
	if r.configPath == "" {
		return fmt.Errorf("no config path available to wait for enrollment")
	}

	log.Printf("agent: waiting for enrollment credentials in %s", r.configPath)
	t := time.NewTicker(2 * time.Second)
	defer t.Stop()
	for {
		select {
		case <-stop:
			return fmt.Errorf("stopped while waiting for enrollment")
		case <-t.C:
			cfg, err := LoadConfigAllowUnenrolled(r.configPath)
			if err != nil && !errors.Is(err, ErrMissingEnrollment) {
				log.Printf("agent: waiting for enrollment: config error: %v", err)
				continue
			}
			if cfg == nil {
				continue
			}
			r.cfg = cfg
			r.client = NewClient(r.cfg)
			enrolled := strings.TrimSpace(r.cfg.AgentUUID) != "" && strings.TrimSpace(r.cfg.AgentToken) != ""
			hasEnrollCreds := strings.TrimSpace(r.cfg.EnrollmentToken) != "" ||
				(strings.TrimSpace(r.cfg.EnrollEmail) != "" && strings.TrimSpace(r.cfg.EnrollPassword) != "")
			if enrolled || hasEnrollCreds {
				return nil
			}
		}
	}
}

// enrollIfNeeded performs first-run enrollment using token or email/password
// then persists the acquired AgentUUID/AgentToken into the config file.
func (r *Runner) enrollIfNeeded() error {
	if r.cfg.AgentUUID != "" && r.cfg.AgentToken != "" {
		return nil
	}

	hostname, _ := os.Hostname()

	var resp *EnrollResponse
	var err error

	switch {
	case r.cfg.EnrollmentToken != "":
		resp, err = r.client.EnrollWithToken(r.cfg.EnrollmentToken, hostname)
	case r.cfg.EnrollEmail != "" && r.cfg.EnrollPassword != "":
		resp, err = r.client.EnrollWithCredentials(r.cfg.EnrollEmail, r.cfg.EnrollPassword, hostname)
	default:
		return fmt.Errorf("no enrollment credentials configured")
	}

	if err != nil {
		return fmt.Errorf("enrollment request failed: %w", err)
	}

	// Persist credentials and clear enrollment fields
	r.cfg.AgentUUID = string(resp.AgentUUID)
	r.cfg.AgentID = ""
	r.cfg.ClientID = string(resp.ClientID)
	r.cfg.AgentToken = resp.AgentToken
	r.cfg.EnrollmentToken = ""
	r.cfg.EnrollEmail = ""
	r.cfg.EnrollPassword = ""

	if r.configPath != "" {
		if err := r.cfg.Save(r.configPath); err != nil {
			return fmt.Errorf("failed to save config: %w", err)
		}
	}

	// Rebuild client with new credentials
	r.client = NewClient(r.cfg)
	log.Printf("agent: enrollment succeeded (client_id=%s, agent_uuid=%s)", r.cfg.ClientID, r.cfg.AgentUUID)
	return nil
}

// reportVolumesLoop periodically publishes the agent's available volumes for UI pickers.
func (r *Runner) reportVolumesLoop(stop <-chan struct{}) {
	interval := 5 * time.Minute
	t := time.NewTicker(interval)
	defer t.Stop()

	// Initial report
	r.reportVolumesOnce()

	for {
		select {
		case <-stop:
			return
		case <-t.C:
			r.reportVolumesOnce()
		}
	}
}

func (r *Runner) reportVolumesOnce() {
	vols, err := ListVolumes()
	if err != nil {
		log.Printf("agent: list volumes failed: %v", err)
		return
	}
	if err := r.client.ReportVolumes(vols); err != nil {
		log.Printf("agent: report volumes failed: %v", err)
	}
}

func (r *Runner) pollOnce() error {
	// Check for new backup runs
	run, err := r.client.NextRun()
	if err != nil {
		return err
	}
	if run == nil {
		log.Printf("agent: no queued runs")
		return nil
	}
	log.Printf("agent: starting run %s for job %s", run.RunID, run.JobID)
	return r.runRun(run)
}

// commandLoop polls pending commands and repo operations frequently to reduce UI latency.
func (r *Runner) commandLoop(stop <-chan struct{}) {
	t := time.NewTicker(1 * time.Second)
	defer t.Stop()
	for {
		if err := r.pollAndHandlePendingCommands(); err != nil {
			log.Printf("agent: pending commands error: %v", err)
		}
		if err := r.pollAndHandleRepoOperations(); err != nil {
			log.Printf("agent: repo operations error: %v", err)
		}
		select {
		case <-stop:
			return
		case <-t.C:
		}
	}
}

// pollAndHandleRepoOperations polls for queued repo operations (retention, maintenance) and dispatches them.
func (r *Runner) pollAndHandleRepoOperations() error {
	op, err := r.client.PollRepoOperations()
	if err != nil {
		return err
	}
	if op == nil {
		return nil
	}
	log.Printf("agent: executing repo operation id=%d type=%s repo_id=%d", op.OperationID, op.OpType, op.RepoID)
	r.executeRepoOperation(op)
	return nil
}

// isTransientRepoOpError returns true for lock/contention errors that may succeed on retry.
func isTransientRepoOpError(err error) bool {
	if err == nil {
		return false
	}
	s := strings.ToLower(err.Error())
	return strings.Contains(s, "lock") ||
		strings.Contains(s, "contention") ||
		strings.Contains(s, "conflict") ||
		strings.Contains(s, "retry") ||
		strings.Contains(s, "temporarily")
}

// retryRepoOp runs fn up to maxAttempts times, with backoff on transient errors.
// Returns the last error if all attempts fail.
func retryRepoOp(maxAttempts int, fn func() error) error {
	var lastErr error
	for attempt := 0; attempt < maxAttempts; attempt++ {
		lastErr = fn()
		if lastErr == nil {
			return nil
		}
		if !isTransientRepoOpError(lastErr) || attempt == maxAttempts-1 {
			return lastErr
		}
		backoff := time.Duration(attempt+1) * time.Second
		log.Printf("agent: transient repo op error (attempt %d/%d): %v; retrying in %v", attempt+1, maxAttempts, lastErr, backoff)
		time.Sleep(backoff)
	}
	return lastErr
}

// executeRepoOperation runs a repo operation (retention apply, maintenance quick/full).
// Operations require repo credentials; when not present in the payload, completes with a clear failure.
func (r *Runner) executeRepoOperation(op *RepoOperation) {
	ctx := context.Background()
	if !op.isRepoRetentionType() {
		log.Printf("agent: repo operation %d unsupported type %q", op.OperationID, op.OpType)
		_ = r.client.CompleteRepoOperation(op.OperationID, op.OperationToken, "failed",
			map[string]any{"error": "unsupported operation type"})
		return
	}
	t := strings.TrimSpace(strings.ToLower(op.OpType))
	mode := "quick"
	if strings.Contains(t, "full") || t == "maintenance_full" {
		mode = "full"
	}
	run := r.repoOperationToRun(op)
	if run == nil {
		_ = r.client.CompleteRepoOperation(op.OperationID, op.OperationToken, "failed",
			map[string]any{"error": "repo operation requires credential support in server payload"})
		return
	}
	var err error
	var result map[string]any
	if strings.Contains(t, "retention") || t == "retention_apply" {
		var res RetentionApplyResult
		applyErr := retryRepoOp(3, func() error {
			var innerErr error
			res, innerErr = r.kopiaRetentionApply(ctx, run, op.EffectivePolicy)
			return innerErr
		})
		result = map[string]any{
			"deleted_count": res.DeletedCount,
			"sources_count": res.SourcesCount,
		}
		if applyErr != nil {
			err = applyErr
			result["error"] = sanitizeErrorMessage(applyErr)
		}
	} else {
		err = retryRepoOp(3, func() error { return r.kopiaMaintenance(ctx, run, mode) })
		result = map[string]any{}
		if err != nil {
			result["error"] = sanitizeErrorMessage(err)
		}
	}
	status := "success"
	if err != nil {
		status = "failed"
		log.Printf("agent: repo operation %d type=%s failed: %v", op.OperationID, op.OpType, err)
	} else {
		log.Printf("agent: repo operation %d type=%s completed", op.OperationID, op.OpType)
	}
	_ = r.client.CompleteRepoOperation(op.OperationID, op.OperationToken, status, result)
}

// repoOperationToRun builds a minimal NextRunResponse from a RepoOperation for kopia calls.
// Returns nil when credentials are not available (server must add DestAccessKey, DestSecretKey to the payload).
func (r *Runner) repoOperationToRun(op *RepoOperation) *NextRunResponse {
	if op == nil || op.DestAccessKey == "" || op.DestSecretKey == "" {
		return nil
	}
	return &NextRunResponse{
		JobID:          "",
		DestType:       "s3",
		DestBucketName: op.BucketName,
		DestPrefix:     op.RootPrefix,
		DestEndpoint:   op.Endpoint,
		DestRegion:     op.Region,
		DestAccessKey:  op.DestAccessKey,
		DestSecretKey:  op.DestSecretKey,
		RepoConfigKey:  fmt.Sprintf("repo_%d", op.RepoID),
	}
}

// pollAndHandlePendingCommands checks for and executes pending commands (restore, maintenance).
func (r *Runner) pollAndHandlePendingCommands() error {
	cmds, err := r.client.PollPendingCommands()
	if err != nil {
		return err
	}
	if len(cmds) == 0 {
		return nil
	}

	for _, cmd := range cmds {
		log.Printf("agent: executing pending command %d type=%s job=%s run=%s", cmd.CommandID, cmd.Type, cmd.JobID, cmd.RunID)
		r.executePendingCommand(cmd)
	}
	return nil
}

// executePendingCommand handles restore, maintenance, and NAS commands with full job context.
func (r *Runner) executePendingCommand(cmd PendingCommand) {
	ctx := context.Background()

	switch strings.ToLower(strings.TrimSpace(cmd.Type)) {
	case "restore":
		r.executeRestoreCommand(ctx, cmd)
	case "maintenance_quick", "maintenance_full":
		r.executeMaintenanceCommand(ctx, cmd)
	case "nas_mount":
		r.executeNASMountCommand(ctx, cmd)
	case "nas_unmount":
		r.executeNASUnmountCommand(ctx, cmd)
	case "nas_mount_snapshot":
		r.executeNASMountSnapshotCommand(ctx, cmd)
	case "nas_unmount_snapshot":
		r.executeNASUnmountSnapshotCommand(ctx, cmd)
	case "browse_directory":
		r.executeBrowseCommand(cmd)
	case "browse_snapshot":
		r.executeBrowseSnapshotCommand(ctx, cmd)
	case "list_disks":
		r.executeListDisksCommand(cmd)
	case "list_hyperv_vms":
		r.executeListHypervVMsCommand(ctx, cmd)
	case "list_hyperv_vm_details":
		r.executeListHypervVMDetailsCommand(ctx, cmd)
	case "hyperv_restore":
		r.executeHyperVRestoreCommand(ctx, cmd)
	case "disk_restore":
		r.executeDiskRestoreCommand(ctx, cmd)
	case "reset_agent":
		log.Printf("agent: reset_agent command %d received; scheduling Windows service restart", cmd.CommandID)
		if err := triggerAgentServiceRestart(); err != nil {
			log.Printf("agent: reset_agent command %d could not schedule service restart: %v", cmd.CommandID, err)
			_ = r.client.CompleteCommand(cmd.CommandID, "failed", "could not schedule service restart: "+sanitizeErrorMessage(err))
			return
		}
		_ = r.client.CompleteCommand(cmd.CommandID, "completed", "service restart scheduled")
		// Exit non-zero so SCM recovery can also restart if configured.
		os.Exit(2)
	case "refresh_inventory":
		r.executeRefreshInventoryCommand(cmd)
	case "fetch_log_tail":
		r.executeFetchLogTailCommand(cmd)
	default:
		log.Printf("agent: unknown pending command type: %s", cmd.Type)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "unknown command type")
	}
}

// executeRestoreCommand handles a restore command with progress tracking.
func (r *Runner) executeRestoreCommand(ctx context.Context, cmd PendingCommand) {
	if cmd.JobContext == nil {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing job context")
		return
	}

	// Extract restore parameters from payload
	manifestID := ""
	targetPath := ""
	mount := false
	var restoreRunID string
	var selectedPaths []string
	if cmd.Payload != nil {
		if v, ok := cmd.Payload["manifest_id"].(string); ok {
			manifestID = v
		}
		if v, ok := cmd.Payload["target_path"].(string); ok {
			targetPath = v
		}
		if v, ok := cmd.Payload["mount"].(bool); ok {
			mount = v
		}
		if v, ok := cmd.Payload["selected_paths"]; ok {
			switch t := v.(type) {
			case []any:
				for _, item := range t {
					if s, ok := item.(string); ok && strings.TrimSpace(s) != "" {
						selectedPaths = append(selectedPaths, strings.TrimSpace(s))
					}
				}
			case []string:
				for _, s := range t {
					if strings.TrimSpace(s) != "" {
						selectedPaths = append(selectedPaths, strings.TrimSpace(s))
					}
				}
			case string:
				trimmed := strings.TrimSpace(t)
				if trimmed != "" {
					var decoded []string
					if err := json.Unmarshal([]byte(trimmed), &decoded); err == nil {
						for _, s := range decoded {
							if strings.TrimSpace(s) != "" {
								selectedPaths = append(selectedPaths, strings.TrimSpace(s))
							}
						}
					}
				}
			}
		}
		// Get restore_run_id for progress tracking (UUID string from new API)
		if v, ok := cmd.Payload["restore_run_id"].(string); ok && strings.TrimSpace(v) != "" {
			restoreRunID = strings.TrimSpace(v)
		}
	}

	// Fallback to manifest from job context if not in payload
	if manifestID == "" {
		manifestID = cmd.JobContext.ManifestID
	}

	if manifestID == "" || targetPath == "" {
		log.Printf("agent: restore command %d missing manifest_id or target_path", cmd.CommandID)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing manifest_id or target_path")
		return
	}

	// Use restore_run_id for progress tracking if available, otherwise fall back to backup run_id
	trackingRunID := cmd.RunID
	if restoreRunID != "" {
		trackingRunID = restoreRunID
	}

	log.Printf("agent: starting restore command=%d manifest=%s target=%s mount=%v tracking_run=%s", cmd.CommandID, manifestID, targetPath, mount, trackingRunID)

	// Mark restore run as running
	startedAt := time.Now().UTC()
	_ = r.client.UpdateRun(RunUpdate{
		RunID:       trackingRunID,
		Status:      "running",
		StartedAt:   startedAt.Format(time.RFC3339),
		CurrentItem: "Restoring: " + manifestID,
	})

	// Push restore start event
	r.pushEvents(trackingRunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "RESTORE_STARTING",
		ParamsJSON: map[string]any{
			"manifest_id": manifestID,
			"target_path": targetPath,
			"mount":       mount,
		},
	})

	// Build NextRunResponse from JobContext for kopia functions
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
	}

	var err error
	if mount {
		err = r.kopiaMount(ctx, run, manifestID, targetPath)
	} else if len(selectedPaths) > 0 {
		err = r.kopiaRestoreSelectedPaths(ctx, run, manifestID, targetPath, selectedPaths, trackingRunID)
	} else {
		err = r.kopiaRestoreWithProgress(ctx, run, manifestID, targetPath, trackingRunID)
	}

	status := "success"
	errMsg := ""
	msg := fmt.Sprintf("restore completed to %s", targetPath)
	if err != nil {
		status = "failed"
		errMsg = sanitizeErrorMessage(err)
		msg = errMsg
		log.Printf("agent: restore command %d failed: %v", cmd.CommandID, err)
		r.pushEvents(trackingRunID, RunEvent{
			Type:      "error",
			Level:     "error",
			MessageID: "RESTORE_FAILED",
			ParamsJSON: map[string]any{
				"error":       errMsg,
				"manifest_id": manifestID,
				"target_path": targetPath,
			},
		})
	} else {
		log.Printf("agent: restore command %d completed successfully", cmd.CommandID)
		r.pushEvents(trackingRunID, RunEvent{
			Type:      "summary",
			Level:     "info",
			MessageID: "RESTORE_COMPLETED",
			ParamsJSON: map[string]any{
				"manifest_id": manifestID,
				"target_path": targetPath,
			},
		})
	}

	// Update restore run with final status
	finishedAt := time.Now().UTC().Format(time.RFC3339)
	_ = r.client.UpdateRun(RunUpdate{
		RunID:        trackingRunID,
		Status:       status,
		ErrorSummary: errMsg,
		FinishedAt:   finishedAt,
	})

	_ = r.client.CompleteCommand(cmd.CommandID, "completed", msg)
}

// executeMaintenanceCommand handles maintenance commands.
func (r *Runner) executeMaintenanceCommand(ctx context.Context, cmd PendingCommand) {
	if cmd.JobContext == nil {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing job context")
		return
	}

	mode := "quick"
	if strings.Contains(strings.ToLower(cmd.Type), "full") {
		mode = "full"
	}

	log.Printf("agent: starting maintenance command=%d mode=%s job=%s", cmd.CommandID, mode, cmd.JobID)

	// Build NextRunResponse from JobContext
	run := &NextRunResponse{
		RunID:          cmd.JobContext.RunID,
		JobID:          cmd.JobContext.JobID,
		Engine:         cmd.JobContext.Engine,
		SourcePath:     cmd.JobContext.SourcePath,
		DestType:       cmd.JobContext.DestType,
		DestBucketName: cmd.JobContext.DestBucketName,
		DestPrefix:     cmd.JobContext.DestPrefix,
		DestLocalPath:  cmd.JobContext.DestLocalPath,
		DestEndpoint:   cmd.JobContext.DestEndpoint,
		DestRegion:     cmd.JobContext.DestRegion,
		DestAccessKey:  cmd.JobContext.DestAccessKey,
		DestSecretKey:  cmd.JobContext.DestSecretKey,
	}

	r.pushEvents(cmd.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "MAINTENANCE_STARTING",
		ParamsJSON: map[string]any{
			"mode": mode,
		},
	})

	err := r.kopiaMaintenance(ctx, run, mode)
	status := "completed"
	msg := "maintenance " + mode + " completed"
	if err != nil {
		status = "failed"
		msg = err.Error()
		log.Printf("agent: maintenance command %d failed: %v", cmd.CommandID, err)
		r.pushEvents(cmd.RunID, RunEvent{
			Type:      "error",
			Level:     "error",
			MessageID: "MAINTENANCE_FAILED",
			ParamsJSON: map[string]any{
				"error": err.Error(),
				"mode":  mode,
			},
		})
	} else {
		log.Printf("agent: maintenance command %d completed successfully", cmd.CommandID)
		r.pushEvents(cmd.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "MAINTENANCE_COMPLETED",
			ParamsJSON: map[string]any{
				"mode": mode,
			},
		})
	}

	_ = r.client.CompleteCommand(cmd.CommandID, status, msg)
}

// executeBrowseCommand handles filesystem browse requests from the dashboard.
func (r *Runner) executeBrowseCommand(cmd PendingCommand) {
	req := BrowseDirectoryRequest{
		Path:     "",
		MaxItems: 500,
	}

	if cmd.Payload != nil {
		if v, ok := cmd.Payload["path"].(string); ok {
			req.Path = v
		}
		if v, ok := cmd.Payload["max_items"].(float64); ok && v > 0 {
			req.MaxItems = int(v)
		}
	}

	resp := BrowseDirectory(req)
	if err := r.client.ReportBrowseResult(cmd.CommandID, resp); err != nil {
		log.Printf("agent: browse command %d failed to report: %v", cmd.CommandID, err)
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "report browse failed: "+err.Error())
		return
	}
	// agent_report_browse.php marks the command as completed; no further action here.
}

func (r *Runner) runRun(run *NextRunResponse) error {
	if run.RunID == "" {
		return fmt.Errorf("next run returned no run_id")
	}

	// Authenticate to network share if credentials provided
	if run.NetworkCredentials != nil && run.NetworkCredentials.Username != "" {
		sourcePaths := normalizeSourcePaths(run.SourcePaths, run.SourcePath)
		for _, path := range uniqueNetworkShareRoots(sourcePaths) {
			if err := r.authenticateNetworkPath(path, run.NetworkCredentials); err != nil {
				log.Printf("agent: run %s network auth failed: %v", run.RunID, err)
				return fmt.Errorf("network authentication failed: %w", err)
			}
		}
		defer func() {
			for _, path := range uniqueNetworkShareRoots(sourcePaths) {
				r.disconnectNetworkPath(path)
			}
		}()
	}

	engine := strings.ToLower(strings.TrimSpace(run.Engine))
	if engine == "" {
		engine = "sync"
	}
	switch engine {
	case "kopia":
		return r.runKopia(run)
	case "disk_image":
		return r.runDiskImage(run)
	case "hyperv":
		return r.runHyperV(run)
	default:
		return r.runSync(run)
	}
}

func (r *Runner) runSync(run *NextRunResponse) error {
	startedAt := time.Now().UTC()

	runDir := filepath.Join(r.cfg.RunDir, "run_"+run.RunID)
	if err := os.MkdirAll(runDir, 0o755); err != nil {
		return fmt.Errorf("create run dir: %w", err)
	}

	destEndpoint := normalizeEndpoint(firstNonEmpty(run.DestEndpoint, r.cfg.DestEndpoint))
	destRegion := firstNonEmpty(run.DestRegion, r.cfg.DestRegion)
	if destEndpoint == "" {
		return fmt.Errorf("missing dest endpoint")
	}
	if run.DestAccessKey == "" || run.DestSecretKey == "" {
		return fmt.Errorf("missing dest credentials")
	}

	sourcePaths := normalizeSourcePaths(run.SourcePaths, run.SourcePath)
	if len(sourcePaths) == 0 {
		return fmt.Errorf("missing source_paths")
	}
	sourceLabels := buildSourceLabels(sourcePaths)
	multiSource := len(sourcePaths) > 1

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	// Build config directly in memory for embedded rclone
	cfgStore := config.Data()
	cfgStore.DeleteSection("source")
	cfgStore.DeleteSection("dest")
	cfgStore.SetValue("source", "type", "local")
	cfgStore.SetValue("source", "nounc", "true")
	cfgStore.SetValue("dest", "type", "s3")
	cfgStore.SetValue("dest", "provider", "Other")
	cfgStore.SetValue("dest", "access_key_id", run.DestAccessKey)
	cfgStore.SetValue("dest", "secret_access_key", run.DestSecretKey)
	cfgStore.SetValue("dest", "endpoint", destEndpoint)
	cfgStore.SetValue("dest", "force_path_style", "true")
	if destRegion != "" {
		cfgStore.SetValue("dest", "region", destRegion)
		cfgStore.SetValue("dest", "location_constraint", destRegion)
	}
	// Reset global stats
	stats := accounting.GlobalStats()
	stats.ResetCounters()

	// Apply include/exclude filters
	fopt := rfilter.DefaultOpt
	fopt.DeleteExcluded = false
	if run.LocalIncludeGlob != "" {
		fopt.FilterRule = append(fopt.FilterRule, "+ "+run.LocalIncludeGlob)
	}
	if run.LocalExcludeGlob != "" {
		fopt.FilterRule = append(fopt.FilterRule, "- "+run.LocalExcludeGlob)
	}
	fi, err := rfilter.NewFilter(&fopt)
	if err != nil {
		return fmt.Errorf("filter init: %w", err)
	}
	ctx = rfilter.ReplaceConfig(ctx, fi)

	// Mark running
	_ = r.client.UpdateRun(RunUpdate{
		RunID:     run.RunID,
		Status:    "running",
		StartedAt: startedAt.Format(time.RFC3339),
	})
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "BACKUP_STARTING",
	})

	// Progress ticker and cancel polling
	progressTicker := time.NewTicker(5 * time.Second)
	defer progressTicker.Stop()
	commandTicker := time.NewTicker(3 * time.Second)
	defer commandTicker.Stop()

	// Run sync
	runErr := make(chan error, 1)
	lastProgressAt := time.Now()
	var lastBytes int64 = 0

	// Increase parallelism for throughput (default transfers/checkers are lower)
	// Note: fs.GetConfig is global; safe here because we run one job at a time.
	cfg := fs.GetConfig(ctx)
	cfg.Transfers = 16
	cfg.Checkers = 16

	go func() {
		for idx, src := range sourcePaths {
			destPrefix := run.DestPrefix
			if multiSource {
				destPrefix = joinDestPrefix(run.DestPrefix, sourceLabels[idx])
			}
			srcRemote := "source:" + src
			destRemote := fmt.Sprintf("dest:%s/%s", run.DestBucketName, strings.TrimPrefix(destPrefix, "/"))
			log.Printf("agent: run %s config source=%s dest=%s bucket=%s prefix=%s endpoint=%s region=%s", run.RunID, src, "dest", run.DestBucketName, destPrefix, destEndpoint, destRegion)
			srcFs, err := fs.NewFs(ctx, srcRemote)
			if err != nil {
				log.Printf("agent: run %s source fs error: %v", run.RunID, err)
				runErr <- fmt.Errorf("source fs: %w", err)
				return
			}
			destFs, err := fs.NewFs(ctx, destRemote)
			if err != nil {
				log.Printf("agent: run %s dest fs error: %v", run.RunID, err)
				runErr <- fmt.Errorf("dest fs: %w", err)
				return
			}
			if err := sync.Sync(ctx, destFs, srcFs, false); err != nil {
				runErr <- err
				return
			}
		}
		runErr <- nil
	}()

	for {
		select {
		case <-progressTicker.C:
			done := stats.GetBytes()
			now := time.Now()
			elapsed := now.Sub(lastProgressAt).Seconds()
			speed := int64(0)
			if elapsed > 0 {
				speed = int64(float64(done-lastBytes) / elapsed)
			}
			lastProgressAt = now
			lastBytes = done
			// total not known without listing; send bytes only
			pct := percent(done, 0)
			_ = r.client.UpdateRun(RunUpdate{
				RunID:              run.RunID,
				Status:             "running",
				ProgressPct:        pct,
				BytesTransferred:   Int64Ptr(done),
				ObjectsTransferred: stats.GetTransfers(),
				ObjectsTotal:       0,
				SpeedBytesPerSec:   speed,
				EtaSeconds:         0,
			})
			r.pushEvents(run.RunID, RunEvent{
				Type:      "progress",
				Level:     "info",
				MessageID: "PROGRESS_UPDATE",
				ParamsJSON: map[string]any{
					"pct":         pct,
					"bytes_done":  done,
					"bytes_total": int64(0),
					"files_done":  stats.GetTransfers(),
					"files_total": int64(0),
					"speed_bps":   speed,
					"eta_seconds": int64(0),
				},
			})
		case <-commandTicker.C:
			cancelReq, cmds, errCmd := r.pollCommands(run.RunID)
			if errCmd != nil {
				log.Printf("agent: command poll error: %v", errCmd)
			}
			// Handle cancel first
			if cancelReq {
				log.Printf("agent: cancel requested for run %s", run.RunID)
				r.pushEvents(run.RunID, RunEvent{
					Type:      "cancelled",
					Level:     "warn",
					MessageID: "CANCEL_REQUESTED",
				})
				cancel()
			}
			// Handle maintenance/restore commands
			for _, c := range cmds {
				r.handleCommand(ctx, run, c)
			}
		case err := <-runErr:
			status := "success"
			errMsg := ""
			if errors.Is(err, context.Canceled) {
				status = "cancelled"
				errMsg = ""
			} else if err != nil {
				status = "failed"
				errMsg = err.Error()
				log.Printf("agent: run %s finished with error: %v", run.RunID, err)
			} else {
				log.Printf("agent: run %s completed successfully", run.RunID)
			}
			finishedAt := time.Now().UTC().Format(time.RFC3339)
			done := stats.GetBytes()
			_ = r.client.UpdateRun(RunUpdate{
				RunID:              run.RunID,
				Status:             status,
				ErrorSummary:       errMsg,
				FinishedAt:         finishedAt,
				BytesTransferred:   Int64Ptr(done),
				ObjectsTransferred: stats.GetTransfers(),
			})
			// Emit terminal events for live log UI
			switch status {
			case "success":
				r.pushEvents(run.RunID, RunEvent{
					Type:      "summary",
					Level:     "info",
					MessageID: "COMPLETED_SUCCESS",
				}, RunEvent{
					Type:      "summary",
					Level:     "info",
					MessageID: "SUMMARY_TOTAL",
					ParamsJSON: map[string]any{
						"bytes_done": done,
					},
				})
			case "cancelled":
				r.pushEvents(run.RunID, RunEvent{
					Type:      "cancelled",
					Level:     "warn",
					MessageID: "CANCELLED",
				})
			default:
				r.pushEvents(run.RunID, RunEvent{
					Type:      "error",
					Level:     "error",
					MessageID: "COMPLETED_FAILED",
					ParamsJSON: map[string]any{
						"error": errMsg,
					},
				})
			}
			return err
		}
	}
}

// runKopia provides a placeholder path for Kopia-based snapshotting.
// It wires the same lifecycle events/updates as the sync path but defers
// the actual Kopia integration to a dedicated implementation.
func (r *Runner) runKopia(run *NextRunResponse) error {
	startedAt := time.Now().UTC()
	_ = r.client.UpdateRun(RunUpdate{
		RunID:     run.RunID,
		Status:    "running",
		StartedAt: startedAt.Format(time.RFC3339),
	})
	r.pushEvents(run.RunID, RunEvent{
		Type:      "info",
		Level:     "info",
		MessageID: "BACKUP_STARTING",
		ParamsJSON: map[string]any{
			"engine": "eazyBackup",
		},
	})

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	sourcePaths := normalizeSourcePaths(run.SourcePaths, run.SourcePath)
	if len(sourcePaths) == 0 {
		return fmt.Errorf("missing source_paths")
	}
	var entryOverride kopiafs.Entry
	if len(sourcePaths) == 1 {
		run.SourcePath = sourcePaths[0]
	} else {
		var err error
		entryOverride, err = buildMultiSourceEntry(sourcePaths)
		if err != nil {
			return err
		}
		run.SourcePath = "multi-job-" + run.JobID
		r.pushEvents(run.RunID, RunEvent{
			Type:      "info",
			Level:     "info",
			MessageID: "KOPIA_MULTI_SOURCE",
			ParamsJSON: map[string]any{
				"sources": sourcePaths,
			},
		})
	}

	// Run kopia snapshot in background so we can poll for cancel/commands.
	errCh := make(chan error, 1)
	go func() {
		if entryOverride != nil {
			errCh <- r.kopiaSnapshotWithEntry(ctx, run, entryOverride, 0)
		} else {
			errCh <- r.kopiaSnapshot(ctx, run)
		}
	}()

	commandTicker := time.NewTicker(3 * time.Second)
	defer commandTicker.Stop()

	var runErr error
loop:
	for {
		select {
		case runErr = <-errCh:
			break loop
		case <-commandTicker.C:
			cancelReq, cmds, errCmd := r.pollCommands(run.RunID)
			if errCmd != nil {
				log.Printf("agent: command poll error for kopia run %s: %v", run.RunID, errCmd)
			}
			if cancelReq {
				log.Printf("agent: cancel requested for kopia run %s", run.RunID)
				r.pushEvents(run.RunID, RunEvent{
					Type:      "cancelled",
					Level:     "warn",
					MessageID: "CANCEL_REQUESTED",
				})
				cancel()
			}
			for _, c := range cmds {
				r.handleCommand(ctx, run, c)
			}
		}
	}

	status := "success"
	errMsg := ""
	if errors.Is(runErr, context.Canceled) {
		status = "cancelled"
	} else if runErr != nil {
		status = "failed"
		errMsg = sanitizeErrorMessage(runErr)
		log.Printf("agent: run %s (kopia) error: %v", run.RunID, runErr)
	}

	finishedAt := time.Now().UTC().Format(time.RFC3339)
	_ = r.client.UpdateRun(RunUpdate{
		RunID:        run.RunID,
		Status:       status,
		ErrorSummary: errMsg,
		FinishedAt:   finishedAt,
	})

	switch status {
	case "success":
		r.pushEvents(run.RunID, RunEvent{
			Type:      "summary",
			Level:     "info",
			MessageID: "COMPLETED_SUCCESS",
			ParamsJSON: map[string]any{
				"engine": "eazyBackup",
			},
		})
	case "cancelled":
		r.pushEvents(run.RunID, RunEvent{
			Type:      "cancelled",
			Level:     "warn",
			MessageID: "CANCELLED",
		})
	default:
		r.pushEvents(run.RunID, RunEvent{
			Type:      "error",
			Level:     "error",
			MessageID: "COMPLETED_FAILED",
			ParamsJSON: map[string]any{
				"error":  errMsg,
				"engine": "eazyBackup",
			},
		})
	}

	return runErr
}

func uniqueNetworkShareRoots(paths []string) []string {
	seen := map[string]bool{}
	out := make([]string, 0, len(paths))
	for _, p := range paths {
		if p == "" {
			continue
		}
		key := p
		if IsUNCPath(p) {
			if share := ExtractShareRoot(p); share != "" {
				key = share
			}
		}
		if !seen[key] {
			seen[key] = true
			out = append(out, key)
		}
	}
	return out
}

func joinDestPrefix(prefix, suffix string) string {
	if suffix == "" {
		return prefix
	}
	if prefix == "" {
		return suffix
	}
	return strings.TrimSuffix(prefix, "/") + "/" + strings.TrimPrefix(suffix, "/")
}

// pollCommands checks server for cancel requests.
type RunCommand struct {
	Type      string         `json:"type"`
	CommandID int64          `json:"command_id,omitempty"`
	Payload   map[string]any `json:"payload,omitempty"`
}

func (r *Runner) pollCommands(runID string) (bool, []RunCommand, error) {
	endpoint := r.client.baseURL + "/agent_poll_commands.php"
	values := url.Values{}
	values.Set("run_id", runID)

	doPoll := func() (*http.Response, error) {
		req, err := http.NewRequest(http.MethodPost, endpoint, strings.NewReader(values.Encode()))
		if err != nil {
			return nil, err
		}
		r.client.authHeaders(req)
		req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
		return r.client.httpClient.Do(req)
	}

	resp, err := doPoll()
	if err != nil && isTransientNetErr(err) {
		time.Sleep(250 * time.Millisecond)
		resp, err = doPoll()
	}
	if err != nil {
		return false, nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return false, nil, fmt.Errorf("poll commands status %d", resp.StatusCode)
	}

	var out struct {
		Status   string       `json:"status"`
		Commands []RunCommand `json:"commands"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return false, nil, err
	}
	if out.Status != "success" {
		return false, nil, fmt.Errorf("poll commands failed: %s", out.Status)
	}
	cancel := false
	cmds := []RunCommand{}
	for _, c := range out.Commands {
		if strings.EqualFold(c.Type, "cancel") {
			log.Printf("agent: pollCommands received cancel command for run %s", runID)
			cancel = true
			continue
		}
		cmds = append(cmds, c)
	}
	if len(out.Commands) > 0 {
		log.Printf("agent: pollCommands run=%s received %d commands, cancel=%v", runID, len(out.Commands), cancel)
	}
	return cancel, cmds, nil
}

func (r *Runner) cloneDestRemote(startResp *StartRunResponse, endpoint, region string) string {
	var sb strings.Builder
	fmt.Fprintf(&sb, "[dest]\n")
	fmt.Fprintf(&sb, "type = s3\n")
	fmt.Fprintf(&sb, "provider = Other\n")
	fmt.Fprintf(&sb, "env_auth = false\n")
	fmt.Fprintf(&sb, "access_key_id = %s\n", startResp.DestAccessKey)
	fmt.Fprintf(&sb, "secret_access_key = %s\n", startResp.DestSecretKey)
	fmt.Fprintf(&sb, "endpoint = %s\n", endpoint)
	if region != "" {
		fmt.Fprintf(&sb, "region = %s\n", region)
	}
	return sb.String()
}

func writeRcloneConfig(path string, content string) error {
	return os.WriteFile(path, []byte(content), 0o600)
}

func firstNonEmpty(vals ...string) string {
	for _, v := range vals {
		if strings.TrimSpace(v) != "" {
			return v
		}
	}
	return ""
}

func policyBool(policy map[string]any, key string) *bool {
	if policy == nil {
		return nil
	}
	val, ok := policy[key]
	if !ok {
		return nil
	}
	switch t := val.(type) {
	case bool:
		return &t
	case string:
		s := strings.TrimSpace(t)
		if s == "" {
			return nil
		}
		if b, err := strconv.ParseBool(s); err == nil {
			return &b
		}
	case float64:
		if t == 0 {
			b := false
			return &b
		}
		b := true
		return &b
	case int:
		if t == 0 {
			b := false
			return &b
		}
		b := true
		return &b
	}
	return nil
}

// normalizeEndpoint strips any path/query/fragment from an endpoint URL.
// Kopia rejects endpoints with paths. Handles host-only, with/without scheme.
func normalizeEndpoint(raw string) string {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return ""
	}
	original := raw
	u, err := url.Parse(raw)
	if err == nil && u.Scheme != "" && u.Host != "" {
		normalized := fmt.Sprintf("%s://%s", u.Scheme, u.Host)
		if normalized != original {
			log.Printf("agent: normalize endpoint %q -> %q", original, normalized)
		}
		return normalized
	}
	// If no scheme/host, try assuming https:// and re-parse
	u2, err2 := url.Parse("https://" + raw)
	if err2 == nil && u2.Host != "" {
		normalized := fmt.Sprintf("https://%s", u2.Host)
		if normalized != original {
			log.Printf("agent: normalize endpoint %q -> %q", original, normalized)
		}
		return normalized
	}
	// Fallback: take first token before '/' to drop any path
	if parts := strings.SplitN(raw, "/", 2); len(parts) > 0 && parts[0] != "" {
		normalized := parts[0]
		if normalized != original {
			log.Printf("agent: normalize endpoint %q -> %q", original, normalized)
		}
		return normalized
	}
	return raw
}

func percent(done, total int64) float64 {
	if total <= 0 {
		return 0
	}
	return (float64(done) / float64(total)) * 100.0
}

func (r *Runner) pushEvents(runID string, events ...RunEvent) {
	if len(events) == 0 {
		return
	}
	if err := r.client.PushEvents(runID, events); err != nil {
		log.Printf("agent: push events for run %s failed: %v", runID, err)
		if summary, ok := fallbackSummaryFromEvents(events); ok {
			if upErr := r.client.UpdateRun(RunUpdate{
				RunID:        runID,
				ErrorSummary: summary,
				CurrentItem:  "Event delivery degraded; fallback summary recorded.",
			}); upErr != nil {
				log.Printf("agent: fallback summary update for run %s failed: %v", runID, upErr)
			}
		}
	}
}

func fallbackSummaryFromEvents(events []RunEvent) (string, bool) {
	for i := len(events) - 1; i >= 0; i-- {
		ev := events[i]
		level := strings.ToLower(strings.TrimSpace(ev.Level))
		typ := strings.ToLower(strings.TrimSpace(ev.Type))
		if level != "error" && typ != "error" {
			continue
		}
		if ev.ParamsJSON != nil {
			if summary, ok := ev.ParamsJSON["summary"].(string); ok && strings.TrimSpace(summary) != "" {
				hint, _ := ev.ParamsJSON["hint"].(string)
				if strings.TrimSpace(hint) != "" {
					return strings.TrimSpace(summary) + " " + strings.TrimSpace(hint), true
				}
				return strings.TrimSpace(summary), true
			}
			if msg, ok := ev.ParamsJSON["message"].(string); ok && strings.TrimSpace(msg) != "" {
				return strings.TrimSpace(msg), true
			}
			if msg, ok := ev.ParamsJSON["error"].(string); ok && strings.TrimSpace(msg) != "" {
				return strings.TrimSpace(msg), true
			}
		}
		if strings.TrimSpace(ev.MessageID) != "" {
			return "Backup failed and event delivery was degraded. Review connection and retry.", true
		}
	}
	return "", false
}

func (r *Runner) pushRecoveryLogs(runID string, logs ...RunLogEntry) {
	if len(logs) == 0 {
		return
	}
	if err := r.client.PushRecoveryLogs(runID, logs); err != nil {
		log.Printf("agent: push recovery logs for run %s failed: %v", runID, err)
	}
}

// handleCommand executes maintenance/restore commands and reports completion.
func (r *Runner) handleCommand(ctx context.Context, run *NextRunResponse, cmd RunCommand) {
	switch strings.ToLower(strings.TrimSpace(cmd.Type)) {
	case "maintenance_quick", "maintenance_full":
		mode := "quick"
		if strings.Contains(strings.ToLower(cmd.Type), "full") {
			mode = "full"
		}
		err := r.kopiaMaintenance(ctx, run, mode)
		status := "completed"
		msg := "maintenance " + mode
		if err != nil {
			status = "failed"
			msg = err.Error()
		}
		_ = r.client.CompleteCommand(cmd.CommandID, status, msg)
	case "restore":
		manifestID := ""
		targetPath := ""
		mount := false
		if cmd.Payload != nil {
			if v, ok := cmd.Payload["manifest_id"].(string); ok {
				manifestID = v
			}
			if v, ok := cmd.Payload["target_path"].(string); ok {
				targetPath = v
			}
			if v, ok := cmd.Payload["mount"].(bool); ok {
				mount = v
			}
		}
		if manifestID == "" || targetPath == "" {
			_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing manifest_id or target_path")
			return
		}
		var err error
		if mount {
			err = r.kopiaMount(ctx, run, manifestID, targetPath)
		} else {
			err = r.kopiaRestore(ctx, run, manifestID, targetPath)
		}
		status := "completed"
		msg := "restore ok"
		if err != nil {
			status = "failed"
			msg = err.Error()
		}
		_ = r.client.CompleteCommand(cmd.CommandID, status, msg)
	default:
		// Unknown command: mark failed to avoid loops
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "unknown command")
	}
}

// authenticateNetworkPath mounts a network share using provided credentials.
// This is essential when the agent runs as a Windows service (SYSTEM account)
// which doesn't have access to user-mapped drives.
func (r *Runner) authenticateNetworkPath(sourcePath string, creds *NetworkCredentials) error {
	if creds == nil || creds.Username == "" {
		return nil
	}

	// Determine the share root to authenticate to
	sharePath := sourcePath
	if IsUNCPath(sourcePath) {
		sharePath = ExtractShareRoot(sourcePath)
		if sharePath == "" {
			sharePath = sourcePath
		}
	}

	// Skip if not a UNC path (local path doesn't need auth)
	if !IsUNCPath(sharePath) {
		log.Printf("agent: skipping network auth for non-UNC path: %s", sourcePath)
		return nil
	}

	log.Printf("agent: authenticating to network share: %s as %s", sharePath, creds.Username)

	// Build net use command
	// Format: net use \\server\share /user:DOMAIN\username password /persistent:no
	args := []string{"use", sharePath}

	// Add user credentials
	user := creds.Username
	if creds.Domain != "" && !strings.Contains(creds.Username, "\\") && !strings.Contains(creds.Username, "@") {
		user = creds.Domain + "\\" + creds.Username
	}
	args = append(args, "/user:"+user)

	if creds.Password != "" {
		args = append(args, creds.Password)
	}

	args = append(args, "/persistent:no")

	cmd := exec.Command("net", args...)
	output, err := cmd.CombinedOutput()
	if err != nil {
		// Don't log the password
		safeArgs := []string{"use", sharePath, "/user:" + user, "***", "/persistent:no"}
		log.Printf("agent: net use failed: %v (args: %v, output: %s)", err, safeArgs, strings.TrimSpace(string(output)))
		return fmt.Errorf("net use failed: %w", err)
	}

	log.Printf("agent: successfully authenticated to %s", sharePath)
	return nil
}

// disconnectNetworkPath removes a network share connection.
func (r *Runner) disconnectNetworkPath(sourcePath string) {
	sharePath := sourcePath
	if IsUNCPath(sourcePath) {
		sharePath = ExtractShareRoot(sourcePath)
		if sharePath == "" {
			sharePath = sourcePath
		}
	}

	if !IsUNCPath(sharePath) {
		return
	}

	log.Printf("agent: disconnecting from network share: %s", sharePath)

	cmd := exec.Command("net", "use", sharePath, "/delete", "/y")
	output, err := cmd.CombinedOutput()
	if err != nil {
		// Log but don't fail - disconnection errors are non-fatal
		log.Printf("agent: net use /delete warning: %v (output: %s)", err, strings.TrimSpace(string(output)))
	}
}

func (r *Runner) buildConfig(endpoint, region string, startResp *StartRunResponse) string {
	var sb strings.Builder
	// source
	fmt.Fprintf(&sb, "[source]\n")
	fmt.Fprintf(&sb, "type = local\n")
	fmt.Fprintf(&sb, "nounc = true\n\n")
	// dest
	fmt.Fprintf(&sb, "[dest]\n")
	fmt.Fprintf(&sb, "type = s3\n")
	fmt.Fprintf(&sb, "provider = Other\n")
	fmt.Fprintf(&sb, "access_key_id = %s\n", startResp.DestAccessKey)
	fmt.Fprintf(&sb, "secret_access_key = %s\n", startResp.DestSecretKey)
	fmt.Fprintf(&sb, "endpoint = %s\n", endpoint)
	if region != "" {
		fmt.Fprintf(&sb, "region = %s\n", region)
	}
	fmt.Fprintln(&sb)
	return sb.String()
}
