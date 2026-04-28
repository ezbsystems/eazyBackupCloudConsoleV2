package applog

import (
	"fmt"
	"os"
	"sync"
)

// rotatingWriter is a tiny size-based log rotator. It keeps `keep` files total
// (active + N-1 rotated) and rolls atomically when MaxSize is exceeded. It is
// intentionally minimalist (no compression, no time-based rotation) because the
// goal is small, predictable disk footprint on the customer PC.
type rotatingWriter struct {
	mu      sync.Mutex
	path    string
	maxSize int64
	keep    int
	mode    os.FileMode
	f       *os.File
	size    int64
}

func newRotatingWriter(path string, maxSize int64, keep int, mode os.FileMode) (*rotatingWriter, error) {
	rw := &rotatingWriter{path: path, maxSize: maxSize, keep: keep, mode: mode}
	if err := rw.openLocked(); err != nil {
		return nil, err
	}
	return rw, nil
}

func (r *rotatingWriter) openLocked() error {
	f, err := os.OpenFile(r.path, os.O_CREATE|os.O_APPEND|os.O_WRONLY, r.mode)
	if err != nil {
		return err
	}
	stat, err := f.Stat()
	if err != nil {
		_ = f.Close()
		return err
	}
	r.f = f
	r.size = stat.Size()
	return nil
}

func (r *rotatingWriter) Write(p []byte) (int, error) {
	r.mu.Lock()
	defer r.mu.Unlock()
	if r.f == nil {
		if err := r.openLocked(); err != nil {
			return 0, err
		}
	}
	if r.size+int64(len(p)) > r.maxSize {
		if err := r.rotateLocked(); err != nil {
			// Best effort: if rotation fails, keep writing rather than losing logs.
			n, werr := r.f.Write(p)
			r.size += int64(n)
			if werr != nil {
				return n, werr
			}
			return n, err
		}
	}
	n, err := r.f.Write(p)
	r.size += int64(n)
	return n, err
}

// Close flushes and closes the underlying file.
func (r *rotatingWriter) Close() error {
	r.mu.Lock()
	defer r.mu.Unlock()
	if r.f != nil {
		err := r.f.Close()
		r.f = nil
		return err
	}
	return nil
}

func (r *rotatingWriter) rotateLocked() error {
	if r.f != nil {
		_ = r.f.Close()
		r.f = nil
	}
	// Shift older files: agent.log.(N-2) -> .(N-1), ..., agent.log -> agent.log.1
	for i := r.keep - 1; i >= 1; i-- {
		src := r.path
		if i > 1 {
			src = fmt.Sprintf("%s.%d", r.path, i-1)
		}
		dst := fmt.Sprintf("%s.%d", r.path, i)
		if _, err := os.Stat(src); err == nil {
			if i == r.keep-1 {
				_ = os.Remove(dst) // drop the oldest
			}
			_ = os.Rename(src, dst)
		}
	}
	return r.openLocked()
}
