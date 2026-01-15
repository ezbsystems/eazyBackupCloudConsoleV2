//go:build !windows
// +build !windows

package hyperv

import (
	"context"
	"fmt"
)

// Manager provides Hyper-V management operations via PowerShell.
// This is a stub for non-Windows platforms.
type Manager struct{}

// NewManager creates a new Hyper-V manager.
func NewManager() *Manager {
	return &Manager{}
}

// ListVMs returns all VMs on the host.
func (m *Manager) ListVMs(ctx context.Context) ([]VMInfo, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

// GetVM returns info for a specific VM by name.
func (m *Manager) GetVM(ctx context.Context, vmName string) (*VMInfo, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

// GetVMByGUID returns info for a specific VM by GUID.
func (m *Manager) GetVMByGUID(ctx context.Context, vmGUID string) (*VMInfo, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

// EnableRCT enables Resilient Change Tracking on a VM's disks.
func (m *Manager) EnableRCT(ctx context.Context, vmName string) error {
	return fmt.Errorf("hyper-v is only supported on Windows")
}

// CreateVSSCheckpoint creates an application-consistent (Production) checkpoint.
func (m *Manager) CreateVSSCheckpoint(ctx context.Context, vmName string) (*CheckpointInfo, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

// CreateReferenceCheckpoint creates a standard checkpoint (crash-consistent).
func (m *Manager) CreateReferenceCheckpoint(ctx context.Context, vmName string) (*CheckpointInfo, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

// CreateLinuxConsistentCheckpoint creates a checkpoint for Linux VMs using fsfreeze.
func (m *Manager) CreateLinuxConsistentCheckpoint(ctx context.Context, vmName string) (*CheckpointInfo, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

// MergeCheckpoint removes a checkpoint, merging it with its parent.
func (m *Manager) MergeCheckpoint(ctx context.Context, vmName, checkpointID string) error {
	return fmt.Errorf("hyper-v is only supported on Windows")
}

// RemoveCheckpoint is an alias for MergeCheckpoint.
func (m *Manager) RemoveCheckpoint(ctx context.Context, vmName, checkpointID string) error {
	return fmt.Errorf("hyper-v is only supported on Windows")
}

// GetCheckpoints returns all checkpoints for a VM.
func (m *Manager) GetCheckpoints(ctx context.Context, vmName string) ([]CheckpointInfo, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

// GetCheckpointDiskPaths returns the VHDX paths for a checkpoint.
func (m *Manager) GetCheckpointDiskPaths(ctx context.Context, vmName, checkpointID string) ([]string, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

