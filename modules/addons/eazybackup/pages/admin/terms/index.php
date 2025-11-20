<?php

declare(strict_types=1);

use WHMCS\Database\Capsule;

// Basic admin guard
if (!isset($_SESSION['adminid']) || (int)$_SESSION['adminid'] <= 0) {
    return '<div class="alert alert-danger">Not authorized.</div>';
}

$e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$notice = '';

// Handle POST operations
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && function_exists('check_token') && check_token('WHMCS.admin.default')) {
    $op = (string)($_POST['op'] ?? '');
    try {
        if ($op === 'create') {
            $version = trim((string)($_POST['version'] ?? ''));
            $title = trim((string)($_POST['title'] ?? 'Terms of Service'));
            $summary = (string)($_POST['summary'] ?? '');
            $content = (string)($_POST['content_html'] ?? '');
            if ($version === '') {
                throw new \RuntimeException('Version is required (e.g., 2025-03-01).');
            }
            Capsule::table('eb_tos_versions')->insert([
                'version' => $version,
                'title' => $title !== '' ? $title : 'Terms of Service',
                'summary' => $summary !== '' ? $summary : null,
                'content_html' => $content !== '' ? $content : null,
                'is_active' => 0,
                'require_acceptance' => 0,
                'created_at' => Capsule::raw('NOW()'),
                'created_by' => (int)$_SESSION['adminid'],
            ]);
            $notice = '<div class="alert alert-success">New TOS version created.</div>';
        } elseif ($op === 'publish') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new \RuntimeException('Invalid version id.');
            }
            Capsule::table('eb_tos_versions')->update(['is_active' => 0]);
            Capsule::table('eb_tos_versions')->where('id', $id)->update([
                'is_active' => 1,
                'published_at' => Capsule::raw('NOW()'),
            ]);
            $notice = '<div class="alert alert-success">TOS version published as active.</div>';
        } elseif ($op === 'toggle_require') {
            $id = (int)($_POST['id'] ?? 0);
            $flag = (int)($_POST['require_acceptance'] ?? 0) ? 1 : 0;
            if ($id <= 0) {
                throw new \RuntimeException('Invalid version id.');
            }
            Capsule::table('eb_tos_versions')->where('id', $id)->update([
                'require_acceptance' => $flag,
            ]);
            $notice = '<div class="alert alert-success">Require acceptance setting updated.</div>';
        }
    } catch (\Throwable $ex) {
        $notice = '<div class="alert alert-danger">Error: ' . $e($ex->getMessage()) . '</div>';
    }
}

// Fetch versions (latest first)
$versions = Capsule::table('eb_tos_versions')->orderBy('created_at', 'desc')->get();

ob_start();
?>
<div class="container-fluid">
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage">Storage</a></li>
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=devices">Devices</a></li>
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=items">Protected Items</a></li>
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=billing">Billing</a></li>
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr">NFR</a></li>
        <li class="nav-item"><a class="nav-link active" href="#">Terms</a></li>
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=whitelabel">White-Label</a></li>
    </ul>

    <?= $notice ?>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Create New TOS Version</strong></div>
                <div class="panel-body">
                    <form method="post">
                        <?= function_exists('generate_token') ? generate_token('input') : '' ?>
                        <input type="hidden" name="op" value="create"/>
                        <div class="form-group">
                            <label>Version (YYYY-MM-DD)</label>
                            <input type="text" name="version" class="form-control" value="<?= $e(date('Y-m-d')) ?>" placeholder="2025-03-01" required/>
                        </div>
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control" value="Terms of Service"/>
                        </div>
                        <div class="form-group">
                            <label>Summary (optional)</label>
                            <textarea name="summary" class="form-control" rows="3" placeholder="Short summary shown in the modal"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Content (HTML)</label>
                            <textarea name="content_html" class="form-control" rows="10" placeholder="<h2>Terms</h2><p>…</p>"></textarea>
                            <p class="help-block">Full HTML will be shown on “View TOS” links.</p>
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
                                        <td><?= $e((string)$v->version) ?></td>
                                        <td><?= ((int)$v->is_active ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>') ?></td>
                                        <td><?= ((int)$v->require_acceptance ? '<span class="label label-warning">On</span>' : '<span class="label label-default">Off</span>') ?></td>
                                        <td><?= $e((string)($v->published_at ?? '')) ?></td>
                                        <td>
                                            <form method="post" style="display:inline-block;margin-right:6px">
                                                <?= function_exists('generate_token') ? generate_token('input') : '' ?>
                                                <input type="hidden" name="op" value="publish"/>
                                                <input type="hidden" name="id" value="<?= (int)$v->id ?>"/>
                                                <button class="btn btn-default btn-xs" type="submit">Publish</button>
                                            </form>
                                            <form method="post" style="display:inline-block">
                                                <?= function_exists('generate_token') ? generate_token('input') : '' ?>
                                                <input type="hidden" name="op" value="toggle_require"/>
                                                <input type="hidden" name="id" value="<?= (int)$v->id ?>"/>
                                                <input type="hidden" name="require_acceptance" value="<?= (int)$v->require_acceptance ? 0 : 1 ?>"/>
                                                <button class="btn btn-<?= ((int)$v->require_acceptance ? 'default' : 'warning') ?> btn-xs" type="submit"><?= ((int)$v->require_acceptance ? 'Disable' : 'Require') ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="6" class="text-center text-muted">No versions found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small">Publishing sets a version as active (deactivates others). “Require” forces client-area acceptance.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
return ob_get_clean();
// Fetch versions
$versions = Capsule::table('eb_tos_versions')->orderBy('created_at', 'desc')->get();

ob_start();
?>
<div class="container-fluid">
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=storage">Storage</a></li>
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=devices">Devices</a></li>
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=items">Protected Items</a></li>
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=billing">Billing</a></li>
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=nfr">NFR</a></li>
        <li class="nav-item"><a class="nav-link" href="addonmodules.php?module=eazybackup&action=powerpanel&view=whitelabel">White-Label</a></li>
        <li class="nav-item"><a class="nav-link active" href="#">Terms</a></li>
    </ul>

    <?= $notice ?>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Create New TOS Version</strong></div>
                <div class="panel-body">
                    <form method="post">
                        <?= generate_token('input') ?>
                        <input type="hidden" name="op" value="create"/>
                        <div class="form-group">
                            <label>Version (YYYY-MM-DD)</label>
                            <input type="text" name="version" class="form-control" value="<?= $e(date('Y-m-d')) ?>" placeholder="2025-03-01" required/>
                        </div>
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control" value="Terms of Service"/>
                        </div>
                        <div class="form-group">
                            <label>Summary (optional)</label>
                            <textarea name="summary" class="form-control" rows="3" placeholder="Short summary shown in the modal"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Content (HTML)</label>
                            <textarea name="content_html" class="form-control" rows="10" placeholder="<h2>Terms</h2><p>…</p>"></textarea>
                            <p class="help-block">Full HTML will be shown on “View TOS” links.</p>
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
                                        <td><?= $e((string)$v->version) ?></td>
                                        <td><?= ((int)$v->is_active ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>') ?></td>
                                        <td><?= ((int)$v->require_acceptance ? '<span class="label label-warning">On</span>' : '<span class="label label-default">Off</span>') ?></td>
                                        <td><?= $e((string)($v->published_at ?? '')) ?></td>
                                        <td>
                                            <form method="post" style="display:inline-block;margin-right:6px">
                                                <?= generate_token('input') ?>
                                                <input type="hidden" name="op" value="publish"/>
                                                <input type="hidden" name="id" value="<?= (int)$v->id ?>"/>
                                                <button class="btn btn-default btn-xs" type="submit">Publish</button>
                                            </form>
                                            <form method="post" style="display:inline-block">
                                                <?= generate_token('input') ?>
                                                <input type="hidden" name="op" value="toggle_require"/>
                                                <input type="hidden" name="id" value="<?= (int)$v->id ?>"/>
                                                <input type="hidden" name="require_acceptance" value="<?= (int)$v->require_acceptance ? 0 : 1 ?>"/>
                                                <button class="btn btn-<?= ((int)$v->require_acceptance ? 'default' : 'warning') ?> btn-xs" type="submit"><?= ((int)$v->require_acceptance ? 'Disable' : 'Require') ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="6" class="text-center text-muted">No versions found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small">Publishing sets a version as active (deactivates others). “Require” forces client-area acceptance.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
return ob_get_clean();


