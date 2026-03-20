# eazyBackup Client Area Theme Handoff

## What This Project Is

This project is migrating the WHMCS client-area theme in `templates/eazyBackup` from mixed inline Tailwind and legacy Bootstrap-style template markup into a reusable design system based on these style guides:

- `modules/addons/eazybackup/Docs/StyleGuides/eazybackup-dark-system.html`
- `modules/addons/eazybackup/Docs/StyleGuides/eazyBackup-StyleGuide.html`

The rollout is dark-first. Light-theme tokens exist in the token layer, but light is not yet activated as the production client-area theme.

## Main Goal

The goal is to make all client-area templates use the same shared token/component system so the UI is:

- visually consistent
- easier to maintain
- easier to extend
- ready for future light-theme activation

## Source of Truth

Core implementation files:

- Tokens: `modules/addons/eazybackup/templates/partials/_ui-tokens.tpl`
- Component classes: `templates/eazyBackup/css/tailwind.src.css`
- Built CSS: `templates/eazyBackup/css/tailwind.css`
- Global CSS load: `templates/eazyBackup/includes/head.tpl`
- Root theme shell: `templates/eazyBackup/header.tpl`
- Shared UI partials: `templates/eazyBackup/includes/ui/`
- Contract test: `modules/addons/eazybackup/bin/dev/clientarea_theme_contract_test.php`
- Migration plan: `modules/addons/eazybackup/Docs/StyleGuides/CLIENT-AREA-THEME-MIGRATION.md`
- Addon inventory: `modules/addons/eazybackup/Docs/StyleGuides/ADDON-TEMPLATE-MIGRATION-INVENTORY.md`

## What Has Been Completed

Completed foundation work:

- a shared token system for dark and light values
- strict dark-style-guide parity tokens for depth, surfaces, text, borders, semantic colors, typography, buttons, tables, forms, menus, modals, and drawers
- a reusable semantic component layer in Tailwind
- shared page shell, auth shell, page header, alert, card, table-toolbar, form-field, and starter partials
- a contract test that verifies core migration markers

Completed migration work:

- auth pages
- services pages
- invoices and quotes pages
- support ticket list/detail/create pages
- account/security/payment/contact/user-management pages

Completed normalization work:

- earlier migrated templates were re-audited and updated to use the stricter parity layer instead of one-off local dark utility bundles

## Current Status

The shared theme system is stable and verified at the code layer:

- Tailwind rebuild passes
- contract test passes
- migrated templates were syntax-checked after normalization

The next migration area is the untouched dashboard/domain/content batch, starting with:

- `clientareahome.tpl`
- `clientareadomains.tpl`
- `clientareadomaindetails.tpl`
- `clientareadomaindns.tpl`
- `clientareaemails.tpl`
- `announcements.tpl`
- `downloads.tpl`
- `downloadscat.tpl`

Addon-module migration is also now in progress. The current addon batch starts with:

- `modules/addons/eazybackup/templates/clientarea/nfr-apply.tpl`
- `modules/addons/eazybackup/templates/clientarea/notify-settings.tpl`
- `modules/addons/eazybackup/templates/clientarea/partials/sidebar.tpl`

The inventory and batch order for addon work lives in `ADDON-TEMPLATE-MIGRATION-INVENTORY.md`.

## Rules For Future Work

- Do not introduce new template-local raw color systems if the shared parity layer already covers the pattern.
- Use `includes/ui/page-shell.tpl` or `includes/ui/auth-shell.tpl` for full-page templates.
- Prefer shared `eb-*` classes for design decisions.
- If a needed pattern is missing, extend the shared token/component layer first, then use it in templates.
- Rebuild `tailwind.css` after shared CSS changes.
- Run `php modules/addons/eazybackup/bin/dev/clientarea_theme_contract_test.php` after migration work.

## Quick Resume Checklist

If a new LLM agent needs to continue:

1. Read `CLIENT-AREA-THEME-MIGRATION.md`.
2. Read this handoff note.
3. Inspect `_ui-tokens.tpl` and `tailwind.src.css`.
4. Continue migrating untouched templates onto the shared shell and parity classes.
5. Rebuild Tailwind and rerun the contract test before handing work back.
