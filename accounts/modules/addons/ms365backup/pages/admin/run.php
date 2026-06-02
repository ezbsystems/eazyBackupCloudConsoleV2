<?php
declare(strict_types=1);

use Ms365Backup\BackupRunRepository;

$runId = (string) ($_GET['run_id'] ?? '');
$run = $runId !== '' ? BackupRunRepository::get($runId) : null;
$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$apiBase = 'addonmodules.php?module=ms365backup&action=api';
$token = generate_token('plain');

if (!$run) {
    echo '<div class="alert alert-danger">Run not found.</div>';
    return;
}
$canCancel = in_array($run['status'] ?? '', ['queued', 'running'], true);

$manifest = null;
$manifestPath = !empty($run['backup_path']) ? $run['backup_path'] . '/manifest.json' : '';
if ($manifestPath !== '' && is_file($manifestPath)) {
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
}
$calendarResults = is_array($manifest['calendar']['calendar_results'] ?? null)
    ? $manifest['calendar']['calendar_results']
    : [];
$calendarIncomplete = is_array($manifest['calendar']['calendars_incomplete'] ?? null)
    ? $manifest['calendar']['calendars_incomplete']
    : [];
$calendarVerify = is_array($manifest['calendar']['verify'] ?? null) ? $manifest['calendar']['verify'] : null;
$verifyAllOk = $calendarVerify === null ? null : (bool) ($calendarVerify['ok'] ?? false);
?>
<style>.ms365-type-badge{display:inline-block;font-size:11px;padding:2px 6px;border-radius:3px;background:#e8e8e8;color:#333;margin-left:6px}</style>
<?php
$logicalSources = [];
$logicalRaw = $run['logical_sources_json'] ?? '[]';
if (is_string($logicalRaw) && $logicalRaw !== '') {
    $decoded = json_decode($logicalRaw, true);
    $logicalSources = is_array($decoded) ? $decoded : [];
}
$scopeParts = [];
$scopeRaw = $run['scope_json'] ?? '';
if (is_string($scopeRaw) && $scopeRaw !== '') {
    $scopeData = json_decode($scopeRaw, true);
    if (is_array($scopeData)) {
        foreach ($scopeData as $k => $v) {
            if ($v) {
                $scopeParts[] = ucfirst((string) $k);
            }
        }
    }
}
if ($scopeParts === []) {
    if (!empty($run['backup_mail'])) {
        $scopeParts[] = 'Mail';
    }
    if (!empty($run['backup_calendar'])) {
        $scopeParts[] = 'Calendar';
    }
}
$resType = (string) ($run['resource_type'] ?? 'user');
$physicalKey = (string) ($run['physical_key'] ?? '');
$resourceId = (string) ($run['resource_id'] ?? '');
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <strong>Backup run</strong> <?= $e($runId) ?>
        — <?= $e($run['user_display_name'] ?: $run['user_upn'] ?: $resourceId) ?>
        <span class="ms365-type-badge"><?= $e($resType !== '' ? $resType : 'user') ?></span>
    </div>
    <div class="panel-body">
        <p>
            <?php if ($resourceId !== ''): ?>
                <strong>Resource:</strong> <code><?= $e($resourceId) ?></code>
                <?php if ($physicalKey !== ''): ?>
                    &nbsp; <strong>Physical key:</strong> <code><?= $e($physicalKey) ?></code>
                <?php endif; ?>
                <br>
            <?php endif; ?>
            <strong>Scope:</strong> <?= $e($scopeParts !== [] ? implode(' + ', $scopeParts) : '—') ?>
            &nbsp; <strong>Status:</strong> <span id="ms365-run-status"><?= $e($run['status']) ?></span>
            &nbsp; <strong>Phase:</strong> <span id="ms365-run-phase"><?= $e($run['phase']) ?></span>
            &nbsp; <strong>Progress:</strong> <span id="ms365-run-percent"><?= $e((string) $run['percent']) ?></span>%
            <?php if ($canCancel): ?>
                <button type="button" class="btn btn-danger btn-sm pull-right" id="ms365-cancel-run" style="margin-top:-4px;margin-left:6px">Abort backup</button>
            <?php endif; ?>
            <?php if (in_array($run['status'] ?? '', ['queued', 'running'], true)): ?>
                <button type="button" class="btn btn-warning btn-sm pull-right" id="ms365-restart-worker" style="margin-top:-4px">Restart worker</button>
            <?php endif; ?>
        </p>
        <div id="ms365-cancel-notice"></div>
        <div class="progress" style="margin-bottom:15px">
            <div id="ms365-run-progress-bar" class="progress-bar progress-bar-info" role="progressbar" style="width:<?= min(100, (float) $run['percent']) ?>%"></div>
        </div>
        <?php if (($run['status'] ?? '') === 'skipped' && !empty($run['error_message'])): ?>
            <div class="alert alert-warning"><strong>Backup skipped:</strong> <?= $e($run['error_message']) ?></div>
        <?php elseif (!empty($run['error_message']) && ($run['status'] ?? '') === 'error'): ?>
            <div class="alert alert-danger"><?= $e($run['error_message']) ?></div>
        <?php elseif (!empty($run['error_message']) && ($run['status'] ?? '') === 'success'): ?>
            <div class="alert alert-info"><?= $e($run['error_message']) ?></div>
        <?php endif; ?>
        <?php if ($calendarVerify !== null && ($run['status'] ?? '') === 'success'): ?>
            <?php if ($verifyAllOk): ?>
                <div class="alert alert-success">
                    <strong>Calendar verify:</strong> Graph <code>$count</code> matches on-disk inventory for all calendars (seriesMaster + singleInstance per year).
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <strong>Calendar verify:</strong> One or more calendars have count mismatches vs Microsoft Graph.
                    Backup inventory completed; review the verify column below and
                    <code>calendar_verify/*.json</code> under the run path for details.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($logicalSources !== []): ?>
            <h4>Logical sources</h4>
            <table class="table table-condensed table-bordered">
                <thead><tr><th>Name</th><th>Type</th><th>ID</th></tr></thead>
                <tbody>
                    <?php foreach ($logicalSources as $src): ?>
                        <?php if (!is_array($src)) { continue; } ?>
                        <tr>
                            <td><?= $e($src['display_name'] ?? '') ?></td>
                            <td><?= $e($src['resource_type'] ?? '') ?></td>
                            <td><code style="font-size:11px"><?= $e($src['id'] ?? '') ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php if (!empty($run['backup_path'])): ?>
            <p class="text-muted"><strong>Path:</strong> <code><?= $e($run['backup_path']) ?></code></p>
        <?php endif; ?>
        <?php
        $contactsStats = is_array($manifest['contacts'] ?? null) ? $manifest['contacts'] : null;
        $tasksStats = is_array($manifest['tasks'] ?? null) ? $manifest['tasks'] : null;
        $mailStats = is_array($manifest['mail'] ?? null) ? $manifest['mail'] : null;
        $onedriveStats = is_array($manifest['onedrive'] ?? null) ? $manifest['onedrive'] : null;
        $sharepointStats = is_array($manifest['sharepoint'] ?? null) ? $manifest['sharepoint'] : null;
        $spFilesStats = is_array($sharepointStats['files'] ?? null) ? $sharepointStats['files'] : null;
        $spListsStats = is_array($sharepointStats['lists'] ?? null) ? $sharepointStats['lists'] : null;
        $teamsStats = is_array($manifest['teams'] ?? null) ? $manifest['teams'] : null;
        $teamsMetaStats = is_array($teamsStats['metadata'] ?? null) ? $teamsStats['metadata'] : null;
        $teamsMsgStats = is_array($teamsStats['messages'] ?? null) ? $teamsStats['messages'] : null;
        ?>
        <?php if ($mailStats !== null && empty($mailStats['skipped'])): ?>
            <h4>Mail backup</h4>
            <p class="small text-muted">
                Folders: <?= $e((string) ($mailStats['folders'] ?? '')) ?>
                · Messages: <?= $e((string) ($mailStats['messages'] ?? '')) ?>
                <?php if (isset($mailStats['folders_delta'])): ?>
                    · Delta folders: <?= $e((string) $mailStats['folders_delta']) ?>
                    · Full/initial folders: <?= $e((string) ($mailStats['folders_full'] ?? 0)) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if ($contactsStats !== null && empty($contactsStats['skipped'])): ?>
            <h4>Contacts backup</h4>
            <p class="small text-muted">
                Folders: <?= $e((string) ($contactsStats['folders'] ?? '')) ?>
                · Contacts: <?= $e((string) ($contactsStats['contacts'] ?? '')) ?>
                (created <?= $e((string) ($contactsStats['created'] ?? 0)) ?>,
                updated <?= $e((string) ($contactsStats['updated'] ?? 0)) ?>,
                removed <?= $e((string) ($contactsStats['removed'] ?? 0)) ?>)
            </p>
        <?php endif; ?>
        <?php if ($tasksStats !== null && empty($tasksStats['skipped'])): ?>
            <h4>Tasks (To Do) backup</h4>
            <p class="small text-muted">
                Lists: <?= $e((string) ($tasksStats['lists'] ?? '')) ?>
                · Tasks: <?= $e((string) ($tasksStats['tasks'] ?? '')) ?>
                (created <?= $e((string) ($tasksStats['created'] ?? 0)) ?>,
                updated <?= $e((string) ($tasksStats['updated'] ?? 0)) ?>,
                removed <?= $e((string) ($tasksStats['removed'] ?? 0)) ?>)
            </p>
        <?php endif; ?>
        <?php if ($onedriveStats !== null && empty($onedriveStats['skipped'])): ?>
            <h4>OneDrive backup</h4>
            <p class="small text-muted">
                Items seen: <?= $e((string) ($onedriveStats['items_seen'] ?? '')) ?>
                · Files downloaded: <?= $e((string) ($onedriveStats['files_downloaded'] ?? '')) ?>
                · Skipped: <?= $e((string) ($onedriveStats['files_skipped'] ?? '')) ?>
                · Bytes: <?= $e((string) ($onedriveStats['bytes_downloaded'] ?? 0)) ?>
                <?php if (!empty($onedriveStats['resynced'])): ?>
                    · <span class="text-warning">Resynced</span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if ($spFilesStats !== null && empty($spFilesStats['skipped'])): ?>
            <h4>SharePoint files backup</h4>
            <p class="small text-muted">
                Document libraries: <?= $e((string) ($spFilesStats['drives'] ?? '')) ?>
                · Items seen: <?= $e((string) ($spFilesStats['items_seen'] ?? '')) ?>
                · Files downloaded: <?= $e((string) ($spFilesStats['files_downloaded'] ?? '')) ?>
                · Skipped: <?= $e((string) ($spFilesStats['files_skipped'] ?? '')) ?>
                · Bytes: <?= $e((string) ($spFilesStats['bytes_downloaded'] ?? 0)) ?>
                · Removed: <?= $e((string) ($spFilesStats['removed'] ?? 0)) ?>
                <?php if (!empty($spFilesStats['drives_resynced'])): ?>
                    · <span class="text-warning">Libraries resynced: <?= $e((string) $spFilesStats['drives_resynced']) ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if ($spListsStats !== null && empty($spListsStats['skipped'])): ?>
            <h4>SharePoint lists backup</h4>
            <p class="small text-muted">
                Lists: <?= $e((string) ($spListsStats['lists'] ?? '')) ?>
                · Items seen: <?= $e((string) ($spListsStats['items_seen'] ?? '')) ?>
                · Stored: <?= $e((string) ($spListsStats['items_stored'] ?? '')) ?>
                · Updated: <?= $e((string) ($spListsStats['items_updated'] ?? '')) ?>
                · Removed: <?= $e((string) ($spListsStats['removed'] ?? 0)) ?>
                <?php if (!empty($spListsStats['lists_resynced'])): ?>
                    · <span class="text-warning">Lists resynced: <?= $e((string) $spListsStats['lists_resynced']) ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if ($teamsMetaStats !== null && empty($teamsMetaStats['skipped'])): ?>
            <h4>Teams metadata backup</h4>
            <p class="small text-muted">
                Channels: <?= $e((string) ($teamsMetaStats['channels'] ?? '')) ?>
                · Members: <?= $e((string) ($teamsMetaStats['members'] ?? '')) ?>
                · Owners: <?= $e((string) ($teamsMetaStats['owners'] ?? '')) ?>
                · Tabs: <?= $e((string) ($teamsMetaStats['tabs'] ?? 0)) ?>
            </p>
        <?php endif; ?>
        <?php if ($teamsMsgStats !== null && empty($teamsMsgStats['skipped'])): ?>
            <h4>Teams messages backup</h4>
            <p class="small text-muted">
                Channels: <?= $e((string) ($teamsMsgStats['channels'] ?? '')) ?>
                · Messages seen: <?= $e((string) ($teamsMsgStats['messages_seen'] ?? '')) ?>
                · Stored: <?= $e((string) ($teamsMsgStats['messages_stored'] ?? '')) ?>
                · Updated: <?= $e((string) ($teamsMsgStats['messages_updated'] ?? '')) ?>
                · Replies: <?= $e((string) ($teamsMsgStats['replies_stored'] ?? 0)) ?>
                · Removed: <?= $e((string) ($teamsMsgStats['removed'] ?? 0)) ?>
                <?php if (!empty($teamsMsgStats['channels_resynced'])): ?>
                    · <span class="text-warning">Channels resynced: <?= $e((string) $teamsMsgStats['channels_resynced']) ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php if ($calendarResults !== [] || $calendarIncomplete !== []): ?>
            <h4>Calendar backup &amp; verify</h4>
            <table class="table table-condensed table-bordered">
                <thead>
                    <tr>
                        <th>Calendar</th>
                        <th>Inventory</th>
                        <th>Scan mode</th>
                        <th>Events</th>
                        <th>Verify</th>
                        <th>Graph / disk (sm+si)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calendarResults as $cr): ?>
                        <?php
                        $v = is_array($cr['verify'] ?? null) ? $cr['verify'] : null;
                        $vOk = $v !== null && !empty($v['ok']);
                        $rowClass = $v === null ? 'success' : ($vOk ? 'success' : 'warning');
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td><?= $e($cr['name'] ?? $cr['calendar_id'] ?? '') ?></td>
                            <td>Complete</td>
                            <td><?= $e($cr['scan_mode'] ?? 'normal') ?></td>
                            <td><?= $e((string) ($cr['event_count'] ?? '')) ?></td>
                            <td>
                                <?php if ($v === null): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($vOk): ?>
                                    <span class="label label-success">OK</span>
                                <?php else: ?>
                                    <span class="label label-warning"><?= $e((string) ($v['gap_count'] ?? '?')) ?> gap(s)</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($v !== null && isset($v['graph_total'], $v['disk_series_master_single'])): ?>
                                    <?= $e((string) $v['graph_total']) ?> / <?= $e((string) $v['disk_series_master_single']) ?>
                                    <?php if (isset($v['total_delta']) && (int) $v['total_delta'] !== 0): ?>
                                        <span class="text-warning"> (Δ <?= $e((string) $v['total_delta']) ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($v !== null && !empty($v['gaps']) && is_array($v['gaps'])): ?>
                            <tr class="<?= $rowClass ?>">
                                <td colspan="6" class="small text-muted" style="border-top:none;padding-top:0">
                                    <?php foreach ($v['gaps'] as $gap): ?>
                                        <div><?= $e(json_encode($gap, JSON_UNESCAPED_SLASHES)) ?></div>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php foreach ($calendarIncomplete as $cr): ?>
                        <tr class="danger">
                            <td><?= $e($cr['name'] ?? $cr['calendar_id'] ?? '') ?></td>
                            <td>Incomplete</td>
                            <td><?= $e($cr['scan_mode'] ?? '') ?></td>
                            <td><?= $e((string) ($cr['event_count'] ?? '')) ?></td>
                            <td><span class="label label-default">skipped</span></td>
                            <td>—</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php foreach ($calendarIncomplete as $cr): ?>
                <?php if (!empty($cr['failure_reason'])): ?>
                    <p class="text-danger small"><strong><?= $e($cr['name'] ?? '') ?>:</strong> <?= $e($cr['failure_reason']) ?></p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <h4>Log</h4>
        <pre id="ms365-run-log" style="max-height:480px;overflow:auto;background:#1e1e1e;color:#d4d4d4;padding:12px;font-size:12px">Loading…</pre>
    </div>
</div>
<p><a href="addonmodules.php?module=ms365backup&action=backup" class="btn btn-default">Back to Backup</a></p>

<script>
window.MS365_API = <?= json_encode($apiBase) ?>;
window.MS365_TOKEN = <?= json_encode($token) ?>;
window.MS365_RUN_ID = <?= json_encode($runId) ?>;
window.MS365_RUN_CANCELLABLE = <?= $canCancel ? 'true' : 'false' ?>;
</script>
<script src="<?= $e(ms365backup_asset_url('assets/js/ms365-admin.js')) ?>"></script>
