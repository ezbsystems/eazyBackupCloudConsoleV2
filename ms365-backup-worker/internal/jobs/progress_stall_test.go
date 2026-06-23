package jobs

import (
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

func TestStallAwareProgressFnSetsNoProgressWhenFlat(t *testing.T) {
	items := 10
	fn := stallAwareProgressFn(1, func() api.ProgressUpdate {
		return api.ProgressUpdate{
			RunID:       "run-1",
			Phase:       "graph_sync",
			ItemsDone:   items,
			BytesHashed: 100,
		}
	})

	_ = fn()
	time.Sleep(1100 * time.Millisecond)
	upd := fn()
	if !upd.NoProgress {
		t.Fatalf("expected no_progress after flat interval, got %+v", upd)
	}
}

func TestStallAwareProgressFnIgnoresRising429(t *testing.T) {
	hits := int64(0)
	fn := stallAwareProgressFn(1, func() api.ProgressUpdate {
		return api.ProgressUpdate{
			RunID:        "run-1",
			ItemsDone:    10,
			BytesHashed:  100,
			Graph429Hits: hits,
		}
	})

	_ = fn()
	time.Sleep(1100 * time.Millisecond)
	hits = 1
	upd := fn()
	if upd.NoProgress {
		t.Fatalf("rising graph_429_hits should count as activity, got %+v", upd)
	}
}

func TestStallAwareProgressFnClearsNoProgressOnChange(t *testing.T) {
	items := 5
	fn := stallAwareProgressFn(1, func() api.ProgressUpdate {
		return api.ProgressUpdate{
			RunID:     "run-1",
			ItemsDone: items,
		}
	})

	_ = fn()
	time.Sleep(1100 * time.Millisecond)
	items = 6
	upd := fn()
	if upd.NoProgress {
		t.Fatalf("expected progress beat after items changed, got %+v", upd)
	}
}
