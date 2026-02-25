package agent

import "testing"

func TestRepoOperationTypeRouting(t *testing.T) {
	tests := []struct {
		name     string
		opType   string
		expected bool
	}{
		{"kopia_retention_apply", "kopia_retention_apply", true},
		{"kopia_maintenance_quick", "kopia_maintenance_quick", true},
		{"kopia_maintenance_full", "kopia_maintenance_full", true},
		{"retention_apply short", "retention_apply", true},
		{"maintenance_quick short", "maintenance_quick", true},
		{"maintenance_full short", "maintenance_full", true},
		{"unknown type", "unknown_op", false},
		{"restore type", "restore", false},
		{"empty type", "", false},
		{"case insensitive retention", "KOPIA_RETENTION_APPLY", true},
		{"case insensitive maintenance quick", "KOPIA_MAINTENANCE_QUICK", true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			op := &RepoOperation{OpType: tt.opType}
			got := op.isRepoRetentionType()
			if got != tt.expected {
				t.Errorf("isRepoRetentionType(%q) = %v, want %v", tt.opType, got, tt.expected)
			}
		})
	}
}
