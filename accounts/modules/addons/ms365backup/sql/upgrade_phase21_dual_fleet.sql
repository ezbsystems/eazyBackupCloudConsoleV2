-- Dual fleet: release sync audit log (optional; idempotent).
CREATE TABLE IF NOT EXISTS `ms365_worker_release_sync` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `release_id` int unsigned DEFAULT NULL,
  `status` varchar(32) NOT NULL,
  `detail` varchar(512) DEFAULT NULL,
  `synced_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_release_synced_at` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
