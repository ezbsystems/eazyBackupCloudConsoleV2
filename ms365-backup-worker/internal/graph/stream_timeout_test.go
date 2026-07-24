package graph

import (
	"context"
	"errors"
	"io"
	"net/http"
	"net/http/httptest"
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
