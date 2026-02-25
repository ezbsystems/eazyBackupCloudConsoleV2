<div class="min-h-screen bg-slate-950 text-gray-200">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-white">e3 Cloud Backup</h1>
                <p class="text-xs text-slate-400 mt-1">Manage your backup agents, enrollment tokens{if $isMspClient}, tenants, and users{/if}.</p>
            </div>
            <a href="index.php?m=cloudstorage&page=e3backup&view=agents" class="mt-4 sm:mt-0 px-4 py-2 rounded-md bg-sky-600 text-white text-sm font-semibold hover:bg-sky-500">
                Manage Agents
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Agents Card -->
            <a href="index.php?m=cloudstorage&page=e3backup&view=agents" class="group block rounded-xl border border-slate-800/80 bg-slate-900/70 p-5 hover:border-sky-500/50 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Active Agents</p>
                        <p class="text-3xl font-bold text-white mt-1">{$agentCount|default:0}</p>
                    </div>
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-sky-500/15 group-hover:bg-sky-500/25 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-sky-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-2">View and manage backup agents</p>
            </a>

            <!-- Enrollment Tokens Card -->
            <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="group block rounded-xl border border-slate-800/80 bg-slate-900/70 p-5 hover:border-emerald-500/50 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Active Tokens</p>
                        <p class="text-3xl font-bold text-white mt-1">{$tokenCount|default:0}</p>
                    </div>
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-emerald-500/15 group-hover:bg-emerald-500/25 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-2">Generate enrollment tokens for agents</p>
            </a>

            {if $isMspClient}
            <!-- Tenants Card (MSP Only) -->
            <a href="index.php?m=cloudstorage&page=e3backup&view=tenants" class="group block rounded-xl border border-slate-800/80 bg-slate-900/70 p-5 hover:border-violet-500/50 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Tenants</p>
                        <p class="text-3xl font-bold text-white mt-1">{$tenantCount|default:0}</p>
                    </div>
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-violet-500/15 group-hover:bg-violet-500/25 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-violet-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-2">Manage customer organizations</p>
            </a>

            <!-- Tenant Members Card (MSP Only) -->
            <a href="index.php?m=cloudstorage&page=e3backup&view=tenant_members" class="group block rounded-xl border border-slate-800/80 bg-slate-900/70 p-5 hover:border-amber-500/50 transition-colors">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Tenant Members</p>
                        <p class="text-3xl font-bold text-white mt-1">{$tenantUserCount|default:0}</p>
                    </div>
                    <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-amber-500/15 group-hover:bg-amber-500/25 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-amber-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-2">Manage members within tenants</p>
            </a>
            {/if}
        </div>

        <!-- Quick Actions -->
        <div class="rounded-xl border border-slate-800/80 bg-slate-900/70 p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Quick Actions</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="flex items-center gap-3 p-4 rounded-lg border border-slate-700 hover:border-emerald-500/50 hover:bg-slate-800/50 transition-colors">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-500/15">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-emerald-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-white">Generate Enrollment Token</p>
                        <p class="text-xs text-slate-400">Create a token for agent deployment</p>
                    </div>
                </a>

                <a href="/client_installer/e3-backup-agent-setup.exe" target="_blank" class="flex items-center gap-3 p-4 rounded-lg border border-slate-700 hover:border-sky-500/50 hover:bg-slate-800/50 transition-colors">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-sky-500/15">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-sky-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-white">Download Windows Agent</p>
                        <p class="text-xs text-slate-400">Get the backup agent installer</p>
                    </div>
                </a>

                {if $isMspClient}
                <a href="index.php?m=cloudstorage&page=e3backup&view=tenants" class="flex items-center gap-3 p-4 rounded-lg border border-slate-700 hover:border-violet-500/50 hover:bg-slate-800/50 transition-colors">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-violet-500/15">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-violet-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-white">Create Tenant</p>
                        <p class="text-xs text-slate-400">Add a new customer organization</p>
                    </div>
                </a>
                {/if}
            </div>
        </div>

        <!-- Live Jobs -->
        <div class="mt-8 rounded-xl border border-slate-800/80 bg-slate-900/70 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Live Jobs</h2>
                    <p class="text-xs text-slate-400 mt-1">Running, starting, or queued backups in progress.</p>
                </div>
                <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" class="text-xs px-3 py-1.5 rounded-md border border-slate-700 text-slate-200 hover:border-sky-500 hover:text-white transition">
                    View all jobs
                </a>
            </div>
            {if $liveRuns|@count > 0}
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-900/80 text-slate-300">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">Job</th>
                            {if $isMspClient}<th class="px-3 py-2 text-left font-medium">Tenant</th>{/if}
                            <th class="px-3 py-2 text-left font-medium">Agent</th>
                            <th class="px-3 py-2 text-left font-medium">Status</th>
                            <th class="px-3 py-2 text-left font-medium">Progress</th>
                            <th class="px-3 py-2 text-left font-medium">Started</th>
                            <th class="px-3 py-2 text-left font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        {foreach from=$liveRuns item=run}
                        <tr class="hover:bg-slate-800/40">
                            <td class="px-3 py-2 text-slate-200 font-semibold">{$run.job_name|default:'Job #'|cat:$run.job_id}</td>
                            {if $isMspClient}
                            <td class="px-3 py-2 text-slate-300">{$run.tenant_name|default:'Direct'}</td>
                            {/if}
                            <td class="px-3 py-2 text-slate-300">{$run.agent_hostname|default:'Agent #'|cat:$run.agent_id}</td>
                            <td class="px-3 py-2">
                                {assign var=status value=$run.status|default:'unknown'}
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold
                                    {if $status eq 'success'}bg-emerald-500/15 text-emerald-200
                                    {elseif $status eq 'failed'}bg-rose-500/15 text-rose-200
                                    {elseif $status eq 'running' || $status eq 'starting'}bg-sky-500/15 text-sky-200
                                    {elseif $status eq 'queued'}bg-amber-500/15 text-amber-200
                                    {else}bg-slate-700 text-slate-200{/if}">
                                    <span class="h-1.5 w-1.5 rounded-full
                                        {if $status eq 'success'}bg-emerald-400
                                        {elseif $status eq 'failed'}bg-rose-400
                                        {elseif $status eq 'running' || $status eq 'starting'}bg-sky-400
                                        {elseif $status eq 'queued'}bg-amber-400
                                        {else}bg-slate-500{/if}"></span>
                                    {$status|replace:'_':' '|capitalize}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-slate-200">
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
                            <td class="px-3 py-2 text-slate-300">
                                {if $run.started_at}
                                    {$run.started_at}
                                {else}
                                    -
                                {/if}
                            </td>
                            <td class="px-3 py-2">
                                <a href="index.php?m=cloudstorage&page=e3backup&view=live&run_id={$run.run_uuid|default:$run.id}"
                                   class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md bg-sky-600 text-white text-xs font-semibold hover:bg-sky-500">
                                    View Live
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
            {else}
            <div class="text-sm text-slate-400 bg-slate-900/60 border border-slate-800 rounded-lg px-4 py-3">
                No live jobs are currently running. Start a job to see live progress here.
            </div>
            {/if}
        </div>
    </div>
</div>

