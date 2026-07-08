package graphsync

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

func TestSubdivideHourRangeIsTerminal(t *testing.T) {
	s := &calendarScanner{}
	start := time.Date(2024, 8, 29, 18, 0, 0, 0, time.UTC)
	end := start.Add(time.Hour)
	if sub := s.subdivide(timeRange{Start: start, End: end}); len(sub) != 0 {
		t.Fatalf("expected hour partition to be terminal, got %d sub-ranges", len(sub))
	}
}

func TestScanPartitionsSkipsWedgedHourPartition(t *testing.T) {
	eventID := "evt-dup-1"
	var page int
	var srv *httptest.Server
	srv = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if !strings.Contains(r.URL.Path, "/events") {
			http.NotFound(w, r)
			return
		}
		w.Header().Set("Content-Type", "application/json")
		page++
		if page == 1 {
			_ = json.NewEncoder(w).Encode(map[string]any{
				"value":           []map[string]any{{"id": eventID, "type": "singleInstance"}},
				"@odata.nextLink": srv.URL + r.URL.Path + "?$skiptoken=page2",
			})
			return
		}
		// Graph duplicate-page defect: page 2 repeats page 1 ids.
		_ = json.NewEncoder(w).Encode(map[string]any{
			"value":           []map[string]any{{"id": eventID, "type": "singleInstance"}},
			"@odata.nextLink": srv.URL + r.URL.Path + "?$skiptoken=page3",
		})
	}))
	defer srv.Close()

	client := graph.NewTestClient(srv.URL, graph.ClientOptions{MaxRetries: 0, MaxConcurrency: 2})
	scanner := newCalendarScanner(client, CalendarSyncOptions{
		UserID:        "user-1",
		AzureTenantID: "tenant-1",
		Staging:       graphfs.NewOverlayBuilder(),
	}, "cal-1", CalendarInventoryState{})

	start := time.Date(2024, 8, 29, 18, 0, 0, 0, time.UTC)
	err := scanner.scanPartitions(context.Background(), []timeRange{{Start: start, End: start.Add(time.Hour)}})
	if err != nil {
		t.Fatalf("scanPartitions: %v", err)
	}
	if scanner.skippedWedgePartitions != 1 {
		t.Fatalf("skippedWedgePartitions = %d, want 1", scanner.skippedWedgePartitions)
	}
}
