<?php
use CometBilling\Settings;

$baseUrl = 'addonmodules.php?module=cometbilling';

$portalRunning = Settings::isJobRunning('portal_pull');
$collectRunning = Settings::isJobRunning('collect_usage');
$anyRunning = $portalRunning || $collectRunning;

$lastPortalPull = Settings::getKv('last_portal_pull_at');
$lastPortalStatus = Settings::getKv('last_portal_pull_status');
$lastPortalMessage = Settings::getKv('last_portal_pull_message');
$portalStartedAt = Settings::getJobStartedAt('portal_pull');

$lastCollect = Settings::getKv('last_collect_usage_at');
$lastCollectStatus = Settings::getKv('last_collect_usage_status');
$lastCollectMessage = Settings::getKv('last_collect_usage_message');
$collectStartedAt = Settings::getJobStartedAt('collect_usage');
?>
<style>
.cb-sync { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.cb-sync-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
.cb-sync-card { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; }
.cb-status-ok { color: #10b981; font-weight: 600; }
.cb-status-error { color: #ef4444; font-weight: 600; }
.cb-status-running { color: #d97706; font-weight: 600; }
.cb-badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; margin-bottom: 10px; }
.cb-badge-running { background: #fef3c7; color: #92400e; }
.cb-badge-idle { background: #f3f4f6; color: #4b5563; }
.cb-badge-ok { background: #d1fae5; color: #065f46; }
.cb-badge-error { background: #fee2e2; color: #991b1b; }
.cb-muted { color: #666; font-size: 12px; }
</style>

<div class="cb-sync">
    <h3>Data Sync</h3>
    <p>Pull billing data from the Comet Portal and collect server usage snapshots for reconciliation.</p>

    <?php if ($anyRunning): ?>
    <p class="cb-muted">A sync job is running. This page refreshes automatically every 15 seconds.</p>
    <?php endif; ?>

    <div class="cb-sync-grid">
        <div class="cb-sync-card">
            <h4>Portal Pull</h4>
            <?php if ($portalRunning): ?>
            <span class="cb-badge cb-badge-running">Running</span>
            <p>Started: <strong><?= htmlspecialchars($portalStartedAt ?? '—') ?></strong></p>
            <p class="cb-muted">Fetching billing history and active services from the Comet Portal. This may take 1–2 minutes.</p>
            <?php else: ?>
            <span class="cb-badge <?= $lastPortalStatus === 'ok' ? 'cb-badge-ok' : ($lastPortalStatus === 'error' ? 'cb-badge-error' : 'cb-badge-idle') ?>">
                <?= $lastPortalStatus ? htmlspecialchars(strtoupper($lastPortalStatus)) : 'IDLE' ?>
            </span>
            <p>Last completed: <strong><?= $lastPortalPull ? htmlspecialchars($lastPortalPull) : 'Never' ?></strong></p>
            <?php endif; ?>
            <?php if ($lastPortalMessage && !$portalRunning): ?>
            <pre style="font-size: 11px; max-height: 120px; overflow: auto; background: #f9fafb; padding: 8px;"><?= htmlspecialchars($lastPortalMessage) ?></pre>
            <?php endif; ?>
            <p>
                <a href="<?= $baseUrl ?>&action=pullnow" class="btn btn-primary"<?= $portalRunning ? ' style="pointer-events:none;opacity:0.6"' : '' ?>>Pull Portal Data Now</a>
            </p>
        </div>

        <div class="cb-sync-card">
            <h4>Server Usage Collection</h4>
            <?php if ($collectRunning): ?>
            <span class="cb-badge cb-badge-running">Running</span>
            <p>Started: <strong><?= htmlspecialchars($collectStartedAt ?? '—') ?></strong></p>
            <p class="cb-muted">Collecting device and VM counts from Comet servers via Admin API.</p>
            <?php else: ?>
            <span class="cb-badge <?= $lastCollectStatus === 'ok' ? 'cb-badge-ok' : ($lastCollectStatus === 'error' ? 'cb-badge-error' : 'cb-badge-idle') ?>">
                <?= $lastCollectStatus ? htmlspecialchars(strtoupper($lastCollectStatus)) : 'IDLE' ?>
            </span>
            <p>Last completed: <strong><?= $lastCollect ? htmlspecialchars($lastCollect) : 'Never' ?></strong></p>
            <?php endif; ?>
            <?php if ($lastCollectMessage && !$collectRunning): ?>
            <pre style="font-size: 11px; max-height: 120px; overflow: auto; background: #f9fafb; padding: 8px;"><?= htmlspecialchars($lastCollectMessage) ?></pre>
            <?php endif; ?>
            <p>
                <a href="<?= $baseUrl ?>&action=collect_usage" class="btn btn-primary"<?= $collectRunning ? ' style="pointer-events:none;opacity:0.6"' : '' ?>>Collect Server Usage</a>
            </p>
        </div>
    </div>

    <p><a href="<?= $baseUrl ?>" class="btn btn-default">← Back to Dashboard</a></p>
</div>

<?php if ($anyRunning): ?>
<script>
setTimeout(function () { window.location.reload(); }, 15000);
</script>
<?php endif; ?>
