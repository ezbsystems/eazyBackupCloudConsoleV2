<?php
use CometBilling\PeriodCompareReport;

$baseUrl = 'addonmodules.php?module=cometbilling&action=period_compare';
$bucketFilter = $_GET['bucket'] ?? 'all';
$preset = $_GET['preset'] ?? null;

$ranges = PeriodCompareReport::resolveRanges(
    isset($_GET['from_a']) ? (string) $_GET['from_a'] : null,
    isset($_GET['to_a']) ? (string) $_GET['to_a'] : null,
    isset($_GET['from_b']) ? (string) $_GET['from_b'] : null,
    isset($_GET['to_b']) ? (string) $_GET['to_b'] : null,
    $preset !== null ? (string) $preset : null
);

$report = PeriodCompareReport::report(
    $ranges['period_a']['from'],
    $ranges['period_a']['to'],
    $ranges['period_b']['from'],
    $ranges['period_b']['to'],
    $bucketFilter
);

$spend = $report['spend'];
$drivers = $report['drivers'];
$snapshots = $report['snapshots'];

function periodCompareBucketLink(
    string $baseUrl,
    array $periodA,
    array $periodB,
    string $bucket,
    string $label,
    string $active
): string {
    $class = ($active === $bucket) ? 'btn btn-primary btn-sm' : 'btn btn-default btn-sm';
    $url = $baseUrl
        . '&from_a=' . urlencode($periodA['from'])
        . '&to_a=' . urlencode($periodA['to'])
        . '&from_b=' . urlencode($periodB['from'])
        . '&to_b=' . urlencode($periodB['to'])
        . '&bucket=' . urlencode($bucket);

    return '<a href="' . htmlspecialchars($url) . '" class="' . $class . '">' . htmlspecialchars($label) . '</a>';
}

function periodCompareDriverLabel(string $class): string
{
    switch ($class) {
        case PeriodCompareReport::DRIVER_NEW_IN_B:
            return 'New in B';
        case PeriodCompareReport::DRIVER_GONE_IN_A:
            return 'Gone in A';
        case PeriodCompareReport::DRIVER_CONTINUING:
            return 'Continuing';
        default:
            return $class;
    }
}
?>
<style>
.cb-period-compare { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin: 20px 0; }
.cb-box h4 { margin: 0 0 15px 0; }
.cb-filter-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 15px; }
.cb-form-inline label { margin-right: 6px; font-size: 13px; }
.cb-form-inline input[type="date"] { margin-right: 12px; }
.cb-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 15px 0 0; }
.cb-stat { background: #f9fafb; border: 1px solid #e5e5e5; border-radius: 8px; padding: 15px; text-align: center; }
.cb-stat .value { font-size: 22px; font-weight: 700; color: #1a73e8; }
.cb-stat .value.positive { color: #137333; }
.cb-stat .value.negative { color: #c5221f; }
.cb-stat .label { font-size: 12px; color: #666; margin-top: 4px; }
.cb-muted { color: #666; font-size: 12px; margin-top: 10px; }
.cb-period-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 768px) { .cb-period-grid { grid-template-columns: 1fr; } }
.cb-delta-pos { color: #137333; }
.cb-delta-neg { color: #c5221f; }
.cb-driver-new { color: #137333; }
.cb-driver-gone { color: #c5221f; }
</style>

<div class="cb-period-compare">
    <h3>Period Compare</h3>
    <p class="cb-muted">
        Compares canonical Bill History credit spend between two periods by category and billable identity.
        Active Services shows portal run-rate snapshots nearest each period end (not cumulative spend).
    </p>

    <div class="cb-box">
        <h4>Date Ranges</h4>
        <div class="cb-filter-row">
            <a href="<?= htmlspecialchars($baseUrl . '&preset=prior_two_months&bucket=' . urlencode($bucketFilter)) ?>"
               class="btn btn-default">Prior two months</a>
        </div>
        <form method="get" class="cb-form-inline">
            <input type="hidden" name="module" value="cometbilling">
            <input type="hidden" name="action" value="period_compare">
            <input type="hidden" name="bucket" value="<?= htmlspecialchars($bucketFilter) ?>">
            <div class="cb-period-grid">
                <div>
                    <strong>Period A</strong><br>
                    <label for="from_a">From</label>
                    <input type="date" id="from_a" name="from_a" value="<?= htmlspecialchars($ranges['period_a']['from']) ?>" required>
                    <label for="to_a">To</label>
                    <input type="date" id="to_a" name="to_a" value="<?= htmlspecialchars($ranges['period_a']['to']) ?>" required>
                </div>
                <div>
                    <strong>Period B</strong><br>
                    <label for="from_b">From</label>
                    <input type="date" id="from_b" name="from_b" value="<?= htmlspecialchars($ranges['period_b']['from']) ?>" required>
                    <label for="to_b">To</label>
                    <input type="date" id="to_b" name="to_b" value="<?= htmlspecialchars($ranges['period_b']['to']) ?>" required>
                </div>
            </div>
            <div style="margin-top: 12px;">
                <button type="submit" class="btn btn-primary">Compare</button>
            </div>
        </form>
    </div>

    <div class="cb-box">
        <h4>Credit Spend Summary</h4>
        <div class="cb-summary">
            <div class="cb-stat">
                <div class="value">$<?= number_format((float) $spend['total_a'], 2) ?></div>
                <div class="label">Period A total<br><?= htmlspecialchars($ranges['period_a']['from']) ?> – <?= htmlspecialchars($ranges['period_a']['to']) ?></div>
            </div>
            <div class="cb-stat">
                <div class="value">$<?= number_format((float) $spend['total_b'], 2) ?></div>
                <div class="label">Period B total<br><?= htmlspecialchars($ranges['period_b']['from']) ?> – <?= htmlspecialchars($ranges['period_b']['to']) ?></div>
            </div>
            <?php
            $delta = (float) $spend['delta'];
            $deltaClass = $delta >= 0 ? 'positive' : 'negative';
            ?>
            <div class="cb-stat">
                <div class="value <?= $deltaClass ?>"><?= $delta >= 0 ? '+' : '' ?>$<?= number_format($delta, 2) ?></div>
                <div class="label">Delta (B − A)</div>
            </div>
        </div>

        <table class="datatable" width="100%" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Period A</th>
                    <th>Period B</th>
                    <th>Delta</th>
                    <th>% change</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($spend['categories'] as $row): ?>
                <?php
                $rowDelta = (float) $row['delta'];
                $deltaClass = $rowDelta >= 0 ? 'cb-delta-pos' : 'cb-delta-neg';
                $pct = $row['pct_change'];
                ?>
                <tr>
                    <td><?= htmlspecialchars((string) $row['category_label']) ?></td>
                    <td>$<?= number_format((float) $row['amount_a'], 2) ?></td>
                    <td>$<?= number_format((float) $row['amount_b'], 2) ?></td>
                    <td class="<?= $deltaClass ?>"><?= $rowDelta >= 0 ? '+' : '' ?>$<?= number_format($rowDelta, 2) ?></td>
                    <td><?= $pct === null ? '—' : (($pct >= 0 ? '+' : '') . number_format((float) $pct, 1) . '%') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="cb-box">
        <h4>Item Drivers</h4>
        <div class="cb-filter-row">
            <?= periodCompareBucketLink($baseUrl, $ranges['period_a'], $ranges['period_b'], 'all', 'All', $bucketFilter) ?>
            <?= periodCompareBucketLink($baseUrl, $ranges['period_a'], $ranges['period_b'], 'devices', 'Devices', $bucketFilter) ?>
            <?= periodCompareBucketLink($baseUrl, $ranges['period_a'], $ranges['period_b'], 'boosters', 'Boosters', $bucketFilter) ?>
            <?= periodCompareBucketLink($baseUrl, $ranges['period_a'], $ranges['period_b'], 'm365', 'M365', $bucketFilter) ?>
        </div>

        <?php $driverItems = $drivers['items']; ?>
        <?php if (count($driverItems) === 0): ?>
        <p class="cb-muted">No billable identities<?= $bucketFilter !== 'all' ? ' for this filter' : '' ?> in these periods.</p>
        <?php else: ?>
        <p class="cb-muted">
            Showing <?= number_format(count($driverItems)) ?> of <?= number_format((int) $drivers['total_identities']) ?> identities,
            sorted by |delta|.
            <?php if (!empty($drivers['capped'])): ?>
            Table capped at <?= number_format(PeriodCompareReport::DETAIL_ROW_CAP) ?> rows; rows with |delta| ≥ $<?= number_format(PeriodCompareReport::DELTA_FLOOR, 2) ?> are prioritized.
            <?php endif; ?>
            Charge counts ≥ 2 on monthly items may indicate calendar double-renewals.
        </p>
        <table class="datatable" width="100%">
            <thead>
                <tr>
                    <th>Class</th>
                    <th>Category</th>
                    <th>Tenant</th>
                    <th>Device</th>
                    <th>Description</th>
                    <th>A $</th>
                    <th>B $</th>
                    <th>Delta</th>
                    <th>A charges</th>
                    <th>B charges</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($driverItems as $item): ?>
                <?php
                $driverClass = (string) ($item['driver_class'] ?? '');
                $classCss = $driverClass === PeriodCompareReport::DRIVER_NEW_IN_B ? 'cb-driver-new'
                    : ($driverClass === PeriodCompareReport::DRIVER_GONE_IN_A ? 'cb-driver-gone' : '');
                $itemDelta = (float) ($item['delta'] ?? 0);
                $itemDeltaCss = $itemDelta >= 0 ? 'cb-delta-pos' : 'cb-delta-neg';
                ?>
                <tr>
                    <td class="<?= $classCss ?>"><?= htmlspecialchars(periodCompareDriverLabel($driverClass)) ?></td>
                    <td><?= htmlspecialchars((string) ($item['category_label'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['tenant_id'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['device_id'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['item_desc'] ?? '')) ?></td>
                    <td>$<?= number_format((float) ($item['amount_a'] ?? 0), 2) ?></td>
                    <td>$<?= number_format((float) ($item['amount_b'] ?? 0), 2) ?></td>
                    <td class="<?= $itemDeltaCss ?>"><?= $itemDelta >= 0 ? '+' : '' ?>$<?= number_format($itemDelta, 2) ?></td>
                    <td><?= number_format((int) ($item['charge_count_a'] ?? 0)) ?></td>
                    <td><?= number_format((int) ($item['charge_count_b'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="cb-box">
        <h4>Active Services Snapshot Compare</h4>
        <p class="cb-muted">
            Portal run-rate nearest each period end (±48h).
            <?php if ($snapshots['snapshot_a']): ?>
            Period A snapshot: <?= htmlspecialchars((string) $snapshots['snapshot_a']) ?>.
            <?php else: ?>
            No Period A snapshot within tolerance (target <?= htmlspecialchars((string) $snapshots['target_a']) ?>).
            <?php endif; ?>
            <?php if ($snapshots['snapshot_b']): ?>
            Period B snapshot: <?= htmlspecialchars((string) $snapshots['snapshot_b']) ?>.
            <?php else: ?>
            No Period B snapshot within tolerance (target <?= htmlspecialchars((string) $snapshots['target_b']) ?>).
            <?php endif; ?>
        </p>

        <?php if ($snapshots['snapshot_a'] === null && $snapshots['snapshot_b'] === null): ?>
        <p class="cb-muted">No Active Services snapshots available for comparison.</p>
        <?php else: ?>
        <table class="datatable" width="100%">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>A count</th>
                    <th>B count</th>
                    <th>Count Δ</th>
                    <th>A run-rate $</th>
                    <th>B run-rate $</th>
                    <th>Amount Δ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($snapshots['rows'] as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $row['label']) ?></td>
                    <td><?= $row['count_a'] === null ? '—' : number_format((float) $row['count_a'], 0) ?></td>
                    <td><?= $row['count_b'] === null ? '—' : number_format((float) $row['count_b'], 0) ?></td>
                    <td><?= $row['count_delta'] === null ? '—' : (($row['count_delta'] >= 0 ? '+' : '') . number_format((float) $row['count_delta'], 0)) ?></td>
                    <td><?= $row['amount_a'] === null ? '—' : '$' . number_format((float) $row['amount_a'], 2) ?></td>
                    <td><?= $row['amount_b'] === null ? '—' : '$' . number_format((float) $row['amount_b'], 2) ?></td>
                    <td><?= $row['amount_delta'] === null ? '—' : (($row['amount_delta'] >= 0 ? '+' : '') . '$' . number_format((float) $row['amount_delta'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total run-rate</th>
                    <th colspan="3"></th>
                    <td><?= $snapshots['total_amount_a'] === null ? '—' : '$' . number_format((float) $snapshots['total_amount_a'], 2) ?></td>
                    <td><?= $snapshots['total_amount_b'] === null ? '—' : '$' . number_format((float) $snapshots['total_amount_b'], 2) ?></td>
                    <td><?= $snapshots['total_amount_delta'] === null ? '—' : (($snapshots['total_amount_delta'] >= 0 ? '+' : '') . '$' . number_format((float) $snapshots['total_amount_delta'], 2)) ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
</div>
