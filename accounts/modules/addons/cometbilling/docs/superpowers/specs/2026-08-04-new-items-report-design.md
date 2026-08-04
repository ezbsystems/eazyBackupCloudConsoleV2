# New Items Report — Design

Date: 2026-08-04  
Status: Approved

## Goal

Admin report answering: how many **new** devices, boosters, and M365 items were **first billed** in a configurable date range.

## Definition

An item is **new** in `[from, to]` when its first positive canonical Bill History charge date
(`MIN(usage_date)` per identity + category on `CanonicalUsage`) falls in that range.

Identity key: `device_id` when present; otherwise `tenant_id` + `item_desc`.  
Category via `ChargeCategoryResolver`.

## Buckets

| Summary | Categories |
|---------|------------|
| Devices | `devices` |
| Boosters | `hyperv_vms`, `vmware_vms`, `proxmox_vms`, `disk_image`, `mssql` |
| M365 | `m365_accounts` — summary = sum of protected accounts on newly first-billed hosts (from qty / "Accounts N" / amount), not host count |

Boosters summary also shows a per-type breakdown.  
`account_plan` / `other` are excluded.

## UI

- Nav: **New Items** next to Usage History / M365 Report
- Date range: presets 30 / 90 / 365 / all + custom From/To (reuse `HistoricalReconciler::resolveDateRange`)
- Default: last 30 days
- Summary counts + optional bucket filter for detail list
- Detail columns: first billed date, bucket, category, tenant, device, description, qty, first amount

## Out of scope (v1)

- Seat/VM quantity growth on existing hosts
- Active Services / inventory diffs
- CSV export
- Device `RegistrationTime` intersection
