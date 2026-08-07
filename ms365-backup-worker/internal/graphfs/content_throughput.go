package graphfs

import (
	"errors"
	"time"
)

// ErrContentThroughputStall is returned when a Graph content stream delivers bytes
// below the configured minimum rate over the measurement window.
var ErrContentThroughputStall = errors.New("graph content throughput stall")

// IsContentThroughputStall reports whether err is ErrContentThroughputStall.
func IsContentThroughputStall(err error) bool {
	return errors.Is(err, ErrContentThroughputStall)
}

// ContentThroughputPolicy configures the minimum-throughput guard for large files.
type ContentThroughputPolicy struct {
	MinBytesPerSecond int64
	WindowSeconds     int
	MinFileSizeBytes  int64
}

// Enabled reports whether the throughput guard is active for a file of the given size.
func (p ContentThroughputPolicy) Enabled(fileSize int64) bool {
	if p.MinBytesPerSecond <= 0 || p.WindowSeconds <= 0 || p.MinFileSizeBytes <= 0 {
		return false
	}
	return fileSize >= p.MinFileSizeBytes
}

// throughputWindow tracks bytes read over a rolling window and detects slow streams.
type throughputWindow struct {
	policy    ContentThroughputPolicy
	now       func() time.Time
	windowEnd time.Time
	bytes     int64
}

func newThroughputWindow(policy ContentThroughputPolicy, now func() time.Time) *throughputWindow {
	if now == nil {
		now = time.Now
	}
	return &throughputWindow{policy: policy, now: now}
}

// add records n bytes read and returns true when the window has elapsed and average
// throughput is below the configured minimum. Callers must gate with policy.Enabled(fileSize).
func (w *throughputWindow) add(n int) bool {
	if w == nil || n <= 0 {
		return false
	}
	now := w.now()
	if w.windowEnd.IsZero() {
		w.windowEnd = now.Add(time.Duration(w.policy.WindowSeconds) * time.Second)
	}
	w.bytes += int64(n)
	if now.Before(w.windowEnd) {
		return false
	}
	elapsed := now.Sub(w.windowEnd.Add(-time.Duration(w.policy.WindowSeconds) * time.Second))
	if elapsed <= 0 {
		elapsed = time.Duration(w.policy.WindowSeconds) * time.Second
	}
	bps := float64(w.bytes) / elapsed.Seconds()
	w.bytes = 0
	w.windowEnd = now.Add(time.Duration(w.policy.WindowSeconds) * time.Second)
	return bps < float64(w.policy.MinBytesPerSecond)
}
