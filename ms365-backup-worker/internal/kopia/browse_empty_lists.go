package kopia

import (
	"context"
	"encoding/json"
	"strings"

	kopiafs "github.com/kopia/kopia/fs"
)

const sharePointEmptyListSubtitle = "Empty list — catalog captured"

func isSharePointListsBrowseDirectory(path string) bool {
	trimmed := strings.Trim(path, "/")
	if trimmed == "" {
		return false
	}
	parts := strings.Split(strings.ToLower(trimmed), "/")
	for i, part := range parts {
		if part != "lists" {
			continue
		}
		return i+1 >= len(parts)
	}
	return false
}

type sharePointListCatalogEntry struct {
	ID          string
	DisplayName string
}

func appendSharePointCatalogOnlyLists(
	ctx context.Context,
	root kopiafs.Directory,
	listsBrowsePath string,
	sorted []mergedBrowseEntry,
) ([]mergedBrowseEntry, []string) {
	catalogPath := joinBrowsePath(listsBrowsePath, "lists.json")
	catalog, err := loadSharePointListsCatalog(ctx, root, catalogPath)
	if err != nil || len(catalog) == 0 {
		return sorted, nil
	}

	existing := make(map[string]struct{}, len(sorted))
	for _, item := range sorted {
		existing[normalizeListIDKey(item.entry.Name)] = struct{}{}
	}

	var warnings []string
	for _, list := range catalog {
		key := normalizeListIDKey(list.ID)
		if key == "" {
			continue
		}
		if _, ok := existing[key]; ok {
			continue
		}
		name := list.ID
		if safe := safeSnapshotID(list.ID); safe != "" {
			name = safe
		}
		label := strings.TrimSpace(list.DisplayName)
		if label == "" {
			label = opaqueSharePointListFallback(name)
		}
		selectable := false
		sorted = append(sorted, mergedBrowseEntry{
			entry: BrowseEntry{
				Name:        name,
				Label:       label,
				Subtitle:    sharePointEmptyListSubtitle,
				Path:        joinBrowsePath(listsBrowsePath, name),
				Type:        "folder",
				HasChildren: false,
				Selectable:  &selectable,
			},
		})
		existing[key] = struct{}{}
	}

	return sorted, warnings
}

func loadSharePointListsCatalog(ctx context.Context, root kopiafs.Directory, catalogPath string) ([]sharePointListCatalogEntry, error) {
	buf, err := readFilePrefix(ctx, root, catalogPath, browseMetaReadLimit)
	if err != nil || len(buf) == 0 {
		return nil, err
	}
	var parsed map[string]any
	if err := json.Unmarshal(buf, &parsed); err != nil {
		return nil, err
	}
	values, _ := parsed["value"].([]any)
	seen := make(map[string]sharePointListCatalogEntry)
	order := make([]string, 0, len(values))
	for _, raw := range values {
		item, _ := raw.(map[string]any)
		if item == nil {
			continue
		}
		id, _ := item["id"].(string)
		id = strings.TrimSpace(id)
		if id == "" {
			continue
		}
		key := normalizeListIDKey(id)
		if _, ok := seen[key]; ok {
			continue
		}
		displayName, _ := item["displayName"].(string)
		seen[key] = sharePointListCatalogEntry{ID: id, DisplayName: displayName}
		order = append(order, key)
	}
	out := make([]sharePointListCatalogEntry, 0, len(order))
	for _, key := range order {
		out = append(out, seen[key])
	}
	return out, nil
}

func normalizeListIDKey(id string) string {
	id = strings.TrimSpace(id)
	if id == "" {
		return ""
	}
	return strings.ToLower(safeSnapshotID(id))
}

func synthesizeSharePointEmptyListEntry(listsBrowsePath string, list sharePointListCatalogEntry) BrowseEntry {
	name := list.ID
	if safe := safeSnapshotID(list.ID); safe != "" {
		name = safe
	}
	label := strings.TrimSpace(list.DisplayName)
	if label == "" {
		label = opaqueSharePointListFallback(name)
	}
	selectable := false
	return BrowseEntry{
		Name:        name,
		Label:       label,
		Subtitle:    sharePointEmptyListSubtitle,
		Path:        joinBrowsePath(listsBrowsePath, name),
		Type:        "folder",
		HasChildren: false,
		Selectable:  &selectable,
	}
}

func sharePointCatalogEntryLabel(list sharePointListCatalogEntry) string {
	label := strings.TrimSpace(list.DisplayName)
	if label == "" {
		return opaqueSharePointListFallback(list.ID)
	}
	return label
}
