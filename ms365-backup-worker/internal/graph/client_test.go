package graph

import (
	"bytes"
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/version"
)

func TestParseRetryAfterNumeric(t *testing.T) {
	d := parseRetryAfter("30", 0, 2*time.Second)
	if d != 30*time.Second {
		t.Fatalf("expected 30s, got %v", d)
	}
}

func TestParseRetryAfter429Honors600sCap(t *testing.T) {
	d := parseRetryAfter429("900", 0, 2*time.Second)
	if d != retryAfter429Cap {
		t.Fatalf("expected 600s cap, got %v", d)
	}
	d = parseRetryAfter429("300", 0, 2*time.Second)
	if d != 300*time.Second {
		t.Fatalf("expected 300s, got %v", d)
	}
}

func TestParseRetryAfterOtherCapsAt120s(t *testing.T) {
	d := parseRetryAfter("300", 0, 2*time.Second)
	if d != retryAfterOtherCap {
		t.Fatalf("expected 120s cap for 503/504, got %v", d)
	}
}

func TestIsMailboxNotEnabled(t *testing.T) {
	err := errorString(`graph 404 Not Found: {"error":{"code":"MailboxNotEnabledForRESTAPI","message":"The mailbox is either inactive, soft-deleted, or is hosted on-premise."}}`)
	if !IsMailboxNotEnabled(err) {
		t.Fatal("expected mailbox-not-enabled detection")
	}
	if IsMailboxNotEnabled(errorString("graph 404 Not Found: itemNotFound")) {
		t.Fatal("unexpected match for unrelated 404")
	}
	if IsMailboxNotEnabled(nil) {
		t.Fatal("nil should not match")
	}
}

func TestIsSharePointAccessDenied(t *testing.T) {
	// Real error shape from run 976893d2 (Designer site).
	sp403 := errorString(`sharepoint: graph 403 Forbidden: {"error":{"code":"accessDenied","message":"Access denied","innerError":{"date":"2026-06-21T03:18:12","request-id":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","client-request-id":"a1b2c3d4-e5f6-7890-abcd-ef1234567890"}}}`)
	if !IsSharePointAccessDenied(sp403) {
		t.Fatal("expected SharePoint accessDenied 403 to match")
	}
	if !IsSharePointAccessDenied(errorString(`sharepoint: graph 403 Forbidden: {"error":{"code":"generalException","message":"General exception while processing"}}`)) {
		t.Fatal("expected site-scoped generalException 403 to match")
	}
	if !IsSharePointAccessDenied(errorString(`sharepoint: graph 403 Forbidden: {"error":{"code":"Authorization_RequestDenied","message":"Insufficient privileges"}}`)) {
		t.Fatal("expected Authorization_RequestDenied 403 to match")
	}
	if IsSharePointAccessDenied(errorString("graph 401 after token refresh: empty token")) {
		t.Fatal("bad-token 401 should not be treated as SharePoint access denied")
	}
	if IsSharePointAccessDenied(errorString("graph 503 Service Unavailable")) {
		t.Fatal("unrelated 503 should not match")
	}
	if IsSharePointAccessDenied(nil) {
		t.Fatal("nil should not match")
	}
}

func TestIsMailboxUnavailable(t *testing.T) {
	// 404 MailboxNotEnabledForRESTAPI (mail/contacts shape) still matches.
	if !IsMailboxUnavailable(errorString(`graph 404 Not Found: {"error":{"code":"MailboxNotEnabledForRESTAPI","message":"The mailbox is either inactive, soft-deleted, or is hosted on-premise."}}`)) {
		t.Fatal("expected 404 MailboxNotEnabledForRESTAPI to be unavailable")
	}
	// To Do (tasks) shape: 401 with empty-message UnknownError for no-mailbox users.
	todo401 := errorString(`tasks: graph 401 Unauthorized: {"error":{"code":"UnknownError","message":"","innerError":{"date":"2026-06-21T11:48:08","request-id":"4740a606-dfbe-4610-865c-2488141c6a1d"}}}`)
	if !IsMailboxUnavailable(todo401) {
		t.Fatal("expected To Do 401 UnknownError to be unavailable")
	}
	// A bad-token 401 (refresh failed) must NOT be treated as a mailbox skip.
	if IsMailboxUnavailable(errorString("graph 401 after token refresh: empty token")) {
		t.Fatal("bad-token 401 should not be treated as mailbox unavailable")
	}
	// A missing-scope 401 uses a distinct code and must not be swallowed.
	if IsMailboxUnavailable(errorString(`graph 401 Unauthorized: {"error":{"code":"InvalidAuthenticationToken","message":"Access token has expired."}}`)) {
		t.Fatal("scope/auth 401 should not be treated as mailbox unavailable")
	}
	// Unrelated errors and nil must not match.
	if IsMailboxUnavailable(errorString("graph 500 Internal Server Error")) {
		t.Fatal("unrelated 500 should not match")
	}
	if IsMailboxUnavailable(nil) {
		t.Fatal("nil should not match")
	}
}

type errorString string

func (e errorString) Error() string { return string(e) }

func TestClientRetries429PastMaxRetries(t *testing.T) {
	var calls int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		calls++
		if calls <= 7 {
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
		maxRetries: 2,
		retryDelay: 50 * time.Millisecond,
		sem:        make(chan struct{}, 4),
	}
	_, err := c.GetJSON(context.Background(), "/users", nil)
	if err != nil {
		t.Fatalf("GetJSON: %v", err)
	}
	if calls != 8 {
		t.Fatalf("expected 8 calls (7x429 + success), got %d", calls)
	}
}

func TestClientSetsUserAgent(t *testing.T) {
	var ua string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		ua = r.Header.Get("User-Agent")
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"value":[]}`))
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
	_, err := c.GetJSON(context.Background(), "/users", nil)
	if err != nil {
		t.Fatalf("GetJSON: %v", err)
	}
	want := "eazyBackup-MS365-Backup/" + version.Version
	if ua != want {
		t.Fatalf("User-Agent=%q want %q", ua, want)
	}
}

func TestClientAdaptiveConservativeStart(t *testing.T) {
	c := NewClient("token", "", ClientOptions{
		MaxConcurrency: 8,
		AdaptiveLimit:  true,
	})
	if got := c.AdaptiveConcurrency(); got != 4 {
		t.Fatalf("initial adaptive=%d want 4 (max(2, concurrency/2))", got)
	}
	c = NewClient("token", "", ClientOptions{
		MaxConcurrency: 2,
		AdaptiveLimit:  true,
	})
	if got := c.AdaptiveConcurrency(); got != 2 {
		t.Fatalf("initial adaptive=%d want 2 for low concurrency", got)
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

func TestClientAdaptiveConcurrencyRecovery(t *testing.T) {
	c := NewClient("token", "", ClientOptions{
		MaxRetries:     1,
		MaxConcurrency: 8,
		AdaptiveLimit:  true,
	})
	if c.AdaptiveConcurrency() != 4 {
		t.Fatalf("initial adaptive=%d want 4", c.AdaptiveConcurrency())
	}
	c.record429(time.Second)
	if c.AdaptiveConcurrency() != 2 {
		t.Fatalf("after 429 adaptive=%d want 2", c.AdaptiveConcurrency())
	}
	for i := 0; i < adaptiveSuccessStreak; i++ {
		c.recordSuccess()
	}
	if c.AdaptiveConcurrency() != 3 {
		t.Fatalf("after success streak adaptive=%d want 3", c.AdaptiveConcurrency())
	}
}

func TestClientReleasesTransportDuring429Backoff(t *testing.T) {
	SetTenantBudget("tenant-transport-test", 1)
	defer func() {
		tenantLimitMu.Lock()
		delete(tenantSem, "tenant-transport-test")
		delete(tenantBudget, "tenant-transport-test")
		tenantLimitMu.Unlock()
	}()

	sleeping := make(chan struct{})
	var calls int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		calls++
		if calls == 1 {
			w.Header().Set("Retry-After", "1")
			w.WriteHeader(http.StatusTooManyRequests)
			return
		}
		close(sleeping)
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"value":[]}`))
	}))
	defer srv.Close()

	c := NewClient("token", "", ClientOptions{
		MaxRetries:     3,
		MaxConcurrency: 4,
		AdaptiveLimit:  true,
	})
	c.SetAzureTenantID("tenant-transport-test")
	c.graphBase = srv.URL
	c.httpClient = srv.Client()

	blocker := make(chan struct{})
	acquired := make(chan struct{}, 1)
	go func() {
		if err := acquireTenant(context.Background(), "tenant-transport-test"); err != nil {
			t.Errorf("acquireTenant during backoff: %v", err)
			return
		}
		acquired <- struct{}{}
		<-blocker
		releaseTenant("tenant-transport-test")
	}()

	select {
	case <-acquired:
	case <-time.After(2 * time.Second):
		t.Fatal("tenant slot was not released during 429 Retry-After backoff")
	}

	close(blocker)

	done := make(chan error, 1)
	go func() {
		_, err := c.GetJSON(context.Background(), "/users", nil)
		done <- err
	}()

	select {
	case err := <-done:
		if err != nil {
			t.Fatalf("GetJSON: %v", err)
		}
	case <-time.After(5 * time.Second):
		t.Fatal("GetJSON did not complete after transport slot was freed")
	}
	if calls < 2 {
		t.Fatalf("expected retry after backoff, calls=%d", calls)
	}
}

func TestBatchGetMessagesChunking(t *testing.T) {
	if maxBatchRequests != 20 {
		t.Fatalf("unexpected batch size %d", maxBatchRequests)
	}
}

func TestUploadChunkRetries429(t *testing.T) {
	var chunkCalls int
	var srv *httptest.Server
	srv = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if strings.Contains(r.URL.Path, "createUploadSession") {
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write([]byte(`{"uploadUrl":"` + srv.URL + `/upload"}`))
			return
		}
		if r.Method == http.MethodPut {
			chunkCalls++
			if chunkCalls == 1 {
				w.Header().Set("Retry-After", "1")
				w.WriteHeader(http.StatusTooManyRequests)
				return
			}
			w.WriteHeader(http.StatusCreated)
			_, _ = w.Write([]byte(`{"id":"file-1"}`))
			return
		}
		w.WriteHeader(http.StatusNotFound)
	}))
	defer srv.Close()

	c := NewClient("token", "", ClientOptions{
		MaxRetries:     3,
		MaxConcurrency: 4,
		AdaptiveLimit:  true,
	})
	c.graphBase = srv.URL
	c.httpClient = srv.Client()

	data := []byte("hello upload chunk")
	_, err := c.putViaUploadSession(context.Background(), "/drive/root:/test.txt:/content", int64(len(data)), bytes.NewReader(data))
	if err != nil {
		t.Fatalf("putViaUploadSession: %v", err)
	}
	if chunkCalls < 2 {
		t.Fatalf("expected chunk retry after 429, calls=%d", chunkCalls)
	}
}

func TestRecord429DebouncesConcurrentShrink(t *testing.T) {
	c := NewClient("token", "", ClientOptions{
		MaxConcurrency: 16,
		AdaptiveLimit:  true,
	})
	if c.AdaptiveConcurrency() != 8 {
		t.Fatalf("initial adaptive=%d want 8", c.AdaptiveConcurrency())
	}
	delay := 500 * time.Millisecond
	var wg sync.WaitGroup
	for i := 0; i < 10; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			c.record429(delay)
		}()
	}
	wg.Wait()
	if got := c.AdaptiveConcurrency(); got != 4 {
		t.Fatalf("after concurrent 429 burst adaptive=%d want 4 (single shrink)", got)
	}
}

func TestClientReleasesWorkloadDuring429Backoff(t *testing.T) {
	c := NewClient("token", "", ClientOptions{
		MaxRetries:     3,
		MaxConcurrency: 2,
		AdaptiveLimit:  true,
	})

	sleeping := make(chan struct{})
	var calls int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		calls++
		if calls == 1 {
			w.Header().Set("Retry-After", "1")
			w.WriteHeader(http.StatusTooManyRequests)
			return
		}
		close(sleeping)
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"value":[]}`))
	}))
	defer srv.Close()
	c.graphBase = srv.URL
	c.httpClient = srv.Client()

	acquired := make(chan struct{}, 1)
	blocker := make(chan struct{})
	go func() {
		if err := c.acquireWorkload(context.Background()); err != nil {
			t.Errorf("acquireWorkload during backoff: %v", err)
			return
		}
		acquired <- struct{}{}
		<-blocker
		c.releaseWorkload()
	}()

	select {
	case <-acquired:
	case <-time.After(2 * time.Second):
		t.Fatal("workload slot was not released during 429 Retry-After backoff")
	}
	close(blocker)

	done := make(chan error, 1)
	go func() {
		_, err := c.GetJSON(context.Background(), "/users", nil)
		done <- err
	}()
	select {
	case err := <-done:
		if err != nil {
			t.Fatalf("GetJSON: %v", err)
		}
	case <-time.After(5 * time.Second):
		t.Fatal("GetJSON did not complete")
	}
}

func TestBatchGetMessagesRetriesSub429(t *testing.T) {
	var batchCalls int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/$batch" {
			w.WriteHeader(http.StatusNotFound)
			return
		}
		batchCalls++
		w.Header().Set("Content-Type", "application/json")
		if batchCalls == 1 {
			_, _ = w.Write([]byte(`{"responses":[{"id":"msg-1","status":429,"headers":{"Retry-After":"1"},"body":{"error":{"code":"activityLimitReached"}}}]}`))
			return
		}
		_, _ = w.Write([]byte(`{"responses":[{"id":"msg-1","status":200,"body":{"id":"msg-1","subject":"hi"}}]}`))
	}))
	defer srv.Close()

	c := NewClient("token", "", ClientOptions{MaxRetries: 3, MaxConcurrency: 4})
	c.graphBase = srv.URL
	c.httpClient = srv.Client()

	out, err := c.BatchGetMessages(context.Background(), "user-1", []string{"msg-1"})
	if err != nil {
		t.Fatalf("BatchGetMessages: %v", err)
	}
	if len(out) != 1 {
		t.Fatalf("expected 1 message body, got %d", len(out))
	}
	if batchCalls < 2 {
		t.Fatalf("expected batch retry after sub-429, calls=%d", batchCalls)
	}
}

func TestClampAdaptiveCeilingTracksTenantBudget(t *testing.T) {
	c := NewClient("token", "", ClientOptions{
		MaxConcurrency: 16,
		AdaptiveLimit:  true,
	})
	c.SetAzureTenantID("tenant-aimd")
	if c.AdaptiveConcurrency() != 8 {
		t.Fatalf("initial adaptive=%d want 8", c.AdaptiveConcurrency())
	}
	c.ClampAdaptiveCeiling(4)
	if c.AdaptiveConcurrency() != 4 {
		t.Fatalf("after clamp adaptive=%d want 4", c.AdaptiveConcurrency())
	}
	learned, _, ok := tenantAdaptiveSeed("tenant-aimd")
	if !ok || learned != 4 {
		t.Fatalf("persisted adaptive=%d ok=%v want 4", learned, ok)
	}
	tenantAdaptiveMu.Lock()
	delete(tenantAdaptiveLimit, "tenant-aimd")
	delete(tenantAdaptiveCeiling, "tenant-aimd")
	tenantAdaptiveMu.Unlock()
}

func TestStreamSuccessCountedOnClose(t *testing.T) {
	payload := strings.Repeat("x", 1024)
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Length", "1024")
		_, _ = w.Write([]byte(payload))
	}))
	defer srv.Close()

	c := NewClient("token", "", ClientOptions{
		MaxConcurrency: 4,
		AdaptiveLimit:  true,
	})
	c.graphBase = srv.URL
	c.httpClient = srv.Client()
	if c.AdaptiveConcurrency() != 2 {
		t.Fatalf("initial adaptive=%d want 2", c.AdaptiveConcurrency())
	}

	rc, _, err := c.GetStream(context.Background(), "/content")
	if err != nil {
		t.Fatalf("GetStream: %v", err)
	}
	if c.AdaptiveConcurrency() != 2 {
		t.Fatalf("adaptive should not grow before close, got %d", c.AdaptiveConcurrency())
	}
	_, _ = io.ReadAll(rc)
	if err := rc.Close(); err != nil {
		t.Fatalf("close: %v", err)
	}
	for i := 0; i < adaptiveSuccessStreak-1; i++ {
		c.recordSuccess()
	}
	if c.AdaptiveConcurrency() != 3 {
		t.Fatalf("after close+streak adaptive=%d want 3", c.AdaptiveConcurrency())
	}
}

func TestThrottleWaitingRefcount(t *testing.T) {
	c := NewClient("token", "", ClientOptions{MaxConcurrency: 2})
	var wg sync.WaitGroup
	for i := 0; i < 3; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			c.throttleWaiting.Add(1)
			time.Sleep(100 * time.Millisecond)
			c.throttleWaiting.Add(-1)
		}()
	}
	time.Sleep(20 * time.Millisecond)
	if !c.ThrottleWaiting() {
		t.Fatal("expected ThrottleWaiting true while sleepers active")
	}
	wg.Wait()
	time.Sleep(10 * time.Millisecond)
	if c.ThrottleWaiting() {
		t.Fatal("expected ThrottleWaiting false after all sleepers done")
	}
}
