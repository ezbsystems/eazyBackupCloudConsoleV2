-- Per-Entra-tenant Graph API concurrency budget (AIMD coordinated across worker fleet).

CREATE TABLE IF NOT EXISTS `ms365_graph_tenant_budget` (
    `azure_tenant_id` VARCHAR(64) NOT NULL,
    `graph_budget` INT UNSIGNED NOT NULL DEFAULT 96,
    `recent_429_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`azure_tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
