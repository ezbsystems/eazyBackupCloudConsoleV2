package agent

import (
	"context"
	"errors"
	"fmt"
	"os"
	"strings"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/snapshot"
	"github.com/kopia/kopia/snapshot/snapshotfs"
)

type previousSnapshot struct {
	Entry      kopiafs.Entry
	ManifestID string
	closeFn    func()
}

func (p *previousSnapshot) Close() {
	if p != nil && p.closeFn != nil {
		p.closeFn()
	}
}

func (r *Runner) openPreviousDiskImageSnapshot(ctx context.Context, run *NextRunResponse, stableSourcePath string) (*previousSnapshot, error) {
	if run == nil {
		return nil, fmt.Errorf("missing run context")
	}
	if strings.TrimSpace(stableSourcePath) == "" {
		return nil, fmt.Errorf("stable source path empty")
	}

	opts := kopiaOptionsFromRun(r.cfg, run)
	repoPath := kopiaRepoConfigPath(r.cfg, run)
	password := opts.password()

	if _, err := os.Stat(repoPath); err != nil {
		if os.IsNotExist(err) {
			st, stErr := opts.storage(ctx)
			if stErr != nil {
				return nil, stErr
			}
			if err := repo.Connect(ctx, repoPath, st, password, nil); err != nil {
				if errors.Is(err, repo.ErrRepositoryNotInitialized) || strings.Contains(strings.ToLower(err.Error()), "repository not initialized") {
					return nil, nil
				}
				return nil, err
			}
		} else {
			return nil, err
		}
	}

	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		if errors.Is(err, repo.ErrRepositoryNotInitialized) || strings.Contains(strings.ToLower(err.Error()), "repository not initialized") {
			return nil, nil
		}
		return nil, err
	}

	srcInfo := snapshot.SourceInfo{
		Host:     opts.host,
		UserName: opts.username,
		Path:     stableSourcePath,
	}

	snaps, err := snapshot.ListSnapshots(ctx, rep, srcInfo)
	if err != nil || len(snaps) == 0 {
		rep.Close(ctx)
		return nil, err
	}

	var newest *snapshot.Manifest
	for _, s := range snaps {
		if newest == nil || s.StartTime.After(newest.StartTime) {
			newest = s
		}
	}
	if newest == nil {
		rep.Close(ctx)
		return nil, fmt.Errorf("no snapshots found")
	}

	man, err := snapshot.LoadSnapshot(ctx, rep, newest.ID)
	if err != nil {
		rep.Close(ctx)
		return nil, err
	}

	entry := snapshotfs.EntryFromDirEntry(rep, man.RootEntry)
	if entry == nil {
		rep.Close(ctx)
		return nil, fmt.Errorf("snapshot root entry missing")
	}

	closeFn := func() {
		_ = rep.Close(context.Background())
	}

	return &previousSnapshot{
		Entry:      entry,
		ManifestID: string(newest.ID),
		closeFn:    closeFn,
	}, nil
}
