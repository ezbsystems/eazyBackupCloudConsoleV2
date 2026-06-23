-- Per-tenant Graph budget: timestamp of last 429 for time-decay recovery.

ALTER TABLE `ms365_graph_tenant_budget`
    ADD COLUMN `last_429_at` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `recent_429_count`;
