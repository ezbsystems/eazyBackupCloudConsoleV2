package graphsync

import (
	"context"
	"encoding/json"
	"fmt"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type DirectorySyncOptions struct {
	AzureTenantID string
	Staging       *graphfs.OverlayBuilder
}

type DirectorySyncResult struct {
	Stats     map[string]int
	FileCount int
}

func SyncDirectory(ctx context.Context, client *graph.Client, opts DirectorySyncOptions) (*DirectorySyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("directory sync requires overlay builder")
	}
	stats := map[string]int{"users": 0, "groups": 0}
	users, err := client.Paginate(ctx, "/users", map[string]string{"$top": "100", "$select": "id,displayName,userPrincipalName,mail"})
	if err != nil {
		return nil, err
	}
	for _, u := range users {
		id, _ := u["id"].(string)
		if id == "" {
			continue
		}
		body, _ := json.Marshal(u)
		opts.Staging.PutJSON(fmt.Sprintf("%s/directory/users/%s.json", opts.AzureTenantID, safeID(id)), body, graphfsModTime(u["lastModifiedDateTime"]))
		stats["users"]++
	}
	groups, err := client.Paginate(ctx, "/groups", map[string]string{"$top": "100", "$select": "id,displayName,mail"})
	if err != nil {
		return nil, err
	}
	for _, g := range groups {
		id, _ := g["id"].(string)
		if id == "" {
			continue
		}
		body, _ := json.Marshal(g)
		opts.Staging.PutJSON(fmt.Sprintf("%s/directory/groups/%s.json", opts.AzureTenantID, safeID(id)), body, graphfsModTime(g["lastModifiedDateTime"]))
		stats["groups"]++
	}
	return &DirectorySyncResult{Stats: stats, FileCount: opts.Staging.EntryCount()}, nil
}
