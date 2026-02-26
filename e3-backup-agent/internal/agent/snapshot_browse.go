package agent

import (
	"context"
	"fmt"
	"os"
	"path"
	"sort"
	"strings"

	kopiafs "github.com/kopia/kopia/fs"
	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/repo/manifest"
	"github.com/kopia/kopia/snapshot"
	"github.com/kopia/kopia/snapshot/snapshotfs"
)

// executeBrowseSnapshotCommand handles snapshot browse requests from the dashboard.
func (r *Runner) executeBrowseSnapshotCommand(ctx context.Context, cmd PendingCommand) {
	if cmd.JobContext == nil {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing job context")
		return
	}

	manifestID := ""
	browsePath := ""
	maxItems := 500

	if cmd.Payload != nil {
		if v, ok := cmd.Payload["manifest_id"].(string); ok {
			manifestID = v
		}
		if v, ok := cmd.Payload["path"].(string); ok {
			browsePath = v
		}
		if v, ok := cmd.Payload["max_items"].(float64); ok && v > 0 {
			maxItems = int(v)
		}
	}
	if manifestID == "" {
		manifestID = cmd.JobContext.ManifestID
	}
	if manifestID == "" {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "missing manifest_id")
		return
	}
	if strings.ToLower(strings.TrimSpace(cmd.JobContext.Engine)) != "kopia" {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "snapshot browsing requires kopia engine")
		return
	}

	resp, err := r.browseSnapshot(ctx, cmd.JobContext, manifestID, browsePath, maxItems)
	if err != nil {
		resp.Error = err.Error()
	}

	if err := r.client.ReportBrowseResult(cmd.CommandID, resp); err != nil {
		_ = r.client.CompleteCommand(cmd.CommandID, "failed", "report browse failed: "+err.Error())
		return
	}
	// agent_report_browse.php marks the command as completed.
}

func (r *Runner) browseSnapshot(ctx context.Context, job *JobContext, manifestID, browsePath string, maxItems int) (BrowseDirectoryResponse, error) {
	run := &NextRunResponse{
		JobID:          job.JobID,
		Engine:         job.Engine,
		DestType:       job.DestType,
		DestBucketName: job.DestBucketName,
		DestPrefix:     job.DestPrefix,
		DestLocalPath:  job.DestLocalPath,
		DestEndpoint:   job.DestEndpoint,
		DestRegion:     job.DestRegion,
		DestAccessKey:  job.DestAccessKey,
		DestSecretKey:  job.DestSecretKey,
	}

	repoPath := kopiaRepoConfigPath(r.cfg, run)
	opts := kopiaOptionsFromRun(r.cfg, run)
	password := opts.password()

	if _, statErr := os.Stat(repoPath); os.IsNotExist(statErr) {
		st, stErr := opts.storage(ctx)
		if stErr != nil {
			return BrowseDirectoryResponse{}, fmt.Errorf("snapshot browse storage init failed: %w", stErr)
		}
		if connErr := repo.Connect(ctx, repoPath, st, password, nil); connErr != nil {
			return BrowseDirectoryResponse{}, fmt.Errorf("snapshot browse connect failed: %w", connErr)
		}
	}

	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		return BrowseDirectoryResponse{}, fmt.Errorf("snapshot browse open repo failed: %w", err)
	}
	defer rep.Close(ctx)

	man, err := snapshot.LoadSnapshot(ctx, rep, manifest.ID(manifestID))
	if err != nil {
		return BrowseDirectoryResponse{}, fmt.Errorf("snapshot browse load manifest failed: %w", err)
	}

	rootEntry := snapshotfs.EntryFromDirEntry(rep, man.RootEntry)
	if rootEntry == nil {
		return BrowseDirectoryResponse{}, fmt.Errorf("snapshot browse root entry unavailable")
	}

	normalizedPath := normalizeSnapshotPath(browsePath)
	entry, err := findSnapshotEntry(ctx, rootEntry, normalizedPath)
	if err != nil {
		return BrowseDirectoryResponse{}, err
	}

	dir, ok := entry.(kopiafs.Directory)
	if !ok {
		return BrowseDirectoryResponse{}, fmt.Errorf("snapshot path is not a directory")
	}

	entries, err := dir.Readdir(ctx)
	if err != nil {
		return BrowseDirectoryResponse{}, fmt.Errorf("snapshot browse read dir failed: %w", err)
	}

	sort.Slice(entries, func(i, j int) bool {
		iDir := entries[i].IsDir()
		jDir := entries[j].IsDir()
		if iDir != jDir {
			return iDir
		}
		return strings.ToLower(entries[i].Name()) < strings.ToLower(entries[j].Name())
	})

	if maxItems <= 0 {
		maxItems = 500
	}
	if maxItems > 1000 {
		maxItems = 1000
	}

	outEntries := make([]FileEntry, 0, len(entries))
	for idx, e := range entries {
		if idx >= maxItems {
			break
		}
		entryPath := path.Join(normalizedPath, e.Name())
		item := FileEntry{
			Name:  e.Name(),
			Path:  entryPath,
			IsDir: e.IsDir(),
			Size:  e.Size(),
		}
		if e.IsDir() {
			item.Type = "folder"
			item.Icon = detectFolderIcon(e.Name())
		} else {
			item.Type = "file"
			item.Icon = detectFileIcon(e.Name())
			item.ModifiedAt = e.ModTime()
		}
		outEntries = append(outEntries, item)
	}

	parent := path.Dir(normalizedPath)
	if parent == "." || parent == "/" {
		parent = ""
	}

	return BrowseDirectoryResponse{
		Path:    normalizedPath,
		Parent:  parent,
		Entries: outEntries,
	}, nil
}

func normalizeSnapshotPath(raw string) string {
	p := strings.TrimSpace(raw)
	if p == "" {
		return ""
	}
	p = strings.ReplaceAll(p, "\\", "/")
	p = path.Clean(p)
	if p == "." || p == "/" {
		return ""
	}
	p = strings.TrimPrefix(p, "/")
	return p
}

func findSnapshotEntry(ctx context.Context, root kopiafs.Entry, relPath string) (kopiafs.Entry, error) {
	if relPath == "" {
		return root, nil
	}
	parts := strings.Split(relPath, "/")
	curr := root
	for _, part := range parts {
		part = strings.TrimSpace(part)
		if part == "" {
			continue
		}
		dir, ok := curr.(kopiafs.Directory)
		if !ok {
			return nil, fmt.Errorf("snapshot path is not a directory")
		}
		child, err := dir.Child(ctx, part)
		if err != nil {
			return nil, err
		}
		curr = child
	}
	return curr, nil
}
