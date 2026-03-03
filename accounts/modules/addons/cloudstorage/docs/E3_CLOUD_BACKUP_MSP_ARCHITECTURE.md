# e3 Cloud Backup MSP Architecture

## Overview

The e3 Cloud Backup system supports a multi-tenant architecture designed for Managed Service Providers (MSPs) and direct end-users. This document describes the overall architecture, user hierarchy, data isolation model, and the implementation progress across development phases.

**Implementation Status:**
- ✅ Phase 1: Database & Backend Foundation (Complete)
- ✅ Phase 2: WHMCS Client Area - MSP View (Complete)
- ✅ Phase 3: Agent Enrollment Flow (Complete)
- ✅ Jobs Management: MSP-Aware Job List, Creation & Actions (Complete)
- ✅ Phase 4: Tenant Portal (Complete)
- ✅ Phase 5: Billing Integration (Canonical tenant + Stripe Connect complete)

## User Hierarchy Model

The system supports four levels of users:

```
Level 0: EazyBackup (WHMCS Admin)
│
Level 1: MSP / Direct Customer (WHMCS Client)
│   └── "TechCorp MSP" - client_id: 42
│       │
│       │   Level 2: Tenant (MSP's Customer / Company)
│       ├── "Acme Corp" - tenant_id: 1
│       │   │
│       │   │   Level 3: Tenant User (Employee)
│       │   ├── "John Smith" - user_id: 1
│       │   │   └── Device: LAPTOP-JOHN
│       │   │   └── Device: DESKTOP-JOHN
│       │   │
│       │   ├── "Jane Doe" - user_id: 2
│       │   │   └── Device: LAPTOP-JANE
│       │   │
│       │   └── [Shared/Unassigned]
│       │       └── Device: FILE-SERVER
│       │
│       ├── "Beta LLC" - tenant_id: 2
│       │   └── "Bob Owner" - user_id: 3
│       │       └── Device: BOB-PC
│       │
│       └── [Direct/Unassigned Devices]
│           └── Device: MSP-INTERNAL-SERVER
│
Level 1: Simple End User (WHMCS Client, no tenants)
    └── "Home User Joe" - client_id: 99
        └── Device: HOME-PC
        └── Device: KIDS-LAPTOP
```

## Access & Isolation Matrix

| Actor | Can See | Can Restore | Portal |
|-------|---------|-------------|--------|
| **EazyBackup Admin** | Everything | Everything | WHMCS Admin |
| **MSP** | All their tenants, users, devices | All their data | WHMCS Client Area |
| **Tenant Admin** | All users/devices in their company | Company data | Tenant Portal |
| **Tenant User** | Only their own device(s) | Only their data | Tenant Portal (limited) |
| **Simple End User** | All their devices | All their data | WHMCS Client Area |

## Data Isolation

Each tenant is provisioned as a separate Ceph RGW user, providing credential-level isolation:

```
MSP Storage Account (Ceph User: msp-techcorp)
├── acme-corp-bucket/           ← Tenant: Acme Corp (Ceph User: tenant-acme-corp)
│   ├── john-smith/             ← User: John
│   │   └── LAPTOP-JOHN/
│   └── jane-doe/
│       └── LAPTOP-JANE/
├── beta-llc-bucket/            ← Tenant: Beta LLC (Ceph User: tenant-beta-llc)
└── msp-internal-bucket/        ← MSP's own backups
```

## MSP Detection

MSPs are identified via WHMCS client groups. The addon setting `msp_client_groups` stores a comma-separated list of group IDs. Clients belonging to these groups are treated as MSPs with additional capabilities:

- Create and manage tenants
- Generate tenant-scoped enrollment tokens
- Configure custom portal branding and domains
- View aggregated usage and billing data

## Tenant Portal Architecture

The tenant portal uses a hybrid entry point approach:

### Access Methods

| Access Method | URL | Use Case |
|--------------|-----|----------|
| Default (no custom domain) | `accounts.eazybackup.ca/portal/` | End users, testing |
| MSP-specific (no custom domain) | `accounts.eazybackup.ca/portal/?msp=slug` | MSPs without custom domains |
| Custom domain | `backup.acmemsp.com/` | Full white-label |

### Custom Domain Flow

```
MSP Customer visits: backup.acmemsp.com
                           │
                           ▼
DNS CNAME: backup.acmemsp.com → portal.eazybackup.ca
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                     /portal/index.php                        │
│                                                              │
│  1. Detect $_SERVER['HTTP_HOST']                            │
│  2. Lookup in s3_msp_portal_domains table                   │
│  3. If found → Load MSP branding (logo, colors)             │
│     If not found → Use default eazyBackup branding          │
│  4. Route to appropriate tenant portal page                 │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
         Tenant Portal (with MSP branding)
```

## Agent Enrollment Flow

Agents can be enrolled via two methods:

### 1. Email/Password Login (Simple End Users)
1. User downloads and installs agent
2. Agent prompts for email/password
3. Agent calls `agent_login.php` with credentials
4. API validates against WHMCS, creates agent record
5. Agent receives permanent token and starts operation

### 2. Enrollment Token (MSPs and Tenant Admins)
1. MSP/Tenant Admin generates enrollment token in portal
2. Token is scoped to client or tenant
3. Agent is installed with token via CLI: `e3-backup-setup.exe /S /TOKEN=ENR-xxx`
4. Agent calls `agent_enroll.php` with token
5. API validates token, creates agent record with proper scoping
6. Agent receives permanent credentials

## Billing Model

| Item | Price (CAD) | Tracking |
|------|-------------|----------|
| Base fee per tenant | $9.45/month | Includes 1TB storage |
| Additional storage | $9.45/TB/month | Per-tenant Ceph usage |
| Regular agent (sync/kopia) | $2.50/agent/month | Agent count by type |
| Disk Image agent | $3.50/agent/month | Jobs with `engine=disk_image` |
| VM backup (Hyper-V/Proxmox/VMware) | $3.50/VM/month | Job count by engine type |

Usage is tracked via the `s3_backup_usage_snapshots` table with monthly snapshots.

## Canonical Tenant + Stripe Connect Billing

The MSP billing architecture now uses canonical tenants from Partner Hub and links cloud storage users through `eb_tenant_storage_links`.

Key release expectations:
- Signup approvals are MSP-reviewed before provisioning.
- Tenant-scoped portal routes support Billing, Services, and Cloud Storage (including default-domain `portal/?msp=` access).
- Stripe is the authoritative source of invoice state, hosted invoice links, and payment status synchronization.

This document should be read together with Partner Hub billing docs and the release gate script to validate deploy readiness.

### Tenant v2 Rollout Notes

Tenant v2 rollout must preserve canonical tenant ownership while keeping portal and billing behavior backward compatible.

#### Migration Checklist
- [ ] **TODO: Canonical table ownership** - treat `s3_backup_tenants` as the canonical tenant profile table owned by Cloud Storage; Partner Hub should link via canonical mapping, not fork ownership.
- [ ] **TODO: Hidden infra fields** - keep `product_id`, `server_id`, and `servergroup_id` internal-only (not exposed in tenant-facing UI/API payloads).
- [ ] **TODO: Legacy route compatibility policy** - keep existing portal compatibility (`portal/?msp=` and current tenant entry patterns) during Tenant v2 rollout; phase removals only after redirects and operator notice.
- [ ] Run `php accounts/modules/addons/eazybackup/bin/dev/msp_billing_release_gate.php` before/after rollout edits and verify `MSP_BILLING_RELEASE_GATE_PASS`.

#### Task 1 Verification Log
- Pre-edit gate run: `MSP_BILLING_RELEASE_GATE_PASS`
- Post-edit gate run: `MSP_BILLING_RELEASE_GATE_PASS`

#### Task 7 Compatibility Wrappers (Legacy e3 Tenant URLs)

Legacy e3 tenant routes remain available as compatibility entry points, but canonical tenant management is now Partner Hub-only.

- Legacy route wrapper: `index.php?m=cloudstorage&page=e3backup&view=tenants` redirects to `index.php?m=eazybackup&a=ph-tenants-manage`.
- Legacy deep-link wrappers map existing bookmarked paths to canonical pages:
  - `view=tenant_detail&tenant_id={id}` -> `a=ph-tenant&id={id}`
  - `view=tenant_members&tenant_id={id}` (and `tenant_users`) -> `a=ph-tenant-members&id={id}`
  - `view=tenant_detail&mode=create` -> `a=ph-tenants-manage`
- Legacy tenant detail/member templates include in-page compatibility callouts and Partner Hub deep-link actions.
- Create/edit/delete/write actions on legacy tenant pages now redirect to Partner Hub tenant pages instead of executing legacy write APIs.

---

# Phase 1: Database & Backend Foundation

## Completed Work

Phase 1 establishes the database schema and configuration settings for the multi-tenant backup system.

### 1. MSP Client Groups Configuration

Added `msp_client_groups` setting to `cloudstorage_config()` with a dual-pane UI for selecting which WHMCS client groups should be classified as MSPs.

**Location**: `cloudstorage.php` in the config fields array

**Usage**: WHMCS Admin → Addons → Cloud Storage → Configure → MSP Client Groups

### 2. New Database Tables

#### `s3_backup_tenants`
Stores MSP customer (tenant) definitions with profile/billing information.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key |
| `client_id` | INT UNSIGNED | MSP's WHMCS client_id |
| `name` | VARCHAR(255) | Display name (e.g., "Acme Corp") |
| `slug` | VARCHAR(100) | URL-safe identifier (e.g., "acme-corp") |
| `contact_email` | VARCHAR(255) | Primary billing/contact email |
| `contact_name` | VARCHAR(255) | Primary contact name |
| `contact_phone` | VARCHAR(50) | Contact phone number |
| `address_line1` | VARCHAR(255) | Billing address line 1 |
| `address_line2` | VARCHAR(255) | Billing address line 2 |
| `city` | VARCHAR(100) | Billing city |
| `state` | VARCHAR(100) | Billing state/province |
| `postal_code` | VARCHAR(20) | Billing postal/ZIP code |
| `country` | VARCHAR(2) | ISO 3166-1 alpha-2 country code |
| `stripe_customer_id` | VARCHAR(255) | Stripe Connect customer ID (billing) |
| `ceph_uid` | VARCHAR(191) | Ceph RGW user ID |
| `bucket_name` | VARCHAR(255) | Dedicated bucket (optional) |
| `storage_quota_bytes` | BIGINT UNSIGNED | Optional quota |
| `status` | ENUM | 'active', 'suspended', 'deleted' |
| `branding_json` | TEXT | Logo, colors, support info |
| `created_at` | TIMESTAMP | Creation timestamp |
| `updated_at` | TIMESTAMP | Last update timestamp |

**Indexes**: `client_id`, `ceph_uid`, `status`, UNIQUE(`client_id`, `slug`)

#### `s3_backup_tenant_users`
Stores users within tenants (employees who can access the tenant portal).

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key |
| `tenant_id` | INT UNSIGNED | FK to s3_backup_tenants |
| `email` | VARCHAR(255) | Login email |
| `password_hash` | VARCHAR(255) | Bcrypt hash |
| `name` | VARCHAR(255) | Display name |
| `role` | ENUM | 'admin' (sees all company devices) or 'user' (own devices only) |
| `status` | ENUM | 'active', 'disabled' |
| `password_reset_token` | VARCHAR(64) | Password reset token |
| `password_reset_expires` | DATETIME | Token expiry |
| `last_login_at` | DATETIME | Last login timestamp |
| `created_at` | TIMESTAMP | Creation timestamp |
| `updated_at` | TIMESTAMP | Last update timestamp |

**Indexes**: `tenant_id`, `email`, UNIQUE(`tenant_id`, `email`)

#### `s3_agent_enrollment_tokens`
Stores tokens for agent enrollment.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key |
| `client_id` | INT UNSIGNED | MSP/client who owns the token |
| `tenant_id` | INT UNSIGNED | Scoped to tenant (NULL = direct client) |
| `token` | VARCHAR(64) | Token value (e.g., "ENR-a8f3b2c1") |
| `description` | VARCHAR(255) | Label (e.g., "December rollout") |
| `max_uses` | INT UNSIGNED | NULL = unlimited |
| `use_count` | INT UNSIGNED | Current usage count |
| `expires_at` | DATETIME | NULL = never expires |
| `revoked_at` | DATETIME | NULL if active |
| `created_at` | TIMESTAMP | Creation timestamp |

**Indexes**: UNIQUE(`token`), `client_id`, `tenant_id`, `expires_at`

#### `s3_backup_usage_snapshots`
Stores monthly usage data for billing.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key |
| `client_id` | INT UNSIGNED | WHMCS client_id |
| `tenant_id` | INT UNSIGNED | Tenant ID (NULL = client-level) |
| `period_start` | DATE | First of month |
| `period_end` | DATE | Last of month |
| `storage_bytes` | BIGINT UNSIGNED | Total storage used |
| `agent_count` | INT UNSIGNED | Regular agent count |
| `disk_image_agent_count` | INT UNSIGNED | Disk image agent count |
| `vm_count` | INT UNSIGNED | VM backup count |
| `calculated_at` | DATETIME | Calculation timestamp |
| `created_at` | TIMESTAMP | Creation timestamp |

**Indexes**: (`client_id`, `period_start`), (`tenant_id`, `period_start`)

#### `s3_msp_portal_domains`
Stores custom domain mappings for white-labeled tenant portals.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED | Primary key |
| `client_id` | INT UNSIGNED | MSP's WHMCS client_id |
| `domain` | VARCHAR(255) | Custom domain (e.g., "backup.acmemsp.com") |
| `is_primary` | TINYINT | Primary domain flag |
| `is_verified` | TINYINT | DNS verification status |
| `branding_json` | TEXT | Branding overrides |
| `created_at` | TIMESTAMP | Creation timestamp |
| `updated_at` | TIMESTAMP | Last update timestamp |

**Indexes**: UNIQUE(`domain`), `client_id`

### 3. Extended Agent Table

Added new columns to `s3_cloudbackup_agents`:

| Column | Type | Description |
|--------|------|-------------|
| `tenant_id` | INT UNSIGNED | Scopes agent to a tenant |
| `tenant_user_id` | INT UNSIGNED | Scopes agent to a specific user |
| `agent_type` | ENUM | 'workstation', 'server', 'hypervisor' |
| `enrollment_token_id` | INT UNSIGNED | Token used for enrollment |

### 4. MSP Detection Hook

**File**: `accounts/includes/hooks/msp_client_flag.php`

Exposes `$isMspClient` boolean to all client area templates. Checks if the logged-in client belongs to a group listed in `msp_client_groups` setting.

**Usage in templates**:
```smarty
{if $isMspClient}
    <!-- Show MSP-specific features -->
{/if}
```

### 5. MSP Helper Library

**File**: `accounts/modules/addons/cloudstorage/lib/Client/MspController.php`

Provides static helper methods:

```php
// Check if a client is an MSP
MspController::isMspClient(int $clientId): bool

// Get all tenants for an MSP
MspController::getTenants(int $clientId): array

// Get a specific tenant (with ownership check)
MspController::getTenant(int $tenantId, int $clientId): ?object
```

## Files Changed/Created

| File | Action |
|------|--------|
| `accounts/modules/addons/cloudstorage/cloudstorage.php` | Modified |
| `accounts/includes/hooks/msp_client_flag.php` | Created |
| `accounts/modules/addons/cloudstorage/lib/Client/MspController.php` | Created |

## Activation/Migration

To apply the schema changes:

1. Navigate to WHMCS Admin → Addons → Cloud Storage
2. Click "Deactivate" (settings are preserved)
3. Click "Activate"
4. Verify tables are created in the database

---

# Phase 2: WHMCS Client Area (MSP View)

## Completed Work

Phase 2 implements the WHMCS client area interface for managing e3 Cloud Backup, with full MSP support including tenant management, user management, and enrollment token generation.

### 1. Navigation Integration

Added **e3 Cloud Backup** dropdown menu to `header.tpl` with conditional MSP-only items:

```
e3 Cloud Backup
├── Dashboard           (All users)
├── Agents              (All users)
├── Enrollment Tokens   (All users)
├── Tenants             (MSP only - via {if $isMspClient})
└── Tenant Users        (MSP only - via {if $isMspClient})
```

**Location**: `accounts/templates/eazyBackup/header.tpl`

The menu uses Alpine.js for expand/collapse behavior and Smarty conditionals for MSP detection.

### 2. Routing Configuration

Added new page routing in `cloudstorage.php` under `case 'e3backup':`:

| View Parameter | Page Controller | Template |
|----------------|-----------------|----------|
| `dashboard` (default) | `e3backup_dashboard.php` | `e3backup_dashboard.tpl` |
| `agents` | `e3backup_agents.php` | `e3backup_agents.tpl` |
| `tokens` | `e3backup_tokens.php` | `e3backup_tokens.tpl` |
| `tenants` | `e3backup_tenants.php` | `e3backup_tenants.tpl` |
| `tenant_users` | `e3backup_tenant_users.php` | `e3backup_tenant_users.tpl` |

**URL Pattern**: `index.php?m=cloudstorage&page=e3backup&view={view}`

### 3. Page Controllers

All page controllers follow a consistent pattern:
1. Authenticate user via `ClientArea`
2. Verify product ownership via `DBController::getProduct()`
3. Check MSP status via `MspController::isMspClient()`
4. Fetch required data and return view variables

#### Dashboard (`pages/e3backup_dashboard.php`)

Returns aggregated statistics:
- `agentCount` - Active agents for the client
- `tokenCount` - Valid enrollment tokens
- `tenantCount` - Active tenants (MSP only)
- `tenantUserCount` - Active tenant users (MSP only)

#### Agents (`pages/e3backup_agents.php`)

Returns:
- `isMspClient` - Boolean for conditional UI
- `tenants` - List of tenants for filter dropdown (MSP only)

#### Tokens (`pages/e3backup_tokens.php`)

Returns:
- `isMspClient` - Boolean for tenant scoping option
- `tenants` - List of tenants for scope dropdown (MSP only)

#### Tenants (`pages/e3backup_tenants.php`)

**Access Control**: Redirects non-MSPs to dashboard.

Returns:
- `isMspClient` - Always true (or redirected)

#### Tenant Users (`pages/e3backup_tenant_users.php`)

**Access Control**: Redirects non-MSPs to dashboard.

Returns:
- `isMspClient` - Always true (or redirected)
- `tenants` - List of tenants for filter dropdown

### 4. Templates

All templates use the established dark slate theme with:
- Gradient backgrounds (`bg-slate-950`, radial gradient overlay)
- Card-based layouts with hover effects
- Alpine.js for interactivity (modals, forms, async data loading)
- Responsive grid layouts

#### Dashboard Template (`templates/e3backup_dashboard.tpl`)

```
┌─────────────────────────────────────────────────────────────────┐
│  e3 Cloud Backup                              [Manage Agents]   │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌──────┐│
│  │ Active       │  │ Active       │  │ Tenants      │  │Tenant││
│  │ Agents: 12   │  │ Tokens: 5    │  │ (MSP): 8     │  │Users ││
│  └──────────────┘  └──────────────┘  └──────────────┘  └──────┘│
├─────────────────────────────────────────────────────────────────┤
│  Quick Actions                                                   │
│  ┌────────────────────┐ ┌────────────────────┐ ┌──────────────┐ │
│  │ Generate Token     │ │ Download Agent     │ │ Create Tenant│ │
│  └────────────────────┘ └────────────────────┘ └──────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

#### Agents Template (`templates/e3backup_agents.tpl`)

Features:
- Tenant filter dropdown (MSP only)
- Agent table with status badges
- Enable/Disable toggle
- Revoke token action
- Agent type display (workstation/server/hypervisor)

```javascript
// Alpine.js app structure
agentsApp() {
    agents: [],
    loading: true,
    tenantFilter: '',
    loadAgents(),
    toggleAgent(agent),
    revokeAgent(agent)
}
```

#### Tokens Template (`templates/e3backup_tokens.tpl`)

Features:
- Token list with copy-to-clipboard
- Create token modal with:
  - Description (optional)
  - Tenant scope (MSP only)
  - Max uses (0 = unlimited)
  - Expiration (24h, 7d, 30d, 90d, 1y, or never)
- Install command modal showing silent install syntax
- Revoke token action

**Silent Install Command Format**:
```
e3-backup-agent.exe /S /TOKEN=<40-char-hex-token>
```

#### Tenants Template (`templates/e3backup_tenants.tpl`)

Features:
- Card grid layout showing tenant stats with contact info preview
- Enhanced Create/Edit modal with sections:
  - **Organization**: Company name, slug (auto-generated, immutable), status
  - **Contact Information**: Email ✱, name ✱, phone
  - **Billing Address**: Collapsible section with full address fields
  - **Portal Admin Account** (create only): Create admin user with auto-generated or manual password
- Delete confirmation modal with cascade warning
- Quick links to tenant users
- Success notifications with admin creation and welcome email status

```
┌─────────────────────────────────────────┐
│  Acme Corp                     [active] │
│  acme-corp                              │
│  Jane Smith • billing@acme.com          │
│  ┌─────────┐ ┌─────────┐                │
│  │Users: 5 │ │Agents: 12│               │
│  └─────────┘ └─────────┘                │
│  [Edit] [Users] [🗑]                     │
└─────────────────────────────────────────┘
```

#### Tenant Users Template (`templates/e3backup_tenant_users.tpl`)

Features:
- Tenant filter dropdown
- User table with role badges (admin/user)
- Create/Edit modal with:
  - Tenant selection (required, locked on edit)
  - Name, Email (required)
  - Password (required on create)
  - Role (user/admin)
  - Status (active/disabled)
- Password reset modal
- Delete action

### 5. API Endpoints

All API endpoints follow a consistent pattern:
1. Initialize WHMCS via `init.php`
2. Authenticate session via `ClientArea`
3. Check MSP access where required via `MspController::isMspClient()`
4. Validate ownership before CRUD operations
5. Return JSON response via Symfony `JsonResponse`

#### Tenant APIs

| Endpoint | Method | Description |
|----------|--------|-------------|
| `e3backup_tenant_list.php` | GET | List tenants with user/agent counts |
| `e3backup_tenant_create.php` | POST | Create tenant with Ceph user placeholder |
| `e3backup_tenant_update.php` | POST | Update tenant name/status |
| `e3backup_tenant_delete.php` | POST | Soft delete with cascade (users disabled, agents unassigned) |

**Create Tenant Request** (with admin user):
```
POST /api/e3backup_tenant_create.php
name=Acme%20Corp
&slug=acme-corp
&status=active
&contact_email=billing@acme.com
&contact_name=Jane%20Smith
&contact_phone=+1-555-123-4567
&address_line1=123%20Main%20St
&city=Toronto
&state=Ontario
&postal_code=M5V%201A1
&country=CA
&create_admin=1
&admin_email=jane@acme.com
&admin_name=Jane%20Smith
&auto_password=1
```

**Create Tenant Response** (with admin created):
```json
{
    "status": "success",
    "tenant_id": 42,
    "message": "Tenant created successfully",
    "admin_created": true,
    "welcome_email_sent": true
}
```

### Tenant Onboarding Flow

The enhanced tenant creation process allows MSPs to fully onboard a tenant in a single step, including creating a portal admin user.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        MSP Creates New Tenant                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Step 1: Organization Info                                                   │
│  ├── Company Name ✱                                                          │
│  ├── Slug (auto-generated)                                                   │
│  └── Status (active/suspended)                                               │
│                                                                              │
│  Step 2: Contact Information                                                 │
│  ├── Contact Email ✱ (used for billing/notifications)                       │
│  ├── Contact Name ✱                                                          │
│  └── Phone (optional)                                                        │
│                                                                              │
│  Step 3: Billing Address (optional, collapsible)                             │
│  ├── Address Line 1, Line 2                                                  │
│  ├── City, State/Province                                                    │
│  ├── Postal Code                                                             │
│  └── Country (ISO 2-letter code)                                             │
│                                                                              │
│  Step 4: Portal Admin Account (optional)                                     │
│  ├── ☑️ Create portal admin user                                             │
│  ├── Admin Email (defaults to contact email)                                 │
│  ├── Admin Name (defaults to contact name)                                   │
│  └── Password:                                                               │
│      ○ Auto-generate & email to user                                         │
│      ○ Set password manually                                                 │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Backend Processing                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  1. Validate all required fields                                             │
│  2. Check slug uniqueness                                                    │
│  3. Create s3_backup_tenants record with all profile fields                  │
│  4. Generate Ceph UID                                                        │
│  5. If create_admin=1:                                                       │
│     a. Generate password (if auto)                                           │
│     b. Create s3_backup_tenant_users record (role=admin)                     │
│     c. Send welcome email with portal URL + credentials                      │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        Welcome Email Sent                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  Subject: Your backup portal account is ready - Acme Corp                    │
│                                                                              │
│  Hi Jane,                                                                    │
│                                                                              │
│  Your organization "Acme Corp" has been set up with cloud backup.            │
│                                                                              │
│  Portal URL: https://accounts.eazybackup.ca/portal/?msp=acme-corp            │
│  Email: jane@acme.com                                                        │
│  Temporary Password: a1b2c3d4e5f6g7h8                                        │
│                                                                              │
│  Please change your password after first login.                              │
│                                                                              │
│  Best regards,                                                               │
│  TechCorp MSP                                                                │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
                    Tenant can immediately access portal!
```

**Benefits of Enhanced Onboarding:**
- Single-step tenant + admin user creation
- No separate user creation needed
- Tenant receives portal access immediately
- Profile data ready for future Stripe Connect billing
- All contact info captured for notifications

### Tenant Portal Email Templates

Tenant portal emails use WHMCS email templates for admin customization. Templates are created automatically during module activation.

#### Available Templates

| Template Name | Purpose | Merge Fields |
|--------------|---------|--------------|
| `Tenant Portal Welcome` | New tenant admin account | `{$tenant_name}`, `{$admin_name}`, `{$admin_email}`, `{$portal_url}`, `{$temp_password}`, `{$msp_name}` |
| `Tenant Portal Password Reset` | Password reset request | `{$user_name}`, `{$reset_url}`, `{$tenant_name}`, `{$company_name}` |

#### Admin Configuration

**Location**: WHMCS Admin → Setup → Email Templates → General Messages

**Addon Settings** (Configure module):
- `tenant_welcome_email_template` - Select template for welcome emails
- `tenant_password_reset_email_template` - Select template for password reset

#### Implementation

The `TenantEmailService` class handles email sending:

```php
// lib/Client/TenantEmailService.php

// Send welcome email
TenantEmailService::sendWelcomeEmail(
    $adminEmail,
    $adminName,
    $tenantName,
    $tenantSlug,
    $password,
    $mspClientId
);

// Send password reset email
TenantEmailService::sendPasswordResetEmail(
    $userEmail,
    $userName,
    $resetUrl,
    $tenantName
);
```

**Fallback Behavior:**
- If template not configured, emails are sent using inline HTML via PHPMailer/mail()
- Ensures emails are always delivered even without template configuration

#### Tenant User APIs

| Endpoint | Method | Description |
|----------|--------|-------------|
| `e3backup_tenant_user_list.php` | GET | List users, filterable by tenant_id |
| `e3backup_tenant_user_create.php` | POST | Create user with bcrypt password |
| `e3backup_tenant_user_update.php` | POST | Update user (not password) |
| `e3backup_tenant_user_delete.php` | POST | Delete user, unassign agents |
| `e3backup_tenant_user_reset_password.php` | POST | Reset user password |

**User Roles**:
- `user` - Can only see their own devices
- `admin` - Can see all devices in the tenant, manage users

#### Enrollment Token APIs

| Endpoint | Method | Description |
|----------|--------|-------------|
| `e3backup_token_list.php` | GET | List tokens with computed validity |
| `e3backup_token_create.php` | POST | Create token with optional expiry/scope |
| `e3backup_token_revoke.php` | POST | Revoke token (set revoked_at) |

**Token Validity Logic** (computed in list API):
```php
$expired = $token->expires_at && new DateTime($token->expires_at) < $now;
$maxedOut = $token->max_uses > 0 && $token->use_count >= $token->max_uses;
$token->is_valid = !$token->revoked_at && !$expired && !$maxedOut;
```

**Create Token Request**:
```
POST /api/e3backup_token_create.php
description=December%20rollout&tenant_id=42&max_uses=50&expires_in=30d
```

**Create Token Response**:
```json
{
    "status": "success",
    "token_id": 123,
    "token": "a8f3b2c1d4e5f6789012345678901234567890ab",
    "message": "Token created successfully"
}
```

#### Agent APIs

| Endpoint | Method | Description |
|----------|--------|-------------|
| `e3backup_agent_list.php` | GET | List agents with tenant info, filterable |
| `e3backup_agent_toggle.php` | POST | Enable/disable agent |

**Agent Filter Options** (MSP only):
- `tenant_id=` - All agents
- `tenant_id=direct` - Agents without tenant
- `tenant_id=42` - Agents in specific tenant

### 6. UI/UX Design Patterns

#### Color Scheme

| Element | Color | Tailwind Class |
|---------|-------|----------------|
| Background | Slate 950 | `bg-slate-950` |
| Cards | Slate 900 | `bg-slate-900/70` |
| Borders | Slate 800 | `border-slate-800` |
| Primary Action | Sky 600 | `bg-sky-600` |
| Success/Tokens | Emerald 600 | `bg-emerald-600` |
| MSP/Tenants | Violet 600 | `bg-violet-600` |
| Users | Amber 600 | `bg-amber-600` |
| Danger | Rose 600 | `bg-rose-600` |

#### Status Badges

```html
<!-- Active -->
<span class="bg-emerald-500/15 text-emerald-200">active</span>

<!-- Disabled/Suspended -->
<span class="bg-slate-700 text-slate-300">disabled</span>

<!-- Revoked/Expired -->
<span class="bg-rose-500/15 text-rose-200">revoked</span>
```

#### Modal Pattern

All modals use consistent structure:
```html
<div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
    <div class="w-full max-w-md rounded-xl border border-slate-700 bg-slate-900">
        <div class="border-b border-slate-700 px-6 py-4"><!-- Header --></div>
        <form class="p-6 space-y-4"><!-- Content --></form>
    </div>
</div>
```

## Files Created/Modified

| File | Action | Description |
|------|--------|-------------|
| `accounts/templates/eazyBackup/header.tpl` | Modified | Added e3 Cloud Backup nav |
| `accounts/modules/addons/cloudstorage/cloudstorage.php` | Modified | Added e3backup routing |
| `pages/e3backup_dashboard.php` | Created | Dashboard controller |
| `pages/e3backup_agents.php` | Created | Agents controller |
| `pages/e3backup_tokens.php` | Created | Tokens controller |
| `pages/e3backup_tenants.php` | Created | Tenants controller (MSP) |
| `pages/e3backup_tenant_users.php` | Created | Tenant users controller (MSP) |
| `templates/e3backup_dashboard.tpl` | Created | Dashboard template |
| `templates/e3backup_agents.tpl` | Created | Agents template |
| `templates/e3backup_tokens.tpl` | Created | Tokens template |
| `templates/e3backup_tenants.tpl` | Created | Tenants template |
| `templates/e3backup_tenant_users.tpl` | Created | Tenant users template |
| `api/e3backup_tenant_list.php` | Created | Tenant list API |
| `api/e3backup_tenant_create.php` | Created | Tenant create API |
| `api/e3backup_tenant_update.php` | Created | Tenant update API |
| `api/e3backup_tenant_delete.php` | Created | Tenant delete API |
| `api/e3backup_tenant_user_list.php` | Created | User list API |
| `api/e3backup_tenant_user_create.php` | Created | User create API |
| `api/e3backup_tenant_user_update.php` | Created | User update API |
| `api/e3backup_tenant_user_delete.php` | Created | User delete API |
| `api/e3backup_tenant_user_reset_password.php` | Created | Password reset API |
| `api/e3backup_token_list.php` | Created | Token list API |
| `api/e3backup_token_create.php` | Created | Token create API |
| `api/e3backup_token_revoke.php` | Created | Token revoke API |
| `api/e3backup_agent_list.php` | Created | Agent list API |
| `api/e3backup_agent_toggle.php` | Created | Agent toggle API |

---

# Phase 3: Agent Enrollment Flow

## Completed Work

Phase 3 delivers first-run agent enrollment via two paths: token-based (MSP/RMM) and email/password (simple users). Agents persist issued credentials and clear enrollment secrets after success.

### APIs

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `api/agent_enroll.php` | POST | Token-based enrollment, increments token use_count, scopes to token tenant/client |
| `api/agent_login.php` | POST | Email/password enrollment for simple users (no tenant scoping) |

**Token Validation Rules**
- `revoked_at` must be NULL
- `expires_at` NULL or > NOW()
- `max_uses` = 0 (unlimited) or `use_count` < `max_uses`
- `use_count` incremented atomically with row lock

**Agent Creation**
- `s3_cloudbackup_agents`: `client_id` from token/login, `tenant_id` from token (nullable), `hostname`, `agent_type='workstation'`, `enrollment_token_id` (token flow)

### Agent (Go) Changes

| File | Change |
|------|--------|
| `internal/agent/config.go` | Added enrollment fields (`EnrollmentToken`, `EnrollEmail`, `EnrollPassword`) and `Save()` helper |
| `internal/agent/api_client.go` | Added `EnrollWithToken` and `EnrollWithCredentials` HTTP helpers |
| `internal/agent/runner.go` | Added `enrollIfNeeded()` before polling; persists credentials and rebuilds client |
| `cmd/agent/main.go` | Passes config path into `NewRunner` for persistence |

**Enrollment Flow (Agent)**
1. On startup, if `AgentID/AgentToken` missing, attempt token enrollment else email/password.
2. On success, write `AgentID`, `AgentToken`, `ClientID` to `agent.conf`; clear enrollment fields.
3. Rebuild client with permanent credentials; continue normal polling.

**Silent Install Inputs**
- Token: `agent.conf` with `api_base_url`, `enrollment_token`
- Email/Password: `agent.conf` with `api_base_url`, `enroll_email`, `enroll_password`
On first start, config is rewritten with permanent credentials.

---

# Jobs Management (MSP-Aware)

## Overview

The e3 Cloud Backup Jobs page provides MSP-aware job management within the WHMCS client area. MSPs can view, filter, create, and manage backup jobs across all their tenants, while simple end-users see only their own jobs.

**Implementation Phases:**
- ✅ Phase A: Basic MSP Job List (Complete)
- ✅ Phase B: Job Creation Wizard (Complete)
- ✅ Phase C: Job Actions & Monitoring (Complete)

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          e3 Cloud Backup Jobs                                │
├─────────────────────────────────────────────────────────────────────────────┤
│  [Tenant Filter ▼] [Agent Filter ▼]                      [+ Create Job]      │
├─────────────────────────────────────────────────────────────────────────────┤
│  Job        │ Tenant   │ Agent      │ Source │ Engine │ Schedule │ Actions  │
│─────────────┼──────────┼────────────┼────────┼────────┼──────────┼──────────│
│  Daily Sync │ Acme     │ FILE-SRV   │ local  │ kopia  │ daily    │ ▶ ⏸ 🔄 📋 🗑│
│  VM Backup  │ Beta LLC │ HV-01      │ hyper  │ hyperv │ weekly   │ ▶ ⏸ 🔄 📋 🗑│
│  Direct Job │ Direct   │ MSP-SRV    │ local  │ sync   │ manual   │ ▶ ⏸ 🔄 📋 🗑│
└─────────────────────────────────────────────────────────────────────────────┘
```

## Job Ownership Model

Jobs inherit tenant context through the `agent_id` relationship:

```
Job (s3_cloudbackup_jobs)
├── client_id: 42 (MSP)
├── agent_id: 15  ──────────► Agent (s3_cloudbackup_agents)
│                             ├── client_id: 42 (MSP)
│                             └── tenant_id: 7 (Acme Corp)
│
│   Effective ownership:
│   ├── Job belongs to MSP client_id=42
│   └── Job is scoped to tenant_id=7 via agent
```

## MSP Authorization Helper

The `MspController::validateJobAccess()` method ensures proper authorization:

```php
// In lib/Client/MspController.php
public static function validateJobAccess(int $jobId, int $clientId): array
{
    // 1. Check basic ownership - job must belong to client
    $job = Capsule::table('s3_cloudbackup_jobs')
        ->where('id', $jobId)
        ->where('client_id', $clientId)
        ->first();

    if (!$job) {
        return ['valid' => false, 'message' => 'Job not found or access denied.'];
    }

    // 2. Non-MSP clients: basic ownership is sufficient
    if (!self::isMspClient($clientId)) {
        return ['valid' => true, 'message' => 'Access granted.', 'job' => (array)$job];
    }

    // 3. MSP clients: validate agent/tenant chain
    if (!empty($job->agent_id)) {
        $agent = Capsule::table('s3_cloudbackup_agents')
            ->where('id', $job->agent_id)
            ->where('client_id', $clientId)
            ->first();

        if (!$agent) {
            return ['valid' => false, 'message' => 'Agent not found.'];
        }

        if (!empty($agent->tenant_id)) {
            $tenant = self::getTenant((int)$agent->tenant_id, $clientId);
            if (!$tenant) {
                return ['valid' => false, 'message' => 'Tenant not found.'];
            }
        }
    }

    return ['valid' => true, 'message' => 'Access granted.', 'job' => (array)$job];
}
```

## Phase A: Basic MSP Job List

### Page Controller (`pages/e3backup_jobs.php`)

Returns data for the jobs list view:
- `isMspClient` - Boolean for conditional UI
- `tenants` - List of tenants for filter dropdown (MSP only)
- `agents` - List of agents for filter dropdown
- `buckets` - Available destination buckets
- `s3_user_id` - Storage user ID for bucket creation
- `client_id` - Client ID for job creation

### Job List API (`api/e3backup_job_list.php`)

**Request**:
```
GET /api/e3backup_job_list.php?tenant_id=42&agent_id=15
```

**Filter Options** (MSP only):
- `tenant_id=` - All jobs
- `tenant_id=direct` - Jobs on agents without tenant
- `tenant_id=42` - Jobs on agents in specific tenant
- `agent_id=15` - Jobs on specific agent

**Response**:
```json
{
    "status": "success",
    "jobs": [
        {
            "id": 1,
            "name": "Daily Sync",
            "source_type": "local_agent",
            "source_path": "C:\\Data",
            "engine": "kopia",
            "backup_mode": "snapshot",
            "schedule_type": "daily",
            "status": "active",
            "created_at": "2025-01-15 10:00:00",
            "agent_id": 15,
            "agent_hostname": "FILE-SERVER",
            "tenant_id": 42,
            "tenant_name": "Acme Corp"
        }
    ]
}
```

### Template (`templates/e3backup_jobs.tpl`)

Features:
- Alpine.js-powered reactive table with filtering
- Tenant filter dropdown (MSP only)
- Agent filter dropdown (filtered by selected tenant)
- Status badges (active/paused)
- Source type badges

```javascript
// Alpine.js app structure
jobsApp() {
    jobs: [],
    loading: true,
    tenantFilter: '',
    agentFilter: '',
    agents: [...],  // From PHP
    get filteredAgents() { ... },
    loadJobs(),
    onTenantChange(),
    statusClass(status),
    sourceClass(type)
}
```

## Phase B: Job Creation Wizard

The job creation wizard is a slide-over panel with multi-step configuration, tenant-aware agent selection for MSPs.

### Wizard Flow

```
Step 1: Basic Setup
├── Job Name
├── Backup Engine (File Backup / Disk Image / Hyper-V)
├── Tenant Selection (MSP only) → filters agents
├── Agent Selection
└── Destination Bucket + Prefix

Step 2: Source Configuration
├── File Browser (local agent)
├── Volume Selection (disk image)
└── Include/Exclude Globs

Step 3: Schedule
├── Manual / Daily / Weekly / Cron
├── Time picker
└── Weekday selector (weekly)

Step 4: Policy
├── Retention (keep_last_n / keep_days)
├── Bandwidth limit
├── Compression
└── Debug logging

Step 5: Review
└── JSON summary of configuration
```

### MSP Tenant → Agent Filtering

For MSPs, selecting a tenant filters the available agents:

```javascript
// When tenant changes, filter agents
onTenantChange() {
    this.agentFilter = '';
    // filteredAgents computed property updates
}

get filteredAgents() {
    if (this.tenantFilter === 'direct') {
        return this.agents.filter(a => !a.tenant_id);
    }
    if (this.tenantFilter) {
        return this.agents.filter(a => 
            String(a.tenant_id) === String(this.tenantFilter)
        );
    }
    return this.agents;
}
```

### Create Job API (`api/cloudbackup_create_job.php`)

Includes MSP tenant validation when `agent_id` is provided:

```php
// Validate agent belongs to client (and tenant if MSP)
if ($agentId) {
    $agent = Capsule::table('s3_cloudbackup_agents')
        ->where('id', $agentId)
        ->where('client_id', $clientId)
        ->where('status', 'active')
        ->first();

    if (!$agent) {
        respond(['status' => 'fail', 'message' => 'Agent not found or inactive']);
    }

    // For MSPs, validate tenant if specified
    if (MspController::isMspClient($clientId) && $tenantId) {
        $tenant = MspController::getTenant((int)$tenantId, $clientId);
        if (!$tenant) {
            respond(['status' => 'fail', 'message' => 'Tenant not found']);
        }
        // Verify agent belongs to the specified tenant
        if ((int)$agent->tenant_id !== (int)$tenantId) {
            respond(['status' => 'fail', 'message' => 'Agent does not belong to tenant']);
        }
    }
}
```

## Phase C: Job Actions & Monitoring

### Action Buttons

Each job row includes action buttons:

| Action | Icon | Function | Description |
|--------|------|----------|-------------|
| Run Now | ▶ | `runJob(id)` | Starts backup immediately |
| Pause/Resume | ⏸/▶ | `toggleJobStatus(id, status)` | Toggles job status |
| Restore | 🔄 | `openRestoreModal(id)` | Opens restore wizard |
| View Logs | 📋 | `viewLogs(id)` | Navigates to run history |
| Delete | 🗑 | `deleteJob(id, name)` | Deletes job with confirmation |

### Restore Wizard Modal

Three-step modal for initiating restores:

```
Step 1: Select Snapshot
└── Dropdown of recent runs with manifest IDs

Step 2: Target Configuration
├── Destination path on agent
└── Mount option (experimental)

Step 3: Review
└── JSON summary of restore parameters
```

### APIs with MSP Validation

All job action APIs include MSP tenant authorization:

| API | Method | MSP Validation |
|-----|--------|----------------|
| `cloudbackup_start_run.php` | POST | `MspController::validateJobAccess()` |
| `cloudbackup_update_job.php` | POST | `MspController::validateJobAccess()` |
| `cloudbackup_delete_job.php` | POST | `MspController::validateJobAccess()` |
| `cloudbackup_start_restore.php` | POST | `MspController::validateJobAccess()` via job lookup |
| `cloudbackup_list_runs.php` | GET | `MspController::validateJobAccess()` |

**Example MSP validation in API**:
```php
require_once __DIR__ . '/../lib/Client/MspController.php';

// ... authentication and job_id validation ...

// MSP tenant authorization check
$accessCheck = MspController::validateJobAccess((int)$jobId, $loggedInUserId);
if (!$accessCheck['valid']) {
    $response = new JsonResponse([
        'status' => 'fail',
        'message' => $accessCheck['message']
    ], 200);
    $response->send();
    exit();
}

// Proceed with job action...
```

## Files Created/Modified

| File | Action | Description |
|------|--------|-------------|
| `pages/e3backup_jobs.php` | Created | Jobs page controller |
| `templates/e3backup_jobs.tpl` | Created | Jobs list + actions template |
| `templates/partials/job_create_wizard.tpl` | Modified | Tenant-aware wizard |
| `api/e3backup_job_list.php` | Created | MSP-filtered job list API |
| `api/cloudbackup_create_job.php` | Modified | Added MSP tenant validation |
| `api/cloudbackup_start_run.php` | Modified | Added MSP validation |
| `api/cloudbackup_update_job.php` | Modified | Added MSP validation |
| `api/cloudbackup_delete_job.php` | Modified | Added MSP validation |
| `api/cloudbackup_start_restore.php` | Modified | Added MSP validation |
| `api/cloudbackup_list_runs.php` | Modified | Added MSP validation |
| `lib/Client/MspController.php` | Modified | Added `validateJobAccess()` |
| `cloudstorage.php` | Modified | Added jobs routing |
| `header.tpl` | Modified | Added Jobs nav link |

---

## Phase 4: Tenant Portal (Complete)

The tenant portal provides MSP customers (tenants) and their users with self-service access to manage devices, view backup history, and initiate restores. It operates independently of the WHMCS client area with its own authentication.

### Architecture

```
/portal/
├── index.php           # Router - dispatches to templates based on ?page=
├── bootstrap.php       # WHMCS init, helpers, branding detection
├── auth.php            # Login logic, CSRF, session helpers
├── api/
│   ├── login.php       # POST: authenticate tenant user
│   ├── logout.php      # POST: destroy session
│   ├── password_reset.php      # POST: request password reset email
│   ├── password_reset_confirm.php  # POST: confirm reset with token
│   ├── dashboard.php   # GET: stats (devices, backups, storage, runs)
│   ├── devices.php     # GET: list devices; POST: admin can rename/assign
│   ├── jobs.php        # GET: list jobs and runs
│   ├── snapshots.php   # GET: snapshot info for restore
│   └── restore.php     # POST: queue restore command
├── templates/
│   ├── layout.tpl      # Base layout with branding, header, nav
│   ├── login.tpl       # Login form
│   ├── password_reset.tpl      # Request reset form
│   ├── password_reset_confirm.tpl  # Set new password form
│   ├── dashboard.tpl   # Overview stats + recent activity
│   ├── devices.tpl     # Device list with management
│   ├── jobs.tpl        # Job list with run history
│   ├── restore.tpl     # Restore wizard
│   └── settings.tpl    # User profile settings
└── assets/css/portal.css   # Portal-specific styles
```

### Authentication Flow

```
User visits /portal/index.php?page=login
              │
              ▼
     ┌────────────────┐
     │  Login Form    │
     │  email/pass    │
     └────────────────┘
              │
              ▼ POST /api/login.php + X-CSRF-Token
     ┌────────────────────────────────┐
     │ portal_login()                 │
     │ 1. Lookup s3_backup_tenant_users │
     │ 2. Verify password_hash (bcrypt) │
     │ 3. Check tenant status=active    │
     │ 4. Set $_SESSION['portal_user']  │
     └────────────────────────────────┘
              │
              ▼ Redirect to ?page=dashboard
     ┌────────────────┐
     │   Dashboard    │
     └────────────────┘
```

### Session Structure

```php
$_SESSION['portal_user'] = [
    'user_id'      => 42,
    'tenant_id'    => 7,
    'client_id'    => 100,      // MSP's WHMCS client_id
    'email'        => 'john@acme.com',
    'name'         => 'John Smith',
    'role'         => 'admin',  // 'admin' or 'user'
    'tenant_name'  => 'Acme Corp',
    'branding'     => [...],
    'logged_in_at' => 1234567890,
];
```

### Role-Based Access Control

| Role | Devices | Jobs/Runs | Restore | Admin Functions |
|------|---------|-----------|---------|-----------------|
| `admin` | All tenant devices | All tenant jobs | Any device | Rename, reassign devices |
| `user` | Only assigned devices | Only assigned | Own devices | None |

**Implementation Pattern:**
```php
$role = $session['role'] ?? 'user';

$query = Capsule::table('s3_cloudbackup_agents')
    ->where('tenant_id', $tenantId);

if ($role !== 'admin') {
    $query->where('tenant_user_id', $userId);
}
```

### Branding System

Branding is detected in this priority:

1. **Custom Domain**: Lookup `$_SERVER['HTTP_HOST']` in `s3_msp_portal_domains`
2. **MSP Slug**: Check `?msp=` parameter against `s3_backup_tenants.slug`
3. **Default**: Use e3 Cloud Backup default branding

**Branding Fields:**
```php
[
    'company_name'   => 'e3 Cloud Backup',
    'logo_url'       => '/templates/eazyBackup/assets/img/logo.svg',
    'logo_dark_url'  => '/templates/eazyBackup/assets/img/logo.svg',
    'primary_color'  => '#FE5000',
    'support_email'  => 'support@eazybackup.ca',
    'support_url'    => 'https://support.eazybackup.ca',
]
```

### API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `login.php` | POST | None | Authenticate user, set session |
| `logout.php` | POST | None | Destroy session |
| `password_reset.php` | POST | None | Send reset email |
| `password_reset_confirm.php` | POST | Token | Set new password |
| `dashboard.php` | GET | Required | Stats: devices, backups, storage, recent runs |
| `devices.php` | GET | Required | List devices (role-scoped) |
| `devices.php` | POST | Admin | Rename device, reassign to user |
| `jobs.php` | GET | Required | List jobs; `?job_id=N` for runs |
| `snapshots.php` | GET | Required | Snapshot details for restore |
| `restore.php` | POST | Required | Queue restore command |

### Security

- **CSRF Protection**: All POST requests require `X-CSRF-Token` header matching `$_SESSION['portal_csrf']`
- **Password Hashing**: bcrypt via `password_hash()` / `password_verify()`
- **Reset Tokens**: 32-char random hex, 1-hour expiry, stored in `password_reset_token`
- **Session**: PHP native sessions, no external state
- **Tenant Scoping**: All queries filter by `tenant_id` from session

### Files Created

| File | Description |
|------|-------------|
| `accounts/portal/index.php` | Main router |
| `accounts/portal/bootstrap.php` | Init + branding helpers |
| `accounts/portal/auth.php` | Login, CSRF, session logic |
| `accounts/portal/api/login.php` | Login API |
| `accounts/portal/api/logout.php` | Logout API |
| `accounts/portal/api/password_reset.php` | Request reset API |
| `accounts/portal/api/password_reset_confirm.php` | Confirm reset API |
| `accounts/portal/api/dashboard.php` | Dashboard stats API |
| `accounts/portal/api/devices.php` | Devices list/update API |
| `accounts/portal/api/jobs.php` | Jobs/runs list API |
| `accounts/portal/api/snapshots.php` | Snapshot details API |
| `accounts/portal/api/restore.php` | Restore queue API |
| `accounts/portal/templates/layout.tpl` | Base layout |
| `accounts/portal/templates/login.tpl` | Login page |
| `accounts/portal/templates/password_reset.tpl` | Reset request page |
| `accounts/portal/templates/password_reset_confirm.tpl` | Reset confirm page |
| `accounts/portal/templates/dashboard.tpl` | Dashboard page |
| `accounts/portal/templates/devices.tpl` | Devices page |
| `accounts/portal/templates/jobs.tpl` | Jobs page |
| `accounts/portal/templates/restore.tpl` | Restore wizard |
| `accounts/portal/templates/settings.tpl` | User settings page |
| `accounts/portal/assets/css/portal.css` | Portal styles |

---

## Phase 4 Testing Checklist

### Authentication Tests

- [ ] **Login with valid credentials** → Redirects to dashboard
- [ ] **Login with invalid email** → Shows "Invalid credentials"
- [ ] **Login with wrong password** → Shows "Invalid credentials"
- [ ] **Login with disabled user** → Shows error (user status != active)
- [ ] **Login with suspended tenant** → Shows "Tenant unavailable"
- [ ] **Session persists across pages** → User stays logged in
- [ ] **Logout** → Session destroyed, redirect to login

### Password Reset Tests

- [ ] **Request reset with valid email** → Email sent (check logs if no SMTP)
- [ ] **Request reset with unknown email** → Still shows success (no reveal)
- [ ] **Reset link opens confirm page** → Token in URL accepted
- [ ] **Set new password** → Password updated, can login
- [ ] **Expired token** → Shows error
- [ ] **Invalid token** → Shows error

### Dashboard Tests

- [ ] **Dashboard loads** → Shows stats cards
- [ ] **Device count** → Matches agent count for tenant
- [ ] **Backups 24h** → Shows success/failed counts
- [ ] **Storage usage** → Shows used/quota (or 0 if no snapshot)
- [ ] **Recent activity** → Shows last 5 runs

### Role-Based Access Tests

#### Admin User
- [ ] **Devices page** → Shows all tenant devices
- [ ] **Jobs page** → Shows all tenant jobs/runs
- [ ] **Can rename device** → Updates hostname
- [ ] **Can reassign device** → Changes tenant_user_id

#### Regular User
- [ ] **Devices page** → Shows only assigned devices
- [ ] **Jobs page** → Shows only assigned device jobs
- [ ] **Cannot update device** → Returns 403 Forbidden

### Restore Tests

- [ ] **Select run with manifest** → Can proceed
- [ ] **Enter target path** → Validates required
- [ ] **Submit restore** → Creates run + command in DB
- [ ] **Restore command pending** → Agent can poll and pick up

### Branding Tests

- [ ] **Default branding** → Shows "e3 Cloud Backup" logo/name
- [ ] **MSP slug param** → `?msp=acme` loads tenant branding
- [ ] **Custom domain** → Verified domain loads MSP branding
- [ ] **Branding in header** → Logo and company name shown
- [ ] **Branding in footer** → Support email shown

### Security Tests

- [ ] **POST without CSRF token** → Returns 401
- [ ] **POST with wrong CSRF token** → Returns 401
- [ ] **Access protected page without login** → Redirects to login
- [ ] **Cannot access other tenant's devices** → tenant_id filtering enforced
- [ ] **Cannot access other user's devices (as user)** → tenant_user_id filtering enforced

---

## Phase 5: Billing Integration
- ✅ Tenant-aggregate usage rollup with idempotent Stripe metered usage push
- ✅ Canonical tenant/customer billing linkage and portal billing APIs
- ✅ Stripe-authoritative invoice/payment cache rendering in tenant portal
- ✅ MSP release gate script for canonical tenant billing readiness

