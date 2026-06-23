CREATE TABLE IF NOT EXISTS `ms365_worker_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `version` INT UNSIGNED NOT NULL,
  `yaml` LONGTEXT NOT NULL,
  `sha256` CHAR(64) NOT NULL,
  `created_by_admin_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` INT UNSIGNED NOT NULL,
  UNIQUE KEY `uniq_ms365_worker_config_version` (`version`),
  KEY `idx_ms365_worker_config_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ms365_worker_nodes`
  ADD COLUMN `config_version` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `deploy_updated_at`,
  ADD COLUMN `target_config_version` INT UNSIGNED NULL DEFAULT NULL AFTER `config_version`,
  ADD COLUMN `config_status` ENUM('current','pending','applying','failed') NOT NULL DEFAULT 'current' AFTER `target_config_version`,
  ADD COLUMN `config_error` VARCHAR(500) NOT NULL DEFAULT '' AFTER `config_status`,
  ADD COLUMN `config_updated_at` INT UNSIGNED NULL DEFAULT NULL AFTER `config_error`;
