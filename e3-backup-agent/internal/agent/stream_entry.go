package agent

import (
	"bufio"
	"context"
	"io"
	"os"
	"strconv"
	"sync/atomic"
	"time"

	kopiafs "github.com/kopia/kopia/fs"
)

// deviceEntry implements fs.File for raw devices/snapshot paths (e.g., VSS snapshots).
// This allows streaming device backups through Kopia's uploader.
type deviceEntry struct {
	name            string
	path            string
	size            int64
	modTime         time.Time
	forceSequential bool
	physicalSource  bool
	warningCallback diskReadWarningCallback
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
	if !d.forceSequential && useParallelReader() {
		pr, err := newParallelDeviceReader(ctx, d.path, d.size, d.physicalSource, d.warningCallback)
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

func (w *parallelReaderWrapper) Read(p []byte) (int, error)            { return w.inner.Read(p) }
func (w *parallelReaderWrapper) Seek(off int64, wh int) (int64, error) { return w.inner.Seek(off, wh) }
func (w *parallelReaderWrapper) Close() error                          { return w.inner.Close() }
func (w *parallelReaderWrapper) Entry() (kopiafs.Entry, error)         { return w.entry, nil }

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
	readStart := r.offset
	n, err := r.reader.Read(p)
	r.offset += int64(n)
	if err == nil || !isWindowsSectorNotFound(err) || r.entry == nil || !r.entry.physicalSource {
		return n, err
	}
	if isStrictReadErrors() {
		return n, err
	}

	requestedBytes := int64(len(p))
	requestedEnd := readStart + requestedBytes
	nearTail := shouldTreatSectorNotFoundAsEOF(r.entry.size, requestedEnd)
	if nearTail {
		r.emitReadWarning(readStart, requestedBytes, int64(n), 0, true, err)
		if n > 0 {
			return n, io.EOF
		}
		return 0, io.EOF
	}

	zeroFilled := int64(len(p) - n)
	if zeroFilled < 0 {
		zeroFilled = 0
	}

	// Advance past the unread range so repeated reads do not get stuck
	// on the same sector and emit synthetic zeros for that span.
	if _, seekErr := r.file.Seek(requestedEnd, io.SeekStart); seekErr != nil {
		return n, err
	}
	r.reader.Reset(r.file)
	for i := n; i < len(p); i++ {
		p[i] = 0
	}
	r.offset = requestedEnd
	r.emitReadWarning(readStart, requestedBytes, int64(n), zeroFilled, false, err)
	return len(p), nil
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
const diskReadSectorTailToleranceBytes = 8 * 1024 * 1024

type diskReadWarning struct {
	Path            string
	Reader          string
	OffsetBytes     int64
	RequestedBytes  int64
	ReadBytes       int64
	ZeroFilledBytes int64
	NearTail        bool
	Error           string
}

type diskReadWarningCallback func(details diskReadWarning)

const (
	parallelDiskReadsUnset    int32 = -1
	parallelDiskReadsDisabled int32 = 0
	parallelDiskReadsEnabled  int32 = 1
	strictReadErrorsUnset     int32 = -1
	strictReadErrorsDisabled  int32 = 0
	strictReadErrorsEnabled   int32 = 1
)

var parallelDiskReadsOverride int32 = parallelDiskReadsUnset
var strictReadErrorsOverride int32 = strictReadErrorsUnset

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

// setStrictReadErrorsOverride sets a per-run override for read error handling.
// Pass nil to clear the override; returns a reset function to restore prior state.
func setStrictReadErrorsOverride(enabled *bool) func() {
	prev := atomic.LoadInt32(&strictReadErrorsOverride)
	next := strictReadErrorsUnset
	if enabled != nil {
		if *enabled {
			next = strictReadErrorsEnabled
		} else {
			next = strictReadErrorsDisabled
		}
	}
	atomic.StoreInt32(&strictReadErrorsOverride, next)
	return func() {
		atomic.StoreInt32(&strictReadErrorsOverride, prev)
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

// Feature flag: enable strict read errors by setting AGENT_DISK_IMAGE_STRICT_READ_ERRORS=1/true
func isStrictReadErrors() bool {
	if v := atomic.LoadInt32(&strictReadErrorsOverride); v != strictReadErrorsUnset {
		return v == strictReadErrorsEnabled
	}
	val := os.Getenv("AGENT_DISK_IMAGE_STRICT_READ_ERRORS")
	if val == "" {
		return false
	}
	enabled, err := strconv.ParseBool(val)
	if err != nil {
		return false
	}
	return enabled
}

func shouldTreatSectorNotFoundAsEOF(size int64, requestedEnd int64) bool {
	if size <= 0 {
		return false
	}
	tolerance := int64(diskReadSectorTailToleranceBytes)
	if tolerance > size {
		tolerance = size
	}
	tailStart := size - tolerance
	return requestedEnd >= tailStart
}

func (r *deviceReader) emitReadWarning(offsetBytes, requestedBytes, readBytes, zeroFilledBytes int64, nearTail bool, readErr error) {
	if r.entry == nil || r.entry.warningCallback == nil {
		return
	}
	msg := ""
	if readErr != nil {
		msg = readErr.Error()
	}
	r.entry.warningCallback(diskReadWarning{
		Path:            r.entry.path,
		Reader:          "sequential",
		OffsetBytes:     offsetBytes,
		RequestedBytes:  requestedBytes,
		ReadBytes:       readBytes,
		ZeroFilledBytes: zeroFilledBytes,
		NearTail:        nearTail,
		Error:           msg,
	})
}
