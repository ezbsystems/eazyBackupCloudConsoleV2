# Microsoft 365 — e3 Cloud Backup UI specification

**Status:** Phase 4b baseline implemented (2026-06-04)  
**Phase:** [PRODUCT_ROADMAP.md](PRODUCT_ROADMAP.md) § Phase 4b  
**Agent prompt:** [Prompts/ms365_e3_ui_agent_prompt.md](Prompts/ms365_e3_ui_agent_prompt.md)

---

## 1. Product intent (fixed — do not change without approval)

Microsoft 365 backup must feel like a **workload inside e3 Cloud Backup**, not a separate product. Customers use the same e3 chrome (sidebar, cards, modals, run patterns) as agent-based backups. The `ms365backup` addon remains the engine; **cloudstorage** owns all client-area UI.

**Canonical URL:** `index.php?m=cloudstorage&page=e3backup&view=ms365` (may gain sub-views in 4b, e.g. `view=ms365_runs`).

**Style authority:** `modules/addons/eazybackup/Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md`

**Reference implementations in e3 today:**

| Pattern | Template |
|---------|----------|
| Shell + sidebar | `cloudstorage/templates/partials/e3backup_shell.tpl` |
| Jobs list | `cloudstorage/templates/e3backup_jobs.tpl` |
| Live run / progress | `cloudstorage/templates/e3backup_live.tpl` |
| Current M365 MVP | `cloudstorage/templates/e3backup_ms365.tpl` |

---

## 2. What to define (fill in below)

Use this section for wireframes, user stories, copy, and interaction notes. Agents implement only what is written here (plus roadmap boundaries).

### 2.1 Information architecture

- **Sidebar placement:** Existing **Microsoft 365** nav item (`view=ms365`) for health; primary job UX on **Users → User detail → Jobs**.
- **Sub-pages / views:** Create Job menu → **Microsoft 365 Backup** modal wizard (4 steps).
- **Breadcrumbs:** User detail breadcrumb; run history via `view=runs&job_id=…`.
- **Deep links from other e3 areas:** OAuth return to user detail with `ms365_wizard=1`.

### 2.2 Connection & tenant health

- **Per backup user:** `ms365_tenant_records.backup_user_id` links Entra consent to `s3_backup_users`.
- **Connect mode toggle (wizard Step 1):** **Automatic** (default) or **Manual**. Toggle hidden when connected via platform OAuth; switching to manual while OAuth-connected requires disconnect first.
- **Connect automatic (wizard):** Step 1 opens Microsoft admin consent in a **popup window** (`consent_mode=popup`). The parent tab keeps the wizard open with a waiting state until consent completes.
- **Connect manual (wizard):** Step 1 form fields `REGION`, `CLIENT_ID`, `TENANT_ID`, `APP_SECRET` with **Test connection** and **Save credentials**. Save is atomic (tests Graph, then persists encrypted secret, sets `connection_auth_mode = customer_app`, marks connected, bootstraps bucket). Wizard advances to step 2 on success (same as OAuth).
- **Connect completion:** Popup callback renders `e3backup_ms365_connect_popup_bridge.php`, which `postMessage`s the opener; the wizard also polls `ms365_status.php` as a backup. On success, wizard advances to step 2 automatically.
- **Connect fallback:** If the popup is blocked, same-tab redirect (`consent_mode=redirect`) returns to user detail via server-built `buildWizardReturnUrl()`; `sessionStorage` preserves `backupUserId` across the redirect.
- **Connect (MS365 page):** Standalone `e3backup_ms365.tpl` still uses redirect mode to `view=ms365`.
- **Storage:** `e3ms365-{token}` bucket via `Ms365StorageBootstrapService`.
- **Reconnect (revoked consent):** Connection state is DB-backed (`connection_status`). When Graph or token calls fail with auth/consent errors (e.g. 401, `invalid_client`, `AADSTS700016`), `Ms365ConnectionGuard` sets `connection_status = action_required` and a customer-safe `health_error`. UI must **not** show green “Connected” when `needs_reconnect` is true.
- **Status flags:** `ms365_status.php` returns `connected` (only when `connection_status === connected`), `needs_reconnect` (when `action_required`), `connection_auth_mode` (`platform_consent` \| `customer_app` \| `none`), `credentials_preview` (safe prefill for manual form; never includes secret), plus `health_error` for copy.
- **Reconnect CTA:** Wizard step 1 and MS365 Connection card show a warning alert + **Reconnect Microsoft 365** (reuses existing admin consent flow). On successful re-consent, `markConnected()` clears `health_error` and restores `connected`.
- **Connected-state actions:** When `connected && !needs_reconnect`, wizard step 1 and MS365 Connection card show **Connect a different organization** (secondary) and **Disconnect** (ghost/danger). Both require a confirmation modal before proceeding.
- **Disconnect:** Local only via `POST ms365_disconnect.php`. Sets `connection_status = disconnected`, clears `health_error`, pauses active `ms365` jobs for the backup user, clears cached `inventory.json`. Preserves `azure_tenant_id`, bucket, and backup history. Does not revoke Entra admin consent — customer must remove the enterprise app in Microsoft Entra admin center for full removal on Microsoft's side.
- **Switch organization:** Confirm → disconnect → admin consent (`connect()`). New tenant overwrites `azure_tenant_id` on successful consent; user must refresh inventory and review job selections.
- **Disconnect from reconnect state:** Warning card also offers **Disconnect** alongside **Reconnect Microsoft 365**.
- **Reactive only:** No proactive health probe on status load or wizard open; detection runs when inventory refresh/load, backup start, or job save/run hits Graph.

### 2.3 Onboarding

- **Steps:** (1) Connect, (2) Inventory, (3) Schedule, (4) Retention + save.
- **Completion criteria:** Connected + inventory refreshed + job saved.
- **Resume (popup):** No page navigation; wizard stays open and advances when consent succeeds.
- **Resume (redirect fallback):** Wizard reopens at step 2 when `connect_ok=1` on user detail; URL params are stripped after resume.

### 2.4 Inventory

- **Layout:** 50/50 modal — expandable hierarchical inventory left, selection summary right (restore-wizard tree styling).
- **Resource types shown:** Users & mailboxes (with nested Mail, Calendar, Contacts, Tasks, OneDrive), SharePoint sites (Files, Lists), Teams (Metadata, Messages, Files + channels), Groups (Mail, Calendar, Files + planner plans), Planner, OneNote, tenant metadata.
- **Selection:** Parent checkbox selects all sub-components; partial selection shows indeterminate parent; OneDrive is nested under each user (no standalone OneDrive section).
- **Scope:** Job save sends `selected_resource_ids` + `scope_overrides` (per-resource authoritative scope). Plan API validates runnable workloads.
- **Actions:** Search, refresh inventory, expand/collapse rows.
- **Wizard open:** Always `POST ms365_inventory_refresh.php` (Graph discovery) before showing step 2. Cached `GET ms365_inventory.php` load runs only after a successful refresh to populate the full `resources[]` list. On refresh auth failure, wizard returns to step 1 with reconnect CTA.
- **API:** `GET ms365_inventory.php`, `POST ms365_inventory_refresh.php`, `POST ms365_job_plan.php` (plan preview / dedup warnings).

### 2.5 Backup (create job)

- **Wizard:** Modal `ms365_job_wizard.tpl` — not preset-only; custom resource selection.
- **Schedule:** Once daily / twice daily cards; backend assigns 7 PM–11:59 PM slots, minutes 20–40.
- **Overlap:** If a scheduled slot fires while a backup batch for the same job is still `queued`/`starting`/`running`, the cron **skips** that slot (no second batch), records a terminal run-history row (`stats_json.ms365_schedule_skip`), and consumes the minute via `last_scheduled_key`. Manual **Run now** is not overlap-guarded.
- **Retention:** Wizard tiers (1y–7y) map to Kopia Comet-style policies (30 daily + weekly); enforced by ms365-backup-worker via `retention_apply` repo operations.
- **Storage:** Jobs in `s3_cloudbackup_jobs` (`source_type=ms365`, `engine=ms365`).

### 2.6 Runs (history & detail)

- **List:** Same as e3 jobs; **Run history** uses `e3backup_runs.tpl` with batch rows in `s3_cloudbackup_runs`.
- **Live progress:** Manual **Run now** redirects to `view=live&run_id={batch_run_id}` (same `e3backup_live.tpl` as agent backups). Progress/logs/cancel use existing `cloudbackup_*` APIs via `Ms365BatchLiveService` aggregation.
- **Logs (history):** **View log** opens shared `ebE3RunModal` (`cloudbackup_get_run_logs.php`); multi-workload lines prefixed with resource label.
- **Cancel:** Live page **Cancel Run** cancels all active child `ms365_backup_runs` workers (`cloudbackup_cancel_run.php` MS365 branch).
- **Manual run:** Jobs tab **Run now** → `ms365_job_run_now.php` (returns `batch_run_id`).

### 2.7 Storage

<!-- Bucket info, usage, link to browse -->

- **What to show:**
- **Link to e3 bucket browser:**

### 2.8 Restore (Phase 5 — placeholders only in 4b)

<!-- Entry points and navigation; full restore UX is Phase 5 -->

- **Navigation slots reserved:**
- **Out of scope for 4b:**

### 2.9 Notifications & empty states

- **Toasts vs modals:**
- **Global empty state (not connected):**
- **Global empty state (connected, no runs):**

### 2.10 API / data contract changes

List any new or changed `cloudstorage/api/ms365_*.php` fields the UI needs.

| Endpoint | Change |
|----------|--------|
| `ms365_connect_start.php` | POST `user_id`, `return_path`, `consent_mode` (`popup` \| `redirect`) |
| `ms365_connect_test.php` | POST manual credentials test: `user_id`, `region`, `client_id`, `tenant_id`, `app_secret` (optional if saved) → `{ organization }` |
| `ms365_connect_save.php` | POST manual credentials save (atomic test + connect): same fields → `{ ms365 }` status payload |
| `ms365_disconnect.php` | POST `user_id` (optional on legacy MS365 page); pauses jobs, clears inventory cache, returns updated `ms365` status |
| `ms365_status.php` | `needs_reconnect`, `health_error`, `connection_status`, `connection_auth_mode`, `credentials_preview` on status payload |
| `ms365_inventory.php` | GET full `resources[]`; fail responses may include `reconnect_required: true` (HTTP 403) |
| `ms365_inventory_refresh.php` | Success returns inventory; auth failure returns `reconnect_required: true` (HTTP 403) |
| `ms365_job_save.php` | POST create/update job with `selected_resource_ids` + `scope_overrides`; `reconnect_required` on auth failure |
| `ms365_job_plan.php` | POST/GET plan preview: runnable/deferred counts, dedup warnings |
| `ms365_job_get.php` | GET job for edit |
| `ms365_job_run_now.php` | POST manual backup; returns `batch_run_id` for live view redirect; `reconnect_required` on auth failure |
| `ms365_start_backup.php` | Preset backup; `reconnect_required` on auth failure |
| `ms365_batch_run_detail.php` | GET batch child runs + log tail (admin/debug; customer UI uses `cloudbackup_get_run_logs.php`) |
| `cloudbackup_progress.php` etc. | When `s3_cloudbackup_runs.engine=ms365`, aggregate child MS365 workload progress/logs |

---

## 3. Wireframes & mockups

<!-- Attach paths, Figma links, or ASCII sketches -->

---

## 4. Copy deck (customer-facing strings)

<!-- Headlines, button labels, error messages -->

---

## 5. Acceptance criteria (Phase 4b done when…)

- [x] Create Job menu includes Microsoft 365 between Local and SaaS (jobs + user detail).
- [x] 4-step modal wizard with per-backup-user OAuth.
- [x] Inventory picker with categorized sections and selection panel.
- [x] Schedule cards (once/twice daily) without user-chosen start time.
- [x] Retention placeholder cards (no backend).
- [x] Jobs list/edit/run from user detail Jobs tab.
- [x] Run history on `e3backup_runs.tpl` for MS365 jobs.
- [x] Live run view (`e3backup_live.tpl`) + shared log modal for MS365 batch runs.
- [ ] Staging E2E on real tenant.

---

## 6. Out of scope for Phase 4b

- Full admin resource picker (admin addon only)
- Comet / LXD provisioning UI
- Phase 5 restore engine behavior (UI shell/entry only)
- Billing SKU purchase flow (unless explicitly added above)
