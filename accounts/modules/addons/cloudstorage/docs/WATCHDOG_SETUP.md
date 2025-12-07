# Agent Watchdog Setup

Purpose: close stuck local agent runs that stop heartbeating and allow the same agent to reclaim a recent in-progress run.

## Defaults and knobs
- Watchdog cutoff: `AGENT_WATCHDOG_TIMEOUT_SECONDS` (default 720s / 12m).
- Reclaim grace: `AGENT_RECLAIM_GRACE_SECONDS` (default 180s / 3m). Must be lower than the watchdog cutoff.
- Script: `accounts/modules/addons/cloudstorage/crons/agent_watchdog.php`.
- Heartbeat source: `COALESCE(updated_at, started_at, created_at)` on `s3_cloudbackup_runs`.
- Actions on timeout: set `status=failed`, `error_summary="Agent offline / no heartbeat since <ts>"`, `finished_at=NOW()`, insert `s3_cloudbackup_run_events` row with `code=AGENT_OFFLINE`.

## Systemd service + timer (recommended)
1) Create `/etc/systemd/system/e3-agent-watchdog.service`
```
[Unit]
Description=E3 agent watchdog
After=network.target mysqld.service mariadb.service

[Service]
Type=oneshot
User=www-data
Group=www-data
Environment=AGENT_WATCHDOG_TIMEOUT_SECONDS=720
Environment=AGENT_RECLAIM_GRACE_SECONDS=180
WorkingDirectory=/var/www/eazybackup.ca/accounts/modules/addons/cloudstorage
ExecStart=/usr/bin/php -q /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/crons/agent_watchdog.php
```

2) Create `/etc/systemd/system/e3-agent-watchdog.timer`
```
[Unit]
Description=Run E3 agent watchdog every minute

[Timer]
OnCalendar=*-*-* *:*:00
Persistent=true
Unit=e3-agent-watchdog.service

[Install]
WantedBy=timers.target
```

3) Enable + start:
```
sudo systemctl daemon-reload
sudo systemctl enable --now e3-agent-watchdog.timer
```

## Cron alternative
Add to root or www-data crontab (align with PHP path):
```
* * * * * AGENT_WATCHDOG_TIMEOUT_SECONDS=720 AGENT_RECLAIM_GRACE_SECONDS=180 /usr/bin/php -q /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/crons/agent_watchdog.php >> /var/log/e3-agent-watchdog.log 2>&1
```

## Testing checklist
- **Reclaim within grace:** start a run, stop the agent, wait > grace (3m) but < cutoff (12m); call `agent_next_run.php` with the same agent headers and confirm the existing run is returned (status remains starting/running).
- **Watchdog timeout:** repeat above but wait beyond cutoff; run `crons/agent_watchdog.php` and verify the run moves to `failed`, `error_summary` includes last heartbeat, and an `AGENT_OFFLINE` run_event is inserted.
- **No cross-agent pickup:** verify another agent calling `agent_next_run.php` does not receive an in-progress run from a different agent; only queued runs assigned to that agent are returned.
- **Heartbeat cadence:** ensure agents post `agent_update_run` at least every 60–90s so `updated_at` stays fresh relative to the 180s grace.

