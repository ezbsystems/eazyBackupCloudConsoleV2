package graphfs

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync/atomic"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

func TestStreamReaderRetriesAfterIdleTimeout(t *testing.T) {
	payload := "hello-world-data"
	var rangeCalls atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Range") == "bytes=5-" {
			rangeCalls.Add(1)
			w.Header().Set("Content-Range", "bytes 5-16/17")
			w.WriteHeader(http.StatusPartialContent)
			_, _ = w.Write([]byte(payload[5:]))
			return
		}
		w.Header().Set("Content-Length", "17")
		_, _ = w.Write([]byte(payload[:5]))
	}))
	defer srv.Close()

	c := graph.NewTestClient(srv.URL, graph.ClientOptions{
		MaxRetries:             1,
		MaxConcurrency:         2,
		ContentReadIdleSeconds: 0,
		ContentReadRetries:     2,
	})
	gf := NewGraphFile(c, "file.bin", "/content", int64(len(payload)), time.Now().UTC())

	ctx := context.Background()
	firstBody, _, err := c.GetStream(ctx, "/content")
	if err != nil {
		t.Fatalf("GetStream: %v", err)
	}

	sr := &streamReader{
		ctx:        ctx,
		ReadCloser: &idleAfterFirstRead{inner: firstBody},
		size:       int64(len(payload)),
		entry:      gf,
		client:     c,
		path:       "/content",
		maxRetries: 2,
	}

	buf := make([]byte, 5)
	if _, err := sr.Read(buf); err != nil {
		t.Fatalf("first read: %v", err)
	}
	if string(buf) != payload[:5] {
		t.Fatalf("first chunk=%q", string(buf))
	}

	got, err := io.ReadAll(sr)
	if err != nil {
		t.Fatalf("read rest: %v", err)
	}
	if string(got) != payload[5:] {
		t.Fatalf("rest=%q want %q", string(got), payload[5:])
	}
	if rangeCalls.Load() != 1 {
		t.Fatalf("range calls=%d want 1", rangeCalls.Load())
	}
}

func TestStreamReaderExhaustsRetries(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if strings.HasPrefix(r.Header.Get("Range"), "bytes=7-") {
			http.Error(w, "fail", http.StatusInternalServerError)
			return
		}
		w.Header().Set("Content-Length", "32")
		_, _ = w.Write([]byte("partial"))
	}))
	defer srv.Close()

	c := graph.NewTestClient(srv.URL, graph.ClientOptions{
		MaxRetries:         0,
		MaxConcurrency:     2,
		ContentReadRetries: 1,
	})
	sr := &streamReader{
		ctx:        context.Background(),
		ReadCloser: &alwaysIdleReader{payload: []byte("partial")},
		size:       32,
		entry:      NewGraphFile(c, "file.bin", "/content", 32, time.Now().UTC()),
		client:     c,
		path:       "/content",
		maxRetries: 1,
	}

	buf := make([]byte, 16)
	if _, err := sr.Read(buf); err != nil {
		t.Fatalf("first read: %v", err)
	}
	_, err := sr.Read(buf)
	if err == nil {
		t.Fatal("expected error after retries exhausted")
	}
	if !strings.Contains(err.Error(), "graph content read failed after") && !strings.Contains(err.Error(), "graph content read retry") {
		t.Fatalf("err=%v", err)
	}
}

func TestStreamReaderSeekUsesOpenContext(t *testing.T) {
	var sawRange string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		sawRange = r.Header.Get("Range")
		w.Header().Set("Content-Length", "8")
		_, _ = w.Write([]byte("12345678"))
	}))
	defer srv.Close()

	c := graph.NewTestClient(srv.URL, graph.ClientOptions{
		MaxRetries:             1,
		MaxConcurrency:         2,
		ContentReadIdleSeconds: 0,
	})
	gf := NewGraphFile(c, "file.bin", "/content", 8, time.Now().UTC())

	ctx, cancel := context.WithCancel(context.Background())
	rc, err := gf.Open(ctx)
	if err != nil {
		t.Fatalf("Open: %v", err)
	}
	defer rc.Close()
	if _, err := rc.Seek(4, io.SeekStart); err != nil {
		t.Fatalf("Seek: %v", err)
	}
	cancel()
	if sawRange != "bytes=4-" {
		t.Fatalf("range=%q want bytes=4-", sawRange)
	}
}

type idleAfterFirstRead struct {
	inner io.ReadCloser
	done  bool
}

func (r *idleAfterFirstRead) Read(p []byte) (int, error) {
	if !r.done {
		r.done = true
		return r.inner.Read(p)
	}
	return 0, graph.ErrContentReadIdleTimeout
}

func (r *idleAfterFirstRead) Close() error {
	return r.inner.Close()
}

type alwaysIdleReader struct {
	payload []byte
	reads   int
}

func (r *alwaysIdleReader) Read(p []byte) (int, error) {
	if r.reads == 0 {
		r.reads++
		n := copy(p, r.payload)
		return n, nil
	}
	return 0, graph.ErrContentReadIdleTimeout
}

func (r *alwaysIdleReader) Close() error { return nil }
