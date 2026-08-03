package kopia

import (
	"context"
	"os"
	"path/filepath"
	"testing"

	"github.com/kopia/kopia/repo"
)

type maintenanceCountRepo struct {
	trackCloseRepo
}

func TestRunManagedMaintenanceFullSkipsColdCacheBelowThreshold(t *testing.T) {
	tmp := t.TempDir()
	pool := NewPool(RepoCacheSettings{RepoConfigDir: tmp, ContentCacheSizeMiB: 64})
	storage := StorageOptions{Bucket: "cold-full", Endpoint: "http://s3.example"}

	withTestRepositoryOpener(t, func(ctx context.Context, opts openRepoOptions) (repo.Repository, error) {
		return &maintenanceCountRepo{}, nil
	})

	outcome, err := RunManagedMaintenance(context.Background(), pool, storage, 64, false, 5000, nil)
	if err != nil {
		t.Fatalf("RunManagedMaintenance: %v", err)
	}
	if !outcome.Skipped {
		t.Fatalf("expected skipped full maintenance on cold cache, got %+v", outcome)
	}
	if outcome.IndexBlobsBefore != 0 || outcome.IndexBlobsAfter != 0 {
		t.Fatalf("unexpected index counts: %+v", outcome)
	}

	var phases []string
	_, err = RunManagedMaintenance(context.Background(), pool, storage, 64, false, 5000, func(phase string, _ map[string]any) {
		phases = append(phases, phase)
	})
	if err != nil {
		t.Fatalf("RunManagedMaintenance with progress: %v", err)
	}
	if phases[0] != "pre_open" || phases[len(phases)-1] != "complete" {
		t.Fatalf("expected pre_open..complete phases on skip, got %v", phases)
	}
}

func TestRunManagedMaintenanceFullRunsWhenIndexCountAboveThreshold(t *testing.T) {
	tmp := t.TempDir()
	pool := NewPool(RepoCacheSettings{RepoConfigDir: tmp, ContentCacheSizeMiB: 64})
	storage := StorageOptions{Bucket: "full-needed", Endpoint: "http://s3.example"}
	cacheDir := filepath.Join(tmp, "cache", storage.repoHash(), "indexes")
	if err := os.MkdirAll(cacheDir, 0o755); err != nil {
		t.Fatal(err)
	}
	for i := 0; i < 5001; i++ {
		if err := os.WriteFile(filepath.Join(cacheDir, "index-"+itoa(i)), []byte("x"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	withTestRepositoryOpener(t, func(ctx context.Context, opts openRepoOptions) (repo.Repository, error) {
		return &maintenanceCountRepo{}, nil
	})

	outcome, err := RunManagedMaintenance(context.Background(), pool, storage, 64, false, 5000, nil)
	if err == nil {
		t.Fatalf("expected maintenance error from mock repo, got outcome=%+v", outcome)
	}
	if outcome.Skipped {
		t.Fatalf("should not skip when index count is above threshold: %+v", outcome)
	}
	if outcome.IndexBlobsBefore < 5000 {
		t.Fatalf("expected post-open index count >= threshold, got %d", outcome.IndexBlobsBefore)
	}
}

func itoa(n int) string {
	if n == 0 {
		return "0"
	}
	var digits []byte
	for n > 0 {
		digits = append([]byte{byte('0' + n%10)}, digits...)
		n /= 10
	}
	return string(digits)
}
