package jobs

import (
	"context"
	"net/http"
	"net/http/httptest"
	"sync/atomic"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

func TestCompletionOutboxFlush503Then200(t *testing.T) {
	var attempts atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		n := attempts.Add(1)
		w.Header().Set("Content-Type", "application/json")
		if n == 1 {
			w.WriteHeader(http.StatusServiceUnavailable)
			_, _ = w.Write([]byte(`unavailable`))
			return
		}
		_, _ = w.Write([]byte(`{"status":"success","data":{}}`))
	}))
	defer srv.Close()

	client := api.NewClient(srv.URL, "tok", "node-1")
	outbox := NewCompletionOutbox(t.TempDir())
	outbox.Enqueue("batch-1", api.CompleteUpdate{
		RunID:     "child-1",
		StatsJSON: `{"status":"no_changes"}`,
	})

	acked, remaining := outbox.Flush(context.Background(), client)
	if acked != 1 || remaining != 0 {
		t.Fatalf("acked=%d remaining=%d want 1/0", acked, remaining)
	}
	if outbox.Len() != 0 {
		t.Fatalf("expected empty outbox, len=%d", outbox.Len())
	}
}

func TestCompletionOutboxDropsAfterFlushCap(t *testing.T) {
	outbox := NewCompletionOutbox(t.TempDir())
	outbox.mu.Lock()
	outbox.entries["child-2"] = &completionEntry{
		RunID:         "child-2",
		Complete:      api.CompleteUpdate{RunID: "child-2", StatsJSON: `{}`},
		EnqueuedAt:    time.Now(),
		FlushAttempts: completionOutboxMaxFlushAttempts,
	}
	outbox.mu.Unlock()

	acked, remaining := outbox.Flush(context.Background(), api.NewClient("http://example.test", "tok", "node-1"))
	if acked != 0 || remaining != 0 {
		t.Fatalf("acked=%d remaining=%d want 0/0 after cap drop", acked, remaining)
	}
	if outbox.Len() != 0 {
		t.Fatalf("expected outbox entry dropped after cap, len=%d", outbox.Len())
	}
}
