# Billing Payment New Header Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Update the standalone Partner Hub one-time payment page so it uses the shared header layout pattern from the newer Partner Hub templates.

**Architecture:** Keep the existing billing payment page route, form fields, and Stripe behavior intact. Only refactor the page shell so the heading and page-level navigation move into a tenants-style header row above the content area, then lock the structure with a small contract test.

**Tech Stack:** Smarty templates, Alpine.js, PHP contract tests

---

### Task 1: Lock the header contract

**Files:**
- Create: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_header_contract_test.php`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl`

**Step 1: Write the failing test**

```php
<?php

$template = file_get_contents(__DIR__ . '/../../templates/whitelabel/billing-payment-new.tpl');

if (strpos($template, 'border-b border-slate-800/60 px-6 py-4') === false) {
    echo "FAIL: missing shared page header\n";
    exit(1);
}

if (strpos($template, 'href="{$modulelink}&a=ph-billing-payments"') === false) {
    echo "FAIL: missing back to payments action\n";
    exit(1);
}

if (strpos($template, '<h1 class="text-xl font-semibold text-slate-50 tracking-tight">') !== false) {
    echo "FAIL: old in-form heading still present\n";
    exit(1);
}
```

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_header_contract_test.php`

Expected: FAIL because the old standalone template still renders the title inside the content card and lacks the shared page header.

**Step 3: Write minimal implementation**

```smarty
<main class="flex-1 min-w-0 overflow-x-auto">
  <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
    <div>
      <h1 class="text-2xl font-semibold tracking-tight">New One-time Payment</h1>
      <p class="mt-1 text-sm text-slate-400">Charge a saved card for setup fees, project work, or ad-hoc adjustments.</p>
    </div>
    <a href="{$modulelink}&a=ph-billing-payments" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5">Back to Payments</a>
  </div>
  <div class="p-6">
    {*
      existing form card remains here
    *}
  </div>
</main>
```

**Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_header_contract_test.php`

Expected: `partnerhub-billing-payment-new-header-contract-ok`

**Step 5: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_header_contract_test.php \
  accounts/modules/addons/eazybackup/Docs/plans/2026-03-16-billing-payment-new-header-design.md \
  accounts/modules/addons/eazybackup/Docs/plans/2026-03-16-billing-payment-new-header-implementation-plan.md
git commit -m "refactor: align billing payment page header"
```
