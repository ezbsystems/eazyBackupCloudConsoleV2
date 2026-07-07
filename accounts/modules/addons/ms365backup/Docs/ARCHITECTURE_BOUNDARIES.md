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
| **Production** | Per-job RGW bucket `e3ms365-{jobHash}` via `Ms365StorageBootstrapService::ensureForJob` (legacy jobs may share tenant bucket); writes through `CloudStorageBackupStorage` |
| **Development** | Local path `/var/www/eazybackup/ms365/` when no bucket is linked |

Object key prefix inside the bucket: Kopia repository packs (not per-user S3 prefixes).

### Vault lifecycle (job delete)

Owned by **cloudstorage** (`Ms365VaultLifecycleService`):

1. Customer deletes MS365 job → job `status=deleted`, vault bucket `recycle_status=recycle` for `ms365_vault_recycle_grace_days` (default 30).
2. Vault data remains in Ceph during grace (no Kopia `retention_apply` on delete).
3. After grace, `ms365_vault_recycle_teardown.php` queues `s3_delete_buckets`; `s3deletebucket.php` removes the bucket.
4. Customer UI: User detail → Vaults (active + recycle bin); early-deletion requests are ops-reviewed manually in v1.

---

## Authentication

- **Platform Entra app** (multi-tenant) with **admin consent** OAuth (`EntraConsentService`).
- Platform `client_id` + secret live in WHMCS **MS365 Backup** addon settings (`PlatformEntraConfig`).
- `ms365_tenant_records` stores `azure_tenant_id`, consent metadata, `connection_status`, and bucket linkage per `whmcs_client_id`.
- Customers do **not** paste app secrets in the admin dashboard in the happy path.

---

## Restore

Backup and restore are separate: PHP **control plane** (`BackupPlanner`, `WorkerClaimService`, queue) vs Go **Kopia worker** execution vs `RestoreOrchestrator` / `RestoreJobService`. Tables: `ms365_backup_runs` vs `ms365_restore_runs`. **All backups and restores use the Kopia Go worker** (no PHP backup engine as of module 1.18.0). Customer UX: e3 Restore tab → MS365 job → snapshot → `ms365_restore_wizard` → live view.

---

## Worker fleet environments (1.50.0+)

| Plane | WHMCS host | Worker fleet DB | Builds / publish |
|-------|------------|-----------------|------------------|
| **Development control** | Dev WHMCS | Dev `ms365_worker_nodes` for dev fleet | Yes — Worker Fleet Builds tab |
| **Production runtime** | Prod WHMCS (`ms365_production_system_url`) | Prod `ms365_worker_nodes`, queue, releases | No — dev pushes releases via `ReleaseSyncService` |

Dev admin UI can **view and operate** the production fleet remotely (`FleetFacade` → `fleet_remote.php`) using `ms365_fleet_deploy_shared_secret`. Proxmox scale-up for production workers still runs on the dev host (shared cluster) but registers nodes against prod.

For **shell access on the production WHMCS host** (browse binary, deploy script, prod CLI diag), use SSH from the dev server: **`Docs/PRODUCTION_SSH_ACCESS.md`** — key `/root/.ssh/whmcs_prod_root`, target `root@192.168.92.75`.

---

## Key entry points

| Surface | Location |
|---------|----------|
| e3 MS365 page | `cloudstorage/pages/e3backup_ms365.php`, `templates/e3backup_ms365.tpl` |
| OAuth callback | `view=ms365_connect_callback` |
| Bridge | `cloudstorage/lib/Client/Ms365E3Controller.php` |
| Engines | `ms365backup/lib/Ms365Backup/*` |
