package jobs

import (
	"context"
	"errors"
	"sync/atomic"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

func isCooperativeCancel(err error, runCtx context.Context) bool {
	return errors.Is(err, context.Canceled) && errors.Is(runCtx.Err(), context.Canceled)
}

func sendProgress(ctx context.Context, client *api.Client, onAbort context.CancelFunc, upd api.ProgressUpdate) {
	sendProgressForTenant(ctx, client, onAbort, upd, "")
}

func sendProgressForTenant(ctx context.Context, client *api.Client, onAbort context.CancelFunc, upd api.ProgressUpdate, tenantID string) {
	if cancel, budget, err := client.Progress(ctx, upd); err == nil {
		if tenantID != "" && budget > 0 {
			graph.SetTenantCeiling(tenantID, budget)
		}
		if cancel && onAbort != nil {
			onAbort()
		}
	}
}

// newThrottledProgressSender coalesces high-frequency progress callbacks so the
// control plane receives at most one intermediate update per minInterval. Kopia
// fires a progress event per hashed/uploaded chunk (potentially hundreds per
// second per run); forwarding each one floods ms365_worker_progress.php and the
// database (every POST fans out to several committed transactions). The first
// event always passes through; the periodic StartProgressHeartbeat still renews
// the lease at its own cadence, so dropping intermediate updates is safe.
func newThrottledProgressSender(ctx context.Context, client *api.Client, onAbort context.CancelFunc, tenantID string, minInterval time.Duration) func(api.ProgressUpdate) {
	if minInterval <= 0 {
		minInterval = 5 * time.Second
	}
	var lastNano atomic.Int64
	return func(upd api.ProgressUpdate) {
		now := time.Now().UnixNano()
		last := lastNano.Load()
		if last != 0 && time.Duration(now-last) < minInterval {
			return
		}
		if !lastNano.CompareAndSwap(last, now) {
			return
		}
		sendProgressForTenant(ctx, client, onAbort, upd, tenantID)
	}
}
