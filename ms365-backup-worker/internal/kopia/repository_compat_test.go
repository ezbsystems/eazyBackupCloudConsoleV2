//go:build integration

package kopia_test

import (
	"context"
	"os"
	"strings"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
	"github.com/kopia/kopia/snapshot"
)

func testStorage(t *testing.T) kopia.StorageOptions {
	t.Helper()
	endpoint := strings.TrimSpace(os.Getenv("KOPIA_TEST_ENDPOINT"))
	bucket := strings.TrimSpace(os.Getenv("KOPIA_TEST_BUCKET"))
	prefix := strings.TrimSpace(os.Getenv("KOPIA_TEST_PREFIX"))
	accessKey := strings.TrimSpace(os.Getenv("KOPIA_TEST_ACCESS_KEY"))
	secretKey := strings.TrimSpace(os.Getenv("KOPIA_TEST_SECRET_KEY"))
	password := strings.TrimSpace(os.Getenv("KOPIA_TEST_PASSWORD"))
	if endpoint == "" || bucket == "" || prefix == "" || accessKey == "" || secretKey == "" {
		t.Skip("KOPIA_TEST_* env vars not set (need endpoint, bucket, prefix, access key, secret key)")
	}
	return kopia.StorageOptions{
		Endpoint:     endpoint,
		Bucket:       bucket,
		Prefix:       prefix,
		AccessKey:    accessKey,
		SecretKey:    secretKey,
		RepoPassword: password,
	}
}

func TestRepositoryCompatOpenListBrowseExtract(t *testing.T) {
	storage := testStorage(t)
	ctx := context.Background()
	tmp := t.TempDir()

	pool := kopia.NewPool(kopia.RepoCacheSettings{
		RepoConfigDir:           tmp,
		ContentCacheSizeMiB:     64,
		ContentCacheLimitMiB:    128,
		MetadataCacheSizeMiB:    32,
		MetadataCacheLimitMiB:   64,
		MinIndexSweepAgeSeconds: 3600,
	})

	rep, release, err := pool.Acquire(ctx, storage, 32)
	if err != nil {
		t.Fatalf("Acquire: %v", err)
	}
	defer release()

	sources, err := snapshot.ListSources(ctx, rep)
	if err != nil {
		t.Fatalf("ListSources: %v", err)
	}
	if len(sources) == 0 {
		t.Skip("no snapshot sources in test repo")
	}

	snaps, err := snapshot.ListSnapshots(ctx, rep, sources[0])
	if err != nil {
		t.Fatalf("ListSnapshots: %v", err)
	}
	if len(snaps) == 0 {
		t.Skip("no snapshots for first source")
	}
	manifestID := string(snaps[0].ID)

	result, err := pool.Browse(ctx, kopia.BrowseRequest{
		Storage:    storage,
		ManifestID: manifestID,
		Path:       "",
		Limit:      10,
	})
	if err != nil {
		t.Fatalf("Browse: %v", err)
	}
	if len(result.Entries) == 0 {
		t.Fatal("expected browse entries at snapshot root")
	}

	// Incremental snapshot with a tiny overlay tree.
	entry := graphfs.BuildTree("ms365", map[string][]byte{
		"kopia-compat-test/ping.txt": []byte("pong " + time.Now().UTC().Format(time.RFC3339)),
	})
	snapRes, err := pool.Snapshot(ctx, kopia.SnapshotRequest{
		Storage:        storage,
		SourcePath:     "/ms365",
		Entry:          entry,
		MaxPackSizeMiB: 32,
		Parallel:       2,
	})
	if err != nil {
		t.Fatalf("Snapshot: %v", err)
	}
	if snapRes.ManifestID == "" {
		t.Fatal("expected manifest ID from incremental snapshot")
	}

	if err := kopia.RunMaintenance(ctx, pool, storage, 32, true); err != nil {
		t.Fatalf("RunMaintenance quick: %v", err)
	}
}
