package graphfs

import (
	kopiafs "github.com/kopia/kopia/fs"
)

// EntryStats summarizes overlay file entries by backing type for upload parallelism.
type EntryStats struct {
	StaticFiles int
	GraphFiles  int
	OtherFiles  int
	StaticBytes int64
	GraphBytes  int64
	OtherBytes  int64
}

// TotalFiles returns the number of file entries counted.
func (s EntryStats) TotalFiles() int {
	return s.StaticFiles + s.GraphFiles + s.OtherFiles
}

// TotalBytes returns the sum of reported file sizes.
func (s EntryStats) TotalBytes() int64 {
	return s.StaticBytes + s.GraphBytes + s.OtherBytes
}

// StaticRatio returns the fraction of files backed by in-overlay static content.
func (s EntryStats) StaticRatio() float64 {
	total := s.TotalFiles()
	if total == 0 {
		return 0
	}
	return float64(s.StaticFiles) / float64(total)
}

// EntryStats walks live overlay entries and classifies them as static, Graph-backed, or other.
func (b *OverlayBuilder) EntryStats() EntryStats {
	b.mu.Lock()
	defer b.mu.Unlock()
	var stats EntryStats
	for p, entry := range b.entries {
		if _, skip := b.removed[p]; skip {
			continue
		}
		file, ok := entry.(kopiafs.File)
		if !ok {
			continue
		}
		size := file.Size()
		switch entry.(type) {
		case *staticFile:
			stats.StaticFiles++
			stats.StaticBytes += size
		case *GraphFile:
			stats.GraphFiles++
			stats.GraphBytes += size
		default:
			stats.OtherFiles++
			stats.OtherBytes += size
		}
	}
	return stats
}
