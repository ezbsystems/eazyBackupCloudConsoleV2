package agent

import (
	"sync"
	"time"

	"github.com/your-org/e3-backup-agent/internal/applog"
)

// HealthEmitter buffers and pushes non-run agent/tray health events to the
// server. It is the client-side counterpart of agent_push_agent_events.php.
//
// The emitter focuses on state transitions only: it remembers the last value
// for each "channel" (e.g. server connection state) so repeated identical
// events collapse to a single dedupe_key per minute. Volume is further capped
// server-side per agent per UTC day.
//
// Typical lifecycle:
//
//	em := NewHealthEmitter(client)
//	em.Start(stop)
//	em.ServiceStarted("1.2.3")
//	em.ServerConnectionState("connected")
//	...
//	em.Stop()
type HealthEmitter struct {
	client *Client

	mu       sync.Mutex
	queue    []AgentEvent
	lastSent map[string]time.Time // dedupe_key -> last enqueue time
	maxQueue int

	flushInterval time.Duration
	stopCh        chan struct{}
	wakeCh        chan struct{}
	disabled      bool // set true after ErrAgentVersionTooOld until next start
}

// NewHealthEmitter constructs a HealthEmitter using the provided API client.
// The client's User-Agent / agent_version are stamped on every push.
func NewHealthEmitter(client *Client) *HealthEmitter {
	return &HealthEmitter{
		client:        client,
		queue:         make([]AgentEvent, 0, 32),
		lastSent:      make(map[string]time.Time),
		maxQueue:      512,
		flushInterval: 30 * time.Second,
		wakeCh:        make(chan struct{}, 1),
	}
}

// Start launches the background flush goroutine.
func (e *HealthEmitter) Start(stop <-chan struct{}) {
	if e == nil {
		return
	}
	e.stopCh = make(chan struct{})
	go e.loop(stop)
}

// Stop signals the flush goroutine to exit and performs a final flush.
func (e *HealthEmitter) Stop() {
	if e == nil || e.stopCh == nil {
		return
	}
	close(e.stopCh)
	e.flushNow()
}

func (e *HealthEmitter) loop(stop <-chan struct{}) {
	t := time.NewTicker(e.flushInterval)
	defer t.Stop()
	for {
		select {
		case <-t.C:
			e.flushNow()
		case <-e.wakeCh:
			e.flushNow()
		case <-stop:
			e.flushNow()
			return
		case <-e.stopCh:
			return
		}
	}
}

// emit appends an event to the queue, applying a 60-second client-side dedupe
// per dedupe_key to keep traffic small even before the server-side dedupe.
func (e *HealthEmitter) emit(level, code, messageID, source, dedupeKey string, params map[string]any, urgent bool) {
	if e == nil || e.client == nil {
		return
	}
	e.mu.Lock()
	if e.disabled {
		e.mu.Unlock()
		return
	}
	if dedupeKey != "" {
		if last, ok := e.lastSent[dedupeKey]; ok && time.Since(last) < 60*time.Second {
			e.mu.Unlock()
			return
		}
		e.lastSent[dedupeKey] = time.Now()
	}
	if source == "" {
		source = "agent"
	}
	if level == "" {
		level = "info"
	}
	if messageID == "" {
		messageID = code
	}
	ev := AgentEvent{
		Source:     source,
		TS:         time.Now().UTC().Format(time.RFC3339),
		Level:      level,
		Code:       code,
		MessageID:  messageID,
		ParamsJSON: params,
		DedupeKey:  dedupeKey,
	}
	if len(e.queue) >= e.maxQueue {
		// Drop the oldest entry to bound memory.
		e.queue = e.queue[1:]
	}
	e.queue = append(e.queue, ev)
	e.mu.Unlock()

	if urgent {
		select {
		case e.wakeCh <- struct{}{}:
		default:
		}
	}
}

func (e *HealthEmitter) flushNow() {
	e.mu.Lock()
	if e.disabled || len(e.queue) == 0 {
		e.mu.Unlock()
		return
	}
	batch := e.queue
	e.queue = make([]AgentEvent, 0, 32)
	// Garbage-collect old dedupe entries (older than 5 minutes).
	for k, t := range e.lastSent {
		if time.Since(t) > 5*time.Minute {
			delete(e.lastSent, k)
		}
	}
	e.mu.Unlock()

	if err := e.client.PushAgentEvents(batch); err != nil {
		if err == ErrAgentVersionTooOld {
			e.mu.Lock()
			e.disabled = true
			e.queue = nil
			e.mu.Unlock()
			applog.Warnf("health", "agent version too old; suspending agent_events ingest until update")
			notifyVersionTooOld()
			return
		}
		applog.Warnf("health", "agent_events push failed: %v (requeueing %d events)", err, len(batch))
		// Best-effort requeue at the front (cap to max).
		e.mu.Lock()
		merged := append(batch, e.queue...)
		if len(merged) > e.maxQueue {
			merged = merged[len(merged)-e.maxQueue:]
		}
		e.queue = merged
		e.mu.Unlock()
	}
}

// --------------------------------------------------------------------------
// State-machine helpers. These give the rest of the agent a small, consistent
// API for emitting health/lifecycle events. Code constants intentionally use
// SCREAMING_SNAKE_CASE to match s3_cloudbackup_run_events conventions.
// --------------------------------------------------------------------------

func (e *HealthEmitter) ServiceStarted(version string) {
	e.emit("info", "AGENT_SERVICE_STARTED", "AGENT_SERVICE_STARTED", "agent",
		"agent:service:started",
		map[string]any{"version": version}, true)
}

func (e *HealthEmitter) ServiceStopping(reason string) {
	e.emit("info", "AGENT_SERVICE_STOPPING", "AGENT_SERVICE_STOPPING", "agent",
		"agent:service:stopping",
		map[string]any{"reason": reason}, true)
}

func (e *HealthEmitter) Enrolled(method string) {
	e.emit("info", "AGENT_ENROLLED", "AGENT_ENROLLED", "agent",
		"agent:enrolled",
		map[string]any{"method": method}, true)
}

func (e *HealthEmitter) EnrollmentFailed(reason string) {
	e.emit("error", "AGENT_ENROLLMENT_FAILED", "AGENT_ENROLLMENT_FAILED", "agent",
		"agent:enrollment:failed",
		map[string]any{"reason": reason}, true)
}

// ServerConnectionState records reachability transitions: "connected",
// "disconnected", "restored". The dedupe_key collapses chatter to one row per
// transition per minute.
func (e *HealthEmitter) ServerConnectionState(state string) {
	e.emit("info", "AGENT_SERVER_CONNECTION_"+upper(state), "AGENT_SERVER_CONNECTION", "agent",
		"agent:server_connection:"+state,
		map[string]any{"state": state}, true)
}

func (e *HealthEmitter) DeviceOnline() {
	e.emit("info", "AGENT_DEVICE_ONLINE", "AGENT_DEVICE_ONLINE", "agent",
		"agent:device:online", nil, true)
}

func (e *HealthEmitter) DeviceOffline(reason string) {
	e.emit("warn", "AGENT_DEVICE_OFFLINE", "AGENT_DEVICE_OFFLINE", "agent",
		"agent:device:offline",
		map[string]any{"reason": reason}, true)
}

func (e *HealthEmitter) CommandReceived(commandID int64, cmdType string) {
	e.emit("info", "AGENT_COMMAND_RECEIVED", "AGENT_COMMAND_RECEIVED", "agent", "",
		map[string]any{"command_id": commandID, "type": cmdType}, false)
}

func (e *HealthEmitter) CommandCompleted(commandID int64, cmdType, status string) {
	e.emit("info", "AGENT_COMMAND_COMPLETED", "AGENT_COMMAND_COMPLETED", "agent", "",
		map[string]any{"command_id": commandID, "type": cmdType, "status": status}, false)
}

func (e *HealthEmitter) CommandFailed(commandID int64, cmdType, errMsg string) {
	e.emit("error", "AGENT_COMMAND_FAILED", "AGENT_COMMAND_FAILED", "agent", "",
		map[string]any{"command_id": commandID, "type": cmdType, "error": errMsg}, true)
}

// RunStarted / RunFinished only carry the run_id; detailed events stay in
// s3_cloudbackup_run_events keyed by run_id.
func (e *HealthEmitter) RunStarted(runID, jobID string) {
	e.emit("info", "AGENT_RUN_STARTED", "AGENT_RUN_STARTED", "agent", "",
		map[string]any{"run_id": runID, "job_id": jobID}, true)
}

func (e *HealthEmitter) RunFinished(runID, status string) {
	e.emit("info", "AGENT_RUN_FINISHED", "AGENT_RUN_FINISHED", "agent", "",
		map[string]any{"run_id": runID, "status": status}, true)
}

// TrayStarted records that the tray UI process started.
func (e *HealthEmitter) TrayStarted(version string) {
	e.emit("info", "TRAY_STARTED", "TRAY_STARTED", "tray", "tray:started",
		map[string]any{"version": version}, true)
}

func (e *HealthEmitter) TrayStopping(reason string) {
	e.emit("info", "TRAY_STOPPING", "TRAY_STOPPING", "tray", "tray:stopping",
		map[string]any{"reason": reason}, true)
}

func upper(s string) string {
	out := make([]byte, len(s))
	for i := 0; i < len(s); i++ {
		c := s[i]
		if c >= 'a' && c <= 'z' {
			c -= 32
		}
		out[i] = c
	}
	return string(out)
}

// notifyVersionTooOld is wired up by main.go / tray to surface a UI banner.
// Default no-op so unit tests can run.
var notifyVersionTooOld = func() {}
