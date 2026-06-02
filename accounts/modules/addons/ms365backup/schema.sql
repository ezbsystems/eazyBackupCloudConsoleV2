CREATE TABLE IF NOT EXISTS `ms365_tenant_config` (
  `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  `region` VARCHAR(64) NOT NULL DEFAULT 'GlobalPublicCloud',
  `tenant_id` VARCHAR(64) NOT NULL DEFAULT '',
  `client_id` VARCHAR(64) NOT NULL DEFAULT '',
  `app_secret_enc` TEXT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ms365_tenant_config` (`id`, `region`, `tenant_id`, `client_id`) VALUES (1, 'GlobalPublicCloud', '', '');

CREATE TABLE IF NOT EXISTS `ms365_backup_runs` (
  `id` CHAR(36) NOT NULL PRIMARY KEY,
  `user_id` VARCHAR(128) NOT NULL,
  `user_upn` VARCHAR(255) NOT NULL DEFAULT '',
  `user_display_name` VARCHAR(255) NOT NULL DEFAULT '',
  `resource_id` VARCHAR(191) NOT NULL DEFAULT '',
  `resource_type` VARCHAR(64) NOT NULL DEFAULT '',
  `graph_id` VARCHAR(128) NOT NULL DEFAULT '',
  `physical_key` VARCHAR(191) NOT NULL DEFAULT '',
  `status` ENUM('queued','running','success','error','cancelled','skipped') NOT NULL DEFAULT 'queued',
  `phase` VARCHAR(64) NOT NULL DEFAULT '',
  `items_done` INT UNSIGNED NOT NULL DEFAULT 0,
  `items_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `percent` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `backup_path` VARCHAR(512) NOT NULL DEFAULT '',
  `backup_mail` TINYINT(1) NOT NULL DEFAULT 1,
  `backup_calendar` TINYINT(1) NOT NULL DEFAULT 1,
  `scope_json` TEXT NULL,
  `logical_sources_json` TEXT NULL,
  `error_message` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  `started_at` INT UNSIGNED NULL,
  `finished_at` INT UNSIGNED NULL,
  `updated_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_runs_status` (`status`, `created_at`),
  KEY `idx_ms365_runs_user` (`user_id`, `created_at`),
  KEY `idx_ms365_runs_physical` (`physical_key`, `created_at`),
  KEY `idx_ms365_runs_resource_type` (`resource_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms365_backup_log_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `run_id` CHAR(36) NOT NULL,
  `level` VARCHAR(16) NOT NULL DEFAULT 'info',
  `message` TEXT NOT NULL,
  `context_json` TEXT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  KEY `idx_ms365_logs_run` (`run_id`, `id`),
  KEY `idx_ms365_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
