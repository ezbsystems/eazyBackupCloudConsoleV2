//go:build windows

package main

import (
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
	"path/filepath"
	"strings"
	"sync"
	"syscall"
	"time"

	"github.com/getlantern/systray"
	"gopkg.in/yaml.v3"
)

type agentConfig struct {
	APIBaseURL string `yaml:"api_base_url"`

	DeviceID   string `yaml:"device_id,omitempty"`
	InstallID  string `yaml:"install_id,omitempty"`
	DeviceName string `yaml:"device_name,omitempty"`

	ClientID   string `yaml:"client_id,omitempty"`
	AgentID    string `yaml:"agent_id,omitempty"`
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
	enrolled := strings.TrimSpace(cfg.AgentID) != "" && strings.TrimSpace(cfg.AgentToken) != ""
	pendingTokenEnroll := strings.TrimSpace(cfg.EnrollmentToken) != ""
	logDebug("onReady: enrolled=%v, pendingTokenEnroll=%v, AgentID=%q, EnrollmentToken=%q",
		enrolled, pendingTokenEnroll, cfg.AgentID, cfg.EnrollmentToken)
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
	agentID := ""
	deviceID := ""
	if cfg != nil {
		// If identity is missing (older installs), generate+persist it.
		if strings.TrimSpace(cfg.DeviceID) == "" || strings.TrimSpace(cfg.InstallID) == "" {
			ensureIdentity(cfg)
			_ = saveConfig(a.configPath, cfg)
		}
		enrolled = strings.TrimSpace(cfg.AgentID) != "" && strings.TrimSpace(cfg.AgentToken) != ""
		agentID = cfg.AgentID
		deviceID = cfg.DeviceID
	}
	svc := serviceStatus()
	if enrolled {
		return fmt.Sprintf("Status: %s | Enrolled (agent_id=%s)", svc, agentID),
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
//       -> https://accounts.eazybackup.ca/index.php?m=cloudstorage&page=e3backup&view=tokens
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
	mux.HandleFunc("/assets/eazybackup-logo.svg", func(w http.ResponseWriter, r *http.Request) {
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
		cfg.AgentID = string(res.AgentID)
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
	Status     string `json:"status"`
	Message    string `json:"message"`
	AgentID    jsonString `json:"agent_id"`
	ClientID   jsonString `json:"client_id"`
	AgentToken string `json:"agent_token"`
	APIBaseURL string `json:"api_base_url"`
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


