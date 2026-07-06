<?php
use WHMCS\Database\Capsule;
use CometBilling\PortalUsageExtractor;
use CometBilling\CreditLedger;
use CometBilling\Reconciler;
use CometBilling\Settings;

$usageCount = Capsule::table('cb_credit_usage')->count();
$svcCount   = Capsule::table('cb_active_services')->count();
$purchSum   = Capsule::table('cb_credit_purchases')->sum(Capsule::raw('credit_amount + bonus_credit'));
$lastBal    = Capsule::table('cb_daily_balance')->orderBy('balance_date', 'desc')->first();
$purchaseCount = Capsule::table('cb_credit_purchases')->count();
$lotCount = 0;

$portalSummary = [];
try {
    $portalSummary = PortalUsageExtractor::getSummary();
} catch (\Exception $e) {
    $portalSummary = ['error' => $e->getMessage()];
}

$creditBalance = ['purchased' => 0, 'bonus' => 0, 'total' => 0];
$isUsingBonus = false;
try {
    $creditBalance = CreditLedger::getCurrentBalance();
    $isUsingBonus = CreditLedger::isUsingBonusCredits();
    $lotCount = Capsule::table('cb_credit_lots')->count();
} catch (\Exception $e) {
    // Tables may not exist yet
}

$runway = ['daily_burn' => 0, 'days_remaining' => null];
try {
    $runway = CreditLedger::estimateRunway(30);
} catch (\Exception $e) {
    // Ignore
}

$lastRecon = null;
try {
    $reports = Reconciler::getReports(1);
    $lastRecon = $reports[0] ?? null;
} catch (\Exception $e) {
    // Table may not exist
}

$addonSettings = Settings::getAddonSettings();
$hasToken = !empty($addonSettings['PortalToken']);
$lastPortalPull = Settings::getKv('last_portal_pull_at');
$lastCollect = Settings::getKv('last_collect_usage_at');
$portalBalance = $lastBal ? (float) $lastBal->closing_credit : null;
$fifoVariance = ($portalBalance !== null) ? abs($portalBalance - $creditBalance['total']) : null;

$baseUrl = 'addonmodules.php?module=cometbilling';

$setupSteps = [
    ['label' => 'Configure Portal Token', 'done' => $hasToken, 'url' => 'configaddonmods.php'],
    ['label' => 'Pull portal billing data', 'done' => $usageCount > 0, 'url' => $baseUrl . '&action=pullnow'],
    ['label' => 'Record credit purchases', 'done' => $purchaseCount > 0, 'url' => $baseUrl . '&action=purchases'],
    ['label' => 'Create credit lots (FIFO)', 'done' => $lotCount > 0, 'url' => $baseUrl . '&action=credit_lots'],
    ['label' => 'Collect server usage', 'done' => !empty($lastCollect), 'url' => $baseUrl . '&action=collect_usage'],
    ['label' => 'Run reconciliation', 'done' => $lastRecon !== null, 'url' => $baseUrl . '&action=reconcile'],
];
$setupComplete = count(array_filter($setupSteps, fn($s) => $s['done'])) === count($setupSteps);
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
.cb-checklist { list-style: none; padding: 0; margin: 0; }
.cb-checklist li { padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
.cb-checklist .done { color: #10b981; }
.cb-checklist .pending { color: #666; }
.alert-box { padding: 15px; border-radius: 8px; margin: 15px 0; background: #fef3c7; border: 1px solid #f59e0b; color: #92400e; }
</style>

<div class="cb-dashboard">
    <h3>Comet Billing Dashboard</h3>

    <?php if ($isUsingBonus): ?>
    <div class="alert-box">
        <strong>⚠️ Using Bonus Credits</strong> — Purchased credits are depleted. FIFO lot tracking shows you are now consuming bonus credits.
    </div>
    <?php endif; ?>

    <?php if (!$setupComplete): ?>
    <div class="cb-card" style="margin-bottom: 20px;">
        <h4>🚀 Setup Checklist</h4>
        <ul class="cb-checklist">
            <?php foreach ($setupSteps as $step): ?>
            <li class="<?= $step['done'] ? 'done' : 'pending' ?>">
                <?= $step['done'] ? '✓' : '○' ?>
                <?php if (!$step['done']): ?>
                <a href="<?= htmlspecialchars($step['url']) ?>"><?= htmlspecialchars($step['label']) ?></a>
                <?php else: ?>
                <?= htmlspecialchars($step['label']) ?>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="cb-cards">
        <div class="cb-card">
            <h4>💳 FIFO Lot Balance</h4>
            <div class="stat <?= $creditBalance['total'] < 1000 ? 'warning' : '' ?>">
                $<?= number_format($creditBalance['total'], 2) ?>
            </div>
            <div class="label">Credit pack lots (FIFO consumption)</div>
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

        <div class="cb-card">
            <h4>📊 Portal-Reconciled Balance</h4>
            <div class="stat">
                <?= $portalBalance !== null ? '$' . number_format($portalBalance, 2) : 'N/A' ?>
            </div>
            <div class="label">From daily balance roll-forward (portal usage)</div>
            <?php if ($lastBal): ?>
            <div style="margin-top: 15px; font-size: 13px;">
                <div>As of: <strong><?= htmlspecialchars($lastBal->balance_date) ?></strong></div>
                <?php if ($fifoVariance !== null && $fifoVariance > 1.0): ?>
                <div style="color: #f59e0b;">FIFO variance: <strong>$<?= number_format($fifoVariance, 2) ?></strong></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="cb-card">
            <h4>📊 Credit Runway</h4>
            <div class="stat <?= ($runway['days_remaining'] ?? 999) < 30 ? 'warning' : '' ?>">
                <?= $runway['days_remaining'] !== null ? $runway['days_remaining'] . ' days' : 'N/A' ?>
            </div>
            <div class="label">Estimated until depletion (FIFO lots)</div>
            <div style="margin-top: 15px; font-size: 13px;">
                <div>Daily burn rate: <strong>$<?= number_format($runway['daily_burn'], 2) ?></strong></div>
                <?php if ($runway['depletion_date']): ?>
                <div>Depletion date: <strong><?= $runway['depletion_date'] ?></strong></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="cb-card">
            <h4>🔄 Sync Status</h4>
            <div style="font-size: 13px;">
                <div>Last portal pull: <strong><?= $lastPortalPull ? htmlspecialchars($lastPortalPull) : 'Never' ?></strong></div>
                <div>Last usage collect: <strong><?= $lastCollect ? htmlspecialchars($lastCollect) : 'Never' ?></strong></div>
            </div>
            <div style="margin-top: 15px;">
                <a href="<?= $baseUrl ?>&action=sync" class="btn btn-sm btn-default">Data Sync</a>
            </div>
        </div>

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

    <h4>Data Summary</h4>
    <table class="cb-table">
        <thead>
            <tr><th>Metric</th><th>Value</th></tr>
        </thead>
        <tbody>
            <tr><td>Active Service Rows (Portal)</td><td><?= number_format($svcCount) ?></td></tr>
            <tr><td>Usage History Rows</td><td><?= number_format($usageCount) ?></td></tr>
            <tr><td>Total Purchases (Lifetime)</td><td>$<?= number_format((float)$purchSum, 2) ?></td></tr>
            <tr>
                <td>Portal-Reconciled Balance</td>
                <td>
                    <?php if ($lastBal): ?>
                    $<?= number_format((float)$lastBal->closing_credit, 2) ?>
                    <span style="color: #666;">(<?= $lastBal->balance_date ?>)</span>
                    <?php else: ?>N/A<?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="cb-actions">
        <h4>Actions</h4>
        <a href="<?= $baseUrl ?>&action=sync" class="btn btn-primary">Data Sync</a>
        <a href="<?= $baseUrl ?>&action=pullnow" class="btn btn-default">Pull Portal Data</a>
        <a href="<?= $baseUrl ?>&action=collect_usage" class="btn btn-default">Collect Server Usage</a>
        <a href="<?= $baseUrl ?>&action=reconcile" class="btn btn-default">Run Reconciliation</a>
        <a href="<?= $baseUrl ?>&action=allocations" class="btn btn-default">Allocation History</a>
        <a href="<?= $baseUrl ?>&action=usage" class="btn btn-default">View Usage</a>
        <a href="<?= $baseUrl ?>&action=purchases" class="btn btn-default">Credit Purchases</a>
        <a href="<?= $baseUrl ?>&action=credit_lots" class="btn btn-default">Credit Lots (FIFO)</a>
    </div>
</div>
