package graphsync

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"net/url"
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

const teamsDeltaTokenErrorBody = `{"error":{"code":"BadRequest","message":"Parameter 'DeltaToken' not supported for this request."}}`

func TestSyncTeamsDeltaTokenFilterRetrySucceeds(t *testing.T) {
	const teamID = "team-abc"
	const channelA = "channel-bad"
	var filterQueries []string

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/channels") && !strings.Contains(r.URL.Path, "/messages/"):
			_, _ = w.Write([]byte(`{"value":[{"id":"` + channelA + `","displayName":"General"}]}`))
		case r.URL.Path == "/teams/"+teamID:
			_, _ = w.Write([]byte(`{"id":"` + teamID + `","displayName":"MSFT"}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			if r.URL.Query().Get("$filter") != "" {
				filterQueries = append(filterQueries, r.URL.RawQuery)
				payload, _ := json.Marshal(map[string]any{
					"value": []map[string]any{
						{"id": "msg-filtered", "lastModifiedDateTime": "2026-07-07T12:00:00Z"},
					},
					"@odata.deltaLink": "https://graph.test/delta-filtered",
				})
				_, _ = w.Write(payload)
				return
			}
			w.WriteHeader(http.StatusBadRequest)
			_, _ = w.Write([]byte(teamsDeltaTokenErrorBody))
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
	if len(filterQueries) != 1 {
		t.Fatalf("expected one $filter retry, got %d (%v)", len(filterQueries), filterQueries)
	}
	u, err := url.Parse("http://local?" + filterQueries[0])
	if err != nil {
		t.Fatal(err)
	}
	filter := u.Query().Get("$filter")
	if filter == "" || !strings.Contains(filter, "lastModifiedDateTime") {
		t.Fatalf("filter query missing lastModifiedDateTime filter: %q (parsed %q)", filterQueries[0], filter)
	}
	if res.Stats["messages"] != 1 {
		t.Fatalf("messages=%d want 1", res.Stats["messages"])
	}
	if res.DeltaStates[channelA] != "https://graph.test/delta-filtered" {
		t.Fatalf("delta state = %q", res.DeltaStates[channelA])
	}
}

func TestSyncTeamsDeltaTokenSoftSkipsBadChannel(t *testing.T) {
	const teamID = "team-abc"
	const channelA = "channel-bad"
	const channelB = "channel-good"
	var backedUpChannels []string

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/channels") && !strings.Contains(r.URL.Path, "/messages/"):
			_, _ = w.Write([]byte(`{"value":[` +
				`{"id":"` + channelA + `","displayName":"Broken"},` +
				`{"id":"` + channelB + `","displayName":"Good"}` +
				`]}`))
		case r.URL.Path == "/teams/"+teamID:
			_, _ = w.Write([]byte(`{"id":"` + teamID + `","displayName":"MSFT"}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			chID := ""
			if strings.Contains(r.URL.Path, channelA) {
				chID = channelA
			} else if strings.Contains(r.URL.Path, channelB) {
				chID = channelB
			}
			if chID == channelA {
				w.WriteHeader(http.StatusBadRequest)
				_, _ = w.Write([]byte(teamsDeltaTokenErrorBody))
				return
			}
			backedUpChannels = append(backedUpChannels, chID)
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{"id": "msg-" + chID, "lastModifiedDateTime": "2026-07-07T12:00:00Z"},
				},
				"@odata.deltaLink": "https://graph.test/delta-" + chID,
			})
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	var runLog []string
	res, err := SyncTeams(context.Background(), client, TeamsSyncOptions{
		AzureTenantID: "tenant-1",
		TeamID:        teamID,
		Staging:       graphfs.NewOverlayBuilder(),
		Log: func(level, message string) {
			runLog = append(runLog, level+": "+message)
		},
	})
	if err != nil {
		t.Fatalf("SyncTeams: %v", err)
	}
	if res.Stats["channels_skipped"] != 1 {
		t.Fatalf("channels_skipped=%d want 1", res.Stats["channels_skipped"])
	}
	if res.Stats["messages"] != 1 {
		t.Fatalf("messages=%d want 1 from good channel", res.Stats["messages"])
	}
	if _, ok := res.DeltaStates[channelA]; ok {
		t.Fatalf("skipped channel should not have delta state")
	}
	if res.DeltaStates[channelB] == "" {
		t.Fatalf("good channel missing delta state")
	}
	if len(backedUpChannels) != 1 || backedUpChannels[0] != channelB {
		t.Fatalf("backed up channels = %v, want [%s]", backedUpChannels, channelB)
	}
	logText := strings.Join(runLog, "\n")
	if !strings.Contains(strings.ToLower(logText), "soft-skip") && !strings.Contains(strings.ToLower(logText), "skipped") {
		t.Fatalf("run log missing soft-skip warning: %q", logText)
	}
}

func TestSyncTeamsDeltaTokenRebaselineOnResume(t *testing.T) {
	const teamID = "team-abc"
	const channelID = "channel-general"
	var deltaRequests []string

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.HasSuffix(r.URL.Path, "/channels") && !strings.Contains(r.URL.Path, "/messages/"):
			_, _ = w.Write([]byte(`{"value":[{"id":"` + channelID + `","displayName":"General"}]}`))
		case r.URL.Path == "/teams/"+teamID:
			_, _ = w.Write([]byte(`{"id":"` + teamID + `","displayName":"MSFT"}`))
		case strings.Contains(r.URL.Path, "/messages/delta"):
			if strings.Contains(r.URL.RawQuery, "deltatoken=stale") {
				deltaRequests = append(deltaRequests, "resume")
				w.WriteHeader(http.StatusBadRequest)
				_, _ = w.Write([]byte(teamsDeltaTokenErrorBody))
				return
			}
			deltaRequests = append(deltaRequests, "baseline")
			payload, _ := json.Marshal(map[string]any{
				"value": []map[string]any{
					{"id": "msg-1", "lastModifiedDateTime": "2026-07-07T12:00:00Z"},
				},
				"@odata.deltaLink": "https://graph.test/delta-fresh",
			})
			_, _ = w.Write(payload)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 4})
	res, err := SyncTeams(context.Background(), client, TeamsSyncOptions{
		AzureTenantID: "tenant-1",
		TeamID:        teamID,
		Staging:       graphfs.NewOverlayBuilder(),
		DeltaStates: map[string]string{
			channelID: srv.URL + "/teams/" + teamID + "/channels/" + channelID + "/messages/delta?$deltatoken=stale",
		},
	})
	if err != nil {
		t.Fatalf("SyncTeams: %v", err)
	}
	if strings.Join(deltaRequests, ",") != "resume,baseline" {
		t.Fatalf("delta requests = %v, want [resume baseline]", deltaRequests)
	}
	if res.Stats["messages"] != 1 {
		t.Fatalf("messages=%d want 1", res.Stats["messages"])
	}
	if res.DeltaStates[channelID] != "https://graph.test/delta-fresh" {
		t.Fatalf("delta state = %q", res.DeltaStates[channelID])
	}
}
