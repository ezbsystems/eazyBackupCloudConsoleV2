-- Phase 16: dedupe shared-client Graph 429 accounting for batch runs.
--
-- In batch mode every child reports the SAME shared graph.Client cumulative
-- 429 count, so recording a per-child delta counted each real 429 ~N times
-- (N = active children), pinning recent_429_count to its cap and continuously
-- refreshing last_429_at, which blocked budget recovery and serialized tenants.
-- This high-water column lets the control plane record the shared client's 429
-- increments exactly once per tenant.
ALTER TABLE ms365_graph_tenant_budget
    ADD COLUMN IF NOT EXISTS last_seen_429_cumulative INT UNSIGNED NOT NULL DEFAULT 0 AFTER recent_429_count;
