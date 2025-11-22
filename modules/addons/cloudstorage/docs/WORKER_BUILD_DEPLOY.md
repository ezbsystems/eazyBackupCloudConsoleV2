## e3 Cloud Backup Worker - Build and Deploy Guide

This guide explains how to build the worker binary on the dev server and deploy it to the worker VM.

Target paths:
- Dev server source: `/var/www/eazybackup.ca/e3-cloudbackup-worker`
- Worker VM install dir: `/opt/e3-cloudbackup-worker`
- Worker binary path (systemd ExecStart): `/opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker`

### 1) Build on the Dev Server

Prerequisites:
- Go 1.21+ installed on the dev server
- Project available at `/var/www/eazybackup.ca/e3-cloudbackup-worker`

```bash
cd /var/www/eazybackup.ca/e3-cloudbackup-worker

# Download deps
go mod download
go mod tidy

# Build (Makefile if available)
make build
# Binary will be at: bin/e3-cloudbackup-worker

# Manual build (alternative)
go build -o bin/e3-cloudbackup-worker cmd/worker/main.go

# Optional: cross-compile (if building from a different OS/arch)
# GOOS=linux GOARCH=amd64 CGO_ENABLED=0 go build -o bin/e3-cloudbackup-worker cmd/worker/main.go

# Verify
ls -lh bin/e3-cloudbackup-worker
bin/e3-cloudbackup-worker -h
```

Troubleshooting (go mod tidy):

```bash
# If you see:
#   cannot find module providing package github.com/your-org/e3-cloudbackup-worker/internal/diag
# Ensure the new internal package exists locally:
test -f internal/diag/preflight.go || echo "missing internal/diag/preflight.go"

# Pull the latest changes that add the package, then retry:
git pull
go mod tidy
```

### 2) Copy Binary to Worker VM

Copy as `e3-cloudbackup-worker.new` first, then swap to avoid partial/locked files.

```bash
# From dev server
scp bin/e3-cloudbackup-worker root@e3-cloudbackup-worker-01:/opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker.new
```

If you prefer a non-root user, copy to `/tmp` then `sudo mv` into place on the VM.

### 3) Swap Binary on Worker VM

On the worker VM:
```bash
ssh root@e3-cloudbackup-worker-01

# Stop service
systemctl stop e3-cloudbackup-worker

# Backup current binary (optional but recommended)
ts=$(date +%F-%H%M%S)
cp /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker \
   /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker.bak.$ts || true

# Swap in new binary
mv /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker.new \
   /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker

# Ownership & permissions
chown e3backup:e3backup /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker
chmod 755 /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker

# Reload units (safe even if unchanged)
systemctl daemon-reload

# Start service
systemctl start e3-cloudbackup-worker
systemctl status e3-cloudbackup-worker -n 50

# Tail logs
journalctl -u e3-cloudbackup-worker -f
```

### 4) Quick Rollback

If needed, restore the previous binary:
```bash
systemctl stop e3-cloudbackup-worker

# Replace with the saved backup
cp /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker.bak.<TIMESTAMP> \
   /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker
chown e3backup:e3backup /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker
chmod 755 /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker

systemctl start e3-cloudbackup-worker
systemctl status e3-cloudbackup-worker -n 20
```

### Notes

- The systemd unit expects the binary at `/opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker`. If you prefer installing to `/usr/local/bin`, update the unit `ExecStart` accordingly, then `systemctl daemon-reload`.
- Ensure `CLOUD_BACKUP_ENCRYPTION_KEY` is configured in the unit (Environment or EnvironmentFile) as per `WORKER_VM_SETUP.md`.
- If Go isn’t in PATH for the `e3backup` user on the worker VM and you choose to build directly there, use the full path `/usr/local/go/bin/go` or update PATH (see `WORKER_VM_SETUP.md`, Step 2.2).
- When adding new source types or logic (e.g., AWS), you must rebuild and redeploy the worker to pick up code changes.

### Common Troubleshooting

- Permission denied on binary:
```bash
chmod 755 /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker
chown e3backup:e3backup /opt/e3-cloudbackup-worker/bin/e3-cloudbackup-worker
```

- Service won’t start; check logs:
```bash
journalctl -u e3-cloudbackup-worker -n 100
journalctl -u e3-cloudbackup-worker -f
```

- “rclone binary not found”: verify `rclone` exists and `rclone.binary_path` in config.yaml matches, typically `/usr/bin/rclone`.

- DB connection errors: verify `database.host`, `username`, `password`, and that the DB user grants match the worker host; test with:
```bash
mysql -h <DB_HOST> -u <DB_USER> -p <DB_NAME> -e "SELECT 1;"
```


