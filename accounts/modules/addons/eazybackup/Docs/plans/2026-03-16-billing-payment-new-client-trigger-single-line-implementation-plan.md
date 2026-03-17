# Billing Payment New Client Trigger Single-Line Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Keep the closed Client picker trigger on `billing-payment-new.tpl` to a single truncated line.

**Architecture:** Reuse the existing searchable Alpine client picker and change only the closed trigger presentation. Remove the stacked helper line, render the selected tenant summary in one row, and lock the UI contract with a small template-level test update.

**Tech Stack:** Smarty templates, Alpine.js, PHP contract tests

---

### Task 1: Lock the single-line trigger contract

**Files:**
- Modify: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl`

**Step 1: Write the failing test**

```php
<?php

$template = file_get_contents(__DIR__ . '/../../templates/whitelabel/billing-payment-new.tpl');

if (strpos($template, 'truncate whitespace-nowrap') === false) {
    echo "FAIL: missing single-line trigger text marker\n";
    exit(1);
}

if (strpos($template, 'Search by tenant name or contact email.') !== false) {
    echo "FAIL: stacked helper text still present in trigger\n";
    exit(1);
}
```

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php`

Expected: FAIL because the closed trigger still uses a stacked two-line label.

**Step 3: Write minimal implementation**

```smarty
<div class="min-w-0 truncate whitespace-nowrap">
  <span x-text="selectedTenant() ? selectedTenant().name : 'Select a client'"></span>
</div>
```

**Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php`

Expected: `partnerhub-billing-payment-new-client-picker-contract-ok`

**Step 5: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php \
  accounts/modules/addons/eazybackup/Docs/plans/2026-03-16-billing-payment-new-client-trigger-single-line-design.md \
  accounts/modules/addons/eazybackup/Docs/plans/2026-03-16-billing-payment-new-client-trigger-single-line-implementation-plan.md
git commit -m "fix: keep billing client trigger to one line"
```
