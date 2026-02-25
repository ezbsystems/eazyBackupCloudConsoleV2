//go:build windows

package main

import (
	"bufio"
	"bytes"
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path"
	"path/filepath"
	"strings"
	"sync"
	"syscall"
	"time"

	"github.com/getlantern/systray"
	"github.com/your-org/e3-backup-agent/internal/recoverymedia"
	"gopkg.in/yaml.v3"
)

type agentConfig struct {
	APIBaseURL string `yaml:"api_base_url"`

	DeviceID   string `yaml:"device_id,omitempty"`
	InstallID  string `yaml:"install_id,omitempty"`
	DeviceName string `yaml:"device_name,omitempty"`

	ClientID   string `yaml:"client_id,omitempty"`
	AgentUUID  string `yaml:"agent_uuid,omitempty"`
	AgentToken string `yaml:"agent_token,omitempty"`

	EnrollmentToken string `yaml:"enrollment_token,omitempty"`
	EnrollEmail     string `yaml:"enroll_email,omitempty"`
	EnrollPassword  string `yaml:"enroll_password,omitempty"`
}

const defaultAPIBaseURL = "https://accounts.eazybackup.ca/modules/addons/cloudstorage/api"

type jsonString string

func (s *jsonString) UnmarshalJSON(b []byte) error {
	if len(b) == 0 {
		*s = ""
		return nil
	}
	if b[0] == '"' {
		var v string
		if err := json.Unmarshal(b, &v); err != nil {
			return err
		}
		*s = jsonString(v)
		return nil
	}
	var num json.Number
	if err := json.Unmarshal(b, &num); err != nil {
		return err
	}
	*s = jsonString(num.String())
	return nil
}

func defaultConfigPath() string {
	pd := os.Getenv("ProgramData")
	if pd == "" {
		pd = `C:\ProgramData`
	}
	return filepath.Join(pd, "E3Backup", "agent.conf")
}

func loadConfig(path string) (*agentConfig, error) {
	b, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}
	var cfg agentConfig
	if err := yaml.Unmarshal(b, &cfg); err != nil {
		return nil, err
	}
	return &cfg, nil
}

func saveConfig(path string, cfg *agentConfig) error {
	b, err := yaml.Marshal(cfg)
	if err != nil {
		return err
	}
	_ = os.MkdirAll(filepath.Dir(path), 0o755)
	return os.WriteFile(path, b, 0o600)
}

func ensureIdentity(cfg *agentConfig) {
	if strings.TrimSpace(cfg.DeviceID) == "" {
		if id, err := newUUIDv4(); err == nil {
			cfg.DeviceID = id
		}
	}
	if strings.TrimSpace(cfg.InstallID) == "" {
		if id, err := newUUIDv4(); err == nil {
			cfg.InstallID = id
		}
	}
	if strings.TrimSpace(cfg.DeviceName) == "" {
		if hn, err := os.Hostname(); err == nil && hn != "" {
			cfg.DeviceName = hn
		}
	}
}

func main() {
	configPath := flag.String("config", defaultConfigPath(), "Path to agent.conf")
	flag.Parse()

	app := &trayApp{configPath: *configPath}
	systray.Run(app.onReady, app.onExit)
}

type trayApp struct {
	configPath string

	httpOnce sync.Once
	httpAddr string

	mu       sync.Mutex
	lastErr  string
	lastInfo string

	recoveryMu   sync.Mutex
	recoveryJobs map[string]*recoveryJob
}

type recoveryMedia struct {
	ID          string `json:"id"`
	Title       string `json:"title"`
	Description string `json:"description"`
	DownloadURL string `json:"download_url"`
	Version     string `json:"version"`
	ApproxSize  string `json:"approx_size"`
	LastUpdated string `json:"last_updated"`
	SHA256      string `json:"sha256"`
}

var recoveryMediaCatalog = map[string]recoveryMedia{
	"winpe": {
		ID:          "winpe",
		Title:       "Windows Recovery",
		Description: "Best for restoring Windows machines. Includes drivers + restore wizard.",
		DownloadURL: "https://accounts.eazybackup.ca/recovery_media/e3-recovery-winpe-prod.iso",
		Version:     "2026.02.11",
		ApproxSize:  "Approx 1.2 GB",
		LastUpdated: "2026-02-11",
		SHA256:      "",
	},
	"linux": {
		ID:          "linux",
		Title:       "Linux Recovery",
		Description: "Advanced environment for bare-metal restores and diagnostics.",
		DownloadURL: "https://downloads.eazybackup.ca/recovery/e3-recovery-linux.img",
		Version:     "1.8.2",
		ApproxSize:  "Approx 1.0 GB",
		LastUpdated: "2026-01-28",
		SHA256:      "",
	},
}

var recoveryPhaseOrder = []struct {
	Key   string
	Label string
}{
	{Key: "downloading", Label: "Downloading image"},
	{Key: "verifying", Label: "Verifying integrity"},
	{Key: "writing", Label: "Writing to USB"},
	{Key: "drivers", Label: "Adding drivers"},
	{Key: "finalizing", Label: "Finalizing / making bootable"},
}

func (a *trayApp) onReady() {
	// Ensure a config exists and stable identity is persisted so the UI can always display/copy device_id.
	cfg, err := loadConfig(a.configPath)
	if err != nil || cfg == nil {
		cfg = &agentConfig{}
	}
	if strings.TrimSpace(cfg.APIBaseURL) == "" {
		cfg.APIBaseURL = defaultAPIBaseURL
	}
	ensureIdentity(cfg)
	_ = saveConfig(a.configPath, cfg)

	// Check if device needs enrollment (not enrolled and no pending token-based enrollment).
	// If so, open the local enrollment UI after a short delay.
	enrolled := strings.TrimSpace(cfg.AgentUUID) != "" && strings.TrimSpace(cfg.AgentToken) != ""
	pendingTokenEnroll := strings.TrimSpace(cfg.EnrollmentToken) != ""
	logDebug("onReady: enrolled=%v, pendingTokenEnroll=%v, AgentUUID=%q, EnrollmentToken=%q",
		enrolled, pendingTokenEnroll, cfg.AgentUUID, cfg.EnrollmentToken)
	if !enrolled && !pendingTokenEnroll {
		// Not enrolled and no MSP/RMM token - open local enrollment UI after short delay.
		logDebug("onReady: will open local enrollment UI in 2 seconds")
		go func() {
			time.Sleep(2 * time.Second)
			a.openEnrollUI()
		}()
	}

	// Load icon from disk (installer will place assets next to the tray exe).
	// We wrap PNG bytes into an ICO container (Windows tray prefers ICO).
	if iconBytes := a.loadIconBytes(); len(iconBytes) > 0 {
		systray.SetIcon(iconBytes)
	}
	systray.SetTitle("E3 Backup Agent")
	systray.SetTooltip("E3 Backup Agent")

	mStatus := systray.AddMenuItem("Status: loading…", "Current status")
	mStatus.Disable()
	systray.AddSeparator()

	mEnroll := systray.AddMenuItem("Enroll / Sign in…", "Enroll this device")
	mOpenData := systray.AddMenuItem("Open data folder", "Open ProgramData\\E3Backup")
	mStart := systray.AddMenuItem("Start service", "Start the E3 Backup Agent service")
	mStop := systray.AddMenuItem("Stop service", "Stop the E3 Backup Agent service")
	mRestart := systray.AddMenuItem("Restart service", "Restart the E3 Backup Agent service")
	mRecovery := systray.AddMenuItem("Create recovery media…", "Create a bootable recovery USB")
	systray.AddSeparator()
	mDevice := systray.AddMenuItem("Device ID: loading…", "Device identity")
	mDevice.Disable()
	mCopyDevice := systray.AddMenuItem("Copy Device ID", "Copy device_id to clipboard")
	mQuit := systray.AddMenuItem("Quit", "Quit tray")

	go func() {
		t := time.NewTicker(3 * time.Second)
		defer t.Stop()
		for {
			select {
			case <-t.C:
				status, deviceLine := a.statusLines()
				mStatus.SetTitle(status)
				mDevice.SetTitle(deviceLine)
			}
		}
	}()

	go func() {
		for {
			select {
			case <-mEnroll.ClickedCh:
				a.openEnrollUI()
			case <-mOpenData.ClickedCh:
				a.openDataFolder()
			case <-mStart.ClickedCh:
				_ = a.sc("start")
			case <-mStop.ClickedCh:
				_ = a.sc("stop")
			case <-mRestart.ClickedCh:
				_ = a.sc("stop")
				time.Sleep(800 * time.Millisecond)
				_ = a.sc("start")
			case <-mRecovery.ClickedCh:
				a.openRecoveryUI()
			case <-mCopyDevice.ClickedCh:
				a.copyDeviceID()
			case <-mQuit.ClickedCh:
				systray.Quit()
				return
			}
		}
	}()
}

func (a *trayApp) onExit() {}

func (a *trayApp) statusLines() (string, string) {
	cfg, _ := loadConfig(a.configPath)
	enrolled := false
	agentUUID := ""
	deviceID := ""
	if cfg != nil {
		// If identity is missing (older installs), generate+persist it.
		if strings.TrimSpace(cfg.DeviceID) == "" || strings.TrimSpace(cfg.InstallID) == "" {
			ensureIdentity(cfg)
			_ = saveConfig(a.configPath, cfg)
		}
		enrolled = strings.TrimSpace(cfg.AgentUUID) != "" && strings.TrimSpace(cfg.AgentToken) != ""
		agentUUID = cfg.AgentUUID
		deviceID = cfg.DeviceID
	}
	svc := serviceStatus()
	if enrolled {
		return fmt.Sprintf("Status: %s | Enrolled (agent_uuid=%s)", svc, agentUUID),
			fmt.Sprintf("Device ID: %s", deviceIDOrDash(deviceID))
	}
	return fmt.Sprintf("Status: %s | Not enrolled", svc),
		fmt.Sprintf("Device ID: %s", deviceIDOrDash(deviceID))
}

func (a *trayApp) openDataFolder() {
	pd := os.Getenv("ProgramData")
	if pd == "" {
		pd = `C:\ProgramData`
	}
	_ = exec.Command("explorer.exe", filepath.Join(pd, "E3Backup")).Start()
}

func (a *trayApp) sc(action string) error {
	// Service name matches cmd/agent/main.go
	cmd := exec.Command("sc.exe", action, "e3-backup-agent")
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	out, err := cmd.CombinedOutput()
	if err != nil {
		a.setErr(fmt.Sprintf("service %s failed: %v (%s)", action, err, strings.TrimSpace(string(out))))
		return err
	}
	a.setInfo(fmt.Sprintf("service %s ok", action))
	return nil
}

func (a *trayApp) copyDeviceID() {
	cfg, err := loadConfig(a.configPath)
	if err != nil || cfg == nil {
		cfg = &agentConfig{}
	}
	ensureIdentity(cfg)
	_ = saveConfig(a.configPath, cfg)
	if strings.TrimSpace(cfg.DeviceID) == "" {
		a.setErr("device_id is empty")
		return
	}

	// Robust clipboard: write to temp file and pipe into clip.exe (avoids quoting issues).
	tmp := filepath.Join(os.TempDir(), "e3-device-id.txt")
	_ = os.WriteFile(tmp, []byte(cfg.DeviceID), 0o600)
	cmd := exec.Command("cmd.exe", "/c", "type", tmp, "|", "clip")
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	if err := cmd.Run(); err != nil {
		// Fallback to PowerShell Set-Clipboard
		ps := exec.Command("powershell.exe", "-NoProfile", "-Command", "Set-Clipboard -Value "+psQuote(cfg.DeviceID))
		ps.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
		if err2 := ps.Run(); err2 != nil {
			a.setErr("failed to copy to clipboard")
			return
		}
	}
	a.setInfo("device_id copied to clipboard")
}

func (a *trayApp) openEnrollUI() {
	logDebug("openEnrollUI: starting local HTTP server")
	a.httpOnce.Do(func() {
		a.startHTTP()
	})
	if a.httpAddr == "" {
		logDebug("openEnrollUI: failed to start local UI server")
		a.setErr("failed to start local UI")
		return
	}
	u := "http://" + a.httpAddr + "/enroll"
	logDebug("openEnrollUI: opening browser to %s", u)
	cmd := exec.Command("rundll32.exe", "url.dll,FileProtocolHandler", u)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	if err := cmd.Start(); err != nil {
		logDebug("openEnrollUI: failed to open browser: %v", err)
	} else {
		logDebug("openEnrollUI: browser launched successfully")
	}
}

func (a *trayApp) openRecoveryUI() {
	logDebug("openRecoveryUI: starting local HTTP server")
	a.httpOnce.Do(func() {
		a.startHTTP()
	})
	if a.httpAddr == "" {
		logDebug("openRecoveryUI: failed to start local UI server")
		a.setErr("failed to start local UI")
		return
	}
	u := "http://" + a.httpAddr + "/recovery"
	logDebug("openRecoveryUI: opening browser to %s", u)
	cmd := exec.Command("rundll32.exe", "url.dll,FileProtocolHandler", u)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	if err := cmd.Start(); err != nil {
		logDebug("openRecoveryUI: failed to open browser: %v", err)
	} else {
		logDebug("openRecoveryUI: browser launched successfully")
	}
}

// openWebEnrollmentPage opens the user's default browser to the web-based enrollment page.
// This is called automatically after installation if the device is not already enrolled
// and no MSP/RMM enrollment token is configured.
func (a *trayApp) openWebEnrollmentPage() {
	logDebug("openWebEnrollmentPage: starting")
	cfg, _ := loadConfig(a.configPath)
	if cfg == nil {
		cfg = &agentConfig{APIBaseURL: defaultAPIBaseURL}
	}
	if strings.TrimSpace(cfg.APIBaseURL) == "" {
		cfg.APIBaseURL = defaultAPIBaseURL
	}

	// Build the enrollment page URL from the API base URL.
	// API URL format: https://accounts.eazybackup.ca/modules/addons/cloudstorage/api
	// Enrollment page: https://accounts.eazybackup.ca/index.php?m=cloudstorage&page=e3backup&view=tokens
	enrollURL := buildEnrollmentPageURL(cfg.APIBaseURL)
	logDebug("openWebEnrollmentPage: opening URL %s", enrollURL)

	cmd := exec.Command("rundll32.exe", "url.dll,FileProtocolHandler", enrollURL)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	if err := cmd.Start(); err != nil {
		logDebug("openWebEnrollmentPage: failed to start browser: %v", err)
		a.setErr("failed to open enrollment page: " + err.Error())
	} else {
		logDebug("openWebEnrollmentPage: browser launched successfully")
	}
}

// buildEnrollmentPageURL converts the API base URL to the enrollment tokens page URL.
// Example: https://accounts.eazybackup.ca/modules/addons/cloudstorage/api
//
//	-> https://accounts.eazybackup.ca/index.php?m=cloudstorage&page=e3backup&view=tokens
func buildEnrollmentPageURL(apiBaseURL string) string {
	// Parse the API URL to extract the base domain
	u, err := url.Parse(strings.TrimRight(apiBaseURL, "/"))
	if err != nil {
		// Fallback to default
		return "https://accounts.eazybackup.ca/index.php?m=cloudstorage&page=e3backup&view=tokens"
	}

	// Build the enrollment page URL using the same scheme and host
	enrollURL := fmt.Sprintf("%s://%s/index.php?m=cloudstorage&page=e3backup&view=tokens", u.Scheme, u.Host)
	return enrollURL
}

func (a *trayApp) startHTTP() {
	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		a.setErr(err.Error())
		return
	}
	a.httpAddr = ln.Addr().String()

	mux := http.NewServeMux()
	mux.HandleFunc("/enroll", a.handleEnroll)
	mux.HandleFunc("/recovery", a.handleRecovery)
	mux.HandleFunc("/recovery/api/catalog", a.handleRecoveryCatalog)
	mux.HandleFunc("/recovery/api/disks", a.handleRecoveryDisks)
	mux.HandleFunc("/recovery/api/start", a.handleRecoveryStart)
	mux.HandleFunc("/recovery/api/status", a.handleRecoveryStatus)
	mux.HandleFunc("/recovery/api/eject", a.handleRecoveryEject)
	mux.HandleFunc("/recovery/api/boot_instructions", a.handleRecoveryBootInstructions)
	mux.HandleFunc("/assets/eazybackup-logo.svg", func(w http.ResponseWriter, r *http.Request) {
		b := []byte(eazybackupLogoSVG)
		w.Header().Set("Content-Type", "image/svg+xml")
		_, _ = w.Write(b)
	})
	mux.HandleFunc("/assets/img/logo.svg", func(w http.ResponseWriter, r *http.Request) {
		b := []byte(eazybackupLogoSVG)
		w.Header().Set("Content-Type", "image/svg+xml")
		_, _ = w.Write(b)
	})

	s := &http.Server{Handler: mux}
	go func() {
		_ = s.Serve(ln)
	}()
}

func (a *trayApp) handleEnroll(w http.ResponseWriter, r *http.Request) {
	switch r.Method {
	case http.MethodGet:
		cfg, _ := loadConfig(a.configPath)
		if cfg == nil {
			cfg = &agentConfig{APIBaseURL: ""}
		}
		if strings.TrimSpace(cfg.APIBaseURL) == "" {
			cfg.APIBaseURL = defaultAPIBaseURL
		}
		ensureIdentity(cfg)
		_ = saveConfig(a.configPath, cfg)
		renderEnrollPage(w, a.lastErrInfo())
		return
	case http.MethodPost:
		_ = r.ParseForm()
		email := strings.TrimSpace(r.Form.Get("email"))
		pass := r.Form.Get("password")
		if email == "" || pass == "" {
			w.WriteHeader(400)
			renderEnrollPage(w, "Missing required fields.")
			return
		}

		cfg, _ := loadConfig(a.configPath)
		if cfg == nil {
			cfg = &agentConfig{}
		}
		if strings.TrimSpace(cfg.APIBaseURL) == "" {
			cfg.APIBaseURL = defaultAPIBaseURL
		}
		ensureIdentity(cfg)
		hn, _ := os.Hostname()

		res, err := enrollWithCredentials(cfg.APIBaseURL, email, pass, hn, cfg.DeviceID, cfg.InstallID, cfg.DeviceName)
		if err != nil {
			a.setErr(err.Error())
			w.WriteHeader(401)
			renderEnrollPage(w, "Enrollment failed: "+err.Error())
			return
		}

		// Respect server-provided base URL if present (helps when moving between dev/prod).
		if strings.TrimSpace(res.APIBaseURL) != "" {
			cfg.APIBaseURL = strings.TrimSpace(res.APIBaseURL)
		}
		cfg.ClientID = string(res.ClientID)
		cfg.AgentUUID = string(res.AgentID)
		cfg.AgentToken = res.AgentToken
		cfg.EnrollEmail = ""
		cfg.EnrollPassword = ""
		cfg.EnrollmentToken = ""
		if err := saveConfig(a.configPath, cfg); err != nil {
			a.setErr("failed to save agent.conf: " + err.Error())
			w.WriteHeader(500)
			renderEnrollPage(w, "Enrollment succeeded but saving agent.conf failed. Please run the tray as Administrator and try again.")
			return
		}

		// Start service after successful enrollment.
		_ = a.sc("start")

		a.setInfo("enrolled successfully")
		renderSuccessPage(w, cfg.APIBaseURL)
		return
	default:
		w.WriteHeader(405)
	}
}

type enrollResp struct {
	Status     string     `json:"status"`
	Message    string     `json:"message"`
	AgentID    jsonString `json:"agent_id"`
	ClientID   jsonString `json:"client_id"`
	AgentToken string     `json:"agent_token"`
	APIBaseURL string     `json:"api_base_url"`
}

func enrollWithCredentials(apiBaseURL, email, password, hostname, deviceID, installID, deviceName string) (*enrollResp, error) {
	endpoint := strings.TrimRight(apiBaseURL, "/") + "/agent_login.php"
	form := url.Values{}
	form.Set("email", email)
	form.Set("password", password)
	form.Set("hostname", hostname)
	if deviceID != "" {
		form.Set("device_id", deviceID)
	}
	if installID != "" {
		form.Set("install_id", installID)
	}
	if deviceName != "" {
		form.Set("device_name", deviceName)
	}

	req, _ := http.NewRequestWithContext(context.Background(), http.MethodPost, endpoint, strings.NewReader(form.Encode()))
	req.Header.Set("Content-Type", "application/x-www-form-urlencoded")

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	body, _ := io.ReadAll(resp.Body)

	var out enrollResp
	if err := json.Unmarshal(body, &out); err != nil {
		return nil, fmt.Errorf("enrollment parse failed: %v", err)
	}
	if out.Status != "success" {
		if out.Message != "" {
			return nil, fmt.Errorf("%s", out.Message)
		}
		return nil, fmt.Errorf("enrollment failed")
	}
	if strings.TrimSpace(string(out.AgentID)) == "" || strings.TrimSpace(out.AgentToken) == "" {
		return nil, fmt.Errorf("enrollment response missing agent credentials")
	}
	return &out, nil
}

func (a *trayApp) setErr(msg string) {
	a.mu.Lock()
	defer a.mu.Unlock()
	a.lastErr = msg
	a.lastInfo = ""
}

func (a *trayApp) setInfo(msg string) {
	a.mu.Lock()
	defer a.mu.Unlock()
	a.lastInfo = msg
	a.lastErr = ""
}

func (a *trayApp) lastErrInfo() string {
	a.mu.Lock()
	defer a.mu.Unlock()
	if a.lastErr != "" {
		return a.lastErr
	}
	return a.lastInfo
}

func serviceStatus() string {
	cmd := exec.Command("sc.exe", "query", "e3-backup-agent")
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	out, err := cmd.CombinedOutput()
	if err != nil {
		return "service: not installed"
	}
	s := strings.ToUpper(string(out))
	if strings.Contains(s, "RUNNING") {
		return "service: running"
	}
	if strings.Contains(s, "STOPPED") {
		return "service: stopped"
	}
	return "service: unknown"
}

func shortID(s string) string {
	s = strings.TrimSpace(s)
	if s == "" {
		return "-"
	}
	if len(s) <= 8 {
		return s
	}
	return s[:8]
}

func psQuote(s string) string {
	// Minimal PowerShell single-quote escaping.
	return "'" + strings.ReplaceAll(s, "'", "''") + "'"
}

func deviceIDOrDash(s string) string {
	s = strings.TrimSpace(s)
	if s == "" {
		return "—"
	}
	return s
}

// logDebug writes debug messages to a log file in ProgramData\E3Backup\logs.
func logDebug(format string, args ...interface{}) {
	pd := os.Getenv("ProgramData")
	if pd == "" {
		pd = `C:\ProgramData`
	}
	logDir := filepath.Join(pd, "E3Backup", "logs")
	_ = os.MkdirAll(logDir, 0o755)
	logPath := filepath.Join(logDir, "tray.log")

	f, err := os.OpenFile(logPath, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o644)
	if err != nil {
		return
	}
	defer f.Close()

	msg := fmt.Sprintf(format, args...)
	timestamp := time.Now().Format("2006-01-02 15:04:05.000")
	fmt.Fprintf(f, "%s  %s\n", timestamp, msg)
}

func renderEnrollPage(w http.ResponseWriter, message string) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	_, _ = io.WriteString(w, `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">`)
	_, _ = io.WriteString(w, `<title>E3 Backup Agent - Enroll</title>`)
	_, _ = io.WriteString(w, `<style>
*{box-sizing:border-box;}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#0b1220;color:#e2e8f0;margin:0;padding:24px;}
.card{max-width:520px;margin:0 auto;background:#0f172a;border:1px solid #334155;border-radius:14px;padding:22px;}
.row{margin:12px 0;}
label{display:block;font-size:13px;color:#cbd5e1;margin-bottom:6px;}
input{width:100%;max-width:100%;padding:10px 12px;border-radius:10px;border:1px solid #334155;background:#0b1220;color:#e2e8f0;box-sizing:border-box;}
button{width:100%;padding:11px 12px;border-radius:10px;border:0;background:#f97316;color:#111827;font-weight:700;cursor:pointer;}
.msg{margin-top:10px;color:#fbbf24;font-size:13px;}
.logo{display:flex;justify-content:center;margin-bottom:14px;}
</style></head><body>`)
	_, _ = io.WriteString(w, `<div class="card">`)
	_, _ = io.WriteString(w, `<div class="logo"><img src="/assets/eazybackup-logo.svg" style="height:34px" alt="eazyBackup"/></div>`)
	_, _ = io.WriteString(w, `<h2 style="margin:0 0 6px 0;">Sign in to enroll this device</h2>`)
	_, _ = io.WriteString(w, `<p style="margin:0 0 14px 0;color:#94a3b8;font-size:13px;">This registers your computer as an agent and enables backups.</p>`)
	_, _ = io.WriteString(w, `<form method="post" action="/enroll">`)
	_, _ = io.WriteString(w, `<div class="row"><label>Email</label><input name="email" type="email" autocomplete="username" required/></div>`)
	_, _ = io.WriteString(w, `<div class="row"><label>Password</label><input name="password" type="password" autocomplete="current-password" required/></div>`)
	_, _ = io.WriteString(w, `<button type="submit">Enroll</button>`)
	if strings.TrimSpace(message) != "" {
		_, _ = io.WriteString(w, `<div class="msg">`+htmlEscape(message)+`</div>`)
	}
	_, _ = io.WriteString(w, `</form></div></body></html>`)
}

func renderSuccessPage(w http.ResponseWriter, apiBaseURL string) {
	w.Header().Set("Content-Type", "text/html; charset=utf-8")

	// Build the jobs page URL from the API base URL
	jobsURL := buildJobsPageURL(apiBaseURL)

	_, _ = io.WriteString(w, `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">`)
	_, _ = io.WriteString(w, `<title>E3 Backup Agent - Enrolled</title>`)
	_, _ = io.WriteString(w, `<style>
		body { font-family: system-ui, -apple-system, sans-serif; background: #0b1220; color: #e2e8f0; padding: 24px; margin: 0; }
		.card { max-width: 520px; margin: 0 auto; background: #0f172a; border: 1px solid #334155; border-radius: 14px; padding: 22px; }
		h2 { margin: 0 0 12px 0; color: #fff; }
		p { margin: 0 0 16px 0; color: #94a3b8; line-height: 1.5; }
		.next-step { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 16px; margin-top: 16px; }
		.next-step h3 { margin: 0 0 8px 0; font-size: 14px; color: #cbd5e1; }
		.next-step p { margin: 0 0 12px 0; font-size: 13px; }
		.btn { display: inline-block; padding: 10px 20px; background: #f97316; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
		.btn:hover { background: #ea580c; }
		.check { color: #22c55e; font-size: 48px; text-align: center; margin-bottom: 16px; }
	</style>`)
	_, _ = io.WriteString(w, `</head><body>`)
	_, _ = io.WriteString(w, `<div class="card">`)
	_, _ = io.WriteString(w, `<div class="check">✓</div>`)
	_, _ = io.WriteString(w, `<h2>Enrolled Successfully!</h2>`)
	_, _ = io.WriteString(w, `<p>Your device has been enrolled. The backup service will start in the background.</p>`)
	_, _ = io.WriteString(w, `<div class="next-step">`)
	_, _ = io.WriteString(w, `<h3>What's Next?</h3>`)
	_, _ = io.WriteString(w, `<p>Return to the e3 Cloud Backup Jobs page in your client area to create a new backup job for this device.</p>`)
	_, _ = io.WriteString(w, `<a href="`+htmlEscape(jobsURL)+`" class="btn" target="_blank">Create Backup Job →</a>`)
	_, _ = io.WriteString(w, `</div>`)
	_, _ = io.WriteString(w, `</div></body></html>`)
}

type recoveryJob struct {
	ID          string               `json:"id"`
	Status      string               `json:"status"`
	Phase       string               `json:"phase,omitempty"`
	Progress    int                  `json:"progress"`
	Message     string               `json:"message,omitempty"`
	SpeedBps    int64                `json:"speed_bps,omitempty"`
	ETASeconds  int64                `json:"eta_seconds,omitempty"`
	Phases      []recoveryPhaseState `json:"phases,omitempty"`
	Diagnostics []string             `json:"diagnostics,omitempty"`
}

type recoveryDisk struct {
	Number         int64  `json:"number"`
	Name           string `json:"name"`
	Model          string `json:"model,omitempty"`
	DriveLetters   string `json:"drive_letters,omitempty"`
	SizeBytes      int64  `json:"size_bytes"`
	PartitionStyle string `json:"partition_style,omitempty"`
}

type recoveryPhaseState struct {
	Key   string `json:"key"`
	Label string `json:"label"`
	State string `json:"state"` // pending | active | done | error
}

type recoveryBuildManifest struct {
	Mode                string `json:"mode"`
	SourceAgentID       int64  `json:"source_agent_id"`
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

func getRecoveryMedia(mediaType string) (recoveryMedia, bool) {
	m, ok := recoveryMediaCatalog[mediaType]
	return m, ok
}

func phaseStates(activePhase, terminalStatus string) []recoveryPhaseState {
	out := make([]recoveryPhaseState, 0, len(recoveryPhaseOrder))
	activeIdx := -1
	for i, p := range recoveryPhaseOrder {
		if p.Key == activePhase {
			activeIdx = i
			break
		}
	}
	for i, p := range recoveryPhaseOrder {
		state := "pending"
		if terminalStatus == "completed" {
			state = "done"
		} else if terminalStatus == "failed" {
			if activeIdx >= 0 {
				if i < activeIdx {
					state = "done"
				} else if i == activeIdx {
					state = "error"
				}
			}
		} else {
			if activeIdx >= 0 {
				if i < activeIdx {
					state = "done"
				} else if i == activeIdx {
					state = "active"
				}
			}
		}
		out = append(out, recoveryPhaseState{Key: p.Key, Label: p.Label, State: state})
	}
	return out
}

func (a *trayApp) fetchRecoveryBuildManifest(mode string, sourceAgentID int64) (*recoveryBuildManifest, error) {
	cfg, err := loadConfig(a.configPath)
	if err != nil || cfg == nil {
		return nil, fmt.Errorf("unable to load agent config")
	}
	if strings.TrimSpace(cfg.APIBaseURL) == "" || strings.TrimSpace(cfg.AgentUUID) == "" || strings.TrimSpace(cfg.AgentToken) == "" {
		return nil, fmt.Errorf("agent is not enrolled; using default media catalog")
	}

	endpoint := strings.TrimRight(cfg.APIBaseURL, "/") + "/agent_get_media_manifest.php"
	body := map[string]any{
		"mode": mode,
	}
	if sourceAgentID > 0 {
		body["source_agent_id"] = sourceAgentID
	}
	buf, _ := json.Marshal(body)

	req, err := http.NewRequest(http.MethodPost, endpoint, bytes.NewReader(buf))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Agent-UUID", cfg.AgentUUID)
	req.Header.Set("X-Agent-Token", cfg.AgentToken)

	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	var out struct {
		Status   string                `json:"status"`
		Message  string                `json:"message,omitempty"`
		Manifest recoveryBuildManifest `json:"manifest"`
	}
	if err := json.NewDecoder(resp.Body).Decode(&out); err != nil {
		return nil, err
	}
	if out.Status != "success" {
		if out.Message == "" {
			out.Message = "manifest lookup failed"
		}
		return nil, fmt.Errorf(out.Message)
	}
	return &out.Manifest, nil
}

func (a *trayApp) handleRecovery(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.WriteHeader(405)
		return
	}
	renderRecoveryPage(w)
}

func (a *trayApp) handleRecoveryCatalog(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.WriteHeader(405)
		return
	}
	// Ordered for stable UI rendering.
	items := []recoveryMedia{
		recoveryMediaCatalog["winpe"],
		recoveryMediaCatalog["linux"],
	}
	writeJSON(w, map[string]any{
		"status":             "success",
		"default_media_type": "winpe",
		"items":              items,
	})
}

func (a *trayApp) handleRecoveryDisks(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.WriteHeader(405)
		return
	}
	disks, err := listRemovableDisks()
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	writeJSON(w, map[string]any{"status": "success", "disks": disks})
}

func (a *trayApp) handleRecoveryStart(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(405)
		return
	}
	var payload struct {
		DiskNumber    int64  `json:"disk_number"`
		Checksum      string `json:"checksum"`
		MediaType     string `json:"media_type"`      // "winpe" (default) or "linux"
		BuildMode     string `json:"build_mode"`      // "fast" or "dissimilar"
		SourceAgentID int64  `json:"source_agent_id"` // optional for tray flow
	}
	dec := json.NewDecoder(r.Body)
	if err := dec.Decode(&payload); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	payload.MediaType = strings.ToLower(strings.TrimSpace(payload.MediaType))
	if payload.MediaType == "" {
		payload.MediaType = "winpe"
	}
	payload.BuildMode = strings.ToLower(strings.TrimSpace(payload.BuildMode))
	if payload.BuildMode == "" {
		payload.BuildMode = "fast"
	}
	if payload.DiskNumber < 0 {
		writeJSON(w, map[string]any{"status": "fail", "message": "disk_number required"})
		return
	}
	if payload.MediaType != "winpe" && payload.MediaType != "linux" {
		writeJSON(w, map[string]any{"status": "fail", "message": "media_type must be winpe or linux"})
		return
	}
	media, ok := getRecoveryMedia(payload.MediaType)
	if !ok {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid media_type"})
		return
	}
	imageURL := media.DownloadURL
	checksum := strings.TrimSpace(payload.Checksum)
	if checksum == "" {
		checksum = strings.TrimSpace(media.SHA256)
	}

	jobID, _ := newUUIDv4()
	if strings.TrimSpace(jobID) == "" {
		jobID = fmt.Sprintf("job-%d", time.Now().UnixNano())
	}
	job := &recoveryJob{
		ID:       jobID,
		Status:   "queued",
		Phase:    "downloading",
		Progress: 0,
		Phases:   phaseStates("downloading", ""),
	}
	a.storeRecoveryJob(job)
	a.appendRecoveryLog(job.ID, fmt.Sprintf("Queued %s recovery media build for disk %d", strings.ToUpper(payload.MediaType), payload.DiskNumber))
	go a.runRecoveryJob(job.ID, payload.DiskNumber, imageURL, checksum, payload.MediaType, payload.BuildMode, payload.SourceAgentID)

	writeJSON(w, map[string]any{"status": "success", "job_id": job.ID})
}

func (a *trayApp) handleRecoveryStatus(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.WriteHeader(405)
		return
	}
	jobID := r.URL.Query().Get("job_id")
	if jobID == "" {
		writeJSON(w, map[string]any{"status": "fail", "message": "job_id required"})
		return
	}
	job := a.getRecoveryJob(jobID)
	if job == nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "job not found"})
		return
	}
	writeJSON(w, map[string]any{"status": "success", "job": job})
}

func (a *trayApp) handleRecoveryEject(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		w.WriteHeader(405)
		return
	}
	var payload struct {
		DiskNumber int64 `json:"disk_number"`
	}
	if err := json.NewDecoder(r.Body).Decode(&payload); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	if payload.DiskNumber < 0 {
		writeJSON(w, map[string]any{"status": "fail", "message": "disk_number required"})
		return
	}
	msg, err := ejectUSBDisk(payload.DiskNumber)
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	writeJSON(w, map[string]any{"status": "success", "message": msg})
}

func (a *trayApp) handleRecoveryBootInstructions(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		w.WriteHeader(405)
		return
	}
	mediaType := strings.ToLower(strings.TrimSpace(r.URL.Query().Get("media_type")))
	if mediaType == "" {
		mediaType = "winpe"
	}
	if mediaType != "winpe" && mediaType != "linux" {
		writeJSON(w, map[string]any{"status": "fail", "message": "media_type must be winpe or linux"})
		return
	}

	if mediaType == "linux" {
		writeJSON(w, map[string]any{
			"status": "success",
			"title":  "Linux Recovery Boot Instructions",
			"steps": []string{
				"Insert the recovery USB into the target computer.",
				"Power on and open the boot menu (often F12, Esc, or Del).",
				"Select the USB drive as the boot device (UEFI mode preferred).",
				"Wait for the Linux recovery environment to load.",
				"Follow the restore wizard and enter your recovery token when prompted.",
			},
		})
		return
	}

	writeJSON(w, map[string]any{
		"status": "success",
		"title":  "Windows (WinPE) Boot Instructions",
		"steps": []string{
			"Insert the recovery USB into the target computer.",
			"Power on and open the boot menu (often F12, Esc, or Del).",
			"Choose the USB device and boot in UEFI mode when available.",
			"Wait for WinPE to load and start the eazyBackup restore wizard.",
			"Select your restore point and follow on-screen recovery steps.",
		},
	})
}

func (a *trayApp) storeRecoveryJob(job *recoveryJob) {
	a.recoveryMu.Lock()
	defer a.recoveryMu.Unlock()
	if a.recoveryJobs == nil {
		a.recoveryJobs = map[string]*recoveryJob{}
	}
	if len(job.Phases) == 0 {
		job.Phases = phaseStates(job.Phase, job.Status)
	}
	a.recoveryJobs[job.ID] = job
}

func (a *trayApp) appendRecoveryLog(id, message string) {
	a.recoveryMu.Lock()
	defer a.recoveryMu.Unlock()
	if a.recoveryJobs == nil {
		return
	}
	job, ok := a.recoveryJobs[id]
	if !ok {
		return
	}
	line := strings.TrimSpace(message)
	if line == "" {
		return
	}
	ts := time.Now().Format("15:04:05")
	entry := ts + "  " + line
	if len(job.Diagnostics) > 0 && job.Diagnostics[len(job.Diagnostics)-1] == entry {
		return
	}
	job.Diagnostics = append(job.Diagnostics, entry)
	const maxDiagLines = 200
	if len(job.Diagnostics) > maxDiagLines {
		job.Diagnostics = append([]string(nil), job.Diagnostics[len(job.Diagnostics)-maxDiagLines:]...)
	}
}

func (a *trayApp) updateRecoveryJob(id, status, phase, message string, progress int, speedBps, etaSeconds int64) {
	a.recoveryMu.Lock()
	defer a.recoveryMu.Unlock()
	if a.recoveryJobs == nil {
		return
	}
	job, ok := a.recoveryJobs[id]
	if !ok {
		return
	}
	if strings.TrimSpace(status) != "" {
		job.Status = status
	}
	if strings.TrimSpace(phase) != "" {
		job.Phase = phase
	}
	if strings.TrimSpace(message) != "" {
		job.Message = message
	}
	if progress >= 0 {
		job.Progress = progress
	}
	if speedBps >= 0 {
		job.SpeedBps = speedBps
	}
	if etaSeconds >= 0 {
		job.ETASeconds = etaSeconds
	}
	job.Phases = phaseStates(job.Phase, job.Status)

	trimmed := strings.TrimSpace(message)
	if trimmed != "" {
		ts := time.Now().Format("15:04:05")
		entry := ts + "  " + trimmed
		if len(job.Diagnostics) == 0 || job.Diagnostics[len(job.Diagnostics)-1] != entry {
			job.Diagnostics = append(job.Diagnostics, entry)
			const maxDiagLines = 200
			if len(job.Diagnostics) > maxDiagLines {
				job.Diagnostics = append([]string(nil), job.Diagnostics[len(job.Diagnostics)-maxDiagLines:]...)
			}
		}
	}
}

func (a *trayApp) getRecoveryJob(id string) *recoveryJob {
	a.recoveryMu.Lock()
	defer a.recoveryMu.Unlock()
	if a.recoveryJobs == nil {
		return nil
	}
	job, ok := a.recoveryJobs[id]
	if !ok || job == nil {
		return nil
	}
	cp := *job
	cp.Phases = append([]recoveryPhaseState(nil), job.Phases...)
	cp.Diagnostics = append([]string(nil), job.Diagnostics...)
	return &cp
}

func (a *trayApp) runRecoveryJob(jobID string, diskNumber int64, imageURL, checksum, mediaType, buildMode string, sourceAgentID int64) {
	a.updateRecoveryJob(jobID, "downloading", "downloading", "Downloading recovery image", 0, 0, 0)
	buildMode = strings.ToLower(strings.TrimSpace(buildMode))
	if buildMode == "" {
		buildMode = "fast"
	}

	var manifest *recoveryBuildManifest
	if mediaType == "winpe" {
		mf, err := a.fetchRecoveryBuildManifest(buildMode, sourceAgentID)
		if err != nil {
			a.appendRecoveryLog(jobID, "Media manifest lookup unavailable: "+err.Error())
		} else {
			manifest = mf
			if strings.TrimSpace(manifest.BaseISOURL) != "" {
				imageURL = strings.TrimSpace(manifest.BaseISOURL)
			}
			if strings.TrimSpace(manifest.BaseISOSHA256) != "" {
				checksum = strings.TrimSpace(manifest.BaseISOSHA256)
			}
			if strings.TrimSpace(manifest.Warning) != "" {
				a.appendRecoveryLog(jobID, manifest.Warning)
			}
		}
	}

	cacheDir := filepath.Join(os.Getenv("ProgramData"), "E3Backup", "recovery-cache")
	_ = os.MkdirAll(cacheDir, 0o755)
	// Use URL path basename (avoids "?v=..." breaking filenames).
	fileName := ""
	if u, err := url.Parse(strings.TrimSpace(imageURL)); err == nil && u != nil {
		fileName = path.Base(u.Path)
	}
	if fileName == "" || fileName == "." || fileName == "/" {
		fileName = filepath.Base(imageURL)
	}
	fileName = strings.TrimSpace(fileName)
	if fileName == "" {
		fileName = fmt.Sprintf("recovery-%d", time.Now().UnixNano())
	}
	imagePath := filepath.Join(cacheDir, fileName)
	releaseMountedDiskImage(imagePath)
	if err := os.Remove(imagePath); err != nil && !os.IsNotExist(err) {
		// If a stale lock still exists, fall back to a unique cache filename.
		imagePath = filepath.Join(cacheDir, fmt.Sprintf("%d-%s", time.Now().UnixNano(), fileName))
	}

	if err := downloadWithProgress(imageURL, imagePath, func(p int, speedBps, etaSeconds int64) {
		a.updateRecoveryJob(jobID, "downloading", "downloading", "Downloading recovery image", p, speedBps, etaSeconds)
	}); err != nil {
		a.updateRecoveryJob(jobID, "failed", "downloading", "Download failed: "+err.Error(), -1, 0, 0)
		return
	}

	a.updateRecoveryJob(jobID, "verifying", "verifying", "Verifying integrity", 100, 0, 0)
	if checksum != "" {
		if err := verifyFileChecksum(imagePath, checksum); err != nil {
			a.updateRecoveryJob(jobID, "failed", "verifying", "Integrity check failed: "+err.Error(), -1, 0, 0)
			return
		}
	} else {
		a.appendRecoveryLog(jobID, "No checksum provided; skipping integrity verification")
	}

	mediaType = strings.ToLower(strings.TrimSpace(mediaType))
	if mediaType == "" {
		// Default to WinPE going forward.
		mediaType = "winpe"
	}
	// Allow auto-detection as a fallback (eg. older UI).
	if mediaType != "winpe" && mediaType != "linux" {
		if strings.HasSuffix(strings.ToLower(imageURL), ".iso") {
			mediaType = "winpe"
		} else {
			mediaType = "linux"
		}
	}

	if mediaType == "winpe" {
		a.updateRecoveryJob(jobID, "writing", "writing", "Writing WinPE media to USB", 0, 0, 0)
		if err := writeWinPEISOToDisk(imagePath, diskNumber, func(p int, speedBps, etaSeconds int64) {
			a.updateRecoveryJob(jobID, "writing", "writing", "Writing WinPE media to USB", p, speedBps, etaSeconds)
		}); err != nil {
			a.updateRecoveryJob(jobID, "failed", "writing", "Failed writing WinPE media: "+err.Error(), -1, 0, 0)
			return
		}
		if manifest != nil {
			a.updateRecoveryJob(jobID, "drivers", "drivers", "Adding drivers", 96, 0, 0)
			if err := applyDriverBundlesToUSB(manifest, diskNumber, func(msg string) {
				a.appendRecoveryLog(jobID, msg)
			}); err != nil {
				a.updateRecoveryJob(jobID, "failed", "drivers", "Failed adding drivers: "+err.Error(), -1, 0, 0)
				return
			}
		}
	} else {
		a.updateRecoveryJob(jobID, "writing", "writing", "Writing Linux image to USB", 0, 0, 0)
		if err := writeImageToDisk(imagePath, diskNumber, func(p int, speedBps, etaSeconds int64) {
			a.updateRecoveryJob(jobID, "writing", "writing", "Writing Linux image to USB", p, speedBps, etaSeconds)
		}); err != nil {
			a.updateRecoveryJob(jobID, "failed", "writing", "Failed writing Linux media: "+err.Error(), -1, 0, 0)
			return
		}
	}

	a.updateRecoveryJob(jobID, "finalizing", "finalizing", "Finalizing / making bootable", 98, 0, 0)
	time.Sleep(400 * time.Millisecond)
	a.updateRecoveryJob(jobID, "completed", "finalizing", "Recovery media is ready.", 100, 0, 0)
}

func writeWinPEISOToDisk(isoPath string, diskNumber int64, update func(int, int64, int64)) error {
	return recoverymedia.WriteWinPEISOToDisk(isoPath, diskNumber, update)
}

func ejectUSBDisk(diskNumber int64) (string, error) {
	return recoverymedia.EjectUSBDisk(diskNumber)
}

func listRemovableDisks() ([]recoveryDisk, error) {
	disks, err := recoverymedia.ListRemovableDisks()
	if err != nil {
		return nil, err
	}
	out := make([]recoveryDisk, 0, len(disks))
	for _, d := range disks {
		out = append(out, recoveryDisk{
			Number:         d.Number,
			Name:           d.Name,
			Model:          d.Model,
			DriveLetters:   d.DriveLetters,
			SizeBytes:      d.SizeBytes,
			PartitionStyle: d.PartitionStyle,
		})
	}
	return out, nil
}

func downloadWithProgress(sourceURL, dest string, update func(int, int64, int64)) error {
	return recoverymedia.DownloadWithProgress(sourceURL, dest, update)
}

func verifyFileChecksum(path, expected string) error {
	return recoverymedia.VerifyFileChecksum(path, expected)
}

func writeImageToDisk(imagePath string, diskNumber int64, update func(int, int64, int64)) error {
	return recoverymedia.WriteImageToDisk(imagePath, diskNumber, update)
}

func resolveUSBDiskRoot(diskNumber int64) (string, error) {
	return recoverymedia.ResolveUSBDiskRoot(diskNumber)
}

func applyDriverBundlesToUSB(manifest *recoveryBuildManifest, diskNumber int64, logf func(string)) error {
	if manifest == nil {
		return nil
	}
	usbRoot, err := resolveUSBDiskRoot(diskNumber)
	if err != nil {
		return err
	}
	sourceDir := filepath.Join(usbRoot, "e3", "drivers", "source")
	broadDir := filepath.Join(usbRoot, "e3", "drivers", "broad")
	if err := os.MkdirAll(sourceDir, 0o755); err != nil {
		return fmt.Errorf("failed preparing source drivers folder on USB")
	}
	if err := os.MkdirAll(broadDir, 0o755); err != nil {
		return fmt.Errorf("failed preparing broad drivers folder on USB")
	}

	if strings.TrimSpace(manifest.SourceBundleURL) != "" {
		if logf != nil {
			logf("Downloading source driver bundle")
		}
		tmp, err := os.CreateTemp("", "e3-source-drivers-*.zip")
		if err != nil {
			return err
		}
		tmp.Close()
		defer os.Remove(tmp.Name())
		if err := downloadWithProgress(manifest.SourceBundleURL, tmp.Name(), func(int, int64, int64) {}); err != nil {
			return err
		}
		if strings.TrimSpace(manifest.SourceBundleSHA256) != "" {
			if err := verifyFileChecksum(tmp.Name(), manifest.SourceBundleSHA256); err != nil {
				return fmt.Errorf("source bundle integrity check failed: %w", err)
			}
		}
		if err := unzipFile(tmp.Name(), sourceDir); err != nil {
			return fmt.Errorf("extract source bundle failed; archive path format is invalid or unsupported")
		}
		if logf != nil {
			logf("Source drivers added to USB")
		}
	}

	// Broad extras are used for dissimilar mode or as a fallback when no source bundle is available.
	shouldApplyBroad := strings.TrimSpace(manifest.BroadExtrasURL) != "" &&
		(strings.EqualFold(strings.TrimSpace(manifest.Mode), "dissimilar") || !manifest.HasSourceBundle)

	if shouldApplyBroad {
		isFallbackOnly := !manifest.HasSourceBundle && !strings.EqualFold(strings.TrimSpace(manifest.Mode), "dissimilar")
		if logf != nil {
			if isFallbackOnly {
				logf("Downloading broad extras pack (fallback: no source bundle available)")
			} else {
				logf("Downloading broad extras pack")
			}
		}
		tmp, err := os.CreateTemp("", "e3-broad-drivers-*.zip")
		if err != nil {
			if isFallbackOnly {
				if logf != nil {
					logf("Broad extras fallback skipped: unable to create temp file")
				}
				return nil
			}
			return err
		}
		tmp.Close()
		defer os.Remove(tmp.Name())
		if err := downloadWithProgress(manifest.BroadExtrasURL, tmp.Name(), func(int, int64, int64) {}); err != nil {
			if isFallbackOnly {
				if logf != nil {
					logf("Broad extras fallback skipped: download failed")
				}
				return nil
			}
			return err
		}
		if strings.TrimSpace(manifest.BroadExtrasSHA256) != "" {
			if err := verifyFileChecksum(tmp.Name(), manifest.BroadExtrasSHA256); err != nil {
				if isFallbackOnly {
					if logf != nil {
						logf("Broad extras fallback skipped: checksum validation failed")
					}
					return nil
				}
				return fmt.Errorf("broad bundle integrity check failed: %w", err)
			}
		}
		if err := unzipFile(tmp.Name(), broadDir); err != nil {
			if isFallbackOnly {
				if logf != nil {
					logf("Broad extras fallback skipped: extraction failed (archive path format unsupported)")
				}
				return nil
			}
			return fmt.Errorf("extract broad bundle failed; archive path format is invalid or unsupported")
		}
		if logf != nil {
			logf("Broad extras added to USB")
		}
	}

	return nil
}

func unzipFile(zipPath, destDir string) error {
	return recoverymedia.UnzipFile(zipPath, destDir)
}

func writeJSON(w http.ResponseWriter, payload map[string]any) {
	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(payload)
}

func renderRecoveryPage(w http.ResponseWriter) {
	const page = `<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Recovery Media</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; padding: 20px; font-family: Inter, Segoe UI, Arial, sans-serif; background: #0b1220; color: #e2e8f0; }
    .shell { max-width: 920px; margin: 0 auto; }
    .header { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .header img { height: 28px; }
    .title { margin: 0; font-size: 24px; font-weight: 700; }
    .subtitle { margin: 4px 0 0 0; color: #94a3b8; font-size: 13px; }

    .stepper { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 14px; }
    .step { border: 1px solid #273246; border-radius: 10px; padding: 10px; font-size: 12px; color: #93a3bb; background: #0f172a; display: flex; align-items: center; gap: 6px; }
    .step-icon { font-size: 10px; opacity: 0.4; }

    .card { background: #0f172a; border: 1px solid #273246; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
    .card h3 { margin: 0 0 10px 0; font-size: 14px; }
    .muted { color: #94a3b8; font-size: 12px; }
    .row { display: flex; gap: 10px; align-items: center; }
    .spacer { flex: 1; }
    .btn { border: 1px solid #334155; background: #111827; color: #e2e8f0; border-radius: 10px; padding: 9px 12px; cursor: pointer; }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn:hover { background: #1a2436; border-color: #475569; }
    .tiny { font-size: 12px; padding: 6px 8px; }

    .os-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .os-card { border: 1px solid #3b4d6b; border-radius: 12px; padding: 12px; background: #0b1220; cursor: pointer; transition: background .15s ease, box-shadow .15s ease; }
    .os-card:hover { background: #111b2a; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
    .os-card.selected { background: #131d2e; border-color: #4ade80; box-shadow: 0 0 0 1px #4ade80; }
    .os-top { display: flex; gap: 9px; align-items: center; margin-bottom: 8px; }
    .os-title { font-weight: 700; font-size: 14px; }
    .link { color: #5cc8ff; text-decoration: none; font-size: 12px; cursor: pointer; }
    .link:hover { text-decoration: underline; }

    .drive-list { display: grid; gap: 8px; }
    .drive-card { border: 1px solid #3b4d6b; border-radius: 10px; padding: 10px; background: #0b1220; cursor: pointer; display: grid; grid-template-columns: auto 1fr auto; gap: 10px; align-items: center; transition: background .15s ease, box-shadow .15s ease; }
    .drive-card:hover { background: #111b2a; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
    .drive-card.selected { background: #131d2e; border-color: #4ade80; box-shadow: 0 0 0 1px #4ade80; }
    .drive-name { font-weight: 600; font-size: 13px; }
    .drive-meta { color: #9aa7bb; font-size: 12px; margin-top: 2px; }
    .cap-wrap { width: 120px; text-align: right; }
    .cap-bar { height: 8px; border-radius: 999px; background: #1e293b; overflow: hidden; margin-bottom: 4px; }
    .cap-fill { height: 100%; background: linear-gradient(90deg, #475569, #64748b); }
    .cap-text { font-size: 11px; color: #a5b2c7; }

    .warning { border: 1px solid #2d3748; background: #0f172a; color: #fff; border-radius: 10px; padding: 10px; margin-top: 10px; font-size: 13px; }
    .confirm { margin-top: 10px; display: flex; gap: 8px; align-items: center; font-size: 13px; color: #cbd5e1; }

    .cta { position: relative; overflow: hidden; border: 1px solid #fe5000; background: #fe5000; color: #fff; border-radius: 999px; padding: 11px 18px; font-weight: 700; cursor: pointer; }
    .cta .label { position: relative; z-index: 2; }
    .cta .absolute { position: absolute; }
    .cta .inset-0 { inset: 0; }
    .cta .rounded-full { border-radius: 999px; }
    .cta .opacity-80 { opacity: 0.8; }
    .cta .blur-\[2px\] { filter: blur(2px); }
    .cta:hover .group-hover\:blur-\[6px\] { filter: blur(6px); }
    .cta:hover .group-hover\:opacity-100 { opacity: 1; }
    .cta:disabled { opacity: 0.55; cursor: not-allowed; }

    .progress { margin-top: 10px; }
    .phase-list { display: grid; gap: 6px; margin: 0 0 10px 0; padding: 0; list-style: none; }
    .phase-item { border: 1px solid #253248; border-radius: 8px; padding: 7px 9px; font-size: 12px; color: #9fb0c8; display: flex; align-items: center; gap: 8px; }
    .phase-item.done { border-color: #334155; color: #94a3b8; }
    .phase-item.active { background: #111b2e; border-color: #334155; color: #e2e8f0; }
    .phase-item.failed { border-color: #334155; color: #fecaca; }
    .phase-marker { width: 14px; height: 14px; flex: 0 0 14px; display: inline-flex; align-items: center; justify-content: center; color: #fff; opacity: 0.5; }
    .phase-label { flex: 1; min-width: 0; }
    .phase-item.done .phase-marker { opacity: 1; font-size: 12px; line-height: 1; }
    .phase-item.pending .phase-marker { border: 1px solid #475569; border-radius: 999px; }
    .phase-item.active .phase-marker { border: 2px solid #1e40af; border-top-color: #5cc8ff; border-right-color: #5cc8ff; border-radius: 999px; opacity: 1; animation: phase-spin .8s linear infinite; }
    .phase-item.failed .phase-marker { border: 1px solid #ef4444; border-radius: 999px; opacity: 1; }
    @keyframes phase-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .bar { width: 100%; height: 10px; border-radius: 999px; background: #1e293b; overflow: hidden; margin: 6px 0; }
    .bar-fill { height: 100%; width: 0%; background: linear-gradient(90deg, #2563eb, #60a5fa); transition: width 0.2s ease; }
    .small { color: #9db0ca; font-size: 12px; }
    .status { margin-top: 6px; font-size: 13px; color: #dbe7f6; }
    .check { font-size: 42px; color: #22c55e; line-height: 1; margin-bottom: 8px; }
    .error { color: #fecaca; background: #2b1212; border: 1px solid #7f1d1d; border-radius: 10px; padding: 10px; font-size: 13px; margin-top: 8px; }
    .details { display: none; margin-top: 10px; border: 1px solid #2d3748; border-radius: 8px; background: #09101e; padding: 10px; max-height: 180px; overflow: auto; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11px; white-space: pre-wrap; color: #d4deeb; }

    .panel { position: fixed; top: 0; right: 0; width: 360px; max-width: 95vw; height: 100vh; background: #0e1628; border-left: 1px solid #2f3b53; transform: translateX(100%); transition: transform .2s ease; z-index: 30; padding: 14px; overflow: auto; }
    .panel.open { transform: translateX(0); }
    .panel h4 { margin: 0 0 8px 0; }
    .panel .kv { margin: 8px 0; font-size: 12px; color: #cbd5e1; }
    .panel .kv b { color: #fff; min-width: 112px; display: inline-block; }
    .panel-backdrop { position: fixed; inset: 0; background: rgba(2, 6, 23, 0.5); display: none; z-index: 20; }
    .panel-backdrop.open { display: block; }

    @media (max-width: 780px) {
      .os-grid { grid-template-columns: 1fr; }
      .stepper { grid-template-columns: 1fr; }
      .cap-wrap { width: 84px; }
    }
  </style>
</head>
<body>
  <div class="shell">
    <div class="header">
      <img src="/assets/img/logo.svg" alt="eazyBackup" onerror="this.src='/assets/eazybackup-logo.svg'"/>
      <div>
        <h1 class="title">Recovery Media</h1>
        <p class="subtitle">Create a bootable USB to restore your computer when Windows won't start.</p>
      </div>
    </div>

    <div class="stepper">
      <div id="step1" class="step"><span id="step1Icon" class="step-icon">&#9675;</span> 1. Choose Environment</div>
      <div id="step2" class="step"><span id="step2Icon" class="step-icon">&#9675;</span> 2. Select USB Drive</div>
      <div id="step3" class="step"><span id="step3Icon" class="step-icon">&#9675;</span> 3. Create Media</div>
    </div>

    <div class="card">
      <h3>Choose Environment</h3>
      <div id="osCards" class="os-grid"></div>
    </div>

    <div class="card">
      <div class="row">
        <h3 style="margin:0;">Select USB Drive</h3>
        <div class="spacer"></div>
        <button id="refreshBtn" class="btn tiny" type="button" title="Refresh drives">Refresh drives</button>
      </div>
      <div id="driveList" class="drive-list"></div>
    </div>

    <div class="card">
      <h3>Create Media</h3>
      <div class="row" style="margin-bottom: 8px;">
        <label class="muted" for="buildModeSelect" style="min-width:120px;">Build mode</label>
        <select id="buildModeSelect" class="btn" style="padding:8px 10px;">
          <option value="fast">Fast / Same Hardware</option>
          <option value="dissimilar">Dissimilar Hardware</option>
        </select>
      </div>
      <div class="warning">Warning: Creating recovery media will erase everything on this USB drive.</div>
      <label class="confirm">
        <input id="confirmErase" type="checkbox"/>
        <span>I understand this will erase the drive</span>
      </label>
      <div class="row" style="margin-top: 12px;">
        <button id="createBtn" class="cta group" type="button" disabled>
          <span class="absolute inset-0 rounded-full bg-[conic-gradient(from_180deg_at_50%_50%,_rgba(254,80,0,0.15),_rgba(254,80,0,0.6),_rgba(254,80,0,0.15))] opacity-80 blur-[2px] transition duration-500 group-hover:blur-[6px] group-hover:opacity-100"></span>
          <span class="label">Create Bootable USB</span>
        </button>
        <button id="downloadBtn" class="btn" type="button">Download ISO</button>
      </div>
      <div id="validationMsg" class="muted" style="margin-top:8px;"></div>
    </div>

    <div id="progressCard" class="card progress" style="display:none;">
      <h3 id="progressTitle">Building Recovery Media</h3>
      <ul id="phaseList" class="phase-list"></ul>
      <div class="bar"><div id="progressFill" class="bar-fill"></div></div>
      <div id="progressPercent" class="small">0%</div>
      <div id="speedEta" class="small"></div>
      <div id="statusText" class="status"></div>
      <div class="row" style="margin-top:10px;">
        <button id="toggleDetailsBtn" class="btn tiny" type="button">Show details</button>
        <div class="spacer"></div>
      </div>
      <div id="detailsLog" class="details"></div>
      <div id="successActions" style="display:none; margin-top:12px;">
        <div class="check">✓</div>
        <div style="margin-bottom:10px; font-weight:700;">Recovery media is ready.</div>
        <div class="row">
          <button id="ejectBtn" class="btn" type="button">Safely eject USB</button>
          <button id="bootBtn" class="btn" type="button">View boot instructions</button>
        </div>
      </div>
      <div id="errorBox" class="error" style="display:none;"></div>
      <div id="errorActions" style="display:none; margin-top:10px;">
        <button id="copyDiagBtn" class="btn" type="button">Copy diagnostics</button>
      </div>
    </div>
  </div>

  <div id="panelBackdrop" class="panel-backdrop"></div>
  <aside id="sidePanel" class="panel">
    <div class="row">
      <h4 id="panelTitle" style="margin:0;">Details</h4>
      <div class="spacer"></div>
      <button id="panelClose" class="btn tiny" type="button">Close</button>
    </div>
    <div id="panelBody" style="margin-top: 10px;"></div>
  </aside>
<script>
const state = {
  catalog: {},
  mediaType: 'winpe',
  buildMode: 'fast',
  selectedDisk: null,
  selectedDiskName: '',
  disks: [],
  jobId: '',
  polling: false,
  detailsOpen: false,
  lastDiagnostics: [],
  lastPhaseSignature: ''
};

function iconWindows() {
  return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="2" y="3" width="9" height="8" rx="1" fill="#5cc8ff"></rect><rect x="13" y="3" width="9" height="8" rx="1" fill="#5cc8ff"></rect><rect x="2" y="13" width="9" height="8" rx="1" fill="#5cc8ff"></rect><rect x="13" y="13" width="9" height="8" rx="1" fill="#5cc8ff"></rect></svg>';
}
function iconLinux() {
  return '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 640 640" fill="#dbeafe" aria-hidden="true"><path d="M316.9 187.3C317.9 187.8 318.7 189 319.9 189C321 189 322.7 188.6 322.8 187.5C323 186.1 320.9 185.2 319.6 184.6C317.9 183.9 315.7 183.6 314.1 184.5C313.7 184.7 313.3 185.2 313.5 185.6C313.8 186.9 315.8 186.7 316.9 187.3zM295 189C296.2 189 297 187.8 298 187.3C299.1 186.7 301.1 186.9 301.5 185.7C301.7 185.3 301.3 184.8 300.9 184.6C299.3 183.7 297.1 184 295.4 184.7C294.1 185.3 292 186.2 292.2 187.6C292.3 188.6 294 189.1 295 189zM516 467.8C512.4 463.8 510.7 456.2 508.8 448.1C507 440 504.9 431.3 498.3 425.7C497 424.6 495.7 423.6 494.3 422.8C493 422 491.6 421.3 490.2 420.8C499.4 393.5 495.8 366.3 486.5 341.7C475.1 311.6 455.2 285.3 440 267.3C422.9 245.8 406.3 225.4 406.6 195.3C407.1 149.4 411.7 64.1 330.8 64C228.4 63.8 254 167.4 252.9 199.2C251.2 222.6 246.5 241 230.4 263.9C211.5 286.4 184.9 322.7 172.3 360.6C166.3 378.5 163.5 396.7 166.1 413.9C159.6 419.7 154.7 428.6 149.5 434.1C145.3 438.4 139.2 440 132.5 442.4C125.8 444.8 118.5 448.4 114 456.9C111.9 460.8 111.2 465 111.2 469.3C111.2 473.2 111.8 477.2 112.4 481.1C113.6 489.2 114.9 496.8 113.2 501.9C108 516.3 107.3 526.3 111 533.6C114.8 540.9 122.4 544.1 131.1 545.9C148.4 549.5 171.9 548.6 190.4 558.4C210.2 568.8 230.3 572.5 246.3 568.8C257.9 566.2 267.4 559.2 272.2 548.6C284.7 548.5 298.5 543.2 320.5 542C335.4 540.8 354.1 547.3 375.6 546.1C376.2 548.4 377 550.7 378.1 552.8L378.1 552.9C386.4 569.6 401.9 577.2 418.4 575.9C435 574.6 452.5 564.9 466.7 548C480.3 531.6 502.7 524.8 517.6 515.8C525 511.3 531 505.7 531.5 497.5C531.9 489.3 527.1 480.2 516 467.8zM319.8 151.3C329.6 129.1 354 129.5 363.8 150.9C370.3 165.1 367.4 181.8 359.5 191.3C357.9 190.5 353.6 188.7 346.9 186.4C348 185.2 350 183.7 350.8 181.8C355.6 170 350.6 154.8 341.7 154.5C334.4 154 327.8 165.3 329.9 177.5C325.8 175.5 320.5 174 316.9 173.1C315.9 166.2 316.6 158.5 319.8 151.3zM279.1 139.8C289.2 139.8 299.9 154 298.2 173.3C294.7 174.3 291.1 175.8 288 177.9C289.2 169 284.7 157.8 278.4 158.3C270 159 268.6 179.5 276.6 186.4C277.6 187.2 278.5 186.2 270.7 191.9C255.1 177.3 260.2 139.8 279.1 139.8zM265.5 200.5C271.7 195.9 279.1 190.5 279.6 190C284.3 185.6 293.1 175.8 307.5 175.8C314.6 175.8 323.1 178.1 333.4 184.7C339.7 188.8 344.7 189.1 356 194C364.4 197.5 369.7 203.7 366.5 212.2C363.9 219.3 355.5 226.6 343.8 230.3C332.7 233.9 324 246.3 305.6 245.2C301.7 245 298.6 244.2 296 243.1C288 239.6 283.8 232.7 276 228.1C267.4 223.3 262.8 217.7 261.3 212.8C259.9 207.9 261.3 203.8 265.5 200.5zM268.8 534.5C266.1 569.6 224.9 568.9 193.5 552.5C163.6 536.7 124.9 546 117 530.6C114.6 525.9 114.6 517.9 119.6 504.2L119.6 504C122 496.4 120.2 488 119 480.1C117.8 472.3 117.2 465.1 119.9 460.1C123.4 453.4 128.4 451 134.7 448.8C145 445.1 146.5 445.4 154.3 438.9C159.8 433.2 163.8 426 168.6 420.9C173.7 415.4 178.6 412.8 186.3 414C194.4 415.2 201.4 420.8 208.2 430L227.8 465.6C237.3 485.5 270.9 514 268.8 534.5zM267.4 508.6C263.3 502 257.8 495 253 489C260.1 489 267.2 486.8 269.7 480.1C272 473.9 269.7 465.2 262.3 455.2C248.8 437 224 422.7 224 422.7C210.5 414.3 202.9 404 199.4 392.8C195.9 381.6 196.4 369.5 199.1 357.6C204.3 334.7 217.7 312.4 226.3 298.4C228.6 296.7 227.1 301.6 217.6 319.2C209.1 335.3 193.2 372.5 215 401.6C215.6 380.9 220.5 359.8 228.8 340.1C240.8 312.7 266.1 265.2 268.1 227.4C269.2 228.2 272.7 230.6 274.3 231.5C278.9 234.2 282.4 238.2 286.9 241.8C299.3 251.8 315.4 251 329.3 243C335.5 239.5 340.5 235.5 345.2 234C355.1 230.9 363 225.4 367.5 219C375.2 249.4 393.2 293.3 404.7 314.7C410.8 326.1 423 350.2 428.3 379.3C431.6 379.2 435.3 379.7 439.2 380.7C453 345 427.5 306.5 415.9 295.8C411.2 291.2 411 289.2 413.3 289.3C425.9 300.5 442.5 323 448.5 348.3C451.3 359.9 451.8 372 448.9 384C465.3 390.8 484.8 401.9 479.6 418.8C477.4 418.7 476.4 418.8 475.4 418.8C478.6 408.7 471.5 401.2 452.6 392.7C433 384.1 416.6 384.1 414.3 405.2C402.2 409.4 396 419.9 392.9 432.5C390.1 443.7 389.3 457.2 388.5 472.4C388 480.1 384.9 490.4 381.7 501.4C349.6 524.3 305 534.3 267.4 508.6zM524.8 497.1C523.9 513.9 483.6 517 461.6 543.6C448.4 559.3 432.2 568 418 569.1C403.8 570.2 391.5 564.3 384.3 549.8C379.6 538.7 381.9 526.7 385.4 513.5C389.1 499.3 394.6 484.7 395.3 472.9C396.1 457.7 397 444.4 399.5 434.2C402.1 423.9 406.1 417 413.2 413.1C413.5 412.9 413.9 412.8 414.2 412.6C415 425.8 421.5 439.2 433 442.1C445.6 445.4 463.7 434.6 471.4 425.8C480.4 425.5 487.1 424.9 494 430.9C503.9 439.4 501.1 461.2 511.1 472.5C521.7 484.1 525.1 492 524.8 497.1zM269.4 212.7C271.4 214.6 274.1 217.2 277.4 219.8C284 225 293.2 230.4 304.7 230.4C316.3 230.4 327.2 224.5 336.5 219.6C341.4 217 347.4 212.6 351.3 209.2C355.2 205.8 357.2 202.9 354.4 202.6C351.6 202.3 351.8 205.2 348.4 207.7C344 210.9 338.7 215.1 334.5 217.5C327.1 221.7 315 227.7 304.6 227.7C294.2 227.7 285.9 222.9 279.7 218C276.6 215.5 274 213 272 211.1C270.5 209.7 270.1 206.5 267.7 206.2C266.3 206.1 265.9 209.9 269.4 212.7z"/></svg>';
}
function iconUSB() {
  return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="7" y="3" width="10" height="7" rx="2" fill="#93c5fd"></rect><rect x="5" y="10" width="14" height="11" rx="3" fill="#3b82f6"></rect><rect x="9" y="5" width="2" height="3" fill="#0f172a"></rect><rect x="13" y="5" width="2" height="3" fill="#0f172a"></rect></svg>';
}
function escapeHtml(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
function formatBytes(bytes) {
  const n = Number(bytes || 0);
  if (!Number.isFinite(n) || n <= 0) return 'Unknown';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let idx = 0;
  let val = n;
  while (val >= 1024 && idx < units.length - 1) {
    val /= 1024;
    idx += 1;
  }
  return val.toFixed(idx <= 1 ? 0 : 1) + ' ' + units[idx];
}
function formatSpeed(speedBps) {
  const v = Number(speedBps || 0);
  if (!Number.isFinite(v) || v <= 0) return 'Speed: --';
  return 'Speed: ' + formatBytes(v) + '/s';
}
function formatEta(seconds) {
  const s = Number(seconds || 0);
  if (!Number.isFinite(s) || s <= 0) return 'ETA: --';
  if (s < 60) return 'ETA: ' + Math.round(s) + 's';
  const m = Math.floor(s / 60);
  const rem = Math.round(s % 60);
  return 'ETA: ' + m + 'm ' + rem + 's';
}

function setValidation(msg) {
  document.getElementById('validationMsg').textContent = msg || '';
}

function updateStepper() {
  const steps = [
    { el: document.getElementById('step1'), icon: document.getElementById('step1Icon') },
    { el: document.getElementById('step2'), icon: document.getElementById('step2Icon') },
    { el: document.getElementById('step3'), icon: document.getElementById('step3Icon') }
  ];
  let activeIdx = 0;
  if (state.selectedDisk) activeIdx = 2;
  else activeIdx = 1;
  if (state.jobId) activeIdx = 2;
  steps.forEach((s, i) => {
    s.el.className = 'step';
    if (i < activeIdx) {
      s.icon.innerHTML = '&#10003;';
      s.icon.style.opacity = '1';
      s.icon.style.color = '#fff';
    } else if (i === activeIdx) {
      s.icon.innerHTML = '&#9679;';
      s.icon.style.opacity = '1';
      s.icon.style.color = '#fff';
    } else {
      s.icon.innerHTML = '&#9675;';
      s.icon.style.opacity = '0.4';
      s.icon.style.color = '';
    }
  });
}

function updateActions() {
  const canCreate = !!state.selectedDisk && document.getElementById('confirmErase').checked && !state.polling;
  document.getElementById('createBtn').disabled = !canCreate;
  document.getElementById('downloadBtn').disabled = !state.catalog[state.mediaType];
  if (!state.selectedDisk) {
    setValidation('Select a USB drive to continue.');
  } else if (!document.getElementById('confirmErase').checked) {
    setValidation('Please confirm the erase warning.');
  } else {
    setValidation('');
  }
  updateStepper();
}

function openPanel(title, htmlBody) {
  document.getElementById('panelTitle').textContent = title;
  document.getElementById('panelBody').innerHTML = htmlBody;
  document.getElementById('panelBackdrop').classList.add('open');
  document.getElementById('sidePanel').classList.add('open');
}
function closePanel() {
  document.getElementById('panelBackdrop').classList.remove('open');
  document.getElementById('sidePanel').classList.remove('open');
}

function renderMediaCards() {
  const ids = ['winpe', 'linux'];
  const root = document.getElementById('osCards');
  root.innerHTML = ids.map(id => {
    const m = state.catalog[id];
    if (!m) return '';
    const selected = state.mediaType === id ? ' selected' : '';
    const icon = id === 'winpe' ? iconWindows() : iconLinux();
    return '<div class="os-card' + selected + '" data-media="' + id + '">' +
      '<div class="os-top">' + icon + '<div class="os-title">' + escapeHtml(m.title) + '</div></div>' +
      '<div class="muted" style="min-height:34px;">' + escapeHtml(m.description) + '</div>' +
      '<div style="margin-top:8px;"><a href="#" class="link" data-details="' + id + '">Details</a></div>' +
      '</div>';
  }).join('');

  root.querySelectorAll('.os-card').forEach(el => {
    el.addEventListener('click', () => {
      state.mediaType = el.getAttribute('data-media') || 'winpe';
      renderMediaCards();
      updateActions();
    });
  });
  root.querySelectorAll('[data-details]').forEach(el => {
    el.addEventListener('click', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      const id = el.getAttribute('data-details') || 'winpe';
      const m = state.catalog[id];
      if (!m) return;
      openPanel(m.title + ' details',
        '<div class="kv"><b>Image version:</b> ' + escapeHtml(m.version || 'n/a') + '</div>' +
        '<div class="kv"><b>Approx size:</b> ' + escapeHtml(m.approx_size || 'Unknown') + '</div>' +
        '<div class="kv"><b>Last updated:</b> ' + escapeHtml(m.last_updated || 'Unknown') + '</div>' +
        '<div class="kv"><b>SHA256:</b> <span style="word-break:break-all;">' + escapeHtml(m.sha256 || 'Not published') + '</span></div>'
      );
    });
  });
}

function renderDisks() {
  const root = document.getElementById('driveList');
  if (!state.disks.length) {
    root.innerHTML = '<div class="muted">No USB drives detected. Insert a USB drive and click Refresh drives.</div>';
    return;
  }
  let max = 1;
  state.disks.forEach(d => { if (Number(d.size_bytes || 0) > max) max = Number(d.size_bytes || 0); });
  root.innerHTML = state.disks.map(d => {
    const selected = Number(state.selectedDisk) === Number(d.number) ? ' selected' : '';
    const fill = Math.max(12, Math.min(100, Math.round((Number(d.size_bytes || 0) / max) * 100)));
    const letters = d.drive_letters ? (' | ' + d.drive_letters) : '';
    const model = d.model ? (' | ' + d.model) : '';
    return '<div class="drive-card' + selected + '" data-disk="' + d.number + '">' +
      '<div>' + iconUSB() + '</div>' +
      '<div><div class="drive-name">' + escapeHtml(d.name || ('Disk ' + d.number)) + '</div>' +
      '<div class="drive-meta">Disk ' + d.number + letters + model + '</div></div>' +
      '<div class="cap-wrap"><div class="cap-bar"><div class="cap-fill" style="width:' + fill + '%"></div></div><div class="cap-text">' + formatBytes(d.size_bytes) + '</div></div>' +
      '</div>';
  }).join('');

  root.querySelectorAll('.drive-card').forEach(el => {
    el.addEventListener('click', () => {
      state.selectedDisk = Number(el.getAttribute('data-disk'));
      const item = state.disks.find(d => Number(d.number) === Number(state.selectedDisk));
      state.selectedDiskName = item ? (item.name || ('Disk ' + item.number)) : '';
      renderDisks();
      updateActions();
    });
  });
}

async function loadDisks() {
  const root = document.getElementById('driveList');
  root.innerHTML = '<div class="muted">Detecting USB drives...</div>';
  try {
    const resp = await fetch('/recovery/api/disks');
    const data = await resp.json();
    if (data.status !== 'success') throw new Error(data.message || 'Failed to load disks');
    state.disks = Array.isArray(data.disks) ? data.disks : [];
    if (state.selectedDisk && !state.disks.some(d => Number(d.number) === Number(state.selectedDisk))) {
      state.selectedDisk = null;
      state.selectedDiskName = '';
      document.getElementById('confirmErase').checked = false;
    }
    renderDisks();
    updateActions();
  } catch (err) {
    root.innerHTML = '<div class="error">Failed to detect USB drives: ' + escapeHtml(err && err.message ? err.message : String(err)) + '</div>';
  }
}

function renderPhases(phases) {
  const root = document.getElementById('phaseList');
  const list = Array.isArray(phases) ? phases : [];
  if (!list.length) {
    state.lastPhaseSignature = '';
    root.innerHTML = '';
    return;
  }
  const signature = JSON.stringify(list.map(p => ({
    key: p && p.key ? String(p.key) : '',
    label: p && p.label ? String(p.label) : '',
    state: p && p.state ? String(p.state) : 'pending'
  })));
  if (signature === state.lastPhaseSignature) {
    return;
  }
  state.lastPhaseSignature = signature;
  root.innerHTML = list.map(p => {
    const rawState = String(p && p.state ? p.state : 'pending');
    const stateClass = rawState === 'error' ? 'failed' : rawState;
    const marker = stateClass === 'done' ? '&#10003;' : '&nbsp;';
    return '<li class="phase-item ' + escapeHtml(stateClass) + '">' +
      '<span class="phase-marker" aria-hidden="true">' + marker + '</span>' +
      '<span class="phase-label">' + escapeHtml(p.label || p.key || '') + '</span>' +
      '</li>';
  }).join('');
}

function renderDiagnostics(lines) {
  state.lastDiagnostics = Array.isArray(lines) ? lines : [];
  const el = document.getElementById('detailsLog');
  el.textContent = state.lastDiagnostics.join('\n');
}

function showProgressCard() {
  document.getElementById('progressCard').style.display = 'block';
}

async function startCreate() {
  if (!state.selectedDisk) return;
  state.polling = true;
  state.jobId = '';
  state.lastPhaseSignature = '';
  document.getElementById('errorBox').style.display = 'none';
  document.getElementById('errorActions').style.display = 'none';
  document.getElementById('successActions').style.display = 'none';
  document.getElementById('statusText').textContent = 'Starting...';
  document.getElementById('progressFill').style.width = '0%';
  document.getElementById('progressPercent').textContent = '0%';
  document.getElementById('speedEta').textContent = '';
  renderDiagnostics([]);
  showProgressCard();
  updateActions();
  try {
    const resp = await fetch('/recovery/api/start', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ disk_number: state.selectedDisk, media_type: state.mediaType, build_mode: state.buildMode })
    });
    const data = await resp.json();
    if (data.status !== 'success') throw new Error(data.message || 'Failed');
    state.jobId = data.job_id;
    pollStatus();
  } catch (err) {
    state.polling = false;
    const msg = err && err.message ? err.message : String(err);
    document.getElementById('errorBox').style.display = 'block';
    document.getElementById('errorBox').textContent = 'Unable to start recovery media creation: ' + msg;
    document.getElementById('errorActions').style.display = 'block';
    updateActions();
  }
}

async function pollStatus() {
  if (!state.jobId) return;
  try {
    const resp = await fetch('/recovery/api/status?job_id=' + encodeURIComponent(state.jobId));
    const data = await resp.json();
    if (data.status !== 'success') throw new Error(data.message || 'Status failed');
    const job = data.job || {};
    const pct = Math.max(0, Math.min(100, Number(job.progress || 0)));
    document.getElementById('progressFill').style.width = pct + '%';
    document.getElementById('progressPercent').textContent = pct + '%';
    document.getElementById('speedEta').textContent = formatSpeed(job.speed_bps) + ' | ' + formatEta(job.eta_seconds);
    document.getElementById('statusText').textContent = job.message || job.status || '';
    renderPhases(job.phases || []);
    renderDiagnostics(job.diagnostics || []);

    if (job.status === 'completed') {
      state.polling = false;
      document.getElementById('successActions').style.display = 'block';
      updateActions();
      return;
    }
    if (job.status === 'failed') {
      state.polling = false;
      document.getElementById('errorBox').style.display = 'block';
      document.getElementById('errorBox').textContent = job.message || 'Recovery media creation failed.';
      document.getElementById('errorActions').style.display = 'block';
      updateActions();
      return;
    }
    setTimeout(pollStatus, 1000);
  } catch (err) {
    state.polling = false;
    document.getElementById('errorBox').style.display = 'block';
    document.getElementById('errorBox').textContent = 'Failed to fetch progress: ' + (err && err.message ? err.message : String(err));
    document.getElementById('errorActions').style.display = 'block';
    updateActions();
  }
}

async function showBootInstructions() {
  try {
    const resp = await fetch('/recovery/api/boot_instructions?media_type=' + encodeURIComponent(state.mediaType));
    const data = await resp.json();
    if (data.status !== 'success') throw new Error(data.message || 'Failed');
    const steps = Array.isArray(data.steps) ? data.steps : [];
    const html = '<ol style="margin:0; padding-left:18px; color:#d5dfed;">' + steps.map(s => '<li style="margin:0 0 8px 0;">' + escapeHtml(s) + '</li>').join('') + '</ol>';
    openPanel(data.title || 'Boot instructions', html);
  } catch (err) {
    openPanel('Boot instructions', '<div class="error">Unable to load boot instructions: ' + escapeHtml(err && err.message ? err.message : String(err)) + '</div>');
  }
}

async function ejectUSB() {
  if (!state.selectedDisk) return;
  const btn = document.getElementById('ejectBtn');
  btn.disabled = true;
  const original = btn.textContent;
  btn.textContent = 'Ejecting...';
  try {
    const resp = await fetch('/recovery/api/eject', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ disk_number: state.selectedDisk })
    });
    const data = await resp.json();
    if (data.status !== 'success') throw new Error(data.message || 'Eject failed');
    btn.textContent = data.message || 'USB is safe to remove.';
  } catch (err) {
    btn.textContent = 'Eject failed';
    openPanel('Safely eject USB', '<div class="error">Unable to eject automatically. You can still use Windows \"Safely Remove Hardware\". Error: ' + escapeHtml(err && err.message ? err.message : String(err)) + '</div>');
  } finally {
    setTimeout(() => { btn.textContent = original; btn.disabled = false; }, 2600);
  }
}

function downloadSelectedMedia() {
  const m = state.catalog[state.mediaType];
  if (!m || !m.download_url) return;
  window.open(m.download_url, '_blank', 'noopener');
}

async function copyDiagnostics() {
  const text = state.lastDiagnostics.join('\n');
  try {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(text);
      openPanel('Diagnostics', '<div class="muted">Diagnostics copied to clipboard.</div>');
      return;
    }
  } catch (e) {}
  const el = document.createElement('textarea');
  el.value = text;
  document.body.appendChild(el);
  el.select();
  document.execCommand('copy');
  document.body.removeChild(el);
  openPanel('Diagnostics', '<div class="muted">Diagnostics copied to clipboard.</div>');
}

function toggleDetails() {
  state.detailsOpen = !state.detailsOpen;
  document.getElementById('detailsLog').style.display = state.detailsOpen ? 'block' : 'none';
  document.getElementById('toggleDetailsBtn').textContent = state.detailsOpen ? 'Hide details' : 'Show details';
}

async function loadCatalog() {
  const resp = await fetch('/recovery/api/catalog');
  const data = await resp.json();
  if (data.status !== 'success') throw new Error(data.message || 'Failed to load media catalog');
  const items = Array.isArray(data.items) ? data.items : [];
  const catalog = {};
  items.forEach(i => { if (i && i.id) catalog[i.id] = i; });
  state.catalog = catalog;
  state.mediaType = data.default_media_type || 'winpe';
  renderMediaCards();
  updateActions();
}

document.getElementById('refreshBtn').addEventListener('click', loadDisks);
document.getElementById('confirmErase').addEventListener('change', updateActions);
document.getElementById('buildModeSelect').addEventListener('change', (e) => {
  state.buildMode = (e && e.target && e.target.value) ? e.target.value : 'fast';
});
document.getElementById('createBtn').addEventListener('click', startCreate);
document.getElementById('downloadBtn').addEventListener('click', downloadSelectedMedia);
document.getElementById('toggleDetailsBtn').addEventListener('click', toggleDetails);
document.getElementById('panelClose').addEventListener('click', closePanel);
document.getElementById('panelBackdrop').addEventListener('click', closePanel);
document.getElementById('bootBtn').addEventListener('click', showBootInstructions);
document.getElementById('ejectBtn').addEventListener('click', ejectUSB);
document.getElementById('copyDiagBtn').addEventListener('click', copyDiagnostics);

(async function init() {
  try {
    const modeEl = document.getElementById('buildModeSelect');
    if (modeEl && modeEl.value) {
      state.buildMode = modeEl.value;
    }
    await loadCatalog();
    await loadDisks();
  } catch (err) {
    const msg = err && err.message ? err.message : String(err);
    document.getElementById('driveList').innerHTML = '<div class="error">Initialization failed: ' + escapeHtml(msg) + '</div>';
  }
  updateActions();
})();
</script>
</body>
</html>`
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	_, _ = w.Write([]byte(page))
}

func runPowerShell(script string) (string, error) {
	// Note: without HideWindow, Windows will often create a visible console window
	// (especially when launched from the tray/recovery UI).
	cmd := exec.Command("powershell.exe", "-NoProfile", "-Command", script)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	var buf bytes.Buffer
	cmd.Stdout = &buf
	cmd.Stderr = &buf
	if err := cmd.Run(); err != nil {
		return "", fmt.Errorf("powershell failed: %v (%s)", err, buf.String())
	}
	return strings.TrimSpace(buf.String()), nil
}

func releaseMountedDiskImage(imagePath string) {
	if strings.TrimSpace(imagePath) == "" {
		return
	}
	script := fmt.Sprintf(`
$ErrorActionPreference = 'SilentlyContinue'
$p = %s
$img = Get-DiskImage -ImagePath $p -ErrorAction SilentlyContinue
if ($img -and $img.Attached) {
  Dismount-DiskImage -ImagePath $p -ErrorAction SilentlyContinue | Out-Null
}
`, psQuote(imagePath))
	_, _ = runPowerShell(script)
}

func runPowerShellWithOutput(script string, onLine func(string)) error {
	cmd := exec.Command("powershell.exe", "-NoProfile", "-Command", script)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	stdout, err := cmd.StdoutPipe()
	if err != nil {
		return err
	}
	stderr, err := cmd.StderrPipe()
	if err != nil {
		return err
	}
	if err := cmd.Start(); err != nil {
		return err
	}

	var outBuf bytes.Buffer
	var mu sync.Mutex
	appendOut := func(s string) {
		mu.Lock()
		defer mu.Unlock()
		if strings.TrimSpace(s) == "" {
			return
		}
		if outBuf.Len() > 0 {
			outBuf.WriteByte('\n')
		}
		outBuf.WriteString(s)
	}

	var wg sync.WaitGroup
	wg.Add(2)
	go func() {
		defer wg.Done()
		sc := bufio.NewScanner(stdout)
		for sc.Scan() {
			line := sc.Text()
			if onLine != nil {
				onLine(line)
			}
			appendOut(line)
		}
	}()
	go func() {
		defer wg.Done()
		sc := bufio.NewScanner(stderr)
		for sc.Scan() {
			appendOut(sc.Text())
		}
	}()
	waitErr := cmd.Wait()
	wg.Wait()
	if waitErr != nil {
		return fmt.Errorf("powershell failed: %v (%s)", waitErr, outBuf.String())
	}
	return nil
}

// buildJobsPageURL converts the API base URL to the backup jobs page URL.
func buildJobsPageURL(apiBaseURL string) string {
	u, err := url.Parse(strings.TrimRight(apiBaseURL, "/"))
	if err != nil {
		return "https://accounts.eazybackup.ca/index.php?m=cloudstorage&page=e3backup&view=jobs"
	}
	return fmt.Sprintf("%s://%s/index.php?m=cloudstorage&page=e3backup&view=jobs", u.Scheme, u.Host)
}

func htmlEscape(s string) string {
	s = strings.ReplaceAll(s, "&", "&amp;")
	s = strings.ReplaceAll(s, "<", "&lt;")
	s = strings.ReplaceAll(s, ">", "&gt;")
	s = strings.ReplaceAll(s, `"`, "&quot;")
	s = strings.ReplaceAll(s, "'", "&#39;")
	return s
}
