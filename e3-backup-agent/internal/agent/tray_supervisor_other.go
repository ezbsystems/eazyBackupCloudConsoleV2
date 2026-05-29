//go:build !windows

package agent

// startTraySupervisor is a no-op on non-Windows platforms. The Linux agent runs
// headless as a systemd service and has no tray helper.
func (r *Runner) startTraySupervisor(stop <-chan struct{}) {}
