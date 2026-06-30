package jobs

import (
	"bufio"
	"context"
	"encoding/json"
	"log"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

const (
	completionOutboxMaxAge          = 24 * time.Hour
	completionOutboxMaxFlushAttempts = 20
)

type completionEntry struct {
	RunID         string             `json:"run_id"`
	BatchRunID    string             `json:"batch_run_id,omitempty"`
	Complete      api.CompleteUpdate `json:"complete"`
	EnqueuedAt    time.Time          `json:"enqueued_at"`
	FlushAttempts int                `json:"flush_attempts"`
}

// CompletionOutbox holds terminal child reports that failed to ACK.
// Keyed by run_id; idempotent replays are safe because backupComplete overwrites success.
type CompletionOutbox struct {
	mu          sync.Mutex
	entries     map[string]*completionEntry
	persistPath string
}

func NewCompletionOutbox(runDir string) *CompletionOutbox {
	o := &CompletionOutbox{
		entries: make(map[string]*completionEntry),
	}
	if runDir != "" {
		o.persistPath = filepath.Join(filepath.Dir(runDir), "pending_completions.ndjson")
		o.loadFromDisk()
	}
	return o
}

func (o *CompletionOutbox) Enqueue(batchRunID string, upd api.CompleteUpdate) {
	runID := strings.TrimSpace(upd.RunID)
	if runID == "" {
		return
	}
	batchRunID = strings.TrimSpace(batchRunID)
	o.mu.Lock()
	o.entries[runID] = &completionEntry{
		RunID:      runID,
		BatchRunID: batchRunID,
		Complete:   upd,
		EnqueuedAt: time.Now(),
	}
	o.mu.Unlock()
	o.appendToDisk(runID)
}

func (o *CompletionOutbox) Len() int {
	o.mu.Lock()
	defer o.mu.Unlock()
	return len(o.entries)
}

func (o *CompletionOutbox) Flush(ctx context.Context, client *api.Client) (acked, remaining int) {
	if o == nil || client == nil {
		return 0, 0
	}
	now := time.Now()
	var toDrop []string

	o.mu.Lock()
	snapshot := make([]*completionEntry, 0, len(o.entries))
	for runID, entry := range o.entries {
		if entry == nil {
			toDrop = append(toDrop, runID)
			continue
		}
		if now.Sub(entry.EnqueuedAt) > completionOutboxMaxAge {
			log.Printf("completion outbox dropping %s: exceeded max age %v", runID, completionOutboxMaxAge)
			toDrop = append(toDrop, runID)
			continue
		}
		if entry.FlushAttempts >= completionOutboxMaxFlushAttempts {
			log.Printf("completion outbox dropping %s: exceeded max flush attempts (%d)", runID, completionOutboxMaxFlushAttempts)
			toDrop = append(toDrop, runID)
			continue
		}
		snapshot = append(snapshot, entry)
	}
	for _, runID := range toDrop {
		delete(o.entries, runID)
	}
	o.mu.Unlock()

	for _, entry := range snapshot {
		var err error
		if entry.BatchRunID != "" {
			err = client.BatchComplete(ctx, api.BatchCompleteUpdate{
				BatchRunID: entry.BatchRunID,
				Children: []api.BatchChildResult{{
					RunID:      entry.Complete.RunID,
					Status:     "success",
					ManifestID: entry.Complete.ManifestID,
					ItemsDone:  entry.Complete.ItemsDone,
					ItemsTotal: entry.Complete.ItemsTotal,
					StatsJSON:  entry.Complete.StatsJSON,
				}},
			})
		} else {
			err = client.Complete(ctx, entry.Complete)
		}
		if err != nil {
			o.mu.Lock()
			if cur := o.entries[entry.RunID]; cur != nil {
				cur.FlushAttempts++
			}
			o.mu.Unlock()
			log.Printf("completion outbox flush failed for %s: %v", entry.RunID, err)
			continue
		}
		o.mu.Lock()
		delete(o.entries, entry.RunID)
		o.mu.Unlock()
		o.removeFromDisk(entry.RunID)
		acked++
	}

	o.mu.Lock()
	remaining = len(o.entries)
	o.mu.Unlock()
	return acked, remaining
}

func (o *CompletionOutbox) loadFromDisk() {
	if o.persistPath == "" {
		return
	}
	f, err := os.Open(o.persistPath)
	if err != nil {
		return
	}
	defer f.Close()
	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" {
			continue
		}
		var entry completionEntry
		if err := json.Unmarshal([]byte(line), &entry); err != nil {
			continue
		}
		if entry.RunID == "" {
			continue
		}
		o.entries[entry.RunID] = &entry
	}
}

func (o *CompletionOutbox) appendToDisk(runID string) {
	if o.persistPath == "" {
		return
	}
	o.mu.Lock()
	entry := o.entries[runID]
	o.mu.Unlock()
	if entry == nil {
		return
	}
	raw, err := json.Marshal(entry)
	if err != nil {
		return
	}
	f, err := os.OpenFile(o.persistPath, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0o600)
	if err != nil {
		return
	}
	_, _ = f.Write(append(raw, '\n'))
	_ = f.Close()
}

func (o *CompletionOutbox) removeFromDisk(runID string) {
	if o.persistPath == "" {
		return
	}
	o.mu.Lock()
	entries := make([]*completionEntry, 0, len(o.entries))
	for _, entry := range o.entries {
		entries = append(entries, entry)
	}
	o.mu.Unlock()
	f, err := os.Create(o.persistPath)
	if err != nil {
		return
	}
	defer f.Close()
	for _, entry := range entries {
		raw, err := json.Marshal(entry)
		if err != nil {
			continue
		}
		_, _ = f.Write(append(raw, '\n'))
	}
}
