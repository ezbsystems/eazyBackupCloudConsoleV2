package updater

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestRequiredStagingBytesIncludesReserve(t *testing.T) {
	offer := Offer{ArtifactSizeBytes: 50 << 20, UpdateReserveMiB: 256}
	got := requiredStagingBytes(offer)
	want := int64(50<<20 + 256<<20)
	if got != want {
		t.Fatalf("required=%d want=%d", got, want)
	}
}

func TestCheckStagingHeadroomRejectsInsufficientSpace(t *testing.T) {
	dir := t.TempDir()
	staging := filepath.Join(dir, stagingFileName)
	offer := Offer{ArtifactSizeBytes: 1 << 40, UpdateReserveMiB: 256}
	err := checkStagingHeadroom(staging, offer)
	if err == nil {
		t.Fatal("expected insufficient headroom error on small temp dir")
	}
	if !strings.Contains(err.Error(), "insufficient staging headroom") {
		t.Fatalf("unexpected error: %v", err)
	}
}

func TestWriteStagingArtifactRemovesOnFailure(t *testing.T) {
	dir := t.TempDir()
	staging := filepath.Join(dir, stagingFileName)
	_, err := writeStagingArtifact(staging, &failReader{})
	if err == nil {
		t.Fatal("expected write failure")
	}
	if _, statErr := os.Stat(staging); !os.IsNotExist(statErr) {
		t.Fatal("expected stale .new removed on write failure")
	}
}

type failReader struct{}

func (f *failReader) Read([]byte) (int, error) {
	return 0, os.ErrInvalid
}
