# CometBilling (WHMCS Addon)

Reconcile Comet Backup usage from your servers against Comet Account Portal billing. Track credit pack consumption with FIFO (main vs bonus credits).

## Features

- **Reconciliation**: Compare server usage vs portal billing using aligned stored snapshots (default) or live server pulls
- **Credit Pack Tracking**: FIFO consumption tracking for purchased vs bonus credits (auto-allocated on portal pull)
- **Usage Collection**: Daily snapshots from your Comet servers (cometbackup, obc)
- **Portal Data Sync**: Pull active services from Comet Account Portal API
- **Dashboard**: Visual overview of credit balance, runway, and reconciliation status

## Installation

1. Place this folder at `modules/addons/cometbilling`
2. Run `composer install` inside the module directory
3. Activate in WHMCS Admin > Setup > Addon Modules
4. Configure the Portal API token and enable daily pulls

## Configuration

| Setting | Description |
|---------|-------------|
| Portal Base URL | `https://account.cometbackup.com` |
| Portal Token | Your Company API token (with billing report permissions) |
| Enable Daily Pull | Runs portal sync + server usage collection during WHMCS cron |
| HTTP Timeout | Increase if large reports time out (default: 180s) |

## Database Tables

| Table | Purpose |
|-------|---------|
| `cb_credit_purchases` | Manual purchase records |
| `cb_credit_lots` | FIFO credit lots (purchased + bonus) |
| `cb_credit_allocations` | Usage allocation log |
| `cb_active_services` | Portal API snapshots (current billing) |
| `cb_credit_usage` | Usage history (if billing_history API works) |
| `cb_daily_balance` | Daily balance roll-forward |
| `cb_server_usage` | Daily usage snapshots per server |
| `cb_server_usage_combined` | Aggregated daily usage (all servers) |
| `cb_reconciliation_reports` | Saved reconciliation results |
| `cb_settings` | Sync cursors, last-run timestamps, status |

## CLI Scripts

### Pull Portal Data
```bash
php modules/addons/cometbilling/bin/portal_pull.php
```

### Collect Server Usage
```bash
# All servers
php modules/addons/cometbilling/bin/collect_usage.php

# Specific server
php modules/addons/cometbilling/bin/collect_usage.php --server=cometbackup

# Verbose output
php modules/addons/cometbilling/bin/collect_usage.php --verbose
```

### Import Purchase History CSV
```bash
# Import purchases and create credit lots
php modules/addons/cometbilling/bin/import_purchases.php /path/to/purchases.csv

# Preview without writing to the database
php modules/addons/cometbilling/bin/import_purchases.php --dry-run /path/to/purchases.csv
```

### Run Reconciliation
```bash
# Display results
php modules/addons/cometbilling/bin/run_reconciliation.php

# Save report to database
php modules/addons/cometbilling/bin/run_reconciliation.php --save

# JSON output
php modules/addons/cometbilling/bin/run_reconciliation.php --json

# Verbose with save
php modules/addons/cometbilling/bin/run_reconciliation.php --verbose --save
```

## Recommended Cron Schedule

```bash
# Pull Portal data daily at 2 AM
0 2 * * * php /var/www/eazybackup.ca/accounts/modules/addons/cometbilling/bin/portal_pull.php

# Collect server usage daily at 1 AM
0 1 * * * php /var/www/eazybackup.ca/accounts/modules/addons/cometbilling/bin/collect_usage.php

# Run reconciliation weekly on Monday at 3 AM
0 3 * * 1 php /var/www/eazybackup.ca/accounts/modules/addons/cometbilling/bin/run_reconciliation.php --save
```

Alternatively, enable "Enable Daily Pull" in addon settings to run both during WHMCS cron.

## Library Classes

| Class | Purpose |
|-------|---------|
| `PortalClient` | HTTP client for Comet Account Portal API |
| `PortalUsageExtractor` | Parse cb_active_services into aggregated totals |
| `ServerUsageCollector` | Collect device/addon counts from Comet servers via Admin API |
| `Reconciler` | Compare server usage vs portal billing, flag discrepancies |
| `Settings` | Addon settings loader (decrypts PortalToken) + cb_settings KV store |
| `CreditLedger` | FIFO credit lot management and consumption tracking |
| `PurchaseCsvImporter` | Import Comet purchase history CSV exports |
| `UsageNormalizer` | Normalize billing history rows |
| `ActiveServicesNormalizer` | Normalize active services rows |

## Reconciliation Categories

| Category | Server Source | Portal Source |
|----------|---------------|---------------|
| Devices | count(Devices) per user | Type='device' rows |
| Hyper-V VMs | TotalVmCount for engine1/hyperv | Type='booster' + 'Hyper-V' |
| VMware VMs | TotalVmCount for engine1/vmware | Type='booster' + 'VMware' |
| Proxmox VMs | TotalVmCount for engine1/proxmox | Type='booster' + 'Proxmox' |
| Disk Image | count of engine1/windisk sources | Type='booster' + 'Disk Image' |
| MS SQL | count of engine1/mssql sources | Type='booster' + 'SQL Server' |
| M365 Accounts | TotalAccountsCount for engine1/winmsofficemail | Type='booster' + 'Office 365' |

## Reconciliation Modes

- **Stored Snapshots (default)**: Uses `cb_server_usage_combined` aligned with the nearest portal `cb_active_services` snapshot. Faster and consistent timing.
- **Live Server Pull**: Calls Comet Admin API directly. Use when you need real-time counts.

Configurable tolerance (±N) treats small variances as warnings rather than errors.

## Credit Pack Tracking

When you purchase credits from Comet:
- $10,000 spent → $1,000 bonus credit
- Total: $11,000 in credit

The module tracks **FIFO consumption**:
1. Record purchase via Admin UI (Purchases page) — credit lots are created automatically
2. Daily portal pull allocates usage from oldest purchased lots first, then bonus
3. Dashboard shows FIFO lot balance vs portal-reconciled balance
4. Allocation history page shows which lots were consumed on each date

### CSV Purchase Import

Export purchase history from the Comet Account Portal as CSV, then import via the **Purchases** admin page or the CLI script above.

| CSV Column | Maps to | Notes |
|------------|---------|-------|
| `Date` | `purchased_at` | Stored at `00:00:00` UTC |
| `Type` | filter | Only `Customer Purchase` rows are imported |
| `Item` | `pack_label` + `pack_units` | e.g. `10,000 Dollars` → units `10000` |
| `Credit Amount` | total credit | Purchased + bonus combined |
| `Cost` | `credit_amount` | Actual USD spent |
| — | `bonus_credit` | `Credit Amount − Cost` |
| — | `currency` | Always `USD` |

Example: Cost `$10,000` + Credit Amount `$11,000` → `credit_amount=10000`, `bonus_credit=1000`.

Re-importing the same file skips duplicates silently (matched by `external_ref` fingerprint or `purchased_at` + amounts). The import summary reports how many rows were imported, skipped, and how many credit lots were created.

### Initial Setup

Use the dashboard setup checklist, or:
1. Configure Portal Token in addon settings
2. Pull portal data and collect server usage
3. Record purchases (lots auto-created) or create opening balance on Credit Lots page
4. Run reconciliation

## Admin UI Pages

- **Dashboard**: Setup checklist, FIFO vs portal balances, sync status, bonus credit warning
- **Data Sync**: Last portal pull / usage collection status with manual triggers
- **Reconcile**: Snapshot or live comparison with tolerance and item drill-down
- **Credit Lots**: View/manage FIFO lots, create opening balance, sync from purchases
- **Allocations**: FIFO allocation history (usage date → lots consumed)
- **Purchases**: Import Comet CSV purchase history or record purchases manually (auto-creates lots)
- **Active Services**: View Portal API snapshot data
- **Usage History**: View billing history
- **M365 Report**: Filter Microsoft 365 Protected Accounts booster billing by date range (latest portal snapshot in period)

### M365 Protected Accounts Report

The **M365 Report** page summarizes Booster (Microsoft 365) Protected Accounts lines from `cb_active_services` portal snapshots.

- Choose **Last 30/60/90 days** or a custom date range
- Uses the **latest** `pulled_at` snapshot within the selected period
- **Total Protected Accounts** = sum of `quantity` per matching line
- **Estimated Monthly Billing** = sum of `amount` on that snapshot (monthly run-rate, not cumulative charges across the period)

CLI:

```bash
php modules/addons/cometbilling/bin/m365_report.php
php modules/addons/cometbilling/bin/m365_report.php --from=2026-06-06 --to=2026-07-06
php modules/addons/cometbilling/bin/m365_report.php --preset=60 --json
```

## Troubleshooting

### Portal API returns empty data
- Verify your API token has billing report permissions
- Check the token is correctly entered (no extra spaces)
- Try increasing HTTP timeout in settings

### Server collection fails
- Ensure Comet server module is configured in WHMCS
- Check server credentials are valid
- Verify server group names contain 'cometbackup' or 'obc'

### Duplicate rows in cb_active_services
- This is expected! Each `pulled_at` is a separate snapshot
- Rows with the same `row_fingerprint` represent the same service at different times

## Dependencies

- PHP 8.1+
- WHMCS with Comet server module (`modules/servers/comet`)
- Comet PHP SDK (via server module's vendor)
- GuzzleHTTP (for Portal API client)
