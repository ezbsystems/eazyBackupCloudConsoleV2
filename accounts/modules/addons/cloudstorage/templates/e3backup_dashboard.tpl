{capture assign=ebE3Actions}
    <div class="flex flex-wrap items-center justify-end gap-2">
        <a href="index.php?m=cloudstorage&page=e3backup&view=agents" class="eb-btn eb-btn-primary eb-btn-sm">Manage Agents</a>
    </div>
{/capture}

{capture assign=ebE3Description}
    Monitor backup health, agents, and recent activity{if $isMspClient} across your tenants{/if}.
{/capture}

{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 17.25h9m-9-3h9m-9-3h9M5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5A2.25 2.25 0 0 1 18.75 19.5H5.25A2.25 2.25 0 0 1 3 17.25V6.75A2.25 2.25 0 0 1 5.25 4.5Z" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3Content}

    {* ── Row 1: Primary KPI Cards ── *}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

        {* Backup Status (24h) *}
        {assign var=statusColor value='success'}
        {if $status24h.failed > 0}
            {assign var=statusColor value='danger'}
        {elseif $status24h.warning > 0}
            {assign var=statusColor value='warning'}
        {elseif $status24hTotal == 0}
            {assign var=statusColor value='default'}
        {/if}
        <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="block eb-card-raised group h-full transition-all duration-200 hover:-translate-y-0.5 {if $statusColor == 'success'}hover:border-[var(--eb-success-border)]{elseif $statusColor == 'danger'}hover:border-[var(--eb-danger-border)]{elseif $statusColor == 'warning'}hover:border-[var(--eb-warning-border)]{else}hover:border-[var(--eb-border-default)]{/if}">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="eb-stat-label">Backup Status (24h)</div>
                    <div class="eb-stat-value !mt-2 !text-4xl">{$status24h.success|default:0}</div>
                    <p class="mt-2 text-sm text-[var(--eb-text-muted)]">
                        {if $status24hTotal == 0}
                            No backups in the last 24 hours.
                        {elseif $status24h.failed > 0}
                            {$status24h.failed} failed &middot; {$status24h.success} succeeded
                        {elseif $status24h.warning > 0}
                            {$status24h.warning} warning{if $status24h.warning != 1}s{/if} &middot; {$status24h.success} succeeded
                        {else}
                            All {$status24hTotal} backup{if $status24hTotal != 1}s{/if} succeeded.
                        {/if}
                    </p>
                </div>
                <span class="eb-icon-box eb-icon-box--{$statusColor}">
                    {if $statusColor == 'success'}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {elseif $statusColor == 'danger'}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    {elseif $statusColor == 'warning'}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    {else}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {/if}
                </span>
            </div>
        </a>

        {* Active Agents *}
        {assign var=agentColor value='info'}
        {if $agentCount == 0}
            {assign var=agentColor value='default'}
        {elseif $offlineAgents > 0 && $onlineAgents > 0}
            {assign var=agentColor value='warning'}
        {elseif $offlineAgents > 0 && $onlineAgents == 0}
            {assign var=agentColor value='danger'}
        {/if}
        <a href="index.php?m=cloudstorage&page=e3backup&view=agents" class="block eb-card-raised group h-full transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--eb-info-border)]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="eb-stat-label">Active Agents</div>
                    <div class="eb-stat-value !mt-2 !text-4xl">{$agentCount|default:0}</div>
                    <p class="mt-2 text-sm text-[var(--eb-text-muted)]">
                        {if $agentCount == 0}
                            No agents deployed yet.
                        {else}
                            <span style="color:var(--eb-success-text)">{$onlineAgents} online</span>
                            {if $offlineAgents > 0}
                                &middot; <span style="color:var(--eb-danger-text)">{$offlineAgents} offline</span>
                            {/if}
                        {/if}
                    </p>
                </div>
                <span class="eb-icon-box eb-icon-box--{$agentColor}">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                    </svg>
                </span>
            </div>
        </a>

        {* Storage Used *}
        <div class="eb-card-raised h-full">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="eb-stat-label">Storage Used</div>
                    <div class="eb-stat-value !mt-2 !text-4xl">{$storageFormatted|default:'0 B'}</div>
                    <p class="mt-2 text-sm text-[var(--eb-text-muted)]">Total across latest successful runs.</p>
                </div>
                <span class="eb-icon-box eb-icon-box--premium">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                    </svg>
                </span>
            </div>
        </div>

        {* Active Jobs *}
        <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="block eb-card-raised group h-full transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--eb-success-border)]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="eb-stat-label">Active Jobs</div>
                    <div class="eb-stat-value !mt-2 !text-4xl">{$activeJobCount|default:0}</div>
                    <p class="mt-2 text-sm text-[var(--eb-text-muted)]">
                        Configured backup jobs.
                        {if $liveRuns|@count > 0}
                            <span style="color:var(--eb-info-text)">{$liveRuns|@count} running now</span>
                        {/if}
                    </p>
                </div>
                <span class="eb-icon-box eb-icon-box--success">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6.878V6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v.878m-12 0c.235-.083.487-.128.75-.128h10.5c.263 0 .515.045.75.128m-12 0A2.25 2.25 0 0 0 4.5 9v.878m13.5-3A2.25 2.25 0 0 1 19.5 9v.878m0 0a2.246 2.246 0 0 0-.75-.128H5.25c-.263 0-.515.045-.75.128m15 0A2.25 2.25 0 0 1 21 12v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6c0-1.007.66-1.862 1.572-2.147" />
                    </svg>
                </span>
            </div>
        </a>
    </div>

    {* ── Row 1b: Secondary stats for MSPs ── *}
    {if $isMspClient}
    <div class="grid grid-cols-1 gap-4 mt-4 sm:grid-cols-3">
        <a href="index.php?m=cloudstorage&page=e3backup&view=tenants" class="block eb-card group h-full transition-colors hover:border-[var(--eb-premium-border)]">
            <div class="flex items-center gap-3">
                <span class="eb-icon-box eb-icon-box--premium eb-icon-box--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="eb-stat-label">Tenants</div>
                    <div class="text-lg font-semibold" style="color:var(--eb-text-primary)">{$tenantCount|default:0}</div>
                </div>
            </div>
        </a>
        <a href="index.php?m=cloudstorage&page=e3backup&view=tenant_members" class="block eb-card group h-full transition-colors hover:border-[var(--eb-warning-border)]">
            <div class="flex items-center gap-3">
                <span class="eb-icon-box eb-icon-box--default eb-icon-box--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="eb-stat-label">Tenant Members</div>
                    <div class="text-lg font-semibold" style="color:var(--eb-text-primary)">{$tenantUserCount|default:0}</div>
                </div>
            </div>
        </a>
        <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="block eb-card group h-full transition-colors hover:border-[var(--eb-success-border)]">
            <div class="flex items-center gap-3">
                <span class="eb-icon-box eb-icon-box--success eb-icon-box--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="eb-stat-label">Active Tokens</div>
                    <div class="text-lg font-semibold" style="color:var(--eb-text-primary)">{$tokenCount|default:0}</div>
                </div>
            </div>
        </a>
    </div>
    {/if}

    {* ── Row 2: Operational Health (two-column) ── *}
    <div class="grid grid-cols-1 gap-4 mt-6 lg:grid-cols-2">

        {* Left: Job Status Summary (24h Donut) *}
        <section class="eb-card-raised !p-0 overflow-hidden">
            <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
                <div>
                    <h2 class="eb-card-title !text-base">Job Status Summary</h2>
                    <p class="eb-card-subtitle">Backup run outcomes over the last 24 hours.</p>
                </div>
            </div>
            <div class="p-6">
                {if $status24hTotal > 0}
                <div id="e3-status-donut" style="height:200px;width:100%;"></div>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm" style="color:var(--eb-text-secondary)">
                    <span class="inline-flex items-center gap-2">
                        <span style="width:12px;height:12px;border-radius:9999px;background:var(--eb-success-bg);border:2px solid var(--eb-success-text);display:inline-block;"></span>
                        Success: {$status24h.success}
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span style="width:12px;height:12px;border-radius:9999px;background:var(--eb-danger-bg);border:2px solid var(--eb-danger-text);display:inline-block;"></span>
                        Failed: {$status24h.failed}
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span style="width:12px;height:12px;border-radius:9999px;background:var(--eb-warning-bg);border:2px solid var(--eb-warning-text);display:inline-block;"></span>
                        Warning: {$status24h.warning}
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span style="width:12px;height:12px;border-radius:9999px;background:var(--eb-info-bg);border:2px solid var(--eb-info-text);display:inline-block;"></span>
                        Running: {$status24h.running}
                    </span>
                    {if $status24h.cancelled > 0}
                    <span class="inline-flex items-center gap-2">
                        <span style="width:12px;height:12px;border-radius:9999px;border:2px solid var(--eb-text-muted);display:inline-block;"></span>
                        Cancelled: {$status24h.cancelled}
                    </span>
                    {/if}
                </div>
                {else}
                <div class="eb-app-empty py-8">
                    <div class="eb-app-empty-title">No backup runs yet</div>
                    <p class="eb-app-empty-copy">Create a backup job and run it to see status here.</p>
                </div>
                {/if}
            </div>
        </section>

        {* Right: Agent Health *}
        <section class="eb-card-raised !p-0 overflow-hidden">
            <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
                <div>
                    <h2 class="eb-card-title !text-base">Agent Health</h2>
                    <p class="eb-card-subtitle">Online/offline status of deployed agents.</p>
                </div>
                <a href="index.php?m=cloudstorage&page=e3backup&view=agents" class="eb-btn eb-btn-secondary eb-btn-xs">View all</a>
            </div>
            <div class="p-6">
                {if $agentCount > 0}
                <div class="flex items-center gap-6 mb-5">
                    <div class="flex items-center gap-2">
                        <span style="width:10px;height:10px;border-radius:9999px;background:var(--eb-success-text);display:inline-block;"></span>
                        <span class="text-sm font-medium" style="color:var(--eb-text-primary)">{$onlineAgents} Online</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span style="width:10px;height:10px;border-radius:9999px;background:var(--eb-danger-text);display:inline-block;"></span>
                        <span class="text-sm font-medium" style="color:var(--eb-text-primary)">{$offlineAgents} Offline</span>
                    </div>
                </div>

                {if $agentCount > 0}
                <div class="w-full rounded-full h-2 overflow-hidden" style="background:var(--eb-bg-tertiary)">
                    {if $onlineAgents > 0}
                    <div class="h-full rounded-full" style="background:var(--eb-success-text);width:{($onlineAgents / $agentCount * 100)|string_format:'%.0f'}%"></div>
                    {/if}
                </div>
                {/if}

                {if $recentAgents|@count > 0}
                <div class="mt-5 space-y-3">
                    {foreach from=$recentAgents item=agent}
                    <div class="flex items-center justify-between gap-3 py-2 border-b" style="border-color:var(--eb-border-subtle)">
                        <div class="flex items-center gap-3 min-w-0">
                            <span style="width:8px;height:8px;border-radius:9999px;display:inline-block;flex-shrink:0;background:{if $agent.is_online}var(--eb-success-text){else}var(--eb-danger-text){/if};"></span>
                            <div class="min-w-0">
                                <div class="text-sm font-medium truncate" style="color:var(--eb-text-primary)">{$agent.hostname|default:'Unknown'}</div>
                                {if $agent.agent_os}
                                <div class="text-xs" style="color:var(--eb-text-muted)">{$agent.agent_os}{if $agent.agent_version} &middot; v{$agent.agent_version}{/if}</div>
                                {/if}
                            </div>
                        </div>
                        <div class="text-xs whitespace-nowrap" style="color:var(--eb-text-muted)">
                            {if $agent.is_online}
                                <span class="eb-badge eb-badge--success eb-badge--dot">Online</span>
                            {else}
                                <span class="eb-badge eb-badge--danger eb-badge--dot">Offline</span>
                            {/if}
                        </div>
                    </div>
                    {/foreach}
                </div>
                {/if}
                {else}
                <div class="eb-app-empty py-8">
                    <div class="eb-app-empty-title">No agents deployed</div>
                    <p class="eb-app-empty-copy">Download and install the e3 Backup Agent to get started.</p>
                </div>
                {/if}
            </div>
        </section>
    </div>

    {* ── Row 3: Getting Started / Quick Actions ── *}
    <section class="mt-6 eb-card-raised !p-0 overflow-hidden">
        {if $agentCount == 0 && $userCount == 0}
        {* ── Onboarding: Getting Started ── *}
        <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
            <div>
                <h2 class="eb-card-title !text-base">Getting Started</h2>
                <p class="eb-card-subtitle">Follow these steps to set up your first backup.</p>
            </div>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-card flex items-start gap-4 !p-4 transition-colors hover:border-[var(--eb-info-border)]">
                    <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold" style="background:var(--eb-info-bg);color:var(--eb-info-text);">1</span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold" style="color:var(--eb-text-primary)">Create a Backup User</div>
                        <p class="mt-1 text-sm" style="color:var(--eb-text-muted)">Add your first user account for agent enrollment.</p>
                    </div>
                </a>

                <div class="eb-card flex items-start gap-4 !p-4 transition-colors hover:border-[var(--eb-success-border)] cursor-pointer" onclick="window.dispatchEvent(new Event('open-e3-download-flyout'))">
                    <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold" style="background:var(--eb-success-bg);color:var(--eb-success-text);">2</span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold" style="color:var(--eb-text-primary)">Download &amp; Install Agent</div>
                        <p class="mt-1 text-sm" style="color:var(--eb-text-muted)">Install the e3 Backup Agent on a workstation or server.</p>
                    </div>
                </div>

                <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="eb-card flex items-start gap-4 !p-4 transition-colors hover:border-[var(--eb-premium-border)]">
                    <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold" style="background:var(--eb-premium-bg);color:var(--eb-premium-text);">3</span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold" style="color:var(--eb-text-primary)">Create Your First Backup Job</div>
                        <p class="mt-1 text-sm" style="color:var(--eb-text-muted)">Configure what to back up and set a schedule.</p>
                    </div>
                </a>
            </div>
        </div>

        {else}
        {* ── Active User: Quick Actions ── *}
        <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
            <div>
                <h2 class="eb-card-title !text-base">Quick Actions</h2>
                <p class="eb-card-subtitle">Common shortcuts for daily backup operations.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-4 p-6 md:grid-cols-2 xl:grid-cols-{if $isMspClient}4{else}3{/if}">
            <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="eb-card flex h-full items-start gap-4 !p-4 transition-colors hover:border-[var(--eb-success-border)]">
                <span class="eb-icon-box eb-icon-box--success eb-icon-box--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="text-sm font-semibold" style="color:var(--eb-text-primary)">Create Backup Job</div>
                    <p class="mt-1 text-sm" style="color:var(--eb-text-muted)">Configure a new backup job for an agent.</p>
                </div>
            </a>

            <div class="eb-card flex h-full items-start gap-4 !p-4 transition-colors hover:border-[var(--eb-info-border)] cursor-pointer" onclick="window.dispatchEvent(new Event('open-e3-download-flyout'))">
                <span class="eb-icon-box eb-icon-box--info eb-icon-box--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="text-sm font-semibold" style="color:var(--eb-text-primary)">Download Agent</div>
                    <p class="mt-1 text-sm" style="color:var(--eb-text-muted)">Get the installer for a new deployment.</p>
                </div>
            </div>

            <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="eb-card flex h-full items-start gap-4 !p-4 transition-colors hover:border-[var(--eb-warning-border)]">
                <span class="eb-icon-box eb-icon-box--warning eb-icon-box--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="text-sm font-semibold" style="color:var(--eb-text-primary)">View All Jobs</div>
                    <p class="mt-1 text-sm" style="color:var(--eb-text-muted)">See status and history for all configured jobs.</p>
                </div>
            </a>

            {if $isMspClient}
            <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-card flex h-full items-start gap-4 !p-4 transition-colors hover:border-[var(--eb-premium-border)]">
                <span class="eb-icon-box eb-icon-box--premium eb-icon-box--sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <div class="text-sm font-semibold" style="color:var(--eb-text-primary)">Create User</div>
                    <p class="mt-1 text-sm" style="color:var(--eb-text-muted)">Add a new backup user for agent enrollment.</p>
                </div>
            </a>
            {/if}
        </div>
        {/if}
    </section>

    {* ── Row 4: Recent Backup Activity + Live Jobs ── *}
    <section class="mt-6 eb-card-raised !p-0 overflow-hidden" x-data="{ldelim} activityTab: '{if $liveRuns|@count > 0}live{else}recent{/if}' {rdelim}">
        <div class="eb-card-header eb-card-header--divided !-mx-0 !-mt-0 !mb-0 !px-6 !py-5">
            <div>
                <h2 class="eb-card-title !text-base">Backup Activity</h2>
                <p class="eb-card-subtitle">Recent and live backup operations.</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    class="eb-btn eb-btn-xs"
                    :class="activityTab === 'recent' ? 'eb-btn-primary' : 'eb-btn-secondary'"
                    @click="activityTab = 'recent'"
                >Recent</button>
                <button
                    class="eb-btn eb-btn-xs"
                    :class="activityTab === 'live' ? 'eb-btn-primary' : 'eb-btn-secondary'"
                    @click="activityTab = 'live'"
                >
                    Live
                    {if $liveRuns|@count > 0}
                        <span class="eb-badge eb-badge--info ml-1" style="font-size:10px;padding:1px 5px;">{$liveRuns|@count}</span>
                    {/if}
                </button>
            </div>
        </div>

        {* Recent Runs Tab *}
        <div x-show="activityTab === 'recent'" x-transition>
            {if $recentRuns|@count > 0}
            <div class="p-6">
                <div class="eb-table-shell">
                    <table class="eb-table">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Agent</th>
                                {if $isMspClient}<th>Tenant</th>{/if}
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Size</th>
                                <th>Finished</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$recentRuns item=run}
                            {assign var=status value=$run.status|default:'unknown'}
                            <tr>
                                <td class="eb-table-primary">{$run.job_name|default:'Unnamed job'}</td>
                                <td>{$run.agent_hostname|default:'—'}</td>
                                {if $isMspClient}
                                <td>{$run.tenant_name|default:'Direct'}</td>
                                {/if}
                                <td>
                                    {if $status eq 'success'}
                                    <span class="eb-badge eb-badge--success">{$status|capitalize}</span>
                                    {elseif $status eq 'failed' || $status eq 'error'}
                                    <span class="eb-badge eb-badge--danger">{$status|capitalize}</span>
                                    {elseif $status eq 'warning'}
                                    <span class="eb-badge eb-badge--warning">{$status|capitalize}</span>
                                    {elseif $status eq 'cancelled'}
                                    <span class="eb-badge eb-badge--default">{$status|capitalize}</span>
                                    {else}
                                    <span class="eb-badge eb-badge--default">{$status|replace:'_':' '|capitalize}</span>
                                    {/if}
                                </td>
                                <td class="eb-table-mono">{$run.duration|default:'—'}</td>
                                <td class="eb-table-mono">{$run.size_formatted|default:'—'}</td>
                                <td>{$run.finished_at|default:'—'}</td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
            {else}
            <div class="p-6">
                <div class="eb-app-empty py-8">
                    <div class="eb-app-empty-title">No recent backup activity</div>
                    <p class="eb-app-empty-copy">Completed backup runs will appear here.</p>
                </div>
            </div>
            {/if}
        </div>

        {* Live Jobs Tab *}
        <div x-show="activityTab === 'live'" x-transition>
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
                                <td>{$run.agent_hostname|default:'—'}</td>
                                <td>
                                    {if $status eq 'running' || $status eq 'starting'}
                                    <span class="eb-badge eb-badge--info">{$status|capitalize}</span>
                                    {elseif $status eq 'queued'}
                                    <span class="eb-badge eb-badge--warning">{$status|capitalize}</span>
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
                                        —
                                    {/if}
                                </td>
                                <td>{$run.started_at|default:'—'}</td>
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
                <div class="eb-app-empty py-8">
                    <div class="eb-app-empty-title">No live jobs running</div>
                    <p class="eb-app-empty-copy">Active backup operations will appear here in real time.</p>
                </div>
            </div>
            {/if}
        </div>
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

{* ── Billboard.js Donut Chart (24h Status) ── *}
{if $status24hTotal > 0}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/billboard.js/dist/billboard.min.css" />
<script src="https://cdn.jsdelivr.net/npm/d3@6/dist/d3.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/billboard.js/dist/billboard.min.js"></script>
<script>
var e3StatusData = {
    success: {$status24h.success|default:0},
    failed:  {$status24h.failed|default:0},
    warning: {$status24h.warning|default:0},
    running: {$status24h.running|default:0},
    cancelled: {$status24h.cancelled|default:0}
};
</script>
{literal}
<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('e3-status-donut');
    if (!el || typeof bb === 'undefined') return;

    var data = window.e3StatusData || {};
    var columns = [];
    var colors = {};
    var colorMap = {
        success:   '#22c55e',
        failed:    '#ef4444',
        warning:   '#f59e0b',
        running:   '#3b82f6',
        cancelled: '#94a3b8'
    };

    Object.keys(data).forEach(function(key) {
        if (data[key] > 0) {
            var label = key.charAt(0).toUpperCase() + key.slice(1);
            columns.push([label, data[key]]);
            colors[label] = colorMap[key];
        }
    });

    if (columns.length === 0) return;

    bb.generate({
        bindto: '#e3-status-donut',
        data: {
            columns: columns,
            type: 'donut',
            colors: colors
        },
        donut: {
            title: data.success + '/' + (data.success + data.failed + data.warning + data.running + data.cancelled),
            label: {
                show: true,
                format: function(v) { return v; }
            },
            width: 32
        },
        legend: { show: false },
        size: { height: 200 },
        transition: { duration: 400 }
    });
});
</script>
{/literal}
<style>
#e3-status-donut .bb-chart-arcs path {
    stroke: var(--eb-bg-primary, #0f172a) !important;
    stroke-width: 2px !important;
}
#e3-status-donut .bb-chart-arcs text {
    fill: var(--eb-text-primary, #f8fafc) !important;
    font-size: 12px !important;
    font-weight: 500 !important;
}
#e3-status-donut .bb-tooltip-container .bb-tooltip {
    border-collapse: separate;
    border-spacing: 0;
    background: var(--eb-bg-secondary, #1e293b) !important;
    color: var(--eb-text-primary, #f8fafc) !important;
    border: 1px solid var(--eb-border-subtle, #334155) !important;
    border-radius: 8px;
    overflow: hidden;
}
#e3-status-donut .bb-tooltip-container .bb-tooltip th,
#e3-status-donut .bb-tooltip-container .bb-tooltip td {
    background: var(--eb-bg-secondary, #1e293b) !important;
    color: var(--eb-text-primary, #f8fafc) !important;
    border-color: var(--eb-border-subtle, #334155) !important;
}
</style>
{/if}
