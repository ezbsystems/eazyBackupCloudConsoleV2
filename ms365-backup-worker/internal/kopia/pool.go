package kopia

import (
	"context"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"sync"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/repo/compression"
	"github.com/kopia/kopia/repo/manifest"
	"github.com/kopia/kopia/snapshot"
	"github.com/kopia/kopia/snapshot/policy"
	snapshotfs "github.com/kopia/kopia/snapshot/snapshotfs"
)

// Pool keeps warm, cached Kopia repository connections keyed by repo identity.
type Pool struct {
	cache RepoCacheSettings
	mu    sync.Mutex
	repos map[string]*poolEntry
	// opening single-flights concurrent first-opens of the same repo. Without it,
	// every child workload of a tenant batch (all sharing one bucket) that Acquires
	// before the repo is cached opens its OWN connection in parallel — N redundant,
	// expensive opens of the same repo (each fetching the full pack-index set from
	// object storage). With a large/uncompacted index this consumes every
	// max_concurrent_runs slot in duplicate repo-opens and stalls the batch.
	opening map[string]chan struct{}
}

type poolEntry struct {
	rep      repo.Repository
	refs     int
	opened   time.Time
	cacheDir string
}

func NewPool(cache RepoCacheSettings) *Pool {
	return &Pool{
		cache:   cache,
		repos:   make(map[string]*poolEntry),
		opening: make(map[string]chan struct{}),
	}
}

// Acquire returns a shared repository handle and a release function.
func (p *Pool) Acquire(ctx context.Context, storage StorageOptions, maxPackSizeMiB int) (repo.Repository, func(), error) {
	key := storage.RepoIdentity()

	for {
		p.mu.Lock()
		if entry, ok := p.repos[key]; ok && entry.rep != nil {
			entry.refs++
			p.mu.Unlock()
			return entry.rep, func() { p.release(key) }, nil
		}
		// Single-flight: if another goroutine is already opening this repo, wait for
		// it to finish and re-check the cache rather than opening a duplicate.
		if ch, ok := p.opening[key]; ok {
			p.mu.Unlock()
			select {
			case <-ch:
				continue
			case <-ctx.Done():
				return nil, nil, ctx.Err()
			}
		}
		// We are the designated opener for this key.
		ch := make(chan struct{})
		p.opening[key] = ch
		p.mu.Unlock()

		rep, err := openRepository(ctx, openRepoOptions{
			storage:        storage,
			cache:          p.cache,
			maxPackSizeMiB: maxPackSizeMiB,
		})

		p.mu.Lock()
		delete(p.opening, key)
		close(ch)
		if err != nil {
			p.mu.Unlock()
			return nil, nil, err
		}
		if entry, ok := p.repos[key]; ok && entry.rep != nil {
			_ = rep.Close(ctx)
			entry.refs++
			p.mu.Unlock()
			return entry.rep, func() { p.release(key) }, nil
		}
		p.repos[key] = &poolEntry{rep: rep, refs: 1, opened: time.Now(), cacheDir: p.cacheDir(storage)}
		p.mu.Unlock()
		return rep, func() { p.release(key) }, nil
	}
}

func (p *Pool) release(key string) {
	p.mu.Lock()
	defer p.mu.Unlock()
	entry, ok := p.repos[key]
	if !ok || entry == nil {
		return
	}
	entry.refs--
}

func (p *Pool) cacheDir(storage StorageOptions) string {
	return filepath.Join(p.cache.RepoConfigDir, "cache", storage.repoHash())
}

// IndexBlobCount returns the number of files in a repository's index cache directory.
func (p *Pool) IndexBlobCount(storage StorageOptions) int {
	indexDir := filepath.Join(p.cacheDir(storage), "indexes")
	entries, err := os.ReadDir(indexDir)
	if err != nil {
		return 0
	}
	count := 0
	for _, e := range entries {
		if !e.IsDir() {
			count++
		}
	}
	return count
}

// EvictRepo closes and removes a pooled repository when it has no active references,
// then deletes its on-disk content cache directory. Tiny repos/{hash}.config files are kept.
func (p *Pool) EvictRepo(ctx context.Context, storage StorageOptions) {
	key := storage.RepoIdentity()
	cacheDir := p.cacheDir(storage)

	p.mu.Lock()
	entry, ok := p.repos[key]
	if !ok || entry == nil || entry.refs > 0 {
		p.mu.Unlock()
		return
	}
	rep := entry.rep
	if entry.cacheDir != "" {
		cacheDir = entry.cacheDir
	}
	delete(p.repos, key)
	p.mu.Unlock()

	if rep != nil {
		_ = rep.Close(ctx)
	}
	_ = os.RemoveAll(cacheDir)
}

// EvictIdle evicts every pooled repository with no active references and deletes cache dirs.
func (p *Pool) EvictIdle(ctx context.Context) {
	p.mu.Lock()
	var idle []*poolEntry
	for key, entry := range p.repos {
		if entry == nil || entry.refs > 0 {
			continue
		}
		idle = append(idle, entry)
		delete(p.repos, key)
	}
	p.mu.Unlock()

	for _, entry := range idle {
		if entry.rep != nil {
			_ = entry.rep.Close(ctx)
		}
		if entry.cacheDir != "" {
			_ = os.RemoveAll(entry.cacheDir)
		}
	}
}

// Drain closes all pooled repositories and deletes their on-disk cache directories.
func (p *Pool) Drain(ctx context.Context) {
	p.DrainAndPurgeCaches(ctx)
}

// DrainAndPurgeCaches closes all pooled repositories and removes cache directories.
func (p *Pool) DrainAndPurgeCaches(ctx context.Context) {
	p.mu.Lock()
	entries := make([]*poolEntry, 0, len(p.repos))
	for _, entry := range p.repos {
		if entry != nil {
			entries = append(entries, entry)
		}
	}
	p.repos = make(map[string]*poolEntry)
	p.mu.Unlock()

	for _, entry := range entries {
		if entry != nil && entry.rep != nil {
			_ = entry.rep.Close(ctx)
		}
		if entry != nil && entry.cacheDir != "" {
			_ = os.RemoveAll(entry.cacheDir)
		}
	}
}

// ActiveRefs returns the total number of active repository references.
func (p *Pool) ActiveRefs() int {
	p.mu.Lock()
	defer p.mu.Unlock()
	total := 0
	for _, entry := range p.repos {
		if entry != nil {
			total += entry.refs
		}
	}
	return total
}

// CacheBreakdownMiB scans cache subdirectories and returns per-category usage.
func (p *Pool) CacheBreakdownMiB() (CacheBreakdown, int64) {
	cacheRoot := filepath.Join(p.cache.RepoConfigDir, "cache")
	var breakdown CacheBreakdown
	entries, err := os.ReadDir(cacheRoot)
	if err != nil {
		return breakdown, 0
	}
	for _, repoDir := range entries {
		if !repoDir.IsDir() {
			continue
		}
		base := filepath.Join(cacheRoot, repoDir.Name())
		breakdown.ContentsMiB += DirSizeMiB(filepath.Join(base, "contents"))
		breakdown.MetadataMiB += DirSizeMiB(filepath.Join(base, "metadata"))
		breakdown.IndexesMiB += DirSizeMiB(filepath.Join(base, "indexes"))
		breakdown.OwnWritesMiB += DirSizeMiB(filepath.Join(base, "own-writes"))
	}
	total := breakdown.ContentsMiB + breakdown.MetadataMiB + breakdown.IndexesMiB + breakdown.OwnWritesMiB
	return breakdown, total
}

// PriorSnapshotRoot loads a snapshot root via the warm pool.
func (p *Pool) PriorSnapshotRoot(ctx context.Context, storage StorageOptions, manifestID string) (kopiafs.Directory, error) {
	if manifestID == "" {
		return nil, fmt.Errorf("manifest_id required")
	}
	rep, release, err := p.Acquire(ctx, storage, 64)
	if err != nil {
		return nil, err
	}
	defer release()

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

// Snapshot writes a snapshot using a pooled repository connection.
func (p *Pool) Snapshot(ctx context.Context, req SnapshotRequest) (*SnapshotResult, error) {
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

	rep, release, err := p.Acquire(ctx, req.Storage, req.MaxPackSizeMiB)
	if err != nil {
		return nil, err
	}
	defer release()

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

	counter := req.Counter
	if counter == nil {
		counter = NewProgressCounter(req.OnProgress)
	} else if req.OnProgress != nil {
		counter.callback = req.OnProgress
	}
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

// Browse lists snapshot entries using the warm pool.
func (p *Pool) Browse(ctx context.Context, req BrowseRequest) (*BrowseResult, error) {
	return browseWithRepo(ctx, req, func(ctx context.Context) (repo.Repository, func(), error) {
		return p.Acquire(ctx, req.Storage, 64)
	})
}

// ListDirectory lists snapshot entries for archive export without UI label filtering.
// Browse omits GUID-like folder segments (e.g. tenant id at snapshot root); archive
// export must see every child to recurse into workload data.
func (p *Pool) ListDirectory(ctx context.Context, req BrowseRequest) (*BrowseResult, error) {
	return listDirectoryWithRepo(ctx, req, func(ctx context.Context) (repo.Repository, func(), error) {
		return p.Acquire(ctx, req.Storage, 64)
	})
}

// Extract reads a file from a snapshot using the warm pool.
func (p *Pool) Extract(ctx context.Context, req ExtractRequest) ([]byte, error) {
	rep, release, err := p.Acquire(ctx, req.Storage, 64)
	if err != nil {
		return nil, err
	}
	defer release()
	return extractWithRepo(ctx, req, rep)
}

// ExtractReader opens a streaming reader for a snapshot file using the warm pool.
func (p *Pool) ExtractReader(ctx context.Context, req ExtractRequest) (io.ReadCloser, int64, error) {
	rep, release, err := p.Acquire(ctx, req.Storage, 64)
	if err != nil {
		return nil, 0, err
	}
	reader, size, err := extractReaderWithRepo(ctx, req, rep)
	if err != nil {
		release()
		return nil, 0, err
	}
	return &pooledReader{reader: reader, release: release}, size, nil
}

type pooledReader struct {
	reader  kopiafs.Reader
	release func()
	closed  bool
}

func (p *pooledReader) Read(buf []byte) (int, error) {
	return p.reader.Read(buf)
}

func (p *pooledReader) Close() error {
	if p.closed {
		return nil
	}
	p.closed = true
	err := p.reader.Close()
	p.release()
	return err
}
