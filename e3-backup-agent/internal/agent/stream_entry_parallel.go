package agent

import (
	"context"
	"fmt"
	"io"
	"log"
	"math"
	"os"
	"runtime"
	"strconv"
	"sync"
	"sync/atomic"
	"time"
)

const (
	defaultParallelChunkSize = 8 * 1024 * 1024 // 8 MiB
	defaultParallelWorkers   = 8               // capped to NumCPU
	defaultReadOpTimeoutSecs = 60              // 1 minute per individual f.Read() syscall
	tailReadTimeoutSecs      = 5               // shorter timeout for tail-region reads
	// Percentage of file size from end that is considered "tail" for zero-fill on timeout
	tailZeroFillPercent = 2
)

type parallelDeviceReader struct {
	path            string
	size            int64
	chunkSize       int64
	workers         int
	physicalSource  bool
	warningCallback diskReadWarningCallback

	// tailStalled is set to 1 after the first tail-region read times out.
	// Once set, all subsequent tail reads are zero-filled immediately without
	// opening the file, preventing orphaned goroutine accumulation that
	// saturates the Windows I/O queue and blocks all further file operations.
	tailStalled int32

	parentCtx     context.Context // immutable root context from constructor
	mu            sync.Mutex
	ctx           context.Context
	cancel        context.CancelFunc
	tasks         chan int64
	results       chan chunkResult
	pending       map[int64][]byte
	expected      int64
	curBuf        []byte
	curPos        int
	chunkStart    int64
	nextSend      int64
	inflight      int
	err           error
	currentOffset int64
}

type chunkResult struct {
	offset int64
	data   []byte
	n      int
	err    error
}

type readSeekCloser interface {
	io.Reader
	io.Seeker
	io.Closer
}

func newParallelDeviceReader(ctx context.Context, path string, size int64, physicalSource bool, warningCallback diskReadWarningCallback, skipTailReads bool) (readSeekCloser, error) {
	chunkSize := int64(defaultParallelChunkSize)
	workers := defaultParallelWorkers
	if workers > runtime.NumCPU() {
		workers = runtime.NumCPU()
	}
	if workers < 2 {
		workers = 2
	}
	if chunkSize < 1024*1024 {
		chunkSize = 1024 * 1024
	}

	pdr := &parallelDeviceReader{
		path:            path,
		size:            size,
		chunkSize:       chunkSize,
		workers:         workers,
		physicalSource:  physicalSource,
		warningCallback: warningCallback,
		parentCtx:       ctx,
		pending:         make(map[int64][]byte),
	}
	if skipTailReads {
		atomic.StoreInt32(&pdr.tailStalled, 1)
		log.Printf("agent: parallel reader pre-marking tail as stalled (live file, tail reads will be zero-filled)")
	}
	pdr.resetPipeline(ctx, 0)
	return pdr, nil
}

func (r *parallelDeviceReader) resetPipeline(ctx context.Context, startOffset int64) {
	if r.cancel != nil {
		r.cancel()
	}
	r.ctx, r.cancel = context.WithCancel(ctx)
	r.tasks = make(chan int64, r.workers)
	r.results = make(chan chunkResult, r.workers)
	r.pending = make(map[int64][]byte)
	r.curBuf = nil
	r.curPos = 0
	r.err = nil
	r.currentOffset = startOffset

	alignedStart := (startOffset / r.chunkSize) * r.chunkSize
	r.expected = alignedStart
	r.nextSend = alignedStart
	r.inflight = 0

	for i := 0; i < r.workers; i++ {
		go r.worker()
	}
	r.fillWindow()
}

// getReadOpTimeout returns the timeout for a single f.Read() syscall.
// Configurable via AGENT_READ_OP_TIMEOUT_SECS environment variable.
func getReadOpTimeout() time.Duration {
	val := os.Getenv("AGENT_READ_OP_TIMEOUT_SECS")
	if val != "" {
		if secs, err := strconv.Atoi(val); err == nil && secs > 0 {
			return time.Duration(secs) * time.Second
		}
	}
	return time.Duration(defaultReadOpTimeoutSecs) * time.Second
}

// isNearTail returns true if the given offset is within the tail region of the file.
// The tail region is where VHDX metadata/BAT/log structures live and may be
// locked by Hyper-V on a running VM.
func (r *parallelDeviceReader) isNearTail(offset int64) bool {
	if r.size <= 0 {
		return false
	}
	tailThreshold := r.size * tailZeroFillPercent / 100
	if tailThreshold < 64*1024*1024 {
		tailThreshold = 64 * 1024 * 1024 // minimum 64 MiB tail region
	}
	return offset >= r.size-tailThreshold
}

// readResult is used to pass results from the read goroutine back to the worker.
type readResult struct {
	n   int
	err error
}

func (r *parallelDeviceReader) worker() {
	for offset := range r.tasks {
		select {
		case <-r.ctx.Done():
			r.results <- chunkResult{offset: offset, data: nil, n: 0, err: r.ctx.Err()}
			return
		default:
		}

		bufSize := r.chunkSize
		if remain := r.size - offset; remain < bufSize {
			bufSize = remain
		}
		if bufSize <= 0 {
			r.results <- chunkResult{offset: offset, data: nil, n: 0, err: io.EOF}
			continue
		}

		// Fast path: if the tail is already known to be stalled (a previous
		// tail read timed out), zero-fill immediately without opening the file.
		// This avoids orphaning more goroutines with stuck ReadFile syscalls
		// that saturate the Windows I/O queue and block all further file ops.
		if r.isNearTail(offset) && atomic.LoadInt32(&r.tailStalled) == 1 {
			zeroBuf := make([]byte, bufSize)
			r.results <- chunkResult{offset: offset, data: zeroBuf, n: int(bufSize), err: nil}
			continue
		}

		r.results <- r.readChunkWithTimeout(offset, bufSize)
	}
}

// readChunkWithTimeout reads a chunk from the file with per-read timeout protection.
//
// CRITICAL WINDOWS BEHAVIOR: On Windows, calling f.Close() does NOT reliably
// unblock a pending synchronous ReadFile() syscall. The goroutine running the
// read may be permanently stuck in the kernel. Therefore:
//   - We NEVER wait for the read goroutine after a timeout (<-ch would block forever)
//   - The goroutine owns the file handle and closes it when/if the read returns
//   - We allocate a FRESH buffer for zero-fill results (the stuck goroutine owns the original)
//   - The orphaned goroutine will be cleaned up when the process exits
func (r *parallelDeviceReader) readChunkWithTimeout(offset, bufSize int64) chunkResult {
	f, err := openDeviceOptimized(r.path)
	if err != nil {
		return chunkResult{offset: offset, data: nil, n: 0, err: err}
	}

	if _, err := f.Seek(offset, io.SeekStart); err != nil {
		_ = f.Close()
		return chunkResult{offset: offset, data: nil, n: 0, err: err}
	}

	buf := make([]byte, bufSize)
	readOpTimeout := getReadOpTimeout()
	nearTail := r.isNearTail(offset)

	// Use a much shorter timeout for tail reads. Tail regions of live VHDX
	// files are almost always locked by Hyper-V; waiting 60s per chunk just
	// orphans goroutines whose stuck ReadFile syscalls saturate the Windows
	// I/O queue and block all subsequent file operations on that path.
	if nearTail {
		readOpTimeout = time.Duration(tailReadTimeoutSecs) * time.Second
	}

	// The read goroutine owns both `f` and `buf`. It closes the file when done.
	// We must NOT access `buf` after a timeout (the goroutine may still be writing to it).
	ch := make(chan readResult, 1)
	go func() {
		n, readErr := io.ReadFull(f, buf)
		_ = f.Close()
		ch <- readResult{n: n, err: readErr}
	}()

	select {
	case <-r.ctx.Done():
		// Context cancelled. The goroutine will eventually close f and exit.
		// Do NOT wait for it -- f.Close() won't unblock the read on Windows.
		return chunkResult{offset: offset, data: nil, n: 0, err: r.ctx.Err()}

	case res := <-ch:
		// Normal completion -- the goroutine is done, we safely own buf.
		return r.processChunkReadResult(offset, bufSize, buf, res.n, res.err)

	case <-time.After(readOpTimeout):
		// Read timed out. The goroutine is stuck in the kernel.
		// We CANNOT wait for it (<-ch would block forever on Windows).
		// The goroutine still owns `buf` and `f` -- do not touch them.
		log.Printf("agent: parallel reader f.Read() timed out at offset %d after %v (near_tail=%v, remaining=%d bytes)",
			offset, readOpTimeout, nearTail, r.size-offset)

		if nearTail {
			// Mark the tail as stalled so subsequent tail chunks are zero-filled
			// instantly by workers without opening the file (no more orphaned goroutines).
			atomic.StoreInt32(&r.tailStalled, 1)

			zeroBuf := make([]byte, bufSize)
			log.Printf("agent: parallel reader zero-filling %d bytes at offset %d (tail stalled, total_size=%d, remaining tail chunks will be zero-filled immediately)",
				bufSize, offset, r.size)
			r.emitReadWarning(offset, bufSize, 0, bufSize, true,
				fmt.Errorf("read timed out at tail offset %d after %v, zero-filled %d bytes", offset, readOpTimeout, bufSize))
			return chunkResult{offset: offset, data: zeroBuf, n: int(bufSize), err: nil}
		}

		// Not near tail: retry once with a fresh file handle
		log.Printf("agent: parallel reader retrying read at offset %d (not near tail)", offset)
		retryResult := r.retryChunkRead(offset, bufSize)
		if retryResult.err == nil {
			return retryResult
		}

		r.emitReadWarning(offset, bufSize, 0, 0, false,
			fmt.Errorf("read timed out at offset %d after %v and retry failed: %v", offset, readOpTimeout, retryResult.err))
		return chunkResult{offset: offset, data: nil, n: 0,
			err: fmt.Errorf("read timed out at offset %d (file may be locked by another process)", offset)}
	}
}

// processChunkReadResult handles the various outcomes of a successful (non-timed-out) chunk read.
func (r *parallelDeviceReader) processChunkReadResult(offset, bufSize int64, buf []byte, totalRead int, readErr error) chunkResult {
	if readErr != nil && isWindowsSectorNotFound(readErr) && r.physicalSource {
		requestedEnd := offset + bufSize
		if isStrictReadErrors() {
			// Keep original error in strict mode
		} else if shouldTreatSectorNotFoundAsEOF(r.size, requestedEnd) {
			r.emitReadWarning(offset, bufSize, int64(totalRead), 0, true, readErr)
			readErr = io.EOF
		} else {
			zeroFilled := bufSize - int64(totalRead)
			if zeroFilled < 0 {
				zeroFilled = 0
			}
			for i := totalRead; i < int(bufSize); i++ {
				buf[i] = 0
			}
			totalRead = int(bufSize)
			r.emitReadWarning(offset, bufSize, int64(totalRead)-zeroFilled, zeroFilled, false, readErr)
			readErr = nil
		}
	}

	if readErr == io.ErrUnexpectedEOF || readErr == io.EOF {
		if totalRead > 0 {
			readErr = nil
		}
	} else if readErr != nil {
		return chunkResult{offset: offset, data: nil, n: totalRead, err: readErr}
	}

	return chunkResult{offset: offset, data: buf[:totalRead], n: totalRead, err: readErr}
}

// retryChunkRead makes one more attempt to read a chunk with a fresh file handle.
// Same timeout/orphan semantics as readChunkWithTimeout.
func (r *parallelDeviceReader) retryChunkRead(offset, bufSize int64) chunkResult {
	f, err := openDeviceOptimized(r.path)
	if err != nil {
		return chunkResult{offset: offset, data: nil, n: 0, err: err}
	}
	if _, err := f.Seek(offset, io.SeekStart); err != nil {
		_ = f.Close()
		return chunkResult{offset: offset, data: nil, n: 0, err: err}
	}

	buf := make([]byte, bufSize)
	readOpTimeout := getReadOpTimeout()

	ch := make(chan readResult, 1)
	go func() {
		n, readErr := io.ReadFull(f, buf)
		_ = f.Close()
		ch <- readResult{n: n, err: readErr}
	}()

	select {
	case <-r.ctx.Done():
		return chunkResult{offset: offset, data: nil, n: 0, err: r.ctx.Err()}
	case res := <-ch:
		return r.processChunkReadResult(offset, bufSize, buf, res.n, res.err)
	case <-time.After(readOpTimeout):
		log.Printf("agent: parallel reader retry also timed out at offset %d after %v", offset, readOpTimeout)
		// Do NOT wait for goroutine -- same Windows limitation
		return chunkResult{offset: offset, data: nil, n: 0,
			err: fmt.Errorf("retry read also timed out at offset %d after %v", offset, readOpTimeout)}
	}
}

func (r *parallelDeviceReader) fillWindow() {
	for r.inflight < r.workers && r.nextSend < r.size {
		r.tasks <- r.nextSend
		r.nextSend += r.chunkSize
		r.inflight++
	}
}

func (r *parallelDeviceReader) fetchNextChunk() error {
	// Fast path: when the tail is known to be stalled, zero-fill directly in
	// the consumer without waiting for workers. Workers may be permanently
	// stuck in CreateFile/ReadFile kernel calls due to Windows oplock
	// negotiation with Hyper-V and will never produce results.
	if r.isNearTail(r.expected) && atomic.LoadInt32(&r.tailStalled) == 1 {
		remaining := r.size - r.expected
		if remaining <= 0 {
			return io.EOF
		}
		fillSize := r.chunkSize
		if remaining < fillSize {
			fillSize = remaining
		}
		zeroBuf := make([]byte, fillSize)
		r.curBuf = zeroBuf
		r.curPos = 0
		r.chunkStart = r.expected
		r.expected += r.chunkSize
		return nil
	}

	safetyTimeout := getReadOpTimeout() * 3

	// Use a shorter safety timeout for tail-region chunks even on the first
	// attempt. Workers use a 5s read timeout for tail reads, but they may
	// get stuck in CreateFile (Windows oplock negotiation with Hyper-V)
	// BEFORE reaching the timer. A 15s safety catches this without waiting
	// the full 3 minutes.
	if r.isNearTail(r.expected) {
		safetyTimeout = time.Duration(tailReadTimeoutSecs*3) * time.Second
	}

	timeoutCh := time.After(safetyTimeout)

	for {
		select {
		case <-r.ctx.Done():
			return r.ctx.Err()

		case <-timeoutCh:
			if r.isNearTail(r.expected) {
				remaining := r.size - r.expected
				if remaining <= 0 {
					return io.EOF
				}
				atomic.StoreInt32(&r.tailStalled, 1)

				fillSize := r.chunkSize
				if remaining < fillSize {
					fillSize = remaining
				}
				log.Printf("agent: parallel reader safety timeout at tail offset %d, zero-filling %d bytes and continuing",
					r.expected, fillSize)
				r.emitTimeoutWarning(r.expected, 1, safetyTimeout)
				zeroBuf := make([]byte, fillSize)
				r.curBuf = zeroBuf
				r.curPos = 0
				r.chunkStart = r.expected
				r.expected += r.chunkSize
				return nil
			}

			log.Printf("agent: parallel reader safety timeout waiting for chunk at offset %d (after %v)",
				r.expected, safetyTimeout)
			r.emitTimeoutWarning(r.expected, 1, safetyTimeout)
			return fmt.Errorf("parallel reader: safety timeout waiting for chunk at offset %d after %v",
				r.expected, safetyTimeout)

		case res, ok := <-r.results:
			if !ok {
				return io.EOF
			}
			r.inflight--

			if res.err != nil && res.err != io.EOF && res.err != io.ErrUnexpectedEOF {
				r.err = res.err
			}
			if res.n > 0 {
				r.pending[res.offset] = res.data[:res.n]
			}
			r.fillWindow()

			if buf, ok := r.pending[r.expected]; ok {
				delete(r.pending, r.expected)
				r.curBuf = buf
				r.curPos = 0
				r.chunkStart = r.expected
				r.expected += r.chunkSize
				return nil
			}
			if r.inflight == 0 && r.nextSend >= r.size {
				if r.err != nil {
					return r.err
				}
				return io.EOF
			}

			timeoutCh = time.After(safetyTimeout)
		}
	}
}

func (r *parallelDeviceReader) Read(p []byte) (int, error) {
	if r.err != nil {
		return 0, r.err
	}
	total := 0
	for total < len(p) {
		if r.curBuf == nil || r.curPos >= len(r.curBuf) {
			if err := r.fetchNextChunk(); err != nil {
				if total > 0 {
					return total, nil
				}
				if err == io.EOF {
					return 0, io.EOF
				}
				return 0, err
			}
			// Handle starting at non-aligned offset
			if r.expected-r.chunkSize == ((r.expected-r.chunkSize)/r.chunkSize)*r.chunkSize {
				// no-op
			}
		}

		n := copy(p[total:], r.curBuf[r.curPos:])
		r.curPos += n
		total += n
		r.currentOffset += int64(n)

		// If we've reached overall size, break
		if r.currentOffset >= r.size {
			break
		}
	}
	if total == 0 && r.err != nil {
		return 0, r.err
	}
	return total, nil
}

func (r *parallelDeviceReader) Seek(offset int64, whence int) (int64, error) {
	var newPos int64
	switch whence {
	case io.SeekStart:
		newPos = offset
	case io.SeekCurrent:
		current := r.expected - r.chunkSize + int64(r.curPos)
		newPos = current + offset
	case io.SeekEnd:
		newPos = r.size + offset
	default:
		return 0, fmt.Errorf("invalid whence")
	}
	if newPos < 0 {
		return 0, fmt.Errorf("negative seek")
	}
	if newPos > r.size {
		newPos = r.size
	}

	// Use parentCtx (the original root context) rather than r.ctx.
	// resetPipeline cancels the old r.ctx before deriving a new child;
	// passing r.ctx here would create a child of an already-cancelled
	// context, immediately killing all new workers.
	r.resetPipeline(r.parentCtx, newPos)
	// Skip bytes within first chunk if starting mid-chunk
	if newPos%r.chunkSize != 0 {
		if err := r.fetchNextChunk(); err != nil {
			return 0, err
		}
		skip := newPos - ((newPos / r.chunkSize) * r.chunkSize)
		if skip > int64(len(r.curBuf)) {
			return 0, io.ErrUnexpectedEOF
		}
		r.curPos = int(skip)
		r.currentOffset = newPos
	}
	return newPos, nil
}

func (r *parallelDeviceReader) Close() error {
	if r.cancel != nil {
		r.cancel()
	}
	if r.tasks != nil {
		close(r.tasks)
	}
	return nil
}

func (r *parallelDeviceReader) emitReadWarning(offsetBytes, requestedBytes, readBytes, zeroFilledBytes int64, nearTail bool, readErr error) {
	if r.warningCallback == nil {
		return
	}
	msg := ""
	if readErr != nil {
		msg = readErr.Error()
	}
	r.warningCallback(diskReadWarning{
		Path:            r.path,
		Reader:          "parallel",
		OffsetBytes:     offsetBytes,
		RequestedBytes:  requestedBytes,
		ReadBytes:       readBytes,
		ZeroFilledBytes: zeroFilledBytes,
		NearTail:        nearTail,
		Error:           msg,
	})
}

// emitTimeoutWarning emits a warning when chunk reads are timing out.
func (r *parallelDeviceReader) emitTimeoutWarning(expectedOffset int64, timeoutCount int, timeout time.Duration) {
	if r.warningCallback == nil {
		return
	}
	r.warningCallback(diskReadWarning{
		Path:           r.path,
		Reader:         "parallel",
		OffsetBytes:    expectedOffset,
		RequestedBytes: r.chunkSize,
		ReadBytes:      0,
		NearTail:       expectedOffset+r.chunkSize >= r.size,
		Error:          fmt.Sprintf("chunk read timeout #%d at offset %d (timeout: %v)", timeoutCount, expectedOffset, timeout),
	})
}

// compile-time check
var _ interface {
	io.Reader
	io.Seeker
	io.Closer
} = (*parallelDeviceReader)(nil)

func minInt(a, b int) int {
	if a < b {
		return a
	}
	return b
}

func ceilDiv(x, y int64) int64 {
	return int64(math.Ceil(float64(x) / float64(y)))
}
