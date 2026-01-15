//go:build !windows && !linux
// +build !windows,!linux

package agent

// enumerateVolumes is a stub for unsupported platforms.
func enumerateVolumes() ([]VolumeInfo, error) {
	return []VolumeInfo{}, nil
}

