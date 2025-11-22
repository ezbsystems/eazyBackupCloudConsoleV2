<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2>Cloud Backup Administration</h2>
            
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Configuration</h3>
                </div>
                <div class="panel-body">
                    <dl class="dl-horizontal">
                        <dt>Worker Host:</dt>
                        <dd>{$worker_host}</dd>
                        <dt>Max Concurrent Jobs:</dt>
                        <dd>{$max_concurrent_jobs}</dd>
                        <dt>Max Bandwidth:</dt>
                        <dd>{$max_bandwidth} KB/s</dd>
                    </dl>
                </div>
            </div>

            <!-- Metrics Dashboard -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Metrics Dashboard</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="well text-center">
                                <h4>{$metrics.total_jobs|default:0}</h4>
                                <p class="text-muted">Total Jobs</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="well text-center">
                                <h4>{$metrics.active_jobs|default:0}</h4>
                                <p class="text-muted">Active Jobs</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="well text-center">
                                <h4>{$metrics.running_runs|default:0} / {$max_concurrent_jobs}</h4>
                                <p class="text-muted">Running Jobs / Limit</p>
                                {if $metrics.running_runs >= $max_concurrent_jobs}
                                    <span class="label label-warning">At Limit</span>
                                {else}
                                    <span class="label label-success">OK</span>
                                {/if}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="well text-center">
                                <h4>{$metrics.success_rate|default:0}%</h4>
                                <p class="text-muted">Success Rate (24h)</p>
                            </div>
                        </div>
                    </div>
                    <div class="row" style="margin-top: 15px;">
                        <div class="col-md-6">
                            <div class="well">
                                <strong>Bandwidth Limit:</strong> {$max_bandwidth} KB/s
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="well">
                                <strong>Worker Host:</strong> {$worker_host}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Filters</h3>
                </div>
                <div class="panel-body">
                    <form method="get" action="addonmodules.php">
                        <input type="hidden" name="module" value="cloudstorage">
                        <input type="hidden" name="action" value="cloudbackup_admin">
                        <div class="row">
                            <div class="col-md-2">
                                <label>Client:</label>
                                <select name="client_id" class="form-control">
                                    <option value="">All Clients</option>
                                    {foreach from=$clients item=client}
                                        <option value="{$client.id}" {if $filters.client_id eq $client.id}selected{/if}>
                                            {$client.firstname} {$client.lastname} ({$client.email})
                                        </option>
                                    {/foreach}
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>Job Name:</label>
                                <input type="text" name="job_name" class="form-control" value="{$filters.job_name|default:''}" placeholder="Search job name...">
                            </div>
                            <div class="col-md-2">
                                <label>Status:</label>
                                <select name="status" class="form-control">
                                    <option value="">All Statuses</option>
                                    <option value="active" {if $filters.status eq 'active'}selected{/if}>Active</option>
                                    <option value="paused" {if $filters.status eq 'paused'}selected{/if}>Paused</option>
                                    <option value="deleted" {if $filters.status eq 'deleted'}selected{/if}>Deleted</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>Source Type:</label>
                                <select name="source_type" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="s3_compatible" {if $filters.source_type eq 's3_compatible'}selected{/if}>S3-Compatible</option>
                                    <option value="aws" {if $filters.source_type eq 'aws'}selected{/if}>AWS</option>
                                    <option value="sftp" {if $filters.source_type eq 'sftp'}selected{/if}>SFTP</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label>Date From:</label>
                                <input type="date" name="date_from" class="form-control" value="{$filters.date_from|default:''}">
                            </div>
                            <div class="col-md-2">
                                <label>Date To:</label>
                                <input type="date" name="date_to" class="form-control" value="{$filters.date_to|default:''}">
                            </div>
                        </div>
                        <div class="row" style="margin-top: 15px;">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&export=csv" class="btn btn-success">Export CSV</a>
                                <a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin" class="btn btn-default">Clear Filters</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Jobs ({count($jobs)})</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Job Name</th>
                                <th>Source</th>
                                <th>Destination</th>
                                <th>Schedule</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if count($jobs) > 0}
                                {foreach from=$jobs item=job}
                                    <tr>
                                        <td>{$job.id}</td>
                                        <td>{$job.firstname} {$job.lastname}</td>
                                        <td>{$job.name}</td>
                                        <td>{$job.source_display_name} ({$job.source_type})</td>
                                        <td>Bucket #{$job.dest_bucket_id} / {$job.dest_prefix}</td>
                                        <td>{$job.schedule_type|ucfirst}</td>
                                        <td>
                                            <span class="label label-{if $job.status eq 'active'}success{elseif $job.status eq 'paused'}warning{else}default{/if}">
                                                {$job.status|ucfirst}
                                            </span>
                                        </td>
                                        <td>{$job.created_at|date_format:"%d %b %Y %H:%M"}</td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="8" class="text-center">No jobs found</td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Recent Runs</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Job</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Finished</th>
                                <th>Progress</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if count($runs) > 0}
                                {foreach from=$runs item=run}
                                    <tr>
                                        <td>{$run.id}</td>
                                        <td>{$run.firstname} {$run.lastname}</td>
                                        <td>{$run.job_name}</td>
                                        <td>
                                            <span class="label label-{if $run.status eq 'success'}success{elseif $run.status eq 'failed'}danger{elseif $run.status eq 'running'}info{else}default{/if}">
                                                {$run.status|ucfirst}
                                            </span>
                                        </td>
                                        <td>{if $run.started_at}{$run.started_at|date_format:"%d %b %Y %H:%M"}{else}-{/if}</td>
                                        <td>{if $run.finished_at}{$run.finished_at|date_format:"%d %b %Y %H:%M"}{else}-{/if}</td>
                                        <td>{if $run.progress_pct}{$run.progress_pct}%{else}-{/if}</td>
                                        <td>
                                            {if $run.status eq 'running' || $run.status eq 'starting'}
                                                <button onclick="forceCancelRun({$run.id})" class="btn btn-danger btn-xs">Force Stop</button>
                                            {/if}
                                            {if $run.log_excerpt || $run.validation_log_excerpt}
                                                <button onclick="showRunLogs({$run.id})" class="btn btn-info btn-xs">View Logs</button>
                                            {/if}
                                        </td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="8" class="text-center">No runs found</td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log Viewer Modal -->
<div class="modal fade" id="logViewerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Run Logs</h4>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="logTabs">
                    <li class="active"><a href="#backupLog" data-toggle="tab">Backup Log</a></li>
                    <li><a href="#validationLog" data-toggle="tab">Validation Log</a></li>
                </ul>
                <div class="tab-content" style="margin-top: 15px;">
                    <div class="tab-pane active" id="backupLog">
                        <pre id="backupLogContent" style="max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 10px; border-radius: 4px;"></pre>
                    </div>
                    <div class="tab-pane" id="validationLog">
                        <pre id="validationLogContent" style="max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 10px; border-radius: 4px;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function forceCancelRun(runId) {
    if (!confirm('Are you sure you want to force stop this run?')) {
        return;
    }
    
    fetch('addonmodules.php?module=cloudstorage&action=cloudbackup_admin&cancel_run=' + runId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message || 'Failed to cancel run');
            }
        });
}

function showRunLogs(runId) {
    fetch('addonmodules.php?module=cloudstorage&action=cloudbackup_admin&get_run_logs=' + runId)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('backupLogContent').textContent = data.backup_log || 'No backup log available';
                document.getElementById('validationLogContent').textContent = data.validation_log || 'No validation log available';
                $('#logViewerModal').modal('show');
            } else {
                alert(data.message || 'Failed to load logs');
            }
        })
        .catch(error => {
            alert('Error loading logs: ' + error);
        });
}
</script>

