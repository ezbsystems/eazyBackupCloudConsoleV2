//go:build !windows

package agent

import "fmt"

func triggerAgentServiceRestart() error {
	return fmt.Errorf("reset_agent reboot command is only supported on windows")
}
