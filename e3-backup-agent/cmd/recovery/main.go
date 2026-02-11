package main

import (
	"bytes"
	"context"
	"encoding/json"
	"flag"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"sync"

	"github.com/your-org/e3-backup-agent/internal/agent"
)

const defaultRecoveryAPI = "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"

type recoveryState struct {
	mu           sync.Mutex
	apiBaseURL   string
	sessionToken string
	restorePoint exchangeRestorePoint
	storage      exchangeStorage
	jobStatus    string
	jobMessage   string
	jobProgress  int
	restoreRunID int64
	debugEnabled bool
	buildInfo    string
}

type exchangeRestorePoint struct {
	ID                 int64  `json:"id"`
	JobID              int64  `json:"job_id"`
	Engine             string `json:"engine"`
	ManifestID         string `json:"manifest_id"`
	DiskLayoutJSON     string `json:"disk_layout_json"`
	DiskTotalBytes     int64  `json:"disk_total_bytes"`
	DiskUsedBytes      int64  `json:"disk_used_bytes"`
	DiskBootMode       string `json:"disk_boot_mode"`
	DiskPartitionStyle string `json:"disk_partition_style"`
	PolicyJSON         map[string]any `json:"policy_json"`
}

type exchangeStorage struct {
	DestType  string `json:"dest_type"`
	Bucket    string `json:"bucket"`
	Prefix    string `json:"prefix"`
	Endpoint  string `json:"endpoint"`
	Region    string `json:"region"`
	AccessKey string `json:"access_key"`
	SecretKey string `json:"secret_key"`
}

func main() {
	listen := flag.String("listen", "0.0.0.0:8080", "listen address")
	apiBase := flag.String("api", defaultRecoveryAPI, "API base URL")
	flag.Parse()

	state := &recoveryState{
		apiBaseURL:   *apiBase,
		debugEnabled: strings.TrimSpace(os.Getenv("E3_RECOVERY_DEBUG_LOG")) == "1",
		buildInfo:    loadBuildInfo(),
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/", state.handleIndex)
	mux.HandleFunc("/api/exchange", state.handleExchange)
	mux.HandleFunc("/api/disks", state.handleDisks)
	mux.HandleFunc("/api/start", state.handleStartRestore)
	mux.HandleFunc("/api/run-status", state.handleRunStatus)
	mux.HandleFunc("/api/run-events", state.handleRunEvents)
	mux.HandleFunc("/api/cancel", state.handleCancel)
	mux.HandleFunc("/api/status", state.handleStatus)
	mux.HandleFunc("/api/refresh-session", state.handleRefreshSession)
	mux.HandleFunc("/api/debug-status", state.handleDebugStatus)
	mux.HandleFunc("/api/debug-toggle", state.handleDebugToggle)
	mux.HandleFunc("/api/debug-logs", state.handleDebugLogs)

	log.Printf("recovery UI listening on %s", *listen)
	log.Printf("recovery API base %s", *apiBase)
	if err := http.ListenAndServe(*listen, mux); err != nil {
		log.Fatal(err)
	}
}

func (s *recoveryState) handleIndex(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	_, _ = w.Write([]byte(recoveryHTML))
}

func (s *recoveryState) handleExchange(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(405)
		return
	}
	var payload struct {
		Token string `json:"token"`
	}
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	token := strings.TrimSpace(payload.Token)
	if token == "" {
		writeJSON(w, map[string]any{"status": "fail", "message": "token required"})
		return
	}

	endpoint := strings.TrimRight(s.apiBaseURL, "/") + "/cloudbackup_recovery_token_exchange.php"
	body := map[string]any{"token": token}
	buf, _ := json.Marshal(body)
	resp, err := http.Post(endpoint, "application/json", bytes.NewReader(buf))
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	defer resp.Body.Close()

	var out struct {
		Status       string               `json:"status"`
		Message      string               `json:"message"`
		SessionToken string               `json:"session_token"`
		RestorePoint exchangeRestorePoint `json:"restore_point"`
		Storage      exchangeStorage      `json:"storage"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid response"})
		return
	}
	if out.Status != "success" {
		writeJSON(w, map[string]any{"status": "fail", "message": out.Message})
		return
	}

	s.mu.Lock()
	s.sessionToken = out.SessionToken
	s.restorePoint = out.RestorePoint
	s.storage = out.Storage
	s.mu.Unlock()

	writeJSON(w, map[string]any{"status": "success", "restore_point": out.RestorePoint})
}

func (s *recoveryState) handleDisks(w http.ResponseWriter, r *http.Request) {
	disks, err := agent.ListDisks()
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	writeJSON(w, map[string]any{"status": "success", "disks": disks})
}

func (s *recoveryState) handleStartRestore(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(405)
		return
	}
	var payload struct {
		TargetDisk    string `json:"target_disk"`
		ShrinkEnabled bool   `json:"shrink_enabled"`
	}
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}

	s.mu.Lock()
	sessionToken := s.sessionToken
	restorePoint := s.restorePoint
	storage := s.storage
	s.mu.Unlock()

	if sessionToken == "" || restorePoint.ManifestID == "" {
		writeJSON(w, map[string]any{"status": "fail", "message": "recovery token not exchanged"})
		return
	}
	if payload.TargetDisk == "" {
		writeJSON(w, map[string]any{"status": "fail", "message": "target_disk required"})
		return
	}

	targetDiskBytes := lookupDiskSize(payload.TargetDisk)
	startEndpoint := strings.TrimRight(s.apiBaseURL, "/") + "/cloudbackup_recovery_start_restore.php"
	startPayload := map[string]any{
		"session_token":     sessionToken,
		"target_disk":       payload.TargetDisk,
		"target_disk_bytes": targetDiskBytes,
		"options": map[string]any{
			"shrink_enabled": payload.ShrinkEnabled,
		},
	}
	startBuf, _ := json.Marshal(startPayload)
	resp, err := http.Post(startEndpoint, "application/json", bytes.NewReader(startBuf))
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	defer resp.Body.Close()

	var startResp struct {
		Status         string `json:"status"`
		Message        string `json:"message"`
		RestoreRunID   int64  `json:"restore_run_id"`
		RestoreRunUUID string `json:"restore_run_uuid"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&startResp); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid response"})
		return
	}
	if startResp.Status != "success" {
		writeJSON(w, map[string]any{"status": "fail", "message": startResp.Message})
		return
	}

	s.mu.Lock()
	s.restoreRunID = startResp.RestoreRunID
	s.mu.Unlock()

	go s.runRestore(sessionToken, startResp.RestoreRunID, payload.TargetDisk, targetDiskBytes, restorePoint, storage, payload.ShrinkEnabled)
	writeJSON(w, map[string]any{"status": "success"})
}

func (s *recoveryState) handleRunStatus(w http.ResponseWriter, r *http.Request) {
	s.mu.Lock()
	sessionToken := s.sessionToken
	runID := s.restoreRunID
	apiBase := s.apiBaseURL
	s.mu.Unlock()

	if sessionToken == "" || runID == 0 {
		writeJSON(w, map[string]any{"status": "fail", "message": "restore not started"})
		return
	}

	endpoint := strings.TrimRight(apiBase, "/") + "/cloudbackup_recovery_get_run_status.php"
	payload := map[string]any{
		"session_token": sessionToken,
		"run_id":        runID,
	}
	buf, _ := json.Marshal(payload)
	resp, err := http.Post(endpoint, "application/json", bytes.NewReader(buf))
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	defer resp.Body.Close()

	var out map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid response"})
		return
	}
	writeJSON(w, out)
}

func (s *recoveryState) handleRunEvents(w http.ResponseWriter, r *http.Request) {
	s.mu.Lock()
	sessionToken := s.sessionToken
	runID := s.restoreRunID
	apiBase := s.apiBaseURL
	s.mu.Unlock()

	if sessionToken == "" || runID == 0 {
		writeJSON(w, map[string]any{"status": "fail", "message": "restore not started"})
		return
	}

	sinceID := r.URL.Query().Get("since_id")
	endpoint := strings.TrimRight(apiBase, "/") + "/cloudbackup_recovery_get_run_events.php"
	payload := map[string]any{
		"session_token": sessionToken,
		"run_id":        runID,
	}
	if sinceID != "" {
		payload["since_id"] = sinceID
	}
	buf, _ := json.Marshal(payload)
	resp, err := http.Post(endpoint, "application/json", bytes.NewReader(buf))
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	defer resp.Body.Close()

	var out map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid response"})
		return
	}
	writeJSON(w, out)
}

func (s *recoveryState) handleCancel(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(405)
		return
	}
	s.mu.Lock()
	sessionToken := s.sessionToken
	runID := s.restoreRunID
	apiBase := s.apiBaseURL
	s.mu.Unlock()

	if sessionToken == "" || runID == 0 {
		writeJSON(w, map[string]any{"status": "fail", "message": "restore not started"})
		return
	}

	endpoint := strings.TrimRight(apiBase, "/") + "/cloudbackup_recovery_cancel_restore.php"
	payload := map[string]any{
		"session_token": sessionToken,
		"run_id":        runID,
	}
	buf, _ := json.Marshal(payload)
	resp, err := http.Post(endpoint, "application/json", bytes.NewReader(buf))
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	defer resp.Body.Close()

	var out map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid response"})
		return
	}
	writeJSON(w, out)
}

func (s *recoveryState) handleStatus(w http.ResponseWriter, r *http.Request) {
	s.mu.Lock()
	defer s.mu.Unlock()
	writeJSON(w, map[string]any{
		"status": "success",
		"job": map[string]any{
			"status":   s.jobStatus,
			"message":  s.jobMessage,
			"progress": s.jobProgress,
		},
	})
}

func (s *recoveryState) handleRefreshSession(w http.ResponseWriter, r *http.Request) {
	s.mu.Lock()
	sessionToken := s.sessionToken
	runID := s.restoreRunID
	apiBase := s.apiBaseURL
	s.mu.Unlock()

	if sessionToken == "" || runID == 0 {
		writeJSON(w, map[string]any{"status": "fail", "message": "restore not started"})
		return
	}

	endpoint := strings.TrimRight(apiBase, "/") + "/cloudbackup_recovery_refresh_session.php"
	payload := map[string]any{
		"session_token": sessionToken,
		"run_id":        runID,
	}
	buf, _ := json.Marshal(payload)
	resp, err := http.Post(endpoint, "application/json", bytes.NewReader(buf))
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	defer resp.Body.Close()

	var out map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid response"})
		return
	}
	writeJSON(w, out)
}

func (s *recoveryState) handleDebugStatus(w http.ResponseWriter, r *http.Request) {
	s.mu.Lock()
	defer s.mu.Unlock()
	writeJSON(w, map[string]any{
		"status":        "success",
		"debug_enabled": s.debugEnabled && isDevAPIBase(s.apiBaseURL),
		"dev_allowed":   isDevAPIBase(s.apiBaseURL),
		"api_base":      s.apiBaseURL,
		"build_info":    s.buildInfo,
	})
}

func (s *recoveryState) handleDebugToggle(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(405)
		return
	}
	var payload struct {
		Enabled bool `json:"enabled"`
	}
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	if !isDevAPIBase(s.apiBaseURL) {
		writeJSON(w, map[string]any{"status": "fail", "message": "debug toggle disabled for non-dev API"})
		return
	}
	s.mu.Lock()
	s.debugEnabled = payload.Enabled
	if payload.Enabled {
		_ = os.Setenv("E3_RECOVERY_DEBUG_LOG", "1")
	} else {
		_ = os.Unsetenv("E3_RECOVERY_DEBUG_LOG")
	}
	s.mu.Unlock()
	writeJSON(w, map[string]any{"status": "success", "debug_enabled": payload.Enabled})
}

func (s *recoveryState) handleDebugLogs(w http.ResponseWriter, r *http.Request) {
	s.mu.Lock()
	sessionToken := s.sessionToken
	runID := s.restoreRunID
	apiBase := s.apiBaseURL
	s.mu.Unlock()

	if !isDevAPIBase(apiBase) {
		writeJSON(w, map[string]any{"status": "fail", "message": "debug logs disabled for non-dev API"})
		return
	}

	if sessionToken == "" || runID == 0 {
		writeJSON(w, map[string]any{"status": "fail", "message": "restore not started"})
		return
	}

	payload := map[string]any{
		"session_token": sessionToken,
		"run_id":        runID,
	}
	if sinceID := strings.TrimSpace(r.URL.Query().Get("since_id")); sinceID != "" {
		payload["since_id"] = sinceID
	}
	if limit := strings.TrimSpace(r.URL.Query().Get("limit")); limit != "" {
		payload["limit"] = limit
	}
	endpoint := strings.TrimRight(apiBase, "/") + "/cloudbackup_recovery_debug_tail.php"
	buf, _ := json.Marshal(payload)
	resp, err := http.Post(endpoint, "application/json", bytes.NewReader(buf))
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	defer resp.Body.Close()

	var out map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid response"})
		return
	}
	writeJSON(w, out)
}

func (s *recoveryState) runRestore(sessionToken string, runID int64, targetDisk string, targetDiskBytes int64, rp exchangeRestorePoint, storage exchangeStorage, shrink bool) {
	s.setJobStatus("running", "Restore in progress", 0)

	cfg := &agent.AgentConfig{
		APIBaseURL:   s.apiBaseURL,
		RunDir:       "/var/lib/e3-backup-agent/runs",
		DestEndpoint: storage.Endpoint,
		DestRegion:   storage.Region,
	}
	_ = os.MkdirAll(cfg.RunDir, 0o755)
	client := agent.NewRecoveryClient(s.apiBaseURL, sessionToken)
	runner := agent.NewRunnerWithClient(cfg, client, "")

	jobContext := &agent.JobContext{
		JobID:          rp.JobID,
		RunID:          runID,
		Engine:         "disk_image",
		DestType:       storage.DestType,
		DestBucketName: storage.Bucket,
		DestPrefix:     storage.Prefix,
		DestEndpoint:   storage.Endpoint,
		DestRegion:     storage.Region,
		DestAccessKey:  storage.AccessKey,
		DestSecretKey:  storage.SecretKey,
		ManifestID:     rp.ManifestID,
		PolicyJSON:     rp.PolicyJSON,
	}

	payload := map[string]any{
		"manifest_id":          rp.ManifestID,
		"restore_run_id":       runID,
		"target_disk":          targetDisk,
		"target_disk_bytes":    targetDiskBytes,
		"disk_layout_json":     rp.DiskLayoutJSON,
		"disk_total_bytes":     rp.DiskTotalBytes,
		"disk_used_bytes":      rp.DiskUsedBytes,
		"disk_boot_mode":       rp.DiskBootMode,
		"disk_partition_style": rp.DiskPartitionStyle,
		"shrink_enabled":       shrink,
	}

	cmd := agent.PendingCommand{
		CommandID:  0,
		Type:       "disk_restore",
		RunID:      runID,
		JobID:      rp.JobID,
		Payload:    payload,
		JobContext: jobContext,
	}

	ctx := context.Background()
	runner.RunDiskRestoreCommand(ctx, cmd)

	s.setJobStatus("finished", "Restore finished - awaiting status", 100)
}

func (s *recoveryState) setJobStatus(status, message string, progress int) {
	s.mu.Lock()
	defer s.mu.Unlock()
	s.jobStatus = status
	s.jobMessage = message
	s.jobProgress = progress
}

func lookupDiskSize(targetPath string) int64 {
	disks, err := agent.ListDisks()
	if err != nil {
		return 0
	}
	for _, d := range disks {
		if d.Path == targetPath {
			return int64(d.SizeBytes)
		}
	}
	return 0
}

func writeJSON(w http.ResponseWriter, payload map[string]any) {
	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(payload)
}

func isDevAPIBase(apiBase string) bool {
	apiBase = strings.ToLower(strings.TrimSpace(apiBase))
	return strings.Contains(apiBase, "dev.eazybackup.ca")
}

func loadBuildInfo() string {
	candidates := []string{}
	if pf := strings.TrimSpace(os.Getenv("ProgramFiles")); pf != "" {
		candidates = append(candidates, filepath.Join(pf, "E3Recovery", "build-info.txt"))
	}
	candidates = append(candidates,
		`C:\Program Files\E3Recovery\build-info.txt`,
		`X:\Program Files\E3Recovery\build-info.txt`,
	)
	for _, p := range candidates {
		if p == "" {
			continue
		}
		if data, err := os.ReadFile(p); err == nil {
			return strings.TrimSpace(string(data))
		}
	}
	return ""
}

const recoveryHTML = `<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
  <title>Recovery Console</title>
  <style>
    * { box-sizing: border-box; }
    body {
      font-family: Segoe UI, Tahoma, Arial, sans-serif;
      background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
      color: #e2e8f0;
      margin: 0;
      padding: 24px;
      min-height: 100vh;
    }

    /* Header */
    .header {
      display: flex;
      align-items: center;
      margin-bottom: 24px;
      padding-bottom: 20px;
      border-bottom: 1px solid #334155;
    }
    .header-icon {
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 16px;
      box-shadow: 0 4px 14px rgba(14, 165, 233, 0.3);
    }
    .header-icon svg { width: 32px; height: 32px; }
    .header-text h1 {
      margin: 0 0 4px 0;
      font-size: 24px;
      font-weight: 600;
      color: #f8fafc;
    }
    .header-text p {
      margin: 0;
      font-size: 14px;
      color: #64748b;
    }

    /* Step indicator */
    .steps {
      display: flex;
      margin-bottom: 24px;
    }
    .step {
      flex: 1;
      padding: 14px 12px;
      text-align: center;
      background: #1e293b;
      font-size: 13px;
      font-weight: 500;
      color: #64748b;
      border: 1px solid #334155;
      position: relative;
    }
    .step:first-child { border-radius: 10px 0 0 10px; }
    .step:last-child { border-radius: 0 10px 10px 0; }
    .step.active {
      background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
      color: #fff;
      border-color: #0ea5e9;
      z-index: 1;
    }
    .step.completed {
      background: #1e293b;
      color: #22c55e;
    }
    .step-num {
      display: inline-block;
      width: 22px;
      height: 22px;
      line-height: 22px;
      border-radius: 50%;
      background: #334155;
      color: #94a3b8;
      font-size: 12px;
      margin-right: 8px;
    }
    .step.active .step-num { background: rgba(255,255,255,0.2); color: #fff; }
    .step.completed .step-num { background: #22c55e; color: #fff; }

    /* Cards */
    .card {
      background: rgba(17, 24, 39, 0.8);
      border: 1px solid #334155;
      border-radius: 14px;
      padding: 20px;
      margin-bottom: 16px;
      backdrop-filter: blur(10px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    }
    .card-header {
      display: flex;
      align-items: center;
      font-size: 15px;
      font-weight: 600;
      color: #f1f5f9;
      margin-bottom: 16px;
    }
    .card-header svg {
      width: 20px;
      height: 20px;
      margin-right: 10px;
      opacity: 0.7;
    }

    /* Form fields */
    .field { margin-bottom: 14px; }
    .field:last-child { margin-bottom: 0; }
    .field label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: #94a3b8;
      margin-bottom: 8px;
    }
    .field input[type="text"],
    .field input[type="password"],
    .field select {
      width: 100%;
      padding: 12px 14px;
      background: #0f172a;
      color: #e2e8f0;
      border: 1px solid #334155;
      border-radius: 10px;
      font-size: 14px;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .field input:focus,
    .field select:focus {
      outline: none;
      border-color: #0ea5e9;
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
    }
    .field input::placeholder { color: #475569; }

    /* Toggle switch */
    .toggle-field {
      display: flex;
      align-items: center;
      cursor: pointer;
      padding: 4px 0;
    }
    .toggle {
      position: relative;
      width: 48px;
      height: 26px;
      margin-right: 12px;
      flex-shrink: 0;
    }
    .toggle input {
      opacity: 0;
      width: 0;
      height: 0;
      position: absolute;
    }
    .toggle-slider {
      position: absolute;
      top: 0; left: 0; right: 0; bottom: 0;
      background: #334155;
      border-radius: 13px;
      transition: background 0.3s;
    }
    .toggle-slider:before {
      content: '';
      position: absolute;
      height: 20px;
      width: 20px;
      left: 3px;
      bottom: 3px;
      background: #fff;
      border-radius: 50%;
      transition: transform 0.3s;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .toggle input:checked + .toggle-slider { background: #0ea5e9; }
    .toggle input:checked + .toggle-slider:before { transform: translateX(22px); }
    .toggle-label {
      font-size: 14px;
      color: #e2e8f0;
    }
    .toggle-hint {
      font-size: 12px;
      color: #64748b;
      margin-top: 2px;
    }

    /* Buttons */
    .btn-row {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    button, .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 20px;
      font-size: 14px;
      font-weight: 600;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.2s;
    }
    button svg, .btn svg { width: 18px; height: 18px; }
    .btn-primary {
      background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
      color: #fff;
      box-shadow: 0 4px 14px rgba(14, 165, 233, 0.3);
    }
    .btn-primary:hover {
      background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
    }
    .btn-success {
      background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
      color: #fff;
      box-shadow: 0 4px 14px rgba(34, 197, 94, 0.3);
    }
    .btn-success:hover {
      background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
      transform: translateY(-1px);
    }
    .btn-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: #fff;
      box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
    }
    .btn-danger:hover {
      background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
      transform: translateY(-1px);
    }
    .btn-secondary {
      background: #334155;
      color: #e2e8f0;
    }
    .btn-secondary:hover {
      background: #475569;
    }
    button:disabled, .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none !important;
      box-shadow: none !important;
    }

    /* Status badges */
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
    }
    .badge svg { width: 14px; height: 14px; }
    .badge-pending { background: #1e293b; color: #94a3b8; border: 1px solid #334155; }
    .badge-success { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
    .badge-error { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .badge-running { background: rgba(14, 165, 233, 0.15); color: #0ea5e9; }

    /* Progress bar */
    .progress-container { margin: 16px 0; }
    .progress-bar {
      height: 10px;
      background: #1e293b;
      border-radius: 5px;
      overflow: hidden;
      box-shadow: inset 0 1px 3px rgba(0,0,0,0.2);
    }
    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #0ea5e9 0%, #6366f1 50%, #8b5cf6 100%);
      border-radius: 5px;
      transition: width 0.3s ease;
      position: relative;
    }
    .progress-fill.animated {
      background-size: 200% 100%;
      animation: progressShine 2s linear infinite;
    }
    @keyframes progressShine {
      0% { background-position: 200% 0; }
      100% { background-position: -200% 0; }
    }
    .progress-stats {
      display: flex;
      justify-content: space-between;
      margin-top: 10px;
      font-size: 13px;
      color: #94a3b8;
    }
    .progress-stats .highlight { color: #0ea5e9; font-weight: 600; }

    /* Status display */
    .status-display {
      padding: 14px;
      background: #0f172a;
      border-radius: 10px;
      margin-bottom: 16px;
      border: 1px solid #334155;
    }
    .status-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .status-text {
      font-size: 14px;
      color: #e2e8f0;
    }

    /* Events log */
    .events {
      max-height: 200px;
      overflow-y: auto;
      font-family: Consolas, Monaco, monospace;
      font-size: 12px;
      line-height: 1.6;
      background: #0a0f1a;
      border: 1px solid #1e293b;
      border-radius: 10px;
      padding: 12px;
    }
    .events::-webkit-scrollbar { width: 8px; }
    .events::-webkit-scrollbar-track { background: #1e293b; border-radius: 4px; }
    .events::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
    .event-line { padding: 2px 0; color: #94a3b8; }
    .event-line .ts { color: #64748b; }
    .event-line .msg { color: #cbd5e1; }

    /* Utility */
    .hidden { display: none !important; }
    .mt-2 { margin-top: 8px; }
    .mt-4 { margin-top: 16px; }
    .text-muted { color: #64748b; font-size: 13px; }
  </style>
</head>
<body>

  <!-- Header -->
  <div class="header">
    <div class="header-icon">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
      </svg>
    </div>
    <div class="header-text">
      <h1>Recovery Console</h1>
      <p>Bare-metal disk recovery powered by E3 Backup</p>
    </div>
  </div>

  <!-- Steps -->
  <div class="steps">
    <div class="step" id="step1">
      <span class="step-num">1</span>Authenticate
    </div>
    <div class="step" id="step2">
      <span class="step-num">2</span>Select Disk
    </div>
    <div class="step" id="step3">
      <span class="step-num">3</span>Restore
    </div>
  </div>

  <!-- Token Card -->
  <div class="card">
    <div class="card-header">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
      </svg>
      Recovery Token
    </div>
    <div class="field">
      <label>Enter the recovery token from your backup portal</label>
      <input type="text" id="token" placeholder="e.g. REC-XXXX-XXXX-XXXX"/>
    </div>
    <div class="mt-4">
      <button class="btn-primary" onclick="exchange()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Validate Token
      </button>
    </div>
    <div id="tokenStatus" class="mt-4"></div>
  </div>

  <!-- Disk Selection Card -->
  <div class="card">
    <div class="card-header">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
      </svg>
      Target Disk
    </div>
    <div class="field">
      <label>Select the disk to restore to</label>
      <select id="diskSelect">
        <option value="">Loading disks...</option>
      </select>
    </div>
    <p class="text-muted mt-2">Warning: All data on the selected disk will be overwritten.</p>
  </div>

  <!-- Options Card -->
  <div class="card">
    <div class="card-header">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      Restore Options
    </div>
    <label class="toggle-field">
      <div class="toggle">
        <input type="checkbox" id="shrinkEnabled"/>
        <span class="toggle-slider"></span>
      </div>
      <div>
        <div class="toggle-label">Allow partition shrinking</div>
        <div class="toggle-hint">Shrink partitions to fit smaller disks (NTFS/ext4 only)</div>
      </div>
    </label>
  </div>

  <!-- Debug Card -->
  <div class="card" id="debugCard">
    <div class="card-header">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3a.75.75 0 01.75.75V6h3V3.75a.75.75 0 011.5 0V6h1.5a2.25 2.25 0 012.25 2.25V9h-2.25V8.25a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75V9H3.75V8.25A2.25 2.25 0 016 6h1.5V3.75a.75.75 0 01.75-.75z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 9h15v5.25a2.25 2.25 0 01-2.25 2.25h-1.5v3.75l-3-3H9a2.25 2.25 0 01-2.25-2.25V9z"/>
      </svg>
      Debug Controls
    </div>
    <label class="toggle-field">
      <div class="toggle">
        <input type="checkbox" id="debugEnabled" onchange="toggleDebug()"/>
        <span class="toggle-slider"></span>
      </div>
      <div>
        <div class="toggle-label">Enable recovery debug logging</div>
        <div class="toggle-hint">Show detailed diagnostics for every recovery step (dev only).</div>
      </div>
    </label>
    <div class="text-muted mt-2" id="debugMeta">Loading debug status...</div>
  </div>

  <!-- Action Card -->
  <div class="card">
    <div class="card-header">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
      </svg>
      Restore Actions
    </div>

    <div class="status-display">
      <div class="status-row">
        <span class="status-text" id="statusText">Ready to restore</span>
        <span class="badge badge-pending" id="statusBadge">
          <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <span id="statusBadgeText">Waiting</span>
        </span>
      </div>
    </div>

    <div class="progress-container hidden" id="progressContainer">
      <div class="progress-bar">
        <div class="progress-fill" id="progressFill" style="width: 0%"></div>
      </div>
      <div class="progress-stats">
        <span id="progressPct">0%</span>
        <span id="progressBytes">0 B / 0 B</span>
        <span id="progressSpeed">-- /s</span>
        <span id="progressEta">ETA: --</span>
      </div>
    </div>

    <div class="btn-row mt-4">
      <button class="btn-success" id="startBtn" onclick="startRestore()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Start Restore
      </button>
      <button class="btn-danger" id="cancelBtn" onclick="cancelRestore()">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Cancel
      </button>
    </div>
  </div>

  <!-- Events Card -->
  <div class="card">
    <div class="card-header">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
      Event Log
    </div>
    <div id="events" class="events">
      <div class="event-line"><span class="msg">Waiting for restore to start...</span></div>
    </div>
  </div>

  <!-- Debug Log Panel -->
  <div class="card hidden" id="debugPanel">
    <div class="card-header">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      Debug Log
    </div>
    <div id="debugLogs" class="events">
      <div class="event-line"><span class="msg">Enable debug logging to view diagnostics.</span></div>
    </div>
  </div>

<script>
var lastEventId = 0;
var polling = false;
var tokenValidated = false;
var currentStep = 1;
var debugEnabled = false;
var lastDebugId = 0;
var lastRefreshAt = 0;

function updateSteps(step) {
  currentStep = step;
  var steps = ['step1', 'step2', 'step3'];
  for (var i = 0; i < steps.length; i++) {
    var el = document.getElementById(steps[i]);
    el.className = 'step';
    if (i + 1 < step) {
      el.className = 'step completed';
    } else if (i + 1 === step) {
      el.className = 'step active';
    }
  }
}

function setStatus(text, badgeType, badgeText) {
  document.getElementById('statusText').textContent = text;
  var badge = document.getElementById('statusBadge');
  badge.className = 'badge badge-' + badgeType;
  document.getElementById('statusBadgeText').textContent = badgeText;
}

function showProgress(show) {
  var container = document.getElementById('progressContainer');
  container.className = show ? 'progress-container' : 'progress-container hidden';
}

function updateProgress(pct, bytes, total, speed, eta) {
  var fill = document.getElementById('progressFill');
  fill.style.width = pct + '%';
  fill.className = pct > 0 && pct < 100 ? 'progress-fill animated' : 'progress-fill';
  document.getElementById('progressPct').innerHTML = '<span class="highlight">' + pct.toFixed(1) + '%</span>';
  document.getElementById('progressBytes').textContent = bytes + ' / ' + total;
  document.getElementById('progressSpeed').textContent = speed + '/s';
  document.getElementById('progressEta').textContent = 'ETA: ' + eta;
}

function xhrJson(method, url, body, cb) {
  var xhr = new XMLHttpRequest();
  xhr.open(method, url, true);
  xhr.onreadystatechange = function () {
    if (xhr.readyState !== 4) return;
    var data = null;
    try {
      data = JSON.parse(xhr.responseText || '{}');
    } catch (e) {
      data = {};
    }
    cb(xhr.status, data);
  };
  if (body) {
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.send(JSON.stringify(body));
  } else {
    xhr.send();
  }
}

function setDebugPanelVisible(show) {
  var panel = document.getElementById('debugPanel');
  if (!panel) return;
  panel.className = show ? 'card' : 'card hidden';
}

function loadDebugStatus() {
  xhrJson('GET', '/api/debug-status', null, function (_status, data) {
    if (!data || data.status !== 'success') {
      var meta = document.getElementById('debugMeta');
      if (meta) meta.textContent = 'Debug status unavailable';
      return;
    }
    var devAllowed = !!data.dev_allowed;
    debugEnabled = !!data.debug_enabled && devAllowed;
    var toggle = document.getElementById('debugEnabled');
    var debugCard = document.getElementById('debugCard');
    if (!devAllowed) {
      if (debugCard) debugCard.className = 'card hidden';
      setDebugPanelVisible(false);
      return;
    }
    if (debugCard) debugCard.className = 'card';
    if (toggle) {
      toggle.checked = debugEnabled;
      toggle.disabled = false;
    }
    setDebugPanelVisible(debugEnabled);
    var metaText = '';
    if (data.build_info) {
      metaText = String(data.build_info).replace(/\r?\n/g, ' | ');
    }
    if (data.api_base) {
      metaText = (metaText ? metaText + ' | ' : '') + 'API: ' + data.api_base;
    }
    var meta = document.getElementById('debugMeta');
    if (meta) meta.textContent = metaText || 'Debug logging available';
  });
}

function toggleDebug() {
  var toggle = document.getElementById('debugEnabled');
  var enabled = toggle && toggle.checked;
  xhrJson('POST', '/api/debug-toggle', { enabled: enabled }, function (_status, data) {
    debugEnabled = !!(data && data.debug_enabled);
    if (toggle) toggle.checked = debugEnabled;
    setDebugPanelVisible(debugEnabled);
    if (!debugEnabled) {
      lastDebugId = 0;
      var container = document.getElementById('debugLogs');
      if (container) {
        container.innerHTML = '<div class="event-line"><span class="msg">Debug logging disabled.</span></div>';
      }
    }
  });
}

function addDebugLog(entry) {
  var container = document.getElementById('debugLogs');
  if (!container) return;
  var line = document.createElement('div');
  line.className = 'event-line';
  var ts = entry.time || new Date().toLocaleTimeString();
  var level = (entry.level || 'info').toUpperCase();
  var code = entry.code ? (' ' + entry.code) : '';
  var msg = entry.message || '';
  line.innerHTML = '<span class="ts">[' + ts + ']</span> <span class="msg">[' + level + ']' + code + ' ' + msg + '</span>';
  container.appendChild(line);
  if (entry.details) {
    var detailsLine = document.createElement('div');
    detailsLine.className = 'event-line';
    var detailsText = '';
    try {
      detailsText = JSON.stringify(entry.details);
    } catch (e) {
      detailsText = String(entry.details);
    }
    detailsLine.innerHTML = '<span class="msg">' + detailsText + '</span>';
    container.appendChild(detailsLine);
  }
  container.scrollTop = container.scrollHeight;
}

function pollDebugLogs(cb) {
  if (!debugEnabled) {
    if (cb) cb();
    return;
  }
  var url = lastDebugId > 0 ? '/api/debug-logs?since_id=' + encodeURIComponent(String(lastDebugId)) : '/api/debug-logs';
  xhrJson('GET', url, null, function (_status, data) {
    if (!data || data.status !== 'success') {
      if (cb) cb();
      return;
    }
    var logs = data.logs || [];
    for (var i = 0; i < logs.length; i++) {
      var entry = logs[i];
      addDebugLog(entry);
      if (typeof entry.id === 'number' && entry.id > lastDebugId) {
        lastDebugId = entry.id;
      }
    }
    if (cb) cb();
  });
}

function exchange() {
  var token = document.getElementById('token').value;
  if (!token) {
    setStatus('Please enter a recovery token', 'warning', 'Required');
    return;
  }
  setStatus('Validating token...', 'running', 'Checking');
  xhrJson('POST', '/api/exchange', { token: token }, function (_status, data) {
    if (!data || data.status !== 'success') {
      setStatus((data && data.message) || 'Token validation failed', 'error', 'Failed');
      document.getElementById('tokenStatus').innerHTML = '<span class="badge badge-error">Invalid token</span>';
      return;
    }
    tokenValidated = true;
    setStatus('Token validated successfully', 'success', 'Valid');
    document.getElementById('tokenStatus').innerHTML = '<span class="badge badge-success"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Token validated</span>';
    updateSteps(2);
  });
}

function loadDisks() {
  xhrJson('GET', '/api/disks', null, function (_status, data) {
    var sel = document.getElementById('diskSelect');
    sel.innerHTML = '';
    var disks = (data && data.disks) ? data.disks : [];
    if (disks.length === 0) {
      var opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'No disks found';
      sel.appendChild(opt);
      return;
    }
    for (var i = 0; i < disks.length; i++) {
      var d = disks[i];
      var opt = document.createElement('option');
      opt.value = d.path;
      var sizeGB = Math.round(d.size_bytes / 1024 / 1024 / 1024);
      opt.textContent = (d.name || d.path) + ' (' + sizeGB + ' GB)';
      sel.appendChild(opt);
    }
  });
}

function startRestore() {
  if (!tokenValidated) {
    setStatus('Please validate your recovery token first', 'warning', 'Required');
    updateSteps(1);
    return;
  }
  var targetDisk = document.getElementById('diskSelect').value;
  if (!targetDisk) {
    setStatus('Please select a target disk', 'warning', 'Required');
    return;
  }
  var shrink = document.getElementById('shrinkEnabled').checked;

  setStatus('Starting restore...', 'running', 'Starting');
  updateSteps(3);
  showProgress(true);
  document.getElementById('startBtn').disabled = true;

  xhrJson('POST', '/api/start', { target_disk: targetDisk, shrink_enabled: shrink }, function (_status, data) {
    if (!data || data.status !== 'success') {
      setStatus((data && data.message) || 'Failed to start restore', 'error', 'Failed');
      showProgress(false);
      document.getElementById('startBtn').disabled = false;
      return;
    }
    lastEventId = 0;
    lastDebugId = 0;
    lastRefreshAt = 0;
    document.getElementById('events').innerHTML = '';
    var debugContainer = document.getElementById('debugLogs');
    if (debugContainer) {
      debugContainer.innerHTML = '';
    }
    addEvent('Restore started');
    startPolling();
  });
}

function cancelRestore() {
  xhrJson('POST', '/api/cancel', {}, function (_status, data) {
    if (!data || data.status !== 'success') {
      setStatus((data && data.message) || 'Cancel failed', 'error', 'Error');
      return;
    }
    setStatus('Cancellation requested...', 'warning', 'Cancelling');
  });
}

function formatBytes(n) {
  var units = ['B', 'KB', 'MB', 'GB', 'TB'];
  var val = Number(n || 0);
  var idx = 0;
  while (val >= 1024 && idx < units.length - 1) {
    val /= 1024;
    idx += 1;
  }
  return val.toFixed(idx === 0 ? 0 : 1) + ' ' + units[idx];
}

function formatEta(seconds) {
  if (!seconds || seconds < 0) return '--';
  if (seconds < 60) return Math.round(seconds) + 's';
  if (seconds < 3600) return Math.round(seconds / 60) + 'm';
  var h = Math.floor(seconds / 3600);
  var m = Math.round((seconds % 3600) / 60);
  return h + 'h ' + m + 'm';
}

function addEvent(msg) {
  var container = document.getElementById('events');
  var line = document.createElement('div');
  line.className = 'event-line';
  var now = new Date();
  var ts = now.toLocaleTimeString();
  line.innerHTML = '<span class="ts">[' + ts + ']</span> <span class="msg">' + msg + '</span>';
  container.appendChild(line);
  container.scrollTop = container.scrollHeight;
}

function pollRunStatus(cb) {
  xhrJson('GET', '/api/run-status', null, function (_status, data) {
    if (!data || data.status !== 'success') {
      setStatus((data && data.message) || 'Status unavailable', 'warning', 'Unknown');
      cb(false);
      return;
    }
    var run = data.run || {};
    var statusText = ((run.status || '') + ' ' + (run.current_item || '')).trim();
    var statusLower = (run.status || '').toLowerCase();

    var badgeType = 'running';
    var badgeText = 'Running';
    if (statusLower === 'success') { badgeType = 'success'; badgeText = 'Complete'; }
    else if (statusLower === 'failed') { badgeType = 'error'; badgeText = 'Failed'; }
    else if (statusLower === 'cancelled') { badgeType = 'warning'; badgeText = 'Cancelled'; }
    else if (statusLower === 'warning') { badgeType = 'warning'; badgeText = 'Warning'; }

    setStatus(statusText || 'Processing...', badgeType, badgeText);

    var pct = run.progress_pct || 0;
    var bytesStr = formatBytes(run.bytes_transferred || 0);
    var totalStr = formatBytes(run.bytes_total || 0);
    var speedStr = formatBytes(run.speed_bytes_per_sec || 0);
    var etaStr = formatEta(run.eta_seconds);

    updateProgress(pct, bytesStr, totalStr, speedStr, etaStr);

    if (run.error_summary) {
      addEvent('Error: ' + run.error_summary);
    }

    var terminal = (statusLower === 'success' || statusLower === 'failed' || statusLower === 'cancelled' || statusLower === 'warning');
    if (terminal) {
      document.getElementById('startBtn').disabled = false;
      if (statusLower === 'success') {
        addEvent('Restore completed successfully!');
      }
    }
    cb(terminal);
  });
}

function pollRunEvents(cb) {
  var url = lastEventId > 0 ? '/api/run-events?since_id=' + encodeURIComponent(String(lastEventId)) : '/api/run-events';
  xhrJson('GET', url, null, function (_status, data) {
    if (!data || data.status !== 'success') {
      cb();
      return;
    }
    if (!data.events || typeof data.events.length === 'undefined') {
      cb();
      return;
    }
    for (var i = 0; i < data.events.length; i++) {
      var ev = data.events[i];
      var msg = ev.message || '';
      if (msg) addEvent(msg);
      if (typeof ev.id === 'number' && ev.id > lastEventId) {
        lastEventId = ev.id;
      }
    }
    cb();
  });
}

function refreshSession(cb) {
  xhrJson('POST', '/api/refresh-session', {}, function (_status, _data) {
    lastRefreshAt = Date.now();
    if (cb) cb();
  });
}

function startPolling() {
  if (polling) return;
  polling = true;
  var tick = function () {
    if (!polling) return;
    pollRunStatus(function (terminal) {
      pollRunEvents(function () {
        pollDebugLogs(function () {
          if (terminal) {
            polling = false;
            return;
          }
          if (!lastRefreshAt || (Date.now() - lastRefreshAt) > 5 * 60 * 1000) {
            refreshSession(function () {});
          }
          setTimeout(tick, 1500);
        });
      });
    });
  };
  tick();
}

// Initialize
updateSteps(1);
loadDisks();
loadDebugStatus();
</script>
</body>
</html>`
