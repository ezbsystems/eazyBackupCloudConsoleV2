package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"log"
	"os"
	"os/signal"
	"path/filepath"
	"strings"
	"syscall"
	"time"

	"github.com/your-org/e3-cloudbackup-worker/internal/config"
	"github.com/your-org/e3-cloudbackup-worker/internal/crypto"
	"github.com/your-org/e3-cloudbackup-worker/internal/db"
	"github.com/your-org/e3-cloudbackup-worker/internal/diag"
	"github.com/your-org/e3-cloudbackup-worker/internal/jobs"
	"github.com/your-org/e3-cloudbackup-worker/internal/rclone"
)

func main() {
	var configPath string
	var printDriveSourceRun int64
	var replayLogPath string
	var replayRunID int64
	flag.StringVar(&configPath, "config", "/opt/e3-cloudbackup-worker/config/config.yaml", "Path to config.yaml")
	flag.Int64Var(&printDriveSourceRun, "print-drive-source-run", 0, "DEV: print computed Drive [source] section for the given run id and exit")
	flag.StringVar(&replayLogPath, "replay-log", "", "DEV: path to an rclone JSON log to replay as events")
	flag.Int64Var(&replayRunID, "replay-run", 0, "DEV: run id to associate with replayed events (requires -replay-log)")
	flag.Parse()

	cfg, err := config.Load(configPath)
	if err != nil {
		log.Fatalf("failed to load config: %v", err)
	}

	dbc, err := db.NewDatabase(cfg)
	if err != nil {
		log.Fatalf("failed to initialize database: %v", err)
	}
	defer dbc.Close()

	// Preflight diagnostics (non-fatal warnings)
	_ = diag.PreflightCheck(context.Background(), dbc)

	// Dev helper: print computed Drive source section for a run and exit
	if printDriveSourceRun > 0 {
		if err := printDriveSourceForRun(cfg, dbc, printDriveSourceRun); err != nil {
			log.Fatalf("print-drive-source-run: %v", err)
		}
		return
	}
	// Dev helper: replay a JSON log file into events for a run id
	if replayLogPath != "" && replayRunID > 0 {
		if err := replayLogAsEvents(cfg, dbc, replayRunID, replayLogPath); err != nil {
			log.Fatalf("replay-log: %v", err)
		}
		return
	}

	// Honor graceful shutdown
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()
	sigCh := make(chan os.Signal, 1)
	signal.Notify(sigCh, syscall.SIGINT, syscall.SIGTERM)
	go func() {
		s := <-sigCh
		log.Printf("received signal %s, shutting down...", s)
		cancel()
	}()

	scheduler := jobs.NewScheduler(dbc, cfg)
	log.Printf("worker starting on host=%s poll_interval=%s max_concurrent=%d",
		cfg.Worker.Hostname, time.Duration(cfg.Worker.PollIntervalSeconds)*time.Second, cfg.Worker.MaxConcurrentJobs)
	if err := scheduler.Run(ctx); err != nil && err != context.Canceled {
		log.Fatalf("scheduler exited with error: %v", err)
	}
	log.Println("worker stopped")
}

// printDriveSourceForRun builds the rclone config for the run and prints the [source] section.
func printDriveSourceForRun(cfg *config.Config, dbc *db.Database, runID int64) error {
	ctx := context.Background()
	r, err := dbc.GetRun(ctx, runID)
	if err != nil {
		return err
	}
	job, err := dbc.GetJobConfig(ctx, r.JobID)
	if err != nil {
		return err
	}
	runDir := os.TempDir()
	confPath := filepath.Join(runDir, fmt.Sprintf("rclone-%d.conf", runID))
	logPath := filepath.Join(runDir, fmt.Sprintf("rclone-%d.json", runID))
	decryptedSource, err := decryptWithFallback(job.SourceConfigEnc)
	if err != nil {
		return fmt.Errorf("decrypt source: %w", err)
	}
	// simulate google injection path
	if strings.ToLower(job.SourceType) == "google_drive" {
		// Load Google creds from env or DB as fallback
		gid := os.Getenv("GOOGLE_CLIENT_ID")
		gsec := os.Getenv("GOOGLE_CLIENT_SECRET")
		if gid == "" || gsec == "" {
			if cfgMap, derr := dbc.GetAddonConfigMap(ctx); derr == nil {
				if gid == "" {
					gid = cfgMap["cloudbackup_google_client_id"]
				}
				if gsec == "" {
					gsec = cfgMap["cloudbackup_google_client_secret"]
				}
			}
		}
		if gid == "" || gsec == "" {
			return fmt.Errorf("missing Google client credentials (env or addon settings)")
		}
		var srcMap map[string]any
		_ = json.Unmarshal([]byte(decryptedSource), &srcMap)
		if srcMap == nil {
			srcMap = map[string]any{}
		}
		srcMap["client_id"] = gid
		srcMap["client_secret"] = gsec
		var conn *db.SourceConnection
		var gerr error
		if job.SourceConnectionID.Valid {
			conn, gerr = dbc.GetSourceConnection(ctx, job.SourceConnectionID.Int64, job.ClientID)
		} else {
			conn, gerr = dbc.GetLatestActiveGoogleDriveSource(ctx, job.ClientID)
		}
		if gerr != nil {
			return gerr
		}
		refreshToken, derr := decryptWithFallback(conn.RefreshTokenEnc)
		if derr != nil || refreshToken == "" {
			return fmt.Errorf("decrypt refresh token failed: %w", derr)
		}
		tokenPayload := map[string]any{
			"access_token":  "",
			"refresh_token": refreshToken,
			"token_type":    "Bearer",
			"expiry":        "1970-01-01T00:00:00Z",
		}
		if b, merr := json.Marshal(tokenPayload); merr == nil {
			srcMap["token"] = string(b)
		}
		if b, merr := json.Marshal(srcMap); merr == nil {
			decryptedSource = string(b)
		}
	}
	// decrypt dest keys
	destAK, err := decryptWithFallback(job.DestAccessKeyEnc)
	if err != nil {
		return fmt.Errorf("decrypt dest access key: %w", err)
	}
	destSK, err := decryptWithFallback(job.DestSecretKeyEnc)
	if err != nil {
		return fmt.Errorf("decrypt dest secret key: %w", err)
	}
	builder := rclone.NewBuilder(cfg)
	// obtain optional drive values for dev helper too
	driveRefresh := ""
	gcid := os.Getenv("GOOGLE_CLIENT_ID")
	gcsec := os.Getenv("GOOGLE_CLIENT_SECRET")
	if gcid == "" || gcsec == "" {
		if cfgMap, derr := dbc.GetAddonConfigMap(ctx); derr == nil {
			if gcid == "" {
				gcid = cfgMap["cloudbackup_google_client_id"]
			}
			if gcsec == "" {
				gcsec = cfgMap["cloudbackup_google_client_secret"]
			}
		}
	}
	if strings.ToLower(job.SourceType) == "google_drive" {
		var conn *db.SourceConnection
		var gerr error
		if job.SourceConnectionID.Valid {
			conn, gerr = dbc.GetSourceConnection(ctx, job.SourceConnectionID.Int64, job.ClientID)
		} else {
			conn, gerr = dbc.GetLatestActiveGoogleDriveSource(ctx, job.ClientID)
		}
		if gerr == nil && conn != nil {
			if tok, derr := decryptWithFallback(conn.RefreshTokenEnc); derr == nil {
				driveRefresh = tok
			}
		}
	}
	if _, _, err := builder.GenerateRcloneConfig(ctx, job, confPath, decryptedSource, destAK, destSK, driveRefresh, gcid, gcsec); err != nil {
		return fmt.Errorf("generate rclone config: %w", err)
	}
	// print [source] section
	data, err := os.ReadFile(confPath)
	if err != nil {
		return err
	}
	lines := strings.Split(string(data), "\n")
	print := false
	for _, ln := range lines {
		if strings.HasPrefix(ln, "[source]") {
			print = true
		} else if strings.HasPrefix(ln, "[") && !strings.HasPrefix(ln, "[source]") {
			print = false
		}
		if print {
			fmt.Println(ln)
		}
	}
	_ = os.Remove(confPath)
	_ = os.Remove(logPath)
	return nil
}

// decryptWithFallback mirrors the worker's decryption strategy used by Runner.
func decryptWithFallback(enc string) (string, error) {
	trim := strings.TrimSpace(enc)
	if strings.HasPrefix(trim, "{") && strings.HasSuffix(trim, "}") && !strings.Contains(trim, "=") {
		return trim, nil
	}
	keyCandidates := []string{
		config.GetEncryptionKey(),
		os.Getenv("CLOUD_STORAGE_ENCRYPTION_KEY"),
		os.Getenv("ENCRYPTION_KEY"),
	}
	var lastErr error
	for _, key := range keyCandidates {
		if key == "" {
			continue
		}
		decrypted, err := crypto.DecryptAES256CBC(enc, key)
		if err == nil {
			return decrypted, nil
		}
		lastErr = err
	}
	if lastErr == nil {
		return "", fmt.Errorf("no encryption key available in environment")
	}
	return "", fmt.Errorf("decrypt failed with provided keys: %w", lastErr)
}

// replayLogAsEvents reads a JSON log file and emits sanitized events for a run.
func replayLogAsEvents(cfg *config.Config, dbc *db.Database, runID int64, path string) error {
	ctx := context.Background()
	// Basic existence check
	if _, err := os.Stat(path); err != nil {
		return fmt.Errorf("replay: log path invalid: %w", err)
	}
	settings, _ := dbc.GetAddonSettings(ctx)
	maxPer := settings.EventsMaxPerRun
	if maxPer <= 0 {
		maxPer = 5000
	}
	intervalS := settings.EventsProgressIntervalS
	if intervalS <= 0 {
		intervalS = 2
	}
	// Emit a starting event with minimal params
	_ = dbc.InsertRunEvent(ctx, runID, time.Now().UTC(), "start", "info", "BACKUP_STARTING", "BACKUP_STARTING", `{}`)
	// Iterate lines
	f, err := os.Open(path)
	if err != nil {
		return err
	}
	defer f.Close()
	dec := json.NewDecoder(f)
	events := 1
	lastEmit := time.Now().Add(-time.Duration(intervalS) * time.Second)
	lastPct := 0.0
	nothingToTransfer := false
	for dec.More() {
		var raw any
		if err := dec.Decode(&raw); err != nil {
			// Try to recover: read a line and continue
			break
		}
		if events >= maxPer {
			continue
		}
		m, ok := raw.(map[string]any)
		if !ok {
			continue
		}
		// detect message lines
		if msg, ok := m["msg"].(string); ok && strings.Contains(strings.ToLower(msg), "nothing to transfer") {
			nothingToTransfer = true
		}
		// progress via stats
		stats, ok := m["stats"].(map[string]any)
		if !ok {
			continue
		}
		bytes := asInt64(stats["bytes"])
		total := asInt64(stats["totalBytes"])
		files := asInt64(stats["files"])
		filesTotal := asInt64(stats["filesTotal"])
		speed := asInt64(stats["speed"])
		eta := asInt64(stats["eta"])
		pct := lastPct
		if v, ok := stats["percent"]; ok {
			switch x := v.(type) {
			case float64:
				pct = x
			case string:
				var pv float64
				_, _ = fmt.Sscanf(x, "%f", &pv)
				pct = pv
			}
		} else if total > 0 {
			pct = (float64(bytes) / float64(total)) * 100.0
		}
		if time.Since(lastEmit) < time.Duration(intervalS)*time.Second {
			continue
		}
		if abs(pct-lastPct) < 1.0 && bytes == 0 {
			continue
		}
		params := map[string]any{
			"files_done":  files,
			"files_total": filesTotal,
			"bytes_done":  bytes,
			"bytes_total": total,
			"pct":         pct,
			"speed_bps":   speed,
			"eta_seconds": eta,
		}
		pb, _ := json.Marshal(params)
		_ = dbc.InsertRunEvent(ctx, runID, time.Now().UTC(), "progress", "info", "PROGRESS_UPDATE", "PROGRESS_UPDATE", string(pb))
		events++
		lastEmit = time.Now()
		lastPct = pct
	}
	// Final summary
	if nothingToTransfer {
		_ = dbc.InsertRunEvent(ctx, runID, time.Now().UTC(), "summary", "info", "NO_CHANGES", "NO_CHANGES", `{}`)
	}
	_ = dbc.InsertRunEvent(ctx, runID, time.Now().UTC(), "summary", "info", "COMPLETED_SUCCESS", "COMPLETED_SUCCESS", `{"status":"success"}`)
	return nil
}

func asInt64(v any) int64 {
	switch x := v.(type) {
	case nil:
		return 0
	case float64:
		return int64(x)
	case float32:
		return int64(x)
	case int64:
		return x
	case int:
		return int64(x)
	case string:
		var i int64
		_, _ = fmt.Sscanf(x, "%d", &i)
		return i
	default:
		return 0
	}
}

func abs(f float64) float64 {
	if f < 0 {
		return -f
	}
	return f
}
