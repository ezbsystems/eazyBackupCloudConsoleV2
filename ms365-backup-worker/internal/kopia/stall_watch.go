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

// StartStallWatch monitors hashing progress during a Kopia snapshot and cancels
// the provided context when progress stalls for StallSeconds.
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
		var lastFilesDone int64 = -1
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
				filesDone := counter.FilesDone.Load()
				if sinceHash < 0 || sinceHash < int64(cfg.StallSeconds) {
					lastFilesDone = filesDone
					continue
				}
				if lastFilesDone >= 0 && filesDone != lastFilesDone {
					lastFilesDone = filesDone
					continue
				}
				if cfg.OnStall != nil {
					cfg.OnStall(snapshot)
				}
				if atomic.CompareAndSwapInt32(&dumped, 0, 1) {
					log.Printf("kopia stall watchdog run=%s since_hash=%ds files_done=%d snapshot=%v",
						cfg.RunID, sinceHash, filesDone, snapshot)
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
