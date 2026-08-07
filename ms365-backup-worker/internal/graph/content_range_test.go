package graph

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestGetStreamRangeRequires206(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Range") == "bytes=1024-" {
			w.Header().Set("Content-Length", "1024")
			_, _ = w.Write(make([]byte, 1024))
			return
		}
		w.Header().Set("Content-Length", "2048")
		_, _ = w.Write(make([]byte, 2048))
	}))
	defer srv.Close()

	c := NewTestClient(srv.URL, ClientOptions{MaxRetries: 0, MaxConcurrency: 2})
	_, _, err := c.GetStreamRange(context.Background(), "/content", 1024)
	if err == nil {
		t.Fatal("expected error for 200 response on range resume")
	}
	if got := err.Error(); got == "" || !containsAll(got, "206", "1024") {
		t.Fatalf("err=%q", got)
	}
}

func TestGetStreamRangeRejectsMismatchedContentRange(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Range", "bytes 0-1023/2048")
		w.WriteHeader(http.StatusPartialContent)
		_, _ = w.Write(make([]byte, 1024))
	}))
	defer srv.Close()

	c := NewTestClient(srv.URL, ClientOptions{MaxRetries: 0, MaxConcurrency: 2})
	_, _, err := c.GetStreamRange(context.Background(), "/content", 1024)
	if err == nil {
		t.Fatal("expected error for mismatched Content-Range start")
	}
}

func TestGetStreamRangeAcceptsValidPartialContent(t *testing.T) {
	const total = int64(4096)
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Range") != "bytes=1024-" {
			http.Error(w, "expected range", http.StatusBadRequest)
			return
		}
		w.Header().Set("Content-Range", "bytes 1024-2047/4096")
		w.WriteHeader(http.StatusPartialContent)
		_, _ = w.Write(make([]byte, 1024))
	}))
	defer srv.Close()

	c := NewTestClient(srv.URL, ClientOptions{MaxRetries: 0, MaxConcurrency: 2})
	rc, size, err := c.GetStreamRange(context.Background(), "/content", 1024)
	if err != nil {
		t.Fatalf("GetStreamRange: %v", err)
	}
	defer rc.Close()
	if size != total {
		t.Fatalf("size=%d want %d", size, total)
	}
	n, err := io.Copy(io.Discard, rc)
	if err != nil {
		t.Fatalf("read: %v", err)
	}
	if n != 1024 {
		t.Fatalf("read %d bytes want 1024", n)
	}
}

func TestParseContentRangeStart(t *testing.T) {
	start, ok := parseContentRangeStart("bytes 1024-2047/4096")
	if !ok || start != 1024 {
		t.Fatalf("start=%d ok=%v", start, ok)
	}
	if _, ok := parseContentRangeStart("bytes 0-9/10"); !ok {
		t.Fatal("expected valid parse")
	}
	if _, ok := parseContentRangeStart("invalid"); ok {
		t.Fatal("expected invalid parse")
	}
}

func containsAll(s string, parts ...string) bool {
	for _, p := range parts {
		if !containsSubstring(s, p) {
			return false
		}
	}
	return true
}

func containsSubstring(s, sub string) bool {
	return len(sub) == 0 || (len(s) >= len(sub) && indexOf(s, sub) >= 0)
}

func indexOf(s, sub string) int {
	for i := 0; i+len(sub) <= len(s); i++ {
		if s[i:i+len(sub)] == sub {
			return i
		}
	}
	return -1
}
