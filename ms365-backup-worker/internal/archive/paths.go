package archive

import (
	"path"
	"strings"
)

func shouldExportFile(snapshotPath string) bool {
	base := strings.ToLower(path.Base(snapshotPath))
	if base == "" {
		return false
	}
	switch base {
	case "folders.json", "delta_state.json", "_folder.json", "_calendar.json",
		"_site.json", "_plan.json", "_bucket.json", "_notebook.json", "_section.json",
		"metadata.json", "drives.json", "lists.json", "attachments.json":
		return false
	}
	if strings.HasSuffix(base, ".removed.json") {
		return false
	}
	return true
}

// snapshotToZipPath maps a Kopia snapshot-relative path to a readable in-zip path.
func snapshotToZipPath(snapshotPath string) string {
	p := strings.Trim(snapshotPath, "/")
	if p == "" {
		return "unknown"
	}
	parts := stripTenantPrefix(strings.Split(p, "/"))
	if len(parts) == 0 {
		return "unknown"
	}

	switch parts[0] {
	case "users":
		if len(parts) < 3 {
			return strings.Join(parts, "/")
		}
		userID := parts[1]
		workload := parts[2]
		rest := parts[3:]
		switch workload {
		case "onedrive":
			if len(rest) > 0 && rest[0] == "content" {
				rest = rest[1:]
			}
			if len(rest) == 0 {
				return "onedrive/" + userID
			}
			return "onedrive/" + path.Join(append([]string{userID}, rest...)...)
		case "mail":
			return "mail/" + path.Join(append([]string{userID}, rest...)...)
		case "calendars", "calendar":
			return "calendar/" + path.Join(append([]string{userID}, rest...)...)
		case "contacts":
			return "contacts/" + path.Join(append([]string{userID}, rest...)...)
		case "tasks":
			return "tasks/" + path.Join(append([]string{userID}, rest...)...)
		case "onenote":
			return "onenote/" + path.Join(append([]string{userID}, rest...)...)
		default:
			return path.Join(parts...)
		}
	case "sites":
		return "sharepoint/" + path.Join(parts[1:]...)
	case "drives":
		rest := parts[1:]
		for i, seg := range rest {
			if seg == "content" {
				rest = rest[i+1:]
				break
			}
		}
		if len(rest) == 0 {
			return "onedrive"
		}
		return "onedrive/" + path.Join(rest...)
	case "teams":
		return "teams/" + path.Join(parts[1:]...)
	case "groups":
		return "groups/" + path.Join(parts[1:]...)
	case "planner":
		return "planner/" + path.Join(parts[1:]...)
	default:
		return path.Join(parts...)
	}
}

func stripTenantPrefix(parts []string) []string {
	if len(parts) < 2 {
		return parts
	}
	if isGuidLike(parts[0]) {
		return parts[1:]
	}
	switch parts[1] {
	case "users", "sites", "drives", "teams", "groups", "planner":
		return parts[1:]
	}
	return parts
}

func isGuidLike(value string) bool {
	if len(value) == 36 && strings.Count(value, "-") == 4 {
		return true
	}
	if len(value) == 32 {
		for _, c := range value {
			if (c < '0' || c > '9') && (c < 'a' || c > 'f') && (c < 'A' || c > 'F') {
				return false
			}
		}
		return true
	}
	return false
}
