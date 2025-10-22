CREATE TABLE IF NOT EXISTS eb_whitelabel_signup_domains (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  hostname VARCHAR(255) NOT NULL,
  status ENUM('pending_dns','dns_ok','cert_ok','verified','disabled','failed') NOT NULL DEFAULT 'pending_dns',
  last_error TEXT NULL,
  cert_expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_hostname (hostname),
  KEY idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eb_whitelabel_signup_flows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  product_pid INT NOT NULL,
  promo_code VARCHAR(64) NULL,
  payment_method VARCHAR(64) NULL,
  require_email_verify TINYINT(1) NOT NULL DEFAULT 0,
  send_customer_welcome TINYINT(1) NOT NULL DEFAULT 1,
  send_msp_notice TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS eb_whitelabel_signup_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id BIGINT UNSIGNED NOT NULL,
  host_header VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  whmcs_client_id INT NULL,
  whmcs_order_id INT NULL,
  comet_username VARCHAR(255) NULL,
  status ENUM('received','validated','ordered','accepted','provisioned','emailed','completed','failed') NOT NULL DEFAULT 'received',
  error TEXT NULL,
  ip VARCHAR(64) NULL,
  user_agent TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_tenant_email (tenant_id, email),
  KEY idx_tenant (tenant_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


