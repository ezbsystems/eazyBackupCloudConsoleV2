# MS365 Backup — Architecture boundaries

**Technical split only.** For product vision, phases, features, and agent workflow, read **[PRODUCT_ROADMAP.md](PRODUCT_ROADMAP.md)**. For session status, read **[PROGRESS.md](PROGRESS.md)**.

---

## Module roles

| Module | Responsibility |
|--------|----------------|
| **ms365backup** | Graph backup engines, workers, run/queue DB, admin dev tool, PHP services consumed by cloudstorage |
| **cloudstorage** | e3 Cloud Backup customer UI, Entra OAuth UX, dedicated RGW bucket provisioning (`Ms365StorageBootstrapService`), `api/ms365_*.php` |
| **eazybackup / Comet** | **Out of scope** for MS365 backup — do not add new MS365 features here |

---

## Customer access

Customers use **e3 Cloud Backup** only:

`index.php?m=cloudstorage&page=e3backup&view=ms365`

Do **not** direct customers to `index.php?m=ms365backup`. That addon’s client area redirects to the e3 view; admin UI (`addonmodules.php?module=ms365backup`) remains for engineering and support.

---

## Storage

| Environment | Backend |
|-------------|---------|
| **Production** | Per-customer RGW bucket `e3ms365-{token}` via `Ms365StorageBootstrapService`; writes through `CloudStorageBackupStorage` |
| **Development** | Local path `/var/www/eazybackup/ms365/` when no bucket is linked |

Object key prefix inside the bucket: `{azure_tenant_id}/users/…`, `sites/…`, etc. (mirrors local layout under the tenant folder).

---

## Authentication

- **Platform Entra app** (multi-tenant) with **admin consent** OAuth (`EntraConsentService`).
- Platform `client_id` + secret live in WHMCS **MS365 Backup** addon settings (`PlatformEntraConfig`).
- `ms365_tenant_records` stores `azure_tenant_id`, consent metadata, `connection_status`, and bucket linkage per `whmcs_client_id`.
- Customers do **not** paste app secrets in the admin dashboard in the happy path.

---

## Restore

Backup and restore are separate: `BackupOrchestrator` vs `RestoreOrchestrator` / `RestoreJobService`, tables `ms365_backup_runs` vs `ms365_restore_runs`. **Production restore is Kopia-only:** Go worker reads snapshot `manifest_id`, writes to Graph via `graphrestore` (skip duplicates by default). Customer UX: e3 Restore tab → MS365 job → snapshot → `ms365_restore_wizard` → live view.

---

## Key entry points

| Surface | Location |
|---------|----------|
| e3 MS365 page | `cloudstorage/pages/e3backup_ms365.php`, `templates/e3backup_ms365.tpl` |
| OAuth callback | `view=ms365_connect_callback` |
| Bridge | `cloudstorage/lib/Client/Ms365E3Controller.php` |
| Engines | `ms365backup/lib/Ms365Backup/*` |
