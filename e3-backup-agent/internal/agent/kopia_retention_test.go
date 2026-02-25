package agent

import (
	"testing"
)

// TestImmediateFallbackUsesVaultPolicyForRetiredSource verifies that when a source
// is retired, buildEffectiveDailyMap immediately falls back to vault default policy
// (no job override applies).
func TestImmediateFallbackUsesVaultPolicyForRetiredSource(t *testing.T) {
	vaultDefault := 30
	input := SourcePolicyInput{
		VaultDefaultDays: vaultDefault,
		Sources: []SourcePolicyEntry{
			{Path: "/retired/path", Retired: true, OverrideDays: 7},
			{Path: "/active/override", Retired: false, OverrideDays: 14},
			{Path: "/active/no-override", Retired: false, OverrideDays: 0},
		},
	}
	got := buildEffectiveDailyMap(input)

	// Retired source: must use vault default (30), not override (7)
	if v := got["/retired/path"]; v != vaultDefault {
		t.Errorf("retired source /retired/path: got %d, want vault default %d", v, vaultDefault)
	}
	// Active with override > 0: must use override (14)
	if v := got["/active/override"]; v != 14 {
		t.Errorf("active+override source /active/override: got %d, want 14", v)
	}
	// Active with override 0: must use vault default (30)
	if v := got["/active/no-override"]; v != vaultDefault {
		t.Errorf("active no-override source /active/no-override: got %d, want vault default %d", v, vaultDefault)
	}
}

func TestParseEffectivePolicyFromMap(t *testing.T) {
	t.Run("nil map", func(t *testing.T) {
		out, err := parseEffectivePolicyFromMap(nil, 30)
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		if out.VaultDefaultDays != 30 {
			t.Errorf("vault_default_days: got %d, want 30", out.VaultDefaultDays)
		}
		if len(out.Sources) != 0 {
			t.Errorf("sources: got %d, want 0", len(out.Sources))
		}
	})
	t.Run("with vault_default_days and sources", func(t *testing.T) {
		m := map[string]any{
			"vault_default_days": float64(14),
			"sources": []any{
				map[string]any{"path": "/a", "retired": true, "override_days": 7},
				map[string]any{"path": "/b", "retired": false, "override_days": 3},
			},
		}
		out, err := parseEffectivePolicyFromMap(m, 30)
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		if out.VaultDefaultDays != 14 {
			t.Errorf("vault_default_days: got %d, want 14", out.VaultDefaultDays)
		}
		if len(out.Sources) != 2 {
			t.Fatalf("sources: got %d, want 2", len(out.Sources))
		}
		em := buildEffectiveDailyMap(out)
		if em["/a"] != 14 {
			t.Errorf("/a (retired): got %d, want 14", em["/a"])
		}
		if em["/b"] != 3 {
			t.Errorf("/b (active+override): got %d, want 3", em["/b"])
		}
	})
	t.Run("Comet-style map", func(t *testing.T) {
		m := map[string]any{
			"hourly":  float64(24),
			"daily":   float64(30),
			"weekly":  float64(8),
			"monthly": float64(12),
			"yearly":  float64(3),
		}
		out, err := parseEffectivePolicyFromMap(m, 14)
		if err != nil {
			t.Fatalf("unexpected error: %v", err)
		}
		if out.VaultDefaultDays != 30 {
			t.Errorf("VaultDefaultDays from daily: got %d, want 30", out.VaultDefaultDays)
		}
		if out.CometTiers == nil {
			t.Fatal("CometTiers should be set")
		}
		for k, want := range map[string]int{"hourly": 24, "daily": 30, "weekly": 8, "monthly": 12, "yearly": 3} {
			if got := out.CometTiers[k]; got != want {
				t.Errorf("CometTiers[%s]: got %d, want %d", k, got, want)
			}
		}
		rp := retentionPolicyFromCometTiers(out.CometTiers)
		if rp.KeepHourly == nil || *rp.KeepHourly != 24 {
			t.Errorf("KeepHourly: got %v, want 24", rp.KeepHourly)
		}
		if rp.KeepDaily == nil || *rp.KeepDaily != 30 {
			t.Errorf("KeepDaily: got %v, want 30", rp.KeepDaily)
		}
		if rp.KeepAnnual == nil || *rp.KeepAnnual != 3 {
			t.Errorf("KeepAnnual (from yearly): got %v, want 3", rp.KeepAnnual)
		}
	})
}
