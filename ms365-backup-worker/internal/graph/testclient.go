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
	c.httpClient = &http.Client{Timeout: 30 * time.Second}
	return c
}
