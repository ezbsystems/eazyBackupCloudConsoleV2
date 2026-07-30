package graphrestore

import (
	"context"
	"fmt"
	"strings"

	"github.com/eazybackup/ms365-backup-worker/internal/kopia"
)

// ResolvedFile is one snapshot file ready for restore/export with separate
// physical (source) and logical paths.
type ResolvedFile struct {
	ManifestID  string
	SourcePath  string
	LogicalPath string
}

// ResolveSelectionFiles expands restore selection items into concrete files.
// Folder selections walk the source prefix inside each item's manifest.
func ResolveSelectionFiles(
	ctx context.Context,
	pool *kopia.Pool,
	storage kopia.StorageOptions,
	items []SelectionItem,
) ([]ResolvedFile, error) {
	seen := make(map[string]struct{})
	var out []ResolvedFile

	for _, item := range items {
		manifestID := strings.TrimSpace(item.ManifestID)
		if manifestID == "" {
			return nil, fmt.Errorf("manifest_id required for restore item")
		}
		sourceRoot := EffectiveSourcePath(item)
		logicalRoot := EffectiveLogicalPath(item)
		if sourceRoot == "" {
			return nil, fmt.Errorf("empty source path")
		}

		if isFolderSelection(item) {
			collected, err := collectManifestFiles(ctx, pool, storage, manifestID, sourceRoot)
			if err != nil {
				return nil, fmt.Errorf("collect %s: %w", sourceRoot, err)
			}
			for _, f := range collected {
				logical := MapSourceFileToLogical(f, sourceRoot, logicalRoot)
				key := logical + "\x00" + manifestID
				if _, ok := seen[key]; ok {
					continue
				}
				seen[key] = struct{}{}
				out = append(out, ResolvedFile{
					ManifestID:  manifestID,
					SourcePath:  f,
					LogicalPath: logical,
				})
			}
			continue
		}

		logical := logicalRoot
		if logical == "" {
			logical = sourceRoot
		}
		key := logical + "\x00" + manifestID
		if _, ok := seen[key]; ok {
			continue
		}
		seen[key] = struct{}{}
		out = append(out, ResolvedFile{
			ManifestID:  manifestID,
			SourcePath:  sourceRoot,
			LogicalPath: logical,
		})
	}

	return out, nil
}

func collectManifestFiles(
	ctx context.Context,
	pool *kopia.Pool,
	storage kopia.StorageOptions,
	manifestID string,
	rootPath string,
) ([]string, error) {
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
		if rootPath != "" {
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
				return []string{rootPath}, nil
			}
		}
		return nil, nil
	}

	var files []string
	for _, entry := range result.Entries {
		switch entry.Type {
		case "folder":
			sub, err := collectManifestFiles(ctx, pool, storage, manifestID, entry.Path)
			if err != nil {
				return nil, err
			}
			files = append(files, sub...)
		case "file":
			files = append(files, entry.Path)
		}
	}
	return files, nil
}
