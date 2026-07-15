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

func TestDrivePathFromSnapshot(t *testing.T) {
	target := Target{}
	tests := []struct {
		name     string
		path     string
		wantID   string
		wantPath string
	}{
		{
			name:     "sharepoint content segment",
			path:     "tenant/sites/site/drives/b!drive/content/IT Testing/doc.docx",
			wantID:   "b!drive",
			wantPath: "/IT Testing/doc.docx",
		},
		{
			name:     "drive root file with content segment",
			path:     "tenant/sites/site/drives/b!drive/content/file.docx",
			wantID:   "b!drive",
			wantPath: "/file.docx",
		},
		{
			name:     "drive path without content segment",
			path:     "tenant/sites/site/drives/b!drive/foo.docx",
			wantID:   "b!drive",
			wantPath: "/foo.docx",
		},
		{
			name:     "onedrive content unchanged",
			path:     "tenant/users/user/onedrive/content/Documents/file.docx",
			wantID:   "",
			wantPath: "/Documents/file.docx",
		},
	}
	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			gotID, gotPath := drivePathFromSnapshot(tc.path, target)
			if gotID != tc.wantID || gotPath != tc.wantPath {
				t.Fatalf("drivePathFromSnapshot(%q) = (%q, %q), want (%q, %q)", tc.path, gotID, gotPath, tc.wantID, tc.wantPath)
			}
		})
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

func TestUseAlternateSharePointDrive(t *testing.T) {
	path := "tenant/sites/site-a/drives/b!source/content/SeederUploads/file.txt"
	if !useAlternateSharePointDrive(Target{DestinationMode: "alternate"}, path) {
		t.Fatal("expected alternate sharepoint path to use target drive resolution")
	}
	if useAlternateSharePointDrive(Target{DestinationMode: "original"}, path) {
		t.Fatal("original mode should keep source drive from path")
	}
	if useAlternateSharePointDrive(Target{DestinationMode: "alternate"}, "tenant/users/u1/mail/inbox/msg.json") {
		t.Fatal("mailbox paths should not trigger alternate sharepoint drive resolution")
	}
}
