//go:build windows

package agent

import (
	"errors"
	"os"
	"syscall"
)

func cloudNASProgramDataDir() string {
	pd := os.Getenv("ProgramData")
	if pd == "" {
		pd = `C:\ProgramData`
	}
	return pd
}

func isSidecarProcessRunning(pid int) bool {
	handle, err := syscall.OpenProcess(syscall.PROCESS_QUERY_LIMITED_INFORMATION, false, uint32(pid))
	if err != nil {
		return false
	}
	defer syscall.CloseHandle(handle)

	var exitCode uint32
	if err := syscall.GetExitCodeProcess(handle, &exitCode); err != nil {
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
