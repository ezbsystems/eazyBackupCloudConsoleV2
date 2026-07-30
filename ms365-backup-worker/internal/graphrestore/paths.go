package graphrestore

import "strings"

// EffectiveLogicalPath returns the browse/logical snapshot path used for restore
// destination resolution (Graph upload targets).
func EffectiveLogicalPath(item SelectionItem) string {
	if p := strings.TrimSpace(item.LogicalPath); p != "" {
		return strings.Trim(p, "/")
	}
	if p := strings.TrimSpace(item.Path); p != "" {
		return strings.Trim(strings.TrimSuffix(p, "/"), "/")
	}
	return strings.Trim(strings.TrimSuffix(strings.TrimSpace(item.PathPrefix), "/"), "/")
}

// EffectiveSourcePath returns the per-manifest physical path used to read from
// Kopia. Falls back to the logical path for legacy single-manifest selections.
func EffectiveSourcePath(item SelectionItem) string {
	if p := strings.TrimSpace(item.SourcePath); p != "" {
		return strings.Trim(p, "/")
	}
	return EffectiveLogicalPath(item)
}

// MapSourceFileToLogical maps a file path collected under sourcePrefix to the
// corresponding logical browse path under logicalPrefix.
func MapSourceFileToLogical(sourceFile, sourcePrefix, logicalPrefix string) string {
	sourceFile = strings.Trim(sourceFile, "/")
	sourcePrefix = strings.Trim(strings.TrimSuffix(sourcePrefix, "/"), "/")
	logicalPrefix = strings.Trim(strings.TrimSuffix(logicalPrefix, "/"), "/")
	if sourceFile == "" {
		return logicalPrefix
	}
	if sourcePrefix == "" {
		if logicalPrefix == "" {
			return sourceFile
		}
		return joinBrowsePath(logicalPrefix, relPathAfterPrefix(sourceFile, ""))
	}
	if sourceFile == sourcePrefix {
		return logicalPrefix
	}
	prefix := sourcePrefix + "/"
	if strings.HasPrefix(sourceFile, prefix) {
		suffix := strings.TrimPrefix(sourceFile, prefix)
		if logicalPrefix == "" {
			return suffix
		}
		return joinBrowsePath(logicalPrefix, suffix)
	}
	if logicalPrefix == "" {
		return sourceFile
	}
	return joinBrowsePath(logicalPrefix, sourceFile)
}

func relPathAfterPrefix(path, prefix string) string {
	path = strings.Trim(path, "/")
	prefix = strings.Trim(strings.TrimSuffix(prefix, "/"), "/")
	if prefix == "" || path == prefix {
		return ""
	}
	if strings.HasPrefix(path, prefix+"/") {
		return strings.TrimPrefix(path, prefix+"/")
	}
	return path
}

func joinBrowsePath(base, name string) string {
	base = strings.Trim(strings.TrimSuffix(base, "/"), "/")
	name = strings.Trim(strings.TrimPrefix(name, "/"), "/")
	if base == "" {
		return name
	}
	if name == "" {
		return base
	}
	return base + "/" + name
}

func isFolderSelection(item SelectionItem) bool {
	if strings.EqualFold(strings.TrimSpace(item.Type), "folder") {
		return true
	}
	if strings.TrimSpace(item.Path) != "" {
		return false
	}
	prefix := strings.TrimSpace(item.PathPrefix)
	return prefix != "" && strings.HasSuffix(prefix, "/")
}
