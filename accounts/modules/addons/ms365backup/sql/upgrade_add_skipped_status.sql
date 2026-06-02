ALTER TABLE `ms365_backup_runs`
  MODIFY COLUMN `status` ENUM('queued','running','success','error','cancelled','skipped') NOT NULL DEFAULT 'queued';
