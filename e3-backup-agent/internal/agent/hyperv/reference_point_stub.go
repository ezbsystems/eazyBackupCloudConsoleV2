//go:build !windows
// +build !windows

package hyperv

import (
	"context"
	"fmt"
	"time"
)

// ReferencePointInfo identifies a Hyper-V RCT reference point.
type ReferencePointInfo struct {
	InstanceID   string
	WMIPath      string
	VMID         string
	CreationTime time.Time
}

// HostHasReferencePointService is always false off-Windows.
func HostHasReferencePointService(ctx context.Context) bool {
	return false
}

// RefPointConsistency mirrors the Windows-only enum.
type RefPointConsistency uint8

const (
	RefPointApplication RefPointConsistency = 1
	RefPointCrash       RefPointConsistency = 0
)

// CreateReferencePoint is unsupported off-Windows.
func (m *Manager) CreateReferencePoint(ctx context.Context, vmName string) (*ReferencePointInfo, error) {
	return nil, fmt.Errorf("hyper-v reference points are only supported on Windows")
}

// CreateReferencePointWithConsistency is unsupported off-Windows.
func (m *Manager) CreateReferencePointWithConsistency(ctx context.Context, vmName string, consistency RefPointConsistency) (*ReferencePointInfo, error) {
	return nil, fmt.Errorf("hyper-v reference points are only supported on Windows")
}

// DestroyReferencePoint is unsupported off-Windows.
func (m *Manager) DestroyReferencePoint(ctx context.Context, refPointInstanceID string) error {
	return fmt.Errorf("hyper-v reference points are only supported on Windows")
}

// ListReferencePoints is unsupported off-Windows.
func (m *Manager) ListReferencePoints(ctx context.Context, vmName string) ([]ReferencePointInfo, error) {
	return nil, fmt.Errorf("hyper-v reference points are only supported on Windows")
}

// ReferencePointDiskRCTIDs is unsupported off-Windows.
func (m *Manager) ReferencePointDiskRCTIDs(ctx context.Context, refPointInstanceID string) (map[string]string, error) {
	return nil, fmt.Errorf("hyper-v reference points are only supported on Windows")
}
