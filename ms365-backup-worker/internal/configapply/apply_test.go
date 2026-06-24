package configapply

import (
	"crypto/sha256"
	"encoding/hex"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestAppliedVersionRoundTrip(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("MS365_WORKER_STATE_DIR", filepath.Join(dir, "state"))
	configPath := filepath.Join(dir, "config.yaml")
	if err := WriteAppliedVersion(configPath, 7); err != nil {
		t.Fatalf("WriteAppliedVersion: %v", err)
	}
	if got := ReadAppliedVersion(configPath); got != 7 {
		t.Fatalf("ReadAppliedVersion = %d, want 7", got)
	}
}

func TestReadAppliedVersionLegacyPath(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("MS365_WORKER_STATE_DIR", filepath.Join(dir, "state"))
	configPath := filepath.Join(dir, "config.yaml")
	legacy := legacyAppliedVersionPath(configPath)
	if err := os.WriteFile(legacy, []byte("3\n"), 0644); err != nil {
		t.Fatal(err)
	}
	if got := ReadAppliedVersion(configPath); got != 3 {
		t.Fatalf("ReadAppliedVersion = %d, want 3", got)
	}
}

func TestApplyRejectsBadSHA256(t *testing.T) {
	dir := t.TempDir()
	t.Setenv("MS365_WORKER_STATE_DIR", filepath.Join(dir, "state"))
	configPath := filepath.Join(dir, "config.yaml")
	yaml := []byte("api:\n  base_url: https://example.test\nworker:\n  token: tok\n")
	err := Apply(configPath, 1, "deadbeef", yaml)
	if err == nil {
		t.Fatal("expected sha256 mismatch error")
	}
	if _, statErr := os.Stat(configPath); statErr == nil {
		t.Fatal("config file should not be written on sha256 failure")
	}
}

func TestApplyWithReadOnlyConfigDir(t *testing.T) {
	root := t.TempDir()
	etcDir := filepath.Join(root, "etc", "ms365-backup-worker")
	if err := os.MkdirAll(etcDir, 0555); err != nil {
		t.Fatal(err)
	}
	configPath := filepath.Join(etcDir, "config.yaml")
	existing := []byte(`api:
  base_url: https://dev.example.test
worker:
  node_id: node-1
  token: secret-token
`)
	if err := os.WriteFile(configPath, existing, 0644); err != nil {
		t.Fatal(err)
	}
	stateDir := filepath.Join(root, "var", "lib", "ms365-backup-worker")
	t.Setenv("MS365_WORKER_STATE_DIR", stateDir)

	fleetYAML := []byte(`worker:
  archive_parallel_extracts: 48
api:
  base_url: https://dev.example.test
`)
	sum := sha256.Sum256(fleetYAML)
	if err := Apply(configPath, 2, hex.EncodeToString(sum[:]), fleetYAML); err != nil {
		t.Fatalf("Apply: %v", err)
	}
	if got := ReadAppliedVersion(configPath); got != 2 {
		t.Fatalf("ReadAppliedVersion = %d, want 2", got)
	}
	written, err := os.ReadFile(configPath)
	if err != nil {
		t.Fatal(err)
	}
	if !strings.Contains(string(written), "archive_parallel_extracts: 48") {
		t.Fatalf("config not updated: %s", written)
	}
	if _, err := os.Stat(filepath.Join(etcDir, ".config-merge-test")); err == nil {
		t.Fatal("merge temp should not be created in read-only config dir")
	}
}
