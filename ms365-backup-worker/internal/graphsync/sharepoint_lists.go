package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type SharePointListsSyncOptions struct {
	AzureTenantID string
	SiteID        string
	Parallel      int
	DeltaStates   map[string]string
	Staging       *graphfs.OverlayBuilder
	OnProgress    func(itemsDone, itemsTotal int)
}

type SharePointListsSyncResult struct {
	Stats       map[string]int
	DeltaStates map[string]string
	FileCount   int
	ItemsDone   int
}

func SyncSharePointLists(ctx context.Context, client *graph.Client, opts SharePointListsSyncOptions) (*SharePointListsSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("sharepoint lists sync requires overlay builder")
	}
	stats := map[string]int{"lists": 0, "items": 0, "removed": 0}
	deltaOut := map[string]string{}

	lists, err := client.Paginate(ctx, fmt.Sprintf("/sites/%s/lists", opts.SiteID), map[string]string{
		"$select": "id,displayName,list,webUrl",
		"$top":    "100",
	})
	if err != nil {
		return nil, err
	}

	catalog, _ := json.Marshal(map[string]any{
		"fetched_at": time.Now().UTC().Format(time.RFC3339),
		"value":      lists,
	})
	siteBase := siteStoragePath(opts.AzureTenantID, opts.SiteID)
	opts.Staging.PutJSON(siteBase+"/lists/lists.json", catalog, time.Now().UTC())
	stats["lists"] = len(lists)

	itemSelect := "id,fields,createdDateTime,lastModifiedDateTime,contentType"
	for _, list := range lists {
		listID, _ := list["id"].(string)
		if listID == "" {
			continue
		}
		priorDelta := ""
		if opts.DeltaStates != nil {
			priorDelta = opts.DeltaStates[listID]
		}
		deltaPath := fmt.Sprintf("/sites/%s/lists/%s/items/delta", opts.SiteID, listID)
		items, deltaLink, err := client.PaginateDelta(ctx, deltaPath, priorDelta, itemSelect, 200)
		if err != nil {
			return nil, fmt.Errorf("list %s: %w", listID, err)
		}
		for _, item := range items {
			if removed, _ := item["@removed"].(map[string]any); removed != nil {
				id, _ := item["id"].(string)
				if id == "" {
					continue
				}
				path := fmt.Sprintf("%s/lists/%s/items/%s.removed.json", siteBase, storageSafeID(listID), storageSafeID(id))
				body, _ := json.Marshal(map[string]any{
					"@removed":  removed,
					"id":        id,
					"removedAt": time.Now().UTC().Format(time.RFC3339),
				})
				opts.Staging.PutJSON(path, body, time.Now().UTC())
				stats["removed"]++
				continue
			}
			id, _ := item["id"].(string)
			if id == "" {
				continue
			}
			body, _ := json.Marshal(item)
			path := fmt.Sprintf("%s/lists/%s/items/%s.json", siteBase, storageSafeID(listID), storageSafeID(id))
			opts.Staging.PutJSON(path, body, graphfsModTime(item["lastModifiedDateTime"]))
			stats["items"]++
		}
		if deltaLink != "" {
			deltaOut[listID] = deltaLink
		}
		if opts.OnProgress != nil {
			opts.OnProgress(stats["items"], stats["items"])
		}
	}

	return &SharePointListsSyncResult{
		Stats:       stats,
		DeltaStates: deltaOut,
		FileCount:   opts.Staging.EntryCount(),
		ItemsDone:   stats["items"],
	}, nil
}
