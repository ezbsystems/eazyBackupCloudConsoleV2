# eazyBackup Dashboard Usage Cards Design

Date: 2026-02-27
Module: `accounts/modules/addons/eazybackup`
Scope: `templates/clientarea/dashboard.tpl`

## Objective

Replace the current top summary cards with richer, chart-enabled cards so users and MSPs can quickly understand backup usage and health trends.

## Approved Outcomes

- Replace existing four cards at `dashboard.tpl:90-116`.
- Remove Users card.
- Keep desktop layout as 4 equal cards (`md:grid-cols-4`).
- Keep eazyBackup visual style (no Comet style cloning).
- Use ApexCharts for chart rendering.
- Aggregate metrics across all active services/usernames for the logged-in WHMCS client.

## Final Card Set

1. **Protected Items**
2. **Devices**
3. **Storage**
4. **Last 24 Hours (Status)** (new)

## Card Requirements

## Protected Items Card

- Show only these engines (fixed order, required-only):
  1. Files and Folders (`engine1/file`)
  2. Disk Image (`engine1/windisk`)
  3. Microsoft Hyper-V (`engine1/hyperv`)
  4. Microsoft SQL Server (`engine1/mssql`)
  5. Microsoft Office 365 (`engine1/winmsofficemail`)
  6. Windows Server System State (`engine1/systemstate`)
  7. Proxmox (`engine1/proxmox`)
- No dynamic "Other" row.

## Devices Card

- Show total devices.
- Show online/offline counts.
- Show 30-day line chart:
  - Registered devices
  - Online devices

## Storage Card

- Show current total storage used.
- Show 30-day storage-used line chart.

## Last 24 Hours (Status) Card

- Show donut chart with strict 5 buckets only:
  - Success
  - Error
  - Warning
  - Missed
  - Running
- Include legend below chart with colors + counts.
- Ignore Timeout/Cancelled/Skipped for donut segmentation.

## Architecture (Hybrid)

1. **Server-rendered first paint**:
   - headline totals and basic counts rendered in `dashboard.tpl`.
2. **Async hydration**:
   - chart series and donut payload fetched after page render.
3. **Frontend chart module**:
   - new dedicated JS file under `templates/assets/js/` for dashboard cards/charts.

This balances fast load with clean separation of chart logic.

## Data Sources

## Existing

- `comet_devices` for current device totals and online status (`is_active`).
- `comet_items` for protected item engine counts.
- `eb_storage_daily` for storage trend history.
- `eb_jobs_recent_24h` plus live running source for 24h status mix.

## New (Required)

Add a new client-scoped rollup for device trends:

- Table: `eb_devices_client_daily`
  - `d` DATE
  - `client_id` INT
  - `registered` INT
  - `online` INT
  - `updated_at` TIMESTAMP
  - PK: (`d`, `client_id`)
- Script: `bin/rollup_devices_client_daily.php`
  - upserts per-client daily device snapshot
  - scoped to active WHMCS services only

Reason: existing `eb_devices_daily` is global and not accurate for per-client trend charts.

## API Contracts

## Server-rendered vars

- `totalDevices`
- `onlineDevices`
- `offlineDevices`
- `totalStorageUsed`
- `protectedItemEngineCounts` (keys for required engines)

## Async metrics payload

- `devices30d`: `[{ d, registered, online }]`
- `storage30d`: `[{ d, bytes_total }]`
- `status24h`: `{ success, error, warning, missed, running }`

All endpoint reads must be authenticated and scoped to current client + active services.

## Error/Empty States

- If async chart fetch fails, cards still show server-rendered totals.
- Show subtle "Trend unavailable" state for failed chart sections.
- If data is missing (new rollup), render "No history yet" chart placeholder.
- No chart errors should impact existing dashboard interactions.

## Performance

- Limit trend payloads to 30 days.
- Use SQL aggregation for chart payloads.
- Defer chart rendering until after first paint.

## Validation Checklist

- Users card removed.
- Protected Items card shows only required seven rows in fixed order.
- Devices card totals and line chart are correct for client scope.
- Storage card total and 30-day trend are correct for client scope.
- Last 24 hours donut has exactly 5 statuses and legend below chart.
- Existing dashboard features continue working:
  - status strip
  - live timeline
  - grouping and filtering

## Out of Scope

- Per-username selector in cards.
- Dynamic additional engine rows.
- Donut slices for Timeout/Cancelled/Skipped.
- Styling parity with Comet UI.

