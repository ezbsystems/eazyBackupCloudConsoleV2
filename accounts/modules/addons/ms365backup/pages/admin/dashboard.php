<?php
declare(strict_types=1);

use Ms365Backup\RegionEndpoints;
use Ms365Backup\TenantRepository;

$row = TenantRepository::get() ?? [];
$regions = RegionEndpoints::allowedRegions();
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$apiBase = 'addonmodules.php?module=ms365backup&action=api';
$token = generate_token('plain');
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Tenant credentials</strong> (single organization)</div>
    <div class="panel-body">
        <form id="ms365-config-form" class="form-horizontal">
            <div class="form-group">
                <label class="col-sm-2 control-label">REGION</label>
                <div class="col-sm-6">
                    <select name="region" id="ms365-region" class="form-control">
                        <?php foreach ($regions as $r): ?>
                            <option value="<?= $e($r) ?>"<?= (($row['region'] ?? '') === $r) ? ' selected' : '' ?>><?= $e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">CLIENT_ID</label>
                <div class="col-sm-6">
                    <input type="text" name="client_id" id="ms365-client-id" class="form-control" value="<?= $e($row['client_id'] ?? '') ?>" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">TENANT_ID</label>
                <div class="col-sm-6">
                    <input type="text" name="tenant_id" id="ms365-tenant-id" class="form-control" value="<?= $e($row['tenant_id'] ?? '') ?>" autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label class="col-sm-2 control-label">APP_SECRET</label>
                <div class="col-sm-6">
                    <input type="password" name="app_secret" id="ms365-app-secret" class="form-control" placeholder="<?= !empty($row['app_secret_enc']) ? '(saved — leave blank to keep)' : '' ?>" autocomplete="new-password">
                </div>
            </div>
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-6">
                    <button type="button" class="btn btn-primary" id="ms365-save-config">Save credentials</button>
                    <button type="button" class="btn btn-default" id="ms365-test-auth">Test connection</button>
                </div>
            </div>
        </form>
        <div id="ms365-config-notice"></div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading"><strong>Quick start</strong></div>
    <div class="panel-body">
        <ol>
            <li>Register an Entra ID app and grant application permissions (see <code>Docs/AZURE_SETUP.md</code>).</li>
            <li>Save credentials above and test the connection.</li>
            <li>Open <a href="addonmodules.php?module=ms365backup&action=discover">Discovery</a> to list users, SharePoint sites, and Teams.</li>
            <li>Open <a href="addonmodules.php?module=ms365backup&action=backup">Backup</a> to run a mailbox + calendar backup for one user.</li>
            <li>Populate a dev tenant with test data via <a href="addonmodules.php?module=ms365backup&action=seeder">Tenant Seeder</a>.</li>
        </ol>
        <p class="text-muted">Backups are stored under <code>/var/www/eazybackup/ms365/</code></p>
    </div>
</div>

<script>
window.MS365_API = <?= json_encode($apiBase) ?>;
window.MS365_TOKEN = <?= json_encode($token) ?>;
</script>
<script src="<?= $e(ms365backup_asset_url('assets/js/ms365-admin.js')) ?>"></script>
