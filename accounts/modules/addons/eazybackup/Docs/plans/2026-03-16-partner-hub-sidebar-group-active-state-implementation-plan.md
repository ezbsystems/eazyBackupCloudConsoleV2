# Partner Hub Sidebar Group Active State Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make grouped Partner Hub sidebar sections render open on active child pages and ensure only the active child link is highlighted.

**Architecture:** Compute per-group active flags in `sidebar_partner_hub.tpl`, initialize each Alpine submenu state from those flags, and keep top-level group buttons visually neutral. Protect the change with a focused contract test that checks the expected template markers.

**Tech Stack:** Smarty, Alpine.js, Tailwind CSS, PHP contract tests.

---

### Task 1: Add a failing contract test for grouped sidebar behavior

**Files:**
- Create: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_state_contract_test.php`
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_state_contract_test.php`

**Step 1: Write the failing test**

Create a contract test that reads `accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl` and asserts markers for:

- Smarty group-active assignments for `catalog`, `billing`, `money`, `stripe`, and `settings`
- `x-data` open-state initialization from those flags
- Child-link markers for each grouped section
- Top-level grouped buttons using only neutral classes instead of active-state conditional classes

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_state_contract_test.php`

Expected: `FAIL` output for the missing group-active markers and/or open-state initialization markers.

**Step 3: Do not change production code yet**

Confirm the test is failing for the intended reason before editing the template.

---

### Task 2: Implement grouped sidebar active/open behavior

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl`

**Step 1: Add Smarty active-group flags**

At the top of the file, add per-group assignments based on `$ebPhSidebarPage`:

- `catalogGroupActive`
- `billingGroupActive`
- `moneyGroupActive`
- `stripeGroupActive`
- `settingsGroupActive`

Use exact page-id membership checks only.

**Step 2: Initialize Alpine submenu state from active-group flags**

Update each grouped section:

- `Catalog`
- `Billing`
- `Money`
- `Stripe Account`
- `Settings`

Change `x-data="{ ...Open: false }"` to initialize from the matching Smarty flag so the group is open on initial render when a child page is active.

**Step 3: Remove active-state styling from grouped section headers**

Update the top-level group buttons so they always use neutral styling:

- `text-slate-400 hover:text-white hover:bg-white/5`

Do not apply:

- `bg-white/10`
- `text-white`
- `ring-1 ring-white/20`

based on child activity.

**Step 4: Keep child-link highlighting unchanged**

Ensure child links still highlight only when `$ebPhSidebarPage` exactly matches the child page id.

---

### Task 3: Verify the change

**Files:**
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_state_contract_test.php`
- Test: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl`

**Step 1: Run the focused contract test**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_state_contract_test.php`

Expected: `partnerhub-sidebar-group-state-contract-ok`

**Step 2: Run a syntax/lint sanity check if applicable**

Run any lightweight validation needed for touched files. For this change, confirm the template remains parseable via the contract test and check IDE diagnostics.

**Step 3: Manual verification checklist**

Verify these pages in the browser if available:

- `ph-catalog-products` or `ph-catalog-plans`
- `ph-billing-subscriptions`
- `ph-money-balance`
- `ph-stripe-connect`
- `ph-settings-email`

Expected:

- matching group is open on load
- only the active child row is highlighted
- expanding other groups does not highlight their heading

---

## Execution handoff

Plan complete and saved to `accounts/modules/addons/eazybackup/Docs/plans/2026-03-16-partner-hub-sidebar-group-active-state-implementation-plan.md`.

Two execution options:

1. **Subagent-driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration
2. **Parallel session (separate)** - Open new session with executing-plans, batch execution with checkpoints
