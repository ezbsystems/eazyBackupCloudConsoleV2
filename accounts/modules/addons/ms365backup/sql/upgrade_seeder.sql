CREATE TABLE IF NOT EXISTS `ms365_seeder_config` (
  `id` int unsigned NOT NULL DEFAULT 1,
  `region` varchar(32) NOT NULL DEFAULT 'GlobalPublicCloud',
  `tenant_id` varchar(64) NOT NULL DEFAULT '',
  `client_id` varchar(64) NOT NULL DEFAULT '',
  `app_secret_enc` text,
  `seed_user_upn` varchar(255) NOT NULL DEFAULT '',
  `seed_user_id` varchar(64) NOT NULL DEFAULT '',
  `refresh_token_enc` text,
  `token_expires_at` int unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `ms365_seeder_config` (`id`, `region`, `tenant_id`, `client_id`) VALUES (1, 'GlobalPublicCloud', '', '');

CREATE TABLE IF NOT EXISTS `ms365_seeder_runs` (
  `id` varchar(36) NOT NULL,
  `status` enum('queued','running','success','error','cancelled') NOT NULL DEFAULT 'queued',
  `profile` varchar(32) NOT NULL DEFAULT 'light',
  `options_json` mediumtext,
  `stats_json` mediumtext,
  `error` text,
  `started_at` int unsigned DEFAULT NULL,
  `finished_at` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
