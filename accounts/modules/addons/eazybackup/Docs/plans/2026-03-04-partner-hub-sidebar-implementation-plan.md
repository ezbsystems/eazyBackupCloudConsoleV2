# Partner Hub Vertical Sidebar — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a fixed left vertical sidebar to all Partner Hub full-page templates, matching the client-area vaults layout and collapse behaviour, with nav items and visibility in sync with `nav_partner_hub.tpl`.

**Architecture:** One new partial `whitelabel/partials/sidebar_partner_hub.tpl` (conditionals, collapse, `ebPhSidebarPage` for active state). Each Partner Hub template gets the same wrapper: content card + Alpine `x-data` (sidebarCollapsed, toggleCollapse, handleResize) → flex → include sidebar → main (flex-1 min-w-0 overflow-x-auto) → existing content.

**Tech Stack:** Smarty, Alpine.js, Tailwind. Reference: `clientarea/partials/sidebar.tpl`, `clientarea/vaults.tpl` (lines 36–58), `templates/eazyBackup/includes/nav_partner_hub.tpl`.

---

## Task 1: Create the Partner Hub sidebar partial

**Files:**
- Create: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl`

**Step 1: Create directory and file**

Ensure directory exists: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/`. Create the partial with:
- `{assign var=ebPhSidebarPage value=$ebPhSidebarPage|default:''}` at top.
- `<aside :class="sidebarCollapsed ? 'w-20' : 'w-48'" class="relative flex-shrink-0 border-r border-slate-800/80 bg-slate-900/50 rounded-tl-3xl rounded-bl-3xl transition-all duration-300 ease-in-out">` (same structure as clientarea sidebar).
- Header block with “Partner Hub” title and icon (e.g. dashboard/grid icon).
- `<nav class="rounded-bl-3xl flex-1 p-3 space-y-1 overflow-y-auto">`.
- For each nav section, use the **same conditionals** as `nav_partner_hub.tpl`: `{if !isset($eb_ph_show_overview) || $eb_ph_show_overview}`, `$eb_ph_show_clients`, `$eb_ph_show_catalog`, `$eb_ph_show_billing`, `$eb_ph_show_money`, `$eb_ph_show_stripe`, `$eb_ph_show_settings`. Include: Overview (link to ph-clients), Clients (ph-clients), White-Label Tenants (whitelabel-branding), Tenant Management (ph-tenants-manage), Signup Approvals (ph-signup-approvals), Storage Users (e3), Catalog (Products, Plans), Billing (Subscriptions, Invoices, Payments), Money (Payouts, Disputes, Balance & Reports), Stripe Account (Connect & Status, Manage Account), Settings (Checkout & Dunning, Tax & Invoicing, Email Templates), Tenant Portal (Billing, Services, Cloud Storage). Use `$ebPhSidebarPage eq 'overview'` etc. for active class: `bg-white/10 text-white ring-1 ring-white/20`; inactive: `text-slate-400 hover:text-white hover:bg-white/5`. Collapsed: `:class="sidebarCollapsed && 'justify-center'"`, labels with `x-show="!sidebarCollapsed"`. Collapse button at bottom: `@click="toggleCollapse()"` and localStorage key `eb_ph_sidebar_collapsed` (parent provides `toggleCollapse`).
- URLs: `{$WEB_ROOT}/index.php?m=eazybackup&a=ph-clients` etc. and `{$WEB_ROOT}/portal/...` for Tenant Portal.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl
git commit -m "feat(partner-hub): add vertical sidebar partial with conditionals and collapse"
```

---

## Task 2: Ensure x-cloak and scrollbar styles for Partner Hub

**Files:**
- Modify: First Partner Hub template that will include the sidebar (e.g. `tenants.tpl` in Task 3), or a shared include already used by whitelabel (e.g. `partials/_ui-tokens.tpl`).

**Step 1: Add [x-cloak] and scrollbar CSS if missing**

If `_ui-tokens.tpl` or the whitelabel templates do not already include `[x-cloak] { display: none !important; }` and the same scrollbar styles as vaults.tpl (lines 2–28), add a small `{literal}<style>...</style>{/literal}` block either in `_ui-tokens.tpl` or at the top of the first template you wrap (e.g. tenants.tpl). Reuse the vaults scrollbar/CSS so the sidebar doesn’t flash.

**Step 2: Commit (if changes made)**

```bash
git add <path>
git commit -m "style(partner-hub): ensure x-cloak and scrollbar styles for sidebar"
```

---

## Task 3: Add layout and sidebar to tenants.tpl

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/tenants.tpl`

**Step 1: Wrap content with sidebar layout**

After `<div class="container mx-auto max-w-full px-4 pb-8 pt-6">`, replace the single inner `<div class="rounded-3xl border...">` with the vaults-style shell:
- Opening wrapper div with `x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }"` and `x-init="window.addEventListener('resize', () => handleResize())"` and same classes (`rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]`).
- `<div class="flex">`
- `{include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='tenants'}`
- `<main class="flex-1 min-w-0 overflow-x-auto">`
- Move the existing `<main>` (or main content block) content inside this new `<main>` and remove the old `<main>` tag if present.
- Close `</main>`, `</div>`, and the wrapper div.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/tenants.tpl
git commit -m "feat(partner-hub): add sidebar layout to tenants.tpl"
```

---

## Task 4: Add layout and sidebar to tenant-detail.tpl

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/tenant-detail.tpl`

**Step 1:** Apply the same wrapper + flex + include sidebar (`ebPhSidebarPage='tenants'`) + main pattern. Keep in-page tabs unchanged. Close main, flex, wrapper.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/tenant-detail.tpl
git commit -m "feat(partner-hub): add sidebar layout to tenant-detail.tpl"
```

---

## Task 5: Add layout and sidebar to clients.tpl

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/clients.tpl`

**Step 1:** Same wrapper + flex + include (`ebPhSidebarPage='clients'` or `overview`) + main. Close main, flex, wrapper.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/clients.tpl
git commit -m "feat(partner-hub): add sidebar layout to clients.tpl"
```

---

## Task 6: Add layout and sidebar to branding-list.tpl and branding.tpl

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/branding-list.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/branding.tpl`

**Step 1:** For each file, apply the same wrapper + flex + sidebar include (`ebPhSidebarPage='branding-list'` and `ebPhSidebarPage='branding'` respectively) + main. Close main, flex, wrapper.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/branding-list.tpl accounts/modules/addons/eazybackup/templates/whitelabel/branding.tpl
git commit -m "feat(partner-hub): add sidebar layout to branding-list and branding"
```

---

## Task 7: Add layout and sidebar to signup-approvals.tpl and signup-settings.tpl

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/signup-approvals.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/signup-settings.tpl`

**Step 1:** Same pattern; `ebPhSidebarPage='signup-approvals'` and `'signup-settings'`.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/signup-approvals.tpl accounts/modules/addons/eazybackup/templates/whitelabel/signup-settings.tpl
git commit -m "feat(partner-hub): add sidebar layout to signup-approvals and signup-settings"
```

---

## Task 8: Add layout and sidebar to catalog templates

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-products.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-products-list.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl`

**Step 1:** Same wrapper + flex + sidebar + main. Use `ebPhSidebarPage='catalog-products'` for products and products-list, `'catalog-plans'` for catalog-plans.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/catalog-products.tpl accounts/modules/addons/eazybackup/templates/whitelabel/catalog-products-list.tpl accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl
git commit -m "feat(partner-hub): add sidebar layout to catalog templates"
```

---

## Task 9: Add layout and sidebar to billing templates

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-subscriptions.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-invoices.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-payments.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl`

**Step 1:** Same pattern. `ebPhSidebarPage='billing-subscriptions'`, `'billing-invoices'`, `'billing-payments'` (for both payments and payment-new).

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/billing-subscriptions.tpl accounts/modules/addons/eazybackup/templates/whitelabel/billing-invoices.tpl accounts/modules/addons/eazybackup/templates/whitelabel/billing-payments.tpl accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl
git commit -m "feat(partner-hub): add sidebar layout to billing templates"
```

---

## Task 10: Add layout and sidebar to money templates

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/money-payouts.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/money-disputes.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/money-balance.tpl`

**Step 1:** Same pattern. `ebPhSidebarPage='money-payouts'`, `'money-disputes'`, `'money-balance'`.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/money-payouts.tpl accounts/modules/addons/eazybackup/templates/whitelabel/money-disputes.tpl accounts/modules/addons/eazybackup/templates/whitelabel/money-balance.tpl
git commit -m "feat(partner-hub): add sidebar layout to money templates"
```

---

## Task 11: Add layout and sidebar to Stripe templates

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/stripe-connect.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/stripe-manage.tpl`

**Step 1:** Same pattern. `ebPhSidebarPage='stripe-connect'`, `'stripe-manage'`.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/stripe-connect.tpl accounts/modules/addons/eazybackup/templates/whitelabel/stripe-manage.tpl
git commit -m "feat(partner-hub): add sidebar layout to Stripe templates"
```

---

## Task 12: Add layout and sidebar to settings templates

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/settings-checkout.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/settings-tax.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/settings-email.tpl`

**Step 1:** Same pattern. `ebPhSidebarPage='settings-checkout'`, `'settings-tax'`, `'settings-email'`.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/settings-checkout.tpl accounts/modules/addons/eazybackup/templates/whitelabel/settings-tax.tpl accounts/modules/addons/eazybackup/templates/whitelabel/settings-email.tpl
git commit -m "feat(partner-hub): add sidebar layout to settings templates"
```

---

## Task 13 (optional): Document sidebar in PARTNER_HUB.md or STYLING_NOTES.md

**Files:**
- Modify: `accounts/modules/addons/eazybackup/Docs/PARTNER_HUB.md` or `STYLING_NOTES.md`

**Step 1:** Add a short section: Partner Hub full-page views use the vertical sidebar partial `whitelabel/partials/sidebar_partner_hub.tpl`. To use it: wrap the page in the content card + Alpine wrapper (sidebarCollapsed, toggleCollapse, handleResize), then flex → include sidebar with `ebPhSidebarPage='page-id'` → main. To add a new Partner Hub page to the sidebar: add the link with the same conditional as in `nav_partner_hub.tpl`, and set `ebPhSidebarPage` in the new template.

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/Docs/PARTNER_HUB.md
git commit -m "docs(partner-hub): document vertical sidebar usage and new pages"
```

---

## Execution handoff

Plan complete and saved to `accounts/modules/addons/eazybackup/Docs/plans/2026-03-04-partner-hub-sidebar-implementation-plan.md`.

**Two execution options:**

1. **Subagent-driven (this session)** — Dispatch a fresh subagent per task, review between tasks, fast iteration. **REQUIRED SUB-SKILL:** @superpowers:subagent-driven-development  
2. **Parallel session (separate)** — Open a new session in the same repo and run through the plan task-by-task with checkpoints. **REQUIRED SUB-SKILL:** New session uses @superpowers:executing-plans  

Which approach do you want to use?
