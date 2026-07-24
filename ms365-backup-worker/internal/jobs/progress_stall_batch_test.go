package jobs

import (
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

func TestStallAwareBatchProgressFnSeparatesHashedAndUploaded(t *testing.T) {
	fn := stallAwareBatchProgressFn(1, func() api.BatchProgressUpdate {
		return api.BatchProgressUpdate{
			Children: []api.ProgressUpdate{{
				RunID:         "child-1",
				Phase:         "kopia_upload",
				ItemsDone:     100,
				BytesHashed:   14_721_902_294,
				BytesUploaded: 4_240_027_924,
			}},
		}
	})

	_ = fn()
	time.Sleep(1100 * time.Millisecond)
	upd := fn()
	if len(upd.Children) != 1 {
		t.Fatalf("children=%d want 1", len(upd.Children))
	}
	if !upd.Children[0].NoProgress {
		t.Fatalf("expected no_progress with flat hashed/uploaded, got %+v", upd.Children[0])
	}
}

func TestStallAwareBatchProgressFnPerChild(t *testing.T) {
	activeItems := 10
	fn := stallAwareBatchProgressFn(1, func() api.BatchProgressUpdate {
		return api.BatchProgressUpdate{
			Children: []api.ProgressUpdate{
				{
					RunID:         "stale",
					ItemsDone:     50,
					BytesHashed:   1000,
					BytesUploaded: 500,
				},
				{
					RunID:         "active",
					ItemsDone:     activeItems,
					BytesHashed:   2000,
					BytesUploaded: 1500,
				},
			},
		}
	})

	_ = fn()
	time.Sleep(1100 * time.Millisecond)
	activeItems = 11
	upd := fn()
	if len(upd.Children) != 2 {
		t.Fatalf("children=%d want 2", len(upd.Children))
	}
	if !upd.Children[0].NoProgress {
		t.Fatalf("stale child should be marked no_progress, got %+v", upd.Children[0])
	}
	if upd.Children[1].NoProgress {
		t.Fatalf("active child should not be marked no_progress, got %+v", upd.Children[1])
	}
}
