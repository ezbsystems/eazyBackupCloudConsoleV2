# Mail Folder Pagination Recovery Design

**Date:** 2026-07-22

## Goal

Prevent a Microsoft Graph `mailFolders` repeated-`@odata.nextLink` defect from failing an otherwise healthy MS365 backup batch, while preserving strict pagination behavior for message delta synchronization.

## Runtime evidence

Production batch `118b5226-34f2-49bf-a495-2368c6e12dc4` completed `partial_success`: 329 child workloads succeeded and four user workloads failed. Each failed queue row contained:

`mail: Graph pagination loop detected: identical @odata.nextLink URL repeated`

The batch claim completed normally on one attempt. Its final 429 ratio was approximately 1.84%, and the focused instrumented reproduction recorded:

- Entry before mail-folder pagination with no delta state.
- A `GraphPaginationError` from mail-folder pagination.
- Zero new 429 hits and no context cancellation.
- No completed folder enumeration and no message-delta execution.

Therefore the failure is the initial `/users/{id}/mailFolders` enumeration, not message delta, throttling, worker cancellation, Kopia upload, or control-plane delivery.

## Design

### Pagination primitive

Extend pagination sessions with an explicit `SoftStopRepeatedNextLink` monitor flag (default `false`) so an identical repeated nextLink can soft-stop when opted in, just as duplicate-only pages already do under `DuplicatePageDetectOnly`. Record this outcome separately from natural completion. Strict sessions and `DuplicatePageDetectOnly` callers without the flag continue returning `GraphPaginationError` on repeated nextLink (SharePoint, calendar, and other existing DetectOnly workloads unchanged).

The repeated-link soft-stop is mail-folder opt-in via `PaginationMonitor.SoftStopRepeatedNextLink`. Existing SharePoint/calendar `DuplicatePageDetectOnly` callers retain strict repeated-nextLink errors unless they set the flag. Mail-folder enumeration sets the flag and uses a nil per-page logger (no truncated nextLink URLs in run logs); high-level wedge retry/final warnings remain on `opts.Log`.

### Mail-folder enumeration

Replace the single `$top=100` mail-folder request with a focused helper that tries page sizes `100`, `50`, then `25`.

For each attempt:

1. Start from `/users/{id}/mailFolders`; do not reuse a prior nextLink.
2. Track item IDs so folders returned on repeated pages are deduplicated.
3. Use DetectOnly and inspect `PaginationOutcome`.
4. Return immediately when enumeration reaches natural EOF.
5. If Graph soft-stops, retry from page one with the next smaller page size.

If the final page size still soft-stops, return the unique folders collected by that final attempt and emit a warning through the existing run logger. This avoids a terminal child failure. No folder cursor or checkpoint is persisted, so every later backup retries complete enumeration from page one.

### Scope and safety

- Message `messages/delta` pagination remains strict and unchanged.
- Delta links are saved only by the existing naturally completed message-delta path.
- No global pagination default changes.
- No additional Graph permissions or schema changes.
- The customer may receive a successful backup containing the folders Graph returned before the wedge; the run log explicitly records the partial folder enumeration.

## Tests

1. Graph pagination unit test: Strict mode still errors on an identical repeated nextLink.
2. Graph pagination unit test: `DuplicatePageDetectOnly` without `SoftStopRepeatedNextLink` still errors on repeated nextLink.
3. Graph pagination unit test: `SoftStopRepeatedNextLink` opt-in soft-stops on repeated nextLink and reports a non-natural outcome.
3. Mail integration test: `$top=100` wedges and `$top=50` completes; backup proceeds with the complete retry result.
4. Mail integration test: all page sizes wedge; backup proceeds with unique folders from the final attempt and emits a warning.
5. Existing graph, graphsync, and full worker suites remain green.

## Deployment and verification

Build the next worker release and deploy it through the production fleet release process. Retry the four failed user workloads or run the next job. Verify:

- No child fails with the prior identical-nextLink mail error.
- Run logs show a smaller-page retry or final soft-stop warning for affected users.
- Message delta and Kopia snapshot phases proceed.
- Parent batch completes without this pagination error.

Keep session `6f5f7c` instrumentation until post-fix runtime logs prove the corrected branch and the operator confirms the issue is gone.
