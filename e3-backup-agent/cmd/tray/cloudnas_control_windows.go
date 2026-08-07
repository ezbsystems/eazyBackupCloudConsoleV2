//go:build windows

package main

import (
	"bytes"
	"context"
	"crypto/rand"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"syscall"
	"time"
	"unsafe"

	"github.com/getlantern/systray"
)

const (
	cloudNASControlTokenHeader = "X-E3-CloudNAS-Token"
	cloudNASSidecarTokenHeader = "X-E3-CloudNAS-Token"
)

type cloudNASTrayDiscovery struct {
	Version      int    `json:"version"`
	SessionID    uint32 `json:"session_id"`
	Username     string `json:"username,omitempty"`
	ListenAddr   string `json:"listen_addr"`
	ControlToken string `json:"control_token"`
	PID          int    `json:"pid"`
	UpdatedAt    string `json:"updated_at"`
}

type cloudNASSidecarDiscovery struct {
	ListenAddr string `json:"listen_addr"`
	Port       int    `json:"port"`
	PID        int    `json:"pid"`
	Version    string `json:"version"`
}

type cloudNASSidecarEndpoint struct {
	BaseURL string
	Token   string
}

type cloudNASSidecarHealth struct {
	OK     bool `json:"ok"`
	WinFsp bool `json:"winfsp"`
}

type cloudNASSidecarUnmountRequest struct {
	DriveLetter string `json:"drive_letter"`
}

type cloudNASMountRequest struct {
	DriveLetter string `json:"drive_letter"`
}

type cloudNASRegisterRequest struct {
	MountID     int64  `json:"mount_id"`
	DriveLetter string `json:"drive_letter"`
	BucketName  string `json:"bucket_name"`
	Status      string `json:"status"`
}

type cloudNASUnmountRequest struct {
	DriveLetter string `json:"drive_letter"`
}

type cloudNASUnregisterRequest struct {
	DriveLetter string `json:"drive_letter"`
}

type cloudNASPendingMount struct {
	MountID     int64
	DriveLetter string
	BucketName  string
	Status      string

	entryItem   *systray.MenuItem
	unmountItem *systray.MenuItem
}

func cloudNASProgramDataRoot() string {
	if pd := strings.TrimSpace(os.Getenv("ProgramData")); pd != "" {
		return pd
	}
	return `C:\ProgramData`
}

func cloudNASProgramDataDir() string {
	return filepath.Join(cloudNASProgramDataRoot(), "E3Backup")
}

func cloudNASSessionDir() string {
	return filepath.Join(cloudNASProgramDataDir(), "tray-sessions")
}

func cloudNASSessionFile(sessionID uint32) string {
	return filepath.Join(cloudNASSessionDir(), fmt.Sprintf("session-%d.json", sessionID))
}

var (
	kernel32Tray             = syscall.NewLazyDLL("kernel32.dll")
	procProcessIdToSessionID = kernel32Tray.NewProc("ProcessIdToSessionId")
)

func currentSessionID() (uint32, error) {
	var sessionID uint32
	r1, _, err := procProcessIdToSessionID.Call(uintptr(uint32(os.Getpid())), uintptr(unsafe.Pointer(&sessionID)))
	if r1 == 0 {
		return 0, fmt.Errorf("ProcessIdToSessionId: %v", err)
	}
	return sessionID, nil
}

func currentWindowsUsername() string {
	domain := strings.TrimSpace(os.Getenv("USERDOMAIN"))
	user := strings.TrimSpace(os.Getenv("USERNAME"))
	if domain != "" && user != "" {
		return domain + `\` + user
	}
	return user
}

func newCloudNASControlToken() (string, error) {
	var raw [32]byte
	if _, err := rand.Read(raw[:]); err != nil {
		return "", err
	}
	return base64.RawURLEncoding.EncodeToString(raw[:]), nil
}

func (a *trayApp) initCloudNASControl() error {
	if strings.TrimSpace(a.httpAddr) == "" {
		return fmt.Errorf("tray HTTP server is not listening")
	}
	if a.cloudNASControlToken == "" {
		token, err := newCloudNASControlToken()
		if err != nil {
			return err
		}
		a.cloudNASControlToken = token
	}
	sessionID, err := currentSessionID()
	if err != nil {
		return err
	}
	discovery := cloudNASTrayDiscovery{
		Version:      1,
		SessionID:    sessionID,
		Username:     currentWindowsUsername(),
		ListenAddr:   a.httpAddr,
		ControlToken: a.cloudNASControlToken,
		PID:          os.Getpid(),
		UpdatedAt:    time.Now().UTC().Format(time.RFC3339),
	}
	if err := os.MkdirAll(cloudNASSessionDir(), 0o755); err != nil {
		return err
	}
	body, err := json.Marshal(discovery)
	if err != nil {
		return err
	}
	path := cloudNASSessionFile(sessionID)
	tmpPath := path + ".tmp"
	if err := os.WriteFile(tmpPath, body, 0o600); err != nil {
		return err
	}
	if err := os.Rename(tmpPath, path); err != nil {
		_ = os.Remove(tmpPath)
		return err
	}
	a.cloudNASDiscoveryPath = path
	logDebug("cloudnas: published tray discovery session=%d addr=%s", sessionID, a.httpAddr)
	return nil
}

func (a *trayApp) cleanupCloudNASControl() {
	if path := strings.TrimSpace(a.cloudNASDiscoveryPath); path != "" {
		_ = os.Remove(path)
	}
}

func loadCloudNASSidecarEndpoint(programData string) (*cloudNASSidecarEndpoint, error) {
	dir := filepath.Join(programData, "E3Backup")
	discoveryBody, err := os.ReadFile(filepath.Join(dir, "cloudnas.discovery"))
	if err != nil {
		return nil, fmt.Errorf("read Cloud NAS sidecar discovery: %w", err)
	}
	tokenBody, err := os.ReadFile(filepath.Join(dir, "cloudnas.token"))
	if err != nil {
		return nil, fmt.Errorf("read Cloud NAS sidecar token: %w", err)
	}
	var discovery cloudNASSidecarDiscovery
	if err := json.Unmarshal(discoveryBody, &discovery); err != nil {
		return nil, fmt.Errorf("decode Cloud NAS sidecar discovery: %w", err)
	}
	addr := strings.TrimSpace(discovery.ListenAddr)
	if addr == "" && discovery.Port > 0 {
		addr = fmt.Sprintf("127.0.0.1:%d", discovery.Port)
	}
	if addr == "" {
		return nil, fmt.Errorf("Cloud NAS sidecar discovery has no listen address")
	}
	if !strings.HasPrefix(addr, "http://") && !strings.HasPrefix(addr, "https://") {
		addr = "http://" + addr
	}
	token := strings.TrimSpace(string(tokenBody))
	if token == "" {
		return nil, fmt.Errorf("Cloud NAS sidecar token is empty")
	}
	return &cloudNASSidecarEndpoint{BaseURL: strings.TrimSuffix(addr, "/"), Token: token}, nil
}

func cloudNASSidecarRequest(ep *cloudNASSidecarEndpoint, method, route string, payload any, timeout time.Duration) ([]byte, error) {
	var body []byte
	if payload != nil {
		var err error
		body, err = json.Marshal(payload)
		if err != nil {
			return nil, err
		}
	}
	ctx, cancel := context.WithTimeout(context.Background(), timeout)
	defer cancel()
	req, err := http.NewRequestWithContext(ctx, method, ep.BaseURL+route, bytes.NewReader(body))
	if err != nil {
		return nil, err
	}
	req.Header.Set(cloudNASSidecarTokenHeader, ep.Token)
	if payload != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()
	respBody, err := io.ReadAll(io.LimitReader(resp.Body, 64*1024))
	if err != nil {
		return nil, err
	}
	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("Cloud NAS sidecar HTTP %d: %s", resp.StatusCode, strings.TrimSpace(string(respBody)))
	}
	return respBody, nil
}

func checkCloudNASSidecarHealth(ep *cloudNASSidecarEndpoint) error {
	body, err := cloudNASSidecarRequest(ep, http.MethodGet, "/health", nil, 2*time.Second)
	if err != nil {
		return err
	}
	var health cloudNASSidecarHealth
	if err := json.Unmarshal(body, &health); err != nil {
		return fmt.Errorf("decode Cloud NAS sidecar health: %w", err)
	}
	if !health.OK {
		return fmt.Errorf("Cloud NAS sidecar reported unhealthy")
	}
	if !health.WinFsp {
		return fmt.Errorf("Cloud NAS sidecar cannot access WinFsp")
	}
	return nil
}

func cloudNASSidecarInstallCandidates() []string {
	var candidates []string
	for _, envName := range []string{"ProgramW6432", "ProgramFiles", "ProgramFiles(x86)"} {
		if root := strings.TrimSpace(os.Getenv(envName)); root != "" {
			candidates = append(candidates, filepath.Join(root, "E3Backup", "CloudNAS", "e3-cloudnas.exe"))
		}
	}
	if trayPath, err := os.Executable(); err == nil {
		dir := filepath.Dir(trayPath)
		candidates = append(candidates,
			filepath.Join(dir, "CloudNAS", "e3-cloudnas.exe"),
			filepath.Join(dir, "e3-cloudnas.exe"),
		)
	}
	return candidates
}

func findCloudNASSidecarBinary() (string, error) {
	seen := make(map[string]bool)
	for _, candidate := range cloudNASSidecarInstallCandidates() {
		candidate = filepath.Clean(candidate)
		key := strings.ToLower(candidate)
		if seen[key] {
			continue
		}
		seen[key] = true
		if info, err := os.Stat(candidate); err == nil && !info.IsDir() {
			return candidate, nil
		}
	}
	return "", fmt.Errorf("e3-cloudnas.exe is not installed under E3Backup\\CloudNAS")
}

// ensureCloudNASSidecarRunning starts the installed sidecar in this desktop
// session when its discovery metadata is missing, stale, or unhealthy.
func ensureCloudNASSidecarRunning() error {
	programData := cloudNASProgramDataRoot()
	if ep, err := loadCloudNASSidecarEndpoint(programData); err == nil {
		if err := checkCloudNASSidecarHealth(ep); err == nil {
			return nil
		}
	}
	binaryPath, err := findCloudNASSidecarBinary()
	if err != nil {
		return err
	}
	cmd := exec.Command(binaryPath, "-program-data", programData)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	if err := cmd.Start(); err != nil {
		return fmt.Errorf("start Cloud NAS sidecar: %w", err)
	}
	_ = cmd.Process.Release()

	deadline := time.Now().Add(10 * time.Second)
	var lastErr error
	for time.Now().Before(deadline) {
		ep, err := loadCloudNASSidecarEndpoint(programData)
		if err == nil {
			err = checkCloudNASSidecarHealth(ep)
		}
		if err == nil {
			logDebug("cloudnas: sidecar started from %s", binaryPath)
			return nil
		}
		lastErr = err
		time.Sleep(250 * time.Millisecond)
	}
	return fmt.Errorf("Cloud NAS sidecar did not become ready within 10s: %w", lastErr)
}

func unmountCloudNASSidecarDrive(driveLetter string) error {
	driveLetter = cloudNASMenuDriveLetter(driveLetter)
	if len(driveLetter) != 1 || driveLetter[0] < 'A' || driveLetter[0] > 'Z' {
		return fmt.Errorf("invalid drive letter %q", driveLetter)
	}
	if err := ensureCloudNASSidecarRunning(); err != nil {
		return err
	}
	ep, err := loadCloudNASSidecarEndpoint(cloudNASProgramDataRoot())
	if err != nil {
		return err
	}
	_, err = cloudNASSidecarRequest(ep, http.MethodPost, "/unmount", cloudNASSidecarUnmountRequest{
		DriveLetter: driveLetter,
	}, 10*time.Second)
	return err
}

func cloudNASMenuDriveLetter(raw string) string {
	return strings.ToUpper(strings.TrimSuffix(strings.TrimSpace(raw), ":"))
}

func cloudNASDisplayStatus(status string) string {
	switch strings.ToLower(strings.TrimSpace(status)) {
	case "mounted":
		return "Mounted"
	case "pending":
		return "Ready"
	case "error":
		return "Error"
	default:
		return "Prepared"
	}
}

func (a *trayApp) refreshCloudNASMenuLocked() {
	if a.cloudNASMenu == nil {
		return
	}
	if len(a.cloudNASMounts) == 0 {
		a.cloudNASMenu.SetTitle("Cloud NAS")
		a.cloudNASMenu.Disable()
		return
	}
	a.cloudNASMenu.SetTitle(fmt.Sprintf("Cloud NAS (%d)", len(a.cloudNASMounts)))
	a.cloudNASMenu.Enable()
}

func (a *trayApp) updateCloudNASMenuItemsLocked(state *cloudNASPendingMount) {
	if state == nil || state.entryItem == nil || state.unmountItem == nil {
		return
	}
	state.entryItem.SetTitle(fmt.Sprintf("%s: %s [%s]", state.DriveLetter, state.BucketName, cloudNASDisplayStatus(state.Status)))
	state.entryItem.Enable()
	if strings.EqualFold(strings.TrimSpace(state.Status), "mounted") {
		state.unmountItem.Enable()
	} else {
		state.unmountItem.Disable()
	}
}

func (a *trayApp) watchCloudNASMenuState(state *cloudNASPendingMount) {
	for range state.unmountItem.ClickedCh {
		go a.handleCloudNASUserUnmountAction(state.DriveLetter)
	}
}

func (a *trayApp) upsertCloudNASPendingMount(req cloudNASRegisterRequest) string {
	driveLetter := cloudNASMenuDriveLetter(req.DriveLetter)
	if len(driveLetter) != 1 {
		return "invalid drive letter"
	}
	a.cloudNASMenuMu.Lock()
	defer a.cloudNASMenuMu.Unlock()
	if a.cloudNASMounts == nil {
		a.cloudNASMounts = make(map[string]*cloudNASPendingMount)
	}
	state, exists := a.cloudNASMounts[driveLetter]
	if !exists {
		if a.cloudNASMenu == nil {
			return "cloud nas menu not ready"
		}
		entry := a.cloudNASMenu.AddSubMenuItem("", "Cloud NAS mount status")
		state = &cloudNASPendingMount{
			entryItem:   entry,
			unmountItem: entry.AddSubMenuItem("Unmount", "Unmount this Cloud NAS drive"),
		}
		a.cloudNASMounts[driveLetter] = state
		go a.watchCloudNASMenuState(state)
	}
	state.MountID = req.MountID
	state.DriveLetter = driveLetter
	state.BucketName = strings.TrimSpace(req.BucketName)
	state.Status = strings.TrimSpace(req.Status)
	if state.Status == "" {
		state.Status = "pending"
	}
	a.updateCloudNASMenuItemsLocked(state)
	a.refreshCloudNASMenuLocked()
	return fmt.Sprintf("registered %s", driveLetter)
}

func (a *trayApp) removeCloudNASPendingMount(driveLetter string) string {
	driveLetter = cloudNASMenuDriveLetter(driveLetter)
	a.cloudNASMenuMu.Lock()
	defer a.cloudNASMenuMu.Unlock()
	state, exists := a.cloudNASMounts[driveLetter]
	if !exists {
		a.refreshCloudNASMenuLocked()
		return fmt.Sprintf("drive %s not registered", driveLetter)
	}
	state.entryItem.Hide()
	state.unmountItem.Hide()
	delete(a.cloudNASMounts, driveLetter)
	a.refreshCloudNASMenuLocked()
	return fmt.Sprintf("unregistered %s", driveLetter)
}

func (a *trayApp) getCloudNASPendingMount(driveLetter string) (*cloudNASPendingMount, bool) {
	a.cloudNASMenuMu.Lock()
	defer a.cloudNASMenuMu.Unlock()
	state, ok := a.cloudNASMounts[cloudNASMenuDriveLetter(driveLetter)]
	if !ok || state == nil {
		return nil, false
	}
	copyState := *state
	return &copyState, true
}

func (a *trayApp) authenticateCloudNASControl(w http.ResponseWriter, r *http.Request) bool {
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil || (host != "127.0.0.1" && host != "::1") {
		writeJSON(w, map[string]any{"status": "fail", "message": "localhost only"})
		return false
	}
	if strings.TrimSpace(r.Header.Get(cloudNASControlTokenHeader)) != a.cloudNASControlToken {
		writeJSON(w, map[string]any{"status": "fail", "message": "unauthorized"})
		return false
	}
	return true
}

func (a *trayApp) handleCloudNASPing(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet || !a.authenticateCloudNASControl(w, r) {
		return
	}
	sessionID, _ := currentSessionID()
	writeJSON(w, map[string]any{"status": "success", "session_id": sessionID, "username": currentWindowsUsername()})
}

func (a *trayApp) handleCloudNASRegister(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost || !a.authenticateCloudNASControl(w, r) {
		return
	}
	var req cloudNASRegisterRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	writeJSON(w, map[string]any{"status": "success", "message": a.upsertCloudNASPendingMount(req)})
}

func (a *trayApp) handleCloudNASUnregister(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost || !a.authenticateCloudNASControl(w, r) {
		return
	}
	var req cloudNASUnregisterRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	writeJSON(w, map[string]any{"status": "success", "message": a.removeCloudNASPendingMount(req.DriveLetter)})
}

// The agent owns mounting. This compatibility endpoint deliberately does not
// create a WebDAV mapping.
func (a *trayApp) handleCloudNASMount(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost || !a.authenticateCloudNASControl(w, r) {
		return
	}
	var req cloudNASMountRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	if err := ensureCloudNASSidecarRunning(); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	writeJSON(w, map[string]any{"status": "fail", "message": "Cloud NAS mounts are managed by the agent sidecar"})
}

func (a *trayApp) handleCloudNASUnmount(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost || !a.authenticateCloudNASControl(w, r) {
		return
	}
	var req cloudNASUnmountRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	if err := unmountCloudNASSidecarDrive(req.DriveLetter); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}
	writeJSON(w, map[string]any{"status": "success", "message": "unmounted via Cloud NAS sidecar"})
}

func (a *trayApp) handleCloudNASUserUnmountAction(driveLetter string) {
	state, ok := a.getCloudNASPendingMount(driveLetter)
	if !ok {
		a.setErr("Cloud NAS mount not found in tray menu")
		return
	}
	if err := unmountCloudNASSidecarDrive(state.DriveLetter); err != nil {
		a.setErr(fmt.Sprintf("Cloud NAS %s: unmount failed: %v", state.DriveLetter, err))
		return
	}
	if err := a.reportCloudNASStatus(state.MountID, "unmounted", ""); err != nil {
		logDebug("cloudnas: unmount status callback warning drive=%s err=%v", state.DriveLetter, err)
	}
	a.removeCloudNASPendingMount(state.DriveLetter)
	a.setInfo(fmt.Sprintf("Cloud NAS %s: unmounted", state.DriveLetter))
}

func (a *trayApp) reportCloudNASStatus(mountID int64, status, errorMsg string) error {
	if mountID <= 0 {
		return fmt.Errorf("missing mount id")
	}
	cfg, err := loadConfig(a.configPath)
	if err != nil || cfg == nil {
		return fmt.Errorf("load config: %w", err)
	}
	apiBase := strings.TrimRight(strings.TrimSpace(cfg.APIBaseURL), "/")
	if apiBase == "" || strings.TrimSpace(cfg.AgentUUID) == "" || strings.TrimSpace(cfg.AgentToken) == "" {
		return fmt.Errorf("agent is not enrolled")
	}
	body := map[string]any{"mount_id": mountID, "status": status}
	if strings.TrimSpace(errorMsg) != "" {
		body["error"] = errorMsg
	}
	buf, _ := json.Marshal(body)
	req, err := http.NewRequest(http.MethodPost, apiBase+"/cloudnas_update_status.php", bytes.NewReader(buf))
	if err != nil {
		return err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Agent-UUID", cfg.AgentUUID)
	req.Header.Set("X-Agent-Token", cfg.AgentToken)
	resp, err := (&http.Client{Timeout: 15 * time.Second}).Do(req)
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("cloudnas_update_status http %d", resp.StatusCode)
	}
	return nil
}
