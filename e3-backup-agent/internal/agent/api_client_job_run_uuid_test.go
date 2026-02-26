package agent

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestRunUpdateUsesUUIDString(t *testing.T) {
	src, err := os.ReadFile(filepath.Join("internal", "agent", "api_client.go"))
	if err != nil {
		src, err = os.ReadFile("api_client.go")
		if err != nil {
			t.Fatal(err)
		}
	}
	text := string(src)
	if strings.Contains(text, "RunID int64") && strings.Contains(text, "json:\"run_id\"") {
		t.Fatalf("legacy numeric RunID contract still present")
	}
	if !strings.Contains(text, "RunID") || !strings.Contains(text, "json:\"run_id\"") {
		t.Fatalf("expected RunID with run_id json tag")
	}
	// Verify RunUpdate.RunID is string
	if !strings.Contains(text, "RunUpdate struct") {
		t.Fatalf("RunUpdate struct not found")
	}
	// Check that RunID in RunUpdate is string (not int64)
	runUpdateIdx := strings.Index(text, "type RunUpdate struct")
	if runUpdateIdx < 0 {
		t.Fatalf("RunUpdate struct not found")
	}
	runUpdateBlock := text[runUpdateIdx : runUpdateIdx+600]
	if strings.Contains(runUpdateBlock, "RunID                int64") {
		t.Fatalf("RunUpdate.RunID must be string, not int64")
	}
	if !strings.Contains(runUpdateBlock, "RunID                string") {
		t.Fatalf("expected RunUpdate.RunID string contract")
	}
}

func TestNextRunResponseUsesUUIDStrings(t *testing.T) {
	src, err := os.ReadFile(filepath.Join("internal", "agent", "api_client.go"))
	if err != nil {
		src, err = os.ReadFile("api_client.go")
		if err != nil {
			t.Fatal(err)
		}
	}
	text := string(src)
	nextRunIdx := strings.Index(text, "type NextRunResponse struct")
	if nextRunIdx < 0 {
		t.Fatalf("NextRunResponse struct not found")
	}
	block := text[nextRunIdx : nextRunIdx+400]
	if strings.Contains(block, "RunID                   int64") || strings.Contains(block, "JobID                   int64") {
		t.Fatalf("NextRunResponse must use string for RunID and JobID, not int64")
	}
	if !strings.Contains(block, "RunID                   string") || !strings.Contains(block, "JobID                   string") {
		t.Fatalf("expected NextRunResponse RunID and JobID as string with json run_id/job_id tags")
	}
}

func TestJSONUnmarshalHandlesUUIDStringRunIDJobID(t *testing.T) {
	// NextRunResponse must unmarshal run_id and job_id as UUID strings
	body := `{"run_id":"018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d6","job_id":"018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d7","engine":"kopia"}`
	var r NextRunResponse
	if err := json.Unmarshal([]byte(body), &r); err != nil {
		t.Fatalf("unmarshal NextRunResponse: %v", err)
	}
	if r.RunID != "018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d6" {
		t.Fatalf("RunID: got %q want UUID string", r.RunID)
	}
	if r.JobID != "018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d7" {
		t.Fatalf("JobID: got %q want UUID string", r.JobID)
	}
}

func TestJSONUnmarshalPendingCommandHandlesUUIDRunIDJobID(t *testing.T) {
	body := `{"command_id":1,"type":"restore","run_id":"018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d6","job_id":"018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d7","payload":{}}`
	var c PendingCommand
	if err := json.Unmarshal([]byte(body), &c); err != nil {
		t.Fatalf("unmarshal PendingCommand: %v", err)
	}
	if c.RunID != "018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d6" {
		t.Fatalf("RunID: got %q want UUID string", c.RunID)
	}
	if c.JobID != "018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d7" {
		t.Fatalf("JobID: got %q want UUID string", c.JobID)
	}
}

func TestRunUpdateMarshalSendsRunIDAsString(t *testing.T) {
	u := RunUpdate{
		RunID:  "018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d6",
		Status: "running",
	}
	b, err := json.Marshal(u)
	if err != nil {
		t.Fatalf("marshal RunUpdate: %v", err)
	}
	var m map[string]any
	if err := json.Unmarshal(b, &m); err != nil {
		t.Fatalf("unmarshal as map: %v", err)
	}
	rid, ok := m["run_id"]
	if !ok {
		t.Fatalf("run_id not in payload")
	}
	if s, ok := rid.(string); !ok || s != "018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d6" {
		t.Fatalf("run_id must be string in JSON, got %T %v", rid, rid)
	}
}

func TestPushEventsPayloadSendsRunIDAsString(t *testing.T) {
	// buildPushEventsPayload sends run_id as string in the outbound JSON.
	runID := "018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d6"
	events := []RunEvent{{Type: "info", MessageID: "TEST"}}
	payload, _, err := buildPushEventsPayload(runID, events, events, false)
	if err != nil {
		t.Fatalf("buildPushEventsPayload: %v", err)
	}
	rid, ok := payload["run_id"]
	if !ok {
		t.Fatalf("run_id not in push events payload")
	}
	if s, ok := rid.(string); !ok || s != runID {
		t.Fatalf("run_id must be string in push events payload, got %T %v", rid, rid)
	}
}
