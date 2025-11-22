## Job Reports: Table and Modal

This document explains how the Job Logs table and the Job Report modal work, the backend endpoints they call, where job details and log entries come from (Comet Admin API), and how the UI is wired so it can be reused on both the profile page and the dashboard.

### Files and Roles
- Backend (AJAX endpoint)
  - `accounts/modules/addons/eazybackup/pages/console/job-reports.php`
    - Actions: `listJobs`, `jobDetail`, `jobLogEntries` (JSON).
    - Scopes requests to the signed‑in client and the specified `serviceId`/username.
  - Router: `accounts/modules/addons/eazybackup/eazybackup.php` → `?a=job-reports`

- Frontend
  - Table + modal logic: `accounts/modules/addons/eazybackup/assets/js/job-reports.js`
    - Factory: `window.jobReportsFactory({})` returns helpers:
      - `makeJobsTable(tableEl, { serviceId, username, totalEl, pagerEl, searchInput })`
      - `openJobModal(serviceId, username, jobId)`
  - Shared modal include: `accounts/modules/addons/eazybackup/templates/console/partials/job-report-modal.tpl`
    - Reusable across pages; contains the modal chrome and summary/log placeholders.

- Profile page (integration example)
  - `accounts/modules/addons/eazybackup/templates/console/user-profile.tpl`
    - Sets `window.EB_JOBREPORTS_ENDPOINT = '{$modulelink}&a=job-reports'`.
    - Includes `assets/js/job-reports.js` and the shared modal partial.
    - Calls `jobReportsFactory().makeJobsTable(...)` to populate the table and wire events.

### Data Sources (Comet Admin API)
The backend endpoint uses the Comet Admin API via the Comet PHP SDK (from the server module) to fetch data:

- `listJobs` (per username/service)
  - Calls `AdminGetJobsForUser($username)` to fetch a list of `BackupJobDetail`.
  - Enriches each row with friendly names:
    - Protected Item Description: `CometItem::getProtectedItemDescriptions($server, $username, [SourceGUID...])`.
    - Device Friendly Name and Vault Description: `AdminGetUserProfileAndHash($username)` (reads `Profile.Devices` and `Profile.Destinations`).
  - Maps job fields to the UI columns and computes derived values like duration.
  - For the current page of rows, fetches `AdminGetJobProperties(GUID)` to fill Vault Size when available.

- `jobDetail`
  - Calls `AdminGetJobProperties($jobId)` for a single job’s full `BackupJobDetail`.
  - Adds `ProtectedItemDescription`, `DeviceFriendlyName`, `VaultDescription`, and friendly `Status`/`Type` strings.

- `jobLogEntries`
  - Calls `AdminGetJobLogEntries($jobId)` and returns a structured `List<JobEntry>`: `{ Time, Severity, Message }`.
  - The frontend renders these with severity filtering and local search.

Notes:
- For completeness, `accounts/modules/addons/eazybackup/pages/console/api.php` also exposes `getJobReport`/`getJobLogRaw` and list endpoints. The current Job Reports UI uses `job-reports.php`; `api.php` is available for other features and potential dashboard aggregation.

### Columns (Table)
The Job Logs table shows the following columns (in order):

1. Username
2. Job ID (GUID)
3. Device
4. Protected Item (Description)
5. Storage Vault
6. Version (ClientVersion)
7. Type (friendly job type)
8. Status (friendly status)
9. Directories
10. Files
11. Size (TotalSize)
12. Storage Vault Size (end size)
13. Uploaded
14. Downloaded
15. Started
16. Ended
17. Duration

Formatting helpers in `job-reports.js` display bytes, timestamps, and durations.

### Sorting, Pagination, Search, and Column Visibility
- Sorting: click a column header (`<th data-sort="...">`) to toggle sort; the frontend sends `sortBy`/`sortDir` to `listJobs`.
- Pagination: the frontend tracks `page`/`pageSize` and renders simple Prev/Next controls.
- Search: a text input filters rows server‑side by a simple contains match across key fields.
- Column visibility: follows the same “View” menu pattern used by the Devices table; body cell visibility mirrors header visibility.

Expected markup hints for the table element used by `makeJobsTable`:
- The table element should have `data-job-table` and contain a `<thead>` with `<th data-sort="Username|JobID|Device|...">` corresponding to the columns above, and a `<tbody>` that the script fills.
- Provide optional companions: total count element (`#jobs-total`), pager container (`#jobs-pager`), and search input (`#jobs-search`).

### Modal Behavior (Shared Partial)
- Include `templates/console/partials/job-report-modal.tpl` once per page.
- Row click to open:
  - `job-reports.js` delegates clicks on `tbody` rows of any `[data-job-table]` to call `openJobModal(serviceId, username, jobId)`.
  - The modal then calls `jobDetail` (summary header) and `jobLogEntries` (log body) via `?a=job-reports`.
- Filtering and search inside the modal:
  - Severity filter (`#jrm-filter`: All, Warnings, Errors) and text search (`#jrm-search`) hide/show log rows client‑side.

### Reuse on Dashboard
- The same assets and modal can be reused on the dashboard:
  - Include `assets/js/job-reports.js` and the modal partial.
  - Set `window.EB_JOBREPORTS_ENDPOINT` to the module link with `&a=job-reports`.
  - For each row, ensure you have `serviceId`, `username`, and `jobId`. Call `openJobModal(serviceId, username, jobId)` to display the report.
- If you need an aggregated Job Logs list (all accounts), you can build the table data from `pages/console/api.php` → `listAllJobs` and still reuse this modal for per‑job details/logs (modal requires the correct `serviceId` for the selected job).

### Security & Scoping
- `job-reports.php` verifies:
  - The caller is authenticated.
  - The `serviceId` belongs to the current client.
  - The `username` matches the WHMCS service username.
- Server credentials are resolved via `comet_ServiceParams($serviceId)` and the Comet PHP SDK (`comet_Server($params)`).

### Status/Type Mapping
- Friendly job type strings are produced by `\Comet\JobType::toString($classification)`.
- Friendly status strings are mapped from status codes inside `job-reports.php` (e.g., `5000 → SUCCESS`, `7002 → ERROR`, `7001 → WARNING`, `6001 → ACTIVE`).

### Legacy Note
- `templates/includes/job-report-modal.tpl` contains an earlier Alpine‑based modal that talks to `?a=api` (`getJobReport`, `getJobLogRaw`). The current implementation standardizes on `?a=job-reports` with `job-reports.js` and the shared partial at `templates/console/partials/job-report-modal.tpl`.


