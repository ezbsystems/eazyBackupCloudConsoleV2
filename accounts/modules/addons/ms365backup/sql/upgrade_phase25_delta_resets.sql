-- Audited delta-reset tombstones: exclude legacy delta_states_json at or before reset_at.

CREATE TABLE IF NOT EXISTS `ms365_delta_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_record_id` INT UNSIGNED NOT NULL,
  `e3_job_id` CHAR(36) NULL DEFAULT NULL,
  `physical_key` VARCHAR(191) NOT NULL,
  `reset_at` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(500) NULL DEFAULT NULL,
  `operator` VARCHAR(191) NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_delta_reset_lookup` (`tenant_record_id`, `physical_key`, `reset_at`),
  KEY `idx_ms365_delta_reset_job` (`tenant_record_id`, `e3_job_id`, `physical_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
