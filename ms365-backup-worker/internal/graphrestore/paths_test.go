package graphrestore

import "testing"

func TestMapSourceFileToLogical(t *testing.T) {
	logical := "contoso/sites/site1/drives/d1/content"
	tests := []struct {
		sourceFile, sourcePrefix, want string
	}{
		{"content/a.pdf", "content", logical + "/a.pdf"},
		{".shards/5/a.pdf", ".shards/5", logical + "/a.pdf"},
		{"content", "content", logical},
		{"content/nested/b.pdf", "content", logical + "/nested/b.pdf"},
	}
	for _, tc := range tests {
		got := MapSourceFileToLogical(tc.sourceFile, tc.sourcePrefix, logical)
		if got != tc.want {
			t.Fatalf("MapSourceFileToLogical(%q, %q, %q) = %q, want %q",
				tc.sourceFile, tc.sourcePrefix, logical, got, tc.want)
		}
	}
}

func TestEffectivePaths(t *testing.T) {
	item := SelectionItem{
		Path:        "tenant/sites/x/drives/y/content/doc.pdf",
		SourcePath:  "content/doc.pdf",
		LogicalPath: "tenant/sites/x/drives/y/content/doc.pdf",
	}
	if got := EffectiveSourcePath(item); got != "content/doc.pdf" {
		t.Fatalf("EffectiveSourcePath = %q", got)
	}
	if got := EffectiveLogicalPath(item); got != "tenant/sites/x/drives/y/content/doc.pdf" {
		t.Fatalf("EffectiveLogicalPath = %q", got)
	}
}
