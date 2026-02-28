# Storage Vaults Table Simplification Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refactor Storage Vaults tables so each vault is rendered as a single row, remove hierarchy/summary noise, and add a non-emphasized Billing column in both target templates.

**Architecture:** Keep this as a template-only change with no backend/controller modifications. In `vaults.tpl`, flatten grouped rendering to vault rows only while preserving per-account derived billing tier. In `user-profile.tpl`, keep single-vault rows and add a Billing column populated from the existing page-level billable tier logic.

**Tech Stack:** WHMCS Smarty templates, Tailwind CSS v4 utility classes, Alpine.js client-side table interactivity.

---

### Task 1: Refactor `vaults.tpl` to one row per vault + Billing

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl`
- Verify: `accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl`

**Step 1: Capture current table behavior baseline**

Run:

```bash
rg -n "account-header|account-summary|summary|Billable" "accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl"
```

Expected:
- Matches exist for account-only row and summary row patterns (baseline before refactor).

**Step 2: Update column toggle model to target final columns**

Edit Alpine `cols` object and checkbox list to final vaults-page columns:
- `acct`, `name`, `stored`, `quota`, `usage`, `billing`, `actions`
- Remove toggles for `id`, `type`, `init`.

**Step 3: Update table header order**

Replace current `<th>` set with:
1. Account Name
2. Storage Vault
3. Stored
4. Quota
5. Usage
6. Billing
7. Actions

**Step 4: Remove account-only row block**

Delete the `<tr class="account-header ...">` render block entirely.

**Step 5: Remove summary row block**

Delete the `<tr class="... account-summary ...">` render block entirely, including:
- `{$acctName} summary`
- Total Used / Total Quota inline content
- Old Billable summary text styling.

**Step 6: Make vault rows independent of expansion state**

In vault row `<tr>`:
- Keep `matchesSearch($el)`.
- Remove `isExpanded(...)` dependency from `x-show`.

**Step 7: Keep one row per vault and remove obsolete cells**

Within the vault row:
- Keep account name and vault name cells.
- Remove cells for old `id`, `type`, `init` columns.
- Keep stored/quota/usage/actions cells.

**Step 8: Add Billing cell to vault row**

In each vault row, render billing value from account totals:

```smarty
{assign var=billableTB value=($acctTotals.quota>0)?ceil($acctTotals.quota/$tbBytes):0}
<td x-show="cols.billing" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
  {if $billableTB>0}{$billableTB} TB{else}—{/if}
</td>
```

Notes:
- Keep text plain (no badge/pill).
- Use exact class requirement: `text-sm text-gray-300`.

**Step 9: Update empty-state colspan**

Set `No storage vaults found` row `colspan` to `7`.

**Step 10: Verify template no longer contains summary/account-only patterns**

Run:

```bash
rg -n "account-header|account-summary|summary" "accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl"
```

Expected:
- No matches for removed row patterns/labels.

**Step 11: Verify billing style class usage in vault row**

Run:

```bash
rg -n "cols\\.billing|text-sm text-gray-300" "accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl"
```

Expected:
- Billing column toggle and billing cell class present.

**Step 12: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl
git commit -m "refactor(vaults): flatten storage vault rows and add billing column"
```

---

### Task 2: Refactor `user-profile.tpl` table to add Billing and simplify columns

**Files:**
- Modify: `accounts/modules/addons/eazybackup/templates/console/user-profile.tpl`
- Verify: `accounts/modules/addons/eazybackup/templates/console/user-profile.tpl`

**Step 1: Capture current table structure baseline**

Run:

```bash
rg -n "Storage Vault ID|Type|Initialized|Billable Tier|summary" "accounts/modules/addons/eazybackup/templates/console/user-profile.tpl"
```

Expected:
- Old extra table columns and separate account billing summary area are present.

**Step 2: Keep top summary card unchanged for now**

Do not remove the account storage summary card in this task. This refactor targets the table row structure and Billing column addition.

**Step 3: Update table column toggle model**

Adjust storage table `cols` object to:
- `name`, `stored`, `quota`, `usage`, `billing`, `actions`
- Remove `id`, `type`, `init`.

**Step 4: Update table headers to final order**

Set headers to:
1. Storage Vault
2. Stored
3. Quota
4. Usage
5. Billing
6. Actions

**Step 5: Remove obsolete row cells**

In each vault row:
- Remove Storage Vault ID cell.
- Remove Type cell.
- Remove Initialized cell.
- Preserve stored/quota/usage/actions implementations and existing data attributes used by JS actions.

**Step 6: Add Billing cell per vault row**

Use existing page-level `billableTB` derived from total quota and render on each vault row:

```smarty
<td x-show="cols.billing" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
  {if $billableTB > 0}{$billableTB} TB{else}—{/if}
</td>
```

**Step 7: Update empty-state colspan**

Set `No storage vaults found for this user.` row `colspan` to `6`.

**Step 8: Verify billing style class usage**

Run:

```bash
rg -n "cols\\.billing|text-sm text-gray-300" "accounts/modules/addons/eazybackup/templates/console/user-profile.tpl"
```

Expected:
- Billing header/toggle/cell wiring present.

**Step 9: Verify removed columns are not rendered in storage table**

Run:

```bash
rg -n "Storage Vault ID|Initialized</th>|>Type</th>" "accounts/modules/addons/eazybackup/templates/console/user-profile.tpl"
```

Expected:
- No matches in the storage vault table section.

**Step 10: Commit**

```bash
git add accounts/modules/addons/eazybackup/templates/console/user-profile.tpl
git commit -m "refactor(profile): simplify storage vault table and add billing column"
```

---

### Task 3: Verification pass (behavior + regressions)

**Files:**
- Verify: `accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl`
- Verify: `accounts/modules/addons/eazybackup/templates/console/user-profile.tpl`

**Step 1: Static grep checks for acceptance requirements**

Run:

```bash
rg -n "summary" "accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl" "accounts/modules/addons/eazybackup/templates/console/user-profile.tpl"
```

Expected:
- No table-row labels containing `summary`.

**Step 2: Lint/diagnostic check for changed files**

Use IDE diagnostics/lints for the two modified templates and fix any introduced issues.

Expected:
- No new lint/template errors introduced by the refactor.

**Step 3: Manual UI validation in client area**

Validate in browser:
- `vaults.tpl`: account with N vaults renders exactly N rows.
- No account-only rows.
- No summary rows.
- Billing column present on every row and visually non-emphasized.
- Search returns vault rows by account and vault text.

**Step 4: Manual UI validation in profile page**

Validate in browser:
- One row per vault remains.
- Columns match target order (no Account Name column here).
- Billing shown on every vault row with `text-sm text-gray-300`.
- Usage unavailable text appears only once in row for no-quota vaults.

**Step 5: Final commit**

```bash
git add accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl accounts/modules/addons/eazybackup/templates/console/user-profile.tpl
git commit -m "refactor(storage-vaults): flatten rows and add per-row billing column"
```

---

### Task 4: Optional cleanup (only if needed after QA)

**Files:**
- Modify (optional): same two template files

**Step 1: Remove dead Alpine functions/state if unused**

If account expand/collapse logic becomes unused after header-row removal, remove dead state/methods in `vaults.tpl` Alpine block.

**Step 2: Re-run verification commands**

Repeat Task 3 checks after cleanup.

**Step 3: Commit optional cleanup**

```bash
git add accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl
git commit -m "chore(vaults): remove unused account expansion state"
```
