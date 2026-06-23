# DB I/O Optimization — Baseline (captured 2026-06-19)

Pre-implementation snapshot on dev server (`dev.eazybackup.ca`).

## Schema variant

- `s3_cloudbackup_runs`: UUIDv7 PK (`run_id BINARY(16)`), not legacy int PK
- `s3_cloudbackup_agents`: `agent_uuid` UNIQUE, `last_seen_at` DATETIME (no `last_checked_ts`)
- `last_heartbeat_at` on runs: **not present** before migration (added by this work)

## Indexes on `s3_cloudbackup_runs` (before)

PRIMARY(run_id), job_id, tenant_id, repository_id, status, started_at, agent_uuid, updated_at, idx_runs_job_started(job_id, started_at)

## Hot-path write sources (confirmed in code)

| Source | Table | Pattern |
|--------|-------|---------|
| ~20 agent API `authenticateAgent()` copies | `s3_cloudbackup_agents` | unconditional `UPDATE last_seen_at = NOW()` per request |
| Go agent 1s command loop × 2 endpoints | same | ~120 req/min/agent idle |
| `agent_watchdog.php` | `s3_cloudbackup_runs` | `SELECT FOR UPDATE` + `TIMESTAMPDIFF(COALESCE(...))` |
| `agent_next_run.php` | same | `FOR UPDATE` held during credential decrypt |
| `comet_ws_worker.php` `saveCursor()` | `eb_event_cursor` | `REPLACE` per websocket frame |
| `resolveWhmcsUser()` | `tblhosting` | `BINARY TRIM(username)` — non-sargable |

## Post-implementation verification

Re-run after deploy:

```sql
EXPLAIN SELECT r.* FROM s3_cloudbackup_runs r
  WHERE r.status IN ('starting','running')
    AND r.last_heartbeat_at < NOW() - INTERVAL 720 SECOND;

SHOW INDEX FROM s3_cloudbackup_runs;
SHOW ENGINE INNODB STATUS\G
```

Monitor `performance_schema.table_io_waits_summary_by_table` for `s3_cloudbackup_agents`, `s3_cloudbackup_runs`, `eb_event_cursor`.
