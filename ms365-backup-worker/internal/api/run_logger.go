package api

import (
	"context"
	"fmt"
	"sync"
	"time"
)

// WorkerLogLine is posted to ms365_worker_log.php.
type WorkerLogLine struct {
	Level   string `json:"level"`
	Message string `json:"message"`
	Ts      int64  `json:"ts,omitempty"`
}

type runLogState struct {
	pending []WorkerLogLine
	window  int64
	count   int
}

// RunLog buffers worker diagnostic lines for a run and ships them to the control plane.
func (c *Client) RunLog(ctx context.Context, runID, level, message string) {
	runID = trim(runID)
	message = trim(message)
	if runID == "" || message == "" || c == nil {
		return
	}
	level = trim(level)
	if level == "" {
		level = "info"
	}
	if c.runLogMu == nil {
		c.runLogMu = &sync.Mutex{}
		c.runLogState = make(map[string]*runLogState)
	}
	now := time.Now()
	ts := now.Unix()
	c.runLogMu.Lock()
	st := c.runLogState[runID]
	if st == nil {
		st = &runLogState{}
		c.runLogState[runID] = st
	}
	sec := now.Unix()
	if st.window != sec {
		st.window = sec
		st.count = 0
	}
	if level == "info" && st.count >= 20 {
		c.runLogMu.Unlock()
		return
	}
	st.count++
	st.pending = append(st.pending, WorkerLogLine{
		Level:   level,
		Message: message,
		Ts:      ts,
	})
	shouldFlush := len(st.pending) >= 25
	c.runLogMu.Unlock()
	if shouldFlush {
		_ = c.FlushRunLogs(ctx, runID)
	}
}

// RunLogf formats and buffers a worker log line.
func (c *Client) RunLogf(ctx context.Context, runID, level, format string, args ...any) {
	c.RunLog(ctx, runID, level, fmt.Sprintf(format, args...))
}

// FlushRunLogs sends pending lines for a run.
func (c *Client) FlushRunLogs(ctx context.Context, runID string) error {
	runID = trim(runID)
	if runID == "" || c == nil || c.nodeID == "" {
		return nil
	}
	if c.runLogMu == nil {
		return nil
	}
	c.runLogMu.Lock()
	st := c.runLogState[runID]
	if st == nil || len(st.pending) == 0 {
		c.runLogMu.Unlock()
		return nil
	}
	batch := st.pending
	st.pending = nil
	c.runLogMu.Unlock()

	payload := map[string]any{
		"node_id": c.nodeID,
		"run_id":  runID,
		"lines":   batch,
	}
	return c.post(ctx, "ms365_worker_log.php", payload, &struct{}{})
}

func trim(s string) string {
	for len(s) > 0 && (s[0] == ' ' || s[0] == '\t' || s[0] == '\n') {
		s = s[1:]
	}
	for len(s) > 0 {
		last := s[len(s)-1]
		if last != ' ' && last != '\t' && last != '\n' {
			break
		}
		s = s[:len(s)-1]
	}
	return s
}
