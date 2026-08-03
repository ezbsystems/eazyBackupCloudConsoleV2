package kopia

import (
	"context"
	"errors"
	"os"
	"path/filepath"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/repo/content"
	"github.com/kopia/kopia/repo/manifest"
	"github.com/kopia/kopia/repo/object"
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

func TestCloseIdleRetainsCache(t *testing.T) {
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
		pool.repos[key] = &poolEntry{refs: 0, opened: time.Now(), cacheDir: cacheDir, rep: &trackCloseRepo{}}
	}

	pool.CloseIdle(context.Background())

	for _, storage := range storages {
		cacheDir := filepath.Join(tmp, "cache", storage.repoHash())
		if _, err := os.Stat(cacheDir); os.IsNotExist(err) {
			t.Fatalf("expected cache dir retained for %s", storage.RepoIdentity())
		}
	}
	if len(pool.repos) != 2 {
		t.Fatalf("expected pool entries retained, got %d", len(pool.repos))
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

type trackCloseRepo struct {
	closed atomic.Bool
}

func (r *trackCloseRepo) OpenObject(context.Context, object.ID) (object.Reader, error) {
	return nil, errors.New("not implemented")
}
func (r *trackCloseRepo) VerifyObject(context.Context, object.ID) ([]content.ID, error) {
	return nil, errors.New("not implemented")
}
func (r *trackCloseRepo) GetManifest(context.Context, manifest.ID, any) (*manifest.EntryMetadata, error) {
	return nil, errors.New("not implemented")
}
func (r *trackCloseRepo) FindManifests(context.Context, map[string]string) ([]*manifest.EntryMetadata, error) {
	return nil, errors.New("not implemented")
}
func (r *trackCloseRepo) ContentInfo(context.Context, content.ID) (content.Info, error) {
	return content.Info{}, errors.New("not implemented")
}
func (r *trackCloseRepo) PrefetchContents(context.Context, []content.ID, string) []content.ID {
	return nil
}
func (r *trackCloseRepo) PrefetchObjects(context.Context, []object.ID, string) ([]content.ID, error) {
	return nil, nil
}
func (r *trackCloseRepo) Time() time.Time { return time.Now() }
func (r *trackCloseRepo) ClientOptions() repo.ClientOptions {
	return repo.ClientOptions{}
}
func (r *trackCloseRepo) NewWriter(context.Context, repo.WriteSessionOptions) (context.Context, repo.RepositoryWriter, error) {
	return nil, nil, errors.New("not implemented")
}
func (r *trackCloseRepo) UpdateDescription(string) {}
func (r *trackCloseRepo) Refresh(context.Context) error { return nil }
func (r *trackCloseRepo) Close(context.Context) error {
	r.closed.Store(true)
	return nil
}

func withTestRepositoryOpener(t *testing.T, fn func(context.Context, openRepoOptions) (repo.Repository, error)) {
	t.Helper()
	prev := repositoryOpener
	repositoryOpener = fn
	t.Cleanup(func() { repositoryOpener = prev })
}

func TestPurgeStaleCachesRemovesOldDirs(t *testing.T) {
	tmp := t.TempDir()
	pool := NewPool(RepoCacheSettings{RepoConfigDir: tmp, ContentCacheSizeMiB: 64})
	cacheDir := filepath.Join(tmp, "cache", "old-repo")
	if err := os.MkdirAll(cacheDir, 0o755); err != nil {
		t.Fatal(err)
	}
	oldTime := time.Now().Add(-8 * 24 * time.Hour)
	if err := os.Chtimes(cacheDir, oldTime, oldTime); err != nil {
		t.Fatal(err)
	}

	pool.PurgeStaleCaches(7*24*time.Hour, 4096)

	if _, err := os.Stat(cacheDir); !os.IsNotExist(err) {
		t.Fatal("expected stale cache dir to be purged")
	}
}

func TestAcquireCancelledOpenerClearsOpening(t *testing.T) {
	tmp := t.TempDir()
	pool := NewPool(RepoCacheSettings{RepoConfigDir: tmp, ContentCacheSizeMiB: 64})
	storage := StorageOptions{Bucket: "blocked-bucket", Endpoint: "http://s3.example"}
	key := storage.RepoIdentity()

	blockOpen := make(chan struct{})
	openerStarted := make(chan struct{})
	var openerOnce sync.Once
	withTestRepositoryOpener(t, func(ctx context.Context, opts openRepoOptions) (repo.Repository, error) {
		openerOnce.Do(func() { close(openerStarted) })
		<-blockOpen
		return &trackCloseRepo{}, nil
	})

	ctx, cancel := context.WithCancel(context.Background())
	errCh := make(chan error, 1)
	go func() {
		_, _, err := pool.Acquire(ctx, storage, 64)
		errCh <- err
	}()

	select {
	case <-openerStarted:
	case <-time.After(2 * time.Second):
		t.Fatal("opener did not start")
	}

	pool.mu.Lock()
	_, opening := pool.opening[key]
	pool.mu.Unlock()
	if !opening {
		t.Fatal("expected opening entry while opener blocked")
	}

	cancel()

	select {
	case err := <-errCh:
		if !errors.Is(err, context.Canceled) {
			t.Fatalf("Acquire err = %v, want context.Canceled", err)
		}
	case <-time.After(2 * time.Second):
		t.Fatal("Acquire did not return after cancel")
	}

	pool.mu.Lock()
	_, stillOpening := pool.opening[key]
	pool.mu.Unlock()
	if stillOpening {
		t.Fatal("opening entry should be cleared after cancel")
	}

	close(blockOpen)

	waiterDone := make(chan struct{})
	go func() {
		defer close(waiterDone)
		rep, release, err := pool.Acquire(context.Background(), storage, 64)
		if err != nil {
			t.Errorf("waiter Acquire: %v", err)
			return
		}
		if rep == nil {
			t.Error("waiter Acquire: nil repo")
			return
		}
		release()
	}()

	select {
	case <-waiterDone:
	case <-time.After(2 * time.Second):
		t.Fatal("waiter blocked after opener cancel")
	}
}

func TestAcquireOrphanedOpenClosesRepo(t *testing.T) {
	tmp := t.TempDir()
	pool := NewPool(RepoCacheSettings{RepoConfigDir: tmp, ContentCacheSizeMiB: 64})
	storage := StorageOptions{Bucket: "orphan-bucket", Endpoint: "http://s3.example"}

	blockOpen := make(chan struct{})
	mock := &trackCloseRepo{}
	openerStarted := make(chan struct{})
	var openerOnce sync.Once
	withTestRepositoryOpener(t, func(ctx context.Context, opts openRepoOptions) (repo.Repository, error) {
		openerOnce.Do(func() { close(openerStarted) })
		<-blockOpen
		return mock, nil
	})

	ctx, cancel := context.WithCancel(context.Background())
	errCh := make(chan error, 1)
	go func() {
		_, _, err := pool.Acquire(ctx, storage, 64)
		errCh <- err
	}()

	<-openerStarted
	cancel()

	if err := <-errCh; !errors.Is(err, context.Canceled) {
		t.Fatalf("Acquire err = %v, want context.Canceled", err)
	}

	close(blockOpen)

	deadline := time.After(2 * time.Second)
	for !mock.closed.Load() {
		select {
		case <-deadline:
			t.Fatal("orphaned repo was not closed")
		default:
			time.Sleep(10 * time.Millisecond)
		}
	}

	pool.mu.Lock()
	_, inPool := pool.repos[storage.RepoIdentity()]
	pool.mu.Unlock()
	if inPool {
		t.Fatal("orphaned repo must not be installed in pool")
	}
}

func TestPriorSnapshotRootRespectsContextTimeout(t *testing.T) {
	tmp := t.TempDir()
	pool := NewPool(RepoCacheSettings{RepoConfigDir: tmp, ContentCacheSizeMiB: 64})
	storage := StorageOptions{Bucket: "prior-timeout", Endpoint: "http://s3.example"}

	blockOpen := make(chan struct{})
	openerStarted := make(chan struct{})
	withTestRepositoryOpener(t, func(ctx context.Context, opts openRepoOptions) (repo.Repository, error) {
		close(openerStarted)
		<-blockOpen
		return &trackCloseRepo{}, nil
	})

	ctx, cancel := context.WithTimeout(context.Background(), 50*time.Millisecond)
	defer cancel()

	errCh := make(chan error, 1)
	go func() {
		_, err := pool.PriorSnapshotRoot(ctx, storage, "manifest-abc")
		errCh <- err
	}()

	<-openerStarted

	select {
	case err := <-errCh:
		if !errors.Is(err, context.DeadlineExceeded) {
			t.Fatalf("PriorSnapshotRoot err = %v, want context.DeadlineExceeded", err)
		}
	case <-time.After(2 * time.Second):
		t.Fatal("PriorSnapshotRoot did not time out")
	}

	close(blockOpen)
}
