package graphrestore

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"strings"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

// ContentFetcher reads snapshot payloads for restore. Drive files use Stream to
// avoid loading multi-hundred-MiB blobs into memory.
type ContentFetcher struct {
	Bytes  func(path string) ([]byte, error)
	Stream func(path string) (io.ReadCloser, int64, error)
}

type Target struct {
	ResourceID   string `json:"resource_id"`
	GraphID      string `json:"graph_id"`
	ResourceType string `json:"resource_type"`
	DriveID      string `json:"drive_id,omitempty"`
}

type SelectionItem struct {
	ChildRunID  string `json:"child_run_id"`
	ManifestID  string `json:"manifest_id"`
	Path        string `json:"path"`
	PathPrefix  string `json:"path_prefix"`
	Type        string `json:"type"`
}

type Options struct {
	Client          *graph.Client
	Target          Target
	ConflictPolicy  string
	OnProgress      func(done, skipped, total int, message string)
}

type Stats struct {
	Restored int `json:"restored"`
	Skipped  int `json:"skipped"`
	Errors   int `json:"errors"`
	// ErrorMessages holds a small, de-duplicated sample of the underlying
	// failures so callers can report a real reason instead of just a count.
	ErrorMessages []string `json:"error_messages,omitempty"`
}

func (s *Stats) recordError(err error) {
	s.Errors++
	if err == nil {
		return
	}
	msg := strings.TrimSpace(err.Error())
	if msg == "" || len(s.ErrorMessages) >= 5 {
		return
	}
	for _, existing := range s.ErrorMessages {
		if existing == msg {
			return
		}
	}
	s.ErrorMessages = append(s.ErrorMessages, msg)
}

type Runner struct {
	Client         *graph.Client
	ConflictPolicy string
	OnProgress     func(done, skipped, total int, message string)
	// OnItemError is called for each per-item failure (path resolution, fetch, Graph upload).
	OnItemError func(message string)
}

func NewRunner(client *graph.Client, conflictPolicy string, onProgress func(done, skipped, total int, message string)) *Runner {
	if conflictPolicy == "" {
		conflictPolicy = "skip_duplicates"
	}
	return &Runner{Client: client, ConflictPolicy: conflictPolicy, OnProgress: onProgress}
}

func (r *Runner) noteError(stats *Stats, err error) {
	stats.recordError(err)
	if err == nil || r.OnItemError == nil {
		return
	}
	msg := strings.TrimSpace(err.Error())
	if msg != "" {
		r.OnItemError(msg)
	}
}

func (r *Runner) RestoreItems(ctx context.Context, target Target, items []SelectionItem, fetch ContentFetcher) (*Stats, error) {
	stats := &Stats{}
	total := len(items)
	for i, item := range items {
		paths, err := r.resolvePaths(item, fetch.Bytes)
		if err != nil {
			r.noteError(stats, err)
			if r.OnProgress != nil {
				r.OnProgress(stats.Restored+stats.Skipped, stats.Skipped, total, itemProgressMessage(i+1, total, selectionItemName(item), "failed", err))
			}
			continue
		}
		itemName := selectionItemName(item)
		var itemRestored, itemSkipped, itemFailed bool
		var itemErr error
		for _, p := range paths {
			if isDriveContentPath(p) && fetch.Stream != nil {
				rc, size, err := fetch.Stream(p)
				if err != nil {
					itemFailed = true
					itemErr = err
					r.noteError(stats, fmt.Errorf("fetch %s: %w", shortPath(p), err))
					continue
				}
				skipped, err := r.RestoreDriveContent(ctx, target, p, size, rc)
				_ = rc.Close()
				if err != nil {
					itemFailed = true
					itemErr = err
					r.noteError(stats, fmt.Errorf("%s: %w", shortPath(p), err))
				} else if skipped {
					itemSkipped = true
					stats.Skipped++
				} else {
					itemRestored = true
					stats.Restored++
				}
				continue
			}

			data, err := fetch.Bytes(p)
			if err != nil {
				itemFailed = true
				itemErr = err
				r.noteError(stats, fmt.Errorf("fetch %s: %w", shortPath(p), err))
				continue
			}
			if len(data) == 0 {
				itemFailed = true
				itemErr = fmt.Errorf("empty payload")
				r.noteError(stats, fmt.Errorf("empty payload for %s", shortPath(p)))
				continue
			}
			skipped, err := r.restorePath(ctx, target, p, data)
			if err != nil {
				itemFailed = true
				itemErr = err
				r.noteError(stats, fmt.Errorf("%s: %w", shortPath(p), err))
			} else if skipped {
				itemSkipped = true
				stats.Skipped++
			} else {
				itemRestored = true
				stats.Restored++
			}
		}
		if r.OnProgress != nil {
			switch {
			case itemFailed:
				r.OnProgress(stats.Restored+stats.Skipped, stats.Skipped, total, itemProgressMessage(i+1, total, itemName, "failed", itemErr))
			case itemSkipped:
				r.OnProgress(stats.Restored+stats.Skipped, stats.Skipped, total, itemProgressMessage(i+1, total, itemName, "skipped", nil))
			case itemRestored:
				r.OnProgress(stats.Restored+stats.Skipped, stats.Skipped, total, itemProgressMessage(i+1, total, itemName, "restored", nil))
			default:
				r.OnProgress(stats.Restored+stats.Skipped, stats.Skipped, total, fmt.Sprintf("item %d/%d", i+1, total))
			}
		}
	}
	return stats, nil
}

func selectionItemName(item SelectionItem) string {
	if name := shortPath(item.Path); name != "" {
		return name
	}
	return shortPath(item.PathPrefix)
}

func itemProgressMessage(index, total int, name, outcome string, err error) string {
	msg := fmt.Sprintf("item %d/%d", index, total)
	if name != "" {
		msg += ": " + name
	}
	switch outcome {
	case "restored":
		return msg + " restored"
	case "skipped":
		return msg + " skipped (already in destination)"
	case "failed":
		if err != nil {
			return msg + " failed: " + strings.TrimSpace(err.Error())
		}
		return msg + " failed"
	default:
		return msg
	}
}

func (r *Runner) RestoreDriveContent(ctx context.Context, target Target, path string, size int64, body io.Reader) (bool, error) {
	return restoreDriveFileStream(ctx, r.Client, target, path, size, body, r.ConflictPolicy)
}

func isDriveContentPath(path string) bool {
	lower := strings.ToLower(path)
	return strings.Contains(lower, "/content/") && !strings.HasSuffix(lower, ".json")
}

func (r *Runner) resolvePaths(item SelectionItem, fetch func(path string) ([]byte, error)) ([]string, error) {
	if item.Path != "" && !strings.HasSuffix(item.Path, "/") {
		return []string{item.Path}, nil
	}
	prefix := item.Path
	if prefix == "" {
		prefix = item.PathPrefix
	}
	if prefix == "" {
		return nil, fmt.Errorf("empty path")
	}
	return []string{prefix}, nil
}

func (r *Runner) restorePath(ctx context.Context, target Target, path string, data []byte) (skipped bool, err error) {
	lower := strings.ToLower(path)
	switch {
	case strings.Contains(lower, "/mail/") && strings.HasSuffix(lower, ".json") && !strings.HasSuffix(lower, "_folder.json") && !strings.HasSuffix(lower, ".removed.json"):
		return restoreMailMessage(ctx, r.Client, target.GraphID, data, r.ConflictPolicy)
	case (strings.Contains(lower, "/calendars/") || strings.Contains(lower, "/calendar/")) && strings.HasSuffix(lower, ".json"):
		return restoreCalendarEvent(ctx, r.Client, target.GraphID, path, data, r.ConflictPolicy)
	case strings.Contains(lower, "/contacts/") && strings.HasSuffix(lower, ".json"):
		return restoreContact(ctx, r.Client, target.GraphID, data, r.ConflictPolicy)
	case strings.Contains(lower, "/tasks/") && strings.HasSuffix(lower, ".json"):
		return restoreTask(ctx, r.Client, target.GraphID, data, r.ConflictPolicy)
	case strings.Contains(lower, "/content/"):
		return restoreDriveFile(ctx, r.Client, target, path, data, r.ConflictPolicy)
	case strings.Contains(lower, "/teams/") && strings.Contains(lower, "/messages/"):
		return restoreTeamsMessage(ctx, r.Client, target, path, data, r.ConflictPolicy)
	case strings.Contains(lower, "/planner/") && strings.HasSuffix(lower, ".json"):
		return restorePlannerItem(ctx, r.Client, target, data, r.ConflictPolicy)
	case strings.Contains(lower, "/onenote/") && strings.HasSuffix(lower, ".json"):
		return restoreOneNoteItem(ctx, r.Client, target, data, r.ConflictPolicy)
	default:
		return false, fmt.Errorf("unsupported path: %s", path)
	}
}

// shortPath trims a long snapshot path down to its workload + leaf so error
// messages stay readable (Graph item ids can be hundreds of chars long).
func shortPath(p string) string {
	parts := strings.Split(strings.Trim(p, "/"), "/")
	if len(parts) <= 2 {
		return p
	}
	return ".../" + strings.Join(parts[len(parts)-2:], "/")
}

func parseJSON(data []byte) (map[string]any, error) {
	var m map[string]any
	if err := json.Unmarshal(data, &m); err != nil {
		return nil, err
	}
	return m, nil
}

// mapString safely reads a string-ish value from a decoded JSON map. A missing
// key or an explicit JSON null yields "" — never the literal "<nil>" that
// fmt.Sprint(nil) would produce, which previously broke empty-string fallbacks
// (e.g. calendar/list/plan id resolution) and caused invalid Graph requests.
func mapString(m map[string]any, key string) string {
	v, ok := m[key]
	if !ok || v == nil {
		return ""
	}
	if s, ok := v.(string); ok {
		return strings.TrimSpace(s)
	}
	return strings.TrimSpace(fmt.Sprint(v))
}
