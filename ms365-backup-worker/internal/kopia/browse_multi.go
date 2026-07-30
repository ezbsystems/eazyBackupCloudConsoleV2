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

// BrowseMaxSourceCount matches the PHP shard ceiling (Ms365EngineConfig::shardMaxCount).
const BrowseMaxSourceCount = 64

// BrowseSource identifies one shard manifest and physical path candidates for the
// current logical browse location.
type BrowseSource struct {
	ChildRunID     string   `json:"child_run_id"`
	ManifestID     string   `json:"manifest_id"`
	CandidatePaths []string `json:"candidate_paths"`
}

// SourceRef pins a browse entry to the manifest and physical path used to read it.
type SourceRef struct {
	ChildRunID string `json:"child_run_id"`
	ManifestID string `json:"manifest_id"`
	SourcePath string `json:"source_path"`
}

type mergedBrowseEntry struct {
	entry     BrowseEntry
	sortKey   string
	sourceRef SourceRef
}

func prepareBrowseRequest(req *BrowseRequest) error {
	if req.Host == "" {
		req.Host = "ms365-worker"
	}
	if req.Username == "" {
		req.Username = "ms365"
	}
	if req.SourcePath == "" {
		req.SourcePath = "/ms365"
	}
	return nil
}

func validateBrowseSources(sources []BrowseSource) error {
	if len(sources) == 0 {
		return fmt.Errorf("browse sources required")
	}
	if len(sources) > BrowseMaxSourceCount {
		return fmt.Errorf("browse sources exceed maximum (%d)", BrowseMaxSourceCount)
	}
	for i, src := range sources {
		if strings.TrimSpace(src.ManifestID) == "" {
			return fmt.Errorf("browse source %d: manifest_id required", i)
		}
	}
	return nil
}

func browseMultiSourceWithRepo(ctx context.Context, req BrowseRequest, acquire repoAcquirer) (*BrowseResult, error) {
	if err := validateBrowseSources(req.Sources); err != nil {
		return nil, err
	}
	if err := prepareBrowseRequest(&req); err != nil {
		return nil, err
	}

	rep, release, err := acquire(ctx)
	if err != nil {
		return nil, err
	}
	defer release()

	logicalPath := normalizeBrowsePath(req.Path)
	merged := make(map[string]*mergedBrowseEntry)
	var warnings []string
	successCount := 0
	var labelRoot kopiafs.Directory
	var mailResolver *mailBrowseResolver

	for _, source := range req.Sources {
		root, err := loadSnapshotRoot(ctx, rep, source.ManifestID)
		if err != nil {
			warnings = append(warnings, formatBrowseSourceWarning(source, err))
			continue
		}
		dir, resolvedBase, err := resolveBrowseSourceDir(ctx, root, source.CandidatePaths)
		if err != nil {
			warnings = append(warnings, formatBrowseSourceWarning(source, err))
			continue
		}
		successCount++
		if labelRoot == nil {
			labelRoot = root
			mailResolver = loadMailBrowseResolver(ctx, root, logicalPath)
		}

		sourceWarnings, err := collectBrowseSourceChildren(
			ctx,
			root,
			dir,
			resolvedBase,
			logicalPath,
			source,
			merged,
			mailResolver,
		)
		if err != nil {
			warnings = append(warnings, formatBrowseSourceWarning(source, err))
			successCount--
			continue
		}
		warnings = append(warnings, sourceWarnings...)
	}

	if successCount == 0 {
		return nil, fmt.Errorf("all browse sources failed")
	}

	sorted, conflictWarnings := collapseMergedBrowseEntries(merged)
	warnings = append(warnings, conflictWarnings...)

	if isSharePointListsBrowseDirectory(logicalPath) && labelRoot != nil {
		var catalogWarnings []string
		sorted, catalogWarnings = appendSharePointCatalogOnlyLists(ctx, labelRoot, logicalPath, sorted)
		warnings = append(warnings, catalogWarnings...)
	}

	return buildBrowseResult(sorted, req, warnings), nil
}

func loadSnapshotRoot(ctx context.Context, rep repo.Repository, manifestID string) (kopiafs.Directory, error) {
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

func resolveBrowseSourceDir(ctx context.Context, root kopiafs.Directory, candidates []string) (kopiafs.Directory, string, error) {
	try := candidates
	if len(try) == 0 {
		try = []string{""}
	}
	for _, candidate := range try {
		candidate = normalizeBrowsePath(candidate)
		if candidate == "" {
			return root, "", nil
		}
		entry, err := walkPath(ctx, root, candidate)
		if err != nil {
			continue
		}
		dir, ok := entry.(kopiafs.Directory)
		if !ok {
			continue
		}
		return dir, candidate, nil
	}
	return nil, "", fmt.Errorf("no valid candidate path")
}

func formatBrowseSourceWarning(source BrowseSource, err error) string {
	id := strings.TrimSpace(source.ChildRunID)
	if id == "" {
		id = source.ManifestID
	}
	return fmt.Sprintf("source %s: %v", id, err)
}

func collectBrowseSourceChildren(
	ctx context.Context,
	labelRoot kopiafs.Directory,
	dir kopiafs.Directory,
	resolvedBase string,
	logicalPath string,
	source BrowseSource,
	merged map[string]*mergedBrowseEntry,
	mailResolver *mailBrowseResolver,
) ([]string, error) {
	children, err := kopiafs.GetAllEntries(ctx, dir)
	if err != nil {
		return nil, fmt.Errorf("readdir: %w", err)
	}

	useFastLabels := len(children) > browseFastLabelChildThreshold
	var warnings []string

	for _, child := range children {
		name := child.Name()
		if shouldHideBrowseName(name) {
			continue
		}
		logicalChildPath := joinBrowsePath(logicalPath, name)
		sourceChildPath := joinBrowsePath(resolvedBase, name)
		entryType, hasChildren, size := browseEntryMeta(child)

		useFast := useFastLabels && !needsFullSharePointListLabel(logicalChildPath, entryType)
		if useFast && needsFullMailLabel(logicalChildPath, entryType) && (mailResolver == nil || !mailResolver.hasIndex()) {
			useFast = false
		}
		labelInfo := labelBrowseChild(ctx, labelRoot, logicalChildPath, name, entryType, useFast, mailResolver)
		if labelInfo.Label == "" {
			continue
		}

		key := browseMergeKey(logicalChildPath, entryType)
		ref := SourceRef{
			ChildRunID: source.ChildRunID,
			ManifestID: source.ManifestID,
			SourcePath: sourceChildPath,
		}
		candidate := mergedBrowseEntry{
			entry: BrowseEntry{
				Name:        name,
				Label:       labelInfo.Label,
				Subtitle:    labelInfo.Subtitle,
				Path:        logicalChildPath,
				Type:        entryType,
				HasChildren: hasChildren,
				Size:        size,
				SourceRefs:  []SourceRef{ref},
			},
			sortKey:   labelInfo.SortKey,
			sourceRef: ref,
		}

		existing, ok := merged[key]
		if !ok {
			merged[key] = &candidate
			continue
		}
		if entryType == "folder" {
			existing.entry.HasChildren = existing.entry.HasChildren || hasChildren
			existing.entry.SourceRefs = appendSourceRef(existing.entry.SourceRefs, ref)
			continue
		}
		if existing.entry.Size != size {
			warnings = append(warnings, fmt.Sprintf(
				"duplicate path conflict at %s: size mismatch across sources",
				logicalChildPath,
			))
		}
		existing.entry.SourceRefs = appendSourceRef(existing.entry.SourceRefs, ref)
	}

	return warnings, nil
}

func browseEntryMeta(child kopiafs.Entry) (entryType string, hasChildren bool, size int64) {
	entryType = "file"
	if _, isDir := child.(kopiafs.Directory); isDir {
		entryType = "folder"
		hasChildren = true
	} else if f, ok := child.(kopiafs.File); ok {
		size = f.Size()
	}
	return entryType, hasChildren, size
}

func browseMergeKey(path, entryType string) string {
	return normalizeBrowsePath(path) + "\x00" + entryType
}

func appendSourceRef(refs []SourceRef, ref SourceRef) []SourceRef {
	for _, existing := range refs {
		if existing.ChildRunID == ref.ChildRunID &&
			existing.ManifestID == ref.ManifestID &&
			existing.SourcePath == ref.SourcePath {
			return refs
		}
	}
	return append(refs, ref)
}

func collapseMergedBrowseEntries(merged map[string]*mergedBrowseEntry) ([]mergedBrowseEntry, []string) {
	out := make([]mergedBrowseEntry, 0, len(merged))
	var warnings []string
	for _, item := range merged {
		if item.entry.Type == "folder" && len(item.entry.SourceRefs) > 1 {
			names := make([]string, 0, len(item.entry.SourceRefs))
			seen := make(map[string]struct{}, len(item.entry.SourceRefs))
			for _, ref := range item.entry.SourceRefs {
				if _, ok := seen[ref.ChildRunID]; ok {
					continue
				}
				seen[ref.ChildRunID] = struct{}{}
				if ref.ChildRunID != "" {
					names = append(names, ref.ChildRunID)
				}
			}
			if len(names) > 1 {
				sort.Strings(names)
				warnings = append(warnings, fmt.Sprintf(
					"merged folder %s from %d shard sources",
					item.entry.Path,
					len(names),
				))
			}
		}
		out = append(out, *item)
	}
	return out, warnings
}

type browseSortItem struct {
	entry   BrowseEntry
	sortKey string
}

func sortBrowseItems(items []mergedBrowseEntry) []browseSortItem {
	sorted := make([]browseSortItem, len(items))
	for i, item := range items {
		sorted[i] = browseSortItem{entry: item.entry, sortKey: item.sortKey}
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
	return sorted
}

func buildBrowseResult(sorted []mergedBrowseEntry, req BrowseRequest, warnings []string) *BrowseResult {
	items := sortBrowseItems(sorted)
	totalCount := len(items)
	offset := req.Offset
	if offset < 0 {
		offset = 0
	}
	limit := req.Limit
	end := totalCount
	hasMore := false
	if limit > 0 {
		if offset > totalCount {
			offset = totalCount
		}
		end = offset + limit
		if end > totalCount {
			end = totalCount
		}
		hasMore = end < totalCount
	} else if offset > 0 {
		if offset > totalCount {
			offset = totalCount
		}
		end = totalCount
	}

	page := items[offset:end]
	entries := make([]BrowseEntry, len(page))
	for i, item := range page {
		entries[i] = item.entry
	}

	result := &BrowseResult{
		Entries:    entries,
		TotalCount: totalCount,
		HasMore:    hasMore,
		Offset:     offset,
		Limit:      limit,
	}
	if len(warnings) > 0 {
		result.Warnings = warnings
	}
	return result
}

// browseMultiFromRoots unions browse children using in-memory snapshot roots (tests).
func browseMultiFromRoots(ctx context.Context, req BrowseRequest, roots map[string]kopiafs.Directory) (*BrowseResult, error) {
	if err := validateBrowseSources(req.Sources); err != nil {
		return nil, err
	}
	if err := prepareBrowseRequest(&req); err != nil {
		return nil, err
	}

	logicalPath := normalizeBrowsePath(req.Path)
	merged := make(map[string]*mergedBrowseEntry)
	var warnings []string
	successCount := 0
	var labelRoot kopiafs.Directory
	var mailResolver *mailBrowseResolver

	for _, source := range req.Sources {
		root := roots[source.ManifestID]
		if root == nil {
			warnings = append(warnings, formatBrowseSourceWarning(source, fmt.Errorf("snapshot root missing")))
			continue
		}
		dir, resolvedBase, err := resolveBrowseSourceDir(ctx, root, source.CandidatePaths)
		if err != nil {
			warnings = append(warnings, formatBrowseSourceWarning(source, err))
			continue
		}
		successCount++
		if labelRoot == nil {
			labelRoot = root
			mailResolver = loadMailBrowseResolver(ctx, root, logicalPath)
		}
		sourceWarnings, err := collectBrowseSourceChildren(
			ctx,
			labelRoot,
			dir,
			resolvedBase,
			logicalPath,
			source,
			merged,
			mailResolver,
		)
		if err != nil {
			warnings = append(warnings, formatBrowseSourceWarning(source, err))
			successCount--
			continue
		}
		warnings = append(warnings, sourceWarnings...)
	}

	if successCount == 0 {
		return nil, fmt.Errorf("all browse sources failed")
	}

	sorted, conflictWarnings := collapseMergedBrowseEntries(merged)
	warnings = append(warnings, conflictWarnings...)

	if isSharePointListsBrowseDirectory(logicalPath) && labelRoot != nil {
		var catalogWarnings []string
		sorted, catalogWarnings = appendSharePointCatalogOnlyLists(ctx, labelRoot, logicalPath, sorted)
		warnings = append(warnings, catalogWarnings...)
	}

	return buildBrowseResult(sorted, req, warnings), nil
}
