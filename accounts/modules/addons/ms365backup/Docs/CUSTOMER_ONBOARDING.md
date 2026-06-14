# MS365 Backup — Customer onboarding

## Overview

Customers onboard from **e3 Cloud Backup → Microsoft 365** (`view=ms365`). Each WHMCS client gets:

1. **Admin consent** to the platform Entra app (no manual secret paste)
2. A row in `ms365_tenant_records` with `azure_tenant_id`, consent metadata, `connection_status`
3. A dedicated **e3 object storage bucket** (`e3ms365-{token}`) provisioned automatically after consent

## Platform Entra app (operations)

Configure in **WHMCS → Addons → MS365 Backup**:

- Platform Entra Client ID / Secret
- Region (Global / USGov / China / Germany)
- Optional redirect URI (default: `SystemURL/index.php?m=cloudstorage&page=e3backup&view=ms365_connect_callback`)

Register the app as **multi-tenant** in Azure. Add redirect URI to the app registration. Grant application permissions per [AZURE_SETUP.md](AZURE_SETUP.md) and grant admin consent once in your home tenant for testing.

## Customer flow

1. Open **e3 Cloud Backup → Microsoft 365**.
2. Click **Connect Microsoft 365** → Microsoft admin consent → return to portal.
3. System stores `azure_tenant_id`, probes Graph health, provisions storage bucket.
3. Click **Refresh inventory** to discover users, sites, and teams from Graph.
4. Run **Start backup** with a preset:
   - **All users — mail + calendar** — every licensed user in inventory
   - **Collaboration** — SharePoint sites, Teams, and M365 groups
   - **Full tenant** — users, OneDrive, collaboration, Planner, OneNote, directory baseline
5. Expand a row in **Run history** to view phase, progress, and live logs.

## WHMCS product linkage

- `ms365_tenant_records.whmcs_client_id` — required
- `whmcs_service_id` — optional link to hosting service row

Legacy `ms365backup` client area URL redirects to the e3 view. Admin addon (`addonmodules.php?module=ms365backup`) remains for engineering and support.

## Storage layout

Objects inside the customer bucket use prefix `{azure_tenant_id}/users/…`, `sites/…`, matching local `StorageLayout` paths under `/var/www/eazybackup/ms365/` for development.

## Restore

Mail restore is available from the e3 MS365 page (target user Graph ID). Additional resource types are planned per restore architecture doc.

## Deprecations

- **Comet / panel.eazybackup.ca** MS365 auth — replaced by in-portal OAuth
- Per-customer Entra app secret paste — optional legacy path only for admin/dev tenant records
