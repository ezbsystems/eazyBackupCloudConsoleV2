package graphsync

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestOneDrivePaginationRebaselineRecoversResumedCursor(t *testing.T) {
	var fullDeltaRequests int
	var srv *httptest.Server
	srv = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case r.URL.Path == "/resume":
			writeOneDriveDelta(w, []map[string]any{oneDriveItem("old")}, srv.URL+"/resume-2", "")
		case r.URL.Path == "/resume-2":
			writeOneDriveDelta(w, []map[string]any{oneDriveItem("old")}, srv.URL+"/resume-3", "")
		case r.URL.Path == "/drives/drive-1/root/delta":
			fullDeltaRequests++
			writeOneDriveDelta(w, []map[string]any{oneDriveItem("fresh")}, "", srv.URL+"/delta-complete")
		case r.URL.Path == "/drives/drive-1/root/children":
			writeOneDriveDelta(w, []map[string]any{oneDriveItem("fresh")}, "", "")
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	res, err := SyncOneDrive(context.Background(), graph.NewTestClient(srv.URL, graph.ClientOptions{
		MaxRetries:     0,
		MaxConcurrency: 1,
	}), OneDriveSyncOptions{
		AzureTenantID: "tenant-1",
		UserID:        "user-1",
		DriveID:       "drive-1",
		DeltaLink:     srv.URL + "/resume",
		Overlay:       graphfs.NewOverlayBuilder(),
	})

	if err != nil {
		t.Fatalf("SyncOneDrive: %v", err)
	}
	if res.DeltaLink != srv.URL+"/delta-complete" {
		t.Fatalf("delta link = %q, want %q", res.DeltaLink, srv.URL+"/delta-complete")
	}
	if res.Stats["pagination_rebaseline"] != 1 {
		t.Fatalf("pagination rebaseline = %d, want 1", res.Stats["pagination_rebaseline"])
	}
	if fullDeltaRequests != 1 {
		t.Fatalf("full delta requests = %d, want 1", fullDeltaRequests)
	}
}

func TestOneDrivePaginationRebaselineFailureIsBounded(t *testing.T) {
	var fullDeltaRequests int
	var runLog []string
	var srv *httptest.Server
	srv = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case r.URL.Path == "/resume":
			writeOneDriveDelta(w, []map[string]any{oneDriveItem("old")}, srv.URL+"/resume-2", "")
		case r.URL.Path == "/resume-2":
			writeOneDriveDelta(w, []map[string]any{oneDriveItem("old")}, srv.URL+"/resume-3", "")
		case r.URL.Path == "/drives/drive-1/root/delta":
			fullDeltaRequests++
			writeOneDriveDelta(w, []map[string]any{oneDriveItem("old")}, srv.URL+"/full-2", "")
		case r.URL.Path == "/full-2":
			writeOneDriveDelta(w, []map[string]any{oneDriveItem("old")}, srv.URL+"/full-3", "")
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	_, err := SyncOneDrive(context.Background(), graph.NewTestClient(srv.URL, graph.ClientOptions{
		MaxRetries:     0,
		MaxConcurrency: 1,
	}), OneDriveSyncOptions{
		AzureTenantID: "tenant-1",
		UserID:        "user-1",
		DriveID:       "drive-1",
		DeltaLink:     srv.URL + "/resume",
		Overlay:       graphfs.NewOverlayBuilder(),
		Log: func(level, message string) {
			runLog = append(runLog, level+": "+message)
		},
	})

	if err == nil || !strings.Contains(err.Error(), "onedrive full delta rebaseline") {
		t.Fatalf("error = %v, want wrapped full delta rebaseline error", err)
	}
	if fullDeltaRequests != 1 {
		t.Fatalf("full delta requests = %d, want 1 (no third rebaseline request)", fullDeltaRequests)
	}
	if !strings.Contains(strings.Join(runLog, "\n"), "error: OneDrive full delta rebaseline failed") {
		t.Fatalf("run log missing failed rebaseline event: %q", runLog)
	}
}

func TestOneDrivePaginationDoesNotRebaselineFullPass(t *testing.T) {
	var fullDeltaRequests int
	var srv *httptest.Server
	srv = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case r.URL.Path == "/drives/drive-1/root/delta":
			fullDeltaRequests++
			writeOneDriveDelta(w, []map[string]any{oneDriveItem("old")}, srv.URL+"/full-2", "")
		case r.URL.Path == "/full-2":
			writeOneDriveDelta(w, []map[string]any{oneDriveItem("old")}, srv.URL+"/full-3", "")
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	_, err := SyncOneDrive(context.Background(), graph.NewTestClient(srv.URL, graph.ClientOptions{
		MaxRetries:     0,
		MaxConcurrency: 1,
	}), OneDriveSyncOptions{
		AzureTenantID: "tenant-1",
		UserID:        "user-1",
		DriveID:       "drive-1",
		Overlay:       graphfs.NewOverlayBuilder(),
	})

	var paginationErr *graph.GraphPaginationError
	if !errors.As(err, &paginationErr) {
		t.Fatalf("error = %v, want GraphPaginationError", err)
	}
	if fullDeltaRequests != 1 {
		t.Fatalf("full delta requests = %d, want 1", fullDeltaRequests)
	}
}

func TestOneDrivePaginationDoesNotRebaselineGraphFailure(t *testing.T) {
	var fullDeltaRequests int
	var srv *httptest.Server
	srv = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/resume":
			http.Error(w, "temporary Graph failure", http.StatusInternalServerError)
		case "/drives/drive-1/root/delta":
			fullDeltaRequests++
			http.Error(w, "unexpected rebaseline", http.StatusInternalServerError)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	_, err := SyncOneDrive(context.Background(), graph.NewTestClient(srv.URL, graph.ClientOptions{
		MaxRetries:     0,
		MaxConcurrency: 1,
	}), OneDriveSyncOptions{
		AzureTenantID: "tenant-1",
		UserID:        "user-1",
		DriveID:       "drive-1",
		DeltaLink:     srv.URL + "/resume",
		Overlay:       graphfs.NewOverlayBuilder(),
	})

	if err == nil || !strings.Contains(err.Error(), "graph 500") {
		t.Fatalf("error = %v, want Graph HTTP 500 error", err)
	}
	if fullDeltaRequests != 0 {
		t.Fatalf("full delta requests = %d, want 0", fullDeltaRequests)
	}
}

func oneDriveItem(id string) map[string]any {
	return map[string]any{
		"id":   id,
		"name": id + ".txt",
		"size": 1,
		"file": map[string]any{},
		"parentReference": map[string]any{
			"path": "/drives/drive-1/root:",
		},
	}
}

func writeOneDriveDelta(w http.ResponseWriter, items []map[string]any, nextLink, deltaLink string) {
	w.Header().Set("Content-Type", "application/json")
	body := map[string]any{"value": items}
	if nextLink != "" {
		body["@odata.nextLink"] = nextLink
	}
	if deltaLink != "" {
		body["@odata.deltaLink"] = deltaLink
	}
	if err := json.NewEncoder(w).Encode(body); err != nil {
		panic(err)
	}
}
