package jobs

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync/atomic"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

func TestSchedulerTrackRepoOpLoad(t *testing.T) {
	s := &Scheduler{}
	if s.currentLoad() != 0 {
		t.Fatalf("expected zero load, got %d", s.currentLoad())
	}
	s.trackRepoOp(1)
	if s.currentLoad() != 1 {
		t.Fatalf("expected load 1 during repo op, got %d", s.currentLoad())
	}
	s.trackRepoOp(-1)
	if s.currentLoad() != 0 {
		t.Fatalf("expected load cleared, got %d", s.currentLoad())
	}
}

func TestRunRepoOperationCompletesAfterCancelledContext(t *testing.T) {
	var progressCalls atomic.Int32
	var completeCalls atomic.Int32

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case strings.Contains(r.URL.Path, "maintenance_progress"):
			progressCalls.Add(1)
		case strings.Contains(r.URL.Path, "maintenance_complete"):
			completeCalls.Add(1)
		}
		_ = json.NewEncoder(w).Encode(map[string]any{"status": "success"})
	}))
	defer srv.Close()

	client := api.NewClient(srv.URL, "token", "test-node")
	s := &Scheduler{
		client: client,
		testHooks: &schedulerTestHooks{
			repoOpExecute: func(_ context.Context, _ *api.RepoOperation, reportProgress func(string, map[string]any)) (map[string]any, error) {
				reportProgress("repo_open", map[string]any{"index_blobs_before": 100})
				return nil, context.Canceled
			},
		},
	}

	op := &api.RepoOperation{
		OperationID:  99,
		OpType:       "maintenance_full",
		DestBucket:   "bucket-a",
		RepositoryID: "ms365:test",
	}

	done := make(chan struct{})
	go func() {
		s.runRepoOperation(op)
		close(done)
	}()

	select {
	case <-done:
	case <-time.After(5 * time.Second):
		t.Fatal("runRepoOperation did not finish")
	}

	if completeCalls.Load() != 1 {
		t.Fatalf("expected complete call after cancel, got %d", completeCalls.Load())
	}
	if progressCalls.Load() < 1 {
		t.Fatalf("expected progress calls, got %d", progressCalls.Load())
	}
}
