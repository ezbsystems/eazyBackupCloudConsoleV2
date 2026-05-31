package agent

import (
	"context"
	"os"
	"path/filepath"
	"testing"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/kopia/kopia/fs/localfs"
)

func TestWrapRenamedEntryFileImplementsKopiaFile(t *testing.T) {
	t.Parallel()

	dir := t.TempDir()
	path := filepath.Join(dir, "sample.txt")
	if err := os.WriteFile(path, []byte("ok"), 0o644); err != nil {
		t.Fatal(err)
	}

	entry, err := localfs.NewEntry(path)
	if err != nil {
		t.Fatal(err)
	}

	wrapped := wrapRenamedEntry(entry, "renamed.txt")
	if wrapped.Name() != "renamed.txt" {
		t.Fatalf("name = %q, want renamed.txt", wrapped.Name())
	}

	f, ok := wrapped.(kopiafs.File)
	if !ok {
		t.Fatalf("wrapped type %T does not implement kopiafs.File", wrapped)
	}

	r, err := f.Open(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	defer r.Close()

	buf := make([]byte, 2)
	if _, err := r.Read(buf); err != nil {
		t.Fatal(err)
	}
	if string(buf) != "ok" {
		t.Fatalf("read %q, want ok", string(buf))
	}
}
