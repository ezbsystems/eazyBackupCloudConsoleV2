//go:build windows

package main

import (
	"encoding/json"
	"flag"
	"fmt"
	"net"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"time"

	agentapi "github.com/your-org/e3-backup-agent/internal/agent"
	"github.com/your-org/e3-backup-agent/internal/recoverymedia"
)

type mediaBuildManifest struct {
	Mode                string `json:"mode"`
	SourceAgentUUID     string `json:"source_agent_uuid"`
	SourceAgentHostname string `json:"source_agent_hostname"`
	BaseISOURL          string `json:"base_iso_url"`
	BaseISOSHA256       string `json:"base_iso_sha256"`
	SourceBundleURL     string `json:"source_bundle_url"`
	SourceBundleSHA256  string `json:"source_bundle_sha256"`
	SourceBundleProfile string `json:"source_bundle_profile"`
	BroadExtrasURL      string `json:"broad_extras_url"`
	BroadExtrasSHA256   string `json:"broad_extras_sha256"`
	HasSourceBundle     bool   `json:"has_source_bundle"`
	Warning             string `json:"warning"`
}

type buildJob struct {
	ID         string   `json:"id"`
	Status     string   `json:"status"`
	Progress   int      `json:"progress"`
	Message    string   `json:"message"`
	SpeedBps   int64    `json:"speed_bps"`
	EtaSeconds int64    `json:"eta_seconds"`
	Logs       []string `json:"logs"`
}

type app struct {
	apiBase string

	mu   sync.Mutex
	jobs map[string]*buildJob
}

func main() {
	apiBase := flag.String("api-base", "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api", "Media token exchange API base")
	flag.Parse()

	a := &app{apiBase: strings.TrimRight(*apiBase, "/"), jobs: map[string]*buildJob{}}
	mux := http.NewServeMux()
	mux.HandleFunc("/", a.handleIndex)
	mux.HandleFunc("/api/disks", a.handleDisks)
	mux.HandleFunc("/api/exchange", a.handleExchange)
	mux.HandleFunc("/api/build", a.handleBuildStart)
	mux.HandleFunc("/api/status", a.handleStatus)

	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		panic(err)
	}
	addr := "http://" + ln.Addr().String()
	go func() {
		_ = http.Serve(ln, mux)
	}()
	_ = exec.Command("rundll32", "url.dll,FileProtocolHandler", addr).Start()
	select {}
}

func (a *app) storeJob(job *buildJob) {
	a.mu.Lock()
	defer a.mu.Unlock()
	cp := *job
	cp.Logs = append([]string(nil), job.Logs...)
	a.jobs[job.ID] = &cp
}

func (a *app) updateJob(id, status, message string, progress int, speed, eta int64) {
	a.mu.Lock()
	defer a.mu.Unlock()
	j := a.jobs[id]
	if j == nil {
		return
	}
	if status != "" {
		j.Status = status
	}
	if message != "" {
		j.Message = message
	}
	if progress >= 0 {
		j.Progress = progress
	}
	j.SpeedBps = speed
	j.EtaSeconds = eta
}

func (a *app) appendJobLog(id, line string) {
	a.mu.Lock()
	defer a.mu.Unlock()
	j := a.jobs[id]
	if j == nil {
		return
	}
	j.Logs = append(j.Logs, line)
	if len(j.Logs) > 400 {
		j.Logs = j.Logs[len(j.Logs)-400:]
	}
}

func (a *app) getJob(id string) *buildJob {
	a.mu.Lock()
	defer a.mu.Unlock()
	j := a.jobs[id]
	if j == nil {
		return nil
	}
	cp := *j
	cp.Logs = append([]string(nil), j.Logs...)
	return &cp
}

func (a *app) handleDisks(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}
	disks, err := recoverymedia.ListRemovableDisks()
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	writeJSON(w, map[string]any{"status": "success", "disks": disks})
}

func (a *app) handleExchange(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
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
		writeJSON(w, map[string]any{"status": "fail", "message": "token is required"})
		return
	}
	manifest, err := agentapi.ExchangeMediaBuildToken(a.apiBase, token)
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	writeJSON(w, map[string]any{"status": "success", "manifest": manifest})
}

func (a *app) handleBuildStart(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}
	var payload struct {
		Token      string `json:"token"`
		DiskNumber int64  `json:"disk_number"`
	}
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	token := strings.TrimSpace(payload.Token)
	if token == "" || payload.DiskNumber < 0 {
		writeJSON(w, map[string]any{"status": "fail", "message": "token and disk_number are required"})
		return
	}
	manifestRaw, err := agentapi.ExchangeMediaBuildToken(a.apiBase, token)
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	manifest := &mediaBuildManifest{
		Mode:                manifestRaw.Mode,
		SourceAgentUUID:     manifestRaw.SourceAgentUUID,
		SourceAgentHostname: manifestRaw.SourceAgentHostname,
		BaseISOURL:          manifestRaw.BaseISOURL,
		BaseISOSHA256:       manifestRaw.BaseISOSHA256,
		SourceBundleURL:     manifestRaw.SourceBundleURL,
		SourceBundleSHA256:  manifestRaw.SourceBundleSHA256,
		SourceBundleProfile: manifestRaw.SourceBundleProfile,
		BroadExtrasURL:      manifestRaw.BroadExtrasURL,
		BroadExtrasSHA256:   manifestRaw.BroadExtrasSHA256,
		HasSourceBundle:     manifestRaw.HasSourceBundle,
		Warning:             manifestRaw.Warning,
	}

	jobID := fmt.Sprintf("job-%d", time.Now().UnixNano())
	a.storeJob(&buildJob{
		ID:       jobID,
		Status:   "queued",
		Progress: 0,
		Message:  "Queued",
		Logs:     []string{},
	})
	go a.runBuildJob(jobID, payload.DiskNumber, manifest)
	writeJSON(w, map[string]any{"status": "success", "job_id": jobID})
}

func (a *app) runBuildJob(jobID string, diskNumber int64, manifest *mediaBuildManifest) {
	a.updateJob(jobID, "running", "Downloading base ISO", 0, 0, 0)
	if manifest.Warning != "" {
		a.appendJobLog(jobID, manifest.Warning)
	}
	cacheDir := filepath.Join(os.Getenv("ProgramData"), "E3Backup", "recovery-cache")
	_ = os.MkdirAll(cacheDir, 0o755)
	isoPath := filepath.Join(cacheDir, fmt.Sprintf("recovery-base-%d.iso", time.Now().UnixNano()))
	if err := recoverymedia.DownloadWithProgress(manifest.BaseISOURL, isoPath, func(p int, speed, eta int64) {
		a.updateJob(jobID, "running", "Downloading base ISO", p, speed, eta)
	}); err != nil {
		a.updateJob(jobID, "failed", "Download failed: "+err.Error(), -1, 0, 0)
		return
	}

	if strings.TrimSpace(manifest.BaseISOSHA256) != "" {
		a.updateJob(jobID, "running", "Verifying base ISO", 100, 0, 0)
		if err := recoverymedia.VerifyFileChecksum(isoPath, manifest.BaseISOSHA256); err != nil {
			a.updateJob(jobID, "failed", "Base ISO checksum failed: "+err.Error(), -1, 0, 0)
			return
		}
	}

	a.updateJob(jobID, "running", "Writing ISO to USB", 0, 0, 0)
	if err := recoverymedia.WriteWinPEISOToDisk(isoPath, diskNumber, func(p int, speed, eta int64) {
		a.updateJob(jobID, "running", "Writing ISO to USB", p, speed, eta)
	}); err != nil {
		a.updateJob(jobID, "failed", "USB write failed: "+err.Error(), -1, 0, 0)
		return
	}

	a.updateJob(jobID, "running", "Adding drivers", 96, 0, 0)
	if err := a.addBundlesToUSB(jobID, diskNumber, manifest); err != nil {
		a.updateJob(jobID, "failed", "Driver stage failed: "+err.Error(), -1, 0, 0)
		return
	}
	a.updateJob(jobID, "completed", "Recovery media is ready", 100, 0, 0)
}

func (a *app) addBundlesToUSB(jobID string, diskNumber int64, manifest *mediaBuildManifest) error {
	usbRoot, err := recoverymedia.ResolveUSBDiskRoot(diskNumber)
	if err != nil {
		return err
	}
	sourceDir := filepath.Join(usbRoot, "e3", "drivers", "source")
	broadDir := filepath.Join(usbRoot, "e3", "drivers", "broad")
	_ = os.MkdirAll(sourceDir, 0o755)
	_ = os.MkdirAll(broadDir, 0o755)

	if strings.TrimSpace(manifest.SourceBundleURL) != "" {
		a.appendJobLog(jobID, "Downloading source bundle")
		tmp, err := os.CreateTemp("", "e3-source-*.zip")
		if err != nil {
			return err
		}
		tmp.Close()
		defer os.Remove(tmp.Name())
		if err := recoverymedia.DownloadWithProgress(manifest.SourceBundleURL, tmp.Name(), nil); err != nil {
			return err
		}
		if strings.TrimSpace(manifest.SourceBundleSHA256) != "" {
			if err := recoverymedia.VerifyFileChecksum(tmp.Name(), manifest.SourceBundleSHA256); err != nil {
				return err
			}
		}
		if err := recoverymedia.UnzipFile(tmp.Name(), sourceDir); err != nil {
			return fmt.Errorf("extract source bundle failed; archive path format is invalid or unsupported")
		}
		a.appendJobLog(jobID, "Source bundle applied")
	}

	shouldApplyBroad := strings.TrimSpace(manifest.BroadExtrasURL) != "" &&
		(strings.EqualFold(strings.TrimSpace(manifest.Mode), "dissimilar") || !manifest.HasSourceBundle)
	if shouldApplyBroad {
		isFallbackOnly := !manifest.HasSourceBundle && !strings.EqualFold(strings.TrimSpace(manifest.Mode), "dissimilar")
		a.appendJobLog(jobID, "Downloading broad extras pack")
		tmp, err := os.CreateTemp("", "e3-broad-*.zip")
		if err != nil {
			if isFallbackOnly {
				a.appendJobLog(jobID, "Broad extras fallback skipped: unable to create temp file")
				return nil
			}
			return err
		}
		tmp.Close()
		defer os.Remove(tmp.Name())
		if err := recoverymedia.DownloadWithProgress(manifest.BroadExtrasURL, tmp.Name(), nil); err != nil {
			if isFallbackOnly {
				a.appendJobLog(jobID, "Broad extras fallback skipped: download failed")
				return nil
			}
			return err
		}
		if strings.TrimSpace(manifest.BroadExtrasSHA256) != "" {
			if err := recoverymedia.VerifyFileChecksum(tmp.Name(), manifest.BroadExtrasSHA256); err != nil {
				if isFallbackOnly {
					a.appendJobLog(jobID, "Broad extras fallback skipped: checksum validation failed")
					return nil
				}
				return err
			}
		}
		if err := recoverymedia.UnzipFile(tmp.Name(), broadDir); err != nil {
			if isFallbackOnly {
				a.appendJobLog(jobID, "Broad extras fallback skipped: extraction failed (archive path format unsupported)")
				return nil
			}
			return fmt.Errorf("extract broad bundle failed; archive path format is invalid or unsupported")
		}
		a.appendJobLog(jobID, "Broad extras applied")
	}
	return nil
}

func (a *app) handleStatus(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.WriteHeader(http.StatusMethodNotAllowed)
		return
	}
	id := strings.TrimSpace(r.URL.Query().Get("job_id"))
	if id == "" {
		writeJSON(w, map[string]any{"status": "fail", "message": "job_id required"})
		return
	}
	job := a.getJob(id)
	if job == nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "job not found"})
		return
	}
	writeJSON(w, map[string]any{"status": "success", "job": job})
}

func (a *app) handleIndex(w http.ResponseWriter, r *http.Request) {
	const page = `<!doctype html>
<html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Recovery Media Creator</title>
<style>
body{font-family:Segoe UI,Arial,sans-serif;background:#0b1220;color:#e2e8f0;margin:0;padding:18px}
.wrap{max-width:920px;margin:0 auto}
.card{background:#0f172a;border:1px solid #273246;border-radius:12px;padding:14px;margin-bottom:12px}
.btn{border:1px solid #334155;background:#111827;color:#e2e8f0;border-radius:10px;padding:8px 12px;cursor:pointer}
.btn:disabled{opacity:.5}
.row{display:flex;gap:10px;align-items:center}
.muted{color:#94a3b8;font-size:12px}
textarea,input,select{width:100%;background:#111827;color:#e2e8f0;border:1px solid #334155;border-radius:8px;padding:8px}
.bar{width:100%;height:10px;border-radius:999px;background:#1e293b;overflow:hidden}
.bar>div{height:100%;width:0%;background:linear-gradient(90deg,#2563eb,#60a5fa)}
.logs{max-height:170px;overflow:auto;background:#09101e;border:1px solid #2d3748;border-radius:8px;padding:8px;white-space:pre-wrap;font-family:ui-monospace,SFMono-Regular,Consolas,monospace;font-size:11px}
</style></head><body><div class="wrap">
<div class="card"><h2 style="margin:0 0 8px 0">Portable Recovery Media Creator</h2><div class="muted">Paste a media build token from the client area.</div></div>
<div class="card">
<label class="muted">Media Build Token</label>
<textarea id="token" style="height:110px"></textarea>
<div class="row" style="margin-top:8px"><button id="exchangeBtn" class="btn" type="button">Validate Token</button><div id="manifestMsg" class="muted"></div></div>
</div>
<div class="card">
<div class="row"><label class="muted" style="min-width:90px">USB Disk</label><select id="diskSelect"></select><button id="refreshBtn" class="btn" type="button">Refresh</button></div>
<div class="row" style="margin-top:10px"><button id="buildBtn" class="btn" type="button">Create Media</button><div id="statusMsg" class="muted"></div></div>
<div style="margin-top:10px" class="bar"><div id="fill"></div></div>
<div id="pct" class="muted" style="margin-top:6px">0%</div>
<div id="logs" class="logs" style="margin-top:8px"></div>
</div>
<script>
let manifest=null, jobId='';
function esc(s){return String(s||'').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));}
async function loadDisks(){
  const sel=document.getElementById('diskSelect'); sel.innerHTML='';
  const r=await fetch('/api/disks'); const d=await r.json();
  if(d.status!=='success'){ document.getElementById('statusMsg').textContent=d.message||'Failed loading disks'; return; }
  (d.disks||[]).forEach(x=>{ const o=document.createElement('option'); o.value=x.number; o.textContent=(x.name||('Disk '+x.number))+' ('+Math.round((x.size_bytes||0)/1024/1024/1024)+' GB)'; sel.appendChild(o);});
}
async function exchangeToken(){
  const t=document.getElementById('token').value.trim(); if(!t) return;
  const r=await fetch('/api/exchange',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:t})});
  const d=await r.json(); if(d.status!=='success'){ document.getElementById('manifestMsg').textContent=d.message||'Invalid token'; manifest=null; return; }
  manifest=d.manifest||null;
  document.getElementById('manifestMsg').textContent = manifest ? ('OK: '+(manifest.source_agent_hostname||'source')+' / '+(manifest.mode||'')) : 'No manifest';
}
async function startBuild(){
  const t=document.getElementById('token').value.trim(); const disk=Number(document.getElementById('diskSelect').value||'-1');
  if(!t||disk<0){ document.getElementById('statusMsg').textContent='Token and disk are required'; return; }
  const r=await fetch('/api/build',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:t,disk_number:disk})});
  const d=await r.json(); if(d.status!=='success'){ document.getElementById('statusMsg').textContent=d.message||'Start failed'; return; }
  jobId=d.job_id; poll();
}
async function poll(){
  if(!jobId) return;
  const r=await fetch('/api/status?job_id='+encodeURIComponent(jobId)); const d=await r.json();
  if(d.status!=='success'){ document.getElementById('statusMsg').textContent=d.message||'Status failed'; return; }
  const j=d.job||{}; document.getElementById('fill').style.width=(Number(j.progress||0))+'%'; document.getElementById('pct').textContent=(Number(j.progress||0))+'%';
  document.getElementById('statusMsg').textContent=j.message||j.status||'';
  document.getElementById('logs').textContent=(j.logs||[]).join('\n');
  if(j.status==='completed'||j.status==='failed') return;
  setTimeout(poll, 1000);
}
document.getElementById('refreshBtn').addEventListener('click', loadDisks);
document.getElementById('exchangeBtn').addEventListener('click', exchangeToken);
document.getElementById('buildBtn').addEventListener('click', startBuild);
loadDisks();
</script></div></body></html>`
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	_, _ = w.Write([]byte(page))
}

func writeJSON(w http.ResponseWriter, payload map[string]any) {
	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(payload)
}

func formatInt(v int64) string {
	return strconv.FormatInt(v, 10)
}
