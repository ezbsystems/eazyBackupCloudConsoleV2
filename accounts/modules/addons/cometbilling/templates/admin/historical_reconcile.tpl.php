<?php
use CometBilling\HistoricalReconciler;

$baseUrl = 'addonmodules.php?module=cometbilling&action=historical_reconcile';
$exportBase = 'addonmodules.php?module=cometbilling&action=historical_reconcile_export';

$preset = $_GET['preset'] ?? null;
$fromInput = $_GET['from'] ?? null;
$toInput = $_GET['to'] ?? null;
$includeGrace = !empty($_GET['include_grace']);

$range = HistoricalReconciler::resolveDateRange($preset, $fromInput, $toInput);
$report = HistoricalReconciler::report($range['from'], $range['to'], $includeGrace);

function histPresetLink(string $baseUrl, $days, $activePreset, string $label): string
{
    $isActive = ($activePreset === $days);
    $class = $isActive ? 'btn btn-primary' : 'btn btn-default';
    $param = $days === 'all' ? 'all' : (int) $days;

    return '<a href="' . htmlspecialchars($baseUrl . '&preset=' . $param) . '" class="' . $class . '">' . htmlspecialchars($label) . '</a>';
}

$exportUrl = $exportBase
    . '&from=' . urlencode($range['from'])
    . '&to=' . urlencode($range['to']);
?>
<style>
.cb-hist { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin: 20px 0; }
.cb-box h4 { margin: 0 0 15px 0; }
.cb-banner { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 16px; margin: 0 0 16px; color: #1e3a5f; font-size: 13px; }
.cb-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin: 15px 0; }
.cb-stat { background: #f9fafb; border: 1px solid #e5e5e5; border-radius: 8px; padding: 15px; text-align: center; }
.cb-stat .value { font-size: 22px; font-weight: 700; color: #1a73e8; }
.cb-stat .value.warn { color: #dc2626; }
.cb-stat .label { font-size: 12px; color: #666; margin-top: 4px; }
.cb-table { width: 100%; border-collapse: collapse; }
.cb-table th, .cb-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e5e5; font-size: 13px; }
.cb-table th { background: #f9fafb; font-weight: 600; font-size: 12px; text-transform: uppercase; }
.cb-table tr:hover { background: #f9fafb; }
.cb-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.cb-filter-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 15px; }
.cb-muted { color: #666; font-size: 12px; margin-top: 10px; }
.cb-form-inline label { margin-right: 6px; font-size: 13px; }
.cb-form-inline input[type="date"] { margin-right: 12px; }
.cb-sortable { cursor: pointer; user-select: none; }
.cb-sortable:hover { background: #eef2ff; }
.cb-sortable::after { content: ''; opacity: 0.35; margin-left: 4px; }
.cb-sortable.cb-sort-asc::after { content: '▲'; opacity: 1; }
.cb-sortable.cb-sort-desc::after { content: '▼'; opacity: 1; }
.cb-status-overbill { color: #dc2626; font-weight: 600; }
.cb-status-grace { color: #059669; }
</style>

<div class="cb-hist">
    <h3>Historical Reconciliation</h3>
    <div class="cb-banner">
        Uses Comet billing history charges (<code>cb_credit_usage</code>) compared to device revocation dates.
        This is <strong>not</strong> server inventory vs portal count variance — it finds charges posted after the expected billing end for revoked devices and boosters.
    </div>

    <div class="cb-box">
        <h4>Date Range</h4>
        <div class="cb-filter-row">
            <?= histPresetLink($baseUrl, 30, $range['preset'], 'Last 30 days') ?>
            <?= histPresetLink($baseUrl, 90, $range['preset'], 'Last 90 days') ?>
            <?= histPresetLink($baseUrl, 365, $range['preset'], 'Last 365 days') ?>
            <?= histPresetLink($baseUrl, 'all', $range['preset'], 'All history') ?>
        </div>
        <form method="get" class="cb-form-inline">
            <input type="hidden" name="module" value="cometbilling">
            <input type="hidden" name="action" value="historical_reconcile">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= htmlspecialchars($range['from']) ?>" required>
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= htmlspecialchars($range['to']) ?>" required>
            <label style="margin-left:12px;">
                <input type="checkbox" name="include_grace" value="1" <?= $includeGrace ? 'checked' : '' ?>>
                Include expected grace rows
            </label>
            <button type="submit" class="btn btn-default">Apply</button>
        </form>
        <p class="cb-muted">Period: <?= htmlspecialchars($range['from']) ?> to <?= htmlspecialchars($range['to']) ?> (UTC)</p>
    </div>

    <div class="cb-box">
        <h4>Summary</h4>
        <div class="cb-summary">
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $report['summary']['charges_scanned']) ?></div>
                <div class="label">Charges scanned</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $report['summary']['matched_revoked']) ?></div>
                <div class="label">Matched revoked devices</div>
            </div>
            <div class="cb-stat">
                <div class="value warn"><?= number_format((int) $report['summary']['overbilled_count']) ?></div>
                <div class="label">Overbilled charges</div>
            </div>
            <div class="cb-stat">
                <div class="value warn">$<?= number_format((float) $report['summary']['overbilled_amount'], 2) ?></div>
                <div class="label">Overbilled amount</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $report['summary']['unmatched_device_count']) ?></div>
                <div class="label">Unmatched / active devices</div>
            </div>
        </div>
        <p class="cb-muted">
            Monthly lines (devices, M365, disk image, MSSQL): period end from device <code>RegistrationTime</code> when available; otherwise revoke + 30 days.
            Daily lines (Hyper-V, VMware, Proxmox): last billable day is revoke date.
        </p>
        <p>
            <a class="btn btn-primary" href="<?= htmlspecialchars($exportUrl) ?>">Export overbilled (CSV)</a>
        </p>
    </div>

    <?php if (!empty($report['categories'])): ?>
    <div class="cb-box">
        <h4>By Category</h4>
        <table class="cb-table cb-sortable-table" id="cb-hist-categories">
            <thead>
                <tr>
                    <th class="cb-sortable" data-type="text">Category</th>
                    <th class="cb-sortable num" data-type="number">Overbilled count</th>
                    <th class="cb-sortable num" data-type="number">Overbilled $</th>
                    <th class="cb-sortable num" data-type="number">Expected grace</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['categories'] as $key => $cat): ?>
                <tr>
                    <td data-sort="<?= htmlspecialchars($cat['label']) ?>"><?= htmlspecialchars($cat['label']) ?></td>
                    <td class="num" data-sort="<?= (int) $cat['overbilled_count'] ?>"><?= number_format((int) $cat['overbilled_count']) ?></td>
                    <td class="num" data-sort="<?= (float) $cat['overbilled_amount'] ?>">$<?= number_format((float) $cat['overbilled_amount'], 2) ?></td>
                    <td class="num" data-sort="<?= (int) $cat['expected_grace_count'] ?>"><?= number_format((int) $cat['expected_grace_count']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="cb-box">
        <h4>Charge Detail<?= $includeGrace ? '' : ' (overbilled only)' ?></h4>
        <?php if (!empty($report['summary']['ui_rows_truncated'])): ?>
        <p class="cb-muted">Showing first <?= (int) $report['summary']['ui_row_cap'] ?> overbilled rows. Export CSV for the full list.</p>
        <?php endif; ?>
        <?php if (empty($report['rows'])): ?>
        <p class="cb-muted">No matching charges in this period.</p>
        <?php else: ?>
        <table class="cb-table cb-sortable-table" id="cb-hist-detail">
            <thead>
                <tr>
                    <th class="cb-sortable" data-type="date">Usage date</th>
                    <th class="cb-sortable" data-type="text">Account</th>
                    <th class="cb-sortable" data-type="text">Device</th>
                    <th class="cb-sortable" data-type="text">Category</th>
                    <th class="cb-sortable" data-type="text">Item</th>
                    <th class="cb-sortable num" data-type="number">Amount</th>
                    <th class="cb-sortable" data-type="date">Revoked</th>
                    <th class="cb-sortable" data-type="date">Registered</th>
                    <th class="cb-sortable" data-type="date">Expected end</th>
                    <th class="cb-sortable" data-type="text">Cycle</th>
                    <th class="cb-sortable" data-type="text">Status</th>
                    <th class="cb-sortable num" data-type="number">Overbill $</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['rows'] as $row): ?>
                <tr>
                    <td data-sort="<?= htmlspecialchars($row['usage_date']) ?>"><?= htmlspecialchars($row['usage_date']) ?></td>
                    <td data-sort="<?= htmlspecialchars($row['account'] ?? '') ?>"><?= htmlspecialchars($row['account'] ?? '—') ?></td>
                    <td data-sort="<?= htmlspecialchars(substr((string) ($row['device_id'] ?? ''), 0, 8)) ?>">
                        <?= htmlspecialchars(substr((string) ($row['device_id'] ?? ''), 0, 6)) ?>
                        <?php if (!empty($row['device_name'])): ?>
                        <span class="cb-muted">— <?= htmlspecialchars($row['device_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-sort="<?= htmlspecialchars($row['category_label'] ?? $row['category']) ?>"><?= htmlspecialchars($row['category_label'] ?? $row['category']) ?></td>
                    <td data-sort="<?= htmlspecialchars($row['item_desc'] ?? '') ?>"><?= htmlspecialchars($row['item_desc'] ?? '') ?></td>
                    <td class="num" data-sort="<?= (float) ($row['amount'] ?? 0) ?>">$<?= number_format((float) ($row['amount'] ?? 0), 2) ?></td>
                    <td data-sort="<?= htmlspecialchars($row['revoked_at'] ?? '') ?>"><?= htmlspecialchars($row['revoked_at'] ?? '—') ?></td>
                    <td data-sort="<?= htmlspecialchars($row['registered_at'] ?? '') ?>"><?= htmlspecialchars($row['registered_at'] ?? '—') ?></td>
                    <td data-sort="<?= htmlspecialchars($row['expected_billing_end'] ?? '') ?>"><?= htmlspecialchars($row['expected_billing_end'] ?? '—') ?></td>
                    <td data-sort="<?= htmlspecialchars($row['cycle'] ?? '') ?>"><?= htmlspecialchars($row['cycle'] ?? '') ?></td>
                    <td data-sort="<?= htmlspecialchars($row['billing_status'] ?? '') ?>" class="<?= ($row['billing_status'] ?? '') === 'overbilled_past_grace' ? 'cb-status-overbill' : 'cb-status-grace' ?>">
                        <?= ($row['billing_status'] ?? '') === 'overbilled_past_grace' ? 'Overbilled past grace' : 'Expected grace' ?>
                    </td>
                    <td class="num" data-sort="<?= (float) ($row['overbill_amount'] ?? 0) ?>">$<?= number_format((float) ($row['overbill_amount'] ?? 0), 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    function cellSortValue(row, colIndex) {
        var cell = row.cells[colIndex];
        if (!cell) return '';
        var ds = cell.getAttribute('data-sort');
        return ds !== null ? ds : (cell.textContent || '').trim();
    }

    function compareValues(a, b, type, dir) {
        var mult = dir === 'asc' ? 1 : -1;
        if (type === 'number') {
            return mult * ((parseFloat(a) || 0) - (parseFloat(b) || 0));
        }
        if (type === 'date') {
            return mult * String(a).localeCompare(String(b));
        }
        return mult * String(a).localeCompare(String(b), undefined, { sensitivity: 'base' });
    }

    function sortPlainTable(table, colIndex, type, dir) {
        var tbody = table.tBodies[0];
        if (!tbody) return;
        var rows = Array.prototype.slice.call(tbody.rows);
        rows.sort(function (ra, rb) {
            return compareValues(cellSortValue(ra, colIndex), cellSortValue(rb, colIndex), type, dir);
        });
        rows.forEach(function (row) { tbody.appendChild(row); });
    }

    document.querySelectorAll('table.cb-sortable-table').forEach(function (table) {
        var headers = table.querySelectorAll('thead th.cb-sortable');
        headers.forEach(function (th, colIndex) {
            th.addEventListener('click', function () {
                var type = th.getAttribute('data-type') || 'text';
                var current = th.getAttribute('data-sort-dir');
                var dir = current === 'asc' ? 'desc' : 'asc';
                headers.forEach(function (h) {
                    h.removeAttribute('data-sort-dir');
                    h.classList.remove('cb-sort-asc', 'cb-sort-desc');
                });
                th.setAttribute('data-sort-dir', dir);
                th.classList.add(dir === 'asc' ? 'cb-sort-asc' : 'cb-sort-desc');
                sortPlainTable(table, colIndex, type, dir);
            });
        });
    });
})();
</script>
