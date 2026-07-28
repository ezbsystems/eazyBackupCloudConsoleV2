CREATE TABLE IF NOT EXISTS `ms365_admin_user_controls` (
  `backup_user_id` INT UNSIGNED NOT NULL,
  `client_id` INT UNSIGNED NOT NULL,
  `admin_suspended_at` DATETIME NULL DEFAULT NULL,
  `admin_suspended_by` INT UNSIGNED NULL DEFAULT NULL,
  `prior_job_statuses_json` TEXT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`backup_user_id`),
  KEY `idx_ms365_admin_user_controls_client` (`client_id`),
  KEY `idx_ms365_admin_user_controls_suspended` (`admin_suspended_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
