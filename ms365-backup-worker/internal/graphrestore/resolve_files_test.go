package graphrestore

import "testing"

func TestIsFolderSelection(t *testing.T) {
	if !isFolderSelection(SelectionItem{Type: "folder", PathPrefix: "tenant/sites/x/content/"}) {
		t.Fatal("folder type should be folder selection")
	}
	if isFolderSelection(SelectionItem{Type: "file", Path: "tenant/sites/x/content/a.pdf"}) {
		t.Fatal("file should not be folder selection")
	}
}

func TestUnionFolderLogicalMapping(t *testing.T) {
	logical := "contoso/sites/site-safe/drives/drive-safe/content"
	cases := []struct {
		sourceFile, sourcePrefix string
	}{
		{"content/doc-a.pdf", "content"},
		{".shards/11/doc-b.pdf", ".shards/11"},
	}
	seen := map[string]struct{}{}
	for _, tc := range cases {
		logicalFile := MapSourceFileToLogical(tc.sourceFile, tc.sourcePrefix, logical)
		if logicalFile == "" {
			t.Fatalf("empty logical for %s", tc.sourceFile)
		}
		seen[logicalFile] = struct{}{}
	}
	if len(seen) != 2 {
		t.Fatalf("expected two distinct logical files, got %d", len(seen))
	}
}
