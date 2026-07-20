package graphfs

import (
	"encoding/json"
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

func TestOverlayReadJSON(t *testing.T) {
	b := NewOverlayBuilder()
	payload := map[string]any{"version": 1, "messages": map[string]any{"msg1": map[string]any{"subject": "hello"}}}
	raw, _ := json.Marshal(payload)
	b.PutJSON("tenant/users/u1/mail/inbox/_browse.json", raw, time.Now())

	var decoded map[string]any
	if !b.ReadJSON("tenant/users/u1/mail/inbox/_browse.json", &decoded) {
		t.Fatal("expected ReadJSON to succeed")
	}
	if decoded["version"].(float64) != 1 {
		t.Fatalf("version: %#v", decoded["version"])
	}

	var missing map[string]any
	if b.ReadJSON("tenant/users/u1/mail/inbox/missing.json", &missing) {
		t.Fatal("expected missing path to return false")
	}

	b.Remove("tenant/users/u1/mail/inbox/_browse.json")
	if b.ReadJSON("tenant/users/u1/mail/inbox/_browse.json", &missing) {
		t.Fatal("expected removed path to return false")
	}
}

func TestOverlayReadFileConcurrent(t *testing.T) {
	b := NewOverlayBuilder()
	b.PutJSON("tenant/users/u1/mail/inbox/_browse.json", []byte(`{"version":1}`), time.Now())
	done := make(chan struct{})
	go func() {
		for i := 0; i < 50; i++ {
			b.PutJSON("tenant/users/u1/mail/inbox/msg.json", []byte(`{"id":"m"}`), time.Now())
		}
		close(done)
	}()
	for i := 0; i < 50; i++ {
		if data, ok := b.ReadFile("tenant/users/u1/mail/inbox/_browse.json"); !ok || len(data) == 0 {
			t.Fatal("expected concurrent ReadFile to succeed")
		}
	}
	<-done
}
