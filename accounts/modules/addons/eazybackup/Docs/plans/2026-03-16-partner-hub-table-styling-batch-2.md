# Partner Hub Table Styling Batch 2 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Apply the newer Partner Hub table styling and interaction pattern to plans, signup approvals, balance, disputes, and payouts tables.

**Architecture:** Reuse the same client-side Alpine table-controller pattern already added to the billing table templates, adapting each page's second dropdown filter to the underlying dataset. Preserve existing page headers, actions, row content, badges, and links while replacing legacy filter bars or simple table shells with the newer shared toolbar, sorting, summary, and pager behavior.

**Tech Stack:** Smarty templates, Alpine.js inline controllers, Tailwind utility classes, existing Partner Hub UI tokens.

---

### Task 1: Upgrade `catalog-plans.tpl` table controls

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl`

**Step 1: Define the failing behavior to replace**

Document the current gap:
- Table lacks `Show N` dropdown
- Table lacks bottom summary and pager
- Header columns are not using the newer inline sort arrow treatment

**Step 2: Verify the current markup pattern**

Read:
- `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl`
- `accounts/modules/addons/eazybackup/templates/whitelabel/billing-subscriptions.tpl`

Expected:
- Confirm the plans page still uses the older filter/table shell

**Step 3: Implement the new toolbar and table controller**

Replace the current filter bar/table region with:
- `Show N` dropdown
- `Status` dropdown (`Active`, `Draft`, `Archived`, `All`)
- Search input
- Sortable table headers with inline arrows
- Entry summary and pager

**Step 4: Verify the updated template**

Run diagnostics on:
- `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl`

Expected:
- No linter/template diagnostics

### Task 2: Upgrade `signup-approvals.tpl` queue table

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/signup-approvals.tpl`

**Step 1: Define the failing behavior to replace**

Document the current gap:
- Static queue table
- No `Show N`, no search, no status filter, no pager, no sortable headers

**Step 2: Verify the current status values**

Read the template and identify row statuses used in the queue:
- `pending_approval`
- `approving`
- `rejecting`
- catch-all status output

Expected:
- Build the second dropdown around the actual states shown by the page

**Step 3: Implement the new toolbar and table controller**

Add:
- `Show N` dropdown
- `Status` dropdown (`Pending Approval`, `Approving`, `Rejecting`, `Approved`, `Rejected`, `All`)
- Search input
- Sortable headers
- Entry summary and pager

Keep:
- Approve/Reject actions
- Existing notices/errors
- Existing header actions

**Step 4: Verify the updated template**

Run diagnostics on:
- `accounts/modules/addons/eazybackup/templates/whitelabel/signup-approvals.tpl`

Expected:
- No linter/template diagnostics

### Task 3: Upgrade `money-balance.tpl` transaction table

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/money-balance.tpl`

**Step 1: Define the failing behavior to replace**

Document the approved scope:
- Replace the legacy `from/to/type/limit` filter row entirely
- Keep the summary cards and export button

**Step 2: Verify the current transaction columns**

Read the existing table and identify:
- sortable data columns
- useful second dropdown mapping for `Type`

Expected:
- Use the `type` field as the second dropdown filter, with `All` as fallback

**Step 3: Implement the new toolbar and table controller**

Add:
- `Show N` dropdown
- `Type` dropdown sourced from common transaction types present in rows
- Search input
- Sortable headers
- Entry summary and pager

Keep:
- Summary cards
- Export CSV header action

**Step 4: Verify the updated template**

Run diagnostics on:
- `accounts/modules/addons/eazybackup/templates/whitelabel/money-balance.tpl`

Expected:
- No linter/template diagnostics

### Task 4: Upgrade `money-disputes.tpl` table

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/money-disputes.tpl`

**Step 1: Define the failing behavior to replace**

Document the current gap:
- Simple search row only
- No `Show N`, no second dropdown, no sortable headers, no pager

**Step 2: Verify dispute statuses used in rows**

Read the row badge mapping and use these values for the second dropdown:
- `needs_response`
- `warning_needs_response`
- `won`
- `lost`
- `all`

**Step 3: Implement the new toolbar and table controller**

Add:
- `Show N` dropdown
- `Status` dropdown
- Search input
- Sortable headers
- Entry summary and pager

Keep:
- Refresh action in page header
- Existing Stripe links and badge styles

**Step 4: Verify the updated template**

Run diagnostics on:
- `accounts/modules/addons/eazybackup/templates/whitelabel/money-disputes.tpl`

Expected:
- No linter/template diagnostics

### Task 5: Upgrade `money-payouts.tpl` table

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/money-payouts.tpl`

**Step 1: Define the failing behavior to replace**

Document the current gap:
- Simple search row only
- No `Show N`, no second dropdown, no sortable headers, no pager

**Step 2: Verify payout statuses used in rows**

Read the row badge mapping and use these values for the second dropdown:
- `paid`
- `pending`
- `in_transit`
- `failed`
- `canceled`
- `all`

**Step 3: Implement the new toolbar and table controller**

Add:
- `Show N` dropdown
- `Status` dropdown
- Search input
- Sortable headers
- Entry summary and pager

Keep:
- Refresh action in page header
- Existing Stripe links and badge styles

**Step 4: Verify the updated template**

Run diagnostics on:
- `accounts/modules/addons/eazybackup/templates/whitelabel/money-payouts.tpl`

Expected:
- No linter/template diagnostics

### Task 6: Final verification

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/catalog-plans.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/signup-approvals.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/money-balance.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/money-disputes.tpl`
- Modify: `accounts/modules/addons/eazybackup/templates/whitelabel/money-payouts.tpl`

**Step 1: Re-read edited files**

Confirm:
- table wrappers are balanced
- Alpine controllers close correctly
- no duplicate toolbars remain

**Step 2: Run diagnostics**

Run diagnostics on all five edited templates.

Expected:
- No linter/template diagnostics

**Step 3: Manual verification notes**

Record that browser rendering was not run in automation unless explicitly requested.
