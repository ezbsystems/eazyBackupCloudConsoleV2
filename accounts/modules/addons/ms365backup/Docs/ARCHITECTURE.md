# MS365 Backup — Architecture

Admin-only WHMCS addon for backing up Microsoft 365 mailbox and calendar data via Microsoft Graph. This is an early development tool (single tenant, one user per run), not integrated with the eazybackup Comet product.

---

## Environment

| Item | Value |
|------|--------|
| WHMCS root | `/var/www/eazybackup.ca/accounts` |
| Addon path | `modules/addons/ms365backup/` |
| PHP | 8.2+ |
| Backup storage | `/var/www/eazybackup/ms365/` (not under `eazybackup.ca/`) |
| Graph auth | OAuth 2.0 client credentials (application permissions) |
| HTTP client | Guzzle (`composer install` in addon directory) |

**Note:** WHMCS admin UI is served from `/admin/`. Static assets must use `ms365backup_asset_url()` (`../modules/addons/ms365backup/...`), not bare `modules/addons/...`.

---

## High-level architecture

```
┌─────────────────────────────────────────────────────────────────┐
│ WHMCS Admin (browser)                                           │
│  addonmodules.php?module=ms365backup&action=…                     │
└────────────┬───────────────────────────────┬────────────────────┘
             │ HTML pages                     │ JSON (action=api)
             ▼                                ▼
┌────────────────────────┐         ┌─────────────────────────────┐
│ pages/admin/*.php      │         │ pages/admin/api.php         │
│ dashboard, discover, │         │ save_config, start_backup,  │
│ backup, run          │         │ progress, logs, cancel_run  │
└────────────────────────┘         └──────────────┬──────────────┘
                                                  │ spawn
                                                  ▼
                                   ┌─────────────────────────────┐
                                   │ bin/ms365_backup.php (CLI)  │
                                   │ BackupOrchestrator          │
                                   └──────────────┬──────────────┘
                                                  │
                    ┌─────────────────────────────┼─────────────────────────────┐
                    ▼                             ▼                             ▼
           MailBackupService            CalendarBackupService          DiscoveryService
                    │                             │                             │
                    └─────────────────────────────┼─────────────────────────────┘
                                                  ▼
                                         GraphClient + TokenProvider
                                                  │
                                                  ▼
                                         Microsoft Graph API v1.0
                                                  │
                                                  ▼
                                         /var/www/eazybackup/ms365/
```

**Data stores:**

- **MySQL** — tenant credentials, run status, log lines (for live UI polling).
- **Filesystem** — backup payloads (JSON per message/event) and optional `run.log` mirror.

---

## WHMCS module shell

| File | Role |
|------|------|
| `ms365backup.php` | `ms365backup_config`, `activate`, `upgrade`, `output`, `sidebar` |
| `schema.sql` | Full schema for fresh installs (`CREATE TABLE IF NOT EXISTS`) |
| `sql/upgrade_*.sql` | Incremental migrations (e.g. enum changes on existing DBs) |

**Schema lifecycle** (`ms365backup.php`):

- `ms365backup_activate()` — runs `schema.sql`, then all `sql/upgrade_*.sql`, then storage setup. Also runs on **deactivate + reactivate**.
- `ms365backup_upgrade($vars)` — same three steps when the **version** in `ms365backup_config()` is bumped while the module stays active. WHMCS does **not** call `upgrade` on reactivate alone.

Bump `version` in `ms365backup_config()` (e.g. `1.0.0` → `1.0.1`) and save the module in **Setup → Addon Modules** to trigger `ms365backup_upgrade()` on an already-active install.
| `ms365backup_autoload.php` | PSR-4 fallback if Composer vendor missing |

### Admin routing (`ms365backup_output`)

| `action` | Handler |
|----------|---------|
| *(default)* | `pages/admin/dashboard.php` — Entra credentials |
| `discover` | `pages/admin/discover.php` — read-only users / sites / teams (legacy JSON caches) |
| `backup` | `pages/admin/backup.php` — tenant **resource picker**, start run, recent runs |
| `run` | `pages/admin/run.php` — progress + live logs |
| `api` | `pages/admin/api.php` — JSON API (exits before WHMCS wrapper) |

### JSON API operations (`action=api&op=…`)

| Operation | Method | Purpose |
|-----------|--------|---------|
| `save_config` | POST | Store tenant credentials (secret encrypted via WHMCS `encrypt()`) |
| `test_auth` | POST | Token + organization probe |
| `get_config` | GET | Read non-secret config |
| `discover_users` / `discover_sites` / `discover_teams` | POST | Refresh Graph lists → legacy `discovery/*.json` |
| `discover_inventory` | POST | Full tenant inventory refresh → `discovery/inventory.json` (+ legacy caches) |
| `load_cached` | GET | Read cached discovery JSON (`type=users\|sites\|teams\|inventory`) |
| `load_inventory` | GET | Read unified `inventory.json` |
| `plan_backup` | POST | `selected_ids_json` + `scope_json` → physical jobs, dedup, runnable/deferred summary |
| `start_backup_plan` | POST | Build physical queue, spawn workers for runnable user/mailbox jobs only |
| `check_access` | POST | Chunked access probe; `type=users\|sites` (legacy) or `inventory_users\|inventory_sites` |
| `start_backup` | POST | Legacy single-user run (uses `createFromPhysicalJob` internally) |
| `start_backup_batch` | POST | Legacy batch via `selected_ids_json` + physical queue |
| `progress` | GET | Run status for polling |
| `logs` | GET | Log tail (`since_id`) |
| `list_runs` | GET | Recent runs |
| `cancel_run` | POST | Mark cancelled, SIGTERM worker if PID file exists |
| `restart_worker` | POST | Re-spawn CLI for stuck queued run |
| `storage_check` | GET | Writable base path, exec availability |

---

## Backup engine (CLI)

**Entry:** `bin/ms365_backup.php`

| Command | Purpose |
|---------|---------|
| `test-auth` | Verify Graph token |
| `discover users\|sites\|teams\|inventory` | Refresh discovery cache or full `inventory.json` |
| `check-access users\|sites` | Probe Graph access for all cached users or sites (`--limit=25` per batch) |
| `run --run-id=UUID` | Execute one backup run |
| `verify-calendar --user-id=… --calendar-id=…` | Compare Graph `$count` per `createdDateTime` year partition to on-disk events; exit 1 if gaps (`--json` for machine output) |

**Bootstrap:** Loads WHMCS `init.php` (for Capsule + `encrypt`/`decrypt`) and addon autoload.

**Worker spawn:** `WorkerSpawner` runs `nohup php …/ms365_backup.php run --run-id=…` with stdout/stderr appended to `/var/www/eazybackup/ms365/_logs/worker.log`.

**Orchestration** (`BackupOrchestrator` + `BackupEngineRegistry`):

1. `auth` — obtain Graph token  
2. Registered engines run in order when `supports(job, scope)`:
   - `MailBackupEngine` → `mail_folders` / `mail_messages`
   - `CalendarBackupEngine` → `calendars` / `calendar_events` (+ `calendar_verify` inside service)
   - `DeferredBackupEngine` — non-user types only (skipped with reason)
3. `finalize` — write `manifest.json` with `resource_id`, `physical_key`, `logical_sources`, `scope`, `engines`

Cancellation: `RunCancellation` checks DB status; `RunCancelledException` aborts cooperatively. Mail folder pagination errors skip that folder and continue. **Calendar:** each calendar must complete inventory (normal pass or full partition fallback); any incomplete calendar fails the run (`CalendarBackupIncompleteException`).

---

## Library (`lib/Ms365Backup/`)

| Class | Responsibility |
|-------|----------------|
| `TenantRepository` | Single-row tenant config (`ms365_tenant_config`), encrypt/decrypt app secret |
| `TokenProvider` | Client credentials token per cloud region |
| `RegionEndpoints` | Login + Graph host for Global / USGov / China / Germany |
| `GraphClient` | GET, pagination generator, safety monitors |
| `PaginationMonitor` | Per-context logging + max page limits |
| `GraphPaginationException` | Pagination loop / cap exceeded |
| `DiscoveryService` | List users, SharePoint sites, Teams; write legacy `discovery/*.json` |
| `InventoryService` | Build unified tenant inventory (users, OneDrive, sites, teams/channels, groups, relationships) |
| `TenantResource` | Resource type constants, badges, capability chips, normalized records |
| `RelationshipResolver` | Team/group/site/OneDrive relationship edges + physical dedup keys |
| `BackupPlanner` | `buildPhysicalQueue()` — dedup at queue time, physical jobs with `engine_status` |
| `BackupScope` | Capability flags (`mail`, `calendar`, …) as JSON on runs |
| `PhysicalBackupJob` | DTO: physical_key, primary resource, logical_sources, scope, runnable/deferred |
| `BackupEngineInterface` / `BackupEngineRegistry` | Pluggable engines per resource type + scope |
| `MailBackupEngine` / `CalendarBackupEngine` | Wrappers around existing backup services |
| `ResourceAccessService` | Probe mail/calendar/site access; merge `access` into discovery or inventory cache |
| `ResourceAccessClassifier` | Map `GraphApiException` → skippable unavailable/locked states |
| `MailBackupService` | Mail folders + messages → disk |
| `CalendarBackupService` | Orchestrates calendar inventory + enrichment |
| `CalendarInventoryScanner` | Normal `/events` pass; triggers partition fallback |
| `CalendarPartitionScanner` | `createdDateTime` partitions (year→hour) |
| `CalendarEventStore` | Upsert envelopes; `backup_state.json` watermark |
| `SeriesMasterEnricher` | GET series master + `$expand=exceptionOccurrences` |
| `CalendarAttachmentFetcher` | GET `/events/{id}/attachments` when `hasAttachments` |
| `PaginationOutcome` | How a `paginate()` session ended |
| `CalendarBackupIncompleteException` | Run fails when any calendar incomplete |
| `BackupOrchestrator` | Phase machine for one run |
| `BackupRunRepository` | CRUD for `ms365_backup_runs` |
| `ProgressLogger` | Inserts `ms365_backup_log_lines` + optional file append |
| `StorageLayout` | Path helpers under `/var/www/eazybackup/ms365` |
| `StoragePermissions` | Ensure `www-data` can write base directory |
| `WorkerSpawner` | Background `exec` of CLI |
| `WorkerProcess` | `worker.pid` file + `posix_kill` on cancel |
| `RunCancellation` | Poll cancelled status during long loops |

---

## Microsoft Graph usage

### Authentication

- Flow: `POST {login}/{tenant}/oauth2/v2.0/token` with `grant_type=client_credentials`, scope `{graph}/.default`.
- Permissions documented in [AZURE_SETUP.md](AZURE_SETUP.md).

### Mail backup

- `GET /users/{id}/mailFolders` (paginated)
- Per folder: `GET /users/{id}/mailFolders/{folderId}/messages` with `$select` for core fields
- Full message JSON written per file

### Calendar backup (important)

- **Uses** `GET /users/{id}/calendars/{calendarId}/events` with `Prefer: IdType="ImmutableId"`.
- **Inventory flow:**
  1. **Normal pass** — unfiltered list, `$top=100`. If pagination ends cleanly → calendar inventory complete.
  2. **Fallback** — if a duplicate-only page appears while `@odata.nextLink` remains, run **partition scan** on `createdDateTime` from `1990-01-01T00:00:00Z` through `now+1 day`, `$top=25`, `$orderby=createdDateTime`. Split year→month→day→hour on loop; calendar complete only if every partition ends cleanly.
- **Do not** use `start/dateTime` filters for inventory (recurring series masters may have old `start` but still be required).
- **Does not use** `calendarView` or `calendarView/delta` (known Graph defect). See [Graph SDK #3070](https://github.com/microsoftgraph/msgraph-sdk-dotnet/issues/3070).
- **No tombstoning:** incomplete scans never delete prior event JSON on disk.
- **After inventory:** enrich each `seriesMaster` via GET; fetch attachments when `hasAttachments`.

**Event types stored:**

| `type` | Action |
|--------|--------|
| `singleInstance` | Save full event envelope JSON |
| `seriesMaster` | Save event + `series/{id}.json` (recurrence, cancelledOccurrences) |
| `exception` | Save event; link to series master |
| `occurrence` | **Skipped** (avoid thousands of expanded instances) |

### Pagination safety (`GraphClient::paginate`)

When a `PaginationMonitor` is attached (mail + calendar backup):

- Logs each page: `items_on_page`, `new_items_on_page`, `skip_token`, `has_next_link`.
- **Max pages** default 500.
- **Identical URL:** same full `@odata.nextLink` seen twice → abort.
- **Duplicate content:** page has items but all IDs already seen → mail: throw; calendar **normal** pass: set `PaginationOutcome::stoppedOnDuplicatePage` and trigger partition fallback; calendar **partition** pass: throw and subdivide (or fail at hour granularity).
- **Empty pages:** 3 consecutive empty pages with a next link → abort.

**Critical:** Compare full nextLink URLs (including `$skiptoken`). Do not strip skiptoken when detecting duplicates.

---

## On-disk layout

```
/var/www/eazybackup/ms365/
  _logs/
    worker.log              # CLI nohup aggregate log
  {tenant_id}/
    discovery/
      users.json
      sites.json
      teams.json
      inventory.json          # unified resource inventory (Phase 1 picker)
    users/{graph_user_id}/
      mail/
        folders.json
        messages/{folder_id}/{message_id}.json
      calendars/{calendar_id}/
        backup_state.json       # complete flag, scanMode, partition manifest
        events/{immutable_event_id}.json
        series/{series_master_id}.json
      runs/{run_id}/
        run.log               # mirror of DB logs
        manifest.json         # written on success (includes calendar.verify)
        calendar_verify/      # full per-calendar verify reports (JSON)
        worker.pid            # CLI PID for cancel
```

Run metadata and live logs are in MySQL for the admin UI; payload data lives under `users/{id}/mail` and `users/{id}/calendars`.

### Unified inventory (`inventory.json`)

Built by `InventoryService::refresh()` (API `discover_inventory`, CLI `discover inventory`). Also refreshes legacy `users.json`, `sites.json`, `teams.json` for the **Discover** tab.

```json
{
  "fetched_at": "2026-06-02T12:00:00+00:00",
  "resources": [ { "id": "user:{guid}", "resource_type": "user", ... } ],
  "relationships": [ { "from_id", "rel", "to_id", "physical_key" } ],
  "counts": { "user": 115, "user_onedrive": 108, ... }
}
```

**Resource types (Phase 1):** `user`, `mailbox`, `user_onedrive`, `sharepoint_site`, `team`, `team_channel`, `m365_group`.

**OneDrive dual display:** One `user_onedrive` row per user (`id`: `onedrive:{userGraphId}`, `parent_id`: `user:{userGraphId}`). The Backup tab shows it nested under the user and again under the **OneDrive** section (same `id`, shared checkbox state).

**Dedup:** `RelationshipResolver` links Teams/groups/channels to SharePoint sites (`files_in_site`). `BackupPlanner` warns when multiple selected resources share the same `physical_key` (files backed up once in a future engine).

**Phase 1 backup:** Only `user` and `mailbox` resources are **runnable** (mail + calendar). Other types are selectable for planning but do not queue workers.

### Discovery access metadata

After **Check access** (API `check_access` or CLI `check-access`), items may include an `access` object in legacy JSON or `inventory.json`:

**Users:** `mail`, `calendar` (`available` | `unavailable` | `locked` | `error`), plus `mail_reason`, `calendar_reason`, `checked_at`.

**Sites:** `status`, `reason`, `checked_at`.

Use `type=inventory_users` / `inventory_sites` on the Backup tab; `users` / `sites` on the Discover tab.

Probes use lightweight Graph GETs (`mailFolders?$top=1`, `calendars?$top=1`, `sites/{id}`). The backup worker updates this cache when a phase is skipped at runtime.

---

## Database schema

| Table | Purpose |
|-------|---------|
| `ms365_tenant_config` | One row (`id=1`): region, tenant_id, client_id, encrypted secret |
| `ms365_backup_runs` | Per **physical** backup job: `resource_id`, `resource_type`, `graph_id`, `physical_key`, `scope_json`, `logical_sources_json`, plus legacy `user_id` / `backup_mail` / `backup_calendar` |
| `ms365_backup_log_lines` | Append-only log for UI tail (`since_id` polling) |

Run `status` values: `queued`, `running`, `success`, `error`, `cancelled`, `skipped` (all selected phases unavailable; see `ResourceUnavailableException` handling in `BackupOrchestrator`).

---

## Frontend

- **No Smarty** — inline PHP admin pages + Bootstrap (WHMCS admin styles).
- **`assets/js/ms365-admin.js`** — Dashboard, Discover tab, run page polling.
- **`assets/js/ms365-resource-picker.js`** — Backup tab categorized resource picker (loaded only on `backup.php`).
- CSRF: WHMCS `token` on POST; `check_token('WHMCS.admin.default')` in API.

---

## Operations checklist

1. Activate addon in WHMCS → creates tables + `/var/www/eazybackup/ms365` (should be `www-data` writable).
2. `composer install` in addon directory.
3. Configure Entra app per [AZURE_SETUP.md](AZURE_SETUP.md).
4. Dashboard → test connection → Backup → **Refresh resource inventory** → select user(s) → Start backup.
5. Optional: Discover tab still uses legacy per-type caches (`discover users|sites|teams`).
6. Troubleshoot worker: `tail -f /var/www/eazybackup/ms365/_logs/worker.log`
7. Manual run: `sudo -u www-data php modules/addons/ms365backup/bin/ms365_backup.php run --run-id=UUID` from WHMCS root
8. Manual inventory: `sudo -u www-data php modules/addons/ms365backup/bin/ms365_backup.php discover inventory`

### Phase 1 verification checklist

1. **Inventory refresh** — Backup tab → Refresh resource inventory; confirm `inventory.json` under `{tenant}/discovery/` with users, OneDrive, sites, teams/channels, groups.
2. **Discover regression** — Discover tab Users/Sites/Teams still load after refresh.
3. **Dual OneDrive** — Select nested OneDrive under a user; same row checked under OneDrive section.
4. **Dedup warning** — Select a Team and its linked SharePoint site; confirm duplicate-coverage warning before backup.
5. **Runnable backup** — Select user(s) only → Start backup queues mail/calendar runs.
6. **Deferred types** — Select Team only → Start backup explains Phase 1 does not run.
7. **CLI** — `php bin/ms365_backup.php discover inventory` exits 0.

---

## Phase 2A platform (implemented)

- **Physical jobs:** `BackupPlanner::buildPhysicalQueue()` collapses selections (e.g. Team + Site → one `site:{id}` job when Files/Lists scope enabled).
- **Runs:** One `ms365_backup_runs` row per physical target; `logical_sources_json` lists admin picker selections.
- **Scope:** `scope_json` on each run; UI sends `scope_json` with `start_backup_plan`.
- **Storage:** `StorageLayout::runDirForJob($physicalKey, $runId)` under `users/`, `sites/`, `drives/`, etc.
- **Queue API:** `start_backup_plan` spawns workers only for `engine_status=runnable` user/mailbox jobs with at least one user-bundle scope enabled (mail, calendar, contacts, tasks).

### Phase 2A verification

1. Migration applied (module version `1.1.0` → `ms365backup_upgrade`).
2. Single user + mail/cal → one run with `physical_key=user:{guid}`, manifest includes `logical_sources`.
3. Team + Site selected → `plan_backup` shows one runnable `sharepoint_site` physical job when Files/Lists scope enabled (dedup warning in UI).
4. Calendar-only scope → mail engine skipped.
5. Legacy `start_backup` still works.

## Phase 2B user bundle (implemented)

- **Engines:** `ContactsBackupEngine`, `TasksBackupEngine`; mail uses per-folder Graph delta via `GraphClient::paginateDelta()`.
- **Scope UI:** Contacts and Tasks (To Do) checkboxes on Backup tab; `scope_json` includes `contacts`, `tasks`.
- **Storage:**
  - `{user}/contacts/folders.json`, `contacts/folders/{folderId}/contacts/*.json`, `delta_state.json`
  - `{user}/todo/lists.json`, `todo/lists/{listId}/tasks/*.json`, `delta_state.json`
  - `{user}/mail/messages/{folderId}/delta_state.json`
- **Tombstones:** Delta `@removed` items → `{id}.removed.json` (entity JSON not deleted).
- **Tasks:** User resources only (shared mailboxes skip To Do with logged reason).
- **Azure:** `Contacts.Read`, `Tasks.Read.All` (see [AZURE_SETUP.md](AZURE_SETUP.md)).

### Phase 2B verification

1. Module version `1.2.0`; Azure permissions granted.
2. Contacts-only scope → `contacts/` tree populated.
3. Tasks-only scope → `todo/lists/` populated.
4. Second mail run → run log shows `delta` mode for folders with prior `delta_state.json`.
5. Combined mail + calendar + contacts + tasks → manifest lists all engine results.
6. Delete one `delta_state.json` → next run resyncs that folder/list only.

## Phase 2C OneDrive (implemented)

- **Engine:** `OneDriveBackupEngine` + `OneDriveBackupService` — `GET /drives/{id}/root/delta`, file download via `/items/{id}/content`.
- **Physical job:** `drive:{driveId}` (separate run from `user:{guid}`); requires OneDrive scope **and** `user_onedrive` resource selection.
- **Storage:** `{tenant}/drives/{driveId}/items/*.json`, `content/{itemId}/{fileName}`, `delta_state.json`.
- **Tombstones:** `@removed` → `items/{id}.removed.json` (content files retained).
- **Azure:** `Files.Read.All` (existing).

### Phase 2C verification

1. Module version `1.3.0`.
2. OneDrive scope + select `user_onedrive` → run with `physical_key=drive:{id}`.
3. `drives/{driveId}/items/` and `content/` populated.
4. Second run uses delta mode in logs.
5. User + OneDrive selection → two separate runs.

## Phase 2D SharePoint site (implemented)

- **Engine:** `SharePointSiteBackupEngine` — phases `sharepoint_files`, `sharepoint_lists`.
- **Services:** `SharePointFilesBackupService` (all document libraries via `DocumentLibraryBackupService` + `SiteDriveStorage`), `SharePointListsBackupService` (per-list `items/delta`).
- **Physical job:** `site:{siteId}` when **Files** and/or **Lists** scope enabled; Team + Site selection dedupes to one site run (`logical_sources` lists all picker rows).
- **Storage:** `{tenant}/sites/{safeSiteId}/drives/{driveId}/…`, `lists/{listId}/items/…`, catalogs `drives.json`, `lists/lists.json`.
- **Refactor:** `DriveItemStorage` interface; `PersonalDriveStorage` (OneDrive), `SiteDriveStorage` (SharePoint libraries).
- **Azure:** `Sites.Read.All` + `Files.Read.All` (existing).

### Phase 2D verification

1. Module version `1.4.0`.
2. Site-only: enable Files and/or Lists, select one SharePoint site → `physical_key=site:{id}`.
3. `sites/{id}/drives/{libraryId}/` has items + content (files scope).
4. `sites/{id}/lists/{listId}/items/` populated (lists scope).
5. Team + Site selected → dedup warning; **one** site run.
6. Second run → delta mode for libraries/lists with prior `delta_state.json`.
7. Regression: OneDrive, user bundle unchanged; Teams messages still not backed up.

## Phase 2E Teams (implemented)

- **Engine:** `TeamsBackupEngine` — phases `teams_metadata`, `teams_messages`; nested manifest `{metadata, messages}`.
- **Physical jobs:** `team:{groupId}` (all channels) or `channel:{groupId}:{channelId}` (channel-only selection); Team + channels dedup to one team run for messages.
- **Files:** Still via Phase 2D `site:{siteId}` — selecting Team + Files + Messages may queue **two** runs.
- **Storage:** `{tenant}/teams/{groupId}/team.json`, `members.json`, `channels/{channelId}/messages/`, `delta_state.json`.
- **Graph:** `messages/delta` + per-message `replies`; 429 retry in `GraphClient`.
- **Azure:** `ChannelMessage.Read.All`, `TeamMember.Read.All` (see [AZURE_SETUP.md](AZURE_SETUP.md)).

### Phase 2E verification

1. Module version `1.5.0`; grant `ChannelMessage.Read.All`.
2. Teams messages + select Team → `physical_key=team:{groupId}`; all channels under `teams/{id}/channels/`.
3. Channel-only selection → `channel:{groupId}:{channelId}` run.
4. Team + two channels → dedup warning; one team message run.
5. Team + Files + Messages → site run + team run.
6. Second run → delta mode; `@removed` tombstones.

## Phase 2F — M365 groups (v1.6.0)

- **Engine:** `GroupBackupEngine` — mail + calendar via `groups/{id}/…` Graph paths (`GraphMailboxOwner`).
- **Physical job:** `group:{groupId}` when mail and/or calendar scope enabled.
- **Files:** Still `site:{siteId}` (Phase 2D); group + files + mail may queue **two** runs.
- **Storage:** `{tenant}/groups/{groupId}/mail/…`, `calendars/…`.

### Phase 2F.1 verification (groups)

1. Module version `1.6.0+`.
2. Non-Team group + mail → `physical_key=group:{id}`, `groups/{id}/mail/` populated.
3. Group + calendar → events + `calendar_verify` in manifest.
4. Group + Files + mail → site run + group run.
5. Regression: users, OneDrive, SharePoint, Teams unchanged.

## Phase 2F — Planner (v1.7.0)

- **Engine:** `PlannerBackupEngine` + `PlannerBackupService` — `planner/plans/{id}`, buckets, tasks.
- **Inventory:** `GET /groups/{groupId}/planner/plans` → `planner_plan` resources.
- **Physical job:** `planner:{planId}` with Planner scope.

## Phase 2F — OneNote (v1.8.0)

- **Engine:** `OneNoteBackupEngine` — notebooks/sections/pages JSON export.
- **Inventory:** user, group, and site notebooks.
- **Physical job:** `onenote:{notebookId}`; Azure `Notes.Read.All`.

## Phase 2F — Directory baseline

- **Engine:** `DirectoryBackupEngine` — `directory:tenant` exports `directory/users.json`, `groups.json`.

## Phase 3 platform (v1.8.0+)

- **Storage:** `BackupStorageInterface` + local + S3-compatible backends (`BackupStorageFactory`).
- **Multi-tenant:** `ms365_tenant_records`, `whmcs_client_id` on runs.
- **Queue:** `ms365_job_queue`, `bin/ms365_queue_worker.php`.
- **Client area:** `ms365backup_clientarea()` → `templates/clientarea/dashboard.tpl`.
- See [PHASE3_PRD.md](PHASE3_PRD.md), [CUSTOMER_ONBOARDING.md](CUSTOMER_ONBOARDING.md).

## Out of scope (later)

- Calendar Graph delta / `calendarView`
- Retention policies
- Comet/eazybackup engine coupling (see Phase 3 PRD P3-8)
- Full client-area resource picker and self-service scheduling (MVP is run history only)

---

## Related docs

- [AZURE_SETUP.md](AZURE_SETUP.md) — Entra app registration
- [Prompts/ms365backup_agent_prompt.md](Prompts/ms365backup_agent_prompt.md) — agent chat starter template
