package jobs

import (
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
)

// stallAwareProgressFn wraps a heartbeat payload builder and sets NoProgress when
// items_done / bytes_hashed / bytes_uploaded are flat for stallSeconds.
// Rising graph_429_hits or graph_requests also counts as activity during throttling or enumeration.
func stallAwareProgressFn(stallSeconds int, getUpdate func() api.ProgressUpdate) func() api.ProgressUpdate {
	if stallSeconds <= 0 {
		return getUpdate
	}
	stall := time.Duration(stallSeconds) * time.Second
	var (
		lastChange  time.Time
		lastItems   int
		lastBytesH  int64
		lastBytesU  int64
		last429     int64
		lastReqs    int64
		initialized bool
	)
	return func() api.ProgressUpdate {
		upd := getUpdate()
		now := time.Now()
		changed := upd.ItemsDone != lastItems || upd.BytesHashed != lastBytesH || upd.BytesUploaded != lastBytesU
		throttleActivity := upd.Graph429Hits > last429
		requestActivity := upd.GraphRequests > lastReqs
		if !initialized || changed || throttleActivity || requestActivity {
			lastItems = upd.ItemsDone
			lastBytesH = upd.BytesHashed
			lastBytesU = upd.BytesUploaded
			last429 = upd.Graph429Hits
			lastReqs = upd.GraphRequests
			lastChange = now
			initialized = true
			return upd
		}
		if now.Sub(lastChange) >= stall {
			upd.NoProgress = true
		}
		return upd
	}
}
