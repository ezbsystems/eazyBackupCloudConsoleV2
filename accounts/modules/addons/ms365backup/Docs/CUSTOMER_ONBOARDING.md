# MS365 Backup — Customer onboarding

## Overview

### New signup (welcome page)

Trial customers who choose **Microsoft 365 Backup** on [`welcome.tpl`](../../cloudstorage/templates/welcome.tpl) are provisioned by `Provisioner::provisionMs365()`:

1. WHMCS MS365 Backup service (`pid_ms365_backup`, default PID 107) — `AcceptOrder` with `autosetup=false` (no Comet).
2. `s3_backup_users` row (`backup_type=cloud_only`) matching the service username.
3. Trial via `Ms365BillingTrial::startTrial()` (`ms365_trial_days` addon setting).
4. Platform-managed `e3ms365-*` bucket via `Ms365StorageBootstrapService::ensureForBackupUser()` at signup.
5. Redirect to **`view=ms365_getting_started`** (3-step connect → inventory → first backup).

See [`cloudstorage/docs/MS365_ONBOARDING.md`](../../cloudstorage/docs/MS365_ONBOARDING.md).

### Existing / MSP customers

Customers onboard from **e3 Cloud Backup → Users → User detail → New Microsoft 365 backup** (wizard). Each backup user gets:

1. **Automatic (default):** Admin consent to the platform Entra app
2. **Manual (advanced):** Customer-owned Entra app credentials (`REGION`, `CLIENT_ID`, `TENANT_ID`, `APP_SECRET`) entered in wizard Step 1
3. A row in `ms365_tenant_records` with `azure_tenant_id`, `connection_status`, and `connection_auth_mode` (`platform_consent` or `customer_app`)
4. A dedicated **e3 object storage bucket** (`e3ms365-{token}`) — provisioned at **welcome signup** and again ensured after Entra connect for MSP paths

## Platform Entra app (operations)

Configure in **WHMCS → Addons → MS365 Backup**:

- Platform Entra Client ID / Secret
- Region (Global / USGov / China / Germany)
- Optional redirect URI (default: `SystemURL/index.php?m=cloudstorage&page=e3backup&view=ms365_connect_callback`)

Register the app as **multi-tenant** in Azure. Add redirect URI to the app registration. Grant application permissions per [AZURE_SETUP.md](AZURE_SETUP.md) and grant admin consent once in your home tenant for testing.

## Customer flow

### Automatic connect (recommended)

1. Open **Users → User detail → Create Job → Microsoft 365 Backup**.
2. Wizard Step 1: leave **Automatic** selected → **Connect Microsoft 365** → Microsoft admin consent → wizard advances to inventory.
3. Complete inventory, schedule, retention, and save the job.

### Manual connect (advanced)

1. Wizard Step 1: switch to **Manual**.
2. Enter `REGION`, `CLIENT_ID`, `TENANT_ID`, and `APP_SECRET` from a customer Entra app (see [AZURE_SETUP.md](AZURE_SETUP.md)).
3. **Test connection** (optional) then **Save credentials** — save runs an atomic connection test, stores encrypted secret, marks tenant connected, and provisions storage.
4. Wizard advances to inventory when connect succeeds.

### After connect

1. System stores `azure_tenant_id`, provisions storage bucket, and refreshes Graph inventory on wizard Step 2.
2. Run backups from saved jobs or presets on the MS365 jobs surface.

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
- Per-customer Entra app secret paste in admin dev dashboard — customer manual connect in wizard uses the same credential model on `ms365_tenant_records` (`connection_auth_mode = customer_app`)
