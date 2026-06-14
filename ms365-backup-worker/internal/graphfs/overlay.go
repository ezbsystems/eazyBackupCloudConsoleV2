package graphfs

import (
	"context"
	"path"
	"strings"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

// itemPaths maps Graph item IDs to virtual paths for tombstone resolution.
type OverlayBuilder struct {
	entries   map[string]kopiafs.Entry
	removed   map[string]struct{}
	itemPaths map[string]string
}

func NewOverlayBuilder() *OverlayBuilder {
	return &OverlayBuilder{
		entries:   make(map[string]kopiafs.Entry),
		removed:   make(map[string]struct{}),
		itemPaths: make(map[string]string),
	}
}

func (b *OverlayBuilder) Put(path string, entry kopiafs.Entry) {
	p := normalizeVirtualPath(path)
	if p == "" || entry == nil {
		return
	}
	delete(b.removed, p)
	b.entries[p] = entry
}

func (b *OverlayBuilder) PutWithItemID(itemID, path string, entry kopiafs.Entry) {
	b.Put(path, entry)
	if itemID != "" {
		b.itemPaths[itemID] = normalizeVirtualPath(path)
	}
}

func (b *OverlayBuilder) RemoveByItemID(itemID string) {
	if itemID == "" {
		return
	}
	if p, ok := b.itemPaths[itemID]; ok {
		b.Remove(p)
		delete(b.itemPaths, itemID)
	}
}

func (b *OverlayBuilder) PutJSON(path string, content []byte, modTime time.Time) {
	b.Put(path, newStaticFile(baseName(path), content, modTime))
}

func (b *OverlayBuilder) Remove(path string) {
	p := normalizeVirtualPath(path)
	if p == "" {
		return
	}
	b.removed[p] = struct{}{}
	delete(b.entries, p)
}

func (b *OverlayBuilder) RemovePrefix(prefix string) {
	prefix = normalizeVirtualPath(prefix)
	if prefix == "" {
		return
	}
	for p := range b.entries {
		if p == prefix || strings.HasPrefix(p, prefix+"/") {
			delete(b.entries, p)
			b.removed[p] = struct{}{}
		}
	}
}

// MergePrior walks a prior Kopia snapshot directory and seeds metadata-only entries.
func (b *OverlayBuilder) MergePrior(ctx context.Context, dir kopiafs.Directory, relPrefix string) error {
	return walkPrior(ctx, dir, relPrefix, b)
}

func walkPrior(ctx context.Context, dir kopiafs.Directory, relPrefix string, b *OverlayBuilder) error {
	children, err := dir.Readdir(ctx)
	if err != nil {
		return err
	}
	for _, child := range children {
		name := child.Name()
		childRel := name
		if relPrefix != "" {
			childRel = relPrefix + "/" + name
		}
		if sub, isDir := child.(kopiafs.Directory); isDir {
			if err := walkPrior(ctx, sub, childRel, b); err != nil {
				return err
			}
			continue
		}
		if _, skip := b.removed[childRel]; skip {
			continue
		}
		if _, exists := b.entries[childRel]; exists {
			continue
		}
		b.entries[childRel] = child
	}
	return nil
}

func (b *OverlayBuilder) EntryCount() int {
	return len(b.entries)
}

func (b *OverlayBuilder) Build(rootName string) kopiafs.Entry {
	root := NewDirectory(rootName)
	for p, entry := range b.entries {
		if _, skip := b.removed[p]; skip {
			continue
		}
		parts := splitPath(p)
		if len(parts) == 0 {
			continue
		}
		cur := root
		for i := 0; i < len(parts)-1; i++ {
			name := parts[i]
			next, err := cur.Child(context.Background(), name)
			if err != nil {
				dir := NewDirectory(name)
				cur.AddEntry(dir)
				cur = dir
				continue
			}
			if md, ok := next.(*memoryDir); ok {
				cur = md
			} else {
				dir := NewDirectory(name)
				cur.AddEntry(dir)
				cur = dir
			}
		}
		cur.AddEntry(entry)
	}
	return root
}

func normalizeVirtualPath(p string) string {
	p = strings.Trim(strings.TrimSpace(p), "/")
	p = path.Clean("/" + p)
	return strings.TrimPrefix(p, "/")
}

func baseName(p string) string {
	parts := splitPath(p)
	if len(parts) == 0 {
		return "unknown"
	}
	return parts[len(parts)-1]
}
