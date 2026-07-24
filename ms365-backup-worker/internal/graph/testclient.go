package graph

import (
	"net/http"
	"strings"
	"time"
)

// NewTestClient constructs a Client pointed at baseURL for integration tests.
// baseURL should be an httptest.Server URL; paths are appended directly (no /v1.0 suffix).
func NewTestClient(baseURL string, opts ClientOptions) *Client {
	c := NewClient("test-token", "", opts)
	c.graphBase = strings.TrimRight(baseURL, "/")
	c.httpClient = &http.Client{Timeout: 30 * time.Second, Transport: c.httpClient.Transport}
	c.streamClient = &http.Client{Timeout: 0, Transport: c.streamClient.Transport}
	// NewClient maps MaxRetries<=0 to 5; tests may pass 0 for single-attempt behavior.
	if opts.MaxRetries == 0 {
		c.maxRetries = 0
	}
	return c
}
