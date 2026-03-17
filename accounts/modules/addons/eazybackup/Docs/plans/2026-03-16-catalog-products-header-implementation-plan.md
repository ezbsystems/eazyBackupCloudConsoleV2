# Catalog Products Header Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Align `catalog-products.tpl` with the newer Partner Hub header and content shell pattern.

**Architecture:** Reuse the existing header row already present in the template and limit the implementation to the remaining shell inconsistency: the extra top margin on the first content section. Preserve all current product cards, Stripe-connected product content, and modal behavior.

**Tech Stack:** Smarty templates, Alpine.js, Tailwind utility classes.

---

### Task 1: Normalize the page content shell

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-products.tpl`

**Step 1: Verify the current shell mismatch**

Read:
- `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-products.tpl`
- `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl`

Expected:
- Confirm the products page already has the correct header row
- Confirm the first section still has an extra `mt-6`

**Step 2: Apply the minimal layout update**

Change:
- Remove the first section's extra top margin so content starts directly under the shared `p-6` wrapper

Keep:
- Page heading and subtitle
- `New Product` button
- Product and Stripe-connected product sections
- Product panel and scripts

**Step 3: Verify diagnostics**

Run diagnostics on:
- `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-products.tpl`

Expected:
- No linter/template diagnostics

### Task 2: Final verification

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-products.tpl`

**Step 1: Re-read the edited region**

Confirm:
- Header row remains unchanged
- The first content section no longer has extra top spacing

**Step 2: Run diagnostics**

Run diagnostics on the edited template.

Expected:
- No linter/template diagnostics
