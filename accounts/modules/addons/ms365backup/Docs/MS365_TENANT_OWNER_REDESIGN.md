# MS365 Backup — Tenant-Owner Redesign (claim unit: tenant batch)

**Status:** Proposed (design + plan) — not yet implemented
**Author:** Architecture review, 2026-06-25
**Supersedes (operationally):** the fleet-of-workers-per-tenant claim model documented in `MS365_WORKER_FLEET.md` §Concurrency / §Orphan claim recovery / §Zombie recovery
**Scope:** Backup execution control plane (PHP) + Go worker scheduler. Does **not** change the e3 customer UI, inventory, Graph workload engines, Kopia repo layout, or restore wizard.

---

## 0. TL;DR

Today one tenant's backup **batch** is fanned out across the whole worker fleet: each child workload
(`user:{id}`, `drive:{id}`, `site:{id}`, …) is an independent queue row that any worker can claim.
Microsoft Graph throttling is **per-application-per-tenant**, so all those workers contend for one
externally-enforced limit that the control plane tries to subdivide through MySQL rows. The result is
weeks of reaper/throttle/liveness heuristics (see `PROGRESS.md`) and a structural inability to tell a
*throttled-but-alive* worker apart from a *dead* worker — every wrong guess triggers a destructive
requeue that races a live worker (the `Run is not active.` / `Recovering this workload` symptoms).

**This redesign changes the claim unit from "child workload" to "tenant batch."** One worker claims and
owns an entire tenant's batch, runs all child workloads with its own in-process concurrency, holds the
single authoritative Graph throttle controller for that tenant, and emits **one** lease/heartbeat. On
worker loss the **whole batch** requeues to another worker and resumes from per-child checkpoints.

This collapses the per-child reaper thicket, removes cross-process Graph-budget division, and makes
liveness a single, knowable signal.

---

## 1. Motivation (why change the structure)

### 1.1 The unit-of-work / unit-of-throttling mismatch

| Reality | Current design |
|---------|----------------|
| Graph throttles per **application + tenant** (one shared rate limit) | Fans one tenant across **N workers**, each claiming child workloads |
| One shared limit is easiest to govern from **one place** | Divides the limit across workers via `ms365_graph_tenant_budget` rows + `GraphTenantBudgetService::workerShare()` |
| A throttled worker goes quiet for `Retry-After` (up to 600s) | Control plane must **guess** whether silence = throttled-alive or dead |

Because the throttling boundary is the tenant, the work unit should be the tenant.

### 1.2 Evidence from the field (from `PROGRESS.md`)

- **Budget division starves children:** *"Claiming too many workloads against the same HTTP budget
  starves children on the shared limiter (no progress, no per-child 429s) and inflates the 429 rate."*
  (`MS365_WORKER_FLEET.md` §Workload vs HTTP budget)
- **Raising concurrency made it worse:** operators raised `ms365_per_tenant_max_concurrent_workloads`
  6→24 with *"no effect / worse"* because more workers amplified tenant-wide throttling.
- **Reaper vs live worker race:** zombie/orphan reapers requeue runs while the worker is mid-flight;
  the worker then calls `ms365_worker_graph_token.php`, the run is no longer active, and it dies with
  `graph 401 after token refresh ... http 500: Run is not active.`
  (`WorkerClaimService::refreshGraphTokenForRun()` throws when the queue/run row is no longer active.)
- **Heuristic sprawl:** liveness now depends on a zoo of thresholds (120/180/600/1200/1800/2700s) and
  mutually-correcting shields (`isThrottledWaitingAlive`, `shouldSkipThrottleReaper`,
  `releaseOrphanedClaimsForIdleNode`, `reconcileExhaustedRunningClaims`, `countsAgainstTenantWorkloadCap`).
  Each fix exists to stop a previous fix from misfiring.
- **DB as real-time bus:** ~434k `ms365_worker_progress.php` POSTs/run and fsync convoys stalled WHMCS
  (2026-06-22 entry) because per-child heartbeats from the whole fleet land in the WHMCS MySQL DB.

### 1.3 What "one worker per job" should actually mean

The lever is correct but the granularity matters:

- **Not** one global worker (would serialize unrelated tenants — kills fleet scaling).
- **Not** per child workload (today's problem).
- **Yes:** one worker owns **one tenant's batch run**. Different tenants still run on different workers,
  so the fleet still scales across **customers** — the parallelism that actually pays off, because each
  tenant is an independent throttling domain.

---

## 2. Current architecture (as-is)

### 2.1 Entities

| Layer | Entity | Granularity |
|-------|--------|-------------|
| Customer parent run | `s3_cloudbackup_runs` (cloudstorage) | one per **batch** (a "Run now" / scheduled run of one e3 job) |
| Child run rows | `ms365_backup_runs` | one per **physical_key** (mailbox/drive/site/list/shard) |
| Queue | `ms365_job_queue` | **one row per child run** (`run_id`, `physical_key`, `tenant_record_id`, lease) |
| Aggregator | `Ms365BatchRunRepository` | rolls child statuses into the parent |
| Graph budget | `ms365_graph_tenant_budget` | per Entra tenant, divided across workers |

### 2.2 Claim flow (`WorkerClaimService::claimNext`)

1. Opportunistically run reapers (`releaseOrphanedClaimsForAllNodes`, `releaseExpiredLeases`,
   `recoverStaleRunning`, `reconcileExhaustedRunningClaims`, `reconcileZombieRuns`).
2. Gate by node load, platform cap (`platformMaxConcurrent`), per-client cap, and
   **per-tenant workload cap** (`perTenantMaxConcurrentWorkloads`, `countRunningForTenant`).
3. Build a **fair candidate pool** (`fetchBackupClaimCandidatesFair`, per-batch head pool of 50) so one
   whale doesn't monopolize the FIFO pool.
4. Under a per-tenant `GET_LOCK`, atomically flip **one** queue row `queued → running`, set lease,
   bump attempts, and return a single-workload `RunJob` payload (`buildRunPayload`).

### 2.3 Worker (`internal/jobs/scheduler.go`)

- Polls `ms365_worker_claim.php` repeatedly; each `Claim` returns **one** child `RunJob`.
- Runs each child in its own goroutine under a RAM/disk/CPU budget; `current_load` = number of running
  child runs across **all tenants** on that node.
- `internal/graph/tenant_controller.go` already keeps **one adaptive Graph controller per Entra tenant
  per worker** — but N workers run N controllers for the same tenant, reconciled only via the DB budget.
- Heartbeats per node; progress posts per child run (`ms365_worker_progress.php`).

### 2.4 The structural problem in one sentence

> One externally-enforced, tenant-wide limit is being managed by many independent processes whose
> liveness the control plane cannot observe, so it polices them with destructive, racy heuristics.

---

## 3. Target architecture (to-be)

### 3.1 Core change

**The claim unit becomes the tenant batch.** A worker claims a *batch lease* over all of one tenant's
queued child workloads for a given parent run. It then drives those children **in-process** using its
existing internal scheduler/concurrency and its single tenant Graph controller.

```
Before:  fleet ── claims ──> [child] [child] [child] ... (per-tenant budget divided via DB)
After:   worker-A ── claims ──> TENANT BATCH (all children) ── runs internally ── one lease/heartbeat
         worker-B ── claims ──> different tenant's batch
```

### 3.2 Invariant changes

| Concern | Before | After |
|---------|--------|-------|
| Claimable unit | child `run_id` | tenant batch (`batch_run_id` + `tenant_record_id`) |
| Graph budget | divided across workers via DB | **owned in-process** by the one assignor worker; DB budget retired (or advisory only) |
| Lease / liveness | one per child run | **one per batch claim** |
| Reaper question | "is each silent child alive?" (unanswerable) | "is the one batch owner heartbeating?" (answerable) |
| Failure recovery | requeue individual child | requeue **whole batch** (children resume from checkpoints) |
| Concurrency knobs | `perTenantMaxConcurrentWorkloads` (claim gate) + `workerShare` budget | worker-local `max_concurrent_runs` + tenant controller only |

### 3.3 What is **kept** (no change)

- All `internal/graphsync/*` workload engines (mail/calendar/contacts/tasks/OneDrive/SharePoint/Teams/…).
- Kopia repo bootstrap, bucket-per-job layout, retention, restore engine.
- Per-child `ms365_backup_runs` rows + `delta_states` checkpoints (resume granularity stays per child).
- Live UI aggregation (`Ms365BatchLiveService`) — it already aggregates children into the parent.
- `internal/graph/tenant_controller.go` — it becomes the **sole** governor instead of one of N.

---

## 4. Design details

### 4.1 Data model

Add a **batch claim** concept. Two viable shapes; **Option A is recommended.**

**Option A — batch-claim table (recommended).** New table `ms365_batch_claims`:

| Column | Purpose |
|--------|---------|
| `batch_run_id` (pk) | parent run (`s3_cloudbackup_runs.run_id`) |
| `tenant_record_id` | the throttling domain owned |
| `worker_node_id` | current owner (null = unclaimed) |
| `status` | `queued` / `running` / `done` / `failed` |
| `claimed_at`, `lease_expires_at` | single lease for the batch |
| `attempts`, `max_attempts` | batch-level retry budget |
| `last_heartbeat_at`, `last_progress_at` | single liveness signal |
| `error_message` | terminal reason |

`ms365_job_queue` is retained but demoted to a **child manifest** (the worker's internal to-do list for
the batch): rows still describe each `physical_key`/`run_id` and carry per-child `delta_states`, but they
are **no longer independently claimable by other workers**. Child status is reported by the owning worker.

**Option B — reuse `ms365_job_queue`.** Add `batch_run_id` + a `claim_scope` enum and claim *the set* of
rows sharing `(batch_run_id, tenant_record_id)` under one lease. Less migration, but overloads the queue
table semantics and keeps per-row lease columns that no longer mean anything. Prefer A for clarity.

> Migration keeps `ms365_backup_runs` and `s3_cloudbackup_runs` exactly as-is. Only the **claim/lease**
> bookkeeping moves up a level.

### 4.2 Control plane (PHP) changes

**New claim entry point** — `WorkerClaimService::claimNextBatch(string $nodeId, ?array $hint): ?array`:

1. Reaper sweep (batch-level now — see §4.5).
2. Node/platform gates (platform cap becomes "max concurrent **tenant batches** per node", typically `1`
   for the simplest model; see §4.7 for >1).
3. Select the highest-priority **unclaimed** batch (`ms365_batch_claims.status='queued'`), fairly across
   tenants/clients (reuse fair-ranking, but rank **batches**, not children).
4. Under a per-tenant `GET_LOCK`, atomically claim the batch: set `worker_node_id`, `status='running'`,
   lease. (A tenant can have at most one active batch owner — enforced by unique constraint on
   `(tenant_record_id)` where `status='running'`, or by the existing overlap guard.)
5. Return a **batch payload**: tenant creds/Graph token, dest bucket/repo info, and the **child manifest**
   — the list of child `RunJob`s (the existing `buildRunPayload` output, emitted once per child into an
   array `children[]`).

**New per-batch APIs (mirror the existing per-run ones):**

| New endpoint | Replaces | Purpose |
|--------------|----------|---------|
| `ms365_worker_batch_claim.php` | `ms365_worker_claim.php` | claim a tenant batch; returns `children[]` |
| `ms365_worker_batch_progress.php` | `ms365_worker_progress.php` (per child) | **one** POST carrying child deltas (array), renews the batch lease |
| `ms365_worker_batch_complete.php` | `ms365_worker_complete.php` | batch finished; carries final per-child statuses |
| `ms365_worker_batch_release.php` | `ms365_worker_release.php` | hand-off/drain: release batch, preserve child checkpoints |
| `ms365_worker_graph_token.php` | (kept) | **fix:** authorize by **batch** lease, never 500 on a live lease (see §4.6) |

Per-child progress is still recorded into `ms365_backup_runs` (the UI depends on it), but it arrives
**batched** inside one batch-progress POST (e.g. `children: [{run_id, phase, items_done, …}]`), drastically
cutting POST volume and fsync pressure (addresses the 2026-06-22 DB-stall finding).

**Lease/heartbeat:** one lease per batch claim. Worker heartbeat renews the batch lease while it owns the
batch. `WorkerLeaseService` gains `renewForBatch()` / drops per-child renewal complexity.

### 4.3 Worker (Go) changes

**New batch runner** wrapping the existing per-run `Runner`:

- `Scheduler.poll()` calls `client.ClaimBatch()` instead of `client.Claim()`.
- A claimed batch yields `[]*api.RunJob` (the children) + shared tenant context.
- A new `BatchRunner` owns:
  - one `graph.Client` + **one `tenant_controller`** for the tenant (the sole governor — no DB budget share),
  - an internal worker pool sized by `max_concurrent_runs` / RAM-disk budget (reuse `resourceBudget`),
  - iteration over children, each executed by the **unchanged** `Runner.Run` / `WorkloadRunner`,
  - a single aggregated progress/heartbeat stream (coalesced; reuse `newThrottledProgressSender`),
  - checkpointing each child's `delta_states` on child completion (already implemented) so a batch
    re-claim resumes only the unfinished children.
- `current_load` semantics simplify to "owns a batch: 1, else 0" at the node level; internal child
  concurrency is private to the worker.

**Graph governance becomes trivial:** `GraphTenantBudget` field in the payload is no longer divided —
the worker owns 100% of the tenant's Graph budget and the in-process `tenant_controller` AIMD loop is the
single source of truth for the 429 response. `GraphTenantBudgetService::workerShare()` and the
`ms365_graph_tenant_budget` cross-process reconciliation are **deleted** (or kept only as a ceiling hint).

### 4.4 Checkpoint / resume semantics

- Children already persist `delta_states` on success (`DeltaStateRepository`) and mid-run via
  `checkpoint_delta_states`. **No change to the resume primitive.**
- On batch loss/requeue: the new owner reads the child manifest, **skips children already `success`**,
  and resumes the rest from their last checkpoint. Whale enumeration progress is preserved exactly as
  today, but now without per-child reaping.

### 4.5 Reaper simplification (the big deletion)

Replace the per-child reaper suite with **one** batch reaper:

```
if batch.status == running and now - batch.last_heartbeat_at > BATCH_HEARTBEAT_GAP:
    requeue whole batch (status=queued, clear owner/lease, attempts+1) if attempts < max
    else terminal-fail the batch
```

Because the owner emits a single heartbeat **independent of Graph throttling** (heartbeat ≠ Graph
activity), a throttled-but-alive worker keeps heartbeating while it waits out `Retry-After`. Liveness is
now directly observable, so the entire throttle-shield apparatus is unnecessary:

**Delete / retire after migration:**
- `isThrottledWaitingAlive`, `isThrottledWaitingAliveFromRow`, `recentlyThrottled` liveness fallbacks
- `shouldSkipThrottleReaper`, `shouldReapRunningChild` per-child logic
- `releaseOrphanedClaimsForIdleNode/ForNode/ForAllNodes`
- `reconcileExhaustedRunningClaims`, `recoverStaleRunning`, `reconcileZombieRuns` (per-child paths)
- `countRunningForTenant` / `countsAgainstTenantWorkloadCap` / `perTenantMaxConcurrentWorkloads`
- the threshold zoo (120/180/600/1200/1800s) reduces to **one** `BATCH_HEARTBEAT_GAP` (+ a `max_run`
  backstop).

`kopia.stall_seconds` (worker-side upload/enumeration watchdog) is **kept** — it's a legitimate
in-process stuck-detector, not a cross-process liveness guess.

### 4.6 Fix the `Run is not active.` race by construction

With batch ownership, `ms365_worker_graph_token.php` authorizes against the **batch lease**, not per-child
run status. A worker holding a valid batch lease is by definition authorized to refresh its token; the
endpoint returns a token (or a soft `retry_after`) and **never** 500s a live owner. The race that
produced `graph 401 after token refresh ... Run is not active.` cannot occur because no other actor
requeues children out from under the owner.

### 4.7 Whale handling (single very large tenant)

A single tenant batch is now bounded by **one CT's** RAM/CPU. This is acceptable because Graph throttling
already caps a single tenant's effective throughput. Options in priority order:

1. **Default:** accept single-owner; tune the owner's internal `max_concurrent_runs`,
   `graph_parallel_requests`, `graph_folder_parallel`, `graph_sharepoint_drive_parallel`.
2. **Vertical:** route known whales to a larger CT class.
3. **Bounded multi-owner (escape hatch, only if needed):** allow a whale batch to be split across **2–3**
   owners by a **static, disjoint partition** (e.g. by `resource_type` band or hashed user range), each
   owner getting an explicit **fixed** Graph budget fraction. This is fundamentally different from today's
   dynamic DB-divided budget: partitions are disjoint and fixed, so there's no shared limiter to starve
   and no cross-process budget reconciliation. Treat as a rare, opt-in exception.

### 4.8 Concurrency knobs after redesign

| Knob | Status | Meaning |
|------|--------|---------|
| `max_concurrent_runs` (worker `config.yaml`) | **kept** | child workloads run in parallel **within** the owner |
| `graph_parallel_requests`, `graph_folder_parallel`, `graph_sharepoint_drive_parallel` | **kept** | in-owner Graph parallelism |
| `tenant_controller` AIMD | **kept, now authoritative** | the only Graph 429 governor for the tenant |
| `platformMaxConcurrent` | **redefined** | max concurrent **tenant batches** fleet-wide |
| max batches per node | **new** (default 1) | how many distinct tenant batches one CT may own |
| `perTenantMaxConcurrentWorkloads` | **deleted** | no longer meaningful |
| `ms365_per_tenant_max_concurrent` / `ms365_graph_tenant_budget` | **deleted / advisory** | budget no longer divided |

---

## 5. Migration plan (phased)

Each phase is independently shippable and reversible. The dev WHMCS + fleet are the test bed.

### Phase 0 — Spec & scaffolding (no behavior change)
- Land this doc; add `ms365_batch_claims` migration (`sql/upgrade_phaseNN_tenant_owner.sql`) **unused**.
- Add a feature flag `ms365_claim_unit` = `child` (default) | `batch` in addon settings.
- Add `FleetSettings`/`Ms365EngineConfig` accessor for the flag.

### Phase 1 — Control-plane batch claim (behind flag)
- Implement `claimNextBatch`, batch lease in `ms365_batch_claims`, batch reaper, and the new
  `ms365_worker_batch_*.php` endpoints.
- Keep the old per-child path fully working when flag = `child`.
- PHP unit tests: batch claim atomicity, single-owner-per-tenant invariant, batch reaper requeue/terminal,
  fair batch ranking, token-refresh authorized-by-lease.

### Phase 2 — Worker `BatchRunner` (behind worker config)
- New `internal/jobs/batch_runner.go` + `ClaimBatch`/`BatchProgress`/`BatchComplete`/`BatchRelease` API
  client methods; reuse `Runner.Run` per child unchanged.
- Worker reads claim-unit mode from claim response (control plane tells the worker which protocol to use),
  so a mixed fleet during rollout is safe.
- Go tests: batch runner runs N children, resumes skipping `success` children, single tenant controller,
  coalesced progress, drain hand-off releases batch with checkpoints intact.

### Phase 3 — Shadow / canary
- Flip `ms365_claim_unit=batch` for **one** test tenant (or a canary worker) on dev.
- Run the whale batch (`39b9838c…`-class) and a multi-tenant mix.
- Verify: no `Recovering this workload`, no `Run is not active.`, one heartbeat per batch, DB POST volume
  down by ~1–2 orders of magnitude, throttle handled entirely in-worker.

### Phase 4 — Fleet rollout
- Flip the flag fleet-wide on dev; soak across several whale + tail-end runs.
- Roll the worker binary that defaults to batch protocol.

### Phase 5 — Remove dead code
- Delete the per-child reaper suite, budget division, `perTenantMaxConcurrentWorkloads`, and the
  threshold zoo (§4.5). Demote `ms365_job_queue` per-row lease columns to child-manifest only.
- Update `MS365_WORKER_FLEET.md`, `ARCHITECTURE.md`, `PROGRESS.md`.

### Phase 6 — (optional) coordination off MySQL
- Only if batch-level POST volume still pressures the DB: move the high-frequency owner heartbeat/progress
  to a lighter channel. Likely unnecessary after §4.2 coalescing.

---

## 6. Development process

### 6.1 Branching & environment
- Branch from current dev tip: `feature/ms365-tenant-owner-claim`.
- All work on the **development** WHMCS + dev fleet (prod fleet untouched; dual-fleet stays as-is).
- Bump `ms365backup_config()['version']` per phase that ships a migration (Phase 0, Phase 5).
- Worker version bump per phase that ships Go changes (Phase 2, Phase 4).

### 6.2 Testing strategy
- **PHP:** extend `tests/` with `ms365_batch_claim_test.php` (atomic claim, single-owner invariant,
  reaper requeue/terminal, lease renewal, token auth-by-lease). Keep existing reaper tests green while the
  flag defaults to `child`.
- **Go:** `internal/jobs/batch_runner_test.go` (N-children run, resume-skip-success, single controller,
  coalesced progress, drain hand-off). Keep `scheduler_*_test.go` green.
- **Integration (dev fleet):** scripted whale run + multi-tenant mix; assert on DB POST counts, heartbeat
  cardinality, absence of requeue churn.

### 6.3 Verification checklist (per phase)
- `php -l` on all touched PHP; PHP test suite under `modules/addons/ms365backup/tests/`.
- `go build ./... && go test ./...` in `ms365-backup-worker`.
- Fleet smoke: `php bin/ms365_fleet_smoke.php`.
- Live-page check on a real multi-workload batch (workloads advance; no "Recovering this workload").

### 6.4 Rollout & rollback
- Rollout is gated by `ms365_claim_unit`. **Rollback = flip the flag to `child`** and (if needed) redeploy
  the prior worker; both protocols coexist until Phase 5.
- Do not delete the per-child path or run Phase 5 until batch mode has soaked through multiple whale + tail
  runs without requeue churn.

### 6.5 Definition of done (acceptance criteria)
1. A whale tenant batch completes with **zero** `Recovering this workload` events and **zero**
   `Run is not active.` token errors.
2. Exactly **one** lease + heartbeat exists per active tenant batch (verified in `ms365_batch_claims`).
3. Worker loss mid-batch requeues the **whole batch**; the new owner resumes and skips already-`success`
   children (no lost enumeration progress).
4. Graph 429 handling is entirely in-worker; no `ms365_graph_tenant_budget` division remains in the hot
   path.
5. `ms365_worker_progress` POST volume per run drops by ≥10× vs. the child-claim baseline.
6. Tail-end of large batches completes without stranded `Queued` workloads.
7. Per-child reaper suite + `perTenantMaxConcurrentWorkloads` + threshold zoo are removed (Phase 5) with
   tests green.

---

## 7. Risks & mitigations

| Risk | Mitigation |
|------|------------|
| Single whale bounded by one CT | Internal parallelism tuning; vertical CT sizing; bounded static multi-owner escape hatch (§4.7) |
| Owner dies late in a huge batch → full re-claim | Children resume from checkpoints (skip `success`); only unfinished children re-run |
| Reduced cross-tenant fleet utilization if few tenants, many workers | Batches-per-node >1 (§4.8) lets one CT own several **small** tenant batches; whales still get a dedicated owner |
| Mixed-protocol fleet during rollout | Control plane tells worker which protocol via claim response; both paths coexist until Phase 5 |
| Migration of in-flight runs at cutover | Cut over on an idle fleet; drain active child-claim runs before flipping the flag |
| `Ms365BatchLiveService` assumptions | UI already aggregates children → parent; only the *source* of child updates changes (batched POST), not the schema |

---

## 8. Open questions (decide before Phase 1)

1. **Batches per node:** start at **1** (simplest, strongest isolation) or allow small-tenant packing
   (>1) from day one? Recommendation: start at 1, add packing in Phase 4 if utilization demands.
2. **Table vs overload:** confirm Option A (`ms365_batch_claims`) over Option B (overload
   `ms365_job_queue`). Recommendation: Option A.
3. **Budget service fate:** delete `GraphTenantBudgetService` outright, or keep it as an advisory ceiling
   the worker may read but not divide? Recommendation: keep as optional ceiling, stop dividing.
4. **Whale escape hatch:** implement bounded multi-owner now or defer until a tenant actually needs it?
   Recommendation: defer; design the partition key but don't build until needed.

---

## 9. Relationship to existing docs

- `MS365_WORKER_FLEET.md` — fleet provisioning/build/deploy is unchanged; the **claim/concurrency** and
  **reaper** sections are superseded by this doc once Phase 5 lands.
- `ARCHITECTURE.md` — execution model (PHP control plane + Go worker) unchanged; claim granularity updated.
- `PROGRESS.md` — log each phase; the reaper/throttle session entries describe the problem this redesign
  removes.
