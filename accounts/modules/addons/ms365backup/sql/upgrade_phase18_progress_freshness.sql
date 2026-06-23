ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `last_progress_at` INT UNSIGNED NULL DEFAULT NULL AFTER `updated_at`;

CREATE INDEX `idx_ms365_backup_runs_status_last_progress`
  ON `ms365_backup_runs` (`status`, `last_progress_at`);
