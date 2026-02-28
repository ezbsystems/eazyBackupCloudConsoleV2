# Storage Vaults Table Simplification Design

Date: 2026-02-28
Owner: eazybackup addon team
Status: Approved for planning

## Objective

Simplify the Storage Vaults table UI in both:

- `accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl`
- `accounts/modules/addons/eazybackup/templates/console/user-profile.tpl`

The updated UI must render one row per vault and remove redundant hierarchy/summary rows.

## Goals

- Render exactly one table row per vault record.
- Remove account-only header rows and summary rows from the vaults listing table.
- Add a dedicated Billing column to vault rows.
- Keep existing quota/usage behavior for unlimited/no-quota vaults.
- Preserve search behavior over vault rows.

## Scope Decisions

### Approach

Use a template-only refactor (no backend/controller changes) to minimize risk and deliver quickly.

### Account Name Column

- Required in `vaults.tpl` (multi-user table).
- Not required in `user-profile.tpl` (single-user page).

### Billing Definition

Billing value shown on each vault row is the account-level billable tier repeated per row:

- `billable_tb = ceil(total_account_quota_bytes / 1 TB)` when total quota > 0
- `billable_display = "{N} TB"` if `billable_tb > 0`, otherwise `—`

## Target Table Structures

### `vaults.tpl` columns (in order)

1. Account Name
2. Storage Vault
3. Stored
4. Quota
5. Usage
6. Billing
7. Actions

### `user-profile.tpl` columns (in order)

1. Storage Vault
2. Stored
3. Quota
4. Usage
5. Billing
6. Actions

## Rendering Rules

- One row per vault only.
- Remove account-only rows (for example, account name + vault count header rows).
- Remove summary rows (for example, rows containing "summary", total used/quota, billable inline summary).
- Keep only the vault detail row and adapt it to include Billing.

## Data Mapping

Each rendered vault row maps to one vault record and includes:

- `account_name` (vaults page only)
- `vault_name`
- `stored_bytes` (or stored display string)
- `quota_bytes` and quota enabled state
- `usage_percent` + usage display text (when quota exists)
- `billable_display` (account-level repeated tier for that row)
- existing action metadata (vault id/name, service id, username)

## UI and Styling Requirements

### Billing Cell

- Display plain text only (no badge/pill).
- Required classes: `text-sm text-gray-300`.
- Must not use bold or green emphasis.

### Usage Edge Case

If quota is unlimited or missing:

- Keep existing behavior text: `Usage unavailable (no quota)`.
- Show this only in the single vault row.

### Empty State

Update table `colspan` values to match new column counts after column removals/additions.

## Search and Sorting

### Search

- Keep current client-side search behavior (`matchesSearch`) so rows remain searchable by account and vault text.
- Because non-vault rows are removed, results become cleaner and easier to scan.

### Sorting

- Preserve sorting behavior for retained sortable columns where implemented in the template.
- Billing can remain non-sortable in this iteration unless explicitly requested.

## Acceptance Criteria

- For an account with N vaults, exactly N rows are rendered.
- No row contains the word `summary`.
- Billing appears in its own column on every vault row.
- Billing text uses `text-sm text-gray-300` and no emphasis color/weight.
- UI is visually flatter and less noisy than the current hierarchy.

## Out of Scope

- Backend schema or controller changes.
- Billing model/logic changes beyond current account-tier display semantics.
- Additional feature work on vault actions or vault panel behavior.
