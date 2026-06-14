ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `backup_user_id` INT UNSIGNED NULL DEFAULT NULL AFTER `whmcs_client_id`;

ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `e3_job_id` CHAR(36) NULL DEFAULT NULL AFTER `backup_user_id`;

ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `e3_batch_run_id` CHAR(36) NULL DEFAULT NULL AFTER `e3_job_id`;

ALTER TABLE `ms365_backup_runs`
  ADD KEY `idx_ms365_runs_e3_job` (`e3_job_id`, `created_at`);

ALTER TABLE `ms365_backup_runs`
  ADD KEY `idx_ms365_runs_e3_batch` (`e3_batch_run_id`, `created_at`);
