CREATE TABLE IF NOT EXISTS `ms365_restore_runs` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `tenant_record_id` INT UNSIGNED NOT NULL,
  `whmcs_client_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `resource_type` VARCHAR(64) NOT NULL DEFAULT '',
  `target_graph_id` VARCHAR(128) NOT NULL DEFAULT '',
  `backup_run_id` CHAR(36) NULL,
  `status` ENUM('queued','running','success','error','cancelled') NOT NULL DEFAULT 'queued',
  `phase` VARCHAR(64) NOT NULL DEFAULT '',
  `scope_json` TEXT NULL,
  `error_message` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `started_at` INT UNSIGNED NULL,
  `finished_at` INT UNSIGNED NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_restore_client` (`whmcs_client_id`, `created_at`),
  KEY `idx_ms365_restore_tenant` (`tenant_record_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
