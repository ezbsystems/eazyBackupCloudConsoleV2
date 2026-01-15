//go:build !windows
// +build !windows

package hyperv

import (
	"context"
	"fmt"
)

// RCTEngine handles Resilient Change Tracking operations.
// This is a stub for non-Windows platforms.
type RCTEngine struct {
	manager *Manager
}

// NewRCTEngine creates a new RCT engine.
func NewRCTEngine(mgr *Manager) *RCTEngine {
	return &RCTEngine{manager: mgr}
}

// GetChangedBlocks returns changed block ranges since the reference checkpoint.
func (e *RCTEngine) GetChangedBlocks(ctx context.Context, vmName string, baseCheckpointID string) ([]RCTInfo, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

// GetCurrentRCTIDs returns current RCT tracking IDs for all disks of a VM.
func (e *RCTEngine) GetCurrentRCTIDs(ctx context.Context, vmName string) (map[string]string, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

// ValidateRCTChain validates that RCT tracking is continuous (no gaps).
func (e *RCTEngine) ValidateRCTChain(ctx context.Context, vmName string, expectedRCTIDs map[string]string) (bool, error) {
	return false, fmt.Errorf("hyper-v is only supported on Windows")
}

// IsRCTEnabled checks if RCT is enabled on all disks of a VM.
func (e *RCTEngine) IsRCTEnabled(ctx context.Context, vmName string) (bool, error) {
	return false, fmt.Errorf("hyper-v is only supported on Windows")
}

// GetDiskRCTStatus returns RCT status for each disk of a VM.
func (e *RCTEngine) GetDiskRCTStatus(ctx context.Context, vmName string) (map[string]bool, error) {
	return nil, fmt.Errorf("hyper-v is only supported on Windows")
}

