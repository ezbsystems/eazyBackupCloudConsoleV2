CREATE TABLE IF NOT EXISTS `ms365_worker_nodes` (
  `node_id` CHAR(36) NOT NULL PRIMARY KEY,
  `hostname` VARCHAR(191) NOT NULL DEFAULT '',
  `proxmox_vmid` INT UNSIGNED NULL DEFAULT NULL,
  `status` ENUM('registering','active','draining','offline','retired') NOT NULL DEFAULT 'registering',
  `max_concurrent_runs` INT UNSIGNED NOT NULL DEFAULT 10,
  `current_load` INT UNSIGNED NOT NULL DEFAULT 0,
  `version` VARCHAR(32) NOT NULL DEFAULT '',
  `last_heartbeat_at` INT UNSIGNED NULL,
  `registered_at` INT UNSIGNED NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_worker_status` (`status`, `last_heartbeat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ms365_job_queue`
  ADD COLUMN `worker_node_id` CHAR(36) NULL DEFAULT NULL AFTER `run_id`,
  ADD COLUMN `claimed_at` INT UNSIGNED NULL DEFAULT NULL AFTER `started_at`,
  ADD COLUMN `lease_expires_at` INT UNSIGNED NULL DEFAULT NULL AFTER `claimed_at`,
  ADD KEY `idx_ms365_job_worker` (`worker_node_id`, `status`);

ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `manifest_id` VARCHAR(128) NOT NULL DEFAULT '' AFTER `backup_path`,
  ADD COLUMN `engine_mode` VARCHAR(32) NOT NULL DEFAULT 'php' AFTER `manifest_id`,
  ADD COLUMN `bytes_hashed` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `engine_mode`,
  ADD COLUMN `bytes_uploaded` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `bytes_hashed`,
  ADD KEY `idx_ms365_runs_manifest` (`manifest_id`);

CREATE TABLE IF NOT EXISTS `ms365_delta_state` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `tenant_record_id` INT UNSIGNED NOT NULL,
  `physical_key` VARCHAR(191) NOT NULL,
  `workload` VARCHAR(64) NOT NULL,
  `state_key` VARCHAR(191) NOT NULL,
  `delta_link` TEXT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  UNIQUE KEY `uniq_ms365_delta` (`tenant_record_id`, `physical_key`, `workload`, `state_key`),
  KEY `idx_ms365_delta_tenant` (`tenant_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
