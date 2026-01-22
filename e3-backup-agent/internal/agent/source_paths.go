package agent

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
)

func normalizeSourcePaths(paths []string, fallback string) []string {
	seen := map[string]bool{}
	out := make([]string, 0, len(paths))
	for _, p := range paths {
		trimmed := strings.TrimSpace(p)
		if trimmed == "" {
			continue
		}
		if !seen[trimmed] {
			seen[trimmed] = true
			out = append(out, trimmed)
		}
	}
	if len(out) == 0 {
		fb := strings.TrimSpace(fallback)
		if fb != "" {
			out = append(out, fb)
		}
	}
	return out
}

func buildSourceLabels(paths []string) []string {
	used := map[string]int{}
	labels := make([]string, 0, len(paths))
	for _, p := range paths {
		labels = append(labels, uniqueSourceLabel(p, used))
	}
	return labels
}

func uniqueSourceLabel(path string, used map[string]int) string {
	label := sourceLabel(path)
	if count := used[label]; count > 0 {
		used[label] = count + 1
		label = fmt.Sprintf("%s_%d", label, count+1)
	} else {
		used[label] = 1
	}
	return label
}

func sourceLabel(path string) string {
	trimmed := strings.TrimSpace(path)
	if trimmed == "" {
		return "source"
	}
	trimmed = strings.TrimRight(trimmed, "\\/")
	base := filepath.Base(trimmed)
	base = strings.TrimSpace(base)
	if base == "" || base == "." || base == string(os.PathSeparator) {
		base = "source"
	}
	base = sanitizeLabel(base)
	if base == "" {
		return "source"
	}
	return base
}

func sanitizeLabel(label string) string {
	replacer := strings.NewReplacer("\\", "_", "/", "_", ":", "_")
	return strings.Trim(replacer.Replace(label), "_")
}
