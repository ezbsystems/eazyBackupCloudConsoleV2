//go:build !windows

package agent

import (
	"os/exec"
	"syscall"
)

// requestServiceRestart asks systemd to cycle the agent service so it reloads
// agent.conf from disk. It spawns a fully detached shell that waits briefly
// (so this process can finish reporting and exit cleanly on the resulting
// SIGTERM) and then restarts the unit. The documented systemd unit runs as
// root with Restart=always, so the restart succeeds and the new process loads
// the freshly written credentials.
func requestServiceRestart(configPath string) error {
	_ = configPath // systemd identifies the unit by name, not by config path
	systemctl := "systemctl"
	if p, err := exec.LookPath("systemctl"); err == nil {
		systemctl = p
	}
	cmd := exec.Command("/bin/sh", "-c", "sleep 2; "+systemctl+" restart e3-backup-agent")
	cmd.SysProcAttr = &syscall.SysProcAttr{Setsid: true}
	cmd.Stdin = nil
	cmd.Stdout = nil
	cmd.Stderr = nil
	return cmd.Start()
}
