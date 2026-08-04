# Reconcile Billing Period Model Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix portal-only reconcile billing status so devices use registration-aligned 30-day periods and boosters use daily remove-day rules (not `revoked_at + 30`).

**Architecture:** Extract pure period math into `BillingPeriodCalculator` (unit-tested without WHMCS). Wire it into `DeviceMatcher::enrichPortalOnlyRows` with category branching (devices vs boosters), RegistrationTime from `comet_devices.content`, next_due walk-back fallback, and optional inventory lookback for booster removal. UI/README consume the same `billing_status` / `overbill_amount` fields.

**Tech Stack:** PHP 8.x, WHMCS Capsule, existing CometBilling addon (no new dependencies).

## Global Constraints

- Do **not** use `revoked_at + cycleDays` as device expected end.
- Do **not** invent registration from `comet_devices.created_at`.
- Prefer `RegistrationTime` when &gt; 0; else walk back from portal `next_due` by cycle.
- Device period: inclusive `[start, end]` where `end = start + cycleDays`, next period starts `end + 1 day`.
- Boosters: last billable day = remove date; overbilled if portal snapshot day &gt; remove date.
- Keep existing status string values: `expected_grace`, `overbilled_past_grace`, `unknown`.
- Keep overbill dollars = full portal line `amount` when overbilled (no pro-rate this phase).
- Do not rewrite saved reconciliation reports; fresh Reconcile required.
- Spec: `accounts/modules/addons/cometbilling/docs/superpowers/specs/2026-08-04-reconcile-billing-period-design.md`

## File map

| File | Role |
|------|------|
| `lib/BillingPeriodCalculator.php` | Pure device period + status helpers (no Capsule) |
| `lib/DeviceMatcher.php` | Enrichment: category branch, RegistrationTime, booster remove, inventory lookback |
| `tests/BillingPeriodCalculatorTest.php` | CLI assert script for period math |
| `templates/admin/reconcile_report_partial.tpl.php` | Show registered_at; daily cycle for boosters |
| `README.md` | Correct billing model docs |

---

### Task 1: BillingPeriodCalculator (pure period math)

**Files:**
- Create: `accounts/modules/addons/cometbilling/lib/BillingPeriodCalculator.php`
- Create: `accounts/modules/addons/cometbilling/tests/BillingPeriodCalculatorTest.php`

**Interfaces:**
- Produces:
  - `BillingPeriodCalculator::deviceExpectedEnd(?string $registrationDate, string $revokedDate, int $cycleDays, ?string $nextDueDate): ?string`
  - `BillingPeriodCalculator::deviceBillingStatus(?string $expectedEnd, ?string $nextDueDate): string`
  - `BillingPeriodCalculator::boosterBillingStatus(?string $removeDate, string $snapshotDate): string`
  - `BillingPeriodCalculator::dateOnly(string $dateTimeOrDate): string`

- [ ] **Step 1: Write the failing test script**

Create `accounts/modules/addons/cometbilling/tests/BillingPeriodCalculatorTest.php`:

```php
<?php
/**
 * Run: php tests/BillingPeriodCalculatorTest.php
 */
require_once __DIR__ . '/../lib/BillingPeriodCalculator.php';

use CometBilling\BillingPeriodCalculator;

function assert_eq($actual, $expected, string $label): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL {$label}: expected " . var_export($expected, true) . " got " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "OK {$label}\n";
}

// Worked example from spec: reg 2026-07-06, revoke 2026-08-01, cycle 30 → end 2026-08-05
assert_eq(
    BillingPeriodCalculator::deviceExpectedEnd('2026-07-06', '2026-08-01', 30, null),
    '2026-08-05',
    'reg mid-cycle expected end'
);

// Must NOT be revoked+30 (that would be 2026-08-31)
assert_eq(
    BillingPeriodCalculator::deviceExpectedEnd('2026-07-06', '2026-08-01', 30, null) === '2026-08-31',
    false,
    'not revoked-plus-cycle'
);

// Boundary: revoke on expected end still in period
assert_eq(
    BillingPeriodCalculator::deviceExpectedEnd('2026-07-06', '2026-08-05', 30, null),
    '2026-08-05',
    'revoke on period end day'
);

// Next period: revoke day after end uses following period
assert_eq(
    BillingPeriodCalculator::deviceExpectedEnd('2026-07-06', '2026-08-06', 30, null),
    '2026-09-05', // next period start 2026-08-06 + 30 days
    'revoke starts next period'
);

// No reg: walk back from next_due. next_due=2026-08-06, cycle=30 → period [2026-07-07, 2026-08-06]
// revoke 2026-06-27 is in prior period ending 2026-07-07
assert_eq(
    BillingPeriodCalculator::deviceExpectedEnd(null, '2026-06-27', 30, '2026-08-06'),
    '2026-07-07',
    'next_due walk-back period containing revoke'
);

// Status: next_due after expected end → overbilled
assert_eq(
    BillingPeriodCalculator::deviceBillingStatus('2026-07-27', '2026-08-06'),
    'overbilled_past_grace',
    'device overbilled'
);
assert_eq(
    BillingPeriodCalculator::deviceBillingStatus('2026-08-06', '2026-08-06'),
    'expected_grace',
    'device still in period'
);
assert_eq(
    BillingPeriodCalculator::deviceBillingStatus(null, '2026-08-06'),
    'unknown',
    'device unknown'
);

// Booster daily
assert_eq(
    BillingPeriodCalculator::boosterBillingStatus('2026-07-06', '2026-07-06'),
    'expected_grace',
    'booster last day still expected'
);
assert_eq(
    BillingPeriodCalculator::boosterBillingStatus('2026-07-06', '2026-07-07'),
    'overbilled_past_grace',
    'booster day after remove overbilled'
);
assert_eq(
    BillingPeriodCalculator::boosterBillingStatus(null, '2026-07-07'),
    'unknown',
    'booster unknown'
);

echo "All BillingPeriodCalculator tests passed.\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
php /var/www/eazybackup.ca/accounts/modules/addons/cometbilling/tests/BillingPeriodCalculatorTest.php
```

Expected: FAIL (class not found or similar)

- [ ] **Step 3: Implement BillingPeriodCalculator**

Create `accounts/modules/addons/cometbilling/lib/BillingPeriodCalculator.php`:

```php
<?php
namespace CometBilling;

/**
 * Pure Comet billing period helpers (no DB).
 *
 * Devices: inclusive periods [start, start+cycleDays], next period starts end+1 day.
 * Boosters: remove date is last billable day.
 */
class BillingPeriodCalculator
{
    public static function dateOnly(string $dateTimeOrDate): string
    {
        return substr($dateTimeOrDate, 0, 10);
    }

    /**
     * Expected billing end for a revoked device (Y-m-d), or null if unknown.
     */
    public static function deviceExpectedEnd(
        ?string $registrationDate,
        string $revokedDate,
        int $cycleDays,
        ?string $nextDueDate
    ): ?string {
        $cycleDays = $cycleDays > 0 ? $cycleDays : 30;
        $revoked = self::dateOnly($revokedDate);

        if ($registrationDate !== null && $registrationDate !== '') {
            $reg = self::dateOnly($registrationDate);
            $end = self::periodEndContaining($reg, $revoked, $cycleDays);
            if ($end !== null) {
                return $end;
            }
        }

        if ($nextDueDate !== null && $nextDueDate !== '') {
            return self::walkBackFromNextDue(self::dateOnly($nextDueDate), $revoked, $cycleDays);
        }

        return null;
    }

    public static function deviceBillingStatus(?string $expectedEnd, ?string $nextDueDate): string
    {
        if ($expectedEnd === null || $expectedEnd === '') {
            return 'unknown';
        }
        if ($nextDueDate === null || $nextDueDate === '') {
            return 'unknown';
        }
        $nextDue = self::dateOnly($nextDueDate);
        $end = self::dateOnly($expectedEnd);

        return $nextDue > $end ? 'overbilled_past_grace' : 'expected_grace';
    }

    public static function boosterBillingStatus(?string $removeDate, string $snapshotDate): string
    {
        if ($removeDate === null || $removeDate === '') {
            return 'unknown';
        }
        $remove = self::dateOnly($removeDate);
        $snap = self::dateOnly($snapshotDate);

        return $snap > $remove ? 'overbilled_past_grace' : 'expected_grace';
    }

    /**
     * From registration anchor, find inclusive period end containing $revoked.
     */
    private static function periodEndContaining(string $regDate, string $revoked, int $cycleDays): ?string
    {
        if ($revoked < $regDate) {
            return null;
        }

        $start = $regDate;
        // Cap iterations (e.g. 40 years of monthly-ish cycles)
        for ($i = 0; $i < 500; $i++) {
            $end = date('Y-m-d', strtotime($start . ' +' . $cycleDays . ' days'));
            if ($revoked >= $start && $revoked <= $end) {
                return $end;
            }
            if ($revoked < $start) {
                return null;
            }
            $start = date('Y-m-d', strtotime($end . ' +1 day'));
        }

        return null;
    }

    /**
     * Periods ending at nextDue, nextDue-cycle, ... until one contains revoked.
     * Period ending on $periodEnd has start = periodEnd - cycleDays.
     */
    private static function walkBackFromNextDue(string $nextDue, string $revoked, int $cycleDays): ?string
    {
        $periodEnd = $nextDue;
        for ($i = 0; $i < 500; $i++) {
            $start = date('Y-m-d', strtotime($periodEnd . ' -' . $cycleDays . ' days'));
            if ($revoked >= $start && $revoked <= $periodEnd) {
                return $periodEnd;
            }
            if ($revoked > $periodEnd) {
                // revoke after known next_due horizon — treat next_due as too early; unknown
                return null;
            }
            $periodEnd = date('Y-m-d', strtotime($start . ' -1 day'));
        }

        return null;
    }
}
```

**Note:** Adjust the `2026-09-04` expected value in the test if `Jul 6 + 30 = Aug 5`, next start `Aug 6`, end `Sep 5` — compute with PHP once and lock the test to that exact string:

```bash
php -r 'echo date("Y-m-d", strtotime("2026-08-06 +30 days")), "\n";'
```

Update the test assertion to match that output before considering the test final.

- [ ] **Step 4: Run tests and fix until all pass**

Run:

```bash
php /var/www/eazybackup.ca/accounts/modules/addons/cometbilling/tests/BillingPeriodCalculatorTest.php
```

Expected: `All BillingPeriodCalculator tests passed.`

- [ ] **Step 5: Commit**

```bash
git add accounts/modules/addons/cometbilling/lib/BillingPeriodCalculator.php \
  accounts/modules/addons/cometbilling/tests/BillingPeriodCalculatorTest.php
git commit -m "$(cat <<'EOF'
Add BillingPeriodCalculator for registration-aligned device periods.

Pure helpers encode device inclusive cycle ends and daily booster remove-day status without DB access.
EOF
)"
```

---

### Task 2: Wire DeviceMatcher enrichment (devices + revoked RegistrationTime)

**Files:**
- Modify: `accounts/modules/addons/cometbilling/lib/DeviceMatcher.php`

**Interfaces:**
- Consumes: `BillingPeriodCalculator::{deviceExpectedEnd,deviceBillingStatus,dateOnly}`
- Produces: portal-only rows with `registered_at`, correct `expected_billing_end`, `billing_status`, `overbill_amount` for `devices` category

- [ ] **Step 1: Pass category into enrichment**

In `matchCategory`, change:

```php
$portalOnly = self::enrichPortalOnlyRows($portalOnly);
```

to:

```php
$portalOnly = self::enrichPortalOnlyRows($portalOnly, $categoryKey, $snapshotDateForEnrich ?? null);
```

If `matchCategory` does not currently receive a snapshot date, add an optional parameter:

```php
public static function matchCategory(
    string $categoryKey,
    array $portalItems,
    array $serverInventory,
    bool $applyCap = true,
    ?string $snapshotDate = null
): array {
```

Callers in `Reconciler.php` that know the snapshot date should pass it (needed for boosters in Task 3). For this task, devices can ignore snapshot date.

Update every `DeviceMatcher::matchCategory(...)` call site in `Reconciler.php` to pass `$snapshotDate` when available (from compare / export paths).

- [ ] **Step 2: Load RegistrationTime in revoked index**

In `loadRevokedDeviceIndex`, select `content` as well and parse:

```php
$rows = Capsule::table('comet_devices')
    ->whereNotNull('revoked_at')
    ->select(['hash', 'username', 'name', 'revoked_at', 'content'])
    ->get();

// inside loop:
$registrationAt = null;
$content = json_decode((string) ($row->content ?? ''), true);
if (is_array($content)) {
    $rt = (int) ($content['RegistrationTime'] ?? 0);
    if ($rt > 0) {
        $registrationAt = gmdate('Y-m-d', $rt);
    }
}
$entry = [
    'hash' => $hash,
    'username' => (string) $row->username,
    'name' => $row->name,
    'revoked_at' => (string) $row->revoked_at,
    'registered_at' => $registrationAt,
];
```

- [ ] **Step 3: Replace revoke+30 logic for devices**

Rewrite `enrichPortalOnlyRows` signature and device branch:

```php
private static function enrichPortalOnlyRows(
    array $portalOnly,
    string $categoryKey,
    ?string $snapshotDate = null
): array {
    // ...
    $isBooster = $categoryKey !== 'devices';

    foreach ($portalOnly as $i => $row) {
        $revoked = self::findRevokedDevice($row, $index);
        $cycleDays = (int) ($row['billing_cycle_days'] ?? 30) ?: 30;
        $nextDue = $row['next_due_date'] ?? null;

        if (!$isBooster) {
            // Device path
            if ($revoked === null) {
                $portalOnly[$i]['billing_status'] = 'unknown';
                $portalOnly[$i]['overbill_amount'] = 0.0;
                continue;
            }
            $revokedAt = (string) $revoked['revoked_at'];
            $registeredAt = $revoked['registered_at'] ?? null;
            $expectedEnd = BillingPeriodCalculator::deviceExpectedEnd(
                $registeredAt,
                $revokedAt,
                $cycleDays,
                $nextDue
            );
            $status = BillingPeriodCalculator::deviceBillingStatus($expectedEnd, $nextDue);

            $portalOnly[$i]['revoked_at'] = $revokedAt;
            $portalOnly[$i]['registered_at'] = $registeredAt;
            $portalOnly[$i]['expected_billing_end'] = $expectedEnd;
            $portalOnly[$i]['billing_status'] = $status;
            $portalOnly[$i]['overbill_amount'] = $status === 'overbilled_past_grace'
                ? (float) ($row['amount'] ?? 0)
                : 0.0;
            // friendly_name fill unchanged
            continue;
        }

        // Booster path filled in Task 3 — for now leave a clear stub:
        // set unknown unless revoked_at present (minimal), completed next task
    }
    return $portalOnly;
}
```

Delete old `computeBillingStatus` that uses `revoked_at + cycleDays`, or make it call `BillingPeriodCalculator` (prefer delete to avoid dual paths).

- [ ] **Step 4: Smoke-check device expected end via CLI**

Run (after WHMCS init + autoload pattern from `bin/run_reconciliation.php`):

```bash
cd /var/www/eazybackup.ca/accounts/modules/addons/cometbilling && php -r '
require_once dirname(__DIR__, 3) . "/init.php";
require_once __DIR__ . "/vendor/autoload.php";
spl_autoload_register(function ($class) {
    $prefix = "CometBilling\\";
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $file = __DIR__ . "/lib/" . str_replace("\\", "/", substr($class, strlen($prefix))) . ".php";
    if (file_exists($file)) require_once $file;
});
use CometBilling\Reconciler;
$report = Reconciler::compare();
$devices = $report["items"]["devices"]["unmatched"]["portal_only"] ?? [];
foreach ($devices as $row) {
    if (($row["account"] ?? "") === "KaizaCorp" || str_contains((string)($row["device_id"] ?? ""), "7bef57")) {
        echo json_encode([
            "account" => $row["account"] ?? null,
            "revoked_at" => $row["revoked_at"] ?? null,
            "registered_at" => $row["registered_at"] ?? null,
            "next_due" => $row["next_due_date"] ?? null,
            "expected_end" => $row["expected_billing_end"] ?? null,
            "status" => $row["billing_status"] ?? null,
        ], JSON_PRETTY_PRINT), "\n";
    }
}
'
```

Expected: `expected_end` is **not** `revoked_at + 30`. For KaizaCorp revoke `2026-06-27` with next_due `2026-08-06`, walk-back should yield period end around `2026-07-07` (exact date from calculator tests).

- [ ] **Step 5: Commit**

```bash
git add accounts/modules/addons/cometbilling/lib/DeviceMatcher.php \
  accounts/modules/addons/cometbilling/lib/Reconciler.php
git commit -m "$(cat <<'EOF'
Use registration-aligned periods for device reconcile billing status.

Stop treating revoked_at+cycle as expected end; prefer RegistrationTime and fall back to next_due walk-back.
EOF
)"
```

---

### Task 3: Booster remove-day status + inventory lookback

**Files:**
- Modify: `accounts/modules/addons/cometbilling/lib/DeviceMatcher.php`

**Interfaces:**
- Consumes: `BillingPeriodCalculator::boosterBillingStatus`
- Produces: booster portal-only rows with `expected_billing_end` = remove date, daily semantics, inventory-based remove when possible

Booster category keys: `hyperv_vms`, `vmware_vms`, `proxmox_vms`, `disk_image`, `mssql`, `m365_accounts`.

- [ ] **Step 1: Implement remove-date resolution**

Add private helpers on `DeviceMatcher`:

```php
private static function resolveBoosterRemoveDate(
    array $row,
    ?array $revoked,
    string $categoryKey,
    ?string $snapshotDate
): ?string {
    if ($revoked !== null && !empty($revoked['revoked_at'])) {
        return BillingPeriodCalculator::dateOnly((string) $revoked['revoked_at']);
    }

    $deviceId = (string) ($row['server_device_id'] ?? '');
    if ($deviceId === '' || $snapshotDate === null || $snapshotDate === '') {
        return null;
    }

    return self::findBoosterDisappearedDate($deviceId, $categoryKey, $snapshotDate);
}

/**
 * Last snapshot_date where category qty > 0 before a later snapshot shows 0 or host missing.
 */
private static function findBoosterDisappearedDate(
    string $deviceId,
    string $categoryKey,
    string $asOfSnapshotDate
): ?string {
    if (!Capsule::schema()->hasTable('cb_server_device_inventory')) {
        return null;
    }
    $allowed = ['hyperv_vms','vmware_vms','proxmox_vms','disk_image','mssql','m365_accounts'];
    if (!in_array($categoryKey, $allowed, true)) {
        return null;
    }

    $rows = Capsule::table('cb_server_device_inventory')
        ->where('device_id', $deviceId)
        ->where('snapshot_date', '<=', $asOfSnapshotDate)
        ->orderBy('snapshot_date', 'asc')
        ->get(['snapshot_date', $categoryKey]);

    $lastPositive = null;
    $prevQty = null;
    $prevDate = null;
    foreach ($rows as $r) {
        $qty = (int) $r->{$categoryKey};
        $date = (string) $r->snapshot_date;
        if ($prevDate !== null && $prevQty > 0 && $qty <= 0) {
            return $prevDate; // last day we saw booster present
        }
        if ($qty > 0) {
            $lastPositive = $date;
        }
        $prevQty = $qty;
        $prevDate = $date;
    }

    // Host vanished from inventory after last positive day
    if ($lastPositive !== null && $lastPositive < $asOfSnapshotDate) {
        $laterExists = Capsule::table('cb_server_device_inventory')
            ->where('snapshot_date', '>', $lastPositive)
            ->where('snapshot_date', '<=', $asOfSnapshotDate)
            ->where('device_id', $deviceId)
            ->exists();
        if (!$laterExists) {
            // Check any inventory exists on a later date for other devices (snapshot was taken)
            $snapshotTaken = Capsule::table('cb_server_device_inventory')
                ->where('snapshot_date', '>', $lastPositive)
                ->where('snapshot_date', '<=', $asOfSnapshotDate)
                ->exists();
            if ($snapshotTaken) {
                return $lastPositive;
            }
        }
    }

    return null;
}
```

- [ ] **Step 2: Complete booster branch in enrichPortalOnlyRows**

```php
// Booster path
$removeDate = self::resolveBoosterRemoveDate($row, $revoked, $categoryKey, $snapshotDate);
$snap = $snapshotDate ?: gmdate('Y-m-d');
$status = BillingPeriodCalculator::boosterBillingStatus($removeDate, $snap);

if ($revoked !== null) {
    $portalOnly[$i]['revoked_at'] = (string) $revoked['revoked_at'];
    $portalOnly[$i]['registered_at'] = $revoked['registered_at'] ?? null;
    if (empty($portalOnly[$i]['friendly_name']) && !empty($revoked['name'])) {
        $portalOnly[$i]['friendly_name'] = $revoked['name'];
    }
    $portalOnly[$i]['device_name'] = $revoked['name'] ?? null;
}

$portalOnly[$i]['billing_cycle_days'] = 1; // daily
$portalOnly[$i]['expected_billing_end'] = $removeDate;
$portalOnly[$i]['billing_status'] = $status;
$portalOnly[$i]['overbill_amount'] = $status === 'overbilled_past_grace'
    ? (float) ($row['amount'] ?? 0)
    : 0.0;
```

If `$revoked === null` and remove date still found via inventory, still set status from remove date (do not force `unknown`).

- [ ] **Step 3: Ensure Reconciler passes snapshotDate into matchCategory**

In `Reconciler::buildReport` / `collectOverbilledPastGraceRows`, pass the active server snapshot date string into `DeviceMatcher::matchCategory(...)`.

- [ ] **Step 4: Smoke-check a booster portal-only row**

```bash
cd /var/www/eazybackup.ca/accounts/modules/addons/cometbilling && php -r '
# same bootstrap as Task 2
use CometBilling\Reconciler;
$report = Reconciler::compare();
foreach (["hyperv_vms","disk_image","mssql","m365_accounts"] as $cat) {
  foreach ($report["items"][$cat]["unmatched"]["portal_only"] ?? [] as $row) {
    if (($row["billing_status"] ?? "") === "overbilled_past_grace") {
      echo $cat, " ", json_encode([
        "account"=>$row["account"]??null,
        "revoked_at"=>$row["revoked_at"]??null,
        "expected_end"=>$row["expected_billing_end"]??null,
        "cycle"=>$row["billing_cycle_days"]??null,
        "overbill"=>$row["overbill_amount"]??null,
      ]), "\n";
      break 2;
    }
  }
}
'
```

Expected: booster overbill rows show `expected_end` = remove/revoke **date** (not +30), `cycle` = 1.

- [ ] **Step 5: Commit**

```bash
git add accounts/modules/addons/cometbilling/lib/DeviceMatcher.php \
  accounts/modules/addons/cometbilling/lib/Reconciler.php
git commit -m "$(cat <<'EOF'
Treat booster reconcile overbill as daily from remove day.

Use host revoked_at or inventory disappearance as last billable day instead of a 30-day grace window.
EOF
)"
```

---

### Task 4: UI + README

**Files:**
- Modify: `accounts/modules/addons/cometbilling/templates/admin/reconcile_report_partial.tpl.php`
- Modify: `accounts/modules/addons/cometbilling/README.md`

- [ ] **Step 1: Update portal-only billing columns**

In the `$showBilling` header section, change columns to:

```php
echo '<th>Registered</th><th>Revoked / removed</th><th>Cycle</th><th>Next due</th><th>Expected end</th><th>Billing status</th><th>Overbill $</th>';
```

In the row body:

```php
echo '<td>' . htmlspecialchars(!empty($row['registered_at']) ? substr((string)$row['registered_at'], 0, 10) : '—') . '</td>';
echo '<td>' . htmlspecialchars($row['revoked_at'] ? substr((string)$row['revoked_at'], 0, 19) : (!empty($row['expected_billing_end']) ? (string)$row['expected_billing_end'] : '—')) . '</td>';
$cycle = (int)($row['billing_cycle_days'] ?? 30);
echo '<td>' . ($cycle <= 1 ? 'daily' : (string)$cycle) . '</td>';
echo '<td>' . htmlspecialchars($row['next_due_date'] ?? '—') . '</td>';
echo '<td>' . htmlspecialchars($row['expected_billing_end'] ?? '—') . '</td>';
// status + overbill unchanged
```

Update the drilldown help line that currently says “Comet bills ~30 days after revoke” to:

```php
echo '<p style="font-size: 11px; color: #666; margin: 0 0 8px;">Devices: billed on registration-aligned cycles; expected end is the period containing revoke. Boosters: billed daily; remove day is last billable.</p>';
```

- [ ] **Step 2: Update README revoked-device section**

Replace the paragraph at `README.md` (~lines 143–147) with:

```markdown
**Revoked / removed billing:** Portal-only rows are enriched from `comet_devices` (and inventory history for boosters).

- **Devices:** Expected billing end is the end of the registration-aligned period that contained `revoked_at` (`RegistrationTime` when available; otherwise walk back from portal `next_due` by cycle days). Status `expected_grace` if portal `next_due` is still within that end; `overbilled_past_grace` if `next_due` is past it.
- **Boosters** (Hyper-V, VMware, Proxmox, Disk Image, MSSQL, M365): Billed daily. Last billable day is host `revoked_at` or the last inventory day the booster was present. Still portal-only after that day → `overbilled_past_grace`.
- `unknown` — cannot determine registration/period or remove date.
```

- [ ] **Step 3: Visual/CLI verify**

Re-run reconcile JSON and confirm Devices past-grace overbill still populates; spot-check one device row shows `registered_at` when RegistrationTime exists.

- [ ] **Step 4: Commit**

```bash
git add accounts/modules/addons/cometbilling/templates/admin/reconcile_report_partial.tpl.php \
  accounts/modules/addons/cometbilling/README.md
git commit -m "$(cat <<'EOF'
Update reconcile UI and README for registration-aligned billing rules.

Show registered/remove dates and daily booster cycles; document the corrected Comet billing model.
EOF
)"
```

---

### Task 5: End-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Unit tests still pass**

```bash
php /var/www/eazybackup.ca/accounts/modules/addons/cometbilling/tests/BillingPeriodCalculatorTest.php
```

Expected: all OK.

- [ ] **Step 2: Full reconcile smoke**

```bash
cd /var/www/eazybackup.ca/accounts/modules/addons/cometbilling && php bin/run_reconciliation.php --json 2>/dev/null | php -r '
$j=json_decode(stream_get_contents(STDIN),true);
echo "past_grace_overbill=" . ($j["summary"]["past_grace_overbill"] ?? "?") . "\n";
foreach ($j["items"] as $k=>$i) {
  if (!empty($i["past_grace_overbill"])) {
    echo "$k count={$i["past_grace_count"]} overbill={$i["past_grace_overbill"]}\n";
  }
}
'
```

Expected: non-zero totals still make sense; device expected ends no longer equal revoke+30 for spot-checked accounts.

- [ ] **Step 3: CSV still exports overbill_amount**

```bash
cd /var/www/eazybackup.ca/accounts/modules/addons/cometbilling && php -r '
# bootstrap...
use CometBilling\Reconciler;
$csv = Reconciler::buildOverbilledPastGraceCsv();
$lines = explode("\n", trim($csv));
echo $lines[0], "\n";
echo "rows=", count($lines)-1, "\n";
'
```

Expected: header includes `overbill_amount`; row count &gt; 0.

- [ ] **Step 4: Final commit only if verification prompted doc tweaks; otherwise done**

No empty commit. If README needed a small fix from verification, amend only if commit was yours and unpushed per git rules — otherwise new commit.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Device expected end = period containing revoke | Task 1–2 |
| Prefer RegistrationTime; fallback next_due walk | Task 1–2 |
| Device status vs next_due | Task 1–2 |
| Booster daily; remove day last billable | Task 1, 3 |
| Remove date = revoked_at then inventory | Task 3 |
| Keep status strings + overbill amount semantics | Task 2–3 |
| UI registered/daily + README | Task 4 |
| Re-run verify; no saved-report rewrite | Task 5 |
| Non-goals (pro-rate, credit_usage rebuild) | Out of plan |

## Placeholder / consistency notes

- `matchCategory` optional `$snapshotDate` must be plumbed from all Reconciler call sites that enrich boosters.
- Test date `2026-09-04` for next-period end must be locked to actual `strtotime('2026-08-06 +30 days')` output before Task 1 is marked done.
- Status label text in UI may still say “past grace”; semantics now mean “past expected billing end” — acceptable this phase (spec keeps string values).
