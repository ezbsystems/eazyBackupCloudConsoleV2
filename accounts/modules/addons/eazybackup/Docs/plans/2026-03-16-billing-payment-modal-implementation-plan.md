# Billing Payment Modal Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the Partner Hub standalone one-time payment page with an inline modal on the Payments list, including a searchable Alpine tenant picker and in-modal saved/new card selection.

**Architecture:** Keep the Payments list page as the primary surface and embed the payment form as a dedicated partial. Add a small JSON endpoint for loading saved Stripe payment methods for the selected tenant, while reusing the existing payment-intent creation route for submission.

**Tech Stack:** WHMCS addon PHP controllers, Smarty templates, Alpine.js, Stripe.js, Stripe Connect payment intents, lightweight PHP contract tests

---

### Task 1: Lock the contract

**Files:**
- Create: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_modal_contract_test.php`
- Test: `accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_modal_contract_test.php`

**Step 1: Write the failing test**

Create a contract test that requires:
- the Payments page to open a modal instead of linking to `ph-billing-payment-new`,
- the modal partial include to exist,
- the billing controller to expose tenant data and a saved-card lookup action,
- the router to expose the saved-card lookup route.

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_modal_contract_test.php`
Expected: `FAIL` output for missing modal markers, route markers, and/or partial file.

**Step 3: Write minimal implementation**

Add only the route/controller/template changes needed to satisfy the contract.

**Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_modal_contract_test.php`
Expected: `partnerhub-billing-payment-modal-contract-ok`

### Task 2: Add modal data and saved-card endpoint

**Files:**
- Modify: `accounts/modules/addons/eazybackup/pages/partnerhub/BillingController.php`
- Modify: `accounts/modules/addons/eazybackup/lib/PartnerHub/StripeService.php`
- Modify: `accounts/modules/addons/eazybackup/eazybackup.php`

**Step 1: Write the failing test**

Use the contract test from Task 1 as the failing check for the new route/action markers.

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_modal_contract_test.php`
Expected: route/action marker failures remain until the endpoint exists.

**Step 3: Write minimal implementation**

- Pass tenant records into `eb_ph_billing_payments()`.
- Add `eb_ph_billing_payment_methods(array $vars): void`.
- Add Stripe helper support for listing a customer's card payment methods.
- Wire the new route through `eazybackup.php`.

**Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_modal_contract_test.php`
Expected: no route/action failures.

### Task 3: Convert the UI to an inline modal

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-payments.tpl`
- Create: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/billing-payment-modal.tpl`
- Reference only: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-payment-new.tpl`

**Step 1: Write the failing test**

Use the same contract test as the UI guard for modal markers and the new partial.

**Step 2: Run test to verify it fails**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_modal_contract_test.php`
Expected: modal trigger/include/partial failures.

**Step 3: Write minimal implementation**

- Add page-level Alpine state for opening/closing the modal.
- Replace the `New Payment` link and empty-state CTA with modal buttons.
- Include the new partial.
- Build the tenant search picker, saved/new card sections, Stripe Elements mount flow, validation, and submit handling.

**Step 4: Run test to verify it passes**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_modal_contract_test.php`
Expected: `partnerhub-billing-payment-modal-contract-ok`

### Task 4: Verify edited files

**Files:**
- Verify: `accounts/modules/addons/eazybackup/templates/whitelabel/billing-payments.tpl`
- Verify: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/billing-payment-modal.tpl`
- Verify: `accounts/modules/addons/eazybackup/pages/partnerhub/BillingController.php`
- Verify: `accounts/modules/addons/eazybackup/lib/PartnerHub/StripeService.php`
- Verify: `accounts/modules/addons/eazybackup/eazybackup.php`

**Step 1: Run the contract test**

Run: `php accounts/modules/addons/eazybackup/bin/dev/partnerhub_billing_payment_modal_contract_test.php`
Expected: `partnerhub-billing-payment-modal-contract-ok`

**Step 2: Run lint/diagnostics check**

Check the recently edited files for diagnostics and resolve any new issues.

**Step 3: Review against the approved design**

Confirm the result includes:
- inline modal launch,
- searchable Alpine tenant picker,
- improved sectioned form layout,
- saved card and new card payment options.
