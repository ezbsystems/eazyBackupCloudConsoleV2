package graph

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

func TestParseRetryAfterNumeric(t *testing.T) {
	d := parseRetryAfter("30", 0, 2*time.Second)
	if d != 30*time.Second {
		t.Fatalf("expected 30s, got %v", d)
	}
}

func TestClientRetries429WithRetryAfter(t *testing.T) {
	var calls int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		calls++
		if calls == 1 {
			w.Header().Set("Retry-After", "1")
			w.WriteHeader(http.StatusTooManyRequests)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"value":[]}`))
	}))
	defer srv.Close()

	c := &Client{
		token:      "test",
		graphBase:  srv.URL,
		httpClient: srv.Client(),
		maxRetries: 3,
		retryDelay: 100 * time.Millisecond,
		sem:        make(chan struct{}, 4),
	}
	_, err := c.GetJSON(context.Background(), "/users", nil)
	if err != nil {
		t.Fatalf("GetJSON: %v", err)
	}
	if calls < 2 {
		t.Fatalf("expected retry, calls=%d", calls)
	}
}

func TestGetStreamReturnsBodyWithoutBuffering(t *testing.T) {
	payload := strings.Repeat("x", 8192)
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Range") != "" {
			w.WriteHeader(http.StatusRequestedRangeNotSatisfiable)
			return
		}
		w.Header().Set("Content-Length", "8192")
		_, _ = w.Write([]byte(payload))
	}))
	defer srv.Close()

	c := &Client{
		token:      "test",
		graphBase:  srv.URL,
		httpClient: srv.Client(),
		maxRetries: 1,
		retryDelay: 50 * time.Millisecond,
		sem:        make(chan struct{}, 2),
	}
	rc, size, err := c.GetStream(context.Background(), "/content")
	if err != nil {
		t.Fatalf("GetStream: %v", err)
	}
	defer rc.Close()
	if size != 8192 {
		t.Fatalf("size=%d want 8192", size)
	}
	buf := make([]byte, 256)
	total := 0
	for {
		n, err := rc.Read(buf)
		total += n
		if err == io.EOF {
			break
		}
		if err != nil {
			t.Fatalf("read: %v", err)
		}
	}
	if total != 8192 {
		t.Fatalf("read %d bytes want 8192", total)
	}
}

func TestBatchGetMessagesChunking(t *testing.T) {
	if maxBatchRequests != 20 {
		t.Fatalf("unexpected batch size %d", maxBatchRequests)
	}
}
