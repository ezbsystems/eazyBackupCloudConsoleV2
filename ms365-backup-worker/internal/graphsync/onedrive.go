package graphsync

import (
	"context"
	"fmt"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type OneDriveSyncOptions struct {
	AzureTenantID string
	DriveID       string
	Parallel      int
	DeltaLink     string
	Overlay       *graphfs.OverlayBuilder
	ShardKey      string
	OnProgress    func(itemsDone, itemsTotal int, bytesEstimate int64)
}

type OneDriveSyncResult struct {
	Stats       map[string]int
	DeltaLink   string
	DeltaStates map[string]string
	FileCount   int
	ItemsDone   int
	BytesTotal  int64
}

// SyncOneDrive applies drive delta to the overlay tree; file content streams on snapshot Open().
func SyncOneDrive(ctx context.Context, client *graph.Client, opts OneDriveSyncOptions) (*OneDriveSyncResult, error) {
	if opts.Overlay == nil {
		return nil, fmt.Errorf("onedrive sync requires overlay builder")
	}
	stats := map[string]int{"items": 0}
	shard := opts.ShardKey

	items, deltaLink, err := client.PaginateDelta(ctx, fmt.Sprintf("/drives/%s/root/delta", opts.DriveID), opts.DeltaLink, "id,name,size,file,folder,parentReference,lastModifiedDateTime,webUrl", 200)
	if err != nil {
		return nil, err
	}

	var bytesTotal int64
	for i, item := range items {
		if removed, _ := item["@removed"].(map[string]any); removed != nil {
			id, _ := item["id"].(string)
			if id == "" {
				continue
			}
			opts.Overlay.RemoveByItemID(id)
			legacyMeta := fmt.Sprintf("%s/drives/%s/items/%s.json", opts.AzureTenantID, safeID(opts.DriveID), safeID(id))
			opts.Overlay.Remove(legacyMeta)
			stats["removed"]++
			continue
		}
		id, _ := item["id"].(string)
		if id == "" {
			continue
		}
		if isDriveFolder(item) {
			stats["folders"]++
			continue
		}
		path := driveContentPath(opts.AzureTenantID, opts.DriveID, item)
		gf, err := graphfs.NewGraphFileFromDriveItem(client, opts.DriveID, item)
		if err != nil {
			return nil, err
		}
		opts.Overlay.PutWithItemID(id, path, gf)
		bytesTotal += gf.Size()
		stats["items"]++
		if opts.OnProgress != nil && (i+1)%50 == 0 {
			opts.OnProgress(i+1, len(items), bytesTotal)
		}
	}
	if opts.OnProgress != nil {
		opts.OnProgress(len(items), len(items), bytesTotal)
	}

	deltaStates := map[string]string{}
	if deltaLink != "" {
		deltaStates[DeltaKeyForShard(shard)] = deltaLink
	}

	return &OneDriveSyncResult{
		Stats:       stats,
		DeltaLink:   deltaLink,
		DeltaStates: deltaStates,
		FileCount:   opts.Overlay.EntryCount(),
		ItemsDone:   stats["items"],
		BytesTotal:  bytesTotal,
	}, nil
}
