package kopia

import (
	"context"
	"fmt"
	"strings"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/kopia/kopia/repo/manifest"
	"github.com/kopia/kopia/snapshot"
	"github.com/kopia/kopia/snapshot/snapshotfs"
)

// PriorSnapshotRoot loads a snapshot root as a read-only fs.Directory (metadata only; content read on Open).
func PriorSnapshotRoot(ctx context.Context, storage StorageOptions, repoConfig, manifestID string) (kopiafs.Directory, error) {
	if strings.TrimSpace(manifestID) == "" {
		return nil, fmt.Errorf("manifest_id required")
	}
	rep, err := openRepository(ctx, storage, repoConfig)
	if err != nil {
		return nil, err
	}
	defer rep.Close(ctx)

	man, err := snapshot.LoadSnapshot(ctx, rep, manifest.ID(manifestID))
	if err != nil {
		return nil, fmt.Errorf("load snapshot: %w", err)
	}
	rootEntry, err := snapshotfs.SnapshotRoot(rep, man)
	if err != nil {
		return nil, fmt.Errorf("snapshot root: %w", err)
	}
	root, ok := rootEntry.(kopiafs.Directory)
	if !ok {
		return nil, fmt.Errorf("snapshot root is not a directory")
	}
	return root, nil
}
