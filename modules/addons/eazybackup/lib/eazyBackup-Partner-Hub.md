# eazyBackup Partner Hub — Project Blueprint

**Purpose**
Build "Partner Hub" into the existing eazyBackup WHMCS addon module so Managed Service Providers can:

* Create and manage their own **Customers** (end-clients).
* Link Customers to one or more **Comet Backup accounts**.
* Provide Customer users with **restricted, branded access** to a simplified dashboard that shows only their devices, protected items, and jobs.
* (Phase 3) **Bill** their Customers using flexible plans and metered usage.

This document is the single source of truth for scope, architecture, milestones, and acceptance criteria. Cursor will consume this context during development.

---

## Existing System (Starting Point)

**Module root**: `accounts/modules/addons/eazybackup`

* **Main module file**: `accounts/modules/addons/eazybackup/eazybackup.php`
* **Frontend backup dashboard (Smarty)**: `accounts/modules/addons/eazybackup/templates/clientarea/dashboard.tpl`
* **Backend dashboard controller**: `accounts/modules/addons/eazybackup/pages/console/dashboard.php`
* **Manage Comet Backup users (Smarty)**: `accounts/modules/addons/eazybackup/templates/console/user-profile.tpl`
* **Helpers**: `accounts/modules/addons/eazybackup/lib/Helper.php`

**Stack**

* Application host: **WHMCS** addon module
* Language/runtime: **PHP 8.2**
* Templates: **Smarty** (with Tailwind CSS + Alpine.js for UI behavior)
* Frontend libraries: **Tailwind CSS**, **Alpine.js**, **Chart.js** (for charts)
* Data: WHMCS database (**MySQL/MariaDB**); optional **Microsoft SQL Server** support via PDO if required for reporting/warehousing (module-owned tables remain in the WHMCS database unless otherwise specified)
* External: **Comet Server API** (existing integration)
* Payments (Phase 3): **Stripe Billing** + **Stripe Connect** (Express or Standard) for MSP payouts

**Key Principles**

* **Single codebase, multi-tenant** by MSP identifier; Customer-scoped views reuse the same components with data filters.
* **No forks** of theme: introduce **branding tokens** (colors, logos, images) rather than template duplication.
* **Repository/service layer** adds **data scoping** so every query is filtered by `msp_id` and optionally `customer_id`.
* **Safe migrations** with backfill and idempotent deploy scripts.

---

## New Concepts

### Tenancy

* **MSP**: the partner who owns Comet users and devices. Already mapped to a WHMCS client.
* **Customer**: an end-client of the MSP. A Customer may be linked to one or more Comet users.
* **Customer User**: a login that views only that Customer’s data. We will leverage WHMCS Users and an association table rather than separate auth.

### Branding

* Each MSP can supply **logo**, **colors**, and optional **login background**. These are stored as a JSON blob plus uploaded assets and injected as CSS variables.

### Billing (Phase 3)

* MSP-defined **plans** (base price, included devices and storage) plus optional **metered components** (storage overage, device overage, features such as Disk Image, Microsoft SQL Server, Object Lock).
* Subscriptions, usage ingestion, invoicing, and payouts via Stripe.

---

## Data Model (Addon Tables)

> Table names use the `eb_` prefix; adjust for your naming conventions.

* **`eb_msp_accounts`**

  * `id (PK)`, `whmcs_client_id (FK)`, `name`, `status`, `branding_json`, `stripe_connect_id`, timestamps

* **`eb_customers`**

  * `id (PK)`, `msp_id (FK -> eb_msp_accounts.id)`, `name`, `external_ref`, `status`, `notes`, timestamps

* **`eb_customer_user_links`**

  * `id (PK)`, `customer_id (FK)`, `whmcs_user_id (FK to WHMCS Users)`, `role (Owner|Viewer)`, timestamps

* **`eb_customer_comet_accounts`** (pivot Customer ↔ Comet User)

  * `customer_id (FK)`, `comet_user_id (FK to mirrored Comet users)`, unique composite key

* **Add columns to mirrors** (nullable; backfilled):

  * `comet_users`: `msp_id`, `customer_id`
  * `comet_devices`: `msp_id`, `customer_id`
  * `comet_items`: `msp_id`, `customer_id`
  * `comet_jobs`: `msp_id`, `customer_id`

* **Branding assets** (optional separate table if not in JSON)

  * `eb_brand_assets`: `id`, `msp_id`, `kind (logo|login_bg|favicon)`, `path`, `meta_json`

* **(Phase 3) Billing**

  * `eb_plans`: `id`, `msp_id`, `name`, `currency`, `base_price`, `includes_devices`, `includes_gb`, `status`, `stripe_product_id`
  * `eb_plan_components`: `id`, `plan_id`, `code`, `type (metered|feature)`, `unit`, `price_per_unit`, `step`, `min_qty`
  * `eb_subscriptions`: `id`, `customer_id`, `plan_id`, `stripe_subscription_id`, `status`, timestamps
  * `eb_usage_ledger`: `id`, `customer_id`, `metric`, `qty`, `period_start`, `period_end`, `source`, `idempotency_key`, `pushed_to_stripe_at`, timestamps

---

## Access Control and Scoping

**Roles**

* MSP Admin, MSP Operator, Customer Owner, Customer Viewer.

**Rule**

* Every repository/service method accepts an **Actor** context `{msp_id, customer_id?, roles[]}` and enforces:

  * `WHERE msp_id = :actor.msp_id`
  * If `actor.customer_id` exists, also `WHERE customer_id = :actor.customer_id`

**Implementation**

* Introduce helper methods in `lib/Helper.php` (or a new `lib/Auth.php`) to resolve the actor from the WHMCS session and the current route.

---

## Branding System

**Storage**: `branding_json` on `eb_msp_accounts` plus uploaded assets under `storage/brands/{msp_id}/...` (served via a controller with access control).

**Tokens** (CSS variables injected at runtime):

```css
:root{
  --brand-primary: #0ea5e9;
  --brand-accent:  #22c55e;
  --brand-nav-bg:  #0b1220;
  --brand-nav-text:#ffffff;
}
```

**Brandable surfaces**

* Login page (logo, background, accent)
* Navbar (logo + colors)
* Buttons, links, status chips (use CSS variables for overridable colors)
* Emails and (Phase 3) invoices

**Admin UI**

* New page under MSP menu: **Branding** (upload, color pickers, live preview)

---

## Billing Architecture (Phase 3)

**Stripe Connect**

* Onboard MSPs to Connect (Express/Standard). Store `stripe_connect_id`.
* Your platform creates Products/Prices on behalf of MSPs; subscriptions are created for each Customer.

**Usage**

* Nightly job computes GB-hours (from Comet or rollups) → normalized to GB-month fractions → sent to Stripe via `usage_records`.
* Keep a local `eb_usage_ledger` for reconciliation.

**Payouts**

* Use Destination Charges or Transfers to route funds to MSP; apply platform application fee if you are merchant of record.

---

## UI/UX Additions

**MSP View**

* New **Customers** section (List → Detail → Users → Linked Comet Accounts)
* **Branding** page
* **Plans and Billing** (Phase 3)

**Customer Portal**

* Restricted dashboard that reuses widgets:

  * Devices, Protected Items, Last 24 Hours Status doughnut chart, Jobs table, basic alerts
* No provisioning or global settings

**Status Colors** (Alpine colours, keep consistent across tables and charts)

* Success (green-400), Warning (amber-500), Error (red-500), Running (sky-500), Skipped (purple-500), Missed (gray-400)

**Engine → Friendly Type Mapping**

```php
ENGINE​
Name	Type	Value	Comment
ENGINE_BUILTIN_FILE	string	"engine1/file"	Files and Folders
ENGINE_BUILTIN_STDOUT	string	"engine1/stdout"	Program Output
ENGINE_BUILTIN_MYSQL	string	"engine1/mysql"	MySQL
ENGINE_BUILTIN_SYSTEMSTATE	string	"engine1/systemstate"	Windows Server System State
ENGINE_BUILTIN_MSSQL	string	"engine1/mssql"	Microsoft SQL Server
ENGINE_BUILTIN_WINDOWSSYSTEM	string	"engine1/windowssystem"	Windows System Backup, deprecated from version 24.12.2
ENGINE_BUILTIN_EXCHANGEEDB	string	"engine1/exchangeedb"	Microsoft Exchange Server
ENGINE_BUILTIN_VSSWRITER	string	"engine1/vsswriter"	Application-Aware Writer
ENGINE_BUILTIN_HYPERV	string	"engine1/hyperv"	Microsoft Hyper-V
ENGINE_BUILTIN_WINDISK	string	"engine1/windisk"	Disk Image
ENGINE_BUILTIN_MONGODB	string	"engine1/mongodb"	MongoDB
ENGINE_BUILTIN_MSOFFICE	string	"engine1/winmsofficemail"	Office 365
ENGINE_BUILTIN_VMWARE	string	"engine1/vmware"	VMware
ENGINE_BUILTIN_PROXMOX	string	"engine1/proxmox"	Proxmox (PVE)
```

**Vault → Friendly Type Mapping**

```php
$VAULT_TYPES = [
    "0" => "INVALID",
    "1000" => "S3-compatible",
    "1001" => "SFTP",
    "1002" => "Local Path",
    "1003" => "eazyBackup",
    "1004" => "FTP",
    "1005" => "Azure",
    "1006" => "SPANNED",
    "1007" => "OpenStack",
    "1008" => "Backblaze B2",
    "1100" => "latest",
    "1101" => "All",
];
```

**Backup Job Status → Friendly Type Mapping**

```PHP  
$BACKUP_STATUS_CODES = [
    "9999" => "UNKNOWN",
    "5000" => "SUCCESS",
    "6001" => "ACTIVE",
    "6002" => "REVIVED",
    "7000" => "TIMEOUT",
    "7001" => "WARNING",
    "7002" => "ERROR",
    "7003" => "FAILED_QUOTA",
    "7004" => "MISSED",
    "7005" => "CANCELLED",
    "7006" => "ALREADY_RUNNING"
    "7007" => "ABANDONED",
];
```

---

## Directory Map and Impacted Files

* `eazybackup.php` — register new pages (Customers, Branding, Billing), add permissions, route handlers
* `pages/console/` — add `customers.php`, `branding.php`, `billing/` controllers; update `dashboard.php` to support customer scope
* `templates/console/` — add `customers.tpl`, `customer-detail.tpl`, `branding.tpl`, `billing/*.tpl`
* `templates/clientarea/` — add Customer Portal layout (re-use `dashboard.tpl` with scope)
* `lib/Helper.php` — actor resolution, formatting helpers (sizes, dates), status color map, brand resolver
* `lib/` — add `Auth.php`, `Branding.php`, `Billing/Stripe.php`, `Repository/*.php`
* `migrations/` — new addon tables + column adds (idempotent)

---

## Milestones and Acceptance Criteria

### Milestone 1 — Customers and Restricted Portal (Step 1)

**Deliverables**

1. Database migrations: `eb_msp_accounts`, `eb_customers`, `eb_customer_user_links`, `eb_customer_comet_accounts`, added `msp_id` and `customer_id` columns to mirrored tables.
2. MSP **Customers** UI (CRUD) and linking Comet users to Customers.
3. User invitation: link existing WHMCS user or invite by email (creates WHMCS user and association).
4. Customer Portal routes and layout; dashboard filters all queries by `customer_id`.
5. Access control helpers and repository scoping.

**Acceptance**

* MSP can create a Customer, link two Comet users, invite a Customer Owner, and that user sees only their data in the portal.
* All dashboard widgets (including Last 24 Hours Status chart) respect scoping.

### Milestone 2 — Branding (Step 2)

**Deliverables**

1. Branding editor (logo upload, background, color tokens) with preview.
2. Asset storage and secure delivery; brand JSON stored on MSP record.
3. Login page and navbar show MSP branding based on resolved tenant.

**Acceptance**

* Two different MSPs render distinct logos/colors with zero template forks.

### Milestone 3 — Billing (Step 3)

**Deliverables**

1. Stripe Connect onboarding flow and persistence.
2. Plan Builder (base + components) and subscription assignment per Customer.
3. Usage pipeline and `eb_usage_ledger`, nightly aggregation, push to Stripe.
4. Customer billing pages (plan, invoices, payment method).

**Acceptance**

* In test mode, an MSP can create a plan, subscribe a Customer, generate usage, receive an invoice, and funds are routed per design.

---

## Non‑Functional Requirements

* **Security**: tenancy scoping enforced in repositories; input validation; rate limits on invites and branding uploads; audit log for sensitive actions.
* **Performance**: dashboards under 1.5 seconds at P95 for MSPs with 200+ devices; cache rollups for last-24-hour aggregates.
* **Reliability**: idempotent migrations; background jobs retriable; Stripe pushes with idempotency keys.
* **Observability**: server logs with correlation ids; metrics for sync latency and job ingest; error notifications.

---

## Migration Plan

1. Deploy migrations adding `msp_id` (backfill from owning WHMCS client) and nullable `customer_id` (null initially).
2. Ship MSP Customers UI; no breaking changes to existing dashboards.
3. Enable Customer Portal only when a Customer is created and a user is linked.
4. Introduce branding (opt-in); defaults render current eazyBackup theme.
5. Introduce billing behind a feature flag; test with sandbox Stripe accounts.

---

## Testing Strategy

* Unit tests for repositories (scoping), helpers (size/date), and engine label mapping.
* Integration tests for Customer creation → link Comet user → portal visibility.
* Browser tests for branding (correct assets applied per MSP).
* Stripe test webhooks and invoice generation in sandbox.

---

## Developer Notes for Cursor

* Use **Tailwind CSS** and **Alpine.js** for all front-end interactions; avoid jQuery.
* When writing inline JavaScript inside Smarty templates, wrap code in `{literal}{/literal}` and pass JSON with `nofilter`.
* Prefer small **service classes** under `lib/` for Comet API calls, branding, and billing.
* Add new routes in `eazybackup.php`; controllers live in `pages/console/…`; templates under `templates/…`.
* Always update `eazyBackup-Partner-Hub-Development.md` at the end of each Cursor task with: summary, files touched, schema changes, and next steps.

---

## Open Questions (to be resolved during implementation)

* Confirm whether module-owned tables should live in the WHMCS MySQL database only, or whether Microsoft SQL Server is used as the primary store for analytics (keep PDO adapters ready).
* Decide on custom domains per MSP (CNAME + certificate automation) timeline.
* Define exact mapping for all Comet engines to friendly labels (list above is a starter).

---

## Definition of Done (per milestone)

* All acceptance criteria met
* Code reviewed and merged
* Migrations applied in staging and production
* Documentation updated (this file + Development log)
* Rollback plan verified
