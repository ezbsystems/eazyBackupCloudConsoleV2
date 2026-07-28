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

func TestSiteDriveContentBasePreservesBangDriveID(t *testing.T) {
	drive := "b!Lo81U-FbgEaT7FbLkW0SvS9d295rdrxKrGY9L2qWF24Sz0cFE5BDR7H4es0msZTd"
	got := siteDriveContentBase("tenant-1", "host,guid,guid", drive)
	want := "tenant-1/sites/host_guid_guid/drives/" + drive + "/content"
	if got != want {
		t.Fatalf("siteDriveContentBase = %q, want %q", got, want)
	}
}

func TestShouldForceSharePointFullResyncPoisonedDelta(t *testing.T) {
	staging := graphfs.NewOverlayBuilder()
	staging.PutJSON("tenant-1/sites/site1/_site.json", []byte(`{}`), time.Now())
	opts := SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		Staging:       staging,
	}
	res := &sharePointDriveResult{items: 0}
	if !shouldForceSharePointFullResync(opts, "b!drive", "https://graph/delta?token=poison", res) {
		t.Fatal("expected full resync when incremental is empty and content tree missing")
	}
}

func TestShouldForceSharePointFullResyncHealthyContent(t *testing.T) {
	staging := graphfs.NewOverlayBuilder()
	staging.PutJSON("tenant-1/sites/site1/drives/b!drive/content/doc.txt", []byte("x"), time.Now())
	opts := SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		Staging:       staging,
	}
	res := &sharePointDriveResult{items: 0}
	if shouldForceSharePointFullResync(opts, "b!drive", "https://graph/delta?token=ok", res) {
		t.Fatal("expected no resync when content tree already present")
	}
}

func TestShouldForceSharePointFullResyncIncrementalWithItemsNoProof(t *testing.T) {
	opts := SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		Staging:       graphfs.NewOverlayBuilder(),
	}
	res := &sharePointDriveResult{items: 5}
	if !shouldForceSharePointFullResync(opts, "b!drive", "https://graph/delta?token=poison", res) {
		t.Fatal("expected full resync when baseline proof is absent even if incremental returned items")
	}
}

func TestShouldForceSharePointFullResyncValidCatalogMarker(t *testing.T) {
	staging := graphfs.NewOverlayBuilder()
	markerPath := sharePointCatalogMarkerPath("tenant-1", "site1", "b!drive")
	staging.PutJSON(markerPath, []byte(`{"v":1,"files":0,"shard_index":0,"shard_total":1}`), time.Now())
	opts := SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		Staging:       staging,
	}
	res := &sharePointDriveResult{items: 0}
	if shouldForceSharePointFullResync(opts, "b!drive", "https://graph/delta?token=ok", res) {
		t.Fatal("expected no resync when valid catalog marker exists")
	}
}

func TestSharePointCatalogMarkerShardIdentity(t *testing.T) {
	staging := graphfs.NewOverlayBuilder()
	markerPath := sharePointCatalogMarkerPath("tenant-1", "site1", "b!drive")
	staging.PutJSON(markerPath, []byte(`{"v":1,"files":0,"shard_index":1,"shard_total":4}`), time.Now())
	opts := SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		Staging:       staging,
		Shard:         ShardFilter{Index: 2, Total: 4},
	}
	if validSharePointCatalogMarker(opts, "b!drive") {
		t.Fatal("marker for shard 1/4 must not validate for shard 2/4")
	}
	opts.Shard = ShardFilter{Index: 1, Total: 4}
	if !validSharePointCatalogMarker(opts, "b!drive") {
		t.Fatal("marker for shard 1/4 must validate for shard 1/4")
	}
}

func TestSyncSharePointPreflightRebaselineWhenIncrementalWouldReturnItems(t *testing.T) {
	driveID := "b!preflight-drive"
	var deltaCalls int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		switch {
		case path == "/drives/"+driveID:
			payload, _ := json.Marshal(map[string]any{"id": driveID, "name": "Documents"})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		case strings.Contains(path, "/drives/") && strings.HasSuffix(path, "/root/delta"):
			deltaCalls++
			if strings.Contains(r.URL.String(), "token=poison") {
				t.Fatal("incremental delta must not run when baseline proof is absent")
			}
			w.Header().Set("Content-Type", "application/json")
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{"id": "file-1", "name": "doc.pdf", "size": float64(42), "file": map[string]any{}},
				},
				"@odata.deltaLink": "http://" + r.Host + "/drives/" + driveID + "/root/delta?token=fresh",
			})
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 2, MaxConcurrency: 8})
	staging := graphfs.NewOverlayBuilder()
	staging.PutJSON(siteStoragePath("tenant-1", "site1")+"/_site.json", []byte(`{}`), time.Now())
	res, err := SyncSharePoint(context.Background(), client, SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		DriveID:       driveID,
		DeltaStates:   map[string]string{driveID: "https://graph/delta?token=poison"},
		Staging:       staging,
	})
	if err != nil {
		t.Fatalf("SyncSharePoint: %v", err)
	}
	if deltaCalls != 1 {
		t.Fatalf("delta calls = %d, want 1 full baseline", deltaCalls)
	}
	if res.Stats["full_resync"] != 1 {
		t.Fatalf("full_resync = %d, want 1", res.Stats["full_resync"])
	}
}

func TestSyncSharePointEmptyFullScanWritesCatalogMarker(t *testing.T) {
	driveID := "b!empty-drive"
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		switch {
		case path == "/drives/"+driveID:
			payload, _ := json.Marshal(map[string]any{"id": driveID, "name": "Documents"})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		case strings.Contains(path, "/drives/") && strings.HasSuffix(path, "/root/delta"):
			payload, _ := json.Marshal(map[string]any{
				"value":            []map[string]any{},
				"@odata.deltaLink": "http://" + r.Host + "/drives/" + driveID + "/root/delta?token=empty",
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
		DriveID:       driveID,
		Staging:       staging,
	})
	if err != nil {
		t.Fatalf("SyncSharePoint: %v", err)
	}
	markerPath := sharePointCatalogMarkerPath("tenant-1", "site1", driveID)
	if !staging.HasPath(markerPath) {
		t.Fatalf("expected catalog marker at %s", markerPath)
	}
	priorDelta := res.DeltaStates[driveID]
	if priorDelta == "" {
		t.Fatal("expected delta link from first full scan")
	}

	var deltaCalls int
	srv2 := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if strings.Contains(r.URL.Path, "/root/delta") {
			deltaCalls++
			payload, _ := json.Marshal(map[string]any{
				"value":            []map[string]any{},
				"@odata.deltaLink": "http://" + r.Host + "/drives/" + driveID + "/root/delta?token=still-empty",
			})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		}
	}))
	defer srv2.Close()
	client2 := graph.NewTestClient(srv2.URL, graph.ClientOptions{MaxRetries: 2, MaxConcurrency: 8})
	res, err = SyncSharePoint(context.Background(), client2, SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		DriveID:       driveID,
		DeltaStates:   map[string]string{driveID: strings.Replace(priorDelta, srv.URL, srv2.URL, 1)},
		Staging:       staging,
	})
	if err != nil {
		t.Fatalf("second SyncSharePoint: %v", err)
	}
	if deltaCalls != 1 {
		t.Fatalf("second run delta calls = %d, want 1 incremental", deltaCalls)
	}
	if res.Stats["full_resync"] != 0 {
		t.Fatalf("second run full_resync = %d, want 0", res.Stats["full_resync"])
	}
}

func TestSyncSharePointSoftStopDoesNotWriteCatalog(t *testing.T) {
	driveID := "b!soft-stop"
	var calls int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		switch {
		case path == "/drives/"+driveID:
			payload, _ := json.Marshal(map[string]any{"id": driveID, "name": "Documents"})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		case strings.Contains(path, "/drives/") && strings.HasSuffix(path, "/root/delta"):
			calls++
			w.Header().Set("Content-Type", "application/json")
			if calls == 1 {
				payload, _ := json.Marshal(map[string]any{
					"value": []map[string]any{
						{"id": "file-1", "name": "a.docx", "size": float64(10), "file": map[string]any{}},
					},
					"@odata.nextLink": "http://" + r.Host + "/drives/" + driveID + "/root/delta?page=2",
				})
				_, _ = w.Write(payload)
				return
			}
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{"id": "file-1", "name": "a.docx", "size": float64(10), "file": map[string]any{}},
				},
				"@odata.nextLink": "http://" + r.Host + "/drives/" + driveID + "/root/delta?page=3",
			})
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 2, MaxConcurrency: 8})
	staging := graphfs.NewOverlayBuilder()
	_, err := SyncSharePoint(context.Background(), client, SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		DriveID:       driveID,
		Staging:       staging,
	})
	if err != nil {
		t.Fatalf("SyncSharePoint: %v", err)
	}
	markerPath := sharePointCatalogMarkerPath("tenant-1", "site1", driveID)
	if staging.HasPath(markerPath) {
		t.Fatal("duplicate-page soft-stop must not write catalog marker")
	}
}

func TestSyncSharePointFailedFullScanLeavesNoMarker(t *testing.T) {
	driveID := "b!fail-drive"
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		switch {
		case path == "/drives/"+driveID:
			payload, _ := json.Marshal(map[string]any{"id": driveID, "name": "Documents"})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		case strings.Contains(path, "/root/delta"):
			http.Error(w, "server error", http.StatusInternalServerError)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 8})
	staging := graphfs.NewOverlayBuilder()
	_, err := SyncSharePoint(context.Background(), client, SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		DriveID:       driveID,
		Staging:       staging,
	})
	if err == nil {
		t.Fatal("expected sync failure")
	}
	markerPath := sharePointCatalogMarkerPath("tenant-1", "site1", driveID)
	if staging.HasPath(markerPath) {
		t.Fatal("failed full scan must not write catalog marker")
	}
}

func TestShouldForceSharePointFullResyncNoPriorDelta(t *testing.T) {
	opts := SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		Staging:       graphfs.NewOverlayBuilder(),
	}
	res := &sharePointDriveResult{items: 0}
	if shouldForceSharePointFullResync(opts, "b!drive", "", res) {
		t.Fatal("expected no resync without prior delta")
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

func TestSyncSharePointDriveSoftStopsOnDuplicatePage(t *testing.T) {
	driveID := "b!dup-drive"
	var calls int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		switch {
		case path == "/drives/"+driveID:
			payload, _ := json.Marshal(map[string]any{
				"id":        driveID,
				"name":      "Documents",
				"driveType": "documentLibrary",
			})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		case strings.Contains(path, "/drives/") && strings.HasSuffix(path, "/root/delta"):
			calls++
			w.Header().Set("Content-Type", "application/json")
			if calls == 1 {
				payload, _ := json.Marshal(map[string]any{
					"value": []map[string]any{
						{"id": "file-1", "name": "a.docx", "size": float64(10), "file": map[string]any{}},
					},
					"@odata.nextLink": "http://" + r.Host + "/drives/" + driveID + "/root/delta?page=2",
				})
				_, _ = w.Write(payload)
				return
			}
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{"id": "file-1", "name": "a.docx", "size": float64(10), "file": map[string]any{}},
				},
				"@odata.nextLink": "http://" + r.Host + "/drives/" + driveID + "/root/delta?page=3",
			})
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
		DriveID:       driveID,
		Parallel:      8,
		Shard:         ShardFilter{},
		Staging:       staging,
	})
	if err != nil {
		t.Fatalf("SyncSharePoint should soft-stop on duplicate page, got %v", err)
	}
	if res.Stats["items"] != 1 {
		t.Fatalf("items=%d want 1", res.Stats["items"])
	}
	if len(res.Warnings) == 0 {
		t.Fatal("expected duplicate-page warning")
	}
	if _, ok := res.DeltaStates[driveID]; ok {
		t.Fatal("delta token must not advance after duplicate soft-stop")
	}
}

func TestSyncSharePointSingleDriveWritesCatalog(t *testing.T) {
	driveID := "b!drive-only"
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		switch {
		case path == "/drives/"+driveID:
			payload, _ := json.Marshal(map[string]any{
				"id":        driveID,
				"name":      "Shared Documents",
				"driveType": "documentLibrary",
			})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		case strings.Contains(path, "/drives/") && strings.HasSuffix(path, "/root/delta"):
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
	_, err := SyncSharePoint(context.Background(), client, SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		DriveID:       driveID,
		Parallel:      8,
		Shard:         ShardFilter{},
		Staging:       staging,
	})
	if err != nil {
		t.Fatalf("SyncSharePoint: %v", err)
	}

	catalogPath := siteStoragePath("tenant-1", "site1") + "/drives.json"
	if !staging.HasPath(catalogPath) {
		t.Fatalf("expected drives.json at %s, paths=%v", catalogPath, staging.Paths())
	}
}
