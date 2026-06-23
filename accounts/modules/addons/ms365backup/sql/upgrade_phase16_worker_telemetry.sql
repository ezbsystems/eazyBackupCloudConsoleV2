ALTER TABLE `ms365_worker_nodes`
  ADD COLUMN `cpu_pct` DECIMAL(5,2) NULL DEFAULT NULL AFTER `claim_admit_rejects`,
  ADD COLUMN `mem_used_mib` INT UNSIGNED NULL DEFAULT NULL AFTER `cpu_pct`,
  ADD COLUMN `mem_total_mib` INT UNSIGNED NULL DEFAULT NULL AFTER `mem_used_mib`,
  ADD COLUMN `disk_free_mib` INT UNSIGNED NULL DEFAULT NULL AFTER `mem_total_mib`,
  ADD COLUMN `disk_total_mib` INT UNSIGNED NULL DEFAULT NULL AFTER `disk_free_mib`,
  ADD COLUMN `telemetry_at` INT UNSIGNED NULL DEFAULT NULL AFTER `disk_total_mib`;

CREATE TABLE IF NOT EXISTS `ms365_worker_telemetry` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `node_id` CHAR(36) NOT NULL,
  `cpu_pct` DECIMAL(5,2) NULL DEFAULT NULL,
  `mem_used_mib` INT UNSIGNED NULL DEFAULT NULL,
  `mem_total_mib` INT UNSIGNED NULL DEFAULT NULL,
  `disk_free_mib` INT UNSIGNED NULL DEFAULT NULL,
  `disk_total_mib` INT UNSIGNED NULL DEFAULT NULL,
  `sampled_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_worker_telemetry_node` (`node_id`, `sampled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
