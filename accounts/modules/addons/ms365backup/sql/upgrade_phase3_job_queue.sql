CREATE TABLE IF NOT EXISTS `ms365_job_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `run_id` CHAR(36) NOT NULL,
  `status` ENUM('queued','running','done','failed') NOT NULL DEFAULT 'queued',
  `priority` INT NOT NULL DEFAULT 100,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `error_message` TEXT NULL,
  `scheduled_at` INT UNSIGNED NOT NULL,
  `started_at` INT UNSIGNED NULL,
  `finished_at` INT UNSIGNED NULL,
  `created_at` INT UNSIGNED NOT NULL,
  UNIQUE KEY `uniq_ms365_job_run` (`run_id`),
  KEY `idx_ms365_job_status` (`status`, `priority`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
