package kopia

import (
	"context"
	"fmt"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

// Snapshot writes a snapshot via pool (convenience wrapper).
func Snapshot(ctx context.Context, pool *Pool, req SnapshotRequest) (*SnapshotResult, error) {
	if pool == nil {
		return nil, fmt.Errorf("kopia pool required")
	}
	return pool.Snapshot(ctx, req)
}

type SnapshotRequest struct {
	Storage            StorageOptions
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
