<div class="min-h-screen bg-slate-950 text-gray-200" x-data="jobsApp()">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 py-6 relative pointer-events-auto">
        <!-- Navigation Tabs -->
        {assign var="activeNav" value="jobs"}
        {include file="modules/addons/cloudstorage/templates/partials/e3backup_nav.tpl"}

        {* Glass panel container *}
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
        <style>
        .btn-run-now {
            display: inline-flex; align-items: center; gap: 0.5rem;
            border-radius: 9999px; padding: 0.375rem 1rem;
            font-size: 0.875rem; font-weight: 600;
            color: rgb(15 23 42);
            background-image: linear-gradient(to right, rgb(16 185 129), rgb(52 211 153), rgb(56 189 248));
            box-shadow: 0 1px 2px rgba(0,0,0,0.25);
            border: 1px solid rgba(16,185,129,0.4);
            transition: transform .15s ease, box-shadow .2s ease;
        }
        .btn-run-now:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(16,185,129,0.25); }
        .btn-run-now:active { transform: translateY(0); box-shadow: 0 1px 2px rgba(0,0,0,0.25); }
        .icon-btn {
            display:inline-flex; align-items:center; justify-content:center;
            width:2rem; height:2rem; border-radius:9999px;
            border:1px solid rgba(51,65,85,0.8);
            background-color: rgba(15,23,42,0.6);
            color:#cbd5e1; font-size:.75rem; transition: all .15s ease;
        }
        .icon-btn:hover { border-color:#94a3b8; color:white; background-color:#1f2937; }
        .icon-btn[disabled] { opacity:.6; cursor:not-allowed; }
        </style>

        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="index.php?m=cloudstorage&page=e3backup" class="text-slate-400 hover:text-white text-sm">e3 Cloud Backup</a>
                    <span class="text-slate-600">/</span>
                    <span class="text-white text-sm font-medium">Jobs</span>
                </div>
                <h1 class="text-2xl font-semibold text-white">Backup Jobs</h1>
                <p class="text-xs text-slate-400 mt-1">View and filter backup jobs. MSPs can filter by tenant and agent.</p>
            </div>
            <div class="flex gap-2 mt-4 sm:mt-0">
                <!-- Create Job Dropdown -->
                <div x-data="{ isOpen: false }" class="relative" @click.away="isOpen = false">
                    <button @click="isOpen = !isOpen" class="btn-run-now">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Create Job
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <!-- Dropdown Menu -->
                    <div x-show="isOpen" 
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-64 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                         style="display: none;">
                        <div class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-800">
                            SELECT BACKUP SOURCE
                        </div>
                        <div class="py-1">
                            <!-- Cloud Backup Option -->
                            <button type="button" 
                                    @click="isOpen = false; window.openCloudBackupWizard()"
                                    class="w-full px-4 py-3 flex items-start gap-3 hover:bg-slate-800/60 transition text-left">
                                <div class="w-10 h-10 rounded-lg bg-sky-500/20 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-100">Cloud Backup</p>
                                    <p class="text-xs text-slate-400 mt-0.5">S3, AWS, SFTP, Google Drive, Dropbox</p>
                                </div>
                                <svg class="w-4 h-4 text-slate-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                            <!-- Local Backup Option -->
                            <button type="button" 
                                    @click="isOpen = false; window.openLocalJobWizard()"
                                    class="w-full px-4 py-3 flex items-start gap-3 hover:bg-slate-800/60 transition text-left">
                                <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-100">Local Agent Backup</p>
                                    <p class="text-xs text-slate-400 mt-0.5">File, Disk Image, Windows Agent</p>
                                </div>
                                <svg class="w-4 h-4 text-slate-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {if $isMspClient}
        <!-- Filters (MSP) -->
        <div class="mb-4 flex flex-col sm:flex-row gap-3 items-start sm:items-center">
            <div class="flex items-center gap-2">
                <label class="text-sm text-slate-400">Tenant:</label>
                <div x-data="{ isOpen: false }" class="relative" @click.away="isOpen = false">
                    <button
                        type="button"
                        @click="isOpen = !isOpen"
                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500"
                    >
                        <span class="truncate max-w-[14rem]" x-text="tenantLabel()"></span>
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div
                        x-show="isOpen"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute left-0 mt-2 w-72 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                        style="display: none;"
                    >
                        <div class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-800">
                            Select tenant
                        </div>
                        <div class="py-1 max-h-72 overflow-auto">
                            <button
                                type="button"
                                class="w-full px-4 py-2 text-left text-sm transition"
                                :class="tenantFilter === '' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="tenantFilter=''; isOpen=false; onTenantChange()"
                                data-tenant-option=""
                            >
                                All Tenants
                            </button>
                            <button
                                type="button"
                                class="w-full px-4 py-2 text-left text-sm transition"
                                :class="tenantFilter === 'direct' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="tenantFilter='direct'; isOpen=false; onTenantChange()"
                                data-tenant-option="direct"
                            >
                                Direct (No Tenant)
                            </button>
                            {foreach from=$tenants item=tenant}
                            <button
                                type="button"
                                class="w-full px-4 py-2 text-left text-sm transition"
                                :class="String(tenantFilter) === String('{$tenant->id}') ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="tenantFilter='{$tenant->id}'; isOpen=false; onTenantChange()"
                                data-tenant-option="{$tenant->id}"
                            >
                                {$tenant->name|escape}
                            </button>
                            {/foreach}
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-slate-400">Agent:</label>
                <div x-data="{ isOpen: false }" class="relative" @click.away="isOpen = false">
                    <button
                        type="button"
                        @click="isOpen = !isOpen"
                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 border border-slate-700 px-3 py-2 text-sm text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500"
                    >
                        <span class="truncate max-w-[14rem]" x-text="agentLabel()"></span>
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div
                        x-show="isOpen"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute left-0 mt-2 w-72 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                        style="display: none;"
                    >
                        <div class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-800">
                            Select agent
                        </div>
                        <div class="py-1 max-h-72 overflow-auto">
                            <button
                                type="button"
                                class="w-full px-4 py-2 text-left text-sm transition"
                                :class="agentFilter === '' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="agentFilter=''; isOpen=false; loadJobs()"
                            >
                                All Agents
                            </button>
                            <template x-for="agent in filteredAgents" :key="agent.id">
                                <button
                                    type="button"
                                    class="w-full px-4 py-2 text-left text-sm transition"
                                    :class="String(agentFilter) === String(agent.id) ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                    @click="agentFilter=agent.id; isOpen=false; loadJobs()"
                                >
                                    <span class="truncate" x-text="agent.hostname || ('Agent #' + agent.id)"></span>
                                </button>
                            </template>
                            <template x-if="filteredAgents.length === 0">
                                <div class="px-4 py-2 text-sm text-slate-400">
                                    No agents available
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {/if}

        <!-- Filters -->
        <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between items-start sm:items-center">
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <input type="text" placeholder="Search jobs" class="w-full sm:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                    x-model="searchQuery">
                <div x-data="{ isOpen: false }" class="relative" @click.away="isOpen = false">
                    <button
                        type="button"
                        @click="isOpen = !isOpen"
                        class="inline-flex items-center justify-between gap-2 rounded-full border border-slate-700 bg-slate-900/80 px-4 py-2 text-xs text-slate-200 w-full sm:w-auto min-w-[12rem]"
                    >
                        <span class="truncate" x-text="sourceLabel()"></span>
                        <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div
                        x-show="isOpen"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute left-0 mt-2 w-64 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                        style="display: none;"
                    >
                        <div class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-800">
                            Source type
                        </div>
                        <div class="py-1">
                            <button type="button" class="w-full px-4 py-2 text-left text-sm transition"
                                :class="sourceFilter === 'all' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="sourceFilter='all'; isOpen=false"
                            >All Sources</button>
                            <button type="button" class="w-full px-4 py-2 text-left text-sm transition"
                                :class="sourceFilter === 'local_agent' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="sourceFilter='local_agent'; isOpen=false"
                            >Local Agent</button>
                            <button type="button" class="w-full px-4 py-2 text-left text-sm transition"
                                :class="sourceFilter === 'cloud' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="sourceFilter='cloud'; isOpen=false"
                            >Cloud-to-Cloud</button>
                            <button type="button" class="w-full px-4 py-2 text-left text-sm transition"
                                :class="sourceFilter === 's3_compatible' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="sourceFilter='s3_compatible'; isOpen=false"
                            >S3-Compatible</button>
                            <button type="button" class="w-full px-4 py-2 text-left text-sm transition"
                                :class="sourceFilter === 'aws' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="sourceFilter='aws'; isOpen=false"
                            >AWS</button>
                            <button type="button" class="w-full px-4 py-2 text-left text-sm transition"
                                :class="sourceFilter === 'sftp' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="sourceFilter='sftp'; isOpen=false"
                            >SFTP</button>
                            <button type="button" class="w-full px-4 py-2 text-left text-sm transition"
                                :class="sourceFilter === 'google_drive' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="sourceFilter='google_drive'; isOpen=false"
                            >Google Drive</button>
                            <button type="button" class="w-full px-4 py-2 text-left text-sm transition"
                                :class="sourceFilter === 'dropbox' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                @click="sourceFilter='dropbox'; isOpen=false"
                            >Dropbox</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400">
                <button class="px-3 py-1.5 rounded-full transition" :class="statusFilter === 'all' ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="statusFilter='all'">All</button>
                <button class="px-3 py-1.5 rounded-full transition" :class="statusFilter === 'success' ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="statusFilter='success'">Success</button>
                <button class="px-3 py-1.5 rounded-full transition" :class="statusFilter === 'warning' ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="statusFilter='warning'">Warning</button>
                <button class="px-3 py-1.5 rounded-full transition" :class="statusFilter === 'failed' ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="statusFilter='failed'">Failed</button>
                <button class="px-3 py-1.5 rounded-full transition" :class="statusFilter === 'running' ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="statusFilter='running'">Running</button>
                <button class="px-3 py-1.5 rounded-full transition" :class="statusFilter === 'failed_recent' ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="statusFilter='failed_recent'">Failed Recently</button>
            </div>
        </div>

        <!-- Jobs Container -->
        <div class="grid grid-cols-1 gap-6">
            <template x-if="loading">
                <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 text-center text-slate-400">
                    <svg class="animate-spin h-6 w-6 mx-auto text-sky-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </template>
            <template x-if="!loading && filteredJobs.length === 0">
                <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-6 text-center text-slate-400">
                    No jobs found.
                </div>
            </template>
            <template x-for="job in filteredJobs" :key="job.id">
                <div class="job-row group relative overflow-hidden rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-400/60 hover:shadow-lg hover:shadow-emerald-500/15">
                    <div class="flex items-center justify-between gap-4 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-800/90 group-hover:bg-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 15.75a4.5 4.5 0 004.5 4.5h9a4.5 4.5 0 004.5-4.5 4.5 4.5 0 00-3.75-4.415A5.25 5.25 0 006.75 9.75a5.25 5.25 0 00-.518 2.25M9 12l3-3m0 0l3 3m-3-3v7.5" />
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-white" x-text="job.name"></h3>
                                    <span x-show="job.source_type === 'local_agent'" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] font-semibold bg-sky-500/15 text-sky-200 border border-sky-400/40">
                                        Local Agent
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(job.status)">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="dotClass(job.status)"></span>
                                        <span x-text="(job.status || '').charAt(0).toUpperCase() + (job.status || '').slice(1)"></span>
                                    </span>
                                </div>                                
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div x-data="{ running: false }">
                                <button
                                    class="btn-run-now"
                                    @click="running = true; runJob(job.id).finally(() => running = false)"
                                    :disabled="job.status !== 'active'"
                                >
                                    <template x-if="!running">
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                            </svg>
                                            <span>Run now</span>
                                        </div>
                                    </template>
                                    <template x-if="running">
                                        <div class="flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                            </svg>
                                            <span>Running…</span>
                                        </div>
                                    </template>
                                </button>
                            </div>
                            <button @click="editJob(job)" class="icon-btn" title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                                    <path d="M21.731 2.269a2.625 2.625 0 00-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 000-3.712z" />
                                    <path d="M3.75 15.75V19.5h3.75l9.72-9.72-3.75-3.75L3.75 15.75z" />
                                </svg>
                            </button>
                            <button @click="toggleJobStatus(job.id, job.status)" class="icon-btn" :title="job.status === 'active' ? 'Pause' : 'Resume'">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5V18M15 7.5V18M3 16.811V8.69c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811Z" />
                                </svg>
                            </button>
                            <button @click="openDeleteModal(job)" class="icon-btn" title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                            <button @click="viewLogs(job.id)" class="icon-btn" title="Logs">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                            </button>
                            <button @click="openRestore(job)" class="icon-btn" title="Restore">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v9m0 0l-3.5-3.5M12 13l3.5-3.5M6 20h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.5" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-6 gap-4 text-xs text-slate-400">
                        <div>
                            <h6 class="text-sm font-medium text-slate-400">Source</h6>
                            <span class="text-md font-medium text-slate-300" x-text="job.source_display_name || formatSourceType(job.source_type)"></span>
                            <span class="text-xs text-slate-500 ml-2" x-text="formatAgentHint(job)"></span>
                        </div>
                        <div>
                            <h6 class="text-sm font-medium text-slate-400">Destination</h6>
                            <span class="text-md font-medium text-slate-300" x-text="formatDestinationName(job)"></span>
                            <span class="text-xs text-slate-500 ml-2" x-show="job.dest_prefix" x-text="'/' + job.dest_prefix"></span>
                        </div>
                        <div>
                            <h6 class="text-sm font-medium text-slate-400">Mode</h6>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                  :class="job.backup_mode === 'archive' ? 'bg-purple-700 text-purple-200' : 'bg-blue-700 text-blue-200'">
                                <span x-text="formatMode(job)"></span>
                            </span>
                            <span x-show="job.encryption_enabled" class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-700 text-green-200" title="Encrypted">
                                🔒
                            </span>
                        </div>
                        <div>
                            <h6 class="text-sm font-medium text-slate-400">Schedule</h6>
                            <span class="text-md font-medium text-slate-300" x-text="formatScheduleLabel(job)"></span>
                        </div>
                        <div>
                            <h6 class="text-sm font-medium text-slate-400">Next Run</h6>
                            <span class="text-md font-medium" :class="job.schedule_type === 'manual' ? 'text-slate-500' : 'text-slate-300'" x-text="nextRunText(job)"></span>
                        </div>
                        <div>
                            <h6 class="text-sm font-medium text-slate-400">Last Run</h6>
                            <div class="text-md font-medium text-slate-300">
                                <span x-text="formatLastRunTime(job)"></span>
                                <template x-if="job.last_run && job.last_run.status">
                                    <span class="ml-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] font-medium" :class="lastRunStatusClass(job.last_run.status)">
                                        <span x-text="capitalize(job.last_run.status)"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Delete Job Confirmation Modal -->
        <div
            x-show="deleteModalOpen"
            x-cloak
            class="fixed inset-0 z-[2200] flex items-center justify-center p-4"
            style="display:none;"
            @keydown.escape.window="closeDeleteModal()"
        >
            <div class="absolute inset-0 bg-black/70" @click="closeDeleteModal()"></div>
            <div
                class="relative w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl overflow-hidden"
                @click.stop
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
            >
                <div class="p-5">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-full bg-rose-500/15 text-rose-300 border border-rose-400/30">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-white">Delete job?</h3>
                            <p class="mt-1 text-sm text-slate-300">
                                This will remove the job configuration and stop scheduled runs.
                            </p>
                            <p class="mt-2 text-sm text-slate-400">
                                Job: <span class="text-slate-200 font-semibold" x-text="deleteJobName || ('Job #' + deleteJobId)"></span>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-4 bg-slate-800 border-t border-slate-700 flex justify-end gap-2">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-md border border-slate-600 text-slate-200 hover:border-slate-500 hover:bg-slate-800"
                        @click="closeDeleteModal()"
                        :disabled="deleteInProgress"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-md bg-rose-600 text-white hover:bg-rose-500 inline-flex items-center gap-2"
                        @click="confirmDeleteJob()"
                        :disabled="deleteInProgress"
                    >
                        <svg x-show="deleteInProgress" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-text="deleteInProgress ? 'Deleting…' : 'Delete'"></span>
                    </button>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<!-- Include the Job Creation Wizard Slide-Over -->
{include file="{$smarty.const.ROOTDIR}/modules/addons/cloudstorage/templates/partials/job_create_wizard.tpl"}

<!-- Include Bucket Creation Modal (shared) -->
{include file="{$smarty.const.ROOTDIR}/modules/addons/cloudstorage/templates/partials/bucket_create_modal.tpl"}

<!-- Restore Wizard Modal -->
<div id="restoreWizardModal" class="fixed inset-0 z-[2100] hidden">
    <div class="absolute inset-0 bg-black/75" onclick="closeRestoreModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-2xl border border-slate-800 bg-slate-950 shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800">
                <div>
                    <p class="text-xs uppercase text-slate-400 tracking-wide">Restore</p>
                    <h3 class="text-xl font-semibold text-white">Restore Snapshot</h3>
                    <p class="text-[11px] text-slate-400 mt-1">Select a snapshot (recent run), choose a target path, and optionally request a mount.</p>
                </div>
                <button class="p-2 rounded hover:bg-slate-800 text-slate-400 hover:text-white" onclick="closeRestoreModal()" aria-label="Close wizard">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4">
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">
                    <span class="px-2 py-1 rounded-full border border-slate-700 bg-slate-900" id="restoreStepLabel">Step 1 of 3</span>
                    <span class="text-slate-300" id="restoreStepTitle">Select Snapshot</span>
                </div>

                <div class="space-y-6">
                    <!-- Step 1 -->
                    <div class="restore-step" data-step="1">
                        <label class="block text-sm font-medium text-slate-200 mb-2">Snapshot (from recent runs)</label>
                        <div class="mb-3">
                            <select id="restoreRunSelect" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100">
                                <option value="">Loading runs…</option>
                            </select>
                            <p class="text-xs text-slate-400 mt-1">Pick a run whose snapshot you want to restore.</p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="restore-step hidden" data-step="2">
                        <label class="block text-sm font-medium text-slate-200 mb-2">Restore Target</label>
                        <div class="space-y-3">
                            <input id="restoreTargetPath" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100" placeholder="Destination path on agent (e.g., C:\Restores\job123)">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                                <input id="restoreMount" type="checkbox" class="rounded border-slate-600 bg-slate-800">
                                <span>Request mount instead of copy</span>
                            </label>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="restore-step hidden" data-step="3">
                        <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-3 text-slate-100">
                            <p class="text-sm font-semibold mb-2">Review</p>
                            <div id="restoreReview" class="text-xs whitespace-pre-wrap leading-5 bg-slate-950 border border-slate-800 rounded-lg p-3 overflow-auto max-h-64"></div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center mt-6">
                    <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700" onclick="restorePrev()">Back</button>
                    <div class="flex gap-2">
                        <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 hover:bg-slate-700" onclick="closeRestoreModal()">Cancel</button>
                        <button type="button" class="px-4 py-2 rounded-lg bg-sky-600 text-white hover:bg-sky-500" onclick="restoreNext()">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Hide native number spinners for custom steppers */
.eb-no-spinner::-webkit-outer-spin-button,
.eb-no-spinner::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.eb-no-spinner { -moz-appearance: textfield; appearance: textfield; }
</style>

{literal}
<script>
// ========================================
// Local Wizard Schedule UI Alpine Component
// ========================================
function localWizardScheduleUI() {
    return {
        scheduleType: 'manual',
        scheduleDropdownOpen: false,
        hourlyMinute: 0,
        dailyHour: 2,
        dailyMinute: 0,
        weeklyHour: 2,
        weeklyMinute: 0,
        selectedWeekdays: [],
        cronExpr: '',
        scheduleTypeLabels: {
            'manual': 'Manual (Run on demand)',
            'hourly': 'Hourly',
            'daily': 'Daily',
            'weekly': 'Weekly',
            'cron': 'Custom (Cron)'
        },
        weekDays: [
            { value: 1, short: 'Mon', name: 'Monday' },
            { value: 2, short: 'Tue', name: 'Tuesday' },
            { value: 3, short: 'Wed', name: 'Wednesday' },
            { value: 4, short: 'Thu', name: 'Thursday' },
            { value: 5, short: 'Fri', name: 'Friday' },
            { value: 6, short: 'Sat', name: 'Saturday' },
            { value: 7, short: 'Sun', name: 'Sunday' }
        ],
        
        init() {
            // Load values from existing job when editing
            this.$nextTick(() => {
                const typeEl = document.getElementById('localWizardScheduleType');
                const timeEl = document.getElementById('localWizardTime');
                const weekdayEl = document.getElementById('localWizardWeekday');
                const cronEl = document.getElementById('localWizardCron');
                
                // Get existing values
                const existingType = typeEl?.value || 'manual';
                const existingTime = timeEl?.value || '';
                const existingWeekday = weekdayEl?.value || '';
                const existingCron = cronEl?.value || '';
                
                this.scheduleType = existingType;
                this.cronExpr = existingCron;
                
                // Parse existing time
                if (existingTime) {
                    const parts = existingTime.split(':');
                    if (parts.length >= 2) {
                        const h = parseInt(parts[0], 10) || 0;
                        const m = parseInt(parts[1], 10) || 0;
                        if (existingType === 'hourly') {
                            this.hourlyMinute = m;
                        } else if (existingType === 'daily') {
                            this.dailyHour = h;
                            this.dailyMinute = m;
                        } else if (existingType === 'weekly') {
                            this.weeklyHour = h;
                            this.weeklyMinute = m;
                        }
                    }
                }
                
                // Parse weekday(s) - check schedule_json first for array
                if (existingType === 'weekly') {
                    let weekdays = [];
                    const stateData = window.localWizardState?.data;
                    const schedJson = stateData?.schedule_json || {};
                    if (schedJson.weekday && Array.isArray(schedJson.weekday)) {
                        weekdays = schedJson.weekday.map(d => parseInt(d, 10)).filter(d => d >= 1 && d <= 7);
                    } else if (existingWeekday) {
                        // Single day or CSV
                        const dayStr = String(existingWeekday);
                        if (dayStr.includes(',')) {
                            weekdays = dayStr.split(',').map(d => parseInt(d.trim(), 10)).filter(d => d >= 1 && d <= 7);
                        } else {
                            const day = parseInt(dayStr, 10);
                            if (day >= 1 && day <= 7) {
                                weekdays = [day];
                            }
                        }
                    }
                    this.selectedWeekdays = weekdays;
                }
            });
            
            // Listen for edit-paths-loaded event to reinitialize
            window.addEventListener('edit-paths-loaded', () => {
                this.$nextTick(() => this.init());
            });
        },
        
        get computedTime() {
            if (this.scheduleType === 'hourly') {
                return String(this.hourlyMinute).padStart(2, '0') + ':00';
            } else if (this.scheduleType === 'daily') {
                return String(this.dailyHour).padStart(2, '0') + ':' + String(this.dailyMinute).padStart(2, '0');
            } else if (this.scheduleType === 'weekly') {
                return String(this.weeklyHour).padStart(2, '0') + ':' + String(this.weeklyMinute).padStart(2, '0');
            }
            return '';
        },
        
        get firstSelectedWeekday() {
            if (this.selectedWeekdays.length > 0) {
                return String(Math.min(...this.selectedWeekdays));
            }
            return '';
        },
        
        toggleWeekday(day) {
            const idx = this.selectedWeekdays.indexOf(day);
            if (idx >= 0) {
                this.selectedWeekdays.splice(idx, 1);
            } else {
                this.selectedWeekdays.push(day);
                this.selectedWeekdays.sort((a, b) => a - b);
            }
            this.syncToState();
        },
        
        selectScheduleType(type) {
            this.scheduleType = type;
            this.scheduleDropdownOpen = false;
            this.syncToState();
        },
        
        onTypeChange() {
            this.syncToState();
        },
        
        syncToState() {
            // Update localWizardState.data.schedule_json for multi-day weekly and hourly minute
            if (!window.localWizardState?.data) return;
            const schedJson = {
                type: this.scheduleType,
                time: this.computedTime,
                cron: this.cronExpr
            };
            if (this.scheduleType === 'weekly') {
                schedJson.weekday = [...this.selectedWeekdays];
            } else if (this.scheduleType === 'hourly') {
                schedJson.minute = this.hourlyMinute;
            }
            window.localWizardState.data.schedule_json = schedJson;
        }
    };
}

// ========================================
// Local Wizard Retention UI Alpine Component
// ========================================
function localWizardRetentionUI() {
    return {
        mode: 'none',
        retentionDropdownOpen: false,
        keepLast: 30,
        keepDaily: 7,
        withinDays: 0,
        withinWeeks: 0,
        withinMonths: 0,
        withinYears: 0,
        withinUnit: '', // 'd', 'w', 'm', 'y' or '' for none
        retentionJson: '',
        modeLabels: {
            'none': 'No Retention',
            'keep_last': 'Keep last ... Backups',
            'keep_within': 'Keep all backups in the last...',
            'keep_daily': 'Keep last ... backup at most one per day'
        },
        
        init() {
            this.$nextTick(() => {
                // Load from state or hidden input
                const stateData = window.localWizardState?.data;
                let retJson = stateData?.retention_json || null;
                
                // Try hidden input if state is empty
                if (!retJson) {
                    const hiddenEl = document.getElementById('localWizardRetention');
                    if (hiddenEl && hiddenEl.value) {
                        try {
                            retJson = JSON.parse(hiddenEl.value);
                        } catch (e) {
                            retJson = null;
                        }
                    }
                }
                
                if (retJson && typeof retJson === 'object') {
                    this.parseRetentionJson(retJson);
                }
                
                this.syncToState();
            });
        },
        
        parseRetentionJson(obj) {
            // Parse keep_last
            if (obj.keep_last && typeof obj.keep_last === 'number') {
                this.mode = 'keep_last';
                this.keepLast = obj.keep_last;
                return;
            }
            
            // Parse keep_daily
            if (obj.keep_daily && typeof obj.keep_daily === 'number') {
                this.mode = 'keep_daily';
                this.keepDaily = obj.keep_daily;
                return;
            }
            
            // Parse keep_within (e.g., "30d", "4w", "6m", "1y")
            if (obj.keep_within && typeof obj.keep_within === 'string') {
                this.mode = 'keep_within';
                const match = obj.keep_within.match(/^(\d+)([dwmy])$/i);
                if (match) {
                    const val = parseInt(match[1], 10);
                    const unit = match[2].toLowerCase();
                    this.withinUnit = unit;
                    this.withinDays = unit === 'd' ? val : 0;
                    this.withinWeeks = unit === 'w' ? val : 0;
                    this.withinMonths = unit === 'm' ? val : 0;
                    this.withinYears = unit === 'y' ? val : 0;
                }
                return;
            }
            
            // Default to none
            this.mode = 'none';
        },
        
        selectMode(newMode) {
            this.mode = newMode;
            this.retentionDropdownOpen = false;
            this.syncToState();
        },
        
        setWithinUnit(unit, value) {
            // Clear all other units when setting one
            this.withinDays = unit === 'd' ? value : 0;
            this.withinWeeks = unit === 'w' ? value : 0;
            this.withinMonths = unit === 'm' ? value : 0;
            this.withinYears = unit === 'y' ? value : 0;
            
            // Set active unit if value > 0, otherwise clear
            if (value > 0) {
                this.withinUnit = unit;
            } else {
                this.withinUnit = '';
            }
            
            this.syncToState();
        },
        
        syncToState() {
            let retObj = null;
            
            if (this.mode === 'keep_last' && this.keepLast > 0) {
                retObj = { keep_last: this.keepLast };
            } else if (this.mode === 'keep_daily' && this.keepDaily > 0) {
                retObj = { keep_daily: this.keepDaily };
            } else if (this.mode === 'keep_within') {
                let withinStr = '';
                if (this.withinDays > 0) {
                    withinStr = this.withinDays + 'd';
                } else if (this.withinWeeks > 0) {
                    withinStr = this.withinWeeks + 'w';
                } else if (this.withinMonths > 0) {
                    withinStr = this.withinMonths + 'm';
                } else if (this.withinYears > 0) {
                    withinStr = this.withinYears + 'y';
                }
                if (withinStr) {
                    retObj = { keep_within: withinStr };
                }
            }
            
            // Update state
            if (window.localWizardState?.data) {
                window.localWizardState.data.retention_json = retObj;
            }
            
            // Update hidden input
            this.retentionJson = retObj ? JSON.stringify(retObj) : '';
        }
    };
}

// ========================================
// e3backup: shared helpers (toast + reload)
// ========================================
function e3backupNotify(type, message) {
    const msg = String(message || '');
    try {
        // Preferred: window.toast.{success,error,info}
        if (window.toast && typeof window.toast[type] === 'function') {
            window.toast[type](msg);
            return;
        }
        // Some pages expose `toast` globally
        if (typeof toast !== 'undefined' && toast && typeof toast[type] === 'function') {
            toast[type](msg);
            return;
        }
    } catch (e) {
        // fall through to inline toast
    }

    // Minimal inline fallback toast (no alert popups)
    try {
        const wrapId = 'e3backup-inline-toasts';
        let wrap = document.getElementById(wrapId);
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = wrapId;
            wrap.className = 'fixed top-4 right-4 z-[9999] space-y-2 pointer-events-none';
            document.body.appendChild(wrap);
        }
        const el = document.createElement('div');
        const isErr = type === 'error';
        el.className = [
            'pointer-events-auto',
            // Smaller + tighter toast
            'rounded-lg px-3 py-2 shadow-lg',
            'text-sm font-medium leading-snug',
            // Solid backgrounds (success = green, error = red)
            isErr ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'
        ].join(' ');
        el.textContent = msg || (isErr ? 'Error' : 'Success');
        wrap.appendChild(el);
        setTimeout(() => {
            el.classList.add('opacity-0');
            el.style.transition = 'opacity 250ms ease';
            setTimeout(() => el.remove(), 260);
        }, 2600);
    } catch (e) {
        // Last resort: do nothing (still better than alert spam)
        console[type === 'error' ? 'error' : 'log'](msg);
    }
}

function e3backupGetJobsApp() {
    const root = document.querySelector('[x-data="jobsApp()"]');
    if (!root) return null;
    // Alpine v3
    try {
        if (window.Alpine && typeof window.Alpine.$data === 'function') {
            return window.Alpine.$data(root);
        }
    } catch (e) {}
    // Alpine v2 fallback
    if (root.__x && root.__x.$data) return root.__x.$data;
    // Alpine v3 internals fallback
    if (root._x_dataStack && root._x_dataStack.length) return root._x_dataStack[0];
    return null;
}

function e3backupReloadJobs() {
    const app = e3backupGetJobsApp();
    if (app && typeof app.loadJobs === 'function') {
        return app.loadJobs();
    }
    console.warn('e3backupReloadJobs: jobsApp() not found/initialized yet');
    return null;
}

function jobsApp() {
    return {
        jobs: [],
        loading: true,
        tenantFilter: '',
        agentFilter: '',
        searchQuery: '',
        sourceFilter: 'all',
        statusFilter: 'all',
        // Delete confirmation modal state
        deleteModalOpen: false,
        deleteJobId: '',
        deleteJobName: '',
        deleteInProgress: false,
        agents: {/literal}{if $agents}{$agents|@json_encode}{else}[]{/if}{literal},
        tenantLabel() {
            if (!this.tenantFilter) return 'All Tenants';
            if (this.tenantFilter === 'direct') return 'Direct (No Tenant)';
            try {
                const sel = String(this.tenantFilter);
                const el = document.querySelector('[data-tenant-option="' + sel.replaceAll('"', '\\"') + '"]');
                const txt = el ? String(el.textContent || '').trim() : '';
                if (txt) return txt;
            } catch (e) {}
            return 'Tenant ' + this.tenantFilter;
        },
        agentLabel() {
            if (!this.agentFilter) return 'All Agents';
            const a = (this.agents || []).find(x => String(x.id) === String(this.agentFilter));
            return (a && a.hostname) ? a.hostname : ('Agent ' + this.agentFilter);
        },
        sourceLabel() {
            const v = (this.sourceFilter || 'all');
            const map = {
                all: 'All Sources',
                local_agent: 'Local Agent',
                cloud: 'Cloud-to-Cloud',
                s3_compatible: 'S3-Compatible',
                aws: 'AWS',
                sftp: 'SFTP',
                google_drive: 'Google Drive',
                dropbox: 'Dropbox',
            };
            return map[v] || 'All Sources';
        },
        get filteredAgents() {
            if (!this.tenantFilter || this.tenantFilter === 'direct') {
                return this.tenantFilter === 'direct'
                    ? this.agents.filter(a => !a.tenant_id)
                    : this.agents;
            }
            return this.agents.filter(a => String(a.tenant_id) === String(this.tenantFilter));
        },
        get filteredJobs() {
            const q = (this.searchQuery || '').toLowerCase().trim();
            const source = (this.sourceFilter || 'all').toLowerCase();
            const status = (this.statusFilter || 'all').toLowerCase();
            const now = Date.now();

            return (this.jobs || []).filter((job) => {
                const name = (job.name || '').toLowerCase();
                const sourceName = (job.source_display_name || '').toLowerCase();
                const sourceType = (job.source_type || '').toLowerCase();
                const dest = (this.formatDestination(job) || '').toLowerCase();
                const lastStatus = (job.last_run && job.last_run.status ? job.last_run.status : '').toLowerCase();
                const lastStartedRaw = job.last_run && job.last_run.started_at ? job.last_run.started_at : '';
                const lastStarted = lastStartedRaw ? Date.parse(lastStartedRaw) : NaN;

                const hay = (name + ' ' + sourceName + ' ' + sourceType + ' ' + dest).trim();
                if (q && hay.indexOf(q) === -1) return false;
                if (source !== 'all' && sourceType !== source) return false;

                if (status !== 'all') {
                    if (status === 'failed_recent') {
                        if (lastStatus !== 'failed' || isNaN(lastStarted)) return false;
                        if ((now - lastStarted) > (24 * 3600 * 1000)) return false;
                    } else if (status === 'running') {
                        if (!['running', 'starting', 'queued'].includes(lastStatus)) return false;
                    } else {
                        if (lastStatus !== status) return false;
                    }
                }
                return true;
            });
        },
        init() { this.loadJobs(); },
        onTenantChange() {
            this.agentFilter = '';
            this.loadJobs();
        },
        async loadJobs() {
            this.loading = true;
            try {
                let url = 'modules/addons/cloudstorage/api/e3backup_job_list.php';
                const params = new URLSearchParams();
                if (this.tenantFilter) params.append('tenant_id', this.tenantFilter);
                if (this.agentFilter) params.append('agent_id', this.agentFilter);
                if ([...params].length) url += '?' + params.toString();
                const res = await fetch(url);
                const data = await res.json();
                if (data.status === 'success') {
                    this.jobs = data.jobs || [];
                } else {
                    console.error(data.message);
                }
            } catch (e) {
                console.error('Failed to load jobs:', e);
            }
            this.loading = false;
        },
        openCreateJobModal() {
            window.openCreateJobModal();
        },
        // Job action handlers
        async runJob(jobId) {
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudbackup_start_run.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ job_id: jobId })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    const runParam = data.run_uuid || data.run_id;
                    e3backupNotify('success', 'Backup started! Redirecting to progress...');
                    setTimeout(() => {
                        window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=live&run_id=' + runParam;
                    }, 500);
                } else {
                    e3backupNotify('error', data.message || 'Failed to start backup');
                }
            } catch (e) {
                e3backupNotify('error', 'Error starting backup');
            }
        },
        async toggleJobStatus(jobId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'paused' : 'active';
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudbackup_update_job.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ job_id: jobId, status: newStatus })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    e3backupNotify('success', 'Job ' + (newStatus === 'paused' ? 'paused' : 'resumed'));
                    this.loadJobs(); // stay scoped to filters
                } else {
                    e3backupNotify('error', data.message || 'Failed to update job');
                }
            } catch (e) {
                e3backupNotify('error', 'Error updating job');
            }
        },
        async deleteJob(jobId, jobName) {
            // Backwards compatibility: route old direct calls to the modal flow
            this.openDeleteModal({ id: jobId, name: jobName });
        },
        openDeleteModal(job) {
            this.deleteJobId = job?.id ? String(job.id) : '';
            this.deleteJobName = job?.name ? String(job.name) : '';
            this.deleteInProgress = false;
            this.deleteModalOpen = true;
        },
        closeDeleteModal() {
            if (this.deleteInProgress) return;
            this.deleteModalOpen = false;
            this.deleteJobId = '';
            this.deleteJobName = '';
        },
        async confirmDeleteJob() {
            const jobId = this.deleteJobId;
            if (!jobId || this.deleteInProgress) return;
            this.deleteInProgress = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/cloudbackup_delete_job.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ job_id: jobId })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    e3backupNotify('success', 'Job deleted');
                    this.deleteModalOpen = false;
                    this.deleteInProgress = false;
                    this.deleteJobId = '';
                    this.deleteJobName = '';
                    this.loadJobs(); // keep current filters
                } else {
                    e3backupNotify('error', data.message || 'Failed to delete job');
                    this.deleteInProgress = false;
                }
            } catch (e) {
                e3backupNotify('error', 'Error deleting job');
                this.deleteInProgress = false;
            }
        },
        viewLogs(jobId) {
            window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=runs&job_id=' + encodeURIComponent(jobId);
        },
        openRestore(job) {
            // For Hyper-V jobs, redirect to the Hyper-V page to select a VM
            if (job.engine === 'hyperv') {
                window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=hyperv&job_id=' + encodeURIComponent(job.id);
                return;
            }
            this.goToRestores(job);
        },
        goToRestores(job) {
            if (!job) return;
            const isMsp = {/literal}{if $isMspClient}true{else}false{/if}{literal};
            const params = [];
            if (job.agent_id) params.push('agent_id=' + encodeURIComponent(job.agent_id));
            if (isMsp) {
                if (job.tenant_id) {
                    params.push('tenant_id=' + encodeURIComponent(job.tenant_id));
                } else {
                    params.push('tenant_id=direct');
                }
            }
            const qs = params.length ? '&' + params.join('&') : '';
            window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=restores' + qs;
        },
        openRestoreModal(jobId) {
            window.openRestoreModal(jobId);
        },
        statusClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'active') return 'bg-emerald-500/15 text-emerald-200';
            if (s === 'paused') return 'bg-amber-500/15 text-amber-200';
            return 'bg-slate-700 text-slate-300';
        },
        dotClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'active') return 'bg-emerald-400';
            if (s === 'paused') return 'bg-amber-400';
            return 'bg-slate-500';
        },
        sourceClass(type) {
            return 'bg-sky-500/15 text-sky-200';
        },
        formatEngine(engine) {
            const e = (engine || '').toLowerCase();
            if (e === 'kopia') return 'eazyBackup (Archive)';
            if (e === 'sync') return 'eazyBackup (Sync)';
            if (e === 'disk_image') return 'Disk Image';
            if (e === 'hyperv') return 'Hyper-V';
            return engine || '-';
        },
        formatSourceType(type) {
            const t = (type || '').toLowerCase();
            if (t === 'local_agent') return 'Local Agent';
            if (t === 's3_compatible') return 'S3-Compatible';
            if (t === 'google_drive') return 'Google Drive';
            if (t === 'dropbox') return 'Dropbox';
            if (t === 'aws') return 'AWS';
            if (t === 'sftp') return 'SFTP';
            if (t === 'cloud') return 'Cloud-to-Cloud';
            return type || '-';
        },
        formatDestination(job) {
            const name = job.dest_bucket_name
                ? job.dest_bucket_name
                : (job.dest_bucket_id ? ('Bucket #' + job.dest_bucket_id) : 'Bucket');
            const prefix = job.dest_prefix ? ('/' + job.dest_prefix) : '';
            return name + prefix;
        },
        formatDestinationName(job) {
            if (job.dest_bucket_name) return job.dest_bucket_name;
            if (job.dest_bucket_id) return 'Bucket #' + job.dest_bucket_id;
            return 'Bucket';
        },
        formatMode(job) {
            const mode = (job.backup_mode || '').toLowerCase();
            if (mode === 'archive') return 'Archive';
            if (mode === 'sync') return 'Sync';
            const engine = (job.engine || '').toLowerCase();
            if (engine === 'kopia') return 'Archive';
            if (engine) return engine;
            return 'Sync';
        },
        formatSchedule(type) {
            const t = (type || '').toLowerCase();
            if (t === 'manual') return 'Manual';
            if (t === 'daily') return 'Daily';
            if (t === 'weekly') return 'Weekly';
            if (t === 'hourly') return 'Hourly';
            if (t === 'cron') return 'Cron';
            return type || '-';
        },
        formatScheduleLabel(job) {
            const type = this.resolveScheduleType(job);
            const rawLabel = this.formatSchedule(type);
            const base = rawLabel && rawLabel !== '-' ? rawLabel : (type ? this.capitalize(type) : 'Schedule');
            const extras = [];
            if (type === 'weekly') {
                const weekdays = this.getWeekdayNames(job);
                if (weekdays.length) {
                    extras.push(weekdays.join(', '));
                }
            }
            const timeLabel = this.formatScheduleTimeLabel(type, job);
            if (timeLabel) {
                extras.push(timeLabel);
            }
            return extras.length ? base + ' · ' + extras.join(' · ') : base;
        },
        formatScheduleTimeLabel(type, job) {
            if (!type || type === 'manual') {
                return '';
            }
            if (type === 'hourly') {
                const minute = this.scheduleJsonNumber(job, 'minute');
                if (minute !== null) {
                    return 'at :' + String(minute).padStart(2, '0') + ' past the hour';
                }
            }
            const timeStr = this.resolveScheduleTime(job);
            const parts = this.parseScheduleTimeParts(timeStr);
            if (!parts) {
                return '';
            }
            if (type === 'hourly') {
                const minute = parts.minute ?? parts.hour ?? 0;
                return 'at :' + String(minute).padStart(2, '0') + ' past the hour';
            }
            if (parts.hour === null && parts.minute === null) {
                return '';
            }
            const hh = parts.hour !== null ? String(parts.hour).padStart(2, '0') : '00';
            const mm = parts.minute !== null ? String(parts.minute).padStart(2, '0') : '00';
            return 'at ' + hh + ':' + mm;
        },
        parseScheduleTimeParts(timeStr) {
            if (!timeStr) {
                return null;
            }
            const parts = String(timeStr).split(':');
            return {
                hour: this.parseScheduleNumber(parts[0]),
                minute: parts.length > 1 ? this.parseScheduleNumber(parts[1]) : null,
                second: parts.length > 2 ? this.parseScheduleNumber(parts[2]) : null,
            };
        },
        parseScheduleNumber(value) {
            if (value === undefined || value === null || value === '') {
                return null;
            }
            const num = parseInt(String(value).trim(), 10);
            return Number.isFinite(num) ? num : null;
        },
        resolveScheduleType(job) {
            const explicit = (job.schedule_type || '').toLowerCase().trim();
            if (explicit && explicit !== 'manual') {
                return explicit;
            }
            const jsonType = (this.scheduleJsonValue(job, 'type') || '').toLowerCase().trim();
            if (jsonType) {
                return jsonType;
            }
            return explicit || 'manual';
        },
        resolveScheduleTime(job) {
            const explicit = job.schedule_time;
            if (explicit) {
                return explicit;
            }
            return this.scheduleJsonValue(job, 'time') || '';
        },
        scheduleJsonValue(job, key) {
            const json = this.parseScheduleJson(job);
            if (!json || typeof json !== 'object') {
                return null;
            }
            return json[key] ?? null;
        },
        scheduleJsonNumber(job, key) {
            const value = this.scheduleJsonValue(job, key);
            return this.parseScheduleNumber(value);
        },
        parseScheduleJson(job) {
            if (!job) {
                return null;
            }
            if ('__parsedScheduleJson' in job) {
                return job.__parsedScheduleJson;
            }
            let parsed = null;
            const raw = job.schedule_json;
            if (raw) {
                if (typeof raw === 'string') {
                    try {
                        parsed = JSON.parse(raw);
                    } catch (e) {
                        parsed = null;
                    }
                } else if (typeof raw === 'object') {
                    parsed = raw;
                }
            }
            job.__parsedScheduleJson = parsed;
            return parsed;
        },
        getWeekdayNames(job) {
            const values = this.getWeekdayIndices(job);
            return values.map(day => this.weekdayName(day)).filter(Boolean);
        },
        getWeekdayIndices(job) {
            const json = this.parseScheduleJson(job);
            const weekdays = [];
            if (json && json.weekday) {
                if (Array.isArray(json.weekday)) {
                    weekdays.push(...json.weekday.map(v => this.parseScheduleNumber(v)));
                } else if (typeof json.weekday === 'string' && json.weekday.includes(',')) {
                    weekdays.push(...json.weekday.split(',').map(v => this.parseScheduleNumber(v)));
                } else {
                    weekdays.push(this.parseScheduleNumber(json.weekday));
                }
            }
            if (weekdays.length === 0 && job.schedule_weekday) {
                if (String(job.schedule_weekday).includes(',')) {
                    weekdays.push(...String(job.schedule_weekday).split(',').map(v => this.parseScheduleNumber(v)));
                } else {
                    weekdays.push(this.parseScheduleNumber(job.schedule_weekday));
                }
            }
            const filtered = weekdays
                .map((v) => (v !== null ? v : null))
                .filter((v) => v !== null && v >= 1 && v <= 7);
            return Array.from(new Set(filtered));
        },
        weekdayName(value) {
            const day = this.parseScheduleNumber(value);
            const map = {
                1: 'Monday',
                2: 'Tuesday',
                3: 'Wednesday',
                4: 'Thursday',
                5: 'Friday',
                6: 'Saturday',
                7: 'Sunday',
            };
            return map[day] || '';
        },
        nextRunText(job) {
            const type = this.resolveScheduleType(job);
            const time = this.resolveScheduleTime(job);
            const weekdayIdx = (this.getWeekdayIndices(job)[0] || '');
            const hourlyMinute = this.scheduleJsonNumber(job, 'minute');
            return computeNextRunText(type, time, weekdayIdx, hourlyMinute);
        },
        formatLastRunTime(job) {
            if (!job.last_run || !job.last_run.started_at) return '-';
            try {
                return fmtDateTime(new Date(job.last_run.started_at));
            } catch (e) {
                return job.last_run.started_at;
            }
        },
        lastRunStatusClass(status) {
            const s = (status || '').toLowerCase();
            if (s === 'success') return 'bg-emerald-500/10 text-emerald-300';
            if (s === 'failed') return 'bg-rose-500/15 text-rose-300';
            if (s === 'warning') return 'bg-amber-500/15 text-amber-300';
            if (s === 'cancelled') return 'bg-amber-500/15 text-amber-300';
            if (s === 'running' || s === 'starting') return 'bg-sky-500/10 text-sky-300';
            return 'bg-slate-500/15 text-slate-300';
        },
        capitalize(value) {
            if (!value) return '';
            return value.charAt(0).toUpperCase() + value.slice(1);
        },
        formatAgentHint(job) {
            const type = (job.source_type || '').toLowerCase();
            if (type === 'local_agent') {
                if (job.agent_hostname) return '(' + job.agent_hostname + ')';
                if (job.agent_id) return '(Agent #' + job.agent_id + ')';
                return '';
            }
            return type ? '(' + type + ')' : '';
        },
        editJob(job) {
            // Route to the correct edit UI based on source_type
            const sourceType = (job.source_type || '').toLowerCase();
            if (sourceType === 'local_agent') {
                // Open the Local Agent Job Wizard in edit mode
                if (typeof openLocalJobWizardForEdit === 'function') {
                    openLocalJobWizardForEdit(job.id);
                }
            } else {
                // Open the Cloud Backup Wizard in edit mode
                if (typeof openCloudBackupWizardForEdit === 'function') {
                    openCloudBackupWizardForEdit(job.id);
                } else {
                    // Fallback: show notification that edit is not yet implemented for cloud jobs
                    e3backupNotify('info', 'Cloud job editing coming soon. Use local agent wizard for now.');
                }
            }
        }
    }
}

function computeNextRunText(type, timeStr, weekday, hourlyMinute) {
    const scheduleType = (type || '').toLowerCase();
    if (!scheduleType || scheduleType === 'manual') return '-';

    const now = new Date();
    const timeParts = parseScheduleTimeString(timeStr);
    let next = new Date();
    next.setSeconds(0);

    if (scheduleType === 'hourly') {
        const minute = Number.isFinite(hourlyMinute) ? hourlyMinute : (timeParts.minute ?? timeParts.hour ?? 0);
        const safeMinute = minMax(minute, 0, 59);
        next.setMinutes(safeMinute, 0, 0);
        if (next <= now) {
            next.setHours(next.getHours() + 1);
        }
        return fmtDateTime(next);
    }

    if (scheduleType === 'daily') {
        const hh = timeParts.hour !== null ? timeParts.hour : 0;
        const mm = timeParts.minute !== null ? timeParts.minute : 0;
        next.setHours(hh, mm, 0, 0);
        if (next <= now) {
            next.setDate(next.getDate() + 1);
        }
        return fmtDateTime(next);
    }

    if (scheduleType === 'weekly') {
        const hh = timeParts.hour !== null ? timeParts.hour : 0;
        const mm = timeParts.minute !== null ? timeParts.minute : 0;
        let targetDay = parseInt(String(weekday || ''), 10);
        if (isNaN(targetDay) || targetDay < 1 || targetDay > 7) {
            targetDay = 0;
        }
        const jsTarget = targetDay ? (targetDay % 7) : 0;
        next.setHours(hh, mm, 0, 0);
        next.setDate(now.getDate() + ((7 + jsTarget - now.getDay()) % 7));
        if (next <= now) {
            next.setDate(next.getDate() + 7);
        }
        return fmtDateTime(next);
    }

    return '-';
}

function parseScheduleTimeString(timeStr) {
    if (!timeStr) {
        return { hour: null, minute: null };
    }
    const parts = String(timeStr).split(':');
    const hour = parseNullableInt(parts[0]);
    const minute = parts.length > 1 ? parseNullableInt(parts[1]) : null;
    return { hour, minute };
}

function parseNullableInt(value) {
    if (value === undefined || value === null || value === '') {
        return null;
    }
    const parsed = parseInt(String(value).trim(), 10);
    return Number.isFinite(parsed) ? parsed : null;
}

function minMax(value, min, max) {
    if (!Number.isFinite(value)) return min;
    if (value < min) return min;
    if (value > max) return max;
    return value;
}

function fmtDateTime(d) {
    try {
        const opts = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        return new Intl.DateTimeFormat(undefined, opts).format(d);
    } catch (e) {
        const pad = (n) => (n < 10 ? '0' + n : '' + n);
        return pad(d.getDate()) + ' ' + (d.toLocaleString('default',{month:'short'})) + ' ' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        const params = new URLSearchParams(window.location.search || '');
        if (params.get('open_create') === '1') {
            const prefillSourceRaw = params.get('prefill_source') || 'google_drive';
            const prefillSource = String(prefillSourceRaw || '').toLowerCase();

            if (prefillSource === 'local_agent') {
                if (typeof openLocalJobWizard === 'function') {
                    openLocalJobWizard();
                }

                const agentId = params.get('prefill_agent_id') || '';
                if (agentId && typeof localWizardSetAgentSelection === 'function') {
                    localWizardSetAgentSelection(agentId, `Agent #${agentId}`);
                }
                if (agentId && typeof localWizardOnAgentSelected === 'function') {
                    localWizardOnAgentSelected(agentId);
                }
            } else {
                if (typeof openCreateJobModal === 'function') {
                    openCreateJobModal();
                }
                const sel = document.getElementById('sourceType');
                if (sel) {
                    sel.value = prefillSourceRaw;
                    sel.dispatchEvent(new Event('change'));
                }
                setTimeout(() => {
                    const gf = document.getElementById('gdriveFields');
                    if (gf && gf.__x && gf.__x.$data && typeof gf.__x.$data.load === 'function') {
                        gf.__x.$data.load();
                    }
                }, 150);
            }

            const url = new URL(window.location.href);
            url.searchParams.delete('open_create');
            url.searchParams.delete('prefill_source');
            url.searchParams.delete('prefill_agent_id');
            url.searchParams.delete('tenant_id');
            window.history.replaceState({}, '', url.toString());
        }
    } catch (e) {}
});

// --- MSP Tenant Filter Component ---
function mspTenantFilter() {
    return {
        selectedTenant: '',
        allAgents: [],
        
        init() {
            // Read agents from data attribute
            const agentsData = this.$el.dataset.agents;
            try {
                this.allAgents = agentsData ? JSON.parse(agentsData) : [];
            } catch (e) {
                console.warn('Failed to parse agents data:', e);
                this.allAgents = [];
            }
            
            // Watch for tenant changes and update agent dropdown
            this.$watch('selectedTenant', () => {
                const agentSelect = document.getElementById('agent_id');
                if (agentSelect) {
                    agentSelect.innerHTML = '<option value="">Select an agent</option>';
                    this.filteredAgents.forEach(a => {
                        const opt = document.createElement('option');
                        opt.value = a.id;
                        opt.textContent = a.hostname ? (a.hostname + ' (ID ' + a.id + ')') : ('Agent #' + a.id);
                        agentSelect.appendChild(opt);
                    });
                }
            });
        },
        
        get filteredAgents() {
            if (!this.selectedTenant) return this.allAgents;
            if (this.selectedTenant === 'direct') return this.allAgents.filter(a => !a.tenant_id);
            return this.allAgents.filter(a => String(a.tenant_id) === String(this.selectedTenant));
        }
    };
}

// --- Job Creation Wizard Functions ---

// Cloud Backup Wizard (Slideover)
function openCloudBackupWizard() {
    const panel = document.getElementById('createJobSlideover');
    if (!panel) return;
    
    // Reset edit mode state when opening via this function (create mode)
    if (!window.cloudWizardState?.editMode) {
        window.cloudWizardState = { editMode: false, jobId: null };
        // Reset panel title for create mode
        const titleEl = panel.querySelector('h2');
        if (titleEl) {
            titleEl.textContent = 'Create Backup Job';
        }
        // Reset form fields
        const form = document.getElementById('createJobForm');
        if (form) form.reset();
    }
    
    panel.style.setProperty('display', 'block', 'important');
    const backdrop = panel.querySelector('.absolute.inset-0.bg-black\\/75');
    if (backdrop) backdrop.style.setProperty('display', 'block', 'important');
    const panelContent = panel.querySelector('.absolute.right-0.top-0');
    if (panelContent) panelContent.style.setProperty('display', 'block', 'important');
    if (window.Alpine) {
        try {
            if (!panel.__x && typeof Alpine.initTree === 'function') {
                Alpine.initTree(panel);
            }
            setTimeout(() => {
                if (panel.__x && panel.__x.$data) {
                    panel.__x.$data.isOpen = true;
                }
            }, 0);
        } catch (e) {}
    }
    applyInitialSourceState();
}

// Legacy alias for backwards compatibility
function openCreateJobModal() {
    openCloudBackupWizard();
}

function closeCreateSlideover() {
    const panel = document.getElementById('createJobSlideover');
    if (!panel) return;
    panel.style.setProperty('display', 'none', 'important');
    if (panel.__x && panel.__x.$data) {
        panel.__x.$data.isOpen = false;
    }
    // Reset edit mode state when closing
    window.cloudWizardState = { editMode: false, jobId: null };
}

// Cloud Wizard edit mode state
window.cloudWizardState = { editMode: false, jobId: null };

// Open Cloud Backup Wizard in edit mode
function openCloudBackupWizardForEdit(jobId) {
    if (!jobId) return;
    window.cloudWizardState = { editMode: true, jobId: jobId, loading: true };
    
    // Open the panel first
    openCloudBackupWizard();
    
    // Then fetch the job data and populate fields
    fetch('modules/addons/cloudstorage/api/cloudbackup_get_job.php?job_id=' + encodeURIComponent(jobId))
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                e3backupNotify('error', data.message || 'Failed to load job');
                closeCreateSlideover();
                return;
            }
            const job = data.job || {};
            const source = data.source || {};
            
            // Populate form fields
            cloudWizardFillFromJob(job, source);
            
            // Update panel title
            const titleEl = document.querySelector('#createJobSlideover h2');
            if (titleEl) {
                titleEl.textContent = 'Edit Backup Job';
            }
            
            window.cloudWizardState.loading = false;
        })
        .catch(err => {
            e3backupNotify('error', 'Error loading job: ' + err.message);
            closeCreateSlideover();
        });
}

// Fill Cloud Wizard fields from job data
function cloudWizardFillFromJob(job, source) {
    // Job name
    const nameEl = document.getElementById('jobName');
    if (nameEl) nameEl.value = job.name || '';
    
    // Source type
    const sourceTypeEl = document.getElementById('sourceType');
    if (sourceTypeEl) {
        sourceTypeEl.value = job.source_type || '';
        onSourceTypeChange(job.source_type || '');
    }
    
    // Fill source-specific fields based on type
    const type = (source.type || job.source_type || '').toLowerCase();
    if (type === 's3_compatible') {
        const endpointEl = document.getElementById('s3Endpoint');
        if (endpointEl) endpointEl.value = source.endpoint || '';
        const bucketEl = document.getElementById('s3Bucket');
        if (bucketEl) bucketEl.value = source.bucket || '';
        const regionEl = document.getElementById('s3Region');
        if (regionEl) regionEl.value = source.region || 'ca-central-1';
        // Access/Secret: don't prefill (security), show indicator if saved
    } else if (type === 'aws') {
        const bucketEl = document.getElementById('awsBucket');
        if (bucketEl) bucketEl.value = source.bucket || '';
        const regionEl = document.getElementById('awsRegion');
        if (regionEl) regionEl.value = source.region || 'us-east-1';
    } else if (type === 'sftp') {
        const hostEl = document.getElementById('sftpHost');
        if (hostEl) hostEl.value = source.host || '';
        const portEl = document.getElementById('sftpPort');
        if (portEl) portEl.value = source.port || '22';
        const userEl = document.getElementById('sftpUsername');
        if (userEl) userEl.value = source.user || '';
    }
    
    // Destination
    const destBucketEl = document.getElementById('destBucketId');
    if (destBucketEl) {
        destBucketEl.value = job.dest_bucket_id || '';
    }
    const destPrefixEl = document.getElementById('destPrefix');
    if (destPrefixEl) destPrefixEl.value = job.dest_prefix || '';
    
    // Schedule
    const scheduleTypeEl = document.getElementById('scheduleType');
    if (scheduleTypeEl) {
        scheduleTypeEl.value = job.schedule_type || 'manual';
        // Trigger schedule UI update
        scheduleTypeEl.dispatchEvent(new Event('change'));
    }
    const scheduleTimeEl = document.getElementById('scheduleTime');
    if (scheduleTimeEl) scheduleTimeEl.value = job.schedule_time || '';
    const scheduleWeekdayEl = document.getElementById('scheduleWeekday');
    if (scheduleWeekdayEl) scheduleWeekdayEl.value = job.schedule_weekday || '1';
    
    // Retention
    const retentionModeEl = document.getElementById('retentionMode');
    if (retentionModeEl) {
        retentionModeEl.value = job.retention_mode || 'none';
        onRetentionModeChange();
    }
    const retentionValueEl = document.getElementById('retentionValue');
    if (retentionValueEl) retentionValueEl.value = job.retention_value || '';
    
    // Backup mode
    const backupModeEl = document.getElementById('backupMode');
    if (backupModeEl) backupModeEl.value = job.backup_mode || 'sync';
}

function applyInitialSourceState() {
    const sourceType = document.getElementById('sourceType');
    if (sourceType) {
        onSourceTypeChange(sourceType.value);
    }
}

function onSourceTypeChange(value) {
    document.querySelectorAll('.source-type-fields').forEach(el => el.classList.add('hidden'));
    const warning = document.getElementById('sourceAccessWarning');
    if (warning) warning.classList.add('hidden');
    
    if (value === 's3_compatible') {
        document.getElementById('s3Fields')?.classList.remove('hidden');
        if (warning) warning.classList.remove('hidden');
    } else if (value === 'aws') {
        document.getElementById('awsFields')?.classList.remove('hidden');
        if (warning) warning.classList.remove('hidden');
    } else if (value === 'sftp') {
        document.getElementById('sftpFields')?.classList.remove('hidden');
    } else if (value === 'local_agent') {
        document.getElementById('localAgentFields')?.classList.remove('hidden');
    } else if (value === 'google_drive') {
        document.getElementById('gdriveFields')?.classList.remove('hidden');
    } else if (value === 'dropbox') {
        document.getElementById('dropboxFields')?.classList.remove('hidden');
    }
}

function onRetentionModeChange() {
    const modeEl = document.getElementById('retentionMode');
    const container = document.getElementById('retentionValueContainer');
    const help = document.getElementById('retentionHelp');
    if (!modeEl || !container) return;
    const mode = modeEl.value;
    if (mode === 'none') {
        container.classList.add('hidden');
        if (help) help.textContent = '';
    } else {
        container.classList.remove('hidden');
        if (help) {
            if (mode === 'keep_last_n') {
                help.textContent = 'Keep only the N most recent successful backup runs.';
            } else if (mode === 'keep_days') {
                help.textContent = 'Keep backup data for N days.';
            }
        }
    }
}

function showNoScheduleModal(callback) {
    const modal = document.getElementById('noScheduleModal');
    if (!modal) {
        callback();
        return;
    }
    window._noScheduleCallback = callback;
    modal.style.display = 'flex';
    if (modal.__x && modal.__x.$data) {
        modal.__x.$data.open = true;
    }
}

function hideNoScheduleModal() {
    const modal = document.getElementById('noScheduleModal');
    if (!modal) return;
    modal.style.display = 'none';
    if (modal.__x && modal.__x.$data) {
        modal.__x.$data.open = false;
    }
}

function confirmNoScheduleCreate() {
    hideNoScheduleModal();
    if (typeof window._noScheduleCallback === 'function') {
        window._noScheduleCallback();
    }
}

function doCreateJobSubmit(formEl) {
    const formData = new FormData(formEl);
    const sourceType = formData.get('source_type');
    
    let sourceConfig = {};
    let sourceDisplayName = '';
    let sourcePath = '';
    
    if (sourceType === 's3_compatible') {
        sourceConfig = {
            endpoint: formData.get('s3_endpoint'),
            access_key: formData.get('s3_access_key'),
            secret_key: formData.get('s3_secret_key'),
            bucket: formData.get('s3_bucket'),
            region: formData.get('s3_region') || 'ca-central-1'
        };
        sourceDisplayName = formData.get('source_display_name') || formData.get('s3_endpoint');
        sourcePath = formData.get('s3_path') || '';
    } else if (sourceType === 'aws') {
        sourceConfig = {
            access_key: formData.get('aws_access_key'),
            secret_key: formData.get('aws_secret_key'),
            bucket: formData.get('aws_bucket'),
            region: formData.get('aws_region')
        };
        sourceDisplayName = formData.get('aws_display_name') || 'AWS S3';
        sourcePath = formData.get('aws_path') || '';
    } else if (sourceType === 'sftp') {
        sourceConfig = {
            host: formData.get('sftp_host'),
            port: formData.get('sftp_port') || 22,
            user: formData.get('sftp_username'),
            pass: formData.get('sftp_password')
        };
        sourceDisplayName = formData.get('sftp_display_name') || formData.get('sftp_host');
        sourcePath = formData.get('sftp_path') || '';
    } else if (sourceType === 'local_agent') {
        sourceConfig = {
            include_glob: formData.get('local_include_glob'),
            exclude_glob: formData.get('local_exclude_glob'),
            bandwidth_limit_kbps: formData.get('local_bandwidth_limit_kbps')
        };
        sourceDisplayName = 'Local Agent';
        sourcePath = formData.get('local_source_path') || '';
    }
    
    formData.set('source_config', JSON.stringify(sourceConfig));
    formData.set('source_display_name', sourceDisplayName || 'Unnamed Source');
    formData.set('source_path', sourcePath);
    formData.set('engine', sourceType === 'local_agent' ? 'kopia' : 'sync');
    
    const msgEl = document.getElementById('jobCreationMessage');
    
    // Check if we're in edit mode
    const isEdit = window.cloudWizardState?.editMode && window.cloudWizardState?.jobId;
    if (isEdit) {
        formData.set('job_id', window.cloudWizardState.jobId);
    }
    
    const endpoint = isEdit 
        ? 'modules/addons/cloudstorage/api/cloudbackup_update_job.php'
        : 'modules/addons/cloudstorage/api/cloudbackup_create_job.php';
    
    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            closeCreateSlideover();
            // Reload jobs list (AJAX, no full page refresh)
            e3backupReloadJobs();
            e3backupNotify('success', isEdit ? 'Job updated successfully!' : 'Job created successfully!');
        } else {
            if (msgEl) {
                msgEl.textContent = data.message || (isEdit ? 'Failed to update job' : 'Failed to create job');
                msgEl.classList.remove('hidden');
            }
            e3backupNotify('error', data.message || (isEdit ? 'Failed to update job' : 'Failed to create job'));
        }
    })
    .catch(err => {
        if (msgEl) {
            msgEl.textContent = 'Error: ' + err.message;
            msgEl.classList.remove('hidden');
        }
        e3backupNotify('error', isEdit ? 'Error updating job' : 'Error creating job');
    });
}

// Schedule type change handler
document.addEventListener('DOMContentLoaded', function() {
    const scheduleType = document.getElementById('scheduleType');
    const scheduleOptions = document.getElementById('scheduleOptions');
    const weeklyOption = document.getElementById('weeklyOption');
    
    if (scheduleType) {
        scheduleType.addEventListener('change', function() {
            if (this.value === 'manual') {
                scheduleOptions?.classList.add('hidden');
                weeklyOption?.classList.add('hidden');
            } else {
                scheduleOptions?.classList.remove('hidden');
                if (this.value === 'weekly') {
                    weeklyOption?.classList.remove('hidden');
                } else {
                    weeklyOption?.classList.add('hidden');
                }
            }
        });
    }
    
    // Source type change handler
    const sourceType = document.getElementById('sourceType');
    if (sourceType) {
        sourceType.addEventListener('change', function() {
            onSourceTypeChange(this.value);
        });
    }
    
    // Retention mode change handler
    const retentionMode = document.getElementById('retentionMode');
    if (retentionMode) {
        retentionMode.addEventListener('change', onRetentionModeChange);
    }
    
    // Form submission
    const createForm = document.getElementById('createJobForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const scheduleTypeEl = document.getElementById('scheduleType');
            const stype = scheduleTypeEl ? (scheduleTypeEl.value || '').toLowerCase() : 'manual';
            if (stype === 'manual') {
                return showNoScheduleModal(() => doCreateJobSubmit(this));
            }
            doCreateJobSubmit(this);
        });
    }
});

// --- Restore Wizard Functions ---

window.restoreState = { jobId: null, step: 1, totalSteps: 3, runs: [], selectedRunId: '', targetPath: '', mount: false };

function openRestoreModal(jobId) {
    window.restoreState.jobId = jobId;
    window.restoreState.step = 1;
    window.restoreState.selectedRunId = '';
    window.restoreState.targetPath = '';
    window.restoreState.mount = false;
    const modal = document.getElementById('restoreWizardModal');
    if (modal) modal.classList.remove('hidden');
    loadRestoreRuns(jobId);
    updateRestoreView();
}

function closeRestoreModal() {
    const modal = document.getElementById('restoreWizardModal');
    if (modal) modal.classList.add('hidden');
}

function loadRestoreRuns(jobId) {
    const sel = document.getElementById('restoreRunSelect');
    if (sel) {
        sel.innerHTML = '<option value="">Loading runs…</option>';
    }
    fetch('modules/addons/cloudstorage/api/cloudbackup_list_runs.php?job_id=' + encodeURIComponent(jobId))
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                if (sel) sel.innerHTML = '<option value="">Failed to load runs</option>';
                return;
            }
            window.restoreState.runs = data.runs || [];
            if (sel) {
                sel.innerHTML = '';
                if (!window.restoreState.runs.length) {
                    sel.innerHTML = '<option value="">No runs available</option>';
                } else {
                    window.restoreState.runs.forEach((run) => {
                        const opt = document.createElement('option');
                        opt.value = String(run.id);
                        const ts = run.started_at ? (' @ ' + run.started_at) : '';
                        opt.textContent = `Run #${run.id} (${run.status})${ts} ${run.log_ref ? ' – manifest ' + run.log_ref : ''}`;
                        sel.appendChild(opt);
                    });
                }
            }
        })
        .catch(() => {
            if (sel) sel.innerHTML = '<option value="">Failed to load runs</option>';
        });
}

function restoreNext() {
    const st = window.restoreState;
    if (st.step === 1) {
        const sel = document.getElementById('restoreRunSelect');
        st.selectedRunId = sel ? sel.value : '';
        if (!st.selectedRunId) {
            if (window.toast) toast.error('Select a run/snapshot to restore');
            else alert('Select a run/snapshot to restore');
            return;
        }
    } else if (st.step === 2) {
        const tp = document.getElementById('restoreTargetPath');
        st.targetPath = tp ? (tp.value || '') : '';
        st.mount = document.getElementById('restoreMount')?.checked || false;
        if (!st.targetPath) {
            if (window.toast) toast.error('Target path is required');
            else alert('Target path is required');
            return;
        }
    }
    if (st.step < st.totalSteps) {
        st.step += 1;
        if (st.step === st.totalSteps) {
            buildRestoreReview();
        }
        updateRestoreView();
    } else {
        submitRestore();
    }
}

function restorePrev() {
    const st = window.restoreState;
    if (st.step > 1) {
        st.step -= 1;
        updateRestoreView();
    }
}

function updateRestoreView() {
    const st = window.restoreState;
    document.querySelectorAll('#restoreWizardModal .restore-step').forEach((el) => {
        const s = parseInt(el.getAttribute('data-step'), 10);
        if (s === st.step) el.classList.remove('hidden'); else el.classList.add('hidden');
    });
    const label = document.getElementById('restoreStepLabel');
    const title = document.getElementById('restoreStepTitle');
    if (label) label.textContent = `Step ${st.step} of ${st.totalSteps}`;
    if (title) {
        const titles = {1:'Select Snapshot',2:'Target',3:'Review'};
        title.textContent = titles[st.step] || 'Restore';
    }
}

function buildRestoreReview() {
    const st = window.restoreState;
    const run = (st.runs || []).find(r => String(r.run_uuid || r.id) === String(st.selectedRunId));
    const review = {
        run_uuid: st.selectedRunId,
        manifest_id: run ? (run.log_ref || '') : '',
        target_path: st.targetPath,
        mount: st.mount,
    };
    const el = document.getElementById('restoreReview');
    if (el) {
        el.textContent = JSON.stringify(review, null, 2);
    }
}

function submitRestore() {
    const st = window.restoreState;
    const run = (st.runs || []).find(r => String(r.run_uuid || r.id) === String(st.selectedRunId));
    const manifest = run ? (run.log_ref || '') : '';
    if (!manifest) {
        if (window.toast) toast.error('Selected run has no manifest (log_ref). Cannot restore.');
        else alert('Selected run has no manifest (log_ref). Cannot restore.');
        return;
    }
    
    const payload = {
        backup_run_id: st.selectedRunId,
        target_path: st.targetPath,
        mount: st.mount ? 'true' : 'false',
    };
    
    const submitBtn = document.querySelector('#restoreWizardModal button[onclick*="restoreNext"]');
    const originalText = submitBtn ? submitBtn.textContent : 'Submit';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Starting restore...';
    }
    
    fetch('modules/addons/cloudstorage/api/cloudbackup_start_restore.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload),
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            if (window.toast) toast.success('Restore started! Redirecting to progress view...');
            closeRestoreModal();
            
            const restoreRunParam = data.restore_run_uuid || data.restore_run_id;
            if (restoreRunParam) {
                setTimeout(() => {
                    window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=live&job_id=' + 
                        encodeURIComponent(data.job_id) + '&run_id=' + encodeURIComponent(restoreRunParam);
                }, 1000);
            }
        } else {
            if (window.toast) toast.error(data.message || 'Failed to start restore');
            else alert(data.message || 'Failed to start restore');
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

// ========================================
// Local Agent Job Wizard Functions
// ========================================

window.localWizardState = {
    step: 1,
    totalSteps: 5,
    data: {
        engine: 'kopia',
        dest_type: 's3',
        bucket_auto_create: true,
        tenant_id: '', // MSP: Tenant scope for the job
    },
    editMode: false,
    jobId: '',
    loading: false,
};

function resetLocalWizardFields() {
    window.localWizardState.data = {
        engine: 'kopia',
        dest_type: 's3',
        bucket_auto_create: true,
        source_paths: [],
        tenant_id: '',
        schedule_json: null, // Reset schedule data for new jobs
        retention_json: null, // Reset retention data for new jobs
    };
    const idsToClear = [
        'localWizardName','localWizardAgentId','localWizardBucketId','localWizardPrefix',
        'localWizardLocalPath','localWizardSource','localWizardSourcePaths','localWizardInclude','localWizardExclude',
        'localWizardTime','localWizardCron','localWizardRetention','localWizardPolicy',
        'localWizardDiskVolume','localWizardDiskTemp','localWizardTenantId'
    ];
    idsToClear.forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const week = document.getElementById('localWizardWeekday');
    if (week) week.value = '1';
    const sched = document.getElementById('localWizardScheduleType');
    if (sched) sched.value = 'manual';
    const bw = document.getElementById('localWizardBandwidth');
    if (bw) bw.value = '0';
    const par = document.getElementById('localWizardParallelism');
    if (par) par.value = '8';
    const comp = document.getElementById('localWizardCompression');
    if (comp) comp.value = 'none';
    const dbg = document.getElementById('localWizardDebugLogs');
    if (dbg) dbg.checked = false;
    const diskFormat = document.getElementById('localWizardDiskFormat');
    if (diskFormat) diskFormat.value = 'vhdx';
    // Reset Agent dropdown via Alpine v3 state
    const agentRoot = document.querySelector('#localWizardAgentId')?.closest('[x-data]');
    if (agentRoot && agentRoot._x_dataStack) {
        const data = agentRoot._x_dataStack[0];
        if (data) {
            data.selectedId = '';
            data.selectedAgent = null;
        }
    }
    const bucketBtn = document.getElementById('localWizardBucketId')?.parentElement?.querySelector('button .block');
    if (bucketBtn) bucketBtn.textContent = 'Choose where to store your backups';
    localWizardSet('engine', 'kopia');
}

function openLocalJobWizard(opts = {}) {
    const modal = document.getElementById('localJobWizardModal');
    if (!modal) return;
    resetLocalWizardFields();
    window.localWizardState.editMode = !!opts.editMode;
    window.localWizardState.jobId = opts.jobId || '';
    window.localWizardState.loading = !!opts.loading;
    modal.classList.remove('hidden');
    window.localWizardState.step = 1;
    localWizardUpdateView();
    if (opts.job) {
        localWizardFillFromJob(opts.job, opts.source || {});
    }
}

function closeLocalJobWizard() {
    const modal = document.getElementById('localJobWizardModal');
    if (modal) modal.classList.add('hidden');
    window.localWizardState.editMode = false;
    window.localWizardState.jobId = '';
    window.localWizardState.loading = false;
    resetLocalWizardFields();
}

function openLocalJobWizardForEdit(jobId) {
    const modal = document.getElementById('localJobWizardModal');
    if (!modal) return;
    window.localWizardState.loading = true;
    openLocalJobWizard({ editMode: true, jobId, loading: true });
    fetch('modules/addons/cloudstorage/api/cloudbackup_get_job.php?job_id=' + encodeURIComponent(jobId))
        .then((r) => r.json())
        .then((data) => {
            if (data.status !== 'success' || !data.job) {
                window.toast?.error?.(data.message || 'Failed to load job');
                closeLocalJobWizard();
                return;
            }
            const j = data.job;
            const s = data.source || {};
            if ((j.source_type || '').toLowerCase() !== 'local_agent') {
                closeLocalJobWizard();
                openCloudBackupWizard();
                return;
            }
            localWizardFillFromJob(j, s);
        })
        .catch((err) => {
            window.toast?.error?.('Failed to load job: ' + err);
            closeLocalJobWizard();
        })
        .finally(() => {
            window.localWizardState.loading = false;
            localWizardUpdateView();
        });
}

function localWizardSetAgentSelection(agentId, agentLabel, agentObj) {
    const hid = document.getElementById('localWizardAgentId');
    if (hid) hid.value = agentId || '';
    // Update Alpine v3 state (do NOT manipulate DOM directly as it destroys Alpine children)
    const root = hid?.closest('[x-data]');
    if (root && root._x_dataStack) {
        try {
            const data = root._x_dataStack[0];
            if (data) {
                data.selectedId = agentId || '';
                // Try to find matching agent from loaded list for full object with online_status
                let agent = agentObj || null;
                if (!agent && agentId && data.allAgents) {
                    agent = data.allAgents.find(a => String(a.id) === String(agentId));
                }
                // Fallback: create minimal agent object if not found
                if (!agent && agentId) {
                    agent = { id: agentId, hostname: agentLabel?.replace(/ \(ID \d+\)$/, '') || '', online_status: 'offline' };
                }
                data.selectedAgent = agent;
            }
        } catch (e) {}
    }
}

function localWizardFillFromJob(j, s) {
    const source = s || {};
    const job = j || {};
    const engineVal = (job.engine || '').toLowerCase();
    if (engineVal === 'disk_image') {
        localWizardSet('engine', 'disk_image');
    } else if (engineVal === 'hyperv') {
        localWizardSet('engine', 'hyperv');
    } else {
        localWizardSet('engine', job.backup_mode === 'sync' ? 'sync' : 'kopia');
    }
    const nameEl = document.getElementById('localWizardName');
    if (nameEl) nameEl.value = job.name || '';

    const agentLabel = job.agent_hostname
        ? `${job.agent_hostname} (ID ${job.agent_id || ''})`
        : (job.agent_id ? `Agent #${job.agent_id}` : 'Select agent');
    localWizardSetAgentSelection(job.agent_id || '', agentLabel);

    const bucketHidden = document.getElementById('localWizardBucketId');
    if (bucketHidden) {
        bucketHidden.value = job.dest_bucket_id || '';
        const bucketBtnLabel = bucketHidden.parentElement?.querySelector('button .block');
        if (bucketBtnLabel) {
    const name = job.dest_bucket_name || (job.dest_bucket_id ? `Bucket #${job.dest_bucket_id}` : 'Choose where to store your backups');
            bucketBtnLabel.textContent = name;
        }
    }
    const prefixEl = document.getElementById('localWizardPrefix');
    if (prefixEl) prefixEl.value = job.dest_prefix || '';
    const localPathEl = document.getElementById('localWizardLocalPath');
    if (localPathEl) localPathEl.value = job.dest_local_path || '';
    const srcEl = document.getElementById('localWizardSource');
    if (srcEl) srcEl.value = job.source_path || '';
    const pathsHidden = document.getElementById('localWizardSourcePaths');
    let parsedPaths = [];
    if (job.source_paths_json) {
        const parsed = safeParseJSON(job.source_paths_json);
        if (Array.isArray(parsed)) {
            parsedPaths = parsed;
        }
    }
    if (!parsedPaths.length && job.source_path) {
        parsedPaths = [job.source_path];
    }
    if (pathsHidden) {
        pathsHidden.value = JSON.stringify(parsedPaths);
    }
    if (window.localWizardState?.data) {
        window.localWizardState.data.source_paths = parsedPaths;
        window.localWizardState.data.source_path = parsedPaths[0] || job.source_path || '';
    }
    
    // Dispatch event to tell fileBrowser to reload selected paths from hidden input
    setTimeout(() => {
        window.dispatchEvent(new CustomEvent('edit-paths-loaded'));
    }, 100);
    
    const diskVolEl = document.getElementById('localWizardDiskVolume');
    const diskVolumeValue = parseStoredVolume(job.disk_source_volume || '');
    if (diskVolEl) diskVolEl.value = diskVolumeValue;
    if (window.localWizardState?.data) {
        window.localWizardState.data.disk_source_volume = diskVolumeValue;
    }
    const diskFmtEl = document.getElementById('localWizardDiskFormat');
    if (diskFmtEl) diskFmtEl.value = (job.disk_image_format || 'vhdx');
    const diskTempEl = document.getElementById('localWizardDiskTemp');
    if (diskTempEl) diskTempEl.value = job.disk_temp_dir || '';
    const incEl = document.getElementById('localWizardInclude');
    if (incEl) incEl.value = source.include_glob || job.local_include_glob || '';
    const excEl = document.getElementById('localWizardExclude');
    if (excEl) excEl.value = source.exclude_glob || job.local_exclude_glob || '';
    const bwEl = document.getElementById('localWizardBandwidth');
    const bwVal = source.bandwidth_limit_kbps || job.local_bandwidth_limit_kbps || job.bandwidth_limit_kbps || '0';
    if (bwEl) bwEl.value = bwVal;
    const policyObj = job.policy_json ? (safeParseJSON(job.policy_json) || {}) : {};
    const parEl = document.getElementById('localWizardParallelism');
    const parVal = job.parallelism || policyObj.parallel_uploads || '16';
    if (parEl) parEl.value = parVal;
    const compEl = document.getElementById('localWizardCompression');
    const compVal = policyObj.compression || 'zstd-default';
    if (compEl) compEl.value = compVal;
    const dbgEl = document.getElementById('localWizardDebugLogs');
    const dbgVal = !!policyObj.debug_logs;
    if (dbgEl) dbgEl.checked = dbgVal;
    const pdrEl = document.getElementById('localWizardParallelDiskReads');
    const pdrVal = policyObj.parallel_disk_reads !== false;
    if (pdrEl) pdrEl.checked = pdrVal;
    
    // Store network credentials flags for edit mode (preserve secrets)
    if (window.localWizardState?.data) {
        window.localWizardState.data.has_network_password = !!source.has_network_password;
        window.localWizardState.data.network_domain = source.network_domain || '';
    }
    
    // Auto-expand advanced settings if any non-default values are loaded
    const hasNonDefaultAdvanced = (
        (parseInt(bwVal, 10) !== 0) ||
        (parseInt(parVal, 10) !== 16) ||
        (compVal !== 'zstd-default') ||
        dbgVal ||
        (pdrVal === false)
    );
    if (hasNonDefaultAdvanced) {
        // Try to find and expand the advanced toggle via Alpine
        setTimeout(() => {
            const advancedToggle = document.querySelector('#localJobWizardModal [data-step="4"] [x-data]');
            if (advancedToggle && advancedToggle.__x && advancedToggle.__x.$data) {
                advancedToggle.__x.$data.showAdvanced = true;
            }
        }, 100);
    }
    
    const schedType = document.getElementById('localWizardScheduleType');
    if (schedType) schedType.value = job.schedule_type || (job.schedule_json?.type) || 'manual';
    const schedTime = document.getElementById('localWizardTime');
    if (schedTime) schedTime.value = job.schedule_time || (job.schedule_json?.time) || '';
    const schedWeek = document.getElementById('localWizardWeekday');
    // Handle weekday as array or single value
    let weekdayVal = '1';
    const jobSchedJson = typeof job.schedule_json === 'string' ? safeParseJSON(job.schedule_json) : job.schedule_json;
    if (jobSchedJson?.weekday) {
        if (Array.isArray(jobSchedJson.weekday) && jobSchedJson.weekday.length > 0) {
            weekdayVal = String(Math.min(...jobSchedJson.weekday));
        } else {
            weekdayVal = String(jobSchedJson.weekday);
        }
    } else if (job.schedule_weekday) {
        weekdayVal = String(job.schedule_weekday);
    }
    if (schedWeek) schedWeek.value = weekdayVal;
    const schedCron = document.getElementById('localWizardCron');
    if (schedCron) schedCron.value = job.schedule_cron || (jobSchedJson?.cron) || '';
    
    // Store schedule_json in state for Alpine component to initialize from
    if (window.localWizardState?.data) {
        window.localWizardState.data.schedule_json = jobSchedJson || {
            type: job.schedule_type || 'manual',
            time: job.schedule_time || '',
            weekday: job.schedule_weekday || weekdayVal,
            cron: job.schedule_cron || ''
        };
    }
    
    // Store retention_json in state for Alpine component to initialize from
    let retentionObj = null;
    if (job.retention_json) {
        retentionObj = typeof job.retention_json === 'string' 
            ? safeParseJSON(job.retention_json) 
            : job.retention_json;
    }
    if (window.localWizardState?.data) {
        window.localWizardState.data.retention_json = retentionObj;
    }
    // Set hidden input for Alpine x-model binding
    const retTxt = document.getElementById('localWizardRetention');
    if (retTxt) {
        retTxt.value = retentionObj ? JSON.stringify(retentionObj) : '';
    }
    if (job.agent_id) {
        // When loading an existing job for edit, preserve any preloaded selections
        // (otherwise the file browser clears them when the agent-selected event fires).
        localWizardOnAgentSelected(job.agent_id, { preserveSelection: true });
    }
    localWizardBuildReview();
}

function localWizardSet(key, val) {
    window.localWizardState.data[key] = val;
    if (key === 'engine') {
        const buttons = document.querySelectorAll('[data-engine-btn]');
        buttons.forEach((btn) => {
            const e = btn.getAttribute('data-engine-btn');
            if (e === val) {
                btn.classList.add('selected', 'ring-2', 'ring-cyan-500', 'border-cyan-500/50', 'bg-slate-800');
            } else {
                btn.classList.remove('selected', 'ring-2', 'ring-cyan-500', 'border-cyan-500/50', 'bg-slate-800');
            }
        });
        window.dispatchEvent(new CustomEvent('engine-changed', { detail: { engine: val } }));
        localWizardUpdateView();
    }
}

const localWizardVolumeState = {
    volumes: [],
    updatedAt: '',
    loading: false,
    lastAgentId: '',
};

function localWizardOnAgentSelected(agentId, opts = {}) {
    if (!agentId) return;
    localWizardVolumeState.lastAgentId = agentId;
    window.dispatchEvent(new CustomEvent('local-agent-selected', { detail: { agentId, preserveSelection: !!opts.preserveSelection } }));
    localWizardUpdateView();
}

function localWizardFormatVolumeLabel(v) {
    const parts = [];
    if (v.path) parts.push(v.path);
    if (v.label) parts.push(v.label);
    if (v.size_bytes) parts.push(localWizardFormatBytes(v.size_bytes));
    return parts.join(' — ');
}

function localWizardFormatBytes(n) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let val = Number(n);
    let idx = 0;
    while (val >= 1024 && idx < units.length - 1) {
        val /= 1024;
        idx += 1;
    }
    return `${val.toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
}

// Alpine.js component backing the remote filesystem browser
function fileBrowser() {
    return {
        loading: false,
        error: null,
        currentPath: '',
        parentPath: '',
        entries: [],
        disks: [],
        selectedPaths: [],
        networkPathsInfo: [],
        manualPath: '',
        agentId: '',
        networkUsername: '',
        networkPassword: '',
        networkDomain: '',
        selectedVolume: '',
        selectedVolumeInfo: null,

        get isDiskImageMode() {
            return window.localWizardState?.data?.engine === 'disk_image';
        },

        // File/folder browse UX:
        // - At root ("This PC"), show drive cards and hide checkboxes.
        // - Inside a drive/folder, show the checkbox-based selector list.
        get isBrowseRoot() {
            return !this.isDiskImageMode && this.currentPath === '';
        },

        get showSelectionCheckboxes() {
            return !this.isDiskImageMode && this.currentPath !== '';
        },

        get rootBrowseDrives() {
            if (!this.isBrowseRoot) return [];
            return this.entries.filter(e => e && e.icon === 'drive' && e.is_dir);
        },

        get localVolumes() {
            if (this.currentPath !== '') return [];
            if (this.isDiskImageMode) {
                return Array.isArray(this.disks) ? this.disks : [];
            }
            return this.entries.filter(e => {
                if (e.icon !== 'drive') return false;
                if (e.is_network) return false;
                if (e.type === 'network') return false;
                if (e.path && e.path.startsWith('\\\\')) return false;
                if (e.unc_path && e.unc_path !== '') return false;
                return true;
            });
        },

        get hasNetworkPaths() {
            return this.selectedPaths.some(path => this.isNetworkPath(path));
        },

        isNetworkPath(path) {
            if (path && path.startsWith('\\\\')) return true;
            return this.networkPathsInfo.some(info => info.path === path && info.is_network);
        },

        selectVolume(entry) {
            this.selectedVolume = entry.path;
            this.selectedVolumeInfo = entry;
            this.syncDiskVolumeToWizard();
        },

        normalizeDiskVolumePath(value) {
            if (!value) return '';
            return String(value).trim().replace(/[\\/]+$/, '').toLowerCase();
        },

        extractDriveLetter(value) {
            if (!value) return '';
            const match = String(value).trim().match(/([A-Za-z]):/);
            return match ? match[1].toLowerCase() : '';
        },

        getVolumeCandidates(entry) {
            if (!entry) return [];
            const candidates = [];
            if (entry.path) candidates.push(entry.path);
            if (entry.name) candidates.push(entry.name);
            if (entry.label) candidates.push(entry.label);
            if (entry.unc_path) candidates.push(entry.unc_path);
            return candidates;
        },

        matchesSelectedVolume(entry) {
            if (!entry) return false;
            const selected = this.selectedVolume || '';
            if (!selected) return false;
            const selectedDrive = this.extractDriveLetter(selected);
            const normalizedSelected = this.normalizeDiskVolumePath(selected);
            if (!selectedDrive && !normalizedSelected) return false;
            const candidates = this.getVolumeCandidates(entry);
            for (const candidate of candidates) {
                const candidateDrive = this.extractDriveLetter(candidate);
                if (selectedDrive && candidateDrive && selectedDrive === candidateDrive) {
                    return true;
                }
                const normalizedCandidate = this.normalizeDiskVolumePath(candidate);
                if (normalizedCandidate && normalizedSelected && normalizedCandidate === normalizedSelected) {
                    return true;
                }
            }
            return false;
        },

        restoreDiskVolumeSelection() {
            const rawLatest = document.getElementById('localWizardDiskVolume')?.value
                || window.localWizardState?.data?.disk_source_volume
                || '';
            const latest = parseStoredVolume(rawLatest);
            if (latest && latest !== this.selectedVolume) {
                this.selectedVolume = latest;
            }
            if (!this.selectedVolume) return;
            const match = (this.localVolumes || []).find(entry => this.matchesSelectedVolume(entry));
            if (match) {
                if (match.path) {
                    this.selectedVolume = match.path;
                }
                this.selectedVolumeInfo = match;
                this.syncDiskVolumeToWizard();
            }
        },

        isVolumeEntrySelected(entry) {
            return this.matchesSelectedVolume(entry);
        },

        syncDiskVolumeToWizard() {
            const volume = this.selectedVolume || '';
            // Always update wizard state first (most reliable)
            if (window.localWizardState?.data) {
                window.localWizardState.data.disk_source_volume = volume;
            }
            // Then update input element if it exists
            const input = document.getElementById('localWizardDiskVolume');
            if (input) {
                input.value = volume;
            }
            // Trigger review rebuild to ensure it's captured
            if (typeof localWizardBuildReview === 'function') {
                localWizardBuildReview();
            }
        },

        get pathSegments() {
            if (!this.currentPath) return [];
            const sep = this.currentPath.includes('\\') ? '\\' : '/';
            const parts = this.currentPath.split(sep).filter(Boolean);
            let acc = '';
            return parts.map((p, idx) => {
                acc += (idx === 0 && sep === '\\') ? (p + sep) : (sep + p);
                return { name: p, path: acc };
            });
        },

        init() {
            this.agentId = document.getElementById('localWizardAgentId')?.value || '';
            const preset = document.getElementById('localWizardSourcePaths')?.value || '';
            if (preset) {
                try {
                    const parsed = JSON.parse(preset);
                    if (Array.isArray(parsed)) {
                        this.selectedPaths = parsed;
                    }
                } catch (e) {}
            }
            const diskVolume = document.getElementById('localWizardDiskVolume')?.value || '';
            if (diskVolume) {
                this.selectedVolume = diskVolume;
            }
            if (this.agentId) {
                if (this.isDiskImageMode) {
                    this.loadDisks();
                } else {
                    this.loadDirectory('');
                }
            } else {
                this.error = 'Select an agent to browse.';
            }
            window.addEventListener('local-agent-selected', (e) => {
                this.agentId = e.detail?.agentId || '';
                const preserve = !!e.detail?.preserveSelection;
                if (!preserve) {
                    this.selectedPaths = [];
                } else {
                    // Hydrate from hidden input/state if present
                    const preset = document.getElementById('localWizardSourcePaths')?.value || '';
                    if (preset) {
                        try {
                            const parsed = JSON.parse(preset);
                            if (Array.isArray(parsed)) {
                                this.selectedPaths = parsed;
                            }
                        } catch (e2) {}
                    } else if (Array.isArray(window.localWizardState?.data?.source_paths)) {
                        this.selectedPaths = [...window.localWizardState.data.source_paths];
                    }
                }
                const diskVolumeFromInput = document.getElementById('localWizardDiskVolume')?.value || '';
                this.selectedVolume = preserve ? (diskVolumeFromInput || this.selectedVolume) : '';
                this.selectedVolumeInfo = null;
                if (preserve) {
                    this.restoreDiskVolumeSelection();
                }
                // Only overwrite hidden inputs when not preserving selections (edit mode preload).
                if (!preserve) {
                    this.syncToWizard();
                }
                this.syncDiskVolumeToWizard();
                if (this.agentId) {
                    this.loadDirectory('');
                } else {
                    this.error = 'Select an agent to browse.';
                }
            });
            window.addEventListener('refresh-browser', () => {
                const path = this.isDiskImageMode ? '' : (this.currentPath || '');
                this.loadDirectory(path);
            });
            // Reload selected paths from hidden input (for edit mode)
            window.addEventListener('edit-paths-loaded', () => {
                const preset = document.getElementById('localWizardSourcePaths')?.value || '';
                if (preset) {
                    try {
                        const parsed = JSON.parse(preset);
                        if (Array.isArray(parsed)) {
                            this.selectedPaths = parsed;
                        }
                    } catch (e) {}
                }
                const diskVolume = document.getElementById('localWizardDiskVolume')?.value || '';
                if (diskVolume) {
                    this.selectedVolume = diskVolume;
                }
                this.restoreDiskVolumeSelection();
            });
            window.addEventListener('engine-changed', () => {
                if (this.isDiskImageMode) {
                    this.selectedPaths = [];
                    this.syncToWizard();
                } else {
                    this.selectedVolume = '';
                    this.selectedVolumeInfo = null;
                    this.syncDiskVolumeToWizard();
                }
                if (this.isDiskImageMode) {
                    this.loadDisks();
                } else {
                    this.loadDirectory('');
                }
            });
        },

        async loadDirectory(path) {
            if (this.isDiskImageMode) {
                return this.loadDisks();
            }
            if (!this.agentId) {
                this.error = 'Select an agent to browse.';
                return;
            }
            this.currentPath = path || '';
            this.parentPath = path ? '' : this.parentPath;
            this.entries = [];
            this.loading = true;
            this.error = null;
            try {
                const resp = await fetch(`modules/addons/cloudstorage/api/agent_browse_filesystem.php?agent_id=${this.agentId}&path=${encodeURIComponent(path || '')}`);
                const text = await resp.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    this.error = `Browse failed (non-JSON response): ${text.slice(0, 120)}...`;
                    return;
                }
                if (data.status === 'success') {
                    const res = data.data || {};
                    this.currentPath = res.path || '';
                    this.parentPath = res.parent || '';
                    this.entries = Array.isArray(res.entries) ? res.entries : [];
                    this.restoreDiskVolumeSelection();
                } else {
                    this.error = data.message || 'Failed to load directory';
                }
            } catch (e) {
                this.error = e.message || 'Network error';
            } finally {
                this.loading = false;
                this.syncToWizard();
            }
        },

        async loadDisks() {
            if (!this.agentId) {
                this.error = 'Select an agent to browse.';
                return;
            }
            this.currentPath = '';
            this.parentPath = '';
            this.entries = [];
            this.disks = [];
            this.loading = true;
            this.error = null;
            const maxAttempts = 3;
            const retryDelayMs = 2500;
            const delay = (ms) => new Promise(r => setTimeout(r, ms));
            for (let attempt = 1; attempt <= maxAttempts; attempt++) {
                try {
                    const resp = await fetch(`modules/addons/cloudstorage/api/agent_list_disks.php?agent_id=${this.agentId}`);
                    const text = await resp.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        this.error = `Disk list failed (non-JSON response): ${text.slice(0, 120)}...`;
                        break;
                    }
                    if (data.status === 'success') {
                        const res = data.data || {};
                        this.disks = Array.isArray(res.disks) ? res.disks : [];
                        this.restoreDiskVolumeSelection();
                        break;
                    }
                    const isTimeout = resp.status === 504 || (data.message && String(data.message).indexOf('Timeout') !== -1);
                    if (isTimeout && attempt < maxAttempts) {
                        this.error = 'Taking longer than usual, retrying…';
                        this.syncToWizard();
                        await delay(retryDelayMs);
                        this.error = null;
                        continue;
                    }
                    this.error = data.message || 'Failed to load disks';
                    break;
                } catch (e) {
                    if (attempt < maxAttempts) {
                        this.error = 'Taking longer than usual, retrying…';
                        this.syncToWizard();
                        await delay(retryDelayMs);
                        this.error = null;
                        continue;
                    }
                    this.error = e.message || 'Network error';
                    break;
                }
            }
            this.loading = false;
            this.syncToWizard();
        },

        navigateTo(path) {
            this.loadDirectory(path || '');
        },

        retry() {
            this.loadDirectory(this.currentPath || '');
        },

        isSelected(path) {
            return this.selectedPaths.includes(path);
        },

        toggleSelection(entry) {
            const path = entry.path;
            if (!path) return;
            if (this.isSelected(path)) {
                this.selectedPaths = this.selectedPaths.filter((p) => p !== path);
                this.networkPathsInfo = this.networkPathsInfo.filter((info) => info.path !== path);
            } else {
                this.selectedPaths = [...this.selectedPaths, path];
                if (entry.is_network || (entry.unc_path && entry.unc_path !== '')) {
                    this.networkPathsInfo.push({
                        path: path,
                        is_network: true,
                        unc_path: entry.unc_path || path
                    });
                }
            }
            this.syncToWizard();
        },

        removeSelection(path) {
            this.selectedPaths = this.selectedPaths.filter((p) => p !== path);
            this.syncToWizard();
        },

        addManualPath() {
            const p = (this.manualPath || '').trim();
            if (!p) return;
            if (!this.selectedPaths.includes(p)) {
                this.selectedPaths.push(p);
            }
            this.manualPath = '';
            this.syncToWizard();
        },

        syncToWizard() {
            const srcInput = document.getElementById('localWizardSource');
            const pathsInput = document.getElementById('localWizardSourcePaths');
            const first = this.selectedPaths[0] || '';
            if (srcInput) srcInput.value = first;
            if (pathsInput) pathsInput.value = JSON.stringify(this.selectedPaths);
            if (window.localWizardState?.data) {
                window.localWizardState.data.source_path = first;
                window.localWizardState.data.source_paths = [...this.selectedPaths];
            }
            this.syncCredentials();
        },

        syncCredentials() {
            if (window.localWizardState?.data && this.hasNetworkPaths) {
                window.localWizardState.data.network_username = this.networkUsername;
                window.localWizardState.data.network_password = this.networkPassword;
                window.localWizardState.data.network_domain = this.networkDomain;
            } else if (window.localWizardState?.data) {
                window.localWizardState.data.network_username = '';
                window.localWizardState.data.network_password = '';
                window.localWizardState.data.network_domain = '';
            }
        },

        formatBytes(n) {
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            let val = Number(n || 0);
            let idx = 0;
            while (val >= 1024 && idx < units.length - 1) {
                val /= 1024;
                idx += 1;
            }
            return `${val.toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
        },
    };
}

// Alpine.js component for Hyper-V VM browser
function hypervBrowser() {
    return {
        loading: false,
        error: null,
        vms: [],
        selectedVMs: [],
        agentId: '',
        detailsLoadingIds: [],

        init() {
            this.agentId = document.getElementById('localWizardAgentId')?.value || '';
            // Load existing selections if any
            const preset = document.getElementById('localWizardHypervVMs')?.value || '';
            if (preset) {
                try {
                    const parsed = JSON.parse(preset);
                    if (Array.isArray(parsed)) {
                        this.selectedVMs = parsed;
                    }
                } catch (e) {}
            }
            if (this.agentId) {
                this.loadVMs();
            } else {
                this.error = 'Select an agent to discover VMs.';
            }
            // Listen for agent selection
            window.addEventListener('local-agent-selected', (e) => {
                this.agentId = e.detail?.agentId || '';
                this.selectedVMs = [];
                this.vms = [];
                this.syncToWizard();
                if (this.agentId) {
                    this.loadVMs();
                } else {
                    this.error = 'Select an agent to discover VMs.';
                }
            });
            // Listen for refresh
            window.addEventListener('refresh-hyperv-vms', () => {
                if (this.agentId) {
                    this.loadVMs();
                }
            });
        },

        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },

        async requestJSON(url, options = {}) {
            const resp = await fetch(url, options);
            const text = await resp.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`Failed to parse response: ${text.slice(0, 120)}...`);
            }
        },

        async pollCommand(commandId, timeoutMs = 45000) {
            const started = Date.now();
            let delay = 600;
            while (Date.now() - started < timeoutMs) {
                const poll = await this.requestJSON(`modules/addons/cloudstorage/api/agent_poll_hyperv_vms.php?command_id=${commandId}`);
                if (poll.status !== 'pending') {
                    return poll;
                }
                await this.sleep(delay);
                delay = Math.min(Math.round(delay * 1.6), 4000);
            }
            return { status: 'timeout' };
        },

        async loadVMs() {
            if (!this.agentId) {
                this.error = 'Select an agent to discover VMs.';
                return;
            }
            this.loading = true;
            this.error = null;
            try {
                const data = await this.requestJSON(`modules/addons/cloudstorage/api/agent_list_hyperv_vms.php?agent_id=${this.agentId}`);
                if (data.status === 'success') {
                    this.applyVMList(data.vms);
                    return;
                }

                if (data.status !== 'pending' || !data.command_id) {
                    this.error = data.message || 'Failed to queue VM discovery';
                    return;
                }

                const poll = await this.pollCommand(data.command_id, 60000);
                if (poll.status === 'success') {
                    this.applyVMList(poll.vms);
                } else if (poll.status === 'timeout') {
                    this.error = 'VM discovery is taking longer than expected. Please try again.';
                } else {
                    this.error = poll.message || 'Failed to load VMs';
                }
            } catch (e) {
                this.error = e.message || 'Network error';
            } finally {
                this.loading = false;
            }
        },

        applyVMList(vms) {
            this.vms = Array.isArray(vms) ? vms.map(vm => ({
                ...vm,
                disk_count: typeof vm.disk_count === 'number' ? vm.disk_count : (Array.isArray(vm.disks) ? vm.disks.length : null),
                disks: Array.isArray(vm.disks) ? vm.disks : null,
                disks_loaded: Array.isArray(vm.disks),
            })) : [];
            // Re-validate selected VMs
            const validIds = this.vms.map(v => v.id);
            this.selectedVMs = this.selectedVMs.filter(id => validIds.includes(id));
            this.syncToWizard();
            if (this.selectedVMs.length > 0) {
                this.queueVMDetails(this.selectedVMs);
            }
        },

        isSelected(vmId) {
            return this.selectedVMs.includes(vmId);
        },

        toggleVM(vm) {
            if (this.isSelected(vm.id)) {
                this.selectedVMs = this.selectedVMs.filter(id => id !== vm.id);
            } else {
                this.selectedVMs = [...this.selectedVMs, vm.id];
                this.queueVMDetails([vm.id]);
            }
            this.syncToWizard();
        },

        removeVM(vmId) {
            this.selectedVMs = this.selectedVMs.filter(id => id !== vmId);
            this.syncToWizard();
        },

        selectAllVMs() {
            this.selectedVMs = this.vms.map(v => v.id);
            this.syncToWizard();
            this.queueVMDetails(this.selectedVMs);
        },

        clearSelection() {
            this.selectedVMs = [];
            this.syncToWizard();
        },

        getVMName(vmId) {
            const vm = this.vms.find(v => v.id === vmId);
            return vm ? vm.name : vmId;
        },

        formatMemory(mb) {
            if (!mb) return '';
            if (mb >= 1024) {
                return `${(mb / 1024).toFixed(1)} GB RAM`;
            }
            return `${mb} MB RAM`;
        },

        async queueVMDetails(vmIds) {
            const uniqueIds = [...new Set((vmIds || []).filter(Boolean))];
            const pending = uniqueIds.filter(id => {
                const vm = this.vms.find(v => v.id === id);
                if (!vm || vm.disks_loaded) return false;
                return !this.detailsLoadingIds.includes(id);
            });
            if (pending.length === 0) {
                return;
            }
            this.detailsLoadingIds = [...new Set([...this.detailsLoadingIds, ...pending])];
            try {
                const data = await this.requestJSON(
                    `modules/addons/cloudstorage/api/agent_list_hyperv_vm_details.php?agent_id=${this.agentId}`,
                    {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ vm_ids: pending }),
                    }
                );
                if (data.status === 'success' && Array.isArray(data.details)) {
                    this.applyVMDetails(data.details);
                    return;
                }
                if (data.status === 'pending' && data.command_id) {
                    const poll = await this.pollCommand(data.command_id, 60000);
                    if (poll.status === 'success' && Array.isArray(poll.details)) {
                        this.applyVMDetails(poll.details);
                        return;
                    }
                }
            } catch (e) {
                console.warn('Failed to load Hyper-V VM details', e);
            } finally {
                this.detailsLoadingIds = this.detailsLoadingIds.filter(id => !pending.includes(id));
            }
        },

        applyVMDetails(details) {
            const detailMap = new Map(details.map(d => [d.id, d]));
            this.vms = this.vms.map(vm => {
                const detail = detailMap.get(vm.id);
                if (!detail) return vm;
                const disks = Array.isArray(detail.disks) ? detail.disks : vm.disks;
                return {
                    ...vm,
                    disks,
                    disk_count: typeof detail.disk_count === 'number' ? detail.disk_count : (Array.isArray(disks) ? disks.length : vm.disk_count),
                    disks_loaded: Array.isArray(disks),
                };
            });
            this.detailsLoadingIds = this.detailsLoadingIds.filter(id => !detailMap.has(id));
        },

        syncToWizard() {
            const input = document.getElementById('localWizardHypervVMs');
            if (input) {
                input.value = JSON.stringify(this.selectedVMs);
            }
            // Also store full VM data for review
            if (window.localWizardState?.data) {
                window.localWizardState.data.hyperv_vm_ids = [...this.selectedVMs];
                window.localWizardState.data.hyperv_vms = this.selectedVMs.map(id => {
                    const vm = this.vms.find(v => v.id === id);
                    return vm ? { id: vm.id, name: vm.name } : { id };
                });
            }
        },
    };
}

function localWizardSetDiskVolume(val) {
    const input = document.getElementById('localWizardDiskVolume');
    if (input) {
        input.value = val;
    }
    if (window.localWizardState?.data) {
        window.localWizardState.data.disk_source_volume = val;
    }
    localWizardBuildReview();
}

function localWizardNext() {
    const state = window.localWizardState;
    if (state.loading) return;

    if (state.step === 1 && !localWizardIsStep1Valid()) {
        window.toast?.error?.('Please fill in Job Name, select an Agent, Engine, and Bucket before proceeding');
        return;
    }

    if (state.step < state.totalSteps) {
        state.step += 1;
        if (state.step === state.totalSteps) {
            localWizardBuildReview();
        }
        localWizardUpdateView();
        return;
    }
    localWizardSubmit();
}

function localWizardPrev() {
    const state = window.localWizardState;
    if (state.step > 1) {
        state.step -= 1;
        localWizardUpdateView();
    }
}

function localWizardUpdateView() {
    const state = window.localWizardState;
    const steps = document.querySelectorAll('#localJobWizardModal .wizard-step');
    steps.forEach((el) => {
        const target = parseInt(el.getAttribute('data-step'), 10);
        if (target === state.step) {
            el.classList.remove('hidden');
        } else {
            el.classList.add('hidden');
        }
    });

    const crumbs = document.querySelectorAll('#localWizardBreadcrumb .wizard-crumb');
    const step1Valid = localWizardIsStep1Valid();
    crumbs.forEach((crumb) => {
        const stepNum = parseInt(crumb.getAttribute('data-wizard-step'), 10);
        const numBadge = crumb.querySelector('span:first-child');
        const isActive = stepNum === state.step;
        const isComplete = stepNum < state.step;
        const isLocked = stepNum > 1 && !step1Valid;

        crumb.classList.remove('bg-slate-800/50', 'text-slate-300', 'text-slate-500', 'cursor-not-allowed', 'hover:bg-slate-800');
        numBadge.classList.remove('bg-cyan-500', 'bg-emerald-500', 'bg-slate-700', 'text-white', 'text-slate-400');

        if (isActive) {
            crumb.classList.add('bg-slate-800/50', 'text-slate-300');
            numBadge.classList.add('bg-cyan-500', 'text-white');
        } else if (isComplete) {
            crumb.classList.add('text-slate-300', 'hover:bg-slate-800');
            numBadge.classList.add('bg-emerald-500', 'text-white');
        } else if (isLocked) {
            crumb.classList.add('text-slate-500', 'cursor-not-allowed');
            numBadge.classList.add('bg-slate-700', 'text-slate-400');
        } else {
            crumb.classList.add('text-slate-400', 'hover:bg-slate-800');
            numBadge.classList.add('bg-slate-700', 'text-slate-400');
        }

        crumb.disabled = isLocked;
        crumb.style.pointerEvents = isLocked ? 'none' : 'auto';
    });

    const nextBtn = document.getElementById('localWizardNextBtn');
    if (nextBtn) {
        if (state.loading) {
            nextBtn.textContent = 'Loading…';
            nextBtn.disabled = true;
            nextBtn.classList.add('opacity-60', 'cursor-not-allowed');
        } else {
            const canProceed = state.step !== 1 || step1Valid;
            nextBtn.disabled = !canProceed;
            if (canProceed) {
                nextBtn.classList.remove('opacity-60', 'cursor-not-allowed');
            } else {
                nextBtn.classList.add('opacity-60', 'cursor-not-allowed');
            }
            const finalLabel = state.editMode ? 'Save changes' : 'Create job';
            nextBtn.textContent = (state.step === state.totalSteps) ? finalLabel : 'Next';
        }
    }
}

function localWizardIsStep1Valid() {
    const name = document.getElementById('localWizardName')?.value?.trim() || '';
    const agentId = document.getElementById('localWizardAgentId')?.value || '';
    const engine = window.localWizardState?.data?.engine || '';
    const bucketId = document.getElementById('localWizardBucketId')?.value || '';
    return name !== '' && agentId !== '' && engine !== '' && bucketId !== '';
}

function localWizardGoToStep(stepNum) {
    const state = window.localWizardState;
    if (state.loading) return;

    if (stepNum < state.step) {
        state.step = stepNum;
        localWizardUpdateView();
        return;
    }

    if (stepNum > 1 && !localWizardIsStep1Valid()) {
        window.toast?.error?.('Please complete all required fields in Setup before proceeding');
        return;
    }

    if (stepNum <= state.step + 1) {
        state.step = stepNum;
        if (state.step === state.totalSteps) {
            localWizardBuildReview();
        }
        localWizardUpdateView();
    }
}

function localWizardBuildReview() {
    const s = window.localWizardState.data;
    s.agent_id = document.getElementById('localWizardAgentId')?.value || '';
    s.name = document.getElementById('localWizardName')?.value || '';
    s.tenant_id = document.getElementById('localWizardTenantId')?.value || '';
    s.dest_prefix = document.getElementById('localWizardPrefix')?.value || '';
    s.dest_local_path = document.getElementById('localWizardLocalPath')?.value || '';
    s.dest_bucket_id = document.getElementById('localWizardBucketId')?.value || '';
    s.source_path = document.getElementById('localWizardSource')?.value || '';
    const srcPathsRaw = document.getElementById('localWizardSourcePaths')?.value || '[]';
    const srcPathsParsed = safeParseJSON(srcPathsRaw);
    s.source_paths = Array.isArray(srcPathsParsed) ? srcPathsParsed : [];
    
    // For disk image backups, prioritize wizard state (updated by syncDiskVolumeToWizard)
    // then fall back to input element
    const diskVolumeInput = document.getElementById('localWizardDiskVolume');
    const diskVolumeFromState = s.disk_source_volume || '';
    const diskVolumeFromInput = diskVolumeInput?.value || '';
    
    // Use state value if available (most reliable), otherwise use input
    s.disk_source_volume = diskVolumeFromState || diskVolumeFromInput;
    
    // If we got a value from state but input is empty, sync it to input for consistency
    if (s.disk_source_volume && diskVolumeFromState && !diskVolumeFromInput && diskVolumeInput) {
        diskVolumeInput.value = s.disk_source_volume;
    }
    s.disk_image_format = document.getElementById('localWizardDiskFormat')?.value || 'vhdx';
    s.disk_temp_dir = document.getElementById('localWizardDiskTemp')?.value || '';
    if ((s.engine || '') === 'disk_image' && !s.source_path) {
        s.source_path = s.disk_source_volume;
    }
    // Hyper-V specific fields
    const hypervVMsRaw = document.getElementById('localWizardHypervVMs')?.value || '[]';
    const hypervVMsParsed = safeParseJSON(hypervVMsRaw);
    s.hyperv_vm_ids = Array.isArray(hypervVMsParsed) ? hypervVMsParsed : [];
    s.hyperv_enable_rct = !!document.getElementById('localWizardHypervEnableRCT')?.checked;
    s.hyperv_consistency_level = document.getElementById('localWizardHypervConsistency')?.value || 'application';
    s.hyperv_quiesce_timeout = parseInt(document.getElementById('localWizardHypervQuiesceTimeout')?.value || '300', 10);
    if ((s.engine || '') === 'hyperv') {
        // Set source path to indicate Hyper-V backup
        s.source_path = 'Hyper-V VMs';
        s.source_paths = s.hyperv_vm_ids;
    }
    s.include = document.getElementById('localWizardInclude')?.value || '';
    s.exclude = document.getElementById('localWizardExclude')?.value || '';
    s.schedule_type = document.getElementById('localWizardScheduleType')?.value || 'manual';
    s.schedule_time = document.getElementById('localWizardTime')?.value || '';
    s.schedule_weekday = document.getElementById('localWizardWeekday')?.value || '';
    s.schedule_cron = document.getElementById('localWizardCron')?.value || '';
    
    // Preserve schedule_json from Alpine component if it has richer data (weekday array, minute)
    const existingSchedJson = s.schedule_json || {};
    const hasWeekdayArray = Array.isArray(existingSchedJson.weekday) && existingSchedJson.weekday.length > 0;
    const hasMinute = typeof existingSchedJson.minute === 'number';
    
    if (hasWeekdayArray || hasMinute) {
        // Use the Alpine-built schedule_json but ensure type/time/cron are current
        s.schedule_json = {
            ...existingSchedJson,
            type: s.schedule_type,
            time: s.schedule_time,
            cron: s.schedule_cron,
        };
        // For compatibility, store first selected day in schedule_weekday
        if (hasWeekdayArray) {
            s.schedule_weekday = String(Math.min(...existingSchedJson.weekday));
        }
    } else {
        s.schedule_json = {
            type: s.schedule_type,
            time: s.schedule_time,
            weekday: s.schedule_weekday,
            cron: s.schedule_cron,
        };
    }
    // Get retention_json from state (set by Alpine component) or fallback to hidden input
    let retentionObj = s.retention_json || null;
    if (!retentionObj) {
        const retentionTxt = document.getElementById('localWizardRetention')?.value || '';
        retentionObj = retentionTxt ? safeParseJSON(retentionTxt) : null;
    }
    s.retention_json = retentionObj;
    
    // Build policy_json from advanced settings fields
    let policyObj = {};
    const bwVal = document.getElementById('localWizardBandwidth')?.value || '';
    const parVal = document.getElementById('localWizardParallelism')?.value || '';
    const compVal = document.getElementById('localWizardCompression')?.value || 'zstd-default';
    const dbgVal = !!document.getElementById('localWizardDebugLogs')?.checked;
    const pdrVal = document.getElementById('localWizardParallelDiskReads')?.checked;
    s.bandwidth_limit_kbps = bwVal;
    s.parallelism = parVal;
    s.compression = compVal;
    // Set compression_enabled flag for Kopia-based jobs
    s.compression_enabled = (compVal && compVal.toLowerCase() !== 'none') ? 1 : 0;
    if (compVal && compVal.toLowerCase() !== 'none') {
        policyObj.compression = compVal;
    }
    if (parVal) {
        const pi = parseInt(parVal, 10);
        if (!isNaN(pi) && pi > 0) {
            policyObj.parallel_uploads = pi;
        }
    }
    if (dbgVal) {
        policyObj.debug_logs = true;
    }
    if (pdrVal === false) {
        policyObj.parallel_disk_reads = false;
    }
    s.policy_json = policyObj;

    const review = document.getElementById('localWizardReview');
    if (review) {
        const displayData = { ...s };
        if (displayData.engine === 'kopia') {
            displayData.engine = 'eazyBackup (Archive)';
        } else if (displayData.engine === 'sync') {
            displayData.engine = 'eazyBackup (Sync)';
        } else if (displayData.engine === 'disk_image') {
            displayData.engine = 'eazyBackup (Disk Image)';
        } else if (displayData.engine === 'hyperv') {
            displayData.engine = 'Hyper-V VM Backup';
            // Show VM names instead of IDs
            if (displayData.hyperv_vms && displayData.hyperv_vms.length) {
                displayData.selected_vms = displayData.hyperv_vms.map(v => v.name || v.id);
            }
        }
        review.textContent = JSON.stringify(displayData, null, 2);
    }
}

function safeParseJSON(txt) {
    try {
        return JSON.parse(txt);
    } catch (e) {
        return null;
    }
}

function htmlDecode(value) {
    if (value === undefined || value === null) return '';
    const div = document.createElement('div');
    div.innerHTML = String(value);
    return div.textContent || div.innerText || '';
}

function parseStoredVolume(value) {
    const decoded = htmlDecode(value);
    if (!decoded) return '';
    if (decoded.startsWith('[')) {
        try {
            const parsed = JSON.parse(decoded);
            if (Array.isArray(parsed) && parsed.length) {
                return String(parsed[0]);
            }
        } catch (e) {
            // fall back to decoded string
        }
    }
    return decoded;
}

function localWizardSubmit() {
    const s = window.localWizardState.data;
    const isEdit = !!window.localWizardState.editMode;
    if (!s.name) {
        const msg = 'Job name is required';
        e3backupNotify('error', msg);
        return;
    }
    if (!s.agent_id) {
        const msg = 'Agent ID is required';
        e3backupNotify('error', msg);
        return;
    }
    if (!s.dest_bucket_id) {
        const msg = 'Bucket ID is required';
        e3backupNotify('error', msg);
        return;
    }
    if ((s.engine || '') === 'disk_image' && !s.disk_source_volume) {
        const msg = 'Disk selection is required for disk image backups';
        e3backupNotify('error', msg);
        return;
    }
    if ((s.engine || '') === 'hyperv' && (!s.hyperv_vm_ids || s.hyperv_vm_ids.length === 0)) {
        const msg = 'Please select at least one VM to backup';
        e3backupNotify('error', msg);
        return;
    }
    
    // Determine source display name based on engine
    let sourceDisplayName = 'Local Agent';
    let sourcePath = s.source_path || '';
    if (s.engine === 'disk_image') {
        sourceDisplayName = 'Disk Image';
        sourcePath = s.disk_source_volume || s.source_path || '';
    } else if (s.engine === 'hyperv') {
        const vmNames = (s.hyperv_vms || []).map(v => v.name).filter(Boolean);
        sourceDisplayName = 'Hyper-V: ' + (vmNames.length > 2 ? vmNames.slice(0, 2).join(', ') + '...' : vmNames.join(', '));
        sourcePath = 'Hyper-V VMs (' + s.hyperv_vm_ids.length + ')';
    }
    
    // Serialize source_paths as JSON to preserve array structure through URLSearchParams
    const sourcePathsArray = Array.isArray(s.source_paths) ? s.source_paths : (s.source_path ? [s.source_path] : []);
    
    const payload = {
        name: s.name,
        source_type: 'local_agent',
        source_display_name: sourceDisplayName,
        source_path: sourcePath,
        source_paths: JSON.stringify(sourcePathsArray),
        dest_bucket_id: s.dest_bucket_id,
        dest_prefix: s.dest_prefix || '',
        backup_mode: s.engine === 'sync' ? 'sync' : 'archive',
        engine: s.engine || 'kopia',
        agent_id: s.agent_id,
        dest_type: 's3',
        tenant_id: s.tenant_id || '',
        schedule_json: s.schedule_json && typeof s.schedule_json === 'object' ? JSON.stringify(s.schedule_json) : '',
        retention_json: (s.retention_json && typeof s.retention_json === 'object') ? JSON.stringify(s.retention_json) : (typeof s.retention_json === 'string' ? s.retention_json : ''),
        policy_json: (s.policy_json && typeof s.policy_json === 'object') ? JSON.stringify(s.policy_json) : (typeof s.policy_json === 'string' ? s.policy_json : ''),
        bandwidth_limit_kbps: s.bandwidth_limit_kbps || '',
        parallelism: s.parallelism || '',
        encryption_mode: s.encryption_mode || 'repokey',
        compression: s.compression || '',
        compression_enabled: s.compression_enabled || 0,
        retention_mode: 'none',
        retention_value: '',
        schedule_type: s.schedule_type || 'manual',
        schedule_time: s.schedule_time || '',
        schedule_weekday: s.schedule_weekday || '',
        schedule_cron: s.schedule_cron || '',
        local_include_glob: s.include || '',
        local_exclude_glob: s.exclude || '',
        disk_source_volume: s.disk_source_volume || '',
        disk_image_format: s.disk_image_format || '',
        disk_temp_dir: s.disk_temp_dir || '',
        network_domain: s.network_domain || '',
    };
    
    // Only include network credentials if provided (for edit mode: blank means "keep existing")
    if (s.network_username) {
        payload.network_username = s.network_username;
    }
    if (s.network_password) {
        payload.network_password = s.network_password;
    }
    
    // Add Hyper-V specific fields
    if (s.engine === 'hyperv') {
        payload.hyperv_enabled = '1';
        payload.hyperv_vm_ids = JSON.stringify(s.hyperv_vm_ids || []);
        // Send VM info with names for proper registration
        payload.hyperv_vms = JSON.stringify(s.hyperv_vms || []);
        payload.hyperv_config = JSON.stringify({
            vms: s.hyperv_vm_ids || [],
            enable_rct: s.hyperv_enable_rct !== false,
            consistency_level: s.hyperv_consistency_level || 'application',
            quiesce_timeout_seconds: s.hyperv_quiesce_timeout || 300,
            backup_all_vms: false,
            exclude_vms: [],
        });
    }
    if (isEdit) {
        payload.job_id = window.localWizardState.jobId;
    }

    const opts = {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload),
    };
    const endpoint = isEdit
        ? 'modules/addons/cloudstorage/api/cloudbackup_update_job.php'
        : 'modules/addons/cloudstorage/api/cloudbackup_create_job.php';
    if (isEdit && !payload.job_id) {
        const msg = 'Missing job ID for update';
        e3backupNotify('error', msg);
        return;
    }
    fetch(endpoint, opts)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                e3backupNotify('success', isEdit ? 'Local agent job updated' : 'Local agent job created');
                closeLocalJobWizard();
                // Reload jobs list (AJAX, no full page refresh)
                e3backupReloadJobs();
            } else {
                const msg = data.message || (isEdit ? 'Failed to update job' : 'Failed to create job');
                e3backupNotify('error', msg);
            }
        })
        .catch(err => {
            const msg = 'Error ' + (isEdit ? 'updating' : 'creating') + ' job: ' + err;
            e3backupNotify('error', msg);
        });
}

function openInlineBucketCreate() {
    const toggle = document.querySelector('#inlineCreateBucketMsg');
    if (toggle) {
        toggle.classList.remove('hidden');
    }
    const btn = document.querySelector('[onclick=\"createBucketInline().finally(() => creating=false)\"]');
    if (btn) {
        btn.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Add event listeners on DOM load
document.addEventListener('DOMContentLoaded', () => {
    const nameInput = document.getElementById('localWizardName');
    if (nameInput) {
        nameInput.addEventListener('input', () => {
            localWizardUpdateView();
        });
    }

    const bucketInput = document.getElementById('localWizardBucketId');
    if (bucketInput) {
        const observer = new MutationObserver(() => {
            localWizardUpdateView();
        });
        observer.observe(bucketInput, { attributes: true, attributeFilter: ['value'] });
        bucketInput.addEventListener('change', () => localWizardUpdateView());
    }
});

// Callback for bucket creation modal in Local Agent wizard
function onLocalWizardBucketCreated(bucket) {
    if (!bucket || !bucket.id) return;
    
    // Find the local wizard bucket dropdown Alpine component
    const dropdownEl = document.getElementById('localWizardBucketDropdown');
    if (dropdownEl && dropdownEl._x_dataStack) {
        const data = dropdownEl._x_dataStack[0];
        if (data && typeof data.addBucket === 'function') {
            data.addBucket(bucket);
        }
    }
    
    // Show success notification
    if (window.toast) {
        window.toast.success('Bucket "' + bucket.name + '" created and selected');
    } else if (typeof e3backupNotify === 'function') {
        e3backupNotify('success', 'Bucket "' + bucket.name + '" created and selected');
    }
}
</script>
{/literal}
