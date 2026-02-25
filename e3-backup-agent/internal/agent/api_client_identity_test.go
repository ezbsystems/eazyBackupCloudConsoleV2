package agent

import (
	"net/http/httptest"
	"testing"
)

func TestAuthHeadersUseAgentUUID(t *testing.T) {
	cfg := &AgentConfig{APIBaseURL: "https://example.test/api", AgentID: "6f78c615-3d2f-4b7f-8f5b-56dc0a3da781", AgentToken: "tok"}
	c := NewClient(cfg)
	req := httptest.NewRequest("POST", "https://example.test", nil)
	c.authHeaders(req)

	if got := req.Header.Get("X-Agent-UUID"); got != cfg.AgentID {
		t.Fatalf("expected X-Agent-UUID %q, got %q", cfg.AgentID, got)
	}
	if got := req.Header.Get("X-Agent-ID"); got != "" {
		t.Fatalf("expected X-Agent-ID to be empty, got %q", got)
	}
}
