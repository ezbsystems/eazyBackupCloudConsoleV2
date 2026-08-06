# Policy Status Report — Design

Date: 2026-08-06  
Status: Approved (pending final review)

## Goal

Admin report in **Comet Billing** that lists Comet Backup accounts on two CSWs with fixed Policy IDs whose last backup state is unhealthy, then cross-references Active Services to show which of those accounts are currently being billed with warning or error last status.

## Servers and policies (v1 constants)

| Server key | Origin | Policy ID |
|------------|--------|-----------|
| `obc` | `https://csw.obcbackup.com/` | `9005920f-fa54-4a22-8844-533bda81da4c` |
| `cometbackup` | `https://csw.eazybackup.ca/` | `0e545d31-e0b3-4b38-8456-0999fa46f588` |

Stored as named constants in the report library (easy to extend later). No UI PolicyID picker in v1.

## Data sources

1. **Live CSW profiles** — `ServerUsageCollector::getCometServer($key)` → `AdminListUsersFull()`. Filter users where `PolicyID` matches the configured ID for that server.
2. **Last backup status** — for each matching user, walk `Sources[*].Statistics.LastBackupJob.Status`. Aggregate to **account** level by taking the **worst** status among sources that have a last job.
3. **Active Services** — latest `cb_active_services` snapshot (`PortalUsageExtractor::getLatestSnapshot()` / max `pulled_at`). Match Comet username to portal account (`tenant_id` / parsed `Account …` from `service_name`).

## Status ranking

Comet job status codes (existing codebase):

| Code | Label | Severity rank (higher = worse) |
|------|-------|--------------------------------|
| 5000 | success | 0 |
| 6000–6002 | running | 1 |
| 7004 | missed | 2 |
| **7001** | **warning** | **3** |
| 7000, 7002, 7003, 7005–7007 | error family | 4 |

**Account last status** = status of the source with the highest severity rank (ties: most recent `StartTime` / `EndTime` if available).

## Report sections

### Section A — Warning accounts

Accounts on the configured Policy IDs whose account last status is **warning** only (`7001`).

Columns:

- Server (label + key)
- Policy ID
- Username (Comet account)
- Sources with last job / sources in warning
- Last job time (from the worst/winning source)
- Status label (`warning`)

### Section B — Billed + warning or error

Same PolicyID universe, restricted to accounts that:

1. Appear in the **latest Active Services** snapshot (any billed line for that account), and
2. Have account last status **warning or error** (severity ≥ warning; i.e. 7001 or error-family codes above).

Columns: Section A columns + billed categories summary + total displayed Active Services amount for that account + snapshot `pulled_at`.

## Matching rules (Active Services)

- Normalize Comet username and portal `tenant_id` / account name with case-insensitive trim.
- An account is “billed” if any latest-snapshot Active Services row resolves to that account (device or booster lines count).
- Match quality: exact tenant/account match preferred; document unmatched PolicyID accounts that have warning/error but no AS row (they appear only in Section A if warning-only, or are omitted from Section B).

## UI

- Nav button: **Policy Status** next to Active Services / Period Compare.
- Action: `policy_status` in `cometbilling.php`.
- Live scan on load: release session + raised time limit (same pattern as Historical Reconcile / Period Compare).
- Summary counts: Section A count, Section B count, per-server breakdown.
- No filters in v1 beyond the fixed PolicyID map (optional refresh button = page reload).

## Implementation sketch

| Piece | Path |
|-------|------|
| Report lib | `lib/PolicyStatusReport.php` |
| Template | `templates/admin/policy_status.tpl.php` |
| Router + nav | `cometbilling.php` |
| Tests | `tests/PolicyStatusReportTest.php` (status ranking, PolicyID filter, AS match, section membership) |
| README | brief bullet under Admin UI Pages |

Reuse: `ServerUsageCollector` server resolution, `PortalUsageExtractor`, existing job-status label helpers if accessible from comet module (`comet_HumanJobStatus` or local map mirroring `7001` → warning).

## Out of scope (v1)

- CSV export
- Configurable PolicyID UI
- Using Pulse / `eb_jobs_recent_24h` instead of live `LastBackupJob`
- Device- or protected-item-level primary rows (account-level only)
- Cron / cached snapshot table

## Success criteria

- Opening **Policy Status** lists warning accounts for both configured Policy IDs across both CSWs.
- Section B lists only accounts that are present in the latest Active Services pull and whose last status is warning or error.
- Unit tests cover worst-status aggregation and section membership without requiring live CSW.
