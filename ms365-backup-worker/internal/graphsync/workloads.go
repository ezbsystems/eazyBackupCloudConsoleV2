package graphsync

import (
	"context"
	"fmt"
	"strings"
	"sync/atomic"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

// WorkloadRunner executes enabled workloads and builds a virtual overlay tree for Kopia.
type WorkloadRunner struct {
	Client           *graph.Client
	Job              *api.RunJob
	Parallel           int
	FolderParallel     int
	DriveParallel      int
	Overlay          *graphfs.OverlayBuilder
	UseBatchFallback bool
	OnProgress       func(phase string, itemsDone, itemsTotal int, bytesTotal int64)
	RunLog           RunLogger
}

type WorkloadResult struct {
	Stats       map[string]any
	DeltaStates map[string]map[string]string
	FileCount   int
	ItemsDone   int64
	BytesTotal  int64
}

func (w *WorkloadRunner) Run(ctx context.Context) (*WorkloadResult, error) {
	if w.Overlay == nil {
		w.Overlay = graphfs.NewOverlayBuilder()
	}
	stats := map[string]any{}
	deltaStates := map[string]map[string]string{}
	var itemsDone int64
	var bytesTotal int64

	_, shardKey := ParsePhysicalKey(w.Job.PhysicalKey)
	if w.Job.Shard != nil {
		switch w.Job.Shard.Kind {
		case "mail_folder":
			if w.Job.Shard.Segment != "" {
				shardKey = "mail:" + w.Job.Shard.Segment
			}
		default:
			if w.Job.Shard.Segment != "" {
				shardKey = w.Job.Shard.Segment
			}
		}
	}
	shardFilter := ShardFilterFromJob(shardKey, w.Job.Shard)

	progress := func(phase string, done, total int, bytes int64) {
		atomic.StoreInt64(&itemsDone, int64(done))
		atomic.StoreInt64(&bytesTotal, bytes)
		if w.OnProgress != nil {
			w.OnProgress(phase, done, total, bytes)
		}
	}

	if w.allowsWorkload("mail") && w.Job.GraphID != "" {
		mailStates := w.deltaForWorkload("mail")
		mailRes, err := SyncMail(ctx, w.Client, MailSyncOptions{
			AzureTenantID:    w.Job.AzureTenantID,
			UserID:           w.Job.GraphID,
			Parallel:         w.Parallel,
			FolderParallel:   w.FolderParallel,
			DeltaStates:      mailStates,
			Staging:          w.Overlay,
			UseBatchFallback: w.UseBatchFallback,
			ShardKey:         shardKey,
			Log:              w.RunLog,
			OnProgress:       func(d, t int, b int64) { progress("mail", d, t, b) },
		})
		if err != nil {
			return nil, fmt.Errorf("mail: %w", err)
		}
		stats["mail"] = mailRes.Stats
		deltaStates["mail"] = mailRes.DeltaStates
	}

	if w.allowsWorkload("contacts") && w.Job.GraphID != "" {
		cRes, err := SyncContacts(ctx, w.Client, ContactsSyncOptions{
			AzureTenantID: w.Job.AzureTenantID,
			UserID:        w.Job.GraphID,
			Parallel:      w.Parallel,
			Staging:       w.Overlay,
			DeltaStates:   w.deltaForWorkload("contacts"),
			Log:           w.RunLog,
		})
		if err != nil {
			return nil, fmt.Errorf("contacts: %w", err)
		}
		stats["contacts"] = cRes.Stats
		if len(cRes.DeltaStates) > 0 {
			deltaStates["contacts"] = cRes.DeltaStates
		}
	}

	if w.allowsWorkload("tasks") && w.Job.GraphID != "" {
		tRes, err := SyncTasks(ctx, w.Client, TasksSyncOptions{
			AzureTenantID: w.Job.AzureTenantID,
			UserID:        w.Job.GraphID,
			Parallel:      w.Parallel,
			Staging:       w.Overlay,
			DeltaStates:   w.deltaForWorkload("tasks"),
			Log:           w.RunLog,
		})
		if err != nil {
			return nil, fmt.Errorf("tasks: %w", err)
		}
		stats["tasks"] = tRes.Stats
		if len(tRes.DeltaStates) > 0 {
			deltaStates["tasks"] = tRes.DeltaStates
		}
	}

	if w.allowsWorkload("onedrive") {
		driveID := strings.TrimSpace(w.Job.DriveID)
		deltaLink := w.singleDeltaForWorkload("onedrive", shardKey)
		if alt := w.singleDeltaForWorkload("onedrive", "drive:"+driveID); deltaLink == "" {
			deltaLink = alt
		}
		odRes, err := SyncOneDrive(ctx, w.Client, OneDriveSyncOptions{
			AzureTenantID: w.Job.AzureTenantID,
			DriveID:       driveID,
			UserID:        w.Job.GraphID,
			Parallel:      w.Parallel,
			DeltaLink:     deltaLink,
			Overlay:       w.Overlay,
			ShardKey:      shardKey,
			Shard:         shardFilter,
			OnProgress:    func(d, t int, b int64) { progress("onedrive", d, t, b) },
		})
		if err != nil {
			return nil, fmt.Errorf("onedrive: %w", err)
		}
		stats["onedrive"] = odRes.Stats
		if len(odRes.DeltaStates) > 0 {
			deltaStates["onedrive"] = odRes.DeltaStates
		}
	}

	if w.allowsWorkload("sharepoint") {
		siteID := strings.TrimSpace(w.Job.SiteID)
		if siteID == "" {
			siteID = strings.TrimSpace(w.Job.GraphID)
		}
		driveID := strings.TrimSpace(w.Job.DriveID)
		spRes, err := SyncSharePoint(ctx, w.Client, SharePointSyncOptions{
			AzureTenantID: w.Job.AzureTenantID,
			SiteID:        siteID,
			DriveID:       driveID,
			Parallel:      w.Parallel,
			DriveParallel: w.DriveParallel,
			DeltaStates:   w.deltaForWorkload("sharepoint"),
			Shard:         shardFilter,
			Staging:       w.Overlay,
			Log:           w.RunLog,
			Job:           w.Job,
			OnProgress:    func(d, t int, b int64) { progress("sharepoint", d, t, b) },
		})
		if err != nil {
			return nil, fmt.Errorf("sharepoint: %w", err)
		}
		stats["sharepoint"] = spRes.Stats
		if len(spRes.Warnings) > 0 {
			stats["pagination_warnings"] = spRes.Warnings
		}
		if len(spRes.DeltaStates) > 0 {
			deltaStates["sharepoint"] = spRes.DeltaStates
		}
	}

	if w.allowsWorkload("sharepoint_lists") {
		siteID := w.Job.SiteID
		if siteID == "" {
			siteID = w.Job.GraphID
		}
		splRes, err := SyncSharePointLists(ctx, w.Client, SharePointListsSyncOptions{
			AzureTenantID:   w.Job.AzureTenantID,
			SiteID:          siteID,
			ListID:          w.Job.ListID,
			ExcludedListIDs: w.Job.ExcludedListIDs,
			Shard:           w.Job.Shard,
			Parallel:        w.Parallel,
			DeltaStates:     w.deltaForWorkload("sharepoint_lists"),
			Staging:         w.Overlay,
			Log:             w.RunLog,
			Job:             w.Job,
			OnProgress:      func(d, t int) { progress("sharepoint_lists", d, t, 0) },
		})
		if err != nil {
			return nil, fmt.Errorf("sharepoint_lists: %w", err)
		}
		stats["sharepoint_lists"] = splRes.Stats
		if len(splRes.Warnings) > 0 {
			stats["pagination_warnings"] = splRes.Warnings
		}
		if len(splRes.DeltaStates) > 0 {
			deltaStates["sharepoint_lists"] = splRes.DeltaStates
		}
	}

	if w.allowsWorkload("teams") {
		tmRes, err := SyncTeams(ctx, w.Client, TeamsSyncOptions{
			AzureTenantID: w.Job.AzureTenantID,
			TeamID:        w.Job.GraphID,
			Parallel:      w.Parallel,
			Staging:       w.Overlay,
			DeltaStates:   w.deltaForWorkload("teams"),
			Log:           w.RunLog,
		})
		if err != nil {
			return nil, fmt.Errorf("teams: %w", err)
		}
		stats["teams"] = tmRes.Stats
		if len(tmRes.DeltaStates) > 0 {
			deltaStates["teams"] = tmRes.DeltaStates
		}
	}

	if w.allowsWorkload("calendar") && w.Job.GraphID != "" {
		calRes, err := SyncCalendar(ctx, w.Client, CalendarSyncOptions{
			AzureTenantID: w.Job.AzureTenantID,
			UserID:        w.Job.GraphID,
			Parallel:      w.Parallel,
			Staging:       w.Overlay,
			DeltaStates:   w.deltaForWorkload("calendar"),
			Log:           w.RunLog,
		})
		if err != nil {
			return nil, fmt.Errorf("calendar: %w", err)
		}
		stats["calendar"] = calRes.Stats
		if len(calRes.DeltaStates) > 0 {
			deltaStates["calendar"] = calRes.DeltaStates
		}
	}

	if w.allowsWorkload("planner") {
		plRes, err := SyncPlanner(ctx, w.Client, PlannerSyncOptions{
			AzureTenantID: w.Job.AzureTenantID,
			PlanID:        w.Job.GraphID,
			Staging:       w.Overlay,
		})
		if err != nil {
			return nil, fmt.Errorf("planner: %w", err)
		}
		stats["planner"] = plRes.Stats
	}

	if w.allowsWorkload("onenote") {
		onRes, err := SyncOneNote(ctx, w.Client, OneNoteSyncOptions{
			AzureTenantID: w.Job.AzureTenantID,
			NotebookID:    w.Job.GraphID,
			Staging:       w.Overlay,
		})
		if err != nil {
			return nil, fmt.Errorf("onenote: %w", err)
		}
		stats["onenote"] = onRes.Stats
	}

	if w.allowsWorkload("directory") {
		dirRes, err := SyncDirectory(ctx, w.Client, DirectorySyncOptions{
			AzureTenantID: w.Job.AzureTenantID,
			Staging:       w.Overlay,
			DeltaStates:   w.deltaForWorkload("directory"),
			Log:           w.RunLog,
		})
		if err != nil {
			return nil, fmt.Errorf("directory: %w", err)
		}
		stats["directory"] = dirRes.Stats
		if len(dirRes.DeltaStates) > 0 {
			deltaStates["directory"] = dirRes.DeltaStates
		}
	}

	stats["graph_429_hits"] = w.Client.ThrottleHits()

	return &WorkloadResult{
		Stats:       stats,
		DeltaStates: deltaStates,
		FileCount:   w.Overlay.EntryCount(),
		ItemsDone:   atomic.LoadInt64(&itemsDone),
		BytesTotal:  atomic.LoadInt64(&bytesTotal),
	}, nil
}

func (w *WorkloadRunner) deltaForWorkload(workload string) map[string]string {
	if w.Job.DeltaStates == nil {
		return map[string]string{}
	}
	if states, ok := w.Job.DeltaStates[workload]; ok {
		return states
	}
	return map[string]string{}
}

func (w *WorkloadRunner) singleDeltaForWorkload(workload, shard string) string {
	states := w.deltaForWorkload(workload)
	if shard != "" {
		if link, ok := states[DeltaKeyForShard(shard)]; ok {
			return link
		}
	}
	if link, ok := states["root"]; ok {
		return link
	}
	return ""
}

func (w *WorkloadRunner) scopeFlag(key string, defaultWhenMissing bool) bool {
	if w.Job.Scope != nil {
		if v, ok := w.Job.Scope[key]; ok {
			return v
		}
	}
	return defaultWhenMissing
}

func (w *WorkloadRunner) enabled(name string) bool {
	if w.Job.Workloads == nil {
		return name == "mail"
	}
	if v, ok := w.Job.Workloads[name]; ok {
		return v
	}
	return false
}

func (w *WorkloadRunner) allowsWorkload(name string) bool {
	if !w.enabled(name) {
		return false
	}
	baseKey, _ := ParsePhysicalKey(w.Job.PhysicalKey)
	kind, _, _ := strings.Cut(baseKey, ":")
	switch name {
	case "mail", "calendar", "contacts", "tasks":
		if kind != "user" && kind != "mailbox" {
			return false
		}
		return w.scopeFlag(name, true)
	case "onedrive":
		if kind == "user" || kind == "mailbox" {
			return w.scopeFlag("onedrive", false) || w.scopeFlag("files", false)
		}
		if kind != "drive" && kind != "onedrive" {
			return false
		}
		return w.scopeFlag("onedrive", true) || w.scopeFlag("files", true)
	case "sharepoint":
		if kind != "site" {
			return false
		}
		return w.scopeFlag("files", true)
	case "sharepoint_lists":
		if kind != "site" && kind != "list" {
			return false
		}
		return w.scopeFlag("lists", true)
	case "teams":
		return kind == "team" || kind == "channel"
	case "planner":
		return kind == "planner"
	case "onenote":
		return kind == "onenote"
	case "directory":
		return strings.HasPrefix(w.Job.PhysicalKey, "directory:")
	default:
		return true
	}
}
