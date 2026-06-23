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
    <div class="panel-heading"><strong>Scale fleet</strong></div>
    <div class="panel-body">
        <form id="fleet-scale-form" class="form-inline">
            <div class="form-group">
                <label>Proxmox node</label>
                <select name="proxmox_node" id="fleet-scale-node" class="form-control input-sm"><option value="">Loading…</option></select>
            </div>
            <div class="form-group" style="margin-left:10px">
                <label>Count</label>
                <input type="number" name="count" class="form-control input-sm" value="1" min="1" max="20" style="width:70px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-left:10px">Scale up</button>
        </form>
        <div id="fleet-scale-notice" style="margin-top:10px"></div>
        <p class="text-muted" style="margin-top:8px;margin-bottom:0"><small>Stops containers (reusable) via per-node <strong>Stop</strong>; cron autoscale is off by default. Cross-node clone needs shared storage or <code>proxmox_template_vmid_map</code>.</small></p>
    </div>
</div>
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
    <div class="panel-heading"><strong>Worker config.yaml</strong> <span id="fleet-config-version" class="label label-default">loading…</span></div>
    <div class="panel-body">
        <p class="text-muted">Fleet-wide template (secrets such as <code>MS365_WORKER_API_BASE</code> and <code>MS365_WORKER_TOKEN</code> stay in each node&rsquo;s <code>environment.conf</code> — omit any <code>api:</code> block here). Saved versions are pushed to nodes via heartbeat pull.</p>
        <textarea id="fleet-config-editor" class="form-control" rows="22" style="font-family:monospace;font-size:12px" spellcheck="false"></textarea>
        <div style="margin-top:10px">
            <button type="button" class="btn btn-default btn-sm" id="fleet-config-validate">Validate</button>
            <button type="button" class="btn btn-primary btn-sm" id="fleet-config-save">Save new version</button>
        </div>
        <div id="fleet-config-notice" style="margin-top:10px"></div>
    </div>
</div>
<div class="panel panel-default" id="fleet-config-rollout">
    <div class="panel-heading"><strong>Roll out config</strong></div>
    <div class="panel-body">
        <div id="fleet-config-status"><p class="text-muted">Loading rollout status…</p></div>
        <div class="form-inline" style="margin-bottom:10px">
            <div class="form-group">
                <label>Version</label>
                <input type="number" id="fleet-config-rollout-version" class="form-control input-sm" min="1" value="1" style="width:80px">
            </div>
            <div class="form-group" style="margin-left:10px">
                <label>Strategy</label>
                <select id="fleet-config-rollout-strategy" class="form-control input-sm">
                    <option value="explicit">Selected nodes</option>
                    <option value="all">All nodes</option>
                    <option value="idle">Idle only</option>
                    <option value="canary">Canary</option>
                </select>
            </div>
            <button type="button" class="btn btn-warning btn-sm fleet-config-preset" data-preset="all" style="margin-left:10px">Select all</button>
            <button type="button" class="btn btn-default btn-sm fleet-config-preset" data-preset="idle">Idle only</button>
            <button type="button" class="btn btn-default btn-sm fleet-config-preset" data-preset="canary">Canary</button>
            <button type="button" class="btn btn-primary btn-sm" id="fleet-config-rollout-btn" style="margin-left:10px">Roll out</button>
        </div>
        <div id="fleet-config-nodes" style="max-height:240px;overflow:auto;border:1px solid #ddd;padding:8px"><p class="text-muted">Loading nodes…</p></div>
    </div>
</div>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Fleet settings</strong> <small class="text-muted">(paths also configurable in Setup → Addon Modules → MS365 Backup)</small></div>
    <div class="panel-body" id="fleet-settings"><p class="text-muted">Loading…</p></div>
</div>
<?php endif; ?>
