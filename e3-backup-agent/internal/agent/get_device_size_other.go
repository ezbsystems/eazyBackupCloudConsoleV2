//go:build !windows
// +build !windows

package agent

import "fmt"

// getDeviceSizeWindows is a stub for non-Windows builds.
func getDeviceSizeWindows(path string) (int64, error) {
	return 0, fmt.Errorf("getDeviceSizeWindows unsupported on this platform")
}
