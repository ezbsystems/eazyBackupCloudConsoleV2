<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h2>Cloud Backup Administration</h2>
            <ul class="nav nav-pills" style="margin-bottom:15px;">
                <li{if $active_tab eq 'dashboard'} class="active"{/if}><a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab=dashboard">Dashboard</a></li>
                <li{if $active_tab eq 'jobs'} class="active"{/if}><a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab=jobs">Jobs</a></li>
                <li{if $active_tab eq 'runs'} class="active"{/if}><a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab=runs">Runs</a></li>
                <li{if $active_tab eq 'agents'} class="active"{/if}><a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab=agents">Agents</a></li>
                <li{if $active_tab eq 'clients'} class="active"{/if}><a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab=clients">Clients</a></li>
                <li{if $active_tab eq 'retention'} class="active"{/if}><a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab=retention">Retention</a></li>
            </ul>
            {if $active_tab eq 'dashboard'}
            <div class="panel panel-default" id="cb-dashboard">
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

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Agent Watchdog & Resume</h3>
                </div>
                <div class="panel-body">
                    {if $watchdog_save_status eq 'success'}
                        <div class="alert alert-success">{$watchdog_save_message}</div>
                    {elseif $watchdog_save_status eq 'error'}
                        <div class="alert alert-danger">{$watchdog_save_message}</div>
                    {/if}
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-6">
                            <strong>Service status:</strong>
                            {if $watchdog_status.service_ok}
                                <span class="label label-success">{$watchdog_status.service_status|default:'unknown'} (last={$watchdog_status.service_result|default:'unknown'})</span>
                            {else}
                                <span class="label label-danger">{$watchdog_status.service_status|default:'unknown'} (last={$watchdog_status.service_result|default:'unknown'})</span>
                            {/if}
                        </div>
                        <div class="col-md-6">
                            <strong>Timer status:</strong>
                            {if $watchdog_status.timer_ok}
                                <span class="label label-success">{$watchdog_status.timer_status|default:'unknown'} (last={$watchdog_status.timer_result|default:'unknown'})</span>
                            {else}
                                <span class="label label-danger">{$watchdog_status.timer_status|default:'unknown'} (last={$watchdog_status.timer_result|default:'unknown'})</span>
                            {/if}
                        </div>
                    </div>
                    <form method="post" action="addonmodules.php?module=cloudstorage&action=cloudbackup_admin">
                        <input type="hidden" name="tab" value="dashboard">
                        <input type="hidden" name="update_watchdog_settings" value="1">
                        <div class="row">
                            <div class="col-md-4">
                                <label>Watchdog timeout (seconds)</label>
                                <input type="number" min="60" step="30" name="agent_watchdog_timeout_seconds" class="form-control" value="{$watchdog_settings.stored_watchdog|default:720}">
                                <p class="help-block">Effective: {$watchdog_settings.effective_watchdog} (env overrides if set)</p>
                            </div>
                            <div class="col-md-4">
                                <label>Reclaim grace (seconds)</label>
                                <input type="number" min="30" step="15" name="agent_reclaim_grace_seconds" class="form-control" value="{$watchdog_settings.stored_reclaim|default:180}">
                                <p class="help-block">Effective: {$watchdog_settings.effective_reclaim} (must be &lt; timeout)</p>
                            </div>
                            <div class="col-md-4">
                                <label>Reclaim enabled</label>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="agent_reclaim_enabled" value="1" {if $watchdog_settings.stored_reclaim_enabled}checked{/if}> Allow agent to reclaim stale in-progress run
                                    </label>
                                </div>
                                <p class="help-block">Effective: {if $watchdog_settings.effective_reclaim_enabled}On{else}Off{/if}</p>
                            </div>
                        </div>
                        <div class="row" style="margin-top: 15px;">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Save Watchdog Settings</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Metrics Dashboard -->
            <div class="panel panel-default" id="cb-dashboard-metrics">
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
            {/if}

            {if $active_tab eq 'jobs' || $active_tab eq 'runs'}
            <div class="panel panel-default" id="cb-filters">
                <div class="panel-heading">
                    <h3 class="panel-title">Filters</h3>
                </div>
                <div class="panel-body">
                    <form method="get" action="addonmodules.php">
                        <input type="hidden" name="module" value="cloudstorage">
                        <input type="hidden" name="action" value="cloudbackup_admin">
                        <input type="hidden" name="tab" value="{$active_tab}">
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
                                <a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab={$active_tab}&export=csv" class="btn btn-success">Export CSV</a>
                                <a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab={$active_tab}" class="btn btn-default">Clear Filters</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            {/if}

            {if $active_tab eq 'jobs'}
            <div class="panel panel-default" id="cb-jobs">
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
                                <th>Engine</th>
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
                                        <td class="font-mono" style="font-size:0.75rem">{$job.job_id|truncate:12:'...'}</td>
                                        <td>{$job.firstname} {$job.lastname}</td>
                                        <td>{$job.name}</td>
                                        <td>{$job.source_display_name} ({$job.source_type})</td>
                                        <td>{$job.engine|default:'sync'|upper}</td>
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
            {/if}

            {if $active_tab eq 'runs'}
            <div class="panel panel-default" id="cb-runs">
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
                                <th>Engine</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Finished</th>
                                <th>Progress</th>
                                        <th>Log Ref</th>
                                        <th>Actions</th>
                                        <th>Maintenance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if count($runs) > 0}
                                {foreach from=$runs item=run}
                                    <tr>
                                        <td class="font-mono" style="font-size:0.75rem">{$run.run_id|truncate:12:'...'}</td>
                                        <td>{$run.firstname} {$run.lastname}</td>
                                        <td>{$run.job_name}</td>
                                        <td>{$run.engine|default:'sync'|upper}</td>
                                        <td>
                                            <span class="label label-{if $run.status eq 'success'}success{elseif $run.status eq 'failed'}danger{elseif $run.status eq 'running'}info{else}default{/if}">
                                                {$run.status|ucfirst}
                                            </span>
                                        </td>
                                        <td>{if $run.started_at}{$run.started_at|date_format:"%d %b %Y %H:%M"}{else}-{/if}</td>
                                        <td>{if $run.finished_at}{$run.finished_at|date_format:"%d %b %Y %H:%M"}{else}-{/if}</td>
                                        <td>{if $run.progress_pct}{$run.progress_pct}%{else}-{/if}</td>
                                        <td>{if $run.log_ref}{$run.log_ref}{else}-{/if}</td>
                                        <td>
                                            {if $run.status eq 'running' || $run.status eq 'starting'}
                                                <button onclick="forceCancelRun('{$run.run_id}')" class="btn btn-danger btn-xs">Force Stop</button>
                                            {/if}
                                            {if $run.log_excerpt || $run.validation_log_excerpt}
                                                <button onclick="showRunLogs('{$run.run_id}')" class="btn btn-info btn-xs">View Logs</button>
                                            {/if}
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-default btn-xs" onclick="enqueueMaintenance('{$run.run_id}', 'maintenance_quick')">Quick</button>
                                                <button class="btn btn-default btn-xs" onclick="enqueueMaintenance('{$run.run_id}', 'maintenance_full')">Full</button>
                                            </div>
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
            {/if}

            {if $active_tab eq 'agents'}
            <div class="panel panel-default" id="cb-agents">
                <div class="panel-heading">
                    <h3 class="panel-title">Agents ({$agents_total|default:0})</h3>
                </div>
                <div class="panel-body">
                    <form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom: 15px;">
                        <input type="hidden" name="module" value="cloudstorage">
                        <input type="hidden" name="action" value="cloudbackup_admin">
                        <input type="hidden" name="tab" value="agents">
                        <input type="hidden" name="agents_sort" value="{$agents_sort|default:'created_at'}">
                        <input type="hidden" name="agents_dir" value="{$agents_dir|default:'desc'}">
                        <input type="hidden" name="agents_page" value="1">
                        <div class="form-group" style="margin-right:10px;">
                            <label class="sr-only" for="agents_q">Search</label>
                            <input type="text" class="form-control" id="agents_q" name="agents_q" value="{$agents_filters.q|default:''}" placeholder="Search hostname, device, name, email, UUID" style="min-width: 320px;">
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <select name="agents_client_id" class="form-control">
                                <option value="">All Clients</option>
                                {foreach from=$clients item=client}
                                    <option value="{$client.id}" {if $agents_filters.client_id eq $client.id}selected{/if}>{$client.firstname} {$client.lastname} ({$client.email})</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <select name="agents_status" class="form-control">
                                <option value="">All Status</option>
                                <option value="active" {if $agents_filters.status eq 'active'}selected{/if}>Active</option>
                                <option value="disabled" {if $agents_filters.status eq 'disabled'}selected{/if}>Disabled</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <select name="agents_type" class="form-control">
                                <option value="">All Types</option>
                                <option value="workstation" {if $agents_filters.agent_type eq 'workstation'}selected{/if}>Workstation</option>
                                <option value="server" {if $agents_filters.agent_type eq 'server'}selected{/if}>Server</option>
                                <option value="hypervisor" {if $agents_filters.agent_type eq 'hypervisor'}selected{/if}>Hypervisor</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <select name="agents_online" class="form-control">
                                <option value="">Any Connection</option>
                                <option value="online" {if $agents_filters.online_status eq 'online'}selected{/if}>Online</option>
                                <option value="offline" {if $agents_filters.online_status eq 'offline'}selected{/if}>Offline</option>
                                <option value="never" {if $agents_filters.online_status eq 'never'}selected{/if}>Never Seen</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <select name="agents_tenant_id" class="form-control">
                                <option value="">All Tenants</option>
                                <option value="direct" {if $agents_filters.tenant_id eq 'direct'}selected{/if}>Direct</option>
                                {foreach from=$agent_tenants item=tenant}
                                    <option value="{$tenant.id}" {if $agents_filters.tenant_id eq $tenant.id}selected{/if}>{$tenant.name}</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <select name="agents_per_page" class="form-control">
                                <option value="25" {if $agents_per_page eq 25}selected{/if}>25</option>
                                <option value="50" {if $agents_per_page eq 50}selected{/if}>50</option>
                                <option value="100" {if $agents_per_page eq 100}selected{/if}>100</option>
                                <option value="200" {if $agents_per_page eq 200}selected{/if}>200</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a class="btn btn-default" href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab=agents">Reset</a>
                        <button type="button" class="btn btn-info" data-toggle="modal" data-target="#agentHelpModal">Help</button>
                    </form>

                    {assign var=agentBaseUrl value="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab=agents&agents_q=`$agents_filters.q|escape:'url'`&agents_client_id=`$agents_filters.client_id|escape:'url'`&agents_status=`$agents_filters.status|escape:'url'`&agents_type=`$agents_filters.agent_type|escape:'url'`&agents_online=`$agents_filters.online_status|escape:'url'`&agents_tenant_id=`$agents_filters.tenant_id|escape:'url'`&agents_per_page=`$agents_per_page`&agents_page=1"}

                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th><a href="{$agentBaseUrl}&agents_sort=online_status&agents_dir={if $agents_sort eq 'online_status' && $agents_dir eq 'asc'}desc{else}asc{/if}">Connection</a></th>
                                    <th><a href="{$agentBaseUrl}&agents_sort=agent_uuid&agents_dir={if $agents_sort eq 'agent_uuid' && $agents_dir eq 'asc'}desc{else}asc{/if}">Agent UUID</a></th>
                                    <th><a href="{$agentBaseUrl}&agents_sort=hostname&agents_dir={if $agents_sort eq 'hostname' && $agents_dir eq 'asc'}desc{else}asc{/if}">Hostname</a></th>
                                    <th><a href="{$agentBaseUrl}&agents_sort=device_id&agents_dir={if $agents_sort eq 'device_id' && $agents_dir eq 'asc'}desc{else}asc{/if}">Device ID</a></th>
                                    <th><a href="{$agentBaseUrl}&agents_sort=device_name&agents_dir={if $agents_sort eq 'device_name' && $agents_dir eq 'asc'}desc{else}asc{/if}">Device Name</a></th>
                                    <th><a href="{$agentBaseUrl}&agents_sort=tenant&agents_dir={if $agents_sort eq 'tenant' && $agents_dir eq 'asc'}desc{else}asc{/if}">Tenant</a></th>
                                    <th><a href="{$agentBaseUrl}&agents_sort=agent_type&agents_dir={if $agents_sort eq 'agent_type' && $agents_dir eq 'asc'}desc{else}asc{/if}">Type</a></th>
                                    <th>Version</th>
                                    <th>OS/Arch</th>
                                    <th>Build</th>
                                    <th><a href="{$agentBaseUrl}&agents_sort=status&agents_dir={if $agents_sort eq 'status' && $agents_dir eq 'asc'}desc{else}asc{/if}">Status</a></th>
                                    <th><a href="{$agentBaseUrl}&agents_sort=last_seen_at&agents_dir={if $agents_sort eq 'last_seen_at' && $agents_dir eq 'asc'}desc{else}asc{/if}">Last Seen</a></th>
                                    <th><a href="{$agentBaseUrl}&agents_sort=created_at&agents_dir={if $agents_sort eq 'created_at' && $agents_dir eq 'asc'}desc{else}asc{/if}">Created</a></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {if count($agents) > 0}
                                    {foreach from=$agents item=agent}
                                        <tr>
                                            <td>
                                                {if $agent.online_status eq 'online'}
                                                    <span class="label label-success">Online</span>
                                                {elseif $agent.online_status eq 'offline'}
                                                    <span class="label label-danger">Offline</span>
                                                {else}
                                                    <span class="label label-default">Never</span>
                                                {/if}
                                            </td>
                                            <td>{$agent.agent_uuid|default:'-'}</td>
                                            <td>
                                                {$agent.hostname|default:'-'}
                                                <div class="text-muted small">{$agent.client_name|default:'Unknown Client'}{if $agent.email} ({$agent.email}){/if}</div>
                                            </td>
                                            <td><code>{$agent.device_id|default:'-'}</code></td>
                                            <td>{$agent.device_name|default:'-'}</td>
                                            <td>{$agent.tenant_name|default:'Direct'}</td>
                                            <td>{$agent.agent_type|default:'workstation'}</td>
                                            <td>{$agent.agent_version|default:'-'}</td>
                                            <td>{if $agent.agent_os || $agent.agent_arch}{$agent.agent_os|default:'?'} / {$agent.agent_arch|default:'?'}{else}-{/if}</td>
                                            <td>{$agent.agent_build|default:'-'}</td>
                                            <td>
                                                <span class="label label-{if $agent.status eq 'active'}success{else}default{/if}">
                                                    {$agent.status|ucfirst}
                                                </span>
                                            </td>
                                            <td>{if $agent.last_seen_at}{$agent.last_seen_at|date_format:"%d %b %Y %H:%M"}{else}-{/if}</td>
                                            <td>{if $agent.created_at}{$agent.created_at|date_format:"%d %b %Y %H:%M"}{else}-{/if}</td>
                                            <td>
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-default btn-xs dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        Manage <span class="caret"></span>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-right">
                                                        <li><a href="#" onclick="openAgentLogs('{$agent.agent_uuid|escape:'javascript'}'); return false;">Agent Logs</a></li>
                                                        <li><a href="#" onclick="openTrayLogs('{$agent.agent_uuid|escape:'javascript'}'); return false;">Tray Logs</a></li>
                                                        <li role="separator" class="divider"></li>
                                                        <li><a href="#" onclick="adminResetAgent('{$agent.agent_uuid|escape:'javascript'}'); return false;">Reset Agent (Restart Service)</a></li>
                                                        {if $agent.active_run_id}
                                                            <li><a href="#" onclick="adminMaintenanceForAgent('{$agent.agent_uuid|escape:'javascript'}', 'maintenance_quick', '{$agent.active_run_id|escape:'javascript'}'); return false;">Maintenance Quick</a></li>
                                                            <li><a href="#" onclick="adminMaintenanceForAgent('{$agent.agent_uuid|escape:'javascript'}', 'maintenance_full', '{$agent.active_run_id|escape:'javascript'}'); return false;">Maintenance Full</a></li>
                                                        {else}
                                                            <li><span class="text-muted" style="display:block; padding:3px 20px;" title="No active run available for this agent">Maintenance Quick</span></li>
                                                            <li><span class="text-muted" style="display:block; padding:3px 20px;" title="No active run available for this agent">Maintenance Full</span></li>
                                                        {/if}
                                                        <li><a href="#" onclick="adminRefreshInventory('{$agent.agent_uuid|escape:'javascript'}'); return false;">Request Inventory Refresh</a></li>
                                                        <li role="separator" class="divider"></li>
                                                        <li><a href="addonmodules.php?module=cloudstorage&action=cloudbackup_admin&tab=runs&agent_uuid={$agent.agent_uuid|escape:'url'}">View This Agent's Runs</a></li>
                                                        <li><a href="index.php?m=cloudstorage&page=e3backup&view=jobs&open_create=1&prefill_source=local_agent&prefill_agent_uuid={$agent.agent_uuid|escape:'url'}" target="_blank" rel="noopener">Create Job (Prefilled)</a></li>
                                                        <li><a href="index.php?m=cloudstorage&page=e3backup&view=restores&agent_uuid={$agent.agent_uuid|escape:'url'}" target="_blank" rel="noopener">Restore Points For Agent</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    {/foreach}
                                {else}
                                    <tr>
                                        <td colspan="14" class="text-center">No agents found</td>
                                    </tr>
                                {/if}
                            </tbody>
                        </table>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <p class="text-muted" style="margin-top:8px;">
                                Showing page {$agents_page|default:1} of {$agents_pages|default:1} ({$agents_total|default:0} total)
                            </p>
                        </div>
                        <div class="col-md-6 text-right">
                            {if $agents_page > 1}
                                <a class="btn btn-default btn-sm" href="{$agentBaseUrl}&agents_sort={$agents_sort|escape:'url'}&agents_dir={$agents_dir|escape:'url'}&agents_page={$agents_page-1}">Previous</a>
                            {/if}
                            {if $agents_page < $agents_pages}
                                <a class="btn btn-default btn-sm" href="{$agentBaseUrl}&agents_sort={$agents_sort|escape:'url'}&agents_dir={$agents_dir|escape:'url'}&agents_page={$agents_page+1}">Next</a>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
            {/if}

            {if $active_tab eq 'retention'}
            <div class="panel panel-default" id="cb-retention-enqueue">
                <div class="panel-heading">
                    <h3 class="panel-title">Manual Enqueue Repo Operation</h3>
                </div>
                <div class="panel-body">
                    <form id="repoOpEnqueueForm" class="form-inline">
                        <div class="form-group" style="margin-right:10px;">
                            <label for="enqueue_repo_id">Repo:</label>
                            <select id="enqueue_repo_id" name="repo_id" class="form-control" required>
                                <option value="">-- Select repo --</option>
                                {foreach from=$kopia_repos item=repo}
                                    <option value="{$repo.id}">#{$repo.id} ({$repo.repository_id|truncate:24})</option>
                                {/foreach}
                            </select>
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <button type="button" class="btn btn-default" onclick="adminEnqueueRepoOp('retention_apply')">Retention Apply</button>
                            <button type="button" class="btn btn-default" onclick="adminEnqueueRepoOp('maintenance_quick')">Maintenance Quick</button>
                            <button type="button" class="btn btn-default" onclick="adminEnqueueRepoOp('maintenance_full')">Maintenance Full</button>
                        </div>
                    </form>
                    {if count($kopia_repos) eq 0}
                        <p class="text-muted" style="margin-top:10px;">No active Kopia repos found.</p>
                    {/if}
                </div>
            </div>
            <div class="panel panel-default" id="cb-retention-ops">
                <div class="panel-heading">
                    <h3 class="panel-title">Repo Operations ({count($repo_ops)})</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Repo</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Attempts</th>
                                <th>Next Attempt</th>
                                <th>Created</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if count($repo_ops) > 0}
                                {foreach from=$repo_ops item=op}
                                    <tr>
                                        <td>{$op.id}</td>
                                        <td>#{$op.repo_id} ({$op.repository_id|default:'-'|truncate:16})</td>
                                        <td>{$op.op_type|default:'-'}</td>
                                        <td>
                                            <span class="label label-{if $op.status eq 'success'}success{elseif $op.status eq 'failed'}danger{elseif $op.status eq 'running'}info{else}default{/if}">
                                                {$op.status|default:'queued'|ucfirst}
                                            </span>
                                        </td>
                                        <td>{$op.attempt_count|default:0}</td>
                                        <td>{if $op.next_attempt_at}{$op.next_attempt_at|date_format:"%d %b %Y %H:%M"}{else}-{/if}</td>
                                        <td>{if $op.created_at}{$op.created_at|date_format:"%d %b %Y %H:%M"}{else}-{/if}</td>
                                        <td>{if $op.updated_at}{$op.updated_at|date_format:"%d %b %Y %H:%M"}{else}-{/if}</td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="8" class="text-center">No repo operations found</td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>
            {/if}

            {if $active_tab eq 'clients'}
            <div class="panel panel-default" id="cb-clients">
                <div class="panel-heading">
                    <h3 class="panel-title">Clients ({count($clients)})</h3>
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            {if count($clients) > 0}
                                {foreach from=$clients item=client}
                                    <tr>
                                        <td>{$client.id}</td>
                                        <td>{$client.firstname} {$client.lastname}</td>
                                        <td>{$client.email}</td>
                                    </tr>
                                {/foreach}
                            {else}
                                <tr>
                                    <td colspan="3" class="text-center">No clients found</td>
                                </tr>
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>
            {/if}
        </div>
    </div>
</div>

<!-- Agent Actions Help Modal -->
<div class="modal fade" id="agentHelpModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Agents Manage Menu Help</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">Quick reference for each action in the Agents table Manage menu.</p>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="width: 220px;">Action</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Agent Logs</strong></td>
                            <td>Loads and displays the latest tail of <code>C:\ProgramData\E3Backup\logs\agent.log</code> from the selected device.</td>
                        </tr>
                        <tr>
                            <td><strong>Tray Logs</strong></td>
                            <td>Loads and displays the latest tail of <code>C:\ProgramData\E3Backup\logs\tray.log</code> from the selected device.</td>
                        </tr>
                        <tr>
                            <td><strong>Reset Agent</strong></td>
                            <td>Queues a reset command to restart the agent process/service on the endpoint. Use this after updates or if command handling appears stale.</td>
                        </tr>
                        <tr>
                            <td><strong>Maintenance Quick</strong></td>
                            <td>Queues quick repository maintenance for the agent's current active run context. Disabled when no active run exists.</td>
                        </tr>
                        <tr>
                            <td><strong>Maintenance Full</strong></td>
                            <td>Queues full repository maintenance for the agent's current active run context. Disabled when no active run exists.</td>
                        </tr>
                        <tr>
                            <td><strong>Request Inventory Refresh</strong></td>
                            <td>Forces an immediate volume/device inventory update from the agent and refreshes cached source-selection metadata.</td>
                        </tr>
                        <tr>
                            <td><strong>View This Agent's Runs</strong></td>
                            <td>Navigates to the Runs section filtered to the selected agent.</td>
                        </tr>
                        <tr>
                            <td><strong>Create Job (Prefilled)</strong></td>
                            <td>Opens the e3 Backup job wizard with local-agent source and this agent preselected.</td>
                        </tr>
                        <tr>
                            <td><strong>Restore Points For Agent</strong></td>
                            <td>Opens restore points scoped to the selected agent.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
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

<!-- Agent Manage Modal -->
<div class="modal fade" id="agentManageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Agent Logs & Diagnostics <span id="agentManageTitle" class="text-muted"></span></h4>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs">
                    <li class="active"><a href="#agentLogsTab" data-toggle="tab">Agent Logs</a></li>
                    <li><a href="#trayLogsTab" data-toggle="tab">Tray Logs</a></li>
                    <li><a href="#agentDiagnosticsTab" data-toggle="tab">Diagnostics</a></li>
                </ul>
                <div class="tab-content" style="margin-top: 15px;">
                    <div class="tab-pane active" id="agentLogsTab">
                        <div style="margin-bottom:8px;">
                            <button type="button" class="btn btn-default btn-xs" onclick="refreshAgentLogTab()">Refresh Agent Log</button>
                        </div>
                        <div id="agentLogMeta" class="text-muted small" style="margin-bottom:8px;"></div>
                        <pre id="agentLogContent" style="max-height: 420px; overflow-y: auto; background: #f5f5f5; padding: 10px; border-radius: 4px;">Select Agent Logs to load content.</pre>
                    </div>
                    <div class="tab-pane" id="trayLogsTab">
                        <div style="margin-bottom:8px;">
                            <button type="button" class="btn btn-default btn-xs" onclick="refreshTrayLogTab()">Refresh Tray Log</button>
                        </div>
                        <div id="trayLogMeta" class="text-muted small" style="margin-bottom:8px;"></div>
                        <pre id="trayLogContent" style="max-height: 420px; overflow-y: auto; background: #f5f5f5; padding: 10px; border-radius: 4px;">Select Tray Logs to load content.</pre>
                    </div>
                    <div class="tab-pane" id="agentDiagnosticsTab">
                        <div id="agentDiagnosticsMeta" class="text-muted small" style="margin-bottom:8px;">
                            Storage/network diagnostics
                        </div>
                        <div id="agentDiagnosticsContent" style="max-height: 420px; overflow-y: auto; background: #f5f5f5; padding: 10px; border-radius: 4px;">
                            Loading diagnostics...
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <label style="font-weight: normal; margin-right: 8px;">
                    <input type="checkbox" id="agentLogAutoRefreshEnabled" onchange="updateAgentLogAutoRefresh()">
                    Auto-refresh
                </label>
                <label style="font-weight: normal; margin-right: 12px;">
                    Every
                    <select id="agentLogAutoRefreshSeconds" onchange="updateAgentLogAutoRefresh()" style="display:inline-block; width:auto;">
                        <option value="5">5s</option>
                        <option value="10" selected>10s</option>
                        <option value="15">15s</option>
                        <option value="30">30s</option>
                        <option value="60">60s</option>
                    </select>
                </label>
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let agentManageCurrentAgentUuid = '';
let agentManageCurrentLogKind = 'agent';
let agentManageAutoRefreshTimer = null;

function forceCancelRun(runId) {
    if (!confirm('Are you sure you want to force stop this run?')) {
        return;
    }
    
    fetch('addonmodules.php?module=cloudstorage&action=cloudbackup_admin&cancel_run=' + encodeURIComponent(runId))
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
    fetch('addonmodules.php?module=cloudstorage&action=cloudbackup_admin&get_run_logs=' + encodeURIComponent(runId))
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

function adminEnqueueRepoOp(opType) {
    const repoId = document.getElementById('enqueue_repo_id');
    if (!repoId || !repoId.value || parseInt(repoId.value, 10) <= 0) {
        alert('Please select a repo');
        return;
    }
    const formData = new URLSearchParams({ repo_id: repoId.value, op_type: opType });
    fetch('/modules/addons/cloudstorage/api/admin_enqueue_repo_operation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success' || data.status === 'duplicate') {
            alert('Operation (' + opType + ') enqueued');
            location.reload();
        } else {
            alert(data.message || 'Failed to enqueue');
        }
    })
    .catch(() => alert('Error enqueuing operation'));
}

function enqueueMaintenance(runId, type) {
    fetch('/modules/addons/cloudstorage/api/admin_cloudbackup_request_command.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ run_id: runId, type })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Maintenance (' + type + ') enqueued for run ' + runId);
        } else {
            alert(data.message || 'Failed to enqueue maintenance');
        }
    })
    .catch(() => alert('Error enqueuing maintenance'));
}

function adminEnqueueAgentCommand(payload) {
    return fetch('/modules/addons/cloudstorage/api/admin_cloudbackup_request_command.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload)
    }).then(r => r.json());
}

function adminResetAgent(agentUuid) {
    if (!confirm('Reset this agent now? This will restart the Windows agent service.')) {
        return;
    }
    adminEnqueueAgentCommand({ agent_uuid: agentUuid, type: 'reset_agent' })
        .then(data => {
            if (data.status === 'success') {
                alert('Service restart command queued for agent ' + agentUuid);
            } else {
                alert(data.message || 'Failed to queue reset command');
            }
        })
        .catch(() => alert('Failed to queue reset command'));
}

function adminMaintenanceForAgent(agentUuid, type, activeRunId) {
    const payload = { type: type, agent_uuid: agentUuid };
    if (typeof activeRunId === 'string' && activeRunId.trim() !== '') {
        payload.run_id = activeRunId;
    }
    adminEnqueueAgentCommand(payload)
        .then(data => {
            if (data.status === 'success') {
                alert('Maintenance command queued for agent ' + agentUuid);
            } else {
                alert(data.message || 'Failed to queue maintenance command');
            }
        })
        .catch(() => alert('Failed to queue maintenance command'));
}

function adminRefreshInventory(agentUuid) {
    adminEnqueueAgentCommand({ agent_uuid: agentUuid, type: 'refresh_inventory' })
        .then(data => {
            if (data.status === 'success') {
                alert('Inventory refresh queued for agent ' + agentUuid);
            } else {
                alert(data.message || 'Failed to queue inventory refresh');
            }
        })
        .catch(() => alert('Failed to queue inventory refresh'));
}

function formatLogMeta(data) {
    if (!data || data.status !== 'success') {
        return '';
    }
    const details = [];
    if (data.path) details.push(data.path);
    if (data.retrieved_at) details.push('retrieved ' + data.retrieved_at);
    if (data.truncated) details.push('truncated');
    return details.join(' | ');
}

function fetchAgentLogTail(agentUuid, logKind, contentEl, metaEl) {
    const contentNode = document.getElementById(contentEl);
    const metaNode = document.getElementById(metaEl);
    if (!contentNode || !metaNode) {
        return;
    }
    contentNode.textContent = 'Loading...';
    metaNode.textContent = '';
    fetch('/modules/addons/cloudstorage/api/admin_cloudbackup_fetch_log_tail.php?agent_uuid=' + encodeURIComponent(agentUuid) + '&log_kind=' + encodeURIComponent(logKind))
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                contentNode.textContent = data.message || 'Failed to load log';
                return;
            }
            metaNode.textContent = formatLogMeta(data);
            contentNode.textContent = data.content || '[empty log]';
        })
        .catch(() => {
            contentNode.textContent = 'Failed to load log';
        });
}

function loadAgentDiagnostics(agentUuid) {
    const diagnostics = document.getElementById('agentDiagnosticsContent');
    diagnostics.textContent = 'Loading diagnostics...';
    fetch('/modules/addons/cloudstorage/api/admin_cloudbackup_agent_diagnostics.php?agent_uuid=' + encodeURIComponent(agentUuid) + '&limit=100')
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                diagnostics.textContent = data.message || 'Failed to load diagnostics';
                return;
            }
            if (!data.events || data.events.length === 0) {
                diagnostics.textContent = 'No recent diagnostic events.';
                return;
            }
            const lines = data.events.map(function (e) {
                return '[' + (e.ts || '-') + '] [' + (e.level || 'info').toUpperCase() + '] '
                    + (e.code || 'EVENT') + ' (run: ' + (e.run_id || '-') + '): ' + (e.message || '');
            });
            diagnostics.textContent = lines.join('\n');
        })
        .catch(() => {
            diagnostics.textContent = 'Failed to load diagnostics';
        });
}

function refreshAgentLogTab() {
    if (!agentManageCurrentAgentUuid) {
        return;
    }
    fetchAgentLogTail(agentManageCurrentAgentUuid, 'agent', 'agentLogContent', 'agentLogMeta');
}

function refreshTrayLogTab() {
    if (!agentManageCurrentAgentUuid) {
        return;
    }
    fetchAgentLogTail(agentManageCurrentAgentUuid, 'tray', 'trayLogContent', 'trayLogMeta');
}

function clearAgentLogAutoRefreshTimer() {
    if (agentManageAutoRefreshTimer) {
        clearInterval(agentManageAutoRefreshTimer);
        agentManageAutoRefreshTimer = null;
    }
}

function refreshActiveLogTab() {
    if (agentManageCurrentLogKind === 'agent') {
        refreshAgentLogTab();
    } else if (agentManageCurrentLogKind === 'tray') {
        refreshTrayLogTab();
    }
}

function updateAgentLogAutoRefresh() {
    clearAgentLogAutoRefreshTimer();
    const enabled = document.getElementById('agentLogAutoRefreshEnabled');
    const secondsInput = document.getElementById('agentLogAutoRefreshSeconds');
    if (!enabled || !enabled.checked || !secondsInput) {
        return;
    }
    const seconds = Math.max(3, Number(secondsInput.value || 10));
    agentManageAutoRefreshTimer = setInterval(function() {
        const modalVisible = document.getElementById('agentManageModal').classList.contains('in');
        if (!modalVisible) {
            clearAgentLogAutoRefreshTimer();
            return;
        }
        refreshActiveLogTab();
    }, seconds * 1000);
}

function initAgentManageModalHandlers() {
    const modal = $('#agentManageModal');
    modal.on('hidden.bs.modal', function() {
        clearAgentLogAutoRefreshTimer();
        agentManageCurrentAgentUuid = '';
    });
    modal.find('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        const href = $(e.target).attr('href') || '';
        if (href === '#agentLogsTab') {
            agentManageCurrentLogKind = 'agent';
            refreshAgentLogTab();
        } else if (href === '#trayLogsTab') {
            agentManageCurrentLogKind = 'tray';
            refreshTrayLogTab();
        } else {
            agentManageCurrentLogKind = '';
        }
    });
}

function openAgentLogs(agentUuid) {
    agentManageCurrentAgentUuid = String(agentUuid || '');
    agentManageCurrentLogKind = 'agent';
    document.getElementById('agentManageTitle').textContent = '(' + agentManageCurrentAgentUuid + ')';
    $('#agentManageModal').modal('show');
    $('#agentManageModal ul.nav-tabs a[href="#agentLogsTab"]').tab('show');
    fetchAgentLogTail(agentManageCurrentAgentUuid, 'agent', 'agentLogContent', 'agentLogMeta');
    loadAgentDiagnostics(agentManageCurrentAgentUuid);
    updateAgentLogAutoRefresh();
}

function openTrayLogs(agentUuid) {
    agentManageCurrentAgentUuid = String(agentUuid || '');
    agentManageCurrentLogKind = 'tray';
    document.getElementById('agentManageTitle').textContent = '(' + agentManageCurrentAgentUuid + ')';
    $('#agentManageModal').modal('show');
    $('#agentManageModal ul.nav-tabs a[href="#trayLogsTab"]').tab('show');
    fetchAgentLogTail(agentManageCurrentAgentUuid, 'tray', 'trayLogContent', 'trayLogMeta');
    loadAgentDiagnostics(agentManageCurrentAgentUuid);
    updateAgentLogAutoRefresh();
}

initAgentManageModalHandlers();
</script>

