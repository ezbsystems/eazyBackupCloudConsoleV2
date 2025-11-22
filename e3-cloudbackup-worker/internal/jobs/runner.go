package jobs

import (
	"bufio"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"syscall"
	"time"

	"github.com/your-org/e3-cloudbackup-worker/internal/config"
	"github.com/your-org/e3-cloudbackup-worker/internal/crypto"
	"github.com/your-org/e3-cloudbackup-worker/internal/db"
	"github.com/your-org/e3-cloudbackup-worker/internal/logs"
	"github.com/your-org/e3-cloudbackup-worker/internal/rclone"
)

type Runner struct {
	db     *db.Database
	cfg    *config.Config
	rclone *rclone.Builder
}

func NewRunner(database *db.Database, cfg *config.Config) *Runner {
	return &Runner{
		db:     database,
		cfg:    cfg,
		rclone: rclone.NewBuilder(cfg),
	}
}

func (r *Runner) Run(ctx context.Context, run db.Run) error {
	job, err := r.db.GetJobConfig(ctx, run.JobID)
	if err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, fmt.Sprintf(`{"code":"%s","message":"failed to load job config","details":"%v"}`, ErrDecryptSourceConfig, err))
		return fmt.Errorf("get job config: %w", err)
	}

	// Verify rclone binary exists
	if _, err := os.Stat(r.cfg.Rclone.BinaryPath); os.IsNotExist(err) {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, fmt.Sprintf(`{"code":"%s","message":"rclone binary not found","details":{"path":"%s"}}`, ErrRcloneStart, r.cfg.Rclone.BinaryPath))
		return fmt.Errorf("rclone binary not found: %s", r.cfg.Rclone.BinaryPath)
	}

	// Prepare working directory
	runDir := filepath.Join(r.cfg.Rclone.RunDir, fmt.Sprintf("%d", run.ID))
	if err := os.MkdirAll(runDir, 0o755); err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, fmt.Sprintf(`{"code":"%s","message":"failed to create run directory","details":"%v"}`, ErrWriteRcloneConfig, err))
		return fmt.Errorf("create run dir: %w", err)
	}
	confPath := filepath.Join(runDir, "rclone.conf")
	logPath := filepath.Join(runDir, "rclone.json")

	// Per-run diagnostics
	diag := map[string]any{
		"run_id":         run.ID,
		"job_id":         job.ID,
		"worker_host":    r.cfg.Worker.Hostname,
		"gdrive":         map[string]any{},
		"encryptionKeys": map[string]any{},
		"rclone_conf":    confPath,
	}

	// Decrypt source_config_enc
	decryptedSource, srcKeyLabel, err := r.decryptSourceConfig(job.SourceConfigEnc)
	if err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrDecryptSourceConfig, "decrypt source config failed", map[string]any{
			"hint":      "Ensure CLOUD_BACKUP_ENCRYPTION_KEY is set or addon encryption key is configured",
			"keySource": srcKeyLabel,
		}))
		return fmt.Errorf("decrypt source config: %w", err)
	}
	diag["encryptionKeys"].(map[string]any)["source_config_enc"] = srcKeyLabel

	// Preflight for S3/AWS sources to catch auth/bucket errors early
	switch strings.ToLower(job.SourceType) {
	case "s3_compatible", "aws", "s3":
		var sc s3SourceConfig
		_ = json.Unmarshal([]byte(decryptedSource), &sc)
		// Try to infer bucket from SourcePath if not present in config
		if strings.TrimSpace(sc.Bucket) == "" && strings.TrimSpace(job.SourcePath) != "" {
			p := strings.TrimPrefix(job.SourcePath, "/")
			if p != "" {
				if idx := strings.IndexByte(p, '/'); idx > 0 {
					sc.Bucket = p[:idx]
				} else {
					sc.Bucket = p
				}
			}
		}
		if strings.TrimSpace(sc.AccessKey) != "" && strings.TrimSpace(sc.SecretKey) != "" && strings.TrimSpace(sc.Bucket) != "" {
			class, status, detail, _ := preflightS3ListZero(ctx, sc)
			if class != s3PreflightOK {
				// Insert sanitized error event and fail the run early
				settings, _ := r.db.GetAddonSettings(ctx)
				emitter := newEventEmitter(r.db, run.ID, settings.EventsMaxPerRun, settings.EventsProgressIntervalS)
				now := time.Now().UTC()
				switch class {
				case s3PreflightAuth:
					_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrS3Auth, "S3 authentication preflight failed", map[string]any{
						"bucket": sc.Bucket,
						"status": status,
						"detail": detail,
					}))
					emitter.EmitError(ctx, "ERROR_AUTH", detail)
					emitter.EmitSummary(ctx, "failed", 0, 0, 0)
					return fmt.Errorf("s3 preflight auth failed: %s", detail)
				case s3PreflightNotFound:
					_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrS3BucketMissing, "S3 bucket not found", map[string]any{
						"bucket": sc.Bucket,
						"status": status,
					}))
					emitter.EmitError(ctx, "ERROR_NOT_FOUND", "bucket_not_found")
					emitter.EmitSummary(ctx, "failed", 0, 0, 0)
					return fmt.Errorf("s3 preflight bucket not found")
				case s3PreflightNetwork:
					_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrS3PreflightNet, "Network error during S3 preflight", map[string]any{
						"bucket": sc.Bucket,
						"status": status,
						"detail": detail,
					}))
					emitter.EmitError(ctx, "ERROR_NETWORK", "network_error")
					emitter.EmitSummary(ctx, "failed", 0, 0, 0)
					return fmt.Errorf("s3 preflight network error: %s", detail)
				default:
					_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrS3PreflightNet, "S3 preflight failed", map[string]any{
						"bucket": sc.Bucket,
						"status": status,
						"detail": detail,
					}))
					emitter.EmitError(ctx, "ERROR_INTERNAL", "s3_preflight_failed")
					emitter.EmitSummary(ctx, "failed", 0, 0, 0)
					return fmt.Errorf("s3 preflight failed: %s", detail)
				}
			}
		}
	}

	// If Google Drive, inject app credentials and token from source connection
	if strings.ToLower(job.SourceType) == "google_drive" {
		var srcMap map[string]any
		_ = json.Unmarshal([]byte(decryptedSource), &srcMap)
		if srcMap == nil {
			srcMap = map[string]any{}
		}
		googleClientID := os.Getenv("GOOGLE_CLIENT_ID")
		googleClientSecret := os.Getenv("GOOGLE_CLIENT_SECRET")
		// Fallback to addon settings in DB if env not set
		if googleClientID == "" || googleClientSecret == "" {
			if cfgMap, derr := r.db.GetAddonConfigMap(ctx); derr == nil {
				if googleClientID == "" {
					googleClientID = cfgMap["cloudbackup_google_client_id"]
				}
				if googleClientSecret == "" {
					googleClientSecret = cfgMap["cloudbackup_google_client_secret"]
				}
			}
		}
		if googleClientID == "" || googleClientSecret == "" {
			now := time.Now().UTC()
			_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrGDriveNoClientCreds, "Google Drive client credentials not configured (env or addon settings)", map[string]any{
				"missing": []string{
					boolToMissing("GOOGLE_CLIENT_ID", googleClientID == ""),
					boolToMissing("GOOGLE_CLIENT_SECRET", googleClientSecret == ""),
				},
			}))
			return fmt.Errorf("missing Google client credentials")
		}
		if googleClientID != "" {
			srcMap["client_id"] = googleClientID
		}
		if googleClientSecret != "" {
			srcMap["client_secret"] = googleClientSecret
		}
		var conn *db.SourceConnection
		var gerr error
		if job.SourceConnectionID.Valid {
			conn, gerr = r.db.GetSourceConnection(ctx, job.SourceConnectionID.Int64, job.ClientID)
			if gerr == nil {
				log.Printf("google drive: using source_connection_id=%d for client_id=%d", job.SourceConnectionID.Int64, job.ClientID)
				diag["gdrive"].(map[string]any)["source_connection_id"] = job.SourceConnectionID.Int64
			}
		} else {
			conn, gerr = r.db.GetLatestActiveGoogleDriveSource(ctx, job.ClientID)
			if gerr == nil {
				log.Printf("google drive: using latest active source id=%d for client_id=%d", conn.ID, job.ClientID)
				diag["gdrive"].(map[string]any)["latest_source_id"] = conn.ID
			}
		}
		if gerr != nil {
			now := time.Now().UTC()
			_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrGDriveSourceLookup, "load google source connection failed", map[string]any{
				"details": gerr.Error(),
			}))
			return fmt.Errorf("get source connection: %w", gerr)
		}
		// Decrypt refresh token
		refreshToken, refreshKeyLabel, derr := r.decryptSourceConfig(conn.RefreshTokenEnc)
		if derr != nil || refreshToken == "" {
			now := time.Now().UTC()
			_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrGDriveNoRefresh, "decrypt google refresh token failed", map[string]any{
				"hint":      "Re-authorize Google Drive source; verify encryption key availability on worker",
				"keySource": refreshKeyLabel,
			}))
			return fmt.Errorf("decrypt refresh token: %w", derr)
		}
		diag["gdrive"].(map[string]any)["refresh_token_decrypted"] = true
		diag["encryptionKeys"].(map[string]any)["google_refresh_token"] = refreshKeyLabel
		// Try to pre-refresh to populate access_token and expiry for the token JSON
		accessTok := ""
		expiry := "1970-01-01T00:00:00Z"
		if at, exp, perr := preRefreshGoogleAccessToken(ctx, googleClientID, googleClientSecret, refreshToken); perr == nil && strings.TrimSpace(at) != "" {
			accessTok = at
			expiry = exp
			diag["gdrive"].(map[string]any)["pre_refresh_ok"] = true
			diag["gdrive"].(map[string]any)["pre_refresh_expiry"] = exp
		}
		tokenPayload := map[string]any{
			"access_token":  accessTok,
			"refresh_token": refreshToken,
			"token_type":    "Bearer",
			"expiry":        expiry,
		}
		if b, merr := json.Marshal(tokenPayload); merr == nil {
			srcMap["token"] = string(b)
		}
		if b, merr := json.Marshal(srcMap); merr == nil {
			decryptedSource = string(b)
		}
	}

	// Decrypt destination credentials
	decryptedDestAccessKey, destAKLabel, err := r.decryptSourceConfig(job.DestAccessKeyEnc)
	if err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrDecryptDestAccess, "decrypt destination access key failed", map[string]any{
			"hint":      "Ensure CLOUD_STORAGE_ENCRYPTION_KEY or addon 'encryption_key' is available",
			"keySource": destAKLabel,
		}))
		return fmt.Errorf("decrypt destination access key: %w", err)
	}
	diag["encryptionKeys"].(map[string]any)["dest_access_key_enc"] = destAKLabel

	decryptedDestSecretKey, destSKLabel, err := r.decryptSourceConfig(job.DestSecretKeyEnc)
	if err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrDecryptDestSecret, "decrypt destination secret key failed", map[string]any{
			"hint":      "Ensure CLOUD_STORAGE_ENCRYPTION_KEY or addon 'encryption_key' is available",
			"keySource": destSKLabel,
		}))
		return fmt.Errorf("decrypt destination secret key: %w", err)
	}
	diag["encryptionKeys"].(map[string]any)["dest_secret_key_enc"] = destSKLabel

	// Prepare Google Drive specific values for builder
	driveRefresh := ""
	gcid := ""
	gcsec := ""
	if strings.ToLower(job.SourceType) == "google_drive" {
		gcid = os.Getenv("GOOGLE_CLIENT_ID")
		gcsec = os.Getenv("GOOGLE_CLIENT_SECRET")
		if gcid == "" || gcsec == "" {
			if cfgMap, derr := r.db.GetAddonConfigMap(ctx); derr == nil {
				if gcid == "" {
					gcid = cfgMap["cloudbackup_google_client_id"]
				}
				if gcsec == "" {
					gcsec = cfgMap["cloudbackup_google_client_secret"]
				}
			}
		}
		// If we still don't have client creds here, fail fast
		if gcid == "" || gcsec == "" {
			now := time.Now().UTC()
			_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrGDriveNoClientCreds, "Google Drive client credentials not configured (env or addon settings)", map[string]any{
				"missing": []string{
					boolToMissing("GOOGLE_CLIENT_ID", gcid == ""),
					boolToMissing("GOOGLE_CLIENT_SECRET", gcsec == ""),
				},
			}))
			return fmt.Errorf("missing Google client credentials")
		}
		diag["gdrive"].(map[string]any)["client_id_source"] = sourceLabelForValue(gcid)
		diag["gdrive"].(map[string]any)["client_secret_source"] = sourceLabelForValue(gcsec)
		// try to obtain refresh token (already validated above if connection exists)
		if job.SourceConnectionID.Valid {
			if conn, gerr := r.db.GetSourceConnection(ctx, job.SourceConnectionID.Int64, job.ClientID); gerr == nil {
				if tok, _, derr := r.decryptSourceConfig(conn.RefreshTokenEnc); derr == nil {
					driveRefresh = tok
				}
			}
		} else if conn, gerr := r.db.GetLatestActiveGoogleDriveSource(ctx, job.ClientID); gerr == nil {
			if tok, _, derr := r.decryptSourceConfig(conn.RefreshTokenEnc); derr == nil {
				driveRefresh = tok
			}
		}
		// Fail fast if no refresh token could be obtained
		if strings.TrimSpace(driveRefresh) == "" {
			now := time.Now().UTC()
			_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrGDriveNoRefresh, "No Google Drive refresh token available; re-authorize Drive connection", map[string]any{
				"hint": "Ask client to reconnect Google Drive in the portal; ensure worker can decrypt refresh_token",
			}))
			return fmt.Errorf("missing Google Drive refresh token")
		}
		diag["gdrive"].(map[string]any)["refresh_token_present"] = true

		// Pre-check: try to refresh access token to catch invalid_client/invalid_grant early
		if at, exp, perr := preRefreshGoogleAccessToken(ctx, gcid, gcsec, driveRefresh); perr != nil {
			now := time.Now().UTC()
			_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrGDriveRefreshPrecheck, "Failed to refresh Google access token (precheck)", map[string]any{
				"error": perr.Error(),
			}))
			return fmt.Errorf("pre-refresh access token: %w", perr)
		} else {
			if at != "" {
				diag["gdrive"].(map[string]any)["pre_refresh_ok"] = true
				diag["gdrive"].(map[string]any)["pre_refresh_expiry"] = exp
			}
		}
	}

	// Write rclone config
	if _, _, err := r.rclone.GenerateRcloneConfig(ctx, job, confPath, decryptedSource, decryptedDestAccessKey, decryptedDestSecretKey, driveRefresh, gcid, gcsec); err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrWriteRcloneConfig, "write rclone config failed", map[string]any{
			"path": confPath,
			"err":  err.Error(),
		}))
		return fmt.Errorf("generate rclone config: %w", err)
	}

	// Some deployments run a wrapper binary that expects eazyBackup.conf.
	// Pre-create an alternate config file with identical content to avoid race with the binary creating it empty.
	altConf := filepath.Join(filepath.Dir(confPath), "eazyBackup.conf")
	if altConf != confPath {
		if b, rerr := os.ReadFile(confPath); rerr == nil {
			_ = os.WriteFile(altConf, b, 0o600)
			diag["gdrive"].(map[string]any)["alt_conf_written"] = altConf
		}
	}

	// Last-mile guard: ensure refresh_token is present in rclone.conf for Google Drive
	if strings.ToLower(job.SourceType) == "google_drive" && strings.TrimSpace(driveRefresh) != "" {
		if err := ensureDriveTokenInConfig(confPath, driveRefresh); err != nil {
			log.Printf("warning: failed to enforce refresh_token in rclone.conf: %v", err)
			now := time.Now().UTC()
			_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, r.failJSON(ErrGDriveTokenEnforce, "failed to enforce refresh_token in rclone.conf", map[string]any{
				"path": confPath,
				"err":  err.Error(),
			}))
			return fmt.Errorf("enforce refresh_token in config: %w", err)
		}
		diag["gdrive"].(map[string]any)["token_enforced_in_conf"] = true

		// Also enforce on alternative config filename if present (some deployments use eazyBackup.conf)
		if altConf != confPath {
			if _, statErr := os.Stat(altConf); statErr == nil {
				if err := ensureDriveTokenInConfig(altConf, driveRefresh); err != nil {
					log.Printf("warning: failed to enforce refresh_token in alt config '%s': %v", altConf, err)
				} else {
					diag["gdrive"].(map[string]any)["token_enforced_in_alt_conf"] = altConf
				}
			}
		}
	}

	// Persist per-run diagnostics (best-effort)
	_ = writeDiagnostics(filepath.Join(runDir, "diagnostics.json"), diag)

	// If archive mode, run alternate flow
	if strings.ToLower(job.BackupMode) == "archive" {
		return r.runArchive(ctx, run, job, runDir, confPath)
	}

	// Build command
	sourceRemote := "source"
	destRemote := "dest"

	// Load dynamic settings to override bandwidth if present
	bwOverride := 0
	if settings, serr := r.db.GetAddonSettings(ctx); serr == nil && settings != nil && settings.GlobalMaxBandwidthKbps > 0 {
		bwOverride = settings.GlobalMaxBandwidthKbps
	}
	args := r.rclone.BuildSyncArgsWithBwLimit(job, sourceRemote, destRemote, confPath, logPath, bwOverride)
	cmd := exec.CommandContext(ctx, r.cfg.Rclone.BinaryPath, args...)
	cmd.Dir = runDir
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr

	// Inject remote-specific env overrides so the drive token is honored even if the wrapper rewrites the file.
	if strings.ToLower(job.SourceType) == "google_drive" && strings.TrimSpace(driveRefresh) != "" {
		env := os.Environ()
		// Attempt another quick refresh to populate access_token/expiry (non-fatal if it fails; fall back to empty access_token)
		accessTok := ""
		expiry := "1970-01-01T00:00:00Z"
		if at, exp, perr := preRefreshGoogleAccessToken(ctx, gcid, gcsec, driveRefresh); perr == nil {
			accessTok = at
			expiry = exp
			diag["gdrive"].(map[string]any)["env_pre_refresh_ok"] = true
			diag["gdrive"].(map[string]any)["env_pre_refresh_expiry"] = exp
		}
		envToken := map[string]any{
			"access_token":  accessTok,
			"token_type":    "Bearer",
			"refresh_token": driveRefresh,
			"expiry":        expiry,
		}
		if b, _ := json.Marshal(envToken); len(b) > 0 {
			env = append(env,
				"RCLONE_CONFIG_SOURCE_TOKEN="+string(b),
				"RCLONE_CONFIG_SOURCE_CLIENT_ID="+gcid,
				"RCLONE_CONFIG_SOURCE_CLIENT_SECRET="+gcsec,
			)
			diag["gdrive"].(map[string]any)["env_token_injected"] = true
		}
		cmd.Env = env
	}

	started := time.Now().UTC()
	if err := r.db.UpdateRunStatus(ctx, run.ID, "starting", &started, nil, ""); err != nil {
		log.Printf("warning: failed to mark run starting: %v", err)
	}

	// Initialize event emitter with addon settings
	settings, _ := r.db.GetAddonSettings(ctx)
	emitter := newEventEmitter(r.db, run.ID, settings.EventsMaxPerRun, settings.EventsProgressIntervalS)
	// Try to derive a source bucket/prefix from SourcePath (best-effort)
	srcBucket := ""
	srcPrefix := strings.TrimPrefix(job.SourcePath, "/")
	if srcPrefix != "" {
		chunks := strings.Split(srcPrefix, "/")
		if len(chunks) > 0 {
			srcBucket = chunks[0]
		}
	}
	emitter.EmitStart(ctx, srcBucket, srcPrefix, job.DestBucketName, job.DestPrefix)

	if err := cmd.Start(); err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, "failed to start rclone")
		return fmt.Errorf("start rclone: %w", err)
	}

	// Mark as running after successful start
	if err := r.db.UpdateRunStatus(ctx, run.ID, "running", &started, nil, ""); err != nil {
		log.Printf("warning: failed to mark run running: %v", err)
	}

	// Ensure log directory exists
	if err := os.MkdirAll(filepath.Dir(logPath), 0o755); err != nil {
		log.Printf("warning: failed to create log directory: %v", err)
	}

	// Tail JSON log and update DB periodically
	progressCh, errCh := logs.TailRcloneJSON(ctx, logPath)
	// Also attempt to tail alternative filename used by some wrappers
	altLogPath := filepath.Join(runDir, "eazyBackup.json")
	altProgressCh, altErrCh := logs.TailRcloneJSON(ctx, altLogPath)
	lastUpdate := time.Time{}
	updateEvery := 5 * time.Second
	cancelCheckEvery := 5 * time.Second
	lastCancelCheck := time.Time{}
	doneCh := make(chan error, 1)

	go func() {
		defer close(doneCh)
		doneCh <- cmd.Wait()
	}()

	var lastProgress logs.ProgressUpdate

loop:
	for {
		select {
		case <-ctx.Done():
			_ = r.terminate(cmd)
			break loop
		case perr := <-errCh:
			if perr != nil {
				log.Printf("tail error: %v", perr)
			}
		case aperr := <-altErrCh:
			if aperr != nil {
				// non-fatal; alternative path may not exist
			}
		case pu, ok := <-progressCh:
			if ok {
				lastProgress = pu
			}
			// Throttle DB updates
			if time.Since(lastUpdate) >= updateEvery && (lastProgress.BytesTransferred > 0 || lastProgress.ObjectsTransferred > 0) {
				p := db.Progress{
					ProgressPct:        lastProgress.ProgressPct,
					BytesTotal:         lastProgress.BytesTotal,
					BytesTransferred:   lastProgress.BytesTransferred,
					ObjectsTotal:       lastProgress.ObjectsTotal,
					ObjectsTransferred: lastProgress.ObjectsTransferred,
					SpeedBytesPerSec:   lastProgress.SpeedBytesPerSec,
					EtaSeconds:         lastProgress.EtaSeconds,
					CurrentItem:        lastProgress.CurrentItem,
				}
				_ = r.db.UpdateRunProgress(ctx, run.ID, p)
				// Emit progress event (additional throttling inside)
				emitter.MaybeEmitProgress(ctx, p)
				lastUpdate = time.Now()
			}
		case apu, ok := <-altProgressCh:
			if ok {
				lastProgress = apu
			}
			// Throttle DB updates
			if time.Since(lastUpdate) >= updateEvery && (lastProgress.BytesTransferred > 0 || lastProgress.ObjectsTransferred > 0) {
				p := db.Progress{
					ProgressPct:        lastProgress.ProgressPct,
					BytesTotal:         lastProgress.BytesTotal,
					BytesTransferred:   lastProgress.BytesTransferred,
					ObjectsTotal:       lastProgress.ObjectsTotal,
					ObjectsTransferred: lastProgress.ObjectsTransferred,
					SpeedBytesPerSec:   lastProgress.SpeedBytesPerSec,
					EtaSeconds:         lastProgress.EtaSeconds,
					CurrentItem:        lastProgress.CurrentItem,
				}
				_ = r.db.UpdateRunProgress(ctx, run.ID, p)
				// Emit progress event (additional throttling inside)
				emitter.MaybeEmitProgress(ctx, p)
				lastUpdate = time.Now()
			}
		case err := <-doneCh:
			// rclone exited
			// Write log excerpt
			// Choose whichever log file contains data
			excerpt := readLastNLines(logPath, 200)
			if len(strings.TrimSpace(excerpt)) == 0 {
				excerpt = readLastNLines(altLogPath, 200)
				if len(strings.TrimSpace(excerpt)) > 0 {
					logPath = altLogPath
				}
			}
			now := time.Now().UTC()
			_ = r.db.UpdateRunLogPathAndExcerpt(ctx, run.ID, logPath, excerpt)

			if err != nil {
				// Check if it was a cancellation
				cancelRequested, _ := r.db.CheckCancelRequested(ctx, run.ID)
				if cancelRequested {
					_ = r.db.UpdateRunStatus(ctx, run.ID, "cancelled", nil, &now, "Job cancelled by user")
					emitter.EmitCancelled(ctx)
				} else {
					// Determine if it's a warning (partial transfer) or failure
					// Check if any data was transferred
					if lastProgress.BytesTransferred > 0 && lastProgress.BytesTransferred < lastProgress.BytesTotal {
						_ = r.db.UpdateRunStatus(ctx, run.ID, "warning", nil, &now, fmt.Sprintf("rclone exited with error but partial transfer completed: %v", err))
						emitter.EmitSummary(ctx, "warning", lastProgress.ObjectsTransferred, lastProgress.BytesTransferred, int64(now.Sub(started).Seconds()))
						_ = r.notifyCompletion(ctx, run.ID)
					} else {
						_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, fmt.Sprintf("rclone failed: %v", err))
						// Derive error type from excerpt
						code, detail := mapErrorCode(excerpt)
						emitter.EmitError(ctx, code, detail)
						emitter.EmitSummary(ctx, "failed", lastProgress.ObjectsTransferred, lastProgress.BytesTransferred, int64(now.Sub(started).Seconds()))
						_ = r.notifyCompletion(ctx, run.ID)
					}
				}
			} else {
				// Success - check if it was a partial transfer (warning)
				if lastProgress.BytesTotal > 0 && lastProgress.BytesTransferred < lastProgress.BytesTotal {
					_ = r.db.UpdateRunStatus(ctx, run.ID, "warning", nil, &now, "Transfer completed but not all files were transferred")
					emitter.EmitSummary(ctx, "warning", lastProgress.ObjectsTransferred, lastProgress.BytesTransferred, int64(now.Sub(started).Seconds()))
					_ = r.notifyCompletion(ctx, run.ID)
				} else {
					_ = r.db.UpdateRunStatus(ctx, run.ID, "success", nil, &now, "")
					// Nothing to transfer detection (best-effort)
					if strings.Contains(strings.ToLower(excerpt), "nothing to transfer") {
						emitter.EmitNoChanges(ctx)
					}
					emitter.EmitSummary(ctx, "success", lastProgress.ObjectsTransferred, lastProgress.BytesTransferred, int64(now.Sub(started).Seconds()))
					// Post-run validation if configured
					if job.ValidationMode == "post_run" {
						_ = r.db.UpdateRunValidationStatus(ctx, run.ID, "running")
						valLog := filepath.Join(runDir, "validation.json")
						checkArgs := r.rclone.BuildCheckArgs(job, sourceRemote, destRemote, confPath, valLog)
						checkCmd := exec.CommandContext(ctx, r.cfg.Rclone.BinaryPath, checkArgs...)
						checkCmd.Dir = runDir
						checkCmd.Stdout = os.Stdout
						checkCmd.Stderr = os.Stderr
						_ = checkCmd.Start()
						// Tail validation log briefly until process exits
						vch, verr := logs.TailRcloneJSON(ctx, valLog)
						_ = verr // not critical to propagate
						valDone := make(chan error, 1)
						go func() { valDone <- checkCmd.Wait() }()
						// Periodically update excerpt while running
						for {
							select {
							case <-time.After(1 * time.Second):
								// no-op tick; allow reading until completion
							case _, ok := <-vch:
								if !ok {
									// tail ended
								}
							case e := <-valDone:
								excerpt := readLastNLines(valLog, 200)
								_ = r.db.UpdateRunValidationLogExcerpt(ctx, run.ID, excerpt)
								if e != nil {
									_ = r.db.UpdateRunValidationStatus(ctx, run.ID, "failed")
								} else {
									_ = r.db.UpdateRunValidationStatus(ctx, run.ID, "success")
								}
								goto post_validation_done
							}
						}
					post_validation_done:
					}
					// Trigger immediate notification (best-effort)
					_ = r.notifyCompletion(ctx, run.ID)
				}
			}
			break loop
		default:
			// Poll cancel flag periodically
			if time.Since(lastCancelCheck) >= cancelCheckEvery {
				cancel, cerr := r.db.CheckCancelRequested(ctx, run.ID)
				if cerr == nil && cancel {
					log.Printf("run %d: cancellation requested, terminating rclone", run.ID)
					if err := r.terminate(cmd); err != nil {
						log.Printf("run %d: error terminating rclone: %v", run.ID, err)
					}
					// Wait a bit for graceful shutdown, then kill if needed
					time.Sleep(5 * time.Second)
					if cmd.Process != nil && cmd.ProcessState == nil {
						log.Printf("run %d: force killing rclone", run.ID)
						_ = cmd.Process.Kill()
					}
				}
				lastCancelCheck = time.Now()
			}
			time.Sleep(100 * time.Millisecond)
		}
	}

	return nil
}

// notifyCompletion triggers immediate email notification by calling the WHMCS API endpoint.
func (r *Runner) notifyCompletion(ctx context.Context, runID int64) error {
	// Resolve base URL: prefer addon override if present, else SystemURL
	base := ""
	cfgMap, _ := r.db.GetAddonConfigMap(ctx)
	if v := strings.TrimSpace(cfgMap["cloudbackup_notify_base_url"]); v != "" {
		base = v
	} else {
		sysURL, err := r.db.GetSystemURL(ctx)
		if err != nil || strings.TrimSpace(sysURL) == "" {
			log.Printf("notify: failed to resolve SystemURL: %v", err)
			return fmt.Errorf("resolve SystemURL: %w", err)
		}
		base = sysURL
	}
	base = strings.TrimRight(base, "/")
	notifyURL := base + "/modules/addons/cloudstorage/api/cloudbackup_notify_run.php"

	// Token is sha256(encryption_key + ':' + run_id)
	encKey := config.GetEncryptionKey()
	if strings.TrimSpace(encKey) == "" {
		// fallback to addon settings if not in env/config
		if v := strings.TrimSpace(cfgMap["cloudbackup_encryption_key"]); v != "" {
			encKey = v
		} else if v := strings.TrimSpace(cfgMap["encryption_key"]); v != "" {
			encKey = v
		}
	}
	if strings.TrimSpace(encKey) == "" {
		log.Printf("notify: encryption key unavailable, cannot compute token")
		return fmt.Errorf("encryption key unavailable")
	}
	tokenSrc := fmt.Sprintf("%s:%d", encKey, runID)
	sum := sha256.Sum256([]byte(tokenSrc))
	token := hex.EncodeToString(sum[:])

	form := url.Values{}
	form.Set("run_id", fmt.Sprintf("%d", runID))
	form.Set("token", token)

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, notifyURL, strings.NewReader(form.Encode()))
	if err != nil {
		log.Printf("notify: build request error: %v", err)
		return err
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		log.Printf("notify: http error: %v", err)
		return err
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		b, _ := io.ReadAll(io.LimitReader(resp.Body, 2048))
		log.Printf("notify: http status %d, body=%s", resp.StatusCode, strings.TrimSpace(string(b)))
		return fmt.Errorf("notify http %d: %s", resp.StatusCode, strings.TrimSpace(string(b)))
	}
	log.Printf("notify: run %d notification triggered successfully", runID)
	return nil
}

// ensureDriveTokenInConfig ensures the [source] section contains a token line with the provided refresh token.
func ensureDriveTokenInConfig(configPath string, refreshToken string) error {
	data, err := os.ReadFile(configPath)
	if err != nil {
		return err
	}
	// Build a valid JSON token payload with proper escaping
	tokenPayload := map[string]any{
		"access_token":  "",
		"token_type":    "Bearer",
		"refresh_token": refreshToken,
		"expiry":        "1970-01-01T00:00:00Z",
	}
	tokenJSON, jerr := json.Marshal(tokenPayload)
	if jerr != nil {
		return jerr
	}
	lines := strings.Split(string(data), "\n")
	var out []string
	inSource := false
	tokenReplaced := false
	tokenLine := "token = " + string(tokenJSON)
	for i := 0; i < len(lines); i++ {
		line := lines[i]
		trim := strings.TrimSpace(line)
		if strings.HasPrefix(trim, "[") {
			// entering a new section
			if inSource && !tokenReplaced {
				out = append(out, tokenLine)
			}
			inSource = strings.EqualFold(trim, "[source]")
			out = append(out, line)
			continue
		}
		if inSource && strings.HasPrefix(trim, "token =") {
			out = append(out, tokenLine)
			tokenReplaced = true
			continue
		}
		out = append(out, line)
	}
	// EOF while still in source section
	if inSource && !tokenReplaced {
		out = append(out, tokenLine)
	}
	newContent := strings.Join(out, "\n")
	if string(data) == newContent {
		// Still verify that refresh_token is present; if not, force write anyway
		if !strings.Contains(newContent, `"refresh_token":"`) {
			// Append token line at end of [source]
			return os.WriteFile(configPath, append([]byte(newContent+"\n"), []byte(tokenLine+"\n")...), 0o600)
		}
		return nil
	}
	if err := os.WriteFile(configPath, []byte(newContent), 0o600); err != nil {
		return err
	}
	// Re-read and verify
	check, _ := os.ReadFile(configPath)
	if !strings.Contains(string(check), `"refresh_token":"`) {
		return fmt.Errorf("refresh_token not present after enforcement")
	}
	return nil
}

// runArchive stages source to local, creates a tar.gz, and uploads as single object.
func (r *Runner) runArchive(ctx context.Context, run db.Run, job *db.Job, runDir, confPath string) error {
	// 1) rclone copy source -> local stage
	stageDir := filepath.Join(runDir, "stage")
	if err := os.MkdirAll(stageDir, 0o755); err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, "failed to create stage directory")
		return fmt.Errorf("create stage dir: %w", err)
	}
	localLog := filepath.Join(runDir, "stage_copy.json")
	sourceRemote := "source"
	destRemote := "dest"

	sourcePath := job.SourcePath
	if strings.HasPrefix(sourcePath, "/") {
		sourcePath = strings.TrimPrefix(sourcePath, "/")
	}
	source := fmt.Sprintf("%s:%s", sourceRemote, sourcePath)

	// rclone copy source stage
	copyArgs := []string{
		"copy",
		source,
		stageDir,
		"--config", confPath,
		"--use-json-log",
		"--log-file", localLog,
		"--stats", r.cfg.Rclone.StatsInterval,
		"--stats-one-line=false",
		"--log-level", strings.ToLower(r.cfg.Rclone.LogLevel),
	}
	cmd := exec.CommandContext(ctx, r.cfg.Rclone.BinaryPath, copyArgs...)
	cmd.Dir = runDir
	cmd.Stdout = os.Stdout
	cmd.Stderr = os.Stderr
	started := time.Now().UTC()
	_ = r.db.UpdateRunStatus(ctx, run.ID, "starting", &started, nil, "")
	if err := cmd.Start(); err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, "failed to start rclone copy for archive")
		return fmt.Errorf("start rclone copy: %w", err)
	}
	_ = r.db.UpdateRunStatus(ctx, run.ID, "running", &started, nil, "")

	ch, cerr := logs.TailRcloneJSON(ctx, localLog)
	_ = cerr
	done := make(chan error, 1)
	go func() { done <- cmd.Wait() }()
	var last logs.ProgressUpdate
	lastUpdate := time.Time{}
	for {
		select {
		case p, ok := <-ch:
			if ok {
				last = p
			}
			if time.Since(lastUpdate) >= 5*time.Second && (last.BytesTransferred > 0 || last.ObjectsTransferred > 0) {
				_ = r.db.UpdateRunProgress(ctx, run.ID, db.Progress{
					ProgressPct:        last.ProgressPct,
					BytesTotal:         last.BytesTotal,
					BytesTransferred:   last.BytesTransferred,
					ObjectsTotal:       last.ObjectsTotal,
					ObjectsTransferred: last.ObjectsTransferred,
					SpeedBytesPerSec:   last.SpeedBytesPerSec,
					EtaSeconds:         last.EtaSeconds,
					CurrentItem:        last.CurrentItem,
				})
				lastUpdate = time.Now()
			}
		case e := <-done:
			excerpt := readLastNLines(localLog, 200)
			_ = r.db.UpdateRunLogPathAndExcerpt(ctx, run.ID, localLog, excerpt)
			now := time.Now().UTC()
			if e != nil {
				_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, fmt.Sprintf("archive stage copy failed: %v", e))
				return fmt.Errorf("rclone copy failed: %w", e)
			}
			goto stage_done
		case <-ctx.Done():
			_ = r.terminate(cmd)
			return ctx.Err()
		}
	}
stage_done:
	// 2) Tar/Gzip
	tarPath := filepath.Join(runDir, "archive.tar.gz")
	tarCmd := exec.CommandContext(ctx, "tar", "-czf", tarPath, "-C", stageDir, ".")
	tarCmd.Stdout = os.Stdout
	tarCmd.Stderr = os.Stderr
	if err := tarCmd.Run(); err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, "tar creation failed (ensure 'tar' is available)")
		return fmt.Errorf("tar failed: %w", err)
	}

	// 3) Upload tar as single object
	objectName := fmt.Sprintf("%s/%s", strings.TrimPrefix(job.DestPrefix, "/"), fmt.Sprintf("run_%d.tar.gz", run.ID))
	dest := fmt.Sprintf("%s:%s/%s", destRemote, job.DestBucketName, objectName)
	copytoArgs := []string{
		"copyto",
		tarPath,
		dest,
		"--config", confPath,
		"--log-level", strings.ToLower(r.cfg.Rclone.LogLevel),
	}
	up := exec.CommandContext(ctx, r.cfg.Rclone.BinaryPath, copytoArgs...)
	up.Stdout = os.Stdout
	up.Stderr = os.Stderr
	if err := up.Run(); err != nil {
		now := time.Now().UTC()
		_ = r.db.UpdateRunStatus(ctx, run.ID, "failed", nil, &now, fmt.Sprintf("upload archive failed: %v", err))
		return fmt.Errorf("rclone copyto failed: %w", err)
	}

	// 4) Mark success
	now := time.Now().UTC()
	_ = r.db.UpdateRunStatus(ctx, run.ID, "success", nil, &now, "")

	// Cleanup (best-effort)
	_ = os.RemoveAll(stageDir)
	_ = os.Remove(tarPath)
	return nil
}

func (r *Runner) terminate(cmd *exec.Cmd) error {
	// Send SIGTERM on unix, kill on windows
	if cmd.Process == nil {
		return fmt.Errorf("process not started")
	}
	if runtime.GOOS == "windows" {
		return cmd.Process.Kill()
	}
	// Try graceful shutdown first
	if err := cmd.Process.Signal(syscall.SIGTERM); err != nil {
		return fmt.Errorf("send SIGTERM: %w", err)
	}
	return nil
}

// decryptSourceConfig decrypts the JSON payload stored in source_config_enc (or other encrypted blobs).
// Returns decrypted plaintext and the label describing which key source was used.
// Uses AES-256-CBC matching PHP HelperController::decryptKey() implementation.
func (r *Runner) decryptSourceConfig(enc string) (string, string, error) {
	// Handle plaintext JSON for testing (if data starts with { and ends with })
	trim := strings.TrimSpace(enc)
	if strings.HasPrefix(trim, "{") && strings.HasSuffix(trim, "}") && !strings.Contains(trim, "=") {
		// Likely plaintext JSON, return as-is
		return trim, "plaintext", nil
	}

	// Try multiple keys: cloud backup key, then storage key, then generic fallback
	type keyCandidate struct {
		label string
		key   string
	}
	keyCandidates := []keyCandidate{
		{label: "CLOUD_BACKUP_ENCRYPTION_KEY", key: config.GetEncryptionKey()},
		{label: "CLOUD_STORAGE_ENCRYPTION_KEY", key: os.Getenv("CLOUD_STORAGE_ENCRYPTION_KEY")},
		{label: "ENCRYPTION_KEY", key: os.Getenv("ENCRYPTION_KEY")},
	}
	// Fallback to addon settings in DB if env keys are not available
	if keyCandidates[0].key == "" && keyCandidates[1].key == "" && keyCandidates[2].key == "" {
		if cfgMap, derr := r.db.GetAddonConfigMap(context.Background()); derr == nil {
			if v := strings.TrimSpace(cfgMap["cloudbackup_encryption_key"]); v != "" {
				keyCandidates = append(keyCandidates, keyCandidate{label: "addon.cloudbackup_encryption_key", key: v})
			}
			if v := strings.TrimSpace(cfgMap["encryption_key"]); v != "" {
				keyCandidates = append(keyCandidates, keyCandidate{label: "addon.encryption_key", key: v})
			}
		}
	}
	var lastErr error
	for _, kc := range keyCandidates {
		if kc.key == "" {
			continue
		}
		decrypted, err := crypto.DecryptAES256CBC(enc, kc.key)
		if err == nil {
			return decrypted, kc.label, nil
		}
		lastErr = err
	}
	if lastErr == nil {
		return "", "none", errors.New("no encryption key available in environment")
	}
	return "", "unknown", fmt.Errorf("decrypt failed with provided keys: %w", lastErr)
}

func readLastNLines(path string, n int) string {
	f, err := os.Open(path)
	if err != nil {
		return ""
	}
	defer f.Close()
	// Simple forward scan; acceptable for moderate log sizes
	var lines []string
	sc := bufio.NewScanner(f)
	for sc.Scan() {
		lines = append(lines, sc.Text())
		if len(lines) > n {
			lines = lines[1:]
		}
	}
	if len(lines) == 0 {
		return ""
	}
	b, _ := json.MarshalIndent(lines, "", "  ")
	return string(b)
}

// failJSON formats a compact JSON error_summary with a stable error code and details.
func (r *Runner) failJSON(code ErrorCode, message string, details map[string]any) string {
	payload := map[string]any{
		"code":    string(code),
		"message": message,
	}
	if details != nil {
		payload["details"] = details
	}
	b, _ := json.Marshal(payload)
	return string(b)
}

func writeDiagnostics(path string, diag map[string]any) error {
	b, err := json.MarshalIndent(diag, "", "  ")
	if err != nil {
		return err
	}
	return os.WriteFile(path, b, 0o600)
}

func boolToMissing(name string, missing bool) string {
	if missing {
		return name
	}
	return ""
}

// sourceLabelForValue returns "env" always for secrets we never log the value of,
// but is kept here for future extension if different sources are tracked.
func sourceLabelForValue(_ string) string {
	return "env_or_db"
}

// preRefreshGoogleAccessToken exchanges a refresh_token for an access_token to validate creds early.
func preRefreshGoogleAccessToken(ctx context.Context, clientID, clientSecret, refreshToken string) (string, string, error) {
	form := url.Values{}
	form.Set("client_id", clientID)
	form.Set("client_secret", clientSecret)
	form.Set("refresh_token", refreshToken)
	form.Set("grant_type", "refresh_token")

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, "https://oauth2.googleapis.com/token", strings.NewReader(form.Encode()))
	if err != nil {
		return "", "", err
	}
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return "", "", err
	}
	defer resp.Body.Close()
	body, _ := io.ReadAll(resp.Body)
	if resp.StatusCode != 200 {
		return "", "", fmt.Errorf("oauth error status=%d body=%s", resp.StatusCode, strings.TrimSpace(string(body)))
	}
	var tok struct {
		AccessToken string `json:"access_token"`
		TokenType   string `json:"token_type"`
		ExpiresIn   int    `json:"expires_in"`
	}
	if err := json.Unmarshal(body, &tok); err != nil {
		return "", "", fmt.Errorf("parse oauth token json: %w", err)
	}
	if strings.TrimSpace(tok.AccessToken) == "" {
		return "", "", fmt.Errorf("empty access_token from oauth server")
	}
	exp := time.Now().UTC().Add(time.Duration(tok.ExpiresIn) * time.Second).Format(time.RFC3339)
	return tok.AccessToken, exp, nil
}
