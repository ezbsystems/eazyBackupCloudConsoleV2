# Partner Hub Sidebar Group Spacing Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add consistent spacing between grouped Partner Hub section headers and submenu rows so active and hover backgrounds no longer touch adjacent elements.

**Architecture:** Keep the existing grouped submenu structure and active styling, but add a shared spacing pattern on each submenu wrapper in `sidebar_partner_hub.tpl`. Protect the layout change with a focused contract test that checks the submenu wrapper classes.

**Tech Stack:** Smarty, Alpine.js, Tailwind CSS, PHP contract tests.

---

### Task 1: Add a failing contract test for grouped submenu spacing

**Files:**
- Create: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_spacing_contract_test.php`
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_spacing_contract_test.php`

**Step 1: Write the failing test**

Create a contract test that reads `accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl` and asserts spacing markers for the grouped submenu wrappers for:

- `Catalog`
- `Billing`
- `Money`
- `Stripe Account`
- `Settings`

Look for a shared wrapper class pattern including `mt-2` and `space-y-1`.

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_spacing_contract_test.php`

Expected: `FAIL` output for missing submenu spacing markers.

---

### Task 2: Implement grouped submenu spacing

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl`

**Step 1: Update grouped submenu wrappers**

For the submenu content wrappers for:

- `Catalog`
- `Billing`
- `Money`
- `Stripe Account`
- `Settings`

add a small, shared spacing treatment such as:

- `mt-2`
- `space-y-1`

while preserving the existing indentation and border classes.

**Step 2: Keep child link styling unchanged**

Do not modify the child link active/hover classes unless required to preserve existing behavior.

---

### Task 3: Verify the spacing fix

**Files:**
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_spacing_contract_test.php`
- Test: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl`

**Step 1: Run the focused contract test**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_sidebar_group_spacing_contract_test.php`

Expected: `partnerhub-sidebar-group-spacing-contract-ok`

**Step 2: Run diagnostics**

Check IDE diagnostics for the touched files.

**Step 3: Manual verification checklist**

Verify in the browser that:

- the first submenu row does not touch the group header
- active and hovered submenu rows do not visually touch each other
- spacing is consistent across all grouped sections

---

## Execution handoff

Plan complete and saved to `accounts/modules/addons/eazybackup/Docs/plans/2026-03-16-partner-hub-sidebar-group-spacing-implementation-plan.md`.

Two execution options:

1. **Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration
2. **Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints
