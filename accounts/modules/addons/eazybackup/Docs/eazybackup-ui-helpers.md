# eazyBackup UI Helpers (`eazybackup-ui-helpers.js`)

This document describes the global UI helpers exposed by `eazybackup-ui-helpers.js`, where it lives, how to include it, and how to use its API to keep UI formatting and job status colors consistent across the eazyBackup addon.

## Location
- Script: `accounts/modules/addons/eazybackup/assets/js/eazybackup-ui-helpers.js`
- This document: `accounts/modules/addons/eazybackup/eazybackup-ui-helpers.md`

## Purpose
Provide a single global namespace (`window.EB`) with shared helpers for:
- Normalizing timestamps (seconds/milliseconds/ISO) and formatting dates
- Formatting byte sizes and durations
- Mapping Comet job status codes/strings to human labels
- Providing a single source of truth for status dot colors (Tailwind classes)
- Normalizing job objects with mixed key shapes to a common structure

All UI surfaces (dashboard timeline, historical dots, Job Logs table, Job Report modal) should use these helpers so labels, times, and colors remain consistent.

## Load Order
Because pages (and Alpine components) reference `window.EB`, ensure helpers are loaded before any scripts that use them.

Recommended order in the template head (loaded before Alpine components use EB):
```html
<script src="modules/addons/eazybackup/assets/js/eazybackup-ui-helpers.js" defer></script>
```

Notes:
- If a page initializes Alpine components that use `EB.*` at creation time, include helpers early (e.g., in the main template head) so `EB` is available before the components evaluate.
- Other feature scripts (e.g., `job-reports.js`) should also be loaded after helpers.

## Global Namespace
The script exposes a single global object:

```js
window.EB = {
  toMs, fmtTs, fmtBytes, fmtDur,
  STATUS, STATUS_DOT,
  humanStatus, statusDot,
  normalizeJob
};
```

### API
- `EB.toMs(input)` → milliseconds
  - Accepts seconds (number), milliseconds (number), integer-like strings, or ISO date strings.
  - Returns 0 when input cannot be parsed.

- `EB.fmtTs(ts)` → string
  - Formats a timestamp using `Intl.DateTimeFormat` (dateStyle: medium, timeStyle: short) when available; falls back to `toLocaleString()`.
  - Returns `—` when missing/invalid.

- `EB.fmtBytes(n)` → string
  - Human‑readable bytes with units B/KB/MB/GB/TB/PB.
  - Example: `1536 → "1.5 KB"`.

- `EB.fmtDur(sec)` → string
  - Human‑readable duration. Accepts seconds (or ms if very large); returns strings like `1h 23m 45s`. Returns `—` for non‑positive.

- `EB.STATUS` (object)
  - Map of Comet job status codes to labels, mirroring PHP `comet_HumanJobStatus`:
    - 5000 → Success
    - 6000/6001 → Running
    - 7000 → Timeout
    - 7001 → Warning
    - 7002/7003 → Error
    - 7004/7006 → Skipped
    - 7005 → Cancelled
  - Can be overridden at runtime by setting `window.EB_STATUS_MAP` (see below).

- `EB.STATUS_DOT` (object)
  - Tailwind dot color classes per label:
    - Success → `bg-green-500`
    - Running → `bg-sky-500`
    - Timeout → `bg-amber-500`
    - Warning → `bg-amber-500`
    - Error → `bg-red-500`
    - Skipped/Cancelled → `bg-gray-500`
    - Unknown → `bg-gray-400`

- `EB.humanStatus(codeOrLabel)` → string
  - Converts numeric codes or various label forms (e.g., `"SUCCESS"`, `"ACTIVE"`, `"ERROR"`) to a normalized human label using `EB.STATUS` and internal fallbacks.

- `EB.statusDot(codeOrLabel)` → string
  - Returns the Tailwind dot class from `EB.STATUS_DOT` for the given code/label.

- `EB.normalizeJob(j)` → `{ id, name, status, start, end }`
  - Accepts mixed job shapes (e.g., `JobID/id/GUID`, `ProtectedItem/ProtectedItemDescription/name`, `StartTime/started_at`, `EndTime/ended_at`), returning a consistent object for UI.

## Server‑side Label Parity (Optional)
If you want to guarantee absolute parity with server labels, emit a JSON map from PHP before including the helpers and the script will adopt it:

```html
<script>
  window.EB_STATUS_MAP = { "5000": "Success", "6000": "Running", /* ... */ };
</script>
<script src="modules/addons/eazybackup/assets/js/eazybackup-ui-helpers.js" defer></script>
```

`EB.STATUS` will be merged with `EB_STATUS_MAP` at runtime.

## Usage Examples
- Status dot color for a job object that may have mixed keys:
```html
<span :class="EB.statusDot(job.status)" class="w-2 h-2 rounded-full"></span>
```

- Format a job row in JS:
```js
const j = EB.normalizeJob(rawJob);
const label = EB.humanStatus(j.status);
const start = EB.fmtTs(j.start);
const size  = EB.fmtBytes(rawJob.TotalSize);
```

- 24‑hour timeline (normalize positions, display dots):
```html
<div x-data="{ jobs24h(){ /* filter with EB.toMs(...) */ } }">
  <template x-for="raw in jobs24h()">
    <div x-data="{ j: EB.normalizeJob(raw) }" :class="EB.statusDot(j.status)" class="w-1.5 h-full absolute"></div>
  </template>
</div>
```

## Consumers (examples in this addon)
- `templates/clientarea/dashboard.tpl` (timeline and historical dots)
- `assets/js/job-reports.js` (Job Logs table + modal data population)
- `templates/console/user-profile.tpl` (Job Logs integration)

## Conventions
- Do not duplicate date/bytes/duration helpers or status maps in templates/components—import and use `EB` helpers instead.
- Keep the load order correct: helpers before any code that calls `EB.*`.
- Use `EB.statusDot` for all status dot colors to ensure consistency across the app.
