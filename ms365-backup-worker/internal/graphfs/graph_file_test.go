package graphfs

import (
	"context"
	"fmt"
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
	if !strings.Contains(err.Error(), "graph content read failed after") &&
		!strings.Contains(err.Error(), "graph content read retry") &&
		!strings.Contains(err.Error(), "range resume") {
		t.Fatalf("err=%v", err)
	}
}

func TestStreamReaderSeekUsesOpenContext(t *testing.T) {
	var sawRange string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		sawRange = r.Header.Get("Range")
		if sawRange == "bytes=4-" {
			w.Header().Set("Content-Range", "bytes 4-7/8")
			w.WriteHeader(http.StatusPartialContent)
			_, _ = w.Write([]byte("5678"))
			return
		}
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

func TestStreamReaderThroughputRecoveryAtExactOffset(t *testing.T) {
	const fileSize = 20 << 20
	var rangeCalls atomic.Int32
	var lastRangeOffset atomic.Int64

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		rng := r.Header.Get("Range")
		if rng == "" {
			w.Header().Set("Content-Length", "1048576")
			_, _ = w.Write(make([]byte, 1024))
			return
		}
		rangeCalls.Add(1)
		if !strings.HasPrefix(rng, "bytes=") {
			http.Error(w, "bad range", http.StatusBadRequest)
			return
		}
		parts := strings.TrimPrefix(rng, "bytes=")
		offStr, _, _ := strings.Cut(parts, "-")
		var off int64
		_, _ = fmt.Sscanf(offStr, "%d", &off)
		lastRangeOffset.Store(off)
		w.Header().Set("Content-Range", fmt.Sprintf("bytes %d-%d/%d", off, off+1023, fileSize))
		w.WriteHeader(http.StatusPartialContent)
		_, _ = w.Write(make([]byte, 1024))
	}))
	defer srv.Close()

	c := graph.NewTestClient(srv.URL, graph.ClientOptions{
		MaxRetries:                   0,
		MaxConcurrency:               4,
		ContentReadIdleSeconds:       0,
		ContentReadRetries:           3,
		ContentReadMinBytesPerSecond: 65536,
		ContentReadMinWindowSeconds:  1,
		ContentReadMinFileSizeBytes:  16 << 20,
	})
	gf := NewGraphFile(c, "large.bin", "/content", fileSize, time.Now().UTC())

	slow := &slowTrickleReader{
		chunk:    64,
		interval: 50 * time.Millisecond,
		limit:    2048,
	}
	sr := &streamReader{
		ctx:        context.Background(),
		ReadCloser: slow,
		size:       fileSize,
		entry:      gf,
		client:     c,
		path:       "/content",
		maxRetries: 3,
		policy: ContentThroughputPolicy{
			MinBytesPerSecond: 65536,
			WindowSeconds:     1,
			MinFileSizeBytes:  16 << 20,
		},
		throughput: newThroughputWindow(ContentThroughputPolicy{
			MinBytesPerSecond: 65536,
			WindowSeconds:     1,
			MinFileSizeBytes:  16 << 20,
		}, nil),
	}

	buf := make([]byte, 4096)
	deadline := time.Now().Add(3 * time.Second)
	for time.Now().Before(deadline) {
		if _, err := sr.Read(buf); err != nil && !IsContentThroughputStall(err) {
			if rangeCalls.Load() > 0 {
				break
			}
		}
		if rangeCalls.Load() > 0 {
			break
		}
	}
	if rangeCalls.Load() == 0 {
		t.Fatal("expected Range resume after slow throughput window")
	}
	if got := lastRangeOffset.Load(); got != int64(slow.delivered) {
		t.Fatalf("range offset=%d want %d", got, slow.delivered)
	}
}

func TestStreamReaderSmallFileBypassesThroughputGuard(t *testing.T) {
	const fileSize = 1 << 20 // 1 MiB < 16 MiB threshold
	c := graph.NewTestClient("http://unused", graph.ClientOptions{
		MaxConcurrency:               2,
		ContentReadRetries:           1,
		ContentReadMinBytesPerSecond: 65536,
		ContentReadMinWindowSeconds:  1,
		ContentReadMinFileSizeBytes:  16 << 20,
	})
	sr := &streamReader{
		ctx:        context.Background(),
		ReadCloser: &slowTrickleReader{chunk: 1, interval: 100 * time.Millisecond, limit: 4096},
		size:       fileSize,
		client:     c,
		path:       "/content",
		maxRetries: 1,
		policy: throughputPolicyFromClient(c),
		throughput: newThroughputWindow(throughputPolicyFromClient(c), nil),
	}
	buf := make([]byte, 256)
	for i := 0; i < 30; i++ {
		if _, err := sr.Read(buf); err != nil && err != io.EOF {
			t.Fatalf("unexpected error on small file: %v", err)
		}
	}
}

func TestStreamReaderThroughputRecoveryExhausted(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Range") != "" {
			http.Error(w, "fail", http.StatusInternalServerError)
			return
		}
		w.Header().Set("Content-Length", "1048576")
		_, _ = w.Write(make([]byte, 512))
	}))
	defer srv.Close()

	c := graph.NewTestClient(srv.URL, graph.ClientOptions{
		MaxRetries:                   0,
		MaxConcurrency:               2,
		ContentReadRetries:           1,
		ContentReadMinBytesPerSecond: 65536,
		ContentReadMinWindowSeconds:  1,
		ContentReadMinFileSizeBytes:  16 << 20,
	})
	policy := ContentThroughputPolicy{
		MinBytesPerSecond: 65536,
		WindowSeconds:     1,
		MinFileSizeBytes:  16 << 20,
	}
	sr := &streamReader{
		ctx:        context.Background(),
		ReadCloser: &slowTrickleReader{chunk: 32, interval: 200 * time.Millisecond, limit: 8192},
		size:       20 << 20,
		client:     c,
		path:       "/content",
		maxRetries: 1,
		policy:     policy,
		throughput: newThroughputWindow(policy, nil),
	}
	buf := make([]byte, 1024)
	var lastErr error
	deadline := time.Now().Add(15 * time.Second)
	for time.Now().Before(deadline) {
		_, lastErr = sr.Read(buf)
		if lastErr != nil {
			break
		}
	}
	if lastErr == nil {
		t.Fatal("expected error after recovery exhausted")
	}
	if !IsContentThroughputStall(lastErr) && !strings.Contains(lastErr.Error(), "graph content read failed") {
		t.Fatalf("err=%v", lastErr)
	}
	_, _, slow, recoveries, exhausted, _ := c.ContentStreamTelemetry()
	if slow == 0 {
		t.Fatal("expected slow detection telemetry")
	}
	if exhausted == 0 {
		t.Fatal("expected recovery exhausted telemetry")
	}
	_ = recoveries
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

type slowTrickleReader struct {
	chunk     int
	interval  time.Duration
	limit     int
	delivered int64
}

func (r *slowTrickleReader) Read(p []byte) (int, error) {
	if r.delivered >= int64(r.limit) {
		time.Sleep(r.interval)
	}
	n := r.chunk
	if n > len(p) {
		n = len(p)
	}
	if int64(n)+r.delivered > int64(r.limit) {
		n = int(int64(r.limit) - r.delivered)
	}
	if n <= 0 {
		time.Sleep(r.interval)
		return 1, nil
	}
	for i := range p[:n] {
		p[i] = 'x'
	}
	r.delivered += int64(n)
	time.Sleep(r.interval)
	return n, nil
}

func (r *slowTrickleReader) Close() error { return nil }
