ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `abort_requested_at` INT UNSIGNED NULL DEFAULT NULL AFTER `last_progress_at`;
