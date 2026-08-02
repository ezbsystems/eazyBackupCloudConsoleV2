<?php
declare(strict_types=1);

$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$apiBase = 'addonmodules.php?module=ms365backup&action=api';
$token = generate_token('plain');
$deepLinkUserId = (int) ($_GET['backup_user_id'] ?? 0);
?>
<script>
  window.MS365_DEPROVISION_API = <?= json_encode($apiBase) ?>;
  window.MS365_TOKEN = <?= json_encode($token) ?>;
  window.MS365_DEPROVISION_DEEP_LINK_USER_ID = <?= (int) $deepLinkUserId ?>;
</script>
<script src="<?= $e(ms365backup_asset_url('assets/js/deprovision.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../../assets/js/deprovision.js') ?>"></script>

<div id="ms365-deprovision-app">
    <div class="alert alert-warning">
        <strong>e3 Backup User deprovision only.</strong>
        This workflow cancels a single <em>e3 Backup User</em> WHMCS product and cascades cleanup for that backup user
        (jobs, MS365 vault recycle, agents, MS365 disconnect). It does <strong>not</strong> cancel or deprovision
        <em>e3 Object Storage</em> / customer RGW buckets. For full storage offboarding use
        <a href="addonmodules.php?module=cloudstorage&amp;action=deprovision" target="_blank" rel="noopener">Cloud Storage &rarr; Deprovision Customer</a>.
    </div>

    <div class="panel panel-default" id="ms365-dep-search-panel">
        <div class="panel-heading"><strong>Find e3 Backup User</strong></div>
        <div class="panel-body">
            <div class="form-group">
                <label for="ms365-dep-client-search">Search client</label>
                <input type="text" class="form-control" id="ms365-dep-client-search"
                       placeholder="Name, company, email, or client ID (min 2 characters)" autocomplete="off">
                <div id="ms365-dep-client-results" class="list-group" style="margin-top:8px;display:none"></div>
            </div>

            <p class="text-muted text-center" style="margin:12px 0"><strong>— or —</strong></p>

            <form id="ms365-dep-service-form" class="form-inline">
                <div class="form-group">
                    <label for="ms365-dep-service-id">WHMCS Service ID</label>
                    <input type="number" class="form-control input-sm" id="ms365-dep-service-id" placeholder="e.g. 5471" style="margin-left:6px">
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-left:10px">Lookup</button>
            </form>
        </div>
    </div>

    <div class="panel panel-default" id="ms365-dep-list-panel" style="display:none">
        <div class="panel-heading clearfix">
            <div class="pull-left" style="margin-top:2px">
                <strong>e3 Backup Users</strong>
                <span class="text-muted" id="ms365-dep-client-label"></span>
            </div>
            <button type="button" class="btn btn-xs btn-default pull-right" id="ms365-dep-back-search">Change client</button>
        </div>
        <div class="panel-body" id="ms365-dep-users-wrap">
            <p class="text-muted">Loading…</p>
        </div>
    </div>

    <div class="panel panel-danger" id="ms365-dep-preview-panel" style="display:none">
        <div class="panel-heading clearfix">
            <div class="pull-left"><strong>Deprovision preview</strong></div>
            <button type="button" class="btn btn-xs btn-default pull-right" id="ms365-dep-back-list">Back to list</button>
        </div>
        <div class="panel-body" id="ms365-dep-preview-wrap">
            <p class="text-muted">Loading preview…</p>
        </div>
        <div class="panel-footer" id="ms365-dep-confirm-wrap" style="display:none">
            <div class="form-group">
                <label for="ms365-dep-confirm-phrase">Type confirmation phrase</label>
                <input type="text" class="form-control" id="ms365-dep-confirm-phrase" placeholder="" autocomplete="off">
                <p class="help-block" id="ms365-dep-confirm-hint"></p>
            </div>
            <button type="button" class="btn btn-danger" id="ms365-dep-execute-btn" disabled>Deprovision backup user</button>
            <span class="text-muted" id="ms365-dep-execute-status" style="margin-left:10px"></span>
        </div>
    </div>
</div>
