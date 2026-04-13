//go:build !windows

package agent

import (
	"context"
	"fmt"
)

func mapNASDriveViaTray(_ context.Context, _, _, _ string, _ int) error {
	return fmt.Errorf("Cloud NAS tray control is only supported on Windows")
}

func unmapNASDriveViaTray(_ context.Context, _ string) error {
	return fmt.Errorf("Cloud NAS tray control is only supported on Windows")
}

func registerPreparedNASDriveViaTray(_ context.Context, _ int64, _, _, _ string, _ int, _ string) error {
	return fmt.Errorf("Cloud NAS tray control is only supported on Windows")
}

func unregisterPreparedNASDriveViaTray(_ context.Context, _ string) error {
	return fmt.Errorf("Cloud NAS tray control is only supported on Windows")
}
