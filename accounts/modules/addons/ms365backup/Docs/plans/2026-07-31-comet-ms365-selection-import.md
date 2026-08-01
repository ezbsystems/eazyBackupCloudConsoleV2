# Comet MS365 Selection Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a dry-run-default admin CLI that maps Comet `CUSTOM_SETTINGV2` selection into a **new** e3 MS365 job for WHMCS client `2269` / service `5471` / public_id `E0B22D704ECE1A42C08E0AD2C6`.

**Architecture:** Pure PHP parser + bitmask helpers (no WHMCS) feed a mapper that resolves Comet IDs against e3 inventory; a thin CLI resolves `public_id` → numeric `backup_user_id`, validates service linkage, and only on `--apply` calls `Ms365CustomerJobService::create` (never `update`).

**Tech Stack:** PHP 8.x, existing `Ms365Backup\` autoload, WHMCS Capsule, standalone assert-style tests under `ms365backup/tests/`.

## Global Constraints

- Policy **B**: `--apply` creates a new job only; never update/delete existing jobs.
- Default is dry-run; `--apply` required to write.
- Never log or write `APP_SECRET`, vault keys, or password fields from Comet profiles.
- `--backup-user-id` accepts e3 `public_id` (26-char) or numeric `s3_backup_users.id`.
- Refuse `--apply` when unmatched `BackupOptions` keys exceed `--max-unmatched-pct` (default `25`).
- First production target: `--whmcs-userid=2269 --service-id=5471 --backup-user-id=E0B22D704ECE1A42C08E0AD2C6`.
- Follow design: `docs/superpowers/specs/2026-07-31-comet-ms365-selection-import-design.md`.

## File structure

| File | Responsibility |
|------|----------------|
| `lib/Ms365Backup/Comet/CometServiceMask.php` | `SERVICE_*` bits + decode to named bool flags |
| `lib/Ms365Backup/Comet/CometOffice365SelectionParser.php` | Extract/parse `CUSTOM_SETTINGV2` from user profile JSON |
| `lib/Ms365Backup/Comet/CometSelectionMapper.php` | Map parsed selection + inventory → ids/scopes + report |
| `lib/Ms365Backup/Comet/CometSelectionImportService.php` | Resolve backup user, validate service, load inventory, dry-run/apply |
| `bin/ms365_comet_selection_import.php` | CLI entrypoint |
| `tests/ms365_comet_service_mask_test.php` | Bitmask unit tests |
| `tests/ms365_comet_office365_selection_parser_test.php` | Parser unit tests |
| `tests/ms365_comet_selection_mapper_test.php` | Mapper fixture tests |
| `Docs/PROGRESS.md` | Short progress note after ship |

---

### Task 1: CometServiceMask

**Files:**
- Create: `accounts/modules/addons/ms365backup/lib/Ms365Backup/Comet/CometServiceMask.php`
- Test: `accounts/modules/addons/ms365backup/tests/ms365_comet_service_mask_test.php`

**Interfaces:**
- Produces:
  - `CometServiceMask::CALENDAR = 1`, `CONTACT = 2`, `MAIL = 4`, `SHAREPOINT = 8`, `ONEDRIVE = 16`
  - `CometServiceMask::decode(int $mask): array{calendar: bool, contacts: bool, mail: bool, sharepoint: bool, onedrive: bool}`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/ms365backup_autoload.php';

use Ms365Backup\Comet\CometServiceMask;

$failures = 0;
function assert_true(bool $c, string $m): void {
    global $failures;
    if (!$c) { echo "FAIL: $m\n"; ++$failures; return; }
    echo "OK: $m\n";
}

$d31 = CometServiceMask::decode(31);
assert_true($d31['calendar'] && $d31['contacts'] && $d31['mail'] && $d31['sharepoint'] && $d31['onedrive'], '31 = all bits');

$d24 = CometServiceMask::decode(24);
assert_true(!$d24['mail'] && $d24['sharepoint'] && $d24['onedrive'], '24 = SP+OD');

$d28 = CometServiceMask::decode(28);
assert_true($d28['mail'] && $d28['sharepoint'] && $d28['onedrive'] && !$d28['calendar'], '28 = mail+SP+OD');

$d0 = CometServiceMask::decode(0);
assert_true(!$d0['mail'] && !$d0['onedrive'], '0 = none');

exit($failures > 0 ? 1 : 0);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/ms365backup/tests/ms365_comet_service_mask_test.php`  
Expected: FAIL (class not found)

- [ ] **Step 3: Write minimal implementation**

```php
<?php
declare(strict_types=1);

namespace Ms365Backup\Comet;

final class CometServiceMask
{
    public const CALENDAR = 1;
    public const CONTACT = 2;
    public const MAIL = 4;
    public const SHAREPOINT = 8;
    public const ONEDRIVE = 16;

    /** @return array{calendar: bool, contacts: bool, mail: bool, sharepoint: bool, onedrive: bool} */
    public static function decode(int $mask): array
    {
        return [
            'calendar' => ($mask & self::CALENDAR) !== 0,
            'contacts' => ($mask & self::CONTACT) !== 0,
            'mail' => ($mask & self::MAIL) !== 0,
            'sharepoint' => ($mask & self::SHAREPOINT) !== 0,
            'onedrive' => ($mask & self::ONEDRIVE) !== 0,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/ms365backup/tests/ms365_comet_service_mask_test.php`  
Expected: all OK, exit 0

- [ ] **Step 5: Commit** (only if user asked to commit; otherwise skip)

---

### Task 2: CometOffice365SelectionParser

**Files:**
- Create: `accounts/modules/addons/ms365backup/lib/Ms365Backup/Comet/CometOffice365SelectionParser.php`
- Test: `accounts/modules/addons/ms365backup/tests/ms365_comet_office365_selection_parser_test.php`

**Interfaces:**
- Consumes: profile array shaped like Comet `GetUserProfile`
- Produces: `CometOffice365SelectionParser::parseProfile(array $profile): array` with keys:
  - `source_guid: string`
  - `description: string`
  - `organization: bool`
  - `whole_org: bool`
  - `backup_options: array<string, int>`
  - `member_backup_options: array<string, int>`
  - `local_timezone: string`
  - Throws `\InvalidArgumentException` if no `engine1/winmsofficemail` source or invalid `CUSTOM_SETTINGV2`

- [ ] **Step 1: Write the failing test**

Use a minimal fixture: one Source with Engine `engine1/winmsofficemail`, EngineProps.CUSTOM_SETTINGV2 JSON string containing `BackupOptions` with one user GUID→31 and one site key→24, `MemberBackupOptions` with one group→31, `Organization`/`WholeOrg` false.

Assert counts, masks, and that a profile with no M365 source throws.

- [ ] **Step 2: Run test — expect FAIL (class missing)**

- [ ] **Step 3: Implement parser**

Logic:
1. Prefer first Source where `Engine === 'engine1/winmsofficemail'`
2. `CUSTOM_SETTINGV2` may already be array or JSON string; `json_decode` if string
3. Normalize option maps: keys → string, values → `(int)`
4. Read `LocalTimezone` from profile root when present

- [ ] **Step 4: Run test — expect PASS**

- [ ] **Step 5: Commit** (if requested)

---

### Task 3: CometSelectionMapper

**Files:**
- Create: `accounts/modules/addons/ms365backup/lib/Ms365Backup/Comet/CometSelectionMapper.php`
- Test: `accounts/modules/addons/ms365backup/tests/ms365_comet_selection_mapper_test.php`

**Interfaces:**
- Consumes: parser result + inventory `['resources' => list<resource>]`
- Produces: `CometSelectionMapper::map(array $parsed, array $inventory): array` with:
  - `selected_resource_ids: list<string>`
  - `scope_overrides: array<string, array<string, bool>>`
  - `report: array{matched_users: int, matched_sites: int, matched_teams: int, matched_groups: int, unmatched_backup_option_keys: list<string>, unmatched_member_roots: list<string>, missing_onedrive_children: list<string>, backup_options_total: int, whole_org: bool}`

Mapping rules (from design):

1. If `organization || whole_org`: return `CustomerSelectionCodec::selectAllFromInventory($inventory)` plus report `whole_org: true`.
2. Index inventory by lowercased `graph_id` → list of resources; prefer type order user/mailbox → team → m365_group → sharepoint_site when resolving plain GUIDs.
3. Site keys (contain `,`): match `sharepoint_site`; scopes files/lists from SharePoint bit (both true if SP; files only if OD-only).
4. User/mailbox: parent scopes mail/calendar/contacts from bits; `tasks=false`; if OD bit, also select `onedrive:` child when present.
5. Team/group: select with best-effort scopes (team: messages+metadata+files if mask≠0; group: mail/calendar/files from bits).
6. `MemberBackupOptions`: resolve team/group; expand to member users only when inventory already links them (e.g. resources with matching group membership meta if present — if no membership data, still select the group/team root and list unresolved expansion as unmatched only when root missing).
7. Merge duplicate resource scopes with boolean OR.
8. Build scope overrides via `BackupScope::emptyCapabilityTemplate($type)` then set mapped keys.

- [ ] **Step 1: Write failing fixture test**

Tiny inventory:
- `user:u1` graph_id `aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa`
- `onedrive:u1` parent `user:u1`
- `site:s1` graph_id `contoso.sharepoint.com,bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb,cccccccc-cccc-cccc-cccc-cccccccccccc`

Parsed BackupOptions:
- user GUID → 31
- site key → 24

Assert selected ids include user + onedrive + site; user mail/calendar/contacts true; site files+lists true; unmatched empty.

Second case: unknown GUID → appears in `unmatched_backup_option_keys`.

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement mapper**

- [ ] **Step 4: Run — expect PASS**

- [ ] **Step 5: Commit** (if requested)

---

### Task 4: CometSelectionImportService + CLI

**Files:**
- Create: `accounts/modules/addons/ms365backup/lib/Ms365Backup/Comet/CometSelectionImportService.php`
- Create: `accounts/modules/addons/ms365backup/bin/ms365_comet_selection_import.php`
- Modify: `accounts/modules/addons/ms365backup/Docs/PROGRESS.md` (brief entry)

**Interfaces:**
- `CometSelectionImportService::resolveBackupUser(int $clientId, string $backupUserRef): array{id: int, public_id: string, username: string, whmcs_service_id: int}`
  - Match `s3_backup_users` by `client_id` + (`public_id` = ref OR `id` = (int)ref)
- `assertServiceLinkage(array $user, int $serviceId): void` — require `whmcs_service_id` column equals `$serviceId` when column present; else match `tblhosting.id` username to backup username for that client (fail closed with clear error)
- `run(array $opts): array` where opts include profile path/array, clientId, serviceId, backupUserRef, scheduleFrequency, timezone, jobName, maxUnmatchedPct, apply bool
  - Load profile JSON from file
  - Parse → load inventory via `CustomerInventoryService::loadForBackupUser`
  - Map → compute unmatched pct from `count(unmatched_backup_option_keys) / max(1, backup_options_total)`
  - If apply and over threshold: throw
  - If apply: `Ms365CustomerJobService::create(...)` with mapped selection; return `job_id`
  - Never call `update`

CLI usage (document in file header):

```text
php ms365_comet_selection_import.php \
  --comet-profile=/path/profile.json \
  --whmcs-userid=2269 \
  --service-id=5471 \
  --backup-user-id=E0B22D704ECE1A42C08E0AD2C6 \
  [--schedule-frequency=once_daily] \
  [--timezone=America/Edmonton] \
  [--job-name='...'] \
  [--max-unmatched-pct=25] \
  [--out-selection=/tmp/selection.json] \
  [--apply] \
  [--json]
```

Bootstrap like `ms365_admin.php` (`bootstrap.php` + WHMCS `init.php`).

Redaction: before any debug dump of profile, unset EngineProps keys `APP_SECRET`, and Destination key material fields if dumping destinations.

- [ ] **Step 1: Implement ImportService + CLI** (mapper/parser already tested; add a small unit test for unmatched-pct gate logic as a pure static helper if easy — optional `CometSelectionImportService::unmatchedPct(int $unmatched, int $total): float`)

- [ ] **Step 2: Run unit tests from Tasks 1–3**

```bash
php accounts/modules/addons/ms365backup/tests/ms365_comet_service_mask_test.php
php accounts/modules/addons/ms365backup/tests/ms365_comet_office365_selection_parser_test.php
php accounts/modules/addons/ms365backup/tests/ms365_comet_selection_mapper_test.php
```

Expected: all exit 0

- [ ] **Step 3: Dry-run on production** (after deploy or from prod checkout)

Requires a redacted Comet profile JSON for ITadmin on the host. Example:

```bash
php accounts/modules/addons/ms365backup/bin/ms365_comet_selection_import.php \
  --comet-profile=/root/itadmin-comet-profile.json \
  --whmcs-userid=2269 \
  --service-id=5471 \
  --backup-user-id=E0B22D704ECE1A42C08E0AD2C6 \
  --json
```

Expected: JSON report with matched counts; no new job row.

- [ ] **Step 4: `--apply` only after dry-run review**

Same command + `--apply`. Expected: prints new `job_id`; existing jobs unchanged.

- [ ] **Step 5: Update PROGRESS.md with one short bullet**

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Parse CUSTOM_SETTINGV2 | 2 |
| Bitmask decode | 1 |
| Map to selected_resource_ids + scope_overrides | 3 |
| MemberBackupOptions best-effort | 3 |
| WholeOrg → selectAll | 3 |
| Dry-run default / --apply create only | 4 |
| Validate client/service/public_id | 4 |
| max-unmatched-pct gate | 4 |
| No secret logging | 4 |
| Unit tests | 1–3 |

## Placeholder scan

None intentional — implementer must use concrete code from steps above.

## Type consistency

- Parser output keys: `backup_options`, `member_backup_options`, `whole_org`, `organization`, `local_timezone`, `source_guid`
- Mapper consumes those exact keys
- ImportService passes mapper result into `Ms365CustomerJobService::create` as `selected_resource_ids` / `scope_overrides`
