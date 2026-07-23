# Teams Channel DeltaToken Recovery Design

**Date:** 2026-07-23

## Goal

Stop Teams child workloads from retry-looping forever when Microsoft Graph returns `graph 400` with `Parameter 'DeltaToken' not supported` on `/teams/{id}/channels/{id}/messages/delta`, while preserving backup progress for healthy channels.

## Runtime evidence

Production batch `bec0a14b-…` / child `a028dd6e-…` (`team:9b9b9167-…`) fails in ~2–3s with:

`teams: graph 400 Bad Request: Parameter 'DeltaToken' not supported for this request.`

- No stored delta for this team (`ms365_delta_state` empty; fresh baseline failure).
- `SyncTeams` aborts the entire team on the first channel delta error.
- PHP `isNonRetryableError()` does not match this signature, so the child requeues every few seconds with `attempts` stuck at `0/5`.

## Design

### Per-channel recovery ladder

On Graph 400 containing `deltatoken` / `Parameter 'DeltaToken' not supported`:

1. **Resume path:** If a prior delta link was supplied, clear it and baseline once.
2. **Filter fallback:** Retry the baseline once with `$filter=lastModifiedDateTime gt <UTC cutoff>` (8 months back, matching Teams message retention guidance).
3. **Soft-skip:** Log a warning, increment `channels_skipped`, do not store a delta token for that channel, continue remaining channels. `SyncTeams` returns success with partial channel coverage.

410 delta-reset handling remains in `paginateDeltaResilient`; this ladder is Teams-specific for the 400 DeltaToken defect.

### URL hygiene

`stripDisallowedGraphQueryParams` removes `$top`/`$skip` from pagination URLs without `url.Values.Encode()` rewriting opaque `$skiptoken`/`$deltatoken` query keys (no `%24` mangling).

### PHP safety net

`JobQueueRepository::isNonRetryableError()` treats `graph 400` + `deltatoken` / `parameter 'deltatoken' not supported` as terminal so an old worker cannot requeue the child indefinitely if the whole team still hard-fails.

## Tests

1. Baseline DeltaToken 400 → `$filter` retry succeeds; messages stored.
2. DeltaToken 400 persists after filter → channel soft-skipped; sibling channel still backed up; `SyncTeams` returns nil.
3. Resume with prior delta + DeltaToken 400 → rebaseline once succeeds.
4. `stripDisallowedGraphQueryParams` preserves literal `$skiptoken`/`$deltatoken` bytes.
5. PHP unit test classifies the production error string as non-retryable.

## Out of scope

Graph beta APIs, non-delta Teams list APIs, replies enumeration changes.
