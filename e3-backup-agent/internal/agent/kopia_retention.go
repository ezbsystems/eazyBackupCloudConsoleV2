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
type SourcePolicyInput struct {
	VaultDefaultDays int
	Sources          []SourcePolicyEntry
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

// parseEffectivePolicyFromMap parses effective_policy from server payload into SourcePolicyInput.
// Expects: {"vault_default_days": N, "sources": [{"path":"...","retired":bool,"override_days":N}]}
// Returns nil and error when parsing fails. Uses vaultDefaultFallback when vault_default_days missing.
func parseEffectivePolicyFromMap(m map[string]any, vaultDefaultFallback int) (SourcePolicyInput, error) {
	if m == nil {
		return SourcePolicyInput{VaultDefaultDays: vaultDefaultFallback, Sources: nil}, nil
	}
	out := SourcePolicyInput{VaultDefaultDays: vaultDefaultFallback}
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
		days := effectiveMap[src.Path]
		if days <= 0 {
			days = input.VaultDefaultDays
		}
		if days <= 0 {
			days = 30
		}
		pol := &policy.Policy{RetentionPolicy: policy.RetentionPolicy{KeepDaily: intPtr(days)}}
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
