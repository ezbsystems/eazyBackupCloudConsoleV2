# OneDrive pagination-loop rebaseline design

## Problem

Production batch `ee36b931-01a4-4377-b636-f2238a73d498` contains a failed
user workload whose OneDrive phase twice resumed from the same previously
successful delta cursor and twice failed with:

`Graph pagination loop detected: page contained only previously seen items`

The Graph paginator correctly distinguishes this duplicate-content condition
from an identical-nextLink cycle, but OneDrive currently treats every
`GraphPaginationError` as terminal. Requeueing alone repeats the poisoned
incremental cursor.

## Selected behavior

When OneDrive pagination starts with a non-empty delta cursor and returns a
`GraphPaginationError`, retry that OneDrive sync exactly once with an empty
cursor. The failed incremental pass has not yet applied its returned items to
the overlay, so the full pass starts from the unchanged prior manifest overlay.

The full pass remains strict:

- Success applies the full delta result and persists its new delta link.
- Another pagination error is returned normally and the workload remains
  failed; there is no retry loop or partial-success promotion.
- Errors other than `GraphPaginationError` do not trigger rebaseline.
- A first/full sync with an empty cursor never recursively retries.

## Runtime diagnostics

Wire the existing OneDrive delta paginator to the worker run-log callback so
page count, duplicate count, resume mode, and completion are available in
`ms365_worker_log_lines`. Add temporary session `b86288` ingestion diagnostics
for the affected child to the provisioned NDJSON log. Diagnostics must not
contain Graph tokens, next-link values, credentials, or item data.

Keep diagnostics through the post-fix production retry. Remove them only after
the retry logs prove the incremental failure triggered one full rebaseline and
the operator confirms the workload completed.

## Verification and rollout

1. Add a failing Go regression where a resumed delta returns a duplicate-only
   page and the empty-cursor retry completes with a new delta link.
2. Verify an empty-cursor pagination error and a non-pagination error do not
   trigger fallback.
3. Run focused graph/graphsync tests and the full Go suite.
4. Release worker `0.4.20`, deploy it fleet-wide, and verify node versions.
5. Requeue only child `de6322a3-b679-43a3-81a0-32f68a811e10` without disturbing
   healthy siblings in the active batch.
6. Confirm logs show one incremental pagination failure, one full-delta retry,
   successful Graph sync, and terminal workload success.
