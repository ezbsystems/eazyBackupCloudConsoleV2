# Tenant Public ID Design

## Summary

Replace client-visible canonical tenant numeric IDs with opaque public IDs so WHMCS clients cannot infer system-wide tenant counts or enumerate tenants through sequential identifiers.

## Problem

- Canonical tenants currently use `eb_tenants.id` as both the internal primary key and the client-visible identifier.
- This numeric ID is exposed in Partner Hub routes, hidden form fields, JavaScript payloads, billing flows, and legacy cloudstorage/e3 screens.
- Because `eb_tenants.id` is auto-incremented globally, it reveals cross-account tenant growth and creates an avoidable enumeration surface.

## Chosen Direction

- Add a dedicated `public_id` column to `eb_tenants`.
- Use a 26-character ULID for `public_id`, matching the existing `eb_whitelabel_tenants.public_id` pattern already used elsewhere in the addon.
- Treat `eb_tenants.id` as internal-only after rollout.
- Perform a clean cutover for all client-visible surfaces on this server; numeric client-facing fallback is not required.

## Data Model

- Keep `eb_tenants.id` as the numeric primary key for joins and internal references.
- Add `eb_tenants.public_id CHAR(26)` with a unique index.
- Backfill all existing canonical tenants with a generated ULID.
- Generate `public_id` on every new canonical tenant create path.

## Routing and Resolution

- Resolve client-visible tenant lookups by `public_id` at controller boundaries.
- Continue using numeric tenant IDs internally after lookup succeeds.
- Standardize authenticated client-facing routes and forms on the opaque identifier.

## Scope

### Partner Hub

- Replace numeric tenant IDs in:
  - tenant list links and labels
  - tenant detail tabs and forms
  - overview recent-tenant links
  - billing payment creation and payment-method flows
  - catalog/plan assignment UI
  - subscription links and legacy client-view links

### Cloudstorage / e3

- Replace numeric canonical tenant IDs in:
  - tenant list/detail URLs
  - tenant selectors in MSP-facing UIs
  - user create/update payloads
  - enrollment token creation/listing
  - job creation/filter flows
  - legacy redirects into Partner Hub

### Internal-only references

- Keep numeric `id` for:
  - joins on billing, usage, storage, members, and subscriptions
  - persisted foreign-key-style columns such as `tenant_id`
  - server-side resolution after a public ID has been translated once

## UI Rules

- Do not show sequential numeric tenant IDs to clients.
- If a visible tenant identifier is still useful, show the new opaque `public_id`.
- Prefer labels such as `Tenant ID` instead of `Tenant #123`.

## Migration Notes

- Backfill must be idempotent and safe to run on an existing environment.
- New code should avoid emitting numeric tenant IDs in JSON responses intended for the browser.
- Legacy server-internal integrations that rely on numeric `tenant_id` columns remain unchanged.

## Verification

- Confirm new tenants receive a unique `public_id`.
- Confirm Partner Hub links and forms use `public_id` instead of numeric IDs.
- Confirm cloudstorage/e3 client-visible tenant references use `public_id`.
- Confirm controllers resolve `public_id` back to the correct internal tenant row.
- Confirm numeric tenant IDs are no longer shown in client-visible templates.
