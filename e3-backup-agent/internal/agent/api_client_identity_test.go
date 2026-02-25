package agent

import (
	"net/http/httptest"
	"os"
	"strings"
	"testing"
)

func TestAuthHeadersUseAgentUUID(t *testing.T) {
	cfg := &AgentConfig{
		APIBaseURL: "https://example.test/api",
		AgentUUID:  "6f78c615-3d2f-4b7f-8f5b-56dc0a3da781",
		AgentID:    "legacy-agent-id",
		AgentToken: "tok",
	}
	c := NewClient(cfg)
	req := httptest.NewRequest("POST", "https://example.test", nil)
	c.authHeaders(req)

	if got := req.Header.Get("X-Agent-UUID"); got != cfg.AgentUUID {
		t.Fatalf("expected X-Agent-UUID %q, got %q", cfg.AgentUUID, got)
	}
	legacyHeader := "X-Agent-" + "ID"
	if got := req.Header.Get(legacyHeader); got != "" {
		t.Fatalf("expected legacy header to be empty, got %q", got)
	}
	if got := req.Header.Get("X-Agent-UUID"); got == cfg.AgentID {
		t.Fatalf("expected X-Agent-UUID not to use legacy AgentID %q", cfg.AgentID)
	}
}

func TestEnrollResponseUsesAgentUUIDJsonTag(t *testing.T) {
	src, err := os.ReadFile("api_client.go")
	if err != nil {
		src, err = os.ReadFile("internal/agent/api_client.go")
		if err != nil {
			t.Fatal(err)
		}
	}
	text := string(src)
	if !strings.Contains(text, "json:\"agent_uuid\"") {
		t.Fatalf("expected EnrollResponse to use agent_uuid json tag")
	}
	if strings.Contains(text, "json:\"agent_id\"") {
		t.Fatalf("legacy agent_id json tag must be removed")
	}
}
