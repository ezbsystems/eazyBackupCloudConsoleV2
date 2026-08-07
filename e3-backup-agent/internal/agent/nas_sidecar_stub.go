//go:build !windows

package agent

import (
	"errors"
	"os"
	"syscall"
)

func cloudNASProgramDataDir() string {
	return os.Getenv("ProgramData")
}

func isSidecarProcessRunning(pid int) bool {
	if pid <= 0 {
		return false
	}
	return syscall.Kill(pid, syscall.Signal(0)) == nil
}

func readFileOS(path string) ([]byte, error) {
	return os.ReadFile(path)
}

func isNotExistOS(err error) bool {
	return errors.Is(err, os.ErrNotExist)
}
