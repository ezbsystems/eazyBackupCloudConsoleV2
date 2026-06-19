package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type TeamsSyncOptions struct {
	AzureTenantID string
	TeamID        string
	Parallel      int
	Staging       *graphfs.OverlayBuilder
	DeltaStates   map[string]string
	Log           RunLogger
}

type TeamsSyncResult struct {
	Stats       map[string]int
	FileCount   int
	DeltaStates map[string]string
}

func SyncTeams(ctx context.Context, client *graph.Client, opts TeamsSyncOptions) (*TeamsSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("teams sync requires overlay builder")
	}
	if opts.Log != nil {
		opts.Log("info", fmt.Sprintf("Starting teams backup team=%s incremental=%v", opts.TeamID, len(opts.DeltaStates) > 0))
	}
	stats := map[string]int{"channels": 0, "messages": 0}
	deltaOut := map[string]string{}

	channels, err := client.Paginate(ctx, fmt.Sprintf("/teams/%s/channels", opts.TeamID), map[string]string{"$top": "50"})
	if err != nil {
		return nil, err
	}
	meta, _ := json.Marshal(map[string]any{"team_id": opts.TeamID, "channels": len(channels)})
	metaPath := fmt.Sprintf("%s/teams/%s/metadata.json", opts.AzureTenantID, safeID(opts.TeamID))
	opts.Staging.PutJSON(metaPath, meta, time.Now().UTC())
	for _, ch := range channels {
		chID, _ := ch["id"].(string)
		if chID == "" {
			continue
		}
		stats["channels"]++
		priorDelta := ""
		if opts.DeltaStates != nil {
			priorDelta = opts.DeltaStates[chID]
		}
		deltaPath := fmt.Sprintf("/teams/%s/channels/%s/messages/delta", opts.TeamID, chID)
		monitor := graph.ForBackupPagination("teams:"+chID, graphLog(opts.Log))
		items, deltaLink, err := paginateDeltaResilient(ctx, client, deltaPath, priorDelta, "", 50, nil, &graph.DeltaPaginateOptions{Monitor: monitor})
		if err != nil {
			return nil, err
		}
		if deltaLink != "" {
			deltaOut[chID] = deltaLink
		}
		for _, item := range items {
			if removed, _ := item["@removed"].(map[string]any); removed != nil {
				id, _ := item["id"].(string)
				path := fmt.Sprintf("%s/teams/%s/channels/%s/messages/%s.json", opts.AzureTenantID, safeID(opts.TeamID), safeID(chID), safeID(id))
				opts.Staging.Remove(path)
				continue
			}
			id, _ := item["id"].(string)
			if id == "" {
				continue
			}
			body, _ := json.Marshal(item)
			path := fmt.Sprintf("%s/teams/%s/channels/%s/messages/%s.json", opts.AzureTenantID, safeID(opts.TeamID), safeID(chID), safeID(id))
			opts.Staging.PutJSON(path, body, graphfsModTime(item["lastModifiedDateTime"]))
			stats["messages"]++
		}
	}
	return &TeamsSyncResult{Stats: stats, FileCount: opts.Staging.EntryCount(), DeltaStates: deltaOut}, nil
}
