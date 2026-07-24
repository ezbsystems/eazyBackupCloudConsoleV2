package graph

import (
	"errors"
	"io"
	"time"
)

// ErrContentReadIdleTimeout is returned when a Graph content stream delivers no
// bytes for longer than the configured idle window.
var ErrContentReadIdleTimeout = errors.New("graph content read idle timeout")

// IsContentReadIdleTimeout reports whether err is ErrContentReadIdleTimeout.
func IsContentReadIdleTimeout(err error) bool {
	return errors.Is(err, ErrContentReadIdleTimeout)
}

type idleTimeoutReader struct {
	rc   io.ReadCloser
	idle time.Duration
}

func newIdleTimeoutReader(rc io.ReadCloser, idle time.Duration) io.ReadCloser {
	if idle <= 0 {
		return rc
	}
	return &idleTimeoutReader{rc: rc, idle: idle}
}

func (r *idleTimeoutReader) Read(p []byte) (int, error) {
	type readResult struct {
		n   int
		err error
	}
	ch := make(chan readResult, 1)
	go func() {
		n, err := r.rc.Read(p)
		ch <- readResult{n: n, err: err}
	}()

	timer := time.NewTimer(r.idle)
	defer timer.Stop()

	select {
	case res := <-ch:
		return res.n, res.err
	case <-timer.C:
		_ = r.rc.Close()
		return 0, ErrContentReadIdleTimeout
	}
}

func (r *idleTimeoutReader) Close() error {
	return r.rc.Close()
}
