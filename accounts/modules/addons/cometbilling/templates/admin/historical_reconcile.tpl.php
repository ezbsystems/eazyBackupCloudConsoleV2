<?php
use CometBilling\HistoricalReconciler;
use CometBilling\Settings;
use WHMCS\Database\Capsule;

$baseUrl = 'addonmodules.php?module=cometbilling&action=historical_reconcile';
$runUrl = 'addonmodules.php?module=cometbilling&action=historical_reconcile_run';
$exportBase = 'addonmodules.php?module=cometbilling&action=historical_reconcile_export';
$disputeCsvBase = 'addonmodules.php?module=cometbilling&action=historical_reconcile_dispute_csv';
$disputePdfBase = 'addonmodules.php?module=cometbilling&action=historical_reconcile_dispute_pdf';

$preset = $_GET['preset'] ?? null;
$fromInput = $_GET['from'] ?? null;
$toInput = $_GET['to'] ?? null;
$includeGrace = !empty($_GET['include_grace']);

$range = HistoricalReconciler::resolveDateRange($preset, $fromInput, $toInput);
$report = null;
$lastSaved = null;
$jobRunning = Settings::isJobRunning('historical_reconcile');
$jobStartedAt = Settings::getJobStartedAt('historical_reconcile');
$lastJobStatus = Settings::getKv('last_historical_reconcile_status');
$lastJobMessage = Settings::getKv('last_historical_reconcile_message');
$lastJobAt = Settings::getKv('last_historical_reconcile_at');

if (Capsule::schema()->hasTable('cb_audit_runs')) {
    $lastSaved = Capsule::table('cb_audit_runs')->orderBy('id', 'desc')->first();
}

if (!$jobRunning) {
    $report = HistoricalReconciler::loadPersistedReport($range['from'], $range['to'], $includeGrace);
}

$rangeQs = 'from=' . urlencode($range['from']) . '&to=' . urlencode($range['to']);
if ($range['preset'] !== null) {
    $rangeQs .= '&preset=' . urlencode((string) $range['preset']);
}
if ($includeGrace) {
    $rangeQs .= '&include_grace=1';
}

function histPresetLink(string $baseUrl, $days, $activePreset, string $label): string
{
    $isActive = ($activePreset === $days);
    $class = $isActive ? 'btn btn-primary' : 'btn btn-default';
    $param = $days === 'all' ? 'all' : (int) $days;

    return '<a href="' . htmlspecialchars($baseUrl . '&preset=' . $param) . '" class="' . $class . '">' . htmlspecialchars($label) . '</a>';
}

$auditRunId = $report['audit_run_id'] ?? null;
$exportSuffix = $auditRunId ? '&audit_run_id=' . (int) $auditRunId . '&' . $rangeQs : '&' . $rangeQs;
$exportUrl = $exportBase . $exportSuffix;
$disputeCsvUrl = $disputeCsvBase . $exportSuffix;
$disputePdfUrl = $disputePdfBase . $exportSuffix;
?>
<style>
.cb-hist { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin: 20px 0; }
.cb-box h4 { margin: 0 0 15px 0; }
.cb-banner { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px 16px; margin: 0 0 16px; color: #1e3a5f; font-size: 13px; }
.cb-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-bottom: 10px; }
.cb-badge-running { background: #fef3c7; color: #92400e; }
.cb-badge-ok { background: #d1fae5; color: #065f46; }
.cb-badge-error { background: #fee2e2; color: #991b1b; }
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
        Audits run in the <strong>background</strong> to avoid gateway timeouts; results load from the last saved run for the selected range.
    </div>

    <?php if (!empty($_GET['started'])): ?>
    <div class="successbox">Audit started in the background. This page refreshes automatically while the job runs.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['busy'])): ?>
    <div class="infobox">An audit is already running. Please wait for it to finish.</div>
    <?php endif; ?>

    <?php if ($jobRunning): ?>
    <div class="cb-box">
        <span class="cb-badge cb-badge-running">Running</span>
        <p>Started: <strong><?= htmlspecialchars($jobStartedAt ?? '—') ?></strong> UTC</p>
        <p class="cb-muted">Large ranges can take several minutes. This page refreshes every 15 seconds.</p>
    </div>
    <?php elseif ($lastJobAt): ?>
    <p class="cb-muted">
        Last job: <span class="cb-badge <?= $lastJobStatus === 'ok' ? 'cb-badge-ok' : 'cb-badge-error' ?>"><?= htmlspecialchars(strtoupper((string) $lastJobStatus)) ?></span>
        at <?= htmlspecialchars((string) $lastJobAt) ?> UTC
    </p>
    <?php if ($lastJobMessage): ?>
    <pre style="font-size: 11px; max-height: 80px; overflow: auto; background: #f9fafb; padding: 8px;"><?= htmlspecialchars((string) $lastJobMessage) ?></pre>
    <?php endif; ?>
    <?php endif; ?>

    <div class="cb-box">
        <h4>Date Range</h4>
        <div class="cb-filter-row">
            <?= histPresetLink($baseUrl, 30, $range['preset'], 'Last 30 days') ?>
            <?= histPresetLink($baseUrl, 90, $range['preset'], 'Last 90 days') ?>
            <?= histPresetLink($baseUrl, 365, $range['preset'], 'Last 365 days') ?>
            <?= histPresetLink($baseUrl, 'all', $range['preset'], 'All history') ?>
        </div>
        <form method="get" action="addonmodules.php" class="cb-form-inline">
            <input type="hidden" name="module" value="cometbilling">
            <input type="hidden" name="action" value="historical_reconcile_run">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= htmlspecialchars($range['from']) ?>" required>
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= htmlspecialchars($range['to']) ?>" required>
            <label style="margin-left:12px;">
                <input type="checkbox" name="include_grace" value="1" <?= $includeGrace ? 'checked' : '' ?>>
                Include expected grace rows
            </label>
            <button type="submit" class="btn btn-primary"<?= $jobRunning ? ' disabled' : '' ?>>Run audit</button>
        </form>
        <p class="cb-muted">Period: <?= htmlspecialchars($range['from']) ?> to <?= htmlspecialchars($range['to']) ?> (UTC).</p>
    </div>

<?php if ($report === null && !$jobRunning): ?>
    <div class="cb-box">
        <h4>No saved audit for this range</h4>
        <p class="cb-muted">Click <strong>Run audit</strong> to start a background scan. Results appear here when complete.</p>
        <?php if ($lastSaved): ?>
        <p class="cb-muted">
            Most recent saved audit: #<?= (int) $lastSaved->id ?>
            (<?= htmlspecialchars((string) $lastSaved->from_date) ?> → <?= htmlspecialchars((string) $lastSaved->to_date) ?>,
            <?= htmlspecialchars((string) $lastSaved->run_at) ?> UTC)
        </p>
        <?php endif; ?>
    </div>
<?php elseif ($report !== null): ?>

    <?php if (!empty($report['coverage'])): ?>
    <div class="cb-box">
        <h4>Source Coverage<?php if ($auditRunId): ?> <span class="cb-muted">(run #<?= (int) $auditRunId ?>)</span><?php endif; ?></h4>
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
        <?php if ($auditRunId): ?>
        <p>
            <a class="btn btn-primary" href="<?= htmlspecialchars($disputeCsvUrl) ?>">Export dispute pack (CSV)</a>
            <a class="btn btn-primary" href="<?= htmlspecialchars($disputePdfUrl) ?>" target="_blank" rel="noopener">Export dispute pack (PDF)</a>
            <a class="btn btn-default" href="<?= htmlspecialchars($exportUrl) ?>">Export overbilled (CSV)</a>
        </p>
        <?php else: ?>
        <p class="cb-muted">Exports require a saved audit run.</p>
        <?php endif; ?>
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
        <p class="cb-muted">Showing first <?= (int) $report['summary']['ui_row_cap'] ?> rows. Export CSV for the full list.</p>
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

<?php if ($jobRunning): ?>
<script>
setTimeout(function () { window.location.reload(); }, 15000);
</script>
<?php endif; ?>

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
