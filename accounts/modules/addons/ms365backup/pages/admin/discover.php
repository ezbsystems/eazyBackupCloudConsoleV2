<?php
declare(strict_types=1);

$tab = (string) ($_GET['tab'] ?? 'users');
if (!in_array($tab, ['users', 'sites', 'teams'], true)) {
    $tab = 'users';
}
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$base = 'addonmodules.php?module=ms365backup&action=discover';
$apiBase = 'addonmodules.php?module=ms365backup&action=api';
$token = generate_token('plain');
?>
<ul class="nav nav-tabs" style="margin-bottom:15px">
    <li<?= $tab === 'users' ? ' class="active"' : '' ?>><a href="<?= $e($base) ?>&tab=users">Users</a></li>
    <li<?= $tab === 'sites' ? ' class="active"' : '' ?>><a href="<?= $e($base) ?>&tab=sites">SharePoint sites</a></li>
    <li<?= $tab === 'teams' ? ' class="active"' : '' ?>><a href="<?= $e($base) ?>&tab=teams">Teams</a></li>
</ul>

<div class="form-inline" style="margin-bottom:15px">
    <input type="text" id="ms365-discover-filter" class="form-control" placeholder="Filter…" style="width:280px">
    <button type="button" class="btn btn-primary" id="ms365-discover-refresh">Refresh from Graph</button>
    <button type="button" class="btn btn-default" id="ms365-discover-load-cache">Load cached</button>
    <?php if (in_array($tab, ['users', 'sites'], true)): ?>
    <button type="button" class="btn btn-default" id="ms365-discover-check-access"><?= $tab === 'sites' ? 'Check site access' : 'Check access' ?></button>
    <?php endif; ?>
    <span id="ms365-discover-status" class="text-muted" style="margin-left:10px"></span>
</div>

<div class="table-responsive">
    <table class="table table-striped table-bordered" id="ms365-discover-table">
        <thead id="ms365-discover-thead"></thead>
        <tbody id="ms365-discover-tbody">
            <tr><td class="text-muted">Click Refresh or Load cached.</td></tr>
        </tbody>
    </table>
</div>

<script>
window.MS365_API = <?= json_encode($apiBase) ?>;
window.MS365_TOKEN = <?= json_encode($token) ?>;
window.MS365_DISCOVER_TAB = <?= json_encode($tab) ?>;
</script>
<script src="<?= $e(ms365backup_asset_url('assets/js/ms365-admin.js')) ?>"></script>
