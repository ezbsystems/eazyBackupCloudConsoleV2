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

type CalendarSyncOptions struct {
	AzureTenantID string
	UserID        string
	Parallel      int
	Staging       *graphfs.OverlayBuilder
}

type CalendarSyncResult struct {
	Stats     map[string]int
	FileCount int
}

func SyncCalendar(ctx context.Context, client *graph.Client, opts CalendarSyncOptions) (*CalendarSyncResult, error) {
	if opts.Parallel <= 0 {
		opts.Parallel = 8
	}
	if opts.Staging == nil {
		return nil, fmt.Errorf("calendar sync requires overlay builder")
	}

	stats := map[string]int{"calendars": 0, "events": 0}
	var mu sync.Mutex

	cals, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/calendars", opts.UserID), map[string]string{"$top": "50"})
	if err != nil {
		return nil, err
	}

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

			events, err := client.Paginate(gctx, fmt.Sprintf("/users/%s/calendars/%s/events", opts.UserID, calID), map[string]string{"$top": "100"})
			if err != nil {
				return err
			}
			for _, ev := range events {
				id, _ := ev["id"].(string)
				if id == "" {
					continue
				}
				body, _ := json.Marshal(ev)
				path := fmt.Sprintf("%s/users/%s/calendar/%s/%s.json", opts.AzureTenantID, opts.UserID, safeID(calID), safeID(id))
				opts.Staging.PutJSON(path, body, graphfsModTime(ev["lastModifiedDateTime"]))
				mu.Lock()
				stats["events"]++
				mu.Unlock()
			}
			return nil
		})
	}

	if err := g.Wait(); err != nil {
		return nil, err
	}

	return &CalendarSyncResult{Stats: stats, FileCount: opts.Staging.EntryCount()}, nil
}
