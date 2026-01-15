# CometBilling (WHMCS Addon)

Reconcile Comet Backup usage from your servers against Comet Account Portal billing. Track credit pack consumption with FIFO (main vs bonus credits).

## Features

- **Reconciliation**: Compare actual device/addon usage from your Comet servers against Portal billing
- **Credit Pack Tracking**: FIFO consumption tracking for purchased vs bonus credits
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
| `CreditLedger` | FIFO credit lot management and consumption tracking |
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

## Credit Pack Tracking

When you purchase credits from Comet:
- $10,000 spent → $1,000 bonus credit
- Total: $11,000 in credit

The module tracks **FIFO consumption**:
1. Record purchase via Admin UI (Purchases page)
2. Credit lots are created (purchased + bonus)
3. Daily usage is allocated from oldest lots first
4. Dashboard shows when you're consuming bonus credits

### Initial Setup

1. Go to **Credit Lots** page
2. Create an opening balance with your current purchased/bonus balances
3. Or sync lots from existing purchases

## Admin UI Pages

- **Dashboard**: Overview cards (credit balance, runway, portal snapshot, last reconciliation)
- **Reconcile**: Run comparison, view item-by-item variances, report history
- **Credit Lots**: View/manage FIFO lots, create opening balance, sync from purchases
- **Purchases**: Record credit pack purchases
- **Active Services**: View Portal API snapshot data
- **Usage History**: View billing history (if API populates it)
- **API Keys**: Manage additional API tokens (future multi-account support)

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

## Dependencies- PHP 8.2+
- WHMCS with Comet server module (`modules/servers/comet`)
- Comet PHP SDK (via server module's vendor)
- GuzzleHTTP (for Portal API client)
