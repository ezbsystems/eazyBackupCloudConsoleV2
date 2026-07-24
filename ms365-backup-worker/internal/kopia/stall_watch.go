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
	StallSeconds           int
	CheckIntervalSeconds   int
	GraceSeconds           int
	RunID                  string
	RunDir                 string
	OnStall                func(snapshot map[string]any)
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
				snapshot := counter.DebugSnapshot()
				sinceHash, _ := snapshot["seconds_since_last_hash"].(int64)
				sinceUpload, _ := snapshot["seconds_since_last_upload"].(int64)
				filesDone := counter.FilesDone.Load()
				bytesUploaded := counter.BytesUploaded.Load()
				filesTotal := counter.FilesTotal.Load()
				if sinceHash < 0 || sinceUpload < 0 {
					continue
				}
				hashStalled := sinceHash >= int64(cfg.StallSeconds)
				uploadStalled := sinceUpload >= int64(cfg.StallSeconds)
				tailPhase := filesTotal > 0 && filesDone >= filesTotal
				var stalled bool
				if tailPhase {
					stalled = hashStalled || uploadStalled
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
