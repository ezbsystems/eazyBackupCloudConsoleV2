package graphsync

import (
	"context"
	"encoding/json"
	"fmt"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type TasksSyncOptions struct {
	AzureTenantID string
	UserID        string
	Parallel      int
	Staging       *graphfs.OverlayBuilder
}

type TasksSyncResult struct {
	Stats     map[string]int
	FileCount int
}

func SyncTasks(ctx context.Context, client *graph.Client, opts TasksSyncOptions) (*TasksSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("tasks sync requires overlay builder")
	}
	stats := map[string]int{}
	lists, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/todo/lists", opts.UserID), map[string]string{"$top": "100"})
	if err != nil {
		return nil, err
	}
	for _, list := range lists {
		listID, _ := list["id"].(string)
		if listID == "" {
			continue
		}
		items, _, err := client.PaginateDelta(ctx, fmt.Sprintf("/users/%s/todo/lists/%s/tasks/delta", opts.UserID, listID), "", "", 100)
		if err != nil {
			return nil, err
		}
		for _, item := range items {
			if removed, _ := item["@removed"].(map[string]any); removed != nil {
				id, _ := item["id"].(string)
				path := fmt.Sprintf("%s/users/%s/tasks/%s/%s.json", opts.AzureTenantID, opts.UserID, safeID(listID), safeID(id))
				opts.Staging.Remove(path)
				continue
			}
			id, _ := item["id"].(string)
			if id == "" {
				continue
			}
			body, _ := json.Marshal(item)
			path := fmt.Sprintf("%s/users/%s/tasks/%s/%s.json", opts.AzureTenantID, opts.UserID, safeID(listID), safeID(id))
			opts.Staging.PutJSON(path, body, graphfsModTime(item["lastModifiedDateTime"]))
			stats["tasks"]++
		}
	}
	return &TasksSyncResult{Stats: stats, FileCount: opts.Staging.EntryCount()}, nil
}
