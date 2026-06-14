# Microsoft 365 — e3 UI agent prompt (Phase 4b)

Use this prompt when **designing or implementing** the unified Microsoft 365 experience inside **e3 Cloud Backup** (`cloudstorage`). For engine/Graph/queue work, use [ms365_product_agent_prompt.md](ms365_product_agent_prompt.md) or [ms365backup_agent_prompt.md](ms365backup_agent_prompt.md).

Copy the block below into a new chat and fill in **Task**.

---

```markdown
You are working on **Phase 4b: Unified e3 Microsoft 365 UX** for eazyBackup.

**Goal:** M365 backup must feel like a **backup engine / workload choice inside e3 Cloud Backup**—not a separate product. Match e3 Jobs, Live progress, and shell patterns.

---

## Read first (mandatory)

1. `modules/addons/ms365backup/Docs/MS365_E3_UI_SPEC.md` — **authoritative UI spec** (product owner fills this; do not invent major UX without spec updates)
2. `modules/addons/ms365backup/Docs/PRODUCT_ROADMAP.md` — Phase 4b section
3. `modules/addons/ms365backup/Docs/PROGRESS.md` — current status
4. `modules/addons/ms365backup/Docs/ARCHITECTURE_BOUNDARIES.md` — cloudstorage = UI, ms365backup = engines
5. `modules/addons/eazybackup/Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md` — **required** for all UI

**Reference templates (read before changing UI):**

- `modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl`
- `modules/addons/cloudstorage/templates/e3backup_jobs.tpl`
- `modules/addons/cloudstorage/templates/e3backup_live.tpl`
- `modules/addons/cloudstorage/templates/e3backup_ms365.tpl` (current MVP to replace/extend)

---

## Module rules

| Change | Module |
|--------|--------|
| Templates, Alpine/JS, `ms365_*` API response shape for UI | **cloudstorage** |
| Graph, backup engines, inventory logic, run queue | **ms365backup** |

Do **not** add customer-facing pages to `ms365backup` client area (redirect stays to e3).

---

## Phase ordering

- **Phase 4b (this work)** — full client-area UX shell and flows per spec
- **Phase 5** — restore depth (uses 4b navigation; do not block 4b on restore engine completeness)
- **Phase 6** — GA hardening

---

## Implementation constraints

- Use semantic `eb-*` classes; no browser `alert()`; use `eb-modal` / toasts like other e3 pages
- Reuse `e3backup_shell.tpl` and existing `Ms365E3Controller` / `api/ms365_*.php` where possible
- Extend APIs only when the spec requires new fields
- Preserve working MVP behavior until the replacement surface is ready (feature-flag or incremental views if helpful)

---

## Before ending session

1. Update `modules/addons/ms365backup/Docs/PROGRESS.md` (session log + Phase 4b status)
2. Update `MS365_E3_UI_SPEC.md` §5 acceptance criteria if items shipped
3. Do not edit PRODUCT_ROADMAP phase definitions unless product owner changed scope

---

## Task

<!-- Product owner: describe what to design or build, e.g. "Fill §2.4 Inventory in MS365_E3_UI_SPEC.md" or "Implement runs list matching e3backup_jobs.tpl" -->

```
