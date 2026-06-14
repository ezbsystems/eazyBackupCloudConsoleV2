-- Phase 5 restore v2: granular Kopia restore, queue job types, batch linkage

ALTER TABLE `ms365_restore_runs`
  ADD COLUMN `e3_batch_run_id` CHAR(36) NULL AFTER `backup_run_id`,
  ADD COLUMN `source_batch_run_id` CHAR(36) NULL AFTER `e3_batch_run_id`,
  ADD COLUMN `selection_json` MEDIUMTEXT NULL AFTER `scope_json`,
  ADD COLUMN `target_resource_id` VARCHAR(128) NOT NULL DEFAULT '' AFTER `target_graph_id`,
  ADD COLUMN `conflict_policy` VARCHAR(32) NOT NULL DEFAULT 'skip_duplicates' AFTER `target_resource_id`,
  ADD COLUMN `source_manifest_id` VARCHAR(128) NOT NULL DEFAULT '' AFTER `conflict_policy`,
  ADD COLUMN `items_total` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `phase`,
  ADD COLUMN `items_done` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `items_total`,
  ADD COLUMN `items_skipped` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `items_done`,
  ADD KEY `idx_ms365_restore_batch` (`e3_batch_run_id`, `status`);

ALTER TABLE `ms365_job_queue`
  ADD COLUMN `job_type` ENUM('backup','restore') NOT NULL DEFAULT 'backup' AFTER `run_id`,
  ADD KEY `idx_ms365_job_type_status` (`job_type`, `status`, `priority`, `scheduled_at`);
