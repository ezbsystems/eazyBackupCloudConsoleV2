package kopia

import (
	"context"
	"fmt"
	"sort"
	"strings"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/repo/manifest"
	"github.com/kopia/kopia/snapshot"
	"github.com/kopia/kopia/snapshot/snapshotfs"
)

type BrowseRequest struct {
	Storage    StorageOptions
	ManifestID string
	Path       string
	Host       string
	Username   string
	SourcePath string
}

type BrowseEntry struct {
	Name        string `json:"name"`
	Label       string `json:"label,omitempty"`
	Subtitle    string `json:"subtitle,omitempty"`
	Path        string `json:"path"`
	Type        string `json:"type"`
	HasChildren bool   `json:"has_children"`
	Size        int64  `json:"size"`
}

type BrowseResult struct {
	Entries []BrowseEntry `json:"entries"`
}

type ExtractRequest struct {
	Storage    StorageOptions
	ManifestID string
	Path       string
	Host       string
	Username   string
	SourcePath string
}

type repoAcquirer func(ctx context.Context) (repo.Repository, func(), error)

// Browse lists snapshot entries (standalone; prefer Pool.Browse for warm cache).
func Browse(ctx context.Context, req BrowseRequest) (*BrowseResult, error) {
	pool := NewPool(RepoCacheSettings{RepoConfigDir: "/tmp/ms365-browse"})
	return pool.Browse(ctx, req)
}

func browseWithRepo(ctx context.Context, req BrowseRequest, acquire repoAcquirer) (*BrowseResult, error) {
	if strings.TrimSpace(req.ManifestID) == "" {
		return nil, fmt.Errorf("manifest_id required")
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

	rep, release, err := acquire(ctx)
	if err != nil {
		return nil, err
	}
	defer release()

	man, err := snapshot.LoadSnapshot(ctx, rep, manifest.ID(req.ManifestID))
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

	targetPath := normalizeBrowsePath(req.Path)
	cur := root
	if targetPath != "" {
		curEntry, err := walkPath(ctx, root, targetPath)
		if err != nil {
			return nil, err
		}
		var dirOk bool
		cur, dirOk = curEntry.(kopiafs.Directory)
		if !dirOk {
			return &BrowseResult{Entries: []BrowseEntry{}}, nil
		}
	}

	children, err := cur.Readdir(ctx)
	if err != nil {
		return nil, fmt.Errorf("readdir: %w", err)
	}

	type entrySort struct {
		entry   BrowseEntry
		sortKey string
	}
	sorted := make([]entrySort, 0, len(children))
	for _, child := range children {
		name := child.Name()
		if shouldHideBrowseName(name) {
			continue
		}
		childPath := joinBrowsePath(targetPath, name)
		entryType := "file"
		hasChildren := false
		var size int64
		if _, isDir := child.(kopiafs.Directory); isDir {
			entryType = "folder"
			hasChildren = true
		} else if f, ok := child.(kopiafs.File); ok {
			size = f.Size()
		}
		labelInfo := browseLabel(ctx, rep, man, root, childPath, name, entryType)
		if labelInfo.Label == "" {
			continue
		}
		sorted = append(sorted, entrySort{
			entry: BrowseEntry{
				Name:        name,
				Label:       labelInfo.Label,
				Subtitle:    labelInfo.Subtitle,
				Path:        childPath,
				Type:        entryType,
				HasChildren: hasChildren,
				Size:        size,
			},
			sortKey: labelInfo.SortKey,
		})
	}
	sort.Slice(sorted, func(i, j int) bool {
		a, b := sorted[i], sorted[j]
		if a.entry.Type != b.entry.Type {
			return a.entry.Type == "folder"
		}
		if a.sortKey != "" && b.sortKey != "" && a.sortKey != b.sortKey {
			return a.sortKey > b.sortKey
		}
		return strings.ToLower(a.entry.Label) < strings.ToLower(b.entry.Label)
	})
	entries := make([]BrowseEntry, len(sorted))
	for i, item := range sorted {
		entries[i] = item.entry
	}

	return &BrowseResult{Entries: entries}, nil
}

func Extract(ctx context.Context, req ExtractRequest) ([]byte, error) {
	pool := NewPool(RepoCacheSettings{RepoConfigDir: "/tmp/ms365-browse"})
	return pool.Extract(ctx, req)
}

func openSnapshotFile(ctx context.Context, req ExtractRequest, rep repo.Repository) (kopiafs.File, error) {
	man, err := snapshot.LoadSnapshot(ctx, rep, manifest.ID(req.ManifestID))
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

	targetPath := normalizeBrowsePath(req.Path)
	cur, err := walkPath(ctx, root, targetPath)
	if err != nil {
		return nil, err
	}
	file, ok := cur.(kopiafs.File)
	if !ok {
		return nil, fmt.Errorf("not a file: %s", req.Path)
	}
	return file, nil
}

func extractWithRepo(ctx context.Context, req ExtractRequest, rep repo.Repository) ([]byte, error) {
	file, err := openSnapshotFile(ctx, req, rep)
	if err != nil {
		return nil, err
	}
	reader, err := file.Open(ctx)
	if err != nil {
		return nil, err
	}
	defer reader.Close()

	buf := make([]byte, 0, 4096)
	tmp := make([]byte, 32*1024)
	for {
		n, readErr := reader.Read(tmp)
		if n > 0 {
			buf = append(buf, tmp[:n]...)
		}
		if readErr != nil {
			break
		}
	}
	return buf, nil
}

func extractReaderWithRepo(ctx context.Context, req ExtractRequest, rep repo.Repository) (kopiafs.Reader, int64, error) {
	file, err := openSnapshotFile(ctx, req, rep)
	if err != nil {
		return nil, 0, err
	}
	reader, err := file.Open(ctx)
	if err != nil {
		return nil, 0, err
	}
	return reader, file.Size(), nil
}

func walkPath(ctx context.Context, root kopiafs.Directory, path string) (kopiafs.Entry, error) {
	if path == "" {
		return root, nil
	}
	parts := strings.Split(path, "/")
	cur := kopiafs.Entry(root)
	for _, part := range parts {
		if part == "" {
			continue
		}
		dir, ok := cur.(kopiafs.Directory)
		if !ok {
			return nil, fmt.Errorf("path not found: %s", path)
		}
		next, err := dir.Child(ctx, part)
		if err != nil {
			return nil, fmt.Errorf("path not found: %s", path)
		}
		cur = next
	}
	return cur, nil
}

func normalizeBrowsePath(p string) string {
	p = strings.TrimSpace(p)
	p = strings.Trim(p, "/")
	return p
}

func joinBrowsePath(base, name string) string {
	if base == "" {
		return name
	}
	return base + "/" + name
}
