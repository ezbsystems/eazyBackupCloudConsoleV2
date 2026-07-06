package graphsync

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type SharePointListsSyncOptions struct {
	AzureTenantID   string
	SiteID          string
	ListID          string
	ExcludedListIDs []string
	Shard           *api.ShardInfo
	Parallel        int
	DeltaStates     map[string]string
	Staging         *graphfs.OverlayBuilder
	OnProgress      func(itemsDone, itemsTotal int)
	Log             RunLogger
	Job             *api.RunJob
}

type SharePointListsSyncResult struct {
	Stats       map[string]int
	DeltaStates map[string]string
	FileCount   int
	ItemsDone   int
	Warnings    []string
}

type listPartitionState struct {
	Complete              bool   `json:"complete"`
	LastModifiedWatermark string `json:"last_modified_watermark,omitempty"`
	PartitionStart        string `json:"partition_start,omitempty"`
	PartitionEnd          string `json:"partition_end,omitempty"`
}

func SyncSharePointLists(ctx context.Context, client *graph.Client, opts SharePointListsSyncOptions) (*SharePointListsSyncResult, error) {
	if opts.Staging == nil {
		return nil, fmt.Errorf("sharepoint lists sync requires overlay builder")
	}
	if opts.SiteID == "" {
		return nil, fmt.Errorf("sharepoint lists sync requires site id")
	}

	excluded := excludedListSet(opts.ExcludedListIDs)
	siteBase := siteStoragePath(opts.AzureTenantID, opts.SiteID)

	if opts.ListID != "" {
		return syncSingleListJob(ctx, client, opts, siteBase, opts.ListID)
	}

	stats := map[string]int{"lists": 0, "items": 0, "removed": 0, "skipped_list_jobs": 0}
	deltaOut := map[string]string{}
	var warnings []string

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
	opts.Staging.PutJSON(siteBase+"/lists/lists.json", catalog, time.Now().UTC())
	stats["lists"] = len(lists)

	for _, list := range lists {
		listID, _ := list["id"].(string)
		if listID == "" || excluded[listID] {
			if listID != "" && excluded[listID] {
				stats["skipped_list_jobs"]++
			}
			continue
		}
		priorDelta := ""
		if opts.DeltaStates != nil {
			priorDelta = opts.DeltaStates[listID]
		}
		n, rm, warn, deltaLink, err := syncListDelta(ctx, client, opts, siteBase, listID, priorDelta)
		if err != nil {
			return nil, fmt.Errorf("list %s: %w", listID, err)
		}
		stats["items"] += n
		stats["removed"] += rm
		warnings = append(warnings, warn...)
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
		Warnings:    warnings,
	}, nil
}

func syncSingleListJob(ctx context.Context, client *graph.Client, opts SharePointListsSyncOptions, siteBase, listID string) (*SharePointListsSyncResult, error) {
	stats := map[string]int{"lists": 1, "items": 0, "removed": 0}
	deltaOut := map[string]string{}
	var warnings []string

	if opts.Shard != nil && opts.Shard.Kind == "list_created_range" && strings.TrimSpace(opts.Shard.Segment) != "" {
		start, end, ok := parseListShardSegment(opts.Shard.Segment)
		if !ok {
			return nil, fmt.Errorf("list %s: invalid shard segment %q", listID, opts.Shard.Segment)
		}
		stateKey := listShardStateKey(listID, opts.Shard.Index)
		prior := decodeListPartitionState(opts.DeltaStates[stateKey])
		n, rm, warn, state, err := syncListPartition(ctx, client, opts, siteBase, listID, start, end, prior)
		if err != nil {
			return nil, fmt.Errorf("list %s shard %d: %w", listID, opts.Shard.Index, err)
		}
		stats["items"] += n
		stats["removed"] += rm
		warnings = append(warnings, warn...)
		if encoded := encodeListPartitionState(state); encoded != "" {
			deltaOut[stateKey] = encoded
		}
	} else {
		priorDelta := ""
		if opts.DeltaStates != nil {
			priorDelta = opts.DeltaStates[listID]
		}
		n, rm, warn, deltaLink, err := syncListDelta(ctx, client, opts, siteBase, listID, priorDelta)
		if err != nil {
			return nil, fmt.Errorf("list %s: %w", listID, err)
		}
		stats["items"] += n
		stats["removed"] += rm
		warnings = append(warnings, warn...)
		if deltaLink != "" {
			deltaOut[listID] = deltaLink
		}
	}

	if opts.OnProgress != nil {
		opts.OnProgress(stats["items"], stats["items"])
	}

	return &SharePointListsSyncResult{
		Stats:       stats,
		DeltaStates: deltaOut,
		FileCount:   opts.Staging.EntryCount(),
		ItemsDone:   stats["items"],
		Warnings:    warnings,
	}, nil
}

func syncListDelta(
	ctx context.Context,
	client *graph.Client,
	opts SharePointListsSyncOptions,
	siteBase, listID, priorDelta string,
) (items, removed int, warnings []string, deltaLink string, err error) {
	deltaPath := fmt.Sprintf("/sites/%s/lists/%s/items/delta", opts.SiteID, listID)
	outcome := &graph.PaginationOutcome{}
	monitor := paginationMonitorForJob(opts.Job, "sharepoint_lists", "sharepoint_lists:"+listID, graphLog(opts.Log))
	deltaOpts := &graph.DeltaPaginateOptions{Monitor: monitor, Outcome: outcome, Expand: ListItemExpand}
	synced, newDelta, err := paginateDeltaResilient(ctx, client, deltaPath, priorDelta, ListItemSelect, 200, nil, deltaOpts)
	if err != nil {
		return 0, 0, nil, "", err
	}
	if outcome.CapReached {
		warnings = append(warnings, fmt.Sprintf("list %s: delta pagination cap reached (%d pages, %d items)", listID, outcome.Pages, outcome.TotalItems))
	}
	items, removed = storeListItems(opts, siteBase, listID, synced)
	return items, removed, warnings, newDelta, nil
}

func syncListPartition(
	ctx context.Context,
	client *graph.Client,
	opts SharePointListsSyncOptions,
	siteBase, listID, start, end string,
	prior listPartitionState,
) (items, removed int, warnings []string, state listPartitionState, err error) {
	state = prior
	state.PartitionStart = start
	state.PartitionEnd = end
	itemsPath := fmt.Sprintf("/sites/%s/lists/%s/items", opts.SiteID, listID)

	var synced []map[string]any
	if prior.Complete && prior.LastModifiedWatermark != "" {
		synced, err = paginateListItemsFiltered(ctx, client, opts, itemsPath, ListLastModifiedFilter(prior.LastModifiedWatermark, start, end))
	} else {
		synced, err = paginateListItemsFiltered(ctx, client, opts, itemsPath, ListCreatedDateTimeFilter(start, end))
	}
	if err != nil {
		return 0, 0, nil, state, err
	}

	items, removed = storeListItems(opts, siteBase, listID, synced)
	state.Complete = true
	state.LastModifiedWatermark = maxLastModified(state.LastModifiedWatermark, synced)
	return items, removed, warnings, state, nil
}

func paginateListItemsFiltered(
	ctx context.Context,
	client *graph.Client,
	opts SharePointListsSyncOptions,
	itemsPath, filter string,
) ([]map[string]any, error) {
	monitor := graph.ForCalendarPartitionScan("sharepoint_list_partition:"+truncateID(itemsPath), graphLog(opts.Log))
	outcome := &graph.PaginationOutcome{}
	query := map[string]string{
		"$filter":  filter,
		"$orderby": "createdDateTime",
		"$top":     ListPartitionPageSize,
		"$select":  ListItemSelect,
		"$expand":  ListItemExpand,
	}
	items, err := client.PaginateOpts(ctx, itemsPath, query, &graph.PaginateOptions{
		Monitor:     monitor,
		Outcome:     outcome,
		Headers:     ListPartitionHeaders(),
		TrackDupIDs: true,
	})
	if err != nil {
		if graphErr, ok := err.(*graph.GraphPaginationError); ok {
			return paginateListItemsClientFilter(ctx, client, opts, itemsPath, filter, graphErr)
		}
		if isGraphFilterUnsupported(err) {
			return paginateListItemsClientFilter(ctx, client, opts, itemsPath, filter, nil)
		}
		return nil, err
	}
	if !outcome.CompletedNaturally {
		return nil, &graph.GraphPaginationError{Message: "list partition scan did not complete naturally", Context: monitor.Context}
	}
	return items, nil
}

func paginateListItemsClientFilter(
	ctx context.Context,
	client *graph.Client,
	opts SharePointListsSyncOptions,
	itemsPath, serverFilter string,
	cause error,
) ([]map[string]any, error) {
	if opts.Log != nil && cause != nil {
		opts.Log("warning", fmt.Sprintf("list partition server filter failed, using client-side range filter: %v", cause))
	} else if opts.Log != nil {
		opts.Log("warning", "list partition $filter unsupported; using client-side range filter")
	}
	start, end := parseFilterCreatedRange(serverFilter)
	monitor := graph.ForCalendarPartitionScan("sharepoint_list_partition_fallback:"+truncateID(itemsPath), graphLog(opts.Log))
	query := map[string]string{
		"$orderby": "createdDateTime",
		"$top":     ListPartitionPageSize,
		"$select":  ListItemSelect,
		"$expand":  ListItemExpand,
	}
	all, err := client.PaginateOpts(ctx, itemsPath, query, &graph.PaginateOptions{
		Monitor:     monitor,
		Outcome:     &graph.PaginationOutcome{},
		Headers:     ListPartitionHeaders(),
		TrackDupIDs: true,
	})
	if err != nil {
		return nil, err
	}
	if start == "" && end == "" {
		return all, nil
	}
	var filtered []map[string]any
	for _, item := range all {
		if itemInCreatedRange(item, start, end) {
			filtered = append(filtered, item)
		}
	}
	return filtered, nil
}

func storeListItems(opts SharePointListsSyncOptions, siteBase, listID string, items []map[string]any) (stored, removed int) {
	for _, item := range items {
		if removedObj, _ := item["@removed"].(map[string]any); removedObj != nil {
			id, _ := item["id"].(string)
			if id == "" {
				continue
			}
			path := fmt.Sprintf("%s/lists/%s/items/%s.removed.json", siteBase, storageSafeID(listID), storageSafeID(id))
			body, _ := json.Marshal(map[string]any{
				"@removed":  removedObj,
				"id":        id,
				"removedAt": time.Now().UTC().Format(time.RFC3339),
			})
			opts.Staging.PutJSON(path, body, time.Now().UTC())
			removed++
			continue
		}
		id, _ := item["id"].(string)
		if id == "" {
			continue
		}
		body, _ := json.Marshal(item)
		path := fmt.Sprintf("%s/lists/%s/items/%s.json", siteBase, storageSafeID(listID), storageSafeID(id))
		opts.Staging.PutJSON(path, body, graphfsModTime(item["lastModifiedDateTime"]))
		stored++
	}
	return stored, removed
}

func excludedListSet(ids []string) map[string]bool {
	out := map[string]bool{}
	for _, id := range ids {
		id = strings.TrimSpace(id)
		if id != "" {
			out[id] = true
		}
	}
	return out
}

func listShardStateKey(listID string, shardIndex int) string {
	return fmt.Sprintf("%s#shard:%d", listID, shardIndex)
}

func parseListShardSegment(segment string) (start, end string, ok bool) {
	segment = strings.TrimSpace(segment)
	parts := strings.SplitN(segment, "/", 2)
	if len(parts) != 2 {
		return "", "", false
	}
	start = strings.TrimSpace(parts[0])
	end = strings.TrimSpace(parts[1])
	return start, end, start != "" && end != ""
}

func decodeListPartitionState(raw string) listPartitionState {
	raw = strings.TrimSpace(raw)
	if raw == "" || strings.HasPrefix(raw, "http") {
		return listPartitionState{}
	}
	var state listPartitionState
	if err := json.Unmarshal([]byte(raw), &state); err != nil {
		return listPartitionState{}
	}
	return state
}

func encodeListPartitionState(state listPartitionState) string {
	if !state.Complete && state.LastModifiedWatermark == "" {
		return ""
	}
	raw, err := json.Marshal(state)
	if err != nil {
		return ""
	}
	return string(raw)
}

func parseFilterCreatedRange(filter string) (start, end string) {
	filter = strings.TrimSpace(filter)
	for _, part := range strings.Split(filter, " and ") {
		part = strings.TrimSpace(part)
		if strings.HasPrefix(part, "createdDateTime ge ") {
			start = strings.TrimSpace(strings.TrimPrefix(part, "createdDateTime ge "))
		}
		if strings.HasPrefix(part, "createdDateTime lt ") {
			end = strings.TrimSpace(strings.TrimPrefix(part, "createdDateTime lt "))
		}
	}
	return start, end
}

func itemInCreatedRange(item map[string]any, start, end string) bool {
	created, _ := item["createdDateTime"].(string)
	if created == "" {
		return false
	}
	if start != "" && created < start {
		return false
	}
	if end != "" && created >= end {
		return false
	}
	return true
}

func isGraphFilterUnsupported(err error) bool {
	if err == nil {
		return false
	}
	msg := strings.ToLower(err.Error())
	return strings.Contains(msg, "graph 400") ||
		strings.Contains(msg, "not supported") ||
		strings.Contains(msg, "operator") ||
		strings.Contains(msg, "filter")
}
