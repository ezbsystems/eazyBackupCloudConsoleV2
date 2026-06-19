package graphsync

import "testing"

func TestDriveContentBaseCoalescedUser(t *testing.T) {
	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	user := "1533e37a-2e8f-4f24-8155-11777c70997d"
	drive := "b!drive"

	got := driveContentBase(tenant, user, drive)
	want := tenant + "/users/" + user + "/onedrive/content"
	if got != want {
		t.Fatalf("driveContentBase() = %q, want %q", got, want)
	}
}

func TestDriveContentBaseDriveWorkload(t *testing.T) {
	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	drive := "b!drive"

	got := driveContentBase(tenant, "", drive)
	want := tenant + "/drives/b!drive/content"
	if got != want {
		t.Fatalf("driveContentBase() = %q, want %q", got, want)
	}
}

func TestDriveRelativePathRootLevel(t *testing.T) {
	drive := "b!MpMKMk4AikK"
	item := map[string]any{
		"parentReference": map[string]any{
			"path": "/drives/" + drive + "/root:",
		},
	}
	if got := driveRelativePath(item); got != "" {
		t.Fatalf("driveRelativePath(root) = %q, want empty", got)
	}
}

func TestDriveRelativePathSubfolder(t *testing.T) {
	drive := "b!MpMKMk4AikK"
	item := map[string]any{
		"parentReference": map[string]any{
			"path": "/drives/" + drive + "/root:/SeederData",
		},
	}
	if got := driveRelativePath(item); got != "SeederData" {
		t.Fatalf("driveRelativePath(subfolder) = %q, want SeederData", got)
	}
}

func TestDriveRelativePathNested(t *testing.T) {
	drive := "b!MpMKMk4AikK"
	item := map[string]any{
		"parentReference": map[string]any{
			"path": "/drives/" + drive + "/root:/A/B",
		},
	}
	if got := driveRelativePath(item); got != "A/B" {
		t.Fatalf("driveRelativePath(nested) = %q, want A/B", got)
	}
}

func TestDriveRelativePathMissingParentRef(t *testing.T) {
	if got := driveRelativePath(map[string]any{}); got != "" {
		t.Fatalf("driveRelativePath(no parent) = %q, want empty", got)
	}
	if got := driveRelativePath(map[string]any{
		"parentReference": map[string]any{"path": ""},
	}); got != "" {
		t.Fatalf("driveRelativePath(empty path) = %q, want empty", got)
	}
}

func TestDriveContentPathRootLevelCoalescedUser(t *testing.T) {
	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	user := "1533e37a-2e8f-4f24-8155-11777c70997d"
	drive := "b!MpMKMk4AikK"
	item := map[string]any{
		"name": "cometd_26.4.2_amd64.deb",
		"parentReference": map[string]any{
			"path": "/drives/" + drive + "/root:",
		},
	}
	want := tenant + "/users/" + user + "/onedrive/content/cometd_26.4.2_amd64.deb"
	if got := driveContentPath(tenant, user, drive, item); got != want {
		t.Fatalf("driveContentPath(root) = %q, want %q", got, want)
	}
}

func TestDriveContentPathSubfolderCoalescedUser(t *testing.T) {
	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	user := "1533e37a-2e8f-4f24-8155-11777c70997d"
	drive := "b!MpMKMk4AikK"
	item := map[string]any{
		"name": "seed-file-0001.txt",
		"parentReference": map[string]any{
			"path": "/drives/" + drive + "/root:/SeederData",
		},
	}
	want := tenant + "/users/" + user + "/onedrive/content/SeederData/seed-file-0001.txt"
	if got := driveContentPath(tenant, user, drive, item); got != want {
		t.Fatalf("driveContentPath(subfolder) = %q, want %q", got, want)
	}
}

func TestDriveContentPathRootLevelDriveWorkload(t *testing.T) {
	tenant := "cfb5450a-eb80-4c61-aecc-9dca87649cf6"
	drive := "b!drive"
	item := map[string]any{
		"name": "report.pdf",
		"parentReference": map[string]any{
			"path": "/drives/" + drive + "/root:",
		},
	}
	want := tenant + "/drives/b!drive/content/report.pdf"
	if got := driveContentPath(tenant, "", drive, item); got != want {
		t.Fatalf("driveContentPath(drive workload) = %q, want %q", got, want)
	}
}
