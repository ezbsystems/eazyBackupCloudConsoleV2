-- Purchases / Packs you make with Comet (manually recorded or later via API)
CREATE TABLE IF NOT EXISTS cb_credit_purchases (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  purchased_at DATETIME NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  record_type ENUM('purchase','refund','adjustment') NOT NULL DEFAULT 'purchase',
  import_batch_id BIGINT UNSIGNED NULL,
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
  packs_used_raw VARCHAR(512) NULL,
  packs_used_parsed JSON NULL,
  raw_row JSON NOT NULL,
  row_fingerprint CHAR(32) NOT NULL,
  content_fingerprint CHAR(32) NOT NULL,
  occurrence_number INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  first_seen_at DATETIME NULL,
  last_seen_at DATETIME NULL,
  pull_manifest_id BIGINT UNSIGNED NULL,
  is_present_in_latest_pull TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_content_occurrence (content_fingerprint, occurrence_number),
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

-- ============================================================================
-- CREDIT PACK TRACKING (FIFO)
-- ============================================================================

-- Credit lots for FIFO consumption tracking
CREATE TABLE IF NOT EXISTS cb_credit_lots (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  purchase_id BIGINT UNSIGNED NULL,
  lot_type ENUM('purchased', 'bonus', 'adjustment') NOT NULL DEFAULT 'purchased',
  original_amount DECIMAL(12,4) NOT NULL,
  remaining_amount DECIMAL(12,4) NOT NULL,
  created_at DATETIME NOT NULL,
  depleted_at DATETIME NULL,
  KEY idx_remaining (remaining_amount),
  KEY idx_type (lot_type),
  KEY idx_created (created_at),
  KEY idx_purchase (purchase_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Credit allocation log (usage -> lots mapping)
CREATE TABLE IF NOT EXISTS cb_credit_allocations (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  usage_date DATE NOT NULL,
  total_amount DECIMAL(12,4) NOT NULL,
  description VARCHAR(255) NULL,
  allocations JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usage_date (usage_date),
  KEY idx_usage_date (usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- RECONCILIATION
-- ============================================================================

-- Saved reconciliation reports
CREATE TABLE IF NOT EXISTS cb_reconciliation_reports (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  report_date DATETIME NOT NULL,
  server_collected_at DATETIME NULL,
  portal_snapshot_at DATETIME NULL,
  overall_status ENUM('ok', 'variance_detected', 'incomplete') NOT NULL DEFAULT 'ok',
  summary JSON NULL,
  items JSON NULL,
  server_data JSON NULL,
  portal_data JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_report_date (report_date),
  KEY idx_status (overall_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- SERVER USAGE SNAPSHOTS
-- ============================================================================

-- Daily server usage snapshots (from Comet Admin API)
CREATE TABLE IF NOT EXISTS cb_server_usage (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  snapshot_date DATE NOT NULL,
  server_key VARCHAR(64) NOT NULL,
  
  -- Aggregate counts
  total_users INT UNSIGNED NOT NULL DEFAULT 0,
  total_devices INT UNSIGNED NOT NULL DEFAULT 0,
  hyperv_vms INT UNSIGNED NOT NULL DEFAULT 0,
  vmware_vms INT UNSIGNED NOT NULL DEFAULT 0,
  proxmox_vms INT UNSIGNED NOT NULL DEFAULT 0,
  disk_image INT UNSIGNED NOT NULL DEFAULT 0,
  mssql INT UNSIGNED NOT NULL DEFAULT 0,
  m365_accounts INT UNSIGNED NOT NULL DEFAULT 0,
  storage_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  protected_items INT UNSIGNED NOT NULL DEFAULT 0,
  
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  UNIQUE KEY uq_snapshot (snapshot_date, server_key),
  KEY idx_server (server_key),
  KEY idx_date (snapshot_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Combined daily usage (all servers aggregated)
CREATE TABLE IF NOT EXISTS cb_server_usage_combined (
  snapshot_date DATE PRIMARY KEY,
  total_servers INT UNSIGNED NOT NULL DEFAULT 0,
  total_users INT UNSIGNED NOT NULL DEFAULT 0,
  total_devices INT UNSIGNED NOT NULL DEFAULT 0,
  hyperv_vms INT UNSIGNED NOT NULL DEFAULT 0,
  vmware_vms INT UNSIGNED NOT NULL DEFAULT 0,
  proxmox_vms INT UNSIGNED NOT NULL DEFAULT 0,
  disk_image INT UNSIGNED NOT NULL DEFAULT 0,
  mssql INT UNSIGNED NOT NULL DEFAULT 0,
  m365_accounts INT UNSIGNED NOT NULL DEFAULT 0,
  storage_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  protected_items INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-device server inventory (for reconciliation drill-down)
CREATE TABLE IF NOT EXISTS cb_server_device_inventory (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  snapshot_date DATE NOT NULL,
  server_key VARCHAR(64) NOT NULL,
  username VARCHAR(255) NOT NULL DEFAULT '',
  device_id VARCHAR(64) NOT NULL,
  friendly_name VARCHAR(255) NULL,
  hyperv_vms INT UNSIGNED NOT NULL DEFAULT 0,
  vmware_vms INT UNSIGNED NOT NULL DEFAULT 0,
  proxmox_vms INT UNSIGNED NOT NULL DEFAULT 0,
  disk_image INT UNSIGNED NOT NULL DEFAULT 0,
  mssql INT UNSIGNED NOT NULL DEFAULT 0,
  m365_accounts INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_snapshot_device (snapshot_date, server_key, device_id),
  KEY idx_snapshot (snapshot_date),
  KEY idx_server (server_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Portal pull provenance
CREATE TABLE IF NOT EXISTS cb_portal_pull_manifests (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  pulled_at DATETIME NOT NULL,
  source VARCHAR(32) NOT NULL,
  row_count INT UNSIGNED NOT NULL DEFAULT 0,
  new_rows INT UNSIGNED NOT NULL DEFAULT 0,
  updated_rows INT UNSIGNED NOT NULL DEFAULT 0,
  checksum CHAR(32) NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL,
  KEY idx_pulled_at (pulled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cb_purchase_import_batches (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  imported_at DATETIME NOT NULL,
  source_filename VARCHAR(255) NULL,
  earliest_date DATE NULL,
  latest_date DATE NULL,
  row_count INT UNSIGNED NOT NULL DEFAULT 0,
  purchase_count INT UNSIGNED NOT NULL DEFAULT 0,
  refund_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_complete TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cb_audit_runs (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  run_at DATETIME NOT NULL,
  from_date DATE NOT NULL,
  to_date DATE NOT NULL,
  summary JSON NOT NULL,
  coverage JSON NULL,
  created_at DATETIME NOT NULL,
  KEY idx_run_at (run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cb_audit_findings (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  audit_run_id BIGINT UNSIGNED NOT NULL,
  usage_id BIGINT UNSIGNED NULL,
  verdict VARCHAR(32) NOT NULL,
  debit_evidence VARCHAR(16) NOT NULL DEFAULT 'unclear',
  billing_verdict VARCHAR(32) NOT NULL,
  amount DECIMAL(12,4) NOT NULL,
  account VARCHAR(128) NULL,
  device_id VARCHAR(128) NULL,
  category VARCHAR(32) NULL,
  usage_date DATE NOT NULL,
  item_desc VARCHAR(255) NULL,
  expected_billing_end DATE NULL,
  confidence_reasons JSON NULL,
  evidence JSON NOT NULL,
  KEY idx_audit_run (audit_run_id),
  KEY idx_verdict (verdict),
  KEY idx_usage_date (usage_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cb_ledger_rebuild_batches (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  started_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  opening_date DATE NOT NULL,
  closing_date DATE NOT NULL,
  opening_credit DECIMAL(12,4) NOT NULL,
  closing_credit DECIMAL(12,4) NULL,
  is_complete TINYINT(1) NOT NULL DEFAULT 0,
  validation JSON NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cb_credit_usage_allocations (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  usage_id BIGINT UNSIGNED NOT NULL,
  lot_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,4) NOT NULL,
  lot_remaining_after DECIMAL(12,4) NOT NULL,
  rebuild_batch_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_usage_lot (usage_id, lot_id),
  KEY idx_usage_id (usage_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

