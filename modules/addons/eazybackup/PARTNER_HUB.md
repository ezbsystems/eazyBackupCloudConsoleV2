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

SQL Files:
- `sql/partner_hub_phase1.sql` creates the 3 new tables above.
- `sql/partner_hub_emails.sql` seeds templates: customer welcome, MSP order notice.

## Files Added/Updated (Highlights)
- Controllers: PublicSignupController.php (new), PublicDownloadController.php (new), SignupSettingsController.php (new), BuildController.php (updated)
- Templates: public-signup.tpl (new), public-download.tpl (new), public-invalid-host.tpl (new), signup-settings.tpl (new), branding-list.tpl (updated with slide-over)
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
