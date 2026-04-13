//go:build windows

package agent

import (
	"fmt"
	"log"
	"os/exec"
	"strings"
	"unsafe"

	"golang.org/x/sys/windows"
	"golang.org/x/sys/windows/registry"
)

// mapNASDriveInteractiveUserWTS maps a WebDAV URL to a drive letter by running
// net.exe in the interactive user's session (WTSQueryUserToken + CreateProcessAsUser).
// This avoids Task Scheduler logon types that run without the user's WebClient context.
func mapNASDriveInteractiveUserWTS(driveLetter, targetURL string) error {
	sid, err := resolveWTSSessionForNAS()
	if err != nil {
		return err
	}
	cmdLine := fmt.Sprintf(`C:\Windows\System32\cmd.exe /c net use %s: "%s" /persistent:no`,
		strings.TrimSuffix(strings.ToUpper(driveLetter), ":"), targetURL)
	exit, err := runCmdLineInWTSUserSession(sid, cmdLine)
	if err != nil {
		return err
	}
	if exit != 0 {
		return fmt.Errorf("net use exited with code %d", exit)
	}
	return nil
}

// unmapNASDriveInteractiveUserWTS removes a drive mapping in the interactive user's session.
func unmapNASDriveInteractiveUserWTS(driveLetter string) {
	sid, err := resolveWTSSessionForNAS()
	if err != nil {
		log.Printf("agent: WTS unmap: resolve session: %v", err)
		return
	}
	dl := strings.TrimSuffix(strings.ToUpper(driveLetter), ":")
	cmdLine := fmt.Sprintf(`C:\Windows\System32\cmd.exe /c net use %s: /delete /y`, dl)
	exit, err := runCmdLineInWTSUserSession(sid, cmdLine)
	if err != nil {
		log.Printf("agent: WTS unmap failed: %v", err)
		return
	}
	if exit != 0 {
		log.Printf("agent: WTS unmap: net use exit code %d", exit)
	}
}

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

func runCmdLineInWTSUserSession(sessionID uint32, cmdLine string) (exitCode uint32, err error) {
	var userToken windows.Token
	if err := windows.WTSQueryUserToken(sessionID, &userToken); err != nil {
		return 0, fmt.Errorf("WTSQueryUserToken: %w", err)
	}
	defer userToken.Close()

	var primary windows.Token
	if err := windows.DuplicateTokenEx(userToken, windows.MAXIMUM_ALLOWED, nil, windows.SecurityIdentification, windows.TokenPrimary, &primary); err != nil {
		return 0, fmt.Errorf("DuplicateTokenEx: %w", err)
	}
	defer primary.Close()

	var envBlock *uint16
	if err := windows.CreateEnvironmentBlock(&envBlock, primary, false); err != nil {
		return 0, fmt.Errorf("CreateEnvironmentBlock: %w", err)
	}
	defer windows.DestroyEnvironmentBlock(envBlock)

	app, err := windows.UTF16PtrFromString(`C:\Windows\System32\cmd.exe`)
	if err != nil {
		return 0, err
	}
	cmdUTF16, err := windows.UTF16FromString(cmdLine)
	if err != nil {
		return 0, err
	}

	si := &windows.StartupInfo{}
	si.Cb = uint32(unsafe.Sizeof(*si))
	si.Flags = windows.STARTF_USESHOWWINDOW
	si.ShowWindow = windows.SW_HIDE
	var pi windows.ProcessInformation

	creationFlags := uint32(windows.CREATE_UNICODE_ENVIRONMENT | windows.CREATE_NO_WINDOW)

	if err := windows.CreateProcessAsUser(primary, app, &cmdUTF16[0], nil, nil, false, creationFlags, envBlock, nil, si, &pi); err != nil {
		return 0, fmt.Errorf("CreateProcessAsUser: %w", err)
	}
	defer windows.CloseHandle(pi.Thread)
	defer windows.CloseHandle(pi.Process)

	if _, err := windows.WaitForSingleObject(pi.Process, windows.INFINITE); err != nil {
		return 0, fmt.Errorf("WaitForSingleObject: %w", err)
	}
	if err := windows.GetExitCodeProcess(pi.Process, &exitCode); err != nil {
		return 0, fmt.Errorf("GetExitCodeProcess: %w", err)
	}
	return exitCode, nil
}

// configureWebClientForLargeFiles raises the Windows WebClient (WebDAV
// redirector) file-size limit from the default 50 MB to 4 GB so that large
// files such as videos and disk images can be accessed through Cloud NAS
// mounted drives.  The agent service runs as SYSTEM and has HKLM write
// access.  The WebClient service is restarted only if the value was changed.
func configureWebClientForLargeFiles() {
	const regPath = `SYSTEM\CurrentControlSet\Services\WebClient\Parameters`
	const valueName = "FileSizeLimitInBytes"
	const desired = uint32(0xFFFFFFFF) // 4 GB (max DWORD)

	key, err := registry.OpenKey(registry.LOCAL_MACHINE, regPath, registry.QUERY_VALUE|registry.SET_VALUE)
	if err != nil {
		log.Printf("agent: WebClient registry open failed (non-critical): %v", err)
		return
	}
	defer key.Close()

	current, _, err := key.GetIntegerValue(valueName)
	if err == nil && uint32(current) >= desired {
		return
	}

	if err := key.SetDWordValue(valueName, desired); err != nil {
		log.Printf("agent: WebClient FileSizeLimitInBytes write failed (non-critical): %v", err)
		return
	}
	log.Printf("agent: WebClient FileSizeLimitInBytes raised to %d (was %d); restarting WebClient service", desired, current)

	stop := exec.Command("net", "stop", "webclient", "/y")
	_ = stop.Run()

	start := exec.Command("net", "start", "webclient")
	_ = start.Run()
}
