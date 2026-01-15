//go:build !windows
// +build !windows

package agent

import "os"

// openDeviceOptimized on non-Windows just uses standard open.
func openDeviceOptimized(path string) (*os.File, error) {
	return os.OpenFile(path, os.O_RDONLY, 0)
}

