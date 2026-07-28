package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
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
	deltaLink         string
	warnings          []string
	removed           int
	items             int
	skipped           int
	bytes             int64
	fullResync        bool
	startedFull       bool
	stoppedIncomplete bool
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

	if siteInfo, err := client.GetJSON(ctx, fmt.Sprintf("/sites/%s", opts.SiteID), map[string]string{
		"$select": "id,displayName,webUrl",
	}); err == nil {
		sidecar, _ := json.Marshal(map[string]any{
			"id":          stringFromAny(siteInfo["id"]),
			"displayName": stringFromAny(siteInfo["displayName"]),
			"webUrl":      stringFromAny(siteInfo["webUrl"]),
		})
		opts.Staging.PutJSON(siteBase+"/_site.json", sidecar, time.Now().UTC())
	}

	var drives []map[string]any
	if opts.DriveID != "" {
		driveEntry := map[string]any{"id": opts.DriveID}
		if driveInfo, err := client.GetJSON(ctx, fmt.Sprintf("/drives/%s", opts.DriveID), map[string]string{
			"$select": "id,name,driveType",
		}); err == nil {
			if id := stringFromAny(driveInfo["id"]); id != "" {
				driveEntry["id"] = id
			}
			if name := stringFromAny(driveInfo["name"]); name != "" {
				driveEntry["name"] = name
			}
			if driveType := stringFromAny(driveInfo["driveType"]); driveType != "" {
				driveEntry["driveType"] = driveType
			}
		}
		drives = []map[string]any{driveEntry}
	} else {
		var err error
		drives, err = client.Paginate(ctx, fmt.Sprintf("/sites/%s/drives", opts.SiteID), map[string]string{"$top": "50"})
		if err != nil {
			return nil, err
		}
	}

	if len(drives) > 0 {
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
		if res.fullResync {
			stats["full_resync"]++
		}
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
				res, err := processSharePointDrive(gctx, client, opts, job, &mu)
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
			res, err := processSharePointDrive(ctx, client, opts, job, &mu)
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

func processSharePointDrive(
	ctx context.Context,
	client *graph.Client,
	opts SharePointSyncOptions,
	job sharePointDriveJob,
	mu *sync.Mutex,
) (*sharePointDriveResult, error) {
	priorDelta := job.priorDelta
	fullResync := false
	if needsSharePointPreflightRebaseline(opts, job.id, priorDelta) {
		if opts.Log != nil {
			opts.Log("warning", fmt.Sprintf("SharePoint drive %s: unproven baseline; forcing full delta before incremental", job.id))
		}
		clearSharePointDriveStaging(opts, job.id)
		priorDelta = ""
		fullResync = true
	}

	res, err := syncSharePointDrive(ctx, client, opts, job.id, priorDelta, mu)
	if err != nil {
		return nil, err
	}
	if fullResync {
		res.fullResync = true
	}

	if shouldForceSharePointFullResync(opts, job.id, job.priorDelta, res) {
		if opts.Log != nil {
			opts.Log("warning", fmt.Sprintf("SharePoint drive %s: incremental completed without baseline proof; forcing full delta", job.id))
		}
		clearSharePointDriveStaging(opts, job.id)
		full, err := syncSharePointDrive(ctx, client, opts, job.id, "", mu)
		if err != nil {
			return nil, err
		}
		full.fullResync = true
		res = full
	}

	ensureSharePointCatalogMarker(opts, job.id, res)
	return res, nil
}

func syncSharePointDrive(
	ctx context.Context,
	client *graph.Client,
	opts SharePointSyncOptions,
	driveID, priorDelta string,
	mu *sync.Mutex,
) (*sharePointDriveResult, error) {
	res := &sharePointDriveResult{startedFull: strings.TrimSpace(priorDelta) == ""}
	outcome := &graph.PaginationOutcome{}
	monitor := paginationMonitorForJob(opts.Job, "sharepoint", "sharepoint:"+driveID, graphLog(opts.Log))
	// Known Graph defect: a page can return only previously-seen item IDs while still
	// advertising @odata.nextLink. Strict mode hard-fails the whole batch and thrash-
	// reclaims forever (prod 352789d3: shard stuck ~109h / attempts≫max). Mirror calendar
	// normal-scan DetectOnly: stop pagination, keep items collected, leave delta unadvanced.
	monitor.DuplicatePageMode = graph.DuplicatePageDetectOnly
	deltaOpts := &graph.DeltaPaginateOptions{
		Monitor:           monitor,
		Outcome:           outcome,
		DuplicatePageMode: graph.DuplicatePageDetectOnly,
	}
	onPage := func(pageItems int) {
		reportSharePointDriveProgress(opts, pageItems)
	}
	items, deltaLink, err := paginateDeltaResilient(ctx, client,
		fmt.Sprintf("/drives/%s/root/delta", driveID),
		priorDelta,
		"id,name,size,file,folder,parentReference,lastModifiedDateTime",
		200, onPage, deltaOpts)
	if err != nil {
		return nil, err
	}
	if outcome.CapReached {
		res.stoppedIncomplete = true
		res.warnings = append(res.warnings, fmt.Sprintf("drive %s: delta pagination cap reached (%d pages, %d items)", driveID, outcome.Pages, outcome.TotalItems))
	}
	if outcome.StoppedOnDuplicatePage {
		res.stoppedIncomplete = true
		res.warnings = append(res.warnings, fmt.Sprintf("drive %s: Graph duplicate-only page (known defect); partial delta kept, token not advanced", driveID))
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
	if deltaLink != "" && !res.stoppedIncomplete {
		res.deltaLink = deltaLink
	}
	return res, nil
}

// maybeResyncSharePointDriveFull clears a poisoned incremental delta when Graph reports
// changed files but the overlay still has no drive baseline proof (metadata-only snapshots).
func maybeResyncSharePointDriveFull(
	ctx context.Context,
	client *graph.Client,
	opts SharePointSyncOptions,
	job sharePointDriveJob,
	res *sharePointDriveResult,
	mu *sync.Mutex,
) (*sharePointDriveResult, error) {
	if !shouldForceSharePointFullResync(opts, job.id, job.priorDelta, res) {
		return res, nil
	}
	if opts.Log != nil {
		opts.Log("warning", fmt.Sprintf("SharePoint drive %s: incremental without baseline proof; forcing full delta", job.id))
	}
	clearSharePointDriveStaging(opts, job.id)
	full, err := syncSharePointDrive(ctx, client, opts, job.id, "", mu)
	if err != nil {
		return nil, err
	}
	full.fullResync = true
	return full, nil
}

func needsSharePointPreflightRebaseline(opts SharePointSyncOptions, driveID, priorDelta string) bool {
	if strings.TrimSpace(priorDelta) == "" {
		return false
	}
	return !hasSharePointDriveBaselineProof(opts, driveID)
}

func shouldForceSharePointFullResync(opts SharePointSyncOptions, driveID, priorDelta string, res *sharePointDriveResult) bool {
	if strings.TrimSpace(priorDelta) == "" || res == nil {
		return false
	}
	return !hasSharePointDriveBaselineProof(opts, driveID)
}

func hasSharePointDriveBaselineProof(opts SharePointSyncOptions, driveID string) bool {
	if validSharePointCatalogMarker(opts, driveID) {
		return true
	}
	if opts.Staging == nil {
		return false
	}
	contentBase := siteDriveContentBase(opts.AzureTenantID, opts.SiteID, driveID)
	if !opts.Staging.HasPathPrefix(contentBase) {
		return false
	}
	// Sharded overlays may retain another shard's content tree from the prior manifest.
	if opts.Shard.Active() {
		return false
	}
	return true
}

func clearSharePointDriveStaging(opts SharePointSyncOptions, driveID string) {
	if opts.Staging == nil {
		return
	}
	opts.Staging.RemovePrefix(siteDriveContentBase(opts.AzureTenantID, opts.SiteID, driveID))
}

func sharePointCatalogMarkerPath(tenantID, siteID, driveID string) string {
	return siteDriveContentBase(tenantID, siteID, driveID) + "/.catalog"
}

func sharePointShardMarkerIdentity(shard ShardFilter) (index, total int) {
	if shard.Active() {
		return shard.Index, shard.Total
	}
	return 0, 1
}

func validSharePointCatalogMarker(opts SharePointSyncOptions, driveID string) bool {
	if opts.Staging == nil {
		return false
	}
	path := sharePointCatalogMarkerPath(opts.AzureTenantID, opts.SiteID, driveID)
	var marker struct {
		V          int `json:"v"`
		ShardIndex int `json:"shard_index"`
		ShardTotal int `json:"shard_total"`
	}
	if !opts.Staging.ReadJSON(path, &marker) {
		return false
	}
	if marker.V != 1 {
		return false
	}
	wantIndex, wantTotal := sharePointShardMarkerIdentity(opts.Shard)
	return marker.ShardIndex == wantIndex && marker.ShardTotal == wantTotal
}

func ensureSharePointCatalogMarker(opts SharePointSyncOptions, driveID string, res *sharePointDriveResult) {
	if res == nil || opts.Staging == nil {
		return
	}
	if !res.startedFull && !res.fullResync {
		return
	}
	if res.stoppedIncomplete {
		return
	}
	if strings.TrimSpace(res.deltaLink) == "" {
		return
	}
	if res.items > 0 {
		return
	}
	path := sharePointCatalogMarkerPath(opts.AzureTenantID, opts.SiteID, driveID)
	if validSharePointCatalogMarker(opts, driveID) {
		return
	}
	shardIndex, shardTotal := sharePointShardMarkerIdentity(opts.Shard)
	payload, _ := json.Marshal(map[string]any{
		"v":           1,
		"files":       0,
		"shard_index": shardIndex,
		"shard_total": shardTotal,
	})
	opts.Staging.PutJSON(path, payload, time.Now().UTC())
}

func reportSharePointDriveProgress(opts SharePointSyncOptions, itemsDone int) {
	if opts.OnProgress == nil || itemsDone < 0 {
		return
	}
	// items_total is unknown until the drive delta finishes; use done so the
	// graph_sync stall watchdog sees per-page movement during long pagination.
	opts.OnProgress(itemsDone, itemsDone, 0)
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
