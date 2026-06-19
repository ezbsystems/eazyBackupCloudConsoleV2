package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"sync"
	"time"

	"golang.org/x/sync/errgroup"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type SharePointSyncOptions struct {
	AzureTenantID string
	SiteID        string
	DriveID       string
	Parallel      int
	DriveParallel int
	DeltaStates   map[string]string
	Shard         ShardFilter
	Staging       *graphfs.OverlayBuilder
	OnProgress    func(itemsDone, itemsTotal int, bytesEstimate int64)
	Log           RunLogger
	Job           *api.RunJob
}

type SharePointSyncResult struct {
	Stats       map[string]int
	DeltaStates map[string]string
	FileCount   int
	ItemsDone   int
	BytesTotal  int64
	Warnings    []string
}

type sharePointDriveResult struct {
	deltaLink string
	warnings  []string
	removed   int
	items     int
	skipped   int
	bytes     int64
}

type sharePointDriveJob struct {
	id         string
	priorDelta string
}

func SyncSharePoint(ctx context.Context, client *graph.Client, opts SharePointSyncOptions) (*SharePointSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("sharepoint sync requires overlay builder")
	}
	stats := map[string]int{"drives": 0, "items": 0, "removed": 0, "skipped_shard": 0}
	deltaOut := map[string]string{}
	var bytesTotal int64
	var warnings []string

	siteBase := siteStoragePath(opts.AzureTenantID, opts.SiteID)

	var drives []map[string]any
	if opts.DriveID != "" {
		drives = []map[string]any{{"id": opts.DriveID}}
	} else {
		var err error
		drives, err = client.Paginate(ctx, fmt.Sprintf("/sites/%s/drives", opts.SiteID), map[string]string{"$top": "50"})
		if err != nil {
			return nil, err
		}
	}

	if opts.DriveID == "" {
		catalog, _ := json.Marshal(map[string]any{
			"fetched_at": time.Now().UTC().Format(time.RFC3339),
			"value":      drives,
		})
		opts.Staging.PutJSON(siteBase+"/drives.json", catalog, time.Now().UTC())
	}

	jobs := make([]sharePointDriveJob, 0, len(drives))
	for _, drive := range drives {
		driveID, _ := drive["id"].(string)
		if driveID == "" {
			continue
		}
		if opts.DriveID != "" && driveID != opts.DriveID {
			continue
		}
		priorDelta := ""
		if opts.DeltaStates != nil {
			priorDelta = opts.DeltaStates[driveID]
		}
		jobs = append(jobs, sharePointDriveJob{id: driveID, priorDelta: priorDelta})
	}

	driveParallel := opts.DriveParallel
	if driveParallel <= 0 {
		driveParallel = minInt(4, opts.Parallel)
	}
	if driveParallel <= 0 {
		driveParallel = 4
	}

	var mu sync.Mutex
	applyDriveResult := func(res *sharePointDriveResult, driveID string) {
		mu.Lock()
		defer mu.Unlock()
		stats["drives"]++
		stats["items"] += res.items
		stats["removed"] += res.removed
		stats["skipped_shard"] += res.skipped
		bytesTotal += res.bytes
		if res.deltaLink != "" {
			deltaOut[driveID] = res.deltaLink
		}
		warnings = append(warnings, res.warnings...)
	}

	useParallel := opts.DriveID == "" && len(jobs) > 1 && driveParallel > 1
	if useParallel {
		g, gctx := errgroup.WithContext(ctx)
		g.SetLimit(driveParallel)
		for _, job := range jobs {
			job := job
			g.Go(func() error {
				res, err := syncSharePointDrive(gctx, client, opts, job.id, job.priorDelta, &mu)
				if err != nil {
					return err
				}
				applyDriveResult(res, job.id)
				return nil
			})
		}
		if err := g.Wait(); err != nil {
			return nil, err
		}
	} else {
		for _, job := range jobs {
			res, err := syncSharePointDrive(ctx, client, opts, job.id, job.priorDelta, &mu)
			if err != nil {
				return nil, err
			}
			applyDriveResult(res, job.id)
		}
	}

	if opts.OnProgress != nil {
		opts.OnProgress(stats["items"], stats["items"], bytesTotal)
	}
	return &SharePointSyncResult{
		Stats:       stats,
		DeltaStates: deltaOut,
		FileCount:   opts.Staging.EntryCount(),
		ItemsDone:   stats["items"],
		BytesTotal:  bytesTotal,
		Warnings:    warnings,
	}, nil
}

func syncSharePointDrive(
	ctx context.Context,
	client *graph.Client,
	opts SharePointSyncOptions,
	driveID, priorDelta string,
	mu *sync.Mutex,
) (*sharePointDriveResult, error) {
	res := &sharePointDriveResult{}
	outcome := &graph.PaginationOutcome{}
	monitor := paginationMonitorForJob(opts.Job, "sharepoint", "sharepoint:"+driveID, graphLog(opts.Log))
	deltaOpts := &graph.DeltaPaginateOptions{Monitor: monitor, Outcome: outcome}
	items, deltaLink, err := paginateDeltaResilient(ctx, client,
		fmt.Sprintf("/drives/%s/root/delta", driveID),
		priorDelta,
		"id,name,size,file,folder,parentReference,lastModifiedDateTime",
		200, nil, deltaOpts)
	if err != nil {
		return nil, err
	}
	if outcome.CapReached {
		res.warnings = append(res.warnings, fmt.Sprintf("drive %s: delta pagination cap reached (%d pages, %d items)", driveID, outcome.Pages, outcome.TotalItems))
	}
	for _, item := range items {
		if removed, _ := item["@removed"].(map[string]any); removed != nil {
			id, _ := item["id"].(string)
			if id == "" {
				continue
			}
			mu.Lock()
			opts.Staging.RemoveByItemID(id)
			mu.Unlock()
			res.removed++
			continue
		}
		id, _ := item["id"].(string)
		if id == "" || isDriveFolder(item) {
			continue
		}
		if !opts.Shard.IncludesItem(id) {
			res.skipped++
			continue
		}
		path := siteDriveContentPath(opts.AzureTenantID, opts.SiteID, driveID, item)
		gf, err := graphfs.NewGraphFileFromDriveItem(client, driveID, item)
		if err != nil {
			return nil, err
		}
		mu.Lock()
		opts.Staging.PutWithItemID(id, path, gf)
		mu.Unlock()
		res.bytes += gf.Size()
		res.items++
	}
	if deltaLink != "" {
		res.deltaLink = deltaLink
	}
	return res, nil
}

func siteDriveContentPath(tenantID, siteID, driveID string, item map[string]any) string {
	name, _ := item["name"].(string)
	if name == "" {
		if id, _ := item["id"].(string); id != "" {
			name = id
		} else {
			name = "unknown"
		}
	}
	name = safePathSegment(name)
	relPath := driveRelativePath(item)
	base := siteDriveContentBase(tenantID, siteID, driveID)
	if relPath == "" {
		return base + "/" + name
	}
	return base + "/" + relPath + "/" + name
}

func siteDriveContentBase(tenantID, siteID, driveID string) string {
	return fmt.Sprintf("%s/sites/%s/drives/%s/content", tenantID, storageSafeID(siteID), safeID(driveID))
}
