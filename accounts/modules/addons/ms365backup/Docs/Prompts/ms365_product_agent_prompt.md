# MS365 Backup — Product agent prompt (reusable)

Use this prompt when working on the **sellable MS365 backup product** (customer e3 UI, Entra onboarding, cloud storage, restore, ops). For **engine-only** or admin-addon debugging, you may also use [ms365backup_agent_prompt.md](ms365backup_agent_prompt.md).

Copy the markdown block below into a new chat. Fill in **Task** at the end.

---

```markdown
You are working on the **Microsoft 365 Backup** product for eazyBackup (WHMCS). This is a multi-module effort: backup **engines** live in the `ms365backup` addon; **customer experience** lives in **e3 Cloud Backup** (`cloudstorage`). **Comet / eazybackup OBC MS365 containers are permanently out of scope.**

---

## Session startup (mandatory — read before coding)

1. **`modules/addons/ms365backup/Docs/PRODUCT_ROADMAP.md`** — product vision, goals, phases 0–6 (including **4b**), feature checklist, out of scope. **Primary guide for what to build.**
2. **`modules/addons/ms365backup/Docs/PROGRESS.md`** — where the last agent left off; phase status; known gaps; session log. **Update before you finish your session.**
3. **`modules/addons/ms365backup/Docs/ARCHITECTURE_BOUNDARIES.md`** — module roles, URLs, storage, auth (technical split only).
4. **`modules/addons/ms365backup/Docs/CUSTOMER_ONBOARDING.md`** — customer connect flow and ops checklist.
5. If touching engines, Graph, or admin UI: **`modules/addons/ms365backup/Docs/ARCHITECTURE.md`** and **`AZURE_SETUP.md`**.
6. If touching e3 client UI or cloudstorage integration: skim **`modules/addons/cloudstorage/docs/CLOUD_STORAGE_README.md`** and any topic-specific doc under that folder (see Documentation map below).
7. If adding or changing **client area UI**: read and follow **`modules/addons/eazybackup/Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md`** (required).

Do not start implementation until you have read **PRODUCT_ROADMAP.md** and **PROGRESS.md**.

---

## Environment

| Item | Value |
|------|--------|
| Workspace root | `/var/www/eazybackup.ca/accounts` |
| PHP | 8.2, `declare(strict_types=1);`, namespace `Ms365Backup\` in engine addon |
| Dev backup path (local disk) | `/var/www/eazybackup/ms365/` (not under `eazybackup.ca/`) |
| Customer MS365 UI | `index.php?m=cloudstorage&page=e3backup&view=ms365` |
| Admin / dev engine UI | `addonmodules.php?module=ms365backup` |
| Legacy client URL | `index.php?m=ms365backup` → redirects to e3 MS365 view |

---

## Product roadmap (high level)

Work should align with these phases. Detailed task lists may live in the project plan file (do not edit the plan unless asked); **PROGRESS.md** is the living status.

| Phase | Goal | Owner emphasis |
|-------|------|----------------|
| **0** | Boundaries: ms365backup = engines; cloudstorage = UI + buckets; no Comet | Docs, redirects |
| **1** | Platform Entra app + admin-consent OAuth; `ms365_tenant_records` | ms365backup + cloudstorage callback |
| **2** | Dedicated `e3ms365-{token}` RGW bucket; `CloudStorageBackupStorage` | cloudstorage bootstrap + ms365backup adapter |
| **3** | e3 UI MVP: connect, presets, run history, onboarding | cloudstorage templates/APIs |
| **4** | Queue scale, run search/filters, failed-engine retry, access health | ms365backup + e3 APIs |
| **4b** | **Unified e3 M365 UX** — full client area; M365 as e3 workload | Spec: `MS365_E3_UI_SPEC.md`; prompt: `ms365_e3_ui_agent_prompt.md` |
| **5** | Restore platform (mail first, then files/calendar/Teams) | ms365backup orchestrator + e3 wizard (**after 4b**) |
| **6** | Hardening / GA: security, load test, runbooks | Ops + docs |

**Explicit non-goals:** Comet/LXD MS365 provisioning as the backup engine; `calendarView` Graph delta; full admin resource picker in client area (use presets first); restore from Comet vaults.

---

## Documentation map

### ms365backup (engines + product docs)

| Document | Path |
|----------|------|
| **Product roadmap (goals, phases, features)** | `modules/addons/ms365backup/Docs/PRODUCT_ROADMAP.md` |
| **Progress / handoff (update every session)** | `modules/addons/ms365backup/Docs/PROGRESS.md` |
| Architecture boundaries | `modules/addons/ms365backup/Docs/ARCHITECTURE_BOUNDARIES.md` |
| Engine architecture | `modules/addons/ms365backup/Docs/ARCHITECTURE.md` |
| PRD summary (legacy) | `modules/addons/ms365backup/Docs/PHASE3_PRD.md` |
| Customer onboarding | `modules/addons/ms365backup/Docs/CUSTOMER_ONBOARDING.md` |
| Azure app permissions | `modules/addons/ms365backup/Docs/AZURE_SETUP.md` |
| DB schema | `modules/addons/ms365backup/schema.sql`, `sql/upgrade_*.sql` |

### cloudstorage / e3 Cloud Backup (customer UI + storage)

Root: **`modules/addons/cloudstorage/docs/`**

| Topic | Document |
|-------|----------|
| Addon overview | `CLOUD_STORAGE_README.md` |
| Cloud backup product | `CLOUD_BACKUP.md`, `CLOUD_BACKUP_TASKS.md` |
| e3 onboarding | `E3_CLOUD_BACKUP_ONBOARDING.md`, `BETA_ONBOARDING.md` |
| Users / MSP | `E3_CLOUD_BACKUP_USERS_ARCHITECTURE.md`, `E3_CLOUD_BACKUP_MSP_ARCHITECTURE.md` |
| Billing / metering | `E3_CLOUD_BACKUP_BILLING.md` |
| Client area styling (addon-specific) | `CLIENT_AREA_STYLEGUIDE.MD` |
| Local agent (out of scope unless task says) | `LOCAL_AGENT_OVERVIEW.md`, `LOCAL_AGENT_BUILD.md`, … |
| Hyper-V / NAS / disk image (out of scope unless task says) | `HYPERV_*.md`, `CLOUD_NAS.md`, `LOCAL_AGENT_DISK_IMAGE.md` |
| e3 agent Go prompt (different product surface) | `docs/Prompts/e3agent.md` |

### UI style (mandatory for new client-area UI)

| Document | Path |
|----------|------|
| **Semantic theme (authoritative)** | `modules/addons/eazybackup/Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md` |
| Design tokens | `modules/addons/eazybackup/templates/partials/_ui-tokens.tpl` |
| e3 shell pattern | `modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl` |
| Reference live UI | `modules/addons/cloudstorage/templates/e3backup_live.tpl` |

**UI rules:** Use `eb-*` semantic classes and `var(--eb-*)` tokens. No raw hex, no `bg-slate-*` for themed surfaces, no template-local `<style>` for standard components. Include `_ui-tokens.tpl` in standalone addon templates. Layout may use Tailwind utilities; visual design must use semantic classes (`eb-card`, `eb-btn`, `eb-sidebar-link`, etc.).

---

## Module boundaries

```text
Customer → cloudstorage (e3backup view=ms365, APIs ms365_*)
         → ms365backup (EntraConsentService, engines, queue, runs)
         → cloudstorage RGW (Ms365StorageBootstrapService, e3ms365-* buckets)
```

| Module | Responsibility |
|--------|----------------|
| **ms365backup** | Graph backup engines, `BackupOrchestrator`, queue worker, `ms365_tenant_records`, admin dev UI, services consumed by cloudstorage |
| **cloudstorage** | e3 Cloud Backup UI, OAuth callback route, bucket lifecycle, `Ms365E3Controller` bridge, `api/ms365_*.php` |
| **eazybackup / Comet** | **Do not use** for MS365 backup |

---

## Key implementation paths

### ms365backup (engines)

| Area | Path |
|------|------|
| WHMCS addon entry | `modules/addons/ms365backup/ms365backup.php` |
| Entra / tenant | `lib/Ms365Backup/PlatformEntraConfig.php`, `EntraConsentService.php`, `TenantRecordRepository.php` |
| Storage | `BackupStorageFactory.php`, `CloudStorageBackupStorage.php`, `LocalFilesystemBackupStorage.php` |
| Orchestration | `BackupOrchestrator.php`, `BackupPlanner.php`, `BackupEngineRegistry.php` |
| Customer backup API (PHP) | `CustomerBackupService.php` |
| Queue | `JobQueueRepository.php`, `bin/ms365_queue_worker.php`, `WorkerSpawner.php` |
| Restore | `RestoreOrchestrator.php`, `MailRestoreService.php`, `RestoreRunRepository.php` |
| Ops | `FailedEngineRetryService.php`, `AccessHealthService.php` |
| Admin UI / API | `pages/admin/`, `pages/admin/api.php` |
| Migrations | `sql/upgrade_phase4_entra_oauth.sql`, `upgrade_phase5_restore.sql`, … |

### cloudstorage (e3 MS365 product surface)

| Area | Path |
|------|------|
| Route switch | `cloudstorage.php` → `view=ms365`, `view=ms365_connect_callback` |
| Page controllers | `pages/e3backup_ms365.php`, `pages/e3backup_ms365_connect_callback.php` |
| Template | `templates/e3backup_ms365.tpl` (uses `partials/e3backup_shell.tpl`) |
| Sidebar nav | `templates/partials/e3backup_sidebar.tpl` |
| Bridge | `lib/Client/Ms365E3Controller.php` |
| Bucket bootstrap | `lib/Client/Ms365StorageBootstrapService.php` |
| Customer APIs | `api/ms365_status.php`, `ms365_connect_start.php`, `ms365_start_backup.php`, `ms365_runs_list.php`, `ms365_health.php`, `ms365_retry_run.php`, `ms365_restore_start.php` |
| Provision redirect | `lib/Provision/Provisioner.php` → `provisionMs365` return URL |

---

## Progress and documentation duties (required)

At the **start** of each session:

- Read **`Docs/PROGRESS.md`** and note open gaps relevant to your task.

During and at the **end** of each session:

1. **Update `Docs/PROGRESS.md`:**
   - Adjust the roadmap status table if you complete or materially advance a phase item.
   - Add or refine entries under **Known gaps / next work**.
   - Prepend a **Session log** entry: date, what changed, files touched, what to do next, blockers.
2. **Keep product docs accurate** when behavior changes:
   - `ARCHITECTURE_BOUNDARIES.md`, `CUSTOMER_ONBOARDING.md`, `PHASE3_PRD.md`, `AZURE_SETUP.md` as applicable.
   - For new customer-facing flows, mention them in onboarding or PRD if user-visible.
3. **Do not edit** `.cursor/plans/*.plan.md` unless the user explicitly asks.
4. **Do not git commit** unless the user explicitly asks.

Mark roadmap items complete in **PROGRESS.md** only when implemented and verified (or note “partial” with specifics).

---

## Conventions

- **Minimal diffs**; match existing patterns in the module you touch.
- **Admin UI** uses WHMCS/Bootstrap under `ms365backup` (not the semantic theme).
- **Customer UI** uses e3 semantic theme via `e3backup_shell.tpl`.
- **Secrets:** WHMCS `encrypt()` / `decrypt()`; platform Entra secret in addon module settings.
- **Background jobs:** `WorkerSpawner` + optional `ms365_job_queue`; logs under `/var/www/eazybackup/ms365/_logs/`.
- **Graph:** Follow rules in `ARCHITECTURE.md` (no `calendarView` delta; pagination safety; delta for mail/contacts/tasks).
- **Module version:** Check `ms365backup_config()['version']` in `ms365backup.php` (bump on activate/upgrade when schema changes).

---

## Task

<!-- Fill in for this session -->

**Goal:**

**Roadmap phase(s):** (e.g. Phase 3 — inventory refresh API)

**Constraints:**

**Files or areas likely involved:**

**How to verify:**

**PROGRESS.md updates expected:** (yes — describe what you will log)
```

---

## How this prompt relates to `ms365backup_agent_prompt.md`

| Prompt | Use when |
|--------|----------|
| **ms365_product_agent_prompt.md** (this file) | Product work: e3 UI, OAuth, buckets, customer APIs, restore UX, roadmap phases, cross-module features |
| **ms365backup_agent_prompt.md** | Deep engine work: Graph pagination, single-engine debugging, admin backup picker, CLI worker, phase 2A–2F engine tables |

Always prefer **this product prompt** for new sessions unless the task is narrowly scoped to admin engine debugging.

---

