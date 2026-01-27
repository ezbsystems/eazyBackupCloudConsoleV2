//go:build windows
// +build windows

package agent

// getDeviceSizeLinux is a stub for Windows builds.
// The Windows code path uses getDeviceSizeWindows instead.
func getDeviceSizeLinux(path string) int64 {
	return 0
}
