package graphsync

import (
	"fmt"
	"strings"
)

// ParsePhysicalKey splits a physical key into base resource key and optional shard suffix.
func ParsePhysicalKey(key string) (base, shard string) {
	key = strings.TrimSpace(key)
	if i := strings.Index(key, "#shard:"); i >= 0 {
		return key[:i], key[i+len("#shard:"):]
	}
	if i := strings.Index(key, "#mail:"); i >= 0 {
		return key[:i], "mail:" + key[i+len("#mail:"):]
	}
	return key, ""
}

// DeltaKeyForShard returns the delta state map key for a workload shard.
func DeltaKeyForShard(shard string) string {
	if shard == "" {
		return "root"
	}
	return shard
}

func driveContentPath(tenantID, driveID string, item map[string]any) string {
	name, _ := item["name"].(string)
	if name == "" {
		if id, _ := item["id"].(string); id != "" {
			name = id
		} else {
			name = "unknown"
		}
	}
	name = safePathSegment(name)

	relPath := driveRelativePath(item)
	base := fmt.Sprintf("%s/drives/%s/content", tenantID, safeID(driveID))
	if relPath == "" {
		return base + "/" + name
	}
	return base + "/" + relPath + "/" + name
}

func driveRelativePath(item map[string]any) string {
	parentRef, _ := item["parentReference"].(map[string]any)
	if parentRef == nil {
		return ""
	}
	p, _ := parentRef["path"].(string)
	if p == "" {
		return ""
	}
	if idx := strings.Index(p, ":/"); idx >= 0 {
		return strings.Trim(strings.TrimPrefix(p[idx+2:], "/"), "/")
	}
	return strings.Trim(p, "/")
}

func isDriveFolder(item map[string]any) bool {
	if folder, ok := item["folder"].(map[string]any); ok && folder != nil {
		return true
	}
	_, hasFile := item["file"].(map[string]any)
	return !hasFile && item["folder"] != nil
}

func safePathSegment(s string) string {
	return strings.NewReplacer("/", "_", "\\", "_", ":", "_").Replace(strings.TrimSpace(s))
}
