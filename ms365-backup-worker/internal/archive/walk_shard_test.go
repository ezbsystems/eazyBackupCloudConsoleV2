package archive

import (
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graphrestore"
)

func TestArchiveDedupUsesLogicalPath(t *testing.T) {
	logical := "tenant/sites/site1/drives/d1/content"
	seen := make(map[string]struct{})
	add := func(logicalPath, manifestID, physical string) bool {
		key := logicalPath
		if key == "" {
			key = manifestID + "\x00" + physical
		}
		if _, ok := seen[key]; ok {
			return false
		}
		seen[key] = struct{}{}
		return true
	}

	if !add(graphrestore.MapSourceFileToLogical("content/doc-a.pdf", "content", logical), "manifest-5", "content/doc-a.pdf") {
		t.Fatal("first shard file should be accepted")
	}
	if !add(graphrestore.MapSourceFileToLogical(".shards/11/doc-b.pdf", ".shards/11", logical), "manifest-11", ".shards/11/doc-b.pdf") {
		t.Fatal("second shard file should be accepted")
	}
	if add(graphrestore.MapSourceFileToLogical("content/doc-a.pdf", "content", logical), "manifest-5", "content/doc-a.pdf") {
		t.Fatal("duplicate logical path should be skipped")
	}
	if len(seen) != 2 {
		t.Fatalf("expected two logical files, got %d", len(seen))
	}
}

func TestEffectiveCollectRoots(t *testing.T) {
	item := api.RestoreItem{
		Type:        "folder",
		PathPrefix:  "tenant/sites/x/drives/y/content/",
		SourcePath:  "content",
		LogicalPath: "tenant/sites/x/drives/y/content",
	}
	if got := effectiveCollectRoot(item); got != "content" {
		t.Fatalf("effectiveCollectRoot = %q", got)
	}
	if got := effectiveLogicalCollectRoot(item); got != "tenant/sites/x/drives/y/content" {
		t.Fatalf("effectiveLogicalCollectRoot = %q", got)
	}
}
