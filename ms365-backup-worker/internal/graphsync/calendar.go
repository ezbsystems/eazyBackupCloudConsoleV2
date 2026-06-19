package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"sync"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
	"golang.org/x/sync/errgroup"
)

// RunLogger posts worker diagnostic lines (level, message).
type RunLogger func(level, message string)

type CalendarSyncOptions struct {
	AzureTenantID string
	UserID        string
	Parallel      int
	Staging       *graphfs.OverlayBuilder
	DeltaStates   map[string]string // calendar inventory state keys
	Log           RunLogger
	GlobalSeen    map[string]bool // optional cross-calendar dedup within run
}

type CalendarSyncResult struct {
	Stats       map[string]int
	FileCount   int
	DeltaStates map[string]string
}

func SyncCalendar(ctx context.Context, client *graph.Client, opts CalendarSyncOptions) (*CalendarSyncResult, error) {
	if opts.Parallel <= 0 {
		opts.Parallel = 8
	}
	if opts.Staging == nil {
		return nil, fmt.Errorf("calendar sync requires overlay builder")
	}

	stats := map[string]int{"calendars": 0, "events": 0}
	deltaOut := map[string]string{}
	globalSeen := map[string]bool{}
	if opts.GlobalSeen != nil {
		globalSeen = opts.GlobalSeen
	}

	cals, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/calendars", opts.UserID), map[string]string{"$top": "50"})
	if err != nil {
		return nil, err
	}

	var mu sync.Mutex
	g, gctx := errgroup.WithContext(ctx)
	g.SetLimit(opts.Parallel)

	for _, cal := range cals {
		cal := cal
		g.Go(func() error {
			calID, _ := cal["id"].(string)
			if calID == "" {
				return nil
			}
			mu.Lock()
			stats["calendars"]++
			mu.Unlock()

			// Write calendar metadata
			meta, _ := json.Marshal(cal)
			metaPath := fmt.Sprintf("%s/users/%s/calendar/%s/_calendar.json", opts.AzureTenantID, opts.UserID, safeID(calID))
			opts.Staging.PutJSON(metaPath, meta, graphfsModTime(cal["lastModifiedDateTime"]))

			prior := calendarInventoryStateFromMap(opts.DeltaStates, calID)
			scanner := newCalendarScanner(client, opts, calID, prior)
			scanner.seenEventIDs = globalSeen
			result, err := scanner.run(gctx)
			if err != nil {
				return err
			}
			if err := enrichCalendarEvents(gctx, client, opts, calID, scanner.enrichQueue); err != nil {
				return err
			}

			mu.Lock()
			stats["events"] += result.events
			mergeCalendarStates(deltaOut, calID, result.state)
			for id := range scanner.seenEventIDs {
				globalSeen[id] = true
			}
			mu.Unlock()
			return nil
		})
	}

	if err := g.Wait(); err != nil {
		return nil, err
	}

	return &CalendarSyncResult{
		Stats:       stats,
		FileCount:   opts.Staging.EntryCount(),
		DeltaStates: deltaOut,
	}, nil
}
