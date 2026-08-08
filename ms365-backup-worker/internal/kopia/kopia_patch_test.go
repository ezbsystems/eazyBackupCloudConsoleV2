package kopia

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestKopiaPatchIndexFetchParallelism(t *testing.T) {
	t.Helper()
	root := filepath.Join("..", "..", "third_party", "kopia")
	manager := filepath.Join(root, "repo", "content", "content_manager.go")
	s3storage := filepath.Join(root, "repo", "blob", "s3", "s3_storage.go")

	managerBody, err := os.ReadFile(manager)
	if err != nil {
		t.Fatalf("read content_manager.go: %v", err)
	}
	if !strings.Contains(string(managerBody), "parallelFetches          = 32") {
		t.Fatalf("expected patched parallelFetches=32 in %s", manager)
	}

	s3Body, err := os.ReadFile(s3storage)
	if err != nil {
		t.Fatalf("read s3_storage.go: %v", err)
	}
	if !strings.Contains(string(s3Body), "indexFetchParallelism = 32") {
		t.Fatalf("expected patched S3 transport idle conns in %s", s3storage)
	}
}
