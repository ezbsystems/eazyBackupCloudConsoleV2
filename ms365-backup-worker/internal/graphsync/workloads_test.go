package graphsync

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestWorkloadRunnerSkipsMailWhenMailboxNotEnabled(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if strings.Contains(r.URL.Path, "/mailFolders") {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusNotFound)
			_, _ = w.Write([]byte(`{"error":{"code":"MailboxNotEnabledForRESTAPI","message":"The mailbox is either inactive, soft-deleted, or is hosted on-premise."}}`))
			return
		}
		http.NotFound(w, r)
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	runner := &WorkloadRunner{
		Client:  client,
		Overlay: graphfs.NewOverlayBuilder(),
		Job: &api.RunJob{
			PhysicalKey:   "mailbox:aidan",
			GraphID:       "user-aidan",
			AzureTenantID: "tenant-1",
			Workloads:     map[string]bool{"mail": true},
			Scope:         api.ScopeFlags{"mail": true},
		},
	}

	res, err := runner.Run(context.Background())
	if err != nil {
		t.Fatalf("Run: %v", err)
	}
	mailStats, ok := res.Stats["mail"].(map[string]any)
	if !ok {
		t.Fatalf("mail stats missing or wrong type: %#v", res.Stats["mail"])
	}
	if mailStats["skipped"] != "mailbox_not_enabled" {
		t.Fatalf("mail skipped stat = %#v", mailStats["skipped"])
	}
	if res.FileCount != 0 {
		t.Fatalf("expected empty overlay, got %d entries", res.FileCount)
	}
}

// TestWorkloadRunnerSkipsTasksWhenMailboxNotEnabled reproduces run
// abef5a51-f02b-497d-b4d9-0feea0e04464: the To Do (tasks) endpoint returns a
// 401 UnknownError for no-mailbox users instead of the 404 mail/contacts return.
// The whole run previously hard-failed and requeued; it must now skip gracefully.
func TestWorkloadRunnerSkipsTasksWhenMailboxNotEnabled(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if strings.Contains(r.URL.Path, "/todo/lists") {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusUnauthorized)
			_, _ = w.Write([]byte(`{"error":{"code":"UnknownError","message":"","innerError":{"date":"2026-06-21T11:48:08","request-id":"4740a606-dfbe-4610-865c-2488141c6a1d"}}}`))
			return
		}
		http.NotFound(w, r)
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	runner := &WorkloadRunner{
		Client:  client,
		Overlay: graphfs.NewOverlayBuilder(),
		Job: &api.RunJob{
			PhysicalKey:   "user:ac77197d-3cc5-4302-b8c2-5ab33e44faec",
			GraphID:       "ac77197d-3cc5-4302-b8c2-5ab33e44faec",
			AzureTenantID: "tenant-1",
			Workloads:     map[string]bool{"tasks": true},
			Scope:         api.ScopeFlags{"tasks": true},
		},
	}

	res, err := runner.Run(context.Background())
	if err != nil {
		t.Fatalf("Run returned error, expected graceful skip: %v", err)
	}
	tasksStats, ok := res.Stats["tasks"].(map[string]any)
	if !ok {
		t.Fatalf("tasks stats missing or wrong type: %#v", res.Stats["tasks"])
	}
	if tasksStats["skipped"] != "mailbox_not_enabled" {
		t.Fatalf("tasks skipped stat = %#v", tasksStats["skipped"])
	}
	if res.FileCount != 0 {
		t.Fatalf("expected empty overlay, got %d entries", res.FileCount)
	}
}

// TestWorkloadRunnerSkipsSharePointWhenAccessDenied reproduces run 976893d2:
// inaccessible SharePoint sites return Graph 403 accessDenied; the run must
// complete with access_denied skip instead of hard-failing the batch.
func TestWorkloadRunnerSkipsSharePointWhenAccessDenied(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if strings.Contains(r.URL.Path, "/drives") {
			w.Header().Set("Content-Type", "application/json")
			w.WriteHeader(http.StatusForbidden)
			_, _ = w.Write([]byte(`{"error":{"code":"accessDenied","message":"Access denied","innerError":{"date":"2026-06-21T03:18:12","request-id":"a1b2c3d4-e5f6-7890-abcd-ef1234567890","client-request-id":"a1b2c3d4-e5f6-7890-abcd-ef1234567890"}}}`))
			return
		}
		http.NotFound(w, r)
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	runner := &WorkloadRunner{
		Client:  client,
		Overlay: graphfs.NewOverlayBuilder(),
		Job: &api.RunJob{
			PhysicalKey:   "site:designer",
			GraphID:       "6d511216-0000-0000-0000-000000000001",
			SiteID:        "6d511216-0000-0000-0000-000000000001",
			AzureTenantID: "tenant-1",
			Workloads:     map[string]bool{"sharepoint": true},
			Scope:         api.ScopeFlags{"files": true},
		},
	}

	res, err := runner.Run(context.Background())
	if err != nil {
		t.Fatalf("Run returned error, expected graceful skip: %v", err)
	}
	spStats, ok := res.Stats["sharepoint"].(map[string]any)
	if !ok {
		t.Fatalf("sharepoint stats missing or wrong type: %#v", res.Stats["sharepoint"])
	}
	if spStats["skipped"] != "access_denied" {
		t.Fatalf("sharepoint skipped stat = %#v", spStats["skipped"])
	}
	if res.FileCount != 0 {
		t.Fatalf("expected empty overlay, got %d entries", res.FileCount)
	}
}

// TestAllowsSharePointForDriveShard reproduces STCHF Admin drive child runs:
// PHP maps drive: shards with _site_id to sharepoint workload, but allowsWorkload
// previously rejected kind "drive" and the run completed no_changes with no manifest.
func TestAllowsSharePointForDriveShard(t *testing.T) {
	siteID := "stchf.sharepoint.com,297208e1-3eaf-45b4-b29c-40a5125d68ff,346e6a93-6fd8-4655-b0e4-acf995cd05eb"
	driveID := "b!4QhyKa8-tEWynEClEl1o_5NqbjTYb1VGsOSs-ZXNBet47NJxJZINR4Q_sTH8rPRj"
	runner := &WorkloadRunner{
		Job: &api.RunJob{
			PhysicalKey: "drive:" + driveID,
			SiteID:      siteID,
			DriveID:     driveID,
			GraphID:     siteID,
			Workloads:   map[string]bool{"sharepoint": true},
			Scope:       api.ScopeFlags{"files": true},
		},
	}
	if !runner.allowsWorkload("sharepoint") {
		t.Fatal("expected sharepoint workload for SharePoint drive shard")
	}
	if runner.allowsWorkload("onedrive") {
		t.Fatal("onedrive must not run for SharePoint drive shard")
	}
}

func TestSyncSharePointDriveShard(t *testing.T) {
	siteID := "stchf.sharepoint.com,297208e1-3eaf-45b4-b29c-40a5125d68ff,346e6a93-6fd8-4655-b0e4-acf995cd05eb"
	driveID := "b!4QhyKa8-tEWynEClEl1o_5NqbjTYb1VGsOSs-ZXNBet47NJxJZINR4Q_sTH8rPRj"
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case strings.Contains(r.URL.Path, "/sites/"):
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write([]byte(`{"id":"` + siteID + `","displayName":"STCHF Admin","webUrl":"https://stchf.sharepoint.com"}`))
		case strings.Contains(r.URL.Path, "/root/delta"):
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write([]byte(`{"value":[{"id":"item-1","name":"Budget.xlsx","size":1024,"file":{},"parentReference":{"path":"/drives/` + driveID + `/root:"}}]}`))
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	runner := &WorkloadRunner{
		Client:  client,
		Overlay: graphfs.NewOverlayBuilder(),
		Job: &api.RunJob{
			PhysicalKey:   "drive:" + driveID,
			SiteID:        siteID,
			DriveID:       driveID,
			GraphID:       siteID,
			AzureTenantID: "4728969e-5eff-4981-b0c6-46eadac79cfe",
			Workloads:     map[string]bool{"sharepoint": true},
			Scope:         api.ScopeFlags{"files": true},
		},
	}

	res, err := runner.Run(context.Background())
	if err != nil {
		t.Fatalf("Run: %v", err)
	}
	spStats, ok := res.Stats["sharepoint"].(map[string]int)
	if !ok {
		t.Fatalf("sharepoint stats missing or wrong type: %#v", res.Stats["sharepoint"])
	}
	if spStats["items"] != 1 {
		t.Fatalf("sharepoint items = %d, want 1", spStats["items"])
	}
	if res.FileCount < 1 {
		t.Fatalf("expected overlay entries, got %d", res.FileCount)
	}
}
