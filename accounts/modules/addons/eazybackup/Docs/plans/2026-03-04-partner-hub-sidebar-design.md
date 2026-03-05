# Partner Hub Vertical Sidebar — Design

**Goal:** Add a fixed left vertical sidebar to all Partner Hub full-page templates, using the same layout and collapse behaviour as the client-area Backup Dashboard (vaults), and keep nav items and visibility in sync with the existing Partner Hub header nav.

**Architecture:** One new sidebar partial plus a shared layout wrapper. Each Partner Hub template wraps its content in the same shell: outer container → inner content card → Alpine `x-data` wrapper (collapse state + resize) → `flex` → sidebar include → `main` (flex-1 min-w-0 overflow-x-auto) → existing content. The sidebar uses the same `eb_ph_show_*` conditionals as `nav_partner_hub.tpl` so header and sidebar stay in sync.

**Tech stack:** Smarty templates, Alpine.js (collapse state, localStorage), Tailwind CSS. No new PHP; controllers already pass template vars and may set `ebPhSidebarPage` where needed.

---

## 1. Sidebar partial

**File:** `accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl`

- **Structure:** Mirror `clientarea/partials/sidebar.tpl`: `<aside>` with `:class="sidebarCollapsed ? 'w-20' : 'w-48'"`, same border/background/rounded classes, transition. Inner: header block (“Partner Hub”), `<nav>`, collapse button at bottom.
- **State:** Expects parent to provide Alpine `sidebarCollapsed` and `toggleCollapse()` (and optional `handleResize()`). Uses same localStorage key pattern as clientarea or a Partner-Hub-specific key (e.g. `eb_ph_sidebar_collapsed`).
- **Active page:** Template receives `ebPhSidebarPage` (e.g. `overview`, `tenants`, `billing-subscriptions`). Each nav link gets active class when `$ebPhSidebarPage` matches (e.g. `bg-white/10 text-white ring-1 ring-white/20`).
- **Conditionals:** Use the same as `nav_partner_hub.tpl`:
  - `eb_ph_show_overview` → Overview
  - `eb_ph_show_clients` → Clients
  - White-Label Tenants, Tenant Management, Signup Approvals, Storage Users (e3) — no conditional in nav, include always or match existing nav.
  - `eb_ph_show_catalog` → Catalog (Products, Plans)
  - `eb_ph_show_billing` → Billing (Subscriptions, Invoices, Payments)
  - `eb_ph_show_money` → Money (Payouts, Disputes, Balance & Reports)
  - `eb_ph_show_stripe` → Stripe Account (Connect & Status, Manage Account)
  - `eb_ph_show_settings` → Settings (Checkout & Dunning, Tax & Invoicing, Email Templates)
  - Tenant Portal section (links to portal; active state optional).
- **Collapse:** Icon-only when collapsed; labels `x-show="!sidebarCollapsed"`; collapse toggle button at bottom (same as clientarea sidebar).
- **URLs:** Same as nav: `{$WEB_ROOT}/index.php?m=eazybackup&a=ph-*` and `{$WEB_ROOT}/portal/...` for Tenant Portal.

## 2. Layout wrapper (per template)

**Pattern (from vaults.tpl):**

- After the inner container (e.g. `<div class="container mx-auto max-w-full px-4 pb-8 pt-6">`), replace the single content card with:
  - One wrapper div: content card classes (`rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-...`) + Alpine:
    - `x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { ... }, handleResize() { ... } }"`
    - `x-init="window.addEventListener('resize', () => handleResize())"`
  - `<div class="flex">`
  - `{include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='...'}`
  - `<main class="flex-1 min-w-0 overflow-x-auto">`
  - Existing page content (header, sections, etc.)
  - `</main>`
  - `</div>`
  - Close wrapper div.

Templates that currently have a single card div wrapping everything should have that card become the wrapper that also contains the flex + sidebar + main. No duplicate cards.

## 3. Page IDs (`ebPhSidebarPage`)

Map each template to one sidebar page id for active state:

| Template | ebPhSidebarPage |
|----------|-----------------|
| clients.tpl | overview or clients |
| tenants.tpl | tenants |
| tenant-detail.tpl | tenants |
| branding-list.tpl | branding-list |
| branding.tpl | branding |
| signup-approvals.tpl | signup-approvals |
| signup-settings.tpl | signup-settings |
| catalog-products.tpl, catalog-products-list.tpl | catalog-products |
| catalog-plans.tpl | catalog-plans |
| billing-subscriptions.tpl | billing-subscriptions |
| billing-invoices.tpl | billing-invoices |
| billing-payments.tpl | billing-payments |
| billing-payment-new.tpl | billing-payments |
| money-payouts.tpl | money-payouts |
| money-disputes.tpl | money-disputes |
| money-balance.tpl | money-balance |
| stripe-connect.tpl | stripe-connect |
| stripe-manage.tpl | stripe-manage |
| settings-checkout.tpl | settings-checkout |
| settings-tax.tpl | settings-tax |
| settings-email.tpl | settings-email |
| client-view.tpl | clients (or omit sidebar if not in nav) |

Tenant-detail: keep in-page tabs as-is; sidebar highlights “Tenant Management” (tenants). Pages not in the nav can pass a non-matching page id or omit the sidebar per project convention.

## 4. Templates to update

Apply the layout wrapper and sidebar include to all Partner Hub full-page whitelabel templates that are reachable from the Partner Hub nav:

- tenants.tpl, tenant-detail.tpl, clients.tpl
- branding-list.tpl, branding.tpl
- signup-approvals.tpl, signup-settings.tpl
- catalog-products.tpl, catalog-products-list.tpl, catalog-plans.tpl
- billing-subscriptions.tpl, billing-invoices.tpl, billing-payments.tpl, billing-payment-new.tpl
- money-payouts.tpl, money-disputes.tpl, money-balance.tpl
- stripe-connect.tpl, stripe-manage.tpl
- settings-checkout.tpl, settings-tax.tpl, settings-email.tpl
- Optionally: client-view.tpl, email-templates.tpl, catalog-product.tpl, etc., if they are full Partner Hub pages and should show the sidebar.

## 5. Edge cases

- **tenant-detail.tpl:** In-page tabs remain; sidebar active = “Tenants” (or “Tenant Management”).
- **Tenant Portal links:** Remain normal links; active state for portal pages is optional.
- **x-cloak:** If the sidebar uses Alpine show/hide, ensure `[x-cloak] { display: none !important; }` is present (e.g. in a shared partial or in the first template that includes the sidebar).
- **Scrollbar styling:** Reuse the same scrollbar CSS as vaults if the sidebar scrolls (e.g. in `partials/_ui-tokens.tpl` or in the sidebar partial’s first included template).

## 6. Docs (optional)

- **PARTNER_HUB.md** or **STYLING_NOTES.md:** Short section describing that Partner Hub uses a vertical sidebar partial, how to include it (wrapper + include + ebPhSidebarPage), and how to add a new Partner Hub page to the sidebar (copy nav item with same conditional, add template to list, set ebPhSidebarPage).

---

*Implementation plan: see `2026-03-04-partner-hub-sidebar-implementation-plan.md`.*
