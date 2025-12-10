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

## Recent updates: Trial signup Turnstile (Cloudflare)

Adds Cloudflare Turnstile (captcha) to the trial signup form to reduce automated signups. Keys are stored in WHMCS addon settings and survive module disable/enable.

### Configuration (Addon Settings)
- `turnstile_site_key` — Cloudflare Turnstile site key for the widget
- `turnstile_secret_key` — Cloudflare Turnstile secret for server-side verification

These are defined in the module config and saved in WHMCS `tbladdonmodules` (persist across deactivate/activate). See:

```60:71:accounts/modules/addons/cloudstorage/cloudstorage.php
'turnstile_site_key' => [
    'FriendlyName' => 'Turnstile Site Key',
    'Type' => 'text',
    'Size' => '100',
    'Description' => 'Cloudflare Turnstile site key used on the signup form.'
],
'turnstile_secret_key' => [
    'FriendlyName' => 'Turnstile Secret Key',
    'Type' => 'password',
    'Size' => '100',
    'Description' => 'Secret key for server-side Turnstile verification.'
]
```

### Client Area integration
- The `signup` page receives `TURNSTILE_SITE_KEY` and renders the widget. POST re-renders the same template on validation errors and continues to include the site key.

```308:329:accounts/modules/addons/cloudstorage/cloudstorage.php
case 'signup':
    $pagetitle = 'e3 Storage Signup';
    $templatefile = 'templates/signup';
    $viewVars = [
        'TURNSTILE_SITE_KEY' => $turnstileSiteKey,
    ];
    break;

case 'handlesignup':
    $pagetitle = 'e3 Storage Signup';
    $templatefile = 'templates/signup';
    $routeVars = (function () use ($turnstileSiteKey, $turnstileSecretKey) {
        return require __DIR__ . '/pages/handlesignup.php';
    })();
    $viewVars = is_array($routeVars) ? $routeVars : [];
    if (empty($viewVars['TURNSTILE_SITE_KEY'])) {
        $viewVars['TURNSTILE_SITE_KEY'] = $turnstileSiteKey;
    }
    break;
```

### Template usage
In `templates/signup.tpl` the widget is embedded near the submit button and the script is loaded from Cloudflare. The widget uses the site key variable provided by the route.

```240:248:accounts/modules/addons/cloudstorage/templates/signup.tpl
<!-- Turnstile captcha -->
<div class="pt-2 space-y-2">
  <div class="flex justify-center">
    <div class="cf-turnstile" data-sitekey="{$TURNSTILE_SITE_KEY}" data-theme="light"></div>
  </div>
  {if isset($errors.turnstile)}
    <p class="text-[11px] text-center text-rose-400 mt-1">{$errors.turnstile}</p>
  {/if}
</div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
```

### Server-side verification
`pages/handlesignup.php` validates the `cf-turnstile-response` token against Cloudflare using the secret from settings. On failure, a readable error is returned and the form is re-rendered.

```14:22:accounts/modules/addons/cloudstorage/pages/handlesignup.php
function validateTurnstile($cfToken, $secretKey)
{
    if (!$secretKey) {
        return false;
    }
    $url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
    // ...
}
```

```86:94:accounts/modules/addons/cloudstorage/pages/handlesignup.php
// Turnstile validation
$cfToken = $_POST['cf-turnstile-response'] ?? '';
if (!validateTurnstile($cfToken, $turnstileSecretKey ?? '')) {
    $errors['turnstile'] = 'Captcha validation failed. Please try again.';
}
```

## Recent updates: Trial signup fields and email verification

The public-facing `signup.tpl` used for free e3 Cloud Storage trials has been redesigned and wired to the backend to capture additional context and require email verification before provisioning.

### Frontend fields

The signup form now includes:

- Company / organisation (required)
- Full name (required, split server-side into first and last name)
- Business email (required)
- Phone (required)
- Use case chips (Managed service provider, Software / SaaS vendor, In-house IT / internal team)
- Estimated data to store (slider + TiB number input)
- \"How will you use e3?\" free-text project description (required)
- Sales consent checkbox: \"I’d like someone from sales to contact me about pricing and deployment options.\"

Additional implementation details:

- A hidden `hp_field` honeypot is used to block basic bots (checked in `handlesignup.php`).
- Turnstile captcha is rendered with `{$TURNSTILE_SITE_KEY}` and validated server-side.
- Validation errors are surfaced inline per field and the form preserves previously submitted values via the `POST` view variable.

### Backend mapping and admin notes

`pages/handlesignup.php` now:

- Accepts the new fields: `company`, `fullName`, `email`, `phone`, `useCase`, `storageTiB`, `project`, and `contactSales`.
- Splits `fullName` into `firstname` and `lastname` for WHMCS `AddClient`.
- Applies server-side validation to require Company, Full name, Email, Phone, Project, and a numeric Estimated storage.
- Normalizes the selected use case (`msp`, `saas`, or `internal`).
- Auto-generates a strong random password and a default country (from WHMCS `DefaultCountry`, falling back to `CA`).
- Auto-generates a storage username (used for Ceph and the hosting record) and ensures uniqueness by checking Admin Ops.

The following details are persisted into the WHMCS client admin notes when the client is created:

- Company
- Full name
- Phone
- Use case
- Estimated storage (TiB)
- \"How they will use e3\" project description
- Sales contact consent (Yes/No)

### Email verification flow

Before an order is provisioned, the trial now requires the user to verify their email address.

1. On successful validation, `handlesignup.php`:
   - Creates the WHMCS client via `AddClient` (with the notes containing the trial fields).
   - Generates a secure random verification token.
   - Inserts a row into `cloudstorage_trial_verifications` with:
     - `client_id`, `email`, `token`, `meta` (JSON with username and form context), `created_at`, `expires_at`.
   - Builds a verification URL:
     - `index.php?m=cloudstorage&page=verifytrial&token=<token>`
   - Sends a verification email using the configured General template (see below), passing a custom merge field `{$trial_verification_link}`.
   - Returns `emailSent => true` to the template so the form is replaced with a \"Please check your email\" message.

2. When the user clicks the verification link, `pages/verifytrial.php`:
   - Validates the token (exists, not expired, not consumed).
   - Creates and accepts the e3 Cloud Storage order for the associated client (`AddOrder` + `AcceptOrder`).
   - Updates the hosting record username to the stored/generated storage username.
   - Creates the Ceph user via `AdminOps::createUser` and stores encrypted access keys in `s3_user_access_keys`.
   - Marks the verification record as consumed.
   - Uses `CreateSsoToken` to auto-log the user in and redirect them to the e3 dashboard.

3. On invalid/expired tokens, the user is redirected back to the signup page with an appropriate message.

### Configuration (Addon Settings) for verification email

A new addon setting has been added in `cloudstorage_config()`:

- `trial_verification_email_template` — dropdown populated from WHMCS email templates in the **General** category (via `cloudstorage_get_email_templates()`).

`handlesignup.php` uses this configured template when calling the `SendEmail` localAPI:

- The selected template is resolved by ID to its name.
- The email is sent to the newly created client with `customvars` containing:
  - `trial_verification_link` — the full verification URL.

In the selected email template, you can include the merge field:

- `{$trial_verification_link}` — renders the clickable verification link for the user.

### Database

The trial verification table is created on activation/upgrade:

- Table: `cloudstorage_trial_verifications`
- Columns:
  - `id` (PK)
  - `client_id`
  - `email`
  - `token` (unique)
  - `meta` (JSON for username and trial context)
  - `created_at`
  - `expires_at`
  - `consumed_at`
- Indexed by `client_id` and `email`.

### Troubleshooting
- Widget not visible: ensure `Turnstile Site Key` is set in the addon settings and the domain is allowed in Cloudflare Turnstile configuration.
- Script blocked: confirm no restrictive CSP or ad-blockers are preventing `https://challenges.cloudflare.com/turnstile/v0/api.js`.
- Always use HTTPS on production pages where the widget renders.
- On POST validation errors, the signup page reuses `templates/signup.tpl` and preserves the site key.

## Notable implementation details
- Real-time current object count in emptiness checks now uses paginated S3 `listObjectsV2` to avoid stale DB snapshots.
- Emptiness checks for versions, delete markers, multipart uploads, Legal Hold, and per-version retention use S3 APIs: `listObjectVersions`, `listMultipartUploads`, `getObjectLegalHold`, `getObjectRetention`.
- Admin Ops is preferred for aggregate counts/sizes (efficient, avoids enumerations); S3 is used for per-object/version checks.

## Configuration
Module settings are managed via WHMCS addon settings:
- `s3_endpoint`, `s3_region`
- `ceph_admin_user`, `ceph_access_key`, `ceph_secret_key`
- `encryption_key` (for stored access keys)
- `turnstile_site_key`, `turnstile_secret_key` (for Turnstile captcha)

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

### Trial signup & verification testing

- Submitting the trial form without Company, Name, Email, or Phone is rejected client-side and server-side with inline error messages.
- Required fields (Company, Full name, Email, Phone, Project) and storage estimate (TiB) are all enforced server-side.
- Turnstile must be completed; invalid/missing tokens surface a readable error.
- On successful submission:
  - A WHMCS client is created with admin notes containing the trial context fields.
  - No order or Ceph user is created yet.
  - The user sees a \"Please check your email\" state on the form.
- The verification email uses the configured General template and includes a working `{$trial_verification_link}`.
- Clicking the verification link:
  - Provisions the e3 product order, accepts it, and creates the Ceph user.
  - Auto-logs the user into their account and redirects them to the e3 dashboard.
  - Marks the verification record as consumed so the link cannot be reused.

## Bucket Browser Modernization (UI + Features)

The bucket browser (`templates/browse.tpl`) has been modernized to match the Cloud Backup UI and now includes:

- Top toolbar: Upload, Download (single file), Copy URL, Create Folder, Delete
- Breadcrumb navigation for quick prefix changes
- Multi-select across files and folders
- Object Lock-aware destructive actions (Delete disabled while locked)
- “Show versions” continues to provide per-version operations (restore/delete) with protections

### Direct URL copying
- The browser constructs direct HTTPS URLs and `s3://` URIs using the configured `s3_endpoint`.
- Template receives `S3_ENDPOINT` from the route so URLs can be formed client-side.

### New API endpoints
- `api/createfolder.php` — create an empty “folder” placeholder under a prefix by writing a zero‑byte object named `<prefix>/`.
- `api/downloadobject.php` — securely streams a single object to the browser. Not available for folders.
- `api/deleteprefix.php` — enqueues a background job to delete ALL objects under a given prefix.

### Cron and Database
- New queue table: `s3_delete_prefixes`
  - Columns: `id`, `user_id`, `bucket_name`, `prefix`, `status`, `attempt_count`, `created_at`, `started_at`, `completed_at`, `error`, `metrics`
- New cron: `accounts/crons/s3deleteprefix.php`
  - Processes queued prefix deletes using paginated `listObjectsV2` + `deleteObjects`.
  - Records metrics and errors; marks jobs success/failed.
- Activation/upgrade ensures the queue table exists.

### UX rules
- Delete:
  - Files: handled immediately via `api/deletefile.php`.
  - Folders/prefixes: queued via `api/deleteprefix.php` and processed by the cron.
- Copy URL:
  - Copies both HTTPS and `s3://` forms; folder URLs end with `/`.
- Download:
  - Only enabled for exactly one selected file.

### Testing
- Verify toolbar actions enable/disable correctly with selection, versions toggle, and Object Lock status.
- Create folder under nested prefixes.
- Copy URL(s) and validate both HTTPS and `s3://` forms.
- Download single file.
- Queue a large prefix delete and confirm the cron drains it safely.

## Folder Upload Support

The bucket browser now supports uploading entire folders with their directory structure preserved. This feature allows customers to drag-and-drop folders or use the folder picker to upload complete directory trees to S3.

### Features

1. **Folder Selection Button**: A dedicated "Select Folder" button that opens the browser's native folder picker dialog (using `webkitdirectory` attribute).

2. **Drag-and-Drop Folders**: Users can drag folders directly onto the upload zone. The browser recursively traverses the folder structure using the `FileSystemEntry` API (`webkitGetAsEntry`).

3. **Preserved Directory Structure**: When uploading a folder, the relative path of each file within the folder is preserved as an S3 key prefix. For example:
   - Dragging folder `reports/` containing `2024/january.pdf` and `2024/february.pdf`
   - Results in keys: `reports/2024/january.pdf` and `reports/2024/february.pdf`

4. **Current Path Awareness**: Uploads respect the current browsing location. If you're viewing `backup/daily/`, uploaded files/folders are placed under that prefix.

5. **Upload Queue UI**: A visual queue shows:
   - Total files to upload and completion progress
   - Per-file status (pending, uploading, completed, failed)
   - Progress bar with percentage
   - Cancel all button to abort remaining uploads

6. **Concurrent Uploads**: Up to 3 files upload simultaneously for faster throughput.

### Implementation Details

#### Frontend (`templates/browse.tpl`)

- **Folder input**: `<input type="file" webkitdirectory directory multiple>` enables native folder selection
- **Drag-drop handling**: Uses `DataTransferItem.webkitGetAsEntry()` to detect and traverse dropped folders
- **Recursive traversal**: `readEntriesRecursively()` function walks the directory tree and collects all files with their relative paths
- **Upload manager**: JavaScript class handles queuing, concurrency control, progress tracking, and error handling

#### Backend (`api/uploadobject.php`)

Accepts two new optional POST parameters:
- `relativePath`: The folder path relative to the root of the uploaded folder (e.g., `subfolder/nested`)
- `folder_path`: The current browsing prefix where uploads should be placed

The final S3 key is constructed as: `folder_path/relativePath/filename`

Both paths are sanitized to prevent directory traversal attacks (`../` patterns are stripped).

### Browser Compatibility

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| Folder picker (`webkitdirectory`) | ✅ | ✅ | ✅ | ✅ |
| Drag-drop folders (`webkitGetAsEntry`) | ✅ | ✅ | ✅ | ✅ |

Note: The `webkit` prefix is historical; these APIs are now standardized and work across all modern browsers.

### Limitations

- **File size**: Individual files are still limited by PHP's `upload_max_filesize` and `post_max_size` settings. For very large files, consider the future presigned URL + multipart upload feature.
- **Empty folders**: Empty folders are not uploaded (S3 folders are just key prefixes, so empty folders have no representation).
- **Symlinks**: Symbolic links within folders are not followed.

### Testing Checklist

- [ ] Click "Select Folder" and choose a folder with nested subfolders → all files uploaded with correct paths
- [ ] Drag a folder onto the upload zone → folder structure preserved
- [ ] Drag multiple folders at once → all processed correctly
- [ ] Upload while in a subfolder prefix → files placed under that prefix
- [ ] Cancel uploads mid-queue → remaining files cancelled, completed files retained
- [ ] Upload fails for some files → partial success shown with failure count
- [ ] Empty folder → no files queued (expected behavior)