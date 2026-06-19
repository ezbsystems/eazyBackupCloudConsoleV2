package kopia

import (
	"context"
	"fmt"
	"strings"

	kopiafs "github.com/kopia/kopia/fs"
)

// PriorSnapshotRoot loads a snapshot root as a read-only fs.Directory (metadata only; content read on Open).
func PriorSnapshotRoot(ctx context.Context, pool *Pool, storage StorageOptions, manifestID string) (kopiafs.Directory, error) {
	if strings.TrimSpace(manifestID) == "" {
		return nil, fmt.Errorf("manifest_id required")
	}
	if pool == nil {
		return nil, fmt.Errorf("kopia pool required")
	}
	return pool.PriorSnapshotRoot(ctx, storage, manifestID)
}
