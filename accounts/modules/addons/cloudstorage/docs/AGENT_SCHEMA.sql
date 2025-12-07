-- Schema additions for Windows/local agent support

-- Agent registry
CREATE TABLE IF NOT EXISTS `s3_cloudbackup_agents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` INT UNSIGNED NOT NULL,
  `agent_token` VARCHAR(191) NOT NULL,
  `hostname` VARCHAR(191) DEFAULT NULL,
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `last_seen_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_agent_token` (`agent_token`),
  KEY `idx_client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extend jobs for local agent source type and optional binding to an agent
ALTER TABLE `s3_cloudbackup_jobs`
  MODIFY `source_type` ENUM('s3_compatible','aws','sftp','google_drive','dropbox','smb','nas','local_agent') NOT NULL,
  ADD COLUMN `agent_id` BIGINT UNSIGNED NULL AFTER `source_type`,
  ADD KEY `idx_agent_id` (`agent_id`);

