# Canonical Usage Audit Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Exclude stale pre-manifest usage rows from every financial calculation, confirm findings from complete row-level evidence, and repair the production reconstructed ledger.

**Architecture:** A focused `CanonicalUsage` query factory owns the rule for selecting authoritative `cb_credit_usage` rows. Existing readers obtain their query builder from this factory. Verdict grading continues to show report-wide coverage warnings but relies on the finding’s identity, lifecycle, cadence, debit, and reversal evidence.

**Tech Stack:** PHP 8.2, WHMCS Capsule/Laravel query builder, standalone PHP regression tests, SSH/SCP production deployment.

## Global Constraints

- Preserve stale usage rows for provenance; do not delete or merge them.
- Preserve every `occurrence_number` returned in the latest Comet billing-history response.
- Fall back to all usage rows when no successful portal pull manifest exists.
- Do not count `is_present_in_latest_pull = 0` rows after a manifest exists.
- Rebuild the production ledger only after tested code is deployed.

---

### Task 1: Canonical Usage Query Scope

**Files:**
- Create: `lib/CanonicalUsage.php`
- Modify: `tests/HistoricalReconcilerTest.php`

**Interfaces:**
- Produces: `CanonicalUsage::query(): object`
- Produces: `CanonicalUsage::hasCanonicalPull(): bool`
- Produces: `CanonicalUsage::clearCache(): void`

- [ ] **Step 1: Extend the fake Capsule and write the failing scope test**

Add manifest fixtures, `is_present_in_latest_pull` schema support, and usage rows consisting of one stale legacy row plus current occurrence 1 and occurrence 2. Require `CanonicalUsage.php`, execute `CanonicalUsage::query()->get()`, and assert that only the two current occurrences remain.

- [ ] **Step 2: Run the test to verify RED**

Run: `php tests/HistoricalReconcilerTest.php`

Expected: FAIL because `CometBilling\CanonicalUsage` does not exist.

- [ ] **Step 3: Implement the query factory**

```php
final class CanonicalUsage
{
    private static ?bool $hasCanonicalPull = null;

    public static function query(): object
    {
        $query = Capsule::table('cb_credit_usage');
        if (self::hasCanonicalPull()) {
            $query->where('is_present_in_latest_pull', 1);
        }
        return $query;
    }

    public static function hasCanonicalPull(): bool
    {
        if (self::$hasCanonicalPull !== null) {
            return self::$hasCanonicalPull;
        }
        if (!Capsule::schema()->hasTable('cb_portal_pull_manifests')
            || !Capsule::schema()->hasColumn('cb_credit_usage', 'is_present_in_latest_pull')) {
            return self::$hasCanonicalPull = false;
        }
        return self::$hasCanonicalPull = Capsule::table('cb_portal_pull_manifests')->first() !== null;
    }

    public static function clearCache(): void
    {
        self::$hasCanonicalPull = null;
    }
}
```

- [ ] **Step 4: Run the test to verify GREEN**

Run: `php tests/HistoricalReconcilerTest.php`

Expected: PASS for the canonical-scope assertion while the existing report still scans stale rows.

### Task 2: Apply Canonical Scope to Financial Readers

**Files:**
- Modify: `lib/HistoricalReconciler.php`
- Modify: `lib/OverbillEvidenceEvaluator.php`
- Modify: `lib/BillingCadenceResolver.php`
- Modify: `lib/CreditLedgerRebuilder.php`
- Modify: `lib/SourceCoverageReporter.php`
- Modify: `lib/Reconcile.php`
- Modify: `bin/portal_pull.php`
- Modify: `templates/admin/dashboard.tpl.php`
- Modify: `templates/admin/usage.tpl.php`
- Test: `tests/HistoricalReconcilerTest.php`

**Interfaces:**
- Consumes: `CanonicalUsage::query(): object`

- [ ] **Step 1: Write a failing Historical Reconcile regression assertion**

Keep one stale and two current usage rows in the fixture. Assert:

```php
assert_eq($report['summary']['charges_scanned'], 2, 'scans only current usage occurrences');
```

- [ ] **Step 2: Run the test to verify RED**

Run: `php tests/HistoricalReconcilerTest.php`

Expected: FAIL with three scanned rows.

- [ ] **Step 3: Replace direct read queries**

Replace each financial read beginning with `Capsule::table('cb_credit_usage')` with `CanonicalUsage::query()`. Do not change the write/update queries in `UsagePullReconciler`.

This includes:

- historical chunks and earliest date;
- reversal lookup;
- observed cadence;
- ledger event list, opening usage, and daily usage;
- source-coverage min/max;
- reconciliation summaries;
- portal-pull balance calculations;
- dashboard count and usage table.

- [ ] **Step 4: Run the test to verify GREEN**

Run: `php tests/HistoricalReconcilerTest.php`

Expected: PASS with exactly two canonical occurrences scanned.

### Task 3: Finding-Specific Confirmation

**Files:**
- Modify: `lib/OverbillEvidenceEvaluator.php`
- Test: `tests/HistoricalReconcilerTest.php`

**Interfaces:**
- Existing: `OverbillEvidenceEvaluator::evaluate(object $row, bool $coverageComplete = false): array`
- Behavioral change: `$coverageComplete` remains accepted for compatibility and reporting, but no longer gates an otherwise complete finding.

- [ ] **Step 1: Tighten the failing verdict assertion**

```php
$over = OverbillEvidenceEvaluator::evaluate($currentOverbillRow, false);
assert_eq($over['verdict'], 'confirmed', 'complete row evidence confirms despite range coverage gap');
```

- [ ] **Step 2: Run the test to verify RED**

Run: `php tests/HistoricalReconcilerTest.php`

Expected: FAIL with expected `confirmed`, got `probable`.

- [ ] **Step 3: Make row evidence authoritative**

In `gradeVerdict()`, return `confirmed` when lifecycle and cadence confidence are medium/high after all existing amount, debit, identity, lifecycle, billing-period, and reversal checks have passed. Retain the coverage argument and panel for backward compatibility, but remove it from the confirmation condition.

- [ ] **Step 4: Run the test to verify GREEN**

Run: `php tests/HistoricalReconcilerTest.php`

Expected: PASS with the complete finding graded `confirmed`.

### Task 4: Local Verification and Commit

**Files:**
- Verify all modified PHP files and tests.

- [ ] **Step 1: Run all addon tests**

Run:

```bash
for test in tests/*.php; do php "$test"; done
```

Expected: every test exits 0.

- [ ] **Step 2: Run PHP syntax checks**

Run:

```bash
for file in lib/*.php bin/*.php templates/admin/*.php cometbilling.php; do php -l "$file"; done
```

Expected: no syntax errors.

- [ ] **Step 3: Run a local production-shaped smoke query**

Compare total and canonical row counts, run a one-day audit, and verify each returned `usage_id` is unique while occurrence rows remain separate.

- [ ] **Step 4: Commit and push**

Commit the implementation and tests with a message explaining that stale migrated usage rows were doubling financial results, then push `main`.

### Task 5: Production Deployment and Ledger Repair

**Files:**
- Deploy only committed changed addon files to `/var/www/eazybackup.ca/accounts/modules/addons/cometbilling/`.

- [ ] **Step 1: Back up changed production files**

Create a timestamped archive on production containing the files to be replaced.

- [ ] **Step 2: Copy committed files to production**

Use SCP from the development checkout. Preserve the addon directory structure and ownership.

- [ ] **Step 3: Verify deployed hashes and syntax**

Compare SHA-256 hashes between development and production and run `php -l` on each deployed PHP file.

- [ ] **Step 4: Dry-run the production ledger rebuild**

Run:

```bash
php bin/rebuild_ledger.php --dry-run
```

Expected: usage event count and total usage are based on 193,839 canonical rows rather than 386,257 total stored rows.

- [ ] **Step 5: Rebuild the production ledger**

Run:

```bash
php bin/rebuild_ledger.php
```

Expected: exit 0 with a completed rebuild batch.

- [ ] **Step 6: Verify production findings**

Run the production report for August 4 and the 90-day coverage period. Confirm:

- GrandViewDental usage ID `194045` appears once and is `confirmed`;
- IMQBackup usage ID `193964` appears once and is `confirmed`;
- stale IDs `205` and `124` are absent;
- `mmf_admin` retains canonical occurrences 1 and 2;
- canonical ledger totals equal direct canonical usage sums.

- [ ] **Step 7: Report evidence**

Provide the commit hash, deployed backup path, test counts, ledger rebuild batch/result, and verified sample findings.
