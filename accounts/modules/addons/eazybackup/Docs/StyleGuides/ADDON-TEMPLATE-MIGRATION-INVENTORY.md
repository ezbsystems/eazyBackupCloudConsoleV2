# Addon Template Migration Inventory

## Purpose

This document tracks the next phase of the client-area theme migration inside addon modules. The goal is to move active addon templates onto the same shared token and component system already established for `templates/eazyBackup`.

Relevant shared styling sources:

- `modules/addons/eazybackup/templates/partials/_ui-tokens.tpl`
- `templates/eazyBackup/css/tailwind.src.css`
- `templates/eazyBackup/includes/ui/`
- `modules/addons/eazybackup/bin/dev/clientarea_theme_contract_test.php`

## Scope Rules

- Focus on active client-facing addon templates first.
- Leave domain-related templates untouched.
- Defer admin-only templates until the client-facing migration is stable.
- Defer obvious legacy or date-stamped variants unless they are still confirmed active.

## Module Review

### `modules/addons/eazybackup`

Completed and under the shared client-area / Partner Hub contract:

- Addon client-area application pages:
  - `templates/clientarea/dashboard.tpl`
  - `templates/clientarea/vaults.tpl`
  - `templates/clientarea/nfr-apply.tpl`
  - `templates/clientarea/notify-settings.tpl`
- Shared addon client-area partials:
  - `templates/clientarea/partials/sidebar.tpl`
  - `templates/clientarea/partials/device-groups-drawer.tpl`
- Public / onboarding / order flows:
  - `templates/createorder.tpl`
- Console / user-facing app pages:
  - `templates/console/job-logs-global.tpl`
  - `templates/console/user-profile.tpl`
- Console shared partials:
  - `templates/console/partials/job-report-modal.tpl`
  - `templates/console/partials/upcoming-charges.tpl`
- Partner Hub / whitelabel application pages:
  - `templates/whitelabel/overview.tpl`
  - `templates/whitelabel/tenants.tpl`
  - `templates/whitelabel/tenant-detail.tpl`
  - `templates/whitelabel/client-view.tpl`
  - `templates/whitelabel/catalog-products.tpl`
  - `templates/whitelabel/catalog-products-list.tpl`
  - `templates/whitelabel/catalog-product.tpl`
  - `templates/whitelabel/catalog-plans.tpl`
  - `templates/whitelabel/plans.tpl`
  - `templates/whitelabel/billing-invoices.tpl`
  - `templates/whitelabel/billing-payments.tpl`
  - `templates/whitelabel/billing-payment-new.tpl`
  - `templates/whitelabel/billing-subscriptions.tpl`
  - `templates/whitelabel/settings-checkout.tpl`
  - `templates/whitelabel/settings-email.tpl`
  - `templates/whitelabel/settings-tax.tpl`
  - `templates/whitelabel/signup-settings.tpl`
  - `templates/whitelabel/signup-approvals.tpl`
  - `templates/whitelabel/branding.tpl`
  - `templates/whitelabel/branding-list.tpl`
  - `templates/whitelabel/email-templates.tpl`
  - `templates/whitelabel/email-template-edit.tpl`
  - `templates/whitelabel/money-balance.tpl`
  - `templates/whitelabel/money-disputes.tpl`
  - `templates/whitelabel/money-payouts.tpl`
  - `templates/whitelabel/stripe-connect.tpl`
  - `templates/whitelabel/stripe-manage.tpl`
  - `templates/whitelabel/subscriptions-new.tpl`
  - `templates/whitelabel/loader.tpl`
  - `templates/whitelabel/public-download.tpl`
  - `templates/whitelabel/public-invalid-host.tpl`
  - `templates/whitelabel/public-signup.tpl`
- Partner Hub shared partials / chrome:
  - `templates/whitelabel/partials/partner_hub_shell.tpl`
  - `templates/whitelabel/partials/sidebar_partner_hub.tpl`
  - `templates/whitelabel/partials/billing-payment-modal.tpl`

Outstanding migration targets:

- Public / onboarding / completion flows:
  - `templates/create.tpl`
  - `templates/created.tpl`
  - `templates/create-ticket.tpl`
  - `templates/eazybackup.tpl`
  - `templates/eazybackup_dashboard2.tpl`
  - `templates/eazybackup-download.tpl`
  - `templates/download.tpl`
  - `templates/download-obc.tpl`
  - `templates/ms365.tpl`
  - `templates/password-onboarding.tpl`
  - `templates/trialsignup.tpl`
  - `templates/trialsignup2.tpl`
  - `templates/trialsignup-obc.tpl`
  - `templates/whitelabel-signup.tpl`
  - `templates/msp-onboarding.tpl`
  - `templates/tos-block.tpl`
  - `templates/tos-view.tpl`
  - `templates/privacy-view.tpl`
  - `templates/error.tpl`
  - `templates/maintenance.tpl`

Batch 8 triage results for previously unclassified eazyBackup templates:

- `migrated`:
  - `templates/services-e3.tpl`
    - Migrated onto the shared `eb-*` page shell, toolbar, table, badge, and pagination contract while preserving the existing DataTables behavior.
  - `templates/console_success.tpl`
    - Migrated to the shared `eb-*` success-panel contract with token-driven icon treatment and structured follow-up copy.
  - `templates/clientarea_ms365.tpl`
    - Migrated onto a shared MS365 success partial using the `eb-*` shell and card contract for the eazyBackup control-panel flow.
  - `templates/complete-eazybackup.tpl`
    - Migrated onto a shared completion/download shell using the `eb-*` modal, button, and panel contract for the eazyBackup installer flow.
  - `templates/complete-obc.tpl`
    - Migrated onto a shared completion/download shell using the `eb-*` modal, button, and panel contract for the OBC installer flow.
  - `templates/complete-whitelabel.tpl`
    - Migrated onto a shared completion/download shell using the `eb-*` modal, button, and panel contract for branded installer flows.
  - `templates/denied.tpl`
    - Migrated to the shared `eb-*` page shell and action-button contract for access-denied states.
  - `templates/reseller.tpl`
    - Migrated to the shared auth/public contract with tokenized benefits, alert, and form primitives for partner signup.
  - `templates/success-obc-ms365.tpl`
    - Migrated onto the shared MS365 success partial using the `eb-*` shell and card contract for the OBC control-panel flow.
  - `templates/usagereport.tpl`
    - Migrated to the shared `eb-*` page shell, navigation, table, and input contract while preserving the reporting DataTables behavior.
- `partially migrated`:
  - None currently in this Batch 8 set.
- `not migrated`:
  - `templates/knowledgebase.tpl`
    - Active iframe wrapper with bespoke loader/CSS and DOM manipulation; not on the shared design system.
  - `templates/userdetails.tpl`
    - Active detail page action in `eazybackup.php`, but still a standalone HTML document with CDN Tailwind and legacy modal styling; not aligned to the completed `templates/console/user-profile.tpl` contract.
- `duplicate/superseded`:
  - `templates/clientareaproducts_tab2.tpl`
    - No active references were found in the addon codebase; appears to be a legacy services tab partial superseded by current services views.
  - `templates/includes/job-report-modal.tpl`
    - Compatibility shim only; it immediately includes `templates/console/partials/job-report-modal.tpl`, which is the current migrated implementation.

Deferred for now:

- Admin-only:
  - `templates/admin/powerpanel/storage.tpl`
- Legacy or likely superseded:
  - `templates/reseller_7Jun2022.tpl`
  - `templates/trialsignup22Dec2022.tpl`
  - `templates/trialsignup_23_Jun_2022.tpl`
  - `templates/success_obc365.tpl`
  - `templates/testing.tpl`
- Documentation and style-guide files.

### `modules/addons/cloudstorage`

Outstanding migration targets:

- Core object-storage application:
  - `templates/dashboard.tpl`
  - `templates/buckets.tpl`
  - `templates/bucket_manager.tpl`
  - `templates/add_bucket.tpl`
  - `templates/access_keys.tpl`
  - `templates/users.tpl`
  - `templates/users_v2.tpl`
  - `templates/subusers.tpl`
  - `templates/billing.tpl`
  - `templates/history.tpl`
  - `templates/browse.tpl`
  - `templates/services.tpl`
  - `templates/welcome.tpl`
  - `templates/signup.tpl`
  - `templates/s3storage.tpl`
- Shared object-storage partials:
  - `templates/partials/core_nav.tpl`
  - `templates/partials/bucket_create_modal.tpl`
- e3 backup application family:
  - `templates/e3backup_dashboard.tpl`
  - `templates/e3backup_users.tpl`
  - `templates/e3backup_user_detail.tpl`
  - `templates/e3backup_agents.tpl`
  - `templates/e3backup_jobs.tpl`
  - `templates/e3backup_runs.tpl`
  - `templates/e3backup_restores.tpl`
  - `templates/e3backup_disk_image_restore.tpl`
  - `templates/e3backup_recovery_media.tpl`
  - `templates/e3backup_cloudnas.tpl`
  - `templates/e3backup_hyperv.tpl`
  - `templates/e3backup_hyperv_restore.tpl`
  - `templates/e3backup_tokens.tpl`
  - `templates/e3backup_tenants_table.tpl`
  - `templates/e3backup_tenant_detail.tpl`
  - `templates/e3backup_tenant_members.tpl`
  - `templates/partials/e3backup_nav.tpl`
  - `templates/partials/e3backup_create_user_modal.tpl`
  - `templates/partials/job_create_wizard.tpl`
  - `templates/partials/cloudnas_drives.tpl`
  - `templates/partials/cloudnas_mount_wizard.tpl`
  - `templates/partials/cloudnas_settings.tpl`
  - `templates/partials/cloudnas_timemachine.tpl`

Deferred for now:

- Admin-only:
  - `templates/admin/cloudbackup_admin.tpl`
- Operational or special-purpose pages that may need separate handling:
  - `templates/cloudbackup_live.tpl`

## Batch Order

### Completed

1. Batch 1: eazyBackup addon client-area forms and shared chrome.
2. Batch 2: eazyBackup addon client-area application screens.
3. Batch 3: eazyBackup console and user-profile views.
4. Batch 7: Partner Hub / whitelabel templates, shared shell, public whitelabel pages, and billing modal contract.
5. Batch 8: inventory-gap triage for unclassified eazyBackup addon templates discovered on disk.
   - Triage classification is complete in this inventory.

### Outstanding

6. Batch 4: eazyBackup public / onboarding / completion flows.
7. Batch 5: cloudstorage core object-storage app and shared navigation.
8. Batch 6: cloudstorage e3 backup application family.
9. Batch 8 follow-up: migrate the templates above still marked `not migrated` or `partially migrated`.

## Current Status

- Inventory re-audited against the current filesystem and the active contract tests.
- `modules/addons/eazybackup` addon client-area pages are completed for the tracked clientarea batch.
- Batch 3 console/user-profile migration is complete and no longer belongs in the outstanding queue.
- Partner Hub / whitelabel migration is completed at the shared-shell contract level and should no longer be treated as an outstanding batch.
- Outstanding eazyBackup work is now concentrated in public/onboarding/completion flows plus the Batch 8 templates above that are classified as `not migrated` or `partially migrated`.
- Batch 8 inventory triage itself is complete; the remaining Batch 8 work is implementation, not discovery.
- `modules/addons/cloudstorage` remains outstanding as a full migration area; no cloudstorage batch is marked complete in this inventory yet.
- Domain-related main-theme templates remain intentionally untouched.
