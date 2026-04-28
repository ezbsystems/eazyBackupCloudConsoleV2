package agent

import (
	"os"
	"path/filepath"
	"runtime"
)

// VersionTooOldFlagPath returns the absolute path of the marker file the
// service writes when it has been rejected by the server's strict
// minimum-agent-version gate. The tray watches this path to render a "Please
// update" banner without needing IPC.
func VersionTooOldFlagPath() string {
	if runtime.GOOS == "windows" {
		pd := os.Getenv("ProgramData")
		if pd == "" {
			pd = `C:\ProgramData`
		}
		return filepath.Join(pd, "E3Backup", "state", "agent_version_too_old.flag")
	}
	return "/var/lib/e3-backup-agent/state/agent_version_too_old.flag"
}

// IsVersionTooOldFlagSet reports whether the marker file exists.
func IsVersionTooOldFlagSet() bool {
	_, err := os.Stat(VersionTooOldFlagPath())
	return err == nil
}

// writeVersionTooOldFlag persists the marker to disk.
func writeVersionTooOldFlag(reportedVersion string) {
	p := VersionTooOldFlagPath()
	_ = os.MkdirAll(filepath.Dir(p), 0o755)
	_ = os.WriteFile(p, []byte(reportedVersion+"\n"), 0o644)
}

func init() {
	// Wire the package-level hook used by HealthEmitter / Client.MarkVersionTooOld.
	notifyVersionTooOld = func() {
		writeVersionTooOldFlag("")
	}
}
