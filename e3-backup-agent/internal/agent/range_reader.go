package agent

import (
	"context"
	"fmt"
	"io"
	"os"
	"sort"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

// rangeAwareDeviceEntry reads selected ranges from the device and falls back to
// a previous snapshot entry (or zeros) for unchanged ranges.
type rangeAwareDeviceEntry struct {
	name          string
	path          string
	size          int64
	modTime       time.Time
	readRanges    []DiskExtent
	fallbackEntry kopiafs.Entry
}

var _ kopiafs.File = (*rangeAwareDeviceEntry)(nil)

func (d *rangeAwareDeviceEntry) Name() string { return d.name }
func (d *rangeAwareDeviceEntry) Size() int64  { return d.size }
func (d *rangeAwareDeviceEntry) Mode() os.FileMode {
	return 0644
}
func (d *rangeAwareDeviceEntry) ModTime() time.Time {
	if d.modTime.IsZero() {
		return time.Now()
	}
	return d.modTime
}
func (d *rangeAwareDeviceEntry) IsDir() bool { return false }
func (d *rangeAwareDeviceEntry) Sys() any    { return nil }
func (d *rangeAwareDeviceEntry) Owner() kopiafs.OwnerInfo {
	return kopiafs.OwnerInfo{}
}
func (d *rangeAwareDeviceEntry) Device() kopiafs.DeviceInfo {
	return kopiafs.DeviceInfo{}
}
func (d *rangeAwareDeviceEntry) LocalFilesystemPath() string { return d.path }

func (d *rangeAwareDeviceEntry) Open(ctx context.Context) (kopiafs.Reader, error) {
	f, err := openDeviceOptimized(d.path)
	if err != nil {
		return nil, err
	}

	var fallback io.ReadSeekCloser
	if d.fallbackEntry != nil {
		if fe, ok := d.fallbackEntry.(kopiafs.File); ok {
			reader, rerr := fe.Open(ctx)
			if rerr != nil {
				_ = f.Close()
				return nil, rerr
			}
			fallback = reader
		}
	}

	reader := newRangeAwareReader(f, fallback, d.size, d.readRanges, d)
	return reader, nil
}

// rangeAwareReader reads from primary for specified ranges and from fallback (or zeros) for the rest.
type rangeAwareReader struct {
	primary   io.ReadSeekCloser
	fallback  io.ReadSeekCloser
	totalSize int64
	ranges    []DiskExtent
	position  int64
	index     int
	closed    bool
	entry     kopiafs.Entry
}

var _ kopiafs.Reader = (*rangeAwareReader)(nil)

func newRangeAwareReader(primary io.ReadSeekCloser, fallback io.ReadSeekCloser, totalSize int64, ranges []DiskExtent, entry kopiafs.Entry) *rangeAwareReader {
	merged := mergeDiskExtents(ranges)
	return &rangeAwareReader{
		primary:   primary,
		fallback:  fallback,
		totalSize: totalSize,
		ranges:    merged,
		entry:     entry,
	}
}

func (r *rangeAwareReader) Read(p []byte) (int, error) {
	if r.closed {
		return 0, io.ErrClosedPipe
	}
	if r.position >= r.totalSize {
		return 0, io.EOF
	}

	remaining := r.totalSize - r.position
	maxRead := int64(len(p))
	if maxRead > remaining {
		maxRead = remaining
	}
	if maxRead <= 0 {
		return 0, io.EOF
	}

	readTotal := 0
	for int64(readTotal) < maxRead {
		inRange, rangeEnd, nextStart := r.locateRange(r.position)
		targetEnd := r.position + (maxRead - int64(readTotal))
		if inRange && rangeEnd < targetEnd {
			targetEnd = rangeEnd
		} else if !inRange && nextStart < targetEnd {
			targetEnd = nextStart
		}
		chunk := int(targetEnd - r.position)
		if chunk <= 0 {
			break
		}
		buf := p[readTotal : readTotal+chunk]
		var err error
		if inRange {
			err = readAt(r.primary, r.position, buf)
			if err != nil && err != io.EOF {
				return readTotal, err
			}
		} else {
			if r.fallback != nil {
				err = readAt(r.fallback, r.position, buf)
				if err != nil && err != io.EOF {
					return readTotal, err
				}
			} else {
				for i := range buf {
					buf[i] = 0
				}
			}
		}
		r.position += int64(chunk)
		readTotal += chunk
		if err == io.EOF {
			break
		}
	}

	if readTotal == 0 {
		return 0, io.EOF
	}
	return readTotal, nil
}

func (r *rangeAwareReader) Seek(offset int64, whence int) (int64, error) {
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
	r.index = findRangeIndex(r.ranges, newPos)
	return newPos, nil
}

func (r *rangeAwareReader) Close() error {
	if r.closed {
		return nil
	}
	r.closed = true
	if r.fallback != nil {
		_ = r.fallback.Close()
	}
	if r.primary != nil {
		return r.primary.Close()
	}
	return nil
}

func (r *rangeAwareReader) Entry() (kopiafs.Entry, error) {
	return r.entry, nil
}

func (r *rangeAwareReader) locateRange(offset int64) (bool, int64, int64) {
	if len(r.ranges) == 0 {
		return false, 0, r.totalSize
	}
	for r.index < len(r.ranges) {
		curr := r.ranges[r.index]
		end := curr.OffsetBytes + curr.LengthBytes
		if offset < curr.OffsetBytes {
			return false, 0, curr.OffsetBytes
		}
		if offset >= curr.OffsetBytes && offset < end {
			return true, end, end
		}
		r.index++
	}
	return false, 0, r.totalSize
}

func readAt(rs io.ReadSeeker, offset int64, p []byte) error {
	if rs == nil {
		return io.EOF
	}
	if _, err := rs.Seek(offset, io.SeekStart); err != nil {
		return err
	}
	n, err := io.ReadFull(rs, p)
	if err == io.ErrUnexpectedEOF || err == io.EOF {
		if n > 0 {
			for i := n; i < len(p); i++ {
				p[i] = 0
			}
			return nil
		}
	}
	return err
}

func mergeDiskExtents(extents []DiskExtent) []DiskExtent {
	if len(extents) < 2 {
		return extents
	}
	sort.Slice(extents, func(i, j int) bool {
		if extents[i].OffsetBytes == extents[j].OffsetBytes {
			return extents[i].LengthBytes < extents[j].LengthBytes
		}
		return extents[i].OffsetBytes < extents[j].OffsetBytes
	})
	merged := make([]DiskExtent, 0, len(extents))
	for _, e := range extents {
		if e.LengthBytes <= 0 {
			continue
		}
		if len(merged) == 0 {
			merged = append(merged, e)
			continue
		}
		last := &merged[len(merged)-1]
		lastEnd := last.OffsetBytes + last.LengthBytes
		curEnd := e.OffsetBytes + e.LengthBytes
		if e.OffsetBytes <= lastEnd {
			if curEnd > lastEnd {
				last.LengthBytes = curEnd - last.OffsetBytes
			}
			continue
		}
		merged = append(merged, e)
	}
	return merged
}

func findRangeIndex(ranges []DiskExtent, offset int64) int {
	if len(ranges) == 0 {
		return 0
	}
	idx := sort.Search(len(ranges), func(i int) bool {
		return ranges[i].OffsetBytes+ranges[i].LengthBytes > offset
	})
	if idx < 0 {
		return 0
	}
	return idx
}
