<?php
declare(strict_types=1);

$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$apiBase = 'addonmodules.php?module=ms365backup&action=api';
$token = generate_token('plain');
?>
<script>window.MS365_USERS_API = <?= json_encode($apiBase) ?>; window.MS365_TOKEN = <?= json_encode($token) ?>;</script>
<script src="<?= $e(ms365backup_asset_url('assets/js/users.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../../assets/js/users.js') ?>"></script>

<div id="ms365-users-app">
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Filters</strong></div>
        <div class="panel-body">
            <form id="ms365-users-filters" class="form-inline">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control input-sm" placeholder="Client, username, email">
                </div>
                <div class="form-group" style="margin-left:10px">
                    <label>Status</label>
                    <select name="status" class="form-control input-sm">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-left:10px">Apply</button>
                <button type="button" class="btn btn-default btn-sm" id="ms365-users-reset">Reset</button>
            </form>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading clearfix">
            <div class="pull-left" style="margin-top:2px"><strong>MS365 backup users</strong></div>
            <button type="button" class="btn btn-xs btn-default pull-right" id="ms365-users-refresh">Refresh</button>
        </div>
        <div class="panel-body" id="ms365-users-table-wrap">
            <p class="text-muted">Loading users…</p>
        </div>
        <div class="panel-footer" id="ms365-users-pagination"></div>
    </div>
</div>
