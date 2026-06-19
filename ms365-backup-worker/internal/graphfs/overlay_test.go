package graphfs

import (
	"testing"
	"time"
)

func TestOverlayHasPathPrefix(t *testing.T) {
	b := NewOverlayBuilder()
	b.PutJSON("tenant/users/u1/onedrive/.catalog", []byte(`{}`), time.Now())
	if !b.HasPathPrefix("tenant/users/u1/onedrive") {
		t.Fatal("expected onedrive prefix match")
	}
	if b.HasPathPrefix("tenant/users/u2/onedrive") {
		t.Fatal("unexpected prefix match for other user")
	}
}
