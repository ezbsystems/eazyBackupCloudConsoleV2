package jobs

import (
	"context"
	"encoding/json"
	"log"
	"strings"
	"time"

	"github.com/your-org/e3-cloudbackup-worker/internal/db"
)

type eventEmitter struct {
	db                *db.Database
	runID             int64
	maxPerRun         int
	progressInterval  time.Duration
	lastProgressEmit  time.Time
	lastProgressPct   float64
	eventsInserted    int
	truncatedNotified bool
}

func newEventEmitter(database *db.Database, runID int64, maxPerRun int, progressIntervalSeconds int) *eventEmitter {
	if maxPerRun <= 0 {
		maxPerRun = 5000
	}
	if progressIntervalSeconds <= 0 {
		progressIntervalSeconds = 2
	}
	return &eventEmitter{
		db:               database,
		runID:            runID,
		maxPerRun:        maxPerRun,
		progressInterval: time.Duration(progressIntervalSeconds) * time.Second,
	}
}

func (e *eventEmitter) canInsertMore() bool {
	return e.eventsInserted < e.maxPerRun
}

func (e *eventEmitter) insert(ctx context.Context, typ, level, code, messageID string, params map[string]any) {
	if !e.canInsertMore() {
		if !e.truncatedNotified {
			e.truncatedNotified = true
			_ = e.db.InsertRunEvent(ctx, e.runID, time.Now().UTC(), "warning", "info", "LOG_TRUNCATED", "LOG_TRUNCATED", `{}`)
		}
		return
	}
	now := time.Now().UTC()
	b, _ := json.Marshal(params)
	if err := e.db.InsertRunEvent(ctx, e.runID, now, typ, level, code, messageID, string(b)); err != nil {
		log.Printf("run %d: failed to insert event (%s/%s): %v", e.runID, typ, code, err)
		return
	}
	e.eventsInserted++
}

func (e *eventEmitter) EmitStart(ctx context.Context, sourceBucket, sourcePrefix, destBucket, destPrefix string) {
	params := map[string]any{
		"source_bucket":       sourceBucket,
		"source_prefix_trunc": truncatePrefix(sourcePrefix),
		"dest_bucket":         destBucket,
		"dest_prefix_trunc":   truncatePrefix(destPrefix),
	}
	e.insert(ctx, "start", "info", "BACKUP_STARTING", "BACKUP_STARTING", params)
}

func (e *eventEmitter) MaybeEmitProgress(ctx context.Context, p db.Progress) {
	now := time.Now()
	if now.Sub(e.lastProgressEmit) < e.progressInterval {
		return
	}
	// Emit if pct delta >= 1% or bytes changed significantly
	if absFloat(p.ProgressPct-e.lastProgressPct) < 1.0 && p.BytesTransferred == 0 {
		return
	}
	params := map[string]any{
		"files_done":  p.ObjectsTransferred,
		"files_total": p.ObjectsTotal,
		"bytes_done":  p.BytesTransferred,
		"bytes_total": p.BytesTotal,
		"pct":         clampPct(p.ProgressPct),
		"speed_bps":   p.SpeedBytesPerSec,
		"eta_seconds": p.EtaSeconds,
	}
	e.insert(ctx, "progress", "info", "PROGRESS_UPDATE", "PROGRESS_UPDATE", params)
	e.lastProgressEmit = now
	e.lastProgressPct = p.ProgressPct
}

func (e *eventEmitter) EmitNoChanges(ctx context.Context) {
	e.insert(ctx, "summary", "info", "NO_CHANGES", "NO_CHANGES", map[string]any{})
}

func (e *eventEmitter) EmitSummary(ctx context.Context, status string, files, bytes int64, durationSeconds int64) {
	params := map[string]any{
		"status":            status,
		"files_transferred": files,
		"bytes_transferred": bytes,
		"duration_seconds":  durationSeconds,
	}
	code := "COMPLETED_SUCCESS"
	if status == "warning" {
		code = "COMPLETED_WARNING"
	} else if status == "failed" {
		code = "COMPLETED_FAILED"
	}
	e.insert(ctx, "summary", "info", code, code, params)
}

func (e *eventEmitter) EmitCancelled(ctx context.Context) {
	e.insert(ctx, "cancelled", "warn", "CANCELLED", "CANCELLED", map[string]any{})
}

func (e *eventEmitter) EmitError(ctx context.Context, code string, detailCode string) {
	params := map[string]any{
		"kind":        strings.ToLower(strings.TrimPrefix(code, "ERROR_")),
		"detail_code": detailCode,
	}
	e.insert(ctx, "error", "error", code, code, params)
}

func truncatePrefix(p string) string {
	s := strings.Trim(p, "/")
	return s
}

func clampPct(v float64) float64 {
	if v < 0 {
		return 0
	}
	if v > 100 {
		return 100
	}
	return v
}

func absFloat(v float64) float64 {
	if v < 0 {
		return -v
	}
	return v
}

// mapErrorCode inspects a textual excerpt to classify a user-facing error code and detail.
func mapErrorCode(excerpt string) (string, string) {
	low := strings.ToLower(excerpt)
	switch {
	case strings.Contains(low, "connect: connection refused"),
		strings.Contains(low, "i/o timeout"),
		strings.Contains(low, "timeout"),
		strings.Contains(low, "tls handshake timeout"),
		strings.Contains(low, "temporary failure"),
		strings.Contains(low, "temporary network"):
		return "ERROR_NETWORK", "connect_refused_or_timeout"
	case strings.Contains(low, "token expired") && strings.Contains(low, "no refresh token"):
		return "ERROR_AUTH", "token_expired_no_refresh"
	case strings.Contains(low, "accessdenied") || strings.Contains(low, "permission denied"):
		return "ERROR_PERMISSION", "permission_denied"
	case strings.Contains(low, "nosuchkey") || strings.Contains(low, "path not found"):
		return "ERROR_NOT_FOUND", "path_not_found"
	case strings.Contains(low, "slowdown") || strings.Contains(low, "rate limit") || strings.Contains(low, "rate-limit"):
		return "ERROR_RATE_LIMIT", "rate_limited"
	case strings.Contains(low, "quota") && strings.Contains(low, "exceed"):
		return "ERROR_QUOTA", "quota_exceeded"
	default:
		return "ERROR_INTERNAL", "unknown_error"
	}
}
