# e3 Cloud Backup - Billing & Trial Lifecycle

Canonical reference for the new e3 Cloud Backup billing subsystem introduced
alongside the dedicated `e3 Cloud Backup` WHMCS product.

## TL;DR

- Two WHMCS products serve a single customer: **e3 Object Storage**
  (existing, bills storage usage) and **e3 Cloud Backup** (new, bills compute
  via config options).
- **e3 Object Storage** is usage-gated: `$0` when billable usage is zero;
  `$9` base fee (first 1 TiB included) applies only once real, non-MS365
  usage exists; overage above 1 TiB is unchanged.
- The new product is auto-provisioned by `cloudstorage_activate()` /
  `cloudstorage_upgrade()` with five config options:
  `endpoint`, `disk_image`, `hyperv_vm`, `proxmox_vm`, `vmware_vm`.
- An hourly cron meters real usage from the operational tables
  (`s3_cloudbackup_agents`, `s3_cloudbackup_jobs`, `s3_hyperv_vms`) and writes
  rated lines to `s3_cloudbackup_rated_lines`.
- A daily WHMCS hook + InvoiceCreationPreEmail hook copies those rated lines
  onto invoices, applying per-client / tiered / trial overrides.
- New customers get a 30-day free trial. At trial end: payment method
  present -> converted; no payment method -> suspended (data preserved).

## Database

| Table | Purpose |
| ----- | ------- |
| `s3_cloudbackup_usage_snapshots` | Hourly per-metric qty captures (`MAX(qty)` is used for rating). |
| `s3_cloudbackup_pricing` | Per-client or global price overrides (`flat_unit`, `tiered`, `flat_monthly`). |
| `s3_cloudbackup_rated_lines` | One row per (service, metric, billing_window_start). Drives invoice writes. |
| `s3_cloudbackup_trial_state` | Lifecycle: `trialing -> converted / suspended_no_payment / cancelled`. |

## Pricing decision tree

`E3CloudBackupPricing::resolve($clientId, $metric, $currencyId, $qty, $effectiveDate)`:

1. Per-client effective row in `s3_cloudbackup_pricing`.
2. Global default row (`client_id IS NULL`) in `s3_cloudbackup_pricing`.
3. Fallback to `tblpricing` (the WHMCS-native config option price).

Tier semantics: **volume**. A qty of 30 in tier `[1-10 @ 4.50, 11-50 @ 3.75]`
is billed as `30 x 3.75 = 112.50`. The chosen band's label is written to
`s3_cloudbackup_rated_lines.tier_label` and is reflected in the invoice line
description.

## Cron schedule

```
15  *  * * * php /var/www/eazybackup.ca/accounts/crons/e3_cloudbackup_billing.php
30  3  * * * php /var/www/eazybackup.ca/accounts/modules/addons/cloudstorage/crons/e3_cloudbackup_trial_check.php
```

The InvoiceCreationPreEmail hook (`hooks/e3cb_invoice.php`) runs the rater
once more inside the WHMCS `DailyCronJob` immediately before invoicing so
quantities are always anchored to the day's MAX.

## Trial lifecycle

```
trialing  -> converted              (trial ends + card on file)
trialing  -> suspended_no_payment   (trial ends + no card)
suspended -> converted              (card later added)
suspended -> cancelled              (admin manual action)
```

While a service is `trialing`:

- `s3_cloudbackup_rated_lines.pricing_source` is `trial_zeroed`.
- `line_amount` is forced to `0` (the customer is not billed).
- `unit_price` and `qty` are still recorded - the customer's billing page
  shows what the trial would have cost.

Suspension keeps all `s3_*` data intact. Storage and Cloud Backup services
both move to `domainstatus='Suspended'`. The customer can self-reactivate by
adding a payment method (see `api/cloudbackup_reactivate.php`); the admin
"Cloud Backup Trials" page can force convert / cancel.

## Admin pages

- **addonmodules.php?module=cloudstorage&action=cloudbackup_trials**
  Lifecycle for every service. Convert / cancel / preview the estimated bill.
- **addonmodules.php?module=cloudstorage&action=cloudbackup_pricing**
  Global defaults + per-client overrides with volume tier support.

## Adding a new metric

1. Extend the metric ENUMs in `cloudstorage_ensure_e3cb_billing_schema()`.
2. Add the metric to `E3CloudBackupProductBootstrap::METRICS` (label + default
   price) or `E3BackupUserProductBootstrap::METRICS` for unified-only metrics.
   Run `cloudstorage_upgrade()` so the new config option / pricing row is
   auto-created.
3. Add the metric to `E3CloudBackupBilling::measureForClient()` / `measureForBackupUser()` with the
   query that counts it.
4. Done. The pricing resolver and invoice hook are metric-agnostic.

## Unified e3 Backup User product (backend)

When `e3_backup_user_unified_enabled` is on in cloudstorage addon settings:

- New backup users get one WHMCS **e3 Backup User** service (`pid_e3_backup_user`, $0 recurring).
- All metrics (local agent + MS365 + `saas_connector`) bill via `e3bu_config_option_ids`.
- `s3_backup_users.encryption_mode` + `whmcs_service_id` link each user row to its service.
- e3 CB metering writes `backup_user_id` on usage snapshots and rated lines; legacy per-client e3 CB services keep `backup_user_id=0`.
- MS365 `trialDays()` and OneDrive overage price fall back to `e3cb_trial_days` and `storage_overage_per_gib_cad` for unified services.

Grandfathered clients on standalone `pid_e3_cloud_backup` / `pid_ms365_backup` are unchanged until the rollout flag is enabled for new users only.

## e3 Object Storage (usage-gated base fee)

Metered by `S3Billing` (`lib/Admin/S3Billing.php`), invoked hourly from
`accounts/crons/s3Billing.php`:

| Billable usage (non-MS365 buckets) | Monthly amount |
| ---------------------------------- | -------------- |
| `0` bytes | `$0.00` |
| `> 0` and `<= 1 TiB` | `$9.00` base (`storage_base_fee_cad`) |
| `> 1 TiB` | `$9.00` + overage (`storage_overage_per_gib_cad`) |

- MS365 platform buckets (`e3ms365-*`) are excluded from billable usage, so
  MS365-only unified-signup clients bill `$0` for object storage.
- `tblhosting.amount` on the cloud storage service is the invoiced recurring
  value; `S3Billing` writes it each cron run from live usage (MAX-over-window
  for customers with usage; direct `$0` for live-zero to avoid stale snapshots).
- `tblpricing.monthly` for `pid_cloud_storage` is `$0` so WHMCS does not apply
  an independent catalog base fee; the dynamic amount from `S3Billing` governs.

**One-time reconcile after deploy** (safe to re-run):

```bash
php /var/www/eazybackup.ca/accounts/crons/s3billing_reconcile_zero_usage.php
```

This runs a full billing pass so existing zero-usage services (empty buckets,
MS365-only clients) drop from `$9` to `$0` immediately. Past `$9` charges are
not credited automatically.

## Developer notes

- The hardcoded `$packageId = 48` in `accounts/crons/s3Billing.php` is now
  read from the `pid_cloud_storage` addon setting (with the original value
  as a fallback).
- `BetaGate` exposes the layered visibility check that the Welcome card
  uses to decide whether to show the e3 Cloud Backup option.
- `DeprovisionHelper::resetOnboarding()` clears all billing + onboarding
  bookkeeping for a client so test loops are repeatable.
