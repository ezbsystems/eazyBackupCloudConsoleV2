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

func TestSyncSharePointListsReportsProgressDuringDeltaPagination(t *testing.T) {
	const listID = "list-alpha"
	deltaPage := 0
	var baseURL string

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		path := r.URL.Path
		switch {
		case strings.HasSuffix(path, "/lists") && !strings.Contains(path, "/items"):
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{{"id": listID, "displayName": "Tasks"}},
			})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		case strings.Contains(path, "/items/delta"):
			deltaPage++
			if deltaPage == 1 {
				payload, _ := json.Marshal(map[string]any{
					"value": []map[string]any{
						{"id": "item-1", "fields": map[string]any{"Title": "A"}},
					},
					"@odata.nextLink": baseURL + "/sites/site1/lists/" + listID + "/items/delta?page=2",
				})
				w.Header().Set("Content-Type", "application/json")
				_, _ = w.Write(payload)
				return
			}
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{"id": "item-2", "fields": map[string]any{"Title": "B"}},
				},
				"@odata.deltaLink": baseURL + "/sites/site1/lists/" + listID + "/items/delta?token=done",
			})
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()
	baseURL = srv.URL

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	staging := graphfs.NewOverlayBuilder()
	var progress []int
	res, err := SyncSharePointLists(context.Background(), client, SharePointListsSyncOptions{
		AzureTenantID: "tenant-1",
		SiteID:        "site1",
		Staging:       staging,
		OnProgress: func(done, total int) {
			progress = append(progress, done)
		},
	})
	if err != nil {
		t.Fatalf("SyncSharePointLists: %v", err)
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
