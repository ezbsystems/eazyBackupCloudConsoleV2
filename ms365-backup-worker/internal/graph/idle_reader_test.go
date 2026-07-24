package graph

import (
	"context"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"
)

func TestIdleTimeoutReaderTimesOutAfterPartialBytes(t *testing.T) {
	r := newIdleTimeoutReader(&blockAfterFirstRead{}, time.Second)
	buf := make([]byte, 16)
	if _, err := r.Read(buf); err != nil {
		t.Fatalf("first read: %v", err)
	}

	start := time.Now()
	_, err := r.Read(buf)
	if !IsContentReadIdleTimeout(err) {
		t.Fatalf("second read err=%v want ErrContentReadIdleTimeout", err)
	}
	if elapsed := time.Since(start); elapsed < 900*time.Millisecond || elapsed > 3*time.Second {
		t.Fatalf("idle timeout took %v want ~1s", elapsed)
	}
	_ = r.Close()
}

type blockAfterFirstRead struct {
	n     int
	block chan struct{}
	once  sync.Once
}

func (b *blockAfterFirstRead) Read(p []byte) (int, error) {
	if b.n == 0 {
		b.n++
		copy(p, []byte("partial"))
		return len("partial"), nil
	}
	if b.block == nil {
		b.block = make(chan struct{})
	}
	select {
	case <-b.block:
		return 0, io.EOF
	}
}

func (b *blockAfterFirstRead) Close() error {
	b.once.Do(func() {
		if b.block != nil {
			close(b.block)
		}
	})
	return nil
}

func TestIdleTimeoutReaderContinuousTrickleDoesNotTimeout(t *testing.T) {
	pr, pw := io.Pipe()
	go func() {
		for i := 0; i < 4; i++ {
			_, _ = pw.Write([]byte("abcd"))
			time.Sleep(200 * time.Millisecond)
		}
		_ = pw.Close()
	}()

	r := newIdleTimeoutReader(pr, time.Second)
	buf := make([]byte, 4)
	total := 0
	for {
		n, err := r.Read(buf)
		total += n
		if err == io.EOF {
			break
		}
		if err != nil {
			t.Fatalf("read: %v", err)
		}
	}
	if total != 16 {
		t.Fatalf("read %d bytes want 16", total)
	}
	_ = r.Close()
}

func TestGetStreamAppliesIdleTimeoutWrapper(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Length", "8")
		_, _ = w.Write([]byte("12345678"))
	}))
	defer srv.Close()

	c := NewTestClient(srv.URL, ClientOptions{
		MaxRetries:             1,
		MaxConcurrency:         2,
		ContentReadIdleSeconds: 1,
	})
	if c.contentReadIdle != time.Second {
		t.Fatalf("contentReadIdle=%v want 1s", c.contentReadIdle)
	}

	rc, _, err := c.GetStream(context.Background(), "/content")
	if err != nil {
		t.Fatalf("GetStream: %v", err)
	}
	defer rc.Close()
	if _, ok := rc.(*streamBody).ReadCloser.(*idleTimeoutReader); !ok {
		t.Fatalf("expected idleTimeoutReader wrapper, got %T", rc.(*streamBody).ReadCloser)
	}
}

func TestGetStreamSemaphoreBalancedAfterIdleTimeout(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Length", "8")
		_, _ = w.Write([]byte("12345678"))
	}))
	defer srv.Close()

	c := NewTestClient(srv.URL, ClientOptions{
		MaxRetries:             1,
		MaxConcurrency:         2,
		ContentReadIdleSeconds: 1,
	})

	rc, _, err := c.GetStream(context.Background(), "/content")
	if err != nil {
		t.Fatalf("GetStream: %v", err)
	}

	pr, pw := io.Pipe()
	_ = rc.Close()
	// Simulate idle timeout path by closing an idle-wrapped body after partial read.
	wrapped := newIdleTimeoutReader(pr, time.Millisecond)
	_, _ = wrapped.Read(make([]byte, 2))
	_ = pw.Close()
	_, _ = wrapped.Read(make([]byte, 2))
	_ = wrapped.Close()

	inUse := len(c.sem)
	if inUse != 0 {
		t.Fatalf("workload semaphore unbalanced: occupancy=%d want 0", inUse)
	}

	rc2, _, err := c.GetStream(context.Background(), "/content")
	if err != nil {
		t.Fatalf("follow-up GetStream: %v", err)
	}
	_ = rc2.Close()
}

func TestGetStreamUsesStreamClientWithoutAbsoluteTimeout(t *testing.T) {
	payload := strings.Repeat("x", 16)
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Length", "16")
		_, _ = w.Write([]byte(payload))
	}))
	defer srv.Close()

	c := NewTestClient(srv.URL, ClientOptions{
		MaxRetries:             1,
		MaxConcurrency:         2,
		ContentReadIdleSeconds: 0,
	})
	if c.streamClient.Timeout != 0 {
		t.Fatalf("stream client timeout=%v want 0", c.streamClient.Timeout)
	}

	rc, _, err := c.GetStream(context.Background(), "/content")
	if err != nil {
		t.Fatalf("GetStream: %v", err)
	}
	defer rc.Close()
	data, err := io.ReadAll(rc)
	if err != nil {
		t.Fatalf("read all: %v", err)
	}
	if string(data) != payload {
		t.Fatalf("payload=%q", string(data))
	}
}

func TestIsContentReadIdleTimeout(t *testing.T) {
	if !IsContentReadIdleTimeout(ErrContentReadIdleTimeout) {
		t.Fatal("expected true for sentinel error")
	}
	if IsContentReadIdleTimeout(errors.New("other")) {
		t.Fatal("expected false for unrelated error")
	}
}
