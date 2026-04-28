<?php

declare(strict_types=1);

use WHMCS\Database\Capsule;

if (!isset($_SESSION['adminid']) || (int)$_SESSION['adminid'] <= 0) {
    echo '<div class="alert alert-danger">Not authorized.</div>';
    return;
}

$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$adminId = (int)$_SESSION['adminid'];

$op   = (string)($_REQUEST['op'] ?? 'list');
$nid  = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$notice = '';

$tokenOk = (function (): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return true;
    return function_exists('check_token') && check_token('WHMCS.admin.default');
})();

// Helpers
$parseClientIds = function (string $raw): array {
    $ids = [];
    foreach (preg_split('/[\s,;]+/', $raw) as $tok) {
        $tok = trim($tok);
        if ($tok === '') continue;
        if (ctype_digit($tok)) $ids[] = (int)$tok;
    }
    $ids = array_values(array_unique($ids));
    if (empty($ids)) return [];
    $valid = Capsule::table('tblclients')->whereIn('id', $ids)->pluck('id')->all();
    return array_map('intval', $valid);
};

$saveTargets = function (int $notifId, string $audienceType, array $products, array $groups, array $clients): void {
    Capsule::table('eb_notification_targets')->where('notification_id', $notifId)->delete();
    if ($audienceType !== 'filtered') return;
    $rows = [];
    foreach ($products as $pid) { $rows[] = ['notification_id'=>$notifId,'target_type'=>'product','target_id'=>(int)$pid]; }
    foreach ($groups as $gid)   { $rows[] = ['notification_id'=>$notifId,'target_type'=>'client_group','target_id'=>(int)$gid]; }
    foreach ($clients as $cid)  { $rows[] = ['notification_id'=>$notifId,'target_type'=>'client','target_id'=>(int)$cid]; }
    if (!empty($rows)) {
        Capsule::table('eb_notification_targets')->insert($rows);
    }
};

// Handle POST operations
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!$tokenOk) {
        $notice = '<div class="alert alert-danger">Invalid CSRF token.</div>';
        $op = (string)($_POST['op'] ?? 'list');
    } else {
        try {
            if ($op === 'save') {
                $id            = (int)($_POST['id'] ?? 0);
                $title         = trim((string)($_POST['title'] ?? ''));
                $body          = (string)($_POST['body'] ?? '');
                $audienceType  = (string)($_POST['audience_type'] ?? 'all');
                $audienceType  = in_array($audienceType, ['all','filtered'], true) ? $audienceType : 'all';
                $publish       = isset($_POST['save_publish']);
                $products      = isset($_POST['target_products']) && is_array($_POST['target_products']) ? array_map('intval', $_POST['target_products']) : [];
                $groups        = isset($_POST['target_groups']) && is_array($_POST['target_groups']) ? array_map('intval', $_POST['target_groups']) : [];
                $clients       = $parseClientIds((string)($_POST['target_clients'] ?? ''));

                if ($title === '') throw new \RuntimeException('Title is required.');
                if ($body === '')  throw new \RuntimeException('Body is required.');
                if ($audienceType === 'filtered' && empty($products) && empty($groups) && empty($clients)) {
                    throw new \RuntimeException('Filtered audience requires at least one product, client group, or client ID.');
                }

                $now = date('Y-m-d H:i:s');
                if ($id > 0) {
                    $data = [
                        'title' => $title,
                        'body'  => $body,
                        'audience_type' => $audienceType,
                        'updated_at' => $now,
                    ];
                    if ($publish) {
                        $data['status'] = 'published';
                        $data['published_at'] = $now;
                    }
                    Capsule::table('eb_notifications')->where('id', $id)->update($data);
                    $saveTargets($id, $audienceType, $products, $groups, $clients);
                    $nid = $id;
                } else {
                    $nid = (int)Capsule::table('eb_notifications')->insertGetId([
                        'title' => $title,
                        'body'  => $body,
                        'status' => $publish ? 'published' : 'draft',
                        'audience_type' => $audienceType,
                        'created_by' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'published_at' => $publish ? $now : null,
                    ]);
                    $saveTargets($nid, $audienceType, $products, $groups, $clients);
                }
                $notice = '<div class="alert alert-success">Notification saved' . ($publish ? ' and published.' : '.') . '</div>';
                $op = 'edit';
            } elseif ($op === 'publish' || $op === 'unpublish') {
                if ($nid <= 0) throw new \RuntimeException('Invalid notification id.');
                $data = ['status' => $op === 'publish' ? 'published' : 'draft', 'updated_at' => date('Y-m-d H:i:s')];
                if ($op === 'publish') $data['published_at'] = date('Y-m-d H:i:s');
                Capsule::table('eb_notifications')->where('id', $nid)->update($data);
                $notice = '<div class="alert alert-success">Notification ' . ($op === 'publish' ? 'published' : 'unpublished') . '.</div>';
                $op = 'list';
            } elseif ($op === 'delete') {
                if ($nid <= 0) throw new \RuntimeException('Invalid notification id.');
                Capsule::table('eb_notification_targets')->where('notification_id', $nid)->delete();
                Capsule::table('mod_eazybackup_dismissals')->where('announcement_key', 'notif:' . $nid)->delete();
                Capsule::table('eb_notifications')->where('id', $nid)->delete();
                $notice = '<div class="alert alert-success">Notification deleted.</div>';
                $op = 'list';
            }
        } catch (\Throwable $ex) {
            $notice = '<div class="alert alert-danger">Error: ' . $e($ex->getMessage()) . '</div>';
        }
    }
}

// Tab strip
$tabs = '<ul class="nav nav-tabs mb-3">'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage">Storage</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=devices">Devices</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=items">Protected Items</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=billing">Billing</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr">NFR</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=terms">Terms</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=privacy">Privacy</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=whitelabel">White-Label</a></li>'
    . '<li class="nav-item"><a class="nav-link active" href="#">Notifications</a></li>'
    . '</ul>';

echo '<div class="container-fluid">';
echo $tabs;
echo $notice;

if ($op === 'edit' || $op === 'new') {
    $row = null;
    $targets = ['product'=>[], 'client_group'=>[], 'client'=>[]];
    if ($nid > 0) {
        $row = Capsule::table('eb_notifications')->where('id', $nid)->first();
        if (!$row) {
            echo '<div class="alert alert-warning">Notification not found.</div>';
            echo '</div>';
            return;
        }
        $tRows = Capsule::table('eb_notification_targets')->where('notification_id', $nid)->get();
        foreach ($tRows as $tr) {
            $targets[$tr->target_type][] = (int)$tr->target_id;
        }
    }

    $title    = $row->title ?? '';
    $body     = $row->body  ?? '';
    $audience = $row->audience_type ?? 'all';
    $status   = $row->status ?? 'draft';

    $products = Capsule::table('tblproducts')->select('id','name','gid')->orderBy('name')->get();
    $groups   = Capsule::table('tblclientgroups')->select('id','groupname')->orderBy('groupname')->get();

    $backUrl = 'addonmodules.php?module=eazybackup&action=powerpanel&view=notifications';
    ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong><?= $nid > 0 ? 'Edit Notification #' . (int)$nid : 'New Notification' ?></strong>
            <span class="pull-right">
                <span class="label label-<?= $status === 'published' ? 'success' : 'default' ?>"><?= $e(ucfirst((string)$status)) ?></span>
            </span>
        </div>
        <div class="panel-body">
            <form method="post" action="addonmodules.php?module=eazybackup&action=powerpanel&view=notifications">
                <?= function_exists('generate_token') ? generate_token('input') : '' ?>
                <input type="hidden" name="op" value="save"/>
                <input type="hidden" name="id" value="<?= (int)$nid ?>"/>

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" maxlength="191" required value="<?= $e($title) ?>"/>
                </div>

                <div class="form-group">
                    <label>Body (plain text)</label>
                    <textarea name="body" class="form-control" rows="8" required placeholder="What's new?"><?= $e($body) ?></textarea>
                    <p class="help-block">Plain text only. Line breaks are preserved. HTML is not rendered.</p>
                </div>

                <div class="form-group">
                    <label>Audience</label><br/>
                    <label class="radio-inline"><input type="radio" name="audience_type" value="all" <?= $audience === 'all' ? 'checked' : '' ?>/> All clients</label>
                    <label class="radio-inline"><input type="radio" name="audience_type" value="filtered" <?= $audience === 'filtered' ? 'checked' : '' ?>/> Filtered (specific products / groups / clients)</label>
                </div>

                <fieldset style="border:1px solid #eee;padding:10px;border-radius:4px;">
                    <legend style="width:auto;border:0;font-size:14px;padding:0 6px;">Filtered targeting</legend>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Products</label>
                                <select name="target_products[]" class="form-control" multiple size="8">
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= (int)$p->id ?>" <?= in_array((int)$p->id, $targets['product'], true) ? 'selected' : '' ?>>
                                            <?= $e((string)$p->name) ?> (#<?= (int)$p->id ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-block">Clients with at least one Active or Suspended service for any selected product.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Client Groups</label>
                                <select name="target_groups[]" class="form-control" multiple size="8">
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?= (int)$g->id ?>" <?= in_array((int)$g->id, $targets['client_group'], true) ? 'selected' : '' ?>>
                                            <?= $e((string)$g->groupname) ?> (#<?= (int)$g->id ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Specific Client IDs</label>
                                <input type="text" name="target_clients" class="form-control" value="<?= $e(implode(', ', $targets['client'])) ?>" placeholder="e.g. 12, 45, 78"/>
                                <p class="help-block">Comma-separated client IDs. Invalid IDs are ignored.</p>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <hr/>
                <button type="submit" name="save_draft" value="1" class="btn btn-default">Save Draft</button>
                <button type="submit" name="save_publish" value="1" class="btn btn-primary">Save &amp; Publish</button>
                <a href="<?= $e($backUrl) ?>" class="btn btn-link">Cancel</a>
                <?php if ($nid > 0): ?>
                    <span class="pull-right">
                        <?php if ($status === 'published'): ?>
                            <button type="submit" formaction="addonmodules.php?module=eazybackup&action=powerpanel&view=notifications" name="op" value="unpublish" class="btn btn-warning" formnovalidate>Unpublish</button>
                        <?php else: ?>
                            <button type="submit" name="op" value="publish" class="btn btn-success" formnovalidate>Publish</button>
                        <?php endif; ?>
                        <button type="submit" name="op" value="delete" class="btn btn-danger" formnovalidate onclick="return confirm('Delete this notification and all dismissal records?');">Delete</button>
                    </span>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php
    echo '</div>';
    return;
}

// LIST view (default)
$rows = Capsule::table('eb_notifications')->orderBy('id', 'desc')->get();
$counts = [];
if (count($rows)) {
    $ids = array_map(fn($r) => (int)$r->id, iterator_to_array($rows));
    $rawCounts = Capsule::table('eb_notification_targets')
        ->whereIn('notification_id', $ids)
        ->selectRaw('notification_id, COUNT(*) as c')
        ->groupBy('notification_id')->get();
    foreach ($rawCounts as $rc) { $counts[(int)$rc->notification_id] = (int)$rc->c; }

    $dismissCounts = Capsule::table('mod_eazybackup_dismissals')
        ->whereIn('announcement_key', array_map(fn($i) => 'notif:' . $i, $ids))
        ->selectRaw("announcement_key, COUNT(DISTINCT COALESCE(client_id, user_id)) as c")
        ->groupBy('announcement_key')->get();
    $dCounts = [];
    foreach ($dismissCounts as $dc) {
        $idPart = (int)substr((string)$dc->announcement_key, strlen('notif:'));
        $dCounts[$idPart] = (int)$dc->c;
    }
}
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <strong>Client Area Notifications</strong>
        <a href="addonmodules.php?module=eazybackup&action=powerpanel&view=notifications&op=new" class="btn btn-primary btn-sm pull-right">+ New Notification</a>
    </div>
    <div class="panel-body">
        <p class="text-muted">Notifications appear as a single dismissable modal on every client area page until each client dismisses each notification.</p>
        <div class="table-responsive">
            <table class="table table-striped table-condensed">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Audience</th>
                        <th>Dismissed</th>
                        <th>Created</th>
                        <th>Published</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($rows)): foreach ($rows as $r): $rid = (int)$r->id; ?>
                    <tr>
                        <td><?= $rid ?></td>
                        <td><a href="addonmodules.php?module=eazybackup&action=powerpanel&view=notifications&op=edit&id=<?= $rid ?>"><?= $e((string)$r->title) ?></a></td>
                        <td>
                            <?php if ($r->status === 'published'): ?>
                                <span class="label label-success">Published</span>
                            <?php else: ?>
                                <span class="label label-default">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r->audience_type === 'all'): ?>
                                <span class="label label-info">All clients</span>
                            <?php else: ?>
                                <span class="label label-warning">Filtered</span>
                                <small class="text-muted">(<?= (int)($counts[$rid] ?? 0) ?> targets)</small>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)($dCounts[$rid] ?? 0) ?></td>
                        <td><small><?= $e((string)($r->created_at ?? '')) ?></small></td>
                        <td><small><?= $e((string)($r->published_at ?? '')) ?></small></td>
                        <td>
                            <a class="btn btn-default btn-xs" href="addonmodules.php?module=eazybackup&action=powerpanel&view=notifications&op=edit&id=<?= $rid ?>">Edit</a>
                            <form method="post" style="display:inline" action="addonmodules.php?module=eazybackup&action=powerpanel&view=notifications">
                                <?= function_exists('generate_token') ? generate_token('input') : '' ?>
                                <input type="hidden" name="op" value="<?= $r->status === 'published' ? 'unpublish' : 'publish' ?>"/>
                                <input type="hidden" name="id" value="<?= $rid ?>"/>
                                <button class="btn btn-<?= $r->status === 'published' ? 'warning' : 'success' ?> btn-xs" type="submit"><?= $r->status === 'published' ? 'Unpublish' : 'Publish' ?></button>
                            </form>
                            <form method="post" style="display:inline" action="addonmodules.php?module=eazybackup&action=powerpanel&view=notifications" onsubmit="return confirm('Delete this notification and all dismissal records?');">
                                <?= function_exists('generate_token') ? generate_token('input') : '' ?>
                                <input type="hidden" name="op" value="delete"/>
                                <input type="hidden" name="id" value="<?= $rid ?>"/>
                                <button class="btn btn-danger btn-xs" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="8" class="text-center text-muted">No notifications yet. Click <em>New Notification</em> to create one.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
echo '</div>';
