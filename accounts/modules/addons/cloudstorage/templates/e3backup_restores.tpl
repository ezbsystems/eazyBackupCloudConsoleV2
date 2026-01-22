<div class="min-h-screen bg-slate-950 text-gray-200" x-data="restoresApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        {assign var="activeNav" value="restores"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Restores</span>
                </div>
                <h1 class="text-2xl font-semibold text-white">Restore Points</h1>
                <p class="text-xs text-slate-400 mt-1">Restore from snapshots across all agents, even after jobs are deleted.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 space-y-3">
            <div class="flex flex-col lg:flex-row gap-3 items-start lg:items-center">
                {if $isMspClient}
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-400">Tenant:</label>
                    <div x-data="{ isOpen: false }" class="relative" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            <span class="truncate max-w-[14rem]" x-text="tenantLabel()"></span>
                            <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="isOpen"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-0 mt-2 w-72 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                             style="display: none;">
                            <div class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-800">
                                Select tenant
                            </div>
                            <div class="px-4 py-2 border-b border-slate-800">
                                <input type="text" x-model="tenantSearch" placeholder="Search tenants"
                                       class="w-full rounded-md bg-slate-950 border border-slate-700 px-3 py-2 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            </div>
                            <div class="py-1 max-h-72 overflow-auto">
                                <button type="button"
                                        class="w-full px-4 py-2 text-left text-sm transition"
                                        :class="tenantFilter === '' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                        @click="selectTenant(''); isOpen=false;">
                                    All Tenants
                                </button>
                                <button type="button"
                                        class="w-full px-4 py-2 text-left text-sm transition"
                                        :class="tenantFilter === 'direct' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                        @click="selectTenant('direct'); isOpen=false;">
                                    Direct (No Tenant)
                                </button>
                                <template x-for="tenant in filteredTenants" :key="tenant.id">
                                    <button type="button"
                                            class="w-full px-4 py-2 text-left text-sm transition"
                                            :class="String(tenantFilter) === String(tenant.id) ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                            @click="selectTenant(String(tenant.id)); isOpen=false;">
                                        <span class="truncate" x-text="tenant.name"></span>
                                    </button>
                                </template>
                                <template x-if="filteredTenants.length === 0">
                                    <div class="px-4 py-2 text-sm text-slate-400">No tenants found</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                {/if}
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-400">Agent:</label>
                    <div x-data="{ isOpen: false }" class="relative" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            <span class="truncate max-w-[14rem]" x-text="agentLabel()"></span>
                            <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="isOpen"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-0 mt-2 w-72 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                             style="display: none;">
                            <div class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-800">
                                Select agent
                            </div>
                            <div class="px-4 py-2 border-b border-slate-800">
                                <input type="text" x-model="agentSearch" placeholder="Search agents"
                                       class="w-full rounded-md bg-slate-950 border border-slate-700 px-3 py-2 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                            </div>
                            <div class="py-1 max-h-72 overflow-auto">
                                <button type="button"
                                        class="w-full px-4 py-2 text-left text-sm transition"
                                        :class="agentFilter === '' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                        @click="selectAgent(''); isOpen=false;">
                                    All Agents
                                </button>
                                <template x-for="agent in filteredAgents" :key="agent.id">
                                    <button type="button"
                                            class="w-full px-4 py-2 text-left text-sm transition"
                                            :class="String(agentFilter) === String(agent.id) ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                            @click="selectAgent(String(agent.id)); isOpen=false;">
                                        <span class="truncate" x-text="agent.hostname || ('Agent #' + agent.id)"></span>
                                    </button>
                                </template>
                                <template x-if="filteredAgents.length === 0">
                                    <div class="px-4 py-2 text-sm text-slate-400">No agents found</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2">
                    <label class="text-sm text-slate-400">Date:</label>
                    <div class="flex items-center gap-2">
                        <input type="date" x-model="dateFrom" @change="onDateChange()"
                               class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
                        <span class="text-slate-500 text-xs">to</span>
                        <input type="date" x-model="dateTo" @change="onDateChange()"
                               class="rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500">
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="setQuickRange(7)"
                                class="text-xs px-2 py-1 rounded border border-slate-700 bg-slate-900/70 text-slate-300 hover:bg-slate-800">
                            7d
                        </button>
                        <button type="button" @click="setQuickRange(30)"
                                class="text-xs px-2 py-1 rounded border border-slate-700 bg-slate-900/70 text-slate-300 hover:bg-slate-800">
                            30d
                        </button>
                        <button type="button" @click="setQuickRange(90)"
                                class="text-xs px-2 py-1 rounded border border-slate-700 bg-slate-900/70 text-slate-300 hover:bg-slate-800">
                            90d
                        </button>
                        <button type="button" @click="clearDateRange()"
                                class="text-xs px-2 py-1 rounded border border-slate-700 bg-slate-900/70 text-slate-300 hover:bg-slate-800">
                            Clear
                        </button>
                    </div>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                <div class="flex items-center gap-2 sm:ml-auto w-full">
                    <input type="text" placeholder="Search restore points" x-model="searchQuery"
                           class="w-full sm:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                </div>
            </div>
        </div>

        <!-- Restore Points Table -->
        <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Snapshot</th>
                        {if $isMspClient}<th class="px-4 py-3 text-left font-medium">Tenant</th>{/if}
                        <th class="px-4 py-3 text-left font-medium">Agent</th>
                        <th class="px-4 py-3 text-left font-medium">Engine</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-left font-medium">Source</th>
                        <th class="px-4 py-3 text-left font-medium">Destination</th>
                        <th class="px-4 py-3 text-left font-medium">Completed</th>
                        <th class="px-4 py-3 text-left font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <template x-if="loading">
                        <tr>
                            <td :colspan="{if $isMspClient}9{else}8{/if}" class="px-4 py-8 text-center text-slate-400">
                                <svg class="animate-spin h-6 w-6 mx-auto text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && needsFilter">
                        <tr>
                            <td :colspan="{if $isMspClient}9{else}8{/if}" class="px-4 py-8 text-center text-slate-400">
                                Select a tenant, agent, date range, or search to load restore points.
                            </td>
                        </tr>
                    </template>
                    <template x-if="!loading && !needsFilter && restorePoints.length === 0">
                        <tr>
                            <td :colspan="{if $isMspClient}9{else}8{/if}" class="px-4 py-8 text-center text-slate-400">
                                No restore points found.
                            </td>
                        </tr>
                    </template>
                    <template x-for="point in restorePoints" :key="point.id">
                        <tr class="hover:bg-slate-800/50">
                            <td class="px-4 py-3">
                                <div class="text-slate-100 font-medium" x-text="point.job_name || ('Job #' + (point.job_id || '—'))"></div>
                                <div class="text-xs text-slate-500" x-text="point.manifest_id || 'No manifest'"></div>
                                <div class="text-xs text-slate-400" x-show="point.hyperv_vm_name">VM: <span x-text="point.hyperv_vm_name"></span></div>
                            </td>
                            {if $isMspClient}
                            <td class="px-4 py-3 text-slate-300" x-text="point.tenant_name || 'Direct'"></td>
                            {/if}
                            <td class="px-4 py-3 text-slate-300" x-text="point.agent_hostname || ('Agent #' + (point.agent_id || '—'))"></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="{ 'bg-sky-500/15 text-sky-200': point.engine === 'kopia', 'bg-purple-500/15 text-purple-200': point.engine === 'disk_image', 'bg-amber-500/15 text-amber-200': point.engine === 'hyperv', 'bg-slate-700 text-slate-300': !point.engine }"
                                      x-text="point.engine || 'unknown'"></span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                      :class="point.status === 'success' ? 'bg-emerald-500/15 text-emerald-200' : (point.status === 'warning' ? 'bg-amber-500/15 text-amber-200' : 'bg-slate-700 text-slate-300')"
                                      x-text="point.status || 'unknown'"></span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                <div class="text-xs" x-text="point.source_display_name || point.source_type || '—'"></div>
                                <div class="text-xs text-slate-500" x-text="point.source_path || ''"></div>
                            </td>
                            <td class="px-4 py-3 text-slate-300">
                                <div class="text-xs" x-text="point.dest_type || 's3'"></div>
                                <div class="text-xs text-slate-500" x-text="point.dest_bucket_name || point.dest_prefix || point.dest_local_path || ''"></div>
                            </td>
                            <td class="px-4 py-3 text-slate-300" x-text="point.finished_at || point.created_at || '—'"></td>
                            <td class="px-4 py-3">
                                <template x-if="point.hyperv_backup_point_id">
                                    <a :href="'index.php?m=cloudstorage&page=e3backup&view=hyperv_restore&vm_id=' + point.hyperv_vm_id"
                                       class="text-xs px-2 py-1 rounded bg-sky-600/20 border border-sky-500/40 text-sky-300 hover:bg-sky-600/30 hover:border-sky-400 transition">
                                        Hyper-V Restore
                                    </a>
                                </template>
                                <template x-if="!point.hyperv_backup_point_id">
                                    <button @click="openRestoreModal(point)"
                                            class="text-xs px-2 py-1 rounded bg-sky-600/20 border border-sky-500/40 text-sky-300 hover:bg-sky-600/30 hover:border-sky-400 transition">
                                        Restore
                                    </button>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex justify-center" x-show="hasMore && !loading" style="display: none;">
            <button type="button" @click="loadRestorePoints(false)"
                    class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800">
                Load more
            </button>
        </div>
        </div>
    </div>

    <!-- Restore Wizard Modal -->
    <div id="restorePointModal" class="fixed inset-0 z-[2100] hidden">
        <div class="absolute inset-0 bg-black/75" onclick="closeRestorePointModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-3xl max-h-[85vh] rounded-2xl bg-slate-950 border border-slate-800 shadow-2xl flex flex-col">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between shrink-0">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Restore Snapshot</h3>
                        <p class="text-xs text-slate-400 mt-1">Restore from a saved restore point.</p>
                    </div>
                    <button class="text-slate-400 hover:text-white" onclick="closeRestorePointModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4 flex-1 overflow-y-auto scrollbar-thin-dark">
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">
                        <span class="px-2 py-1 rounded-full border border-slate-700 bg-slate-900" id="restorePointStepLabel">Step 1 of 4</span>
                        <span class="text-slate-300" id="restorePointStepTitle">Confirm Snapshot</span>
                    </div>

                    <div class="space-y-6">
                        <!-- Step 1 -->
                        <div class="restore-point-step" data-step="1">
                            <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-3">
                                <div class="text-sm text-slate-200 font-semibold" id="restorePointJobName">Selected restore point</div>
                                <div class="text-xs text-slate-400 mt-1" id="restorePointManifest"></div>
                                <div class="text-xs text-slate-400 mt-1" id="restorePointAgent"></div>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="restore-point-step hidden" data-step="2">
                            <label class="block text-sm font-medium text-slate-200 mb-2">Destination Agent</label>
                            <p class="text-xs text-slate-400 mb-3">Select the computer where the data should be restored.</p>
                            <div class="space-y-3">
                                <select id="restorePointTargetAgent" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100">
                                    <option value="">Select an agent</option>
                                </select>
                                <p id="restorePointAgentHint" class="text-xs text-amber-300 hidden"></p>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="restore-point-step hidden" data-step="3">
                            <label class="block text-sm font-medium text-slate-200 mb-2">Select Items (Optional)</label>
                            <p class="text-xs text-slate-400 mb-3">Choose specific files or folders to restore. Leave empty to restore the full snapshot.</p>
                            <div class="rounded-xl border border-slate-800 bg-slate-900 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-2 bg-slate-800/60 border-b border-slate-800">
                                    <div id="restorePointSnapshotBreadcrumbs" class="flex items-center gap-1 text-xs text-slate-300"></div>
                                    <div id="restorePointSnapshotSelection" class="text-xs text-slate-500">0 selected</div>
                                </div>
                                <div id="restorePointSnapshotStatus" class="px-4 py-2 text-xs text-slate-400 hidden"></div>
                                <div class="h-[360px] overflow-y-auto scrollbar-thin-dark">
                                    <div id="restorePointSnapshotList" class="p-2 space-y-1"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4 -->
                        <div class="restore-point-step hidden" data-step="4">
                            <label class="block text-sm font-medium text-slate-200 mb-2">Restore Target</label>
                            <div class="space-y-3">
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <input id="restorePointTargetPath" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100" placeholder="Destination path on agent (e.g., C:\Restores\snapshot)">
                                    <button type="button" onclick="openRestorePointBrowseModal()"
                                            class="px-3 py-2 rounded-lg border border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800">
                                        Browse
                                    </button>
                                </div>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                                    <input id="restorePointMount" type="checkbox" class="rounded border-slate-600 bg-slate-800">
                                    <span>Request mount instead of copy</span>
                                </label>
                            </div>
                        </div>

                        <!-- Step 5 -->
                        <div class="restore-point-step hidden" data-step="5">
                            <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-3 text-slate-100">
                                <p class="text-sm font-semibold mb-2">Review</p>
                                <div id="restorePointReview" class="text-xs whitespace-pre-wrap leading-5 bg-slate-950 border border-slate-800 rounded-lg p-3 overflow-auto max-h-64"></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mt-6">
                        <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700" onclick="restorePointPrev()">Back</button>
                        <div class="flex gap-2">
                            <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700" onclick="closeRestorePointModal()">Cancel</button>
                            <button type="button" class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-500" onclick="restorePointNext()">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Destination Browse Modal -->
    <div id="restorePointBrowseModal" class="fixed inset-0 z-[2200] hidden">
        <div class="absolute inset-0 bg-black/75" onclick="closeRestorePointBrowseModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-2xl rounded-2xl bg-slate-950 border border-slate-800 shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Browse Destination</h3>
                        <p class="text-xs text-slate-400 mt-1">Select a folder on the destination agent.</p>
                    </div>
                    <button class="text-slate-400 hover:text-white" onclick="closeRestorePointBrowseModal()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="px-6 py-4 space-y-3">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <div id="restorePointBrowsePath">This PC</div>
                        <button type="button" onclick="restorePointBrowseUp()"
                                class="px-2 py-1 rounded border border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800">
                            Up
                        </button>
                    </div>
                    <div id="restorePointBrowseStatus" class="text-xs text-slate-400 hidden"></div>
                    <div class="rounded-xl border border-slate-800 bg-slate-900 max-h-72 overflow-auto">
                        <div id="restorePointBrowseList" class="divide-y divide-slate-800"></div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" onclick="closeRestorePointBrowseModal()"
                                class="px-3 py-2 rounded-lg border border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800">
                            Cancel
                        </button>
                        <button type="button" onclick="applyRestorePointBrowseSelection()"
                                class="px-3 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-500">
                            Use this folder
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{literal}
<script>
function restoresApp() {
    return {
        restorePoints: [],
        loading: true,
        tenantFilter: '',
        agentFilter: '',
        searchQuery: '',
        dateFrom: '',
        dateTo: '',
        limit: 200,
        offset: 0,
        hasMore: false,
        requireFilters: {/literal}{if $isMspClient}true{else}false{/if}{literal},
        tenants: {/literal}{if $tenants}{$tenants|json_encode nofilter}{else}[]{/if}{literal},
        agents: {/literal}{if $agents}{$agents|json_encode nofilter}{else}[]{/if}{literal},
        tenantSearch: '',
        agentSearch: '',
        searchTimer: null,
        dateTimer: null,

        get hasAnyFilter() {
            return !!(this.tenantFilter || this.agentFilter || this.searchQuery || this.dateFrom || this.dateTo);
        },

        get needsFilter() {
            return this.requireFilters && !this.hasAnyFilter;
        },

        get filteredTenants() {
            const term = (this.tenantSearch || '').toLowerCase().trim();
            if (!term) return this.tenants || [];
            return (this.tenants || []).filter(t => String(t.name || '').toLowerCase().includes(term));
        },

        get filteredAgents() {
            let list = Array.isArray(this.agents) ? this.agents : [];
            {/literal}{if $isMspClient}{literal}
            if (this.tenantFilter) {
                if (this.tenantFilter === 'direct') {
                    list = list.filter(a => !a.tenant_id);
                } else {
                    list = list.filter(a => String(a.tenant_id) === String(this.tenantFilter));
                }
            }
            {/literal}{/if}{literal}
            const term = (this.agentSearch || '').toLowerCase().trim();
            if (!term) return list;
            return list.filter(a => {
                const name = String(a.hostname || '').toLowerCase();
                return name.includes(term) || String(a.id || '').includes(term);
            });
        },

        init() {
            try {
                const params = new URLSearchParams(window.location.search);
                const tenant = params.get('tenant_id');
                const agent = params.get('agent_id');
                const fromDate = params.get('from_date');
                const toDate = params.get('to_date');
                if (tenant !== null) this.tenantFilter = tenant;
                if (agent !== null) this.agentFilter = agent;
                if (fromDate) this.dateFrom = fromDate;
                if (toDate) this.dateTo = toDate;
            } catch (e) {}
            this.$watch('searchQuery', () => {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => this.loadRestorePoints(true), 300);
            });
            this.loadRestorePoints(true);
        },

        tenantLabel() {
            if (!this.tenantFilter) return 'All Tenants';
            if (this.tenantFilter === 'direct') return 'Direct (No Tenant)';
            const match = (this.tenants || []).find(t => String(t.id) === String(this.tenantFilter));
            return match ? match.name : `Tenant ${this.tenantFilter}`;
        },

        agentLabel() {
            if (!this.agentFilter) return 'All Agents';
            const match = (this.agents || []).find(a => String(a.id) === String(this.agentFilter));
            if (match) return match.hostname || `Agent #${match.id}`;
            return `Agent #${this.agentFilter}`;
        },

        selectTenant(value) {
            this.tenantFilter = value;
            {/literal}{if $isMspClient}{literal}
            if (this.agentFilter) {
                const stillVisible = this.filteredAgents.some(a => String(a.id) === String(this.agentFilter));
                if (!stillVisible) this.agentFilter = '';
            }
            {/literal}{/if}{literal}
            this.loadRestorePoints(true);
        },

        selectAgent(value) {
            this.agentFilter = value;
            this.loadRestorePoints(true);
        },

        setQuickRange(days) {
            const to = new Date();
            const from = new Date();
            from.setDate(to.getDate() - (days - 1));
            this.dateFrom = this.formatDate(from);
            this.dateTo = this.formatDate(to);
            this.loadRestorePoints(true);
        },

        clearDateRange() {
            this.dateFrom = '';
            this.dateTo = '';
            this.loadRestorePoints(true);
        },

        onDateChange() {
            clearTimeout(this.dateTimer);
            this.dateTimer = setTimeout(() => this.loadRestorePoints(true), 200);
        },

        formatDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        },

        async loadRestorePoints(reset = true) {
            if (this.needsFilter) {
                this.restorePoints = [];
                this.loading = false;
                this.hasMore = false;
                this.offset = 0;
                return;
            }
            this.loading = true;
            if (reset) {
                this.restorePoints = [];
                this.offset = 0;
            }
            try {
                const params = new URLSearchParams();
                if (this.tenantFilter) params.set('tenant_id', this.tenantFilter);
                if (this.agentFilter) params.set('agent_id', this.agentFilter);
                if (this.searchQuery) params.set('search', this.searchQuery);
                if (this.dateFrom) params.set('from_date', this.dateFrom);
                if (this.dateTo) params.set('to_date', this.dateTo);
                params.set('limit', String(this.limit));
                params.set('offset', String(this.offset));
                let url = 'modules/addons/cloudstorage/api/e3backup_restore_points_list.php';
                const qs = params.toString();
                if (qs) url += '?' + qs;

                const res = await fetch(url);
                const data = await res.json();
                if (data.status === 'success') {
                    const rows = data.restore_points || [];
                    this.restorePoints = reset ? rows : [...this.restorePoints, ...rows];
                    this.hasMore = !!data.has_more;
                    this.offset = data.next_offset !== null && data.next_offset !== undefined ? data.next_offset : 0;
                } else {
                    console.error(data.message);
                }
            } catch (e) {
                console.error('Failed to load restore points:', e);
            }
            this.loading = false;
        },

        openRestoreModal(point) {
            window.openRestorePointModal(point);
        }
    };
}

window.restorePointAgents = {/literal}{$agents|json_encode nofilter}{literal};

window.restorePointState = {
    point: null,
    step: 1,
    totalSteps: 4,
    stepSequence: [1, 2, 4, 5],
    targetPath: '',
    mount: false,
    targetAgentId: '',
    availableAgents: [],
    agentRequired: false,
    allowSnapshotBrowse: false,
    selectedSnapshotPaths: [],
    snapshotPath: '',
    snapshotParent: '',
    snapshotEntries: [],
    snapshotLoading: false,
    snapshotError: '',
    snapshotAgentId: ''
};

function normalizeTenantId(value) {
    if (value === null || value === undefined || value === '') return null;
    const num = parseInt(value, 10);
    return Number.isNaN(num) ? null : num;
}

function getCompatibleAgents(point) {
    const agents = Array.isArray(window.restorePointAgents) ? window.restorePointAgents : [];
    if (!point) return agents;
    const pointTenantId = normalizeTenantId(point.tenant_id);
    return agents.filter((agent) => {
        const agentTenantId = normalizeTenantId(agent.tenant_id);
        if (pointTenantId === null) {
            return agentTenantId === null;
        }
        return agentTenantId === pointTenantId;
    });
}

function hydrateRestorePointAgents(point) {
    const st = window.restorePointState;
    if (!point) return;
    st.availableAgents = getCompatibleAgents(point);
    const originalAvailable = st.availableAgents.some((agent) => String(agent.id) === String(point.agent_id));
    st.agentRequired = !originalAvailable;
    if (!st.targetAgentId) {
        st.targetAgentId = originalAvailable ? String(point.agent_id) : '';
    }
    const select = document.getElementById('restorePointTargetAgent');
    if (select) {
        select.innerHTML = '<option value="">Select an agent</option>';
        st.availableAgents.forEach((agent) => {
            const opt = document.createElement('option');
            opt.value = String(agent.id);
            opt.textContent = agent.hostname || `Agent #${agent.id}`;
            select.appendChild(opt);
        });
        select.value = st.targetAgentId || '';
        select.onchange = () => {
            st.targetAgentId = select.value || '';
        };
    }
    const hint = document.getElementById('restorePointAgentHint');
    if (hint) {
        if (st.agentRequired) {
            hint.textContent = 'Original agent is unavailable. Select a destination agent.';
            hint.classList.remove('hidden');
        } else {
            hint.textContent = '';
            hint.classList.add('hidden');
        }
    }
}

function getRestorePointAgentLabel(agentId) {
    if (!agentId) return '';
    const agents = Array.isArray(window.restorePointAgents) ? window.restorePointAgents : [];
    const found = agents.find((agent) => String(agent.id) === String(agentId));
    if (found) {
        return found.hostname || `Agent #${found.id}`;
    }
    return `Agent #${agentId}`;
}

function getRestorePointStepId() {
    const st = window.restorePointState;
    if (!Array.isArray(st.stepSequence) || st.stepSequence.length === 0) {
        return st.step;
    }
    const idx = Math.max(0, st.step - 1);
    return st.stepSequence[idx] || st.step;
}

function initSnapshotBrowser() {
    const st = window.restorePointState;
    if (!st.allowSnapshotBrowse) {
        setSnapshotStatus('Snapshot browsing is not available for this restore point.', 'info');
        renderSnapshotList();
        return;
    }
    if (!st.targetAgentId) {
        setSnapshotStatus('Select a destination agent to browse the snapshot.', 'info');
        renderSnapshotList();
        return;
    }
    if (st.snapshotAgentId !== st.targetAgentId) {
        st.snapshotAgentId = st.targetAgentId;
        st.snapshotPath = '';
        st.snapshotParent = '';
        st.snapshotEntries = [];
        st.snapshotError = '';
    }
    if (!st.snapshotLoading && st.snapshotEntries.length === 0) {
        loadSnapshotEntries('');
        return;
    }
    renderSnapshotList();
}

function setSnapshotStatus(message, kind) {
    const st = window.restorePointState;
    st.snapshotError = kind === 'error' ? message : '';
    const statusEl = document.getElementById('restorePointSnapshotStatus');
    if (!statusEl) return;
    if (!message) {
        statusEl.textContent = '';
        statusEl.classList.add('hidden');
        return;
    }
    statusEl.textContent = message;
    statusEl.classList.remove('hidden');
    statusEl.classList.toggle('text-rose-300', kind === 'error');
    statusEl.classList.toggle('text-slate-400', kind !== 'error');
}

function loadSnapshotEntries(path) {
    const st = window.restorePointState;
    if (!st.point || !st.targetAgentId) {
        setSnapshotStatus('Select a destination agent to browse the snapshot.', 'info');
        return;
    }
    st.snapshotLoading = true;
    setSnapshotStatus('Loading snapshot entries...', 'info');
    renderSnapshotList();
    const qs = new URLSearchParams();
    qs.set('agent_id', st.targetAgentId);
    qs.set('restore_point_id', String(st.point.id));
    if (path) qs.set('path', path);
    const url = `modules/addons/cloudstorage/api/agent_browse_snapshot.php?${qs.toString()}`;
    fetch(url)
        .then(res => res.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                setSnapshotStatus(`Browse failed (non-JSON response): ${text.slice(0, 120)}...`, 'error');
                return;
            }
            if (data.status === 'success') {
                const res = data.data || {};
                if (res.error) {
                    st.snapshotEntries = [];
                    st.snapshotPath = res.path || '';
                    st.snapshotParent = res.parent || '';
                    setSnapshotStatus(res.error, 'error');
                } else {
                    st.snapshotPath = res.path || '';
                    st.snapshotParent = res.parent || '';
                    st.snapshotEntries = Array.isArray(res.entries) ? res.entries : [];
                    setSnapshotStatus('', 'info');
                }
            } else {
                setSnapshotStatus(data.message || 'Failed to load snapshot entries', 'error');
            }
        })
        .catch(err => {
            setSnapshotStatus(err?.message || 'Network error', 'error');
        })
        .finally(() => {
            st.snapshotLoading = false;
            renderSnapshotList();
        });
}

function renderSnapshotList() {
    const st = window.restorePointState;
    const listEl = document.getElementById('restorePointSnapshotList');
    if (!listEl) return;
    listEl.innerHTML = '';
    renderSnapshotBreadcrumbs();

    updateSnapshotSelectionCount();

    if (st.snapshotLoading) {
        return;
    }

    if (!st.allowSnapshotBrowse || !st.targetAgentId) {
        return;
    }

    const statusEl = document.getElementById('restorePointSnapshotStatus');
    if (statusEl && statusEl.textContent) {
        // Status is handled separately (no list rendering when showing error)
    }

    if (!Array.isArray(st.snapshotEntries) || st.snapshotEntries.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'px-3 py-6 text-center text-sm text-slate-500';
        empty.textContent = st.snapshotError ? 'Unable to browse snapshot.' : 'No entries found.';
        listEl.appendChild(empty);
        return;
    }

    if (st.snapshotPath) {
        const upRow = document.createElement('button');
        upRow.type = 'button';
        upRow.className = 'w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800/60 text-left transition';
        upRow.addEventListener('click', () => loadSnapshotEntries(st.snapshotParent || ''));
        upRow.innerHTML = `
            <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center">
                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                </svg>
            </div>
            <span class="text-sm text-slate-400">..</span>
        `;
        listEl.appendChild(upRow);
    }

    st.snapshotEntries.forEach((entry) => {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-800/60 transition';
        if (isSnapshotSelected(entry.path)) {
            row.classList.add('bg-cyan-500/10', 'ring-1', 'ring-cyan-500/40');
        }

        const checkboxWrap = document.createElement('label');
        checkboxWrap.className = 'w-5 h-5 flex items-center justify-center rounded border cursor-pointer';
        checkboxWrap.className += isSnapshotSelected(entry.path) ? ' bg-cyan-500 border-cyan-500' : ' border-slate-600';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'hidden';
        checkbox.checked = isSnapshotSelected(entry.path);
        checkbox.addEventListener('change', () => toggleSnapshotSelection(entry.path));
        checkboxWrap.appendChild(checkbox);
        checkboxWrap.insertAdjacentHTML('beforeend', `
            <svg class="w-3 h-3 text-white ${isSnapshotSelected(entry.path) ? '' : 'hidden'}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
        `);
        row.appendChild(checkboxWrap);

        const nameBtn = document.createElement('button');
        nameBtn.type = 'button';
        nameBtn.className = 'flex-1 flex items-center gap-3 text-left cursor-pointer';
        if (entry.is_dir) {
            nameBtn.addEventListener('click', () => loadSnapshotEntries(entry.path));
        }
        const icon = entry.is_dir
            ? `<svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                </svg>`
            : `<svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>`;
        nameBtn.innerHTML = `
            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-slate-800">
                ${icon}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm text-slate-100 truncate">${entry.name || entry.path || 'Unnamed'}</p>
                <p class="text-xs text-slate-500">${entry.is_dir ? 'Folder' : formatSnapshotBytes(entry.size || 0)}</p>
            </div>
            ${entry.is_dir ? '<svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>' : ''}
        `;
        row.appendChild(nameBtn);

        listEl.appendChild(row);
    });
}

function renderSnapshotBreadcrumbs() {
    const st = window.restorePointState;
    const crumbsEl = document.getElementById('restorePointSnapshotBreadcrumbs');
    if (!crumbsEl) return;
    crumbsEl.innerHTML = '';

    const rootBtn = document.createElement('button');
    rootBtn.type = 'button';
    rootBtn.className = 'px-2 py-1 rounded hover:bg-slate-700/60 transition';
    rootBtn.textContent = 'Snapshot root';
    rootBtn.addEventListener('click', () => loadSnapshotEntries(''));
    crumbsEl.appendChild(rootBtn);

    const segments = getSnapshotPathSegments(st.snapshotPath || '');
    segments.forEach((segment) => {
        const sep = document.createElement('span');
        sep.className = 'text-slate-600';
        sep.textContent = '/';
        crumbsEl.appendChild(sep);

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'px-2 py-1 rounded hover:bg-slate-700/60 transition truncate max-w-[140px]';
        btn.textContent = segment.name;
        btn.addEventListener('click', () => loadSnapshotEntries(segment.path));
        crumbsEl.appendChild(btn);
    });
}

function getSnapshotPathSegments(rawPath) {
    if (!rawPath) return [];
    const parts = String(rawPath).split('/').filter(Boolean);
    let acc = '';
    return parts.map((part) => {
        acc = acc ? `${acc}/${part}` : part;
        return { name: part, path: acc };
    });
}

function isSnapshotSelected(path) {
    const st = window.restorePointState;
    return st.selectedSnapshotPaths.includes(path);
}

function toggleSnapshotSelection(path) {
    const st = window.restorePointState;
    if (!path) return;
    if (isSnapshotSelected(path)) {
        st.selectedSnapshotPaths = st.selectedSnapshotPaths.filter((p) => p !== path);
    } else {
        st.selectedSnapshotPaths = [...st.selectedSnapshotPaths, path];
    }
    updateSnapshotSelectionCount();
    renderSnapshotList();
}

function updateSnapshotSelectionCount() {
    const st = window.restorePointState;
    const el = document.getElementById('restorePointSnapshotSelection');
    if (el) {
        el.textContent = `${st.selectedSnapshotPaths.length} selected`;
    }
}

function formatSnapshotBytes(n) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let val = Number(n);
    let idx = 0;
    while (val >= 1024 && idx < units.length - 1) {
        val /= 1024;
        idx += 1;
    }
    const precision = idx === 0 ? 0 : 1;
    return `${val.toFixed(precision)} ${units[idx]}`;
}

window.restorePointBrowseState = {
    path: '',
    parent: '',
    entries: [],
    loading: false,
    error: '',
    agentId: ''
};

function openRestorePointBrowseModal() {
    const st = window.restorePointState;
    if (!st.targetAgentId) {
        if (window.toast) toast.error('Select a destination agent first');
        else alert('Select a destination agent first');
        return;
    }
    const bs = window.restorePointBrowseState;
    if (bs.agentId !== st.targetAgentId) {
        bs.path = '';
        bs.parent = '';
        bs.entries = [];
    }
    bs.agentId = st.targetAgentId;
    const modal = document.getElementById('restorePointBrowseModal');
    if (modal) modal.classList.remove('hidden');
    loadRestorePointBrowsePath(bs.path || '');
}

function closeRestorePointBrowseModal() {
    const modal = document.getElementById('restorePointBrowseModal');
    if (modal) modal.classList.add('hidden');
}

function setRestorePointBrowseStatus(message, kind) {
    const bs = window.restorePointBrowseState;
    bs.error = kind === 'error' ? message : '';
    const statusEl = document.getElementById('restorePointBrowseStatus');
    if (!statusEl) return;
    if (!message) {
        statusEl.textContent = '';
        statusEl.classList.add('hidden');
        return;
    }
    statusEl.textContent = message;
    statusEl.classList.remove('hidden');
    statusEl.classList.toggle('text-rose-300', kind === 'error');
    statusEl.classList.toggle('text-slate-400', kind !== 'error');
}

function loadRestorePointBrowsePath(path) {
    const bs = window.restorePointBrowseState;
    if (!bs.agentId) {
        setRestorePointBrowseStatus('Select a destination agent first.', 'error');
        return;
    }
    bs.loading = true;
    setRestorePointBrowseStatus('Loading folders...', 'info');
    renderRestorePointBrowseList();
    const url = `modules/addons/cloudstorage/api/agent_browse_filesystem.php?agent_id=${encodeURIComponent(bs.agentId)}&path=${encodeURIComponent(path || '')}`;
    fetch(url)
        .then(res => res.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                setRestorePointBrowseStatus(`Browse failed (non-JSON response): ${text.slice(0, 120)}...`, 'error');
                return;
            }
            if (data.status === 'success') {
                const res = data.data || {};
                if (res.error) {
                    bs.entries = [];
                    bs.path = res.path || '';
                    bs.parent = res.parent || '';
                    setRestorePointBrowseStatus(res.error, 'error');
                } else {
                    bs.path = res.path || '';
                    bs.parent = res.parent || '';
                    bs.entries = Array.isArray(res.entries) ? res.entries : [];
                    setRestorePointBrowseStatus('', 'info');
                }
            } else {
                setRestorePointBrowseStatus(data.message || 'Failed to load directory', 'error');
            }
        })
        .catch(err => {
            setRestorePointBrowseStatus(err?.message || 'Network error', 'error');
        })
        .finally(() => {
            bs.loading = false;
            renderRestorePointBrowseList();
        });
}

function renderRestorePointBrowseList() {
    const bs = window.restorePointBrowseState;
    const listEl = document.getElementById('restorePointBrowseList');
    if (!listEl) return;
    listEl.innerHTML = '';

    const pathEl = document.getElementById('restorePointBrowsePath');
    if (pathEl) {
        pathEl.textContent = bs.path || 'This PC';
    }

    if (bs.loading) {
        return;
    }

    const entries = Array.isArray(bs.entries) ? bs.entries.filter(e => e && e.is_dir) : [];
    if (entries.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'px-4 py-3 text-xs text-slate-500';
        empty.textContent = bs.error ? 'Unable to browse folders.' : 'No folders found.';
        listEl.appendChild(empty);
        return;
    }

    entries.forEach((entry) => {
        const row = document.createElement('div');
        row.className = 'px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/50 cursor-pointer';
        row.textContent = entry.name || entry.path || 'Unnamed';
        row.addEventListener('click', () => loadRestorePointBrowsePath(entry.path || ''));
        listEl.appendChild(row);
    });
}

function restorePointBrowseUp() {
    const bs = window.restorePointBrowseState;
    if (bs.parent !== undefined) {
        loadRestorePointBrowsePath(bs.parent || '');
    }
}

function applyRestorePointBrowseSelection() {
    const bs = window.restorePointBrowseState;
    const input = document.getElementById('restorePointTargetPath');
    if (input) {
        input.value = bs.path || '';
    }
    closeRestorePointBrowseModal();
}

function openRestorePointModal(point) {
    window.restorePointState.point = point;
    window.restorePointState.step = 1;
    window.restorePointState.targetPath = '';
    window.restorePointState.mount = false;
    window.restorePointState.targetAgentId = '';
    window.restorePointState.availableAgents = [];
    window.restorePointState.agentRequired = false;
    window.restorePointState.allowSnapshotBrowse = !!point && String(point.engine || '').toLowerCase() === 'kopia';
    window.restorePointState.stepSequence = window.restorePointState.allowSnapshotBrowse ? [1, 2, 3, 4, 5] : [1, 2, 4, 5];
    window.restorePointState.totalSteps = window.restorePointState.stepSequence.length;
    window.restorePointState.selectedSnapshotPaths = [];
    window.restorePointState.snapshotPath = '';
    window.restorePointState.snapshotParent = '';
    window.restorePointState.snapshotEntries = [];
    window.restorePointState.snapshotLoading = false;
    window.restorePointState.snapshotError = '';
    window.restorePointState.snapshotAgentId = '';
    hydrateRestorePointAgents(point);
    const modal = document.getElementById('restorePointModal');
    if (modal) modal.classList.remove('hidden');
    updateRestorePointView();
}

function closeRestorePointModal() {
    const modal = document.getElementById('restorePointModal');
    if (modal) modal.classList.add('hidden');
}

function restorePointNext() {
    const st = window.restorePointState;
    const stepId = getRestorePointStepId();
    if (stepId === 2) {
        const agentSel = document.getElementById('restorePointTargetAgent');
        st.targetAgentId = agentSel ? (agentSel.value || '') : '';
        if (!st.targetAgentId) {
            if (window.toast) toast.error('Select a destination agent');
            else alert('Select a destination agent');
            return;
        }
    }
    if (stepId === 4) {
        const tp = document.getElementById('restorePointTargetPath');
        st.targetPath = tp ? (tp.value || '') : '';
        st.mount = document.getElementById('restorePointMount')?.checked || false;
        if (!st.targetPath) {
            if (window.toast) toast.error('Target path is required');
            else alert('Target path is required');
            return;
        }
    }
    if (st.step < st.totalSteps) {
        st.step += 1;
        if (getRestorePointStepId() === 5) {
            buildRestorePointReview();
        }
        updateRestorePointView();
        return;
    }
    submitRestorePoint();
}

function restorePointPrev() {
    const st = window.restorePointState;
    if (st.step > 1) {
        st.step -= 1;
        updateRestorePointView();
    }
}

function updateRestorePointView() {
    const st = window.restorePointState;
    const stepId = getRestorePointStepId();
    document.querySelectorAll('#restorePointModal .restore-point-step').forEach((el) => {
        const s = parseInt(el.getAttribute('data-step'), 10);
        if (s === stepId) el.classList.remove('hidden'); else el.classList.add('hidden');
    });
    const label = document.getElementById('restorePointStepLabel');
    const title = document.getElementById('restorePointStepTitle');
    if (label) label.textContent = `Step ${st.step} of ${st.totalSteps}`;
    if (title) {
        title.textContent = stepId === 1
            ? 'Confirm Snapshot'
            : (stepId === 2 ? 'Destination Agent' : (stepId === 3 ? 'Select Items' : (stepId === 4 ? 'Restore Target' : 'Review')));
    }
    if (stepId === 1 && st.point) {
        const jobName = document.getElementById('restorePointJobName');
        const manifest = document.getElementById('restorePointManifest');
        const agent = document.getElementById('restorePointAgent');
        if (jobName) jobName.textContent = st.point.job_name || `Job #${st.point.job_id || '—'}`;
        if (manifest) manifest.textContent = `Manifest: ${st.point.manifest_id || '—'}`;
        if (agent) agent.textContent = `Agent: ${st.point.agent_hostname || ('Agent #' + (st.point.agent_id || '—'))}`;
    }
    if (stepId === 2) {
        hydrateRestorePointAgents(st.point);
        const agentSelect = document.getElementById('restorePointTargetAgent');
        if (agentSelect) {
            agentSelect.value = st.targetAgentId || '';
        }
    }
    if (stepId === 3) {
        initSnapshotBrowser();
    }
}

function buildRestorePointReview() {
    const st = window.restorePointState;
    const agentLabel = getRestorePointAgentLabel(st.targetAgentId);
    const review = {
        restore_point_id: st.point?.id,
        job_name: st.point?.job_name,
        manifest_id: st.point?.manifest_id,
        target_agent_id: st.targetAgentId || '',
        target_agent: agentLabel || '',
        target_path: st.targetPath,
        mount: st.mount,
    };
    if (Array.isArray(st.selectedSnapshotPaths) && st.selectedSnapshotPaths.length > 0) {
        review.selected_paths = st.selectedSnapshotPaths;
    }
    const el = document.getElementById('restorePointReview');
    if (el) {
        el.textContent = JSON.stringify(review, null, 2);
    }
}

function submitRestorePoint() {
    const st = window.restorePointState;
    if (!st.point || !st.point.id) {
        if (window.toast) toast.error('Restore point is missing');
        else alert('Restore point is missing');
        return;
    }
    const data = new URLSearchParams();
    data.set('restore_point_id', String(st.point.id));
    if (st.targetAgentId) {
        data.set('target_agent_id', String(st.targetAgentId));
    }
    data.set('target_path', st.targetPath || '');
    data.set('mount', st.mount ? 'true' : 'false');
    if (Array.isArray(st.selectedSnapshotPaths) && st.selectedSnapshotPaths.length > 0) {
        data.set('selected_paths', JSON.stringify(st.selectedSnapshotPaths));
    }

    const submitBtn = document.querySelector('#restorePointModal button[onclick*="restorePointNext"]');
    const originalText = submitBtn ? submitBtn.textContent : 'Submit';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Starting restore...';
    }

    fetch('modules/addons/cloudstorage/api/cloudbackup_start_restore.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: data
    })
    .then(res => res.json())
    .then(resp => {
        if (resp.status === 'success') {
            closeRestorePointModal();
            const restoreRunParam = resp.restore_run_uuid || resp.restore_run_id;
            if (restoreRunParam) {
                setTimeout(() => {
                    window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=live&job_id=' +
                        encodeURIComponent(resp.job_id) + '&run_id=' + encodeURIComponent(restoreRunParam);
                }, 500);
            }
        } else {
            if (window.toast) toast.error(resp.message || 'Failed to start restore');
            else alert(resp.message || 'Failed to start restore');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    })
    .catch(err => {
        if (window.toast) toast.error('Error starting restore: ' + err);
        else alert('Error starting restore: ' + err);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}
</script>
{/literal}
