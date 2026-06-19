CREATE TABLE IF NOT EXISTS `ms365_run_worker_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id` CHAR(36) NOT NULL,
  `worker_node_id` CHAR(36) NOT NULL,
  `claimed_at` INT UNSIGNED NOT NULL,
  `released_at` INT UNSIGNED NULL DEFAULT NULL,
  `release_reason` VARCHAR(64) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ms365_rwa_run` (`run_id`),
  KEY `idx_ms365_rwa_node_time` (`worker_node_id`, `claimed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms365_worker_log_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `run_id` CHAR(36) NOT NULL,
  `worker_node_id` CHAR(36) NOT NULL,
  `level` VARCHAR(16) NOT NULL DEFAULT 'info',
  `message` TEXT NOT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ms365_wll_run` (`run_id`, `id`),
  KEY `idx_ms365_wll_node_time` (`worker_node_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
