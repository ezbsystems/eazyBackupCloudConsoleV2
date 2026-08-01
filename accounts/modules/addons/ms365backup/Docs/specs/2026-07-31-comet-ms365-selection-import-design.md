# Comet → e3 MS365 selection import (CLI)

**Date:** 2026-07-31  
**Status:** Draft — awaiting user review  
**First target:** WHMCS client `2269`, service `5471`, e3 `backup_user_id` `E0B22D704ECE1A42C08E0AD2C6`  
**Source example:** Comet `csw.eazybackup.ca` user `ITadmin` (Protected Item `engine1/winmsofficemail`)

## Goal

Reliably read Microsoft 365 **inventory selection** from a legacy Comet Backup user profile and create a **new** e3 MS365 Users backup job on production WHMCS with equivalent `selected_resource_ids` + `scope_overrides`. Existing e3 jobs are never updated or deleted.

## Non-goals

- Migrating Comet backup data / vaults / history
- Reusing Comet as a backup engine
- Wizard UI import (can reuse the same mapper later)
- Whole-org auto-follow (`Organization` / `WholeOrg` true) beyond a documented select-all path
- Perfect parity for Comet-only concepts with no e3 equivalent (e.g. Tasks — Comet has no Tasks bit)

## Source of truth (Comet)

Selection lives on the Protected Item, not as a full Graph inventory dump:

```
Sources[<guid>].Engine = "engine1/winmsofficemail"
Sources[<guid>].EngineProps.CUSTOM_SETTINGV2  // JSON string
```

Parsed `CUSTOM_SETTINGV2` fields used:

| Field | Use |
|--------|-----|
| `Organization` / `WholeOrg` | If either true → treat as “select all selectable inventory” (e3 `CustomerSelectionCodec::selectAllFromInventory`). ITadmin has both false. |
| `BackupOptions` | Map of account/site ID → Comet `SERVICE_*` bitmask |
| `MemberBackupOptions` | Map of group/team ID → bitmask applied to **members** |

Comet service bits ([API constants](https://docs.cometbackup.com/latest/api/api-constants/)):

| Bit | Value | e3 mapping |
|-----|------:|------------|
| Calendar | 1 | `calendar` on user/mailbox |
| Contact | 2 | `contacts` on user/mailbox |
| Mail | 4 | `mail` on user/mailbox |
| SharePoint | 8 | `files` + `lists` on sites; for users, presence of OD/SP bits drives OneDrive child selection (see below) |
| OneDrive | 16 | select child `onedrive:<userGraphId>` with `onedrive` + `files` |

Common masks: `31` = full user workloads; `24` = SharePoint+OneDrive; `28` = Mail+SP+OD.

**ID shapes in `BackupOptions`:**

- Plain GUID → user, mailbox, group, or team Graph ID
- `hostname,siteCollectionGuid,webGuid` → SharePoint site Graph ID (same shape Graph/e3 inventory uses)

**Secrets:** `CLIENT_ID` / `TENANT_ID` / `APP_SECRET` may appear in EngineProps. Importer must never log or write them. Prefer `--comet-profile` JSON that can be redacted; if fetching via Comet API, strip secrets before any report dump.

## Target of truth (e3)

- Inventory: connected tenant for `backup_user_id`, loaded the same way `Ms365CustomerJobService::create` does
- Job create only: `Ms365CustomerJobService::create($clientId, $backupUserId, $payload)`
- Payload must include `selected_resource_ids`, `scope_overrides`, `schedule_frequency` (required by create)
- Policy **B**: always insert a new job; never call `update`

## Mapping rules

### 1. Index e3 inventory

Build lookup maps from inventory `resources[]`:

- `graph_id` (case-insensitive) → resource(s)
- `id` (`user:…`, `site:…`, `onedrive:…`, `team:…`, `group:…`)
- For sites: also index by full Graph site id string (already `host,guid,guid`)

If multiple resources share a graph id (unusual), prefer type order: user/mailbox → team → group → site; report as ambiguous.

### 2. `BackupOptions` entry

For each `(cometId, mask)`:

**A. Site key (contains `,`)**  
- Resolve `sharepoint_site` by `graph_id === cometId`  
- If found and selectable: add `site:…`  
- Scopes: if `mask & 8` (SharePoint) → `files=true`, `lists=true`; else if only OneDrive bit → `files=true`, `lists=false`  
- Unmatched → `unmatched_sites[]`

**B. Plain GUID**  
Try resolve in order:

1. `user` / `mailbox` by graph_id  
2. Else `team` by graph_id  
3. Else `m365_group` by graph_id  
4. Else `sharepoint_site` by graph_id (rare GUID-only site ids)

**User / mailbox:**

- Always add parent `user:…` / mailbox id if any of mail/calendar/contacts bits set, OR if OneDrive bit set (parent still required for OD child in wizard model)
- Scope on parent:
  - `mail` = `(mask & 4) !== 0`
  - `calendar` = `(mask & 1) !== 0`
  - `contacts` = `(mask & 2) !== 0`
  - `tasks` = `false` (no Comet bit)
- If `(mask & 16) !== 0` (OneDrive): also select child `onedrive:<sameGraphId>` when present in inventory, scopes `onedrive=true`, `files=true`. If child missing, report `missing_onedrive_child[]` but keep user selected.

**Team / group:**

- Select team/group resource
- Map bits best-effort onto e3 capability template:
  - Team: `teams_messages` / `teams_metadata` / `files` true if any of mail/calendar/contacts/sharepoint/onedrive bits set (Comet does not model Teams messages separately — prefer enabling messages+files when mask ≠ 0)
  - Group: `mail`/`calendar`/`files` from corresponding bits (SharePoint/OneDrive → files)

### 3. `MemberBackupOptions`

For each `(groupOrTeamId, mask)`:

1. Resolve team/group in inventory; if found, ensure it is selected (scopes as above)
2. Expand members when inventory exposes them (site/group member lists / linked user resources already in inventory). Apply **user/mailbox + OneDrive** mapping from §2 using `mask` to each resolved member user
3. Keys that do not resolve → `unmatched_member_roots[]` (do not fail the whole import by default)

v1 does **not** call live Graph membership APIs beyond what is already in the cached inventory. Document that a fresh inventory refresh improves member expansion.

### 4. Deduping

Union all selected ids; merge scope overrides with OR of booleans per capability key when the same resource is hit multiple times (BackupOptions + MemberBackupOptions).

## CLI

Path: `accounts/modules/addons/ms365backup/bin/ms365_comet_selection_import.php`  
Bootstrap: existing `bin/bootstrap.php` + WHMCS init (same as other admin CLIs).

```text
php ms365_comet_selection_import.php \
  --comet-profile=/path/to/itadmin-profile.json \
  --whmcs-userid=2269 \
  --service-id=5471 \
  --backup-user-id=E0B22D704ECE1A42C08E0AD2C6 \
  [--schedule-frequency=once_daily] \
  [--timezone=America/Edmonton] \
  [--job-name='M365 (imported from Comet ITadmin)'] \
  [--max-unmatched-pct=25] \
  [--apply] \
  [--json]
```

| Flag | Behavior |
|------|----------|
| (default) | Dry-run: print summary + unmatched; write optional `--out-selection.json` with proposed payload; **no DB write** |
| `--apply` | After validation, call `Ms365CustomerJobService::create` only |
| `--comet-profile` | Required for v1 (API fetch optional follow-up) |
| `--service-id` | Assert `s3_backup_users` / service linkage matches `backup_user_id` + client |
| `--max-unmatched-pct` | If unmatched BackupOptions keys / total BackupOptions keys exceeds threshold, refuse `--apply` (dry-run still reports). Default `25`. |
| `--json` | Machine-readable report on stdout |

Schedule: create requires a frequency; default `once_daily`. Timezone default from payload flag, else client resolver / Comet `LocalTimezone` if present in profile. Slot assignment remains `Ms365ScheduleAssigner` (evening window) — we do **not** hard-code Comet 21:30 unless a future flag is added.

## Validation before `--apply`

1. WHMCS client id matches backup user owner  
2. Service id `5471` is linked to that backup user (fail closed if mismatch)  
3. Tenant connected for backup user  
4. Inventory non-empty and loadable  
5. `CustomerSelectionCodec::validate` passes on proposed selection  
6. Unmatched % under threshold  
7. Never call `update`

## Library layout

| Component | Role |
|-----------|------|
| `Ms365Backup\Comet\CometOffice365SelectionParser` | Extract `CUSTOM_SETTINGV2` from profile; decode options maps; no WHMCS deps |
| `Ms365Backup\Comet\CometServiceMask` | Bit constants + decode to named flags |
| `Ms365Backup\Comet\CometSelectionMapper` | Map parsed selection + e3 inventory → ids/scopes + report |
| CLI bin | Wiring, dry-run/apply, safety checks |
| PHPUnit/standalone tests | Bitmask decode, site vs user key split, fixture profile → expected ids against tiny inventory fixture |

## Reports

Human summary includes:

- Comet source description / source GUID used  
- Counts: BackupOptions users/sites, MemberBackupOptions roots, matched users, matched sites, matched teams/groups, missing OneDrive children, unmatched  
- On `--apply` success: new `job_id`

## Security / ops

- Redact `APP_SECRET`, `CometBucketKey`, password fields from any written copy of the profile  
- Run on production WHMCS host (same as other `ms365backup/bin` tools) after code deploy  
- First production run: dry-run, review unmatched, then `--apply`

## Success criteria (ITadmin → E0B22D…)

1. Dry-run maps the large majority of `BackupOptions` GUIDs and site keys to e3 inventory for that tenant  
2. `--apply` creates exactly one new `s3_cloudbackup_jobs` row (`source_type=ms365`) with selection persisted  
3. Existing jobs for that backup user unchanged  
4. Job opens in e3 wizard/UI with expected users/sites checked  
5. Unit tests cover mask decode + mapping fixtures without live Comet/Graph

## Open follow-ups (out of v1)

- Fetch profile via Comet Admin API (`--comet-username` + server)  
- Exact Comet schedule time import  
- Live Graph member expansion for `MemberBackupOptions`  
- Wizard “Import from Comet” UI
