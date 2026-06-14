# MS365 Tenant Seeder

Admin-only tool to populate a Microsoft 365 **dev/test tenant** with synthetic data for backup and restore QA.

**URL:** `addonmodules.php?module=ms365backup&action=seeder`

## What it creates

| Workload | Auth | Graph operations |
|----------|------|------------------|
| Mail | App-only | `POST /users/{id}/messages` |
| Calendar | App-only | `POST /users/{id}/events` |
| Contacts | App-only | `POST /users/{id}/contacts` |
| To Do tasks | App-only | `POST /users/{id}/todo/lists` + tasks |
| OneDrive files | App-only | `PUT /drives/{id}/root:/path:/content` |
| SharePoint files | App-only | Upload to default document library |
| Teams messages | **Delegated** (seed user) | `POST /teams/{id}/channels/{id}/messages` |

## Profiles

| Profile | Approx. per user / site |
|---------|-------------------------|
| **Light** | 10 mail, 5 events, 10 contacts, 5 tasks, 10 OneDrive files; 5 SP files/site; 3 Teams msgs/channel |
| **Standard** | 50 mail, 20 events, 30 contacts, 15 tasks, 50 files; 20 SP; 10 Teams |
| **Heavy** | Stress-test volumes for performance QA |

## Architecture

- Config: `ms365_seeder_config` (single row)
- Runs: `ms365_seeder_runs`
- Worker: `bin/ms365_seeder_worker.php --run-id=…`
- Progress: `/var/www/eazybackup/ms365/seeder/{runId}/progress.json`
- Code: `lib/Ms365Backup/Seeder/*`

## Setup

Follow [SEEDER_AZURE_SETUP.md](SEEDER_AZURE_SETUP.md) to register the seeder Entra app.

## Typical workflow

1. Configure seeder app credentials → **Test connection**
2. **Connect seed user** (Teams)
3. **Refresh targets** to see user/site/team counts
4. **Start seeding** (background worker)
5. Run **Discovery** + **Backup** on the same tenant to verify backup engines pick up new data

## Out of scope

- Customer e3 UI
- Production customer tenants
- Bulk delete / tenant reset (manual cleanup only)
