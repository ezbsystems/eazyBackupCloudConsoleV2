# Comet → e3 MS365 selection import

Admin CLI that reads Microsoft 365 **inventory selection** from a legacy Comet Backup user profile and creates a **new** e3 MS365 Users backup job with equivalent `selected_resource_ids` + `scope_overrides`.

This does **not** migrate backup data, vaults, or job history — selection only.

| | |
|--|--|
| **Status** | Implemented (PHP CLI + `Ms365Backup\Comet\*` mapper). Deploy module code to the WHMCS host before running. |
| **CLI** | `accounts/modules/addons/ms365backup/bin/ms365_comet_selection_import.php` |
| **Design** | [specs/2026-07-31-comet-ms365-selection-import-design.md](specs/2026-07-31-comet-ms365-selection-import-design.md) |
| **Plan** | [plans/2026-07-31-comet-ms365-selection-import.md](plans/2026-07-31-comet-ms365-selection-import.md) |
| **Related** | [CUSTOMER_ONBOARDING.md](CUSTOMER_ONBOARDING.md), [PRODUCTION_SSH_ACCESS.md](PRODUCTION_SSH_ACCESS.md) |

## When to use

- Migrating a customer from Comet MS365 (`engine1/winmsofficemail`) to e3 Users MS365 backup
- You already have an e3 backup user with **tenant connected** and **inventory refreshed**
- You want the new job’s checked users/sites/scopes to match Comet’s Protected Item selection

## Prerequisites

1. **e3 backup user** exists (WHMCS client + `s3_backup_users` row; route id is usually `public_id`).
2. **WHMCS service** for that user is known (e.g. service id `5471`).
3. **Microsoft 365 connected** for that backup user (`ms365_tenant_records.connection_status = connected`).
4. **Inventory refreshed** in the portal (or via inventory refresh tooling) so Graph users/sites exist for ID matching.
5. **Comet user profile JSON** exported from the Comet server (Admin API / console export). Must include `Sources` with at least one `engine1/winmsofficemail` Protected Item.

Run the CLI on the **WHMCS host** that owns the target database (production: see [PRODUCTION_SSH_ACCESS.md](PRODUCTION_SSH_ACCESS.md)). Deploy module code first if the bin script is not on that host yet.

## What Comet stores (and what it does not)

The full Graph directory is **not** in the Comet profile. Selection **is**:

```text
Sources[<guid>].Engine = "engine1/winmsofficemail"
Sources[<guid>].EngineProps.CUSTOM_SETTINGV2   ← JSON string
```

Inside `CUSTOM_SETTINGV2`:

| Field | Meaning |
|--------|---------|
| `Organization` / `WholeOrg` | If true → importer selects all selectable e3 inventory |
| `BackupOptions` | Map of user/group/site ID → service bitmask |
| `MemberBackupOptions` | Group/team IDs → bitmask applied to members (best-effort from inventory) |

Comet service bits:

| Bit | Value | e3 mapping |
|-----|------:|------------|
| Calendar | 1 | `calendar` |
| Contact | 2 | `contacts` |
| Mail | 4 | `mail` |
| SharePoint | 8 | site `files` + `lists` |
| OneDrive | 16 | child `onedrive:…` with `onedrive` + `files` |

Common masks: **31** = full user; **24** = SharePoint + OneDrive; **28** = Mail + SP + OD.

ID shapes in `BackupOptions`:

- Plain GUID → user / mailbox / team / group
- `hostname,siteCollectionGuid,webGuid` → SharePoint site (same shape as Graph / e3 `graph_id`)

**Security:** Profiles often contain `APP_SECRET`, vault keys, and password hashes. Store exports outside the web root, restrict permissions, and rotate any secret that was pasted into chat or tickets. The importer must not log secrets.

## Policy: create new job only

| Mode | Behavior |
|------|----------|
| Default (no `--apply`) | Dry-run: parse, map, print report; **no DB write** |
| `--apply` | Calls `Ms365CustomerJobService::create` only — **never** updates or deletes existing jobs |

If a job already exists for the backup user, it is left untouched. Review the new job in the e3 UI after apply.

## CLI reference

From the WHMCS accounts root (or with absolute paths):

```bash
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/ms365_comet_selection_import.php \
  --comet-profile=/path/to/comet-user-profile.json \
  --whmcs-userid=2269 \
  --service-id=5471 \
  --backup-user-id=E0B22D704ECE1A42C08E0AD2C6 \
  [--schedule-frequency=once_daily] \
  [--timezone=America/Edmonton] \
  [--job-name='M365 (imported from Comet)'] \
  [--max-unmatched-pct=25] \
  [--out-selection=/tmp/selection.json] \
  [--apply] \
  [--json]
```

### Flags

| Flag | Required | Description |
|------|----------|-------------|
| `--comet-profile=` | Yes | Path to Comet user profile JSON |
| `--whmcs-userid=` | Yes | WHMCS client id (`tblclients.id`) |
| `--service-id=` | Yes | WHMCS hosting service id; must link to the backup user |
| `--backup-user-id=` | Yes | e3 `s3_backup_users.public_id` (26-char) **or** numeric `s3_backup_users.id` |
| `--schedule-frequency=` | No | Default `once_daily` (also `twice_daily`). Slot times use `Ms365ScheduleAssigner` evening window — not a hard-coded Comet clock time |
| `--timezone=` | No | Job timezone; else client resolver / profile `LocalTimezone` when available |
| `--job-name=` | No | Display name for the new job |
| `--max-unmatched-pct=` | No | Default `25`. `--apply` refused if unmatched `BackupOptions` keys exceed this % of total |
| `--out-selection=` | No | Write proposed `selected_resource_ids` + `scope_overrides` JSON (dry-run or apply) |
| `--apply` | No | Create the new job |
| `--json` | No | Machine-readable report on stdout |

### Exit behavior

- Non-zero if profile/inventory/validation fails, service linkage mismatch, or `--apply` blocked by unmatched threshold
- Dry-run success still prints unmatched keys for review

## Recommended workflow

### 1. Export Comet profile

From Comet Admin (example server `csw.eazybackup.ca`), export the user profile JSON for the legacy account (e.g. `ITadmin`). Copy to the WHMCS host outside the docroot, e.g. `/root/comet-imports/itadmin-profile.json`, mode `600`.

### 2. Confirm e3 target

In production, confirm:

- WHMCS client id
- Service id
- Backup user `public_id` (Users UI / DB)
- Tenant connected + inventory current for that user

### 3. Dry-run (always first)

```bash
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/ms365_comet_selection_import.php \
  --comet-profile=/root/comet-imports/itadmin-profile.json \
  --whmcs-userid=2269 \
  --service-id=5471 \
  --backup-user-id=E0B22D704ECE1A42C08E0AD2C6 \
  --out-selection=/tmp/itadmin-e3-selection.json \
  --json
```

Review:

- Matched users / sites / teams / groups
- `unmatched_backup_option_keys` and `unmatched_member_roots`
- `missing_onedrive_children`
- Unmatched % vs `--max-unmatched-pct`

If many sites/users are unmatched, refresh e3 inventory and re-run dry-run before apply.

### 4. Apply (create new job)

```bash
php /var/www/eazybackup.ca/accounts/modules/addons/ms365backup/bin/ms365_comet_selection_import.php \
  --comet-profile=/root/comet-imports/itadmin-profile.json \
  --whmcs-userid=2269 \
  --service-id=5471 \
  --backup-user-id=E0B22D704ECE1A42C08E0AD2C6 \
  --job-name='M365 (imported from Comet ITadmin)' \
  --timezone=America/Edmonton \
  --apply \
  --json
```

Note the returned `job_id`. Open **e3 → Users → that user → Jobs** and confirm selection in the wizard/UI. Existing jobs for the same backup user must still be present and unchanged.

### 5. First production example (ITadmin → e3)

| Field | Value |
|--------|--------|
| Comet server (example) | `csw.eazybackup.ca` |
| Comet username | `ITadmin` |
| WHMCS client id | `2269` |
| WHMCS service id | `5471` |
| e3 `public_id` | `E0B22D704ECE1A42C08E0AD2C6` |

## Mapping notes / known limits

- **Personal OneDrive sites (`*-my.sharepoint.com,…`):** Comet lists these under SharePoint Sites (often with the person’s name). e3 usually has no matching `sharepoint_site` row — the person appears under Users & Mailboxes. The importer resolves the Graph drive **owner** and selects that user/mailbox (mail/calendar/contacts + OneDrive when present). Report field: `personal_sites_mapped_to_users`.
- **Tasks:** Comet has no Tasks bit → e3 `tasks` stays off (except when forced via personal-site owner mailbox scopes above).
- **Teams messages:** Comet bitmasks do not model Teams chat separately; team resources get best-effort scopes when a team GUID appears in options.
- **MemberBackupOptions:** Expansion uses membership already present in e3 inventory only (no live Graph member walk in v1). Unresolved roots are reported; they do not always block apply.
- **Whole org:** `Organization` / `WholeOrg` true → `CustomerSelectionCodec::selectAllFromInventory`.
- **Schedule:** New job gets a normal e3 daily (or twice-daily) evening slot unless you change it later in the UI — Comet “Daily 21:30” is not copied bit-for-bit in v1.

## Verification checklist

- [ ] Dry-run unmatched % acceptable
- [ ] `--apply` returned a new `job_id`
- [ ] `s3_cloudbackup_jobs` has a new `source_type=ms365` row for that `backup_user_id`
- [ ] Prior jobs for the same user unchanged
- [ ] UI shows expected users/sites checked
- [ ] Comet profile file removed or secured; secrets rotated if exposed

## Tests

```bash
php accounts/modules/addons/ms365backup/tests/ms365_comet_service_mask_test.php
php accounts/modules/addons/ms365backup/tests/ms365_comet_office365_selection_parser_test.php
php accounts/modules/addons/ms365backup/tests/ms365_comet_selection_mapper_test.php
```

## Out of scope (v1)

- Comet Admin API live fetch (`--comet-username`)
- Exact Comet schedule clock import
- Live Graph expansion for all group members
- Wizard “Import from Comet” UI
- Vault / snapshot / data migration
