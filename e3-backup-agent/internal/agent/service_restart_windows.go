//go:build windows

package agent

import (
	"fmt"
	"os"
)

// requestServiceRestart cycles the agent service so it reloads agent.conf from
// disk. The agent service runs as LocalSystem, so it can restart itself even
// when the re-enrollment that changed agent.conf was performed by a
// non-elevated UI (e.g. the tray, whose sc.exe stop/start is rejected without
// elevation).
//
// Primary path: spawn a detached copy of ourselves with "-service restart".
// This reuses the exact, proven stop+wait+start sequence the installer runs
// post-upgrade (cmd/agent/main.go), driven through the SCM via
// kardianos/service. The child must break away from the service's job object
// so it survives the parent service stopping; runDetached sets the required
// CREATE_BREAKAWAY_FROM_JOB / DETACHED_PROCESS flags.
//
// Fallback: if we cannot resolve/launch our own executable, fall back to the
// SYSTEM scheduled-task + PowerShell restart used by the remote reset_agent
// command.
func requestServiceRestart(configPath string) error {
	exe, err := os.Executable()
	if err == nil {
		args := []string{"-service", "restart"}
		if configPath != "" {
			args = append(args, "-config", configPath)
		}
		if serr := runDetached(exe, args...); serr == nil {
			return nil
		} else {
			err = serr
		}
	}

	if terr := triggerAgentServiceRestart(); terr != nil {
		if err == nil {
			err = terr
		}
		return fmt.Errorf("service restart failed (detached exec: %v; scheduled task: %v)", err, terr)
	}
	return nil
}
