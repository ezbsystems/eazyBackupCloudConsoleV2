# Tenant Model Restructuring — Design Spec

**Date:** 2026-03-03
**Status:** Draft
**Scope:** Replace `eb_customers` and `s3_backup_tenants` with a single canonical `eb_tenants` table owned by the `eazybackup` addon module. Align all Partner Hub billing, catalog, and service tables to the new model.

---

## 1. Problem statement

The Partner Hub currently has two overlapping customer-organization concepts:

| Table | Module owner | Purpose |
|-------|-------------|---------|
| `s3_backup_tenants` | cloudstorage | Originally scoped to S3/cloud-backup MSP customers |
| `eb_customers` | eazybackup | Partner Hub billing customer, holds `whmcs_client_id` + `stripe_customer_id` |

They are bridged by `eb_customers.tenant_id → s3_backup_tenants.id`, but the split creates ambiguity: some tables FK to `customer_id` (meaning `eb_customers.id`), others to `tenant_id` (meaning `s3_backup_tenants.id`). The `eb_customers` table also requires a `whmcs_client_id` per tenant, which is unnecessary — only the MSP should be a WHMCS client.

Similarly, `s3_backup_tenant_users` (portal auth for tenant users) lives in the cloudstorage module despite being a Partner Hub concern.

### Goals

1. **Single canonical tenant table** — `eb_tenants`, owned by `eazybackup`.
2. **Phase out `eb_customers`** — all FKs that point to `eb_customers.id` re-point to `eb_tenants.id`.
3. **Phase out `s3_backup_tenants`** — creation moves to `eazybackup`; cloudstorage migration code becomes a no-op when `eb_tenants` exists.
4. **Phase out `s3_backup_tenant_users`** — replaced by `eb_tenant_users` in `eazybackup`.
5. **No WHMCS client for tenants** — only the MSP has a `tblclients` entry. WHMCS services (`tblhosting`) belong to the MSP; a mapping table links them to tenants.
6. **No data migration needed** — the system is in development with no production rows to preserve.

---

## 2. Entity model

```
┌──────────────────────────────────────────────────────────────────┐
│ eazyBackup platform                                              │
│                                                                  │
│  ┌────────────────────┐                                          │
│  │ eb_msp_accounts    │  MSP = WHMCS client of eazyBackup        │
│  │  id                │  One tblclients entry per MSP             │
│  │  whmcs_client_id ──┼──► tblclients                            │
│  │  stripe_connect_id │  Stripe Connected Account                 │
│  │  ...               │                                          │
│  └────────┬───────────┘                                          │
│           │ 1:N                                                   │
│  ┌────────▼───────────┐          ┌──────────────────────┐        │
│  │ eb_tenants         │          │ eb_tenant_users       │        │
│  │  id                │ 1:N      │  id                   │        │
│  │  msp_id ───────────┼──► MSP   │  tenant_id ──► tenant │        │
│  │  name              │          │  email                │        │
│  │  slug              │          │  password_hash        │        │
│  │  contact_email     │          │  name                 │        │
│  │  stripe_customer_id│          │  role (admin|user)    │        │
│  │  ...               │          │  status               │        │
│  └────────┬───────────┘          │  last_login_at        │        │
│           │                      └──────────────────────┘        │
│           │ 1:N                                                   │
│  ┌────────▼───────────────────────────────────┐                  │
│  │ eb_tenant_services                          │                  │
│  │  id                                         │                  │
│  │  tenant_id ──► eb_tenants.id                │                  │
│  │  msp_id    ──► eb_msp_accounts.id           │                  │
│  │  hosting_id ─► tblhosting.id (MSP's svc)    │                  │
│  │  catalog_product_id ─► eb_catalog_products  │                  │
│  │  status                                     │                  │
│  └─────────────────────────────────────────────┘                  │
│                                                                  │
│  ┌──────────────────────────────┐                                │
│  │ eb_catalog_products          │                                │
│  │  id                          │                                │
│  │  msp_id                      │                                │
│  │  whmcs_product_id (new, FK)──┼──► tblproducts.id              │
│  │  name                        │                                │
│  │  stripe_product_id           │                                │
│  │  ...                         │                                │
│  └──────────────────────────────┘                                │
└──────────────────────────────────────────────────────────────────┘
```

### Key relationships

- **MSP → WHMCS:** 1:1 via `eb_msp_accounts.whmcs_client_id`.
- **MSP → Tenants:** 1:N via `eb_tenants.msp_id`.
- **Tenant → Portal users:** 1:N via `eb_tenant_users.tenant_id`. Standalone auth (not WHMCS login).
- **Tenant → Services:** 1:N via `eb_tenant_services`. Each row links a tenant to a `tblhosting` service owned by the MSP.
- **Catalog product → WHMCS product:** Optional N:1 via `eb_catalog_products.whmcs_product_id`. When present, ordering this catalog product provisions the linked WHMCS product under the MSP's client account.

---

## 3. Billing flow

```
                    Stripe Connect
Tenant ◄───────────────────────────── MSP
  (pays MSP for "MSPBackup")           │
                                       │  WHMCS invoicing
                                       ▼
                                   eazyBackup
                             (bills MSP for "eazyBackup Ultra")
```

1. **MSP creates a catalog product** "MSPBackup" in `eb_catalog_products`, sets a price, and maps it to the eazyBackup WHMCS product "eazyBackup Ultra" via `whmcs_product_id`.
2. **Order is placed** (by MSP or by tenant from portal).
3. **WHMCS provisioning:** A `tblhosting` row is created under the MSP's `whmcs_client_id` for the "eazyBackup Ultra" product. The Comet server module provisions the backup account.
4. **Service mapping:** An `eb_tenant_services` row is created linking `tenant_id`, `hosting_id`, and `catalog_product_id`.
5. **eazyBackup → MSP billing:** Normal WHMCS invoicing. The MSP pays eazyBackup for the "eazyBackup Ultra" service.
6. **MSP → Tenant billing:** A Stripe Connect subscription is created on the MSP's connected account, charging the tenant's `stripe_customer_id` for the MSP's price. Revenue flows to the MSP.

### Double-invoice prevention

The WHMCS service (`tblhosting`) belongs to the MSP. WHMCS invoices go to the MSP, not the tenant. The tenant only sees Stripe Connect charges. There is no WHMCS client for the tenant, so WHMCS cannot invoice them.

---

## 4. Order placement

### MSP-initiated order

1. MSP selects a catalog product and assigns it to a tenant.
2. System creates a WHMCS order under the MSP's client account (if `whmcs_product_id` is set on the catalog product).
3. System creates `eb_tenant_services` row.
4. System creates a Stripe Connect subscription for the tenant.

### Tenant-initiated order (future)

1. Tenant logs into portal, browses MSP's published catalog.
2. Tenant selects a product and confirms order.
3. Same provisioning flow as MSP-initiated, but triggered by the tenant portal.
4. Requires tenant portal auth to be built (see section 7).

---

## 5. Schema changes

### 5.1 New table: `eb_tenants`

Replaces both `s3_backup_tenants` and `eb_customers`. Created in `eazybackup.php` schema migration.

```sql
CREATE TABLE eb_tenants (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    msp_id          BIGINT NOT NULL,               -- FK → eb_msp_accounts.id
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(100) NOT NULL,
    contact_email   VARCHAR(255) NULL,
    contact_name    VARCHAR(255) NULL,
    contact_phone   VARCHAR(50) NULL,
    address_line1   VARCHAR(255) NULL,
    address_line2   VARCHAR(255) NULL,
    city            VARCHAR(100) NULL,
    state           VARCHAR(100) NULL,
    postal_code     VARCHAR(20) NULL,
    country         CHAR(2) NULL,                   -- ISO 3166-1 alpha-2
    stripe_customer_id VARCHAR(255) NULL,            -- Stripe customer on MSP's connected account
    external_ref    VARCHAR(191) NULL,               -- MSP's own reference/ID
    status          ENUM('active','suspended','deleted') DEFAULT 'active',
    notes           TEXT NULL,
    branding_json   TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_tenant_msp_slug (msp_id, slug),
    INDEX idx_tenant_msp_id (msp_id),
    INDEX idx_tenant_status (status),
    INDEX idx_tenant_stripe_customer (stripe_customer_id)
);
```

**Columns absorbed from `eb_customers`:** `stripe_customer_id`, `external_ref`, `notes`.
**Columns absorbed from `s3_backup_tenants`:** `name`, `slug`, `contact_*`, `address_*`, `branding_json`, `status`.
**Columns dropped:** `whmcs_client_id` (tenants are not WHMCS clients), `ceph_uid`, `bucket_name`, `storage_quota_bytes` (S3-specific, moved to `eb_tenant_storage_links` if needed).

### 5.2 New table: `eb_tenant_users`

Replaces `s3_backup_tenant_users`. Created in `eazybackup.php` schema migration.

```sql
CREATE TABLE eb_tenant_users (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id               BIGINT NOT NULL,        -- FK → eb_tenants.id
    email                   VARCHAR(255) NOT NULL,
    password_hash           VARCHAR(255) NOT NULL,
    name                    VARCHAR(255) NOT NULL,
    role                    ENUM('admin','user') DEFAULT 'user',
    status                  ENUM('active','disabled') DEFAULT 'active',
    password_reset_token    VARCHAR(64) NULL,
    password_reset_expires  DATETIME NULL,
    last_login_at           DATETIME NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_tenant_user_email (tenant_id, email),
    INDEX idx_tenant_user_tenant (tenant_id),
    INDEX idx_tenant_user_email_lookup (email)
);
```

### 5.3 New table: `eb_tenant_services`

Links an MSP's WHMCS service to a tenant. Created in `eazybackup.php`.

```sql
CREATE TABLE eb_tenant_services (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT NOT NULL,            -- FK → eb_tenants.id
    msp_id              BIGINT NOT NULL,            -- FK → eb_msp_accounts.id (denormalized for query perf)
    hosting_id          INT NULL,                   -- FK → tblhosting.id (NULL if no WHMCS provisioning)
    catalog_product_id  BIGINT NULL,                -- FK → eb_catalog_products.id
    status              ENUM('active','suspended','cancelled','pending') DEFAULT 'pending',
    provisioned_at      TIMESTAMP NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_tenant_hosting (hosting_id),      -- one hosting row maps to one tenant
    INDEX idx_ts_tenant (tenant_id),
    INDEX idx_ts_msp (msp_id),
    INDEX idx_ts_catalog (catalog_product_id)
);
```

### 5.4 Altered table: `eb_catalog_products`

Add `whmcs_product_id` to link MSP catalog products to eazyBackup's WHMCS products.

```sql
ALTER TABLE eb_catalog_products
    ADD COLUMN whmcs_product_id INT NULL AFTER msp_id;

CREATE INDEX idx_catalog_whmcs_pid ON eb_catalog_products (whmcs_product_id);
```

### 5.5 Tables to re-point (`customer_id` → `tenant_id`)

These tables currently use `customer_id` referencing `eb_customers.id`. They will be re-pointed to `tenant_id` referencing `eb_tenants.id`.

Since there is no production data, these are drop-and-recreate operations.

| Table | Column change | Notes |
|-------|--------------|-------|
| `eb_subscriptions` | `customer_id` → `tenant_id` | Stripe Connect subscription per tenant |
| `eb_usage_ledger` | `customer_id` → `tenant_id` | Already has `tenant_id` (nullable); drop `customer_id` |
| `eb_invoice_cache` | `customer_id` → `tenant_id` | Stripe invoice cache |
| `eb_payment_cache` | `customer_id` → `tenant_id` | Stripe payment cache |
| `eb_service_links` | `customer_id` → `tenant_id` | WHMCS service → Comet user link |
| `eb_customer_comet_accounts` | Rename to `eb_tenant_comet_accounts`, `customer_id` → `tenant_id` | Comet user pivot |
| `comet_users` (mirror) | `customer_id` → `tenant_id` | Nullable scoping column |
| `comet_devices` (mirror) | `customer_id` → `tenant_id` | Nullable scoping column |
| `comet_items` (mirror) | `customer_id` → `tenant_id` | Nullable scoping column |
| `comet_jobs` (mirror) | `customer_id` → `tenant_id` | Nullable scoping column |

### 5.6 Tables to drop

| Table | Reason |
|-------|--------|
| `eb_customers` | Replaced by `eb_tenants` |
| `eb_customer_user_links` | Used WHMCS user IDs for portal access; replaced by `eb_tenant_users` |
| `s3_backup_tenants` | Replaced by `eb_tenants` (cloudstorage migration becomes no-op) |
| `s3_backup_tenant_users` | Replaced by `eb_tenant_users` |

### 5.7 Tables unchanged

| Table | Notes |
|-------|-------|
| `eb_msp_accounts` | No changes |
| `eb_msp_settings` | No changes |
| `eb_plans` / `eb_plan_prices` | Already scoped by `msp_id` |
| `eb_catalog_prices` | Scoped via `product_id` → `eb_catalog_products` |
| `eb_whitelabel_tenants` | `canonical_tenant_id` will now FK to `eb_tenants.id` instead of `s3_backup_tenants.id` (same semantic, new table name) |
| `eb_tenant_storage_links` | `tenant_id` will FK to `eb_tenants.id` |

---

## 6. Controller / code changes

### 6.1 Re-point Partner Hub controllers

All controllers currently querying `eb_customers` or `s3_backup_tenants` switch to `eb_tenants`.

| Controller | Change |
|-----------|--------|
| `TenantsController.php` | Query `eb_tenants` instead of `s3_backup_tenants` |
| `TenantMembersController.php` | Query `eb_tenant_users` instead of `s3_backup_tenant_users` |
| `TenantBillingController.php` | Replace `customer_id` lookups with `tenant_id` on billing tables |
| `ClientsController.php` | Remove `eb_customers` CRUD. Client creation flow refactored: "Create Client" becomes "Create Tenant" with direct `eb_tenants` insert |
| `CatalogProductsController.php` | Surface `whmcs_product_id` for product mapping |

### 6.2 Remove `eb_customers` from `TenantCustomerService.php`

`PartnerHub\TenantCustomerService` currently bridges `eb_whitelabel_tenants` and `eb_customers`. This service should bridge `eb_whitelabel_tenants` and `eb_tenants` directly via `canonical_tenant_id`.

### 6.3 Schema migration in `eazybackup.php`

- Add `eb_tenants`, `eb_tenant_users`, `eb_tenant_services` creation blocks.
- Add `whmcs_product_id` column to `eb_catalog_products`.
- Drop `eb_customers`, `eb_customer_user_links`, `eb_customer_comet_accounts` creation blocks.
- Update re-pointed tables to use `tenant_id`.
- Guard `s3_backup_tenants` / `s3_backup_tenant_users` creation in `cloudstorage.php` with `if (!hasTable('eb_tenants'))` so they become no-ops once the eazybackup module is active.

### 6.4 Template changes

- `tenants.tpl`: Update "Back to Clients" link → remove or change to Partner Hub dashboard link.
- Remove or archive `ClientsController`-specific templates if they exist.

---

## 7. Tenant portal auth (future, out of scope for this restructuring)

The `eb_tenant_users` table provides the data model for standalone tenant portal authentication. The auth system is **not** WHMCS login — tenant users authenticate with email + password against `eb_tenant_users`.

**Still to build (separate spec):**
- Login controller + session management
- Password reset flow
- Route guarding (middleware) for tenant portal routes
- Role-based access (admin vs. user) within the portal

This restructuring delivers the schema foundation. The portal auth implementation is a follow-on project.

---

## 8. What is NOT changing

- **MSP ↔ WHMCS relationship**: MSPs remain WHMCS clients. No change to `eb_msp_accounts`.
- **WHMCS order flow**: Orders are still placed through WHMCS for products that require provisioning. The `tblhosting` row belongs to the MSP.
- **Stripe Connect architecture**: MSPs still have connected accounts. Tenants still have `stripe_customer_id` on the MSP's connected account.
- **White-label provisioning**: `eb_whitelabel_tenants` continues to work, with `canonical_tenant_id` now pointing to `eb_tenants.id`.
- **Comet server module**: Still provisions via WHMCS server module hooks. The `comet_users` mirror table gets `tenant_id` instead of `customer_id`.

---

## 9. Summary of new/changed tables

| Action | Table | Owner module |
|--------|-------|-------------|
| **CREATE** | `eb_tenants` | eazybackup |
| **CREATE** | `eb_tenant_users` | eazybackup |
| **CREATE** | `eb_tenant_services` | eazybackup |
| **ALTER** | `eb_catalog_products` (add `whmcs_product_id`) | eazybackup |
| **RE-POINT** | `eb_subscriptions`, `eb_usage_ledger`, `eb_invoice_cache`, `eb_payment_cache`, `eb_service_links` | eazybackup |
| **RENAME+RE-POINT** | `eb_customer_comet_accounts` → `eb_tenant_comet_accounts` | eazybackup |
| **RE-POINT** | `comet_users/devices/items/jobs` mirrors | eazybackup |
| **DROP** | `eb_customers`, `eb_customer_user_links` | eazybackup |
| **NO-OP GUARD** | `s3_backup_tenants`, `s3_backup_tenant_users` | cloudstorage |
