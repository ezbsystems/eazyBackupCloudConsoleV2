package graphsync

import "strings"

func safeID(s string) string {
	return strings.NewReplacer("/", "_", "\\", "_", ":", "_").Replace(s)
}
