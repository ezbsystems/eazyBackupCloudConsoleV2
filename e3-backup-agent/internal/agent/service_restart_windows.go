//go:build windows

package agent

// requestServiceRestart asks the OS service manager to cycle the agent service
// so it reloads agent.conf from disk. The agent service runs as LocalSystem, so
// it can restart itself via an ephemeral SYSTEM scheduled task even when the
// re-enrollment that changed agent.conf was performed by a non-elevated UI
// (e.g. the tray, whose sc.exe stop/start is rejected without elevation).
func requestServiceRestart() error {
	return triggerAgentServiceRestart()
}
