//go:build !windows

package agent

import (
	"context"
	"fmt"
)

func registerPreparedNASDriveViaTray(_ context.Context, _ int64, _, _, _ string) error {
	return fmt.Errorf("Cloud NAS tray control is only supported on Windows")
}

func unregisterPreparedNASDriveViaTray(_ context.Context, _ string) error {
	return fmt.Errorf("Cloud NAS tray control is only supported on Windows")
}
