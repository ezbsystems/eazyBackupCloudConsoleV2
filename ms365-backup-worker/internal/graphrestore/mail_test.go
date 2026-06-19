package graphrestore

import "testing"

func TestGraphDriveItemPath(t *testing.T) {
	tests := []struct {
		itemPath string
		want     string
	}{
		{itemPath: "/cometd_26.4.2_amd64.deb", want: "/cometd_26.4.2_amd64.deb"},
		{itemPath: "/SeederData/seed-file-0001.txt", want: "/SeederData/seed-file-0001.txt"},
		{itemPath: "JellyfinMediaPlayer-1.12.0-windows-x64.exe", want: "/JellyfinMediaPlayer-1.12.0-windows-x64.exe"},
		{itemPath: "/", want: "/"},
	}
	for _, tc := range tests {
		if got := graphDriveItemPath(tc.itemPath); got != tc.want {
			t.Fatalf("graphDriveItemPath(%q) = %q, want %q", tc.itemPath, got, tc.want)
		}
	}
}

func TestDriveItemSize(t *testing.T) {
	if got := driveItemSize(map[string]any{"size": float64(325058560)}); got != 325058560 {
		t.Fatalf("driveItemSize float64 = %d", got)
	}
}

func TestItemMatchesDriveItemForSkip(t *testing.T) {
	const expectedSize = int64(1000)
	item := map[string]any{
		"name": "cometd_26.4.2_amd64.deb",
		"file": map[string]any{},
		"size": float64(1000),
	}
	if !itemMatchesDriveItemForSkip(item, "cometd_26.4.2_amd64.deb", expectedSize) {
		t.Fatal("expected matching file to skip")
	}
	if itemMatchesDriveItemForSkip(item, "other.deb", expectedSize) {
		t.Fatal("name mismatch should not skip")
	}
	if itemMatchesDriveItemForSkip(map[string]any{
		"name":   "cometd_26.4.2_amd64.deb",
		"folder": map[string]any{},
	}, "cometd_26.4.2_amd64.deb", expectedSize) {
		t.Fatal("folder should not skip")
	}
	if itemMatchesDriveItemForSkip(item, "cometd_26.4.2_amd64.deb", 2000) {
		t.Fatal("size mismatch should not skip")
	}
	if itemMatchesDriveItemForSkip(map[string]any{
		"name": "cometd_26.4.2_amd64.deb",
		"file": map[string]any{},
		"size": float64(0),
	}, "cometd_26.4.2_amd64.deb", 325058560) {
		t.Fatal("zero-byte stub should not skip when snapshot is larger")
	}
	if itemMatchesDriveItemForSkip(map[string]any{
		"name": "cometd_26.4.2_amd64.deb",
		"file": map[string]any{},
		"size": float64(0),
	}, "cometd_26.4.2_amd64.deb", 0) {
		t.Fatal("zero-byte remote should not skip when snapshot size unknown")
	}
}

func TestGraphDriveItemPathUploadURL(t *testing.T) {
	driveID := "b!drive"
	graphPath := graphDriveItemPath("/cometd_26.4.2_amd64.deb")
	got := "/drives/" + driveID + "/root:" + graphPath + ":/content"
	want := "/drives/b!drive/root:/cometd_26.4.2_amd64.deb:/content"
	if got != want {
		t.Fatalf("upload path = %q, want %q", got, want)
	}
}
