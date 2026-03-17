# Partner Hub Sidebar Group Active State — Design

**Goal:** Keep grouped Partner Hub sidebar sections open when the current page is a child route, and ensure only the active child link is highlighted while the section heading remains visually neutral.

**Architecture:** Update the grouped sections in `templates/whitelabel/partials/sidebar_partner_hub.tpl` so each group derives a Smarty-side "has active child" flag from `$ebPhSidebarPage`. That flag initializes the Alpine open state for the group (`catalogOpen`, `billingOpen`, `moneyOpen`, `stripeOpen`, `settingsOpen`) and controls child-link highlighting only. Section heading buttons remain expandable/collapsible but no longer receive the active-state styling used by child links.

**Tech stack:** Smarty templates, Alpine.js, Tailwind CSS, lightweight PHP contract tests for template markers.

---

## Scope

Apply the same behavior to these grouped sidebar sections:

- `Catalog`
- `Billing`
- `Money`
- `Stripe Account`
- `Settings`

Out of scope:

- Ungrouped top-level links such as `Overview`, `Tenants`, `White-Label Tenants`, `Signup Approvals`, and `Storage Users (e3)`
- `Tenant Portal` links
- Changes to route/controller behavior

## Current Problems

1. Grouped sections initialize closed with Alpine state like `x-data="{ billingOpen: false }"`, so visiting a child page such as `billing-subscriptions` does not auto-open the matching group.
2. Top-level group buttons currently use the same active-state styling when any child page is active, so opening the section while on a child route highlights both the section heading and the child link.

## Proposed Behavior

For each grouped section:

- Determine whether the current page belongs to the group using `$ebPhSidebarPage`.
- Initialize the corresponding Alpine open state from that value.
- Keep the child link highlight behavior as-is for the exact matching page only.
- Remove active-state classes from the top-level group button so it remains neutral whether expanded or collapsed.

Examples:

- On `billing-subscriptions`, the `Billing` section renders open and only `Subscriptions` is highlighted.
- On `money-balance`, the `Money` section renders open and only `Balance & Reports` is highlighted.
- If the user manually expands `Catalog` while on `billing-subscriptions`, `Catalog` opens but no `Catalog` heading or child is highlighted unless one of its children is active.

## Implementation Notes

### 1. Group activity flags

At the top of the sidebar partial, define per-group flags from `$ebPhSidebarPage`, for example:

- `catalogGroupActive` when page is `catalog-products` or `catalog-plans`
- `billingGroupActive` when page is `billing-subscriptions`, `billing-invoices`, or `billing-payments`
- `moneyGroupActive` when page is `money-payouts`, `money-disputes`, or `money-balance`
- `stripeGroupActive` when page is `stripe-connect` or `stripe-manage`
- `settingsGroupActive` when page is `settings-checkout`, `settings-tax`, or `settings-email`

### 2. Group open state

Use each flag to initialize its Alpine state, for example:

- `x-data="{ billingOpen: {if $billingGroupActive}true{else}false{/if} }"`

This makes the current section render open on first load while still allowing manual toggling afterward.

### 3. Top-level button styling

Use only the neutral button styling for the grouped section headers:

- Neutral: `text-slate-400 hover:text-white hover:bg-white/5`
- No `bg-white/10`, `text-white`, or `ring-1 ring-white/20` based on child activity

### 4. Child link styling

Keep exact-page active logic on the child anchors only, so the highlighted row always corresponds to the current page.

## Testing

Add a focused contract test covering:

- grouped sections derive active-group flags
- Alpine open state initializes from those flags
- grouped section header buttons no longer use child-active highlight conditions

Manual verification:

- Visit one child page in each group and confirm the group is open by default
- Expand/collapse groups from unrelated pages and confirm no top-level double-highlight appears

---

*Implementation plan: see `2026-03-16-partner-hub-sidebar-group-active-state-implementation-plan.md`.*
