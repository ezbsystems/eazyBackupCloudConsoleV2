package graphsync

import (
	"context"
	"fmt"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type SharePointSyncOptions struct {
	AzureTenantID string
	SiteID        string
	Parallel      int
	Staging       *graphfs.OverlayBuilder
	OnProgress    func(itemsDone, itemsTotal int, bytesEstimate int64)
}

type SharePointSyncResult struct {
	Stats      map[string]int
	FileCount  int
	ItemsDone  int
	BytesTotal int64
}

func SyncSharePoint(ctx context.Context, client *graph.Client, opts SharePointSyncOptions) (*SharePointSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("sharepoint sync requires overlay builder")
	}
	stats := map[string]int{"drives": 0, "items": 0}
	var bytesTotal int64
	drives, err := client.Paginate(ctx, fmt.Sprintf("/sites/%s/drives", opts.SiteID), map[string]string{"$top": "50"})
	if err != nil {
		return nil, err
	}
	for _, drive := range drives {
		driveID, _ := drive["id"].(string)
		if driveID == "" {
			continue
		}
		stats["drives"]++
		items, _, err := client.PaginateDelta(ctx, fmt.Sprintf("/drives/%s/root/delta", driveID), "", "id,name,size,file,folder,parentReference,lastModifiedDateTime", 200)
		if err != nil {
			return nil, err
		}
		for _, item := range items {
		if removed, _ := item["@removed"].(map[string]any); removed != nil {
			id, _ := item["id"].(string)
			opts.Staging.RemoveByItemID(id)
			continue
		}
			id, _ := item["id"].(string)
			if id == "" || isDriveFolder(item) {
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
	}
	return &SharePointSyncResult{Stats: stats, FileCount: opts.Staging.EntryCount(), ItemsDone: stats["items"], BytesTotal: bytesTotal}, nil
}
