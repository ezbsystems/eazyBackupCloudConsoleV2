# Local Backup Agent – Build & Deploy

## Prereqs
- Go toolchain (1.24.x) on the build host.
- Source: `/var/www/eazybackup.ca/e3-backup-agent/`
- Windows target: GOOS=windows, GOARCH=amd64, CGO_ENABLED=0.

## Build (Windows binary)
From the agent repo:
```bash
cd /var/www/eazybackup.ca/e3-backup-agent
CGO_ENABLED=0 GOOS=windows GOARCH=amd64 go build -trimpath -ldflags="-s -w" -o bin/e3-backup-agent.exe ./cmd/agent
```

## Files of interest
- Output: `bin/e3-backup-agent.exe`
- Config (on Windows): `%PROGRAMDATA%\E3Backup\agent.conf`
- Run dir (default): `%PROGRAMDATA%\E3Backup\runs`

## Running (debug/foreground)
```powershell
.\e3-backup-agent.exe -config C:\ProgramData\E3Backup\agent.conf
```

## Service install (kardianos/service)
- `.\e3-backup-agent.exe install -config C:\ProgramData\E3Backup\agent.conf`
- `.\e3-backup-agent.exe start`
- `.\e3-backup-agent.exe stop`
- `.\e3-backup-agent.exe uninstall`

## Notes
- Embedded rclone (no external binary).
- Backends registered: local, s3.
- S3 config uses path-style and location_constraint (region) when provided.
- Poll interval set via `poll_interval_secs` in config (default 30s).

## Current blocking issue (handoff)
- Agent sees queued runs in DB, but `agent_next_run.php` returns `no_run` even with `base_count=2` / `filtered_count=2`; claim may be failing.
- Investigate `agent_next_run.php` claim update and DB transaction; see `LOCAL_AGENT_OVERVIEW.md` for context.

