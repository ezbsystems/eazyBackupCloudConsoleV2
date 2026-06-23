ALTER TABLE `ms365_worker_nodes`
  ADD COLUMN `proxmox_node` VARCHAR(64) NULL DEFAULT NULL AFTER `proxmox_vmid`;

ALTER TABLE `ms365_worker_nodes`
  MODIFY COLUMN `status` ENUM('registering','active','draining','offline','retired','stopped') NOT NULL DEFAULT 'registering';
