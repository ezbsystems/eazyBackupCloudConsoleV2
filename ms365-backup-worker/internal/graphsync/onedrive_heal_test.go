package graphsync

import (
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestHealOneDriveRootFilesAddsMissingRootFile(t *testing.T) {
	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	user := "1533e37a-2e8f-4f24-8155-11777c70997d"
	drive := "b!drive"

	overlay := graphfs.NewOverlayBuilder()
	overlay.PutJSON(tenant+"/users/"+user+"/onedrive/content/SeederData/seed.txt", []byte("x"), time.Now())

	// Simulate root file not in overlay (incremental delta gap).
	items := []map[string]any{
		{
			"id":   "file-root-1",
			"name": "cometd_26.4.2_amd64.deb",
			"file": map[string]any{},
			"parentReference": map[string]any{
				"path": "/drives/" + drive + "/root:",
			},
		},
	}

	added := 0
	for _, item := range items {
		if isDriveFolder(item) {
			continue
		}
		path := driveContentPath(tenant, user, drive, item)
		if overlay.HasPath(path) {
			continue
		}
		overlay.PutWithItemID("file-root-1", path, graphfs.NewGraphFile(nil, "cometd_26.4.2_amd64.deb", "/drives/"+drive+"/items/file-root-1/content", 0, time.Now()))
		added++
	}

	want := tenant + "/users/" + user + "/onedrive/content/cometd_26.4.2_amd64.deb"
	if added != 1 {
		t.Fatalf("added = %d, want 1", added)
	}
	if !overlay.HasPath(want) {
		t.Fatalf("expected healed file at %q", want)
	}
}

func TestOverlayHasPathExact(t *testing.T) {
	b := graphfs.NewOverlayBuilder()
	b.PutJSON("tenant/users/u1/onedrive/content/a.txt", []byte("x"), time.Now())
	if !b.HasPath("tenant/users/u1/onedrive/content/a.txt") {
		t.Fatal("expected exact path match")
	}
	if b.HasPath("tenant/users/u1/onedrive/content") {
		t.Fatal("prefix should not match as exact path")
	}
}

func TestHealOneDriveRootFilesRelocatesMisroutedItem(t *testing.T) {
	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	user := "1533e37a-2e8f-4f24-8155-11777c70997d"
	drive := "b!drive"

	overlay := graphfs.NewOverlayBuilder()
	wrong := tenant + "/users/" + user + "/onedrive/content/drives/" + drive + "/root:/cometd_26.4.2_amd64.deb"
	right := tenant + "/users/" + user + "/onedrive/content/cometd_26.4.2_amd64.deb"
	overlay.PutWithItemID("file-root-1", wrong, graphfs.NewGraphFile(nil, "cometd_26.4.2_amd64.deb", "/drives/"+drive+"/items/file-root-1/content", 0, time.Now()))

	item := map[string]any{
		"id":   "file-root-1",
		"name": "cometd_26.4.2_amd64.deb",
		"file": map[string]any{},
		"parentReference": map[string]any{
			"path": "/drives/" + drive + "/root:",
		},
	}
	path := driveContentPath(tenant, user, drive, item)
	if existing, ok := overlay.ItemPath("file-root-1"); !ok || existing != wrong {
		t.Fatalf("item path = %q, want %q", existing, wrong)
	}
	if existing, ok := overlay.ItemPath("file-root-1"); ok && existing != path {
		overlay.RemoveByItemID("file-root-1")
	}
	gf := graphfs.NewGraphFile(nil, "cometd_26.4.2_amd64.deb", "/drives/"+drive+"/items/file-root-1/content", 0, time.Now())
	overlay.PutWithItemID("file-root-1", path, gf)

	if overlay.HasPath(wrong) {
		t.Fatal("misrouted path should be removed")
	}
	if !overlay.HasPath(right) {
		t.Fatal("expected file at corrected root path")
	}
}
