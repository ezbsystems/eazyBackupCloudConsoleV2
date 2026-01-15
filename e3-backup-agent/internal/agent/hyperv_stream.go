//go:build windows
// +build windows

package agent

import (
	"fmt"
	"io"
	"os"
	"sort"
	"time"

	"github.com/your-org/e3-backup-agent/internal/agent/hyperv"
)

// sparseVHDXReader implements io.Reader for Kopia streaming.
// It only reads changed block ranges from the VHDX file,
// returning zeros for unchanged ranges to let Kopia dedupe.
type sparseVHDXReader struct {
	file          *os.File
	totalSize     int64
	changedRanges []hyperv.ChangedBlockRange
	position      int64
	rangeIndex    int  // Current index in changedRanges for efficient lookup
	closed        bool
}

// newSparseVHDXReader creates a reader that only reads changed blocks.
// For unchanged blocks, it returns zeros which Kopia will dedupe against
// the previous snapshot.
func newSparseVHDXReader(vhdxPath string, totalSize int64, changedBlocks []hyperv.ChangedBlockRange) (*sparseVHDXReader, error) {
	f, err := os.Open(vhdxPath)
	if err != nil {
		return nil, fmt.Errorf("open vhdx: %w", err)
	}

	// Sort ranges by offset for efficient sequential access
	ranges := make([]hyperv.ChangedBlockRange, len(changedBlocks))
	copy(ranges, changedBlocks)
	sort.Slice(ranges, func(i, j int) bool {
		return ranges[i].Offset < ranges[j].Offset
	})

	return &sparseVHDXReader{
		file:          f,
		totalSize:     totalSize,
		changedRanges: ranges,
		position:      0,
		rangeIndex:    0,
	}, nil
}

// Read implements io.Reader.
// Returns actual data for changed blocks, zeros for unchanged blocks.
func (r *sparseVHDXReader) Read(p []byte) (int, error) {
	if r.closed {
		return 0, io.ErrClosedPipe
	}

	if r.position >= r.totalSize {
		return 0, io.EOF
	}

	// How much we can potentially read
	remaining := r.totalSize - r.position
	toRead := int64(len(p))
	if toRead > remaining {
		toRead = remaining
	}

	// Find if we're in a changed range
	inRange, rangeEnd := r.findCurrentRange()

	if inRange {
		// We're in a changed range - read actual data from file
		// Limit read to end of current range
		if r.position+toRead > rangeEnd {
			toRead = rangeEnd - r.position
		}

		// Seek to position if needed
		if _, err := r.file.Seek(r.position, io.SeekStart); err != nil {
			return 0, fmt.Errorf("seek: %w", err)
		}

		n, err := r.file.Read(p[:toRead])
		r.position += int64(n)

		// Update range index if we've passed the current range
		if r.position >= rangeEnd && r.rangeIndex < len(r.changedRanges) {
			r.rangeIndex++
		}

		return n, err
	}

	// We're in an unchanged range - return zeros
	// Find where the next changed range starts
	nextRangeStart := r.totalSize
	if r.rangeIndex < len(r.changedRanges) {
		nextRangeStart = r.changedRanges[r.rangeIndex].Offset
	}

	// Limit zeros to either the request size or until next changed range
	zeroEnd := r.position + toRead
	if zeroEnd > nextRangeStart {
		zeroEnd = nextRangeStart
	}
	zeroCount := int(zeroEnd - r.position)

	// Fill with zeros
	for i := 0; i < zeroCount && i < len(p); i++ {
		p[i] = 0
	}

	r.position += int64(zeroCount)
	return zeroCount, nil
}

// findCurrentRange checks if current position is in a changed range.
// Returns (inRange, rangeEnd).
func (r *sparseVHDXReader) findCurrentRange() (bool, int64) {
	// Advance rangeIndex if needed
	for r.rangeIndex < len(r.changedRanges) {
		rng := r.changedRanges[r.rangeIndex]
		rangeEnd := rng.Offset + rng.Length

		if r.position < rng.Offset {
			// We're before this range - not in any range
			return false, 0
		}

		if r.position < rangeEnd {
			// We're in this range
			return true, rangeEnd
		}

		// We're past this range, move to next
		r.rangeIndex++
	}

	// No more ranges
	return false, 0
}

// Seek implements io.Seeker.
func (r *sparseVHDXReader) Seek(offset int64, whence int) (int64, error) {
	var newPos int64
	switch whence {
	case io.SeekStart:
		newPos = offset
	case io.SeekCurrent:
		newPos = r.position + offset
	case io.SeekEnd:
		newPos = r.totalSize + offset
	default:
		return 0, fmt.Errorf("invalid whence: %d", whence)
	}

	if newPos < 0 {
		return 0, fmt.Errorf("negative position")
	}

	r.position = newPos

	// Reset range index for new position
	r.rangeIndex = 0
	for r.rangeIndex < len(r.changedRanges) {
		rng := r.changedRanges[r.rangeIndex]
		if newPos < rng.Offset+rng.Length {
			break
		}
		r.rangeIndex++
	}

	return newPos, nil
}

// Close closes the underlying file.
func (r *sparseVHDXReader) Close() error {
	if r.closed {
		return nil
	}
	r.closed = true
	return r.file.Close()
}

// Size returns the total virtual size of the VHDX.
func (r *sparseVHDXReader) Size() int64 {
	return r.totalSize
}

// fullVHDXReader is a simple reader for full VHDX backup.
type fullVHDXReader struct {
	file      *os.File
	totalSize int64
	position  int64
	closed    bool
}

// newFullVHDXReader creates a reader for full VHDX backup.
func newFullVHDXReader(vhdxPath string) (*fullVHDXReader, error) {
	f, err := os.Open(vhdxPath)
	if err != nil {
		return nil, fmt.Errorf("open vhdx: %w", err)
	}

	fi, err := f.Stat()
	if err != nil {
		f.Close()
		return nil, fmt.Errorf("stat vhdx: %w", err)
	}

	return &fullVHDXReader{
		file:      f,
		totalSize: fi.Size(),
		position:  0,
	}, nil
}

// Read implements io.Reader.
func (r *fullVHDXReader) Read(p []byte) (int, error) {
	if r.closed {
		return 0, io.ErrClosedPipe
	}
	n, err := r.file.Read(p)
	r.position += int64(n)
	return n, err
}

// Seek implements io.Seeker.
func (r *fullVHDXReader) Seek(offset int64, whence int) (int64, error) {
	newPos, err := r.file.Seek(offset, whence)
	if err == nil {
		r.position = newPos
	}
	return newPos, err
}

// Close closes the underlying file.
func (r *fullVHDXReader) Close() error {
	if r.closed {
		return nil
	}
	r.closed = true
	return r.file.Close()
}

// Size returns the file size.
func (r *fullVHDXReader) Size() int64 {
	return r.totalSize
}

// VHDXStreamEntry wraps a VHDX reader as a Kopia entry.
type VHDXStreamEntry struct {
	name      string
	size      int64
	reader    io.ReadSeekCloser
	modTime   time.Time
	diskPath  string
}

// NewVHDXStreamEntry creates a new entry for streaming a VHDX to Kopia.
func NewVHDXStreamEntry(name string, reader io.ReadSeekCloser, size int64, diskPath string) *VHDXStreamEntry {
	return &VHDXStreamEntry{
		name:     name,
		size:     size,
		reader:   reader,
		modTime:  time.Now(),
		diskPath: diskPath,
	}
}

// Name returns the entry name.
func (e *VHDXStreamEntry) Name() string {
	return e.name
}

// Size returns the entry size.
func (e *VHDXStreamEntry) Size() int64 {
	return e.size
}

// ModTime returns the modification time.
func (e *VHDXStreamEntry) ModTime() time.Time {
	return e.modTime
}

// IsDir returns false for VHDX entries.
func (e *VHDXStreamEntry) IsDir() bool {
	return false
}

// Reader returns the underlying reader.
func (e *VHDXStreamEntry) Reader() io.ReadSeekCloser {
	return e.reader
}

// DiskPath returns the original disk path.
func (e *VHDXStreamEntry) DiskPath() string {
	return e.diskPath
}

// Close closes the underlying reader.
func (e *VHDXStreamEntry) Close() error {
	return e.reader.Close()
}

