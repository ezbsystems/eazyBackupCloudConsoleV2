<?php
declare(strict_types=1);

use Ms365Backup\RegionEndpoints;
use Ms365Backup\Seeder\SeederConfigRepository;
use Ms365Backup\Seeder\SeederEntraConfig;
use Ms365Backup\Seeder\SeederProfileCatalog;

$row = SeederConfigRepository::get() ?? [];
$regions = RegionEndpoints::allowedRegions();
$profiles = SeederProfileCatalog::profileKeys();
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$apiBase = 'addonmodules.php?module=ms365backup&action=api';
$token = generate_token('plain');
$redirectUri = SeederEntraConfig::redirectUri();
?>
<div class="alert alert-warning">
    <strong>Dev / test tenants only.</strong> This tool writes real data into Microsoft 365 (mail, files, Teams messages, etc.).
    Use a dedicated Entra app with write permissions — not the read-only backup app.
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Seeder app credentials</strong></div>
    <div class="panel-body">
        <form id="ms365-seeder-config-form" class="form-horizontal">
            <div class="form-group">
                <label class="col-sm-2 control-label">REGION</label>
                <div class="col-sm-6">
                    <select name="region" id="ms365-seeder-region" class="form-control">
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= $e($r) ?>"<?= (($row['region'] ?? '') === $r) ? ' selected' : '' ?>><?= $e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">TENANT_ID</label>
                <div class="col-sm-6">
                    <input type="text" name="tenant_id" id="ms365-seeder-tenant-id" class="form-control" value="<?= $e($row['tenant_id'] ?? '') ?>" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">CLIENT_ID</label>
                <div class="col-sm-6">
                    <input type="text" name="client_id" id="ms365-seeder-client-id" class="form-control" value="<?= $e($row['client_id'] ?? '') ?>" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">APP_SECRET</label>
                <div class="col-sm-6">
                    <input type="password" name="app_secret" id="ms365-seeder-app-secret" class="form-control" placeholder="<?= !empty($row['app_secret_enc']) ? '(saved — leave blank to keep)' : '' ?>" autocomplete="new-password">
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-6">
                    <button type="button" class="btn btn-primary" id="ms365-seeder-save-config">Save credentials</button>
                    <button type="button" class="btn btn-default" id="ms365-seeder-test-auth">Test connection</button>
                </div>
            </div>
        </form>
        <p class="text-muted">OAuth redirect URI for seed user: <code><?= $e($redirectUri) ?></code></p>
        <div id="ms365-seeder-config-notice"></div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Seed user (Teams)</strong></div>
    <div class="panel-body">
        <p>Teams channel messages require <strong>delegated</strong> OAuth. Sign in as a licensed user who is a member of your Teams.</p>
        <p id="ms365-seeder-user-status">
            <?php if (!empty($row['seed_user_upn'])): ?>
                Connected: <strong><?= $e($row['seed_user_upn']) ?></strong>
            <?php else: ?>
                <span class="text-muted">Not connected</span>
            <?php endif; ?>
        </p>
        <button type="button" class="btn btn-primary" id="ms365-seeder-connect-user">Connect seed user</button>
        <button type="button" class="btn btn-default" id="ms365-seeder-disconnect-user">Disconnect</button>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Seed run</strong></div>
    <div class="panel-body">
        <div class="form-horizontal">
            <div class="form-group">
                <label class="col-sm-2 control-label">Profile</label>
                <div class="col-sm-4">
                    <select id="ms365-seeder-profile" class="form-control">
                        <?php foreach ($profiles as $p): ?>
                            <option value="<?= $e($p) ?>"><?= $e(ucfirst($p)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">Workloads</label>
                <div class="col-sm-8">
                    <?php
                    $workloads = ['mail', 'calendar', 'contacts', 'tasks', 'onedrive', 'sharepoint', 'teams'];
                    foreach ($workloads as $w):
                    ?>
                        <label class="checkbox-inline"><input type="checkbox" class="ms365-seeder-workload" value="<?= $e($w) ?>" checked> <?= $e(ucfirst($w)) ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">Scope</label>
                <div class="col-sm-8">
                    <label class="checkbox-inline"><input type="checkbox" id="ms365-seeder-all-users" checked> All users</label>
                    <label class="checkbox-inline"><input type="checkbox" id="ms365-seeder-all-sites" checked> All sites</label>
                    <label class="checkbox-inline"><input type="checkbox" id="ms365-seeder-all-teams" checked> All teams</label>
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-6">
                    <button type="button" class="btn btn-success" id="ms365-seeder-discover">Refresh targets</button>
                    <button type="button" class="btn btn-primary" id="ms365-seeder-start">Start seeding</button>
                    <button type="button" class="btn btn-warning" id="ms365-seeder-cancel" style="display:none">Cancel</button>
                </div>
            </div>
        </div>
        <p id="ms365-seeder-targets" class="text-muted">Targets: not loaded</p>
        <div id="ms365-seeder-progress" style="display:none;margin-top:15px">
            <h4>Progress</h4>
            <p><strong>Status:</strong> <span id="ms365-seeder-status">—</span></p>
            <p><strong>Phase:</strong> <span id="ms365-seeder-phase">—</span></p>
            <div class="progress"><div id="ms365-seeder-bar" class="progress-bar progress-bar-info" style="width:0%"></div></div>
            <pre id="ms365-seeder-log" style="max-height:240px;overflow:auto;background:#f5f5f5;padding:10px;font-size:12px"></pre>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Recent runs</strong></div>
    <div class="panel-body">
        <table class="table table-striped" id="ms365-seeder-runs-table">
            <thead><tr><th>Run ID</th><th>Profile</th><th>Status</th><th>Created</th></tr></thead>
            <tbody id="ms365-seeder-runs-tbody"><tr><td colspan="4" class="text-muted">Loading…</td></tr></tbody>
        </table>
    </div>
</div>

<script>
window.MS365_API = <?= json_encode($apiBase) ?>;
window.MS365_TOKEN = <?= json_encode($token) ?>;
</script>
<script src="<?= $e(ms365backup_asset_url('assets/js/ms365-admin.js')) ?>"></script>
<script src="<?= $e(ms365backup_asset_url('assets/js/ms365-seeder.js')) ?>"></script>
