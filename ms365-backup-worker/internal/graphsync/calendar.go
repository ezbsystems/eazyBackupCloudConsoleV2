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

// concurrentBoolSet is a mutex-guarded string→bool map used for cross-calendar
// event dedup while SyncCalendar runs calendars in parallel.
type concurrentBoolSet struct {
	mu sync.Mutex
	m  map[string]bool
}

// MarkIfNew returns true when id was not present and is now marked.
func (s *concurrentBoolSet) MarkIfNew(id string) bool {
	if s == nil || id == "" {
		return true
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	if s.m == nil {
		s.m = map[string]bool{}
	}
	if s.m[id] {
		return false
	}
	s.m[id] = true
	return true
}

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
	// Shared across parallel calendar workers — must be mutex-guarded. Assigning
	// the bare map to each scanner (prior bug) caused fatal concurrent map writes
	// under Parallel>1 and crash-looped production batch owners.
	seen := &concurrentBoolSet{m: globalSeen}

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
			scanner.tryMarkSeen = seen.MarkIfNew
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
