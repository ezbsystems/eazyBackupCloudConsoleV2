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
		if root == "" && !isWholeManifestSelection(item) {
			continue
		}
		manifestID := resolveManifestID(item, root, sourceManifestID, manifestByPath)
		collected, err := collectFiles(ctx, pool, storage, manifestID, root)
		if err != nil {
			label := root
			if label == "" {
				label = "manifest:" + manifestID
			}
			return nil, fmt.Errorf("collect %s: %w", label, err)
		}
		for _, f := range collected {
			key := manifestID + "\x00" + f.Path
			if _, ok := seen[key]; ok {
				continue
			}
			seen[key] = struct{}{}
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

// isWholeManifestSelection is true when the UI selected a top-level workload
// resource (type=resource) with no path — export the entire Kopia manifest.
func isWholeManifestSelection(item api.RestoreItem) bool {
	if strings.TrimSpace(item.Path) != "" || strings.TrimSpace(item.PathPrefix) != "" {
		return false
	}
	return strings.TrimSpace(item.ManifestID) != "" &&
		strings.EqualFold(strings.TrimSpace(item.Type), "resource")
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
	result, err := pool.ListDirectory(ctx, kopia.BrowseRequest{
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
			isFile, err := pool.IsSnapshotFile(ctx, kopia.BrowseRequest{
				Storage:    storage,
				ManifestID: manifestID,
				Path:       rootPath,
				SourcePath: "/ms365",
			}, rootPath)
			if err != nil {
				return nil, err
			}
			if isFile {
				return []fileEntry{{Path: rootPath}}, nil
			}
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

func filterExportFiles(files []fileEntry) []fileEntry {
	mailMsgs := mailMessagePathsSet(files)
	out := make([]fileEntry, 0, len(files))
	for _, f := range files {
		if shouldSkipAsEmbeddedAttachment(f.Path, mailMsgs) {
			continue
		}
		if shouldSkipCalendarExport(f.Path) {
			continue
		}
		out = append(out, f)
	}
	return out
}

func stringsHasPrefix(s, prefix string) bool {
	return len(s) >= len(prefix) && s[:len(prefix)] == prefix
}
