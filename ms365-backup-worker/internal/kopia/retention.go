package kopia

import (
	"context"
	"encoding/json"
	"fmt"
	"sort"

	"github.com/kopia/kopia/repo"
	"github.com/kopia/kopia/repo/maintenance"
	"github.com/kopia/kopia/snapshot"
	"github.com/kopia/kopia/snapshot/policy"
)

// RetentionResult holds metrics from retention apply.
type RetentionResult struct {
	DeletedCount  int `json:"deleted_count"`
	SourcesCount  int `json:"sources_count"`
}

// ApplyRetention applies Comet-tier retention policy to all snapshot sources in the repo.
func ApplyRetention(ctx context.Context, pool *Pool, storage StorageOptions, maxPackSizeMiB int, effectivePolicy map[string]any) (RetentionResult, error) {
	rep, release, err := pool.Acquire(ctx, storage, maxPackSizeMiB)
	if err != nil {
		return RetentionResult{}, err
	}
	defer release()

	dr, ok := rep.(repo.DirectRepository)
	if !ok {
		return RetentionResult{}, fmt.Errorf("kopia: repository does not support retention apply")
	}

	tiers := cometTiersFromMap(effectivePolicy)
	retPol := retentionPolicyFromCometTiers(tiers)

	sources, err := snapshot.ListSources(ctx, rep)
	if err != nil {
		return RetentionResult{}, fmt.Errorf("kopia: list sources: %w", err)
	}
	sort.Slice(sources, func(i, j int) bool { return sources[i].String() < sources[j].String() })

	var totalDeleted int
	for _, src := range sources {
		err := repo.DirectWriteSession(ctx, dr, repo.WriteSessionOptions{Purpose: "retention"}, func(wctx context.Context, dw repo.DirectRepositoryWriter) error {
			pol := &policy.Policy{RetentionPolicy: retPol}
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
			return RetentionResult{DeletedCount: totalDeleted, SourcesCount: len(sources)}, fmt.Errorf("kopia: apply retention for %v: %w", src, err)
		}
	}

	return RetentionResult{DeletedCount: totalDeleted, SourcesCount: len(sources)}, nil
}

// RunMaintenance runs Kopia quick or full maintenance.
func RunMaintenance(ctx context.Context, pool *Pool, storage StorageOptions, maxPackSizeMiB int, quick bool) error {
	rep, release, err := pool.Acquire(ctx, storage, maxPackSizeMiB)
	if err != nil {
		return err
	}
	defer release()

	dr, ok := rep.(repo.DirectRepository)
	if !ok {
		return fmt.Errorf("kopia: repository does not support maintenance")
	}

	mode := maintenance.ModeQuick
	if !quick {
		mode = maintenance.ModeFull
	}

	return repo.DirectWriteSession(ctx, dr, repo.WriteSessionOptions{Purpose: "maintenance"}, func(wctx context.Context, dw repo.DirectRepositoryWriter) error {
		return maintenance.RunExclusive(wctx, dw, mode, true, func(ctx context.Context, rp maintenance.RunParameters) error {
			return maintenance.Run(ctx, rp, maintenance.SafetyParameters{})
		})
	})
}

func cometTiersFromMap(m map[string]any) map[string]int {
	out := make(map[string]int)
	if m == nil {
		return out
	}
	for _, key := range []string{"hourly", "daily", "weekly", "monthly", "yearly"} {
		if v, ok := m[key]; ok {
			if n := jsonInt(v); n > 0 {
				out[key] = n
			}
		}
	}
	return out
}

func jsonInt(v any) int {
	switch n := v.(type) {
	case float64:
		return int(n)
	case int:
		return n
	case int64:
		return int(n)
	case json.Number:
		i, _ := n.Int64()
		return int(i)
	default:
		return 0
	}
}

func intPtr(n int) *int {
	p := n
	return &p
}

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
