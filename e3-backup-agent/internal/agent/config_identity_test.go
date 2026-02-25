package agent

import "testing"

func TestValidateRejectsLegacyAgentIDEnrollment(t *testing.T) {
	cfg := &AgentConfig{
		APIBaseURL: "https://example.test/api",
		AgentID:    "legacy-id",
		AgentToken: "tok",
	}
	if err := cfg.Validate(); err == nil {
		t.Fatalf("expected validation error when only legacy agent_id is present")
	}
}
