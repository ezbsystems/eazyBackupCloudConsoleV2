package archive

import "testing"

func TestSnapshotToZipPath(t *testing.T) {
	tests := []struct {
		in   string
		want string
	}{
		{
			in:   "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/users/u1/mail/inbox/msg.json",
			want: "mail/u1/inbox/msg.json",
		},
		{
			in:   "tenant/users/u1/onedrive/content/SeederData/seed.txt",
			want: "onedrive/u1/SeederData/seed.txt",
		},
		{
			in:   "aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee/sites/site1/lists/list1/item.json",
			want: "sharepoint/site1/lists/list1/item.json",
		},
		{
			in:   "tenant/drives/d1/content/folder/file.docx",
			want: "onedrive/folder/file.docx",
		},
		{
			in:   "tenant/teams/t1/messages/m1.json",
			want: "teams/t1/messages/m1.json",
		},
	}
	for _, tc := range tests {
		if got := snapshotToZipPath(tc.in); got != tc.want {
			t.Fatalf("snapshotToZipPath(%q) = %q, want %q", tc.in, got, tc.want)
		}
	}
}

func TestShouldExportFile(t *testing.T) {
	if shouldExportFile("tenant/users/u1/mail/inbox/msg.json") != true {
		t.Fatal("expected mail json to export")
	}
	if shouldExportFile("tenant/users/u1/mail/inbox/_folder.json") != false {
		t.Fatal("expected _folder.json to skip")
	}
	if shouldExportFile("tenant/users/u1/mail/inbox/msg.removed.json") != false {
		t.Fatal("expected .removed.json to skip")
	}
}
