<?php
use WHMCS\Database\Capsule;
use CometBilling\Reconciler;
use CometBilling\PortalUsageExtractor;
use CometBilling\ServerUsageCollector;

// Load classes
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

// Also load comet SDK
$cometAutoload = dirname(__DIR__, 5) . '/modules/servers/comet/vendor/autoload.php';
if (file_exists($cometAutoload)) require_once $cometAutoload;

$baseUrl = 'addonmodules.php?module=cometbilling';
$message = '';
$report = null;
$runNow = isset($_GET['run']) && $_GET['run'] === '1';

// Run reconciliation if requested
if ($runNow || ($_SERVER['REQUEST_METHOD'] === 'POST' && check_token('WHMCS.admin.default'))) {
    try {
        $report = Reconciler::compare();
        
        // Save if checkbox was checked
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

// Get saved reports
$savedReports = [];
try {
    $savedReports = Reconciler::getReports(10);
} catch (\Exception $e) {
    // Table may not exist yet
}
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
    
    <!-- Run Reconciliation Form -->
    <div class="cb-box" style="margin-bottom: 20px;">
        <form method="post">
            <?= generate_token('WHMCS.admin.default') ?>
            <p>Compare actual usage from your Comet servers against what the Comet Portal is billing you.</p>
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
    <!-- Overall Status -->
    <div class="overall-status overall-<?= $report['overall_status'] === 'ok' ? 'ok' : ($report['overall_status'] === 'incomplete' ? 'incomplete' : 'variance') ?>">
        <?php if ($report['overall_status'] === 'ok'): ?>
            ✓ ALL ITEMS MATCH
        <?php elseif ($report['overall_status'] === 'incomplete'): ?>
            ⚠️ INCOMPLETE (Server Errors)
        <?php else: ?>
            ⚠️ VARIANCE DETECTED
        <?php endif; ?>
    </div>
    
    <!-- Comparison Details -->
    <div class="cb-comparison">
        <div class="cb-box">
            <h4>🖥️ Comet Servers (Actual Usage)</h4>
            <p>Collected: <?= $report['server_collected_at'] ?? 'N/A' ?></p>
            <ul>
                <li>Total Users: <strong><?= $report['server_raw']['total_users'] ?? 0 ?></strong></li>
                <li>Total Devices: <strong><?= $report['server_raw']['total_devices'] ?? 0 ?></strong></li>
                <li>Protected Items: <strong><?= $report['server_raw']['total_protected_items'] ?? 0 ?></strong></li>
                <li>Storage: <strong><?= $report['server_raw']['storage_human'] ?? 'N/A' ?></strong></li>
            </ul>
            <?php if (!empty($report['server_raw']['errors'])): ?>
            <div style="color: #ef4444; margin-top: 10px;">
                <strong>Errors:</strong>
                <ul>
                    <?php foreach ($report['server_raw']['errors'] as $srv => $err): ?>
                    <li><?= htmlspecialchars($srv) ?>: <?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="cb-box">
            <h4>📦 Comet Portal (Billing)</h4>
            <p>Snapshot: <?= $report['portal_snapshot_at'] ?? 'N/A' ?></p>
            <ul>
                <li>Active Rows: <strong><?= $report['portal_raw']['raw_rows'] ?? 0 ?></strong></li>
                <li>Total Billable: <strong>$<?= number_format($report['portal_raw']['total_amount'] ?? 0, 2) ?></strong></li>
                <li>Account Fees: <strong>$<?= number_format($report['portal_raw']['account_fees'] ?? 0, 2) ?></strong></li>
                <li>Server Licenses: <strong>$<?= number_format($report['portal_raw']['server_licenses'] ?? 0, 2) ?></strong></li>
            </ul>
        </div>
    </div>
    
    <!-- Item-by-Item Comparison -->
    <div class="cb-box">
        <h4>📋 Item Comparison</h4>
        <table class="cb-items-table">
            <thead>
                <tr>
                    <th>Item Type</th>
                    <th>Server Count</th>
                    <th>Portal Count</th>
                    <th>Portal Amount</th>
                    <th>Variance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['items'] as $key => $item): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['label']) ?></strong></td>
                    <td><?= number_format($item['server']) ?></td>
                    <td><?= number_format($item['portal']) ?></td>
                    <td>$<?= number_format($item['portal_amount'], 2) ?></td>
                    <td>
                        <?php 
                        $sign = $item['variance'] > 0 ? '+' : '';
                        $class = $item['status'] === 'ok' ? 'variance-ok' : ($item['status'] === 'over_billed' ? 'variance-over' : 'variance-under');
                        ?>
                        <span class="variance-badge <?= $class ?>"><?= $sign . $item['variance'] ?></span>
                        <?php if ($item['variance_pct'] !== null && $item['variance'] != 0): ?>
                        <span style="font-size: 11px; color: #666;">(<?= $sign . $item['variance_pct'] ?>%)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($item['status'] === 'ok'): ?>
                        <span class="status-ok">✓ OK</span>
                        <?php elseif ($item['status'] === 'over_billed'): ?>
                        <span class="status-over">⚠️ Over-billed</span>
                        <?php else: ?>
                        <span class="status-under">⚠️ Under-billed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Saved Reports -->
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
