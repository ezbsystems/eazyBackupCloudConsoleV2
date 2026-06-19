package graphsync

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync/atomic"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestSiteDriveContentBase(t *testing.T) {
	got := siteDriveContentBase("tenant-1", "site/id", "drive-xyz")
	want := "tenant-1/sites/site_id/drives/drive-xyz/content"
	if got != want {
		t.Fatalf("siteDriveContentBase = %q, want %q", got, want)
	}
}

func TestPaginationMonitorForJobSharePoint(t *testing.T) {
	job := &api.RunJob{
		GraphPagination: map[string]api.PaginationLimit{
			"sharepoint": {MaxPages: 2500, OnCap: "warn_continue"},
		},
	}
	m := paginationMonitorForJob(job, "sharepoint", "sp:test", nil)
	if m.MaxPages != 2500 {
		t.Fatalf("max pages = %d", m.MaxPages)
	}
	if m.CapMode != graph.CapWarnContinue {
		t.Fatalf("cap mode = %v", m.CapMode)
	}
}

func TestSyncSharePointParallelDrives(t *testing.T) {
	var peakConcurrent int64
	var inFlight int64

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		switch {
		case strings.Contains(path, "/sites/site1/drives"):
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{"id": "drive-a"},
					{"id": "drive-b"},
					{"id": "drive-c"},
				},
			})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		case strings.Contains(path, "/drives/") && strings.HasSuffix(path, "/root/delta"):
			cur := atomic.AddInt64(&inFlight, 1)
			for {
				peak := atomic.LoadInt64(&peakConcurrent)
				if cur <= peak || atomic.CompareAndSwapInt64(&peakConcurrent, peak, cur) {
					break
				}
			}
			time.Sleep(50 * time.Millisecond)
			defer atomic.AddInt64(&inFlight, -1)

			driveID := strings.TrimPrefix(path, "/drives/")
			driveID = strings.TrimSuffix(driveID, "/root/delta")
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{
						"id":   "item-" + driveID,
						"name": "file.txt",
						"size": float64(10),
						"file": map[string]any{},
					},
				},
			})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 2, MaxConcurrency: 8})
	staging := graphfs.NewOverlayBuilder()
	res, err := SyncSharePoint(context.Background(), client, SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		DriveParallel: 3,
		Parallel:      8,
		Shard:         ShardFilter{},
		Staging:       staging,
	})
	if err != nil {
		t.Fatalf("SyncSharePoint: %v", err)
	}
	if res.Stats["drives"] != 3 {
		t.Fatalf("drives = %d, want 3", res.Stats["drives"])
	}
	if res.Stats["items"] != 3 {
		t.Fatalf("items = %d, want 3", res.Stats["items"])
	}
	if peakConcurrent < 2 {
		t.Fatalf("expected parallel drive fetch, peakConcurrent=%d", peakConcurrent)
	}
}

func TestSyncSharePointDriveParallelOneMatchesSequential(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		switch {
		case strings.Contains(path, "/sites/site1/drives"):
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{{"id": "drive-only"}},
			})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		case strings.Contains(path, "/drives/drive-only/root/delta"):
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{
						"id":   "item-1",
						"name": "doc.pdf",
						"size": float64(42),
						"file": map[string]any{},
					},
				},
			})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 2, MaxConcurrency: 8})
	staging := graphfs.NewOverlayBuilder()
	res, err := SyncSharePoint(context.Background(), client, SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		DriveParallel: 1,
		Parallel:      8,
		Shard:         ShardFilter{},
		Staging:       staging,
	})
	if err != nil {
		t.Fatalf("SyncSharePoint: %v", err)
	}
	if res.Stats["drives"] != 1 || res.Stats["items"] != 1 {
		t.Fatalf("unexpected stats: %+v", res.Stats)
	}
}
