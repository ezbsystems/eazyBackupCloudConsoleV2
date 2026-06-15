package graphsync

import "strings"

func safeID(s string) string {
	return strings.NewReplacer("/", "_", "\\", "_", ":", "_").Replace(s)
}

// storageSafeID matches PHP StorageLayout::sanitize for snapshot paths.
func storageSafeID(s string) string {
	var b strings.Builder
	for _, r := range s {
		if (r >= 'a' && r <= 'z') || (r >= 'A' && r <= 'Z') || (r >= '0' && r <= '9') || r == '.' || r == '_' || r == '-' {
			b.WriteRune(r)
		} else {
			b.WriteByte('_')
		}
	}
	out := b.String()
	if out == "" {
		return "unknown"
	}
	return out
}

func siteStoragePath(tenantID, siteID string) string {
	return tenantID + "/sites/" + storageSafeID(siteID)
}
