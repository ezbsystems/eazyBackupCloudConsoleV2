//go:build !windows && !linux

package agent

import (
	"context"
	"errors"
)

// createDiskImage stub for unsupported platforms.
func (r *Runner) createDiskImage(ctx context.Context, run *NextRunResponse, opts diskImageOptions) (*diskImageResult, error) {
	return nil, errors.New("disk image creation not implemented for this platform")
}

