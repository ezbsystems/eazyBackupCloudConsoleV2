//go:build windows

package main

import (
	"github.com/your-org/e3-backup-agent/internal/applog"
	"golang.org/x/sys/windows"
)

// trayInstanceMutexName is a session-local name so exactly one tray runs per
// interactive logon session. Using the Local\ namespace (rather than Global\)
// scopes the mutex per session, which is what we want: each logged-in user
// gets their own tray, but the agent service's supervisor and the HKCU autorun
// key cannot spawn duplicate trays within the same session.
const trayInstanceMutexName = `Local\e3-backup-tray-singleton`

// acquireSingleInstance returns true when this process is the first/only tray
// in the current session. When another tray already holds the mutex it returns
// false and the caller should exit. The mutex handle is intentionally kept open
// for the lifetime of the process (released by the OS on exit).
func acquireSingleInstance() bool {
	name, err := windows.UTF16PtrFromString(trayInstanceMutexName)
	if err != nil {
		// Fail open: better to risk a duplicate than to refuse to start.
		return true
	}
	_, err = windows.CreateMutex(nil, false, name)
	if err == windows.ERROR_ALREADY_EXISTS {
		applog.Infof("tray", "another tray instance is already running in this session; exiting")
		return false
	}
	return true
}
