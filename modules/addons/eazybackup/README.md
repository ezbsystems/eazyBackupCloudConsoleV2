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

**Form Fields**
Make sure to add the following tailwind css rules to form fileds: 
  focus:outline-none focus:ring-0 focus:border-sky-600

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

## comet_ws_worker.php Jobs, Device and Protected Item ingestion (WebSocket-driven)
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
systemctl restart eazybackup-comet-ws@cometbackup eazybackup-comet-ws@obc

systemctl start eazybackup-comet-ws@cometbackup.service
systemctl start eazybackup-comet-ws@obc

systemctl status eazybackup-comet-ws@cometbackup -n 50
systemctl status eazybackup-comet-ws@obc -n 50

cd /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin
php monitor_stalled_jobs.php --verbose=1 --profile=cometbackup
php monitor_stalled_jobs.php --verbose=1 --profile=obc

**Live logs:**
journalctl -u eazybackup-comet-ws@cometbackup.service -f

**Debugging:**
Temporarily set EB_WS_DEBUG=1 and/or EB_DB_DEBUG=1 in the unit and restart.

**Monitor the websocket live:**
cd /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin
EB_WS_DEBUG=1 EB_DB_DEBUG=0 COMET_PROFILE=cometbackup php comet_ws_worker.php

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
  systemctl enable --now eazybackup-comet-ws@cometbackup
  systemctl enable --now eazybackup-comet-ws@obc

**Logs:**
  journalctl -u eazybackup-comet-ws@cometbackup -f

**Environment Configuration**
- Global .env (example: /var/www/eazybackup.ca/.env), one block per Comet profile.
- Set via environment or in the systemd unit.


  # -------- Comet: cometbackup --------
  COMET_cometbackup_NAME="Comet Backup"
  COMET_cometbackup_URL="wss://csw.eazybackup.ca/api/v1/events/stream"
  COMET_cometbackup_ORIGIN="https://csw.eazybackup.ca"
  COMET_cometbackup_USERNAME="websocket"
  COMET_cometbackup_AUTHTYPE="Password"
  COMET_cometbackup_PASSWORD=""
  COMET_cometbackup_SESSIONKEY=""
  COMET_cometbackup_TOTP=""

  # -------- Comet: obc --------
  COMET_obc_NAME="OBC"
  COMET_obc_URL="wss://csw.obcbackup.com/api/v1/events/stream"
  COMET_obc_ORIGIN="https://eazybackup.local"
  COMET_obc_USERNAME="websocket"
  COMET_obc_AUTHTYPE="Password"
  COMET_obc_PASSWORD=""
  COMET_obc_SESSIONKEY=""
  COMET_obc_TOTP=""

  # Database (bootstrap.php defaults can be overridden here)
  DB_DSN="mysql:host=127.0.0.1;dbname=eazyback_whmcs;charset=utf8mb4"
  DB_USER="whmcs"
  DB_PASS=""

  # monitor_stalled_jobs.php defaults (can be overridden per run)
  EB_MON_STALE_SECS=3600
  EB_MON_RECHECK_SECS=300
  EB_MON_MAX_ATTEMPTS=3
  EB_MON_BATCH_LIMIT=200
  # Optional:
  # EB_MON_VERBOSE=1
  # EB_MON_DRY_RUN=1

  # Debug toggles (optional):
  # EB_WS_DEBUG=1 ? log raw frames and parsed events.
  # EB_DB_DEBUG=1 ? log database writes.


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
  php monitor_stalled_jobs.php --verbose=1 --profile=cometbackup --stale-secs=600 --recheck-secs=300
  php monitor_stalled_jobs.php --dry-run --verbose=1 --profile=cometbackup --stale-secs=600 --recheck-secs=300

**CLI flags (override env):**
  --stale-secs=<seconds> (default 3600)
  --recheck-secs=<seconds> (default 300) [also used as heartbeat window]
  --max-attempts=<n> (default 3)
  --limit=<n> (default 200)
- Example: php monitor_stalled_jobs.php --profile=cometbackup --stale-secs=3600 --recheck-secs=300

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
    /usr/bin/php monitor_stalled_jobs.php --profile=cometbackup --recheck-secs=300 --stale-secs=3600
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
- Correct profile per service: --profile=cometbackup for eazybackup; --profile=obc for OBC.
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

## Device Management Feature – Run Backup, Update Software, Revoke, Uninstall

These features live in the Profile → Devices tab. Each device row has a “Manage” button that opens a slide-over panel with two tabs (Device, Storage Vault).

### Files involved
- UI (client):
  - `templates/console/user-profile.tpl` (Devices table, Manage slide-over)
  - `modules/addons/eazybackup/assets/js/device-actions.js` (panel logic, menus, dispatcher calls, confirmations/toasts)
- Backend (AJAX endpoint):
  - `pages/console/device-actions.php` (isolated handlers)
  - Router: `eazybackup.php` → `?a=device-actions`
- Data helpers:
  - `accounts/modules/servers/comet/summary_functions.php` (device list, protected items count)

### Data sources & lookups
- Device table rows: `getUserDevicesDetails($username, $serviceid)` enriches:
  - Online status via `AdminDispatcherListActive`.
  - Protected Items count via `UserProfile.Sources` filtered by `OwnerDevice == deviceId`.
  - Platform, registration time, client version via `UserProfile.Devices[deviceId]`.
- Manage panel Protected Items menu: reads `AdminGetUserProfileAndHash(Username)`, then filters `Profile.Sources` by `OwnerDevice == deviceId`. The user sees the `Description`; we keep the GUID for API calls.
- Storage Vault menus: populated from `$vaults` already passed to the template.

### Dispatcher TargetID vs DeviceID
Admin Dispatcher APIs require a live connection GUID (TargetID), not the device GUID. We map DeviceID → TargetID by calling `AdminDispatcherListActive($username)` and matching on `LiveUserConnection.DeviceID`. If no live match, we return a friendly “device not online” message.

### API methods used
- Update Software
  - `AdminDispatcherListActive(UserNameFilter)` → TargetID
  - `AdminDispatcherUpdateSoftware(TargetID)`
- Uninstall Software
  - `AdminDispatcherListActive(UserNameFilter)`
  - `AdminDispatcherUninstallSoftware(TargetID, RemoveConfigFile)`
- Revoke Device
  - `AdminRevokeDevice(Username, DeviceID)` (dispatcher not required)
- Run Backup
  - `AdminGetUserProfileAndHash(Username)` → enumerate `Profile.Sources` (filter by `OwnerDevice`)
  - `AdminDispatcherListActive(UserNameFilter)` → TargetID
  - `AdminDispatcherRunBackupCustom(TargetID, ProtectedItemGUID, StorageVaultGUID)`
- Storage Vault tab actions
  - `AdminDispatcherApplyRetentionRules(TargetID, DestinationGUID)`
  - `AdminDispatcherReindexStorageVault(TargetID, DestinationGUID)`

### UX notes
- Tailwind confirmation modal replaces browser confirm for destructive actions (Revoke, Uninstall, Reindex, Update).
- Global toasts (`#toast-container`) show success/error for all actions.
- Run Backup controls are hidden until the user clicks “Run Backup…”, then the user selects Protected Item (by name; GUID is used under the hood) and a Storage Vault, and submits.

### Security/scoping
- `device-actions.php` verifies the logged-in WHMCS client owns the `serviceId` and normalizes the username from the service record.
- All dispatcher calls are made using the configured server credentials for the WHMCS product’s server group.


## Restore Backup Feature – Wizard (Client Area)

This feature enables starting a restore from the WHMCS client area, modeled on Comet’s web UI. It currently supports selecting a Storage Vault, choosing a Protected Item and snapshot, and submitting a basic restore with initial engine-specific options.

### Files involved
- UI (client):
  - `templates/console/user-profile.tpl`
    - Contains the slide-over Manage panel and the Restore Wizard modal (`#restore-wizard`).
    - The Restore Step 1 vault selector is an Alpine.js dropdown (not a `<select>`).
  - `modules/addons/eazybackup/assets/js/device-actions.js`
    - Wires the Manage panel actions and the Restore wizard steps (load items, load snapshots, method selection, submit restore).
- Backend (AJAX endpoint):
  - `pages/console/device-actions.php`
    - Actions: `listProtectedItems`, `vaultSnapshots`, `runRestore`.
  - Router: `eazybackup.php` → `?a=device-actions`.

### Data sources & flow
1) Device context:
   - The Restore wizard acts on the device selected in the Manage slide-over. Dispatcher actions require the device to be online.
   - DeviceID → TargetID mapping is resolved via `AdminDispatcherListActive($username)`; `TargetID` is required for dispatcher APIs.

2) Step 1 – Select Storage Vault (Alpine dropdown):
   - Vault list comes from `$vaults` already provided to `user-profile.tpl`.
   - Selecting a vault stores the GUID and advances to Step 2.

3) Step 2 – Protected Item & Snapshot:
   - Protected items are loaded from the user profile (`AdminGetUserProfileAndHash`) and filtered by `OwnerDevice == current DeviceID`.
   - Snapshots are read via `AdminDispatcherRequestVaultSnapshots(TargetID, VaultId)` and filtered client-side by the selected Protected Item (Source GUID).

4) Step 3 – Method selection and options (initial coverage):
   - Files & Folders (`engine1/file`):
     - Methods: Files and Folders; Compressed archive; Simulate restore only.
   - Disk Image (`engine1/windisk`):
     - Method: Files and Folders (restore disk image files). More modes to be added.
   - Microsoft Hyper‑V (`engine1/hyperv`):
     - Method: Files and Folders (restore VM files). Hypervisor/VM-targeted restore to be added.
   - Common options scaffolded: destination path (text), overwrite policy (`none | ifNewer | ifDifferent | always`).

5) Submit:
   - `runRestore` builds `RestoreJobAdvancedOptions` based on selection and calls `AdminDispatcherRunRestoreCustom(TargetID, ...)`.

### API methods used
- Online mapping:
  - `AdminDispatcherListActive(UserNameFilter)` → `TargetID` (`LiveUserConnection`)
- Enumerate:
  - `AdminGetUserProfileAndHash(Username)` → `Profile.Sources` (Protected Items)
  - `AdminDispatcherRequestVaultSnapshots(TargetID, DestinationGUID)` → `DispatcherVaultSnapshotsResponse`
- Execute:
  - `AdminDispatcherRunRestoreCustom(TargetID, ...)` with `RestoreJobAdvancedOptions`

### UX details
- The Restore wizard opens from the Manage panel’s “Restore…” button.
- Step 1 uses an Alpine.js dropdown for vault selection. The label updates to the chosen vault and the wizard moves to Step 2.
- Step 2 shows a two-pane list: Protected Items and Snapshots. Selecting both enables method options and the “Start Restore” action.
- Toasts (global `#toast-container`) provide success/error feedback for all actions.
- Robustness: If the template markup is ever missing, the JS includes a fallback minimal wizard to keep the feature usable (primarily for development).

### Current status
- Completed:
  - Modal markup integrated into `user-profile.tpl`.
  - Alpine-based vault dropdown in Step 1 with de-duplicated entries.
  - Load and filter Protected Items (by device) and list snapshots per item.
  - Engine-aware method options (initial set) and basic restore submission.
  - TargetID mapping and online checks for dispatcher calls.

- Remaining work (planned):
  - Engine-specific advanced options:
    - `engine1/hyperv`: direct restore to Hyper‑V (VM selection, storage path for VHDs).
    - `engine1/windisk`: physical restore mapper (source partitions → destination disks/partitions UI).
  - Device-side file browser for selecting destination paths.
  - Persist and load recent choices in-session to streamline repeated restores.
  - Progress/telemetry surfacing (restore job listing and status after submission).

## Developer Notes – Password Reset (Client UI Actions)

- UI: An "Actions" dropdown button was added to the Profile tab navigation in `templates/console/user-profile.tpl`. Selecting "Reset password" triggers an Alpine custom event `eb-reset-password`.
- Frontend script: `assets/js/user-actions.js`
  - Listens for `eb-reset-password` and opens a dedicated Reset Password modal with:
    - A password input
    - A "Generate" button (generates a strong 16‑char password)
    - A primary "Reset password" submit button
    - A top‑right X close button. Clicking the overlay or X closes the modal without generating or submitting
  - Flow after submit:
    - Shows a loader
    - Calls `EB_USER_ENDPOINT` (`?m=eazybackup&a=user-actions`) with action `resetPassword` and payload `{ password }`
    - On success, immediately shows a "New Password" modal with the new password and a click‑to‑copy button (uses Clipboard API)
    - Also shows an action reminder panel advising to re‑sign in on all devices
- Backend endpoint: `pages/console/user-actions.php`
  - Action: `resetPassword`
  - Verifies client ownership of `serviceId` and `username`
  - Calls Comet Admin API `AdminResetUserPassword($username, 'Password', $newPassword)`
  - Updates WHMCS service credentials with `comet_UpdateServiceCredentials` (encrypted)
  - Returns `{ status: 'success', password: <string> }` on success


## Developer Notes – Quota Management (Profile → User Details)

- UI: A "Quotas" card is rendered below the User Details in `templates/console/user-profile.tpl`.
  - Four controls with Alpine toggles and numeric inputs:
    - Maximum devices → `MaximumDevices`
    - Microsoft 365 protected accounts → `QuotaOffice365ProtectedAccounts`
    - Hyper‑V guests → `QuotaHyperVGuests`
    - VMware guests → `QuotaVMwareGuests`
  - Enable/disable semantics:
    - Enabled if the value is a positive integer (≥1)
    - Disabled (unlimited) if the value is 0
  - When a toggle is Off: input is disabled (`disabled`, `opacity-50`, `cursor-not-allowed`, `tabindex=-1`) and the payload sends `0`
  - When a toggle is On: input enforces integer ≥1; blur coerces to at least 1
  - Buttons: "Reset" (reload from profile) and "Save quotas"
- Data flow helpers (inline in template):
  - `call('piProfileGet', { username })` → fetches profile via `device-actions.php`
  - `call('piProfileUpdate', payload)` → updates the four fields via `device-actions.php`
- Backend (`pages/console/device-actions.php`):
  - `piProfileGet`: `AdminGetUserProfileAndHash($username)` → returns `{ profile, hash }`
  - `piProfileUpdate`: re‑reads profile and `AdminSetUserProfileHash($username, $profile, $hash)` after applying the four integer fields (coerced to `>=0`), where `0` means unlimited


## Developer Notes – VM / M365 / Device Counts

### Counts on Profile page
- Backend: `pages/console/user-profile.php`
  - Computes usage counters for display in the "User Details" panel:
    - Hyper‑V VMs: sum of `Statistics.LastSuccessfulBackupJob.TotalVmCount` (fallback to `LastBackupJob` when `Status == 5000`/`SUCCESS`) across all `Profile.Sources[*]` whose `Engine` contains `hyperv`
    - VMware VMs: same logic across `Profile.Sources[*]` whose `Engine` contains `vmware`
    - The counters are exposed as `hvGuestCount` and `vmwGuestCount` (rendered directly as integers)
  - Microsoft 365 protected accounts count remains from existing logic (`MicrosoftAccountCount($user)`) and is displayed as `{$msAccountCount}`

### Counts on Dashboard → Users table
- Backend: `eazybackup.php` (action `a=dashboard`)
  - For each active service user, reads `AdminGetUserProfileAndHash`
  - Exposes per‑user fields in `$accounts[]` used by `templates/clientarea/dashboard.tpl`:
    - `hv_vm_count` and `vmw_vm_count`: computed by summing `TotalVmCount` per engine as described above
    - `m365_accounts`: read from `Profile.QuotaOffice365ProtectedAccounts` (displayed per requirements)
    - `total_devices`: counted from `comet_devices`
    - `total_protected_items`: counted from `comet_items`
    - `vaults`: list of the user’s vault rows; template shows `count(vaults)`
- Frontend: `templates/clientarea/dashboard.tpl`
  - Users table gained three optional columns (toggled in the View dropdown):
    - Hyper‑V Count → `{$account.hv_vm_count|default:0}`
    - VMware Count → `{$account.vmw_vm_count|default:0}`
    - MS365 Protected Accounts → `{$account.m365_accounts|default:0}`

Notes:
- All counts are computed in read operations and surfaced via existing endpoints/templates; no schema changes are required.
- Error handling is defensive; if profile reads fail, counters default to 0 without disrupting page rendering.

### Notes & edge cases
- Dispatcher calls will fail with 403 if the device is offline or `TargetID` was not resolved; we return a friendly message in that case.
- Snapshot lists are filtered client-side by Source GUID; when there are many snapshots, consider server-side filtering in a future iteration.
- Concurrency: restore submission itself is not hash-protected; Protected Item and vault selection depend on a fresh profile read in the same flow.

## Developer Notes – Dashboard Live Running Job Slivers (24h timeline)

Overview
- Show running jobs in near real-time on the Dashboard → Last 24 hours timeline as pulsing blue slivers; remove on completion; no reload required.

Files involved
- UI (client):
  - `templates/clientarea/dashboard.tpl`
    - Injects pulse endpoints via `{$modulelink}`.
    - Includes: `assets/js/pulse-events.js`, `assets/js/dashboard-timeline.js`.
    - Timeline Alpine component subscribes to live updates and recomputes.
- Frontend scripts:
  - `assets/js/pulse-events.js`
    - Connects to `?m=eazybackup&a=pulse-events` (SSE) and seeds from `?m=eazybackup&a=pulse-snapshot` (JSON).
    - Emits `eb:pulse-snapshot` and `eb:pulse` browser events.
    - Quick reconnect (1s) and 10s JSON polling fallback if SSE drops.
  - `assets/js/dashboard-timeline.js`
    - Keeps a running-jobs store keyed by `username + device_name`.
    - On updates, dispatches `eb:timeline-changed` and bumps `Alpine.store('ebTimeline').ver`.

Backend endpoints
- Router (`eazybackup.php`): actions added:
  - `a=pulse-events` → SSE stream (live running job deltas)
  - `a=pulse-snapshot` → JSON one-shot
  - `a=pulse-snooze` → optional incident snooze
- Controller (`pages/console/pulse.php`):
  - `eb_pulse_events()`
    - Scopes to the logged-in client’s active Comet usernames.
    - Sends a `snapshot` then diffs `eb_jobs_live` every ~1s for ~30s, emitting `job:start` for new rows and `job:end` for disappeared rows (enriched from `eb_jobs_recent_24h` when possible).
    - SSE headers: no cache, keep-alive, buffering disabled.
  - `eb_pulse_snapshot()`
    - Returns `jobsRunning` from `eb_jobs_live` and `jobsRecent24h` from `eb_jobs_recent_24h`.

Data sources
- `eb_jobs_live` → running jobs (written by `bin/comet_ws_worker.php`, cleaned by `bin/monitor_stalled_jobs.php`).
- `eb_jobs_recent_24h` → recent completed jobs.
- `eb_devices_registry` → enrich device `friendly_name`.

Template behavior
- `jobs24h()` merges completed jobs (`device.jobs`) with live running jobs from `__EB_TIMELINE.getFor(device.username, device.name)` and sorts by start time.
- Running slivers: Tailwind `bg-blue-500 animate-pulse`.
- Re-render triggers: `eb:timeline-changed` event and `Alpine.store('ebTimeline').ver`.

Expected latency
- ~1–2 seconds from job start/end to timeline update when SSE is connected.
- Fallback polling: ~10 seconds if SSE is unavailable.

Troubleshooting
- Ensure SSE isn’t buffered by reverse proxies (we send `X-Accel-Buffering: no`; disable compression for the route).
- Verify the dashboard is visible so Alpine refreshes in view.
- Network tab should show periodic `data:` SSE events every second.