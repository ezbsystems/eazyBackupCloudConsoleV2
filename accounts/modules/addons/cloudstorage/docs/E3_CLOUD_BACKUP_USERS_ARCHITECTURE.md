# e3 Cloud Backup Users Architecture

## Purpose

Introduce a dedicated **Username** entity for e3 Cloud Backup without remapping existing vault/job/agent ownership data.

This phase focuses on:

- username lifecycle management (create/read/update/delete/reset password)
- MSP/direct scoping
- new Users list/detail UI
- derived scoped metrics shown per username

## Data model

Table: `s3_backup_users`

- `id` (PK)
- `client_id` (required)
- `tenant_id` (nullable)
- `username` (required)
- `password_hash` (required)
- `email` (required; reporting email)
- `status` (`active` or `disabled`)
- `created_at`
- `updated_at`

Indexes:

- unique scope key: `uniq_backup_users_scope_username` on (`client_id`, `tenant_id`, `username`)
- non-unique indexes: `client_id`, `tenant_id`, `status`, `email`

## Routing

Implemented in `cloudstorage_clientarea()`:

- `page=e3backup&view=users`
  - page controller: `pages/e3backup_users.php`
  - template: `templates/e3backup_users.tpl`
- `page=e3backup&view=user_detail&user_id=<id>`
  - page controller: `pages/e3backup_user_detail.php`
  - template: `templates/e3backup_user_detail.tpl`

## API endpoints

- `api/e3backup_user_list.php`
  - list users in current account scope
  - optional tenant filter for MSP (`tenant_id` or `direct`)
  - includes derived scoped metrics
- `api/e3backup_user_create.php`
  - create user with validation and scope checks
- `api/e3backup_user_get.php`
  - fetch single user and derived scoped metrics
- `api/e3backup_user_update.php`
  - update username/email/status/tenant assignment with scope checks
- `api/e3backup_user_reset_password.php`
  - reset password hash for scoped user
- `api/e3backup_user_delete.php`
  - delete scoped user record

## Authorization rules

- all endpoints require authenticated WHMCS client area session
- all operations require `client_id` ownership
- MSP clients:
  - can use tenant-scoped users and direct users
  - assigned tenant must belong to MSP account
- direct clients:
  - only direct users (`tenant_id` must be null)

## Validation rules

- `username`: required, `^[A-Za-z0-9._-]{3,64}$`, unique within scope
- `email`: required, valid format
- `password`:
  - required on create
  - minimum 8 characters
  - confirmation must match
- `status`: `active` or `disabled`
- `tenant_id`:
  - optional for MSP
  - rejected for direct customers

## Metrics strategy (no ownership remap)

Per-username metrics are currently **derived by scope** (tenant/direct), not bound to explicit username ownership links in existing resource tables:

- `# Agents`: from `s3_cloudbackup_agents` by `client_id` + tenant scope
- `# Jobs`: from `s3_cloudbackup_jobs` joined to agent tenant scope
- `# Vaults`: distinct destination buckets from jobs in scope
- `Last Backup`: latest successful/warning run from `s3_cloudbackup_runs` in scope
- `Online Devices`: agents with `last_seen_at` inside online threshold setting

## UI notes

Users list (`e3backup_users.tpl`) includes:

- sortable columns
- show entries menu: 10, 25, 50, 100
- column visibility menu
- custom tenant filter menu (MSP)
- Add User modal with custom tenant assignment menu
- row click navigation to user detail

User detail (`e3backup_user_detail.tpl`) includes:

- profile summary
- derived metrics cards
- update form
- password reset form
- delete action

## Non-goals in this phase

- no schema-level ownership remapping of existing vault/job/agent/log records
- no backfill from legacy tenant user/storage user data into `s3_backup_users`
- no changes to existing backup execution or provisioning flows

