<?php
declare(strict_types=1);

$tab = (string) ($_GET['tab'] ?? 'dashboard');
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$apiBase = 'addonmodules.php?module=ms365backup&action=api';
$token = generate_token('plain');
$fleetBase = 'addonmodules.php?module=ms365backup&action=fleet';
?>
<script>window.MS365_FLEET_API = <?= json_encode($apiBase) ?>; window.MS365_TOKEN = <?= json_encode($token) ?>;</script>
<script src="<?= $e(ms365backup_asset_url('assets/js/fleet.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../../assets/js/fleet.js') ?>"></script>

<ul class="nav nav-tabs" style="margin-bottom:15px">
<?php foreach (['dashboard' => 'Dashboard', 'nodes' => 'Nodes', 'builds' => 'Builds', 'deployments' => 'Deployments', 'settings' => 'Settings'] as $key => $label): ?>
    <li<?= $tab === $key ? ' class="active"' : '' ?>><a href="<?= $e($fleetBase . '&tab=' . $key) ?>"><?= $e($label) ?></a></li>
<?php endforeach; ?>
</ul>

<?php if ($tab === 'dashboard'): ?>
<div id="fleet-dashboard">
    <p class="text-muted">Loading fleet summary…</p>
</div>
<div class="panel panel-default" style="margin-top:15px">
    <div class="panel-heading"><strong>Recent audit</strong></div>
    <div class="panel-body" id="fleet-audit"><p class="text-muted">Loading…</p></div>
</div>
<?php elseif ($tab === 'nodes'): ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Worker nodes</strong>
        <button type="button" class="btn btn-xs btn-default pull-right" id="fleet-refresh-nodes">Refresh</button>
        <button type="button" class="btn btn-xs btn-warning pull-right" id="fleet-release-leases" style="margin-right:6px">Release stale leases</button>
    </div>
    <div class="panel-body" id="fleet-nodes"><p class="text-muted">Loading…</p></div>
</div>
<?php elseif ($tab === 'builds'): ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Build worker binary</strong></div>
    <div class="panel-body">
        <form id="fleet-build-form" class="form-inline">
            <div class="form-group">
                <label>Version</label>
                <input type="text" name="version_label" class="form-control input-sm" placeholder="0.1.18" pattern="\d+\.\d+\.\d+" title="Three-part version, e.g. 0.1.18" required>
            </div>
            <div class="form-group" style="margin-left:10px">
                <label>Git ref</label>
                <input type="text" name="git_ref" class="form-control input-sm" value="main">
            </div>
            <label class="checkbox" style="margin-left:10px"><input type="checkbox" name="run_tests" checked> Tests</label>
            <label class="checkbox"><input type="checkbox" name="git_sync"> Git sync</label>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-left:10px">Queue build</button>
        </form>
        <div id="fleet-build-notice" style="margin-top:10px"></div>
    </div>
</div>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Build jobs</strong></div>
    <div class="panel-body" id="fleet-builds"><p class="text-muted">Loading…</p></div>
</div>
<div class="panel panel-default" id="fleet-build-detail-panel" style="display:none">
    <div class="panel-heading"><strong>Build detail</strong></div>
    <div class="panel-body" id="fleet-build-detail"></div>
</div>
<?php elseif ($tab === 'deployments'): ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Deploy release</strong></div>
    <div class="panel-body">
        <form id="fleet-deploy-form" class="form-inline">
            <div class="form-group">
                <label>Release</label>
                <select name="release_id" id="fleet-deploy-release" class="form-control input-sm"><option value="">Loading…</option></select>
            </div>
            <div class="form-group" style="margin-left:10px">
                <label>Strategy</label>
                <select name="strategy" class="form-control input-sm">
                    <option value="rolling">Rolling</option>
                    <option value="all_idle">All idle</option>
                    <option value="canary">Canary</option>
                    <option value="force">Force</option>
                </select>
            </div>
            <label class="checkbox" style="margin-left:10px"><input type="checkbox" name="force_deploy"> Force (running jobs)</label>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-left:10px">Start deploy</button>
        </form>
        <div id="fleet-deploy-notice" style="margin-top:10px"></div>
    </div>
</div>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Deploy jobs</strong></div>
    <div class="panel-body" id="fleet-deployments"><p class="text-muted">Loading…</p></div>
</div>
<?php else: ?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Fleet settings</strong> <small class="text-muted">(paths also configurable in Setup → Addon Modules → MS365 Backup)</small></div>
    <div class="panel-body" id="fleet-settings"><p class="text-muted">Loading…</p></div>
</div>
<?php endif; ?>
