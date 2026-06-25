package jobs

// #region agent log
// Debug instrumentation retained to verify the Graph 429 over-release deadlock
// fix on the live fleet. Ships a graph_diag line every 30s with global transport
// semaphore occupancy, tenant controller limit/inflight, and Graph throughput.
// Pre-fix signature of the deadlock was: inflight stuck >= limit while global=0
// with zero req_delta and no recent 429s. Remove once the fix is confirmed live.

import (
	"context"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

type diagThroughputSource interface {
	RequestsTotal() int64
	ThrottleHits() int64
}

func startLimiterDiag(ctx context.Context, client *api.Client, runID, tenantID string, gc diagThroughputSource) func() {
	if client == nil || gc == nil || strings.TrimSpace(runID) == "" {
		return func() {}
	}
	done := make(chan struct{})
	go func() {
		ticker := time.NewTicker(30 * time.Second)
		defer ticker.Stop()
		var lastReq int64 = -1
		for {
			select {
			case <-ctx.Done():
				return
			case <-done:
				return
			case <-ticker.C:
				reqTotal := gc.RequestsTotal()
				throttle := gc.ThrottleHits()
				inUse, capacity := graph.GlobalSemStats()
				limit, inFlight, last429, cooldown := graph.TenantControllerDebug(tenantID)

				delta := int64(-1)
				if lastReq >= 0 {
					delta = reqTotal - lastReq
				}
				lastReq = reqTotal

				last429Ago := -1
				if !last429.IsZero() {
					last429Ago = int(time.Since(last429).Seconds())
				}
				cooldownIn := 0
				if d := time.Until(cooldown); d > 0 {
					cooldownIn = int(d.Seconds())
				}

				client.RunLogf(ctx, runID, "warning",
					"graph_diag global=%d/%d tenant_limit=%d inflight=%d req_total=%d req_delta_30s=%d throttle=%d last429_ago=%ds cooldown_in=%ds",
					inUse, capacity, limit, inFlight, reqTotal, delta, throttle, last429Ago, cooldownIn,
				)
			}
		}
	}()
	return func() { close(done) }
}

// #endregion
