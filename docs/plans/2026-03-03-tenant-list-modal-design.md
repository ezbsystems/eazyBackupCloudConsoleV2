# Tenant List Modal Design

Date: 2026-03-03
Status: Implemented (see Implementation notes)

## Baseline Gap Checklist (Pre-implementation state)

- [x] Tenants page currently shows an inline create form on the page itself.
- [x] Tenants page does not currently use a modal for tenant creation.
- [x] Tenants page does not currently provide vaults-style entries menu, column visibility controls, search, sort, and pagination controls.

## Baseline Validation Evidence

Validated against branch commit: `5ff2fe4`

### `accounts/modules/addons/eazybackup/templates/whitelabel/tenants.tpl`

- The create UI is rendered inline as a section with a direct `<form>` (`Create Customer Tenant`) on the page.
- Existing tenants are shown in a simple table with fixed columns and no client-side controls for entries/columns/search/sort/pagination.

### `accounts/modules/addons/eazybackup/templates/clientarea/vaults.tpl`

- Vault list includes an Alpine-powered data table controller with:
  - entries-per-page selector (`setEntries`, `entriesPerPage`)
  - column visibility dropdown (`cols`, `columnsOpen`)
  - search input (`search`, `x-model.debounce`)
  - sortable headers (`setSort`, `sortKey`, `sortDirection`)
  - pagination controls (`currentPage`, `prevPage`, `nextPage`, `totalPages`)

Conclusion: the tenants page baseline is missing the vaults-style list controls and modal create pattern targeted by this task.

## Implementation notes (2026-03-03)

- **Completed:** All baseline gaps addressed. Tenants list page now has vaults-style entries menu, column visibility controls, search, sort, and pagination; tenant creation is modal-based with full-e3 intake fields; client-side validation and inline errors added.
- **Backend:** Create flow persists contact and address fields to `s3_backup_tenants` (schema-safe via `hasColumn`). Country validated as 2-letter when provided.
- **Deferred:** Portal admin user creation from the modal (create_admin / admin_email / admin_name / auto_password / admin_password) is not yet implemented; form collects the fields but the controller ignores them and does not create a tenant member. Documented in `TenantsController.php` for follow-up.
