package jobs

import (
	"context"
	"fmt"
	"log"
	"strings"
	"sync"
	"sync/atomic"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

type BatchRunner struct {
	cfg       *config.Config
	client    *api.Client
	runner    *Runner
	scheduler *Scheduler
}

func NewBatchRunner(cfg *config.Config, client *api.Client, runner *Runner, scheduler *Scheduler) *BatchRunner {
	return &BatchRunner{cfg: cfg, client: client, runner: runner, scheduler: scheduler}
}

type batchProgressHub struct {
	mu         sync.Mutex
	children   map[string]api.ProgressUpdate
	batchRunID string
	client     *api.Client
	tenantID   string
	minInterval time.Duration
	lastNano   atomic.Int64
}

func newBatchProgressHub(client *api.Client, batchRunID, tenantID string, minInterval time.Duration) *batchProgressHub {
	return &batchProgressHub{
		children:    make(map[string]api.ProgressUpdate),
		batchRunID:  batchRunID,
		client:      client,
		tenantID:    tenantID,
		minInterval: minInterval,
	}
}

func (h *batchProgressHub) record(upd api.ProgressUpdate) {
	if upd.RunID == "" {
		return
	}
	h.mu.Lock()
	h.children[upd.RunID] = upd
	h.mu.Unlock()
}

func (h *batchProgressHub) snapshot() []api.ProgressUpdate {
	h.mu.Lock()
	defer h.mu.Unlock()
	out := make([]api.ProgressUpdate, 0, len(h.children))
	for _, upd := range h.children {
		out = append(out, upd)
	}
	return out
}

func (h *batchProgressHub) sendThrottled(ctx context.Context, onAbort context.CancelFunc) {
	now := time.Now().UnixNano()
	last := h.lastNano.Load()
	if last != 0 && time.Duration(now-last) < h.minInterval {
		return
	}
	if !h.lastNano.CompareAndSwap(last, now) {
		return
	}
	children := h.snapshot()
	if len(children) == 0 {
		return
	}
	if cancel, budget, err := h.client.BatchProgress(ctx, api.BatchProgressUpdate{
		BatchRunID: h.batchRunID,
		Children:   children,
	}); err == nil {
		if h.tenantID != "" && budget > 0 {
			graph.SetTenantCeiling(h.tenantID, budget)
		}
		if cancel && onAbort != nil {
			onAbort()
		}
	}
}

func (h *batchProgressHub) flush(ctx context.Context, message string) {
	h.mu.Lock()
	for id, upd := range h.children {
		if upd.Message == "" {
			upd.Message = message
		}
		h.children[id] = upd
	}
	h.mu.Unlock()
	children := h.snapshot()
	if len(children) == 0 {
		return
	}
	flushCtx, cancel := context.WithTimeout(context.WithoutCancel(ctx), 15*time.Second)
	_, _, _ = h.client.BatchProgress(flushCtx, api.BatchProgressUpdate{
		BatchRunID: h.batchRunID,
		Children:   children,
	})
	cancel()
}

func hydrateChild(batch *api.BatchJob, child *api.RunJob) *api.RunJob {
	if batch == nil || child == nil {
		return child
	}
	if child.AzureTenantID == "" {
		child.AzureTenantID = batch.AzureTenantID
	}
	if child.TenantRecordID == 0 {
		child.TenantRecordID = batch.TenantRecordID
	}
	if child.WhmcsClientID == 0 {
		child.WhmcsClientID = batch.WhmcsClientID
	}
	if child.GraphToken == "" {
		child.GraphToken = batch.GraphToken
	}
	if child.GraphRegion == "" {
		child.GraphRegion = batch.GraphRegion
	}
	if child.DestEndpoint == "" {
		child.DestEndpoint = batch.DestEndpoint
	}
	if child.DestRegion == "" {
		child.DestRegion = batch.DestRegion
	}
	if child.DestBucket == "" {
		child.DestBucket = batch.DestBucket
	}
	if child.DestPrefix == "" {
		child.DestPrefix = batch.DestPrefix
	}
	if child.DestAccessKey == "" {
		child.DestAccessKey = batch.DestAccessKey
	}
	if child.DestSecretKey == "" {
		child.DestSecretKey = batch.DestSecretKey
	}
	if child.RepoPassword == "" {
		child.RepoPassword = batch.RepoPassword
	}
	if child.KopiaRepoID == "" {
		child.KopiaRepoID = batch.KopiaRepoID
	}
	if child.GraphTenantBudget == 0 && batch.GraphTenantBudget > 0 {
		child.GraphTenantBudget = batch.GraphTenantBudget
	}
	return child
}

func childAlreadySuccess(job *api.RunJob) bool {
	return job != nil && strings.EqualFold(strings.TrimSpace(job.Status), "success")
}

func (br *BatchRunner) Run(ctx context.Context, batch *api.BatchJob, onAbort context.CancelFunc) error {
	if batch == nil || strings.TrimSpace(batch.BatchRunID) == "" {
		return nil
	}
	batchRunID := strings.TrimSpace(batch.BatchRunID)
	log.Printf("starting batch %s tenant=%s children=%d", batchRunID, batch.AzureTenantID, len(batch.Children))

	gc := graph.NewClient(batch.GraphToken, batch.GraphRegion, graph.ClientOptions{
		MaxRetries:       br.cfg.Graph.MaxRetries,
		RetryBaseDelayMs: br.cfg.Graph.RetryBaseDelayMs,
		MaxConcurrency:   br.effectiveGraphParallel(batch),
		AdaptiveLimit:    br.cfg.Graph.AdaptiveEnabled(),
	})
	if batch.AzureTenantID != "" {
		gc.SetAzureTenantID(batch.AzureTenantID)
		if batch.GraphTenantBudget > 0 {
			graph.SetTenantCeiling(batch.AzureTenantID, batch.GraphTenantBudget)
		}
	}
	tokenRunID := batchRunID
	for _, child := range batch.Children {
		if child != nil && strings.TrimSpace(child.RunID) != "" {
			tokenRunID = strings.TrimSpace(child.RunID)
			break
		}
	}
	stopTokenRefresh := bindGraphTokenRefresh(ctx, br.cfg, br.client, gc, tokenRunID)
	defer stopTokenRefresh()

	hub := newBatchProgressHub(br.client, batchRunID, batch.AzureTenantID, br.cfg.ProgressMinInterval())
	emitProgress := func(upd api.ProgressUpdate) {
		hub.record(upd)
		hub.sendThrottled(ctx, onAbort)
	}

	onTenantBudget := func(budget int) {
		if batch.AzureTenantID == "" || budget <= 0 {
			return
		}
		graph.SetTenantCeiling(batch.AzureTenantID, budget)
		gc.SetTenantCeilingFromClient(budget)
		graph.LogTenantControllerState(batch.AzureTenantID, gc.RequestsTotal(), gc.ThrottleHits())
	}

	progressStop := br.client.StartBatchProgressHeartbeat(ctx, batchRunID, br.cfg.ProgressHeartbeat(), stallAwareBatchProgressFn(br.cfg.Worker.ProgressStallSeconds, func() api.BatchProgressUpdate {
		return api.BatchProgressUpdate{
			BatchRunID: batchRunID,
			Children:   hub.snapshot(),
		}
	}), onAbort, onTenantBudget)
	defer progressStop()

	brc := &batchRunContext{
		sharedGC:     gc,
		progressSink: emitProgress,
		completeSink: func(upd api.CompleteUpdate) error {
			return br.client.BatchComplete(ctx, api.BatchCompleteUpdate{
				BatchRunID: batchRunID,
				Children: []api.BatchChildResult{{
					RunID:      upd.RunID,
					Status:     "success",
					ManifestID: upd.ManifestID,
					ItemsDone:  upd.ItemsDone,
					ItemsTotal: upd.ItemsTotal,
					StatsJSON:  upd.StatsJSON,
				}},
			})
		},
		failSink: func(runID, message string) {
			_ = br.client.BatchComplete(ctx, api.BatchCompleteUpdate{
				BatchRunID: batchRunID,
				Children: []api.BatchChildResult{{
					RunID:   runID,
					Status:  "failed",
					Message: message,
				}},
			})
		},
	}
	batchCtx := withBatchRunContext(ctx, brc)

	pending := make([]*api.RunJob, 0, len(batch.Children))
	for _, child := range batch.Children {
		if child == nil {
			continue
		}
		child = hydrateChild(batch, child)
		if childAlreadySuccess(child) {
			log.Printf("batch %s skipping child %s (already success)", batchRunID, child.RunID)
			continue
		}
		pending = append(pending, child)
	}

	maxConcurrent := br.cfg.Worker.MaxConcurrentRuns
	if maxConcurrent <= 0 {
		maxConcurrent = 1
	}
	sem := make(chan struct{}, maxConcurrent)
	var wg sync.WaitGroup
	var firstErr atomic.Value

	for _, child := range pending {
		child := child
		wg.Add(1)
		go func() {
			defer wg.Done()
			sem <- struct{}{}
			defer func() { <-sem }()

			for !br.scheduler.tryReserve(child) {
				if ctx.Err() != nil {
					return
				}
				time.Sleep(250 * time.Millisecond)
			}
			defer br.scheduler.releaseReserve(child.RunID)

			childCtx, cancel := context.WithCancel(batchCtx)
			defer cancel()
			br.scheduler.registerRunCancel(child.RunID, cancel)
			defer br.scheduler.unregisterRunCancel(child.RunID)

			if err := br.runner.RunSafe(childCtx, child, cancel); err != nil {
				if isCooperativeCancel(err, childCtx) {
					return
				}
				firstErr.CompareAndSwap(nil, err)
			}
		}()
	}
	wg.Wait()

	if errVal := firstErr.Load(); errVal != nil {
		if err, ok := errVal.(error); ok {
			return err
		}
	}
	return nil
}

func (br *BatchRunner) FlushProgressCheckpoint(ctx context.Context, batchRunID, tenantID string, snapshot map[string]api.ProgressUpdate) {
	if len(snapshot) == 0 {
		return
	}
	children := make([]api.ProgressUpdate, 0, len(snapshot))
	for _, upd := range snapshot {
		if upd.Message == "" {
			upd.Message = "drain hand-off checkpoint"
		}
		children = append(children, upd)
	}
	flushCtx, cancel := context.WithTimeout(context.WithoutCancel(ctx), 15*time.Second)
	_, _, _ = br.client.BatchProgress(flushCtx, api.BatchProgressUpdate{
		BatchRunID: batchRunID,
		Children:   children,
	})
	cancel()
}

func (br *BatchRunner) effectiveGraphParallel(batch *api.BatchJob) int {
	parallel := br.cfg.Worker.GraphParallelRequests
	if batch != nil && batch.GraphTenantBudget > 0 && batch.GraphTenantBudget < parallel {
		parallel = batch.GraphTenantBudget
	}
	return parallel
}

func stallAwareBatchProgressFn(stallSeconds int, getUpdate func() api.BatchProgressUpdate) func() api.BatchProgressUpdate {
	if stallSeconds <= 0 {
		return getUpdate
	}
	// Reuse per-run stall tracking by flattening the first child update for stall detection.
	var lastItemsDone int
	var lastBytes int64
	var flatSince time.Time
	return func() api.BatchProgressUpdate {
		upd := getUpdate()
		if len(upd.Children) == 0 {
			return upd
		}
		child := upd.Children[0]
		if child.ItemsDone != lastItemsDone || child.BytesHashed != lastBytes || child.BytesUploaded != lastBytes {
			lastItemsDone = child.ItemsDone
			lastBytes = child.BytesHashed
			if child.BytesUploaded > lastBytes {
				lastBytes = child.BytesUploaded
			}
			flatSince = time.Time{}
			return upd
		}
		if flatSince.IsZero() {
			flatSince = time.Now()
			return upd
		}
		if time.Since(flatSince) >= time.Duration(stallSeconds)*time.Second {
			for i := range upd.Children {
				upd.Children[i].NoProgress = true
			}
		}
		return upd
	}
}

func (br *BatchRunner) RunSafe(ctx context.Context, batch *api.BatchJob, onAbort context.CancelFunc) (err error) {
	defer func() {
		if rec := recover(); rec != nil {
			err = fmt.Errorf("batch panic: %v", rec)
		}
	}()
	return br.Run(ctx, batch, onAbort)
}
