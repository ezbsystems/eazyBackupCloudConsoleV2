ALTER TABLE `ms365_backup_runs`
  MODIFY COLUMN `status` ENUM('queued','running','success','error','cancelled') NOT NULL DEFAULT 'queued';
