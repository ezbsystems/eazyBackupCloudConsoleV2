package agent

import (
	"context"
	"fmt"
	"io"
	"math"
	"runtime"
	"sync"
)

const (
	defaultParallelChunkSize = 8 * 1024 * 1024 // 8 MiB
	defaultParallelWorkers   = 8               // capped to NumCPU
)

type parallelDeviceReader struct {
	path      string
	size      int64
	chunkSize int64
	workers   int

	mu        sync.Mutex
	ctx       context.Context
	cancel    context.CancelFunc
	tasks     chan int64
	results   chan chunkResult
	pending   map[int64][]byte
	expected  int64
	curBuf    []byte
	curPos    int
	chunkStart int64
	nextSend  int64
	inflight  int
	err       error
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

func newParallelDeviceReader(ctx context.Context, path string, size int64) (readSeekCloser, error) {
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
		path:      path,
		size:      size,
		chunkSize: chunkSize,
		workers:   workers,
		pending:   make(map[int64][]byte),
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

func (r *parallelDeviceReader) worker() {
	for offset := range r.tasks {
		bufSize := r.chunkSize
		if remain := r.size - offset; remain < bufSize {
			bufSize = remain
		}
		if bufSize <= 0 {
			r.results <- chunkResult{offset: offset, data: nil, n: 0, err: io.EOF}
			continue
		}

		f, err := openDeviceOptimized(r.path)
		if err != nil {
			r.results <- chunkResult{offset: offset, data: nil, n: 0, err: err}
			continue
		}

		_, err = f.Seek(offset, io.SeekStart)
		if err != nil {
			_ = f.Close()
			r.results <- chunkResult{offset: offset, data: nil, n: 0, err: err}
			continue
		}

		buf := make([]byte, bufSize)
		n, readErr := io.ReadFull(f, buf)
		if readErr == io.ErrUnexpectedEOF || readErr == io.EOF {
			// partial chunk ok
		} else if readErr != nil {
			_ = f.Close()
			r.results <- chunkResult{offset: offset, data: nil, n: n, err: readErr}
			continue
		}
		_ = f.Close()

		r.results <- chunkResult{offset: offset, data: buf[:n], n: n, err: readErr}
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
	for {
		select {
		case <-r.ctx.Done():
			return r.ctx.Err()
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
			// Otherwise continue waiting
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

	r.resetPipeline(r.ctx, newPos)
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

// compile-time check
var _ interface {
	io.Reader
	io.Seeker
	io.Closer
} = (*parallelDeviceReader)(nil)

// Utility for optional tuning via env (future extension)
func minInt(a, b int) int {
	if a < b {
		return a
	}
	return b
}

func ceilDiv(x, y int64) int64 {
	return int64(math.Ceil(float64(x) / float64(y)))
}

