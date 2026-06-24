package archive

import (
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
