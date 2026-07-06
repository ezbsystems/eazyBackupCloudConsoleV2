<?php
use WHMCS\Database\Capsule;
use CometBilling\Reconciler;
use CometBilling\PortalUsageExtractor;
use CometBilling\ServerUsageCollector;

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$cometAutoload = dirname(__DIR__, 5) . '/modules/servers/comet/vendor/autoload.php';
if (file_exists($cometAutoload)) {
    require_once $cometAutoload;
}

$baseUrl = 'addonmodules.php?module=cometbilling';
$message = '';
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_token('WHMCS.admin.default')) {
    try {
        $mode = $_POST['recon_mode'] ?? 'snapshot';
        $tolerance = max(0, (int) ($_POST['tolerance'] ?? 1));
        $snapshotDate = !empty($_POST['snapshot_date']) ? $_POST['snapshot_date'] : null;

        if ($mode === 'live') {
            $report = Reconciler::compareLive($tolerance);
        } else {
            $report = Reconciler::compare($snapshotDate, $tolerance);
        }

        if (!empty($_POST['save_report'])) {
            $reportId = Reconciler::saveReport($report);
            $message = '<div class="successbox">Reconciliation complete. Report saved (ID: ' . $reportId . ').</div>';
        } else {
            $message = '<div class="successbox">Reconciliation complete.</div>';
        }
    } catch (\Exception $e) {
        $message = '<div class="errorbox">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

$savedReports = [];
try {
    $savedReports = Reconciler::getReports(10);
} catch (\Exception $e) {
    // Table may not exist yet
}

$availableSnapshots = Capsule::table('cb_server_usage_combined')
    ->orderBy('snapshot_date', 'desc')
    ->limit(14)
    ->pluck('snapshot_date')
    ->toArray();
?>
<style>
.cb-recon { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
.cb-box { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; }
.cb-box h4 { margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 1px solid #eee; }
.cb-items-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
.cb-items-table th, .cb-items-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
.cb-items-table th { background: #f9fafb; font-weight: 600; font-size: 12px; text-transform: uppercase; }
.cb-items-table tr:hover { background: #f9fafb; }
.status-ok { color: #10b981; font-weight: 600; }
.status-over { color: #f59e0b; font-weight: 600; }
.status-under { color: #ef4444; font-weight: 600; }
.variance-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.variance-ok { background: #d1fae5; color: #065f46; }
.variance-over { background: #fef3c7; color: #92400e; }
.variance-under { background: #fee2e2; color: #991b1b; }
.overall-status { font-size: 24px; font-weight: 700; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0; }
.overall-ok { background: #d1fae5; color: #065f46; }
.overall-variance { background: #fef3c7; color: #92400e; }
.overall-incomplete { background: #fee2e2; color: #991b1b; }
</style>

<div class="cb-recon">
    <h3>🔍 Reconciliation: Server Usage vs Portal Billing</h3>

    <?= $message ?>

    <div class="cb-box" style="margin-bottom: 20px;">
        <form method="post">
            <?= generate_token('WHMCS.admin.default') ?>
            <p>Compare server usage against portal billing. Default uses aligned stored snapshots (faster, consistent timing).</p>
            <p>
                <label>Mode:
                    <select name="recon_mode">
                        <option value="snapshot">Stored Snapshots (recommended)</option>
                        <option value="live">Live Server Pull (slower)</option>
                    </select>
                </label>
                &nbsp;
                <label>Tolerance (±):
                    <input type="number" name="tolerance" value="1" min="0" max="99" style="width: 60px;">
                </label>
                <?php if (!empty($availableSnapshots)): ?>
                &nbsp;
                <label>Snapshot date:
                    <select name="snapshot_date">
                        <option value="">Latest (<?= htmlspecialchars($availableSnapshots[0]) ?>)</option>
                        <?php foreach ($availableSnapshots as $sd): ?>
                        <option value="<?= htmlspecialchars($sd) ?>"><?= htmlspecialchars($sd) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="save_report" value="1" checked>
                    Save report to history
                </label>
            </p>
            <button type="submit" class="btn btn-primary">Run Reconciliation Now</button>
        </form>
    </div>

    <?php if ($report): ?>
    <?php include __DIR__ . '/reconcile_report_partial.tpl.php'; ?>
    <?php endif; ?>

    <?php if (!empty($savedReports)): ?>
    <div class="cb-box" style="margin-top: 20px;">
        <h4>📜 Report History</h4>
        <table class="cb-items-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>OK</th>
                    <th>Over</th>
                    <th>Under</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($savedReports as $r): ?>
                <tr>
                    <td><?= date('M j, Y g:i A', strtotime($r->report_date)) ?></td>
                    <td>
                        <?php if ($r->overall_status === 'ok'): ?>
                        <span class="status-ok">OK</span>
                        <?php elseif ($r->overall_status === 'variance_detected'): ?>
                        <span class="status-over">Variance</span>
                        <?php else: ?>
                        <span class="status-under">Incomplete</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $r->summary['ok'] ?? 0 ?></td>
                    <td><?= $r->summary['over_billed'] ?? 0 ?></td>
                    <td><?= $r->summary['under_billed'] ?? 0 ?></td>
                    <td>
                        <a href="<?= $baseUrl ?>&action=reconcile_view&id=<?= $r->id ?>" class="btn btn-xs btn-default">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <p style="margin-top: 20px;">
        <a href="<?= $baseUrl ?>" class="btn btn-default">← Back to Dashboard</a>
    </p>
</div>
