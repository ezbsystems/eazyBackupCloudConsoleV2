# Partner Hub Overview Page — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a dedicated Overview dashboard as the default Partner Hub landing page, with onboarding wizard, Stripe status, key metrics, live billing snapshot, recent tenants, and quick actions.

**Architecture:** Single synchronous controller (`OverviewController.php`) gathers all data — DB counts first, then Stripe API calls for connect status and billing — and passes to a Smarty template (`overview.tpl`) that renders five sections. Stripe failures degrade gracefully. Template uses the sidebar layout pattern from `tenants.tpl`.

**Tech Stack:** PHP 7.4+ / WHMCS Capsule ORM, Smarty templates, Tailwind CSS, Alpine.js, Stripe Connect API (via existing `StripeService`).

**Design doc:** `docs/plans/2026-03-05-partner-hub-overview-design.md`

---

### Task 1: Add Stripe billing helper methods to StripeService

The existing `listInvoices()` and `listCharges()` require a `$stripeCustomerId` parameter. The Overview page needs account-level queries (all invoices/charges across all tenants on the connected account). Add two new methods.

**Files:**
- Modify: `accounts/modules/addons/eazybackup/lib/PartnerHub/StripeService.php`

**Step 1: Add `listInvoicesForAccount` method**

After the existing `listCharges` method (around line 237), add:

```php
public function listInvoicesForAccount(string $stripeAccount, array $params = []): array
{
    if (!isset($params['limit'])) { $params['limit'] = 100; }
    return $this->request('GET', '/v1/invoices', $params, null, $stripeAccount);
}
```

**Step 2: Add `listChargesForAccount` method**

Immediately after the new method:

```php
public function listChargesForAccount(string $stripeAccount, array $params = []): array
{
    if (!isset($params['limit'])) { $params['limit'] = 100; }
    return $this->request('GET', '/v1/charges', $params, null, $stripeAccount);
}
```

**Step 3: Verify PHP syntax**

Run: `php -l accounts/modules/addons/eazybackup/lib/PartnerHub/StripeService.php`
Expected: `No syntax errors detected`

**Step 4: Commit**

```bash
git add accounts/modules/addons/eazybackup/lib/PartnerHub/StripeService.php
git commit -m "feat: add account-level invoice/charge list methods to StripeService"
```

---

### Task 2: Create the Overview controller

**Files:**
- Create: `accounts/modules/addons/eazybackup/pages/partnerhub/OverviewController.php`

**Step 1: Create the controller file**

Create `accounts/modules/addons/eazybackup/pages/partnerhub/OverviewController.php` with the full implementation. The controller must:

1. Call `eb_ph_tenants_require_context($vars)` for auth (this function is in `TenantsController.php`, which must be required first, or the function must be available; the router in `eazybackup.php` will require both files).
2. Gather DB counts scoped by `msp_id`:
   - `$tenantCount` from `eb_tenants` (non-deleted)
   - `$productCount` from `eb_catalog_products`
   - `$planCount` from `eb_plans`
   - `$activeSubCount` from `eb_subscriptions` where `stripe_status IN ('active','trialing')`
   - `$pendingApprovalCount` from `eb_whitelabel_signup_events` joined with `eb_whitelabel_tenants` where `t.client_id = $clientId` and `e.status = 'pending_approval'`
   - `$wlTenantCount` from `eb_whitelabel_tenants` where `client_id = $clientId` and `status NOT IN ('removing','removed')`
3. Gather Stripe Connect status (same pattern as `eb_ph_tenants_index` in TenantsController.php lines 367-389):
   - `$connect` array with `hasAccount`, `chargesEnabled`, `payoutsEnabled`, `detailsSubmitted`
   - `$connectDue` array from `requirements.currently_due`
   - `$connectIdMasked` — masked account ID for display
4. Gather live billing data (only if `$connect['chargesEnabled']` is true):
   - Use `StripeService::listInvoicesForAccount()` with `created[gte]` = first of current month, on the connected account
   - Compute `$revenueThisMonth` (sum of `amount_paid` from paid invoices), `$invoiceCountThisMonth`, `$outstandingInvoices` (status open/draft)
   - Use `StripeService::listChargesForAccount()` with `created[gte]` = first of month, filter `status === 'failed'` for `$failedPayments`
   - Get `$currency` from the Stripe account's `default_currency` field
   - Wrap all Stripe billing calls in try/catch; on failure set `$billingUnavailable = true`
5. Gather recent tenants: `eb_tenants` last 5, ordered by `created_at DESC`
6. Read onboard flags from query string (`onboard_error`, `onboard_success`, `onboard_refresh`)
7. Build setup wizard state (`$setup` array with booleans for each step + `all_complete`)
8. Return the WHMCS module output array with `templatefile => 'whitelabel/overview'` and all vars

```php
<?php

use WHMCS\Database\Capsule;
use PartnerHub\StripeService;

function eb_ph_overview_index(array $vars)
{
    require_once __DIR__ . '/TenantsController.php';
    [$clientId, $msp] = eb_ph_tenants_require_context($vars);

    $mspId = (int)$msp->id;
    $baseLink = (string)($vars['modulelink'] ?? 'index.php?m=eazybackup');

    // --- DB Counts ---
    $tenantCount = 0;
    $productCount = 0;
    $planCount = 0;
    $activeSubCount = 0;
    $pendingApprovalCount = 0;
    $wlTenantCount = 0;

    try {
        if (Capsule::schema()->hasTable('eb_tenants')) {
            $tenantCount = (int)Capsule::table('eb_tenants')
                ->where('msp_id', $mspId)
                ->where('status', '!=', 'deleted')
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_catalog_products')) {
            $productCount = (int)Capsule::table('eb_catalog_products')
                ->where('msp_id', $mspId)
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_plans')) {
            $planCount = (int)Capsule::table('eb_plans')
                ->where('msp_id', $mspId)
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_subscriptions')) {
            $activeSubCount = (int)Capsule::table('eb_subscriptions')
                ->where('msp_id', $mspId)
                ->whereIn('stripe_status', ['active', 'trialing'])
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_whitelabel_signup_events')
            && Capsule::schema()->hasTable('eb_whitelabel_tenants')) {
            $pendingApprovalCount = (int)Capsule::table('eb_whitelabel_signup_events as e')
                ->join('eb_whitelabel_tenants as t', 't.id', '=', 'e.tenant_id')
                ->where('t.client_id', $clientId)
                ->where('e.status', 'pending_approval')
                ->count();
        }
    } catch (\Throwable $__) {}

    try {
        if (Capsule::schema()->hasTable('eb_whitelabel_tenants')) {
            $wlTenantCount = (int)Capsule::table('eb_whitelabel_tenants')
                ->where('client_id', $clientId)
                ->whereNotIn('status', ['removing', 'removed'])
                ->count();
        }
    } catch (\Throwable $__) {}

    // --- Stripe Connect Status ---
    $connect = [
        'hasAccount' => false,
        'chargesEnabled' => false,
        'payoutsEnabled' => false,
        'detailsSubmitted' => false,
    ];
    $connectDue = [];
    $connectIdMasked = '';
    $stripeConnectId = '';
    $stripeCurrency = 'usd';

    try {
        $stripeConnectId = (string)($msp->stripe_connect_id ?? '');
        if ($stripeConnectId !== '') {
            $svc = new StripeService();
            $acct = $svc->retrieveAccount($stripeConnectId);
            $connect = [
                'hasAccount' => true,
                'chargesEnabled' => (bool)($acct['charges_enabled'] ?? false),
                'payoutsEnabled' => (bool)($acct['payouts_enabled'] ?? false),
                'detailsSubmitted' => (bool)($acct['details_submitted'] ?? false),
            ];
            $reqs = $acct['requirements'] ?? [];
            if (is_array($reqs) && isset($reqs['currently_due']) && is_array($reqs['currently_due'])) {
                $connectDue = $reqs['currently_due'];
            }
            $connectIdMasked = strlen($stripeConnectId) > 8
                ? substr($stripeConnectId, 0, 5) . '...' . substr($stripeConnectId, -4)
                : $stripeConnectId;
            $stripeCurrency = (string)($acct['default_currency'] ?? 'usd');
        }
    } catch (\Throwable $__) {}

    // --- Live Billing Snapshot ---
    $billing = null;
    $billingUnavailable = false;

    if ($connect['chargesEnabled'] && $stripeConnectId !== '') {
        try {
            $svc = $svc ?? new StripeService();
            $monthStart = (int)strtotime(date('Y-m-01 00:00:00'));

            $invResponse = $svc->listInvoicesForAccount($stripeConnectId, [
                'created[gte]' => $monthStart,
                'limit' => 100,
            ]);
            $invoices = $invResponse['data'] ?? [];

            $revenueThisMonth = 0;
            $invoiceCountThisMonth = count($invoices);
            foreach ($invoices as $inv) {
                if (($inv['status'] ?? '') === 'paid') {
                    $revenueThisMonth += (int)($inv['amount_paid'] ?? 0);
                }
            }

            $openResponse = $svc->listInvoicesForAccount($stripeConnectId, [
                'status' => 'open',
                'limit' => 100,
            ]);
            $outstandingInvoices = count($openResponse['data'] ?? []);

            $chargeResponse = $svc->listChargesForAccount($stripeConnectId, [
                'created[gte]' => $monthStart,
                'limit' => 100,
            ]);
            $failedPayments = 0;
            foreach (($chargeResponse['data'] ?? []) as $ch) {
                if (($ch['status'] ?? '') === 'failed') {
                    $failedPayments++;
                }
            }

            $billing = [
                'revenue_this_month' => $revenueThisMonth,
                'invoices_this_month' => $invoiceCountThisMonth,
                'outstanding_invoices' => $outstandingInvoices,
                'failed_payments' => $failedPayments,
                'currency' => strtoupper($stripeCurrency),
            ];
        } catch (\Throwable $__) {
            $billingUnavailable = true;
        }
    }

    // --- Recent Tenants ---
    $recentTenants = [];
    try {
        if (Capsule::schema()->hasTable('eb_tenants')) {
            $rows = Capsule::table('eb_tenants')
                ->where('msp_id', $mspId)
                ->where('status', '!=', 'deleted')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'slug', 'contact_email', 'status', 'created_at']);
            foreach ($rows as $row) {
                $recentTenants[] = (array)$row;
            }
        }
    } catch (\Throwable $__) {}

    // --- Setup Wizard ---
    $stripeConnected = $connect['chargesEnabled'];
    $setup = [
        'stripe_connected' => $stripeConnected,
        'has_products' => $productCount > 0,
        'has_plans' => $planCount > 0,
        'has_tenants' => $tenantCount > 0,
        'has_subscriptions' => $activeSubCount > 0,
    ];
    $setup['all_complete'] = $setup['stripe_connected']
        && $setup['has_products']
        && $setup['has_plans']
        && $setup['has_tenants']
        && $setup['has_subscriptions'];

    // --- Onboard flags ---
    $onboardError = isset($_GET['onboard_error']) && $_GET['onboard_error'] !== '';
    $onboardSuccess = isset($_GET['onboard_success']) && $_GET['onboard_success'] !== '';
    $onboardRefresh = isset($_GET['onboard_refresh']) && $_GET['onboard_refresh'] !== '';

    return [
        'pagetitle' => 'Partner Hub',
        'templatefile' => 'whitelabel/overview',
        'breadcrumb' => ['index.php?m=eazybackup' => 'eazyBackup'],
        'requirelogin' => true,
        'forcessl' => true,
        'vars' => [
            'modulelink' => $baseLink,
            'msp' => (array)$msp,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'setup' => $setup,
            'connect' => $connect,
            'connect_due' => $connectDue,
            'connect_id_masked' => $connectIdMasked,
            'counts' => [
                'tenants' => $tenantCount,
                'active_subscriptions' => $activeSubCount,
                'products' => $productCount,
                'plans' => $planCount,
                'pending_approvals' => $pendingApprovalCount,
                'whitelabel_tenants' => $wlTenantCount,
            ],
            'billing' => $billing,
            'billing_unavailable' => $billingUnavailable,
            'recent_tenants' => $recentTenants,
            'onboardError' => $onboardError,
            'onboardSuccess' => $onboardSuccess,
            'onboardRefresh' => $onboardRefresh,
        ],
    ];
}
```

**Step 2: Verify PHP syntax**

Run: `php -l accounts/modules/addons/eazybackup/pages/partnerhub/OverviewController.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/pages/partnerhub/OverviewController.php
git commit -m "feat: add OverviewController for Partner Hub dashboard"
```

---

### Task 3: Register the ph-overview route

**Files:**
- Modify: `accounts/modules/addons/eazybackup/eazybackup.php` (around line 4088)

**Step 1: Add the ph-overview route**

In `eazybackup.php`, find the line (around line 4088):

```php
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-clients') {
```

Insert **before** that line:

```php
    } else if (isset($_REQUEST['a']) && $_REQUEST['a'] === 'ph-overview') {
        require_once __DIR__ . '/pages/partnerhub/OverviewController.php';
        return eb_ph_overview_index($vars);
```

**Step 2: Verify PHP syntax**

Run: `php -l accounts/modules/addons/eazybackup/eazybackup.php`
Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/eazybackup.php
git commit -m "feat: register ph-overview route in eazybackup router"
```

---

### Task 4: Create the Overview template

**Files:**
- Create: `accounts/modules/addons/eazybackup/templates/whitelabel/overview.tpl`

**Step 1: Create the template**

Create the file with the full Smarty template implementing all 5 design sections. The template must:

1. Include the standard head and UI tokens partial
2. Use the sidebar layout pattern from `tenants.tpl` with `ebPhSidebarPage='overview'`
3. Render the Setup Wizard card with 5 steps (Alpine.js for collapse/dismiss behavior using `localStorage` key `eb_ph_setup_dismissed`)
4. Render Stripe onboarding alert banners (error/success/refresh)
5. Render the Stripe Connect Status card (compact horizontal)
6. Render the Key Metrics grid (6 cards, 3-col responsive)
7. Render the Billing Snapshot card (conditional on `$billing` not being null, with fallback for `$billing_unavailable`)
8. Render the Recent Tenants + Quick Actions two-column layout

Follow the dark UI playbook from `STYLING_NOTES.md`:
- Outer: `min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden`
- Inner: `container mx-auto max-w-full px-4 pb-8 pt-6`
- Content card: `rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]`
- Stat cards: `rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl ring-1 ring-white/10`
- Amber highlights for warnings, emerald for success, rose for errors

Alpine.js state for the setup wizard:
```javascript
x-data="{
  setupDismissed: localStorage.getItem('eb_ph_setup_dismissed') === 'true',
  dismissSetup() {
    this.setupDismissed = true;
    localStorage.setItem('eb_ph_setup_dismissed', 'true');
  }
}"
```

Currency formatting helper (inline Smarty):
- Use `{$billing.currency}` and format `{$billing.revenue_this_month}` by dividing by 100 with 2 decimal places: `{($billing.revenue_this_month / 100)|number_format:2}`
- Prepend currency symbol based on `$billing.currency` (default `$` for USD)

Relative date display for recent tenants: use a simple Smarty approach — display the `created_at` value directly (formatted via `{$tenant.created_at|date_format:'%b %e, %Y'}`).

The template should be approximately 300-450 lines.

**Step 2: Verify the template renders**

Visually verify the template structure is valid Smarty — check for:
- Balanced `{if}...{/if}` tags
- Correct `{foreach}...{/foreach}` usage
- Proper variable references matching controller output

**Step 3: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/overview.tpl
git commit -m "feat: add Partner Hub Overview template with all 5 dashboard sections"
```

---

### Task 5: Update sidebar and header navigation

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl` (line 16)
- Modify: `accounts/templates/eazyBackup/includes/nav_partner_hub.tpl` (line 14)

**Step 1: Update sidebar Overview link**

In `sidebar_partner_hub.tpl`, find line 16:

```smarty
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-tenants-manage" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $ebPhSidebarPage eq 'overview'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}" :class="sidebarCollapsed && 'justify-center'" :title="sidebarCollapsed ? 'Overview' : ''">
```

Replace with:

```smarty
            <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-overview" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $ebPhSidebarPage eq 'overview'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}" :class="sidebarCollapsed && 'justify-center'" :title="sidebarCollapsed ? 'Overview' : ''">
```

**Step 2: Update header nav Overview link**

In `nav_partner_hub.tpl`, find line 14:

```smarty
    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-clients" class="block px-2 py-1 text-gray-300 rounded-md hover:bg-[#1B2C50]">Overview</a>
```

Replace with:

```smarty
    <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=ph-overview" class="block px-2 py-1 text-gray-300 rounded-md hover:bg-[#1B2C50]">Overview</a>
```

**Step 3: Verify no syntax issues**

Check that no PHP or Smarty errors were introduced (these are `.tpl` files, visual check is sufficient).

**Step 4: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl
git add accounts/templates/eazyBackup/includes/nav_partner_hub.tpl
git commit -m "feat: point Overview nav links to ph-overview route"
```

---

### Task 6: PHP lint all changed files

**Files:** All PHP files created or modified in Tasks 1-3.

**Step 1: Run PHP lint on all changed PHP files**

```bash
php -l accounts/modules/addons/eazybackup/lib/PartnerHub/StripeService.php
php -l accounts/modules/addons/eazybackup/pages/partnerhub/OverviewController.php
php -l accounts/modules/addons/eazybackup/eazybackup.php
```

Expected: All three report `No syntax errors detected`.

**Step 2: Fix any errors found**

If any lint errors are reported, fix them and re-run until clean.

---

### Task 7: Update PARTNER_HUB.md documentation

**Files:**
- Modify: `accounts/modules/addons/eazybackup/Docs/PARTNER_HUB.md`

**Step 1: Add Overview section**

Find the "## Partner Hub vertical sidebar" section (around line 91). Insert a new section **before** it:

```markdown
## Partner Hub Overview (Dashboard)

The Overview page is the default Partner Hub landing page, providing MSPs with an at-a-glance view of their account health, onboarding status, and key metrics.

- Route: `index.php?m=eazybackup&a=ph-overview`
- Controller: `pages/partnerhub/OverviewController.php`
- Template: `templates/whitelabel/overview.tpl`
- Sidebar page ID: `overview`

### Page Sections

1. **Setup Wizard** — 5-step onboarding checklist (Connect Stripe, Create Product, Create Plan, Add Tenant, Create Subscription). Collapses into a dismissible "Setup Complete" badge when all steps are done. Dismiss state stored in `localStorage` (`eb_ph_setup_dismissed`).

2. **Stripe Connect Status** — Compact card showing connect account ID (masked), charges/payouts status badges, and action-required alerts.

3. **Key Metrics Grid** — 6 clickable stat cards: Tenants, Active Subscriptions, Products, Plans, Pending Approvals (with amber badge when > 0), White-Label Tenants.

4. **Billing Snapshot** — Live Stripe API data (not cached): revenue this month, invoice count, outstanding invoices, failed payments. Only shown when Stripe charges are enabled. Gracefully degrades if Stripe API is unavailable.

5. **Recent Tenants + Quick Actions** — Last 5 tenants with status badges and creation dates. Quick action buttons for common tasks (Create Tenant, Create Product, Manage Stripe, Signup Approvals).

### Data Sources

- DB counts: `eb_tenants`, `eb_catalog_products`, `eb_plans`, `eb_subscriptions`, `eb_whitelabel_signup_events`, `eb_whitelabel_tenants` — all scoped by `msp_id` or `client_id`.
- Stripe Connect status: `StripeService::retrieveAccount()` via `eb_msp_accounts.stripe_connect_id`.
- Billing snapshot: `StripeService::listInvoicesForAccount()` and `StripeService::listChargesForAccount()` (live API calls on the connected account).
```

**Step 2: Commit**

```bash
git add accounts/modules/addons/eazybackup/Docs/PARTNER_HUB.md
git commit -m "docs: add Partner Hub Overview section to PARTNER_HUB.md"
```

---

### Task 8: Final verification

**Step 1: Verify all files exist**

```bash
ls -la accounts/modules/addons/eazybackup/pages/partnerhub/OverviewController.php
ls -la accounts/modules/addons/eazybackup/templates/whitelabel/overview.tpl
```

Expected: Both files exist.

**Step 2: Run PHP lint on all PHP files one more time**

```bash
php -l accounts/modules/addons/eazybackup/lib/PartnerHub/StripeService.php
php -l accounts/modules/addons/eazybackup/pages/partnerhub/OverviewController.php
php -l accounts/modules/addons/eazybackup/eazybackup.php
```

Expected: All clean.

**Step 3: Verify route is reachable**

Check that `eazybackup.php` contains the `ph-overview` case and it requires the correct controller file:

```bash
grep -n 'ph-overview' accounts/modules/addons/eazybackup/eazybackup.php
```

Expected: Shows the route case with `OverviewController.php` require.

**Step 4: Verify sidebar links updated**

```bash
grep -n 'ph-overview' accounts/modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl
grep -n 'ph-overview' accounts/templates/eazyBackup/includes/nav_partner_hub.tpl
```

Expected: Both files reference `a=ph-overview`.

**Step 5: Verify docs updated**

```bash
grep -n 'Partner Hub Overview' accounts/modules/addons/eazybackup/Docs/PARTNER_HUB.md
```

Expected: Shows the new section heading.
