package graphfs

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"sync/atomic"
	"testing"
	"time"

	"github.com/eazybackup/ms365-backup-worker/internal/graph"
)

// BenchmarkContentStreamRecovery measures recovery overhead for trickle vs healthy streams.
func BenchmarkContentStreamRecovery(b *testing.B) {
	for _, mode := range []string{"healthy", "trickle"} {
		b.Run(mode, func(b *testing.B) {
			var recoveries atomic.Int64
			srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				if r.Header.Get("Range") != "" {
					recoveries.Add(1)
					off := r.Header.Get("Range")
					_ = off
					w.Header().Set("Content-Range", "bytes 8192-16383/1048576")
					w.WriteHeader(http.StatusPartialContent)
					_, _ = w.Write(make([]byte, 8192))
					return
				}
				w.Header().Set("Content-Length", "1048576")
				_, _ = w.Write(make([]byte, 8192))
			}))
			b.Cleanup(srv.Close)

			c := graph.NewTestClient(srv.URL, graph.ClientOptions{
				MaxRetries:                   0,
				MaxConcurrency:               4,
				ContentReadRetries:           3,
				ContentReadMinBytesPerSecond: 65536,
				ContentReadMinWindowSeconds:  1,
				ContentReadMinFileSizeBytes:  16 << 20,
			})
			policy := ContentThroughputPolicy{
				MinBytesPerSecond: 65536,
				WindowSeconds:     1,
				MinFileSizeBytes:  16 << 20,
			}

			b.ResetTimer()
			for i := 0; i < b.N; i++ {
				recoveries.Store(0)
				var body io.ReadCloser
				if mode == "trickle" {
					body = &slowTrickleReader{chunk: 64, interval: 50 * time.Millisecond, limit: 16384}
				} else {
					body = io.NopCloser(&fastReader{chunk: 128 << 10, limit: 256 << 10})
				}
				sr := &streamReader{
					ctx:        context.Background(),
					ReadCloser: body,
					size:       20 << 20,
					client:     c,
					path:       "/content",
					maxRetries: 3,
					policy:     policy,
					throughput: newThroughputWindow(policy, nil),
				}
				buf := make([]byte, 32<<10)
				deadline := time.Now().Add(2 * time.Second)
				for time.Now().Before(deadline) {
					if _, err := sr.Read(buf); err != nil {
						break
					}
				}
				_ = sr.Close()
			}
		})
	}
}

type fastReader struct {
	chunk  int
	limit  int
	sent   int
}

func (r *fastReader) Read(p []byte) (int, error) {
	if r.sent >= r.limit {
		return 0, io.EOF
	}
	n := r.chunk
	if n > len(p) {
		n = len(p)
	}
	if r.sent+n > r.limit {
		n = r.limit - r.sent
	}
	for i := range p[:n] {
		p[i] = 'f'
	}
	r.sent += n
	return n, nil
}
