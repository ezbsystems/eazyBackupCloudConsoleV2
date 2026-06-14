ALTER TABLE `ms365_worker_nodes`
  ADD COLUMN `deploy_status` ENUM('current','pending','updating','failed') NOT NULL DEFAULT 'current' AFTER `version`,
  ADD COLUMN `target_release_id` INT UNSIGNED NULL DEFAULT NULL AFTER `deploy_status`,
  ADD COLUMN `deploy_error` VARCHAR(500) NOT NULL DEFAULT '' AFTER `target_release_id`,
  ADD COLUMN `deploy_updated_at` INT UNSIGNED NULL DEFAULT NULL AFTER `deploy_error`;

CREATE TABLE IF NOT EXISTS `ms365_worker_fleet_state` (
  `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  `target_release_id` INT UNSIGNED NULL DEFAULT NULL,
  `deploy_strategy` VARCHAR(32) NOT NULL DEFAULT 'rolling',
  `deploy_force` TINYINT(1) NOT NULL DEFAULT 0,
  `canary_node_id` CHAR(36) NULL DEFAULT NULL,
  `active_deploy_job_id` INT UNSIGNED NULL DEFAULT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  CONSTRAINT `chk_ms365_fleet_state_singleton` CHECK (`id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ms365_worker_fleet_state` (`id`, `updated_at`) VALUES (1, UNIX_TIMESTAMP());

CREATE TABLE IF NOT EXISTS `ms365_worker_releases` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `version` VARCHAR(64) NOT NULL,
  `git_ref` VARCHAR(191) NOT NULL DEFAULT '',
  `sha256` CHAR(64) NOT NULL,
  `artifact_path` VARCHAR(512) NOT NULL,
  `artifact_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `build_job_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_by_admin_id` INT UNSIGNED NULL DEFAULT NULL,
  `notes` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  UNIQUE KEY `uniq_ms365_worker_release_version` (`version`),
  KEY `idx_ms365_worker_release_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms365_worker_build_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `created_by_admin_id` INT UNSIGNED NULL DEFAULT NULL,
  `git_ref` VARCHAR(191) NOT NULL DEFAULT 'main',
  `version_label` VARCHAR(64) NOT NULL,
  `flags_json` TEXT NULL,
  `status` ENUM('queued','running','succeeded','failed','cancelled') NOT NULL DEFAULT 'queued',
  `current_step` VARCHAR(64) NOT NULL DEFAULT '',
  `error_message` TEXT NULL,
  `release_id` INT UNSIGNED NULL DEFAULT NULL,
  `host_runner` VARCHAR(191) NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `started_at` INT UNSIGNED NULL DEFAULT NULL,
  `ended_at` INT UNSIGNED NULL DEFAULT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_worker_build_status` (`status`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms365_worker_build_steps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `job_id` INT UNSIGNED NOT NULL,
  `step_key` VARCHAR(64) NOT NULL,
  `seq` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('pending','running','succeeded','failed','skipped') NOT NULL DEFAULT 'pending',
  `exit_code` INT NULL DEFAULT NULL,
  `summary` VARCHAR(500) NOT NULL DEFAULT '',
  `log_path` VARCHAR(512) NOT NULL DEFAULT '',
  `started_at` INT UNSIGNED NULL DEFAULT NULL,
  `ended_at` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_worker_build_step_job` (`job_id`, `seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms365_worker_deploy_jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `release_id` INT UNSIGNED NOT NULL,
  `strategy` VARCHAR(32) NOT NULL DEFAULT 'rolling',
  `force_deploy` TINYINT(1) NOT NULL DEFAULT 0,
  `canary_node_id` CHAR(36) NULL DEFAULT NULL,
  `status` ENUM('pending','rolling','succeeded','failed','cancelled') NOT NULL DEFAULT 'pending',
  `nodes_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `nodes_updated` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_message` TEXT NULL,
  `created_by_admin_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `started_at` INT UNSIGNED NULL DEFAULT NULL,
  `ended_at` INT UNSIGNED NULL DEFAULT NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_worker_deploy_status` (`status`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms365_worker_fleet_audit` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NULL DEFAULT NULL,
  `action` VARCHAR(64) NOT NULL,
  `subject_type` VARCHAR(64) NOT NULL DEFAULT '',
  `subject_id` VARCHAR(64) NOT NULL DEFAULT '',
  `message` VARCHAR(500) NOT NULL DEFAULT '',
  `context_json` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_fleet_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms365_worker_artifact_downloads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `release_id` INT UNSIGNED NOT NULL,
  `node_id` CHAR(36) NOT NULL,
  `nonce` VARCHAR(128) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `downloaded_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_artifact_dl_release` (`release_id`, `downloaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
