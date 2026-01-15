//go:build !windows
// +build !windows

package agent

import (
	"context"
	"log"
)

// executeHyperVRestoreCommand is not supported on non-Windows platforms.
func (r *Runner) executeHyperVRestoreCommand(ctx context.Context, cmd PendingCommand) {
	log.Printf("agent: hyperv_restore command %d not supported on this platform", cmd.CommandID)
	_ = r.client.CompleteCommand(cmd.CommandID, "failed", "hyperv_restore is only supported on Windows")
}

