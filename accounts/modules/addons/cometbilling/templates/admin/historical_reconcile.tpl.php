<?php
use CometBilling\HistoricalReconciler;
use WHMCS\Database\Capsule;

$baseUrl = 'addonmodules.php?module=cometbilling&action=historical_reconcile';
$exportBase = 'addonmodules.php?module=cometbilling&action=historical_reconcile_export';

$preset = $_GET['preset'] ?? null;
$fromInput = $_GET['from'] ?? null;
$toInput = $_GET['to'] ?? null;
$includeGrace = !empty($_GET['include_grace']);
$run = !empty($_GET['run']) || ($fromInput && $toInput);

$range = HistoricalReconciler::resolveDateRange($preset, $fromInput, $toInput);
$report = null;
$lastSaved = null;

if (Capsule::schema()->hasTable('cb_audit_runs')) {
    $lastSaved = Capsule::table('cb_audit_runs')->orderBy('id', 'desc')->first();
}

if ($run) {
    // Persist only when explicitly requested — keeps page load lighter.
    $persist = !empty($_GET['persist']);
    $report = HistoricalReconciler::report($range['from'], $range['to'], $includeGrace, $persist);
}

function histPresetLink(string $baseUrl, $days, $activePreset, string $label): string
{
    $isActive = ($activePreset === $days);
    $class = $isActive ? 'btn btn-primary' : 'btn btn-default';
    $param = $days === 'all' ? 'all' : (int) $days;

    return '<a href="' . htmlspecialchars($baseUrl . '&preset=' . $param . '&run=1') . '" class="' . $class . '">' . htmlspecialchars($label) . '</a>';
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
        Audit-grade analysis correlating Comet <strong>Amount Used</strong> + <strong>Packs Used</strong> debits with billing periods and lifecycle evidence.
        This proves overbilling from Comet’s own records — not independent verification of a hidden account balance (Comet does not expose one).
        Large ranges can take a few minutes; other admin tabs stay responsive while this runs.
    </div>

    <div class="cb-box">
        <h4>Date Range</h4>
        <div class="cb-filter-row">
            <?= histPresetLink($baseUrl, 30, $run ? $range['preset'] : null, 'Last 30 days') ?>
            <?= histPresetLink($baseUrl, 90, $run ? $range['preset'] : null, 'Last 90 days') ?>
            <?= histPresetLink($baseUrl, 365, $run ? $range['preset'] : null, 'Last 365 days') ?>
            <?= histPresetLink($baseUrl, 'all', $run ? $range['preset'] : null, 'All history') ?>
        </div>
        <form method="get" class="cb-form-inline">
            <input type="hidden" name="module" value="cometbilling">
            <input type="hidden" name="action" value="historical_reconcile">
            <input type="hidden" name="run" value="1">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= htmlspecialchars($range['from']) ?>" required>
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= htmlspecialchars($range['to']) ?>" required>
            <label style="margin-left:12px;">
                <input type="checkbox" name="include_grace" value="1" <?= $includeGrace ? 'checked' : '' ?>>
                Include expected grace rows
            </label>
            <label style="margin-left:12px;">
                <input type="checkbox" name="persist" value="1" <?= !empty($_GET['persist']) ? 'checked' : '' ?>>
                Save audit run
            </label>
            <button type="submit" class="btn btn-primary">Run audit</button>
        </form>
        <p class="cb-muted">Period: <?= htmlspecialchars($range['from']) ?> to <?= htmlspecialchars($range['to']) ?> (UTC). Click a preset or <strong>Run audit</strong> to generate results.</p>
    </div>

<?php if (!$run): ?>
    <div class="cb-box">
        <h4>Ready to run</h4>
        <p class="cb-muted">Choose a date range above to start. Default recommendation: <a href="<?= htmlspecialchars($baseUrl . '&preset=90&run=1') ?>">Last 90 days</a>.</p>
        <?php if ($lastSaved): ?>
        <p class="cb-muted">
            Last saved audit: #<?= (int) $lastSaved->id ?>
            (<?= htmlspecialchars((string) $lastSaved->from_date) ?> → <?= htmlspecialchars((string) $lastSaved->to_date) ?>,
            <?= htmlspecialchars((string) $lastSaved->run_at) ?> UTC)
        </p>
        <?php endif; ?>
    </div>
<?php else: ?>

    <?php if (!empty($report['coverage'])): ?>
    <div class="cb-box">
        <h4>Source Coverage</h4>
        <?php if (!$report['coverage']['complete_overlap']): ?>
        <p class="cb-muted" style="color:#b45309;"><strong>Coverage incomplete.</strong> Confirmed findings require full overlap of usage, active services, and Bill History CSV.</p>
        <?php else: ?>
        <p class="cb-muted">All required sources overlap the selected period.</p>
        <?php endif; ?>
        <ul class="cb-muted">
            <li>Usage: <?= htmlspecialchars($report['coverage']['usage']['min'] ?? '—') ?> → <?= htmlspecialchars($report['coverage']['usage']['max'] ?? '—') ?></li>
            <li>Active services: <?= htmlspecialchars($report['coverage']['active_services']['min'] ?? '—') ?> → <?= htmlspecialchars($report['coverage']['active_services']['max'] ?? '—') ?></li>
            <li>Bill History (purchases): <?= htmlspecialchars($report['coverage']['purchases']['min'] ?? '—') ?> → <?= htmlspecialchars($report['coverage']['purchases']['max'] ?? '—') ?></li>
        </ul>
    </div>
    <?php endif; ?>

    <div class="cb-box">
        <h4>Summary</h4>
        <div class="cb-summary">
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $report['summary']['charges_scanned']) ?></div>
                <div class="label">Charges scanned</div>
            </div>
            <div class="cb-stat">
                <div class="value warn"><?= number_format((int) ($report['summary']['confirmed_count'] ?? 0)) ?></div>
                <div class="label">Confirmed by Comet records</div>
            </div>
            <div class="cb-stat">
                <div class="value warn">$<?= number_format((float) ($report['summary']['confirmed_amount'] ?? 0), 2) ?></div>
                <div class="label">Confirmed $</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format((int) ($report['summary']['probable_count'] ?? 0)) ?></div>
                <div class="label">Probable overbill</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format((int) ($report['summary']['review_required_count'] ?? 0)) ?></div>
                <div class="label">Review required</div>
            </div>
            <div class="cb-stat">
                <div class="value"><?= number_format((int) $report['summary']['unmatched_device_count']) ?></div>
                <div class="label">Unmatched / active</div>
            </div>
        </div>
        <p class="cb-muted">
            <strong>Confirmed</strong> requires proven identity, lifecycle stop, portal-supported cadence, pack debit evidence, and no reversal.
            Probable and review rows are excluded from confirmed totals.
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
                    <th class="cb-sortable" data-type="text">Verdict</th>
                    <th class="cb-sortable" data-type="text">Debit</th>
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
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['rows'] as $row): ?>
                <tr>
                    <td data-sort="<?= htmlspecialchars($row['verdict'] ?? '') ?>" class="<?= in_array($row['verdict'] ?? '', ['confirmed', 'probable'], true) ? 'cb-status-overbill' : '' ?>">
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($row['verdict'] ?? '')))) ?>
                    </td>
                    <td data-sort="<?= htmlspecialchars($row['debit_evidence'] ?? '') ?>"><?= htmlspecialchars($row['debit_evidence'] ?? '') ?></td>
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
                    <td data-sort="<?= htmlspecialchars($row['billing_verdict'] ?? '') ?>">
                        <?= htmlspecialchars(str_replace('_', ' ', (string) ($row['billing_verdict'] ?? ''))) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
<?php endif; ?>
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
