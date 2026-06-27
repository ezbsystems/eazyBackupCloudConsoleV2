package archive

import (
	"strings"
	"unicode"
)

const maxZipSegmentLen = 80

var zipSegmentReplacer = strings.NewReplacer(
	"<", "",
	">", "",
	":", "_",
	"\"", "",
	"/", "_",
	"\\", "_",
	"|", "_",
	"?", "",
	"*", "",
)

// sanitizeZipSegment makes a single zip path segment safe for cross-platform archives
// while preserving spaces and common email characters.
func sanitizeZipSegment(name string) string {
	name = strings.TrimSpace(name)
	if name == "" {
		return ""
	}
	var b strings.Builder
	b.Grow(len(name))
	prevSpace := false
	for _, r := range name {
		if unicode.IsControl(r) {
			continue
		}
		if r == ' ' || r == '\t' {
			if !prevSpace {
				b.WriteByte(' ')
				prevSpace = true
			}
			continue
		}
		prevSpace = false
		b.WriteRune(r)
	}
	out := zipSegmentReplacer.Replace(b.String())
	out = strings.TrimSpace(out)
	if out == "" {
		return ""
	}
	if len(out) > maxZipSegmentLen {
		out = strings.TrimSpace(out[:maxZipSegmentLen])
	}
	if out == "" {
		return ""
	}
	return out
}

func guidSuffix(id string) string {
	id = strings.TrimSpace(id)
	if len(id) >= 8 {
		return id[:8]
	}
	return id
}
