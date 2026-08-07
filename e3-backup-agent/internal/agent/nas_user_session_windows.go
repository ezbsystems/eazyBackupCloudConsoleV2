//go:build windows

package agent

import (
	"fmt"
	"os/exec"
	"strings"
	"unsafe"

	"golang.org/x/sys/windows"
)

func getInteractiveUserNameForNAS() (string, error) {
	ps := `$ErrorActionPreference = 'Stop'
$loggedUser = $null
try {
    $cs = Get-WmiObject Win32_ComputerSystem -ErrorAction SilentlyContinue
    if ($cs -and $cs.UserName) { $loggedUser = $cs.UserName }
} catch { }
if (-not $loggedUser) {
    try {
        $explorer = Get-WmiObject Win32_Process -Filter "Name='explorer.exe'" -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($explorer) {
            $owner = $explorer.GetOwner()
            if ($owner.User) { $loggedUser = $owner.Domain + '\' + $owner.User }
        }
    } catch { }
}
if ($loggedUser) { Write-Output $loggedUser }
`
	out, err := exec.Command("powershell", "-NoProfile", "-ExecutionPolicy", "Bypass", "-Command", ps).CombinedOutput()
	if err != nil {
		return "", fmt.Errorf("powershell user lookup: %w: %s", err, strings.TrimSpace(string(out)))
	}
	u := strings.TrimSpace(string(out))
	if u == "" {
		return "", fmt.Errorf("no interactive user (empty WMI/explorer owner)")
	}
	return u, nil
}

func domainUserFromToken(tok windows.Token) (string, error) {
	tu, err := tok.GetTokenUser()
	if err != nil {
		return "", err
	}
	account, domain, _, err := tu.User.Sid.LookupAccount("")
	if err != nil {
		return "", err
	}
	return domain + `\` + account, nil
}

func findWTSSessionMatchingUser(expected string) (uint32, error) {
	var sessions *windows.WTS_SESSION_INFO
	var count uint32
	err := windows.WTSEnumerateSessions(0, 0, 1, &sessions, &count)
	if err != nil {
		return 0, fmt.Errorf("WTSEnumerateSessions: %w", err)
	}
	defer windows.WTSFreeMemory(uintptr(unsafe.Pointer(sessions)))

	list := unsafe.Slice(sessions, count)
	expected = strings.TrimSpace(expected)
	for _, s := range list {
		if s.State != windows.WTSActive && s.State != windows.WTSConnected {
			continue
		}
		var ut windows.Token
		if err := windows.WTSQueryUserToken(s.SessionID, &ut); err != nil {
			continue
		}
		du, err := domainUserFromToken(ut)
		ut.Close()
		if err != nil {
			continue
		}
		if strings.EqualFold(strings.TrimSpace(du), expected) {
			return s.SessionID, nil
		}
	}
	return 0, fmt.Errorf("no WTS session for user %q", expected)
}

func listInteractiveWTSSessions() ([]uint32, error) {
	var sessions *windows.WTS_SESSION_INFO
	var count uint32
	err := windows.WTSEnumerateSessions(0, 0, 1, &sessions, &count)
	if err != nil {
		return nil, fmt.Errorf("WTSEnumerateSessions: %w", err)
	}
	defer windows.WTSFreeMemory(uintptr(unsafe.Pointer(sessions)))

	list := unsafe.Slice(sessions, count)
	seen := make(map[uint32]struct{})
	out := make([]uint32, 0, len(list))
	for _, s := range list {
		if s.State != windows.WTSActive && s.State != windows.WTSConnected {
			continue
		}
		var tok windows.Token
		if err := windows.WTSQueryUserToken(s.SessionID, &tok); err != nil {
			continue
		}
		tok.Close()
		if _, ok := seen[s.SessionID]; ok {
			continue
		}
		seen[s.SessionID] = struct{}{}
		out = append(out, s.SessionID)
	}
	return out, nil
}

func resolveWTSSessionForNAS() (uint32, error) {
	expected, wmiErr := getInteractiveUserNameForNAS()
	if wmiErr == nil && expected != "" {
		if sid, err := findWTSSessionMatchingUser(expected); err == nil {
			return sid, nil
		}
	}

	if sessions, err := listInteractiveWTSSessions(); err == nil && len(sessions) > 1 {
		if wmiErr != nil {
			return 0, fmt.Errorf("multiple interactive sessions are active and the Explorer owner could not be resolved: %w", wmiErr)
		}
		return 0, fmt.Errorf("multiple interactive sessions are active and no matching Explorer session was found for %q", expected)
	}

	sid := windows.WTSGetActiveConsoleSessionId()
	if sid == 0 {
		if wmiErr != nil {
			return 0, fmt.Errorf("console session 0 and user lookup failed: %w", wmiErr)
		}
		return 0, fmt.Errorf("no WTS session (console id 0, user match failed for %q)", expected)
	}
	var t windows.Token
	if err := windows.WTSQueryUserToken(sid, &t); err != nil {
		if wmiErr != nil {
			return 0, fmt.Errorf("WTSQueryUserToken(%d): %w (user lookup: %v)", sid, err, wmiErr)
		}
		return 0, fmt.Errorf("WTSQueryUserToken(%d): %w", sid, err)
	}
	t.Close()
	return sid, nil
}
