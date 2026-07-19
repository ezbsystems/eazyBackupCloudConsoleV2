package graph

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"strings"
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

func TestPaginateDeltaOptsDuplicatePageDetectOnly(t *testing.T) {
	var serverURL string
	var calls int
	c, serverURL := testGraphClient(t, func(w http.ResponseWriter, r *http.Request) {
		calls++
		w.Header().Set("Content-Type", "application/json")
		if calls == 1 {
			_, _ = w.Write(deltaResponse([]map[string]any{{"id": "a"}, {"id": "b"}}, serverURL+"/page2", ""))
			return
		}
		_, _ = w.Write(deltaResponse([]map[string]any{{"id": "a"}, {"id": "b"}}, serverURL+"/page3", ""))
	})

	outcome := &PaginationOutcome{}
	items, delta, err := c.PaginateDeltaOpts(context.Background(), "/items/delta", "", "id", 100, nil, &DeltaPaginateOptions{
		Monitor:           ForCalendarNormalScan("dup-detect", nil),
		Outcome:           outcome,
		DuplicatePageMode: DuplicatePageDetectOnly,
	})
	if err != nil {
		t.Fatalf("DetectOnly should soft-stop, got %v", err)
	}
	if len(items) != 2 {
		t.Fatalf("items=%d want 2", len(items))
	}
	if delta != "" {
		t.Fatalf("delta should not advance on duplicate soft-stop, got %q", delta)
	}
	if !outcome.StoppedOnDuplicatePage {
		t.Fatalf("outcome=%+v", outcome)
	}
	if calls != 2 {
		t.Fatalf("calls=%d want 2", calls)
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

func TestPaginateDeltaOptsStripsTopFromNextLink(t *testing.T) {
	var serverURL string
	var requests []string
	c, serverURL := testGraphClient(t, func(w http.ResponseWriter, r *http.Request) {
		requests = append(requests, r.URL.String())
		w.Header().Set("Content-Type", "application/json")
		if len(requests) == 1 {
			_, _ = w.Write(deltaResponse(
				[]map[string]any{{"id": "m1"}},
				serverURL+"/teams/t1/channels/c1/messages/delta?$skiptoken=abc&$top=50",
				"",
			))
			return
		}
		_, _ = w.Write(deltaResponse(
			[]map[string]any{{"id": "m2"}},
			"",
			serverURL+"/teams/t1/channels/c1/messages/delta?$deltatoken=done",
		))
	})

	items, delta, err := c.PaginateDeltaOpts(context.Background(), "/teams/t1/channels/c1/messages/delta", "", "id", 50, nil, &DeltaPaginateOptions{
		Monitor:              ForBackupPagination("teams-next", nil),
		OmitDeltaQueryParams: true,
	})
	if err != nil {
		t.Fatalf("PaginateDeltaOpts: %v", err)
	}
	if len(items) != 2 {
		t.Fatalf("items=%d want 2", len(items))
	}
	if delta == "" {
		t.Fatal("expected delta link")
	}
	if len(requests) < 2 {
		t.Fatalf("expected 2 requests, got %d", len(requests))
	}
	if strings.Contains(strings.ToLower(requests[1]), "top=") {
		t.Fatalf("nextLink request must not include top, got %q", requests[1])
	}
}

func TestPaginateDeltaOptsOmitQueryParams(t *testing.T) {
	var gotQuery string
	c, _ := testGraphClient(t, func(w http.ResponseWriter, r *http.Request) {
		gotQuery = r.URL.RawQuery
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write(deltaResponse(
			[]map[string]any{{"id": "c1"}},
			"",
			"https://graph.microsoft.com/v1.0/contacts/delta?$deltatoken=abc",
		))
	})

	_, delta, err := c.PaginateDeltaOpts(context.Background(), "/users/u1/contactFolders/f1/contacts/delta", "", "id", 100, nil, &DeltaPaginateOptions{
		Monitor:              ForBackupPagination("contacts-omit", nil),
		OmitDeltaQueryParams: true,
	})
	if err != nil {
		t.Fatalf("PaginateDeltaOpts: %v", err)
	}
	if gotQuery != "" {
		t.Fatalf("expected no query params on contacts delta, got %q", gotQuery)
	}
	if delta == "" {
		t.Fatal("expected delta link")
	}
}
