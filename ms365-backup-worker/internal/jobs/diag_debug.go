package jobs

// #region agent log
// This file is debug instrumentation for diagnosing the batch-wide Graph stall
// (workloads freeze at ~0 CPU, "no progress for X minutes"). It ships limiter
// occupancy, tenant-controller state, Graph throughput, and a goroutine-category
// breakdown to the control plane via RunLog so we can see WHERE slots are pinned
// without SSH/pprof access to the worker node. Remove after the stall is fixed.

import (
	"context"
	"fmt"
	"runtime"
	"sort"
	"strings"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

type diagThroughputSource interface {
	RequestsTotal() int64
	ThrottleHits() int64
}

// startLimiterDiag launches a 30s ticker that ships a single NDJSON-ish diag line
// to the control plane (ms365_worker_log_lines) for the given runID. It is keyed
// off the shared batch graph client + tenant controller.
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
				limit, inFlight, waiters, last429, cooldown := graph.TenantControllerDebug(tenantID)

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

				cats := goroutineCategoryCounts()
				client.RunLogf(ctx, runID, "warning",
					"graph_diag global=%d/%d tenant_limit=%d inflight=%d waiters=%d req_total=%d req_delta_30s=%d throttle=%d last429_ago=%ds cooldown_in=%ds goroutines=%d cats=%s",
					inUse, capacity, limit, inFlight, waiters, reqTotal, delta, throttle, last429Ago, cooldownIn,
					runtime.NumGoroutine(), cats,
				)
			}
		}
	}()
	return func() { close(done) }
}

// goroutineCategoryCounts buckets every goroutine by a signature frame in its
// stack so we can tell whether slots are held by stream reads, kopia uploads,
// http round-trips, or blocked acquirers.
func goroutineCategoryCounts() string {
	buf := make([]byte, 1<<20)
	n := runtime.Stack(buf, true)
	stacks := strings.Split(string(buf[:n]), "\n\n")
	counts := map[string]int{}
	for _, st := range stacks {
		counts[classifyGoroutine(st)]++
	}
	type kv struct {
		k string
		v int
	}
	pairs := make([]kv, 0, len(counts))
	for k, v := range counts {
		pairs = append(pairs, kv{k, v})
	}
	sort.Slice(pairs, func(i, j int) bool { return pairs[i].v > pairs[j].v })
	var b strings.Builder
	b.WriteByte('{')
	for i, p := range pairs {
		if i > 0 {
			b.WriteByte(',')
		}
		fmt.Fprintf(&b, "%s:%d", p.k, p.v)
	}
	b.WriteByte('}')
	return b.String()
}

func classifyGoroutine(stack string) string {
	switch {
	case strings.Contains(stack, "(*tenantController).acquire"):
		return "tenant_acquire_wait"
	case strings.Contains(stack, "graph.acquireGlobal"):
		return "global_sem_wait"
	case strings.Contains(stack, "graphfs.(*streamReader).Read"), strings.Contains(stack, "graph.(*Client).getStream"):
		return "stream_read"
	case strings.Contains(stack, "(*Client).sleep429"), strings.Contains(stack, "(*Client).sleepRetry"):
		return "sleep_retry"
	case strings.Contains(stack, "graph.(*Client).doRequest"):
		return "graph_do_request"
	case strings.Contains(stack, "net/http.(*persistConn).roundTrip"), strings.Contains(stack, "(*http2ClientConn)"):
		return "http_roundtrip"
	case strings.Contains(stack, "kopia") && (strings.Contains(stack, "upload") || strings.Contains(stack, "Upload")):
		return "kopia_upload"
	case strings.Contains(stack, "kopia"):
		return "kopia_other"
	case strings.Contains(stack, "io.Copy"), strings.Contains(stack, "io.copyBuffer"):
		return "io_copy"
	default:
		return "other"
	}
}

// #endregion
