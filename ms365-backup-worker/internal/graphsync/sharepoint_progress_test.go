package graphsync

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestSyncSharePointDriveReportsPerPageProgress(t *testing.T) {
	const driveID = "b!drive-progress"
	deltaPage := 0
	var baseURL string

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		switch {
		case path == "/drives/"+driveID:
			payload, _ := json.Marshal(map[string]any{"id": driveID, "name": "Documents"})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		case strings.Contains(path, "/root/delta"):
			deltaPage++
			if deltaPage == 1 {
				payload, _ := json.Marshal(map[string]any{
					"value": []map[string]any{
						{
							"id":   "item-1",
							"name": "doc.pdf",
							"size": float64(1),
							"file": map[string]any{},
						},
					},
					"@odata.nextLink": baseURL + "/drives/" + driveID + "/root/delta?page=2",
				})
				w.Header().Set("Content-Type", "application/json")
				_, _ = w.Write(payload)
				return
			}
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{
						"id":   "item-2",
						"name": "doc2.pdf",
						"size": float64(1),
						"file": map[string]any{},
					},
				},
				"@odata.deltaLink": baseURL + "/drives/" + driveID + "/root/delta?token=done",
			})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()
	baseURL = srv.URL

	var progress []int
	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	staging := graphfs.NewOverlayBuilder()
	res, err := SyncSharePoint(context.Background(), client, SharePointSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		DriveID:       driveID,
		Parallel:      4,
		Shard:         ShardFilter{},
		Staging:       staging,
		OnProgress: func(done, total int, _ int64) {
			progress = append(progress, done)
		},
	})
	if err != nil {
		t.Fatalf("SyncSharePoint: %v", err)
	}
	if res.Stats["items"] != 2 {
		t.Fatalf("items = %d, want 2", res.Stats["items"])
	}
	if len(progress) < 3 {
		t.Fatalf("expected per-page + final progress callbacks, got %d: %v", len(progress), progress)
	}
	if progress[0] != 1 {
		t.Fatalf("first progress = %d, want 1 after first delta page", progress[0])
	}
}
