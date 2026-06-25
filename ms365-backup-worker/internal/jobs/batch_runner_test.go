package jobs

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/api"
	"github.com/eazybackup/ms365-backup-worker/internal/config"
	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

func TestChildAlreadySuccess(t *testing.T) {
	if !childAlreadySuccess(&api.RunJob{Status: "success"}) {
		t.Fatal("expected success child to skip")
	}
	if childAlreadySuccess(&api.RunJob{Status: "running"}) {
		t.Fatal("expected running child to run")
	}
}

func TestHydrateChildInheritsBatchContext(t *testing.T) {
	batch := &api.BatchJob{
		AzureTenantID:     "tenant-a",
		GraphToken:        "tok",
		GraphRegion:       "GlobalPublicCloud",
		DestBucket:        "bucket",
		GraphTenantBudget: 12,
		TenantRecordID:    7,
	}
	child := hydrateChild(batch, &api.RunJob{RunID: "child-1"})
	if child.AzureTenantID != "tenant-a" || child.GraphToken != "tok" || child.DestBucket != "bucket" {
		t.Fatalf("hydrated child missing batch fields: %+v", child)
	}
	if child.GraphTenantBudget != 12 {
		t.Fatalf("graph budget = %d", child.GraphTenantBudget)
	}
}

func TestBatchProgressHubCoalesces(t *testing.T) {
	var posts atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		posts.Add(1)
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"status":"success","data":{}}`))
	}))
	defer srv.Close()

	client := api.NewClient(srv.URL, "tok", "node-1")
	hub := newBatchProgressHub(client, "batch-1", "tenant-a", 200*time.Millisecond)
	hub.record(api.ProgressUpdate{RunID: "c1", Phase: "graph_sync", Percent: 1})
	hub.record(api.ProgressUpdate{RunID: "c2", Phase: "graph_sync", Percent: 2})

	ctx := context.Background()
	hub.sendThrottled(ctx, nil)
	hub.sendThrottled(ctx, nil)
	if posts.Load() != 1 {
		t.Fatalf("expected 1 coalesced POST, got %d", posts.Load())
	}

	time.Sleep(250 * time.Millisecond)
	hub.sendThrottled(ctx, nil)
	if posts.Load() != 2 {
		t.Fatalf("expected second POST after interval, got %d", posts.Load())
	}
}

func TestBatchRunnerSkipsSuccessChildren(t *testing.T) {
	var completed []string
	var mu sync.Mutex
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.Contains(r.URL.Path, "batch_complete"):
			var body api.BatchCompleteUpdate
			_ = json.NewDecoder(r.Body).Decode(&body)
			mu.Lock()
			for _, child := range body.Children {
				completed = append(completed, child.RunID)
			}
			mu.Unlock()
		default:
			_, _ = w.Write([]byte(`{"status":"success","data":{}}`))
		}
	}))
	defer srv.Close()

	cfg := testBatchConfig(t)
	client := api.NewClient(srv.URL, "tok", "node-1")
	scheduler := NewScheduler(cfg, client, t.TempDir()+"/config.yaml")
	runner := NewRunner(cfg, client, scheduler.repoPool)
	br := NewBatchRunner(cfg, client, runner, scheduler)

	batch := &api.BatchJob{
		BatchRunID:    "batch-1",
		AzureTenantID: "tenant-a",
		GraphToken:    "tok",
		GraphRegion:   "GlobalPublicCloud",
		Children: []*api.RunJob{
			{RunID: "done-1", Status: "success", PhysicalKey: "mailbox:u1"},
			{RunID: "pending-1", Status: "queued", PhysicalKey: "mailbox:u2", JobType: "backup"},
		},
	}

	// pending child will fail fast without real graph/kopia; we only assert skip behavior.
	_ = br.Run(context.Background(), batch, nil)

	mu.Lock()
	defer mu.Unlock()
	for _, id := range completed {
		if id == "done-1" {
			t.Fatal("success child should have been skipped")
		}
	}
}

func TestBatchRunnerSingleTenantController(t *testing.T) {
	graph.ResetTenantControllerForTest("tenant-single")
	defer graph.ResetTenantControllerForTest("tenant-single")

	cfg := testBatchConfig(t)
	client := api.NewClient("http://example.test", "tok", "node-1")
	scheduler := NewScheduler(cfg, client, t.TempDir()+"/config.yaml")
	br := NewBatchRunner(cfg, client, scheduler.runner, scheduler)

	batch := &api.BatchJob{
		BatchRunID:        "batch-ctrl",
		AzureTenantID:     "tenant-single",
		GraphToken:        "tok",
		GraphRegion:       "GlobalPublicCloud",
		GraphTenantBudget: 8,
		Children:          []*api.RunJob{{RunID: "c1", PhysicalKey: "mailbox:a"}},
	}

	// Run will fail on network, but graph client + ceiling should be initialized once.
	ctx, cancel := context.WithTimeout(context.Background(), 100*time.Millisecond)
	defer cancel()
	_ = br.Run(ctx, batch, nil)

	// A second batch runner reusing the tenant should hit the same controller map entry.
	br2 := NewBatchRunner(cfg, client, scheduler.runner, scheduler)
	_ = br2.Run(ctx, batch, nil)
}

func TestSchedulerCurrentLoadWithBatch(t *testing.T) {
	cfg := testBatchConfig(t)
	s := NewScheduler(cfg, api.NewClient("http://example.test", "tok", ""), t.TempDir()+"/config.yaml")
	s.batchMu.Lock()
	s.activeBatchID = "batch-abc"
	s.batchMu.Unlock()
	if s.currentLoad() != 1 {
		t.Fatalf("expected load 1 while batch active, got %d", s.currentLoad())
	}
	if s.availableSlots() != 0 {
		t.Fatalf("expected no free slots while batch active, got %d", s.availableSlots())
	}
}

func TestSchedulerDrainReleasesBatch(t *testing.T) {
	var released atomic.Bool
	var progressBodies []api.BatchProgressUpdate
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		switch {
		case strings.Contains(r.URL.Path, "batch_release"):
			released.Store(true)
			_, _ = w.Write([]byte(`{"status":"success"}`))
		case strings.Contains(r.URL.Path, "batch_progress"):
			var body api.BatchProgressUpdate
			_ = json.NewDecoder(r.Body).Decode(&body)
			progressBodies = append(progressBodies, body)
			_, _ = w.Write([]byte(`{"status":"success","data":{}}`))
		default:
			_, _ = w.Write([]byte(`{"status":"success","data":{}}`))
		}
	}))
	defer srv.Close()

	cfg := testBatchConfig(t)
	client := api.NewClient(srv.URL, "tok", "node-1")
	s := NewScheduler(cfg, client, t.TempDir()+"/config.yaml")
	s.batchMu.Lock()
	s.activeBatchID = "batch-drain"
	s.batchMu.Unlock()
	s.recordRunProgress("child-1", api.ProgressUpdate{
		RunID:                 "child-1",
		Phase:                 "graph_sync",
		CheckpointDeltaStates: map[string]map[string]string{"mail": {"delta": "link"}},
	})

	s.releaseAllActiveClaims(context.Background(), "drain")
	if !released.Load() {
		t.Fatal("expected BatchRelease on drain")
	}
	if len(progressBodies) == 0 {
		t.Fatal("expected drain checkpoint batch progress flush")
	}
	found := false
	for _, child := range progressBodies[0].Children {
		if child.RunID == "child-1" && child.CheckpointDeltaStates != nil {
			found = true
		}
	}
	if !found {
		t.Fatalf("checkpoint not preserved in drain flush: %+v", progressBodies)
	}
}

func TestClaimBatchDecode(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"status":"success","data":{"batch":{"batch_run_id":"b1","azure_tenant_id":"t1","children":[{"run_id":"c1"}]}}}`))
	}))
	defer srv.Close()

	client := api.NewClient(srv.URL, "tok", "node-1")
	batch, err := client.ClaimBatch(context.Background(), nil)
	if err != nil {
		t.Fatalf("ClaimBatch: %v", err)
	}
	if batch == nil || batch.BatchRunID != "b1" || len(batch.Children) != 1 {
		t.Fatalf("batch = %+v", batch)
	}
}

func testBatchConfig(t *testing.T) *config.Config {
	cfg := &config.Config{}
	cfg.Worker.MaxConcurrentRuns = 2
	cfg.Worker.RamBudgetMiB = 8192
	cfg.Worker.DiskBudgetMiB = 8192
	cfg.Worker.MaxCPUCores = 4
	cfg.Worker.JobRamBudgetMiB = 256
	cfg.Worker.JobDiskBudgetMiB = 256
	cfg.Worker.RunDir = t.TempDir()
	cfg.Worker.GraphParallelRequests = 4
	cfg.Worker.ProgressHeartbeatSeconds = 60
	cfg.Worker.ProgressMinIntervalSeconds = 1
	cfg.Graph.MaxRetries = 1
	cfg.Graph.RetryBaseDelayMs = 1
	cfg.Kopia.RepoConfigDir = t.TempDir()
	return cfg
}
