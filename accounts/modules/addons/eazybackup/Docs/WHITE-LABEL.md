# White‑Label Tenant Automation (eazyBackup)

This feature provides one‑click provisioning of a branded tenant environment for resellers and white‑label partners. It automates DNS, TLS, Comet Organization setup (tenant/admin/branding/email/storage), and WHMCS wiring (server, group, product clone). It also exposes client and admin UIs to manage branding and tenants, plus a DEV/test mode for rapid iteration.

## What it does (pipeline)

Provisioning runs as a sequence of idempotent steps (logged in DB), with a friendly customer loader:

1) DNS — “Reserving your service address…”
- Route 53 UPSERT CNAME/A record in your target hosted zone, then poll until INSYNC.

2) Nginx — “Checking that your address is live on the Internet…”
- Write/update tenant vhost config on the reverse proxy host, `nginx -t && systemctl reload nginx`.

3) Certificate — “Making everything shiny and secure…”
- Issue/renew SSL via Certbot (non‑interactive).

4) Comet Organization — “Creating your private management space…”
- Create/Update via `AdminOrganizationSet(Name, Hosts, Branding.DefaultLoginServerURL, IsSuspended=false)` and store `org_id`.

5) Admin user & policy — “Setting up your admin access…”
- Create tenant admin via `AdminAdminUserNew(TargetOrgID)` with a generated strong password (stored encrypted in DB; never shown to customers).
- Clone the template Group Policy (deterministic ID per org) using `AdminPoliciesGet`/`AdminPoliciesListFull` + `AdminPoliciesSet`, then attach it to the new admin by setting `Permissions.AllowedUserPolicies=[<newPolicyId>]` and guard-rail flags (`PreventEditServerSettings=true`, `PreventServerShutdown=true`) in a single `AdminMetaServerConfigSet`. After this write, Comet restarts, so we wait for readiness before continuing.

6) Branding — “Applying your branding…”
- Upload binary assets with `AdminMetaResourceNew` and rewrite all asset paths in Branding to `resource://<hash>` (LogoImage, Favicon, PathHeaderImage, PathAppIconImage, PathTilePng, PathIcoFile, PathIcnsFile, PathMenuBarIcnsFile, PathEulaRtf).
- Merge Branding on the org and write via `AdminOrganizationSet`. Also set:
  - `CompanyName` (from Company input; fallback to ProductName)
  - `CloudStorageName` (defaults to ProductName)
  - Colors via Comet keys `TopColor`, `AccentColor`, `TileBackgroundColor`
  - DefaultLoginServerURL if empty.
- Enable `SoftwareBuildRole` on the Organization (`RoleEnabled=true`, `AllowUnauthenticatedDownloads=false`, `MaxBuilders=0`).

7) Email — “Configuring email options…”
- If SMTP host is blank, inherit parent mail (`Mode=""`, skip). Otherwise map security to `Mode` (`smtp-ssl` for SSL/TLS; `smtp` for STARTTLS or Plain) and set `SMTPAllowUnencrypted=true` only for Plain. Merge on org and write via `AdminOrganizationSet`.

8) Storage — “Preparing storage templates…”
- Build a tenant storage template under `Organization.RemoteStorage` (Type=`comet`, `RebrandStorage=true`, `Default=true`).
  - `ID`: deterministic per-org
  - `Description`: `<ProductName> Cloud Storage` (fallback: `<fqdn> Cloud Storage`)
  - `RemoteAddress`: `https://<fqdn>/`
  - `Username` / `Password`: the tenant admin credentials
- Upsert by ID and write via `AdminOrganizationSet`. Verify visibility with `AdminRequestStorageVaultProviders(TargetOrganization=<orgId>)`, and (optionally) test connectivity via `AdminMetaRemoteStorageVaultTest`.

9) WHMCS wiring — “Finalizing your product…”
- Insert server + group; clone template product; set module server group and defaults.
 - Ensure the required WHMCS configurable option groups are attached to the new product (devices/storage/guests/etc.).

10) Verify —
- Health checks (HTTPS, Comet login URL, storage test) and mark tenant `active`.

All steps are idempotent and safe to re‑run individually in DEV.

## Files and paths

- Router + controller
  - `accounts/modules/addons/eazybackup/eazybackup.php` (routes and addon config)
  - `accounts/modules/addons/eazybackup/pages/whitelabel/BuildController.php` (client: intake, loader, branding, status)

- Service layer
  - `accounts/modules/addons/eazybackup/lib/Whitelabel/Builder.php` (orchestration runner + DEV step runner)
  - `accounts/modules/addons/eazybackup/lib/Whitelabel/AwsRoute53.php` (AWS SDK Route 53 UPSERT + GetChange polling)
  - `accounts/modules/addons/eazybackup/lib/Whitelabel/HostOps.php` (SSH/sudo: write vhost, reload nginx, issue cert)
  - `accounts/modules/addons/eazybackup/lib/Whitelabel/CometTenant.php` (Comet API: org/admin/branding/email/storage)
  - `accounts/modules/addons/eazybackup/lib/Whitelabel/WhmcsOps.php` (server + group insert; product clone)

- Client templates
  - `accounts/modules/addons/eazybackup/templates/whitelabel/loader.tpl` (loader + DEV debug panel)
  - `accounts/modules/addons/eazybackup/templates/whitelabel/branding.tpl` (dark UI, consistent with `console/user-profile.tpl`)
  - `accounts/modules/addons/eazybackup/templates/whitelabel/branding-list.tpl` (multi‑tenant list)
  
- Theme download integration
  - `accounts/templates/eazyBackup/header.tpl` (Download flyout + modals adopt MSP branding: product name, accent color, base URL)
  - `accounts/modules/addons/eazybackup/hooks.php` (exposes `{$eb_brand_download}` to templates: `base`, `base_urlenc`, `productName`, `accent`, `isBranded`)

- Admin page
  - `accounts/modules/addons/eazybackup/pages/admin/whitelabel/index.php` (admin tenants list: search/sort/paginate, suspend/unsuspend/remove)
  - Linked in admin Power Panel tabs: Storage / Devices / Protected Items / Billing / **White‑Label**

## Database tables

- `eb_whitelabel_tenants`
  - `id BIGINT PK`, `client_id`, `status` (`queued|building|active|failed|suspended|removing`), `org_id`, `subdomain`, `fqdn`, `custom_domain`, `product_id`, `server_id`, `servergroup_id`, `comet_admin_user`, `comet_admin_pass_enc` (encrypted), `brand_json`, `email_json`, `policy_ids_json`, `storage_template_json`, `idempotency_key`, `last_build_id`, `created_at`, `updated_at`.

- `eb_whitelabel_builds`
  - `id BIGINT PK`, `tenant_id`, `step` (`dns|nginx|cert|org|admin|branding|email|storage|whmcs|verify`), `status` (`queued|running|success|failed`), `log_json`, `last_error`, `started_at`, `finished_at`, `idempotency_key`.

- `eb_whitelabel_assets`
  - `id BIGINT PK`, `tenant_id`, `asset_type` (`logo|header|icon|tile|app_icon`), `filename`, `comet_resource_hash`, `mime`, `size`, `created_at`.

- `eb_whitelabel_custom_domains`
  - `id BIGINT PK`, `tenant_id BIGINT INDEX`, `hostname VARCHAR(255)`, `status ENUM('pending_dns','dns_ok','cert_ok','org_updated','verified','failed')`, `last_error TEXT NULL`, `checked_at DATETIME NULL`, `cert_expires_at DATETIME NULL`, `created_at DATETIME`, `updated_at DATETIME`, `UNIQUE (tenant_id, hostname)`.

- `eb_whitelabel_tenants` additions
  - `custom_domain VARCHAR(255) NULL`, `custom_domain_status VARCHAR(32) NULL`.

## Addon configuration (WHMCS Addon Settings)

- Feature flag: `whitelabel_enabled` (yes/no)

- AWS: `aws_access_key_id`, `aws_secret_access_key`, `aws_region`, `route53_hosted_zone_id`, `whitelabel_base_domain`

- Comet root admin: `comet_root_url`, `comet_root_admin`, `comet_root_password`

- Host ops:
  - `ops_mode` (`ssh` or `sudo`)
  - `ops_ssh_host`, `ops_ssh_user`, `ops_ssh_key_path` (for SSH mode)
  - `ops_sudo_script` (absolute path to wrapper, e.g., `/usr/local/bin/tenant_provision`)

- WHMCS wiring: `whitelabel_template_pid` (product to clone), `server_module_name` (e.g., `comet`)

- DEV / Test mode:
  - `whitelabel_dev_mode` (yes/no)
  - `whitelabel_dev_fixture_dir` (path to pre‑filled branding assets for faster testing)
  - `whitelabel_dev_skip_dns`, `whitelabel_dev_skip_nginx`, `whitelabel_dev_skip_cert` (skip external ops)

## Admin workflow

1) Admin → Addons → eazyBackup → Power Panel → **White‑Label** tab.
2) Use search, status filters, sort headers, and pagination. Columns: FQDN, custom domain, status, created date.
3) Actions:
   - Suspend / Unsuspend (reflect in tenant status; Comet org suspension/unsuspension in roadmap)
   - Remove (marks tenant as `removing`; safe teardown flow to delete DNS/nginx/cert + Comet Organization should be run before full removal)
4) New tenants appear when a client submits the intake and the builder completes.

## Customer workflow

1) Client Area → Dashboard → “Branding & Hostname”.
2) Intake at `?m=eazybackup&a=whitelabel` — collects Subdomain, Product/Company names, Help URL, colors, optional custom domain (CNAME), and email settings (inherit or SMTP).
3) Loader at `?m=eazybackup&a=whitelabel-loader&id=TENANT_ID` shows friendly status; redirects to Branding page on success.
4) Branding page at `?m=eazybackup&a=whitelabel-branding&id=TENANT_ID` allows future edits to branding/email.
5) Custom Domain (optional): In Hostname, enter a custom subdomain and:
   - Click "Check DNS" — validates CNAME to tenant vanity across multiple resolvers; warns on Cloudflare proxy (A record).
   - Click "Attach Domain" — creates HTTP stub, issues certificate, writes HTTPS vhost, updates Comet Organization (Hosts + DefaultLoginServerURL), verifies HTTPS.
   - UI shows inline loader ("Checking DNS…" / "Attaching domain…") and status pill; shows "Primary:" and "Custom:" hostnames after success.

## DEV / Test mode

- Enable `whitelabel_dev_mode=1`.
- Loader page shows a **DEV Debug Panel** that can run individual steps (dns, nginx, cert, org, admin, branding, email, storage, whmcs, verify).
- Skip toggles allow iterating Comet/WHMCS only: `whitelabel_dev_skip_dns`, `whitelabel_dev_skip_nginx`, `whitelabel_dev_skip_cert`.
- Fixture directory `whitelabel_dev_fixture_dir` (e.g., `modules/addons/eazybackup/assets/whitelabel-dev/`) can host pre‑built branding files for quick submissions:
  - `logo.png` / `logo.svg` — logo
  - `header.png` — header/banner
  - `app_icon.png` — desktop/app icon
  - `tile.png` — installer/tile background
- All steps log to `logModuleCall('eazybackup', …)`; secrets are masked where possible. Steps are idempotent.
  - Custom Domain diagnostics: in DEV mode, JSON includes resolver details and table presence; server errors include exception text.

## Reverse proxy & certificate operations

- **SSH mode (recommended):**
  - `ops_mode=ssh`, `ops_ssh_host=proxy1.eazybackup.internal`, `ops_ssh_user=whitelabelbot`, `ops_ssh_key_path=/path/to/id_ed25519`.
  - Remote host grants limited sudo only to `/usr/local/bin/tenant_provision`, which:
    - writes/updates `/etc/nginx/conf.d/tenants/<slug>.conf` from a template,
    - runs `nginx -t && systemctl reload nginx`,
    - issues/renews SSL with Certbot.

- **Local sudo mode (alternative):**
  - `ops_mode=sudo`, `ops_sudo_script=/usr/local/bin/tenant_provision`.

## Dependencies

- **AWS SDK:** `aws/aws-sdk-php` must be available to PHP for Route 53 calls (`Aws\\Route53\\Route53Client`).
- **Comet API SDK:** A Comet SDK client must be autoloaded for organization/admin/branding/email/storage/policy calls. We use the native `Comet\Server` client when available and fall back to compatible clients. `AdminUserPermissions.AllowedUserPolicies` requires Comet ≥ 23.9.11.

## Comet API summary used

- Organizations: `AdminOrganizationSet`, `AdminOrganizationList` (read; with fallback when unavailable)
- Resources: `AdminMetaResourceNew`
- Email: merged via `AdminOrganizationSet` (`Organization.Email`)
- Policies: `AdminPoliciesGet` / `AdminPoliciesListFull`, `AdminPoliciesSet`
- Server config: `AdminMetaServerConfigGet` / `AdminMetaServerConfigSet` (single write; then readiness wait)
- Storage: `AdminRequestStorageVaultProviders`, `AdminMetaRemoteStorageVaultTest`
 - Organization hosts + URL: `AdminOrganizationSet` to append `Organization.Hosts` and set `Branding.DefaultLoginServerURL` during custom domain attach.

## Resiliency & restarts

- `AdminMetaServerConfigSet` restarts Comet. We consolidate to a single write (policy attach) and wait for readiness before subsequent calls. Transient 5xx from Comet or the reverse proxy are handled with short retries and exponential backoff on critical calls (policy clone/set, org reads).

## Branding keys & assets

- Colors: use Comet keys `TopColor`, `AccentColor`, `TileBackgroundColor` (legacy template aliases are supported for display only).
- Assets: all local file paths are uploaded via `AdminMetaResourceNew` and rewritten to `resource://…` before writing to Comet.
- EULA: textarea content is saved to a local file, uploaded as a resource, and the Branding key `PathEulaRtf` is set to the resulting `resource://…` URL (never leaves a server-local path in Comet).
- Background logo: Removed from UI and backend. Comet no longer supports a distinct "Background logo" asset; corresponding fields have been removed from the intake form and branding management page.

### UI/Backend updates (Oct 2025)
- Custom Domain card added with DNS check/attach flow, inline loader, detailed status, and timestamps. HTTPS verification accepts 2xx/3xx and 401/403.
- Download flyout + modals now dynamically reflect MSP branding (product name, accent color) and use the tenant vanity or verified custom domain base for links (e.g., `{$base}dl/1`). Linux cURL/wget SelfAddress uses the same base URL‑encoded.

### UI/Backend updates (Oct 2025)
- Branding page (client area) redesigned with Tailwind/Alpine and split into three sections (System Branding, Backup Agent Branding, Email Reporting), with Comet as source of truth on GET and after POST (cache refresh).
- Asset status badges and persistent EULA editor added; color pickers standardized; updated accepted file types per asset.
- Toast notifications standardized to user-profile behavior with a global container and robust on-load trigger.
- White-label intake form restyled to match branding page (same three sections, inputs, and upload accept lists) and now includes payment gating: if Stripe is default and no card is on file, submit is disabled and users are linked to the Add Card page.
- Menu gating: “White Label” menu item appears only for reseller clients (based on addon-configured client group IDs); $isResellerClient is computed in controller and passed to templates.

## Email options mapping

- Inherit when SMTP host is empty (`Mode=""`).
- Otherwise set `Mode` to `smtp-ssl` (SSL/TLS) or `smtp` (STARTTLS/Plain); for Plain also set `SMTPAllowUnencrypted=true`.

## Storage template details

- Template lives under `Organization.RemoteStorage` as a `RemoteStorageOption` (Type=`comet`).
- Deterministic per-tenant `ID` ensures idempotent upsert and safe retries.
- Defaults: `RebrandStorage=true`, `Default=true`, `RemoteAddress=https://<fqdn>/`, credentials from the tenant admin.
- Visibility and connectivity can be checked via `AdminRequestStorageVaultProviders(TargetOrganization)` and `AdminMetaRemoteStorageVaultTest`.

## Idempotency, logging, safety

- Tenants have an `idempotency_key`; each step row records status and timestamps.
- Builder updates only the affected step; re‑running steps is safe.
- On failure, set `failed` and re‑run in DEV or suspend the tenant.

## Quick start

1) Configure Addon Settings:
   - Enable `whitelabel_enabled`.
   - AWS credentials + hosted zone + base domain.
   - Comet root URL/admin/password.
   - Host ops SSH details; ensure wrapper script on your proxy host.
   - Template product PID + module name.
   - Optional DEV mode + fixtures directory + skip toggles.
2) Customer submits branding intake; loader shows progress; redirect to Branding.
3) Admin monitors tenants in the **White‑Label** tab (search/sort/paginate).
4) (Optional) Customer attaches a custom domain from the Branding page.
5) Download flyout automatically adopts branding and uses tenant/custome domain base for downloads.

## Backfill: attach required configurable options to existing white-label products

If you created white-label tenants before the “attach config option groups” step was added, you can backfill the missing product links using:

Run a dry-run first:

```bash
cd /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin
php whitelabel_backfill_configoptions.php --dry-run
```

Apply:

```bash
cd /var/www/eazybackup.ca/accounts/modules/addons/eazybackup/bin
php whitelabel_backfill_configoptions.php
```

Scope to a specific tenant or product:

```bash
php whitelabel_backfill_configoptions.php --tenant-id=17 --dry-run
php whitelabel_backfill_configoptions.php --product-id=100
```

## Roadmap

- Full “Remove” teardown orchestration (DNS/nginx/cert + Comet Organization delete) via the Builder.
- Tenant suspend/unsuspend mapping to Comet Organization suspension.
- Health checks and enhanced error reporting.

# eazyBackup White-Label Proxy: Full Nginx Setup & Ops Guide

end-to-end playbook to set up proxy host ready for the white-label flow. It assumes:
- Ubuntu/Debian on both boxes
- Base domain: obcbackup.com
- Proxy hostname: proxy1.eazybackup.internal
- Centralized upstream to Comet server at 192.168.92.165:8060
- For keys: keep the Secure Shell private key owned by the web user (www-data) on the WHMCS box
- Per-tenant vhosts live under /etc/nginx/conf.d/tenants (no sites-available)

**0 Quick topology**
- WHMCS server (billing app): initiates Secure Shell to the proxy
- Proxy server: runs Nginx, Certbot, and your wrapper script
- Comet upstream (behind the proxy): http://obc_servers → 192.168.92.165:8060

**1 Identity & DNS**
**1.1 Set proxy hostname (no reboot needed)**
  sudo hostnamectl set-hostname proxy1.eazybackup.internal
  # optional: ensure immediate local resolution (if your router already has DNS, you can skip):
  echo "192.168.92.111 proxy1.eazybackup.internal proxy1" | sudo tee -a /etc/hosts

Open a new shell to see the prompt update.

**1.2 DNS for tenants**
- All servers point at your MikroTik (192.168.92.1) for DNS. 
- MikroTik has a static record for proxy1.eazybackup.internal → 192.168.92.111.
- MikroTik forwards everything else to 1.1.1.1.

**2 Users & Keys**
2.1 Create service user on proxy (Nginx host)
sudo adduser --disabled-password --gecos "" whitelabelbot
sudo install -d -m 700 -o whitelabelbot -g whitelabelbot /home/whitelabelbot/.ssh

**2.2 Create storage dir & keypair on WHMCS (Option A)**

Use the same user your PHP runs as (usually www-data) so your code can read the key.

  # create a place for keys
  sudo install -d -m 750 -o www-data -g www-data /var/lib/eazybackup/keys

  # generate ed25519 keypair (no passphrase for automation)
  sudo -u www-data -H ssh-keygen -t ed25519 -N "" \
    -C "whmcs@billing" \
    -f /var/lib/eazybackup/keys/whitelabel_ed25519

  # perms
  sudo chmod 600 /var/lib/eazybackup/keys/whitelabel_ed25519
  sudo chmod 644 /var/lib/eazybackup/keys/whitelabel_ed25519.pub

**2.3 Add public key to proxy user’s authorized_keys**

On the proxy:

  # paste public key from WHMCS: /var/lib/eazybackup/keys/whitelabel_ed25519.pub
  sudo bash -c 'cat >> /home/whitelabelbot/.ssh/authorized_keys' <<'EOF'
  ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAA...YOUR_PUBLIC_KEY... whmcs@billing
  EOF
  sudo chown -R whitelabelbot:whitelabelbot /home/whitelabelbot/.ssh
  sudo chmod 600 /home/whitelabelbot/.ssh/authorized_keys

**2.4 Optional: avoid “known_hosts” prompts on WHMCS**
  # create a known_hosts near your key
  sudo -u www-data -H install -m 644 -o www-data -g www-data /dev/null \
    /var/lib/eazybackup/keys/known_hosts

  # pre-seed the proxy host key
  sudo -u www-data -H ssh-keyscan -t ed25519 proxy1.eazybackup.internal \
    >> /var/lib/eazybackup/keys/known_hosts

**2.5 Test Secure Shell from WHMCS**
  sudo -u www-data -H ssh \
    -i /var/lib/eazybackup/keys/whitelabel_ed25519 \
    -o UserKnownHostsFile=/var/lib/eazybackup/keys/known_hosts \
    -o StrictHostKeyChecking=yes \
    whitelabelbot@proxy1.eazybackup.internal 'echo ok'

You should see ok.

**3 Install & prepare Nginx + Certbot on the proxy**
**3.1 Packages**
  sudo apt-get update
  sudo apt-get install -y nginx certbot python3-certbot-nginx
  sudo ufw allow 'Nginx Full' || true

**3.2 Directory layout**
  sudo mkdir -p /etc/nginx/conf.d/tenants
  sudo mkdir -p /etc/nginx/templates
  sudo mkdir -p /var/www/letsencrypt
  sudo chown -R www-data:www-data /var/www/letsencrypt

Ensure the tenants folder is included
  # load tenant vhosts from a file Nginx already includes:
  sudo tee /etc/nginx/conf.d/000-tenants-include.conf >/dev/null <<'EOF'
  # Load all tenant vhosts
  include /etc/nginx/conf.d/tenants/*.conf;
  EOF

  sudo nginx -t && sudo systemctl reload nginx

**3.3 Central upstream (single source of truth)**
  sudo tee /etc/nginx/conf.d/upstream_comet.conf >/dev/null <<'EOF'
  upstream obc_servers {
      server 192.168.92.165:8060 max_fails=0;
      keepalive 128;
  }
  EOF

  sudo nginx -t && sudo systemctl reload nginx


If any older vhost files also declare upstream obc_servers { ... }, remove those duplicate blocks so only this file defines it.

**4 Templates (two-stage)**

We use two templates so Nginx never breaks while we wait for certificates.

**4.1 HTTP stub (for ACME + redirect)**

`/etc/nginx/templates/tenant.http.tpl`

  # HTTP -> HTTPS redirect for {{SERVER_NAME}}
  server {
      listen 80;
      server_name {{SERVER_NAME}};
      access_log /var/log/nginx/{{SERVER_NAME}}_access.log main_ext buffer=256k flush=5s;
      error_log  /var/log/nginx/{{SERVER_NAME}}_error.log;

      # ACME HTTP-01 (webroot)
      location /.well-known/acme-challenge/ {
          root /var/www/letsencrypt;
      }

      return 301 https://$host$request_uri;
  }

**4.2 HTTPS final (after cert exists)**

`/etc/nginx/templates/tenant.https.tpl`

  # HTTPS vhost for {{SERVER_NAME}}
  server {
      listen 443 ssl;
      http2 on;
      server_name {{SERVER_NAME}};

      access_log /var/log/nginx/{{SERVER_NAME}}_access.log main_ext buffer=256k flush=5s;
      error_log  /var/log/nginx/{{SERVER_NAME}}_error.log;

      ssl_certificate     /etc/letsencrypt/live/{{SERVER_NAME}}/fullchain.pem;
      ssl_certificate_key /etc/letsencrypt/live/{{SERVER_NAME}}/privkey.pem;
      include /etc/letsencrypt/options-ssl-nginx.conf;
      ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

      location / {
          proxy_pass {{UPSTREAM}};
          proxy_next_upstream error timeout invalid_header http_500 http_502 http_503 http_504;

          proxy_set_header Host              $host;
          proxy_set_header X-Real-IP         $remote_addr;
          proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
          proxy_set_header X-Forwarded-Proto $scheme;
          proxy_set_header Authorization     $http_authorization;

          proxy_connect_timeout 30s;
          proxy_send_timeout    3000s;
          proxy_read_timeout    3000s;
          client_body_timeout   3000s;

          proxy_buffering         off;
          proxy_request_buffering off;

          proxy_http_version 1.1;
          proxy_set_header Upgrade    $http_upgrade;
          proxy_set_header Connection "upgrade";

          client_max_body_size    0;
          client_body_buffer_size 32k;
      }
  }

**5 Wrapper script + sudoers**
**5.1 /usr/local/bin/tenant_provision**
  #!/usr/bin/env bash
  #
  # tenant_provision - Manage per-tenant Nginx vhosts and certificates
  #
  # Flow:
  #   1) write_http_stub <fqdn>
  #   2) issue_cert <fqdn> <email>
  #   3) write_https <fqdn> <upstream>   # usually http://obc_servers
  #
  # Utilities:
  #   disable <fqdn>    # rename <fqdn>.conf -> <fqdn>.conf.disabled and reload
  #   delete  <fqdn>    # remove both enabled/disabled confs and reload
  #   status  <fqdn>    # prints: enabled|disabled|absent
  #
  set -Eeuo pipefail

  TENANT_DIR="/etc/nginx/conf.d/tenants"
  HTTP_TPL="/etc/nginx/templates/tenant.http.tpl"
  HTTPS_TPL="/etc/nginx/templates/tenant.https.tpl"
  WEBROOT="/var/www/letsencrypt"

  NGINX_TEST_CMD="nginx -t"
  NGINX_RELOAD_CMD="systemctl reload nginx"

  log()  { printf '%s %s\n' "[tenant_provision]" "$*" >&2; }
  die()  { printf '%s ERROR: %s\n' "[tenant_provision]" "$*" >&2; exit 10; }
  usage(){
    cat >&2 <<USAGE
  Usage:
    $0 write_http_stub <host>
    $0 issue_cert <host> <email>
    $0 write_https <host> <upstream>
    $0 disable <host>
    $0 delete <host>
    $0 status <host>
  USAGE
    exit 2
  }

  require_file() { [[ -f "$1" ]] || die "Missing file: $1"; }
  require_dir()  { [[ -d "$1" ]] || die "Missing directory: $1"; }

  validate_host() {
    local h="$1"
    [[ -n "$h" ]] || die "Host is empty"
    [[ "$h" =~ ^[a-zA-Z0-9.-]+$ ]] || die "Host contains invalid characters: $h"
  }
  validate_upstream() {
    local u="$1"
    [[ -n "$u" ]] || die "Upstream is empty"
    [[ "$u" =~ ^[a-zA-Z0-9+.-]+://.*$ || "$u" =~ ^[a-zA-Z0-9_:-]+$ ]] || \
      die "Upstream looks invalid: $u"
  }

  test_and_reload() { ${NGINX_TEST_CMD} >/dev/null 2>&1 || die "nginx -t failed"; ${NGINX_RELOAD_CMD} || die "Failed to reload Nginx"; }

  render_template() {
    local tpl="$1" dest="$2" host="$3" upstream="${4:-}"
    require_file "$tpl"
    mkdir -p "$(dirname "$dest")" || die "Cannot mkdir $(dirname "$dest")"
    if [[ -n "$upstream" ]]; then
      sed -e "s/{{SERVER_NAME}}/${host}/g" -e "s#{{UPSTREAM}}#${upstream}#g" \
        "$tpl" > "$dest"
    else
      sed -e "s/{{SERVER_NAME}}/${host}/g" "$tpl" > "$dest"
    fi
  }

  cmd_write_http_stub() {
    local host="$1"
    validate_host "$host"; require_dir "$TENANT_DIR"; require_file "$HTTP_TPL"
    local conf="${TENANT_DIR}/${host}.conf"
    log "Writing HTTP stub for ${host} -> ${conf}"
    render_template "$HTTP_TPL" "$conf" "$host"
    test_and_reload
    log "HTTP stub active for ${host}"
  }
  cmd_issue_cert() {
    local host="$1" email="$2"
    validate_host "$host"; [[ -n "$email" ]] || die "Email is empty"; require_dir "$WEBROOT"
    log "Issuing certificate (webroot) for ${host}"
    certbot certonly --webroot -w "$WEBROOT" -n --agree-tos -m "$email" -d "$host" \
      || die "Certbot issuance failed for ${host}"
    log "Certificate obtained for ${host}"
  }
  cmd_write_https() {
    local host="$1" upstream="$2"
    validate_host "$host"; validate_upstream "$upstream"
    require_dir "$TENANT_DIR"; require_file "$HTTPS_TPL"
    local cert="/etc/letsencrypt/live/${host}/fullchain.pem"
    local key="/etc/letsencrypt/live/${host}/privkey.pem"
    [[ -f "$cert" && -f "$key" ]] || die "Expected cert/key not found for ${host} under /etc/letsencrypt/live"
    local conf="${TENANT_DIR}/${host}.conf"
    log "Writing HTTPS vhost for ${host} (upstream: ${upstream}) -> ${conf}"
    render_template "$HTTPS_TPL" "$conf" "$host" "$upstream"
    test_and_reload
    log "HTTPS vhost active for ${host}"
  }
  cmd_disable() {
    local host="$1"; validate_host "$host"; require_dir "$TENANT_DIR"
    local conf="${TENANT_DIR}/${host}.conf"; local dis="${TENANT_DIR}/${host}.conf.disabled"
    if [[ -f "$conf" ]]; then mv -f "$conf" "$dis"; log "Disabled ${host}"; test_and_reload
    else log "No active config for ${host} (nothing to disable)"; fi
  }
  cmd_delete() {
    local host="$1"; validate_host "$host"; require_dir "$TENANT_DIR"
    local conf="${TENANT_DIR}/${host}.conf"; local dis="${TENANT_DIR}/${host}.conf.disabled"
    rm -f "$conf" "$dis"; log "Deleted config for ${host} (if any)"; test_and_reload
  }
  cmd_status() {
    local host="$1"; validate_host "$host"; require_dir "$TENANT_DIR"
    local conf="${TENANT_DIR}/${host}.conf"; local dis="${TENANT_DIR}/${host}.conf.disabled"
    if   [[ -f "$conf" ]]; then echo "enabled"
    elif [[ -f "$dis" ]]; then echo "disabled"
    else echo "absent"; fi
  }

  [[ $# -ge 1 ]] || usage
  cmd="$1"; shift || true
  case "$cmd" in
    write_http_stub) [[ $# -eq 1 ]] || usage; cmd_write_http_stub "$1" ;;
    issue_cert)      [[ $# -eq 2 ]] || usage; cmd_issue_cert "$1" "$2" ;;
    write_https)     [[ $# -eq 2 ]] || usage; cmd_write_https "$1" "$2" ;;
    disable)         [[ $# -eq 1 ]] || usage; cmd_disable "$1" ;;
    delete)          [[ $# -eq 1 ]] || usage; cmd_delete "$1" ;;
    status)          [[ $# -eq 1 ]] || usage; cmd_status "$1" ;;
    *) usage ;;
  esac

Permissions & sudoers:

  # script permissions
  sudo chown root:root /usr/local/bin/tenant_provision
  sudo chmod 750 /usr/local/bin/tenant_provision

  # sudoers rule (proxy host)
  sudo tee /etc/sudoers.d/whitelabelbot >/dev/null <<'EOF'
  whitelabelbot ALL=(root) NOPASSWD: /usr/local/bin/tenant_provision
  EOF
  sudo visudo -cf /etc/sudoers.d/whitelabelbot  # must say "parsed OK"

**6 Smoke test (manual)**

On the proxy:

  HOST="testbrand.obcbackup.com"
  EMAIL="[email protected]"

  sudo /usr/local/bin/tenant_provision write_http_stub "$HOST"
  sudo /usr/local/bin/tenant_provision issue_cert "$HOST" "$EMAIL"
  sudo /usr/local/bin/tenant_provision write_https "$HOST" "http://obc_servers"
  sudo nginx -t && sudo systemctl reload nginx
  curl -I https://$HOST


From WHMCS (to prove Secure Shell path works end-to-end):

  sudo -u www-data -H ssh \
    -i /var/lib/eazybackup/keys/whitelabel_ed25519 \
    -o UserKnownHostsFile=/var/lib/eazybackup/keys/known_hosts \
    -o StrictHostKeyChecking=yes \
    whitelabelbot@proxy1.eazybackup.internal \
    "sudo /usr/local/bin/tenant_provision status $HOST"

**7 eazyBackup module config (Ops → Nginx)**

Ops Secure Shell Host: `proxy1.eazybackup.internal`

Ops Secure Shell User: `whitelabelbot`

Ops Secure Shell Key Path: `/var/lib/eazybackup/keys/whitelabel_ed25519`

Ops sudo Script: `/usr/local/bin/tenant_provision`

**8 Troubleshooting quick hits**

“Could not automatically find a matching server block” during Certbot
→ Make sure /etc/nginx/conf.d/000-tenants-include.conf exists and includes tenants/*.conf; reload Nginx.

Deprecated listen … http2
→ Use listen 443 ssl; and http2 on; (the provided HTTPS template already does this).

“no ssl_certificate is defined for the listen … ssl” on nginx -t
→ You flipped to HTTPS before cert issuance. Use the two-stage flow (stub → cert → https).

“conflicting server name … on 0.0.0.0:80, ignored”
→ Duplicate server_name in multiple HTTP blocks. Clean up old files; not fatal but tidy is better.

Secure Shell prompts for password
→ Check /etc/sudoers.d/whitelabelbot on the proxy; ensure the rule allows tenant_provision NOPASSWD.

Permission denied writing known_hosts
→ Create /var/lib/eazybackup/keys/known_hosts owned by www-data, or add -o UserKnownHostsFile=… to your test commands (your production code may use phpseclib and not need it).

**9 Maintenance**

Certbot installs a systemd timer for renewals; the HTTPS template points to /etc/letsencrypt/live/<host>/*, so renewals are seamless.

Logs per tenant: /var/log/nginx/<fqdn>_access.log and _error.log.

Rollback:

sudo /usr/local/bin/tenant_provision disable <fqdn>   # soft off
sudo /usr/local/bin/tenant_provision delete <fqdn>    # remove config