//go:build windows

package selfupdate

import (
	"fmt"
	"os/exec"
	"path/filepath"
	"syscall"
	"time"
)

// apply runs the downloaded signed Inno installer silently via a one-shot
// SYSTEM scheduled task. This mirrors the proven out-of-process restart pattern
// in internal/agent/reset_agent_windows.go: the running service cannot replace
// its own EXE, so an external SYSTEM process drives stop -> swap -> restart. The
// installer's PrepareToInstall stops the tray and service, swaps the binaries,
// and runs `-service restart`, which is why this is the most reliable path.
func apply(artifactPath string, spec Spec) error {
	logPath := filepath.Join(filepath.Dir(artifactPath), "update-install.log")
	// /VERYSILENT      - no wizard UI
	// /SUPPRESSMSGBOXES - no blocking dialogs
	// /NORESTART       - never reboot the machine
	// /LOG             - capture installer output for diagnostics
	installCmd := fmt.Sprintf(`"%s" /VERYSILENT /SUPPRESSMSGBOXES /NORESTART /LOG="%s"`, artifactPath, logPath)

	if err := scheduleInstallTask(installCmd); err == nil {
		return nil
	} else if derr := runDetachedInstaller(artifactPath, logPath); derr == nil {
		return nil
	} else {
		return fmt.Errorf("schedule task failed (%v) and detached launch failed (%v)", err, derr)
	}
}

// scheduleInstallTask registers and immediately runs a one-shot SYSTEM task that
// executes the installer. Running as SYSTEM avoids a UAC prompt and ensures the
// installer has the privilege to stop/replace the service.
func scheduleInstallTask(installCmd string) error {
	taskName := fmt.Sprintf("E3BackupAgentUpdate_%d", time.Now().UTC().UnixNano())
	start := time.Now().Add(1 * time.Minute).Format("15:04")

	if err := runCommand(
		"schtasks.exe",
		"/Create", "/F",
		"/TN", taskName,
		"/SC", "ONCE",
		"/ST", start,
		"/RL", "HIGHEST",
		"/RU", "SYSTEM",
		"/TR", installCmd,
	); err != nil {
		return fmt.Errorf("create update task failed: %w", err)
	}
	if err := runCommand("schtasks.exe", "/Run", "/TN", taskName); err != nil {
		_ = runCommand("schtasks.exe", "/Delete", "/F", "/TN", taskName)
		return fmt.Errorf("run update task failed: %w", err)
	}
	// The task self-removes after running; best-effort cleanup of the registration.
	_ = runCommand("schtasks.exe", "/Delete", "/F", "/TN", taskName)
	return nil
}

// runDetachedInstaller is a fallback if scheduling a SYSTEM task is unavailable.
// The agent service already runs as LocalSystem, so a detached child inherits
// sufficient privilege to drive the installer.
func runDetachedInstaller(artifactPath, logPath string) error {
	cmd := exec.Command(artifactPath, "/VERYSILENT", "/SUPPRESSMSGBOXES", "/NORESTART", "/LOG="+logPath)
	const detachedProcess = 0x00000008
	const breakawayFromJob = 0x01000000
	const createNoWindow = 0x08000000
	cmd.SysProcAttr = &syscall.SysProcAttr{
		CreationFlags: detachedProcess | breakawayFromJob | createNoWindow | syscall.CREATE_NEW_PROCESS_GROUP,
		HideWindow:    true,
	}
	return cmd.Start()
}

func runCommand(name string, args ...string) error {
	cmd := exec.Command(name, args...)
	out, err := cmd.CombinedOutput()
	if err != nil {
		return fmt.Errorf("%s failed: %w (output: %s)", name, err, string(out))
	}
	return nil
}
