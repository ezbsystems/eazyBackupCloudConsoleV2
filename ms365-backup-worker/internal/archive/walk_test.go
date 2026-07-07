package archive

import (
	"fmt"
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

func TestIsWholeManifestSelection(t *testing.T) {
	tests := []struct {
		name string
		item api.RestoreItem
		want bool
	}{
		{
			name: "resource root",
			item: api.RestoreItem{Type: "resource", ManifestID: "abc123"},
			want: true,
		},
		{
			name: "folder prefix",
			item: api.RestoreItem{Type: "folder", ManifestID: "abc123", PathPrefix: "tenant/users/u1/mail/"},
			want: false,
		},
		{
			name: "file path",
			item: api.RestoreItem{Type: "file", ManifestID: "abc123", Path: "tenant/users/u1/mail/inbox/msg.json"},
			want: false,
		},
		{
			name: "empty without manifest",
			item: api.RestoreItem{Type: "resource"},
			want: false,
		},
	}
	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			if got := isWholeManifestSelection(tc.item); got != tc.want {
				t.Fatalf("isWholeManifestSelection() = %v, want %v", got, tc.want)
			}
		})
	}
}

func TestNormalizeCollectRoot(t *testing.T) {
	tests := []struct {
		in, want string
	}{
		{"tenant/users/u1/calendars/", "tenant/users/u1/calendar"},
		{"tenant/users/u1/calendars", "tenant/users/u1/calendar"},
		{"tenant/groups/g1/calendars/messages", "tenant/groups/g1/calendar/messages"},
		{"tenant/users/u1/mail/inbox", "tenant/users/u1/mail/inbox"},
	}
	for _, tc := range tests {
		if got := normalizeCollectRoot(tc.in); got != tc.want {
			t.Fatalf("normalizeCollectRoot(%q) = %q, want %q", tc.in, got, tc.want)
		}
	}
}

func TestIsCollectPathMissing(t *testing.T) {
	if !isCollectPathMissing(fmt.Errorf("path not found: tenant/users/u1/tasks")) {
		t.Fatal("expected path not found")
	}
	if isCollectPathMissing(fmt.Errorf("upload failed")) {
		t.Fatal("expected false for other errors")
	}
}
