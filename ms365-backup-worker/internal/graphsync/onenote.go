package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type OneNoteSyncOptions struct {
	AzureTenantID string
	NotebookID    string
	Staging       *graphfs.OverlayBuilder
}

type OneNoteSyncResult struct {
	Stats     map[string]int
	FileCount int
}

func SyncOneNote(ctx context.Context, client *graph.Client, opts OneNoteSyncOptions) (*OneNoteSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("onenote sync requires overlay builder")
	}
	stats := map[string]int{"sections": 0, "pages": 0}
	notebookBase := fmt.Sprintf("%s/onenote/%s", opts.AzureTenantID, safeID(opts.NotebookID))
	if notebook, err := client.GetJSON(ctx, fmt.Sprintf("/onenote/notebooks/%s", opts.NotebookID), map[string]string{
		"$select": "id,displayName",
	}); err == nil {
		nbSidecar, _ := json.Marshal(map[string]any{
			"id":          stringFromAny(notebook["id"]),
			"displayName": stringFromAny(notebook["displayName"]),
		})
		opts.Staging.PutJSON(notebookBase+"/_notebook.json", nbSidecar, time.Now().UTC())
	}
	sections, err := client.Paginate(ctx, fmt.Sprintf("/me/onenote/notebooks/%s/sections", opts.NotebookID), map[string]string{"$top": "100"})
	if err != nil {
		sections, err = client.Paginate(ctx, fmt.Sprintf("/onenote/notebooks/%s/sections", opts.NotebookID), map[string]string{"$top": "100"})
		if err != nil {
			return nil, err
		}
	}
	for _, sec := range sections {
		secID, _ := sec["id"].(string)
		if secID == "" {
			continue
		}
		secSidecar, _ := json.Marshal(map[string]any{
			"id":          secID,
			"displayName": stringFromAny(sec["displayName"]),
		})
		secMetaPath := fmt.Sprintf("%s/sections/%s/_section.json", notebookBase, safeID(secID))
		opts.Staging.PutJSON(secMetaPath, secSidecar, time.Now().UTC())
		stats["sections"]++
		pages, err := client.Paginate(ctx, fmt.Sprintf("/onenote/sections/%s/pages", secID), map[string]string{"$top": "100"})
		if err != nil {
			return nil, err
		}
		for _, page := range pages {
			id, _ := page["id"].(string)
			if id == "" {
				continue
			}
			body, _ := json.Marshal(page)
			path := fmt.Sprintf("%s/onenote/%s/sections/%s/%s.json", opts.AzureTenantID, safeID(opts.NotebookID), safeID(secID), safeID(id))
			opts.Staging.PutJSON(path, body, graphfsModTime(page["lastModifiedDateTime"]))
			stats["pages"]++
		}
	}
	return &OneNoteSyncResult{Stats: stats, FileCount: opts.Staging.EntryCount()}, nil
}
