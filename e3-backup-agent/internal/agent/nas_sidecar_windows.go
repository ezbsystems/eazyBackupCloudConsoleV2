//go:build windows

package agent

import (
	"errors"
	"os"

	"golang.org/x/sys/windows"
)

func cloudNASProgramDataDir() string {
	pd := os.Getenv("ProgramData")
	if pd == "" {
		pd = `C:\ProgramData`
	}
	return pd
}

func isSidecarProcessRunning(pid int) bool {
	handle, err := windows.OpenProcess(windows.PROCESS_QUERY_LIMITED_INFORMATION, false, uint32(pid))
	if err != nil {
		return false
	}
	defer windows.CloseHandle(handle)

	var exitCode uint32
	if err := windows.GetExitCodeProcess(handle, &exitCode); err != nil {
		return false
	}
	const stillActive = 259
	return exitCode == stillActive
}

func readFileOS(path string) ([]byte, error) {
	return os.ReadFile(path)
}

func isNotExistOS(err error) bool {
	return errors.Is(err, os.ErrNotExist)
}
