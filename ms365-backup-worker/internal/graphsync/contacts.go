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
	DeltaStates   map[string]string
	Log           RunLogger
}

type ContactsSyncResult struct {
	Stats       map[string]int
	FileCount   int
	DeltaStates map[string]string
}

func SyncContacts(ctx context.Context, client *graph.Client, opts ContactsSyncOptions) (*ContactsSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("contacts sync requires overlay builder")
	}
	if opts.Log != nil {
		opts.Log("info", fmt.Sprintf("Starting contacts backup user=%s incremental=%v", opts.UserID, len(opts.DeltaStates) > 0))
	}
	stats := map[string]int{}
	deltaOut := map[string]string{}

	folders, err := client.Paginate(ctx, fmt.Sprintf("/users/%s/contactFolders", opts.UserID), map[string]string{"$top": "100"})
	if err != nil {
		return nil, err
	}
	for _, folder := range folders {
		folderID, _ := folder["id"].(string)
		if folderID == "" {
			continue
		}
		priorDelta := ""
		if opts.DeltaStates != nil {
			priorDelta = opts.DeltaStates[folderID]
		}
		deltaPath := fmt.Sprintf("/users/%s/contactFolders/%s/contacts/delta", opts.UserID, folderID)
		folderMonitor := graph.ForBackupPagination("contacts:"+folderID, graphLog(opts.Log))
		items, deltaLink, err := paginateDeltaResilient(ctx, client, deltaPath, priorDelta, "", 100, nil, &graph.DeltaPaginateOptions{Monitor: folderMonitor})
		if err != nil {
			return nil, err
		}
		if deltaLink != "" {
			deltaOut[folderID] = deltaLink
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
	return &ContactsSyncResult{Stats: stats, FileCount: opts.Staging.EntryCount(), DeltaStates: deltaOut}, nil
}

func graphLog(log RunLogger) graph.PageLogFunc {
	if log == nil {
		return nil
	}
	return func(level, message string) {
		log(level, message)
	}
}
