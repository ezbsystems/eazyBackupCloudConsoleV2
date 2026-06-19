package graph

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"testing"
)

func testGraphClient(t *testing.T, handler http.HandlerFunc) (*Client, string) {
	t.Helper()
	srv := httptest.NewServer(handler)
	t.Cleanup(srv.Close)
	return &Client{
		token:      "test",
		graphBase:  srv.URL,
		httpClient: srv.Client(),
		maxRetries: 1,
		retryDelay: 10,
		sem:        make(chan struct{}, 2),
	}, srv.URL
}

func deltaResponse(items []map[string]any, nextLink, deltaLink string) []byte {
	body := map[string]any{"value": items}
	if nextLink != "" {
		body["@odata.nextLink"] = nextLink
	}
	if deltaLink != "" {
		body["@odata.deltaLink"] = deltaLink
	}
	raw, _ := json.Marshal(body)
	return raw
}

func TestPaginateDeltaOptsCompletesNaturally(t *testing.T) {
	var serverURL string
	c, serverURL := testGraphClient(t, func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write(deltaResponse(
			[]map[string]any{{"id": "1"}, {"id": "2"}},
			"",
			serverURL+"/delta-token",
		))
	})

	outcome := &PaginationOutcome{}
	monitor := ForBackupPagination("test-delta", nil)
	items, delta, err := c.PaginateDeltaOpts(context.Background(), "/items/delta", "", "id", 100, nil, &DeltaPaginateOptions{
		Monitor: monitor,
		Outcome: outcome,
	})
	if err != nil {
		t.Fatalf("PaginateDeltaOpts: %v", err)
	}
	if len(items) != 2 || delta == "" {
		t.Fatalf("items=%d delta=%q", len(items), delta)
	}
	if !outcome.CompletedNaturally || outcome.Pages != 1 || outcome.TotalItems != 2 {
		t.Fatalf("outcome=%+v", outcome)
	}
}

func TestPaginateDeltaOptsDuplicatePageStrict(t *testing.T) {
	var serverURL string
	var calls int
	c, serverURL := testGraphClient(t, func(w http.ResponseWriter, r *http.Request) {
		calls++
		w.Header().Set("Content-Type", "application/json")
		next := serverURL + "/page2"
		_, _ = w.Write(deltaResponse([]map[string]any{{"id": "a"}}, next, ""))
	})

	_, _, err := c.PaginateDeltaOpts(context.Background(), "/items/delta", "", "id", 100, nil, &DeltaPaginateOptions{
		Monitor: ForBackupPagination("dup-test", nil),
	})
	if err == nil {
		t.Fatal("expected duplicate-page error")
	}
	if _, ok := err.(*GraphPaginationError); !ok {
		t.Fatalf("expected GraphPaginationError, got %T: %v", err, err)
	}
}

func TestPaginateDeltaOptsEmptyPagesLoop(t *testing.T) {
	var serverURL string
	var calls int
	c, serverURL := testGraphClient(t, func(w http.ResponseWriter, r *http.Request) {
		calls++
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write(deltaResponse(nil, fmt.Sprintf("%s/empty/%d", serverURL, calls), ""))
	})

	_, _, err := c.PaginateDeltaOpts(context.Background(), "/items/delta", "", "id", 100, nil, &DeltaPaginateOptions{
		Monitor: ForBackupPagination("empty-test", nil),
	})
	if err == nil {
		t.Fatal("expected empty-page loop error")
	}
	if calls < 3 {
		t.Fatalf("expected at least 3 empty pages, calls=%d", calls)
	}
}

func TestPaginateDeltaOptsIdenticalLinkRepeated(t *testing.T) {
	var serverURL string
	var calls int
	c, serverURL := testGraphClient(t, func(w http.ResponseWriter, r *http.Request) {
		calls++
		w.Header().Set("Content-Type", "application/json")
		next := serverURL + "/same"
		_, _ = w.Write(deltaResponse([]map[string]any{{"id": fmt.Sprintf("id-%d", calls)}}, next, ""))
	})

	_, _, err := c.PaginateDeltaOpts(context.Background(), "/items/delta", "", "id", 100, nil, &DeltaPaginateOptions{
		Monitor: ForBackupPagination("link-test", nil),
	})
	if err == nil {
		t.Fatal("expected identical link error")
	}
}

func TestPaginateDeltaOptsCapWarnContinue(t *testing.T) {
	var serverURL string
	var calls int
	c, serverURL := testGraphClient(t, func(w http.ResponseWriter, r *http.Request) {
		calls++
		w.Header().Set("Content-Type", "application/json")
		next := fmt.Sprintf("%s/page/%d", serverURL, calls+1)
		_, _ = w.Write(deltaResponse([]map[string]any{{"id": fmt.Sprintf("id-%d", calls)}}, next, ""))
	})

	outcome := &PaginationOutcome{}
	monitor := ForBackupPagination("cap-test", nil)
	monitor.MaxPages = 2
	monitor.CapMode = CapWarnContinue

	items, delta, err := c.PaginateDeltaOpts(context.Background(), "/items/delta", "", "id", 100, nil, &DeltaPaginateOptions{
		Monitor: monitor,
		Outcome: outcome,
	})
	if err != nil {
		t.Fatalf("warn_continue should not error: %v", err)
	}
	if !outcome.CapReached {
		t.Fatal("expected CapReached")
	}
	if outcome.Pages < 2 || outcome.TotalItems != 2 {
		t.Fatalf("outcome pages/items=%d/%d", outcome.Pages, outcome.TotalItems)
	}
	if len(items) != 2 || delta != "" {
		t.Fatalf("items=%d delta=%q", len(items), delta)
	}
}
