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
	DeltaStates   map[string]string
	Log           RunLogger
}

type DirectorySyncResult struct {
	Stats       map[string]int
	FileCount   int
	DeltaStates map[string]string
}

const (
	directoryUsersKey  = "users"
	directoryGroupsKey = "groups"
)

func SyncDirectory(ctx context.Context, client *graph.Client, opts DirectorySyncOptions) (*DirectorySyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("directory sync requires overlay builder")
	}
	if opts.Log != nil {
		opts.Log("info", fmt.Sprintf("Starting directory backup incremental=%v", len(opts.DeltaStates) > 0))
	}
	stats := map[string]int{"users": 0, "groups": 0}
	deltaOut := map[string]string{}

	userPrior := ""
	groupPrior := ""
	if opts.DeltaStates != nil {
		userPrior = opts.DeltaStates[directoryUsersKey]
		groupPrior = opts.DeltaStates[directoryGroupsKey]
	}

	userMonitor := graph.ForBackupPagination("directory:users", graphLog(opts.Log))
	users, userDelta, err := paginateDeltaResilient(ctx, client, "/users/delta", userPrior, "id,displayName,userPrincipalName,mail,lastModifiedDateTime", 100, nil, &graph.DeltaPaginateOptions{Monitor: userMonitor})
	if err != nil {
		return nil, fmt.Errorf("users delta: %w", err)
	}
	if userDelta != "" {
		deltaOut[directoryUsersKey] = userDelta
	}
	for _, u := range users {
		if removed, _ := u["@removed"].(map[string]any); removed != nil {
			id, _ := u["id"].(string)
			if id != "" {
				opts.Staging.Remove(fmt.Sprintf("%s/directory/users/%s.json", opts.AzureTenantID, safeID(id)))
			}
			continue
		}
		id, _ := u["id"].(string)
		if id == "" {
			continue
		}
		body, _ := json.Marshal(u)
		opts.Staging.PutJSON(fmt.Sprintf("%s/directory/users/%s.json", opts.AzureTenantID, safeID(id)), body, graphfsModTime(u["lastModifiedDateTime"]))
		stats["users"]++
	}

	groupMonitor := graph.ForBackupPagination("directory:groups", graphLog(opts.Log))
	groups, groupDelta, err := paginateDeltaResilient(ctx, client, "/groups/delta", groupPrior, "id,displayName,mail,lastModifiedDateTime", 100, nil, &graph.DeltaPaginateOptions{Monitor: groupMonitor})
	if err != nil {
		return nil, fmt.Errorf("groups delta: %w", err)
	}
	if groupDelta != "" {
		deltaOut[directoryGroupsKey] = groupDelta
	}
	for _, g := range groups {
		if removed, _ := g["@removed"].(map[string]any); removed != nil {
			id, _ := g["id"].(string)
			if id != "" {
				opts.Staging.Remove(fmt.Sprintf("%s/directory/groups/%s.json", opts.AzureTenantID, safeID(id)))
			}
			continue
		}
		id, _ := g["id"].(string)
		if id == "" {
			continue
		}
		body, _ := json.Marshal(g)
		opts.Staging.PutJSON(fmt.Sprintf("%s/directory/groups/%s.json", opts.AzureTenantID, safeID(id)), body, graphfsModTime(g["lastModifiedDateTime"]))
		stats["groups"]++
	}

	return &DirectorySyncResult{Stats: stats, FileCount: opts.Staging.EntryCount(), DeltaStates: deltaOut}, nil
}
