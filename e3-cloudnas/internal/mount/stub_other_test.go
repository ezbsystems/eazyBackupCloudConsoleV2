//go:build !windows || !cgo

package mount

import (
	"strings"
	"testing"
)

func TestWinFspStubIsUnavailable(t *testing.T) {
	if WinFspAvailable() {
		t.Fatal("WinFspAvailable() = true on unsupported build")
	}

	mounter, err := NewWinFspMounter("test")
	if err == nil {
		t.Fatal("NewWinFspMounter() error = nil, want unsupported error")
	}
	if mounter != nil {
		t.Fatalf("NewWinFspMounter() mounter = %T, want nil", mounter)
	}
	if !strings.Contains(err.Error(), "windows with cgo") {
		t.Fatalf("error = %q, want Windows/cgo explanation", err)
	}
}
