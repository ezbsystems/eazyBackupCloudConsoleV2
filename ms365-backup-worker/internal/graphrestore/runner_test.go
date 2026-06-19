package graphrestore

import "testing"

func TestIsDriveContentPath(t *testing.T) {
	tests := []struct {
		path string
		want bool
	}{
		{
			path: "tenant/users/u1/onedrive/content/cometd_26.4.2_amd64.deb",
			want: true,
		},
		{
			path: "tenant/users/u1/onedrive/content/SeederData/seed.txt",
			want: true,
		},
		{
			path: "tenant/users/u1/mail/inbox/msg.json",
			want: false,
		},
		{
			path: "tenant/users/u1/onedrive/content/meta.json",
			want: false,
		},
	}
	for _, tc := range tests {
		if got := isDriveContentPath(tc.path); got != tc.want {
			t.Fatalf("isDriveContentPath(%q) = %v, want %v", tc.path, got, tc.want)
		}
	}
}
