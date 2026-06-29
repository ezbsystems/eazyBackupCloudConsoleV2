package kopia

import (
	"context"
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestEvictRepoRespectsRefs(t *testing.T) {
	tmp := t.TempDir()
	pool := NewPool(RepoCacheSettings{RepoConfigDir: tmp, ContentCacheSizeMiB: 64})
	storage := StorageOptions{Bucket: "test-bucket", Prefix: "tenant", Endpoint: "http://s3.example"}
	cacheDir := filepath.Join(tmp, "cache", storage.repoHash())
	if err := os.MkdirAll(cacheDir, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(cacheDir, "blob"), []byte("cached"), 0o644); err != nil {
		t.Fatal(err)
	}

	key := storage.RepoIdentity()
	pool.repos[key] = &poolEntry{refs: 1, opened: time.Now(), cacheDir: cacheDir}

	pool.EvictRepo(context.Background(), storage)
	if _, err := os.Stat(cacheDir); os.IsNotExist(err) {
		t.Fatal("cache dir must not be removed while refs > 0")
	}

	pool.repos[key].refs = 0
	pool.EvictRepo(context.Background(), storage)
	if _, err := os.Stat(cacheDir); !os.IsNotExist(err) {
		t.Fatal("cache dir should be removed when refs == 0")
	}
	if _, ok := pool.repos[key]; ok {
		t.Fatal("pool entry should be removed")
	}
}

func TestEvictIdleRemovesIdleCaches(t *testing.T) {
	tmp := t.TempDir()
	pool := NewPool(RepoCacheSettings{RepoConfigDir: tmp, ContentCacheSizeMiB: 64})

	storages := []StorageOptions{
		{Bucket: "b1", Endpoint: "http://s3"},
		{Bucket: "b2", Endpoint: "http://s3"},
	}
	for _, storage := range storages {
		cacheDir := filepath.Join(tmp, "cache", storage.repoHash())
		if err := os.MkdirAll(cacheDir, 0o755); err != nil {
			t.Fatal(err)
		}
		key := storage.RepoIdentity()
		pool.repos[key] = &poolEntry{refs: 0, opened: time.Now(), cacheDir: cacheDir}
	}

	pool.EvictIdle(context.Background())

	for _, storage := range storages {
		cacheDir := filepath.Join(tmp, "cache", storage.repoHash())
		if _, err := os.Stat(cacheDir); !os.IsNotExist(err) {
			t.Fatalf("expected cache dir removed for %s", storage.RepoIdentity())
		}
	}
	if len(pool.repos) != 0 {
		t.Fatalf("expected empty pool, got %d entries", len(pool.repos))
	}
}
