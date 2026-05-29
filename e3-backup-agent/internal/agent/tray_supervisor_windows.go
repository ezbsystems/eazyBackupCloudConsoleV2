//go:build windows

package agent

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"
	"unsafe"

	"github.com/your-org/e3-backup-agent/internal/applog"
	"golang.org/x/sys/windows"
)

const trayExeName = "e3-backup-tray.exe"

// startTraySupervisor launches a background goroutine that keeps the tray
// helper running in the active interactive user session.
//
// The agent service runs as LocalSystem and is restarted after every install
// or upgrade (interactive, /VERYSILENT, or remote self-update). Making the
// service responsible for the tray is therefore the single reliable point that
// guarantees the tray is present regardless of how the agent was (re)installed
// - this fixes silent/remote upgrades that previously left no tray. The HKCU
// autorun key still covers the normal logon case; the tray's per-session
// single-instance mutex prevents duplicates.
func (r *Runner) startTraySupervisor(stop <-chan struct{}) {
	go func() {
		// Let the desktop/session settle after boot or an upgrade restart.
		select {
		case <-stop:
			return
		case <-time.After(5 * time.Second):
		}

		ticker := time.NewTicker(30 * time.Second)
		defer ticker.Stop()
		for {
			r.ensureTrayRunning()
			select {
			case <-stop:
				return
			case <-ticker.C:
			}
		}
	}()
}

// ensureTrayRunning launches the tray in the active user session when it is not
// already running and an interactive user is logged on. All failure modes are
// non-fatal and simply retried on the next tick.
func (r *Runner) ensureTrayRunning() {
	if trayProcessRunning() {
		return
	}

	sessionID, ok := activeUserSessionID()
	if !ok {
		// No interactive user logged on yet (e.g. boot before login, or a
		// headless server). Nothing to do until someone logs in.
		return
	}

	exePath, err := trayExePath()
	if err != nil {
		applog.Warnf("tray", "cannot locate tray executable: %v", err)
		return
	}

	if err := launchTrayInSession(sessionID, exePath, r.configPath); err != nil {
		applog.Warnf("tray", "failed to launch tray in session %d: %v", sessionID, err)
		return
	}
	applog.Infof("tray", "launched tray helper in session %d", sessionID)
}

// trayProcessRunning reports whether any e3-backup-tray.exe process exists.
func trayProcessRunning() bool {
	snap, err := windows.CreateToolhelp32Snapshot(windows.TH32CS_SNAPPROCESS, 0)
	if err != nil {
		return false
	}
	defer windows.CloseHandle(snap)

	var pe windows.ProcessEntry32
	pe.Size = uint32(unsafe.Sizeof(pe))
	if err := windows.Process32First(snap, &pe); err != nil {
		return false
	}
	for {
		if strings.EqualFold(windows.UTF16ToString(pe.ExeFile[:]), trayExeName) {
			return true
		}
		if err := windows.Process32Next(snap, &pe); err != nil {
			return false
		}
	}
}

// trayExePath returns the path to the tray helper, which the installer places
// alongside the agent service binary.
func trayExePath() (string, error) {
	self, err := os.Executable()
	if err != nil {
		return "", err
	}
	p := filepath.Join(filepath.Dir(self), trayExeName)
	if _, err := os.Stat(p); err != nil {
		return "", fmt.Errorf("tray not found at %s: %w", p, err)
	}
	return p, nil
}

// activeUserSessionID returns the active console session id when a user token
// can be obtained for it (i.e. an interactive user is actually logged on).
func activeUserSessionID() (uint32, bool) {
	sid := windows.WTSGetActiveConsoleSessionId()
	if sid == 0xFFFFFFFF {
		return 0, false
	}
	var tok windows.Token
	if err := windows.WTSQueryUserToken(sid, &tok); err != nil {
		return 0, false
	}
	tok.Close()
	return sid, true
}

// launchTrayInSession starts the tray helper inside the given session using the
// interactive user's token, so the systray icon appears on that user's desktop
// even though this service runs as LocalSystem.
func launchTrayInSession(sessionID uint32, exePath, configPath string) error {
	var userToken windows.Token
	if err := windows.WTSQueryUserToken(sessionID, &userToken); err != nil {
		return fmt.Errorf("WTSQueryUserToken: %w", err)
	}
	defer userToken.Close()

	var primary windows.Token
	if err := windows.DuplicateTokenEx(userToken, windows.MAXIMUM_ALLOWED, nil, windows.SecurityIdentification, windows.TokenPrimary, &primary); err != nil {
		return fmt.Errorf("DuplicateTokenEx: %w", err)
	}
	defer primary.Close()

	var envBlock *uint16
	if err := windows.CreateEnvironmentBlock(&envBlock, primary, false); err != nil {
		return fmt.Errorf("CreateEnvironmentBlock: %w", err)
	}
	defer windows.DestroyEnvironmentBlock(envBlock)

	appPtr, err := windows.UTF16PtrFromString(exePath)
	if err != nil {
		return err
	}
	cmdLine := fmt.Sprintf(`"%s" -config "%s"`, exePath, configPath)
	cmdUTF16, err := windows.UTF16FromString(cmdLine)
	if err != nil {
		return err
	}
	workDir, err := windows.UTF16PtrFromString(filepath.Dir(exePath))
	if err != nil {
		return err
	}
	// The tray must run on the interactive window station/desktop to show its
	// notification-area icon.
	desktop, err := windows.UTF16PtrFromString(`winsta0\default`)
	if err != nil {
		return err
	}

	si := &windows.StartupInfo{}
	si.Cb = uint32(unsafe.Sizeof(*si))
	si.Desktop = desktop

	var pi windows.ProcessInformation
	creationFlags := uint32(windows.CREATE_UNICODE_ENVIRONMENT)

	if err := windows.CreateProcessAsUser(primary, appPtr, &cmdUTF16[0], nil, nil, false, creationFlags, envBlock, workDir, si, &pi); err != nil {
		return fmt.Errorf("CreateProcessAsUser: %w", err)
	}
	windows.CloseHandle(pi.Thread)
	windows.CloseHandle(pi.Process)
	return nil
}
