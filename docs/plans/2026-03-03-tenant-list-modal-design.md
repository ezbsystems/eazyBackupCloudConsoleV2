# Tenant List Modal Design

Date: 2026-03-03
Status: Baseline captured

## Baseline Gap Checklist (Current State)

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
