package kopia

import (
	"context"
	"log"
	"os"
	"runtime"
	"runtime/debug"
	"sync/atomic"
	"time"
)

// StallWatchConfig controls worker-side detection of wedged Kopia uploads.
type StallWatchConfig struct {
	StallSeconds         int
	CheckIntervalSeconds int
	GraceSeconds         int
	RunID                string
	RunDir               string
	ReportedItemsTotal   int64
	// GraphEnumerationComplete is true when Graph sync finished (items_done >= items_total)
	// before Kopia upload. Kopia FilesDone can lag graph item counts; without this flag
	// the watchdog stays in mid-upload AND mode and parallel hash progress masks wedges.
	GraphEnumerationComplete bool
	OnStall                  func(snapshot map[string]any)
}

// StartStallWatch monitors hashing and upload progress during a Kopia snapshot
// and cancels the provided context when progress is flat for StallSeconds.
// Mid-upload uses AND semantics; upload tail (files_done >= files_total) uses OR.
func StartStallWatch(ctx context.Context, cancel context.CancelFunc, counter *ProgressCounter, cfg StallWatchConfig) func() {
	if cancel == nil || counter == nil || cfg.StallSeconds <= 0 {
		return func() {}
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
	var dumped int32
	var lastUploadBytes int64
	var lastUploadBytesChangeAt int64

	go func() {
		ticker := time.NewTicker(time.Duration(interval) * time.Second)
		defer ticker.Stop()
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
				// Repository acquisition can legitimately spend many minutes loading
				// fragmented indexes on a cold worker. It is not an upload stall:
				// Kopia calls UploadStarted only after repo open/policy/listing finish.
				if !counter.IsUploadStarted() {
					continue
				}
				snapshot := counter.SafeDebugSnapshot()
				sinceHash, _ := snapshot["seconds_since_last_hash"].(int64)
				sinceUpload, _ := snapshot["seconds_since_last_upload"].(int64)
				filesDone := counter.FilesDone.Load()
				bytesUploaded := counter.BytesUploaded.Load()
				filesTotal := counter.FilesTotal.Load()
				if sinceHash < 0 || sinceUpload < 0 {
					continue
				}
				nowNano := time.Now().UnixNano()
				if bytesUploaded > lastUploadBytes {
					lastUploadBytes = bytesUploaded
					lastUploadBytesChangeAt = nowNano
				} else if lastUploadBytesChangeAt == 0 {
					lastUploadBytesChangeAt = nowNano
				}
				bytesStalled := false
				if lastUploadBytesChangeAt > 0 {
					bytesStalled = time.Since(time.Unix(0, lastUploadBytesChangeAt)) >= time.Duration(cfg.StallSeconds)*time.Second
				}
				effectiveTotal := filesTotal
				if cfg.ReportedItemsTotal > effectiveTotal {
					effectiveTotal = cfg.ReportedItemsTotal
				}
				hashStalled := sinceHash >= int64(cfg.StallSeconds)
				uploadStalled := sinceUpload >= int64(cfg.StallSeconds)
				tailPhase := cfg.GraphEnumerationComplete ||
					(effectiveTotal > 0 && filesDone >= effectiveTotal)
				var stalled bool
				if tailPhase {
					stalled = hashStalled || uploadStalled || bytesStalled
				} else {
					stalled = hashStalled && uploadStalled
				}
				if !stalled {
					continue
				}
				if cfg.OnStall != nil {
					cfg.OnStall(snapshot)
				}
				if atomic.CompareAndSwapInt32(&dumped, 0, 1) {
					log.Printf("kopia stall watchdog run=%s since_hash=%ds since_upload=%ds files_done=%d bytes_uploaded=%d snapshot=%v",
						cfg.RunID, sinceHash, sinceUpload, filesDone, bytesUploaded, snapshot)
					if cfg.RunDir != "" {
						dumpPath := cfg.RunDir + "/kopia_stall_dump.txt"
						buf := make([]byte, 1<<20)
						n := runtime.Stack(buf, true)
						body := "kopia upload stall\n" + string(debug.Stack()) + "\n\n" + string(buf[:n])
						_ = writeStallDump(dumpPath, body)
					}
				}
				cancel()
				return
			}
		}
	}()

	return func() { close(done) }
}

func writeStallDump(path, body string) error {
	return os.WriteFile(path, []byte(body), 0o600)
}
