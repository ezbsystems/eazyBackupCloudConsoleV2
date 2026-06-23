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
  table eb_device_groups           # Device grouping: group definitions (Phase 1)
  table eb_device_group_assignments # Device grouping: device-to-group assignments 

## File Layout

- All paths relative to the addon root: accounts/modules/addons/eazybackup/.

bin/
  bootstrap.php            # Shared bootstrap (autoload, .env, db(), logger)
  comet_ws_worker.php      # WebSocket worker: Comet → DB (jobs/devices)
  rollup_devices_daily.php # Nightly snapshot of device counts
  rollup_storage_daily.php # Nightly snapshot of storage totals
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

## Automated Tests

The addon ships with a layered automated test suite (static contract tests + PHPUnit unit + PHPUnit integration) covering the Partner Hub MSP billing system, the white-label tenant pipeline, and the canonical Stripe Connect integration.

**Quick start:**

```bash
cd accounts/modules/addons/eazybackup
composer install      # one-time
composer test:all     # gate -> unit -> integration
```

For everything else — composer scripts, where to add new tests, Stripe / SMTP test seams, manual QA fixtures, the production-DB guard, the release-gate integration, and the phase roadmap — see the dedicated guide:

[Docs/PARTNER_HUB_AUTOMATED_TESTING.md](./PARTNER_HUB_AUTOMATED_TESTING.md)

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
  - Looks up tblservergroups.name ==  → tblservergroupsrel →   tblservers.
  - Decrypts tblservers.password with localAPI('DecryptPassword').
  - Builds base URL from hostname, secure, port.
  - Fallback: if WHMCS mapping fails, uses .env COMET__ORIGIN/USERNAME/PASSWORD.

**Device ingestion**

- Event triggers:
  - SEVT_DEVICE_NEW: upsert device as active.
  - SEVT_DEVICE_REMOVED: mark device revoked/inactive.
- Data extraction:
  - Actor → username
  - ResourceID → Comet DeviceID hash (stored in `hash` column)
  - Data → FriendlyName, PlatformVersion.os, PlatformVersion.arch
- Database (table comet_devices):
  - On SEVT_DEVICE_NEW:
  - Upsert: id = sha256(client_id + hash), hash (raw Comet DeviceID), username, client_id (from tblhosting.username), content (raw JSON), name, platform_os, platform_arch, is_active=1, updated_at=NOW(), revoked_at=NULL (set created_at on first insert).
- On SEVT_DEVICE_REMOVED:
  - Update: is_active=0, revoked_at=NOW(), updated_at=NOW().
- Notes:
  - client_id is resolved via tblhosting for the username.
  - The `id` column is a computed hash: `sha256(client_id + device_hash)`. This ensures uniqueness when the same Comet device is used across multiple WHMCS clients.
  - All writes are idempotent; conflicts are handled via ON DUPLICATE KEY UPDATE.
  - If client_id cannot be resolved (user not found in WHMCS), the device upsert is skipped to avoid creating orphaned records.

**Protected item ingestion**

- Event trigger: SEVT_ACCOUNT_UPDATED (we intentionally ignore SEVT_ACCOUNT_LOGIN to avoid noise).
- Fetch:
  - Admin API AdminGetUserProfile().
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
  - See "Nightly Sync Cron Optimization" section below for performance details.

**Schema highlights**

- comet_devices:
  - id (VARCHAR), hash (VARCHAR), client_id (INT), username (VARCHAR), content (JSON), name (VARCHAR), platform_os (VARCHAR), platform_arch (VARCHAR), is_active (TINYINT), created_at (TIMESTAMP), updated_at (TIMESTAMP), revoked_at (TIMESTAMP)
- comet_items:
  - id (UUID), client_id (INT), username (VARCHAR), content (JSON), owner_device (VARCHAR), comet_device_id (VARCHAR), name (VARCHAR), type (VARCHAR), total_bytes (BIGINT), total_files (BIGINT), total_directories (BIGINT), created_at (TIMESTAMP), updated_at (TIMESTAMP)

This flow ensures the client dashboard reflects device and item changes quickly, with a simple daily safety pass to reconcile any gaps.

### Device ID Generation Scheme (Important)

Both `comet_ws_worker.php` and `lib/Comet.php` must use the same ID generation scheme for the `comet_devices.id` column:

```php
$id = hash('sha256', (string)$clientId . $deviceHash);
```

**Why this matters:**

- The `comet_devices` table has `PRIMARY KEY (id)` and `UNIQUE (hash, client_id)`.
- The same Comet device (identified by `hash`) can be used by different WHMCS clients.
- Using `id = sha256(client_id + hash)` ensures each client's device record is unique.

**Historical issue (fixed Jan 2026):**

- The websocket worker previously used `id = hash` (raw device hash), while `lib/Comet.php` used `id = sha256(client_id + hash)`.
- This mismatch caused duplicate entry errors when the worker tried to upsert devices.
- The fix aligned the worker with `lib/Comet.php` and cleaned up legacy rows with `client_id = 0`.

**Database cleanup required when deploying this fix:**

```sql
-- Delete legacy rows with no valid client
DELETE FROM comet_devices WHERE client_id = 0;

-- Update rows using old ID scheme to use correct scheme
UPDATE comet_devices SET id = SHA2(CONCAT(client_id, hash), 256) 
WHERE id = hash AND client_id > 0;

-- Verify cleanup
SELECT COUNT(*) FROM comet_devices WHERE client_id = 0;  -- Should be 0
SELECT COUNT(*) FROM comet_devices WHERE id = hash;      -- Should be 0
```

### Device Online/Offline Status (is_active) Handling

The `comet_devices.is_active` column tracks whether a device is currently online (connected to the Comet server). This status is displayed on the WHMCS dashboard.

**Challenge:** Comet emits websocket events when devices come online (`SEVT_DEVICE_NEW`) but does NOT emit events when devices go offline. When a device disconnects, Comet simply removes it from its internal dispatcher list without notification.

**Solution (Jan 2026):** A periodic heartbeat mechanism polls `AdminDispatcherListActive()` every 60 seconds to sync device online status.

#### How it works

1. **Device comes online** (`SEVT_DEVICE_NEW`):
  - The websocket worker calls `upsertCometDevice()` with `is_active=true`.
  - The device is immediately marked as online in the database.
2. **Device sync** (`syncUserDevices()`):
  - When called (e.g., on `SEVT_DEVICE_UPDATED`), fetches `AdminDispatcherListActive()` for the user.
  - Each device is checked against the active connections list.
  - `is_active` is set based on whether the specific (username, deviceHash) pair is in the active list.
3. **Periodic heartbeat** (every 60 seconds):
  - The worker runs `refreshDeviceOnlineStatus()` on a timer.
  - Calls `AdminDispatcherListActive('')` to get ALL active connections from the Comet server.
  - Uses **server group mapping** to determine which devices belong to this Comet server:
    - Queries `tblhosting` → `tblservergroupsrel` → `tblservergroups` to find all usernames on this server.
  - For each device belonging to this server:
    - If the (username, deviceHash) pair is in the active list → `is_active=1`
    - If not in the active list → `is_active=0`
  - This correctly handles users with ALL devices offline (no longer in active list).

#### Key implementation details

**Username + DeviceHash matching:**
The same device hash can exist for multiple users (shared device scenario). The heartbeat must check the **(username, deviceHash) pair**, not just the hash:

```php
// Build active connections map: username => [deviceHash => true]
$activeByUser = [];
foreach ($activeConnections as $conn) {
    $activeByUser[$conn->Username][$conn->DeviceID] = true;
}

// Check if THIS user's device is active
$shouldBeActive = isset($activeByUser[$deviceUsername][$deviceHash]) ? 1 : 0;
```

**Server group filtering:**
Each worker handles one Comet server profile. The heartbeat only updates devices belonging to that server:

```php
// Get usernames that belong to this Comet server
$stmt = $pdo->prepare("SELECT DISTINCT h.username
                       FROM tblhosting h
                       JOIN tblservergroupsrel sgr ON sgr.serverid = h.server
                       JOIN tblservergroups sg ON sg.id = sgr.groupid
                       WHERE LOWER(sg.name) LIKE ?");
$stmt->execute([$serverGroupPattern]);
```

**Environment variable:**

- `EB_HEARTBEAT_INTERVAL` — Heartbeat interval in seconds (default: 60).

**Logging:**
With `EB_WS_DEBUG=1`, the heartbeat logs:

```
Heartbeat: Comet reports 942 active connections for 663 users
Heartbeat: checked 1315 devices for 972 server users, online=896, offline=419, changed=10
```

#### Production deployment

When deploying this fix, reset device online status and restart workers:

```sql
-- Reset all devices to offline (heartbeat will correct within 60 seconds)
UPDATE comet_devices SET is_active = 0;
```

```bash
# Restart workers
sudo systemctl restart eazybackup-comet-ws@cometbackup.service eazybackup-comet-ws@obc.service

# Verify heartbeat is running
journalctl -u eazybackup-comet-ws@cometbackup.service -n 20 --no-pager | grep -i heartbeat
```

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
  ├─ bin/rollup_devices_client_daily.php ─► eb_devices_client_daily (per-client registered, online)
  ├─ bin/rollup_storage_daily.php ─► eb_storage_daily   (per-client per-user storage totals)
  └─ bin/rollup_items_daily.php   ─► eb_items_daily     (Disk Image devices, Hyper-V VMs, VMware VMs, Microsoft 365 users, Files/Folders)

## Comet WS Worker Background Worker (systemd)

- A templated unit runs one worker per Comet server (for example, eazybackup, obc).

**comet_ws_worker Systemd Operations**

**Start/stop status of workers:**
systemctl restart eazybackup-comet-ws@cometbackup eazybackup-comet-ws@obc

systemctl start [eazybackup-comet-ws@cometbackup.service](mailto:eazybackup-comet-ws@cometbackup.service)
systemctl start eazybackup-comet-ws@obc

systemctl stop [eazybackup-comet-ws@cometbackup.service](mailto:eazybackup-comet-ws@cometbackup.service)
systemctl stop eazybackup-comet-ws@obc

systemctl status eazybackup-comet-ws@cometbackup -n 50
systemctl status eazybackup-comet-ws@obc -n 50

cd /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin
php monitor_stalled_jobs.php --verbose=1 --profile=cometbackup
php monitor_stalled_jobs.php --verbose=1 --profile=obc

**Live logs:**
journalctl -u [eazybackup-comet-ws@cometbackup.service](mailto:eazybackup-comet-ws@cometbackup.service) -f

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
WorkingDirectory=/var/www/eazybackup.ca/accounts/modules/addons/eazybackup
Environment=COMET_PROFILE=%i
Environment=WHMCS_SERVER_NAME=accounts.eazybackup.ca
Environment=SERVER_NAME=accounts.eazybackup.ca
Environment=HTTP_HOST=accounts.eazybackup.ca
Environment=HTTPS=on
Environment=SERVER_PORT=443

# Optional debug toggles:

# Environment=EB_WS_DEBUG=1

# Environment=EB_DB_DEBUG=1

# NOTE (2026-02-17): direct systemd->php launch crashed with status=11/SEGV.

# Stable production launch uses runuser + clean env.

ExecStart=/bin/bash -lc 'exec runuser -u www-data -- /bin/bash -lc "cd /var/www/eazybackup.ca/accounts/modules/addons/eazybackup && exec env -i COMET_PROFILE=%i WHMCS_SERVER_NAME=accounts.eazybackup.ca SERVER_NAME=accounts.eazybackup.ca HTTP_HOST=accounts.eazybackup.ca HTTPS=on SERVER_PORT=443 HOME=/var/www PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin LANG=en_US.UTF-8 /usr/bin/php -dopcache.enable_cli=0 bin/comet_ws_worker.php"'
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
  COMET_obc_ORIGIN="https://csw.obcbackup.com"
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

**Worker Stability Incident (2026-02-17)**

- Symptom: both `eazybackup-comet-ws@obc.service` and `eazybackup-comet-ws@cometbackup.service` repeatedly exited with `status=11/SEGV`, causing missed device/addon notification emails.
- Evidence:
  - `journalctl -u eazybackup-comet-ws@obc.service -n 100 --no-pager` showed repeated `code=dumped, status=11/SEGV`.
  - `journalctl -k` showed segfaults in `php8.2`, `mbstring.so`, and `ioncube_loader_lin_8.2.so`.
  - Reproduced with `systemd-run ... /usr/bin/php ... comet_ws_worker.php`.
- Root cause class: native PHP runtime/extension instability (not WHMCS mail-template logic and not COMET origin config).
- Confirmed workaround:
  - Start worker through `runuser` with a clean environment (the `ExecStart` above).
  - Keep `-dopcache.enable_cli=0` for worker process.
- Post-change validation:
  - `systemctl show eazybackup-comet-ws@obc.service -p ActiveState -p SubState -p NRestarts`
  - `systemctl show eazybackup-comet-ws@cometbackup.service -p ActiveState -p SubState -p NRestarts`
  - Expected: `ActiveState=active`, `SubState=running`, `NRestarts=0`.

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

- Nightly sync safety net (00:30) **required once nightly**
30 0 * * * php /var/www/eazybackup.ca/accounts/crons/eazybackupSyncComet.php
- Devices daily (02:05)
5 2 * * * php /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin/rollup_devices_daily.php
- Devices client daily (02:07)
7 2 * * * php /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin/rollup_devices_client_daily.php
- Storage daily (02:08)
8 2 * * * php /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin/rollup_storage_daily.php
- Items daily (02:10)
10 2 * * * php /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin/rollup_items_daily.php
- rollup_devices_daily.php → writes eb_devices_daily using eb_devices_registry
- rollup_devices_client_daily.php → writes eb_devices_client_daily using client-scoped active services and comet_devices
- rollup_storage_daily.php → writes eb_storage_daily by aggregating active vault bytes from comet_vaults
- rollup_items_daily.php → writes eb_items_daily using comet_items.content (exact JSON paths you provided)

## Nightly Sync Cron Optimization (eazybackupSyncComet.php)

**File:** `accounts/crons/eazybackupSyncComet.php`

This cron runs once nightly as a safety net to reconcile any events missed by the WebSocket worker.

**Required schedule (production):**

```bash
30 0 * * * php /var/www/eazybackup.ca/accounts/crons/eazybackupSyncComet.php
```

**Run frequency requirement:** this script should run **once nightly**. It is not a replacement for the websocket worker; it is a reconciliation pass to keep data correct if any realtime event was missed.

**What this cron does each run (high level):**

1. Builds the active product/server-group map (excluding configured non-Comet product groups).
2. Creates reusable Comet Admin API clients per server group.
3. Pulls active dispatcher connections (`AdminDispatcherListActive`) for online state context.
4. Bulk-fetches user profiles per group (`AdminListUsersFull`) to avoid N+1 API calls.
5. Pulls jobs for the recent window (`AdminGetJobsForDateRange`, currently 14 days) and upserts into `comet_jobs`.
6. Iterates active hostings and upserts:
  - devices (`comet_devices`)
  - protected items (`comet_items`)
  - vault metadata/usage (`comet_vaults`)
7. Emits structured `logModuleCall` telemetry (start/end, timing, processed/skipped counts, vault summary).

**Operational notes:**

- Keep this as a nightly job so dashboard and reporting tables are reconciled even if websocket delivery was partial.
- Run before downstream nightly rollups so rollups consume the latest reconciled source data.
- The cron is idempotent by design (upsert-heavy), so nightly execution is safe.

### Optimization: Bulk Profile Fetching (Jan 2026)

**Problem:** The original implementation made one `AdminGetUserProfile()` API call per active WHMCS hosting record. With ~1,500 active hostings, this resulted in ~1,500 network round-trips, causing the cron to take hours to complete.

**Solution:** Replaced N individual API calls with bulk fetching using `AdminListUsersFull()`:

```php
// Before: ~1,500 API calls (one per hosting)
foreach ($hostingsArray as $hosting) {
    $userProfile = $server->AdminGetUserProfile($hosting->username);  // N+1 problem!
    // ... process
}

// After: ~3 API calls (one per server group)
foreach ($serverClients as $gid => $serverClient) {
    $allUserProfiles[$gid] = $serverClient->AdminListUsersFull();  // Bulk fetch
}
foreach ($hostingsArray as $hosting) {
    $userProfile = $allUserProfiles[$gid][$hosting->username];  // Local lookup
    // ... process
}
```

**Performance improvement:**


| Metric                      | Before | After |
| --------------------------- | ------ | ----- |
| API calls for profiles      | ~1,500 | ~3    |
| Network round-trips         | ~1,500 | ~3    |
| Estimated runtime reduction | -      | 90%+  |


**How it works:**

1. For each server group, call `AdminListUsersFull()` once to get all user profiles
2. Store results in a map: `$allUserProfiles[gid][username] => UserProfileConfig`
3. When processing hostings, look up the profile from the pre-fetched map
4. Users not found on the Comet server are silently skipped (logged in `hostings_skipped` count)

**Logging:** The cron now logs:

- `cron AdminListUsersFull` — Number of profiles fetched per server group
- `hostings_processed` / `hostings_skipped` — Final counts in completion log

**Trade-off:** The bulk response is held in memory briefly, but this is a worthwhile trade-off for the massive reduction in network calls.

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

**Dashboard Protected Items icons (client area):**  
The Comet Server web UI uses a proprietary icon font (`cometicon-*`, e.g. `cometicon-vmware`) which is not redistributable. For the eazyBackup client area dashboard, the VMware icon is taken from [Simple Icons](https://simpleicons.org/?q=vmware) (CC0-1.0) and stored as `templates/assets/images/vmware.svg` for use in the Protected Items count card.

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
  --stale-secs= (default 3600)
  --recheck-secs= (default 300) [also used as heartbeat window]
  --max-attempts= (default 3)
  --limit= (default 200)

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

- — “No-progress” window before a job is a candidate.
- Example: 3600 = 1 hour without progress/heartbeat before we act.
- EB_MON_RECHECK_SECS # Per-row backoff. After we look at a job, do not look again for at least this many seconds.
  - Example: 300 = do not re-check the same job for 5 minutes.
- EB_MON_MAX_ATTEMPTS # How many times we’ll try (cancel/abandon/re-read) before giving up for this cycle.
- EB_MON_BATCH_LIMIT # Max rows processed per profile per pass. Keeps the run “light.”
- EB_MON_VERBOSE (optional) # 1 to print extra status lines to standard error.
- EB_MON_DRY_RUN (optional) # 1 means print what we’d do; no Admin API calls, no database writes.
- The monitor_stalled_jobs.php script runs every 5 minutes, controlled by a systemd timer

**monitor_stalled_jobs Systemd (per profile)**

1. Service: /etc/systemd/system/eb-monitor-eazybackup.service
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
  ExecStart=/usr/bin/flock -n /run/eb-monitor/eazybackup.lock  
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

1. Timer: /etc/systemd/system/eb-monitor-eazybackup.timer
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
2. Service: /etc/systemd/system/eb-monitor-obc.service
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
  ExecStart=/usr/bin/flock -n /run/eb-monitor/obc.lock  
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
3. Timer: /etc/systemd/system/eb-monitor-obc.timer
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
4. Enable monitor_stalled_jobs Systemd service
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

This feature enables starting a restore from the WHMCS client area, modeled on Comet’s web UI. It supports selecting a Storage Vault, choosing a Protected Item + snapshot, picking a restore method, and running either an **All items** restore or a **Select items** restore (for Files & Folders backups).

### Files involved

- UI (client):
  - `templates/console/user-profile.tpl`
    - Contains the slide-over Manage panel and the Restore Wizard modal (`#restore-wizard`).
    - The Restore Step 1 vault selector is an Alpine.js dropdown (not a `<select>`).
    - Contains the destination filesystem browser modal (`#fs-browser`) and the snapshot browser modal (`#snap-browser`).
  - `modules/addons/eazybackup/assets/js/device-actions.js`
    - Wires the Manage panel actions and the Restore wizard steps (load items, load snapshots, method selection, scope selection, submit restore).
    - Implements destination browsing (`browseFs`) and snapshot browsing (`browseSnapshot`).
- Backend (AJAX endpoint):
  - `pages/console/device-actions.php`
    - Actions: `listProtectedItems`, `vaultSnapshots`, `browseFs`, `browseSnapshot`, `runRestore`.
  - Router: `eazybackup.php` → `?a=device-actions`.

### Data sources & flow

1. Device context:
  - The Restore wizard acts on the device selected in the Manage slide-over. Dispatcher actions require the device to be online.
  - DeviceID → TargetID mapping is resolved via `AdminDispatcherListActive($username)`; `TargetID` is required for dispatcher APIs.
2. Step 1 – Select Storage Vault (Alpine dropdown):
  - Vault list comes from `$vaults` already provided to `user-profile.tpl`.
  - Selecting a vault stores the GUID and advances to Step 2.
3. Step 2 – Protected Item & Snapshot:
  - Protected items are loaded from the user profile (`AdminGetUserProfileAndHash`) and filtered by `OwnerDevice == current DeviceID`.
  - Snapshots are read via `AdminDispatcherRequestVaultSnapshots(TargetID, VaultId)` and filtered client-side by the selected Protected Item (Source GUID).
4. Step 3 – Method selection and options (initial coverage):
  - Files & Folders (`engine1/file`):
    - Methods: Files and Folders; Compressed archive; Simulate restore only.
  - Disk Image (`engine1/windisk`):
    - Method: Files and Folders (restore disk image files). More modes to be added.
  - Microsoft Hyper‑V (`engine1/hyperv`):
    - Method: Files and Folders (restore VM files). Hypervisor/VM-targeted restore to be added.
  - Common options scaffolded:
    - Destination path (required for all methods except Simulate)
    - Overwrite policy (`none | ifNewer | ifDifferent | always`)
  - Destination path can be entered manually or selected via the destination filesystem browser modal (`browseFs`).
5. Step 4 – Restore scope:
  - All items (default): restore everything from the chosen snapshot.
  - Select items (Files & Folders only): browse the snapshot contents and select specific files/folders to restore.
    - Snapshot browsing is lazy-loaded via `AdminDispatcherRequestStoredObjects(TargetID, VaultId, SnapshotId, TreeId)`
    - Folder entries provide a `Subtree` token which is used as `TreeId` to browse deeper.
6. Submit:
  - `runRestore` builds `RestoreJobAdvancedOptions` based on selection and calls `AdminDispatcherRunRestoreCustom(TargetID, ...)`.
  - If scope is **Select items**, the UI passes `paths: string[]` and the backend forwards it to Comet as the `Paths` parameter.
  - If `paths` is omitted/empty, Comet restores the full snapshot (all items).

### API methods used

- Online mapping:
  - `AdminDispatcherListActive(UserNameFilter)` → `TargetID` (`LiveUserConnection`)
- Enumerate:
  - `AdminGetUserProfileAndHash(Username)` → `Profile.Sources` (Protected Items)
  - `AdminDispatcherRequestVaultSnapshots(TargetID, DestinationGUID)` → `DispatcherVaultSnapshotsResponse`
- Browse:
  - Destination filesystem browser: `AdminDispatcherRequestFilesystemObjects(TargetID, Path)`
  - Snapshot browser (stored objects): `AdminDispatcherRequestStoredObjects(TargetID, DestinationGUID, SnapshotID, TreeID)`
- Execute:
  - `AdminDispatcherRunRestoreCustom(TargetID, ...)` with `RestoreJobAdvancedOptions` and optional `Paths[]`

### UX details

- The Restore wizard opens from the Manage panel’s “Restore…” button.
- Step 1 uses an Alpine.js dropdown for vault selection. The label updates to the chosen vault and the wizard moves to Step 2.
- Step 2 shows a two-pane list: Protected Items and Snapshots.
- Step 3 selects restore method and destination settings.
- Step 4 selects scope (All vs Select items) and shows the snapshot browser when needed.
- Toasts (global `#toast-container`) provide success/error feedback for all actions.
- Robustness: If the template markup is ever missing, the JS includes a fallback minimal wizard to keep the feature usable (primarily for development).

### Current status

- Completed:
  - Modal markup integrated into `user-profile.tpl`.
  - Alpine-based vault dropdown in Step 1 with de-duplicated entries.
  - Load and filter Protected Items (by device) and list snapshots per item.
  - Engine-aware method options (initial set) and restore submission.
  - TargetID mapping and online checks for dispatcher calls.
  - Destination filesystem browser for destination path selection (`browseFs`).
  - Restore scope step (All items vs Select items).
  - Snapshot browser for selecting items in a Files & Folders snapshot (`browseSnapshot`).
  - Destination path validation (required except Simulate).
- Remaining work (planned):
  - Engine-specific advanced options:
    - `engine1/hyperv`: direct restore to Hyper‑V (VM selection, storage path for VHDs).
    - `engine1/windisk`: physical restore mapper (source partitions → destination disks/partitions UI).
  - Persist and load recent choices in-session to streamline repeated restores.
  - Progress/telemetry surfacing (restore job listing and status after submission).
  - Optional UX upgrade: expand/collapse tree view for snapshot browser (instead of directory navigation).

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

## Developer Notes – Issues Summary Strip (Dashboard → Backup Status)

Overview

- A compact “status toolbar” shown above the device list on the Dashboard → Backup Status view.
- Purpose: quick at-a-glance operational summary for the last 24 hours, and fast filtering of the device list.

UI + Interaction

- Chips for: `Error`, `Missed`, `Warning`, `Timeout`, `Cancelled`, `Running`, `Skipped`, `Success`.
- Chips are multi-select (OR semantics). Clicking an active chip toggles it off.
- “Issues only” toggle filters to: `Error`, `Missed`, `Warning`, `Timeout`, `Cancelled` (Skipped is excluded from issues-only semantics).
- “Clear” resets chip selections and issues-only mode.
- Zero-count chips are muted/disabled and show a tooltip (“No devices currently in this state”).

Status semantics (Last 24 hours)

- Each device is assigned a **single** status for counting/filtering: the **most recent** job event in the last 24 hours.
  - “Most recent” is chosen by `ended_at` when present, otherwise `started_at`.
  - Running jobs are included live via the dashboard timeline store (`__EB_TIMELINE`) and count as `Running`.

Counts behavior

- Counts reflect the current scope (last 24h) and the current device search query, so the strip stays truthful when the list is narrowed by search.

Implementation notes

- Implemented in `templates/clientarea/dashboard.tpl` by extending the existing Alpine `deviceFilter()` component:
  - state: `selectedStatuses[]`, `issuesOnly`, `countsCache`
  - derived: per-device `latestStatus24h(device)` (includes `__EB_TIMELINE` running jobs)
  - reactivity: listens to `eb:timeline-changed` to update when live running jobs start/end

## Developer Notes – Dashboard Usage Overview Cards (Dashboard → Top Cards)

Overview

- The legacy top summary cards were replaced with chart-enabled usage cards:
  - Protected Items
  - Devices
  - Storage
  - Last 24 Hours (Status)
- Scope is aggregated across all active services/usernames for the logged-in WHMCS client.

Backend route + endpoint

- New JSON endpoint: `?m=eazybackup&a=dashboard-usage-metrics`
- Router wiring: `eazybackup.php`
- Controller: `pages/console/dashboard_usage_metrics.php`
- Endpoint payload:
  - `devices30d`: daily points with `d`, `registered`, `online`
  - `storage30d`: daily points with `d`, `bytes_total`
  - `status24h`: strict 5 buckets (`success`, `error`, `warning`, `missed`, `running`)

Frontend files

- Template: `templates/clientarea/dashboard.tpl`
  - New card markup and chart mount points:
    - `#eb-devices-chart`
    - `#eb-storage-chart-dashboard`
    - `#eb-status24h-donut`
  - Legend container: `#eb-status24h-legend`
- Script: `templates/assets/js/dashboard-usage-cards.js`
  - Fetches endpoint and hydrates charts/legend
  - Handles loading, empty, and error states
- Library: ApexCharts loaded from CDN in the dashboard template.

Server-rendered headline metrics

- Dashboard action (`a=dashboard`) now also passes:
  - `onlineDevices`, `offlineDevices`
  - `protectedItemEngineCounts` for required engines:
    - Files and Folders
    - Disk Image
    - Microsoft Hyper-V
    - Microsoft SQL Server
    - Microsoft Office 365
    - Windows Server System State
    - Proxmox

Devices trend data source

- New table: `eb_devices_client_daily` (per-client daily trend snapshots).
- New rollup job: `bin/rollup_devices_client_daily.php`
- Purpose: accurate client-scoped device growth history for the Devices card line chart.

Storage trend data source

- Existing table: `eb_storage_daily` (per-client, per-user daily storage snapshots).
- Rollup job: `bin/rollup_storage_daily.php`
- Purpose: historical storage totals for the Storage card line chart.

## Developer Notes – Device Grouping (Phase 1) (Dashboard → Backup Status)

### Overview

- **Goal**: Help MSPs organize large fleets (50+ devices) into manual Client/Company groups for faster triage and operational clarity.
- **Rule**: **Single group per device** (Option A). Each device can belong to exactly one group at a time.
- **Ungrouped**: Devices without a group assignment appear under a system-managed "Ungrouped" section (not a real group object in the database).
- **Scope**: Groups are **per WHMCS client account** (`tblclients.id`) across all active services/usernames shown on the dashboard.

### Files Involved

**Backend:**

- `eazybackup.php` — Main module file; routes `?a=device-groups` to the controller; includes schema creation in `eazybackup_activate()` and `eazybackup_migrate_schema()`.
- `pages/console/device_groups.php` — JSON API controller for all group management actions. Includes `ebdg_ensure_schema()` for on-demand table creation.

**Frontend (Templates):**

- `templates/clientarea/dashboard.tpl` — Main dashboard template; contains:
  - Toolbar with "Group by" selector, "Manage Groups" button, and "Select" bulk-mode toggle.
  - Grouped device list rendering (collapsible headers, inline group pills, bulk action bar).
  - Alpine.js `deviceFilter()` extension for grouping state and interactions.
- `templates/clientarea/partials/device-groups-drawer.tpl` — Slide-over drawer for "Manage Groups" UI (create, rename, delete, reorder groups).

**Frontend (JavaScript):**

- `assets/js/device-groups.js` — Alpine store `ebDeviceGroups` managing:
  - Groups list and device-to-group assignments
  - Drawer open/close state
  - Create, rename, delete, reorder flows
  - Bulk selection state
  - Collapsed groups (persisted to `localStorage`)
  - Drag-and-drop state
  - API calls with optimistic UI updates and error handling
- `assets/js/ui.js` — Shared helpers: `window.showToast()`, `window.ebShowLoader()`, `window.ebHideLoader()`.

### Database Schema

**Table: `eb_device_groups`**

- Client-scoped group definitions with ordering support.

```sql
CREATE TABLE IF NOT EXISTS eb_device_groups (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id    INT UNSIGNED NOT NULL,
  name         VARCHAR(100) NOT NULL,
  name_norm    VARCHAR(100) NOT NULL,  -- lowercase for uniqueness check
  color        VARCHAR(20) DEFAULT NULL,
  icon         VARCHAR(50) DEFAULT NULL,
  sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY idx_client_name (client_id, name_norm),
  KEY idx_client_order (client_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Table: `eb_device_group_assignments`**

- Single assignment per device; deleting the row = Ungrouped.

```sql
CREATE TABLE IF NOT EXISTS eb_device_group_assignments (
  client_id    INT UNSIGNED NOT NULL,
  device_id    VARCHAR(128) NOT NULL,  -- matches comet_devices.id
  group_id     INT UNSIGNED NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (client_id, device_id),
  KEY idx_group (group_id),
  CONSTRAINT fk_ebdga_group FOREIGN KEY (group_id)
    REFERENCES eb_device_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Schema creation:**

- Tables are created during module activation (`eazybackup_activate()`).
- On-demand creation via `ebdg_ensure_schema()` in `device_groups.php` ensures tables exist even if activation was skipped (e.g., dev environments).

### API Endpoint

**Route:** `?m=eazybackup&a=device-groups` (client-area, JSON POST)

**Controller:** `pages/console/device_groups.php`

**Actions:**


| Action          | Payload                           | Response                                                  | Description                                                                               |
| --------------- | --------------------------------- | --------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| `list`          | —                                 | `{ groups: [...], assignments: { device_id: group_id } }` | Fetch all groups and current assignments for the client.                                  |
| `createGroup`   | `{ name, color?, icon? }`         | `{ status, group }`                                       | Create a new group. Name must be unique (case-insensitive).                               |
| `renameGroup`   | `{ id, name }`                    | `{ status }`                                              | Rename an existing group.                                                                 |
| `deleteGroup`   | `{ id }`                          | `{ status }`                                              | Delete a group. Devices in this group become Ungrouped (assignments deleted).             |
| `reorderGroups` | `{ order: [id1, id2, ...] }`      | `{ status }`                                              | Update `sort_order` for all groups based on the provided ID array.                        |
| `assignDevice`  | `{ device_id, group_id }`         | `{ status }`                                              | Assign a device to a group. If `group_id` is `null` or `0`, the device becomes Ungrouped. |
| `bulkAssign`    | `{ device_ids: [...], group_id }` | `{ status }`                                              | Bulk assign multiple devices to a group (or Ungrouped if `group_id` is `null`).           |


**Security:** All actions verify the logged-in WHMCS client and scope queries to `client_id`.

### Dashboard UX

**Toolbar Additions:**

1. **"Group by" selector** — Toggle between:
  - `None` (flat device list, current behavior)
  - `Client/Company Groups` (grouped view with collapsible sections)
  - Selection is persisted in `localStorage`.
2. **"Manage Groups" button** — Opens the slide-over drawer for group management.
3. **"Select" toggle button** — Enables bulk selection mode with checkboxes on device rows.

**Grouped View:**

- **Collapsible group headers** showing:
  - Group name (or "Ungrouped")
  - Device count in parentheses
  - Mini issue badges (Error/Missed/Warning counts based on each device's latest 24h status)
- **Collapse state** is persisted per user in `localStorage`.
- **Empty groups** are shown when no filters are active to support drag-to-empty-group workflows.
- **Drag-and-drop** assignment: drag a device row onto a group header to reassign.

**Device Assignment Workflows:**

1. **Inline assignment (per device row):**
  - Each device row shows a small group pill displaying the current group name or "Ungrouped".
  - Clicking the pill opens a popover with:
    - Search input for filtering groups
    - List of existing groups
    - "Move to Ungrouped" option
    - "+ Create new group" quick action
  - Selecting a group immediately reassigns the device (optimistic UI).
2. **Bulk assignment (multi-select):**
  - Enable via the "Select" toolbar button.
  - Checkboxes appear on device rows.
  - When devices are selected, a sticky bulk action bar appears with:
    - "Assign to Group…" button (opens group selector)
    - "Move to Ungrouped" button
    - "Clear selection" button
  - Bulk operations update all selected devices in one API call.
3. **Drag-and-drop assignment (grouped view only):**
  - Devices can be dragged from one group section to another group header.
  - Drop targets highlight on hover (ring indicator).
  - Dropping on "Ungrouped" removes the group assignment.
  - Toast notification confirms the move.

**Filtering Integration:**

- Existing search and status filters work in grouped mode.
- Filters apply across all groups; empty groups are hidden when filters are active.
- The Issues Summary Strip counts reflect the current filter state.

### Manage Groups Slide-Over Drawer

**Entry point:** "Manage Groups" button in the toolbar.

**Layout:**

- **Header:** Title, subtitle, help tooltip, close button.
- **Primary actions:** "New Group" button, optional search input.
- **Group list:** Scrollable list of groups with:
  - Drag handle for reordering
  - Group name (double-click or kebab menu to rename)
  - Device count
  - Kebab menu (Rename, Delete)
- **Footer:** Note about Ungrouped devices.

**Interactions:**

1. **Create group:**
  - Click "New Group" → inline form appears.
  - Enter name (required, unique case-insensitive).
  - Press Enter or click "Create" to save.
  - Toast: "Group created"
2. **Rename group:**
  - Trigger via kebab menu → "Rename" or double-click the name.
  - Inline input replaces the name text.
  - Enter to save, Escape to cancel.
3. **Delete group:**
  - Trigger via kebab menu → "Delete".
  - Confirmation modal appears (inside drawer).
  - States consequence: "Devices in this group will be moved to Ungrouped."
  - If group has devices, shows count warning.
  - Toast: "Group deleted. X devices moved to Ungrouped."
4. **Reorder groups:**
  - Drag handle on each row.
  - Drag-and-drop to reorder.
  - Order is persisted via `reorderGroups` API call.

### Alpine.js State (Store: `ebDeviceGroups`)

```javascript
Alpine.store('ebDeviceGroups', {
  // Data
  groups: [],                    // Array of group objects
  assignments: {},               // { device_id: group_id }
  
  // Drawer state
  drawerOpen: false,
  loading: false,
  search: '',
  
  // Create flow
  creating: false,
  newName: '',
  savingCreate: false,
  
  // Rename flow
  renameId: null,
  renameValue: '',
  savingRename: false,
  
  // Delete flow
  deleteId: null,
  deleteName: '',
  deleteCount: 0,
  deleting: false,
  
  // Reorder
  dragId: null,
  
  // Selection (for bulk assignment popover)
  activePopover: null,
  
  // Collapsed groups (persisted)
  collapsedGroups: {},
  
  // Methods (API calls with optimistic updates)
  openDrawer(), closeDrawer(),
  list(), createGroup(), renameGroup(), deleteGroup(),
  reorderGroups(), assignDevice(), bulkAssign(),
  // ... helpers
});
```

### Responsive Design

**Mobile (< 640px):**

- Toolbar buttons show icons only; text labels hidden.
- 13-day historical status dots hidden.
- Full-width search input on separate row.

**Tablet/Desktop (≥ 640px):**

- Full button labels shown.
- Two-column device row layout.
- All status indicators visible.

### Error Handling & Loading States

- **Optimistic UI:** Assignments update immediately; revert on API failure with error toast.
- **Loading states:** Skeleton rows in drawer while fetching groups.
- **Error toasts:** "Couldn't rename group. Please try again." etc.
- **Form validation:** Name required, uniqueness checked client-side and server-side.

### localStorage Keys

- `eb_groupMode` — Current group-by selection (`none` | `groups`)
- `eb_collapsedGroups` — JSON object of collapsed group states
- `eb_bulkSelectMode` — Whether bulk selection is enabled

---

## Device Grouping – Phase 2 Roadmap (INCOMPLETE)

> **⚠️ STATUS: NOT IMPLEMENTED**
> The features listed below are planned enhancements for Phase 2 of the Device Grouping feature. They are **not yet built** and require future development work. This section serves as a roadmap and specification for upcoming iterations.

### 1. Automatic / Smart Grouping

Phase 1 is explicitly "manual groups only." Phase 2 should introduce automatic grouping capabilities:

- **Auto-grouping rules engine**
  - Create rules based on device metadata: hostname patterns (regex), domain membership, OS type, platform architecture
  - Example: "Devices matching `^ACME-.`* → assign to group 'ACME Corp'"
  - Rules evaluated on device registration or on-demand "Re-evaluate all"
- **Suggested groupings**
  - Analyze device naming conventions and suggest potential groups
  - "We found 12 devices starting with 'CLINIC-'. Create a group?"
- **Import groups from external sources**
  - CSV import: columns for device identifier + group name
  - Active Directory / LDAP integration (future consideration)
  - RMM tool integration (ConnectWise, Datto, etc.)

### 2. Group Visual Customization

The database schema already includes `color` and `icon` columns that are **not yet wired up** in the UI:

- **Group color chips**
  - Preset color palette (8-12 colors) for quick selection
  - Color displayed as a small dot/badge next to group name in headers and pills
  - Helps visual differentiation in large group lists
- **Group icons**
  - Small icon set (building, briefcase, hospital, school, server, etc.)
  - Optional icon displayed in group headers
  - Consider using Heroicons or similar lightweight icon set

**Database columns ready (unused):**

```sql
color  VARCHAR(20) DEFAULT NULL,  -- e.g., 'emerald', 'rose', 'amber'
icon   VARCHAR(50) DEFAULT NULL,  -- e.g., 'building', 'briefcase'
```

### 3. Enhanced Sorting & Filtering

Features mentioned in original spec but not yet implemented:

- **Global sort options for device list**
  - Sort by: Name (A-Z / Z-A)
  - Sort by: Worst recent status (Error → Missed → Warning → Success)
  - Sort by: Most overdue (longest time since last successful backup)
  - Sort by: Last backup time (newest / oldest)
- **Per-group sorting**
  - Override global sort within specific groups
  - Sticky sort preference per group (persisted)
- **Advanced filtering**
  - Filter by group (show only devices in selected groups)
  - Combine group filter with status filter
  - "Show all groups with issues" quick filter

### 4. Bulk Operations & Keyboard Shortcuts

- **Range selection with Shift+Click**
  - Select first device, Shift+Click last device → select all in between
  - Works within a group or across groups
- **Keyboard navigation**
  - Arrow keys to navigate device list
  - `Space` to toggle device selection
  - `G` to open group assignment popover for selected device(s)
  - `Escape` to clear selection / close popovers
  - `/` to focus search input
- **Bulk actions expansion**
  - Bulk delete devices (with confirmation)
  - Bulk run backup on selected devices
  - Bulk export device list (CSV)

### 5. Group-Level Features

- **Group-level notifications**
  - Configure email alerts per group (e.g., "Alert me if any device in 'Critical Servers' has an error")
  - Aggregate daily/weekly digest per group
- **Group-level reports**
  - Export backup status report filtered by group
  - PDF/CSV export with group summary statistics
  - Scheduled report delivery
- **Group health dashboard**
  - Mini dashboard per group showing:
    - Device count, online/offline split
    - Last 24h success rate
    - Storage usage breakdown
    - Trend chart (last 7/30 days)

### 6. Nested Groups / Hierarchy

For larger organizations with complex structures:

- **Sub-groups (2-level hierarchy)**
  - Parent group → Child groups
  - Example: "Healthcare" → "Clinic A", "Clinic B", "Hospital Main"
  - Collapsible parent groups that expand to show children
- **Group inheritance**
  - Settings/notifications cascade from parent to children unless overridden

**Note:** This adds significant complexity; consider as Phase 3 if needed.

### 7. Partner Hub Integration

When Partner Hub is active, connect Device Grouping with Customer management:

- **Customer ↔ Group auto-mapping**
  - Automatically create a group for each Customer
  - Devices linked to a Customer's Comet accounts are auto-assigned to their group
- **Customer-scoped group views**
  - Customer portal users see only their group(s)
  - MSP sees all groups with Customer attribution
- **Billing integration**
  - Report usage/billing by group
  - Group-based pricing tiers (future)

### 8. List Density Options

- **Compact mode**
  - Reduce vertical padding on device rows
  - Hide some metadata (platform, registration date)
  - Useful for accounts with 100+ devices
- **Comfortable mode (current)**
  - Full device row with all information
- **Density toggle in toolbar**
  - Persisted preference in `localStorage`

### 9. Drag-and-Drop Enhancements

- **Multi-device drag**
  - When multiple devices are selected, drag them all at once
  - Visual indicator showing count being dragged ("Moving 5 devices...")
- **Reorder devices within a group**
  - Custom device ordering per group (optional)
  - Drag handle on device rows when enabled
- **Touch support improvements**
  - Long-press to initiate drag on mobile/tablet
  - Larger drop targets for touch accuracy

### 10. Data Export & Sync

- **Export group configuration**
  - Export groups + assignments as JSON/CSV
  - Useful for backup or migration
- **Import group configuration**
  - Restore groups from exported file
  - Merge or replace existing groups
- **Sync with external systems**
  - Webhook notifications on group changes
  - API for external systems to manage groups programmatically

---

### Implementation Priority (Suggested)


| Priority | Feature                     | Effort | Impact |
| -------- | --------------------------- | ------ | ------ |
| P1       | Group colors & icons        | Low    | Medium |
| P1       | Global sort options         | Low    | High   |
| P1       | Shift+Click range selection | Low    | Medium |
| P2       | Auto-grouping rules         | Medium | High   |
| P2       | Group-level reports         | Medium | High   |
| P2       | Keyboard shortcuts          | Low    | Medium |
| P2       | Compact density mode        | Low    | Medium |
| P3       | Partner Hub integration     | High   | High   |
| P3       | Nested groups               | High   | Medium |
| P3       | Import/Export               | Medium | Low    |


---

## Trial Signup & Email Verification (eazyBackup)

Overview

- The public trial signup (`a=signup`, template `templates/trialsignup.tpl`) now uses an email verification step before provisioning.
- On submit, a WHMCS Client is created and a verification email is sent. No order or service is created yet.
- Clicking the verification link provisions the trial order, accepts it, and SSO‑redirects the user to set their Client Area password.

Admin Setting

- Addon setting: `trial_verification_email_template` (General templates)
  - Path: Addon configuration in WHMCS admin
  - Loader: `eazybackup_EmailTemplatesLoader()` (General)
  - The chosen template should include the merge field `{$trial_verification_link}`.

Merge Field

- `{$trial_verification_link}` — verification URL for the user to click.

Database

- Table: `eazybackup_trial_verifications`
  - Columns: `id`, `client_id`, `email`, `token` (unique), `meta` (JSON), `created_at`, `expires_at`, `consumed_at`
  - `meta` includes: `username`, `productId`, and `phone`
  - Token expiry: 48 hours

Routes & Files

- Signup handler: `eazybackup_signup($vars)` in `eazybackup.php`
  - Validates Turnstile, username, email, and password strength
  - Creates WHMCS client with a randomly generated password
  - Stores verification token into `eazybackup_trial_verifications`
  - Sends verification email via `SendEmail` using the selected General template with `customvars = ['trial_verification_link' => <url>]`
  - Re-renders `templates/trialsignup.tpl` with `emailSent => true` and the submitted email for confirmation
- Verification route: `a=verifytrial` in `eazybackup_clientarea()` (`eazybackup.php`)
  - Validates token (exists, not expired, not consumed)
  - Creates the order (`AddOrder` with promo `trial`, `noinvoice`)
  - Accepts the order (`AcceptOrder`) with `serviceusername = meta.username` and a generated strong password
  - Sets a 14‑day trial window on the service (`UpdateClientProduct`)
  - Optional OBC/MS365 step: `EazybackupObcMs365::provisionLXDContainer(...)` when `productId == '52'`
  - Marks token as consumed
  - Creates SSO token and redirects to `clientarea.php?action=changepw`

Template Behavior

- `templates/trialsignup.tpl`:
  - Shows the full form by default
  - When `{$emailSent}` is true, hides the form and shows a confirmation panel:
    - “Please check your email” with the submitted address
    - Guidance to look in spam/junk; link remains valid until expiry

Testing Checklist

- Missing or invalid Turnstile: inline error
- Invalid username/email/password: inline errors; form re-renders preserving inputs
- Valid submit:
  - Client is created
  - Verification email is sent using the configured template
  - The form is replaced by the “Please check your email” state
- Verification link:
  - Valid token → order created/accepted, service username set, SSO to password change page
  - Invalid/expired token → render signup page with an appropriate error

## Configurable Options Discount Management (Admin – Clients Services)

This feature lets admins set per‑config‑option Discount Price values for a service and reliably recalculate the service’s Recurring Amount using those discounts. It avoids WHMCS’s native “Recalculate on Save” behavior and uses our own calculation/commit flow.

### Files involved

- Hooks (admin UI and AJAX):
  - `accounts/includes/hooks/recurringDiscount_ConfigOptions.php`
    - Injects Discount Price fields beside each configurable option on the admin `clientsservices` page.
    - Adds an actions row under the Recurring Amount row with two buttons:
      - “Recalculate (discounts)” — preview using current form selections + discounts
      - “Save with discounts” — save discounts, recalc, and commit the Recurring Amount
    - Disables the native “Recalculate on Save” switch to prevent WHMCS from overriding our computed amount.
  - `accounts/includes/hooks/configOptionsDiscount_ajax.php`
    - JSON endpoint for discount operations and calculations (see Endpoints below).
  - `accounts/includes/hooks/productRecurringDiscount.php` (existing)
    - Separate, older “global percent” discount UI; unrelated to per‑config‑option Discount Price.

### Database

- `mod_rd_discountConfigOptions`
  - Stores Discount Price per service/config option.
  - Columns: `id`, `serviceid`, `configoptionid`, `discount_price`, `price`, `created_at`, `updated_at`
  - Unique index: `(serviceid, configoptionid)` ensures idempotent saves.
  - Created/ensured by addon activation (`eazybackup_activate()` in `eazybackup.php`).

### Admin UI (buttons and fields)

- Per‑option “Discount Price” field:
  - One input per configurable option.
  - Discount values are read directly from these inputs when you click “Recalculate (discounts)” or “Save with discounts”.
  - “Remove” deletes the saved discount for that option (and clears the badge).
- Actions row (inserted under the Recurring Amount row):
  - `Recalculate (discounts)`:
    - Collects all visible Discount Price inputs and saves them to `mod_rd_discountConfigOptions` for this service.
    - Computes the Recurring Amount using **current page selections and quantities** (live form state) together with the saved discounts; updates the Amount field (preview only).
  - `Save with discounts`:
    - Same as `Recalculate (discounts)` (saves all discounts and recalculates using the live form), and then:
    - Commits the calculated amount directly to `tblhosting.amount`, then reloads the page with `success=true`. This bypasses WHMCS’ native recalc/save pipeline.

### Endpoints (JSON)

All endpoints live at `accounts/includes/hooks/configOptionsDiscount_ajax.php` and return `{ status, data, message }`.

- `apply_config_discount` (POST)
  - Body: `service_id`, `configoptionid`, `discount_price`
  - Upserts a single discount for the service/option.
  - Primarily used for legacy/one‑off flows; the modern UI prefers `save_discounts` driven by the actions row buttons.
- `remove_config_discount` (POST)
  - Body: `service_id`, `configoptionid`
  - Removes the saved discount for the service/option.
- `save_discounts` (POST)
  - Body: `service_id`, `discounts[]` where each item: `{ configoptionid, discount_price }`
  - Batch upsert of many per‑option discounts; called by both “Recalculate (discounts)” and “Save with discounts” with the current Discount Price inputs.
- `recalc_service_amount` (POST) and alias `calculate_amount`
  - Body: `service_id`
  - Optional: `options` (JSON array) describing live page selections. Each item may include:
    - `{ configid, subId }` for select/radio options
    - `{ configid, qty }` for quantity options
  - Response: `{ data: { amount, meta } }`
    - `meta.lines` provides a per‑option breakdown: `{ configId, type, qty|subId, discountApplied, listUnit|listSelected, contribution }`
- `commit_amount` (POST; admin‑only)
  - Body: `service_id`, `amount`
  - Directly updates `tblhosting.amount` for the service and returns `{ status:true }` on success.

### Calculation rules

- Base product recurring price is ignored (`baseRecurring = 0`); only configurable options contribute to the total.
- Quantity options (optiontype = 3):
  - Per‑unit price = saved `discount_price` for that `configid` (if present) or the WHMCS list unit price.
  - WHMCS list unit price is resolved via `tblhostingconfigoptions.optionid` → `tblpricing.relid` (type = `configoptions`, matching the service’s currency and billing cycle). This mirrors how WHMCS’s own Configurable Options UI prices quantity options.
  - Contribution = `unit × qty`.
- Select/radio options:
  - When live `options` payload is present (from the page), contribution is:
    - Saved `discount_price` (absolute) when present for that `configid`, otherwise the selected sub‑option’s list price.
  - When no `options` payload is present (DB snapshot), we include select options only when a `discount_price` exists for that `configid` (prevents counting default non‑billable selects).
- The server returns a breakdown (`meta.lines`) so admins can see exactly which options contributed and by how much.

### Typical workflows

- Set or adjust discounts and quantities in one pass:
  1. Enter Discount Price values beside each desired config option and adjust the service’s option quantities/selections on the page as needed.
  2. Click **“Recalculate (discounts)”** to save all Discount Price values, then preview the new Recurring Amount using the current form state.
  3. If the preview looks correct, click **“Save with discounts”** to save all discounts again, recompute, and commit the final amount to `tblhosting.amount`.
- One‑off update for a single option:
  1. Change the Discount Price and/or quantity for that option.
  2. Click **“Recalculate (discounts)”** to confirm the new total, then **“Save with discounts”** when satisfied.

### Notes & troubleshooting

- Native “Recalculate on Save” is disabled in the UI by the hook to avoid WHMCS overwriting the amount. Use our buttons instead.
- If totals look off, open DevTools → Network → the recalc POST and inspect `data.meta.lines` to identify which `configId` contributed.
  - For select/radio, ensure the intended “0/None” sub‑option is actually selected on the page; otherwise it will price the selected sub‑option.
  - For quantity, verify the quantity input reflects the intended value and that a per‑unit discount is saved if needed.
- The feature is admin‑only; `commit_amount` requires an admin session.

## Product Packages:

OBC plan package ID = 60
eazyBackup package ID = 58
Microsoft 365 Backup id=52
Microsoft 365 Backup (OBC) id=57
Virtual Server Backup id=53
Virtual Server Backup (OBC) id=54
OBC (NFR) pid=93
Microsoft 365 Backup (NFR) pid=95
Virtual Server Backup (NFR) pid=94
e3 Object Storage pid=48
cloud to cloud backup pid=101

## NEW Configurable Options:

Cloud Storage cid=67
Device endpoint cid=88
Disk Image cid=91
Microsoft 365 Accounts cid=60
Hyper-V Guest VM cid=97
Proxmox Guest VM cid=102
VMware Guest VM cid=99
Server endpoint cid=89

# How WHMCS Configurable Options are linked to Products in the database:

## table tblclients:

tblclients.id = the customers ID

## Products are kept in table tblhosting:

- Customers userid is mapped to tblhosting.userid
- tblhosting.id maps to tblhostingconfigoptions.relid

## Table tblhostingconfigoptions:

tblhostingconfigoptions.relid is equal to the tblhosting.id
tblhostingconfigoptions.cid is the configurable option id.
tblhostingconfigoptions.qty is the users purchased amount of the configurable option.
tblhostingconfigoptions.optionid links to the tblpricing.relid

## Table tblpricing:

tblpricing stores pricing information for the products and configurable options.
tblpricing.relid links to the tblhostingconfigoptions.optionid

## Developer Notes – Job Modal "Open Support Ticket" Handoff

### Overview

- One-click handoff from a problematic backup job (Warning / Error / Missed / Timeout / Cancelled / Running) to the WHMCS create-ticket flow.
- The customer stays inside `submitticket.php?step=2` so all WHMCS rules (department routing, captcha, custom fields, validation) still apply.
- Subject and message body are pre-filled. The full job log is auto-attached as both a human-readable `.txt` and a machine-friendly `.csv`.
- Up to 3 KB hint links from `docs.eazybackup.com` are surfaced inside the modal before the redirect (curated mappings first, GitBook live index second), so customers can self-help when applicable.
- An open-ticket dedupe check warns the customer if they already have an open ticket about the same Job ID in the last 7 days.

### High-Level Flow

```
Job Report Modal (status ∈ Warning|Error|Missed|Timeout|Cancelled|Running)
   ▼  click "Open Support Ticket"
job-ticket.js:
  - POST ?a=job-reports&action=ticketContext  → subject, body, deptId, kbHints, customFieldId, jobMeta
  - POST ?a=job-reports&action=ticketDuplicateCheck → existing ticket if any
  - Renders preview/dedupe panel inside the modal (KB links + dedupe warning)
   ▼  click "Continue to ticket"
  - Builds job-<id>.txt + job-<id>.csv from modal._ebJobLogRows (the same array used by Export CSV)
  - sessionStorage.setItem('eb_ticket_<jobId>', { subject, body, deptId, customFieldId, files:[…base64…] })
  - window.location → submitticket.php?step=2&deptid=1&subject=…&eb_job=<jobId>
   ▼
WHMCS create-ticket form loads the eazyBackup steptwo template
ticket-prefill.js:
  - Reads sessionStorage['eb_ticket_<jobId>']
  - Hydrates #inputSubject + #inputMessage (fallback if Smarty did not pre-render)
  - Reconstructs File objects from base64 and assigns them to #inputAttachments via DataTransfer
  - For the second file, calls the existing extraTicketAttachment() helper to spawn another <input type=file> slot
  - Writes a hidden customfield[<id>]=<jobId> input so the new ticket is tagged with eb_job_id
  - Reveals the green "Backup log auto-attached" banner (or amber fallback banner if it had to download)
  - sessionStorage.removeItem on success
```

### Files Involved

Backend:

- `accounts/modules/addons/eazybackup/pages/console/job-reports.php`
  - `case 'ticketContext'` — composes subject + body, resolves friendly device/vault/protected-item names, computes priority (`High` for Error, `Medium` otherwise), invokes `KbSuggester`, returns the `eb_job_id` custom-field id.
  - `case 'ticketDuplicateCheck'` — joins `tblcustomfieldsvalues` × `tbltickets` for the logged-in client, last 7 days, status != Closed, value == jobId.
- `accounts/modules/addons/eazybackup/lib/KbSuggester.php` — hybrid KB lookup (curated map first, GitBook site-index keyword scoring second).
- `accounts/modules/addons/eazybackup/data/kb-curated.json` — admin-editable starter mappings (VSS, locked file, network/UNC, quota, vault auth, antivirus, M365, Hyper-V, Disk Image).
- `accounts/modules/addons/eazybackup/cache/kb-search/` — file cache for KB suggestions and the GitBook site-index. One JSON file per signature, sha256-keyed, TTL embedded.
- `accounts/modules/addons/eazybackup/eazybackup.php` — `eazybackup_activate()` ensures the `eb_job_id` support custom field exists (Technical Support, admin-only text).
- `accounts/includes/hooks/eb_ticket_enrichment.php` — optional `TicketOpen` hook that auto-posts an admin-only ticket note with last 5 jobs / device state / vault summary when the ticket has `eb_job_id` set.

Frontend (modal side):

- `accounts/modules/addons/eazybackup/templates/console/partials/job-report-modal.tpl` — owns the toolbar (now uses an Alpine `eb-menu-trigger` for the Log entries filter) and the full-width preview/dedupe panel directly under the toolbar.
- `accounts/modules/addons/eazybackup/templates/console/partials/job-report-ticket-button.tpl` — the trigger button include (lives inside the toolbar).
- `accounts/modules/addons/eazybackup/assets/js/job-reports.js` — emits two `CustomEvent`s consumed by the new feature:
  - `eb:job-loaded` (after summary load) → `{ serviceId, username, jobId, status, job }`
  - `eb:job-logs-loaded` (after log rows load) → `{ serviceId, username, jobId, rows }`
- `accounts/modules/addons/eazybackup/templates/assets/js/job-ticket.js` — listens to those events, decides eligibility (Warning / Error / Missed / Timeout / Cancelled / Running), owns the preview/dedupe panel, builds the log files and sessionStorage payload, redirects.
- `accounts/modules/addons/eazybackup/templates/clientarea/dashboard.tpl`, `accounts/modules/addons/eazybackup/templates/console/user-profile.tpl`, and `accounts/modules/addons/eazybackup/templates/console/job-logs-global.tpl` — load `job-ticket.js` and expose `window.EB_WEB_ROOT` for the redirect.

Frontend (ticket form side):

- `accounts/templates/eazyBackup/supportticketsubmit-steptwo.tpl` — green/amber banner placeholder + privacy note (only when `?eb_job=` is present) and the `ticket-prefill.js` script tag.
- `accounts/modules/addons/eazybackup/templates/assets/js/ticket-prefill.js` — drains sessionStorage, attaches files, populates the hidden custom field, handles fallbacks.

### Database

- `tblcustomfields` — a single row added by activation:
  - `type='support'`, `relid=1` (Technical Support), `fieldname='eb_job_id'`, `fieldtype='text'`, `adminonly='on'`.
  - Used to tag tickets with their originating Job ID so the dedupe check and the optional enrichment hook can find them.
- `tblcustomfieldsvalues` — populated by WHMCS on ticket submit because the form posts `customfield[<id>]=<jobId>` (set by `ticket-prefill.js`).
- No new tables.

### Subject + Body Templates

Subject (server-composed):

```
Backup {Status}: {DeviceFriendlyName} - {ProtectedItemDescription} - {Y-m-d}
```

Examples:

- `Backup Warning: ACME-PC1 - Files and Folders - 2026-04-28`
- `Backup Error: SERVER01 - SQL Daily - 2026-04-28`

Body:

```
Hello eazyBackup Support,

I noticed a backup job on my account finished with a {Status} and would
appreciate someone taking a look. The full job log is attached.

Backup account: {Username}
Device:         {DeviceFriendlyName}
Protected Item: {ProtectedItemDescription}
Storage Vault:  {VaultDescription}
Job Type:       {FriendlyJobType}
Job Status:     {Status}
Started:        {Started}
Ended:          {Ended}
Duration:       {Duration}
Client Version: {ClientVersion}
Job ID:         {jobId}

Thanks,
{ClientFirstName}
```

### Attachments

- Built in the browser from `modal._ebJobLogRows` (the same array `Export CSV` already uses).
- `job-<id>.txt` — `YYYY-MM-DD HH:MM:SS UTC  WARNING  <message>` lines, with a header block of job metadata. Capped at ~1.5 MB; if exceeded, only Warning + Error lines are kept and a header note explains the truncation.
- `job-<id>.csv` — `UnixTime, Timestamp, Severity, Message`. Also size-capped.
- The full payload (subject + body + base64 file blobs) is stashed in `sessionStorage` under `eb_ticket_<jobId>` and capped at ~3 MB. If exceeded (or if `sessionStorage.setItem` throws a quota error), `job-ticket.js` automatically downloads both files and sets a `downloadedFallback` flag on the payload, which triggers an amber "please attach the downloaded files" banner on the ticket form.
- Browser support for populating `<input type=file>` is via `DataTransfer`. If the runtime doesn't support it (very old browsers), `ticket-prefill.js` falls back to downloading + amber banner.

### KB Suggestions (Hybrid)

- `KbSuggester::suggest($logRows, $jobType, $status)` returns up to 3 hints with shape `{ title, url, source: 'curated'|'gitbook', matchedPattern?, snippet? }`.
- Step 1 — signature extraction: the helper groups all `Severity ∈ {W, E}` messages, normalizes them (lowercase, strip Windows/POSIX/UNC paths, GUIDs, hex literals, large numeric IDs, timestamps, collapse whitespace, truncate to 200 chars) and keeps the top 3 by frequency. If the log has no warnings/errors, falls back to `<status> <jobType>` as a single seed signature.
- Step 2 — curated lookup: for each signature, walks `data/kb-curated.json` (an array of `{ name, patterns[], title, url }` objects). Patterns are PHP-compatible regex, case-insensitive. First match wins per entry.
- Step 3 — GitBook live lookup: for any signature without a curated hit, hits `https://docs.eazybackup.com/~gitbook/site-index` (a small `~33 KB` static JSON returned by the GitBook hosted site at v2). Each `{title, pathname}` page is tokenized and scored by keyword overlap with the signature. Top 3 by score (tie-break on shorter title) are returned.
  - Important: GitBook's `/~gitbook/search` endpoint returns 405 on direct GETs and is not used. The site-index endpoint is the public, reliable source we hit instead.
- File cache layout (under `accounts/modules/addons/eazybackup/cache/kb-search/`):
  - One JSON per cache key; `{ written_at, expires_at, data }`.
  - Per-signature: 6h TTL on hits, 30 min TTL on empty results so a temporary GitBook hiccup doesn't stick.
  - Site-index entry: 1h TTL.
  - All keys include the cache-version suffix `EB_KB_CACHE_VER` so the entire cache can be busted by bumping it.
- Circuit breaker: 3 GitBook failures within a 10-minute window flip a flag that suppresses live calls for 1 hour. Curated lookups continue to work in suppressed mode.

### Configuration (`.env`)

```
# All optional; defaults shown
EB_KB_GITBOOK_URL=https://docs.eazybackup.com    # base URL for the site-index fetch
EB_KB_DISABLE=0                                  # 1 = curated only, no live calls
EB_KB_CACHE_TTL=21600                            # per-signature hit TTL (seconds)
EB_KB_CACHE_VER=1                                # bump to invalidate all cached results
EB_TICKET_ENRICHMENT=0                           # 1 = enable the TicketOpen enrichment hook
```

### Eligibility Rules

- The "Open Support Ticket" button only renders for status `Warning`, `Error`, `Missed`, `Timeout`, `Cancelled`, or `Running` (status is normalized via `EB.humanStatus()`).
- It is hidden for `Success`, `Running`, `Skipped`, `Cancelled`, `Unknown`.
- Clicking the button hides the trigger and reveals a full-width preview panel directly under the modal toolbar. The Cancel button (or any modal close action) restores the trigger button.

### Self-Help / Dedupe Panel

The panel rendered before the redirect contains:

- The composed subject (read-only preview).
- Up to 3 KB hint links, each labeled `Curated` (emerald) or `docs.eazybackup.com` (slate) so customers can tell apart human-curated and live-index suggestions.
- If `ticketDuplicateCheck` returned an existing open ticket for the same Job ID in the last 7 days, an amber banner with a deep link to `viewticket.php?tid=<tid>`.
- A primary "Continue to ticket" button and a secondary Cancel.

### Security / Scoping

- `ticketContext` and `ticketDuplicateCheck` live inside the existing `pages/console/job-reports.php` switch which already verifies `tblhosting.userid == current uid`. No new attack surface.
- The dedupe SQL joins `tblcustomfieldsvalues` × `tbltickets` and filters by `t.userid = $clientId`, so a customer cannot discover other customers' tickets.
- `ticket-prefill.js` only reads `sessionStorage` keys it created (`eb_ticket_*`) and removes them after a successful or fallback attach.
- Privacy disclosure shown on the ticket form: "The attached log may contain file and folder names from the affected device. Remove any details you do not want to share before submitting."

### Optional: TicketOpen Enrichment Hook

`accounts/includes/hooks/eb_ticket_enrichment.php` is shipped disabled. When enabled (set `EB_TICKET_ENRICHMENT=1` or define `EB_TICKET_ENRICHMENT_ENABLED`), it fires on `TicketOpen` and, for any ticket that has the `eb_job_id` custom field populated, posts an admin-only ticket note via `localAPI('AddTicketNote')` containing:

- Service id + product + status
- Last 5 entries from `eb_jobs_recent_24h` for that username (and device when known)
- Device row from `comet_devices` (online state, OS, last update)
- Vault summary from `comet_vaults` (count + total GiB + per-vault size)

### UI Conventions Used

- Modal: `eb-modal`, `eb-modal-backdrop`, `eb-modal-header`, `eb-modal-title`, `eb-modal-body`.
- Buttons: `eb-btn`, `eb-btn-primary`, `eb-btn-outline`, `eb-btn-xs`.
- Log entries filter (Alpine): `eb-menu-trigger` + `eb-dropdown-menu` + `eb-menu-option` with `is-active` for the selected option (per `Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md` §10). A hidden `<select id="jrm-filter">` shim is preserved so the existing `applyFilter()` listener in `assets/js/job-reports.js` keeps working without modification.
- KB hint badges: `bg-emerald-500/15 text-emerald-300` for curated, `bg-slate-500/20 text-slate-300` for GitBook.

### Test Plan

- Visibility: open jobs of each status from the dashboard timeline pop-overs; confirm the trigger button only shows for Warning / Error / Missed / Timeout / Cancelled / Running.
- Curated path: open a Warning job containing a VSS line; expect the curated VSS article with the "Curated" label.
- Live path: open a job with a novel error; expect 1-3 GitBook links, sourced from the site-index.
- Cache: open the same job twice in quick succession; verify a `<sha256>.json` appears under `cache/kb-search/`.
- Kill switch: set `EB_KB_DISABLE=1`, re-open the job; expect curated-only output and zero outbound calls.
- Dedupe: submit a ticket from a job, then re-open the same job within 7 days; expect the amber dedupe banner with a working link.
- Attachment success: submit the ticket end-to-end and verify both `job-<id>.txt` and `job-<id>.csv` appear on the admin ticket page; the `eb_job_id` custom field shows the Job ID.
- Attachment fallback: in DevTools set `window.DataTransfer = undefined` before clicking Continue; expect the browser to download both files and the form to show the amber "please attach" banner.
- Hide-on-open UX: click "Open Support Ticket" — trigger button disappears, panel renders below toolbar. Click Cancel — panel disappears, trigger reappears (still eligible).
- Optional enrichment: set `EB_TICKET_ENRICHMENT=1`, open a ticket from a job; verify an admin-only note appears on the new ticket with the context block.

## Developer Notes – Client Area Notifications ("What's New")

### Overview

- Admin-managed announcements that appear as a single consolidated dismissable modal on every WHMCS client area page for matching, undismissed clients.
- Authoring lives under WHMCS admin → eazyBackup Power Panel → **Notifications** tab.
- Two-state lifecycle (`draft`, `published`) with optional audience targeting (all clients, specific products, client groups, or individual client IDs).
- Per-client/per-user dismissal that persists across pages and devices.

### Where it appears

- The modal is rendered globally by `ClientAreaFooterOutput` so it shows on any client area page (dashboard, billing, services, support, etc.) until the customer dismisses each card.
- The modal title is "What's New" with a subtitle showing the count of remaining undismissed notifications. The customer can dismiss cards one at a time or close the modal entirely (open cards reappear on the next page load).

### Files Involved

Backend:

- `accounts/modules/addons/eazybackup/eazybackup.php`
  - `eazybackup_migrate_schema()` creates `eb_notifications` and `eb_notification_targets` if missing, plus a helpful index on `mod_eazybackup_dismissals`.
  - `eb_get_active_notifications_for_client(int $clientId, ?int $userId)` returns all published, undismissed notifications visible to a given client/user, applying audience filtering in PHP for OR-style matches across product / client_group / client.
- `accounts/modules/addons/eazybackup/hooks.php`
  - `ClientAreaPage` hook (priority 1): resolves the active client + user, calls `eb_get_active_notifications_for_client`, exposes `$ebActiveNotifications` and `$ebNotifCsrf` to the template scope.
  - `ClientAreaFooterOutput` hook (priority 1): includes the modal partial when `$ebActiveNotifications` is non-empty.
- `accounts/modules/addons/eazybackup/pages/admin/notifications.php` — admin UI: list / new / edit / publish / unpublish / delete. Routed via the eazyBackup Power Panel ( `addonmodules.php?module=eazybackup&action=powerpanel&view=notifications` ). Validates CSRF on POST.
- `accounts/modules/addons/eazybackup/endpoints/dismiss_notification.php` — JSON dismissal endpoint. Requires WHMCS session + valid CSRF token. Writes to `mod_eazybackup_dismissals` keyed by `notif:<id>` for both `user_id` and `client_id` so dismissals follow the user across logins.

Frontend:

- `accounts/modules/addons/eazybackup/templates/partials/_notifications_modal.phtml` — the consolidated modal markup (Alpine `ebNotifModal()` component). Styled per `Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md` §11 (`eb-modal`, `eb-modal-backdrop`, `eb-card`).

### Database

`eb_notifications`:


| column                                                      | type                      | notes                                                           |
| ----------------------------------------------------------- | ------------------------- | --------------------------------------------------------------- |
| `id`                                                        | INT AUTO_INCREMENT        | PK                                                              |
| `title`                                                     | VARCHAR(191)              |                                                                 |
| `body`                                                      | TEXT                      | plain text; `nl2br` + `htmlspecialchars` applied at render time |
| `status`                                                    | ENUM('draft','published') | only `published` rows are exposed to clients                    |
| `audience_type`                                             | ENUM('all','filtered')    |                                                                 |
| `created_by`                                                | INT                       | admin id                                                        |
| `created_at`, `updated_at`, `published_at`                  | TIMESTAMP                 |                                                                 |
| `expires_at`                                                | TIMESTAMP NULL            | optional hard expiry; once past, hidden for everyone            |
| KEY `idx_status_published (status, published_at)`           |                           |                                                                 |
| KEY `idx_status_pub_exp (status, published_at, expires_at)` |                           | covering index for the active-lookup hot path                   |


`eb_notification_targets` (only used when `audience_type='filtered'`):


| column                                                               | type                                    | notes                                               |
| -------------------------------------------------------------------- | --------------------------------------- | --------------------------------------------------- |
| `id`                                                                 | INT AUTO_INCREMENT                      | PK                                                  |
| `notification_id`                                                    | INT                                     | FK -> `eb_notifications.id`                         |
| `target_type`                                                        | ENUM('product','client_group','client') |                                                     |
| `target_id`                                                          | INT                                     | tblproducts.id / tblclientgroups.id / tblclients.id |
| UNIQUE `uniq_notif_target (notification_id, target_type, target_id)` |                                         |                                                     |


`mod_eazybackup_dismissals` (shared across multiple announcement features):


| column                                                          | type         | notes                                      |
| --------------------------------------------------------------- | ------------ | ------------------------------------------ |
| `user_id`                                                       | INT NULL     | when dismissed via WHMCS user session      |
| `client_id`                                                     | INT NULL     | when dismissed via tblclients session      |
| `announcement_key`                                              | VARCHAR(191) | for this feature, `notif:<notificationId>` |
| `dismissed_at`                                                  | TIMESTAMP    |                                            |
| UNIQUE `uniq_user_announcement (user_id, announcement_key)`     |              |                                            |
| UNIQUE `uniq_client_announcement (client_id, announcement_key)` |              |                                            |


### Audience Targeting

- `audience_type='all'` — visible to every logged-in client until dismissed.
- `audience_type='filtered'` — match is OR-style across all rows in `eb_notification_targets` for the notification:
  - `product` — matches if the client has at least one `Active` or `Suspended` service in `tblhosting` for that product id.
  - `client_group` — matches if `tblclients.groupid` equals the target id.
  - `client` — matches the specific client id.
- Filtered notifications must have at least one target row, otherwise the admin save fails validation.

### Lifecycle

```
admin "New" / "Edit"
        │
        ▼
   eb_notifications (status=draft|published)
        │ ClientAreaPage hook
        ▼
eb_get_active_notifications_for_client()
   - filter by status=published AND published_at IS NOT NULL
   - filter by published_at >= viewer baseline
       (tblusers.created_at if logged-in user, else tblclients.datecreated)
   - filter out expired (expires_at IS NULL OR expires_at > NOW())
   - filter out dismissed (notif:<id> in mod_eazybackup_dismissals for this client/user)
   - apply audience targeting
        │ ClientAreaFooterOutput hook
        ▼
_notifications_modal.phtml (Alpine ebNotifModal)
        │ user clicks Dismiss
        ▼
POST /modules/addons/eazybackup/endpoints/dismiss_notification.php
   - check_token / WHMCS\Security\Token validation
   - INSERT INTO mod_eazybackup_dismissals (notif:<id>) for both user_id + client_id
        │ next page load
        ▼
   notification no longer surfaces
```

### Admin Workflow

1. WHMCS admin → eazyBackup Power Panel → **Notifications**.
2. Click **New Notification**.
3. Fill in:
  - **Title** — short headline (max 191 chars).
  - **Body** — plain text; line breaks preserved, HTML is escaped (no rich formatting).
  - **Audience** — `All clients` or `Filtered`. For `Filtered`, choose any combination of products, client groups, and specific client IDs. When `All clients` is selected the filtered targeting fields are visually disabled and not submitted.
  - **Estimated audience** — live count of distinct clients matched by the current selection. Recalculated via `endpoints/audience_count.php` (admin-only, CSRF-protected) as you change the audience inputs.
  - **Expires at** — optional. After this datetime the notification is hidden for everyone, regardless of dismissal. Leave blank for no expiry.
4. Save options:
  - **Save Draft** — keeps `status='draft'`; not visible to clients.
  - **Save & Publish** — sets `status='published'` and stamps `published_at`.
5. Existing notifications can be Published / Unpublished / Deleted from the list view. Deleting also removes related rows in `eb_notification_targets` and the matching `mod_eazybackup_dismissals` records (so re-creating with the same id won't surface as dismissed).
6. The list view shows a per-notification reach count (distinct clients/users who have already dismissed it) computed from `mod_eazybackup_dismissals`.

### Suggested Authoring Conventions

- Keep the **title** under ~60 chars so it reads cleanly on mobile (`eb-card-title` truncates gracefully but shorter is better).
- Keep the **body** to ~50-90 words. The modal scrolls but customers do not.
- Use a stable mental "key" per announcement (e.g. `support-ticket-handoff-2026-04`) when planning, even though the actual `announcement_key` is auto-derived as `notif:<id>`. This keeps a paper trail in your release notes.
- Do not edit the body of a published notification to switch topic — re-dismissed customers will miss the new content. Publish a new notification instead.
- For feature launches that benefit a sub-segment (e.g. MSPs), filter by client group or by the relevant product so non-impacted customers don't see noise.

### Per-Client Inbox Messages

A second delivery channel, layered on top of the same modal, lets admins send a persistent message to a single client. Unlike global "What's New" notifications, dismissing a personal message **archives** it to the client's Inbox instead of permanently dismissing it.

#### Schema

`eb_client_messages`:


| column                                                                | type               | notes                                                                          |
| --------------------------------------------------------------------- | ------------------ | ------------------------------------------------------------------------------ |
| `id`                                                                  | INT AUTO_INCREMENT | PK                                                                             |
| `client_id`                                                           | INT                | FK -> `tblclients.id`                                                          |
| `title`                                                               | VARCHAR(191)       |                                                                                |
| `body`                                                                | TEXT               | plain text; `nl2br` + `htmlspecialchars` at render time                        |
| `expires_at`                                                          | TIMESTAMP NULL     | optional; once past, hidden from the modal but still visible in inbox as muted |
| `created_by`                                                          | INT NULL           | admin id                                                                       |
| `created_at`, `updated_at`                                            | TIMESTAMP          |                                                                                |
| `viewed_at`                                                           | TIMESTAMP NULL     | set when dismissed in the modal or opened in the inbox                         |
| `deleted_at`                                                          | TIMESTAMP NULL     | soft-delete; hidden from the customer, still visible to admins                 |
| KEY `idx_inbox_client (client_id, deleted_at)`                        |                    |                                                                                |
| KEY `idx_modal_lookup (client_id, viewed_at, deleted_at, expires_at)` |                    |                                                                                |


#### Lifecycle

```
admin: clientssummary -> Notifications tab -> New / Edit
        |
        v
   eb_client_messages
        | hooks.php ClientAreaPage merges into modal items
        v
"What's New" modal (kind=inbox)
        | customer Dismiss/Archive
        v
   POST endpoints/inbox_dismiss.php   -> sets viewed_at = NOW()
        | always available in:
        v
   /index.php?m=eazybackup&a=inbox    (Inbox page)
        | customer Delete
        v
   POST endpoints/inbox_delete.php    -> sets deleted_at = NOW()
        | hidden from customer; admin can Restore from Notifications tab
```

#### Admin UI

- WHMCS admin -> Clients -> View -> a new **Notifications** tab is injected via the `AdminAreaFooterOutput` hook in `hooks.php`. The tab loads `pages/admin/client_notifications.php` inside an iframe (URL: `addonmodules.php?module=eazybackup&action=client_notifications_panel&clientid=<userid>`).
- The same controller is also reachable from the eazyBackup Power Panel via `view=client_notifications&clientid=<id>` for direct linking.
- Form fields: **Title**, **Body** (plain text), optional **Expires at** (`<input type="datetime-local">`). No audience controls.
- List view shows status (Unread / Read / Expired / Deleted). Actions: Edit, Archive (soft-delete), Restore, Delete (hard).

#### Endpoints

- `POST /modules/addons/eazybackup/endpoints/inbox_dismiss.php` -- params `message_id`, `token`. Requires `$_SESSION['uid']`. Sets `viewed_at = NOW()` if not already set. Returns `{ ok, id }` or HTTP 4xx.
- `POST /modules/addons/eazybackup/endpoints/inbox_delete.php` -- params `message_id`, `token`. Requires `$_SESSION['uid']`. Sets `deleted_at = NOW()` (soft delete).
- Both verify the WHMCS plain-style CSRF token (`WHMCS\Security\Token::isValid()` with a `check_token('plain', ...)` fallback).

#### Client-area Inbox

- New route in `eazybackup_clientarea()` -- `index.php?m=eazybackup&a=inbox` renders `templates/clientarea/inbox.tpl`.
- Sidebar link added in `templates/eazyBackup/header.tpl` next to the Log Out entry.
- Page lists all non-deleted messages with read/unread dot, snippet, created timestamp, expired tag where applicable. Clicking a row opens the message in an `eb-modal`, and that interaction also marks it as read via the dismiss endpoint. Delete uses the delete endpoint and animates the row out.

### Visibility Edge Cases

- **New-client cutoff**: each viewer only sees notifications whose `published_at >= baseline`, where the baseline is `tblusers.created_at` for the logged-in user (preferred), falling back to `tblclients.datecreated`. New signups never see a backlog of historical "What's New" items.
- **Sub-account contacts**: because the baseline prefers `tblusers.created_at`, a brand-new contact added to a long-time client only sees notifications published after they joined.
- **Drafts and seeded rows**: `published_at IS NULL` rows are excluded outright.
- **Save Draft → Publish later**: `published_at` is stamped at publish time, so a draft authored before a client signed up but published after will correctly show.
- **Re-publish (unpublish → publish)**: rewrites `published_at = NOW()`, effectively re-showing it to anyone whose baseline is older. This is intentional.
- **Expiry**: notifications with `expires_at <= NOW()` are hidden for everyone. Expiry trumps baseline and dismissal-state.
- **Admin preview**: relies on the admin's own client/user record; no bypass.

### Security

- Admin write paths validate the WHMCS admin CSRF token (`check_token('WHMCS.admin.default')`) and require `$_SESSION['adminid'] > 0`.
- The dismissal endpoint requires an authenticated client/user session and a valid CSRF token (the modal partial injects `$ebNotifCsrf` for the Alpine component).
- Body is plain text; rendering uses `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` followed by `nl2br()`. There is no HTML/JS injection surface for admins or customers.
- The dismissal endpoint will only accept `notification_id` values that resolve to a `published` row, blocking attempts to dismiss draft or deleted ids.

### UI Conventions

- Modal: `eb-modal`, `eb-modal-backdrop`, `eb-modal-header`, `eb-modal-title`, `eb-modal-subtitle`, `eb-modal-close`, `eb-modal-body` (per `Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md` §11).
- Each notification card uses `eb-card` + `eb-card-title`, with a small `published_at` line under the title. Dismiss action is a button styled as a quiet outline action.
- Alpine component (`ebNotifModal`) tracks `dismissed[id]` locally for instant UI response and only opens when there is at least one undismissed item.

### Test Plan

- Create a draft, verify it does not appear in the client area.
- Publish to `All clients`; verify it appears for any logged-in client.
- Filter to a product, verify only clients with active/suspended services for that product see it.
- Filter to a client group, verify only members see it.
- Filter to specific client IDs, verify only those clients see it.
- Dismiss a notification as a logged-in client; verify it disappears, and the row exists in `mod_eazybackup_dismissals` keyed by `notif:<id>`.
- Log out and back in as the same client; verify the notification stays dismissed (dismissal is per-user/client, not per-session).
- Delete the notification from admin; verify the dismissal rows are cleaned up so a future notification with the same id starts fresh.
- POST to the dismiss endpoint without a session → 401; with an invalid CSRF token → 400; with an unknown id → 404.

## Lite (Reduced Storage) Plans

The Lite tier offers a reduced-storage backup plan (default 250 GB, 1 device) for customers who do not need the standard 1 TB starting allocation. Lite SKUs are gated behind WHMCS client groups so they are invisible to ordinary customers and appear on the order form only for entitled clients.

### Architecture summary

- Each Lite tier is a dedicated WHMCS product SKU (e.g. `OBC-Lite` pid=102, `eazyBackup-Lite` pid=103).
- Visibility on the order form is gated by client-group membership (the **entitlement** signal).
- Enforcement of the storage cap and device limit lives on the **service** (product), not the client. A client moved out of a Lite group keeps any existing Lite services capped, but cannot order more.
- Comet user policies on the Comet server lock down vault management (create / edit / delete) from the desktop client.
- The addon's client-area code refuses any attempt to disable the cap or delete the vault from the WHMCS UI.

### WHMCS configuration

**Client groups (created via Setup → Clients → Manage Client Groups):**

- `OBC-Lite` — id=15 on both dev and prod.
- `eazyBackup-Lite` — id=16 on both dev and prod.

**Products (created via Setup → Products/Services → Products/Services):**

- `OBC-Lite` — pid=102, server type `comet`, product group `OBC` (gid=7).
- `eazyBackup-Lite` — pid=103, server type `comet`, product group `eazyBackup` (gid=6).
- Each product's Comet provisioning module `Policy Group` (configoption1) must point at the matching Lite policy on the Comet server (see below).
- Each product needs a `Backup Account Username` and `Backup Account Password` custom field configured in *Custom Fields* (these are copied automatically when WHMCS provisions the user).

**Addon module settings (Setup → Addon Modules → eazyBackup → Configure):**


| Setting                | Purpose                                                                  | Example value     |
| ---------------------- | ------------------------------------------------------------------------ | ----------------- |
| `liteobcgroups`        | Client groups whose members may purchase OBC-Lite                        | `15`              |
| `liteeazybackupgroups` | Client groups whose members may purchase eazyBackup-Lite                 | `16`              |
| `lite_obc_pids`        | WHMCS PIDs that represent the OBC-Lite SKU                               | `102`             |
| `lite_eazybackup_pids` | WHMCS PIDs that represent the eazyBackup-Lite SKU                        | `103`             |
| `lite_pid_caps`        | CSV of `PID:GB` pairs that mark a PID as Lite and define its storage cap | `102:250,103:250` |


A PID is considered "Lite" everywhere in the codebase **iff** it appears in `lite_pid_caps`. Removing a PID from that setting reverts it to a standard unlimited product on the next provisioning / profile update.

### Comet server configuration (manual)

Create one user policy per Lite brand on each Comet server:

- `eazybackup-lite-250`
- `obc-lite-250`

Each policy must have these flags set:

- `PreventRequestStorageVault = true`
- `PreventAddCustomStorageVault = true`
- `PreventEditStorageVault = true`
- `PreventDeleteStorageVault = true`
- `EnforceStorageVaultRetention = true` (recommended; keeps the standard retention defaults applied)

Copy the policy GUID from Comet and paste it into each Lite product's Comet provisioning module **Policy Group** field (`configoption1`) in WHMCS Admin.

### Provisioning flow

When a customer in a Lite group places an order:

1. `eazybackup_createorder()` (in `eazybackup.php`) verifies the client is in the matching Lite group via `eazybackup_lite_resolve()` and that the posted PID is one of the client's `allowedPids`. The POST handler also blocks Lite clients from posting the full backup SKUs (58 / 60).
2. WHMCS provisions via the Comet module:
  - `comet_CreateAccount()` (in `accounts/modules/servers/comet/comet.php`) creates the Comet user, then calls `comet_UpdateUser()`.
  - `comet_UpdateUser()` (in `accounts/modules/servers/comet/functions.php`) reads the cap via `comet_LiteCapForPid()`. For Lite PIDs it sets `AllProtectedItemsQuotaEnabled = true`, `AllProtectedItemsQuotaBytes = capGB * 1024^3`, and `MaximumDevices = 1`.
  - `comet_CreateAccount()` requests the initial Storage Vault, then walks `Profile.Destinations` and stamps `StorageLimitEnabled = true` / `StorageLimitBytes = capGB * 1024^3` on the new vault before the atomic `AdminSetUserProfileHash` call.

### Client-area lockdown

For any service whose `packageid` resolves to a Lite cap, the addon enforces lockdown in three places:

- `templates/console/user-profile.tpl` — the **Quotas** card disables the "Maximum devices" toggle and stepper, displays a "plan-enforced limits" banner, locks the per-vault Quota controls to a read-only display of the cap, and replaces the "Delete vault" affordance with an explanatory notice.
- `lib/Vault.php` — `updateVault()` ignores any client-supplied `vaultQuota` payload and re-stamps `StorageLimitBytes = capBytes`; `deleteVault()` returns an error.
- `pages/console/device-actions.php` `piProfileUpdate` — clamps `MaximumDevices = 1` and re-asserts `AllProtectedItemsQuotaEnabled = true` / `AllProtectedItemsQuotaBytes = capBytes` on every save.

The Comet user policy on the Comet server is the source of truth for desktop-client-side restrictions (cannot add / edit / delete vaults from the Comet Backup desktop app).

### Upgrade / downgrade

- **Lite → Standard** (e.g. `102` → `60`) uses WHMCS's native product upgrade flow. `comet_ChangePackage()` calls `comet_UpdateUser()`, which sees `capGB = 0` for the new PID and:
  - Disables `AllProtectedItemsQuotaEnabled` and clears `MaximumDevices` back to 0 (unlimited).
  - Walks `Profile.Destinations` and clears `StorageLimitEnabled` on any vault that previously had a Lite cap, so the customer is no longer capped after the upgrade.
- **Standard → Lite** is treated as a support-assisted operation. The provisioning code will happily apply the cap, but if the existing usage exceeds it the customer's vault will immediately be over-quota. Refusing this case automatically is not currently implemented; handle through support.

### Validation checklist (on dev)

- Place a client in group 16 (eazyBackup-Lite). Visit `index.php?m=eazybackup&a=createorder`: sees only eazyBackup-Lite (103) + MS365 (52); does NOT see OBC-Lite (102), full eazyBackup (58), full OBC (60).
- Place a client in group 15 (OBC-Lite). Mirror check: only OBC-Lite (102) + MS365 (52).
- Non-Lite, non-reseller client: sees only full eazyBackup (58) + MS365 (52); no Lite SKUs.
- Place an order for a Lite SKU. Confirm the Comet user has `AllProtectedItemsQuotaEnabled = true`, `AllProtectedItemsQuotaBytes = 268435456000` (250 GiB), `MaximumDevices = 1`, and the initial vault has `StorageLimitEnabled = true` / `StorageLimitBytes = 268435456000`.
- On the client area Profile tab for the Lite service: the "Maximum devices" controls are disabled and the banner is visible; the vault editor's Quota tab shows the read-only Lite limit message; the vault editor's Danger tab shows "Vault deletion is disabled on this plan".
- POST a Lite PID directly while logged in as a non-Lite client (e.g. via curl with form fields): rejected with `This product is not available for your account.`.
- Native WHMCS upgrade Lite → Standard (e.g. 102 → 60): confirm the Comet user's `AllProtectedItemsQuotaEnabled` flips to `false` and the existing vault's `StorageLimitEnabled` flips to `false`; the client-area Quotas card unlocks.

