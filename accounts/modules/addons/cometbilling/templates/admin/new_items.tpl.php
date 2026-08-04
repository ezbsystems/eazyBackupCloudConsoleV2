<?php
use CometBilling\HistoricalReconciler;
use CometBilling\NewItemsReport;

$baseUrl = 'addonmodules.php?module=cometbilling&action=new_items';
$preset = $_GET['preset'] ?? null;
$fromInput = $_GET['from'] ?? null;
$toInput = $_GET['to'] ?? null;
$bucketFilter = $_GET['bucket'] ?? 'all';

if ($fromInput === null && $toInput === null && $preset === null) {
    $preset = 30;
}

$range = HistoricalReconciler::resolveDateRange($preset, $fromInput, $toInput);
$report = NewItemsReport::report($range['from'], $range['to'], $bucketFilter);
$counts = $report['counts'];

function newItemsPresetLink(string $baseUrl, $days, $activePreset, string $label): string
{
    $isActive = ((string) $activePreset === (string) $days);
    $class = $isActive ? 'btn btn-primary' : 'btn btn-default';
    $param = $days === 'all' ? 'all' : (int) $days;

    return '<a href="' . htmlspecialchars($baseUrl . '&preset=' . $param) . '" class="' . $class . '">'
        . htmlspecialchars($label) . '</a>';
}

function newItemsBucketLink(string $baseUrl, string $from, string $to, ?string $preset, string $bucket, string $label, string $active): string
{
    $class = ($active === $bucket) ? 'btn btn-primary btn-sm' : 'btn btn-default btn-sm';
    $url = $baseUrl . '&from=' . urlencode($from) . '&to=' . urlencode($to) . '&bucket=' . urlencode($bucket);
    if ($preset !== null && $preset !== '') {
        $url .= '&preset=' . urlencode((string) $preset);
    }

    return '<a href="' . htmlspecialchars($url) . '" class="' . $class . '">' . htmlspecialchars($label) . '</a>';
}
?>
<style>
.cb-new-items { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin: 20px 0; }
.cb-box h4 { margin: 0 0 15px 0; }
.cb-filter-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 15px; }
.cb-form-inline label { margin-right: 6px; font-size: 13px; }
.cb-form-inline input[type="date"] { margin-right: 12px; }
.cb-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 15px 0 0; }
.cb-stat { background: #f9fafb; border: 1px solid #e5e5e5; border-radius: 8px; padding: 15px; text-align: center; }
.cb-stat .value { font-size: 22px; font-weight: 700; color: #1a73e8; }
.cb-stat .label { font-size: 12px; color: #666; margin-top: 4px; }
.cb-muted { color: #666; font-size: 12px; margin-top: 10px; }
.cb-breakdown { margin-top: 12px; font-size: 13px; color: #444; }
.cb-breakdown span { display: inline-block; margin-right: 14px; }
</style>

<div class="cb-new-items">
    <h3>New Items</h3>
    <p class="cb-muted">
        Counts identities whose <strong>first</strong> canonical Bill History charge falls in the selected period.
        Renewals and daily re-bills are excluded. Boosters = Hyper-V / VMware / Proxmox / Disk Image / MS SQL.
    </p>

    <div class="cb-box">
        <h4>Date Range</h4>
        <div class="cb-filter-row">
            <?= newItemsPresetLink($baseUrl, 30, $range['preset'], 'Last 30 days') ?>
            <?= newItemsPresetLink($baseUrl, 90, $range['preset'], 'Last 90 days') ?>
            <?= newItemsPresetLink($baseUrl, 365, $range['preset'], 'Last 365 days') ?>
            <?= newItemsPresetLink($baseUrl, 'all', $range['preset'], 'All history') ?>
        </div>
        <form method="get" class="cb-form-inline">
            <input type="hidden" name="module" value="cometbilling">
            <input type="hidden" name="action" value="new_items">
            <input type="hidden" name="bucket" value="<?= htmlspecialchars($bucketFilter) ?>">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= htmlspecialchars($range['from']) ?>" required>
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= htmlspecialchars($range['to']) ?>" required>
            <button type="submit" class="btn btn-primary">Apply</button>
        </form>
        <p class="cb-muted">Period: <?= htmlspecialchars($range['from']) ?> to <?= htmlspecialchars($range['to']) ?> (UTC)</p>

        <div class="cb-summary">
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $counts['devices']) ?></div>
                <div class="label">New devices</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $counts['boosters']) ?></div>
                <div class="label">New boosters</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $counts['m365']) ?></div>
                <div class="label">New M365</div>
            </div>
        </div>
        <?php if ((int) $counts['boosters'] > 0): ?>
        <div class="cb-breakdown">
            <?php foreach ($report['booster_breakdown'] as $cat => $n): ?>
                <?php if ((int) $n > 0): ?>
                <span><?= htmlspecialchars(\CometBilling\ChargeCategoryResolver::label($cat)) ?>: <?= number_format((int) $n) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="cb-box">
        <h4>Detail</h4>
        <?php $totalInPeriod = (int) $counts['devices'] + (int) $counts['boosters'] + (int) $counts['m365']; ?>
        <div class="cb-filter-row">
            <?= newItemsBucketLink($baseUrl, $range['from'], $range['to'], $range['preset'], 'all', 'All (' . number_format($totalInPeriod) . ')', $bucketFilter) ?>
            <?= newItemsBucketLink($baseUrl, $range['from'], $range['to'], $range['preset'], 'devices', 'Devices (' . number_format((int)$counts['devices']) . ')', $bucketFilter) ?>
            <?= newItemsBucketLink($baseUrl, $range['from'], $range['to'], $range['preset'], 'boosters', 'Boosters (' . number_format((int)$counts['boosters']) . ')', $bucketFilter) ?>
            <?= newItemsBucketLink($baseUrl, $range['from'], $range['to'], $range['preset'], 'm365', 'M365 (' . number_format((int)$counts['m365']) . ')', $bucketFilter) ?>
        </div>

        <?php $detailCount = count($report['items']); ?>
        <?php if ($detailCount === 0): ?>
        <p class="cb-muted">No new items in this period<?= $bucketFilter !== 'all' ? ' for this filter' : '' ?>.</p>
        <?php else: ?>
        <p class="cb-muted">Showing <?= number_format($detailCount) ?> item<?= $detailCount === 1 ? '' : 's' ?>.</p>
        <table class="datatable" width="100%">
            <thead>
                <tr>
                    <th>First billed</th>
                    <th>Bucket</th>
                    <th>Category</th>
                    <th>Tenant</th>
                    <th>Device</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>First amount</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($report['items'] as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $item['first_billed']) ?></td>
                    <td><?= htmlspecialchars((string) $item['bucket']) ?></td>
                    <td><?= htmlspecialchars((string) $item['category_label']) ?></td>
                    <td><?= htmlspecialchars((string) ($item['tenant_id'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['device_id'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['item_desc'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['quantity'] ?? '')) ?></td>
                    <td>$<?= number_format((float) ($item['amount'] ?? 0), 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
