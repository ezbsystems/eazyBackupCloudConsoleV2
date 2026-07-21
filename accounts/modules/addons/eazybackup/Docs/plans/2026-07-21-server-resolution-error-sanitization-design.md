# Server Resolution and Client Error Sanitization

## Problem

The eazyBackup user-profile route calls `comet_ProductParams($packageId)`. That helper resolves a WHMCS server by requiring the server-group name to equal the server name. WHMCS actually represents membership in `tblservergroupsrel`, and an individual service records its assigned server in `tblhosting.server`.

Production service 4653 demonstrates the failure: its product belongs to server group 22, the group has no same-named server, and the helper therefore returns empty connection values. The SDK constructs `http:///api/v1/admin/get-user-profile`, then the route exposes the raw exception to the customer.

## Runtime Evidence

- Service 4653 resolves to package 96 and assigned server 24.
- Package 96 belongs to server group 22.
- Group 22 has server 24 in `tblservergroupsrel`, but the group and server names differ.
- Server 24 has a hostname and credentials.
- A read-only production request through server 24 constructed a valid URI and returned the requested profile.

## Design

Extend `comet_ProductParams` with an optional assigned-server ID:

1. Load and validate the product and server group.
2. If an assigned-server ID is supplied, accept it only when `tblservergroupsrel` confirms that it belongs to the product's group.
3. Otherwise select a server through `tblservergroupsrel`.
4. Retain the name-based lookup only as a compatibility fallback for legacy configuration.
5. Throw a controlled exception when no usable server or hostname can be resolved instead of returning empty connection values.

The user-profile route will fetch both `packageid` and `server` from the already authorized service record and pass the assigned-server ID to `comet_ProductParams`.

## Error Handling

The customer-facing route will never concatenate SDK/helper exception text into its response. Configuration and API failures will return a generic retry/support message with no provider name, URI, hostname, or endpoint.

Internal diagnostics will contain only service, package, server-group, and server IDs plus failure classifications. They will not contain usernames, hostnames, credentials, or raw request payloads.

## Compatibility

Existing callers that only pass a package ID continue to work through group-relation resolution and the legacy fallback. Service-aware callers can opt into deterministic assigned-server resolution. No database schema change is required.

## Verification

1. Add a regression test that fails against name-only server resolution and expects an assigned relational server to be selected.
2. Add a regression test that rejects raw provider/URI details in the customer-facing error.
3. Run PHP syntax and focused regression checks.
4. Reproduce service 4653's mapping on development and inspect the retained debug instrumentation.
5. After deployment, verify the affected profile loads and compare post-fix logs to the pre-fix production evidence before removing instrumentation.
