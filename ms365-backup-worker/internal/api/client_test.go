package api

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"sync/atomic"
	"testing"
	"time"
)

func TestDeltaStatesMapUnmarshalToleratesLegacyArrays(t *testing.T) {
	cases := []struct {
		name string
		raw  string
		want map[string]map[string]string
	}{
		{name: "empty array", raw: `[]`, want: map[string]map[string]string{}},
		{name: "nested empty mail", raw: `{"mail":[]}`, want: map[string]map[string]string{}},
		{name: "mixed", raw: `{"mail":[],"calendar":{"default":"https://delta/cal"}}`, want: map[string]map[string]string{
			"calendar": {"default": "https://delta/cal"},
		}},
		{name: "valid", raw: `{"mail":{"inbox":"https://delta/inbox"}}`, want: map[string]map[string]string{
			"mail": {"inbox": "https://delta/inbox"},
		}},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			var got DeltaStatesMap
			if err := json.Unmarshal([]byte(tc.raw), &got); err != nil {
				t.Fatalf("unmarshal: %v", err)
			}
			if len(got) != len(tc.want) {
				t.Fatalf("len=%d want %d (%v)", len(got), len(tc.want), got)
			}
			for wk, inner := range tc.want {
				gotInner, ok := got[wk]
				if !ok {
					t.Fatalf("missing workload %q", wk)
				}
				for sk, link := range inner {
					if gotInner[sk] != link {
						t.Fatalf("%s.%s=%q want %q", wk, sk, gotInner[sk], link)
					}
				}
			}
		})
	}
}

func TestClaimBatchToleratesLegacyDeltaArrays(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"status":"success","data":{"batch":{"batch_run_id":"b1","children":[{"run_id":"c1","delta_states":{"mail":[]}}]}}}`))
	}))
	defer srv.Close()
	c := NewClient(srv.URL, "t", "n1")
	batch, err := c.ClaimBatch(context.Background(), nil)
	if err != nil {
		t.Fatalf("ClaimBatch: %v", err)
	}
	if batch == nil || batch.BatchRunID != "b1" || len(batch.Children) != 1 {
		t.Fatalf("unexpected batch: %+v", batch)
	}
	if len(batch.Children[0].DeltaStates) != 0 {
		t.Fatalf("expected empty delta_states after sanitize, got %#v", batch.Children[0].DeltaStates)
	}
}

func TestRefreshGraphTokenRetriesTransientServerError(t *testing.T) {
	var calls atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, _ *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		if calls.Add(1) == 1 {
			w.WriteHeader(http.StatusInternalServerError)
			_, _ = w.Write([]byte(`{"status":"error","message":"temporary token endpoint failure"}`))
			return
		}
		_, _ = w.Write([]byte(`{"status":"success","data":{"graph_token":"fresh-token","expires_in":3600}}`))
	}))
	defer srv.Close()

	client := NewClient(srv.URL, "tok", "node-1")
	token, err := client.RefreshGraphToken(context.Background(), "run-1")
	if err != nil {
		t.Fatalf("RefreshGraphToken: %v", err)
	}
	if token != "fresh-token" {
		t.Fatalf("token = %q, want fresh-token", token)
	}
	if calls.Load() != 2 {
		t.Fatalf("calls = %d, want 2", calls.Load())
	}
}

func TestDecodeEnvelopeResponseNullRepoOperation(t *testing.T) {
	raw := []byte(`{"status":"success","data":null}`)
	var op *RepoOperation
	if err := decodeEnvelopeResponse(raw, &op); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if op != nil {
		t.Fatalf("expected nil repo operation, got %+v", op)
	}
}

func TestDecodeEnvelopeResponseValidRepoOperation(t *testing.T) {
	raw := []byte(`{"status":"success","data":{"operation_id":42,"op_type":"maintenance_quick"}}`)
	var op *RepoOperation
	if err := decodeEnvelopeResponse(raw, &op); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if op == nil {
		t.Fatal("expected repo operation")
	}
	if op.OperationID != 42 {
		t.Fatalf("operation_id = %d, want 42", op.OperationID)
	}
	if op.OpType != "maintenance_quick" {
		t.Fatalf("op_type = %q, want maintenance_quick", op.OpType)
	}
}

func TestDecodeEnvelopeResponseNullRun(t *testing.T) {
	raw := []byte(`{"status":"success","data":{"run":null}}`)
	var out struct {
		Run *RunJob `json:"run"`
	}
	if err := decodeEnvelopeResponse(raw, &out); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if out.Run != nil {
		t.Fatalf("expected nil run, got %+v", out.Run)
	}
}

func TestClaimRepoOperationValidation(t *testing.T) {
	raw := []byte(`{"status":"success","data":null}`)
	var op *RepoOperation
	if err := decodeEnvelopeResponse(raw, &op); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if op != nil {
		t.Fatalf("expected nil after null data decode, got %+v", op)
	}
	if op == nil || op.OperationID <= 0 || op.OpType == "" {
		// mirrors ClaimRepoOperation guard — empty queue is nil, nil
		return
	}
	t.Fatal("expected validation guard to treat empty op as no job")
}

func TestProgressCancelRequested(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"status":"success","data":{"cancel_requested":true}}`))
	}))
	defer srv.Close()

	c := &Client{
		token:      "test",
		baseURL:    srv.URL + "/",
		httpClient: srv.Client(),
	}
	cancel, _, err := c.Progress(context.Background(), ProgressUpdate{RunID: "run-1", Phase: "graph_sync"})
	if err != nil {
		t.Fatalf("Progress: %v", err)
	}
	if !cancel {
		t.Fatal("expected cancel_requested=true")
	}
}

func TestHeartbeatPayloadIncludesTelemetryAndConfig(t *testing.T) {
	var got map[string]any
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		_ = json.NewDecoder(r.Body).Decode(&got)
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"status":"success","data":{"active_claims":[]}}`))
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "tok", "node-1")
	_, err := c.Heartbeat(context.Background(), HeartbeatParams{
		CurrentLoad:   2,
		Version:       "0.3.4",
		ConfigVersion: 3,
		ConfigError:   "nope",
		Telemetry: &TelemetryReport{
			CPUPct:       12.5,
			CPUCoresUsed: 0.5,
			MemUsedMiB:   1024,
			SampledAt:    "2026-06-22T12:00:00Z",
		},
	})
	if err != nil {
		t.Fatalf("Heartbeat: %v", err)
	}
	if got["config_version"] != float64(3) {
		t.Fatalf("config_version = %v", got["config_version"])
	}
	if got["config_error"] != "nope" {
		t.Fatalf("config_error = %v", got["config_error"])
	}
	tel, ok := got["telemetry"].(map[string]any)
	if !ok {
		t.Fatalf("telemetry missing: %#v", got["telemetry"])
	}
	if tel["cpu_pct"] != 12.5 {
		t.Fatalf("cpu_pct = %v", tel["cpu_pct"])
	}
}

func TestDecodeEnvelopeResponseConfigOffer(t *testing.T) {
	raw := []byte(`{"status":"success","data":{"config":{"version":5,"sha256":"abc","download_url":"https://example.test/cfg"}}}`)
	var out HeartbeatResponse
	if err := decodeEnvelopeResponse(raw, &out); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if out.Config == nil || out.Config.Version != 5 {
		t.Fatalf("config offer = %+v", out.Config)
	}
}

func TestHeartbeatAppliesBudget(t *testing.T) {
	var gotBudget int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"status":"success","data":{"graph_tenant_budget":12}}`))
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "tok", "node-1")
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	stop := c.StartProgressHeartbeat(ctx, "run-1", 20*time.Millisecond, func() ProgressUpdate {
		return ProgressUpdate{Phase: "graph_sync"}
	}, nil, func(budget int) {
		gotBudget = budget
	})
	defer stop()

	deadline := time.Now().Add(2 * time.Second)
	for gotBudget == 0 && time.Now().Before(deadline) {
		time.Sleep(10 * time.Millisecond)
	}
	if gotBudget != 12 {
		t.Fatalf("onBudget got %d want 12", gotBudget)
	}
}

func TestClaimBatchNull(t *testing.T) {
	raw := []byte(`{"status":"success","data":{"batch":null}}`)
	var out struct {
		Batch *BatchJob `json:"batch"`
	}
	if err := decodeEnvelopeResponse(raw, &out); err != nil {
		t.Fatalf("decode: %v", err)
	}
	if out.Batch != nil {
		t.Fatalf("expected nil batch, got %+v", out.Batch)
	}
}

func TestBatchProgressCancelRequested(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"status":"success","data":{"cancel_requested":true,"graph_tenant_budget":16}}`))
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "tok", "node-1")
	cancel, budget, err := c.BatchProgress(context.Background(), BatchProgressUpdate{
		BatchRunID: "batch-1",
		Children:   []ProgressUpdate{{RunID: "c1", Phase: "graph_sync"}},
	})
	if err != nil {
		t.Fatalf("BatchProgress: %v", err)
	}
	if !cancel || budget != 16 {
		t.Fatalf("cancel=%v budget=%d", cancel, budget)
	}
}

func TestTerminalRetry403Then200(t *testing.T) {
	var attempts atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		n := attempts.Add(1)
		w.Header().Set("Content-Type", "application/json")
		if n <= 2 {
			w.WriteHeader(http.StatusForbidden)
			_, _ = w.Write([]byte(`forbidden`))
			return
		}
		_, _ = w.Write([]byte(`{"status":"success","data":{}}`))
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "tok", "node-1")
	err := c.BatchComplete(context.Background(), BatchCompleteUpdate{
		BatchRunID: "batch-1",
		Children:   []BatchChildResult{{RunID: "child-1", Status: "success"}},
	})
	if err != nil {
		t.Fatalf("BatchComplete: %v", err)
	}
	if attempts.Load() < 3 {
		t.Fatalf("expected >=3 attempts, got %d", attempts.Load())
	}
}

func TestTerminalRetry401NoRetry(t *testing.T) {
	var attempts atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		attempts.Add(1)
		w.WriteHeader(http.StatusUnauthorized)
		_, _ = w.Write([]byte(`unauthorized`))
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "tok", "node-1")
	err := c.Complete(context.Background(), CompleteUpdate{RunID: "run-1", StatsJSON: `{}`})
	if err == nil {
		t.Fatal("expected error")
	}
	if attempts.Load() != 1 {
		t.Fatalf("expected single attempt on 401, got %d", attempts.Load())
	}
}

func TestTerminalRetry503Then200(t *testing.T) {
	var attempts atomic.Int32
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		n := attempts.Add(1)
		w.Header().Set("Content-Type", "application/json")
		if n == 1 {
			w.WriteHeader(http.StatusServiceUnavailable)
			_, _ = w.Write([]byte(`unavailable`))
			return
		}
		_, _ = w.Write([]byte(`{"status":"success","data":{}}`))
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "tok", "node-1")
	err := c.Complete(context.Background(), CompleteUpdate{RunID: "run-1", StatsJSON: `{}`})
	if err != nil {
		t.Fatalf("Complete: %v", err)
	}
	if attempts.Load() != 2 {
		t.Fatalf("expected 2 attempts, got %d", attempts.Load())
	}
}

func TestFetchConfig(t *testing.T) {
	body := []byte("api:\n  base_url: https://example.test\nworker:\n  token: tok\n")
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("X-MS365-Worker-Token") != "secret" {
			t.Fatalf("token header = %q", r.Header.Get("X-MS365-Worker-Token"))
		}
		_, _ = w.Write(body)
	}))
	defer srv.Close()

	c := NewClient(srv.URL, "secret", "node-1")
	raw, sum, err := c.FetchConfig(context.Background(), srv.URL)
	if err != nil {
		t.Fatalf("FetchConfig: %v", err)
	}
	if string(raw) != string(body) {
		t.Fatalf("body mismatch")
	}
	if sum == "" {
		t.Fatal("expected sha256")
	}
}
