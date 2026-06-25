-- Tenant-owner batch claim table (Option A from MS365_TENANT_OWNER_REDESIGN.md).
CREATE TABLE IF NOT EXISTS `ms365_batch_claims` (
  `batch_run_id` CHAR(36) NOT NULL,
  `tenant_record_id` INT UNSIGNED NOT NULL,
  `worker_node_id` VARCHAR(64) DEFAULT NULL,
  `status` ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued',
  `claimed_at` INT UNSIGNED DEFAULT NULL,
  `lease_expires_at` INT UNSIGNED DEFAULT NULL,
  `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  `last_heartbeat_at` INT UNSIGNED DEFAULT NULL,
  `last_progress_at` INT UNSIGNED DEFAULT NULL,
  `error_message` VARCHAR(500) DEFAULT NULL,
  `priority` INT NOT NULL DEFAULT 100,
  `running_tenant_key` INT UNSIGNED DEFAULT NULL COMMENT 'tenant_record_id when running else NULL',
  `created_at` INT UNSIGNED NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`batch_run_id`),
  UNIQUE KEY `uq_ms365_batch_one_running_per_tenant` (`running_tenant_key`),
  KEY `idx_ms365_batch_claim_select` (`status`, `priority`, `batch_run_id`),
  KEY `idx_ms365_batch_worker` (`worker_node_id`, `status`),
  KEY `idx_ms365_batch_tenant` (`tenant_record_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
