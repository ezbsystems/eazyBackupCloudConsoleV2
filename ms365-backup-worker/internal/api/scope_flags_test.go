package api

import (
	"encoding/json"
	"testing"
)

func TestScopeFlagsUnmarshalIgnoresMetadata(t *testing.T) {
	raw := `{"mail":true,"onedrive":true,"_drive_id":"b!abc","_shard":{"index":1}}`
	var job struct {
		Scope ScopeFlags `json:"scope"`
	}
	if err := json.Unmarshal([]byte(`{"scope":`+raw+`}`), &job); err != nil {
		t.Fatalf("unmarshal: %v", err)
	}
	if !job.Scope["mail"] || !job.Scope["onedrive"] {
		t.Fatalf("expected mail and onedrive true, got %#v", job.Scope)
	}
	if _, ok := job.Scope["_drive_id"]; ok {
		t.Fatalf("metadata key should be ignored: %#v", job.Scope)
	}
}

func TestScopeFlagsUnmarshalCoercesIntegers(t *testing.T) {
	raw := `{"tasks":1,"calendar":0}`
	var job struct {
		Scope ScopeFlags `json:"scope"`
	}
	if err := json.Unmarshal([]byte(`{"scope":`+raw+`}`), &job); err != nil {
		t.Fatalf("unmarshal: %v", err)
	}
	if !job.Scope["tasks"] || job.Scope["calendar"] {
		t.Fatalf("expected tasks true and calendar false, got %#v", job.Scope)
	}
}
