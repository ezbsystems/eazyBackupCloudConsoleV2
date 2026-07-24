package graph

import (
	"context"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
	"runtime"
	"sync/atomic"
	"testing"
	"time"
)

func TestGetStreamHeaderTimeoutWhenServerHangs(t *testing.T) {
	block := make(chan struct{})
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		<-block
	}))
	t.Cleanup(func() {
		close(block)
		srv.Close()
	})

	c := NewTestClient(srv.URL, ClientOptions{
		MaxRetries:                  0,
		MaxConcurrency:              2,
		StreamResponseHeaderSeconds: 1,
		RetryBaseDelayMs:            10,
	})

	start := time.Now()
	_, _, err := c.GetStream(context.Background(), "/content")
	if err == nil {
		t.Fatal("expected header timeout error")
	}
	elapsed := time.Since(start)
	if elapsed > 3*time.Second {
		t.Fatalf("header timeout took %v want <=3s", elapsed)
	}
}

func TestGetStreamContextCancelUnblocksHungDo(t *testing.T) {
	block := make(chan struct{})
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		<-block
	}))
	t.Cleanup(func() {
		close(block)
		srv.Close()
	})

	c := NewTestClient(srv.URL, ClientOptions{
		MaxRetries:                  0,
		MaxConcurrency:              2,
		StreamResponseHeaderSeconds: 120,
	})

	ctx, cancel := context.WithCancel(context.Background())
	done := make(chan struct{})
	go func() {
		defer close(done)
		_, _, _ = c.GetStream(ctx, "/content")
	}()

	time.Sleep(100 * time.Millisecond)
	start := time.Now()
	cancel()

	select {
	case <-done:
	case <-time.After(3 * time.Second):
		t.Fatal("GetStream did not return after context cancel")
	}
	if elapsed := time.Since(start); elapsed > 2*time.Second {
		t.Fatalf("cancel unblock took %v want prompt return", elapsed)
	}
}

func TestGetStreamBodyIdleTimeoutAfterHeaders(t *testing.T) {
	block := make(chan struct{})
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Length", "32")
		if _, err := w.Write([]byte("partial-bytes")); err != nil {
			return
		}
		if f, ok := w.(http.Flusher); ok {
			f.Flush()
		}
		<-block
	}))
	t.Cleanup(func() {
		close(block)
		srv.Close()
	})

	c := NewTestClient(srv.URL, ClientOptions{
		MaxRetries:             0,
		MaxConcurrency:         2,
		ContentReadIdleSeconds: 1,
	})

	rc, _, err := c.GetStream(context.Background(), "/content")
	if err != nil {
		t.Fatalf("GetStream: %v", err)
	}
	defer rc.Close()

	buf := make([]byte, 64)
	if _, err := rc.Read(buf); err != nil && !errors.Is(err, io.EOF) {
		t.Fatalf("first read: %v", err)
	}

	start := time.Now()
	_, err = rc.Read(buf)
	if !IsContentReadIdleTimeout(err) {
		t.Fatalf("second read err=%v want ErrContentReadIdleTimeout", err)
	}
	if elapsed := time.Since(start); elapsed < 900*time.Millisecond || elapsed > 3*time.Second {
		t.Fatalf("idle timeout took %v want ~1s", elapsed)
	}
}

func TestStreamTransportUsesHTTP1(t *testing.T) {
	c := NewClient("token", "", ClientOptions{
		MaxConcurrency:              2,
		StreamResponseHeaderSeconds: 120,
	})
	transport, ok := c.streamClient.Transport.(*http.Transport)
	if !ok {
		t.Fatalf("stream transport type=%T", c.streamClient.Transport)
	}
	if transport.ForceAttemptHTTP2 {
		t.Fatal("stream transport must disable HTTP/2")
	}
	if transport.DialContext == nil {
		t.Fatal("stream transport missing DialContext")
	}
	if transport.TLSHandshakeTimeout != 15*time.Second {
		t.Fatalf("TLSHandshakeTimeout=%v want 15s", transport.TLSHandshakeTimeout)
	}
}

func TestGetStreamHeaderTimeoutDrainsRacedResponse(t *testing.T) {
	gate := make(chan struct{})
	var inFlight int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		atomic.AddInt32(&inFlight, 1)
		defer atomic.AddInt32(&inFlight, -1)
		select {
		case <-gate:
			_, _ = io.WriteString(w, "ok")
		case <-r.Context().Done():
		}
	}))
	t.Cleanup(srv.Close)

	c := NewTestClient(srv.URL, ClientOptions{
		MaxRetries:                  0,
		MaxConcurrency:              2,
		StreamResponseHeaderSeconds: 1,
		RetryBaseDelayMs:            10,
	})

	before := runtime.NumGoroutine()
	for i := 0; i < 8; i++ {
		_, _, err := c.GetStream(context.Background(), "/content")
		if err == nil {
			t.Fatalf("iteration %d: expected header timeout error", i)
		}
	}
	close(gate)

	deadline := time.Now().Add(3 * time.Second)
	for atomic.LoadInt32(&inFlight) > 0 && time.Now().Before(deadline) {
		time.Sleep(20 * time.Millisecond)
	}
	if atomic.LoadInt32(&inFlight) > 0 {
		t.Fatalf("server handlers still in flight after gate release")
	}

	time.Sleep(100 * time.Millisecond)
	after := runtime.NumGoroutine()
	if after > before+4 {
		t.Fatalf("goroutine leak after raced header timeouts: before=%d after=%d", before, after)
	}

	gate = make(chan struct{})
	close(gate)
	rc, _, err := c.GetStream(context.Background(), "/content")
	if err != nil {
		t.Fatalf("GetStream after drain cycles: %v", err)
	}
	if err := rc.Close(); err != nil && !errors.Is(err, io.EOF) {
		t.Fatalf("Close: %v", err)
	}
}
