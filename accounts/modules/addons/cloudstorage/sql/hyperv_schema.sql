-- =====================================================
-- HYPER-V BACKUP ENGINE SCHEMA
-- Version: 1.0
-- Date: December 2025
-- =====================================================

-- -----------------------------------------------------
-- Extend existing jobs table for Hyper-V support
-- -----------------------------------------------------
ALTER TABLE s3_cloudbackup_jobs 
    MODIFY COLUMN engine ENUM('sync', 'kopia', 'disk_image', 'hyperv') NOT NULL DEFAULT 'sync';

ALTER TABLE s3_cloudbackup_jobs 
    MODIFY COLUMN source_type ENUM('local', 'network_share', 'disk_volume', 'hyperv', 'local_agent') NOT NULL DEFAULT 'local';

ALTER TABLE s3_cloudbackup_jobs 
    ADD COLUMN IF NOT EXISTS hyperv_enabled BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS hyperv_config JSON NULL;
-- hyperv_config example:
-- {
--   "vms": ["VM1", "VM2"],
--   "exclude_vms": [],
--   "backup_all_vms": false,
--   "enable_rct": true,
--   "consistency_level": "application",
--   "quiesce_timeout_seconds": 300,
--   "instant_restore_enabled": false
-- }

-- -----------------------------------------------------
-- Extend runs table for disk manifests
-- -----------------------------------------------------
ALTER TABLE s3_cloudbackup_runs
    ADD COLUMN IF NOT EXISTS disk_manifests_json JSON NULL;
-- Example: {"C:\\VMs\\disk0.vhdx": "manifest123", "C:\\VMs\\disk1.vhdx": "manifest456"}

-- -----------------------------------------------------
-- VM Registry: tracks VMs configured for backup
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS s3_hyperv_vms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    vm_name VARCHAR(255) NOT NULL,
    vm_guid VARCHAR(64),                          -- Hyper-V VM GUID
    generation TINYINT DEFAULT 2,                 -- VM Generation (1 or 2)
    is_linux BOOLEAN DEFAULT FALSE,
    integration_services BOOLEAN DEFAULT TRUE,
    rct_enabled BOOLEAN DEFAULT FALSE,
    backup_enabled BOOLEAN DEFAULT TRUE,          -- Whether to include in backups
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (job_id) REFERENCES s3_cloudbackup_jobs(id) ON DELETE CASCADE,
    UNIQUE KEY uk_job_vm (job_id, vm_guid),
    INDEX idx_job_enabled (job_id, backup_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- VM Disks: tracks VHDX files for each VM
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS s3_hyperv_vm_disks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vm_id INT NOT NULL,
    disk_path VARCHAR(1024) NOT NULL,             -- Full path to VHDX
    controller_type ENUM('SCSI', 'IDE') DEFAULT 'SCSI',
    controller_number INT DEFAULT 0,
    controller_location INT DEFAULT 0,
    vhd_format ENUM('VHDX', 'VHD') DEFAULT 'VHDX',
    size_bytes BIGINT,                            -- Virtual size
    used_bytes BIGINT,                            -- Actual data size
    rct_enabled BOOLEAN DEFAULT FALSE,
    current_rct_id VARCHAR(128),                  -- Current RCT tracking ID
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (vm_id) REFERENCES s3_hyperv_vms(id) ON DELETE CASCADE,
    INDEX idx_vm_id (vm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Checkpoints: tracks backup reference points for RCT
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS s3_hyperv_checkpoints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vm_id INT NOT NULL,
    run_id INT NULL,                              -- Associated backup run
    checkpoint_id VARCHAR(64) NOT NULL,           -- Hyper-V checkpoint GUID
    checkpoint_name VARCHAR(255),
    checkpoint_type ENUM('Production', 'Standard', 'Reference') DEFAULT 'Production',
    rct_ids JSON,                                 -- RCT IDs for each disk at this point
    is_active BOOLEAN DEFAULT TRUE,               -- Is this the current reference point?
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    merged_at TIMESTAMP NULL,                     -- When checkpoint was merged
    
    FOREIGN KEY (vm_id) REFERENCES s3_hyperv_vms(id) ON DELETE CASCADE,
    FOREIGN KEY (run_id) REFERENCES s3_cloudbackup_runs(id) ON DELETE SET NULL,
    INDEX idx_vm_active (vm_id, is_active),
    INDEX idx_run_id (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Backup Points: tracks backup metadata for restore
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS s3_hyperv_backup_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vm_id INT NOT NULL,
    run_id INT NOT NULL,
    backup_type ENUM('Full', 'Incremental') NOT NULL,
    manifest_id VARCHAR(128) NOT NULL,            -- Kopia manifest ID
    parent_backup_id INT NULL,                    -- For incremental: points to base
    vm_config_json JSON,                          -- VM configuration at backup time
    disk_manifests JSON,                          -- Manifest IDs per disk
    total_size_bytes BIGINT,
    changed_size_bytes BIGINT,                    -- For incremental
    duration_seconds INT,
    consistency_level ENUM('Crash', 'Application', 'CrashNoCheckpoint') DEFAULT 'Application',
    warnings_json JSON NULL,                      -- Any warnings during backup
    warning_code VARCHAR(64) NULL,                -- Summary warning code (e.g., CHECKPOINTS_DISABLED)
    has_warnings BOOLEAN DEFAULT FALSE,           -- Quick flag to identify backups with warnings
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,                    -- Based on retention policy
    
    FOREIGN KEY (vm_id) REFERENCES s3_hyperv_vms(id) ON DELETE CASCADE,
    FOREIGN KEY (run_id) REFERENCES s3_cloudbackup_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_backup_id) REFERENCES s3_hyperv_backup_points(id) ON DELETE SET NULL,
    INDEX idx_vm_created (vm_id, created_at DESC),
    INDEX idx_manifest (manifest_id),
    INDEX idx_run_id (run_id),
    INDEX idx_has_warnings (has_warnings)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- Schema update for existing installations
-- Add warnings columns if they don't exist
-- -----------------------------------------------------
-- Note: Run this ALTER TABLE only if upgrading from a previous version
-- ALTER TABLE s3_hyperv_backup_points
--     MODIFY COLUMN consistency_level ENUM('Crash', 'Application', 'CrashNoCheckpoint') DEFAULT 'Application',
--     ADD COLUMN IF NOT EXISTS warnings_json JSON NULL,
--     ADD COLUMN IF NOT EXISTS warning_code VARCHAR(64) NULL,
--     ADD COLUMN IF NOT EXISTS has_warnings BOOLEAN DEFAULT FALSE;

-- -----------------------------------------------------
-- Instant Restore Sessions: tracks active instant restore sessions
-- (Phase 5 - future implementation)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS s3_hyperv_instant_restore_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_point_id INT NOT NULL,
    target_host VARCHAR(255),                     -- Target Hyper-V host
    restored_vm_name VARCHAR(255),
    session_type ENUM('NBD', 'iSCSI', 'Direct') DEFAULT 'NBD',
    nbd_address VARCHAR(64),                      -- NBD server address:port
    iscsi_target_iqn VARCHAR(255),
    differential_vhdx_path VARCHAR(1024),         -- Path to write differential
    status ENUM('Starting', 'Active', 'Migrating', 'Completed', 'Failed') DEFAULT 'Starting',
    migration_progress INT DEFAULT 0,             -- 0-100%
    error_message TEXT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    FOREIGN KEY (backup_point_id) REFERENCES s3_hyperv_backup_points(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_backup_point (backup_point_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

