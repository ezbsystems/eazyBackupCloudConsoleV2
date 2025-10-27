# eazyBackup Partner Hub - Phase 1 (MSP-Branded Public Registration)

This document describes the eazyBackup Partner Hub project and Phase 1 deliverables that enable MSPs to operate a fully branded public registration flow for their customers. It covers new routes, UI, backend controllers, database tables, and host/ops integrations.

## Goals
- Branded public signup experience on a custom signup domain (e.g., signup.acmebackup.com)
- Automatic WHMCS order creation and Comet account provisioning
- Branded customer welcome email and MSP notification
- Redirect to a branded download page after signup
- Client-area controls to configure the public signup flow and attach a signup domain

## Feature Flags & Routing
- Addon setting `PARTNER_HUB_SIGNUP_ENABLED` gates public routes and Partner Hub nav.
- Addon setting `ops_whmcs_upstream` is used by HostOps when writing HTTPS vhosts for signup domains.
- Public routes resolve tenant by Host header; unknown hosts show an invalid-host page.

## Public-Facing Pages
- Public Signup
  - Route: `index.php?m=eazybackup&a=public-signup`
  - Template: `templates/whitelabel/public-signup.tpl`
  - Behavior: shows MSP branding, enforces Turnstile, creates client + order, provisions Comet account, logs an event, and redirects to the branded download page.
- Public Download
  - Route: `index.php?m=eazybackup&a=public-download`
  - Template: `templates/whitelabel/public-download.tpl`
- Invalid Host Page
  - Template: `templates/whitelabel/public-invalid-host.tpl`

## Client-Area Management (MSP)
- Signup Settings (per-tenant)
  - Route: `index.php?m=eazybackup&a=whitelabel-signup-settings&id=<tenantId>`
  - Controller: `pages/whitelabel/SignupSettingsController.php`
  - Template: `templates/whitelabel/signup-settings.tpl`
  - Configure product/promo/payment, content (hero, bullets, links), abuse controls (allow/deny domains, per-IP/per-email rate limits), Turnstile override, and signup domain (DNS check, attach, cert, HTTPS vhost).
- Branding & Hostname + Tenant Picker
  - List: `index.php?m=eazybackup&a=whitelabel-branding` (shows all tenants)
  - Manage tenant: `index.php?m=eazybackup&a=whitelabel-branding&id=<tenantId>`
  - Template: `templates/whitelabel/branding-list.tpl` includes a slide-over panel with two actions for the selected tenant:
    - Signup Settings -> `a=whitelabel-signup-settings&id=<tenantId>`
    - Manage Branding -> `a=whitelabel-branding&id=<tenantId>`
    - Email Templates -> `a=whitelabel-email-templates&tid=<tenantPublicId>` (per-tenant templates with enable/disable toggle and test send)
- Partner Hub Nav
  - Theme include: `accounts/templates/eazyBackup/includes/nav_partner_hub.tpl`
  - Variables injected via `hooks.php` (`eb_partner_hub_enabled`, `eb_partner_hub_links`).

## Backend Controllers
- `pages/whitelabel/PublicSignupController.php`
  - GET: render signup; POST: Turnstile verification, domain allow/deny, per-IP/per-email rate limiting, LocalAPI (AddClient, AddOrder, AcceptOrder), provisioning, email sends, redirect to public-download.
- `pages/whitelabel/PublicDownloadController.php`
- `pages/whitelabel/SignupSettingsController.php`
  - GET/POST flow configuration, content, abuse controls.
  - AJAX: `a=whitelabel-signup-checkdns` and `a=whitelabel-signup-attachdomain` (HostOps HTTP stub -> cert -> HTTPS to WHMCS upstream).
  - Runtime safety: ensures signup tables exist if missing.
- `pages/whitelabel/BuildController.php`
  - Intake `a=whitelabel` creates tenant and runs provisioning steps (Comet org, admin user/policy, branding/email, storage template, WHMCS server+product, cert/vhost). Intake defaults blank colors to `#1B2C50`.

## Host/Ops Integrations
- `lib/Whitelabel/HostOps.php`
  - New `writeSignupHttps(string $fqdn)` to proxy signup host to WHMCS upstream.
  - Existing helpers: `writeHttpStub`, `issueCert`, `writeHttps`, `deleteHost`.
- `lib/Whitelabel/CometTenant.php`
  - Branding applies by merging over existing branding to avoid nulling unspecified fields and performs read-back verification of key fields. Fail-closed if mismatch.

## Database Schema (Phase 1)
All tables InnoDB + utf8mb4.

- `eb_whitelabel_signup_domains`
  - id (PK), tenant_id, hostname, status (pending_dns|dns_ok|cert_ok|verified|disabled|failed), last_error, cert_expires_at, created_at, updated_at
  - Unique(hostname), Key(tenant_id)
- `eb_whitelabel_signup_flows`
  - id (PK), tenant_id, product_pid, promo_code, payment_method, require_email_verify (TINYINT), send_customer_welcome (TINYINT), send_msp_notice (TINYINT), created_at, updated_at
  - Extended columns used in Phase 1 UI: hero_title, hero_subtitle, feature_bullets, tos_url, privacy_url, support_url, accent_override, allow_domains, deny_domains, rate_ip, rate_email, turnstile_sitekey_override
- `eb_whitelabel_signup_events`
  - id (PK), tenant_id, host_header, email, whmcs_client_id, whmcs_order_id, comet_username, status (received|validated|ordered|accepted|provisioned|emailed|completed|failed), error, ip, user_agent, created_at, updated_at
  - Unique(tenant_id,email), Key(tenant_id), Key(status)
- Existing: `eb_whitelabel_tenants` is the canonical tenant record (client ownership, org_id, FQDN, product/server refs, branding/email JSON).

## Database Schema (Email Templates)
All tables InnoDB + utf8mb4.

- `eb_whitelabel_tenant_mail`
  - id (PK), tenant_id (UNIQUE), mode (`builtin|smtp|smtp-ssl`), host, port, username, password_enc (encrypted via WHMCS), from_name, from_email, allow_unencrypted (TINYINT), created_at, updated_at
- `eb_whitelabel_email_templates`
  - id (PK), tenant_id, key (e.g., `welcome`), name, subject, body_html (LONGTEXT), body_text (LONGTEXT), is_active (TINYINT), created_at, updated_at
  - UNIQUE (tenant_id, key)
- `eb_whitelabel_email_log`
  - id (PK), tenant_id, template_id, to_email, status (`queued|sent|failed`), provider_resp, created_at

SQL Files:
- `sql/partner_hub_phase1.sql` creates the 3 new tables above.
- `sql/partner_hub_emails.sql` seeds templates: customer welcome, MSP order notice.
  
Note: Email template tables are also created/maintained automatically by `eazybackup_migrate_schema()`; no manual SQL is required in typical deployments.

## Files Added/Updated (Highlights)
- Controllers: PublicSignupController.php (new), PublicDownloadController.php (new), SignupSettingsController.php (new), BuildController.php (updated)
- Controllers (Email Templates): `pages/whitelabel/EmailTemplatesController.php` (new) — lists/templates edit, toggle active, test send
- Library: `lib/Whitelabel/MailService.php` (new) — per-tenant SMTP, token rendering, PHPMailer send; `pages/whitelabel/EmailTriggers.php` (new) — simple trigger facade
- Templates: public-signup.tpl (new), public-download.tpl (new), public-invalid-host.tpl (new), signup-settings.tpl (new), branding-list.tpl (updated with Email Templates button)
- Templates (Email): `templates/whitelabel/email-templates.tpl` (new), `templates/whitelabel/email-template-edit.tpl` (new, TinyMCE editor, test-send modal, merge fields)
- Routing: `eazybackup.php` (updated) — routes for `whitelabel-email-templates` and `whitelabel-email-template-edit`
- Branding save: `pages/whitelabel/BuildController.php` (updated) — upserts `eb_whitelabel_tenant_mail` when SMTP settings are saved on Branding page

## Email Templates & MSP SMTP

### Overview
- MSPs can configure a custom SMTP server per tenant and manage multiple email templates from the client area (Partner Hub → White‑Label → Email Templates).
- The system sends through the tenant’s SMTP for enabled templates. If SMTP is not configured, custom emails are disabled and the UI shows a callout.

### UI
- Access via tenant slide‑over button “Email Templates”.
- List page (`email-templates.tpl`): shows Name, Key, Subject, Active toggle, Edit link, Configure SMTP callout.
- Edit page (`email-template-edit.tpl`):
  - Fields: Active checkbox, Subject, HTML body (WYSIWYG), optional plain text body.
  - Editor: self-hosted TinyMCE (dark theme) under `modules/addons/eazybackup/assets/vendor/tinymce/`.
  - Test send: styled modal to capture recipient email; success/error shown as toasts (consistent with Branding page toasts).
  - Merge fields: `{{customer_name}}`, `{{brand_name}}`, `{{portal_url}}`, `{{help_url}}` listed with examples.

### Backend Components
- `MailService.php`:
  - `seedSmtpIfMissing(tenantId)` — pulls from `eb_whitelabel_tenants.email_json` into `eb_whitelabel_tenant_mail` on first access.
  - `seedDefaultTemplatesIfMissing(tenantId)` — inserts `welcome` template if missing.
  - `isSmtpConfigured(tenantId)` — validates tenant SMTP is usable.
  - `sendTemplate(tenantId, key, toEmail, vars, allowInactiveForTest=false)` — renders tokens and sends via PHPMailer; logs to `eb_whitelabel_email_log`.
  - HTML rendering uses simple token replacement: `{{token}}`.
- `EmailTemplatesController.php`:
  - `whitelabel-email-templates` — list + toggle `is_active` (CSRF protected).
  - `whitelabel-email-template-edit` — GET (load template), POST (save with sanitization), POST (test send).
  - Sanitization on save strips scripts/on* attributes and disallows `javascript:` URLs.
- `EmailTriggers.php`:
  - Provides `EmailTriggers::trigger($tenantId, $key, $toEmail, $vars)`; used by business events.

### Sending Flow
1) MSP configures SMTP on Branding page (or via seeding) → `eb_whitelabel_tenant_mail` upserted.
2) MSP enables a template and edits Subject/Body in the editor.
3) Trigger fires (e.g., Welcome on signup) → system calls `EmailTriggers::trigger($tenantId,'welcome',to,vars)`.
4) `MailService` checks SMTP + template `is_active`, renders tokens, sends via PHPMailer, logs status.
5) If SMTP is missing or template disabled, sending is skipped; Public Signup controller falls back to WHMCS native email for welcome.

### Triggers
- Implemented: `WELCOME_ON_SIGNUP` in `PublicSignupController.php` — after provisioning, attempt custom Welcome; fallback to WHMCS `SendEmail` when unavailable.
- Future: add more triggers (password reset, onboarding, notices) by calling `EmailTriggers::trigger()` with the appropriate key.

### Security & Privacy
- SMTP passwords stored encrypted (`password_enc`) using WHMCS encrypt/decrypt.
- CSRF protection on POST routes (`generate_token` / `check_token`).
- HTML sanitized on save; plain text optional; messages sent as HTML with AltText when available.
- Template ownership enforced by tenant client context.

### TinyMCE (Self‑Hosted)
- Path: `modules/addons/eazybackup/assets/vendor/tinymce/` (include `tinymce.min.js`, `skins/`, `plugins/`, etc.).
- Init (dark theme):
  - `skin: 'oxide-dark'`, `content_css: 'dark'`, plugins `'link lists'`, toolbar `'bold italic underline | bullist numlist | link | undo redo'`.
  - `license_key: 'gpl'` to use Open Source terms; ensure no cloud-only plugins (e.g., `licensekeymanager`) are referenced.
  - Use `base_url` pointing to the self-host directory so TinyMCE can resolve skins/plugins.

- Library: HostOps.php (updated, adds writeSignupHttps), CometTenant.php (updated)
- Routing/Hook: eazybackup.php (routes), hooks.php (Partner Hub nav + download branding)
- Theme: accounts/templates/eazyBackup/header.tpl (includes Partner Hub nav), includes/nav_partner_hub.tpl (new)

## Defaults, Observability, Abuse Controls
- Intake defaults blank colors to `#1B2C50` for TopColor, AccentColor, TileBackgroundColor.
- `logModuleCall` used for public signup POST steps and verification logs.
- Allow/Deny email domains and 1-hour rate limits configurable per tenant.
- Cloudflare Turnstile enforced; site key override per tenant supported.

## Operating Partner Hub (Phase 1)
1. Enable `PARTNER_HUB_SIGNUP_ENABLED` and set `ops_whmcs_upstream`.
2. Create a tenant via `a=whitelabel` intake.
3. Go to Partner Hub -> Branding & Hostname; click Manage on the desired tenant.
4. In the slide-over: use Signup Settings to configure flow/domain; use Manage Branding to update Comet branding.
5. Once the signup domain is verified and HTTPS is active, customers can sign up on `https://<signup-host>/` and download from `https://<signup-host>/download`.

## Next Phases (Preview)
- Phase 2: analytics, funnel metrics, richer email editor, client management shortcuts.
- Phase 3: deeper multi-tenant operations, roles, extended billing workflows.
