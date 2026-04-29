<?php

declare(strict_types=1);

use WHMCS\Database\Capsule;

if (!isset($_SESSION['adminid']) || (int)$_SESSION['adminid'] <= 0) {
    echo '<div class="alert alert-danger">Not authorized.</div>';
    return;
}

$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$adminId = (int)$_SESSION['adminid'];

$mode = ((string)($_REQUEST['action'] ?? '') === 'client_notifications_panel') ? 'iframe' : 'powerpanel';
$clientId = (int)($_REQUEST['clientid'] ?? 0);
if ($clientId <= 0) {
    echo '<div class="alert alert-warning">No client selected.</div>';
    return;
}
$client = Capsule::table('tblclients')->where('id', $clientId)->first(['id','firstname','lastname','companyname']);
if (!$client) {
    echo '<div class="alert alert-warning">Client not found.</div>';
    return;
}

$selfUrl = $mode === 'iframe'
    ? 'addonmodules.php?module=eazybackup&action=client_notifications_panel&clientid=' . $clientId
    : 'addonmodules.php?module=eazybackup&action=powerpanel&view=client_notifications&clientid=' . $clientId;

// Form submissions go to a dedicated endpoint outside addonmodules.php
// so they bypass the WHMCS framework admin-CSRF gate (which our custom
// iframe action cannot reliably populate). Auth + CSRF are enforced
// inside the endpoint via $_SESSION['adminid'] and a per-session token.
$webRoot = rtrim((string)\WHMCS\Config\Setting::getValue('SystemURL'), '/');
$postUrl = $webRoot . '/modules/addons/eazybackup/endpoints/admin_client_messages.php';

if (empty($_SESSION['eb_admin_inbox_csrf'])) {
    try { $_SESSION['eb_admin_inbox_csrf'] = bin2hex(random_bytes(16)); }
    catch (\Throwable $__) { $_SESSION['eb_admin_inbox_csrf'] = sha1(uniqid('', true)); }
}
$ebCsrf = (string)$_SESSION['eb_admin_inbox_csrf'];

$op = (string)($_REQUEST['op'] ?? 'list');
$mid = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$notice = '';

if (!empty($_SESSION['eb_admin_inbox_flash']) && is_array($_SESSION['eb_admin_inbox_flash'])) {
    $f = $_SESSION['eb_admin_inbox_flash'];
    unset($_SESSION['eb_admin_inbox_flash']);
    $type = ($f['type'] ?? 'success') === 'danger' ? 'danger' : 'success';
    $notice = '<div class="alert alert-' . $type . '">' . $e((string)($f['msg'] ?? '')) . '</div>';
}

ob_start();

if ($mode === 'iframe') {
    echo '<!doctype html><html><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Client Notifications</title>'
       . '<link rel="stylesheet" href="../assets/css/bootstrap.min.css">'
       . '<link rel="stylesheet" href="../assets/css/font-awesome.min.css">'
       . '<style>body{padding:14px;background:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:13px;}'
       . '.help-block{font-size:12px;color:#777;}fieldset{margin:0;padding:0;border:0;}</style>'
       . '</head><body>';
}

echo $notice;

if ($op === 'edit' || $op === 'new') {
    $row = null;
    if ($mid > 0) {
        $row = Capsule::table('eb_client_messages')->where('id', $mid)->where('client_id', $clientId)->first();
        if (!$row) {
            echo '<div class="alert alert-warning">Message not found.</div>';
            echo '<a href="' . $e($selfUrl) . '" class="btn btn-default">Back</a>';
            if ($mode === 'iframe') echo '</body></html>';
            return;
        }
    }
    $title = (string)($row->title ?? '');
    $body  = (string)($row->body  ?? '');
    $expiresAtVal = '';
    if (!empty($row->expires_at)) {
        $ts = strtotime((string)$row->expires_at);
        if ($ts) $expiresAtVal = date('Y-m-d\TH:i', $ts);
    }
    ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong><?= $mid > 0 ? 'Edit Message #' . (int)$mid : 'New Message' ?></strong>
            <span class="pull-right text-muted">Client #<?= (int)$clientId ?> &mdash; <?= $e(trim(($client->firstname ?? '') . ' ' . ($client->lastname ?? '') . ($client->companyname ? ' (' . $client->companyname . ')' : ''))) ?></span>
        </div>
        <div class="panel-body">
            <form method="post" action="<?= $e($postUrl) ?>">
                <input type="hidden" name="eb_csrf" value="<?= $e($ebCsrf) ?>"/>
                <input type="hidden" name="op" value="save"/>
                <input type="hidden" name="id" value="<?= (int)$mid ?>"/>
                <input type="hidden" name="clientid" value="<?= (int)$clientId ?>"/>

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" maxlength="191" required value="<?= $e($title) ?>"/>
                </div>
                <div class="form-group">
                    <label>Body (plain text)</label>
                    <textarea name="body" class="form-control" rows="8" required placeholder="Personal message to this client&hellip;"><?= $e($body) ?></textarea>
                    <p class="help-block">Plain text only. Line breaks are preserved. HTML is not rendered.</p>
                </div>
                <div class="form-group">
                    <label>Expires at</label>
                    <input type="datetime-local" name="expires_at" class="form-control" value="<?= $e($expiresAtVal) ?>" style="max-width:280px;"/>
                    <p class="help-block">Leave blank for no expiry. Expired messages are hidden from the modal but remain in the customer's Inbox as muted entries.</p>
                </div>

                <hr/>
                <button type="submit" class="btn btn-primary">Save Message</button>
                <a href="<?= $e($selfUrl) ?>" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
    <?php
} else {
    // LIST
    $rows = Capsule::table('eb_client_messages')->where('client_id', $clientId)->orderBy('id','desc')->get();
    $now = date('Y-m-d H:i:s');
    ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Client Notifications</strong>
            <span class="text-muted">&nbsp;Personal messages for this client. They appear in the client's "What's New" modal and are archived to their Inbox.</span>
            <a href="<?= $e($selfUrl) ?>&op=new" class="btn btn-primary btn-sm pull-right">+ New Message</a>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped table-condensed">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Viewed</th>
                            <th>Expires</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($rows)): foreach ($rows as $r):
                        $rid = (int)$r->id;
                        $isDeleted = !empty($r->deleted_at);
                        $isExpired = !empty($r->expires_at) && (string)$r->expires_at <= $now;
                        $isRead    = !empty($r->viewed_at);
                        if ($isDeleted)      { $label='label-default'; $status='Deleted'; }
                        elseif ($isExpired)  { $label='label-warning'; $status='Expired'; }
                        elseif ($isRead)     { $label='label-info';    $status='Read'; }
                        else                 { $label='label-success'; $status='Unread'; }
                    ?>
                        <tr>
                            <td><?= $rid ?></td>
                            <td><a href="<?= $e($selfUrl) ?>&op=edit&id=<?= $rid ?>"><?= $e((string)$r->title) ?></a></td>
                            <td><span class="label <?= $label ?>"><?= $e($status) ?></span></td>
                            <td><small><?= $e((string)($r->created_at ?? '')) ?></small></td>
                            <td><small><?= $e((string)($r->viewed_at ?? '')) ?></small></td>
                            <td><small><?= $e((string)($r->expires_at ?? '')) ?></small></td>
                            <td>
                                <a class="btn btn-default btn-xs" href="<?= $e($selfUrl) ?>&op=edit&id=<?= $rid ?>">Edit</a>
                                <?php if ($isDeleted): ?>
                                    <form method="post" style="display:inline" action="<?= $e($postUrl) ?>">
                                        <input type="hidden" name="eb_csrf" value="<?= $e($ebCsrf) ?>"/>
                                        <input type="hidden" name="op" value="restore"/>
                                        <input type="hidden" name="id" value="<?= $rid ?>"/>
                                        <input type="hidden" name="clientid" value="<?= (int)$clientId ?>"/>
                                        <button class="btn btn-success btn-xs" type="submit">Restore</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline" action="<?= $e($postUrl) ?>" onsubmit="return confirm('Archive this message? It will be hidden from the customer.');">
                                        <input type="hidden" name="eb_csrf" value="<?= $e($ebCsrf) ?>"/>
                                        <input type="hidden" name="op" value="soft_delete"/>
                                        <input type="hidden" name="id" value="<?= $rid ?>"/>
                                        <input type="hidden" name="clientid" value="<?= (int)$clientId ?>"/>
                                        <button class="btn btn-warning btn-xs" type="submit">Archive</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline" action="<?= $e($postUrl) ?>" onsubmit="return confirm('Permanently delete this message? This cannot be undone.');">
                                    <input type="hidden" name="eb_csrf" value="<?= $e($ebCsrf) ?>"/>
                                    <input type="hidden" name="op" value="hard_delete"/>
                                    <input type="hidden" name="id" value="<?= $rid ?>"/>
                                    <input type="hidden" name="clientid" value="<?= (int)$clientId ?>"/>
                                    <button class="btn btn-danger btn-xs" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" class="text-center text-muted">No messages yet. Click <em>New Message</em> to send one.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

if ($mode === 'iframe') {
    echo '</body></html>';
}

$html = ob_get_clean();
echo $html;
