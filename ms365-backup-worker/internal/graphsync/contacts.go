package graphsync

import (
	"context"
	"encoding/json"
	"fmt"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type ContactsSyncOptions struct {
	AzureTenantID string
	UserID        string
	Parallel      int
	Staging       *graphfs.OverlayBuilder
}

type ContactsSyncResult struct {
	Stats     map[string]int
	FileCount int
}

func SyncContacts(ctx context.Context, client *graph.Client, opts ContactsSyncOptions) (*ContactsSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("contacts sync requires overlay builder")
	}
	stats := map[string]int{}
	folders, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/contactFolders", opts.UserID), map[string]string{"$top": "100"})
	if err != nil {
		return nil, err
	}
	for _, folder := range folders {
		folderID, _ := folder["id"].(string)
		if folderID == "" {
			continue
		}
		items, _, err := client.PaginateDelta(ctx, fmt.Sprintf("/users/%s/contactFolders/%s/contacts/delta", opts.UserID, folderID), "", "", 100)
		if err != nil {
			return nil, err
		}
		for _, item := range items {
			if removed, _ := item["@removed"].(map[string]any); removed != nil {
				id, _ := item["id"].(string)
				path := fmt.Sprintf("%s/users/%s/contacts/%s/%s.json", opts.AzureTenantID, opts.UserID, safeID(folderID), safeID(id))
				opts.Staging.Remove(path)
				continue
			}
			id, _ := item["id"].(string)
			if id == "" {
				continue
			}
			body, _ := json.Marshal(item)
			path := fmt.Sprintf("%s/users/%s/contacts/%s/%s.json", opts.AzureTenantID, opts.UserID, safeID(folderID), safeID(id))
			opts.Staging.PutJSON(path, body, graphfsModTime(item["lastModifiedDateTime"]))
			stats["contacts"]++
		}
	}
	return &ContactsSyncResult{Stats: stats, FileCount: opts.Staging.EntryCount()}, nil
}
