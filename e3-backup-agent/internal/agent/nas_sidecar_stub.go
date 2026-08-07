//go:build !windows

package agent

import (
	"errors"
	"os"
)

func cloudNASProgramDataDir() string {
	return os.Getenv("ProgramData")
}

func isSidecarProcessRunning(pid int) bool {
	return true
}

func readFileOS(path string) ([]byte, error) {
	return os.ReadFile(path)
}

func isNotExistOS(err error) bool {
	return errors.Is(err, os.ErrNotExist)
}
