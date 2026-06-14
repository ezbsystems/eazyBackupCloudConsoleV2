package graphsync

import (
	"context"
	"encoding/json"
	"fmt"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type PlannerSyncOptions struct {
	AzureTenantID string
	PlanID        string
	Staging       *graphfs.OverlayBuilder
}

type PlannerSyncResult struct {
	Stats     map[string]int
	FileCount int
}

func SyncPlanner(ctx context.Context, client *graph.Client, opts PlannerSyncOptions) (*PlannerSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("planner sync requires overlay builder")
	}
	stats := map[string]int{"tasks": 0}
	buckets, err := client.Paginate(ctx, fmt.Sprintf("/planner/plans/%s/buckets", opts.PlanID), map[string]string{"$top": "100"})
	if err != nil {
		return nil, err
	}
	for _, bucket := range buckets {
		bucketID, _ := bucket["id"].(string)
		if bucketID == "" {
			continue
		}
		tasks, err := client.Paginate(ctx, fmt.Sprintf("/planner/buckets/%s/tasks", bucketID), map[string]string{"$top": "100"})
		if err != nil {
			return nil, err
		}
		for _, task := range tasks {
			id, _ := task["id"].(string)
			if id == "" {
				continue
			}
			body, _ := json.Marshal(task)
			path := fmt.Sprintf("%s/planner/%s/buckets/%s/%s.json", opts.AzureTenantID, safeID(opts.PlanID), safeID(bucketID), safeID(id))
			opts.Staging.PutJSON(path, body, graphfsModTime(task["lastModifiedDateTime"]))
			stats["tasks"]++
		}
	}
	return &PlannerSyncResult{Stats: stats, FileCount: opts.Staging.EntryCount()}, nil
}
