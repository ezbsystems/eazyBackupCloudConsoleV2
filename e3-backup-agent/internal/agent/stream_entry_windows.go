//go:build windows
// +build windows

package agent

import (
	"os"
	"syscall"
)

const (
	// Windows file flags for optimized sequential reads
	FILE_FLAG_NO_BUFFERING    = 0x20000000 // kept for future tuning; not used now
	FILE_FLAG_SEQUENTIAL_SCAN = 0x08000000
)

// openDeviceOptimized opens a device with Windows-optimized flags for sequential reads.
// Falls back to a regular os.OpenFile if CreateFile fails.
func openDeviceOptimized(path string) (*os.File, error) {
	pathp, err := syscall.UTF16PtrFromString(path)
	if err != nil {
		return nil, err
	}

	h, err := syscall.CreateFile(
		pathp,
		syscall.GENERIC_READ,
		syscall.FILE_SHARE_READ|syscall.FILE_SHARE_WRITE,
		nil,
		syscall.OPEN_EXISTING,
		syscall.FILE_ATTRIBUTE_NORMAL|FILE_FLAG_SEQUENTIAL_SCAN,
		0,
	)
	if err != nil {
		// Fallback to standard open if CreateFile fails (e.g., permissions)
		return os.OpenFile(path, os.O_RDONLY, 0)
	}

	return os.NewFile(uintptr(h), path), nil
}

