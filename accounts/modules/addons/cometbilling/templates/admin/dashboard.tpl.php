<?php
use WHMCS\Database\Capsule;
use CometBilling\PortalUsageExtractor;
use CometBilling\CreditLedger;
use CometBilling\Reconciler;

// Load classes
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

// Basic stats
$usageCount = Capsule::table('cb_credit_usage')->count();
$svcCount   = Capsule::table('cb_active_services')->count();
$purchSum   = Capsule::table('cb_credit_purchases')->sum(Capsule::raw('credit_amount + bonus_credit'));
$lastBal    = Capsule::table('cb_daily_balance')->orderBy('balance_date', 'desc')->first();

// Get portal snapshot summary
$portalSummary = [];
try {
    $portalSummary = PortalUsageExtractor::getSummary();
} catch (\Exception $e) {
    $portalSummary = ['error' => $e->getMessage()];
}

// Get credit balance
$creditBalance = ['purchased' => 0, 'bonus' => 0, 'total' => 0];
try {
    $creditBalance = CreditLedger::getCurrentBalance();
} catch (\Exception $e) {
    // Tables may not exist yet
}

// Get runway estimate
$runway = ['daily_burn' => 0, 'days_remaining' => null];
try {
    $runway = CreditLedger::estimateRunway(30);
} catch (\Exception $e) {
    // Ignore
}

// Last reconciliation
$lastRecon = null;
try {
    $reports = Reconciler::getReports(1);
    $lastRecon = $reports[0] ?? null;
} catch (\Exception $e) {
    // Table may not exist
}

$baseUrl = 'addonmodules.php?module=cometbilling';
?>
<style>
.cb-dashboard { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin: 20px 0; }
.cb-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; }
.cb-card h4 { margin: 0 0 15px 0; color: #333; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
.cb-card .stat { font-size: 28px; font-weight: 600; color: #1a73e8; }
.cb-card .stat.warning { color: #f59e0b; }
.cb-card .stat.danger { color: #ef4444; }
.cb-card .stat.success { color: #10b981; }
.cb-card .label { font-size: 12px; color: #666; margin-top: 4px; }
.cb-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
.cb-table th, .cb-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
.cb-table th { background: #f9fafb; font-weight: 600; font-size: 12px; text-transform: uppercase; }
.cb-actions { margin: 20px 0; }
.cb-actions a { display: inline-block; margin-right: 10px; margin-bottom: 10px; }
.cb-status-ok { color: #10b981; }
.cb-status-warning { color: #f59e0b; }
.cb-status-error { color: #ef4444; }
.cb-progress-bar { height: 8px; background: #e5e5e5; border-radius: 4px; overflow: hidden; margin-top: 10px; }
.cb-progress-fill { height: 100%; border-radius: 4px; }
.cb-progress-purchased { background: #1a73e8; }
.cb-progress-bonus { background: #10b981; }
</style>

<div class="cb-dashboard">
    <h3>Comet Billing Dashboard</h3>
    
    <div class="cb-cards">
        <!-- Credit Balance Card -->
        <div class="cb-card">
            <h4>💳 Credit Balance</h4>
            <div class="stat <?= $creditBalance['total'] < 1000 ? 'warning' : '' ?>">
                $<?= number_format($creditBalance['total'], 2) ?>
            </div>
            <div class="label">Total Available</div>
            
            <div style="margin-top: 15px; font-size: 13px;">
                <div>Purchased: <strong>$<?= number_format($creditBalance['purchased'], 2) ?></strong></div>
                <div>Bonus: <strong style="color: #10b981;">$<?= number_format($creditBalance['bonus'], 2) ?></strong></div>
            </div>
            
            <?php if ($creditBalance['total'] > 0): ?>
            <div class="cb-progress-bar">
                <?php 
                $purchPct = ($creditBalance['purchased'] / $creditBalance['total']) * 100;
                $bonusPct = ($creditBalance['bonus'] / $creditBalance['total']) * 100;
                ?>
                <div class="cb-progress-fill cb-progress-purchased" style="width: <?= $purchPct ?>%; float: left;"></div>
                <div class="cb-progress-fill cb-progress-bonus" style="width: <?= $bonusPct ?>%; float: left;"></div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Runway Card -->
        <div class="cb-card">
            <h4>📊 Credit Runway</h4>
            <div class="stat <?= ($runway['days_remaining'] ?? 999) < 30 ? 'warning' : '' ?>">
                <?= $runway['days_remaining'] !== null ? $runway['days_remaining'] . ' days' : 'N/A' ?>
            </div>
            <div class="label">Estimated until depletion</div>
            
            <div style="margin-top: 15px; font-size: 13px;">
                <div>Daily burn rate: <strong>$<?= number_format($runway['daily_burn'], 2) ?></strong></div>
                <?php if ($runway['depletion_date']): ?>
                <div>Depletion date: <strong><?= $runway['depletion_date'] ?></strong></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Portal Snapshot Card -->
        <div class="cb-card">
            <h4>📦 Current Portal Charges</h4>
            <?php if (!empty($portalSummary['snapshot_time'])): ?>
            <div class="stat">$<?= number_format($portalSummary['total_amount'] ?? 0, 2) ?></div>
            <div class="label">Active billing this cycle</div>
            
            <div style="margin-top: 15px; font-size: 13px;">
                <div>Devices: <strong><?= $portalSummary['devices']['count'] ?? 0 ?></strong></div>
                <div>Hyper-V VMs: <strong><?= $portalSummary['hyperv_vms']['count'] ?? 0 ?></strong></div>
                <div>VMware VMs: <strong><?= $portalSummary['vmware_vms']['count'] ?? 0 ?></strong></div>
                <div>M365 Accounts: <strong><?= $portalSummary['m365_accounts']['count'] ?? 0 ?></strong></div>
            </div>
            <div class="label" style="margin-top: 10px;">
                Snapshot: <?= date('M j, g:i A', strtotime($portalSummary['snapshot_time'])) ?>
            </div>
            <?php else: ?>
            <div class="label">No portal data. <a href="<?= $baseUrl ?>&action=pullnow">Pull now</a></div>
            <?php endif; ?>
        </div>
        
        <!-- Reconciliation Card -->
        <div class="cb-card">
            <h4>🔍 Last Reconciliation</h4>
            <?php if ($lastRecon): ?>
            <div class="stat <?= $lastRecon->overall_status === 'ok' ? 'success' : 'warning' ?>">
                <?= strtoupper($lastRecon->overall_status) ?>
            </div>
            <div class="label"><?= date('M j, Y g:i A', strtotime($lastRecon->report_date)) ?></div>
            
            <?php if ($lastRecon->summary): ?>
            <div style="margin-top: 15px; font-size: 13px;">
                <div>OK: <strong class="cb-status-ok"><?= $lastRecon->summary['ok'] ?? 0 ?></strong></div>
                <div>Over-billed: <strong class="cb-status-warning"><?= $lastRecon->summary['over_billed'] ?? 0 ?></strong></div>
                <div>Under-billed: <strong class="cb-status-warning"><?= $lastRecon->summary['under_billed'] ?? 0 ?></strong></div>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="label">No reconciliation run yet.</div>
            <?php endif; ?>
            <div style="margin-top: 10px;">
                <a href="<?= $baseUrl ?>&action=reconcile" class="btn btn-sm btn-default">Run Reconciliation</a>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats Table -->
    <h4>Data Summary</h4>
    <table class="cb-table">
        <thead>
            <tr>
                <th>Metric</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Active Service Rows (Portal)</td>
                <td><?= number_format($svcCount) ?></td>
            </tr>
            <tr>
                <td>Usage History Rows</td>
                <td><?= number_format($usageCount) ?></td>
            </tr>
            <tr>
                <td>Total Purchases (Lifetime)</td>
                <td>$<?= number_format((float)$purchSum, 2) ?></td>
            </tr>
            <tr>
                <td>Last Daily Balance</td>
                <td>
                    <?php if ($lastBal): ?>
                    $<?= number_format((float)$lastBal->closing_credit, 2) ?> 
                    <span style="color: #666;">(<?= $lastBal->balance_date ?>)</span>
                    <?php else: ?>
                    N/A
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
    
    <!-- Actions -->
    <div class="cb-actions">
        <h4>Actions</h4>
        <a href="<?= $baseUrl ?>&action=pullnow" class="btn btn-primary">Pull Portal Data Now</a>
        <a href="<?= $baseUrl ?>&action=reconcile" class="btn btn-default">Run Reconciliation</a>
        <a href="<?= $baseUrl ?>&action=usage" class="btn btn-default">View Usage</a>
        <a href="<?= $baseUrl ?>&action=active_services" class="btn btn-default">View Active Services</a>
        <a href="<?= $baseUrl ?>&action=purchases" class="btn btn-default">Credit Purchases</a>
        <a href="<?= $baseUrl ?>&action=credit_lots" class="btn btn-default">Credit Lots (FIFO)</a>
        <a href="<?= $baseUrl ?>&action=keys" class="btn btn-default">API Keys</a>
    </div>
</div>
