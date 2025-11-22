## EazyBackup – Billing Notification System

This document explains the architecture, data model, configuration, and operations of the Billing Notification System added to the eazyBackup WHMCS addon. It covers how notifications are generated from Comet events and database state, how emails are routed and deduplicated, and how to test and troubleshoot.

### Scope and Goals
- Inform customers and MSPs about billing-impacting changes in near real time (devices, add‑ons, storage thresholds/overage).
- Clean audit trail (durable sent log) and zero duplicate emails (idempotency keys).
- Minimal changes to the Comet WebSocket worker; main logic lives in the addon.

---

## Architecture Overview

### Components
- `lib/Notifications/NotificationService.php`
  - Facade with small API: `onDeviceRegistered`, `onAddonEnabled` (via account profile update), `onBackupCompleted`, `scanStorageForUser`.
  - Composes recipients, ensures idempotency, selects the email template, builds merge fields, calls WHMCS `SendEmail`, writes a durable sent-log.

- `lib/Notifications/RecipientResolver.php`
  - Resolves recipients according to policy (primary/billing/technical/custom), deduplicates addresses, and honors Test Mode overrides.

- `lib/Notifications/StorageThresholds.php`
  - Binary TiB math (2^40), threshold calculation (default 90%), and milestone enumeration.

- `lib/Notifications/IdempotencyStore.php`
  - Durable insert with unique keys per `(username, category, threshold_key)`, attaches Email Log IDs when available. Includes Capsule and PDO fallbacks for worker context.

- `lib/Notifications/PricingCalculator.php`
  - Computes price deltas for configurable options by looking up `tblpricing` in client currency and billing cycle.

- `lib/Notifications/TemplateRenderer.php`
  - Resolves template settings to names (ID→name supported), merges variables, and calls WHMCS `SendEmail`.
  - In Test Mode, constructs a custom email (subject/body derived from the template content) and sends to explicit recipients only.

- `lib/Notifications/bootstrap.php`
  - Lazy loader and small shims (`eb_notify_*`) so the worker can call into the service without heavy coupling.

### Data Flow
1. Comet WebSocket event (device/account/job) arrives in worker.
2. Worker persists device/items/vaults and then calls into notification service (via bootstrap shims).
3. Notification service loads defaults and per-event context, resolves recipients, enforces idempotency, selects template, sends email, and writes to `eb_notifications_sent`.
4. Client UI surfaces recent notifications (“Upcoming Charges”).
5. Daily safety-net cron scans storage usage per user to backstop missed events.

---

## Database Model

### Tables (existing in addon)
- `comet_devices` — device inventory (populated from worker & profile sync)
- `comet_items` — protected items inventory (profile sync)
- `comet_vaults` — vault inventory and stats (profile sync)

### Tables (added by notifications)
- `eb_notifications_sent`
  - Tracks each attempted notification for audit and idempotency.
  - Columns:
    - `id` BIGINT PK
    - `service_id` INT NOT NULL — WHMCS service ID
    - `client_id` INT NOT NULL — WHMCS client ID
    - `username` VARCHAR(191) NOT NULL — Comet username
    - `category` ENUM('storage','device','addon') NOT NULL
    - `threshold_key` VARCHAR(191) NOT NULL — idempotency key (e.g., `device:<hash>`, `storage:tib_2`, `addon:disk_image`)
    - `template` VARCHAR(191) NOT NULL — template name for audit
    - `subject` VARCHAR(255) NOT NULL — subject used
    - `recipients` TEXT NOT NULL — final recipient CSV
    - `merge_json` JSON NOT NULL — merge variable snapshot
    - `email_log_id` INT NULL — WHMCS email log linkage when available
    - `status` ENUM('sent','failed') DEFAULT 'sent'
    - `error` TEXT NULL — transport or API error
    - `created_at` DATETIME
    - `updated_at` DATETIME
  - Indexes:
    - `UNIQUE (username, category, threshold_key)` — deduplication
    - `INDEX (service_id, created_at)` — UI queries

Migration is performed via `eazybackup_migrate_schema()` and runs when addon settings are opened or during module activation/upgrade.

---

## Entry Points and Worker Integration

### Worker (Comet WebSocket) — Minimal Edits
File: `bin/comet_ws_worker.php`
- Includes `lib/Notifications/bootstrap.php`.
- Calls service via shims at key events:
  - `SEVT_DEVICE_NEW` → `eb_notify_device_registered($pdo, $profile, $username, $deviceHash, $payload)`
  - `SEVT_ACCOUNT_UPDATED` → `eb_notify_account_updated($pdo, $profile, $username)`
  - Job end (terminal): `SEVT_JOB_COMPLETED|COMPLETE|FINISH|END|FAILED|CANCELLED|ABORTED` → `eb_notify_backup_completed($pdo, $profile, $username)`
- On job-end, the worker first refreshes vault usage (`syncUserVaults`) and then triggers the storage scan.
- For non-success terminal jobs, the worker performs a one-shot delayed re-scan (~3s) to tolerate stats lag.
- When `EB_WS_DEBUG=1`, notification debug is mirrored (`EB_NOTIFY_DEBUG=1`) so storage scan decisions are logged.

The worker remains focused on ingesting events and persisting live state. All email logic lives in notifications components.

### Daily Safety Net (Cron)
File: `bin/notifications_storage_sweep.php`
- Iterates active services for product IDs `{52, 57, 53, 54, 58, 60}` and calls `scanStorageForUser()` to evaluate storage thresholds/overage.
- Use this as a once-daily cron to backstop missed events.
- Supports CLI flags: `--user=<username>` to target a single user and `--debug` to enable verbose logs.

---

## Storage Threshold Logic (Binary TiB)

### Unit and Inputs
- Unit: TiB (2^40).
- Paid tier (integer TiB): from `tblhostingconfigoptions` where `configid=67` (Cloud Storage).
- Usage: sum `comet_vaults.total_bytes` for Comet user where `type IN (1000,1003)` and `is_active=1`.

### Thresholds and Milestones
- Default threshold percent: 90% (configurable).
- Milestones: K ∈ { paidTiB, paidTiB+1, ... } with threshold TiB = `0.90 × K`.
- Trigger when usage first crosses a milestone threshold.
- Warning projections: for milestone `K` warnings, the projected next tier is `K+1` (not simply `ceil(usage)`). Pricing deltas reflect the incremental units from current paid tier to `K+1`.

### Leap Handling
- If a backup jumps usage across multiple milestones in a single check or if usage ≥ paidTiB, send a single `storage_overage` email that states the current usage and paid tier.
- All crossed milestones are recorded as “sent” to prevent duplicates.

---

## Notification Categories and Templates

### Categories
- Device Added — immediate on device registration
- Add-on Enabled — when usage exceeds billed quantity for the add-on (compares WHMCS config options vs Comet usage)
- Storage Warning — at milestone threshold (e.g., 90% of K)
- Storage Overage — usage ≥ paid tier or leap across milestones

### Template Settings (Addon Config)
- `Template: Device Added` (`tpl_device_added`)
- `Template: Add-on Enabled` (`tpl_addon_enabled`)
- `Template: Storage Warning` (`tpl_storage_warning`)
- `Template: Storage Overage` (`tpl_storage_overage`)

Templates can be configured by name or ID; the system resolves IDs to names automatically.

### Merge Fields (by category)
- Device Added: `username`, `service_id`, `client_id` (when known), `device_id`, `device_name`, `subject`, `recipients`
- Add-on Enabled: `username`, `service_id`, `client_id`, `addon_code`, `subject`, `recipients`
- Storage Warning: `username`, `service_id`, `client_id`, `paid_tib`, `current_usage_tib`, `threshold_k_tib`, `projected_tib`, `projected_monthly_delta`, `subject`, `recipients`
- Storage Overage: same as Storage Warning

> Note: You can add more merge fields (e.g., product name, cycle, currency) if needed by extending `NotificationService` and templates.

### Additional Merge Fields (Grace Periods)
- For Device Added and Add‑on Enabled emails, when grace is enabled:
  - `grace_first_seen_at` — first time the device/add-on was seen (UTC)
  - `grace_days` — grace length in days (int)
  - `grace_expires_at` — date when grace expires (UTC)
  - `grace_expires_in_days` — remaining days until expiry (int)

---

## Admin Configuration

In `eazybackup_config()` (Addon Settings):

### Toggles and Thresholds
- `Notify: Storage` (`notify_storage`) — enable/disable storage notifications
- `Notify: Devices` (`notify_devices`) — enable/disable device notifications
- `Notify: Add-ons` (`notify_addons`) — enable/disable add-on notifications
- `Storage Threshold %` (`notify_threshold_percent`) — default 90

### Routing Defaults
- `Recipient Routing` (`notify_routing`): `primary | billing | technical | custom`
- `Custom Recipients` (`notify_custom_emails`) — CSV/SSV list when routing=custom

### Test Mode
- `Notifications Test Mode` (`notify_test_mode`) — route all notifications ONLY to Test Recipient(s); customers receive none
- `Test Recipient(s)` (`notify_test_recipient`) — CSV/SSV emails for test mode
- `Test Client ID (optional)` (`notify_test_client_id`) — optional client ID to associate custom test emails with WHMCS Email Log

### Template Selectors
- `Template: Storage Warning` (`tpl_storage_warning`)
- `Template: Storage Overage` (`tpl_storage_overage`)
- `Template: Device Added` (`tpl_device_added`)
- `Template: Add-on Enabled` (`tpl_addon_enabled`)

### Product-level Gates (Built-in)
- Storage and Device notifications are suppressed for the following packages (unlimited storage; single device):
  - Microsoft 365 Backup — package id 52
  - Microsoft 365 Backup (OBC) — package id 57
- Implementation details:
  - `NotificationService::isStorageDeviceNotificationsDisabled(serviceId)` reads `tblhosting.packageid` and returns true for 52 or 57.
  - When true, `onDeviceRegistered` and `scanStorageForUser` return early, skipping notification sends.
  - Add‑on notifications are not affected by this gate.

---

## Recipient Resolution

### Normal Mode
- Primary — `tblclients.email`
- Billing — contacts with invoice/general flags; primary is included by default
- Technical — contacts with support/general flags; primary is included by default
- Custom — parse CSV/SSV; deduplicate

### Test Mode
- Short-circuits recipient resolution: sends only to `Test Recipient(s)`.
- If none are configured, the send is skipped.

---

## Client and Admin UI

### Client: Upcoming Charges Panel
- Template include: `templates/console/partials/upcoming-charges.tpl`
- Shown on `templates/clientarea/dashboard.tpl` above “Backup Status”.
- Data source: recent rows in `eb_notifications_sent` (last 30 days) for the client’s active services; left-joined with `eb_billing_grace` to surface grace dates.
- When a device/add-on item includes grace fields, the panel displays:
  - “Enabled on <first_seen_at>, billing starts on <grace_expires_at> (grace <grace_days> days)”

### Admin: Notifications Page (basic)
- `pages/notifications.php` — simple listing scoped to a service for quick review.

### Admin: Test Notification Sender (Power Panel → Storage tab)
- Small form to send a test notification using current templates and Test Mode routing.
- Displays the `SendEmail` response inline to aid troubleshooting.

### Admin: Upcoming Charges (Client Services)
- Hook: `accounts/includes/hooks/billingUpcoming_ClientServices.php` injects an “Upcoming Charges” panel on `clientsservices.php`.
- Data source: recent `eb_notifications_sent` for the service, joined with `eb_billing_grace`.
- Includes a “Cooldown (+3 days)” button which calls the addon endpoint to extend `grace_expires_at` for all grace rows tied to the service.
- Endpoint: `addonmodules.php?module=eazybackup&action=billing-cooldown&serviceid=<id>&token=...`

---

## Idempotency & Audit

### Keys
- Device events: `device:<device_hash>`
- Add-on events: `addon:<code>` (e.g., `addon:disk_image`)
- Storage milestones: `storage:tib_<K>`

### Behavior
- Attempt to reserve a `(username,category,key)` before sending; the reservation is created with `status='pending'`.
- If reserved, proceed to `SendEmail`, attach `email_log_id` when available, then mark the row `status='sent'`.
- If duplicate, skip to avoid re-sending.

---

## Grace Periods (Devices & Add‑ons)

### Overview
Grace periods allow a configurable number of days before billing starts for newly detected devices and add‑ons. The system records the first time an entity is seen, computes an expiry date, and surfaces these dates in emails and UI panels.

### Schema
- Table: `eb_billing_grace`
  - `id` BIGINT PK
  - `service_id` INT (0 allowed if unknown at first), `client_id` INT (0 allowed)
  - `username` VARCHAR(191) — Comet username
  - `category` ENUM('device','addon')
  - `entity_key` VARCHAR(191) — device: DeviceID hash; add‑on: `addonCode` or `addonCode@deviceHash` when tied to a device
  - `quantity` INT NULL — reserved for future use; optional
  - `first_seen_at` DATETIME (UTC)
  - `grace_days` INT
  - `grace_expires_at` DATETIME (UTC)
  - `source` VARCHAR(32) — `ws|daily|admin`
  - `created_at`, `updated_at` DATETIME (UTC)
  - Unique: `(username, category, entity_key)`
  - Indexes: `(service_id)`, `(username, category)`

### Admin Configuration
- `Grace Period (days) — Devices` (`grace_days_devices`) — default 0 disables grace
- `Grace Period (days) — Add‑ons` (`grace_days_addons`) — default 0 disables grace

### First‑Seen Upsert & Anti‑Gaming
- Devices: first time a device GUID (hash) is seen, a row is inserted and never reset; removing/re‑adding the device does not move `first_seen_at` or `grace_expires_at`.
- Add‑ons: track by account or per device when possible. For engines tied to a device, the entity_key is `addonCode@deviceHash` to prevent reset by toggling.
- Cooldown: Admin can extend all grace expiries by +3 days for a service from the Client Services page (button powered by an addon endpoint).

### Email Merge Fields
- Device Added / Add‑on Enabled templates receive:
  - `grace_first_seen_at`, `grace_days`, `grace_expires_at`, `grace_expires_in_days`

### Client Dashboard
- The Upcoming Charges panel joins `eb_notifications_sent` with `eb_billing_grace` to show first‑seen and billing start (grace expiry) dates.

### Admin Client Services
- Injected panel shows upcoming items and grace dates; includes a one‑click Cooldown (+3 days) to extend `grace_expires_at` in bulk.

### Activation/Upgrade
- Created via `eazybackup_migrate_schema()` called in `eazybackup_activate()` / `eazybackup_upgrade()`.
- Additive/idempotent; existing installs get columns and indexes added if missing.


## Testing and Troubleshooting

### Worker Debug
- Start worker with environment variables:
  - `EB_WS_DEBUG=1` — verbose logging
  - `EB_DB_DEBUG=1` — DB writes logging
  - Example:
    - `EB_WS_DEBUG=1 EB_DB_DEBUG=0 COMET_PROFILE=cometbackup php comet_ws_worker.php`

### Admin Test Send
- In addon settings (Power Panel → Storage tab), use the “Send Test Notification” form.
- Test Mode must have `Test Recipient(s)` configured.
- Optional `Test Client ID` associates emails with Email Log in WHMCS.
- The inline response shows the `SendEmail` API result; check the WHMCS Email Log.

### Common Pitfalls
- Service mapping: notifications suppress for Suspended/Cancelled; Comet `username` must map to an Active `tblhosting.username`.
- Test Mode recipients missing: results in skipped sends with no Email Log entry.
- Templates not configured: set template selectors to valid template names or IDs.
- Email transport: verify SMTP/gateway by sending a built‑in WHMCS email first.

---

## Extensibility Notes

- Add new categories by:
  1) Creating a template and selector in addon config.
  2) Adding a new `category` string and idempotency key scheme.
  3) Emitting a service method (e.g., `onXyz`) and calling it from the appropriate entry point.

- Enhance merge fields by extending `NotificationService` to include more service/user/product metadata.

---

## File Map (Key Files)

- Worker integration
  - `bin/comet_ws_worker.php`
  - `bin/notifications_storage_sweep.php`

- Notifications core
  - `lib/Notifications/bootstrap.php`
  - `lib/Notifications/NotificationService.php`
  - `lib/Notifications/RecipientResolver.php`
  - `lib/Notifications/StorageThresholds.php`
  - `lib/Notifications/IdempotencyStore.php`
  - `lib/Notifications/PricingCalculator.php`
  - `lib/Notifications/TemplateRenderer.php`

- UI
  - `templates/console/partials/upcoming-charges.tpl`
  - `pages/notifications.php`
  - Admin Power Panel extension in `eazybackup.php` (Storage tab test sender)

- Migrations and config
  - `eazybackup.php` — migrations (`eazybackup_migrate_schema()`), addon settings, dashboard wiring

---

## Versioning & Backward Compatibility

- Version bump in addon configuration reflects the feature addition.
- Existing installs receive migrations on addon settings open; new installs create tables on activation/upgrade.
- The worker edit is intentionally minimal and backward-compatible with existing ingestion.


