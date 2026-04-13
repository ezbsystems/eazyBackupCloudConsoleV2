//go:build windows

package main

import (
	"bytes"
	"context"
	"crypto/rand"
	"encoding/base64"
	"encoding/json"
	"fmt"
	"net"
	"net/http"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"sync"
	"syscall"
	"time"
	"unsafe"

	"github.com/getlantern/systray"
	"golang.org/x/sys/windows"
	"golang.org/x/sys/windows/registry"
)

// Win32 networking constants for WNetAddConnection2W / WNetCancelConnection2W.
const (
	resourceTypeDisk     = 0x00000001
	connectUpdateProfile = 0x00000001
)

// NETRESOURCE describes a network resource for WNet functions.
type netResource struct {
	Scope       uint32
	Type        uint32
	DisplayType uint32
	Usage       uint32
	LocalName   *uint16
	RemoteName  *uint16
	Comment     *uint16
	Provider    *uint16
}

var (
	mpr                       = windows.NewLazySystemDLL("mpr.dll")
	shell32                   = windows.NewLazySystemDLL("shell32.dll")
	advapi32                  = windows.NewLazySystemDLL("advapi32.dll")
	procWNetAddConnection2    = mpr.NewProc("WNetAddConnection2W")
	procWNetCancelConnection2 = mpr.NewProc("WNetCancelConnection2W")
	procSHChangeNotify        = shell32.NewProc("SHChangeNotify")
	procImpersonateLoggedOn   = advapi32.NewProc("ImpersonateLoggedOnUser")
	procRevertToSelf          = advapi32.NewProc("RevertToSelf")
)

// SHChangeNotify event IDs
const (
	shcneDriveAdd      = 0x00000100
	shcneDriveRemoved  = 0x00000080
	shcneMediaInserted = 0x00000020
	shcneMediaRemoved  = 0x00000040
	shcneUpdateDir     = 0x00001000
	shcneAssocChanged  = 0x08000000
	shcnfPath          = 0x0005
)

// wnetAddConnection2 maps a drive letter to a UNC path using the calling
// process's token, which — when the tray runs non-elevated in the user's
// desktop session — is the same logon session as Explorer.
//
// We use CONNECT_UPDATE_PROFILE so the mapping is written to
// HKCU\Network\<letter>.  Explorer enumerates that key to populate
// "This PC" — without it, the drive is usable from cmd/programmatic
// access but invisible in the Explorer shell.
//
// The HKCU\Network entry is cleaned up on unmount (WNetCancelConnection2
// with CONNECT_UPDATE_PROFILE) and stale entries are purged on tray
// startup (see cleanupStaleCloudNASNetworkEntries).
func wnetAddConnection2(driveLetter, uncPath string) error {
	local, err := windows.UTF16PtrFromString(driveLetter + ":")
	if err != nil {
		return err
	}
	remote, err := windows.UTF16PtrFromString(uncPath)
	if err != nil {
		return err
	}
	nr := netResource{
		Type:       resourceTypeDisk,
		LocalName:  local,
		RemoteName: remote,
	}
	ret, _, _ := procWNetAddConnection2.Call(
		uintptr(unsafe.Pointer(&nr)),
		0, // no password
		0, // no username
		uintptr(connectUpdateProfile),
	)
	if ret != 0 {
		return fmt.Errorf("WNetAddConnection2W failed: error code %d", ret)
	}
	return nil
}

// wnetCancelConnection2 removes a mapped drive letter.
func wnetCancelConnection2(driveLetter string) error {
	name, err := windows.UTF16PtrFromString(driveLetter + ":")
	if err != nil {
		return err
	}
	ret, _, _ := procWNetCancelConnection2.Call(
		uintptr(unsafe.Pointer(name)),
		uintptr(connectUpdateProfile),
		1, // force
	)
	if ret != 0 {
		return fmt.Errorf("WNetCancelConnection2W failed: error code %d", ret)
	}
	return nil
}

// findExplorerPID locates the desktop-shell explorer.exe process running in
// the active console session.
func findExplorerPID() (uint32, error) {
	// Determine which Windows session owns the physical console.
	var consoleSession uint32
	r, _, _ := kernel32Tray.NewProc("WTSGetActiveConsoleSessionId").Call()
	consoleSession = uint32(r)
	if consoleSession == 0xFFFFFFFF {
		return 0, fmt.Errorf("no active console session")
	}

	snap, err := windows.CreateToolhelp32Snapshot(windows.TH32CS_SNAPPROCESS, 0)
	if err != nil {
		return 0, fmt.Errorf("CreateToolhelp32Snapshot: %w", err)
	}
	defer windows.CloseHandle(snap)

	var pe windows.ProcessEntry32
	pe.Size = uint32(unsafe.Sizeof(pe))
	if err := windows.Process32First(snap, &pe); err != nil {
		return 0, fmt.Errorf("Process32First: %w", err)
	}

	for {
		name := windows.UTF16ToString(pe.ExeFile[:])
		if strings.EqualFold(name, "explorer.exe") {
			var sid uint32
			if r, _, _ := procProcessIdToSessionID.Call(
				uintptr(pe.ProcessID),
				uintptr(unsafe.Pointer(&sid)),
			); r != 0 && sid == consoleSession {
				return pe.ProcessID, nil
			}
		}
		if err := windows.Process32Next(snap, &pe); err != nil {
			break
		}
	}
	return 0, fmt.Errorf("explorer.exe not found in session %d", consoleSession)
}

// runAsExplorer executes fn while impersonating the desktop-shell
// explorer.exe process token.  This ensures that WNet calls (and any
// other per-logon-session operations) target Explorer's logon session
// rather than the tray's.
//
// Impersonation of a same-user, same-or-lower-integrity token is allowed
// by the kernel without SE_IMPERSONATE_NAME privilege, so this works from
// a standard (Medium-integrity) tray process.
func runAsExplorer(fn func() error) error {
	pid, err := findExplorerPID()
	if err != nil {
		return err
	}

	hProc, err := windows.OpenProcess(windows.PROCESS_QUERY_INFORMATION, false, pid)
	if err != nil {
		return fmt.Errorf("OpenProcess(explorer %d): %w", pid, err)
	}
	defer windows.CloseHandle(hProc)

	var srcToken windows.Token
	if err := windows.OpenProcessToken(hProc,
		windows.TOKEN_DUPLICATE|windows.TOKEN_QUERY, &srcToken); err != nil {
		return fmt.Errorf("OpenProcessToken(explorer %d): %w", pid, err)
	}
	defer srcToken.Close()

	var impToken windows.Token
	if err := windows.DuplicateTokenEx(
		srcToken,
		windows.TOKEN_QUERY|windows.TOKEN_DUPLICATE|windows.TOKEN_IMPERSONATE,
		nil,
		windows.SecurityImpersonation,
		windows.TokenImpersonation,
		&impToken,
	); err != nil {
		return fmt.Errorf("DuplicateTokenEx: %w", err)
	}
	defer impToken.Close()

	runtime.LockOSThread()
	defer runtime.UnlockOSThread()

	if r, _, e := procImpersonateLoggedOn.Call(uintptr(impToken)); r == 0 {
		return fmt.Errorf("ImpersonateLoggedOnUser: %v", e)
	}
	defer procRevertToSelf.Call()

	return fn()
}

func wnetAddConnection2AsExplorer(driveLetter, uncPath string) error {
	return runAsExplorer(func() error {
		return wnetAddConnection2(driveLetter, uncPath)
	})
}

func wnetCancelConnection2AsExplorer(driveLetter string) error {
	return runAsExplorer(func() error {
		return wnetCancelConnection2(driveLetter)
	})
}

// shChangeNotifyInt broadcasts a shell change notification with IntPtr items.
func shChangeNotifyInt(eventID, flags int, item1, item2 uintptr) {
	procSHChangeNotify.Call(
		uintptr(eventID),
		uintptr(flags),
		item1,
		item2,
	)
}

// shChangeNotifyPath broadcasts a shell change notification with a path string.
func shChangeNotifyPath(eventID int, path string) {
	p, err := windows.UTF16PtrFromString(path)
	if err != nil {
		return
	}
	procSHChangeNotify.Call(
		uintptr(eventID),
		uintptr(shcnfPath),
		uintptr(unsafe.Pointer(p)),
		0,
	)
}

// ensureHKCUNetworkEntry writes the HKCU\Network\<letter> registry key that
// Explorer reads to populate "This PC" with mapped network drives.  When the
// drive is mapped via impersonation of Explorer's process token,
// CONNECT_UPDATE_PROFILE may not reliably write this entry in the user's own
// registry hive.  Writing it explicitly from the tray's (non-impersonated)
// context guarantees Explorer can discover the drive.
func ensureHKCUNetworkEntry(driveLetter, uncPath string) error {
	dl := strings.ToUpper(strings.TrimSuffix(strings.TrimSpace(driveLetter), ":"))
	regPath := `Network\` + dl
	key, _, err := registry.CreateKey(registry.CURRENT_USER, regPath, registry.SET_VALUE)
	if err != nil {
		return fmt.Errorf("create HKCU\\Network\\%s: %w", dl, err)
	}
	defer key.Close()

	if err := key.SetStringValue("RemotePath", uncPath); err != nil {
		return err
	}
	if err := key.SetDWordValue("ConnectionType", 1); err != nil {
		return err
	}
	if err := key.SetStringValue("ProviderName", "Web Client Network"); err != nil {
		return err
	}
	return nil
}

const (
	hwndBroadcast      = uintptr(0xFFFF)
	wmDeviceChange     = 0x0219
	dbtDevnodesChanged = 0x0007
)

// broadcastDriveChange sends WM_DEVICECHANGE to all top-level windows so
// Explorer re-enumerates drives without needing an explorer.exe restart.
func broadcastDriveChange() {
	procPostMessageW.Call(hwndBroadcast, wmDeviceChange, dbtDevnodesChanged, 0)
}

// ensureWebClientService starts the Windows WebClient service if it is not
// already running.  WNetAddConnection2 for WebDAV UNC paths requires this
// service and the first WNet call can stall for 30-60s while the service
// auto-starts.  Pre-starting it reduces mount latency.
func ensureWebClientService() {
	cmd := exec.Command("net", "start", "webclient")
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	_ = cmd.Run()
}

var (
	explorerRestartMu    sync.Mutex
	explorerRestartTimer *time.Timer
)

// scheduleExplorerRestart queues a single debounced explorer.exe restart.
// Multiple calls within the delay window (3 seconds) are coalesced into one
// restart, so rapid mount/unmount sequences don't kill Explorer repeatedly.
func scheduleExplorerRestart() {
	explorerRestartMu.Lock()
	defer explorerRestartMu.Unlock()

	if explorerRestartTimer != nil {
		explorerRestartTimer.Stop()
	}
	explorerRestartTimer = time.AfterFunc(3*time.Second, func() {
		restartExplorerShell()
	})
}

// notifyShellDriveAdded broadcasts shell notifications so Explorer shows the new drive.
func notifyShellDriveAdded(driveLetter string) {
	drivePath := strings.ToUpper(strings.TrimSuffix(driveLetter, ":")) + `:\`

	shChangeNotifyInt(shcneDriveAdd, 0, 0, 0)
	shChangeNotifyPath(shcneDriveAdd, drivePath)
	shChangeNotifyPath(shcneMediaInserted, drivePath)
	shChangeNotifyPath(shcneUpdateDir, drivePath)

	// Refresh "This PC" namespace
	shChangeNotifyPath(shcneUpdateDir, `::{20D04FE0-3AEA-1069-A2D8-08002B30309D}`)

	// Force shell to flush and reload
	shChangeNotifyInt(shcneAssocChanged, 0, 0, 0)

	broadcastDriveChange()
}

// notifyShellDriveRemovedDirect broadcasts shell notifications for drive removal.
func notifyShellDriveRemovedDirect(driveLetter string) {
	drivePath := strings.ToUpper(strings.TrimSuffix(driveLetter, ":")) + `:\`

	shChangeNotifyInt(shcneDriveRemoved, 0, 0, 0)
	shChangeNotifyPath(shcneDriveRemoved, drivePath)
	shChangeNotifyPath(shcneMediaRemoved, drivePath)
	shChangeNotifyInt(shcneAssocChanged, 0, 0, 0)

	broadcastDriveChange()
}

const cloudNASControlTokenHeader = "X-E3-CloudNAS-Token"

type cloudNASTrayDiscovery struct {
	Version      int    `json:"version"`
	SessionID    uint32 `json:"session_id"`
	Username     string `json:"username,omitempty"`
	ListenAddr   string `json:"listen_addr"`
	ControlToken string `json:"control_token"`
	PID          int    `json:"pid"`
	UpdatedAt    string `json:"updated_at"`
}

type cloudNASMountRequest struct {
	DriveLetter string `json:"drive_letter"`
	TargetURL   string `json:"target_url"`
	BucketName  string `json:"bucket_name"`
	WebDAVPort  int    `json:"webdav_port"`
}

type cloudNASRegisterRequest struct {
	MountID     int64  `json:"mount_id"`
	DriveLetter string `json:"drive_letter"`
	TargetURL   string `json:"target_url"`
	BucketName  string `json:"bucket_name"`
	WebDAVPort  int    `json:"webdav_port"`
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
	TargetURL   string
	BucketName  string
	WebDAVPort  int
	Status      string

	entryItem   *systray.MenuItem
	mountItem   *systray.MenuItem
	manualItem  *systray.MenuItem
	unmountItem *systray.MenuItem
}

type cloudNASDiagnostics struct {
	DriveLetter              string `json:"drive_letter"`
	TestPath                 bool   `json:"test_path"`
	ReadDirOK                bool   `json:"read_dir_ok"`
	PSDrive                  string `json:"psdrive"`
	NetUseLine               string `json:"net_use_line"`
	HKCUNetwork              bool   `json:"hkcu_network"`
	HKCUNetworkRemote        string `json:"hkcu_network_remote"`
	MountPoints2             bool   `json:"mountpoints2"`
	MountPoints2Label        string `json:"mountpoints2_label"`
	ExplorerVisible          bool   `json:"explorer_visible"`
	ExplorerItem             string `json:"explorer_item"`
	ExplorerWindows          int    `json:"explorer_windows"`
	ExplorerLocations        string `json:"explorer_locations"`
	ThisPCItemCount          int    `json:"this_pc_item_count"`
	PolicyNoDrivesHKCU       string `json:"policy_no_drives_hkcu"`
	PolicyNoDrivesHKLM       string `json:"policy_no_drives_hklm"`
	PolicyNoViewHKCU         string `json:"policy_no_view_hkcu"`
	PolicyNoViewHKLM         string `json:"policy_no_view_hklm"`
	DriveHiddenByPolicy      bool   `json:"drive_hidden_by_policy"`
	CurrentUsername          string `json:"current_username"`
	CurrentSessionID         string `json:"current_session_id"`
	CurrentIntegrity         string `json:"current_integrity"`
	CurrentIsAdmin           bool   `json:"current_is_admin"`
	ActiveConsoleSession     string `json:"active_console_session"`
	EnableLinkedConnections  string `json:"enable_linked_connections"`
	ExplorerProcessCount     string `json:"explorer_process_count"`
	ExplorerSameSessionCount string `json:"explorer_same_session_count"`
	ExplorerProcesses        string `json:"explorer_processes"`
}

func cloudNASProgramDataDir() string {
	pd := os.Getenv("ProgramData")
	if pd == "" {
		pd = `C:\ProgramData`
	}
	return filepath.Join(pd, "E3Backup")
}

func cloudNASSessionDir() string {
	return filepath.Join(cloudNASProgramDataDir(), "tray-sessions")
}

func cloudNASSessionFile(sessionID uint32) string {
	return filepath.Join(cloudNASSessionDir(), fmt.Sprintf("session-%d.json", sessionID))
}

var (
	kernel32Tray             = syscall.NewLazyDLL("kernel32.dll")
	user32Tray               = syscall.NewLazyDLL("user32.dll")
	procProcessIdToSessionID = kernel32Tray.NewProc("ProcessIdToSessionId")
	procFindWindowW          = user32Tray.NewProc("FindWindowW")
	procGetWindowThreadPID   = user32Tray.NewProc("GetWindowThreadProcessId")
	procPostMessageW         = user32Tray.NewProc("PostMessageW")
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
	switch {
	case domain != "" && user != "":
		return domain + `\` + user
	case user != "":
		return user
	default:
		return ""
	}
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

	cleanupStaleCloudNASNetworkEntries()

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

	tmpPath := cloudNASSessionFile(sessionID) + ".tmp"
	body, err := json.Marshal(discovery)
	if err != nil {
		return err
	}
	if err := os.WriteFile(tmpPath, body, 0o600); err != nil {
		return err
	}
	if err := os.Rename(tmpPath, cloudNASSessionFile(sessionID)); err != nil {
		_ = os.Remove(tmpPath)
		return err
	}

	a.cloudNASDiscoveryPath = cloudNASSessionFile(sessionID)
	logDebug("cloudnas: published tray discovery session=%d addr=%s", sessionID, a.httpAddr)
	return nil
}

func (a *trayApp) cleanupCloudNASControl() {
	if path := strings.TrimSpace(a.cloudNASDiscoveryPath); path != "" {
		_ = os.Remove(path)
	}
}

// cleanupStaleCloudNASNetworkEntries removes HKCU\Network\<letter> entries
// left behind by previous Cloud NAS sessions.  Because we use
// CONNECT_UPDATE_PROFILE, the mapping is written to the registry.  If the
// agent/tray exited without unmounting (crash, reboot, etc.) the entry
// will survive and Windows will attempt to reconnect it on next login —
// which will fail because the WebDAV port has changed.
//
// We identify our entries by checking if RemotePath matches
// \\127.0.0.1@<port>\DavWWWRoot (the pattern our WebDAV server uses).
func cleanupStaleCloudNASNetworkEntries() {
	netKey, err := registry.OpenKey(registry.CURRENT_USER, `Network`, registry.ENUMERATE_SUB_KEYS)
	if err != nil {
		return
	}
	defer netKey.Close()

	subkeys, err := netKey.ReadSubKeyNames(-1)
	if err != nil {
		return
	}

	for _, letter := range subkeys {
		if len(letter) != 1 {
			continue
		}
		driveKey, err := registry.OpenKey(registry.CURRENT_USER, `Network\`+letter, registry.QUERY_VALUE)
		if err != nil {
			continue
		}
		remote, _, err := driveKey.GetStringValue("RemotePath")
		driveKey.Close()
		if err != nil {
			continue
		}

		// Our WebDAV mounts always go through 127.0.0.1@<port>\DavWWWRoot.
		lower := strings.ToLower(remote)
		if !strings.Contains(lower, `127.0.0.1@`) || !strings.Contains(lower, `\davwwwroot`) {
			continue
		}

		logDebug("cloudnas: removing stale HKCU\\Network\\%s entry (RemotePath=%s)", letter, remote)
		_ = registry.DeleteKey(registry.CURRENT_USER, `Network\`+letter)
	}
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
	if state == nil || state.entryItem == nil || state.mountItem == nil || state.manualItem == nil || state.unmountItem == nil {
		return
	}

	title := fmt.Sprintf("%s: %s [%s]", state.DriveLetter, state.BucketName, cloudNASDisplayStatus(state.Status))
	state.entryItem.SetTitle(title)
	state.entryItem.Enable()

	switch strings.ToLower(strings.TrimSpace(state.Status)) {
	case "mounted":
		state.mountItem.SetTitle("Already mounted")
		state.mountItem.Disable()
		state.manualItem.SetTitle("Open mount command")
		state.manualItem.Disable()
		state.unmountItem.SetTitle("Unmount")
		state.unmountItem.Enable()
	default:
		state.mountItem.SetTitle("Mount now")
		state.mountItem.Enable()
		state.manualItem.SetTitle("Open mount command")
		state.manualItem.Enable()
		state.unmountItem.SetTitle("Unmount")
		state.unmountItem.Disable()
	}
}

func (a *trayApp) watchCloudNASMenuState(state *cloudNASPendingMount) {
	for {
		select {
		case <-state.mountItem.ClickedCh:
			go a.handleCloudNASUserMountAction(state.DriveLetter, false)
		case <-state.manualItem.ClickedCh:
			go a.handleCloudNASUserMountAction(state.DriveLetter, true)
		case <-state.unmountItem.ClickedCh:
			go a.handleCloudNASUserUnmountAction(state.DriveLetter)
		}
	}
}

func (a *trayApp) upsertCloudNASPendingMount(req cloudNASRegisterRequest) string {
	driveLetter := cloudNASMenuDriveLetter(req.DriveLetter)
	if driveLetter == "" {
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
		entry := a.cloudNASMenu.AddSubMenuItem("", "Prepared Cloud NAS mount")
		state = &cloudNASPendingMount{
			entryItem:   entry,
			mountItem:   entry.AddSubMenuItem("Mount now", "Attempt the drive mapping now"),
			manualItem:  entry.AddSubMenuItem("Open mount command", "Open a visible command prompt mount"),
			unmountItem: entry.AddSubMenuItem("Unmount", "Unmount this Cloud NAS drive"),
		}
		a.cloudNASMounts[driveLetter] = state
		go a.watchCloudNASMenuState(state)
	}

	state.MountID = req.MountID
	state.DriveLetter = driveLetter
	state.TargetURL = strings.TrimSpace(req.TargetURL)
	state.BucketName = strings.TrimSpace(req.BucketName)
	state.WebDAVPort = req.WebDAVPort
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
	if driveLetter == "" {
		return "invalid drive letter"
	}

	a.cloudNASMenuMu.Lock()
	defer a.cloudNASMenuMu.Unlock()

	state, exists := a.cloudNASMounts[driveLetter]
	if !exists {
		a.refreshCloudNASMenuLocked()
		return fmt.Sprintf("drive %s not registered", driveLetter)
	}
	if state.entryItem != nil {
		state.entryItem.Hide()
	}
	if state.mountItem != nil {
		state.mountItem.Hide()
	}
	if state.manualItem != nil {
		state.manualItem.Hide()
	}
	if state.unmountItem != nil {
		state.unmountItem.Hide()
	}
	delete(a.cloudNASMounts, driveLetter)
	a.refreshCloudNASMenuLocked()
	return fmt.Sprintf("unregistered %s", driveLetter)
}

func (a *trayApp) getCloudNASPendingMount(driveLetter string) (*cloudNASPendingMount, bool) {
	driveLetter = cloudNASMenuDriveLetter(driveLetter)
	a.cloudNASMenuMu.Lock()
	defer a.cloudNASMenuMu.Unlock()
	state, ok := a.cloudNASMounts[driveLetter]
	if !ok || state == nil {
		return nil, false
	}
	copyState := *state
	return &copyState, true
}

func (a *trayApp) setCloudNASPendingMountStatus(driveLetter, status string) {
	driveLetter = cloudNASMenuDriveLetter(driveLetter)
	a.cloudNASMenuMu.Lock()
	defer a.cloudNASMenuMu.Unlock()
	state, ok := a.cloudNASMounts[driveLetter]
	if !ok || state == nil {
		return
	}
	state.Status = status
	a.updateCloudNASMenuItemsLocked(state)
}

func (a *trayApp) authenticateCloudNASControl(w http.ResponseWriter, r *http.Request) bool {
	host, _, err := net.SplitHostPort(r.RemoteAddr)
	if err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid remote address"})
		return false
	}
	if host != "127.0.0.1" && host != "::1" && host != "[::1]" {
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
	if r.Method != http.MethodGet {
		writeJSON(w, map[string]any{"status": "fail", "message": "method not allowed"})
		return
	}
	if !a.authenticateCloudNASControl(w, r) {
		return
	}
	sessionID, _ := currentSessionID()
	writeJSON(w, map[string]any{
		"status":     "success",
		"session_id": sessionID,
		"username":   currentWindowsUsername(),
	})
}

func (a *trayApp) handleCloudNASRegister(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, map[string]any{"status": "fail", "message": "method not allowed"})
		return
	}
	if !a.authenticateCloudNASControl(w, r) {
		return
	}

	var req cloudNASRegisterRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	detail := a.upsertCloudNASPendingMount(req)
	logDebug("cloudnas: tray register drive=%s detail=%s", req.DriveLetter, detail)
	writeJSON(w, map[string]any{"status": "success", "message": detail})
}

func (a *trayApp) handleCloudNASUnregister(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, map[string]any{"status": "fail", "message": "method not allowed"})
		return
	}
	if !a.authenticateCloudNASControl(w, r) {
		return
	}

	var req cloudNASUnregisterRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	detail := a.removeCloudNASPendingMount(req.DriveLetter)
	logDebug("cloudnas: tray unregister drive=%s detail=%s", req.DriveLetter, detail)
	writeJSON(w, map[string]any{"status": "success", "message": detail})
}

func (a *trayApp) handleCloudNASMount(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, map[string]any{"status": "fail", "message": "method not allowed"})
		return
	}
	if !a.authenticateCloudNASControl(w, r) {
		return
	}

	var req cloudNASMountRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	detail, err := mapCloudNASDrive(req.DriveLetter, req.TargetURL, req.BucketName, req.WebDAVPort)
	if err != nil {
		logDebug("cloudnas: tray mount failed drive=%s target=%s err=%v", req.DriveLetter, req.TargetURL, err)
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}

	logDebug("cloudnas: tray mount verified drive=%s target=%s detail=%s", req.DriveLetter, req.TargetURL, detail)
	writeJSON(w, map[string]any{"status": "success", "message": detail})
}

func (a *trayApp) handleCloudNASUnmount(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, map[string]any{"status": "fail", "message": "method not allowed"})
		return
	}
	if !a.authenticateCloudNASControl(w, r) {
		return
	}

	var req cloudNASUnmountRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, map[string]any{"status": "fail", "message": "invalid payload"})
		return
	}
	detail, err := unmapCloudNASDrive(req.DriveLetter)
	if err != nil {
		logDebug("cloudnas: tray unmount warning drive=%s err=%v", req.DriveLetter, err)
		writeJSON(w, map[string]any{"status": "fail", "message": err.Error()})
		return
	}

	logDebug("cloudnas: tray unmount completed drive=%s detail=%s", req.DriveLetter, detail)
	scheduleExplorerRestart()
	writeJSON(w, map[string]any{"status": "success", "message": detail})
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
	if apiBase == "" {
		return fmt.Errorf("missing api base url")
	}
	if strings.TrimSpace(cfg.AgentUUID) == "" || strings.TrimSpace(cfg.AgentToken) == "" {
		return fmt.Errorf("agent is not enrolled")
	}

	body := map[string]any{
		"mount_id": mountID,
		"status":   status,
	}
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

func mountCloudNASDriveVisible(state *cloudNASPendingMount) (string, error) {
	uncTarget, err := webDAVUNCFromTarget(state.TargetURL)
	if err != nil {
		return "", err
	}

	_ = wnetCancelConnection2AsExplorer(state.DriveLetter)
	_ = wnetCancelConnection2(state.DriveLetter)
	time.Sleep(200 * time.Millisecond)

	cmdScript := fmt.Sprintf(
		`title E3 Cloud NAS %s && echo. && echo Mounting %s: from %s && echo. && net use %s: "%s" /persistent:yes && echo. && echo Close this window when you are done reviewing the result.`,
		state.DriveLetter,
		state.DriveLetter,
		uncTarget,
		state.DriveLetter,
		uncTarget,
	)
	cmd := exec.Command("cmd.exe", "/C", "start", "", "cmd.exe", "/K", cmdScript)
	if err := cmd.Start(); err != nil {
		return "", err
	}

	time.Sleep(750 * time.Millisecond)
	if err := verifyCloudNASDrive(state.DriveLetter); err != nil {
		return "", err
	}
	if state.WebDAVPort > 0 && state.BucketName != "" {
		if err := setCloudNASDriveLabel(state.WebDAVPort, state.BucketName); err != nil {
			logDebug("cloudnas: visible cmd label warning drive=%s err=%v", state.DriveLetter, err)
		}
	}
	notifyShellDriveAdded(state.DriveLetter)
	return "visible cmd net use", nil
}

func (a *trayApp) handleCloudNASUserMountAction(driveLetter string, forceVisible bool) {
	state, ok := a.getCloudNASPendingMount(driveLetter)
	if !ok {
		a.setErr("Cloud NAS mount not found in tray menu")
		return
	}

	method := ""
	var err error
	if forceVisible {
		method, err = mountCloudNASDriveVisible(state)
	} else {
		method, err = mapCloudNASDrive(state.DriveLetter, state.TargetURL, state.BucketName, state.WebDAVPort)
		if err != nil {
			logDebug("cloudnas: silent tray mount failed drive=%s err=%v; opening visible fallback", state.DriveLetter, err)
			method, err = mountCloudNASDriveVisible(state)
		}
	}
	if err != nil {
		a.setErr(fmt.Sprintf("Cloud NAS %s: mount failed: %v", state.DriveLetter, err))
		logDebug("cloudnas: user mount failed drive=%s err=%v", state.DriveLetter, err)
		return
	}

	if err := a.reportCloudNASStatus(state.MountID, "mounted", ""); err != nil {
		logDebug("cloudnas: status callback warning drive=%s err=%v", state.DriveLetter, err)
		a.setErr(fmt.Sprintf("Cloud NAS %s: mounted, but status sync failed", state.DriveLetter))
	} else {
		a.setInfo(fmt.Sprintf("Cloud NAS %s: mounted", state.DriveLetter))
	}
	a.setCloudNASPendingMountStatus(state.DriveLetter, "mounted")
	logDebug("cloudnas: user mount completed drive=%s method=%s", state.DriveLetter, method)
	scheduleExplorerRestart()
}

func (a *trayApp) handleCloudNASUserUnmountAction(driveLetter string) {
	state, ok := a.getCloudNASPendingMount(driveLetter)
	if !ok {
		a.setErr("Cloud NAS mount not found in tray menu")
		return
	}

	detail, err := unmapCloudNASDrive(state.DriveLetter)
	if err != nil {
		a.setErr(fmt.Sprintf("Cloud NAS %s: unmount failed: %v", state.DriveLetter, err))
		logDebug("cloudnas: user unmount failed drive=%s err=%v", state.DriveLetter, err)
		return
	}
	if err := a.reportCloudNASStatus(state.MountID, "unmounted", ""); err != nil {
		logDebug("cloudnas: unmount status callback warning drive=%s err=%v", state.DriveLetter, err)
		a.setErr(fmt.Sprintf("Cloud NAS %s: unmounted locally, but status sync failed", state.DriveLetter))
	} else {
		a.setInfo(fmt.Sprintf("Cloud NAS %s: unmounted", state.DriveLetter))
	}
	a.removeCloudNASPendingMount(state.DriveLetter)
	logDebug("cloudnas: user unmount completed drive=%s detail=%s", state.DriveLetter, detail)
	scheduleExplorerRestart()
}

func mapCloudNASDrive(driveLetter, targetURL, bucketName string, webdavPort int) (string, error) {
	dl := strings.TrimSuffix(strings.ToUpper(strings.TrimSpace(driveLetter)), ":")
	if len(dl) != 1 || dl[0] < 'A' || dl[0] > 'Z' {
		return "", fmt.Errorf("invalid drive letter %q", driveLetter)
	}
	targetURL = strings.TrimSpace(targetURL)
	if targetURL == "" {
		return "", fmt.Errorf("missing target URL")
	}

	method, err := mapCloudNASDriveInExplorerSession(dl, targetURL)
	if err != nil {
		return "", err
	}

	// Ensure HKCU\Network\<letter> is written so Explorer discovers the drive
	// in "This PC".  The impersonated WNetAddConnection2 call may not reliably
	// write this entry, so we do it explicitly from the tray's own context.
	if uncTarget, uncErr := webDAVUNCFromTarget(targetURL); uncErr == nil {
		if regErr := ensureHKCUNetworkEntry(dl, uncTarget); regErr != nil {
			logDebug("cloudnas: HKCU\\Network\\%s write warning: %v", dl, regErr)
		}
	}

	if webdavPort > 0 && bucketName != "" {
		if err := setCloudNASDriveLabel(webdavPort, bucketName); err != nil {
			logDebug("cloudnas: drive label warning drive=%s err=%v", dl, err)
		}
	}

	notifyShellDriveAdded(dl)
	scheduleExplorerRestart()

	// Run verification and diagnostics in the background so the HTTP handler
	// responds promptly — WNetAddConnection2 already consumed most of the
	// caller's timeout budget.
	go func() {
		if err := verifyCloudNASDrive(dl); err != nil {
			logDebug("cloudnas: drive %s verification warning (non-fatal): %v", dl, err)
		}
		logCloudNASDiagnostics("post-mount", dl, webdavPort)
		scheduleCloudNASDiagnostics("delayed+5s", dl, webdavPort, 5*time.Second)
		scheduleCloudNASDiagnostics("delayed+15s", dl, webdavPort, 15*time.Second)
	}()

	detail := fmt.Sprintf("method=%s", method)
	logDebug("cloudnas: drive %s registered via %s", dl, method)
	return detail, nil
}

// mapCloudNASDriveInExplorerSession maps the WebDAV UNC path so that it is
// visible in Windows Explorer.  Drive mappings are per-logon-session, so
// the WNetAddConnection2W call must execute under the SAME logon session
// as the desktop-shell explorer.exe.
//
// When the tray is started from the login chain (HKCU\...\Run) it shares
// Explorer's logon session and a direct call is sufficient.  But when the
// tray is restarted manually (after an update, from a different CMD, etc.)
// it may be in a DIFFERENT logon session.  To handle both cases we first
// try impersonating Explorer's process token, which forces the WNet call
// into Explorer's logon session regardless of the tray's own session.
func mapCloudNASDriveInExplorerSession(driveLetter, targetURL string) (string, error) {
	uncTarget, err := webDAVUNCFromTarget(targetURL)
	if err != nil {
		return "", err
	}

	ensureWebClientService()

	// Clean up any stale mapping on this letter first (try both sessions).
	_ = wnetCancelConnection2AsExplorer(driveLetter)
	_ = wnetCancelConnection2(driveLetter)
	time.Sleep(200 * time.Millisecond)

	// Primary: impersonate Explorer's token so the mapping lands in
	// Explorer's logon session even when the tray's own session differs.
	if explorerPID, _ := findExplorerPID(); explorerPID != 0 {
		logDebug("cloudnas: attempting WNetAddConnection2 via explorer.exe PID %d impersonation", explorerPID)
	}
	if err := wnetAddConnection2AsExplorer(driveLetter, uncTarget); err == nil {
		return "WNetAddConnection2(explorer-impersonate)", nil
	} else {
		logDebug("cloudnas: WNetAddConnection2(explorer-impersonate) failed: %v, trying direct call", err)
	}

	// Fallback 1: direct WNetAddConnection2W with UNC path.
	// Works when the tray IS in the same logon session as Explorer.
	_ = wnetCancelConnection2(driveLetter)
	if err := wnetAddConnection2(driveLetter, uncTarget); err == nil {
		return "WNetAddConnection2(UNC)", nil
	} else {
		logDebug("cloudnas: WNetAddConnection2(UNC) failed: %v, trying HTTP fallback", err)
	}

	// Fallback 2: WNetAddConnection2W with HTTP URL (some WebClient
	// versions accept this form directly).
	_ = wnetCancelConnection2(driveLetter)
	if err := wnetAddConnection2(driveLetter, targetURL); err == nil {
		return "WNetAddConnection2(HTTP)", nil
	} else {
		logDebug("cloudnas: WNetAddConnection2(HTTP) failed: %v, trying net use fallback", err)
	}

	// Last resort: shell out to net use (inherits tray token).
	// Use /persistent:yes so the mapping writes to HKCU\Network, which
	// Explorer reads to populate "This PC".
	_ = wnetCancelConnection2(driveLetter)
	cmd := exec.Command("net", "use", driveLetter+":", uncTarget, "/persistent:yes")
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	if out, err := cmd.CombinedOutput(); err != nil {
		return "", fmt.Errorf("all mapping methods failed; last net use error: %s %v",
			strings.TrimSpace(string(out)), err)
	}

	return "net use(UNC)", nil
}

func verifyCloudNASDrive(driveLetter string) error {
	root := strings.ToUpper(strings.TrimSuffix(driveLetter, ":")) + `:\`

	// readDir tries os.ReadDir in the CURRENT thread context.
	readDir := func() error {
		_, err := os.ReadDir(root)
		return err
	}

	var lastErr error
	deadline := time.Now().Add(8 * time.Second)
	for time.Now().Before(deadline) {
		// Try from the tray's own logon session first.
		if err := readDir(); err == nil {
			return nil
		} else {
			lastErr = err
		}
		// Also try under Explorer impersonation (the drive may be
		// mapped in Explorer's logon session, not the tray's).
		if verifyAsExplorer(root) == nil {
			return nil
		}
		time.Sleep(500 * time.Millisecond)
	}
	if lastErr == nil {
		lastErr = fmt.Errorf("drive did not become accessible")
	}
	return fmt.Errorf("verification failed for %s: %w", root, lastErr)
}

// verifyAsExplorer checks drive accessibility while impersonating Explorer.
func verifyAsExplorer(root string) error {
	return runAsExplorer(func() error {
		_, err := os.ReadDir(root)
		return err
	})
}

func unmapCloudNASDrive(driveLetter string) (string, error) {
	dl := strings.TrimSuffix(strings.ToUpper(strings.TrimSpace(driveLetter)), ":")
	if len(dl) != 1 || dl[0] < 'A' || dl[0] > 'Z' {
		return "", fmt.Errorf("invalid drive letter %q", driveLetter)
	}
	before := logCloudNASDiagnostics("pre-unmount", dl, 0)

	// Try Explorer-impersonated cancel first (mirrors the mount approach).
	err := wnetCancelConnection2AsExplorer(dl)
	if err != nil {
		logDebug("cloudnas: WNetCancelConnection2(explorer) failed for %s: %v, trying direct", dl, err)
		// Fallback: direct cancel in the tray's own logon session.
		err = wnetCancelConnection2(dl)
	}
	if err != nil {
		logDebug("cloudnas: WNetCancelConnection2 failed for %s: %v, trying net use fallback", dl, err)
		out, netErr := exec.Command("net", "use", dl+":", "/delete", "/y").CombinedOutput()
		if netErr != nil {
			msg := strings.TrimSpace(string(out))
			if msg == "" {
				msg = netErr.Error()
			}
			return "", fmt.Errorf("unmount failed: WNetCancelConnection2: %v; net use: %s", err, msg)
		}
	}

	// Also clean the HKCU\Network entry in case the impersonated cancel
	// didn't remove it (e.g. if the cancel went through a different path).
	_ = registry.DeleteKey(registry.CURRENT_USER, `Network\`+dl)

	notifyShellDriveRemovedDirect(dl)
	after := logCloudNASDiagnostics("post-unmount", dl, 0)
	return fmt.Sprintf("before=%s; after=%s", before, after), nil
}

func setCloudNASDriveLabel(webdavPort int, bucketName string) error {
	regPath := fmt.Sprintf(`Software\Microsoft\Windows\CurrentVersion\Explorer\MountPoints2\##127.0.0.1@%d#DavWWWRoot`, webdavPort)
	key, _, err := registry.CreateKey(registry.CURRENT_USER, regPath, registry.SET_VALUE)
	if err != nil {
		return err
	}
	defer key.Close()
	return key.SetStringValue("_LabelFromReg", fmt.Sprintf("Cloud NAS (%s)", bucketName))
}

// refreshCloudNASExplorer and refreshCloudNASExplorerRemoval are kept as
// compatibility wrappers. The primary mount/unmount paths now call
// notifyShellDriveAdded / notifyShellDriveRemovedDirect directly via Win32
// syscalls, which avoids spawning a PowerShell process.

func refreshCloudNASExplorer(driveLetter string) {
	notifyShellDriveAdded(driveLetter)
}

func refreshCloudNASExplorerRemoval(driveLetter string) {
	notifyShellDriveRemovedDirect(driveLetter)
}

func openCloudNASDriveInExplorer(driveLetter string) {
	drivePath := strings.ToUpper(strings.TrimSuffix(driveLetter, ":")) + `:\`
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	cmd := exec.CommandContext(ctx, "explorer.exe", drivePath)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	_ = cmd.Start()
}

const wmQuit = 0x0012

// restartExplorerShell gracefully terminates the desktop-shell explorer.exe
// and starts a fresh instance.  This forces Explorer to re-enumerate drives
// which is the only reliable way to make a new WebDAV mapping (or the removal
// of one) visible in the "This PC" view on Windows 10.
//
// The sequence is:
//  1. Find the taskbar window ("Shell_TrayWnd") owned by the shell explorer.
//  2. Derive the explorer.exe PID from that window.
//  3. Post WM_QUIT so Explorer exits gracefully (saves state, etc.).
//  4. Wait for the process to terminate.
//  5. Start a new explorer.exe.
//
// The tray app is a separate process and is not affected by this restart.
func restartExplorerShell() {
	logDebug("cloudnas: restarting explorer.exe to refresh drive list")

	className, _ := syscall.UTF16PtrFromString("Shell_TrayWnd")
	hwnd, _, _ := procFindWindowW.Call(uintptr(unsafe.Pointer(className)), 0)
	if hwnd == 0 {
		logDebug("cloudnas: Shell_TrayWnd not found, falling back to taskkill")
		killCmd := exec.Command("taskkill", "/f", "/im", "explorer.exe")
		killCmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
		_ = killCmd.Run()
		time.Sleep(1500 * time.Millisecond)
		startCmd := exec.Command("explorer.exe")
		startCmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
		_ = startCmd.Start()
		return
	}

	var explorerPID uint32
	procGetWindowThreadPID.Call(hwnd, uintptr(unsafe.Pointer(&explorerPID)))
	if explorerPID == 0 {
		logDebug("cloudnas: could not determine explorer PID from Shell_TrayWnd")
		return
	}

	hProc, err := windows.OpenProcess(windows.SYNCHRONIZE, false, explorerPID)
	if err != nil {
		logDebug("cloudnas: OpenProcess(explorer %d): %v", explorerPID, err)
	}

	procPostMessageW.Call(hwnd, uintptr(wmQuit), 0, 0)
	logDebug("cloudnas: posted WM_QUIT to explorer.exe PID %d", explorerPID)

	if hProc != 0 {
		evt, _ := windows.WaitForSingleObject(hProc, 5000)
		windows.CloseHandle(hProc)
		if evt != windows.WAIT_OBJECT_0 {
			logDebug("cloudnas: explorer.exe did not exit within 5s, forcing kill")
			killCmd := exec.Command("taskkill", "/f", "/pid", fmt.Sprintf("%d", explorerPID))
			killCmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
			_ = killCmd.Run()
			time.Sleep(500 * time.Millisecond)
		}
	} else {
		time.Sleep(2 * time.Second)
	}

	startCmd := exec.Command("explorer.exe")
	startCmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	_ = startCmd.Start()
	logDebug("cloudnas: explorer.exe restarted")
}

func logCloudNASDiagnostics(stage, driveLetter string, webdavPort int) string {
	diag, err := collectCloudNASDiagnostics(driveLetter, webdavPort)
	if err != nil {
		logDebug("cloudnas: diagnostics stage=%s drive=%s err=%v", stage, driveLetter, err)
		return "diagnostics_error=" + err.Error()
	}
	summary := summarizeCloudNASDiagnostics(diag)
	raw, _ := json.Marshal(diag)
	logDebug("cloudnas: diagnostics stage=%s drive=%s summary=%s raw=%s", stage, driveLetter, summary, strings.TrimSpace(string(raw)))
	return summary
}

func scheduleCloudNASDiagnostics(stage, driveLetter string, webdavPort int, delay time.Duration) {
	go func() {
		time.Sleep(delay)
		_ = logCloudNASDiagnostics(stage, driveLetter, webdavPort)
	}()
}

func collectCloudNASDiagnostics(driveLetter string, webdavPort int) (*cloudNASDiagnostics, error) {
	dl := strings.ToUpper(strings.TrimSuffix(strings.TrimSpace(driveLetter), ":"))
	psScript := fmt.Sprintf(`
$ErrorActionPreference = 'Continue'
$driveName = '%s'
$drive = $driveName + ':'
$port = %d
$result = [ordered]@{
  drive_letter = $drive
  test_path = $false
  read_dir_ok = $false
  psdrive = ''
  net_use_line = ''
  hkcu_network = $false
  hkcu_network_remote = ''
  mountpoints2 = $false
  mountpoints2_label = ''
  explorer_visible = $false
  explorer_item = ''
  explorer_windows = 0
  explorer_locations = ''
  this_pc_item_count = 0
  policy_no_drives_hkcu = ''
  policy_no_drives_hklm = ''
  policy_no_view_hkcu = ''
  policy_no_view_hklm = ''
  drive_hidden_by_policy = $false
  current_username = ''
  current_session_id = ''
  current_integrity = ''
  current_is_admin = $false
  active_console_session = ''
  enable_linked_connections = ''
  explorer_process_count = ''
  explorer_same_session_count = ''
  explorer_processes = ''
}
function Set-PolicyValue([string]$path, [string]$name, [string]$resultKey) {
  try {
    $val = (Get-ItemProperty -Path $path -Name $name -ErrorAction SilentlyContinue).$name
    if ($null -ne $val) { $result[$resultKey] = [string]$val }
  } catch { }
}
function Test-DriveHiddenMask([string]$maskText) {
  try {
    if ([string]::IsNullOrWhiteSpace($maskText)) { return $false }
    $mask = [uint32]$maskText
    $index = [byte][char]$driveName[0] - [byte][char]'A'
    if ($index -lt 0 -or $index -gt 25) { return $false }
    return (($mask -band (1 -shl $index)) -ne 0)
  } catch { return $false }
}
function Get-IntegrityLevel {
  try {
    $groups = (whoami /groups | Out-String)
    if ($groups -match 'System Mandatory Level') { return 'System' }
    if ($groups -match 'High Mandatory Level') { return 'High' }
    if ($groups -match 'Medium Mandatory Level') { return 'Medium' }
    if ($groups -match 'Low Mandatory Level') { return 'Low' }
  } catch { }
  return ''
}
try {
  $result.current_username = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
  $result.current_integrity = Get-IntegrityLevel
  $principal = New-Object Security.Principal.WindowsPrincipal([System.Security.Principal.WindowsIdentity]::GetCurrent())
  $result.current_is_admin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
} catch { }
try {
  $result.current_session_id = [string](Get-Process -Id $PID -ErrorAction Stop).SessionId
} catch { }
try {
  Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class CloudNASKernelDiag {
  [DllImport("kernel32.dll")]
  public static extern uint WTSGetActiveConsoleSessionId();
}
"@ -ErrorAction SilentlyContinue | Out-Null
  $result.active_console_session = [string][CloudNASKernelDiag]::WTSGetActiveConsoleSessionId()
} catch { }
try {
  $elc = (Get-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Policies\System' -Name 'EnableLinkedConnections' -ErrorAction SilentlyContinue).EnableLinkedConnections
  if ($null -ne $elc) { $result.enable_linked_connections = [string]$elc }
} catch { }
try { $result.test_path = Test-Path ($drive + '\') } catch { }
try { Get-ChildItem ($drive + '\') -Force -ErrorAction Stop | Select-Object -First 1 | Out-Null; $result.read_dir_ok = $true } catch { }
try {
  $ps = Get-PSDrive -Name $driveName -ErrorAction SilentlyContinue
  if ($ps) { $result.psdrive = ($ps.Name + '|' + $ps.Root + '|' + $ps.DisplayRoot + '|' + $ps.Description) }
} catch { }
try {
  $net = (cmd.exe /c 'net use') | Out-String
  $line = ($net -split "\r?\n" | Where-Object { $_ -match ('(^|\s)' + [regex]::Escape($drive) + '(\s|$)') } | Select-Object -First 1)
  if ($line) { $result.net_use_line = $line.Trim() }
} catch { }
try {
  $key = 'HKCU:\Network\' + $driveName
  if (Test-Path $key) {
    $result.hkcu_network = $true
    $props = Get-ItemProperty -Path $key -ErrorAction SilentlyContinue
    if ($props.RemotePath) { $result.hkcu_network_remote = [string]$props.RemotePath }
  }
} catch { }
try {
  if ($port -gt 0) {
    $mp = 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Explorer\MountPoints2\##127.0.0.1@' + $port + '#DavWWWRoot'
    if (Test-Path $mp) {
      $result.mountpoints2 = $true
      $props = Get-ItemProperty -Path $mp -ErrorAction SilentlyContinue
      if ($props._LabelFromReg) { $result.mountpoints2_label = [string]$props._LabelFromReg }
    }
  }
} catch { }
try {
  $shell = New-Object -ComObject Shell.Application
  $wins = @($shell.Windows())
  $result.explorer_windows = $wins.Count
  $locs = New-Object System.Collections.Generic.List[string]
  foreach ($win in $wins) {
    try {
      $loc = '' + $win.LocationURL
      if (-not [string]::IsNullOrWhiteSpace($loc)) { $locs.Add($loc) }
    } catch { }
  }
  if ($locs.Count -gt 0) { $result.explorer_locations = ($locs -join ' | ') }
  $ns = $shell.Namespace(17)
  if ($ns) {
    $items = @($ns.Items())
    $result.this_pc_item_count = $items.Count
    foreach ($item in $items) {
      try {
        $itemPath = '' + $item.Path
        $itemName = '' + $item.Name
        if ($itemPath -eq $drive -or $itemPath -eq ($drive + '\') -or $itemName -eq $drive -or $itemName -like ($drive + '*')) {
          $result.explorer_visible = $true
          $result.explorer_item = $itemName
          break
        }
      } catch { }
    }
  }
} catch { }
try {
  $explorers = @(Get-CimInstance Win32_Process -Filter "Name = 'explorer.exe'" -ErrorAction SilentlyContinue)
  $result.explorer_process_count = [string]$explorers.Count
  $sameSession = 0
  $procSummary = New-Object System.Collections.Generic.List[string]
  foreach ($proc in $explorers) {
    try {
      $owner = $proc.GetOwner()
      $ownerName = ''
      if ($owner -and $owner.User) { $ownerName = $owner.Domain + '\' + $owner.User }
      if ([string]$proc.SessionId -eq $result.current_session_id) { $sameSession++ }
      $procSummary.Add(([string]$proc.ProcessId) + '@s' + ([string]$proc.SessionId) + ':' + $ownerName)
    } catch { }
  }
  $result.explorer_same_session_count = [string]$sameSession
  if ($procSummary.Count -gt 0) { $result.explorer_processes = ($procSummary -join ' | ') }
} catch { }
Set-PolicyValue 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer' 'NoDrives' 'policy_no_drives_hkcu'
Set-PolicyValue 'HKLM:\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer' 'NoDrives' 'policy_no_drives_hklm'
Set-PolicyValue 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer' 'NoViewOnDrive' 'policy_no_view_hkcu'
Set-PolicyValue 'HKLM:\Software\Microsoft\Windows\CurrentVersion\Policies\Explorer' 'NoViewOnDrive' 'policy_no_view_hklm'
$result.drive_hidden_by_policy = (
  (Test-DriveHiddenMask $result.policy_no_drives_hkcu) -or
  (Test-DriveHiddenMask $result.policy_no_drives_hklm) -or
  (Test-DriveHiddenMask $result.policy_no_view_hkcu) -or
  (Test-DriveHiddenMask $result.policy_no_view_hklm)
)
$result | ConvertTo-Json -Compress
`, dl, webdavPort)

	ctx, cancel := context.WithTimeout(context.Background(), 12*time.Second)
	defer cancel()
	cmd := exec.CommandContext(ctx, "powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", psScript)
	cmd.SysProcAttr = &syscall.SysProcAttr{HideWindow: true}
	out, err := cmd.CombinedOutput()
	if err != nil {
		return nil, fmt.Errorf("diagnostics powershell failed: %w: %s", err, strings.TrimSpace(string(out)))
	}
	var diag cloudNASDiagnostics
	if err := json.Unmarshal(out, &diag); err != nil {
		return nil, fmt.Errorf("diagnostics decode failed: %w: %s", err, strings.TrimSpace(string(out)))
	}
	return &diag, nil
}

func summarizeCloudNASDiagnostics(diag *cloudNASDiagnostics) string {
	if diag == nil {
		return "diagnostics=nil"
	}
	return fmt.Sprintf(
		"test_path=%t read_dir_ok=%t explorer_visible=%t explorer_item=%q hkcu_network=%t net_use=%q psdrive=%q mountpoints2=%t label=%q explorer_windows=%d hidden_by_policy=%t current_integrity=%q current_is_admin=%t current_session=%q console_session=%q explorer_same_session=%q explorer_total=%q explorer_locations=%q",
		diag.TestPath,
		diag.ReadDirOK,
		diag.ExplorerVisible,
		diag.ExplorerItem,
		diag.HKCUNetwork,
		diag.NetUseLine,
		diag.PSDrive,
		diag.MountPoints2,
		diag.MountPoints2Label,
		diag.ExplorerWindows,
		diag.DriveHiddenByPolicy,
		diag.CurrentIntegrity,
		diag.CurrentIsAdmin,
		diag.CurrentSessionID,
		diag.ActiveConsoleSession,
		diag.ExplorerSameSessionCount,
		diag.ExplorerProcessCount,
		diag.ExplorerLocations,
	)
}

func webDAVUNCFromTarget(targetURL string) (string, error) {
	u, err := url.Parse(strings.TrimSpace(targetURL))
	if err != nil {
		return "", fmt.Errorf("invalid target URL %q: %w", targetURL, err)
	}
	host := strings.TrimSpace(u.Hostname())
	port := strings.TrimSpace(u.Port())
	if host == "" || port == "" {
		return "", fmt.Errorf("target URL %q is missing host or port", targetURL)
	}
	return fmt.Sprintf(`\\%s@%s\DavWWWRoot`, host, port), nil
}
