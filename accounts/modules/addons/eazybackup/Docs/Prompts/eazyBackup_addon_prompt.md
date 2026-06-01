**Environment:** WHMCS on PHP 8.2. Workspace root for this work: `/var/www/eazybackup.ca/accounts`. The **eazyBackup** WHMCS addon lives at `modules/addons/eazybackup/`. It integrates with the **Comet Server** provisioning module (`modules/servers/comet/`, Comet PHP SDK) to manage cloud backup accounts and expose Comet controls in WHMCS instead of sending customers to the native Comet UI.

**Purpose:** Replicate Comet Backup web UI capabilities in WHMCS—protected items, storage vaults, devices, jobs, restores, schedules, etc.—via Comet Admin API + live event ingestion (WebSocket worker → custom DB tables). Near-real-time job/device data uses tables like `eb_jobs_live`, `comet_devices`, `comet_vaults` (see README for full schema list).

**My focus:** New features and bug fixes for the **Comet backup dashboard** surfaces—not Partner Hub / billing unless I say otherwise.

**Primary files I work in:**


| **Area**                       | **Path**                                                                                                         |
| ------------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| Client dashboard               | `modules/addons/eazybackup/templates/clientarea/dashboard.tpl`                                                   |
| Client vaults                  | `modules/addons/eazybackup/templates/clientarea/vaults.tpl`                                                      |
| Admin console: user profile    | `modules/addons/eazybackup/templates/console/user-profile.tpl`                                                   |
| Admin console: global job logs | `modules/addons/eazybackup/pages/console/job-logs-global.php` (+ matching `.tpl` if present)                     |
| JSON/action endpoints          | `modules/addons/eazybackup/pages/console/*.php` (e.g. `device-actions.php`, `job-reports.php`)                   |
| Router                         | `modules/addons/eazybackup/eazybackup.php` (`?a=` routes to `pages/console/…`)                                   |
| Frontend logic                 | `modules/addons/eazybackup/assets/js/` (Alpine factories, table actions—not inline multiline `x-data` in `.tpl`) |
| Comet helpers                  | `modules/servers/comet/summary_functions.php`                                                                    |


**Read first (authoritative):**

1. `modules/addons/eazybackup/Docs/EAZYBACKUP_README.md` — architecture, routing, DB tables, ingestion, conventions.
2. `modules/addons/eazybackup/Docs/StyleGuides/SEMANTIC-THEME-REFERENCE.md` — **required for all UI** (`eb-`* classes, `var(--eb-*)` tokens; Tailwind only for layout).
3. As needed: `Docs/eazybackup-ui-helpers.md` (`window.EB` formatting/status), `Docs/JobReport.md`, `Docs/StyleGuides/User-management-redesign-guide.md` (console user-management patterns).

**Stack & conventions:**

- Templates: **Smarty** (`.tpl`). Use **Smarty-safe syntax**—wrap JS with `{literal}…{/literal}` or avoid `{…}` inside Alpine/JS; space object literals in Alpine (`{ name: x }` not `{name:x}`).
- Frontend: **Alpine.js** + **Tailwind** (compiled semantic layer). Register complex Alpine state in `.js` via `Alpine.data(…)` / `window.*Factory()` on `alpine:init`; keep templates thin.
- Backend: isolate features in `pages/console/<feature>.php`; avoid bloating `eazybackup.php`. Prefer `{include}` partials under `templates/console/partials/`.
- Scripts: `assets/js/` for addon JS; load factories **before** modal partials that use them.
- UI: *No new raw hex / slate- /* `dark:` *variants / copy-pasted button bundles**—use semantic theme per SEMANTIC-THEME-REFERENCE. Include `_ui-tokens.tpl` when rendering outside the main theme shell.
- Scope: minimal diffs; match existing patterns; don’t commit unless I ask.

**When starting a task:** Confirm which page/action (`?a=`), template, and endpoint; skim the relevant section of EAZYBACKUP_README; check SEMANTIC-THEME-REFERENCE for any new/changed UI.