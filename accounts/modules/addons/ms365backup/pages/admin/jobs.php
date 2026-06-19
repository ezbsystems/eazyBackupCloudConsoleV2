<?php
declare(strict_types=1);

$e = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$apiBase = 'addonmodules.php?module=ms365backup&action=api';
$token = generate_token('plain');
?>
<script>window.MS365_JOBS_API = <?= json_encode($apiBase) ?>; window.MS365_TOKEN = <?= json_encode($token) ?>;</script>
<script src="<?= $e(ms365backup_asset_url('assets/js/jobs.js')) ?>?v=<?= (int) @filemtime(__DIR__ . '/../../assets/js/jobs.js') ?>"></script>

<div id="ms365-jobs-app">
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Filters</strong></div>
        <div class="panel-body">
            <form id="ms365-jobs-filters" class="form-inline">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control input-sm">
                        <option value="">All</option>
                        <option value="queued">Queued</option>
                        <option value="starting">Starting</option>
                        <option value="running">Running</option>
                        <option value="success">Success</option>
                        <option value="partial_success">Partial success</option>
                        <option value="failed">Failed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="warning">Warning</option>
                    </select>
                </div>
                <div class="form-group" style="margin-left:10px">
                    <label>Type</label>
                    <select name="type" class="form-control input-sm">
                        <option value="">All</option>
                        <option value="backup">Backup</option>
                        <option value="restore">Restore</option>
                    </select>
                </div>
                <div class="form-group" style="margin-left:10px">
                    <label>Client</label>
                    <input type="text" name="client_name" class="form-control input-sm" placeholder="Name or email">
                </div>
                <div class="form-group" style="margin-left:10px">
                    <label>Job name</label>
                    <input type="text" name="job_name" class="form-control input-sm" placeholder="Contains…">
                </div>
                <div class="form-group" style="margin-left:10px">
                    <label>Run ID</label>
                    <input type="text" name="run_id" class="form-control input-sm" placeholder="UUID prefix">
                </div>
                <div class="form-group" style="margin-left:10px">
                    <label>From</label>
                    <input type="date" name="date_from" class="form-control input-sm">
                </div>
                <div class="form-group" style="margin-left:10px">
                    <label>To</label>
                    <input type="date" name="date_to" class="form-control input-sm">
                </div>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-left:10px">Apply</button>
                <button type="button" class="btn btn-default btn-sm" id="ms365-jobs-reset">Reset</button>
            </form>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>MS365 batch runs</strong>
            <button type="button" class="btn btn-xs btn-default pull-right" id="ms365-jobs-refresh">Refresh</button>
        </div>
        <div class="panel-body" id="ms365-jobs-table-wrap">
            <p class="text-muted">Loading jobs…</p>
        </div>
        <div class="panel-footer" id="ms365-jobs-pagination"></div>
    </div>
</div>

<div class="modal fade" id="ms365-jobs-log-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" style="width:90%;max-width:1100px">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="ms365-jobs-log-modal-title">Logs</h4>
            </div>
            <div class="modal-body">
                <div id="ms365-jobs-log-summary" style="margin-bottom:10px"></div>
                <div class="form-group">
                    <input type="text" id="ms365-jobs-log-search" class="form-control input-sm" placeholder="Filter log text…">
                </div>
                <pre id="ms365-jobs-log-content" style="max-height:60vh;overflow:auto;background:#1e1e1e;color:#d4d4d4;padding:12px;font-size:12px"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" id="ms365-jobs-log-download">Download</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ms365-jobs-detail-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Batch workloads</h4>
            </div>
            <div class="modal-body" id="ms365-jobs-detail-body">
                <p class="text-muted">Loading…</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
