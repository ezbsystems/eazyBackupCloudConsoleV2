# Legal Agreements – Terms of Service + Privacy Policy (Versioning, Consent, and Gating)

This document explains the Legal Agreements feature added to the eazyBackup WHMCS addon: what it is, how it works, the data model, file layout (backend, hooks, templates), and operational notes for developers.

## Overview

The Legal Agreements system allows admins to publish versioned **Terms of Service** and versioned **Privacy Policy** and optionally require users to accept the latest active versions upon login before continuing to the client area. It records acceptance at two levels (for each document):

- Account-level consent (Customer): one record per client_id per document version
- Per-user consent (Owner or Sub-account/Contact): one record per user/contact per document version

When “Require acceptance” is enabled on the active version(s), the client area is gated until each loginable user accepts the required document(s). Users can later review the exact versions and their agreement details in My Account → Terms.

## UX Flows

### Admin flow
1. Navigate: Addons → eazyBackup → Power Panel → Terms
2. Create a new TOS version (defaults `version = YYYY-MM-DD`)
3. Publish the version (sets it as the active TOS; deactivates other versions)
4. Toggle “Require acceptance” to enforce the TOS acceptance for all loginable users

### Client flow (gating)
1. User visits client area while a required Terms of Service and/or Privacy Policy version is active
2. A modal prevents access until acceptance
3. Modal shows checkbox + link for each required document:
   - Terms of Service (opens in a modal)
   - Privacy Policy (opens in a modal)
4. Acceptance requires only checking the checkbox(es). There is **no requirement** to open or scroll the documents.
5. Upon acceptance:
   - Account-level acceptance is recorded (client_id + version + timestamp + IP + UA)
   - Per-user acceptance is recorded (client_id + user or contact + version + timestamp + IP + UA)
6. Users can later view the exact versions they agreed to and acceptance details in My Account → Terms

### Client flow (viewing later)
- My Account → Terms shows the user’s name, email, accepted versions, accepted timestamps, IP, and UA
- “View Terms” opens the exact HTML content (versioned) they agreed to
- “View Privacy Policy” opens the exact HTML content (versioned) they agreed to

## Data Model

Created and migrated via `eazybackup_migrate_schema()` (auto-run on activate/upgrade):

- `eb_tos_versions`
  - `id` (PK), `version` (e.g. `2025-03-01`), `title`, `summary`, `content_html` (LONGTEXT)
  - `is_active` (bool), `require_acceptance` (bool), `published_at`, `created_at`, `created_by`
  - Indexes/unique: version unique; indexes on `is_active`, `require_acceptance`

- `eb_tos_client_acceptances`
  - `id` (PK), `client_id`, `tos_version`, `accepted_at`, `ip_address`, `user_agent`
  - Unique: `(client_id, tos_version)`

- `eb_tos_user_acceptances`
  - `id` (PK), `client_id`, `user_id` (nullable), `contact_id` (nullable), `tos_version`
  - `accepted_at`, `ip_address`, `user_agent`
  - Unique: `(client_id, user_id, contact_id, tos_version)`

Notes:
- Account-level acceptance is the “master” acceptance for the Customer entity
- Per-user acceptance uniquely tracks each loginable person’s consent

### Privacy Policy data model (mirrors TOS)

- `eb_privacy_versions`
  - `id` (PK), `version` (e.g. `2026-01-13`), `title`, `summary`, `content_html` (LONGTEXT)
  - `is_active` (bool), `require_acceptance` (bool), `published_at`, `created_at`, `created_by`
  - Indexes/unique: version unique; indexes on `is_active`, `require_acceptance`

- `eb_privacy_client_acceptances`
  - `id` (PK), `client_id`, `privacy_version`, `accepted_at`, `ip_address`, `user_agent`
  - Unique: `(client_id, privacy_version)`

- `eb_privacy_user_acceptances`
  - `id` (PK), `client_id`, `user_id` (nullable), `contact_id` (nullable), `privacy_version`
  - `accepted_at`, `ip_address`, `user_agent`
  - Unique: `(client_id, user_id, contact_id, privacy_version)`

## Files & Structure

### Backend routing – client area
`accounts/modules/addons/eazybackup/eazybackup.php` in `eazybackup_clientarea()`:

- `a=tos-block`: Shows the gating modal page (used by hooks when require_acceptance is enabled)
- `a=legal-accept` (POST): Records acceptance for Terms and/or Privacy (client + user/contact), then redirects to `return_to`
- `a=tos-accept` (POST): Backward-compatible alias for `a=legal-accept`
- `a=terms`: My Account → Terms page (shows acceptance details and link to TOS viewer)
- `a=tos-view[&version=YYYY-MM-DD]`: Read-only render of a specific TOS version (HTML)
- `a=privacy-view[&version=YYYY-MM-DD]`: Read-only render of a specific Privacy Policy version (HTML)

### Backend routing – admin area
`accounts/modules/addons/eazybackup/eazybackup.php` in `eazybackup_output()` (Power Panel):

- `view=terms`: Admin UI for TOS management
  - Controller: `accounts/modules/addons/eazybackup/pages/admin/terms/index.php`
  - Wrapper fallback: `accounts/modules/addons/eazybackup/pages/admin/powerpanel/terms.php`
  - Operations:
    - `create` → insert new version (inactive)
    - `publish` → set selected version active (deactivates others)
    - `toggle_require` → turn require-acceptance on/off for the active version

- `view=privacy`: Admin UI for Privacy Policy management (mirrors Terms)
  - Controller: `accounts/modules/addons/eazybackup/pages/admin/privacy/index.php`
  - Operations:
    - `create` → insert new version (inactive)
    - `publish` → set selected version active (deactivates others)
    - `toggle_require` → turn require-acceptance on/off for the active version

### Hooks – client-area gating
`accounts/modules/addons/eazybackup/hooks.php`

- `ClientAreaPage` hook enforces acceptance across the portal when an active Terms and/or Privacy version has `require_acceptance=1`
- Whitelists legal routes (`tos-block`, `legal-accept`, `tos-accept`, `tos-view`, `privacy-view`) and login/logout flows to prevent redirect loops
- On missing acceptance for any required version: redirects to `a=tos-block&return_to=<original>`

### Templates (Smarty)

Client modals and pages:

- `accounts/modules/addons/eazybackup/templates/tos-block.tpl`
  - Modal UI that blocks access until acceptance
  - Checkbox + link for each required document (Terms and/or Privacy)
  - Links open in-modal document viewers
  - No scroll-to-bottom requirement; checkbox acceptance only
  - After acceptance, notes that Terms and agreement details are available in My Account → Terms

- `accounts/modules/addons/eazybackup/templates/terms.tpl`
  - My Account → Terms page (shows accepted versions, timestamps, IP/UA; links to view each version)

- `accounts/modules/addons/eazybackup/templates/tos-view.tpl`
  - Read-only display of the `content_html` for any version
  - Uses `{$tos->content_html|unescape:'html' nofilter}` to render saved HTML as-is

- `accounts/modules/addons/eazybackup/templates/privacy-view.tpl`
  - Read-only display of the `content_html` for any privacy version
  - Uses `{$privacy->content_html|unescape:'html' nofilter}` to render saved HTML as-is

Navigation:

- `accounts/templates/eazyBackup/includes/profile-nav.tpl`
  - Adds a “Terms” tab linking to `index.php?m=eazybackup&a=terms` (desktop + mobile nav)

### Admin UI (Power Panel)

`accounts/modules/addons/eazybackup/pages/admin/terms/index.php`:

- Create version: saves `version`, `title`, `summary`, `content_html` (inactive by default)
- Publish version: sets `is_active=1` and `published_at=NOW()` (deactivate others)
- Require acceptance: toggles `require_acceptance` on the active version
- Renders a table of existing versions with their status and actions

## Security & Guards

- Admin routes require an admin session (`$_SESSION['adminid']`) and CSRF validation (`check_token('WHMCS.admin.default')`)
- Client routes use `check_token('WHMCS.clientarea.default')` for `legal-accept` / legacy `tos-accept` POSTs
- ClientArea gating hook:
  - Whitelists TOS routes and login/logout to prevent loops
  - On exception in the hook, fails open (so users are not locked out by hook failures)

## Rendering Notes

- TOS page and modal use `stdClass` objects – access fields as `{$tos->title}` etc. (not array syntax)
- To ensure admin-supplied HTML is rendered correctly on `tos-view.tpl` and in the in‑modal panel:
  - Use `|unescape:'html' nofilter` on `content_html`
  - The summary (if provided) is a short text/HTML snippet

## How “Require Acceptance” Works

1. Admin publishes a Terms version and/or a Privacy version and turns on “Require acceptance” for each as desired
2. ClientArea hook detects required active version(s) and missing acceptance for the current login identity
3. Redirects to `a=tos-block` (blocking modal) with `return_to` of the originally requested page
4. On “Accept and Continue” → records acceptance, then redirects back to `return_to`

## Acceptance Recording

On `a=legal-accept` (or legacy `a=tos-accept`):

- Client-level (one per client/version) is written for each document:
  - `eb_tos_client_acceptances` (`tos_version`)
  - `eb_privacy_client_acceptances` (`privacy_version`)

- Per-user (per login persona per version):
  - If sub-account contact session exists (`$_SESSION['cid'] > 0`) → `contact_id`
  - Otherwise owner user (WHMCS 8 users) or “owner context” is recorded with `user_id` and/or both null to represent the owner context depending on implementation
  - Stored in:
    - `eb_tos_user_acceptances` (`tos_version`)
    - `eb_privacy_user_acceptances` (`privacy_version`)

## Configuration & Customization

- Scope of gating: By default the hook gates the entire client area when `require_acceptance=1`. If you want to scope to only module pages, change the `ClientAreaPage` hook logic accordingly.
- Text copy: You can update the language in `tos-block.tpl` and `terms.tpl` as required.
- Admin UI: The controller under `pages/admin/terms/index.php` can be extended (e.g., add a WYSIWYG editor, version cloning, etc.).

## Testing

1. As Admin:
   - Create a new TOS version and a new Privacy version and populate `content_html` with sample HTML
   - Publish both versions
   - Enable “Require acceptance” on both
2. As Client:
   - Login → verify modal appears
   - Click “Terms of Service” link → modal opens with full HTML
   - Click “Privacy Policy” link → modal opens with full HTML
   - Tick both checkboxes → “Accept and Continue” becomes enabled
3. After acceptance:
   - My Account → Terms shows accepted versions, timestamps, IP/UA; links show exact HTML
   - Toggle require off → verify no gating on subsequent logins

## Troubleshooting & Known Pitfalls

- “stdClass as array” errors in Smarty:
  - Always use object access (`{$tos->version}`), not array syntax (`{$tos.version}`) in templates

- HTML showing as literal text in the viewer:
  - Ensure `|unescape:'html' nofilter` is applied in the template to render saved HTML correctly

- Redirect loops when gating:
  - Ensure the hook whitelists module routes (`tos-block`, `legal-accept`, `tos-accept`, `tos-view`, `privacy-view`) and login/logout pages
  - If you add routing changes, update the whitelist to match

## File Map (Quick Reference)

- Database migrations:
  - `accounts/modules/addons/eazybackup/eazybackup.php` → `eazybackup_migrate_schema()`

- Admin (Power Panel):
  - `accounts/modules/addons/eazybackup/pages/admin/terms/index.php`
  - `accounts/modules/addons/eazybackup/pages/admin/powerpanel/terms.php` (fallback loader)

- Client routes:
  - `a=tos-block`, `a=legal-accept` (and legacy `a=tos-accept`), `a=terms`, `a=tos-view`, `a=privacy-view` in `eazybackup_clientarea()`

- Hook (gating):
  - `accounts/modules/addons/eazybackup/hooks.php` (`ClientAreaPage` hook)

- Templates:
  - `accounts/modules/addons/eazybackup/templates/tos-block.tpl`
  - `accounts/modules/addons/eazybackup/templates/terms.tpl`
  - `accounts/modules/addons/eazybackup/templates/tos-view.tpl`
  - `accounts/modules/addons/eazybackup/templates/privacy-view.tpl`

- Navigation:
  - `accounts/templates/eazyBackup/includes/profile-nav.tpl` (adds Terms tab)

## Future Enhancements

- Admin-side version diffing and archival/audit trail
- Rich text editor for `content_html` with sanitization policy
- Per-locale TOS versions and auto-selection based on client locale
- Granular scoping (e.g., require acceptance only for specific client groups)


