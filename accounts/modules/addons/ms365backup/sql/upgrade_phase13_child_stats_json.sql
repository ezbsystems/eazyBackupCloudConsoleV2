-- Per-child worker timing and stats (graph_sync_ms, kopia_snapshot_ms, etc.)
ALTER TABLE `ms365_backup_runs`
    ADD COLUMN `stats_json` JSON NULL AFTER `bytes_uploaded`;
