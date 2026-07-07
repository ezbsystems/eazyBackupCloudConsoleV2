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

func TestSyncTeamsDeltaOmitsTopQueryParam(t *testing.T) {
	const teamID = "team-abc"
	const channelID = "channel-general"
	var deltaQuery string

	var channelsQuery string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/channels") && !strings.Contains(r.URL.Path, "/messages/"):
			channelsQuery = r.URL.RawQuery
			_, _ = w.Write([]byte(`{"value":[{"id":"` + channelID + `","displayName":"General"}]}`))
		case r.URL.Path == "/teams/"+teamID:
			_, _ = w.Write([]byte(`{"id":"` + teamID + `","displayName":"MSFT"}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			deltaQuery = r.URL.RawQuery
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{"id": "msg-1", "lastModifiedDateTime": "2026-07-07T12:00:00Z", "body": map[string]any{"contentType": "text", "content": "hi"}},
				},
				"@odata.deltaLink": "https://graph.test/delta-done",
			})
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	overlay := graphfs.NewOverlayBuilder()

	res, err := SyncTeams(context.Background(), client, TeamsSyncOptions{
		AzureTenantID: "tenant-1",
		TeamID:        teamID,
		Staging:       overlay,
	})
	if err != nil {
		t.Fatalf("SyncTeams: %v", err)
	}
	if strings.Contains(deltaQuery, "top") {
		t.Fatalf("teams messages/delta must not send $top, got query %q", deltaQuery)
	}
	if strings.Contains(strings.ToLower(channelsQuery), "top") {
		t.Fatalf("teams channels list must not send $top, got query %q", channelsQuery)
	}
	if res.Stats["messages"] != 1 {
		t.Fatalf("messages=%d want 1", res.Stats["messages"])
	}
}
