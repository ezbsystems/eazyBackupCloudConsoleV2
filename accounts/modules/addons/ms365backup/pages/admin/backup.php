<?php
declare(strict_types=1);

use Ms365Backup\BackupRunRepository;

$runs = BackupRunRepository::listRecent(25);
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$apiBase = 'addonmodules.php?module=ms365backup&action=api';
$token = generate_token('plain');
?>
<style>
.ms365-resource-row-selected td { background-color: #f0f7ff; }
.ms365-resource-child td:first-child { padding-left: 28px; }
.ms365-type-badge { display: inline-block; font-size: 11px; padding: 2px 6px; border-radius: 3px; background: #e8e8e8; color: #333; margin-right: 4px; }
.ms365-cap-chip { display: inline-block; font-size: 10px; padding: 1px 5px; border-radius: 2px; background: #f5f5f5; border: 1px solid #ddd; color: #555; margin: 1px 2px 1px 0; }
.ms365-section-toggle { cursor: pointer; user-select: none; font-weight: 600; margin: 12px 0 6px; }
.ms365-section-toggle .caret { display: inline-block; width: 14px; }
.ms365-section-body { margin-bottom: 8px; }
</style>
<div class="row">
    <div class="col-md-5">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Start backup</strong></div>
            <div class="panel-body">
                <p class="text-muted">Select resources below, choose scope, and click <strong>Start backup</strong>. Jobs are queued for the <strong>Kopia Go worker fleet</strong> (no local PHP backup worker). User/mailbox, OneDrive, SharePoint site, and Teams backups run when the matching scope and resources are selected. Team + SharePoint files may queue <strong>two</strong> runs (site + team).</p>
                <div class="alert alert-info" style="padding:8px 12px;margin-bottom:10px">
                    <small>Teams messages require <code>ChannelMessage.Read.All</code> in Azure (admin consent). Contacts/Tasks need <code>Contacts.Read</code> and <code>Tasks.Read.All</code>. See <code>Docs/AZURE_SETUP.md</code>.</small>
                </div>
                <div class="form-group">
                    <label>Selected resources</label>
                    <div id="ms365-selected-resources-summary" class="well well-sm" style="margin-bottom:0;min-height:42px">
                        <span class="text-muted">No resources selected.</span>
                    </div>
                </div>
                <div id="ms365-dedup-warnings" style="margin-bottom:10px"></div>
                <div id="ms365-queue-preview" class="well well-sm" style="display:none;margin-bottom:10px"></div>
                <div class="form-group" id="ms365-scope-panel">
                    <label>Default backup scope</label>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-mail" value="1" checked> Mail</label></div>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-calendar" value="1" checked> Calendar</label></div>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-contacts" value="1"> Contacts</label></div>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-tasks" value="1"> Tasks <small class="text-muted">(Microsoft To Do)</small></label></div>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-onedrive" value="1"> OneDrive <small class="text-muted">(files + metadata)</small></label></div>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-files" value="1"> SharePoint files <small class="text-muted">(all document libraries on site)</small></label></div>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-lists" value="1"> SharePoint lists</label></div>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-teams-metadata" value="1"> Teams metadata <small class="text-muted">(members, channels, tabs)</small></label></div>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-teams-messages" value="1"> Teams channel messages <small class="text-muted">(full history + replies)</small></label></div>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-planner" value="1"> Microsoft Planner</label></div>
                    <div class="checkbox"><label><input type="checkbox" id="ms365-backup-include-onenote" value="1"> OneNote</label></div>
                </div>
                <p id="ms365-scope-hint" class="text-muted small" style="display:none">Select resources and enable at least one matching scope (users, OneDrive, SharePoint site, or Team/channel).</p>
                <button type="button" class="btn btn-primary" id="ms365-start-backup">Start backup</button>
                <button type="button" class="btn btn-default btn-sm" id="ms365-clear-resource-selection" style="display:none">Clear selection</button>
                <div id="ms365-batch-backup-result" style="margin-top:10px"></div>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Tenant resource inventory</strong></div>
            <div class="panel-body">
                <div class="form-inline" style="margin-bottom:10px">
                    <button type="button" class="btn btn-primary btn-sm" id="ms365-refresh-inventory">Refresh resource inventory from Graph</button>
                    <button type="button" class="btn btn-default btn-sm" id="ms365-load-inventory">Load cached inventory</button>
                    <button type="button" class="btn btn-default btn-sm" id="ms365-check-inventory-access">Check access</button>
                </div>
                <input type="text" id="ms365-resource-filter" class="form-control" placeholder="Search/filter resources…" style="margin-bottom:8px">
                <p id="ms365-inventory-status" class="text-muted small"></p>
                <div id="ms365-resource-picker" style="max-height:480px;overflow:auto;border:1px solid #ddd;padding:8px;background:#fafafa">
                    <p class="text-muted">Load or refresh the resource inventory to begin.</p>
                </div>
            </div>
        </div>
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Recent runs</strong></div>
            <div class="panel-body">
                <table class="table table-condensed">
                    <thead>
                        <tr>
                            <th>Resource</th>
                            <th>Type</th>
                            <th>Scope</th>
                            <th>Status</th>
                            <th>Phase</th>
                            <th>%</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($runs === []): ?>
                            <tr><td colspan="7" class="text-muted">No backup runs yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($runs as $run): ?>
                                <?php
                                $scopeLabel = '';
                                $scopeRaw = $run['scope_json'] ?? '';
                                if (is_string($scopeRaw) && $scopeRaw !== '') {
                                    $scopeData = json_decode($scopeRaw, true);
                                    if (is_array($scopeData)) {
                                        $parts = [];
                                        foreach ($scopeData as $k => $v) {
                                            if ($v) {
                                                $parts[] = ucfirst((string) $k);
                                            }
                                        }
                                        $scopeLabel = implode(', ', $parts);
                                    }
                                }
                                if ($scopeLabel === '') {
                                    $sp = [];
                                    if (!empty($run['backup_mail'])) {
                                        $sp[] = 'Mail';
                                    }
                                    if (!empty($run['backup_calendar'])) {
                                        $sp[] = 'Calendar';
                                    }
                                    $scopeLabel = $sp !== [] ? implode(', ', $sp) : '—';
                                }
                                $resType = (string) ($run['resource_type'] ?? 'user');
                                $resName = (string) ($run['user_display_name'] ?: $run['user_upn'] ?: '');
                                if ($resName === '' && !empty($run['resource_id'])) {
                                    $resName = (string) $run['resource_id'];
                                }
                                ?>
                                <tr>
                                    <td><?= $e($resName) ?></td>
                                    <td><span class="ms365-type-badge"><?= $e($resType !== '' ? $resType : 'user') ?></span></td>
                                    <td><small><?= $e($scopeLabel) ?></small></td>
                                    <td><span class="label label-<?= $run['status'] === 'success' ? 'success' : ($run['status'] === 'error' ? 'danger' : ($run['status'] === 'skipped' ? 'warning' : ($run['status'] === 'cancelled' ? 'warning' : 'info'))) ?>"><?= $e($run['status']) ?></span></td>
                                    <td><?= $e($run['phase']) ?></td>
                                    <td><?= $e((string) $run['percent']) ?></td>
                                    <td>
                                        <a href="addonmodules.php?module=ms365backup&action=run&run_id=<?= $e($run['id']) ?>">View</a>
                                        <?php if (in_array($run['status'] ?? '', ['queued', 'running'], true)): ?>
                                            <button type="button" class="btn btn-danger btn-xs ms365-cancel-run-inline" data-run-id="<?= $e($run['id']) ?>">Abort</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
window.MS365_API = <?= json_encode($apiBase) ?>;
window.MS365_TOKEN = <?= json_encode($token) ?>;
</script>
<script src="<?= $e(ms365backup_asset_url('assets/js/ms365-admin.js')) ?>"></script>
<script src="<?= $e(ms365backup_asset_url('assets/js/ms365-resource-picker.js')) ?>"></script>
