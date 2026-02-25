package agent

import (
	"context"
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"sort"

	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/snapshot"
	"github.com/kopia/kopia/snapshot/policy"
)

// SourcePolicyEntry describes retention policy for a single source.
// When Retired is true, vault default is used; when false and OverrideDays > 0, override is used.
type SourcePolicyEntry struct {
	Path         string
	Retired      bool
	OverrideDays int
}

// SourcePolicyInput holds vault default and per-source policy for retention resolution.
// CometTiers is set when server sends Comet-style map (hourly/daily/weekly/monthly/yearly).
// When CometTiers is non-nil, retention applies all tiers; otherwise only KeepDaily from vault/sources.
type SourcePolicyInput struct {
	VaultDefaultDays int
	Sources          []SourcePolicyEntry
	CometTiers       map[string]int // hourly, daily, weekly, monthly, yearly
}

// buildEffectiveDailyMap returns a map of source path -> keep_daily days.
// Rule: active source with override > 0 uses override; retired or override==0 uses vault default.
func buildEffectiveDailyMap(input SourcePolicyInput) map[string]int {
	out := make(map[string]int)
	for _, s := range input.Sources {
		if !s.Retired && s.OverrideDays > 0 {
			out[s.Path] = s.OverrideDays
		} else {
			out[s.Path] = input.VaultDefaultDays
		}
	}
	return out
}

// cometTierKeys are Comet-style retention keys from PHP (hourly, daily, weekly, monthly, yearly).
var cometTierKeys = []string{"hourly", "daily", "weekly", "monthly", "yearly"}

// parseEffectivePolicyFromMap parses effective_policy from server payload into SourcePolicyInput.
// Accepts two formats:
//   - Source-aware: {"vault_default_days": N, "sources": [{"path":"...","retired":bool,"override_days":N}]}
//   - Comet-style: {"hourly": N, "daily": N, "weekly": N, "monthly": N, "yearly": N}
//
// When Comet-style is present (at least "daily" or other tier keys), CometTiers is populated
// and VaultDefaultDays is taken from "daily". Source-aware format takes precedence when "sources" exists.
// Uses vaultDefaultFallback when vault_default_days/daily missing.
func parseEffectivePolicyFromMap(m map[string]any, vaultDefaultFallback int) (SourcePolicyInput, error) {
	if m == nil {
		return SourcePolicyInput{VaultDefaultDays: vaultDefaultFallback, Sources: nil}, nil
	}
	out := SourcePolicyInput{VaultDefaultDays: vaultDefaultFallback}

	// Parse Comet-style tiers (hourly, daily, weekly, monthly, yearly)
	cometTiers := make(map[string]int)
	hasComet := false
	for _, key := range cometTierKeys {
		if v, ok := m[key]; ok {
			switch n := v.(type) {
			case float64:
				cometTiers[key] = int(n)
				hasComet = true
			case int:
				cometTiers[key] = n
				hasComet = true
			case json.Number:
				if i, err := n.Int64(); err == nil {
					cometTiers[key] = int(i)
					hasComet = true
				}
			}
		}
	}
	if hasComet {
		out.CometTiers = cometTiers
		if d, ok := cometTiers["daily"]; ok && d > 0 {
			out.VaultDefaultDays = d
		}
	}

	// Source-aware format
	if v, ok := m["vault_default_days"]; ok {
		switch n := v.(type) {
		case float64:
			out.VaultDefaultDays = int(n)
		case int:
			out.VaultDefaultDays = n
		case json.Number:
			if i, err := n.Int64(); err == nil {
				out.VaultDefaultDays = int(i)
			}
		}
	}
	srcs, ok := m["sources"]
	if !ok {
		return out, nil
	}
	arr, ok := srcs.([]any)
	if !ok {
		return out, nil
	}
	for _, item := range arr {
		ent, ok := item.(map[string]any)
		if !ok {
			continue
		}
		e := SourcePolicyEntry{}
		if p, ok := ent["path"].(string); ok {
			e.Path = p
		}
		if r, ok := ent["retired"].(bool); ok {
			e.Retired = r
		}
		if v, ok := ent["override_days"]; ok {
			switch n := v.(type) {
			case float64:
				e.OverrideDays = int(n)
			case int:
				e.OverrideDays = n
			case json.Number:
				if i, err := n.Int64(); err == nil {
					e.OverrideDays = int(i)
				}
			}
		}
		if e.Path != "" {
			out.Sources = append(out.Sources, e)
		}
	}
	return out, nil
}

// RetentionApplyResult holds metrics from a retention apply operation.
type RetentionApplyResult struct {
	DeletedCount int      `json:"deleted_count"`
	SourcesCount int      `json:"sources_count"`
	Error        string   `json:"error,omitempty"`
}

// kopiaRetentionApply applies Kopia forget logic using effective policy context.
// Opens repo at repoPath, parses effectivePolicy, sets policy per source, and runs ApplyRetentionPolicy.
// Returns metrics/result payload for CompleteRepoOperation.
func (r *Runner) kopiaRetentionApply(ctx context.Context, run *NextRunResponse, effectivePolicy map[string]any) (RetentionApplyResult, error) {
	opts := kopiaOptionsFromRun(r.cfg, run)
	repoPath := kopiaRepoConfigPath(r.cfg, run)
	password := opts.password()
	if _, statErr := os.Stat(repoPath); os.IsNotExist(statErr) && run != nil && run.RepoConfigKey != "" {
		st, stErr := opts.storage(ctx)
		if stErr != nil {
			return RetentionApplyResult{}, fmt.Errorf("kopia: storage init for retention: %w", stErr)
		}
		if err := os.MkdirAll(filepath.Dir(repoPath), 0o755); err != nil {
			return RetentionApplyResult{}, fmt.Errorf("kopia: mkdir repo dir: %w", err)
		}
		if connErr := repo.Connect(ctx, repoPath, st, password, nil); connErr != nil {
			return RetentionApplyResult{}, fmt.Errorf("kopia: connect for retention: %w", connErr)
		}
	}
	rep, err := repo.Open(ctx, repoPath, password, nil)
	if err != nil {
		return RetentionApplyResult{}, fmt.Errorf("kopia: open for retention: %w", err)
	}
	defer rep.Close(ctx)
	dr, ok := rep.(repo.DirectRepository)
	if !ok {
		return RetentionApplyResult{}, fmt.Errorf("kopia: repo does not support retention apply")
	}
	input, err := parseEffectivePolicyFromMap(effectivePolicy, 30)
	if err != nil {
		return RetentionApplyResult{}, fmt.Errorf("kopia: parse effective policy: %w", err)
	}
	effectiveMap := buildEffectiveDailyMap(input)
	sources, err := snapshot.ListSources(ctx, rep)
	if err != nil {
		return RetentionApplyResult{}, fmt.Errorf("kopia: list sources: %w", err)
	}
	sort.Slice(sources, func(i, j int) bool { return sources[i].String() < sources[j].String() })
	var totalDeleted int
	for _, src := range sources {
		retPol := policy.RetentionPolicy{}
		if input.CometTiers != nil {
			retPol = retentionPolicyFromCometTiers(input.CometTiers)
		} else {
			days := effectiveMap[src.Path]
			if days <= 0 {
				days = input.VaultDefaultDays
			}
			if days <= 0 {
				days = 30
			}
			retPol = policy.RetentionPolicy{KeepDaily: intPtr(days)}
		}
		pol := &policy.Policy{RetentionPolicy: retPol}
		err := repo.DirectWriteSession(ctx, dr, repo.WriteSessionOptions{Purpose: "retention"}, func(wctx context.Context, dw repo.DirectRepositoryWriter) error {
			if setErr := policy.SetPolicy(wctx, dw, src, pol); setErr != nil {
				return setErr
			}
			deleted, applyErr := policy.ApplyRetentionPolicy(wctx, dw, src, true)
			if applyErr != nil {
				return applyErr
			}
			totalDeleted += len(deleted)
			return nil
		})
		if err != nil {
			return RetentionApplyResult{DeletedCount: totalDeleted, SourcesCount: len(sources)}, fmt.Errorf("kopia: apply retention for %v: %w", src, err)
		}
	}
	return RetentionApplyResult{DeletedCount: totalDeleted, SourcesCount: len(sources)}, nil
}

func intPtr(n int) *int { p := &n; return p }

// retentionPolicyFromCometTiers builds Kopia RetentionPolicy from Comet-style map.
// Keys: hourly, daily, weekly, monthly, yearly. Maps yearly -> KeepAnnual.
func retentionPolicyFromCometTiers(tiers map[string]int) policy.RetentionPolicy {
	rp := policy.RetentionPolicy{}
	if v := tiers["hourly"]; v > 0 {
		rp.KeepHourly = intPtr(v)
	}
	if v := tiers["daily"]; v > 0 {
		rp.KeepDaily = intPtr(v)
	}
	if v := tiers["weekly"]; v > 0 {
		rp.KeepWeekly = intPtr(v)
	}
	if v := tiers["monthly"]; v > 0 {
		rp.KeepMonthly = intPtr(v)
	}
	if v := tiers["yearly"]; v > 0 {
		rp.KeepAnnual = intPtr(v)
	}
	if rp.KeepDaily == nil && rp.KeepHourly == nil && rp.KeepWeekly == nil && rp.KeepMonthly == nil && rp.KeepAnnual == nil {
		rp.KeepDaily = intPtr(30)
	}
	return rp
}
