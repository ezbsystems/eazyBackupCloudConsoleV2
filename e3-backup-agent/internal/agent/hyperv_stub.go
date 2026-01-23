//go:build !windows
// +build !windows

package agent

import (
	"context"
	"fmt"
)

// runHyperV is not supported on non-Windows platforms.
func (r *Runner) runHyperV(run *NextRunResponse) error {
	return fmt.Errorf("hyperv backup engine is only supported on Windows")
}

// executeListHypervVMsCommand is not supported on non-Windows platforms.
func (r *Runner) executeListHypervVMsCommand(ctx context.Context, cmd PendingCommand) {
	_ = r.client.CompleteCommand(cmd.CommandID, "failed", "Hyper-V is only supported on Windows")
}

// executeListHypervVMDetailsCommand is not supported on non-Windows platforms.
func (r *Runner) executeListHypervVMDetailsCommand(ctx context.Context, cmd PendingCommand) {
	_ = r.client.CompleteCommand(cmd.CommandID, "failed", "Hyper-V is only supported on Windows")
}
