ALTER TABLE `ms365_worker_nodes`
  ADD COLUMN `disk_critical` TINYINT(1) NOT NULL DEFAULT 0 AFTER `claim_admit_rejects`,
  ADD COLUMN `reserved_disk_mib` INT NULL DEFAULT NULL AFTER `disk_critical`;
