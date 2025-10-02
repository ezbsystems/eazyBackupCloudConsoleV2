-- Purchases / Packs you make with Comet (manually recorded or later via API)
CREATE TABLE IF NOT EXISTS cb_credit_purchases (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  purchased_at DATETIME NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  pack_label VARCHAR(64) NULL,
  pack_units INT NULL,
  credit_amount DECIMAL(12,4) NOT NULL,
  bonus_credit DECIMAL(12,4) NOT NULL DEFAULT 0,
  payment_method VARCHAR(64) NULL,
  receipt_no VARCHAR(128) NULL,
  external_ref VARCHAR(128) NULL,
  notes TEXT NULL,
  raw_receipt JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_receipt (receipt_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usage rows from ReportBillingHistory
CREATE TABLE IF NOT EXISTS cb_credit_usage (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  usage_date DATE NOT NULL,
  posted_at DATETIME NULL,
  tenant_id VARCHAR(128) NULL,
  device_id VARCHAR(128) NULL,
  item_type VARCHAR(64) NOT NULL,
  item_desc VARCHAR(255) NULL,
  quantity DECIMAL(12,4) NULL,
  unit_cost DECIMAL(12,6) NULL,
  amount DECIMAL(12,4) NOT NULL,
  packs_used DECIMAL(12,4) NULL,
  raw_row JSON NOT NULL,
  row_fingerprint CHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fingerprint (row_fingerprint),
  KEY ix_usage_date (usage_date),
  KEY ix_type_date (item_type, usage_date),
  KEY ix_tenant_device_date (tenant_id, device_id, usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Point-in-time snapshot from ReportActiveServices
CREATE TABLE IF NOT EXISTS cb_active_services (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  pulled_at DATETIME NOT NULL,
  service_name VARCHAR(128) NOT NULL,
  billing_cycle_days INT NOT NULL,
  next_due_date DATE NOT NULL,
  unit_cost DECIMAL(12,6) NULL,
  quantity DECIMAL(12,4) NULL,
  amount DECIMAL(12,4) NULL,
  tenant_id VARCHAR(128) NULL,
  device_id VARCHAR(128) NULL,
  extra JSON NULL,
  row_fingerprint CHAR(32) NOT NULL,
  UNIQUE KEY uq_snapshot (pulled_at, row_fingerprint),
  KEY ix_next_due (next_due_date),
  KEY ix_service (service_name, next_due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily balance roll-forward (computed)
CREATE TABLE IF NOT EXISTS cb_daily_balance (
  balance_date DATE PRIMARY KEY,
  opening_credit DECIMAL(12,4) NOT NULL,
  purchases_credit DECIMAL(12,4) NOT NULL,
  usage_amount DECIMAL(12,4) NOT NULL,
  closing_credit DECIMAL(12,4) NOT NULL,
  recomputed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings/cursors
CREATE TABLE IF NOT EXISTS cb_settings (
  k VARCHAR(64) PRIMARY KEY,
  v TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: Additional API keys for multi-account futureproofing
CREATE TABLE IF NOT EXISTS cb_api_keys (
  id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  label VARCHAR(64) NOT NULL,
  base_url VARCHAR(255) NOT NULL,
  auth_type ENUM('token') NOT NULL DEFAULT 'token',
  token_enc TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


