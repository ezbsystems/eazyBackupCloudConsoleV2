package agent

import (
	"encoding/json"
	"runtime"
	"strconv"
	"strings"
)

type RestorePolicy struct {
	ParallelWorkers int
	SegmentSizeBytes int64
	KopiaParallel    int
}

func parseRestorePolicy(policyJSON map[string]any) RestorePolicy {
	workers := runtime.NumCPU()
	if workers < 8 {
		workers = 8
	}
	if workers > 16 {
		workers = 16
	}

	segmentMB := int64(32)
	kopiaParallel := workers
	if kopiaParallel < 2 {
		kopiaParallel = 2
	}
	if kopiaParallel > 16 {
		kopiaParallel = 16
	}

	if policyJSON != nil {
		if v, ok := readPolicyInt(policyJSON, "restore_parallel_workers"); ok {
			workers = clampInt(v, 1, 64)
		}
		if v, ok := readPolicyInt(policyJSON, "restore_segment_size_mb"); ok {
			segmentMB = clampInt64(int64(v), 8, 1024)
		}
		if v, ok := readPolicyInt(policyJSON, "restore_kopia_parallel"); ok {
			kopiaParallel = clampInt(v, 1, 32)
		}
	}

	return RestorePolicy{
		ParallelWorkers: workers,
		SegmentSizeBytes: segmentMB * 1024 * 1024,
		KopiaParallel:    kopiaParallel,
	}
}

func readPolicyInt(policy map[string]any, key string) (int, bool) {
	raw, ok := policy[key]
	if !ok || raw == nil {
		return 0, false
	}
	switch v := raw.(type) {
	case int:
		return v, true
	case int64:
		return int(v), true
	case float64:
		return int(v), true
	case json.Number:
		if i, err := v.Int64(); err == nil {
			return int(i), true
		}
	case string:
		trimmed := strings.TrimSpace(v)
		if trimmed == "" {
			return 0, false
		}
		if i, err := strconv.Atoi(trimmed); err == nil {
			return i, true
		}
	}
	return 0, false
}

func clampInt(val, min, max int) int {
	if val < min {
		return min
	}
	if val > max {
		return max
	}
	return val
}

func clampInt64(val, min, max int64) int64 {
	if val < min {
		return min
	}
	if val > max {
		return max
	}
	return val
}
