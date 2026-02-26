# Cloud Backup UUIDv7 Cutover – Verification & Release Notes

## Overview

As of the UUIDv7 cutover, cloud backup job and run identities use **UUIDv7 strings** at all API boundaries. In MySQL, `job_id` and `run_id` are stored as `BINARY(16)` using `UUID_TO_BIN` / `BIN_TO_UUID`. Numeric identifiers are no longer supported.

## Release Notes

### Big-Bang Reset

- Tables `s3_cloudbackup_jobs` and `s3_cloudbackup_runs` were recreated with UUID-native schema.
- **No migration path** from legacy numeric IDs; this is a clean reset.
- Run `scripts/cloudbackup_uuidv7_bigbang_cutover.php` before deploying the cutover.

### No Backward Compatibility

- Numeric `job_id` and `run_id` values are **rejected** by all cloud backup and agent APIs.
- Old clients or agents sending numeric IDs will receive `invalid_identifier_format` errors.
- There is **no fallback** to numeric IDs; the API contract is UUID-only.

### Strict Minimum Agent Version

- **Old agents are blocked.** Only agents that send UUID strings for `job_id` and `run_id` are compatible.
- Ensure all deployed agents are updated to a version that uses UUID-only job/run contracts before or at cutover.

### UUID-Only API Contract

- All cloud backup and agent APIs expect and return `job_id` and `run_id` as UUIDv7 strings (e.g. `018f7c8c-5cf0-7ad8-9f2b-8c58a7e7b2d6`).
- At database boundaries, values are converted via `UUID_TO_BIN` / `BIN_TO_UUID`; storage is `BINARY(16)`.

## Verification Checklist

Use this checklist to verify the cutover before and after deployment:

- [ ] Numeric `job_id` rejected by `cloudbackup_start_run.php`
- [ ] Numeric `run_id` rejected by `agent_update_run.php`
- [ ] UUID job/run flow passes create → run → progress → cancel

### Manual API Smoke Verification

Run these commands against your deployment host (replace `<host>` with the actual host, e.g. `eazybackup.example.com`):

**1. Verify numeric `job_id` is rejected**

```bash
curl -s -X POST "https://<host>/modules/addons/cloudstorage/api/cloudbackup_start_run.php" \
  -d "job_id=123" | jq
```

Expected: `"status": "fail"`, `"code": "invalid_identifier_format"`.

**2. Verify numeric `run_id` is rejected by agent API**

```bash
curl -s -X POST "https://<host>/modules/addons/cloudstorage/api/agent_update_run.php" \
  -H "X-Agent-UUID: <agent-uuid>" -H "X-Agent-Token: <token>" \
  -d "run_id=456" | jq
```

Expected: `"status": "fail"`, `"code": "invalid_identifier_format"` or equivalent validation error.

**3. Verify valid UUID flow (create → run → progress → cancel)**

```bash
# Create job (returns job_id UUID)
# Start run with job_id UUID (returns run_id UUID)
# Poll progress: GET cloudbackup_progress.php?run_id=<uuid>
# Cancel: POST cloudbackup_cancel_run.php with run_id=<uuid>
```

Expected: Success responses for valid UUIDs throughout the flow.

## Related Documentation

- [LOCAL_AGENT_OVERVIEW.md](LOCAL_AGENT_OVERVIEW.md) – Agent contract and job/run identity
- [CLOUD_BACKUP.md](CLOUD_BACKUP.md) – Cloud backup architecture and schema
- [AGENT_TESTING.md](AGENT_TESTING.md) – Build and test guide
