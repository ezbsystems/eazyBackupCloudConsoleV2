# Billing Payment New Client Picker Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the standalone billing payment page's native Client select with a searchable Alpine menu that filters by tenant name and contact email.

**Architecture:** Keep the existing `billing-payment-new.tpl` page shell and payment submission flow intact. Introduce a local Alpine tenant-picker state object inside the page form, render a button-triggered menu with search and selection UI, and preserve the existing submit contract by syncing the chosen tenant ID into the hidden `#np-tenant` input.

**Tech Stack:** Smarty templates, Alpine.js, PHP contract tests

---

### Task 1: Lock the searchable picker contract

**Files:**
- Create: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl`

**Step 1: Write the failing test**

```php
<?php

$template = file_get_contents(__DIR__ . '/../../templates/whitelabel/billing-payment-new.tpl');

if (strpos($template, 'x-data=\'{ open: false, tenantSearch:') === false) {
    echo "FAIL: missing alpine client picker\n";
    exit(1);
}

if (strpos($template, 'Start typing a tenant name or email') === false) {
    echo "FAIL: missing tenant picker search input\n";
    exit(1);
}

if (strpos($template, "tenant.contact_email") === false) {
    echo "FAIL: missing contact email search/display marker\n";
    exit(1);
}

if (strpos($template, '<select id="np-tenant"') !== false) {
    echo "FAIL: legacy client select still present\n";
    exit(1);
}
```

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php`

Expected: FAIL because the page still uses a native select and has no searchable Alpine picker.

**Step 3: Write minimal implementation**

```smarty
<div class="relative" x-data='...tenant picker state...'>
  <input id="np-tenant" type="hidden" :value="selectedTenantId">
  <button type="button" @click="open = !open">...</button>
  <div x-show="open">
    <input type="text" x-model.debounce.150ms="tenantSearch" placeholder="Start typing a tenant name or email">
    <template x-for="tenant in filteredTenants()" :key="tenant.id">
      <button type="button" @click="selectTenant(tenant)">...</button>
    </template>
  </div>
</div>
```

**Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php`

Expected: `partnerhub-billing-payment-new-client-picker-contract-ok`

**Step 5: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl \
  accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_new_client_picker_contract_test.php \
  accounts/modules/addons/eazybackup/Docs/plans/2026-03-16-billing-payment-new-client-picker-design.md \
  accounts/modules/addons/eazybackup/Docs/plans/2026-03-16-billing-payment-new-client-picker-implementation-plan.md
git commit -m "feat: add searchable billing client picker"
```
