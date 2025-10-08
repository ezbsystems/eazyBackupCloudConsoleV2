# Cloud Storage (WHMCS Addon)

Cloud Storage is a WHMCS addon module that manages S3-compatible storage on a Ceph RADOS Gateway (RGW). It provides customer self-service for buckets, access keys, subusers, browsing, and usage; and it integrates with Ceph Admin Ops APIs for administrative/statistical functions and the S3 API for data operations.

## What it does
- Customer self-service dashboard for S3 buckets (create, list, browse, delete)
- Access Keys and Subuser management
- Per-bucket usage and transfer stats (historical + live summaries)
- Object Lock and Versioning awareness
- Background deletion workflow for buckets (safe and auditable)

## Key concepts
- Admin Ops API: used for safe, aggregate, and metadata operations (e.g., per-uid bucket stats) without enumerating objects.
- S3 API: used where object-level interaction is required (e.g., checking versions, delete markers, multipart uploads).
- Database: normalized tables record users, buckets, stats, and queued jobs.

## Important file paths

- Entry point and page routing
  - `accounts/modules/addons/cloudstorage/cloudstorage.php`
    - Registers config/activation schema and routes Client Area pages via `cloudstorage_clientarea()`.

- Client controllers
  - `accounts/modules/addons/cloudstorage/lib/Client/BucketController.php` — S3 client interactions, bucket lifecycle, object/version checks, historical usage helpers.
  - `accounts/modules/addons/cloudstorage/lib/Client/DBController.php` — Database helpers for module entities.
  - `accounts/modules/addons/cloudstorage/lib/Client/HelperController.php` — Common helpers (formatting, crypto for keys, etc.).

- Admin Ops integration
  - `accounts/modules/addons/cloudstorage/lib/Admin/AdminOps.php` — Thin wrapper around Ceph Admin Ops endpoints (user, bucket, usage, keys) using AWS-style signing.

- Client Area pages and templates
  - Pages: `accounts/modules/addons/cloudstorage/pages/*.php` (e.g., `pages/buckets.php` builds list + latest DB snapshot for sizes/objects)
  - Templates: `accounts/modules/addons/cloudstorage/templates/*.tpl` (e.g., `templates/buckets.tpl` renders bucket cards + modals)

- API endpoints (Client-area AJAX)
  - `accounts/modules/addons/cloudstorage/api/deletebucket.php` — Queue bucket deletion (with validation for object-locked buckets).
  - `accounts/modules/addons/cloudstorage/api/objectlockstatus.php` — Live, detailed emptiness and Object Lock status for a bucket.
  - `accounts/modules/addons/cloudstorage/api/livebucketstats.php` — Live per-uid Admin Ops aggregation (30s cache) for usage/object counts.
  - `accounts/modules/addons/cloudstorage/api/emptybucket.php` — Queue “empty bucket” background job (non-object-locked buckets) via `s3_delete_buckets`.

- Background / crons
  - `accounts/crons/s3deletebucket.php` — Processes `s3_delete_buckets` queue (deletes contents and/or buckets in background).

## Database schema (created on activation)
Defined in `cloudstorage.php` activation:
- `s3_users` — storage users mapped to WHMCS accounts/tenants
- `s3_buckets` — bucket registry and flags (versioning, object_lock_enabled, is_active)
- `s3_bucket_stats`, `s3_bucket_stats_summary` — storage size/object counters
- `s3_transfer_stats`, `s3_transfer_stats_summary` — transfer and op counters
- `s3_delete_buckets` — queue for background delete jobs
- `s3_subusers`, `s3_subusers_keys` — subuser access
- `s3_bucket_sizes_history` — collected size/object history for analytics
- `s3_historical_stats` — per-user daily historical aggregates

## Recent updates: Bucket delete process

### 1) Object-locked buckets (strict)
- The application will not attempt to delete an object-locked bucket unless it is totally empty.
- Emptiness check validates ALL of the following are clear:
  - Current objects
  - Object versions (including all non-current versions)
  - Delete markers
  - Multipart uploads in progress
  - Legal Holds (none present)
  - Retention under Compliance or Governance (no future retain-until)
- UX improvements in `templates/buckets.tpl`:
  - Delete modal shows “Object Lock enabled” title and a mode chip (Compliance/Governance).
  - Live “Empty Check” panel with a “Check status” button, and human-readable blockers with examples.
  - Guidance section (accordion) on how to empty the bucket.
  - Destructive confirmation requires typing: `DELETE BUCKET <bucket-name>`.
- Server logic:
  - `api/objectlockstatus.php` calls `BucketController::getObjectLockEmptyStatus()` to compute precise status using S3 APIs.
  - `api/deletebucket.php` rejects deletion of locked buckets unless the bucket is fully empty.

### 2) Non-object-locked buckets (customer-driven empty + background job)
- Delete modal now also shows the same live Empty Check for non-locked buckets.
- If the bucket is not empty, we show a convenience “Empty bucket” button.
  - Clicking it reveals a high-risk confirmation with:
    - Lead warning (permanent, versions and delete markers included; replication may propagate)
    - Acknowledgement checkbox
    - Typed phrase: `EMPTY BUCKET <bucket-name>`
  - On confirm, the module queues a background empty job by inserting into `s3_delete_buckets` (processed by cron).
  - Immediate toast: “Empty job queued. We’ve started clearing <bucket-name> in the background. You can close this window; we’ll keep working.”
  - The bucket card shows an “Emptying…” badge while the job is in progress.
- 2FA delete protection:
  - If a per-bucket `two_factor_delete_enabled` flag is present and true, the empty queue request is blocked (returned as a readable error).
- Server logic:
  - `api/emptybucket.php` validates ownership, checks optional 2FA delete flag, and inserts a job into `s3_delete_buckets`.

### 3) Live stats on bucket list
- `api/livebucketstats.php` fetches Admin Ops `/admin/bucket?uid=<uid>&stats=true` in parallel for parent + tenants.
- 30-second session cache prevents hammering RGW during repeated refreshes.
- `templates/buckets.tpl` updates Usage (bytes) and Objects live on page load (falls back to DB snapshot when offline).

### 4) Modal + UX tweaks
- Delete modal constrained height with internal scroll (`max-h-[85vh] overflow-y-auto`).
- Guidance section is now an accordion (collapsed by default).
- Destructive flows require typed phrases; success toasts show in the page chrome, and modals close promptly to surface the toast.

## Notable implementation details
- Real-time current object count in emptiness checks now uses paginated S3 `listObjectsV2` to avoid stale DB snapshots.
- Emptiness checks for versions, delete markers, multipart uploads, Legal Hold, and per-version retention use S3 APIs: `listObjectVersions`, `listMultipartUploads`, `getObjectLegalHold`, `getObjectRetention`.
- Admin Ops is preferred for aggregate counts/sizes (efficient, avoids enumerations); S3 is used for per-object/version checks.

## Configuration
Module settings are managed via WHMCS addon settings:
- `s3_endpoint`, `s3_region`
- `ceph_admin_user`, `ceph_access_key`, `ceph_secret_key`
- `encryption_key` (for stored access keys)

## Development notes
- Key touchpoints for the delete flow:
  - `templates/buckets.tpl` — modal and client logic (status check, blockers, empty/delete flows, toasts)
  - `api/objectlockstatus.php`, `api/deletebucket.php`, `api/emptybucket.php`
  - `lib/Client/BucketController.php` — emptiness and object-lock checks
  - `accounts/crons/s3deletebucket.php` — background processor
- If you rename or move the optional 2FA delete flag, update `api/emptybucket.php` accordingly.
- For very large tenants, consider server-side rate limiting on `livebucketstats.php` beyond the built-in 30s session cache.

## Testing checklist
- Object-locked bucket with Legal Hold/retention: Empty Check shows blockers and Delete remains disabled.
- Object-locked bucket empty: destructive phrase enables Delete and succeeds.
- Non-locked bucket not empty: “Empty bucket” path queues job, shows toast, and adds an “Emptying…” badge.
- Non-locked bucket empty: Delete path allows direct deletion with destructive phrase.
- Live stats appear shortly after page load; DB snapshot shows immediately as a fallback.
