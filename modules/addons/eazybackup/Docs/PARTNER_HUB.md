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

## MSP Clients + Stripe Connect Billing (Implemented)

### Overview
- Added MSP-managed Clients, Plans, Subscriptions, and Stripe Connect (destination charges, on_behalf_of, application_fee).
- Stripe-first billing with Stripe Tax; WHMCS remains the source for authentication/profile.
- Webhook-driven invoice/charge/subscription cache; nightly backfill; per-customer on-demand refresh.
- Services tab to associate WHMCS services with Comet users; Usage ledger and metered usage push.
- Public signup enhancements: enable/disable per tenant; plan/price selection; optional card capture.

### Files Added/Updated

- Library (Stripe + WHMCS bridges):
  - `lib/PartnerHub/StripeService.php` — Connect (accounts/links), Customers, SetupIntents, Prices/Products, Subscriptions, Usage Records, Charges/Invoices list.
  - `lib/PartnerHub/WhmcsBridge.php` — LocalAPI helpers: `AddClient`, `UpdateClient`, `AddUser`, `AddClientUser`.

- Controllers (Client area → Partner Hub):
  - `pages/partnerhub/ClientsController.php` — Clients list (search/sort/paginate), create Client (LocalAPI) and Partner-Hub Customer.
  - `pages/partnerhub/ClientViewController.php` — Client Summary/Profile/Services/Subscriptions/Invoices/Transactions; computes KPIs; loads caches.
  - `pages/partnerhub/PlansController.php` — MSP plans management; creates Stripe Product + Price and persists `eb_plans` / `eb_plan_prices`.
  - `pages/partnerhub/SubscriptionsController.php` — Subscription create; posts to Stripe Subscriptions with `transfer_data.destination`, `on_behalf_of`, `automatic_tax`, optional `application_fee_amount`; persists `eb_subscriptions`.
  - `pages/partnerhub/StripeController.php` — Connect onboarding (AccountLink redirect) and SetupIntent endpoint for Add Card (client-side Elements).
  - `pages/partnerhub/StripeWebhookController.php` — Handles `invoice.*`, `charge.*`, `payment_intent.*`, `customer.subscription.*`; updates caches and subscription status.
  - `pages/partnerhub/BackfillController.php` — Per-customer “Refresh” action to backfill invoices/charges for last 30 days (JSON response + toast).
  - `pages/partnerhub/ServicesController.php` — Link WHMCS service → Comet user; updates `eb_service_links` and tags comet mirrors (msp_id/customer_id).
  - `pages/partnerhub/UsageController.php` — Records usage in `eb_usage_ledger`; pushes a Stripe Usage Record if subscription is metered.
  - `pages/partnerhub/ProfileController.php` — Profile save (Client view) via `WhmcsBridge::updateClient` (JSON + toast).

- Controllers (Public):
  - `pages/whitelabel/PublicSignupController.php` — GET: renders signup (per-tenant branding, plan/price list); POST: validates, abuse controls, creates WHMCS Client, optional card capture (SetupIntent + attach PM + set default), creates Order, Accepts Order, emails, redirects to download.
  - `PublicSignupController::eazybackup_public_setupintent()` — returns `{ publishable, client_secret }` for the public card capture flow.

- Templates:
  - `templates/whitelabel/clients.tpl` — Clients table, search/page-size, Add Client modal (static), Stripe Connect CTA.
  - `templates/whitelabel/client-view.tpl` —
    - Summary/Profile form (toast), Billing KPIs, Subscriptions list and New subscription button.
    - Invoices/Transactions tables with “Refresh” action (toast); Usage form (toast) for metered push.
    - Services link form (toast) and Add Card modal (Stripe Elements).
  - `templates/whitelabel/plans.tpl` — List and create plan (modal); creates Stripe Product + monthly Price.
  - `templates/whitelabel/subscriptions-new.tpl` — Choose plan/price, optional application fee; posts to create subscription.
  - `templates/whitelabel/signup-settings.tpl` — Extended with Enable public signup, Plan/Price picker, and Require Card checkbox.
  - `templates/whitelabel/public-signup.tpl` — Extended with plan/price selector and Stripe card element when Require Card is enabled; calls `public-setupintent`.

- Assets:
  - `assets/js/stripe-elements.js` — Minimal helper to mount card and confirm SetupIntent; used in client view Add Card.

- Cron / Backfill:
  - `bin/stripe_backfill_caches.php` — Nightly job to fetch last 7 days of invoices/charges for all customers and update caches.

- Router and hooks:
  - `eazybackup.php` — Added Partner Hub routes (`ph-*`), public `public-setupintent`, and migrations; wired all controllers above.
  - `hooks.php` — Partner Hub nav updated; includes Clients (and Plans) links; slide-over in `branding-list.tpl` remains the access point for Signup Settings.

### Database Schema (created/extended by `eazybackup_migrate_schema()`)

- Tenants/public signup (Phase 1 recap):
  - `eb_whitelabel_signup_domains` — per-tenant signup host verification.
  - `eb_whitelabel_signup_flows` — extended with: `is_enabled TINYINT(1)`, `plan_price_id BIGINT`, `require_card TINYINT(1)` and branding/abuse fields.

- MSP + Customers + Billing:
  - `eb_msp_accounts` — MSP linkage to WHMCS client; `stripe_connect_id`, branding/invoice JSON.
  - `eb_customers` — Partner-Hub Customer mapping to WHMCS Client; optional `stripe_customer_id` for platform.
  - `eb_customer_user_links` — Maps WHMCS users to Customers (Owner/Viewer).
  - `eb_customer_comet_accounts` — Customer ↔ Comet user pivot.
  - `eb_service_links` — WHMCS service → Comet user; optional `msp_id`, `customer_id` for scoping.
  - `eb_plans` / `eb_plan_prices` — MSP plans; Stripe Product/Price linkage; supports metered prices (`is_metered`, `metric_code`).
  - `eb_subscriptions` — MSP subscriptions; Stripe subscription id/status; current price.
  - `eb_usage_ledger` — Metric, qty, window, source, idempotency, `pushed_to_stripe_at`.
  - `eb_invoice_cache` / `eb_payment_cache` — Cached invoices and payment intents/charges for fast UI.
  - Mirrors: added nullable `msp_id`, `customer_id` to `comet_users`, `comet_devices`, `comet_items`, `comet_jobs` when present.

### Routing (new)

- Client area (Partner Hub):
  - `a=ph-clients`, `a=ph-client`, `a=ph-plans`, `a=ph-subscriptions`.
  - `a=ph-stripe-onboard`, `a=ph-stripe-setupintent`, `a=ph-stripe-subscribe`, `a=ph-stripe-webhook` (POST).
  - `a=ph-invoices-refresh` (per-customer JSON refresh), `a=ph-services-link` (service ↔ Comet user), `a=ph-usage-push` (usage ledger + usage record).
  - `a=ph-client-profile-update` (WHMCS client profile save).

- Public:
  - `a=public-signup` (GET/POST; per-host tenant resolution; Turnstile; abuse controls; idempotent events; WHMCS client/order/accept; optional card capture).
  - `a=public-setupintent` (POST; returns publishable key + SetupIntent client_secret for Stripe Elements).

### Key Flows

- Stripe Connect onboarding: Partner Hub → Clients page shows CTA until `charges_enabled`; route builds Account Link and redirects.
- Add Card (client view): calls `ph-stripe-setupintent`, mounts card, confirms SI, toasts on completion.
- Plans/Prices: creates Stripe Product and monthly Price; persists to `eb_plans`/`eb_plan_prices`.
- Subscriptions: creates Stripe Subscription with `transfer_data.destination` (MSP), `on_behalf_of` (MSP), `automatic_tax`, optional application fee; mirrors to DB; webhook keeps status in sync.
- Webhooks: caches invoices/charges/payment intents and updates subscriptions; defensive JSON handling.
- Invoice/Transaction refresh: per-customer JSON endpoint to re-pull recent data (toast in UI); nightly backfill cron covers drift.
- Services Link: associates WHMCS service id to Comet user (pivot + mirrors); toast on success.
- Usage Ledger: records metric/qty window; if metered price is active, pushes Stripe Usage Record and stamps `pushed_to_stripe_at`.
- Public Signup (enhanced): enable/disable per tenant; plan/price select; optional card capture before order; same abuse controls/Turnstile; welcome + MSP notice emails; redirect to download.

### Security & UX Notes
- All Partner Hub routes enforce client ownership; JSON actions scope by `msp_id` and `customer_id`.
- CSRF where applicable (existing token helpers); public route gated by host + feature flag + Turnstile + rate limiting + domain allow/deny.
- Minimal PII in caches; Stripe keys come from addon settings; logs via `logModuleCall` for observability.
- Non-blocking toasts for manual actions (refresh, link, usage, profile save) for a smoother UX.

## Next Phases (Preview)
- Phase 2: analytics, funnel metrics, richer email editor, client management shortcuts.
- Phase 3: deeper multi-tenant operations, roles, extended billing workflows.


## Updates — October 2025 (Partner Hub hardening and UI/UX polish)

### Overview
This iteration focused on stabilizing the Partner Hub Clients flow, improving client profile editing, aligning UI with the dark style guide, and ensuring CSP‑safe frontend behavior. No database schema changes were required in this session.

### New/Updated Files
- Templates
  - `templates/whitelabel/clients.tpl` — fixed action urls (ensured `modulelink` is passed); added UI polish and pagination styling.
  - `templates/whitelabel/client-view.tpl` —
    - Adopted tokens and dark card pattern (`bg-[rgb(var(--bg-card))]` + `ring-1 ring-white/10` + `shadow-xl`).
    - Standardized form fields to canonical control classes from the style guide.
    - Styled “Add Card” modal with blurred overlay and proper card shell.
    - Added number steppers (Quantity, Period End) using Alpine helper.
    - Moved inline profile‑save JS to CSP‑safe external file include.
- Controllers / Backend
  - `pages/partnerhub/ClientsController.php` — ensured `modulelink` is available to templates, added robust logging during client intake, and normalized data structures for Smarty.
  - `pages/partnerhub/ClientViewController.php` — hardened reads and normalized records to arrays for template use; no schema changes.
  - `pages/partnerhub/ProfileController.php` — JSON endpoint `ph-client-profile-update` now has activity/module logs and improved error reporting.
- Frontend JS
  - `assets/js/client-profile.js` — new CSP‑safe handler for the Client Profile “Save” action (POST fetch to JSON endpoint, toasts, auto‑reload on success, light console logs). Avoids inline scripts and works with strict CSP.

### Routing changes (client management)
- `a=ph-client` (GET) — Manage Client page (Client Summary/Billing/Subscriptions/Transactions/Services). Template: `templates/whitelabel/client-view.tpl` (updated styling + controls).
- `a=ph-client-profile-update` (POST, JSON) — Save client profile edits for the selected Partner‑Hub Customer.
  - Request body: `customer_id`, and any of `firstname, lastname, companyname, email, address1, address2, city, state, postcode, country, phonenumber`.
  - Auth: requires MSP client session; scoping verified via `eb_customers (msp_id)`.
  - Response: `{ status: 'success' }` or `{ status: 'error', message }`.

### Logging / Observability
- Activity Log (`Utilities → Logs → Activity Log`)
  - `eazybackup: ph-clients ...` — client intake trace and redirect markers
  - `eazybackup: ph-client-profile-update ...` — keys received, success/exception markers
- Module Log (enable under `Utilities → Logs → Module Log`)
  - Action `ph-clients:addClient` — intake payload/response
  - Action `ph-client-profile-update` — profile update payload/response

### Email Templates & TinyMCE (recap)
No functional changes this session; Phase 1 behavior remains as documented:
- Self‑hosted TinyMCE (dark theme) under `modules/addons/eazybackup/assets/vendor/tinymce/` with basic plugins and `license_key: 'gpl'`.
- Templates managed via `pages/whitelabel/EmailTemplatesController.php` (list, edit, toggle active), and `lib/Whitelabel/MailService.php` handles token rendering + SMTP send.
- Triggers via `pages/whitelabel/EmailTriggers.php` facade; Public Signup attempts custom Welcome email (fallback to WHMCS native email if SMTP/template unavailable).

### Composer / Autoload
- `composer.json` (addon) updated to map `PartnerHub\` → `lib/PartnerHub/`. Run `composer dump-autoload -o` in `accounts/modules/addons/eazybackup/` after deployment so `WhmcsBridge` and other classes resolve reliably without inline includes.

### Frontend patterns applied (style guide compliance)
- Page tokens partial included on all Partner Hub pages to set CSS variables.
- Cards: `rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden`.
- Controls: universal input/select classes with `bg-[rgb(var(--bg-input))]`, neutral rings, and accent focus.
- Modals: overlay blur + card shell with ring + shadow; buttons adhere to primary/ghost patterns.
- Number stepper: Alpine `ebStepper` helper + Tailwind shell with fixed‑width buttons; see `Docs/STYLING_NOTES.md` for usage.

### Security / CSP
- Eliminated inline JS for profile save to align with strict CSP (`style-src`/`script-src` policies). All dynamic behavior is now in `assets/js/client-profile.js`.

### Database Schema
- No new tables were added in this session. Existing Partner Hub and Email Template schemas remain as previously documented.

## Updates — Stripe Connect Billing (Oct 2025)

### Overview
This update completes Stripe Connect Express with Direct Charges in Partner Hub. MSPs sell Products/Prices on their own connected account; subscriptions and one‑time payments are created on the connected account. The platform collects fees via `application_fee_percent` (subscriptions) and `application_fee_amount` (one‑time). New billing/money pages, status banners, and refresh actions are included.

### Required Settings (Addon → eazyBackup)
- Stripe Publishable Key (platform)
- Stripe Secret Key (platform)
- Stripe Webhook Secret (platform endpoint; Connect enabled)
- Stripe Connect Client ID (Express onboarding)
- Partner Hub: Default Application Fee % (module‑wide fallback)

### Admin Area — MSP Defaults
- Admin → Addons → eazyBackup → Power Panel → Partner Hub (new tab)
  - Set per‑MSP `default_fee_percent` (0–100, two decimals).
  - Stored in `eb_msp_accounts.default_fee_percent`.

### Client‑Area Routes (new/updated)
- Stripe Account
  - `a=ph-stripe-connect` — Connect status (charges_enabled, payouts_enabled, currently_due; resume onboarding link)
  - `a=ph-stripe-onboard` — Hosted onboarding/resume
  - `a=ph-stripe-manage` — Embedded account management page
  - `a=ph-stripe-account-session` — JSON; creates Account Session for embedded components
  - `a=ph-stripe-manage-redirect` — Redirects to Stripe Express Dashboard via short‑lived Login Link (fallback when embed isn’t available)
- Billing
  - `a=ph-billing-subscriptions` — List subscriptions
  - `a=ph-billing-invoices` — List invoices (hosted link + dashboard deep link)
  - `a=ph-billing-payments` — List one‑time payments
  - `a=ph-billing-payment-new` — New one‑time payment (Elements)
  - `a=ph-billing-create-payment` — JSON; creates PaymentIntent on connected account
- Money
  - `a=ph-money-payouts` — Payouts (+ Refresh last 30 days)
  - `a=ph-money-disputes` — Disputes (+ Refresh last 30 days)
  - `a=ph-money-balance` — Balance summary + Balance Transactions (filters, CSV export)

### Backfill/Refresh
- `a=ph-invoices-refresh` — Refresh last 30 days of invoices/charges per customer
- `a=ph-payouts-refresh` — Refresh last 30 days of payouts
- `a=ph-disputes-refresh` — Refresh last 30 days of disputes

### Embedded Account Management

- Server: `a=ph-stripe-account-session` creates an Account Session and returns:
  - `{ status: 'success', publishable, client_secret }` on success
  - `{ status: 'error', message: 'not_connected' | 'config_missing' | 'session_failed' }` on failure
- Client (Manage page): uses Stripe Connect embedded components per Stripe docs
  - Load `https://connect-js.stripe.com/v1.0/connect.js`.
  - Initialize `StripeConnect.init({ publishableKey, fetchClientSecret })` where `fetchClientSecret` POSTs to `ph-stripe-account-session` to obtain a fresh Account Session.
  - Create and append the component (we try common names): `connectInstance.create('account-management')` (preferred) or `'accountManagement'`.
  - Loader + error states: shows loader (using `ebShowLoader`), handles network errors, not_connected, invalid/missing fields; logs lightweight analytics to console (prefixed `[eb.stripeManage]`).
  - Fallback: if the embedded component isn’t available or CSP blocks Connect.js, show an inline callout with a button to `a=ph-stripe-manage-redirect`, which generates a secure Express Login Link to the Stripe Dashboard for the connected account.
- CSP
  - Allow `connect-js.stripe.com` (Connect.js), `js.stripe.com` (Stripe.js resources), and `m.stripe.network` (auth/cookies helper) in script-src and frame-src as required. See Stripe docs for the current list.

Reference: Get started with Connect embedded components — `https://docs.stripe.com/connect/get-started-connect-embedded-components`

### Connect & Status (Client area)

- Route: `a=ph-stripe-connect`; Template: `templates/whitelabel/stripe-connect.tpl`.
- Primary CTA logic
  - “Connect Stripe” when no connected account.
  - “Resume Onboarding” when requirements are due or when charges/payouts are disabled.
- Status badges
  - Payments Enabled/Disabled, Payouts Enabled/Disabled, Requirements Due (count) with color‑coded pills.
- Requirements checklist
  - Renders `account.requirements.currently_due` with helper copy; empty state when none.
- Last checked + Refresh
  - Shows `eb_msp_accounts.last_verification_check` and provides a manual refresh link (page reload).
- Deep‑link to embedded management
  - Link to `a=ph-stripe-manage` for in‑app management.

### Onboarding flow (Account Link)

- Route: `a=ph-stripe-onboard` builds an Account Link (Express onboarding/resume).
- URL safety
  - We normalize the return/refresh base URL to an absolute `https` origin using `systemsslurl` → `systemurl` → request host; logs the chosen base to Activity Log for diagnostics.
  - Return URL: `...&a=ph-clients&onboard_success=1`; Refresh URL: `...&a=ph-clients&onboard_refresh=1`.
- Post‑return client area callouts (Clients page)
  - Success (green): onboarding complete, with links to Connect & Status and Manage Account.
  - Refresh (neutral): user can resume onboarding or go to Manage.
  - Error (red): `onboard_error=1` when Account Link creation fails; shows retry guidance.
- Platform settings validation
  - Before creating Account Links, we validate platform Publishable/Secret keys. Missing keys are logged to Activity Log but never exposed to the client UI.

### Webhooks (Connect events)

- Endpoint: `a=ph-stripe-webhook` with Connect events enabled in Stripe.
- Verification: Stripe signature preferred (if `stripe-php` is available) or HMAC fallback.
- Idempotency: `eb_stripe_events` stores processed event ids.
- Updates on `account.updated` and `capability.updated`
  - Snapshots `charges_enabled`, `payouts_enabled`, `connect_capabilities`, `connect_requirements`, and stamps `last_verification_check`.
  - On `capability.updated`, we pull a fresh Account snapshot; on failure, we still stamp the verification check time.
- Operational safety: if the webhook secret is missing, we log an Activity entry and return 200 to avoid noisy failures.

### Fee Defaults Cascade (subscription create)
1) Subscription override (form input)
2) Plan price default `eb_plan_prices.application_fee_percent`
3) MSP default `eb_msp_accounts.default_fee_percent`
4) Module default `partnerhub_default_fee_percent`

### Webhook (Connect‑mode)
- Endpoint: `{systemurl}/index.php?m=eazybackup&a=ph-stripe-webhook` with Connect events enabled
- Signature verification: Stripe webhook construct (if available) or HMAC fallback
- Idempotency: `eb_stripe_events` stores processed event ids
- Events: `account.updated`, `capability.updated`, `customer.subscription.*`, `invoice.*`, `payment_intent.*`, `payout.*`, `charge.dispute.*`, `application_fee.*`

### Database Schema (additions)
- `eb_msp_accounts`: +`charges_enabled`, `payouts_enabled`, `connect_capabilities` (JSON), `connect_requirements` (JSON), `onboarded_at`, `last_verification_check`, `default_fee_percent` (DECIMAL(5,2))
- `eb_plan_prices`: +`application_fee_percent` (DECIMAL(5,2) NULL)
- `eb_payouts`: payouts cache (msp_id, stripe_payout_id UNIQUE, amount, currency, status, arrival_date, created, updated_at)
- `eb_disputes`: disputes cache (msp_id, stripe_dispute_id UNIQUE, amount, currency, reason, status, evidence_due_by, charge_id, created, updated_at)
- `eb_stripe_events`: idempotency store for webhook event ids

### UI/UX
- Partner Hub nav updated (nested): Billing (Subscriptions/Invoices/Payments), Money (Payouts/Disputes/Balance), Stripe Account (Connect & Status/Manage), Settings
- Lists: currency/amount formatting, date formatting, colored status pills, deep links to the Stripe Dashboard
- Clients page: banner when Connect requirements are due with links to Connect & Status and Resume Onboarding

### Partner Hub Catalog - Products and Plans

Treat “Cloud Storage” and “Workstation Seat” as separate Products. A purchasable “plan” is a Subscription composed of multiple Prices (one per Product), e.g., Storage (metered) + Seat (per‑unit).

Why multiple Prices in one Product?

- Variants of the same thing: monthly vs annual, different currencies, tiers of the same feature, or a one‑time price for the same product line.
- Cleaner catalog: one canonical product identity (name, description, branding) with all its billing variants; easier reporting and management in Stripe.
- Flexible selling: pick the appropriate Price at checkout, swap intervals later without changing the product family.

When NOT to pack into one Product:
- Different resources/metrics (Storage vs Seat vs Disk Image) should be separate Products. Combine them at subscription time by selecting multiple Prices (one from each Product).
Rule of thumb:
- Same resource, different billing variant → multiple Prices under one Product.
- Different resources/features → separate Products; compose a plan by adding their Prices together.

One resource per product (builder rule)

- Each Product in Partner Hub represents a single resource type (base metric). Examples: `STORAGE_TB`, `DEVICE_COUNT`, `DISK_IMAGE`, `HYPERV_VM`, `PROXMOX_VM`, `VMWARE_VM`, `M365_USER`, `GENERIC`.
- Prices within a Product are billing variants of that resource: monthly vs annual, one‑time vs recurring, or different currencies/tier names.
- Storage requires a billing unit selection (GiB or TiB). The unit label is locked and pricing is interpreted per selected unit:
  - GiB → amount per GiB; per‑TiB display = GiB amount × 1024
  - TiB → amount per TiB; per‑GiB computed for metering
- The builder prevents publishing mixed‑metric products and will prompt to split.

Splitting mixed products

- If an existing Product contains multiple resource types, use the Split action to move all Prices of a chosen metric into a new Product. The new Product inherits the name (with a suffix) and sets its base metric. Publish to create the corresponding Stripe Product/Prices when ready.

## Catalog — Products (Stripe Connect UI & API)

This section documents the current Catalog → Products experience for Stripe‑connected MSPs, including list and detail pages, data model, endpoints, and Stripe integration.

### Pages and Templates

- Products List (Stripe‑style)
  - Route: `index.php?m=eazybackup&a=ph-catalog-products`
  - Template: `templates/whitelabel/catalog-products-list.tpl`
  - Purpose: Lists Stripe‑connected products (only), shows counts (All/Active/Archived), a search bar, export buttons (CSV), and a table with per‑row actions (Archive/Unarchive/Delete) and a link to Product detail.

- Product Detail (editor)
  - Route: `index.php?m=eazybackup&a=ph-catalog-product&id=<stripe_product_id>`
  - Template: `templates/whitelabel/catalog-product.tpl` (includes `catalog-products.tpl` editor)
  - Purpose: Create/edit product info and pricing, including Stripe products and prices.

### Controllers and Actions (pages/partnerhub/CatalogProductsController.php)

- `eb_ph_catalog_products_list` — list page: loads MSP + Stripe readiness; fetches products and their prices for summaries and counters
- `eb_ph_catalog_product_show` — detail page wrapper reusing the existing editor
- Stripe JSON:
  - `eb_ph_catalog_product_get_stripe` (GET) — returns product + prices; `active=1|0|all`
  - `eb_ph_catalog_product_save_stripe` (POST) — updates product fields; updates nickname/active; new Price on amount change; deactivates removed prices; optionally persists `features_json`
  - `eb_ph_catalog_product_archive_stripe` / `eb_ph_catalog_product_unarchive_stripe` — toggle product active
  - `eb_ph_catalog_product_delete_stripe` — delete product (HTTP DELETE)
- Local JSON (existing): `eb_ph_catalog_product_get`, `eb_ph_catalog_product_save`, `eb_ph_catalog_products_create`, `eb_ph_catalog_price_create`, toggles
- CSV exports: `eb_ph_catalog_export_products`, `eb_ph_catalog_export_prices`

Router (`eazybackup.php`) maps all routes above, plus the CSV exports.

### Stripe bridge (lib/PartnerHub/CatalogService.php)

- `listProducts`, `retrieveProduct`, `updateProduct(active|name|description)`, `deleteProduct`
- `createPrice`, `listPrices(product,limit,active|null)`, `updatePrice(nickname|active)`

### Frontend JS (assets/js/catalog-products.js)

- `productPanelFactory` — slide‑over editor
  - `openEditStripe(spid)` → GET `ph-catalog-product-get-stripe` (includes CSRF token and cookies)
  - `refreshStripePrices()` → reloads Stripe product (`active=all|1|0`)
  - `save()` (Stripe) → POST `ph-catalog-product-save-stripe` with form‑encoded `payload` and `token`
  - Handles `baseMetric` rules: Storage metered + GiB/TiB, device/VM per‑unit, Generic per‑unit or one‑time
- `pricePanelFactory` — second slide‑over for a single price (includes live preview)
- `window.ebStripeActions` — `archiveProduct`, `unarchiveProduct`, `deleteProduct`
- Hidden token: `<input id="eb-token" value="{$token}">`; all POSTs include it

### Data Model

- `eb_catalog_products`: `id,msp_id,name,description,category,stripe_product_id,active,is_published,published_at,default_currency,created_by,updated_by,base_metric_code,features_json,created_at,updated_at`
- `eb_catalog_prices`: `id,product_id,name,kind,currency,unit_label,unit_amount,interval,aggregate_usage,metric_code,stripe_price_id,active,billing_type,version,supersedes_price_id,is_published,published_at,last_publish_request_id,amount_per_gb_cents,display_per_tb_money,created_at,updated_at`

Storage units:

- GiB input → per‑GiB billing; per‑TiB display = ×1024
- TiB input → per‑TiB billing; per‑GiB computed = ÷1024 for metering

### CSV exports

- Products: `id,name,active,created`
- Prices: `product_id,price_id,nickname,currency,unit_amount,interval,kind,active,created` (UTF‑8 BOM)

### Dev tips

- Verify list at `ph-catalog-products` (search and counters)
- Use the row ellipsis to Archive/Unarchive/Delete on Stripe and confirm in Console
- Click a product name to open the detail editor
- 302/redirects: ensure token + cookies; JS falls back to full‑page redirect when non‑JSON is detected
- Default Stripe list limit is 100; add pagination if needed later

## Settings — Tax & Invoicing (Nov 2025)

### Overview

Adds a dedicated Partner Hub page to centralize tax behavior and invoice presentation for the MSP’s Stripe‑connected account. The page is lightweight but professional, with sensible defaults and a preview modal. Settings persist per MSP and are used across Catalog (Price creation), invoices, and future device‑generated one‑time payments.

### Pages and Templates

- Route: `index.php?m=eazybackup&a=ph-settings-tax`
- Template: `templates/whitelabel/settings-tax.tpl`
- Script: `assets/js/settings-tax.js` (CSP‑safe; validation, save, registration CRUD, preview)
- Nav: updated `accounts/templates/eazyBackup/includes/nav_partner_hub.tpl` Settings group → “Tax & Invoicing”

### Controllers and Actions (pages/partnerhub/TaxSettingsController.php)

- `ph-settings-tax` (GET) — render page with merged defaults
- `ph-settings-tax-save` (POST, JSON) — CSRF; parse payload (x-www-form-urlencoded + HTML entities); validate and persist; best‑effort Stripe invoice settings update
- Registrations (JSON):
  - `ph-tax-registrations` (GET) — list local registrations (and mirror state)
  - `ph-tax-registration-upsert` (POST) — add/update one registration (attempt Stripe Tax Registration; mirror locally; audit)
  - `ph-tax-registration-delete` (POST) — delete (attempt Stripe; remove local; audit)

Router mapping lives in [eazybackup.php](mdc:accounts/modules/addons/eazybackup/eazybackup.php).

### Data Model

- `eb_msp_settings`
  - +`tax_json` JSON (nullable) — stores all settings from this page
- `eb_msp_tax_regs`
  - `id (PK)`, `msp_id`, `country (CHAR2)`, `region (optional)`, `registration_number`, `legal_name (optional)`, `stripe_registration_id (optional)`, `source (stripe|local)`, `is_active TINYINT`, timestamps
- `eb_msp_tax_audit`
  - `id (PK)`, `msp_id`, `user_id`, `action (create|update|delete|sync)`, `before_json`, `after_json`, `meta_json`, `created_at`

Schema is created/ensured by `eazybackup_migrate_schema()` in [eazybackup.php](mdc:accounts/modules/addons/eazybackup/eazybackup.php).

### Settings JSON shape (stored in `tax_json`)

```
{
  "tax_mode": {
    "stripe_tax_enabled": true,
    "default_tax_behavior": "exclusive",
    "respect_exemption": true
  },
  "registrations": {
    "business_address": { "line1":"", "line2":"", "city":"", "state":"", "postal":"", "country":"CA" }
  },
  "invoice_presentation": {
    "invoice_prefix": "EB-",
    "footer_md": "Thanks for your business.",
    "show_logo": true,
    "show_legal_override": false,
    "legal_name_override": "",
    "payment_terms": "due_immediately",
    "show_qty_x_price": true
  },
  "credit_notes": {
    "allow_partial": true,
    "allow_negative_lines": false,
    "default_reason": "customer_request"
  },
  "rounding": {
    "rounding_mode": "bankers_rounding",
    "writeoff_threshold_cents": 50
  }
}
```

### Service Layer

- `lib/PartnerHub/SettingsService.php`
  - `getTaxSettings(mspId)`, `saveTaxSettings(mspId, json)`
  - Registrations: `listRegistrations`, `upsertRegistration`, `deleteRegistration`
  - `auditTax(mspId, action, before?, after?, meta?, userId?)`
- `lib/PartnerHub/StripeService.php`
  - `updateInvoiceSettings(mspId, fields)` — attempts `settings[invoice][footer|days_until_due]` (and legacy aliases) on the connected account
  - Tax Registrations: `listTaxRegistrations(accountId)`, `createTaxRegistration(accountId, params)`, `deleteTaxRegistration(accountId, registrationId)`

### Sections & Effects

1) Tax Mode
- Fields: Enable Stripe Tax, Default tax behavior (`exclusive|inclusive`), Respect customer tax exemption
- Effects:
  - Catalog Price creation should apply `tax_behavior` default
  - When possible, update connected account invoice settings to align with behavior

2) Registrations
- Fields: Country/Region, Registration number, Legal name override; Business address (for invoice header)
- Effects:
  - Uses Stripe Tax Registrations API when supported; otherwise stores locally and includes details in invoice footer/header
  - All changes audited to `eb_msp_tax_audit`

3) Invoice Presentation
- Fields: Invoice prefix, Footer/Memo (limited Markdown), Show logo, Legal name override, Payment terms (`due_immediately|net_7|net_15|net_30`), Show quantity × unit price
- Effects:
  - Best‑effort update of Stripe `invoice_settings[footer]` and default `days_until_due` (when applicable)
  - Prefix is stored locally and applied where we render PDFs/emails; Stripe‑hosted pages retain their native formatting
  - Preview modal shows a sample invoice using current selections

4) Credit Notes & Refunds
- Fields: Allow partial, Allow negative line items, Default credit note reason (`customer_request|service_issue|promotion`)
- Effects: Drives UI permissions and defaults when staff adjust invoices; aligns with Stripe credit notes semantics

5) Rounding & Minor Units (optional)
- Fields: Rounding mode (`bankers_rounding|round_half_up`), Small balance write‑off threshold (money)
- Effects: Platform‑side only; applied during invoice preview and one‑time adjustments

### Validation & Guardrails

- Invoice prefix: `/^[A-Z0-9\-_.]{0,16}$/i`
- Warn (non‑blocking) if Stripe Tax is enabled but no registrations are present
- CSRF on all POSTs; JSON payload parser tolerates x-www-form-urlencoded + HTML entities
- Footer Markdown sanitized (allow basic emphasis/links; strip scripts/on*)

### Logging / Observability

- Module Log entries for `ph-settings-tax-save` and registration CRUD
- Activity Log summaries
- Immutable audit rows in `eb_msp_tax_audit`
