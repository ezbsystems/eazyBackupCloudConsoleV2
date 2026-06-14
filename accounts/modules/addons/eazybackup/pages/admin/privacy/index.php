<?php

declare(strict_types=1);

use WHMCS\Database\Capsule;

if (!isset($_SESSION['adminid']) || (int)$_SESSION['adminid'] <= 0) {
    return '<div class="alert alert-danger">Not authorized.</div>';
}

$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$textarea = function ($s) {
    return str_ireplace('</textarea>', '&lt;/textarea&gt;', (string)$s);
};

$baseUrl = 'addonmodules.php?module=eazybackup&action=powerpanel&view=privacy';
$op = (string)($_REQUEST['op'] ?? 'list');
$vid = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$notice = '';

$tabs = '<ul class="nav nav-tabs mb-3">'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage">Storage</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=devices">Devices</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=items">Protected Items</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=billing">Billing</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr">NFR</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=terms">Terms</a></li>'
    . '<li class="nav-item"><a class="nav-link active" href="#">Privacy</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=whitelabel">White-Label</a></li>'
    . '<li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=notifications">Notifications</a></li>'
    . '</ul>';

$tokenOk = (function (): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return true;
    }
    return function_exists('check_token') && check_token('WHMCS.admin.default');
})();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!$tokenOk) {
        $notice = '<div class="alert alert-danger">Invalid CSRF token.</div>';
        $op = (string)($_POST['op'] ?? 'list');
    } else {
        $postOp = (string)($_POST['op'] ?? '');
        try {
            if ($postOp === 'create') {
                $version = trim((string)($_POST['version'] ?? ''));
                $title = trim((string)($_POST['title'] ?? 'Privacy Policy'));
                $summary = (string)($_POST['summary'] ?? '');
                $content = (string)($_POST['content_html'] ?? '');
                if ($version === '') {
                    throw new \RuntimeException('Version is required (e.g., 2026-01-13).');
                }
                $vid = (int)Capsule::table('eb_privacy_versions')->insertGetId([
                    'version' => $version,
                    'title' => $title !== '' ? $title : 'Privacy Policy',
                    'summary' => $summary !== '' ? $summary : null,
                    'content_html' => $content !== '' ? $content : null,
                    'is_active' => 0,
                    'require_acceptance' => 0,
                    'created_at' => Capsule::raw('NOW()'),
                    'created_by' => (int)$_SESSION['adminid'],
                ]);
                $notice = '<div class="alert alert-success">New Privacy Policy version created.</div>';
                $op = 'edit';
            } elseif ($postOp === 'save') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new \RuntimeException('Invalid version id.');
                }
                $existing = Capsule::table('eb_privacy_versions')->where('id', $id)->first();
                if (!$existing) {
                    throw new \RuntimeException('Version not found.');
                }

                $title = trim((string)($_POST['title'] ?? 'Privacy Policy'));
                $summary = (string)($_POST['summary'] ?? '');
                $content = (string)($_POST['content_html'] ?? '');
                $version = trim((string)($_POST['version'] ?? ''));

                if ($title === '') {
                    throw new \RuntimeException('Title is required.');
                }

                $data = [
                    'title' => $title,
                    'summary' => $summary !== '' ? $summary : null,
                    'content_html' => $content !== '' ? $content : null,
                ];

                if ((int)$existing->is_active === 0) {
                    if ($version === '') {
                        throw new \RuntimeException('Version is required (e.g., 2026-01-13).');
                    }
                    if ($version !== (string)$existing->version) {
                        $dup = Capsule::table('eb_privacy_versions')
                            ->where('version', $version)
                            ->where('id', '!=', $id)
                            ->exists();
                        if ($dup) {
                            throw new \RuntimeException('That version identifier is already in use.');
                        }
                        $data['version'] = $version;
                    }
                }

                Capsule::table('eb_privacy_versions')->where('id', $id)->update($data);
                $vid = $id;
                $notice = '<div class="alert alert-success">Privacy Policy version saved.</div>';
                $op = 'edit';
            } elseif ($postOp === 'publish') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new \RuntimeException('Invalid version id.');
                }
                Capsule::table('eb_privacy_versions')->update(['is_active' => 0]);
                Capsule::table('eb_privacy_versions')->where('id', $id)->update([
                    'is_active' => 1,
                    'published_at' => Capsule::raw('NOW()'),
                ]);
                $notice = '<div class="alert alert-success">Privacy Policy version published as active.</div>';
                $op = 'list';
            } elseif ($postOp === 'toggle_require') {
                $id = (int)($_POST['id'] ?? 0);
                $flag = (int)($_POST['require_acceptance'] ?? 0) ? 1 : 0;
                if ($id <= 0) {
                    throw new \RuntimeException('Invalid version id.');
                }
                Capsule::table('eb_privacy_versions')->where('id', $id)->update([
                    'require_acceptance' => $flag,
                ]);
                $notice = '<div class="alert alert-success">Require acceptance setting updated.</div>';
                $op = 'list';
            }
        } catch (\Throwable $ex) {
            $notice = '<div class="alert alert-danger">Error: ' . $e($ex->getMessage()) . '</div>';
            if ($postOp === 'save' || $postOp === 'create') {
                $op = 'edit';
            }
        }
    }
}

ob_start();
echo '<div class="container-fluid">';
echo $tabs;
echo $notice;

if ($op === 'edit' && $vid > 0) {
    $row = Capsule::table('eb_privacy_versions')->where('id', $vid)->first();
    if (!$row) {
        echo '<div class="alert alert-warning">Version not found.</div>';
        echo '<p><a class="btn btn-default" href="' . $e($baseUrl) . '">Back to list</a></p>';
        echo '</div>';
        return ob_get_clean();
    }

    $isActive = (int)$row->is_active === 1;
    ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Edit Privacy Policy — <?= $e((string)$row->version) ?></strong>
            <span class="pull-right">
                <span class="label label-<?= $isActive ? 'success' : 'default' ?>"><?= $isActive ? 'Published (Active)' : 'Draft' ?></span>
            </span>
        </div>
        <div class="panel-body">
            <p class="text-muted">
                <a href="<?= $e($baseUrl) ?>">&larr; Back to all versions</a>
                &nbsp;|&nbsp;
                <a href="<?= $e($baseUrl . '&op=view&id=' . (int)$row->id) ?>">Preview content</a>
            </p>
            <form method="post" action="<?= $e($baseUrl) ?>">
                <?= function_exists('generate_token') ? generate_token('input') : '' ?>
                <input type="hidden" name="op" value="save"/>
                <input type="hidden" name="id" value="<?= (int)$row->id ?>"/>

                <div class="form-group">
                    <label>Version (YYYY-MM-DD)</label>
                    <?php if ($isActive): ?>
                        <input type="text" class="form-control" value="<?= $e((string)$row->version) ?>" readonly/>
                        <p class="help-block">Version cannot be changed while published. Create a new version for a new effective date.</p>
                    <?php else: ?>
                        <input type="text" name="version" class="form-control" value="<?= $e((string)$row->version) ?>" required/>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" value="<?= $e((string)$row->title) ?>" required/>
                </div>
                <div class="form-group">
                    <label>Summary (optional)</label>
                    <textarea name="summary" class="form-control" rows="3"><?= $e((string)($row->summary ?? '')) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Content (HTML)</label>
                    <textarea name="content_html" class="form-control" rows="18"><?= $textarea((string)($row->content_html ?? '')) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a class="btn btn-default" href="<?= $e($baseUrl . '&op=view&id=' . (int)$row->id) ?>">Preview</a>
                <a class="btn btn-default" href="<?= $e($baseUrl) ?>">Cancel</a>
                <?php if (!$isActive): ?>
                    <button type="submit" formaction="<?= $e($baseUrl) ?>" name="op" value="publish" class="btn btn-success pull-right" formnovalidate>Publish</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php
    echo '</div>';
    return ob_get_clean();
}

if ($op === 'view' && $vid > 0) {
    $row = Capsule::table('eb_privacy_versions')->where('id', $vid)->first();
    if (!$row) {
        echo '<div class="alert alert-warning">Version not found.</div>';
        echo '<p><a class="btn btn-default" href="' . $e($baseUrl) . '">Back to list</a></p>';
        echo '</div>';
        return ob_get_clean();
    }

    $isActive = (int)$row->is_active === 1;
    ?>
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Preview: <?= $e((string)$row->title) ?> (<?= $e((string)$row->version) ?>)</strong>
            <span class="pull-right">
                <span class="label label-<?= $isActive ? 'success' : 'default' ?>"><?= $isActive ? 'Published (Active)' : 'Draft' ?></span>
            </span>
        </div>
        <div class="panel-body">
            <p>
                <a href="<?= $e($baseUrl) ?>">&larr; Back to all versions</a>
                &nbsp;|&nbsp;
                <a href="<?= $e($baseUrl . '&op=edit&id=' . (int)$row->id) ?>">Edit this version</a>
            </p>
            <?php if (!empty($row->summary)): ?>
                <div class="well well-sm"><?= nl2br($e((string)$row->summary)) ?></div>
            <?php endif; ?>
            <div class="panel panel-default">
                <div class="panel-body" style="max-height:600px;overflow:auto;">
                    <?= (string)($row->content_html ?? '<p class="text-muted">No content yet.</p>') ?>
                </div>
            </div>
            <p class="text-muted small">
                Created: <?= $e((string)($row->created_at ?? '')) ?>
                <?php if (!empty($row->published_at)): ?> | Published: <?= $e((string)$row->published_at) ?><?php endif; ?>
            </p>
        </div>
    </div>
    <?php
    echo '</div>';
    return ob_get_clean();
}

$versions = Capsule::table('eb_privacy_versions')->orderBy('created_at', 'desc')->get();
?>
<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Create New Privacy Policy Version</strong></div>
            <div class="panel-body">
                <form method="post" action="<?= $e($baseUrl) ?>">
                    <?= function_exists('generate_token') ? generate_token('input') : '' ?>
                    <input type="hidden" name="op" value="create"/>
                    <div class="form-group">
                        <label>Version (YYYY-MM-DD)</label>
                        <input type="text" name="version" class="form-control" value="<?= $e(date('Y-m-d')) ?>" placeholder="2026-01-13" required/>
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" class="form-control" value="Privacy Policy"/>
                    </div>
                    <div class="form-group">
                        <label>Summary (optional)</label>
                        <textarea name="summary" class="form-control" rows="3" placeholder="Short summary shown in the modal"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Content (HTML)</label>
                        <textarea name="content_html" class="form-control" rows="10" placeholder="<h2>Privacy</h2><p>…</p>"></textarea>
                        <p class="help-block">Full HTML will be shown on client “View Privacy” links.</p>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Version</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>All Versions</strong></div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Version</th>
                                <th>Title</th>
                                <th>Active</th>
                                <th>Require</th>
                                <th>Published</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($versions)): foreach ($versions as $v): ?>
                                <tr>
                                    <td><?= (int)$v->id ?></td>
                                    <td><a href="<?= $e($baseUrl . '&op=view&id=' . (int)$v->id) ?>"><?= $e((string)$v->version) ?></a></td>
                                    <td><?= $e((string)$v->title) ?></td>
                                    <td><?= ((int)$v->is_active ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>') ?></td>
                                    <td><?= ((int)$v->require_acceptance ? '<span class="label label-warning">On</span>' : '<span class="label label-default">Off</span>') ?></td>
                                    <td><?= $e((string)($v->published_at ?? '')) ?></td>
                                    <td style="white-space:nowrap">
                                        <a class="btn btn-default btn-xs" href="<?= $e($baseUrl . '&op=view&id=' . (int)$v->id) ?>">View</a>
                                        <a class="btn btn-primary btn-xs" href="<?= $e($baseUrl . '&op=edit&id=' . (int)$v->id) ?>">Edit</a>
                                        <form method="post" style="display:inline-block;margin-left:4px" action="<?= $e($baseUrl) ?>">
                                            <?= function_exists('generate_token') ? generate_token('input') : '' ?>
                                            <input type="hidden" name="op" value="publish"/>
                                            <input type="hidden" name="id" value="<?= (int)$v->id ?>"/>
                                            <button class="btn btn-default btn-xs" type="submit"<?= (int)$v->is_active ? ' disabled' : '' ?>>Publish</button>
                                        </form>
                                        <form method="post" style="display:inline-block" action="<?= $e($baseUrl) ?>">
                                            <?= function_exists('generate_token') ? generate_token('input') : '' ?>
                                            <input type="hidden" name="op" value="toggle_require"/>
                                            <input type="hidden" name="id" value="<?= (int)$v->id ?>"/>
                                            <input type="hidden" name="require_acceptance" value="<?= (int)$v->require_acceptance ? 0 : 1 ?>"/>
                                            <button class="btn btn-<?= ((int)$v->require_acceptance ? 'default' : 'warning') ?> btn-xs" type="submit"><?= ((int)$v->require_acceptance ? 'Disable' : 'Require') ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="7" class="text-center text-muted">No versions found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small">Click a version to preview. Use Edit to update content. Publishing sets a version as active (deactivates others). “Require” forces client-area acceptance.</p>
            </div>
        </div>
    </div>
</div>
<?php
echo '</div>';
return ob_get_clean();
