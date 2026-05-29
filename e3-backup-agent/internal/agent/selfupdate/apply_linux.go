//go:build linux

package selfupdate

import (
	"fmt"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"syscall"
)

// apply replaces the running agent binary with the freshly downloaded one and
// triggers a detached `systemctl restart`. The documented Linux deployment uses
// a systemd unit (e3-backup-agent.service) with Restart=always, so even if the
// explicit restart is missed, systemd will bring the new binary up after this
// process exits.
//
// The swap is done with an atomic os.Rename within the same directory as the
// target executable (guaranteeing the same filesystem). A running ELF binary
// can be replaced via rename while executing because the kernel keeps the old
// inode open until the process exits.
func apply(artifactPath string, spec Spec) error {
	target, err := os.Executable()
	if err != nil {
		return fmt.Errorf("resolve executable: %w", err)
	}
	if resolved, rerr := filepath.EvalSymlinks(target); rerr == nil {
		target = resolved
	}

	staged := filepath.Join(filepath.Dir(target), ".e3-backup-agent.new")
	if err := copyFile(artifactPath, staged, 0o755); err != nil {
		return fmt.Errorf("stage new binary: %w", err)
	}
	if err := os.Rename(staged, target); err != nil {
		_ = os.Remove(staged)
		return fmt.Errorf("swap binary: %w", err)
	}

	if err := scheduleRestart(); err != nil {
		// Non-fatal: systemd Restart=always will recover after this process exits.
		return nil
	}
	return nil
}

// scheduleRestart spawns a fully detached shell that waits briefly (so this
// process can finish reporting/exiting) and then restarts the service.
func scheduleRestart() error {
	systemctl := "systemctl"
	if p, err := exec.LookPath("systemctl"); err == nil {
		systemctl = p
	}
	script := fmt.Sprintf("sleep 2; %s restart e3-backup-agent", systemctl)
	cmd := exec.Command("/bin/sh", "-c", script)
	cmd.SysProcAttr = &syscall.SysProcAttr{Setsid: true}
	cmd.Stdin = nil
	cmd.Stdout = nil
	cmd.Stderr = nil
	return cmd.Start()
}

func copyFile(src, dst string, mode os.FileMode) error {
	in, err := os.Open(src)
	if err != nil {
		return err
	}
	defer in.Close()

	out, err := os.OpenFile(dst, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, mode)
	if err != nil {
		return err
	}
	if _, err := io.Copy(out, in); err != nil {
		_ = out.Close()
		return err
	}
	if err := out.Chmod(mode); err != nil {
		_ = out.Close()
		return err
	}
	return out.Close()
}
