package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type SharePointSyncOptions struct {
	AzureTenantID string
	SiteID        string
	Parallel      int
	DeltaStates   map[string]string
	Shard         ShardFilter
	Staging       *graphfs.OverlayBuilder
	OnProgress    func(itemsDone, itemsTotal int, bytesEstimate int64)
}

type SharePointSyncResult struct {
	Stats       map[string]int
	DeltaStates map[string]string
	FileCount   int
	ItemsDone   int
	BytesTotal  int64
}

func SyncSharePoint(ctx context.Context, client *graph.Client, opts SharePointSyncOptions) (*SharePointSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("sharepoint sync requires overlay builder")
	}
	stats := map[string]int{"drives": 0, "items": 0, "removed": 0, "skipped_shard": 0}
	deltaOut := map[string]string{}
	var bytesTotal int64

	drives, err := client.Paginate(ctx, fmt.Sprintf("/sites/%s/drives", opts.SiteID), map[string]string{"$top": "50"})
	if err != nil {
		return nil, err
	}

	catalog, _ := json.Marshal(map[string]any{
		"fetched_at": time.Now().UTC().Format(time.RFC3339),
		"value":      drives,
	})
	siteBase := siteStoragePath(opts.AzureTenantID, opts.SiteID)
	opts.Staging.PutJSON(siteBase+"/drives.json", catalog, time.Now().UTC())

	for _, drive := range drives {
		driveID, _ := drive["id"].(string)
		if driveID == "" {
			continue
		}
		stats["drives"]++
		priorDelta := ""
		if opts.DeltaStates != nil {
			priorDelta = opts.DeltaStates[driveID]
		}
		items, deltaLink, err := client.PaginateDelta(ctx, fmt.Sprintf("/drives/%s/root/delta", driveID), priorDelta, "id,name,size,file,folder,parentReference,lastModifiedDateTime", 200)
		if err != nil {
			return nil, err
		}
		for _, item := range items {
			if removed, _ := item["@removed"].(map[string]any); removed != nil {
				id, _ := item["id"].(string)
				if id == "" {
					continue
				}
				opts.Staging.RemoveByItemID(id)
				stats["removed"]++
				continue
			}
			id, _ := item["id"].(string)
			if id == "" || isDriveFolder(item) {
				continue
			}
			if !opts.Shard.IncludesItem(id) {
				stats["skipped_shard"]++
				continue
			}
			path := driveContentPath(opts.AzureTenantID, driveID, item)
			gf, err := graphfs.NewGraphFileFromDriveItem(client, driveID, item)
			if err != nil {
				return nil, err
			}
			opts.Staging.PutWithItemID(id, path, gf)
			bytesTotal += gf.Size()
			stats["items"]++
		}
		if deltaLink != "" {
			deltaOut[driveID] = deltaLink
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
	}, nil
}
