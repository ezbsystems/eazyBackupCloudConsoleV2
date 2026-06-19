package graphsync

import (
	"context"
	"fmt"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
	"github.com/eazybackup/ms365-backup-worker/internal/graphfs"
)

type OneDriveSyncOptions struct {
	AzureTenantID string
	UserID        string
	DriveID       string
	Parallel      int
	DeltaLink     string
	Overlay       *graphfs.OverlayBuilder
	ShardKey      string
	Shard         ShardFilter
	OnProgress    func(itemsDone, itemsTotal int, bytesEstimate int64)
}

type OneDriveSyncResult struct {
	Stats       map[string]int
	DeltaLink   string
	DeltaStates map[string]string
	FileCount   int
	ItemsDone   int
	BytesTotal  int64
}

// SyncOneDrive applies drive delta to the overlay tree; file content streams on snapshot Open().
func SyncOneDrive(ctx context.Context, client *graph.Client, opts OneDriveSyncOptions) (*OneDriveSyncResult, error) {
	if opts.Overlay == nil {
		return nil, fmt.Errorf("onedrive sync requires overlay builder")
	}
	driveID, err := resolveOneDriveID(ctx, client, opts)
	if err != nil {
		return nil, err
	}

	res, err := syncOneDriveDelta(ctx, client, opts, driveID)
	if err != nil {
		return nil, err
	}
	if shouldForceOneDriveFullResync(opts, driveID, res) {
		legacyBase := fmt.Sprintf("%s/drives/%s/content", opts.AzureTenantID, safeID(driveID))
		opts.Overlay.RemovePrefix(legacyBase)
		if misrouted := oneDriveMisroutedDrivesPrefix(opts.AzureTenantID, opts.UserID); misrouted != "" {
			opts.Overlay.RemovePrefix(misrouted)
		}

		fullOpts := opts
		fullOpts.DeltaLink = ""
		fullRes, err := syncOneDriveDelta(ctx, client, fullOpts, driveID)
		if err != nil {
			return nil, err
		}
		fullRes.Stats["full_resync"] = 1
		res = fullRes
	}

	healed, err := healOneDriveRootFiles(ctx, client, opts, driveID)
	if err != nil {
		return nil, err
	}
	if healed > 0 {
		res.Stats["root_heal"] = healed
		res.Stats["items"] += healed
		res.ItemsDone += healed
		res.FileCount = opts.Overlay.EntryCount()
	}
	if missing, err := countMissingOneDriveRootFiles(ctx, client, opts, driveID); err != nil {
		return nil, err
	} else if missing > 0 {
		return nil, fmt.Errorf("onedrive root heal incomplete: %d root file(s) still missing from overlay", missing)
	}

	ensureOneDriveCatalogMarker(opts, res)
	return res, nil
}

// ResolveOneDriveID returns the drive id for OneDrive sync options.
func ResolveOneDriveID(ctx context.Context, client *graph.Client, opts OneDriveSyncOptions) (string, error) {
	return resolveOneDriveID(ctx, client, opts)
}

func resolveOneDriveID(ctx context.Context, client *graph.Client, opts OneDriveSyncOptions) (string, error) {
	driveID := strings.TrimSpace(opts.DriveID)
	if driveID == "" && strings.TrimSpace(opts.UserID) != "" {
		resolved, err := client.GetJSON(ctx, fmt.Sprintf("/users/%s/drive", opts.UserID), nil)
		if err != nil {
			return "", fmt.Errorf("resolve user drive: %w", err)
		}
		driveID, _ = resolved["id"].(string)
	}
	if driveID == "" {
		return "", fmt.Errorf("onedrive sync requires drive id")
	}
	return driveID, nil
}

func syncOneDriveDelta(ctx context.Context, client *graph.Client, opts OneDriveSyncOptions, driveID string) (*OneDriveSyncResult, error) {
	stats := map[string]int{"items": 0, "folders": 0, "removed": 0, "skipped_shard": 0}
	shard := opts.ShardKey

	var bytesTotal int64
	lastProgress := 0
	items, deltaLink, err := client.PaginateDelta(ctx, fmt.Sprintf("/drives/%s/root/delta", driveID), opts.DeltaLink, "id,name,size,file,folder,parentReference,lastModifiedDateTime,webUrl", 200, func(itemsSoFar int) {
		if opts.OnProgress == nil {
			return
		}
		if itemsSoFar-lastProgress >= 10 || itemsSoFar < 10 {
			lastProgress = itemsSoFar
			opts.OnProgress(itemsSoFar, itemsSoFar, 0)
		}
	})
	if err != nil {
		return nil, err
	}

	for i, item := range items {
		if removed, _ := item["@removed"].(map[string]any); removed != nil {
			id, _ := item["id"].(string)
			if id == "" {
				continue
			}
			opts.Overlay.RemoveByItemID(id)
			legacyMeta := fmt.Sprintf("%s/drives/%s/items/%s.json", opts.AzureTenantID, safeID(driveID), safeID(id))
			opts.Overlay.Remove(legacyMeta)
			stats["removed"]++
			continue
		}
		id, _ := item["id"].(string)
		if id == "" {
			continue
		}
		if isDriveFolder(item) {
			stats["folders"]++
			continue
		}
		if !opts.Shard.IncludesItem(id) {
			stats["skipped_shard"]++
			continue
		}
		path := driveContentPath(opts.AzureTenantID, opts.UserID, driveID, item)
		gf, err := graphfs.NewGraphFileFromDriveItem(client, driveID, item)
		if err != nil {
			return nil, err
		}
		opts.Overlay.PutWithItemID(id, path, gf)
		bytesTotal += gf.Size()
		stats["items"]++
		if opts.OnProgress != nil && (i+1)%10 == 0 {
			opts.OnProgress(i+1, len(items), bytesTotal)
		}
	}
	if opts.OnProgress != nil {
		opts.OnProgress(len(items), len(items), bytesTotal)
	}

	deltaStates := map[string]string{}
	if deltaLink != "" {
		deltaStates[DeltaKeyForShard(shard)] = deltaLink
	}

	return &OneDriveSyncResult{
		Stats:       stats,
		DeltaLink:   deltaLink,
		DeltaStates: deltaStates,
		FileCount:   opts.Overlay.EntryCount(),
		ItemsDone:   stats["items"],
		BytesTotal:  bytesTotal,
	}, nil
}

// HealOneDriveRootFiles lists drive root children and adds any files missing from the overlay.
// Incremental delta often skips unchanged root items that were never captured due to prior path bugs.
func HealOneDriveRootFiles(ctx context.Context, client *graph.Client, opts OneDriveSyncOptions, driveID string) (int, error) {
	return healOneDriveRootFiles(ctx, client, opts, driveID)
}

func healOneDriveRootFiles(ctx context.Context, client *graph.Client, opts OneDriveSyncOptions, driveID string) (int, error) {
	if strings.TrimSpace(opts.UserID) == "" || opts.Overlay == nil {
		return 0, nil
	}
	items, err := client.Paginate(ctx, fmt.Sprintf("/drives/%s/root/children", driveID), map[string]string{
		"$select": "id,name,size,file,folder,parentReference,lastModifiedDateTime,webUrl",
	})
	if err != nil {
		return 0, fmt.Errorf("onedrive root heal: %w", err)
	}
	added := 0
	var bytesTotal int64
	for _, item := range items {
		if isDriveFolder(item) {
			continue
		}
		id, _ := item["id"].(string)
		if id == "" || !opts.Shard.IncludesItem(id) {
			continue
		}
		path := driveContentPath(opts.AzureTenantID, opts.UserID, driveID, item)
		if existing, ok := opts.Overlay.ItemPath(id); ok {
			if existing == path {
				continue
			}
			opts.Overlay.RemoveByItemID(id)
		}
		if opts.Overlay.HasPath(path) {
			opts.Overlay.Remove(path)
		}
		gf, err := graphfs.NewGraphFileFromDriveItem(client, driveID, item)
		if err != nil {
			return added, err
		}
		opts.Overlay.PutWithItemID(id, path, gf)
		bytesTotal += gf.Size()
		added++
	}
	if added > 0 && opts.OnProgress != nil {
		opts.OnProgress(added, added, bytesTotal)
	}
	return added, nil
}

// CountMissingOneDriveRootFiles returns how many Graph root files are absent from the overlay.
func CountMissingOneDriveRootFiles(ctx context.Context, client *graph.Client, opts OneDriveSyncOptions, driveID string) (int, error) {
	return countMissingOneDriveRootFiles(ctx, client, opts, driveID)
}

func countMissingOneDriveRootFiles(ctx context.Context, client *graph.Client, opts OneDriveSyncOptions, driveID string) (int, error) {
	if strings.TrimSpace(opts.UserID) == "" || opts.Overlay == nil {
		return 0, nil
	}
	items, err := client.Paginate(ctx, fmt.Sprintf("/drives/%s/root/children", driveID), map[string]string{
		"$select": "id,name,file,folder,parentReference",
	})
	if err != nil {
		return 0, fmt.Errorf("onedrive root verify: %w", err)
	}
	missing := 0
	for _, item := range items {
		if isDriveFolder(item) {
			continue
		}
		id, _ := item["id"].(string)
		if id == "" || !opts.Shard.IncludesItem(id) {
			continue
		}
		path := driveContentPath(opts.AzureTenantID, opts.UserID, driveID, item)
		if opts.Overlay.HasPath(path) {
			continue
		}
		missing++
	}
	return missing, nil
}

// shouldForceOneDriveFullResync handles poisoned incremental deltas: prior runs advanced
// the Graph delta token while OneDrive files never landed in the Kopia snapshot (legacy
// drive-scoped paths outside the user source root).
func shouldForceOneDriveFullResync(opts OneDriveSyncOptions, driveID string, res *OneDriveSyncResult) bool {
	if strings.TrimSpace(opts.DeltaLink) == "" {
		return false
	}
	userID := strings.TrimSpace(opts.UserID)
	if userID != "" {
		misrouted := oneDriveMisroutedDrivesPrefix(opts.AzureTenantID, userID)
		if misrouted != "" && opts.Overlay.HasPathPrefix(misrouted) {
			return true
		}
	}
	if res.Stats["items"] > 0 {
		return false
	}
	if userID == "" {
		return false
	}
	userPrefix := oneDriveUserPrefix(opts.AzureTenantID, userID)
	if opts.Overlay.HasPathPrefix(userPrefix) {
		return false
	}
	legacyPrefix := fmt.Sprintf("%s/drives/%s/content", opts.AzureTenantID, safeID(driveID))
	if opts.Overlay.HasPathPrefix(legacyPrefix) {
		return true
	}
	// Incremental delta returned no file changes and the overlay has no OneDrive catalog yet.
	return true
}

func oneDriveUserPrefix(tenantID, userID string) string {
	return fmt.Sprintf("%s/users/%s/onedrive", tenantID, userID)
}

// oneDriveMisroutedDrivesPrefix is the bogus subtree created when root-level drive items
// were stored under content/drives/{driveId}/root:/ instead of content/{name}.
func oneDriveMisroutedDrivesPrefix(tenantID, userID string) string {
	if strings.TrimSpace(userID) == "" {
		return ""
	}
	return fmt.Sprintf("%s/users/%s/onedrive/content/drives", tenantID, userID)
}

func ensureOneDriveCatalogMarker(opts OneDriveSyncOptions, res *OneDriveSyncResult) {
	userID := strings.TrimSpace(opts.UserID)
	if userID == "" || opts.Overlay == nil {
		return
	}
	if res.Stats["items"] > 0 {
		return
	}
	marker := oneDriveUserPrefix(opts.AzureTenantID, userID) + "/.catalog"
	if opts.Overlay.HasPathPrefix(marker) {
		return
	}
	opts.Overlay.PutJSON(marker, []byte(`{"files":0}`), time.Now().UTC())
}
