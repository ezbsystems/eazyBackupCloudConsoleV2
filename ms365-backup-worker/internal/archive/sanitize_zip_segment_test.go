package archive

import "testing"

func TestSanitizeZipSegment(t *testing.T) {
	tests := []struct {
		in   string
		want string
	}{
		{"Inbox", "Inbox"},
		{"RE: Project <urgent>", "RE_ Project urgent"},
		{"  spaced  name  ", "spaced name"},
		{"", ""},
		{string([]byte{'a', 0x07, 'b'}), "ab"},
	}
	for _, tc := range tests {
		if got := sanitizeZipSegment(tc.in); got != tc.want {
			t.Fatalf("sanitizeZipSegment(%q) = %q, want %q", tc.in, got, tc.want)
		}
	}
}
