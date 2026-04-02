{capture assign=ebE3Actions}
    <div class="flex flex-wrap items-center justify-end gap-2">
        <a href="index.php?m=cloudstorage&page=e3backup&view=agents" class="eb-btn eb-btn-primary eb-btn-sm">Manage Agents</a>
    </div>
{/capture}

{capture assign=ebE3Description}
    Manage your backup agents, enrollment tokens{if $isMspClient}, tenants, and users{/if}.
{/capture}

{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 17.25h9m-9-3h9m-9-3h9M5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5A2.25 2.25 0 0 1 18.75 19.5H5.25A2.25 2.25 0 0 1 3 17.25V6.75A2.25 2.25 0 0 1 5.25 4.5Z" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3Content}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <a href="index.php?m=cloudstorage&page=e3backup&view=agents" class="block eb-card-raised group h-full transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--eb-info-border)]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="eb-stat-label">Active Agents</div>
                    <div class="eb-stat-value !mt-2 !text-4xl">{$agentCount|default:0}</div>
                    <p class="mt-2 text-sm text-[var(--eb-text-muted)]">View and manage deployed backup agents.</p>
                </div>
                <span class="eb-icon-box eb-icon-box--info">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                    </svg>
                </span>
            </div>
        </a>

        <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="block eb-card-raised group h-full transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--eb-success-border)]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="eb-stat-label">Active Tokens</div>
                    <div class="eb-stat-value !mt-2 !text-4xl">{$tokenCount|default:0}</div>
                    <p class="mt-2 text-sm text-[var(--eb-text-muted)]">Generate and manage enrollment tokens for agent setup.</p>
                </div>
                <span class="eb-icon-box eb-icon-box--success">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                </span>
            </div>
        </a>

        {if $isMspClient}
        <a href="index.php?m=cloudstorage&page=e3backup&view=tenants" class="block eb-card-raised group h-full transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--eb-premium-border)]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="eb-stat-label">Tenants</div>
                    <div class="eb-stat-value !mt-2 !text-4xl">{$tenantCount|default:0}</div>
                    <p class="mt-2 text-sm text-[var(--eb-text-muted)]">Manage customer organizations and tenant routing.</p>
                </div>
                <span class="eb-icon-box eb-icon-box--premium">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                    </svg>
                </span>
            </div>
        </a>

        <a href="index.php?m=cloudstorage&page=e3backup&view=tenant_members" class="block eb-card-raised group h-full transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--eb-warning-border)]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="eb-stat-label">Tenant Members</div>
                    <div class="eb-stat-value !mt-2 !text-4xl">{$tenantUserCount|default:0}</div>
                    <p class="mt-2 text-sm text-[var(--eb-text-muted)]">Manage members assigned inside each tenant.</p>
                </div>
                <span class="eb-icon-box eb-icon-box--default">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </span>
            </div>
        </a>
        {/if}
    </div>

    <section class="mt-6 eb-card-raised !p-0 overflow-hidden">
        <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
            <div>
                <h2 class="eb-card-title !text-base">Quick Actions</h2>
                <p class="eb-card-subtitle">Common setup and navigation shortcuts for daily cloud backup operations.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
            <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="eb-card flex h-full items-start gap-4 !p-4 transition-colors hover:border-[var(--eb-success-border)]">
                <span class="eb-icon-box eb-icon-box--success eb-icon-box--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Generate Enrollment Token</div>
                    <p class="mt-1 text-sm text-[var(--eb-text-muted)]">Create a token for the next backup agent deployment.</p>
                </div>
            </a>

            <a href="/client_installer/e3-backup-agent-setup.exe" target="_blank" rel="noopener" class="eb-card flex h-full items-start gap-4 !p-4 transition-colors hover:border-[var(--eb-info-border)]">
                <span class="eb-icon-box eb-icon-box--info eb-icon-box--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Download Windows Agent</div>
                    <p class="mt-1 text-sm text-[var(--eb-text-muted)]">Get the installer for a workstation or server deployment.</p>
                </div>
            </a>

            {if $isMspClient}
            <a href="index.php?m=cloudstorage&page=e3backup&view=tenants" class="eb-card flex h-full items-start gap-4 !p-4 transition-colors hover:border-[var(--eb-premium-border)]">
                <span class="eb-icon-box eb-icon-box--premium eb-icon-box--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Create Tenant</div>
                    <p class="mt-1 text-sm text-[var(--eb-text-muted)]">Add a new customer organization and start assigning workloads.</p>
                </div>
            </a>
            {/if}
        </div>
    </section>

    <section class="mt-6 eb-card-raised !p-0 overflow-hidden">
        <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
            <div>
                <h2 class="eb-card-title !text-base">Live Jobs</h2>
                <p class="eb-card-subtitle">Running, starting, or queued backups currently in progress.</p>
            </div>
            <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="eb-btn eb-btn-secondary eb-btn-xs">View all jobs</a>
        </div>

        {if $liveRuns|@count > 0}
        <div class="p-6">
            <div class="eb-table-shell">
                <table class="eb-table">
                    <thead>
                        <tr>
                            <th>Job</th>
                            {if $isMspClient}<th>Tenant</th>{/if}
                            <th>Agent</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Started</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$liveRuns item=run}
                        {assign var=status value=$run.status|default:'unknown'}
                        <tr>
                            <td class="eb-table-primary">{$run.job_name|default:'Unnamed job'}</td>
                            {if $isMspClient}
                            <td>{$run.tenant_name|default:'Direct'}</td>
                            {/if}
                            <td>{$run.agent_hostname|default:$run.agent_uuid|default:'Agent unavailable'}</td>
                            <td>
                                {if $status eq 'success'}
                                <span class="eb-badge eb-badge--success">{$status|replace:'_':' '|capitalize}</span>
                                {elseif $status eq 'failed'}
                                <span class="eb-badge eb-badge--danger">{$status|replace:'_':' '|capitalize}</span>
                                {elseif $status eq 'running' || $status eq 'starting'}
                                <span class="eb-badge eb-badge--info">{$status|replace:'_':' '|capitalize}</span>
                                {elseif $status eq 'queued'}
                                <span class="eb-badge eb-badge--warning">{$status|replace:'_':' '|capitalize}</span>
                                {else}
                                <span class="eb-badge eb-badge--default">{$status|replace:'_':' '|capitalize}</span>
                                {/if}
                            </td>
                            <td class="eb-table-primary">
                                {if $run.progress_pct}
                                    {$run.progress_pct|string_format:"%.1f"}%
                                {elseif $run.bytes_processed}
                                    {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_processed)}
                                {elseif $run.bytes_transferred}
                                    {\WHMCS\Module\Addon\CloudStorage\Client\HelperController::formatSizeUnits($run.bytes_transferred)}
                                {else}
                                    -
                                {/if}
                            </td>
                            <td>
                                {if $run.started_at}
                                    {$run.started_at}
                                {else}
                                    -
                                {/if}
                            </td>
                            <td>
                                <a href="index.php?m=cloudstorage&page=e3backup&view=live&run_id={$run.run_id}" class="eb-btn eb-btn-primary eb-btn-xs">
                                    View Live
                                </a>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
        {else}
        <div class="p-6">
            <div class="eb-app-empty text-sm">
                No live jobs are currently running. Start a job to see live progress here.
            </div>
        </div>
        {/if}
    </section>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='dashboard'
    ebE3Title='e3 Cloud Backup'
    ebE3Description=$ebE3Description
    ebE3Icon=$ebE3Icon
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}
