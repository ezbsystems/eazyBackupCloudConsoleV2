# Cloud Backup Agent Logging Cutover

This is a strict-minimum-version cutover (modeled on the UUIDv7 cutover) that
moves the majority of agent and tray logging server-side and reduces local
on-disk logging to a small health/break-glass footprint.

## Why

- Local logs on customer PCs were too verbose, contained sensitive runtime
  detail, were not rotated reliably, and were unavailable to support when the
  customer's machine was offline.
- Admins relied on a live "endpoint tail" that only worked while the agent was
  online and reachable.
- We need durable, structured logs in WHMCS that scale to thousands of agents
  without overwhelming MySQL.

## What changes

### Agent (Go) — local

- Local log files (`agent.log`, `tray.log`) are now written through a custom
  rotating logger (`internal/applog`) at 5 MB x 3 files, mode 0600.
- Default level is `warn`; agents log only state transitions, service health,
  command outcomes, and run start/finish (by run ID).
- Noisy lines removed/demoted: "agent: no queued runs", per-byte Kopia upload
  callbacks, NAS/Hyper-V verbose internals, etc.
- Tray live-tail redaction expanded (enrollment URL/token, bearer tokens,
  query-string secrets, basic-auth credentials).

### Agent (Go) — server-side

- New `HealthEmitter` (`internal/agent/health_emitter.go`) batches non-run
  health events (service/tray start-stop, enrollment, server connection state,
  device online/offline, command lifecycle, run start/finish) and ships them
  to `agent_push_agent_events.php`.
- Run progress events are throttled (>=30s OR >=1% step OR phase change),
  values configurable via addon settings.
- Optional admin verbose-logging window per run (`enable_verbose_admin_logging`
  command) buffers detailed lines and uploads them as gzipped chunks to
  `agent_push_log_chunk.php`.
- All authenticated ingest requests stamp `X-Agent-Version` (and embed
  `agent_version` in the JSON body for the gzip endpoints).

### Server (PHP)

- New tables:
  - `s3_cloudbackup_agent_events` — durable agent/tray health events.
  - `s3_cloudbackup_admin_log_chunks` — gzipped verbose admin chunks
    (`LONGBLOB`, FK to `s3_cloudbackup_runs.run_id`).
- New ingest endpoints:
  - `agent_push_agent_events.php`
  - `agent_push_log_chunk.php`
- Existing endpoints updated:
  - `agent_push_events.php` — enforces `cloudbackup_event_max_per_run`,
    inserts one `EVENTS_TRUNCATED` marker, removes dev-only debug write.
  - `agent_update_run.php` — removes dev-only debug write, accepts
    `agent_version`.
- New admin endpoints:
  - `admin_cloudbackup_agent_events.php`
  - `admin_cloudbackup_agent_runs.php`
  - `admin_cloudbackup_run_chunks.php`
  - `admin_cloudbackup_run_chunk_download.php`
- Admin "Manage" modal rebuilt: Agent/Tray/Run Logs default to durable
  server-side data; live "Fetch Endpoint Tail" demoted to a per-tab
  break-glass action.
- Prune cron (`s3cloudbackup_events_prune.php`) extended to cover the new
  tables and `s3_cloudbackup_run_logs` using the new retention settings.

### Strict cutover (HTTP 426)

The shared helper `AgentIngestSupport::checkMinAgentVersion()` is invoked at
the top of every authenticated agent ingest endpoint. Agents reporting a
version older than `cloudbackup_min_local_agent_version` are rejected with
`HTTP 426 Upgrade Required` and a JSON body containing
`code: "agent_version_too_old"`.

On the agent, `Client.MarkVersionTooOld()` flips a process-wide flag and
writes a marker file (`agent_version_too_old.flag` next to the agent's logs).
While the flag is set, the agent stops attempting `PushEvents`,
`UpdateRun`, `PushAgentEvents`, and `PushLogChunk`. The tray surfaces a
"Please update" banner.

## New addon settings

| Setting | Default | Notes |
|---|---|---|
| `cloudbackup_event_progress_interval_seconds` | `30` | Server-side progress throttle (s). |
| `cloudbackup_event_progress_pct_step` | `1.0` | Min % change per progress event. |
| `cloudbackup_run_logs_retention_days` | `60` | Retention for `s3_cloudbackup_run_logs`. |
| `cloudbackup_agent_events_retention_days` | `30` | Retention for `s3_cloudbackup_agent_events`. |
| `cloudbackup_agent_events_max_per_day_per_agent` | `5000` | Daily ingest cap per agent. |
| `cloudbackup_chunks_max_per_run` | `200` | Hard cap on verbose chunks per run. |
| `cloudbackup_admin_chunks_retention_days` | `14` | Retention for verbose chunks. |
| `cloudbackup_min_local_agent_version` | (blank) | Strict cutover gate. |

## Cutover steps

1. **Deploy server first.**
   - Run the addon upgrade so the new tables and settings exist
     (Setup → Addon Modules → Cloud Storage → Activate/Save).
   - Verify `s3_cloudbackup_agent_events` and `s3_cloudbackup_admin_log_chunks`
     exist; verify the new settings are present in `tbladdonmodules`.
   - Leave `cloudbackup_min_local_agent_version` blank for now.
2. **Roll out the new agent build to all customers.**
   - New agents will start populating `s3_cloudbackup_agent_events` and
     respecting progress-event throttling. Older agents continue to function
     against the unchanged endpoints (no 426 yet).
3. **Set the strict floor.**
   - When all known production agents are at or above the new minimum,
     set `cloudbackup_min_local_agent_version` to that version
     (e.g. `1.7.0`). From this point forward, older agents are blocked
     with HTTP 426 and surface "Please update" in the tray.
4. **Verify pruning.**
   - Confirm the cron `s3cloudbackup_events_prune.php` is enabled and runs
     daily; check log output to see counts deleted from each table.

## Rollback

- Clear `cloudbackup_min_local_agent_version` to immediately re-allow older
  agents.
- New tables can remain in place; older agents simply do not write to them.
- The Manage modal still exposes "Fetch Endpoint Tail" so support retains a
  live diagnostic path even if server-side ingest is briefly disabled.

## Operational notes

- **Database load.** All new write paths are batched, capped, and indexed for
  the UI access patterns: `(agent_uuid, ts)`, `(client_id, ts)`,
  `(run_id, chunk_seq)` (unique). Verbose chunks are gzipped before insert.
- **Sensitive data.** Local logs are now state-machine focused; the server
  ingest endpoints reject events lacking valid agent auth, and the
  `log_tail` redactor scrubs enrollment URLs/tokens, bearer tokens, basic
  auth credentials, and common `key=value` secret patterns.
- **Verbose admin window.** Admins enable verbose capture per run via the
  Run Logs tab (`Enable Verbose Admin Logging (30 min)` button). The agent
  buffers detailed lines until the run completes (or the TTL elapses), then
  ships one gzipped chunk; the chunk count is hard-capped per run.
