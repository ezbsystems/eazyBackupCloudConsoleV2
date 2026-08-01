package kopia

import (
	"context"
	"path/filepath"
	"testing"

	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/repo/blob/filesystem"
	"github.com/kopia/kopia/repo/format"
)

// newLocalTestRepoConfig initializes a real (local filesystem-backed) Kopia
// repo and connects it, returning the on-disk config path. This exercises the
// same LoadConfigFromFile/writeToFile machinery SetCachingOptions uses,
// without depending on network/S3 access.
func newLocalTestRepoConfig(t *testing.T) (repoConfig string, password string) {
	t.Helper()
	ctx := context.Background()
	root := t.TempDir()
	blobDir := filepath.Join(root, "blob")
	repoConfig = filepath.Join(root, "repo.config")
	password = "test-password-1234"

	st, err := filesystem.New(ctx, &filesystem.Options{Path: blobDir}, true)
	if err != nil {
		t.Fatalf("filesystem storage: %v", err)
	}
	initOpts := &repo.NewRepositoryOptions{
		BlockFormat: format.ContentFormat{
			MutableParameters: format.MutableParameters{MaxPackSize: 32 << 20},
		},
	}
	if err := repo.Initialize(ctx, st, initOpts, password); err != nil {
		t.Fatalf("initialize: %v", err)
	}
	if err := repo.Connect(ctx, repoConfig, st, password, nil); err != nil {
		t.Fatalf("connect: %v", err)
	}
	return repoConfig, password
}

// TestReconcileCacheSettingsPersistsHardLimits is the regression test for the
// broken Repository.SetCachingOptions type-assert (Kopia v0.23.1 only exposes
// this as the package-level repo.SetCachingOptions(ctx, configFile, opt)). It
// grew the on-disk contents cache to ~53GiB on prod worker 9008 because
// LimitBytes silently stayed 0 (unlimited).
func TestReconcileCacheSettingsPersistsHardLimits(t *testing.T) {
	repoConfig, _ := newLocalTestRepoConfig(t)
	ctx := context.Background()

	settings := RepoCacheSettings{RepoConfigDir: t.TempDir(), ContentCacheSizeMiB: 512}
	storage := StorageOptions{Bucket: "test-bucket", Endpoint: "http://s3.example"}
	caching := settings.cachingOptions(storage)

	if err := reconcileCacheSettings(ctx, repoConfig, caching); err != nil {
		t.Fatalf("reconcileCacheSettings: %v", err)
	}

	persisted, err := repo.GetCachingOptions(ctx, repoConfig)
	if err != nil {
		t.Fatalf("GetCachingOptions: %v", err)
	}

	const gib = int64(1) << 30
	if persisted.ContentCacheSizeLimitBytes < 2*gib || persisted.ContentCacheSizeLimitBytes > 4*gib {
		t.Fatalf("expected persisted content hard limit in 2-4GiB band, got %d bytes", persisted.ContentCacheSizeLimitBytes)
	}
	if persisted.MetadataCacheSizeLimitBytes <= 0 {
		t.Fatalf("expected persisted metadata hard limit set, got %d bytes", persisted.MetadataCacheSizeLimitBytes)
	}
	if persisted.ContentCacheSizeLimitBytes != caching.ContentCacheSizeLimitBytes {
		t.Fatalf("persisted content hard limit %d does not match computed %d",
			persisted.ContentCacheSizeLimitBytes, caching.ContentCacheSizeLimitBytes)
	}
	if persisted.MetadataCacheSizeLimitBytes != caching.MetadataCacheSizeLimitBytes {
		t.Fatalf("persisted metadata hard limit %d does not match computed %d",
			persisted.MetadataCacheSizeLimitBytes, caching.MetadataCacheSizeLimitBytes)
	}
}

// TestOpenRepositoryPersistsHardLimitsOnExistingConfig covers the exact
// production shape: a repo config that already exists (soft maxCacheSize
// only, no hard limit, as written by older worker versions) must gain
// contentCacheSizeLimitBytes/metadataCacheSizeLimitBytes the next time the
// repo is opened, before repo.Open constructs the PersistentCache.
func TestOpenRepositoryPersistsHardLimitsOnExistingConfig(t *testing.T) {
	repoConfig, _ := newLocalTestRepoConfig(t)
	ctx := context.Background()

	before, err := repo.GetCachingOptions(ctx, repoConfig)
	if err != nil {
		t.Fatalf("GetCachingOptions before: %v", err)
	}
	if before.ContentCacheSizeLimitBytes != 0 {
		t.Fatalf("expected freshly connected repo to have no hard limit yet, got %d", before.ContentCacheSizeLimitBytes)
	}

	settings := RepoCacheSettings{RepoConfigDir: t.TempDir(), ContentCacheSizeMiB: 512}
	storage := StorageOptions{Bucket: "test-bucket", Endpoint: "http://s3.example"}
	caching := settings.cachingOptions(storage)

	if err := reconcileCacheSettings(ctx, repoConfig, caching); err != nil {
		t.Fatalf("reconcileCacheSettings: %v", err)
	}

	after, err := repo.GetCachingOptions(ctx, repoConfig)
	if err != nil {
		t.Fatalf("GetCachingOptions after: %v", err)
	}
	if after.ContentCacheSizeLimitBytes <= 0 {
		t.Fatal("expected hard limit to be persisted on an already-existing config")
	}
}
