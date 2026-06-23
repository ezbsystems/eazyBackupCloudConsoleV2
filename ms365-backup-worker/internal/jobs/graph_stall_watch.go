package jobs

import (
	"context"
	"log"
	"sync/atomic"
	"time"
)

// GraphProgressSnapshot is read by the graph_sync stall watchdog.
type GraphProgressSnapshot struct {
	ItemsDone  func() int
	BytesTotal func() int64
	// ThrottleHits returns cumulative Graph 429 count; activity while throttled
	// counts as forward progress so Retry-After backoff does not trip the watchdog.
	ThrottleHits func() int64
}

// GraphStallWatchConfig controls detection of wedged graph_sync enumeration.
type GraphStallWatchConfig struct {
	StallSeconds                int
	ThrottleStallCeilingSeconds int
	CheckIntervalSeconds        int
	GraceSeconds                int
	RunID                       string
	OnStall                     func(snapshot map[string]any)
}

// StartGraphStallWatch monitors graph_sync progress and cancels the context when
// enumeration stalls with no items/bytes/429 activity for StallSeconds.
func StartGraphStallWatch(ctx context.Context, cancel context.CancelFunc, snap GraphProgressSnapshot, cfg GraphStallWatchConfig) func() {
	if cancel == nil || cfg.StallSeconds <= 0 {
		return func() {}
	}
	if snap.ItemsDone == nil {
		snap.ItemsDone = func() int { return 0 }
	}
	if snap.BytesTotal == nil {
		snap.BytesTotal = func() int64 { return 0 }
	}
	if snap.ThrottleHits == nil {
		snap.ThrottleHits = func() int64 { return 0 }
	}

	interval := cfg.CheckIntervalSeconds
	if interval <= 0 {
		interval = 60
	}
	grace := cfg.GraceSeconds
	if grace < 0 {
		grace = 0
	}
	started := time.Now()
	done := make(chan struct{})
	var stalled atomic.Bool

	go func() {
		ticker := time.NewTicker(time.Duration(interval) * time.Second)
		defer ticker.Stop()
		var lastItems int = -1
		var lastBytes int64 = -1
		var last429 int64 = -1
		var lastActivity time.Time
		var lastRealActivity time.Time

		for {
			select {
			case <-ctx.Done():
				return
			case <-done:
				return
			case <-ticker.C:
				if time.Since(started) < time.Duration(grace)*time.Second {
					continue
				}
				items := snap.ItemsDone()
				bytes := snap.BytesTotal()
				hits429 := snap.ThrottleHits()
				now := time.Now()

				if lastActivity.IsZero() {
					lastActivity = now
					lastRealActivity = now
					lastItems = items
					lastBytes = bytes
					last429 = hits429
					continue
				}

				realProgress := items != lastItems || bytes != lastBytes
				if realProgress {
					lastRealActivity = now
				}
				if realProgress || hits429 != last429 {
					lastItems = items
					lastBytes = bytes
					last429 = hits429
					lastActivity = now
					continue
				}

				sinceActivity := int(now.Sub(lastActivity).Seconds())
				if sinceActivity < cfg.StallSeconds {
					if cfg.ThrottleStallCeilingSeconds > 0 && !lastRealActivity.IsZero() {
						sinceReal := int(now.Sub(lastRealActivity).Seconds())
						if sinceReal >= cfg.ThrottleStallCeilingSeconds {
							snapshot := map[string]any{
								"items_done":                     items,
								"bytes_total":                    bytes,
								"graph_429_hits":                 hits429,
								"seconds_since_real_progress":    sinceReal,
								"throttle_stall_ceiling_seconds": cfg.ThrottleStallCeilingSeconds,
							}
							if cfg.OnStall != nil {
								cfg.OnStall(snapshot)
							}
							stalled.Store(true)
							log.Printf("graph_sync throttle ceiling run=%s since_real_progress=%ds snapshot=%v",
								cfg.RunID, sinceReal, snapshot)
							cancel()
							return
						}
					}
					continue
				}

				snapshot := map[string]any{
					"items_done":            items,
					"bytes_total":           bytes,
					"graph_429_hits":        hits429,
					"seconds_since_activity": sinceActivity,
				}
				if cfg.OnStall != nil {
					cfg.OnStall(snapshot)
				}
				stalled.Store(true)
				log.Printf("graph_sync stall watchdog run=%s since_activity=%ds snapshot=%v",
					cfg.RunID, sinceActivity, snapshot)
				cancel()
				return
			}
		}
	}()

	return func() { close(done) }
}
