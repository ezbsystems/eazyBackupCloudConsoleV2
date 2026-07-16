package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync/atomic"
	"testing"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

// Regression: SyncCalendar used to alias every scanner's seenEventIDs to the same
// globalSeen map, then write it from parallel errgroup workers → fatal concurrent
// map writes (observed crashing prod workers mid Deetken batch).
func TestSyncCalendarParallelCalendarsNoSharedSeenRace(t *testing.T) {
	const calendars = 8
	const eventsPerCal = 40

	var calListCalls atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		path := r.URL.Path
		switch {
		case strings.HasSuffix(path, "/calendars") && !strings.Contains(path, "/events"):
			calListCalls.Add(1)
			cals := make([]map[string]any, 0, calendars)
			for i := 0; i < calendars; i++ {
				cals = append(cals, map[string]any{
					"id":                   fmt.Sprintf("cal-%d", i),
					"lastModifiedDateTime": "2026-01-01T00:00:00Z",
				})
			}
			_ = json.NewEncoder(w).Encode(map[string]any{"value": cals})
		case strings.Contains(path, "/events"):
			// Overlapping event IDs across calendars stress shared-seen dedup.
			evs := make([]map[string]any, 0, eventsPerCal)
			for i := 0; i < eventsPerCal; i++ {
				evs = append(evs, map[string]any{
					"id":                   fmt.Sprintf("evt-shared-%d", i),
					"type":                 "singleInstance",
					"hasAttachments":       false,
					"lastModifiedDateTime": "2026-01-02T00:00:00Z",
				})
			}
			_ = json.NewEncoder(w).Encode(map[string]any{"value": evs})
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 16})
	globalSeen := map[string]bool{}
	_, err := SyncCalendar(context.Background(), client, CalendarSyncOptions{
		AzureTenantID: "tenant-1",
		UserID:        "user-1",
		Parallel:      calendars,
		Staging:       graphfs.NewOverlayBuilder(),
		GlobalSeen:    globalSeen,
	})
	if err != nil {
		t.Fatalf("SyncCalendar: %v", err)
	}
	if calListCalls.Load() < 1 {
		t.Fatal("expected calendar list call")
	}
	// Dedup across calendars: each shared event id marked once.
	if got := len(globalSeen); got != eventsPerCal {
		t.Fatalf("globalSeen size=%d want %d", got, eventsPerCal)
	}
}
