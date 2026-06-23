ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `last_429_at` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_progress_at`;

CREATE INDEX `idx_ms365_backup_runs_status_last_429`
  ON `ms365_backup_runs` (`status`, `last_429_at`);
