# Partner Hub Overview Page — Design

**Status:** Approved  
**Date:** 2026-03-05  
**Approach:** Single synchronous controller (Approach A)

## Purpose

The Partner Hub currently has no dedicated landing page — the "Overview" sidebar link points to the tenants list. This design adds a proper Overview dashboard that serves as the default entry point for MSPs, providing account health, guided onboarding, billing visibility, and navigation shortcuts.

## Decisions

- **Billing data source:** Live Stripe API calls (not local cache). Stripe outage degrades gracefully with "unavailable" fallback.
- **Setup wizard collapse:** When all 5 steps are complete, collapses into a dismissible "Setup Complete" badge. Dismiss state stored in `localStorage` (`eb_ph_setup_dismissed`).
- **Pending approvals:** Amber notification badge on the metrics card when count > 0.

## Route & Navigation

- **New route:** `a=ph-overview` in `eazybackup.php`, placed before `ph-tenants-manage`.
- **Controller:** `pages/partnerhub/OverviewController.php` → `eb_ph_overview_index($vars)`.
- **Template:** `templates/whitelabel/overview.tpl` with `ebPhSidebarPage='overview'`.
- **Auth:** Reuses `eb_ph_tenants_require_context()` for session/MSP resolution and reseller-group gating.
- **Sidebar update:** Overview link in `sidebar_partner_hub.tpl` changes href from `a=ph-tenants-manage` to `a=ph-overview`.
- **Header nav update:** Overview link in `nav_partner_hub.tpl` also updated.
- `ph-tenants-manage` remains unchanged — it still serves the tenants list for bookmarks/direct links.

## Page Sections (top to bottom)

### 1. Setup Wizard (Onboarding Progress)

A card with a vertical checklist of 5 steps. Each step shows a status icon (green checkmark or numbered amber circle), title, one-line description, and a CTA button for incomplete steps.

| # | Step | Complete when | CTA target |
|---|------|--------------|------------|
| 1 | Connect Stripe | `eb_msp_accounts.stripe_connect_id` set AND Stripe `charges_enabled = true` | `ph-stripe-onboard` |
| 2 | Create a Product | `eb_catalog_products` count > 0 for MSP | `ph-catalog-products` |
| 3 | Create a Plan | `eb_plans` count > 0 for MSP | `ph-catalog-plans` |
| 4 | Add a Tenant | `eb_tenants` count > 0 (non-deleted) for MSP | `ph-tenants` |
| 5 | Create a Subscription | `eb_subscriptions` count > 0 with `stripe_status IN ('active','trialing')` for MSP | `ph-billing-subscriptions` |

When all 5 steps are complete, the wizard collapses into a single-line "Setup Complete" badge (green checkmark). The badge can be permanently dismissed via Alpine.js + `localStorage`.

Stripe onboarding alert banners (onboard error/success/refresh from query string) render below the wizard.

### 2. Stripe Connect Status Card

A compact horizontal card showing:

- Left: "Stripe Connect" label + masked account ID (`acct_...xxxx`).
- Right: Status badges — green "Charges Enabled" / "Payouts Enabled", amber "Pending", red "Not Connected".
- If `requirements.currently_due` has items: amber "Action Required" badge linking to `ph-stripe-connect`.
- Stripe API failure: graceful "Status unavailable" text.

### 3. Key Metrics Grid

Six clickable stat cards in a responsive grid (`grid-cols-2 md:grid-cols-3`). Each card has a large number and small label.

| Metric | Query (scoped by `msp_id`) | Link |
|--------|---------------------------|------|
| Tenants | `eb_tenants` count (non-deleted) | `ph-tenants-manage` |
| Active Subscriptions | `eb_subscriptions` count (`stripe_status IN ('active','trialing')`) | `ph-billing-subscriptions` |
| Products | `eb_catalog_products` count | `ph-catalog-products` |
| Plans | `eb_plans` count | `ph-catalog-plans` |
| Pending Approvals | `eb_whitelabel_signup_events` count (`status='pending_approval'`) for MSP's whitelabel tenants | `ph-signup-approvals` |
| White-Label Tenants | `eb_whitelabel_tenants` count (non-removing) for MSP's `client_id` | `whitelabel-branding` |

Pending Approvals card gets an amber ring and notification dot when count > 0.

Card styling: `rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl ring-1 ring-white/10`.

### 4. Billing Snapshot (Live Stripe)

Shown only when `charges_enabled = true`. Hidden entirely if Stripe is not connected.

Data pulled via live Stripe API calls on the MSP's connected account:

| Metric | Stripe API | Display |
|--------|-----------|---------|
| Revenue this month | `Invoice::all()` — `created >= first-of-month`, `status='paid'`, sum `amount_paid` | Currency-formatted (e.g., "$4,250.00") |
| Invoices this month | Same call, count | Plain number |
| Outstanding invoices | `Invoice::all()` — `status IN ('open','draft')` | Amber highlight if > 0 |
| Failed payments | `Charge::all()` — `created >= first-of-month`, filter failed | Red highlight if > 0 |

Stripe API failure: "Billing data temporarily unavailable" message.

Layout: Single card with "Billing" heading, 2x2 grid of stat cells, "View All" link to `ph-billing-invoices`.

Currency from Stripe account default. Amounts divided by 100 (cents → dollars).

### 5. Recent Tenants + Quick Actions

Two-column layout (`grid-cols-1 lg:grid-cols-2 gap-6`).

**Left — Recent Tenants:**
- Last 5 tenants: `eb_tenants` ordered by `created_at DESC LIMIT 5`.
- Each row: name (linked to `ph-tenant&id=`), status badge, relative date.
- Empty state: friendly message + CTA to create a tenant.
- Heading has "View All" link to `ph-tenants-manage`.

**Right — Quick Actions:**
- Vertical list of buttons:
  1. "Create New Tenant" → `ph-tenants` (primary accent button)
  2. "Create Product" → `ph-catalog-products` (ghost button)
  3. "Manage Stripe" → `ph-stripe-manage` (ghost button)
  4. "Signup Approvals" → `ph-signup-approvals` (ghost button + amber badge if pending > 0)

## Controller Data Shape

```php
'vars' => [
    'modulelink' => $baseLink,
    'msp'        => (array)$msp,
    'token'      => generate_token('plain'),

    'setup' => [
        'stripe_connected'  => bool,
        'has_products'      => bool,
        'has_plans'         => bool,
        'has_tenants'       => bool,
        'has_subscriptions' => bool,
        'all_complete'      => bool,
    ],

    'connect'     => ['hasAccount'=>bool, 'chargesEnabled'=>bool, 'payoutsEnabled'=>bool, 'detailsSubmitted'=>bool],
    'connect_due' => array,
    'connect_id_masked' => string,

    'counts' => [
        'tenants'              => int,
        'active_subscriptions' => int,
        'products'             => int,
        'plans'                => int,
        'pending_approvals'    => int,
        'whitelabel_tenants'   => int,
    ],

    'billing' => [  // null if Stripe not connected
        'revenue_this_month'    => int (cents),
        'invoices_this_month'   => int,
        'outstanding_invoices'  => int,
        'failed_payments'       => int,
        'currency'              => string,
    ],

    'recent_tenants' => array,  // last 5

    'onboardError'   => bool,
    'onboardSuccess' => bool,
    'onboardRefresh' => bool,
]
```

## Template Styling

Uses the dark UI playbook from `STYLING_NOTES.md`:
- Outer: `min-h-screen bg-slate-950 text-gray-100`
- Inner: `container mx-auto max-w-full px-4 pb-8 pt-6`
- Sidebar layout pattern from `tenants.tpl` (Alpine `sidebarCollapsed` + flex)
- Cards: `rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl ring-1 ring-white/10`
- UI tokens partial included

## Documentation Update

After implementation, add a section to `PARTNER_HUB.md` documenting:
- The new route, controller, and template
- What data the overview displays
- The setup wizard steps and collapse behavior

## Files Changed

| Action | File |
|--------|------|
| Create | `pages/partnerhub/OverviewController.php` |
| Create | `templates/whitelabel/overview.tpl` |
| Edit | `eazybackup.php` (add `ph-overview` route) |
| Edit | `templates/whitelabel/partials/sidebar_partner_hub.tpl` (Overview href) |
| Edit | `accounts/templates/eazyBackup/includes/nav_partner_hub.tpl` (Overview href) |
| Edit | `Docs/PARTNER_HUB.md` (documentation) |
