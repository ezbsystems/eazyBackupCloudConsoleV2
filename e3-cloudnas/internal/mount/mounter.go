package mount

import (
	"context"

	"github.com/ezbsystems/e3-cloudnas/internal/api"
)

// Mounter is the mount backend interface implemented by Manager and WinFsp backends.
type Mounter interface {
	Mount(ctx context.Context, req api.MountRequest) error
	Unmount(ctx context.Context, driveLetter string) error
	List() []api.MountStatus
}
