package mount

import (
	"context"
	"fmt"

	"github.com/ezbsystems/e3-cloudnas/internal/api"
)

type unavailableMounter struct {
	reason error
}

func NewUnavailableMounter(reason error) Mounter {
	return &unavailableMounter{reason: reason}
}

func (m *unavailableMounter) Mount(_ context.Context, _ api.MountRequest) error {
	return fmt.Errorf("WinFsp mount unavailable: %w", m.reason)
}

func (m *unavailableMounter) Unmount(_ context.Context, _ string) error {
	return fmt.Errorf("WinFsp mount unavailable: %w", m.reason)
}

func (m *unavailableMounter) List() []api.MountStatus {
	return nil
}
