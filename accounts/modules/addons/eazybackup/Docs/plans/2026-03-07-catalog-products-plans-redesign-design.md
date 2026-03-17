# Partner Hub Catalog — Products & Plans Redesign

**Date:** 2026-03-07
**Status:** Approved
**Approach:** Single comprehensive plan, interleaved by dependency (Approach C), executed in 5 phases.

## Key Decisions

| Decision | Choice |
|----------|--------|
| Phasing | Single blueprint, phased execution |
| Quick Plan wizard | Hybrid — auto-creates products with clear summary |
| Tiered pricing | Full Stripe parity (graduated + volume) |
| Subscription management | Plans page is the primary hub |
| Multi-currency | Full — per-price currency selector |
| Wizard vs builder | Two separate entry points |

## Context

The Partner Hub enables MSPs to bill their customers for cloud backup and object storage services via Stripe Connect. The catalog has two layers: Products (individual billable resources with prices) and Plans (compositions of product prices into subscription bundles assigned to tenants).

The Products page is functional but has gaps (duplicate list pages, no filtering, no tiered pricing, no multi-currency, no draft deletion). The Plans page is bare-bones (basic modals, no editing, no component removal, no subscription visibility, no guided creation flow). This redesign addresses all identified gaps.

## Phase 1 — Foundation

**Goal:** Consolidate duplicate pages, retire the old plans system, extend schema for tiered pricing, multi-currency, and plan instance management.

### 1.1 Consolidate Products list page

- Keep `catalog-products-list.tpl` as the single canonical products page (route `ph-catalog-products`).
- Change `ph-catalog-product` route to redirect to `ph-catalog-products`.
- Move the slide-over panel markup (`#eb-product-panel` with `productPanelFactory`) from `catalog-products.tpl` into `catalog-products-list.tpl`.
- `catalog-products.tpl` is no longer rendered as a standalone page.

### 1.2 Retire old Plans system

- Old system: `eb_plans` / `eb_plan_prices`, `PlansController.php`, route `ph-plans`, template `plans.tpl`.
- New system: `eb_plan_templates` / `eb_plan_components` / `eb_plan_instances` / `eb_plan_instance_items`, `CatalogPlansController.php`, route `ph-catalog-plans`, template `catalog-plans.tpl`.
- Change `ph-plans` route to redirect to `ph-catalog-plans`.
- Mark `PlansController.php` and `plans.tpl` as deprecated. Do not delete `eb_plans` / `eb_plan_prices` tables.

### 1.3 Schema additions

**`eb_catalog_prices` — new columns:**
- `pricing_scheme` ENUM(`per_unit`, `tiered`) DEFAULT `per_unit`
- `tiers_mode` ENUM(`graduated`, `volume`) NULL
- `tiers_json` TEXT NULL — JSON array: `[{ "up_to": 1024, "unit_amount": 50, "flat_amount": 0 }, { "up_to": null, "unit_amount": 30, "flat_amount": 0 }]`

**`eb_catalog_products` — new column:**
- `product_template` VARCHAR(50) NULL — tracks auto-creation source (e.g., `eazybackup_storage`, `e3_storage`, `custom`)

**`eb_plan_templates` — new columns:**
- `billing_interval` VARCHAR(10) DEFAULT `month`
- `currency` VARCHAR(3) DEFAULT `CAD`
- `status` ENUM(`active`, `archived`, `draft`) DEFAULT `active`
- `metadata_json` TEXT NULL

**`eb_plan_instances` — new columns:**
- `cancelled_at` DATETIME NULL
- `cancel_reason` VARCHAR(255) NULL

**New table: `eb_plan_instance_usage_map`**
- `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY
- `plan_instance_item_id` INT UNSIGNED NOT NULL (FK → `eb_plan_instance_items.id`)
- `metric_code` VARCHAR(50) NOT NULL
- `stripe_subscription_item_id` VARCHAR(255) NOT NULL
- `last_pushed_at` DATETIME NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- KEY (`plan_instance_item_id`), KEY (`metric_code`)

All changes go through `eazybackup_migrate_schema()` with `hasColumn` / `hasTable` guards.

### 1.4 Documentation

Update `PARTNER_HUB.md` to document `eb_plan_templates`, `eb_plan_components`, `eb_plan_instances`, `eb_plan_instance_items`, and `eb_plan_instance_usage_map`.

---

## Phase 2 — Products Page Completion

**Goal:** Fill all product builder gaps.

### 2.1 Product filtering and status tabs

- Filter tabs above the products table: All / Active / Draft / Archived.
- Product type filter dropdown: All Types / Storage / Device Count / Disk Image / Hyper-V VM / Proxmox VM / VMware VM / M365 User / Generic.
- Counter cards update to reflect filtered view.
- Search works in combination with filters.

### 2.2 Draft product management

- Kebab menu on draft product rows: Edit, Publish to Stripe, Delete draft.
- Delete requires confirmation modal. Only available for products without a `stripe_product_id`.
- New endpoint: `ph-catalog-product-delete-draft`.

### 2.3 Explicit Publish flow

- Slide-over in create/edit (non-Stripe) mode shows two buttons: "Save Draft" (secondary) and "Publish to Stripe" (primary).
- In `editStripe` mode: single "Save" button.
- Draft rows on the list show a "Publish" action.

### 2.4 Product type contextual help

- Reactive description area below the product type button grid. Updates on selection. Content:
  - **Storage** — "Metered billing based on the customer's storage consumption. Priced per GiB or TiB."
  - **Device Count** — "Per-unit billing for each backup endpoint (workstation or server) registered in the customer's account."
  - **Disk Image** — "Per-unit billing for each machine protected with disk image backups."
  - **Hyper-V VM** — "Per-unit billing for each Microsoft Hyper-V virtual machine being backed up."
  - **Proxmox VM** — "Per-unit billing for each Proxmox virtual machine being backed up."
  - **VMware VM** — "Per-unit billing for each VMware virtual machine being backed up."
  - **M365 User** — "Per-unit billing for each Microsoft 365 user account protected."
  - **Generic** — "Flexible billing for any service you provide — IT support, antivirus, consulting, or any recurring/one-time charge."

### 2.5 Product templates / presets

- "Start from template" section in slide-over (create mode only). Preset cards:
  - **eazyBackup Cloud Backup** — STORAGE_TB, metered, GiB, monthly
  - **e3 Object Storage** — STORAGE_TB, metered, GiB, monthly, 1TiB minimum note
  - **Workstation Backup Seat** — DEVICE_COUNT, per-unit, device, monthly
  - **Custom Service** — GENERIC, per-unit, unit, monthly
- Selecting a preset populates form fields. "Using template: X" badge with "Clear" link.

### 2.6 Tiered pricing UI

- "Pricing model" selector per price row: Flat rate / Graduated tiers / Volume tiers.
- Tier builder: table with First unit, Last unit, Per unit ($), Flat fee ($), Remove. "Add tier" button.
- Validation: contiguous tiers, at least 2, last tier unbounded.
- Live preview: "If a customer uses X units..." with simulated cost calculation.
- Backend: `pricing_scheme`, `tiers_mode`, `tiers_json` handled in save endpoints. Stripe publish uses `billing_scheme: tiered` with `tiers[]` array.

### 2.7 Multi-currency per price

- Currency selector per price row (CAD, USD, EUR, GBP, AUD, NZD, etc.).
- Default from MSP's `default_currency`.
- Dynamic currency badge on amount input.
- Plan-level currency consistency enforced in the plan builder (Phase 3), not here.

### 2.8 Attributes field

- Key-value editor stored in `eb_catalog_products.attributes_json` (new TEXT column).
- "Add attribute" button. Displayed in product card on list when populated.

### 2.9 Subscription safety warning

- New lightweight endpoint: `ph-catalog-price-sub-count` — queries `eb_plan_instance_items` for active subscription count per price.
- Amber badge on prices with active subscriptions.
- Confirmation modal when deactivating/removing such a price.

---

## Phase 3 — Plans Page Rebuild

**Goal:** Full-featured plan builder, subscription management hub, polished list view.

### 3.1 Plans list page redesign

- Table layout matching products page style.
- Filter tabs: All / Active / Draft / Archived.
- Search across plan name and description.
- Counter cards.
- Table columns: Name, Components (count), Currency, Billing Interval, Active Subscriptions (count), Status (pill), Created, Actions.
- Kebab menu: Edit, Duplicate, Archive/Unarchive, Delete.
- Two header buttons: "New Plan" (standard builder) and "eazyBackup Quick Plan" (wizard, Phase 4).

### 3.2 Plan builder slide-over

Single slide-over replacing three modals. Sections:

**Header:** Plan name, description, billing interval (Monthly/Annual), currency selector, trial toggle + days input (helper: "New subscribers will have full access for this many days before billing begins"), status pill.

**Components:** "Plan Components" with "Add Component" button. Each component card shows:
- Product name + type badge
- Price label and amount
- Pricing model badge (Flat / Graduated / Volume)
- Included quantity (inline editable, or "Usage-based" for metered)
- Overage mode dropdown: "Bill all usage" / "Cap at included" with tooltips
- Remove button (confirmation if active subscriptions)
- Drag handle for reorder

"Add Component" inline picker: searchable dropdown of active prices grouped by product name. Currency mismatch validation.

**Pricing preview:** Live-calculated summary. Line-by-line breakdown. Metered items show "Usage-based (billed on consumption)". Total at bottom.

**Footer:** Cancel, Save Draft (secondary), Save & Activate (primary).

### 3.3 Plan editing

- "Edit" opens slide-over pre-populated with plan data.
- All fields editable. Currency only changeable if no active subscriptions.
- New endpoint: `ph-plan-template-update` — diff-based component update.
- Version increments on each save. Version badge in header.

### 3.4 Plan duplication

- "Duplicate" creates copy with "Copy of {name}", status draft, version 1, copies all components.
- Opens slide-over immediately.
- New endpoint: `ph-plan-template-duplicate`.

### 3.5 Plan status management

- Three statuses: active, archived, draft.
- Archive: warning if active subscriptions. Prevents new assignments.
- Delete: only if zero active subscriptions AND status is draft or archived. Confirmation required.

### 3.6 Plan versioning display

- "v{version}" badge on each plan row.
- Collapsible "Version history" in slide-over: version number, created/updated timestamps.

### 3.7 Assign to Customer (improved)

- Dedicated section in plan slide-over or secondary slide-over.
- **Tenant picker:** searchable dropdown from `eb_tenants`, shows name + email + status.
- **eazyBackup User picker:** dropdown from `eb_tenant_comet_accounts` joined with `comet_users`. Auto-select if only one. Never use the word "Comet" — label is "eazyBackup User".
- **Application fee:** numeric + "%" suffix. Pre-fills from MSP default.
- **Pricing summary:** component breakdown, metered notes, trial note, fee note, estimated total.
- "Create Subscription" calls updated `ph-plan-assign`.

### 3.8 Subscription management on Plans page

- "Active Subscriptions" section or tab below plans list.
- Table: Tenant Name, Plan Name, Status, Quantity Summary, Monthly Amount, Created, Actions.
- Per-subscription actions: Update quantities (modal), Cancel (with reason), View in Stripe.
- Per-plan clickable count badge filters subscriptions table.
- Data: `eb_plan_instances` joined with `eb_tenants` and `eb_plan_templates`.

### 3.9 Overage mode labels

- `bill_all` → "Bill all usage" — tooltip: "The customer is billed for their total consumption of this resource. The included quantity sets the minimum, not a free allowance."
- `cap_at_default` → "Cap at included" — tooltip: "The customer is never charged beyond the included quantity. Usage above the cap is free."

---

## Phase 4 — Quick Plan Wizard & Custom Services

**Goal:** Guided wizard for eazyBackup plans, clear custom service path.

### 4.1 Wizard entry and structure

- "eazyBackup Quick Plan" button in Plans page header.
- Full-width modal with 4-step progress bar: Choose Type → Configure Resources → Set Pricing → Review & Create.

### 4.2 Step 1 — Choose plan type

Three cards:
- **Cloud Backup** — STORAGE_TB (metered) + DEVICE_COUNT (per-unit) base, optional add-ons in Step 2.
- **e3 Object Storage** — STORAGE_TB (metered) only, 1 TiB minimum note.
- **Custom Service** — exits wizard, opens standard plan builder with GENERIC type.

### 4.3 Step 2 — Configure resources

Cloud Backup path — checklist:
- Storage (always included), Device Endpoints (default on), Disk Image, Hyper-V VMs, Proxmox VMs, VMware VMs, Microsoft 365 Users (all optional).
- Plan name (pre-filled, editable), billing interval, currency, trial toggle + days.

e3 path — Storage only, optional minimum quantity.

### 4.4 Step 3 — Set pricing

Per-resource pricing card: pricing model selector (flat/graduated/volume), amount inputs, unit label, included quantity (for per-unit). GiB/TiB selector for storage. Live running total.

### 4.5 Step 4 — Review & Create

Summary: plan details, products to be created (or existing matches), prices, components table, estimated cost.

Buttons: "Create as Draft" / "Create & Activate".

**Hybrid auto-creation:**
1. Check for matching product by `msp_id` + `base_metric_code` + active.
2. Reuse if match, create if not.
3. Create plan template + components.
4. Publish to Stripe if "Create & Activate".

Success screen with links: View plan, Edit plan, Edit products.

### 4.6 Custom Service path

When `baseMetric === 'GENERIC'`, swap all terminology:
- "Product type" → "Service type"
- "Resource" → "Service"
- Name placeholder: "e.g., Managed IT Support, Antivirus License, On-Call Support"
- Price label placeholder: "e.g., Monthly Retainer, Per-Seat License, Hourly Rate"
- Unit label placeholder: "e.g., seat, hour, license, user"
- Full billing type options available.

---

## Phase 5 — Integration & Polish

**Goal:** Metered usage wiring, validation, import/export, preview, documentation.

### 5.1 Metered usage integration

- On plan assignment, for each metered component, insert row in `eb_plan_instance_usage_map` linking `plan_instance_item_id` + `metric_code` + `stripe_subscription_item_id`.
- Usage push logic updated: look up tenant's active plan instance → find usage map row for metric → push to mapped subscription item.
- Fallback for legacy subscriptions without a map row.
- Usage map updated/soft-deleted on quantity changes or cancellation.

### 5.2 Real-time price validation

Client-side validation (on blur + on save):
- Minimum amount: `unit_amount >= 1` cent.
- Maximum amount: Stripe cap 99,999,999.
- Currency consistency within a plan.
- Tier validation: contiguous, at least 2, last unbounded, no negatives.
- Interval consistency within a plan.
- Inline red error text. Save/Publish disabled when errors exist.
- Backend mirrors all checks.

### 5.3 Plan import/export

- Export: JSON file with plan template fields + components array.
- Import: file picker, match products by name + type + amount or create as drafts. Opens slide-over for review.
- Validation on import structure.

### 5.4 Pricing table preview

- "Preview" button in plan builder opens modal with customer-facing pricing card mockup.
- Shows plan name, monthly/annual price, feature bullets from components, usage-based items, trial badge.
- Read-only visual preview.

### 5.5 Documentation updates

- Update `PARTNER_HUB.md`: complete schema, new routes, catalog section rewrite, plans section rewrite, metered usage integration, wizard flow.
- Update `EAZYBACKUP_README.md` if module layout changed.

### 5.6 Old system cleanup

- Verify `ph-plans` redirect works.
- Deprecation comments in `PlansController.php` and `plans.tpl`.
- Remove old `ph-plans` sidebar entry.
- Audit router for stale references.

---

## Files Affected (Summary)

### Templates
- `catalog-products-list.tpl` — major updates (filters, slide-over, presets, tiered UI)
- `catalog-products.tpl` — retired as standalone page, slide-over moved to list
- `catalog-plans.tpl` — complete rewrite (list, slide-over builder, subscriptions)
- `sidebar_partner_hub.tpl` — minor (remove old plans link if needed)
- New: Quick Plan wizard template (inline in `catalog-plans.tpl` or separate partial)

### JavaScript
- `catalog-products.js` — major updates (tiered pricing, multi-currency, filtering, presets, validation)
- `catalog-plans.js` — complete rewrite (slide-over builder, component CRUD, pricing preview, assignment, subscription management)
- New: `catalog-plans-wizard.js` (Quick Plan wizard logic)

### Controllers
- `CatalogProductsController.php` — new endpoints (delete draft, price sub count), tiered pricing support in save
- `CatalogPlansController.php` — major expansion (update, duplicate, status toggle, assignment improvements, subscription CRUD)
- `PlansController.php` — deprecated, redirect only
- New or updated: usage push logic in `UsageController.php`

### Schema
- `eazybackup.php` (`eazybackup_migrate_schema`) — new columns and tables as specified

### Documentation
- `PARTNER_HUB.md` — comprehensive update
- `EAZYBACKUP_README.md` — update if needed
