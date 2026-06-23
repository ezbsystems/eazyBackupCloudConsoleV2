package archive

import (
	"context"
	"fmt"
	"strings"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

type fileEntry struct {
	Path       string
	ManifestID string
	Size       int64
}

func collectSelectionFiles(
	ctx context.Context,
	pool *kopia.Pool,
	storage kopia.StorageOptions,
	sourceManifestID string,
	manifestByPath map[string]string,
	items []api.RestoreItem,
) ([]fileEntry, error) {
	seen := make(map[string]struct{})
	var files []fileEntry

	for _, item := range items {
		root := itemRoot(item)
		if root == "" {
			continue
		}
		manifestID := resolveManifestID(item, root, sourceManifestID, manifestByPath)
		collected, err := collectFiles(ctx, pool, storage, manifestID, root)
		if err != nil {
			return nil, fmt.Errorf("collect %s: %w", root, err)
		}
		for _, f := range collected {
			if _, ok := seen[f.Path]; ok {
				continue
			}
			seen[f.Path] = struct{}{}
			f.ManifestID = manifestID
			files = append(files, f)
		}
	}
	return files, nil
}

func itemRoot(item api.RestoreItem) string {
	if item.Path != "" {
		return strings.Trim(strings.TrimSuffix(item.Path, "/"), "/")
	}
	return strings.Trim(strings.TrimSuffix(item.PathPrefix, "/"), "/")
}

func resolveManifestID(item api.RestoreItem, path, fallback string, manifestByPath map[string]string) string {
	if item.ManifestID != "" {
		return item.ManifestID
	}
	for prefix, mid := range manifestByPath {
		if path == prefix || stringsHasPrefix(path, prefix) {
			return mid
		}
	}
	return fallback
}

func collectFiles(
	ctx context.Context,
	pool *kopia.Pool,
	storage kopia.StorageOptions,
	manifestID string,
	rootPath string,
) ([]fileEntry, error) {
	rootPath = strings.Trim(rootPath, "/")
	result, err := pool.Browse(ctx, kopia.BrowseRequest{
		Storage:    storage,
		ManifestID: manifestID,
		Path:       rootPath,
		SourcePath: "/ms365",
	})
	if err != nil {
		return nil, err
	}
	if len(result.Entries) == 0 {
		if rootPath != "" && shouldExportFile(rootPath) {
			return []fileEntry{{Path: rootPath}}, nil
		}
		return nil, nil
	}

	var files []fileEntry
	for _, entry := range result.Entries {
		switch entry.Type {
		case "folder":
			sub, err := collectFiles(ctx, pool, storage, manifestID, entry.Path)
			if err != nil {
				return nil, err
			}
			files = append(files, sub...)
		case "file":
			if shouldExportFile(entry.Path) {
				files = append(files, fileEntry{Path: entry.Path, Size: entry.Size})
			}
		}
	}
	return files, nil
}

func stringsHasPrefix(s, prefix string) bool {
	return len(s) >= len(prefix) && s[:len(prefix)] == prefix
}
