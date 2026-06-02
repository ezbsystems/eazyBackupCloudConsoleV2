ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `backup_mail` TINYINT(1) NOT NULL DEFAULT 1 AFTER `backup_path`,
  ADD COLUMN `backup_calendar` TINYINT(1) NOT NULL DEFAULT 1 AFTER `backup_mail`;
