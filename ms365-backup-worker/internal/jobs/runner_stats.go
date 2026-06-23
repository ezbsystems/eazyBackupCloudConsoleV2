package jobs

// collectSkippedWorkloads extracts per-workload skip reasons from workload runner stats.
func collectSkippedWorkloads(stats map[string]any) map[string]string {
	if len(stats) == 0 {
		return nil
	}
	skipped := map[string]string{}
	for name, raw := range stats {
		switch name {
		case "graph_429_hits", "pagination_warnings":
			continue
		}
		m, ok := raw.(map[string]any)
		if !ok {
			continue
		}
		reason, ok := m["skipped"].(string)
		if !ok || reason == "" {
			continue
		}
		skipped[name] = reason
	}
	if len(skipped) == 0 {
		return nil
	}
	return skipped
}

func completionItemCounts(storedCount int) (done, total int) {
	if storedCount < 0 {
		storedCount = 0
	}
	return storedCount, storedCount
}

func mergeCompletionStats(base map[string]any, workloads map[string]any) map[string]any {
	out := make(map[string]any, len(base)+1)
	for k, v := range base {
		out[k] = v
	}
	if skipped := collectSkippedWorkloads(workloads); len(skipped) > 0 {
		out["skipped_workloads"] = skipped
	}
	return out
}
