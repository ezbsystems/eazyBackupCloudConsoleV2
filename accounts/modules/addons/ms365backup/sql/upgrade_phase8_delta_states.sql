ALTER TABLE `ms365_backup_runs`
  ADD COLUMN `delta_states_json` LONGTEXT NULL DEFAULT NULL AFTER `logical_sources_json`;
