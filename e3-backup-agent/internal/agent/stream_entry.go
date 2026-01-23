package agent

import (
	"bufio"
	"context"
	"os"
	"strconv"
	"sync/atomic"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

// deviceEntry implements fs.File for raw devices/snapshot paths (e.g., VSS snapshots).
// This allows streaming device backups through Kopia's uploader.
type deviceEntry struct {
	name    string
	path    string
	size    int64
	modTime time.Time
}

// Verify deviceEntry implements fs.File
var _ kopiafs.File = (*deviceEntry)(nil)

// os.FileInfo methods
func (d *deviceEntry) Name() string { return d.name }
func (d *deviceEntry) Size() int64  { return d.size }
func (d *deviceEntry) Mode() os.FileMode {
	// Return regular file mode (not directory, not symlink)
	return 0644
}
func (d *deviceEntry) ModTime() time.Time {
	if d.modTime.IsZero() {
		return time.Now()
	}
	return d.modTime
}
func (d *deviceEntry) IsDir() bool { return false }
func (d *deviceEntry) Sys() any    { return nil }

// fs.Entry methods
func (d *deviceEntry) Owner() kopiafs.OwnerInfo    { return kopiafs.OwnerInfo{} }
func (d *deviceEntry) Device() kopiafs.DeviceInfo  { return kopiafs.DeviceInfo{} }
func (d *deviceEntry) LocalFilesystemPath() string { return d.path }

// Open implements fs.File interface - required for Kopia's top-level Upload().
// Returns a fs.Reader that wraps the device file handle with buffered I/O.
func (d *deviceEntry) Open(ctx context.Context) (kopiafs.Reader, error) {
	// Optional parallel reader (feature flag)
	if useParallelReader() {
		pr, err := newParallelDeviceReader(ctx, d.path, d.size)
		if err != nil {
			return nil, err
		}
		return &parallelReaderWrapper{inner: pr, entry: d}, nil
	}

	f, err := openDeviceOptimized(d.path)
	if err != nil {
		return nil, err
	}
	return &deviceReader{
		file:   f,
		reader: bufio.NewReaderSize(f, diskReadBufferSize),
		entry:  d,
	}, nil
}

// parallelReaderWrapper wraps readSeekCloser to implement kopiafs.Reader
type parallelReaderWrapper struct {
	inner readSeekCloser
	entry *deviceEntry
}

func (w *parallelReaderWrapper) Read(p []byte) (int, error)         { return w.inner.Read(p) }
func (w *parallelReaderWrapper) Seek(off int64, wh int) (int64, error) { return w.inner.Seek(off, wh) }
func (w *parallelReaderWrapper) Close() error                        { return w.inner.Close() }
func (w *parallelReaderWrapper) Entry() (kopiafs.Entry, error)       { return w.entry, nil }

var _ kopiafs.Reader = (*parallelReaderWrapper)(nil)

// deviceReader implements fs.Reader for device file handles.
// fs.Reader requires: io.ReadCloser, io.Seeker, and Entry() method.
type deviceReader struct {
	file   *os.File
	reader *bufio.Reader
	entry  *deviceEntry
	offset int64
}

// Verify deviceReader implements fs.Reader
var _ kopiafs.Reader = (*deviceReader)(nil)

// Read implements io.Reader
func (r *deviceReader) Read(p []byte) (int, error) {
	n, err := r.reader.Read(p)
	r.offset += int64(n)
	return n, err
}

// Close implements io.Closer
func (r *deviceReader) Close() error {
	return r.file.Close()
}

// Seek implements io.Seeker
func (r *deviceReader) Seek(offset int64, whence int) (int64, error) {
	newPos, err := r.file.Seek(offset, whence)
	if err != nil {
		return newPos, err
	}
	// Reset buffered reader after seek to avoid stale buffer
	r.reader.Reset(r.file)
	r.offset = newPos
	return newPos, nil
}

// Entry implements fs.Reader - returns the entry for this reader.
// This is called after the file is read to get up-to-date metadata.
func (r *deviceReader) Entry() (kopiafs.Entry, error) {
	return r.entry, nil
}

// diskReadBufferSize is the buffer size for disk image reads.
// Larger buffer reduces syscall overhead and improves sequential throughput.
const diskReadBufferSize = 8 * 1024 * 1024 // 8 MiB

const (
	parallelDiskReadsUnset    int32 = -1
	parallelDiskReadsDisabled int32 = 0
	parallelDiskReadsEnabled  int32 = 1
)

var parallelDiskReadsOverride int32 = parallelDiskReadsUnset

// setParallelDiskReadsOverride sets a per-run override for parallel disk reads.
// Pass nil to clear the override; returns a reset function to restore prior state.
func setParallelDiskReadsOverride(enabled *bool) func() {
	prev := atomic.LoadInt32(&parallelDiskReadsOverride)
	next := parallelDiskReadsUnset
	if enabled != nil {
		if *enabled {
			next = parallelDiskReadsEnabled
		} else {
			next = parallelDiskReadsDisabled
		}
	}
	atomic.StoreInt32(&parallelDiskReadsOverride, next)
	return func() {
		atomic.StoreInt32(&parallelDiskReadsOverride, prev)
	}
}

// Feature flag: disable parallel reader by setting AGENT_PARALLEL_DISK_READS=0/false
func useParallelReader() bool {
	if v := atomic.LoadInt32(&parallelDiskReadsOverride); v != parallelDiskReadsUnset {
		return v == parallelDiskReadsEnabled
	}
	val := os.Getenv("AGENT_PARALLEL_DISK_READS")
	if val == "" {
		return true
	}
	enabled, err := strconv.ParseBool(val)
	if err != nil {
		return true
	}
	return enabled
}
