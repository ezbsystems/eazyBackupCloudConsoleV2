package graphfs

import (
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

func TestOverlayBuilderEntryStatsStaticAndGraph(t *testing.T) {
	c := graph.NewClient("token", "", graph.ClientOptions{})
	b := NewOverlayBuilder()
	b.PutJSON("lists/item1.json", []byte(`{"id":1}`), time.Now().UTC())
	b.PutJSON("lists/item2.json", []byte(`{"id":2}`), time.Now().UTC())
	b.Put("files/doc.pdf", NewGraphFile(c, "doc.pdf", "/content", 1024, time.Now().UTC()))

	stats := b.EntryStats()
	if stats.StaticFiles != 2 || stats.GraphFiles != 1 {
		t.Fatalf("static=%d graph=%d want 2/1", stats.StaticFiles, stats.GraphFiles)
	}
	if stats.TotalFiles() != 3 {
		t.Fatalf("total=%d want 3", stats.TotalFiles())
	}
	if stats.StaticRatio() < 0.66 {
		t.Fatalf("static ratio=%v want ~0.66", stats.StaticRatio())
	}
}

func TestOverlayBuilderEntryStatsIgnoresRemoved(t *testing.T) {
	b := NewOverlayBuilder()
	b.PutJSON("lists/item1.json", []byte(`{"id":1}`), time.Now().UTC())
	b.Remove("lists/item1.json")
	stats := b.EntryStats()
	if stats.TotalFiles() != 0 {
		t.Fatalf("total=%d want 0 after remove", stats.TotalFiles())
	}
}
