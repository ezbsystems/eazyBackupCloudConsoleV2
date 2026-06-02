# MS365 Backup — Agent prompt template

Copy the block below into a new chat. Fill in the **Task** section at the end.

---

```markdown
**Environment:** WHMCS on PHP 8.2. Workspace root for this work: `/var/www/eazybackup.ca/accounts`. The **MS365 Backup** WHMCS addon is a **standalone** admin-only module at `modules/addons/ms365backup/`. It is **not** part of the eazybackup Comet addon—do not modify `modules/addons/eazybackup/` unless I explicitly ask.

**Purpose:** Early-development tool to back up **one Microsoft 365 user at a time** (mail + calendar) via Microsoft Graph, with local JSON storage, admin UI, CLI worker, progress/logging, and pagination safety for known Graph defects.

**Backup storage path:** `/var/www/eazybackup/ms365/` — **not** `/var/www/eazybackup.ca/ms365/`.

**My focus:** MS365 backup module only (admin UI, backup engine, Graph client, CLI, docs)—not Comet, Partner Hub, or cloudstorage unless I say otherwise.

---

## Read first (authoritative)

1. `modules/addons/ms365backup/Docs/ARCHITECTURE.md` — module layout, backup engine, Graph rules, on-disk paths, DB tables.
2. `modules/addons/ms365backup/Docs/AZURE_SETUP.md` — Entra app permissions and credential field mapping.
3. As needed: `modules/addons/ms365backup/schema.sql` — table definitions.

---

## Primary files

| Area | Path |
|------|------|
| WHMCS entry + routing | `modules/addons/ms365backup/ms365backup.php` |
| Admin pages | `modules/addons/ms365backup/pages/admin/` (`dashboard.php`, `discover.php`, `backup.php`, `run.php`) |
| JSON API | `modules/addons/ms365backup/pages/admin/api.php` (`action=api&op=…`) |
| Admin JS | `modules/addons/ms365backup/assets/js/ms365-admin.js` |
| CLI worker | `modules/addons/ms365backup/bin/ms365_backup.php` |
| CLI bootstrap | `modules/addons/ms365backup/bin/bootstrap.php` |
| Backup engine | `modules/addons/ms365backup/lib/Ms365Backup/BackupOrchestrator.php` |
| Mail backup | `modules/addons/ms365backup/lib/Ms365Backup/MailBackupService.php` |
| Calendar backup | `modules/addons/ms365backup/lib/Ms365Backup/CalendarBackupService.php` |
| Graph + pagination | `modules/addons/ms365backup/lib/Ms365Backup/GraphClient.php`, `PaginationMonitor.php` |
| Discovery | `modules/addons/ms365backup/lib/Ms365Backup/DiscoveryService.php` |
| Inventory / resource picker | `InventoryService.php`, `TenantResource.php`, `BackupPlanner.php`, `assets/js/ms365-resource-picker.js` |
| Phase 2A platform | `BackupScope.php`, `PhysicalBackupJob.php`, `BackupEngineRegistry.php`, `MailBackupEngine.php`, `CalendarBackupEngine.php`, `sql/upgrade_phase2a_resource_runs.sql` |
| Phase 2B user bundle | `ContactsBackupService.php`, `TasksBackupService.php`, `GraphClient::paginateDelta()`, `DeltaSyncState.php`, `ContactsBackupEngine.php`, `TasksBackupEngine.php` |
| Phase 2C OneDrive | `OneDriveBackupService.php`, `OneDriveBackupEngine.php`, `DriveItemStore.php`, `DocumentLibraryBackupService.php`, `GraphClient::downloadToFile()` |
| Phase 2D SharePoint | `SharePointSiteBackupEngine.php`, `SharePointFilesBackupService.php`, `SharePointListsBackupService.php`, `ListItemStore.php`, `SiteDriveStorage.php`, `GraphSitePaths.php` |
| Phase 2E Teams | `TeamsBackupEngine.php`, `TeamsMessagesBackupService.php`, `TeamsMetadataBackupService.php`, `ChannelMessageStore.php`, `GraphTeamPaths.php` |
| Credentials | `modules/addons/ms365backup/lib/Ms365Backup/TenantRepository.php`, `TokenProvider.php` |
| Runs + logs | `modules/addons/ms365backup/lib/Ms365Backup/BackupRunRepository.php`, `ProgressLogger.php` |
| Storage paths | `modules/addons/ms365backup/lib/Ms365Backup/StorageLayout.php` |
| Worker spawn | `modules/addons/ms365backup/lib/Ms365Backup/WorkerSpawner.php` |

---

## Admin URLs (WHMCS)

- Dashboard: `addonmodules.php?module=ms365backup`
- Discovery: `&action=discover` (tabs: users, sites, teams)
- Backup: `&action=backup`
- Run detail: `&action=run&run_id={uuid}`
- API: `&action=api&op={operation}` (JSON; admin session + CSRF on POST)

---

## Stack and conventions

- **PHP 8.2**, strict types, namespace `Ms365Backup\`.
- **Composer** in addon dir: `guzzlehttp/guzzle`; run `composer install` under `modules/addons/ms365backup/`.
- **WHMCS Capsule** for DB; secrets via WHMCS `encrypt()` / `decrypt()`.
- **Admin UI:** Bootstrap/WHMCS admin classes (not eazybackup semantic theme).
- **Assets:** Use `ms365backup_asset_url('assets/js/…')` in admin pages (admin runs under `/admin/`).
- **Background jobs:** `WorkerSpawner` → `nohup php bin/ms365_backup.php run --run-id=…`; logs in `/var/www/eazybackup/ms365/_logs/worker.log`.
- **Calendar:** `/calendars/{id}/events` + `Prefer: IdType="ImmutableId"`; normal unfiltered pass, then `createdDateTime` partition fallback (not `start/dateTime` filters). Skip `occurrence`; enrich `seriesMaster` via GET + `$expand=exceptionOccurrences`; attachments via `/events/{id}/attachments`. **Never** `calendarView` / `calendarView/delta`.
- **Pagination:** Full `@odata.nextLink` comparison; `PaginationOutcome` on calendar normal pass triggers fallback; all partitions must complete cleanly or run errors (`CalendarBackupIncompleteException`). Mail folder errors skip folder only.
- **Mail / contacts / tasks delta:** `paginateDelta()` follows `@odata.nextLink` then stores `@odata.deltaLink` per folder/list in `delta_state.json`. `@removed` → `{id}.removed.json` tombstone. `GraphDeltaResetException` (410) triggers one full resync.
- **To Do:** Graph `/users/{id}/todo/lists` — not Outlook mailbox tasks. `Tasks.Read.All` + `Contacts.Read` required in Azure.
- **OneDrive:** `drives/{id}/root/delta` + item content download; separate physical run `drive:{driveId}`. `Files.Read.All`.
- **SharePoint site:** `site:{siteId}` run backs all document libraries (`sites/{id}/drives/…`) and/or all lists (`sites/{id}/lists/…`). Team+Site dedup → one site run. `Sites.Read.All` + `Files.Read.All`.
- **Teams:** `team:{groupId}` or `channel:{groupId}:{channelId}` for metadata/messages; files still via `site:{siteId}`. `ChannelMessage.Read.All` + `TeamMember.Read.All`. Message delta + reply fetch; 429 retry on list APIs.
- **Calendar verify:** Runs automatically after calendar backup (`calendar_verify` phase); results in manifest + run UI. Manual: `php bin/ms365_backup.php verify-calendar --user-id=… --calendar-id=…` (`--json` optional).
- **Scope:** Minimal diffs; match existing patterns; do not commit unless I ask.

---

## Task

<!-- Fill in below: what you want the agent to do -->

**Goal:**

**Constraints:**

**Files or areas likely involved:**

**How to verify:**
```

---

## Tips for filling in Task

- Reference a **run ID** or user UPN if debugging a specific backup.
- Mention whether the issue is **UI**, **CLI worker**, **Graph API**, or **filesystem permissions**.
- For pagination bugs, include log lines with `pagination_context` and page numbers.
- If logs show `queued` forever, check `www-data` write access to `/var/www/eazybackup/ms365` and `worker.log`.
