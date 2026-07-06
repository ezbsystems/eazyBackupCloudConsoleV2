<?php
use CometBilling\M365BoosterReport;

$baseUrl = 'addonmodules.php?module=cometbilling&action=m365_report';

$preset = isset($_GET['preset']) ? (int) $_GET['preset'] : null;
$fromInput = $_GET['from'] ?? null;
$toInput = $_GET['to'] ?? null;

$range = M365BoosterReport::resolveDateRange($preset, $fromInput, $toInput);
$report = M365BoosterReport::report($range['from'], $range['to']);

function m365PresetLink(string $baseUrl, int $days, ?int $activePreset): string
{
    $class = ($activePreset === $days) ? 'btn btn-primary' : 'btn btn-default';
    return '<a href="' . htmlspecialchars($baseUrl . '&preset=' . $days) . '" class="' . $class . '">Last ' . $days . ' days</a>';
}
?>
<style>
.cb-m365 { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin: 20px 0; }
.cb-box h4 { margin: 0 0 15px 0; }
.cb-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 15px 0; }
.cb-stat { background: #f9fafb; border: 1px solid #e5e5e5; border-radius: 8px; padding: 15px; text-align: center; }
.cb-stat .value { font-size: 22px; font-weight: 700; color: #1a73e8; }
.cb-stat .label { font-size: 12px; color: #666; margin-top: 4px; }
.cb-table { width: 100%; border-collapse: collapse; }
.cb-table th, .cb-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
.cb-table th { background: #f9fafb; font-weight: 600; font-size: 12px; text-transform: uppercase; }
.cb-table tr:hover { background: #f9fafb; }
.cb-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.cb-filter-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 15px; }
.cb-muted { color: #666; font-size: 12px; margin-top: 10px; }
.cb-form-inline label { margin-right: 6px; font-size: 13px; }
.cb-form-inline input[type="date"] { margin-right: 12px; }
</style>

<div class="cb-m365">
    <h3>M365 Protected Accounts Report</h3>
    <p class="cb-muted">Booster (Microsoft 365) Protected Accounts billing from Comet Portal active-services snapshots.</p>

    <div class="cb-box">
        <h4>Date Range</h4>
        <div class="cb-filter-row">
            <?= m365PresetLink($baseUrl, 30, $range['preset']) ?>
            <?= m365PresetLink($baseUrl, 60, $range['preset']) ?>
            <?= m365PresetLink($baseUrl, 90, $range['preset']) ?>
        </div>
        <form method="get" class="cb-form-inline">
            <input type="hidden" name="module" value="cometbilling">
            <input type="hidden" name="action" value="m365_report">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= htmlspecialchars($range['from']) ?>" required>
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= htmlspecialchars($range['to']) ?>" required>
            <button type="submit" class="btn btn-default">Apply</button>
        </form>
        <p class="cb-muted">Period: <?= htmlspecialchars($range['from']) ?> to <?= htmlspecialchars($range['to']) ?> (UTC)</p>
    </div>

    <?php if (!empty($report['message'])): ?>
    <div class="errorbox"><?= htmlspecialchars($report['message']) ?></div>
    <?php else: ?>
    <div class="cb-box">
        <h4>Summary (latest snapshot in period)</h4>
        <p class="cb-muted">
            Amounts reflect the latest portal active-services snapshot in the selected period
            (monthly billing estimate, not cumulative charges across the period).
        </p>
        <div class="cb-summary">
            <div class="cb-stat">
                <div class="value"><?= htmlspecialchars($report['snapshot_at'] ?? '—') ?></div>
                <div class="label">Snapshot (UTC)</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $report['total_accounts']) ?></div>
                <div class="label">Total Protected Accounts</div>
            </div>
            <div class="cb-stat">
                <div class="value">$<?= number_format((float) $report['total_amount'], 2) ?></div>
                <div class="label">Estimated Monthly Billing</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $report['line_count']) ?></div>
                <div class="label">Line Items</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $report['snapshot_count']) ?></div>
                <div class="label">Snapshots in Period</div>
            </div>
        </div>
    </div>

    <div class="cb-box">
        <h4>Detail</h4>
        <?php if (empty($report['items'])): ?>
        <p class="cb-muted">No M365 Protected Accounts booster lines in this snapshot.</p>
        <?php else: ?>
        <table class="cb-table">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Device</th>
                    <th class="num">Protected Accounts</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($report['items'] as $item): ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($item['account'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string) ($item['device_id'] ?? '—')) ?></td>
                    <td class="num"><?= number_format((int) $item['protected_accounts']) ?></td>
                    <td class="num">$<?= number_format((float) $item['amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2">Total</th>
                    <th class="num"><?= number_format((int) $report['total_accounts']) ?></th>
                    <th class="num">$<?= number_format((float) $report['total_amount'], 2) ?></th>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <p>
        <a href="<?= htmlspecialchars('addonmodules.php?module=cometbilling&action=sync') ?>" class="btn btn-default">Data Sync</a>
        <a href="<?= htmlspecialchars('addonmodules.php?module=cometbilling') ?>" class="btn btn-default">Dashboard</a>
    </p>
</div>
