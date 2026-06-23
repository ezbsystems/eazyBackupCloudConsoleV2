package jobs

import (
	"encoding/json"
	"testing"
)

func TestCollectSkippedWorkloads(t *testing.T) {
	stats := map[string]any{
		"mail": map[string]any{"Folders": 3, "Messages": 10},
		"tasks": map[string]any{
			"skipped": "mailbox_not_enabled",
		},
		"sharepoint": map[string]any{
			"skipped": "access_denied",
		},
		"graph_429_hits": int64(2),
	}
	got := collectSkippedWorkloads(stats)
	if len(got) != 2 {
		t.Fatalf("skipped workloads = %#v, want 2 entries", got)
	}
	if got["tasks"] != "mailbox_not_enabled" {
		t.Fatalf("tasks skip = %q", got["tasks"])
	}
	if got["sharepoint"] != "access_denied" {
		t.Fatalf("sharepoint skip = %q", got["sharepoint"])
	}
}

func TestCompletionItemCountsEqual(t *testing.T) {
	for _, count := range []int{0, 1, 226, 999} {
		done, total := completionItemCounts(count)
		if done != count || total != count {
			t.Fatalf("completionItemCounts(%d) = (%d,%d), want equal pair", count, done, total)
		}
	}
}

func TestMergeCompletionStatsIncludesSkippedWorkloads(t *testing.T) {
	workloads := map[string]any{
		"mail":  map[string]any{"Messages": 226},
		"tasks": map[string]any{"skipped": "mailbox_not_enabled"},
	}
	merged := mergeCompletionStats(map[string]any{
		"files": 226,
	}, workloads)
	raw, ok := merged["skipped_workloads"].(map[string]string)
	if !ok {
		t.Fatalf("skipped_workloads missing: %#v", merged["skipped_workloads"])
	}
	if raw["tasks"] != "mailbox_not_enabled" {
		t.Fatalf("tasks skip = %q", raw["tasks"])
	}
	if _, ok := raw["mail"]; ok {
		t.Fatal("mail should not appear in skipped_workloads")
	}

	encoded, err := json.Marshal(merged)
	if err != nil {
		t.Fatalf("marshal: %v", err)
	}
	var decoded map[string]any
	if err := json.Unmarshal(encoded, &decoded); err != nil {
		t.Fatalf("unmarshal: %v", err)
	}
	skipped, ok := decoded["skipped_workloads"].(map[string]any)
	if !ok || skipped["tasks"] != "mailbox_not_enabled" {
		t.Fatalf("decoded skipped_workloads = %#v", decoded["skipped_workloads"])
	}
}
