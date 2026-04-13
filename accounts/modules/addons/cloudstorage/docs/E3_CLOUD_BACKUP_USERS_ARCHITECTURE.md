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
- `backup_type` ENUM(`cloud_only`, `local`, `both`) DEFAULT `both` -- intent selector for user creation
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
  - required on create when `backup_type` is `local` or `both`
  - auto-generated server-side when `backup_type` is `cloud_only`
  - minimum 8 characters
  - confirmation must match
- `backup_type`: `cloud_only`, `local`, or `both` (default `both`)
- `status`: `active` or `disabled`
- `tenant_id`:
  - optional for MSP
  - rejected for direct customers

## Job ownership link

Table `s3_cloudbackup_jobs` has a nullable `backup_user_id` column that directly links a job to a `s3_backup_users` row. When a job is created from a User Detail page, this FK is set automatically. Legacy jobs (created before this column existed) have `backup_user_id = NULL` and continue to be associated via tenant scope derivation.

## Agent ownership link

Table `s3_cloudbackup_agents` has a nullable `backup_user_id` column that directly links an agent to a `s3_backup_users` row. When an enrollment token carries a `backup_user_id`, the agent enrolled with that token inherits the link automatically. Legacy agents (enrolled before this column existed) have `backup_user_id = NULL` and are associated via tenant scope derivation.

Table `s3_agent_enrollment_tokens` also has a nullable `backup_user_id` column. When a token is created from a User Detail page context, this FK is set so that agents enrolled via that token are automatically linked to the correct backup user.

## Metrics strategy (hybrid)

Per-username metrics use a **hybrid** approach: direct FK when available, tenant scope derivation as fallback. This applies uniformly to agents, jobs, vaults, and last backup.

- `# Agents`: from `s3_cloudbackup_agents` where `backup_user_id = <user_id>` OR (`backup_user_id IS NULL` AND `client_id` + tenant scope match)
- `# Jobs`: from `s3_cloudbackup_jobs` where `backup_user_id = <user_id>` OR (`backup_user_id IS NULL` AND agent tenant scope match)
- `# Vaults`: distinct destination buckets from jobs in scope (same hybrid logic)
- `Last Backup`: latest successful/warning run from `s3_cloudbackup_runs` in scope (same hybrid logic on jobs)
- `Online Devices`: agents with `last_seen_at` inside online threshold setting (same hybrid agent scope)

## UI notes

Users list (`e3backup_users.tpl`) includes:

- sortable columns
- show entries menu: 10, 25, 50, 100
- column visibility menu
- custom tenant filter menu (MSP)
- Add User modal with custom tenant assignment menu
- row click navigation to user detail

User detail (`e3backup_user_detail.tpl`) includes:

- profile summary with backup type badge and upgrade action
- derived metrics cards
- update form
- password reset form
- delete action
- Create Job dropdown contextualized by `backup_type` (hides irrelevant job types)
- Agents tab hidden when `backup_type` is `cloud_only`

The standalone Jobs page (`e3backup_jobs.tpl`) has been deprecated. Jobs are now managed from each User's detail page. The page still exists with a deprecation banner for legacy bookmarks.

## Non-goals in this phase

- no backfill from legacy tenant user/storage user data into `s3_backup_users`
- no changes to existing backup execution or provisioning flows

