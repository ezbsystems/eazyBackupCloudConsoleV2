package configapply

import (
	"os"
	"path/filepath"
	"testing"
)

func TestAppliedVersionRoundTrip(t *testing.T) {
	dir := t.TempDir()
	configPath := filepath.Join(dir, "config.yaml")
	if err := WriteAppliedVersion(configPath, 7); err != nil {
		t.Fatalf("WriteAppliedVersion: %v", err)
	}
	if got := ReadAppliedVersion(configPath); got != 7 {
		t.Fatalf("ReadAppliedVersion = %d, want 7", got)
	}
}

func TestApplyRejectsBadSHA256(t *testing.T) {
	dir := t.TempDir()
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
