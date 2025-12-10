-- Cloud NAS Schema
-- Run this SQL to create the required tables for Cloud NAS feature

-- Mount configurations
CREATE TABLE IF NOT EXISTS `s3_cloudnas_mounts` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` INT(11) UNSIGNED NOT NULL,
    `agent_id` INT(11) UNSIGNED NOT NULL,
    `bucket_name` VARCHAR(255) NOT NULL,
    `prefix` VARCHAR(1024) DEFAULT '',
    `drive_letter` CHAR(1) NOT NULL,
    `read_only` TINYINT(1) DEFAULT 0,
    `persistent` TINYINT(1) DEFAULT 1,
    `cache_mode` VARCHAR(20) DEFAULT 'full',
    `status` VARCHAR(20) DEFAULT 'unmounted',
    `error` TEXT DEFAULT NULL,
    `last_mounted_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_client` (`client_id`),
    KEY `idx_agent` (`agent_id`),
    KEY `idx_client_agent_letter` (`client_id`, `agent_id`, `drive_letter`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Client settings for Cloud NAS
CREATE TABLE IF NOT EXISTS `s3_cloudnas_settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` INT(11) UNSIGNED NOT NULL,
    `settings_json` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

