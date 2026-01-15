//go:build !windows
// +build !windows

package agent

import "strings"

// IsUNCPath returns true if the path is a UNC path (starts with \\).
// On non-Windows platforms, this always returns false.
func IsUNCPath(path string) bool {
	return strings.HasPrefix(path, "\\\\")
}

// ExtractShareRoot extracts the server\share portion from a UNC path.
// On non-Windows platforms, returns empty string.
func ExtractShareRoot(uncPath string) string {
	if !IsUNCPath(uncPath) {
		return ""
	}

	// Remove leading \\
	path := strings.TrimPrefix(uncPath, "\\\\")
	parts := strings.SplitN(path, "\\", 3)
	if len(parts) < 2 {
		return ""
	}

	return "\\\\" + parts[0] + "\\" + parts[1]
}
