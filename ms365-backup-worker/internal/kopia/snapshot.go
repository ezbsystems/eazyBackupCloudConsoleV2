package kopia

import (
	"context"
	"errors"
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/repo/compression"
	"github.com/kopia/kopia/repo/content"
	"github.com/kopia/kopia/snapshot"
	"github.com/kopia/kopia/snapshot/policy"
	snapshotfs "github.com/kopia/kopia/snapshot/snapshotfs"
)

type SnapshotRequest struct {
	Storage            StorageOptions
	RepoConfig         string
	SourcePath         string
	Host               string
	Username           string
	Entry              kopiafs.Entry
	Parallel           int
	Compressor         string
	MaxPackSizeMiB     int
	CheckpointInterval time.Duration
	OnProgress         func(ProgressCounter)
}

type SnapshotResult struct {
	ManifestID    string
	BytesHashed   int64
	BytesUploaded int64
	FilesDone     int64
}

func Snapshot(ctx context.Context, req SnapshotRequest) (*SnapshotResult, error) {
	if req.Entry == nil {
		return nil, fmt.Errorf("kopia: source entry required")
	}
	if req.Parallel <= 0 {
		req.Parallel = 16
	}
	if req.Compressor == "" {
		req.Compressor = "zstd-default"
	}
	if req.MaxPackSizeMiB <= 0 {
		req.MaxPackSizeMiB = 64
	}
	if req.Host == "" {
		req.Host = "ms365-worker"
	}
	if req.Username == "" {
		req.Username = "ms365"
	}
	if req.SourcePath == "" {
		req.SourcePath = "/ms365"
	}

	if err := os.MkdirAll(filepath.Dir(req.RepoConfig), 0o755); err != nil {
		return nil, err
	}

	st, err := req.Storage.Storage(ctx)
	if err != nil {
		return nil, fmt.Errorf("kopia storage init: %w", err)
	}
	password := req.Storage.Password()

	initAndConnect := func() error {
		initOpts := &repo.NewRepositoryOptions{
			BlockFormat: content.FormattingOptions{
				MutableParameters: content.MutableParameters{
					MaxPackSize: req.MaxPackSizeMiB << 20,
				},
			},
		}
		if err := repo.Initialize(ctx, st, initOpts, password); err != nil && !errors.Is(err, repo.ErrAlreadyInitialized) {
			return fmt.Errorf("initialize: %w", err)
		}
		if err := repo.Connect(ctx, req.RepoConfig, st, password, nil); err != nil && !errors.Is(err, repo.ErrAlreadyInitialized) {
			return fmt.Errorf("connect: %w", err)
		}
		return nil
	}

	if _, err := os.Stat(req.RepoConfig); err != nil {
		if os.IsNotExist(err) {
			if err := initAndConnect(); err != nil {
				return nil, err
			}
		} else {
			return nil, fmt.Errorf("stat repo config: %w", err)
		}
	}

	rep, err := repo.Open(ctx, req.RepoConfig, password, nil)
	if err != nil {
		if errors.Is(err, repo.ErrRepositoryNotInitialized) || strings.Contains(strings.ToLower(err.Error()), "not initialized") {
			if err := initAndConnect(); err != nil {
				return nil, err
			}
			rep, err = repo.Open(ctx, req.RepoConfig, password, nil)
		}
		if err != nil {
			return nil, fmt.Errorf("open repo: %w", err)
		}
	}
	defer rep.Close(ctx)

	srcInfo := snapshot.SourceInfo{
		Host:     req.Host,
		UserName: req.Username,
		Path:     req.SourcePath,
	}

	pol, err := policy.TreeForSource(ctx, rep, srcInfo)
	if err != nil {
		return nil, fmt.Errorf("policy: %w", err)
	}
	ep := pol.EffectivePolicy()
	ep.CompressionPolicy.CompressorName = compression.Name(req.Compressor)

	var previousManifests []*snapshot.Manifest
	if snaps, err := snapshot.ListSnapshots(ctx, rep, srcInfo); err == nil {
		previousManifests = snaps
	}

	counter := NewProgressCounter(req.OnProgress)
	manifestID := ""

	uploadErr := repo.WriteSession(ctx, rep, repo.WriteSessionOptions{
		Purpose:  "snapshot",
		OnUpload: counter.UploadedBytes,
	}, func(wctx context.Context, w repo.RepositoryWriter) error {
		u := snapshotfs.NewUploader(w)
		u.Progress = counter
		u.ParallelUploads = req.Parallel
		if req.CheckpointInterval > 0 {
			u.CheckpointInterval = req.CheckpointInterval
		} else {
			u.CheckpointInterval = snapshotfs.DefaultCheckpointInterval
		}
		man, err := u.Upload(wctx, req.Entry, pol, srcInfo, previousManifests...)
		if err != nil {
			return err
		}
		if man == nil {
			return fmt.Errorf("upload returned nil manifest")
		}
		savedID, err := snapshot.SaveSnapshot(wctx, w, man)
		if err != nil {
			return err
		}
		manifestID = string(savedID)
		return nil
	})
	if uploadErr != nil {
		return nil, fmt.Errorf("write session: %w", uploadErr)
	}

	return &SnapshotResult{
		ManifestID:    manifestID,
		BytesHashed:   counter.BytesHashed.Load(),
		BytesUploaded: counter.BytesUploaded.Load(),
		FilesDone:     counter.FilesDone.Load(),
	}, nil
}
