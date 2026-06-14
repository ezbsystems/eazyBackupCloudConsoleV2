ALTER TABLE `s3_cloudbackup_jobs`
  MODIFY COLUMN `source_type` ENUM('s3_compatible','aws','sftp','google_drive','dropbox','smb','nas','local_agent','ms365') NOT NULL DEFAULT 's3_compatible';

ALTER TABLE `s3_cloudbackup_jobs`
  MODIFY COLUMN `engine` ENUM('sync', 'kopia', 'disk_image', 'hyperv', 'ms365') NOT NULL DEFAULT 'sync';

ALTER TABLE `s3_cloudbackup_runs`
  MODIFY COLUMN `engine` ENUM('sync', 'kopia', 'disk_image', 'hyperv', 'ms365') NOT NULL DEFAULT 'sync';
