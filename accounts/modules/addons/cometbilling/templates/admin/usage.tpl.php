<?php
use CometBilling\CanonicalUsage;
use CometBilling\HistoricalReconciler;

$baseUrl = 'addonmodules.php?module=cometbilling&action=usage';
$preset = $_GET['preset'] ?? null;
$fromInput = $_GET['from'] ?? null;
$toInput = $_GET['to'] ?? null;

// Default to last 30 days when no range is provided.
if ($fromInput === null && $toInput === null && $preset === null) {
    $preset = 30;
}

$range = HistoricalReconciler::resolveDateRange($preset, $fromInput, $toInput);
$from = $range['from'];
$to = $range['to'];
$rowCap = 2000;

$chargeCount = (int) CanonicalUsage::query()->whereBetween('usage_date', [$from, $to])->count();
$creditConsumed = (float) CanonicalUsage::query()->whereBetween('usage_date', [$from, $to])->sum('amount');

$rows = CanonicalUsage::query()
    ->whereBetween('usage_date', [$from, $to])
    ->orderBy('usage_date', 'desc')
    ->orderBy('id', 'desc')
    ->limit($rowCap)
    ->get();

function usagePresetLink(string $baseUrl, $days, $activePreset, string $label): string
{
    $isActive = ((string) $activePreset === (string) $days);
    $class = $isActive ? 'btn btn-primary' : 'btn btn-default';
    $param = $days === 'all' ? 'all' : (int) $days;

    return '<a href="' . htmlspecialchars($baseUrl . '&preset=' . $param) . '" class="' . $class . '">'
        . htmlspecialchars($label) . '</a>';
}
?>
<style>
.cb-usage { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
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
</style>

<div class="cb-usage">
    <h3>Credit Usage</h3>

    <div class="cb-box">
        <h4>Date Range</h4>
        <div class="cb-filter-row">
            <?= usagePresetLink($baseUrl, 30, $range['preset'], 'Last 30 days') ?>
            <?= usagePresetLink($baseUrl, 90, $range['preset'], 'Last 90 days') ?>
            <?= usagePresetLink($baseUrl, 365, $range['preset'], 'Last 365 days') ?>
            <?= usagePresetLink($baseUrl, 'all', $range['preset'], 'All history') ?>
        </div>
        <form method="get" class="cb-form-inline">
            <input type="hidden" name="module" value="cometbilling">
            <input type="hidden" name="action" value="usage">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>" required>
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>" required>
            <button type="submit" class="btn btn-primary">Apply</button>
        </form>
        <p class="cb-muted">Period: <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?> (UTC). Totals use canonical Bill History rows only.</p>

        <div class="cb-summary">
            <div class="cb-stat">
                <div class="value">$<?= number_format($creditConsumed, 2) ?></div>
                <div class="label">Credit consumed</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format($chargeCount) ?></div>
                <div class="label">Charges in period</div>
            </div>
        </div>
    </div>

    <div class="cb-box">
        <h4>Usage Detail</h4>
        <?php if ($chargeCount > $rowCap): ?>
        <p class="cb-muted">Showing the latest <?= number_format($rowCap) ?> of <?= number_format($chargeCount) ?> charges. Credit consumed above includes the full period.</p>
        <?php endif; ?>
        <?php if ($chargeCount === 0): ?>
        <p class="cb-muted">No usage charges in this period.</p>
        <?php else: ?>
        <table class="datatable" width="100%">
            <thead>
                <tr>
                    <th>Usage Date</th>
                    <th>Posted</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Amount</th>
                    <th>Tenant</th>
                    <th>Device</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $r->usage_date) ?></td>
                    <td><?= htmlspecialchars((string) ($r->posted_at ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($r->item_type ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($r->item_desc ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($r->quantity ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($r->unit_cost ?? '')) ?></td>
                    <td>$<?= number_format((float) ($r->amount ?? 0), 2) ?></td>
                    <td><?= htmlspecialchars((string) ($r->tenant_id ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($r->device_id ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
