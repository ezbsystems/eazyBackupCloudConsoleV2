-- MS365 Backup billing tables (Phase 7)

CREATE TABLE IF NOT EXISTS `ms365_billing_usage_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `service_id` INT UNSIGNED NOT NULL,
  `client_id` INT UNSIGNED NOT NULL,
  `backup_user_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `metric` ENUM('protected_users','onedrive_overage_gib') NOT NULL,
  `qty` INT UNSIGNED NOT NULL DEFAULT 0,
  `taken_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ms365_billing_snap_service` (`service_id`, `metric`, `taken_at`),
  KEY `idx_ms365_billing_snap_client` (`client_id`, `taken_at`),
  KEY `idx_ms365_billing_snap_backup_user` (`backup_user_id`, `metric`, `taken_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms365_onedrive_usage_daily` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT UNSIGNED NOT NULL,
  `backup_user_id` INT UNSIGNED NOT NULL,
  `tenant_record_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `azure_user_id` VARCHAR(128) NOT NULL DEFAULT '',
  `upn` VARCHAR(255) NOT NULL DEFAULT '',
  `display_name` VARCHAR(255) NOT NULL DEFAULT '',
  `drive_id` VARCHAR(128) NOT NULL DEFAULT '',
  `used_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `included_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `overage_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `collected_date` DATE NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_ms365_od_daily` (`backup_user_id`, `azure_user_id`, `collected_date`),
  KEY `idx_ms365_od_daily_client` (`client_id`, `collected_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms365_billing_trial_state` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `service_id` INT UNSIGNED NOT NULL,
  `client_id` INT UNSIGNED NOT NULL,
  `trial_started_at` TIMESTAMP NULL DEFAULT NULL,
  `trial_ends_at` TIMESTAMP NULL DEFAULT NULL,
  `status` ENUM('trialing','converted','suspended_no_payment','cancelled') NOT NULL DEFAULT 'trialing',
  `last_evaluated_at` TIMESTAMP NULL DEFAULT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_ms365_billing_trial_service` (`service_id`),
  KEY `idx_ms365_billing_trial_client` (`client_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ms365_billing_rated_lines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `service_id` INT UNSIGNED NOT NULL,
  `client_id` INT UNSIGNED NOT NULL,
  `metric` ENUM('protected_users','onedrive_overage_gib') NOT NULL,
  `qty` INT UNSIGNED NOT NULL DEFAULT 0,
  `unit_price` DECIMAL(12,4) NOT NULL DEFAULT 0,
  `line_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `currency_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `billing_window_start` DATE NOT NULL,
  `billing_window_end` DATE NOT NULL,
  `pricing_source` ENUM('settings','trial_zeroed') NOT NULL DEFAULT 'settings',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_ms365_rated_line` (`service_id`, `metric`, `billing_window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
