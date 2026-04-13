//go:build !windows

package agent

import "fmt"

func mapNASDriveInteractiveUserWTS(_, _ string) error {
	return fmt.Errorf("NAS user-session mapping is only supported on Windows")
}

func unmapNASDriveInteractiveUserWTS(_ string) {}

func configureWebClientForLargeFiles() {}
