# eazyBackup Addon — NFR System Documentation

## Overview

The NFR (Not‑For‑Resale) system lets MSPs request and run a time‑boxed, quota‑limited, non‑billable product instance through the eazyBackup addon. It adds a client‑facing application form, an admin review/provisioning panel, email notifications, and end‑date reminder automation. It does not automatically suspend or remove accounts at expiry.


## Key Components and File Map

- Backend/router and schema
  - [eazybackup.php](mdc:accounts/modules/addons/eazybackup/eazybackup.php)
    - Adds NFR config fields (module settings)
    - Client route `?m=eazybackup&a=nfr-apply`
    - Admin view Addons → eazyBackup → NFR (`action=powerpanel&view=nfr`)
    - Schema migration for `eb_nfr`
- Helpers
  - [lib/Nfr.php](mdc:accounts/modules/addons/eazybackup/lib/Nfr.php) — config getters, truthy parsing, active‑grant helpers
- Client application UI
  - [pages/client/nfr-apply.php](mdc:accounts/modules/addons/eazybackup/pages/client/nfr-apply.php)
  - [templates/clientarea/nfr-apply.tpl](mdc:accounts/modules/addons/eazybackup/templates/clientarea/nfr-apply.tpl)
- Admin panel (review and actions)
  - [pages/admin/nfr.php](mdc:accounts/modules/addons/eazybackup/pages/admin/nfr.php)
- End‑date reminders (no auto‑suspend/convert)
  - [accounts/crons/nfr_end_reminder.php](mdc:accounts/crons/nfr_end_reminder.php)
- Styling tokens (dark UI)
  - [templates/partials/_ui-tokens.tpl](mdc:accounts/modules/addons/eazybackup/templates/partials/_ui-tokens.tpl)


## Database Schema

Created/ensured by `eazybackup_migrate_schema()` in [eazybackup.php](mdc:accounts/modules/addons/eazybackup/eazybackup.php).

Table: `eb_nfr`
- `id` INT UNSIGNED PK (auto‑increment)
- `client_id` INT UNSIGNED NOT NULL (WHMCS client)
- `product_id` INT UNSIGNED NULL (approved PID)
- `service_id` INT UNSIGNED NULL (set after provisioning)
- `service_username` VARCHAR(255) NULL (WHMCS/comet username)
- `requested_username` VARCHAR(255) NULL (requested at application)
- `requested_password` VARCHAR(255) NULL (requested at application)
- `status` ENUM('pending','approved','provisioned','rejected','suspended','expired','converted','cancelled') NOT NULL DEFAULT 'pending'
- Company/contact fields: `company_name`, `contact_name`, `job_title`, `work_email`, `phone`
- Fit/intent: `markets`, `use_cases`, `platforms`, `virtualization`, `disk_image` (TINYINT 0/1)
- Quota/caps/duration: `requested_quota_gib`, `approved_quota_gib`, `overage` ('block'|'allow_notice'), `device_cap`, `duration_days`
- Dates: `start_date`, `end_date`, `end_reminder_sent_at` (TIMESTAMP NULL)
- `notes` TEXT
- Timestamps: `created_at`, `updated_at`
- Indexes: `idx_nfr_client` (client_id), `idx_nfr_status` (status), `idx_nfr_service` (service_id)


## Module Configuration (Addon Settings)

All settings live under Addons → eazyBackup → Configure.

- NFR: Enable (`nfr_enable`) — yes/no flag controlling client form visibility
- NFR: Product IDs (`nfr_pids`) — comma‑separated WHMCS product IDs eligible for NFR
- NFR: Admin notify email (`nfr_admin_email`) — address to receive application notices and end‑date reminders
- NFR: Require approval (`nfr_require_approval`) — yes/no to require manual approval
  - Note: If multiple PIDs are configured, approval is always required (forced), even if this is set to off
- NFR: Default duration (days) (`nfr_default_duration_days`) — used during approval
- NFR: Default quota (GiB) (`nfr_default_quota_gib`) — used during approval
- NFR: Max active grants per client (`nfr_max_active_per_client`) — integer, default 1
- NFR: Conversion behavior (`nfr_conversion_behavior`) — currently not actioned automatically at end‑date
- NFR: Captcha on client form (`nfr_captcha`) — yes/no, uses Cloudflare Turnstile keys from global addon settings
- NFR: Auto‑create ticket on approval (`nfr_auto_ticket`) — yes/no (creates a low‑priority ticket for coordination)
- NFR: Admin email template (optional) (`nfr_admin_email_template`) — general template selector for application notices
- NFR: End‑date Admin Email Template (`nfr_end_admin_email_template`) — admin template used by end‑date reminder cron

Setting value parsing: yes/no settings accept typical WHMCS values (on/yes/true/1 → enabled).


## Client Application (MSP Flow)

- Route: `index.php?m=eazybackup&a=nfr-apply`
- Controller: [pages/client/nfr-apply.php](mdc:accounts/modules/addons/eazybackup/pages/client/nfr-apply.php)
- Template: [templates/clientarea/nfr-apply.tpl](mdc:accounts/modules/addons/eazybackup/templates/clientarea/nfr-apply.tpl)

Behavior:
1) If NFR is disabled, a friendly “NFR applications are currently closed.” error is shown.
2) If the client already has an active grant (`status` in approved/provisioned and `end_date >= today`), the page shows a status panel instead of the form.
3) Otherwise, the application form is shown with fields:
   - Company name (required), Contact name, Job title, Work email (required), Phone
   - Markets, Use cases, Platforms, Virtualization, Disk Image (Yes/No)
   - Requested storage quota (GiB), Overage handling (Block/Allow with notice)
   - Optional Device cap
   - Username and Password (requested for provisioning)
   - Required: “I agree to NFR terms” checkbox
   - Optional Captcha (enabled via addon settings)
4) On submit:
   - Validates required fields, captcha (when enabled), and active‑grant limit
   - Inserts a new `eb_nfr` row with `status='pending'` (stores `requested_username` and `requested_password`; mirrors username to `service_username` for visibility)
   - Sends notifications:
     - Admin: “New NFR Application from {company}” (SendAdminEmail with custom subject/body)
     - Client: “We received your NFR application …” (optional acknowledgement)
   - Reloads same page with success banner

Styling:
- Uses dark UI tokens: include [templates/partials/_ui-tokens.tpl](mdc:accounts/modules/addons/eazybackup/templates/partials/_ui-tokens.tpl)
- Page/card/field patterns follow the shared styling guide in [STYLING_NOTES.md](mdc:accounts/modules/addons/eazybackup/Docs/STYLING_NOTES.md)


## Admin NFR Panel

- Location: WHMCS Admin → Addons → eazyBackup → NFR
- Controller: [pages/admin/nfr.php](mdc:accounts/modules/addons/eazybackup/pages/admin/nfr.php)
- Navigation: Appears as a tab alongside Storage / Devices / Items / Billing / White‑Label

Tabs:
- Applications — lists all `status='pending'`
- Active NFRs — `status in (approved,provisioned)`
- All — all records, newest first

Columns:
- ID, Client ID, Date Registered (created_at), Username (service_username), Company, Status, Start, End, Quota (GiB), Device Cap, Actions

Notes:
- Client ID on the Active tab links to the client’s services page in admin.

Row Actions:
- Approve — required fields:
  - Product (PID) — if multiple PIDs are configured, a selection is required
  - Duration (days), Approved quota (GiB), Device cap (optional)
  - On success: auto‑provisions the service (AddOrder + AcceptOrder), sets `service_id`/`service_username`, applies Comet quotas (MaximumDevices and StorageLimitBytes), and sets `status='provisioned'`
- Reject — sets `status='rejected'`, emails client
- Provision — manual retry if auto‑provision failed (AddOrder + AcceptOrder + quota apply)
- Suspend — suspends the hosting service (when set) and sets `status='suspended'`
- Resume — clears suspend by setting `status='approved'`
- Update (inline, Active tab) — edit End date, Approved quota (GiB), and Device cap; applies values to Comet user profile immediately
- Expire now — sets `status='expired'` and `end_date=today` (no auto suspend/convert)


## Provisioning Flow

On approval, the system attempts to auto‑provision:
1) Creates an order for the approved PID (`AddOrder`) and accepts it (`AcceptOrder`) with the requested username/password
2) Fetches the created service (by orderid) and stores `service_id` and `service_username`
3) Applies quotas to the Comet user profile:
   - `MaximumDevices` = Device cap (0 → unlimited)
   - For each Destination: `StorageLimitEnabled/StorageLimitBytes` from Approved quota GiB (0 → unlimited)
4) Updates `status='provisioned'`

If auto‑provision fails, the Provision button is available to retry the flow.


## End‑date Reminder (No Auto‑Suspend)

- Script: [accounts/crons/nfr_end_reminder.php](mdc:accounts/crons/nfr_end_reminder.php)
- Runs daily via cron; finds NFRs with `status in (approved,provisioned)`, `end_date <= today`, and no `end_reminder_sent_at`
- Sends an email to the configured “NFR: Admin notify email” using the selected admin template (`nfr_end_admin_email_template`) or a custom subject/body if none is selected
- Marks `end_reminder_sent_at` to avoid duplicate reminders
- No suspension, conversion, or removal is performed automatically


## Emails and Notifications

Admin notifications use WHMCS `SendAdminEmail` (custom subject/body by default), with optional template selectors in module settings.

Events:
- Application received (to admin) — subject “New NFR Application from {company}” with quick details and link to review
- Acknowledgement (to client) — “We received your NFR application …”
- Approval — includes chosen PID and date window; auto‑provisions and applies quotas; WHMCS may send its standard order emails
- Reject / Suspend / Resume / Expired — concise status update
- End‑date reminder (to admin) — triggered by daily cron; uses the configured admin template when set


## Security & Scoping

- Client route is gated by WHMCS session; it only reads/writes for the logged‑in `client_id`
- Max active grants per client is enforced on submit
- Captcha (Turnstile) is optional but recommended on public/visible routes
- Admin actions require WHMCS admin session; CSRF tokens are used for POST actions


## Styling and UX

- Include the tokens partial at the top of client/admin pages that adopt the dark UI:
  - `{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}`
- Wrap content in the page/container shells described in [STYLING_NOTES.md](mdc:accounts/modules/addons/eazybackup/Docs/STYLING_NOTES.md)
- Use the canonical input/select/checkbox/button classes to maintain consistent focus/hover rings


## Enabling and Testing

1) Activate the eazyBackup addon (ensures schema) and open Addons → eazyBackup → Configure
2) Set:
   - NFR: Enable = On
   - NFR: Product IDs = a valid PID (or multiple)
   - NFR: Admin notify email = your email
   - (Optional) Default duration/quota, Captcha, Auto‑ticket, End‑date Admin Email Template
3) As a client, open `index.php?m=eazybackup&a=nfr-apply` and submit the form
4) As admin, open Addons → eazyBackup → NFR → Applications, Approve (auto‑provisions) or use Provision if needed
5) Wire cron: `php accounts/crons/nfr_end_reminder.php`


## Integration Points (localAPI)

- `SendAdminEmail` — admin notices (applications, end‑date reminders)
- `SendEmail` — client notices
- `AddOrder` / `AcceptOrder` — provisioning
- `ModuleSuspend` / `ModuleUnsuspend` — manual admin flows
- Comet Admin API — `AdminGetUserProfileAndHash`, `AdminSetUserProfileHash` (quota/cap updates)


## Troubleshooting

- Client page shows “NFR applications are currently closed”: ensure “NFR: Enable” is set; the addon accepts typical on/yes/true values
- Form doesn’t render tokens: ensure the tokens partial include path matches
  - `modules/addons/eazybackup/templates/partials/_ui-tokens.tpl`
- Admin panel missing NFR tab: confirm you’re on Addons → eazyBackup → Power Panel and the addon file is up to date
- End‑date reminder not executing: run the cron script manually and check the Module Log entries for `nfr_end_reminder_*`


## Change Log (NFR subsystem)

- Added requested username/password, auto‑provisioning, Comet quota/cap application, end‑date reminder cron, admin inline updates on Active NFRs.


