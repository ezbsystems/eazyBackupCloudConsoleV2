# eazyBackup WHMCS Addon: 

- I’m extending a WHMCS addon module called eazyBackup. It integrates with the Comet Server module (accounts\modules\servers\comet) which contains Comet PHP Software Development Kit. 
- We are using this to manage cloud backup users and surface controls in the WHMCS client area.

The goal of this project is to replicate all of the features form the Comet Backup web interface into our WHMCS client area by using the Comet Server API. Rather than having the customer leave our client area to log in to the Comet control panel, we want them to be able to fully manage their backup account using our addon module. 

Whenever possible, try to create new files to keep concerns isolated. Use includes whenever possible rather than putting scripts into the template file. Use includes with the main module router file so that the main router file does not become filled with code for different features, keep each feature isolated. Put scripts in accounts\modules\addons\eazybackup\templates\assets\js. Create new folders and files whenver needed. 

**Tech stack:**
- Runtime: PHP 8.2
- Templates: Smarty
- Frontend: Tailwind CSS, Alpine.js
- Composer present; addon uses its own vendor/autoload.php
**Module layout (paths are relative to the addon root)**
- Main module file: accounts/modules/addons/eazybackup/eazybackup.php
- Client UI (backup dashboard): templates/clientarea/dashboard.tpl
- Console/Profile UI (manage Comet users): templates/console/user-profile.tpl
- Backend controller(s): pages/console/dashboard.php (plus handlers in eazybackup.php)
- Helpers: lib/Helper.php, functions.php, lib/Vault.php

We have several custom database table that have been added to the WHMCS database. 

table comet_devices
table comet_items
table comet_jobs
table comet_users
table comet_vaults 
table eb_devices_daily
table eb_event_cursor
table eb_items_daily
table eb_jobs_live
table eb_jobs_recent_24h 


## File Layout
- All paths relative to the addon root: accounts/modules/addons/eazybackup/.

bin/
  bootstrap.php            # Shared bootstrap (autoload, .env, db(), logger)
  comet_ws_worker.php      # WebSocket worker: Comet → DB (jobs/devices)
  rollup_devices_daily.php # Nightly snapshot of device counts
  rollup_items_daily.php   # Nightly snapshot of protected-item counts
  monitor_stalled_jobs.php 

templates/clientarea/dashboard.tpl # Includes Protection Pulse (Live) card partial

pages/console/dashboard.php # Controllers (SSE + snapshot + actions)
eazybackup.php              # Routes to controllers
lib/Helper.php              # Utilities (e.g., scoping helpers)
vendor/                     # Composer deps (addon-local)

Dependencies (Composer):
amphp/websocket-client:^2, amphp/http-client:^5 (used by comet_ws_worker.php).

**Important** - Make sure to use Smarty safe syntax for all markup and scripts in the template .tpl files

**Our Goal** 
We want to create a web dashboard that will allow customers to manage their backup account, from creating protected items, managing storage vauls, performing remote restores. We want to support near-real-time jobs, devices, and incidents in the WHMCS client dashboard using Comet Server live events over WebSocket.

## Backend (routing + endpoints + ingestion)
`accounts/modules/addons/eazybackup/eazybackup.php`
- Passes serviceid and device list to templates. 

`accounts/modules/addons/eazybackup/pages/console/dashboard.php`
- Helpers: ownership checks, cursor management, device-name enrichment, job type names.

## comet_ws_worker.php Device and Protected Item ingestion (WebSocket-driven)
`accounts/modules/addons/eazybackup/bin/comet_ws_worker.php`

- Overview: The bin/comet_ws_worker.php listens to Comet Server live events and updates our WHMCS database in near real-time. 
We only run accounts/crons/eazybackupSyncComet.php once daily as a safety net.
- Ingests Comet WebSocket events, writes to eb_jobs_live and eb_jobs_recent_24h.
- accounts/modules/addons/eazybackup/bin/monitor_stalled_jobs.php
- Optional background watcher: checks running jobs via Comet Admin API, updates progress bytes/heartbeat, finalizes/cleans up stalled jobs.

**How the worker connects and selects the Comet server**
- The worker runs per profile (e.g., eazybackup, obc).
- Admin API client is built from WHMCS server config:
  - Looks up tblservergroups.name == <profile> → tblservergroupsrel →   tblservers.
  - Decrypts tblservers.password with localAPI('DecryptPassword').
  - Builds base URL from hostname, secure, port.
  - Fallback: if WHMCS mapping fails, uses .env COMET_<PROFILE>_ORIGIN/USERNAME/PASSWORD.

**Device ingestion**
- Event triggers:
  - SEVT_DEVICE_NEW: upsert device as active.
  - SEVT_DEVICE_REMOVED: mark device revoked/inactive.
- Data extraction:
  - Actor → username
  - ResourceID → Comet DeviceID hash (“device hash” we store as hash and also as id)
  - Data → FriendlyName, PlatformVersion.os, PlatformVersion.arch
- Database (table comet_devices):
  - On SEVT_DEVICE_NEW:
  - Upsert: id (device hash), hash, username, client_id (from tblhosting.username), content (raw JSON), name, platform_os, platform_arch, is_active=1, updated_at=NOW(), revoked_at=NULL (set created_at on first insert).
- On SEVT_DEVICE_REMOVED:
  - Update: is_active=0, revoked_at=NOW(), updated_at=NOW().
- Notes:
  - client_id is resolved via tblhosting for the username.
  - All writes are idempotent; conflicts are handled via ON DUPLICATE KEY UPDATE.

**Protected item ingestion**
- Event trigger: SEVT_ACCOUNT_UPDATED (we intentionally ignore SEVT_ACCOUNT_LOGIN to avoid noise).
- Fetch:
  - Admin API AdminGetUserProfile(<Actor username>).
  - Read Sources (map of itemId → item).
- Normalization:
  - Timestamps:
    - CreateTime/ModifyTime may be seconds or milliseconds; we normalize both.
    - If CreateTime is missing/invalid, use ModifyTime or current time to avoid 1970 dates.
  - Device linkage:
    - OwnerDevice is the raw Comet DeviceID for the item.
    - comet_device_id is our derived hash: sha256(client_id . OwnerDevice).
- Database (table comet_items):
  - Upsert per item:
    - Keys/fields set:
      - id (Source GUID)
      - client_id (from tblhosting.username)
      - username
      - content (raw JSON for auditing/reporting)
      - owner_device (raw DeviceID from Comet)
      - comet_device_id (derived hash)
      - name (Description)
      - type (Engine, e.g., engine1/file)
      - total_bytes, total_files, total_directories (from Statistics.LastBackupJob)
      - created_at, updated_at (normalized)
  - Pruning:
    - After a successful fetch, delete any comet_items rows for that (client_id, username) whose id is not in the latest Sources. Keeps the table in lockstep.

**Reliability and safety**
- Idempotent upserts: multiple events or reconnects do not duplicate rows.
- Debouncing is not necessary; writes are cheap and scoped to the actor.
- Logging:
    - Set EB_WS_DEBUG=1 to see event flow and decisions.
    - Set EB_DB_DEBUG=1 to see DB write confirmations and prune counts.
- Fallback daily cron (accounts/crons/eazybackupSyncComet.php):
  - Runs once per day to correct any missed events (rare), using bulk Admin API reads and the same upsert logic.

**Schema highlights**
  - comet_devices:
    - id (VARCHAR), hash (VARCHAR), client_id (INT), username (VARCHAR), content (JSON), name (VARCHAR), platform_os (VARCHAR), platform_arch (VARCHAR), is_active (TINYINT), created_at (TIMESTAMP), updated_at (TIMESTAMP), revoked_at (TIMESTAMP)
  - comet_items:
    - id (UUID), client_id (INT), username (VARCHAR), content (JSON), owner_device (VARCHAR), comet_device_id (VARCHAR), name (VARCHAR), type (VARCHAR), total_bytes (BIGINT), total_files (BIGINT), total_directories (BIGINT), created_at (TIMESTAMP), updated_at (TIMESTAMP)

This flow ensures the client dashboard reflects device and item changes quickly, with a simple daily safety pass to reconcile any gaps.

**High-Level Flow**
Comet Server (WebSocket /api/v1/events/stream)
         │
         ▼
bin/comet_ws_worker.php (one process per Comet server profile)
         │
         ├─► eb_jobs_live                (running jobs)
         ├─► eb_jobs_recent_24h          (completed jobs for last 24h)
         ├─► eb_devices_registry         (registered/removed devices + last_seen)
         └─► eb_event_cursor             (bookkeeping for delta windows)

Nightly
  ├─ bin/rollup_devices_daily.php ─► eb_devices_daily   (registered, active_24h)
  └─ bin/rollup_items_daily.php   ─► eb_items_daily     (Disk Image devices, Hyper-V VMs, VMware VMs, Microsoft 365 users, Files/Folders)


## Comet WS Worker Background Worker (systemd)
- A templated unit runs one worker per Comet server (for example, eazybackup, obc).

**comet_ws_worker Systemd Operations**

**Start/stop status of workers:**
systemctl restart eazybackup-comet-ws@eazybackup eazybackup-comet-ws@obc

systemctl start eazybackup-comet-ws@eazybackup 
systemctl start eazybackup-comet-ws@obc

systemctl status eazybackup-comet-ws@eazybackup -n 50
systemctl status eazybackup-comet-ws@obc -n 50

cd /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin
php monitor_stalled_jobs.php --verbose=1 --profile=eazybackup
php monitor_stalled_jobs.php --verbose=1 --profile=obc

**Live logs:**
journalctl -u eazybackup-comet-ws@eazybackup -f

**Debugging:**
Temporarily set EB_WS_DEBUG=1 and/or EB_DB_DEBUG=1 in the unit and restart.

**Monitor the websocket live:**
cd /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin
EB_WS_DEBUG=1 EB_DB_DEBUG=0 COMET_PROFILE=eazybackup php comet_ws_worker.php

**Retention:**
ToDo: Optionally prune eb_jobs_recent_24h older than 24–48 hours in a nightly housekeeping step.

**Unit file: /etc/systemd/system/eazybackup-comet-ws@.service**

[Unit]
Description=EazyBackup Comet WS Worker (%i)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/eazybackup.ca/accounts/modules/addons/eazybackup
Environment=COMET_PROFILE=%i
# Optional debug toggles:
# Environment=EB_WS_DEBUG=1
# Environment=EB_DB_DEBUG=1
ExecStart=/usr/bin/php bin/comet_ws_worker.php
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target

**Enable and start:**
  systemctl daemon-reload
  systemctl enable --now eazybackup-comet-ws@eazybackup
  systemctl enable --now eazybackup-comet-ws@obc

**Logs:**
  journalctl -u eazybackup-comet-ws@eazybackup -f

**Environment Configuration**
- Global .env (example: /var/www/eazybackup.ca/.env), one block per Comet profile.
- Set via environment or in the systemd unit.

  # -------- Comet: eazybackup --------
  COMET_eazybackup_NAME="eazybackup"
  COMET_eazybackup_URL="wss://csw.eazybackup.ca/api/v1/events/stream"
  COMET_eazybackup_ORIGIN="https://csw.eazybackup.ca"
  COMET_eazybackup_USERNAME="websocket"
  COMET_eazybackup_AUTHTYPE="Password"   
  COMET_eazybackup_PASSWORD="***"
  COMET_eazybackup_SESSIONKEY=""
  COMET_eazybackup_TOTP=""

  # -------- Comet: obc --------
  COMET_obc_NAME="obc"
  COMET_obc_URL="wss://csw.obcbackup.com/api/v1/events/stream"
  COMET_obc_ORIGIN="https://eazybackup.local"
  COMET_obc_USERNAME="websocket"
  COMET_obc_AUTHTYPE="Password"
  COMET_obc_PASSWORD="***"
  COMET_obc_SESSIONKEY=""
  COMET_obc_TOTP=""

  # Database (bootstrap.php defaults can be overridden here)
  DB_DSN="mysql:host=127.0.0.1;dbname=whmcs;charset=utf8mb4"
  DB_USER="whmcs"
  DB_PASS="***"

  # monitor_stalled_jobs.php defaults (can be overridden per run)
  EB_MON_STALE_SECS=3600
  EB_MON_RECHECK_SECS=300
  EB_MON_MAX_ATTEMPTS=3
  EB_MON_BATCH_LIMIT=200
  # Optional:
  # EB_MON_VERBOSE=1
  # EB_MON_DRY_RUN=1

  # Debug toggles (optional):
  # EB_WS_DEBUG=1 → log raw frames and parsed events.
  # EB_DB_DEBUG=1 → log database writes.


**Database Schema**
- Created automatically by the eazybackup addon module on module activate.

**Table: eb_event_cursor**
- Bookkeeping for our own delta windows (used by SSE).

CREATE TABLE IF NOT EXISTS eb_event_cursor (
  source   VARCHAR(128) PRIMARY KEY,  -- e.g. 'comet-ws:eazybackup'
  last_ts  INT UNSIGNED NOT NULL,     -- last processed Unix timestamp
  last_id  VARCHAR(128) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

**Table: eb_jobs_live**
- Running jobs (one row per Comet job currently active).

CREATE TABLE IF NOT EXISTS eb_jobs_live (
  server_id      VARCHAR(64)  NOT NULL,
  job_id         VARCHAR(64)  NOT NULL,
  username       VARCHAR(255) NOT NULL DEFAULT '',
  device         VARCHAR(255) NOT NULL DEFAULT '',  -- DeviceID (friendly name can be mapped later)
  job_type       VARCHAR(64)  NOT NULL DEFAULT '',  -- classification/engine code if available
  started_at     INT UNSIGNED NOT NULL,
  bytes_done     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  throughput_bps BIGINT UNSIGNED NOT NULL DEFAULT 0,
  last_update    INT UNSIGNED NOT NULL,
  last_bytes BIGINT NOT NULL DEFAULT 0,
  last_bytes_ts INT NOT NULL DEFAULT 0,
  cancel_attempts TINYINT NOT NULL DEFAULT 0,
  last_checked_ts INT NOT NULL DEFAULT 0;
  PRIMARY KEY (server_id, job_id),
  KEY idx_last_update (last_update)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

**Table: eb_jobs_recent_24h**
- Completed jobs within the recent window (used for incidents and summaries).

CREATE TABLE IF NOT EXISTS eb_jobs_recent_24h (
  server_id    VARCHAR(64)  NOT NULL,
  job_id       VARCHAR(64)  NOT NULL,
  username     VARCHAR(255) NOT NULL DEFAULT '',
  device       VARCHAR(255) NOT NULL DEFAULT '',
  job_type     VARCHAR(64)  NOT NULL DEFAULT '',
  status       ENUM('success','warning','error','missed','skipped') NOT NULL,
  bytes        BIGINT UNSIGNED NOT NULL DEFAULT 0,
  duration_sec INT UNSIGNED   NOT NULL DEFAULT 0,
  ended_at     INT UNSIGNED   NOT NULL,
  created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (server_id, job_id),
  KEY idx_status (status),
  KEY idx_ended (ended_at),
  KEY idx_username (username),
  KEY idx_device (device)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

**Table: eb_devices_registry**
- Live device roster for instant counters and 24-hour activity.

CREATE TABLE IF NOT EXISTS eb_devices_registry (
  server_id     VARCHAR(64)  NOT NULL,
  device_id     VARCHAR(128) NOT NULL,
  username      VARCHAR(255) NOT NULL DEFAULT '',
  friendly_name VARCHAR(255) NOT NULL DEFAULT '',
  platform_os   VARCHAR(32)  NOT NULL DEFAULT '',
  platform_arch VARCHAR(32)  NOT NULL DEFAULT '',
  registered_at INT UNSIGNED NOT NULL,
  last_seen     INT UNSIGNED NOT NULL DEFAULT 0,
  status        ENUM('active','removed') NOT NULL DEFAULT 'active',
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (server_id, device_id),
  KEY idx_username (username),
  KEY idx_status (status),
  KEY idx_last_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

Table: eb_devices_daily
# Immutable daily device snapshot for trends and reports.

CREATE TABLE IF NOT EXISTS eb_devices_daily (
  d           DATE PRIMARY KEY,
  registered  INT UNSIGNED NOT NULL DEFAULT 0,
  active_24h  INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

Table: eb_items_daily
# Immutable daily protected-item snapshot for trends and billing.

CREATE TABLE IF NOT EXISTS eb_items_daily (
  d           DATE PRIMARY KEY,
  di_devices  INT UNSIGNED NOT NULL DEFAULT 0,   -- Disk Image devices (distinct)
  hv_vms      INT UNSIGNED NOT NULL DEFAULT 0,   -- Hyper-V virtual machines
  vw_vms      INT UNSIGNED NOT NULL DEFAULT 0,   -- VMware virtual machines
  m365_users  INT UNSIGNED NOT NULL DEFAULT 0,   -- Microsoft 365 users
  ff_items    INT UNSIGNED NOT NULL DEFAULT 0    -- Files & Folders items (visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

Table: eb_incident_ack (optional, for “Snooze”)
# Hide acknowledged incidents for a period.

CREATE TABLE IF NOT EXISTS eb_incident_ack (
  client_id INT NOT NULL,
  job_id    VARCHAR(64) NOT NULL,
  until_ts  INT UNSIGNED NOT NULL,
  PRIMARY KEY (client_id, job_id),
  KEY idx_until (until_ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

# Runtime Behavior:
comet_ws_worker.php connects to each Comet server over WebSocket:
- Auth handshake: Username → AuthType → Password → SessionKey → TOTP.

Parses TypeString + Data and writes:
- SEVT_JOB_NEW → eb_jobs_live upsert
- SEVT_JOB_COMPLETED → eb_jobs_recent_24h upsert and remove from eb_jobs_live
- SEVT_DEVICE_NEW → eb_devices_registry upsert (status='active')
- SEVT_DEVICE_REMOVED → eb_devices_registry.status='removed'
- SEVT_DEVICE_UPDATED/SEVT_DEVICE_RENAMED → refresh friendly_name
- On every job completion, bump device last_seen in the registry.
- Ignores SEVT_ACCOUNT_* and SEVT_META_* chatter.
- Deltas: Worker updates eb_event_cursor so the SSE layer can compute “what changed since T”.

# Server-Sent Events (SSE) Endpoints
Exposed via the module routing (example query parameters shown):

GET ?m=eazybackup&a=pulse-events
Streams for ~25–30 seconds, flushing every 1–2 seconds.
Emits:
{"kind":"snapshot", ...} on connect and periodically
{"kind":"job:start", ...}, {"kind":"job:end", ...}
{"kind":"device:new", ...}, {"kind":"device:removed", ...}
GET ?m=eazybackup&a=pulse-snapshot
One-shot snapshot JSON (used for reconnects and polling fallback).
POST ?m=eazybackup&a=pulse-snooze
Body: job_id, minutes → inserts/refreshes eb_incident_ack for the current client.

Scoping: All queries must be constrained to the current WHMCS client context using the existing Comet-to-client mapping.

**Nightly Rollups**

**Cron examples (as www-data or root):**
- Devices daily (02:05)
5 2 * * * php /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin/rollup_devices_daily.php
- Items daily (02:10)
10 2 * * * php /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin/rollup_items_daily.php

- rollup_devices_daily.php → writes eb_devices_daily using eb_devices_registry
- rollup_items_daily.php → writes eb_items_daily using comet_items.content (exact JSON paths you provided)


## Comet Backup Engine → Friendly Type Mapping**

  $engineToLabel = [
    'engine1/file'            => 'Files and Folders',
    'engine1/stdout'          => 'Program Output',
    'engine1/mysql'           => 'MySQL',
    'engine1/systemstate'     => 'Windows Server System State',
    'engine1/mssql'           => 'Microsoft SQL Server',
    'engine1/windowssystem'   => 'Windows System Backup',
    'engine1/exchangeedb'     => 'Microsoft Exchange Server',
    'engine1/vsswriter'       => 'Application-Aware Writer',
    'engine1/hyperv'          => 'Microsoft Hyper-V',
    'engine1/windisk'         => 'Disk Image',
    'engine1/mongodb'         => 'MongoDB',
    'engine1/winmsofficemail' => 'Office 365',
    'engine1/vmware'          => 'VMware',
    'engine1/proxmox'         => 'Proxmox (PVE)',
  ];

## Comet Backup Vault → Friendly Type Mapping**
  VAULT_TYPES = [
      "0" => "INVALID",
      "1000" => "S3-compatible",
      "1001" => "SFTP",
      "1002" => "Local Path",
      "1003" => "Comet",
      "1004" => "FTP",
      "1005" => "Azure",
      "1006" => "SPANNED",
      "1007" => "OpenStack",
      "1008" => "Backblaze B2",
      "1100" => "latest",
      "1101" => "All",
  ];

**Backup Job Status → Friendly Type Mapping**
  BACKUP_STATUS_CODES = [
      "9999" => "UNKNOWN",
      "5000" => "SUCCESS",
      "6001" => "ACTIVE",
      "6002" => "REVIVED",          # nomalize to active
      "7000" => "TIMEOUT",          # nomalize to error
      "7001" => "WARNING",
      "7002" => "ERROR",
      "7003" => "QUOTA_EXCEEDED",   # nomalize to error
      "7004" => "MISSED",
      "7005" => "CANCELLED",        # nomalize to error
      "7006" => "ALREADY_RUNNING",  # nomalize to error
      "7007" => "ABANDONED"         # nomalize to error
  ];

## monitor_stalled_jobs.php
Note: We identified found a problem where some backup jobs remain in the "running" status but they are actually abandoned. Jobs in this state are never removed from table eb_jobs_live. When a Comet Server administrator manually cancels these jobs from the Comet web UI, we have found that the Comet WebSocket did not emit any SEVT_JOB_*. For this reason, WebSocket alone won’t reliably close those jobs. 

To prevent abondoned backup jobs from getting stuck in eb_jobs_live, we will monitor the job's upload progress and the Heartbeat: For running jobs, Comet exposes BackupJobDetail.Progress with a monotonic Counter and SentTime/RecievedTime timestamps—these tick even when UploadSize is flat. We also use this at our  “is it alive?” signal. If the amount of data uploaded has not changed 1 hour, in most cases we can assume the job is abandoned. If we detect that the job is abandoned, our application should attempt to force cancel that job on the job server. This way that job is automatically cleaned up and marked as abandoned or cancelled. 

**I created a new file for this feature at accounts/modules/addons/eazybackup/bin/monitor_stalled_jobs.php.**
- We can manually run the script with or without a dry-run flag 
  php monitor_stalled_jobs.php --verbose=1 --profile=eazybackup --stale-secs=600 --recheck-secs=300
  php monitor_stalled_jobs.php --dry-run --verbose=1 --profile=eazybackup --stale-secs=600 --recheck-secs=300

**CLI flags (override env):**
  --stale-secs=<seconds> (default 3600)
  --recheck-secs=<seconds> (default 300) [also used as heartbeat window]
  --max-attempts=<n> (default 3)
  --limit=<n> (default 200)
- Example: php monitor_stalled_jobs.php --profile=eazybackup --stale-secs=3600 --recheck-secs=300

**/var/www/eazybackup.ca/.env (baseline defaults, loaded by bootstrap):**

  # monitor_stalled_jobs.php defaults (can be overridden per run)
  EB_MON_STALE_SECS=3600
  EB_MON_RECHECK_SECS=300
  EB_MON_MAX_ATTEMPTS=3
  EB_MON_BATCH_LIMIT=200
  # Optional:
  EB_MON_VERBOSE=1
  EB_MON_DRY_RUN=1

**Here’s what each variable does:**
-  — “No-progress” window before a job is a candidate.
  - Example: 3600 = 1 hour without progress/heartbeat before we act.

- EB_MON_RECHECK_SECS # Per-row backoff. After we look at a job, do not look again for at least this many seconds.
  - Example: 300 = do not re-check the same job for 5 minutes.

- EB_MON_MAX_ATTEMPTS # How many times we’ll try (cancel/abandon/re-read) before giving up for this cycle.
- EB_MON_BATCH_LIMIT # Max rows processed per profile per pass. Keeps the run “light.”
- EB_MON_VERBOSE (optional) # 1 to print extra status lines to standard error.
- EB_MON_DRY_RUN (optional) # 1 means print what we’d do; no Admin API calls, no database writes.

- The monitor_stalled_jobs.php script runs every 5 minutes, controlled by a systemd timer

**monitor_stalled_jobs Systemd (per profile)**
1) Service: /etc/systemd/system/eb-monitor-eazybackup.service

  [Unit]
  Description=EazyBackup stalled job monitor (eazybackup)
  Wants=network-online.target
  After=network-online.target

  [Service]
  Type=oneshot
  WorkingDirectory=/var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin
  # Create a private runtime dir owned by www-data under /run
  RuntimeDirectory=eb-monitor
  User=www-data
  Group=www-data
  ExecStart=/usr/bin/flock -n /run/eb-monitor/eazybackup.lock \
    /usr/bin/php monitor_stalled_jobs.php --profile=eazybackup --recheck-secs=300 --stale-secs=3600
  # Optional hardening / quality-of-life
  Nice=5
  IOSchedulingClass=best-effort
  IOSchedulingPriority=5
  PrivateTmp=yes
  ProtectSystem=full
  ProtectHome=true
  NoNewPrivileges=true
  StandardOutput=journal
  StandardError=journal

2) Timer: /etc/systemd/system/eb-monitor-eazybackup.timer  

  [Unit]
  Description=Run eb-monitor-eazybackup every 5 minutes

  [Timer]
  OnBootSec=1min
  OnUnitActiveSec=5min
  Unit=eb-monitor-eazybackup.service
  AccuracySec=30s
  RandomizedDelaySec=15s
  Persistent=true

  [Install]
  WantedBy=timers.target

3) Service: /etc/systemd/system/eb-monitor-obc.service

  [Unit]
  Description=OBC stalled job monitor (obc)
  Wants=network-online.target
  After=network-online.target

  [Service]
  Type=oneshot
  WorkingDirectory=/var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin
  RuntimeDirectory=eb-monitor
  User=www-data
  Group=www-data
  ExecStart=/usr/bin/flock -n /run/eb-monitor/obc.lock \
    /usr/bin/php monitor_stalled_jobs.php --profile=obc --recheck-secs=300 --stale-secs=3600
  Nice=5
  IOSchedulingClass=best-effort
  IOSchedulingPriority=5
  PrivateTmp=yes
  ProtectSystem=full
  ProtectHome=true
  NoNewPrivileges=true
  StandardOutput=journal
  StandardError=journal


4) Timer: /etc/systemd/system/eb-monitor-obc.timer  

  [Unit]
  Description=Run eb-monitor-obc every 5 minutes

  [Timer]
  OnBootSec=1min
  OnUnitActiveSec=5min
  Unit=eb-monitor-obc.service
  AccuracySec=30s
  RandomizedDelaySec=15s
  Persistent=true

  [Install]
  WantedBy=timers.target


5) Enable monitor_stalled_jobs Systemd service
  sudo systemctl daemon-reload
  sudo systemctl enable --now eb-monitor-eazybackup.timer eb-monitor-obc.timer

**sanity checks**
  systemctl status eb-monitor-eazybackup.timer eb-monitor-obc.timer
  journalctl -u eb-monitor-eazybackup.service -f
  journalctl -u eb-monitor-obc.service -f

**Note: If you make changes in .env**
  sudo systemctl daemon-reload
  sudo systemctl restart eb-monitor-eazybackup.timer eb-monitor-obc.timer

**The services will:**
- give each profile its own lock (/run/eb-monitor/eazybackup.lock and /run/eb-monitor/obc.lock),
- run them as www-data,
- and ensure they have a writable runtime directory under /run thanks to RuntimeDirectory=eb-monitor.

**Sanity checklist (so nothing bites you later)**
- Lock path is writable: solved via RuntimeDirectory=eb-monitor.
- Correct profile per service: --profile=eazybackup for eazybackup; --profile=obc for OBC.
- Timer targets the matching service: unit lines fixed or templated.
- Network readiness: Wants=network-online.target and After=network-online.target.
- Missed runs on reboot: Persistent=true on timers.
- No overlaps: flock -n with per-profile locks; Type=oneshot is right for timer-driven runs.
- Working directory: set to the bin folder so relative includes (via your bootstrap.php) behave.

**What monitor_stalled_jobs.php does:**
- Scans all configured Comet profiles (e.g., eazybackup, obc) from the .env.
- Finds running jobs in eb_jobs_live that haven’t uploaded new bytes for ≥ 1 hour (tunable).
- Uses Comet AdminGetJobProperties to confirm state and read UploadSize.
- If still running and idle: tries AdminJobCancel (if CancellationID present).
- If cancel is not possible or ineffective, calls AdminJobAbandon.
- On any terminal state, finalizes the job into eb_jobs_recent_24h and removes it from eb_jobs_live.
- Throttles with last_checked_ts and caps cancel_attempts.

- It expects the existing bin/bootstrap.php for db(), cfg(), logLine() and .env loading.

**monitor_stalled_jobs loop**
- Runs every 5 minutes as a timer inside the existing worker.
- Selection: pull only likely-stale rows.

  SELECT * FROM eb_jobs_live
  WHERE status = 4001 /* running */
    AND (UNIX_TIMESTAMP() - GREATEST(last_bytes_ts, started_at)) >= 3600
    AND cancel_attempts < 3
  ORDER BY last_checked_ts ASC
  LIMIT 200;

- For each candidate job_id on server_id:
    - GET details via AdminGetJobProperties.
    - If job now shows a terminal status (success/failed/cancelled/etc), call your existing finishJob(...) and remove from eb_jobs_live. (If a Comet Server adminsitrator cancels a job from the UI in Comet, there is no SEVT_JOB_* event.) 

- If still running:
  - Read UploadSize (and Progress if present). If UploadSize > last_bytes, update last_bytes, last_bytes_ts, last_checked_ts and do nothing (it’s not stalled).

  - If UploadSize unchanged for ≥ 1 hour:
    - If CancellationID is non-empty, call AdminJobCancel. On OK, re-fetch properties; if it’s now terminal, call finishJob(...). If it remains “Running” (device offline), fall through to abandon. 
    - Otherwise (no CancellationID or cancel failed / device offline), call AdminJobAbandon and then re-fetch properties. If terminal → finishJob(...).
    - Increment cancel_attempts and set last_checked_ts = NOW().
    - Backoff: if a cancel/abandon attempt fails due to a transient (HTTP 5xx / network), just bump last_checked_ts and try on the next sweep.

**What we write back on success**
- When a cancel or abandon “sticks” (properties show a terminal state), call your existing finishJob(...) that:
  - deletes from eb_jobs_live;
  - inserts a final row into eb_jobs_recent_24h with a mapped status (e.g., Cancelled / Abandoned);
  - sets ended_at = NOW();
  - Protection Pulse card and “Incidents” tab react instantly.

**Safety rails / edge cases**
- No SEVT on cancel: Totally expected in some flows; that is why we fall back to polling AdminGetJobProperties. 
- Device comes back after Abandon: Comet can “revive” the job if it detects it was actually still running. Your next WebSocket SEVT_JOB_COMPLETED will finish it properly; until then, the row will already be out of eb_jobs_live, which is what you want for the live view. 
- Throttle API calls: Use LIMIT and last_checked_ts to avoid hammering big fleets; shard by server_id if you want to parallelize.