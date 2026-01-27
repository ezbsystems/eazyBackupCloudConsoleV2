package main

import (
	"bytes"
	"context"
	"encoding/json"
	"flag"
	"log"
	"net/http"
	"os"
	"strings"
	"sync"

	"github.com/your-org/e3-backup-agent/internal/agent"
)

const defaultRecoveryAPI = "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"

type recoveryState struct {
	mu          sync.Mutex
	apiBaseURL  string
	sessionToken string
	restorePoint exchangeRestorePoint
	storage      exchangeStorage
	jobStatus    string
	jobMessage   string
	jobProgress  int
	restoreRunID int64
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

	state := &recoveryState{apiBaseURL: *apiBase}

	mux := http.NewServeMux()
	mux.HandleFunc("/", state.handleIndex)
	mux.HandleFunc("/api/exchange", state.handleExchange)
	mux.HandleFunc("/api/disks", state.handleDisks)
	mux.HandleFunc("/api/start", state.handleStartRestore)
	mux.HandleFunc("/api/run-status", state.handleRunStatus)
	mux.HandleFunc("/api/run-events", state.handleRunEvents)
	mux.HandleFunc("/api/cancel", state.handleCancel)
	mux.HandleFunc("/api/status", state.handleStatus)

	log.Printf("recovery UI listening on %s", *listen)
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
		Status         string               `json:"status"`
		Message        string               `json:"message"`
		SessionToken   string               `json:"session_token"`
		RestorePoint   exchangeRestorePoint `json:"restore_point"`
		Storage        exchangeStorage      `json:"storage"`
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
		"session_token":    sessionToken,
		"target_disk":      payload.TargetDisk,
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
	}

	payload := map[string]any{
		"manifest_id":       rp.ManifestID,
		"restore_run_id":    runID,
		"target_disk":       targetDisk,
		"target_disk_bytes": targetDiskBytes,
		"disk_layout_json":  rp.DiskLayoutJSON,
		"disk_total_bytes":  rp.DiskTotalBytes,
		"disk_used_bytes":   rp.DiskUsedBytes,
		"disk_boot_mode":    rp.DiskBootMode,
		"disk_partition_style": rp.DiskPartitionStyle,
		"shrink_enabled":    shrink,
	}

	cmd := agent.PendingCommand{
		CommandID: 0,
		Type:      "disk_restore",
		RunID:     runID,
		JobID:     rp.JobID,
		Payload:   payload,
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

const recoveryHTML = `<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Recovery Console</title>
  <style>
    body { font-family: sans-serif; background: #0f172a; color: #e2e8f0; padding: 24px; }
    .card { background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 16px; margin-bottom: 16px; }
    button { background: #0ea5e9; color: #fff; border: 0; padding: 8px 12px; border-radius: 8px; cursor: pointer; }
    select, input { width: 100%; padding: 8px; background: #0b1220; color: #e2e8f0; border: 1px solid #1f2937; border-radius: 8px; }
    small { color: #94a3b8; }
    .row { display: flex; gap: 8px; align-items: center; }
    .events { max-height: 260px; overflow: auto; font-family: monospace; font-size: 12px; background: #0b1220; border: 1px solid #1f2937; padding: 8px; border-radius: 8px; }
    .muted { color: #94a3b8; }
  </style>
</head>
<body>
  <h2>Recovery Console</h2>
  <div class="card">
    <label>Recovery Token</label>
    <input id="token" placeholder="Enter recovery token"/>
    <button onclick="exchange()">Validate Token</button>
  </div>
  <div class="card">
    <label>Target Disk</label>
    <select id="diskSelect"></select>
  </div>
  <div class="card">
    <label><input type="checkbox" id="shrinkEnabled"/> Allow shrink (NTFS/ext4 only)</label>
  </div>
  <div class="card">
    <div class="row">
      <button onclick="startRestore()">Start Restore</button>
      <button id="cancelBtn" onclick="cancelRestore()" style="background:#ef4444;">Cancel Restore</button>
    </div>
    <div id="status" style="margin-top: 8px;"></div>
    <div id="progress" class="muted" style="margin-top: 4px;"></div>
  </div>
  <div class="card">
    <label>Events</label>
    <div id="events" class="events"></div>
  </div>
<script>
let lastEventId = 0;
let polling = false;

async function exchange() {
  const token = document.getElementById('token').value;
  const resp = await fetch('/api/exchange', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ token })});
  const data = await resp.json();
  if (data.status !== 'success') {
    alert(data.message || 'Failed');
    return;
  }
  document.getElementById('status').textContent = 'Token validated.';
}
async function loadDisks() {
  const resp = await fetch('/api/disks');
  const data = await resp.json();
  const sel = document.getElementById('diskSelect');
  sel.innerHTML = '';
  (data.disks || []).forEach(d => {
    const opt = document.createElement('option');
    opt.value = d.path;
    opt.textContent = (d.name || d.path) + ' (' + Math.round(d.size_bytes/1024/1024/1024) + ' GB)';
    sel.appendChild(opt);
  });
}
async function startRestore() {
  const targetDisk = document.getElementById('diskSelect').value;
  const shrink = document.getElementById('shrinkEnabled').checked;
  const resp = await fetch('/api/start', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ target_disk: targetDisk, shrink_enabled: shrink })});
  const data = await resp.json();
  if (data.status !== 'success') {
    document.getElementById('status').textContent = data.message || 'Failed';
    return;
  }
  lastEventId = 0;
  document.getElementById('events').textContent = '';
  startPolling();
}

async function cancelRestore() {
  const resp = await fetch('/api/cancel', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({})});
  const data = await resp.json();
  if (data.status !== 'success') {
    document.getElementById('status').textContent = data.message || 'Cancel failed';
    return;
  }
  document.getElementById('status').textContent = 'Cancellation requested.';
}

function formatBytes(n) {
  const units = ['B','KB','MB','GB','TB'];
  let val = Number(n || 0);
  let idx = 0;
  while (val >= 1024 && idx < units.length - 1) {
    val /= 1024;
    idx += 1;
  }
  return val.toFixed(idx === 0 ? 0 : 1) + ' ' + units[idx];
}

async function pollRunStatus() {
  const resp = await fetch('/api/run-status');
  const data = await resp.json();
  if (data.status !== 'success') {
    document.getElementById('status').textContent = data.message || 'Status unavailable';
    return { terminal: false };
  }
  const run = data.run || {};
  const statusText = ((run.status || '') + ' ' + (run.current_item || '')).trim();
  document.getElementById('status').textContent = statusText || 'Running';
  const progress = [];
  if (run.progress_pct !== null && typeof run.progress_pct !== 'undefined') {
    progress.push('Progress: ' + Number(run.progress_pct).toFixed(1) + '%');
  }
  if (run.bytes_transferred !== null && run.bytes_total !== null) {
    progress.push(formatBytes(run.bytes_transferred) + ' / ' + formatBytes(run.bytes_total));
  }
  if (run.speed_bytes_per_sec !== null) {
    progress.push('Speed: ' + formatBytes(run.speed_bytes_per_sec) + '/s');
  }
  if (run.eta_seconds !== null) {
    progress.push('ETA: ' + Math.round(run.eta_seconds) + 's');
  }
  if (run.error_summary) {
    progress.push('Error: ' + run.error_summary);
  }
  document.getElementById('progress').textContent = progress.join(' · ');
  const terminal = ['success','failed','cancelled','warning'].includes((run.status || '').toLowerCase());
  return { terminal };
}

async function pollRunEvents() {
  const url = lastEventId > 0 ? '/api/run-events?since_id=' + encodeURIComponent(String(lastEventId)) : '/api/run-events';
  const resp = await fetch(url);
  const data = await resp.json();
  if (data.status !== 'success') return;
  if (!Array.isArray(data.events)) return;
  const container = document.getElementById('events');
  data.events.forEach(ev => {
    const line = document.createElement('div');
    const ts = ev.ts || '';
    const msg = ev.message || '';
    line.textContent = (ts + ' ' + msg).trim();
    container.appendChild(line);
    if (typeof ev.id === 'number' && ev.id > lastEventId) {
      lastEventId = ev.id;
    }
  });
  container.scrollTop = container.scrollHeight;
}

async function startPolling() {
  if (polling) return;
  polling = true;
  while (polling) {
    const status = await pollRunStatus();
    await pollRunEvents();
    if (status.terminal) {
      polling = false;
      break;
    }
    await new Promise(resolve => setTimeout(resolve, 1500));
  }
}

loadDisks();
</script>
</body>
</html>`
