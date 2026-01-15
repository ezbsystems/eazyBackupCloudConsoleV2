<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
        {include file="modules/addons/cloudstorage/templates/partials/cloudbackup_nav.tpl"}
        <!-- Glass panel container -->
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
        <style>
        .btn-run-now {
            display: inline-flex; align-items: center; gap: 0.5rem;
            border-radius: 9999px; padding: 0.375rem 1rem;
            font-size: 0.875rem; font-weight: 600;
            color: rgb(15 23 42); /* slate-950 */
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
            border:1px solid rgba(51,65,85,0.8); /* slate-700/80 */
            background-color: rgba(15,23,42,0.6); /* slate-900/60 */
            color:#cbd5e1; font-size:.75rem; transition: all .15s ease;
        }
        .icon-btn:hover { border-color:#94a3b8; color:white; background-color:#1f2937; }
        .icon-btn[disabled] { opacity:.6; cursor:not-allowed; }
        </style>

        <div class="flex flex-col sm:flex-row h-16 justify-between items-start sm:items-center mb-3">
            <div class="flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                <h2 class="text-2xl font-semibold text-white ml-2 flex items-center gap-2">
                    <span>Cloud Backup Jobs</span>
                    <span class="inline-flex items-center rounded-full bg-amber-500/15 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-amber-200 border border-amber-400/40">
                        Beta
                    </span>
                </h2>
            </div>
            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    class="btn-run-now"
                    @click="open = !open"
                    @click.away="open = false"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>Create Job</span>
                    <svg class="w-4 h-4 ml-1 text-slate-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
                <div
                    x-show="open"
                    x-transition
                    class="absolute right-0 mt-2 w-56 rounded-lg border border-slate-800 bg-slate-900 shadow-lg z-10"
                    style="display: none;"
                >
                    <div class="px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 border-b border-slate-800">
                        Select backup source
                    </div>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-4 py-3 text-sm text-slate-100 hover:bg-slate-800 transition"
                        @click="open = false; openCreateJobModal();"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-sky-300">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v6.75m0 0-3-3m3 3 3-3m-8.25 6a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                        </svg>
                        <span>Cloud Backup</span>
                    </button>
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-4 py-3 text-sm text-slate-100 hover:bg-slate-800 transition"
                        @click="open = false; openLocalJobWizard();"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-[#FE5000]">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                        </svg>
                        <span>Local Backup</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="mb-6 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-xs text-amber-100 flex items-start gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-[2px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75M12 15.75h.007M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <p class="font-semibold text-amber-100 text-[0.75rem] uppercase tracking-wide">Cloud Backup (Beta)</p>
                <p class="mt-1 text-[0.75rem] leading-relaxed text-amber-100/90">
                    Cloud Backup is currently in beta. Functionality may change and occasional issues are expected.
                    Please keep a primary backup strategy in place and contact support if you notice any problems.
                </p>
            </div>
        </div>

        <!-- Summary Metrics Band -->
        <div class="mb-6 grid gap-4 md:grid-cols-5">
            <div class="rounded-2xl px-4 py-3 bg-slate-950/70 border border-slate-800/80">
                <p class="text-[0.65rem] font-medium tracking-wide uppercase text-slate-400">Total Jobs</p>
                <p class="mt-1 text-2xl font-semibold text-white">{if isset($metrics.total_jobs)}{$metrics.total_jobs}{else}{count($jobs)}{/if}</p>
            </div>
            <div class="rounded-2xl px-4 py-3 bg-slate-950/70 border
                {if isset($metrics.active) && $metrics.active > 0}
                    border-emerald-500/35
                {else}
                    border-slate-800/80
                {/if}">
                <p class="text-[0.65rem] font-medium tracking-wide uppercase
                    {if isset($metrics.active) && $metrics.active > 0} text-emerald-200 {else} text-slate-400 {/if}">
                    Active
                </p>
                <p class="mt-1 text-2xl font-semibold text-slate-50">{if isset($metrics.active)}{$metrics.active}{else}0{/if}</p>
            </div>
            <div class="rounded-2xl px-4 py-3 bg-slate-950/70 border
                {if isset($metrics.paused) && $metrics.paused > 0}
                    border-amber-500/35
                {else}
                    border-slate-800/80
                {/if}">
                <p class="text-[0.65rem] font-medium tracking-wide uppercase
                    {if isset($metrics.paused) && $metrics.paused > 0} text-amber-200 {else} text-slate-400 {/if}">
                    Paused
                </p>
                <p class="mt-1 text-2xl font-semibold text-slate-50">{if isset($metrics.paused)}{$metrics.paused}{else}0{/if}</p>
            </div>
            <div class="rounded-2xl px-4 py-3 bg-slate-950/70 border
                {if isset($metrics.failed_24h) && $metrics.failed_24h > 0}
                    border-rose-500/35 shadow-[0_0_24px_rgba(248,113,113,0.25)]
                {else}
                    border-slate-800/80
                {/if}">
                <p class="text-[0.65rem] font-medium tracking-wide uppercase
                    {if isset($metrics.failed_24h) && $metrics.failed_24h > 0} text-rose-300 {else} text-slate-400 {/if}">
                    Failed (24h)
                </p>
                <p class="mt-1 text-2xl font-semibold text-slate-50">{if isset($metrics.failed_24h)}{$metrics.failed_24h}{else}0{/if}</p>
            </div>
            <div class="rounded-2xl px-4 py-3 bg-slate-950/70 border border-slate-800/80">
                <p class="text-[0.65rem] font-medium tracking-wide uppercase text-slate-400">Last Run</p>
                <p class="mt-1 text-sm text-slate-200">
                    {if isset($metrics.last_run_started_at) && $metrics.last_run_started_at}
                        {$metrics.last_run_started_at|date_format:"%d %b %Y %H:%M"}
                        {if $metrics.last_run_status}
                            <span class="ml-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] font-medium
                                {if $metrics.last_run_status eq 'success'}bg-emerald-500/10 text-emerald-300
                                {elseif $metrics.last_run_status eq 'failed'}bg-rose-500/15 text-rose-300
                                {elseif $metrics.last_run_status eq 'cancelled'}bg-amber-500/15 text-amber-300
                                {else}bg-slate-500/15 text-slate-300{/if}">
                                {$metrics.last_run_status|ucfirst}
                            </span>
                        {/if}
                    {else}
                        <span class="text-slate-500">Never</span>
                    {/if}
                </p>
            </div>
        </div>

        <!-- Global Message Container -->
        <div id="globalMessage" class="text-white px-4 py-2 rounded-md mb-6 hidden" role="alert"></div>

        <!-- Alpine Toast Notification -->
        <div x-data="{
                visible: false,
                message: '',
                type: 'info',
                timeout: null,
                show(msg, t = 'info') {
                    this.message = msg;
                    this.type = t;
                    this.visible = true;
                    if (this.timeout) clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => { this.visible = false; }, t === 'error' ? 7000 : 4000);
                }
            }"
             x-init="window.toast = {
                success: (m) => show(m, 'success'),
                error: (m) => show(m, 'error'),
                info: (m) => show(m, 'info')
             }"
             class="fixed top-4 right-4 z-[9999]"
             x-cloak>
            <div x-show="visible"
                 x-transition:enter="transform transition ease-out duration-300"
                 x-transition:enter-start="translate-y-2 opacity-0"
                 x-transition:enter-end="translate-y-0 opacity-100"
                 x-transition:leave="transform transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 opacity-100"
                 x-transition:leave-end="translate-y-2 opacity-0"
                 class="rounded-md px-4 py-3 text-white shadow-lg min-w-[300px] max-w-[500px]"
                 :class="{
                    'bg-green-600': type === 'success',
                    'bg-red-600': type === 'error',
                    'bg-blue-600': type === 'info'
                 }">
                <div class="flex items-start justify-between">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg x-show="type === 'success'" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <svg x-show="type === 'error'" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <svg x-show="type === 'info'" class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <p class="ml-3 text-sm font-medium" x-text="message"></p>
                    </div>
                    <button @click="visible = false" class="ml-4 inline-flex text-white hover:text-gray-200 focus:outline-none">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between items-start sm:items-center">
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <input type="text" placeholder="Search jobs" class="w-full sm:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                    x-model="$store.jobFilters.q" @input="$dispatch('jobs-filter-apply')">
                <select class="rounded-full border border-slate-700 bg-slate-900/80 px-3 py-2 text-xs text-slate-200"
                    x-model="$store.jobFilters.sourceType" @change="$dispatch('jobs-filter-apply')">
                    <option value="all">All Sources</option>
                    <option value="local_agent">Local Agent</option>
                    <option value="cloud">Cloud-to-Cloud</option>
                    <option value="s3_compatible">S3-Compatible</option>
                    <option value="aws">AWS</option>
                    <option value="sftp">SFTP</option>
                    <option value="google_drive">Google Drive</option>
                    <option value="dropbox">Dropbox</option>
                </select>
            </div>
            <div class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400">
                <button class="px-3 py-1.5 rounded-full transition" :class="($store.jobFilters.status==='all') ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="$store.jobFilters.status='all'; $dispatch('jobs-filter-apply')">All</button>
                <button class="px-3 py-1.5 rounded-full transition" :class="($store.jobFilters.status==='success') ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="$store.jobFilters.status='success'; $dispatch('jobs-filter-apply')">Success</button>
                <button class="px-3 py-1.5 rounded-full transition" :class="($store.jobFilters.status==='warning') ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="$store.jobFilters.status='warning'; $dispatch('jobs-filter-apply')">Warning</button>
                <button class="px-3 py-1.5 rounded-full transition" :class="($store.jobFilters.status==='failed') ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="$store.jobFilters.status='failed'; $dispatch('jobs-filter-apply')">Failed</button>
                <button class="px-3 py-1.5 rounded-full transition" :class="($store.jobFilters.status==='running') ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="$store.jobFilters.status='running'; $dispatch('jobs-filter-apply')">Running</button>
                <button class="px-3 py-1.5 rounded-full transition" :class="($store.jobFilters.status==='failed_recent') ? 'bg-slate-800 text-slate-50 shadow-sm' : 'hover:text-slate-200'" @click="$store.jobFilters.status='failed_recent'; $dispatch('jobs-filter-apply')">Failed Recently</button>
            </div>
        </div>

        <!-- Jobs Container -->
        <div class="grid grid-cols-1 gap-6" x-data="{
            init() { this.apply(); },
            apply() {
                try {
                    const store = (window.Alpine && Alpine.store('jobFilters')) ? Alpine.store('jobFilters') : { q:'', status:'all' };
                    const q = (store.q || '').toLowerCase();
                    const status = (store.status || 'all').toLowerCase();
                    const now = Date.now();
                    const cards = this.$root.querySelectorAll('[data-job-card]');
                    cards.forEach((card) => {
                        const name = (card.getAttribute('data-name') || '').toLowerCase();
                        const jobStatus = (card.getAttribute('data-status') || '').toLowerCase();
                        const lastStatus = (card.getAttribute('data-last-status') || '').toLowerCase();
                        const lastStarted = card.getAttribute('data-last-started');
                        const source = (card.getAttribute('data-source') || '').toLowerCase();
                        const sourceType = (card.getAttribute('data-source-type') || '').toLowerCase();
                        const dest = (card.getAttribute('data-dest') || '').toLowerCase();
                        let failedRecent = false;
                        if (lastStatus === 'failed' && lastStarted) {
                            const t = Date.parse(lastStarted);
                            if (!isNaN(t)) {
                                failedRecent = (now - t) <= (24 * 3600 * 1000);
                            }
                        }
                        const hay = (name + ' ' + jobStatus + ' ' + lastStatus + ' ' + source + ' ' + sourceType + ' ' + dest).trim();
                        let ok = (!q || hay.indexOf(q) !== -1);
                        if (ok && status !== 'all') {
                            if (status === 'failed_recent') {
                                ok = failedRecent;
                            } else if (status === 'running') {
                                ok = (lastStatus === 'running' || lastStatus === 'starting' || lastStatus === 'queued');
                            } else {
                                ok = (lastStatus === status);
                            }
                        }
                        card.style.display = ok ? '' : 'none';
                    });
                } catch (e) {}
            }
        }" x-init="init()" @jobs-filter-apply.window="apply()">
            {if count($jobs) > 0}
                {foreach from=$jobs item=job}
                    <div class="job-row group relative overflow-hidden rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-400/60 hover:shadow-lg hover:shadow-emerald-500/15"
                         id="jobRow{$job.id}"
                         data-job-card
                         data-name="{$job.name|escape:'html'}"
                         data-status="{$job.status|lower}"
                         data-source="{$job.source_display_name|escape:'html'}"
                         data-source-type="{$job.source_type|lower}"
                         {if $job.dest_bucket_name}
                             data-dest="{$job.dest_bucket_name|escape:'html'}{if $job.dest_prefix}/{$job.dest_prefix|escape:'html'}{/if}"
                         {else}
                             data-dest="Bucket #{$job.dest_bucket_id}{if $job.dest_prefix} / {$job.dest_prefix|escape:'html'}{/if}"
                         {/if}
                         {if $job.last_run}
                             data-last-status="{$job.last_run.status|lower}"
                             data-last-started="{$job.last_run.started_at}"
                         {/if}
                    >
                        <div class="flex items-center justify-between gap-4 mb-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-800/90 group-hover:bg-slate-700">
                                    <!-- heroicon-cloud-arrow-down-up -->
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 15.75a4.5 4.5 0 004.5 4.5h9a4.5 4.5 0 004.5-4.5 4.5 4.5 0 00-3.75-4.415A5.25 5.25 0 006.75 9.75a5.25 5.25 0 00-.518 2.25M9 12l3-3m0 0l3 3m-3-3v7.5" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-sm font-semibold text-white">{$job.name}</h3>
                                        {if $job.source_type eq 'local_agent'}
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] font-semibold bg-sky-500/15 text-sky-200 border border-sky-400/40">
                                                Local Agent
                                            </span>
                                        {/if}
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium
                                            {if $job.status eq 'active'}bg-emerald-500/10 text-emerald-300
                                            {elseif $job.status eq 'paused'}bg-amber-500/15 text-amber-300
                                            {else}bg-slate-500/15 text-slate-300{/if}">
                                            <span class="h-1.5 w-1.5 rounded-full
                                                {if $job.status eq 'active'}bg-emerald-400
                                                {elseif $job.status eq 'paused'}bg-amber-400
                                                {else}bg-slate-400{/if}"></span>
                                            {$job.status|ucfirst}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-400">
                                        {if $job.backup_mode eq 'archive'}Archive{else}Sync{/if}
                                        from
                                        <span class="font-mono text-slate-200">
                                            {if $job.source_type eq 'aws'}AWS
                                            {elseif $job.source_type eq 's3_compatible'}S3-Compatible
                                            {elseif $job.source_type eq 'sftp'}SFTP
                                            {elseif $job.source_type eq 'google_drive'}Google Drive
                                            {elseif $job.source_type eq 'dropbox'}Dropbox
                                            {elseif $job.source_type eq 'local_agent'}Local Agent
                                            {else}{$job.source_type|capitalize}{/if}
                                        </span>
                                        →
                                        <span class="font-mono text-slate-200">
                                            {if $job.dest_bucket_name}
                                                {$job.dest_bucket_name}{if $job.dest_prefix}/{$job.dest_prefix}{/if}
                                            {else}
                                                Bucket #{$job.dest_bucket_id}{if $job.dest_prefix} / {$job.dest_prefix}{/if}
                                            {/if}
                                        </span>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div x-data="{ running: false }">
                                    <button
                                        class="btn-run-now"
                                        @click="running = true; runJob({$job.id}).finally(() => running = false)"
                                        {if $job.status neq 'active'}disabled{/if}
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
                                <button
                                    onclick="editJob({$job.id}, '{$job.source_type}')"
                                    class="icon-btn"
                                    title="Edit"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4">
                                        <path d="M21.731 2.269a2.625 2.625 0 00-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 000-3.712z" />
                                        <path d="M3.75 15.75V19.5h3.75l9.72-9.72-3.75-3.75L3.75 15.75z" />
                                    </svg>
                                </button>
                                <button
                                    onclick="toggleJobStatus({$job.id}, '{$job.status}')"
                                    class="icon-btn"
                                    title="{if $job.status eq 'active'}Pause{else}Resume{/if}"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5V18M15 7.5V18M3 16.811V8.69c0-.864.933-1.406 1.683-.977l7.108 4.061a1.125 1.125 0 0 1 0 1.954l-7.108 4.061A1.125 1.125 0 0 1 3 16.811Z" />
                                    </svg>
                                </button>
                                <button
                                    onclick="deleteJob({$job.id})"
                                    class="icon-btn"
                                    title="Delete"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                                <button
                                    onclick="viewLogs({$job.id})"
                                    class="icon-btn"
                                    title="Logs"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                              
                                </button>
                                <button
                                    onclick="{if $job.engine eq 'hyperv'}window.location.href='index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_hyperv&job_id={$job.id}'{else}openRestoreModal({$job.id}){/if}"
                                    class="icon-btn"
                                    title="Restore"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v9m0 0l-3.5-3.5M12 13l3.5-3.5M6 20h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.5" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-6 gap-4 text-xs text-slate-400">
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Source</h6>
                                <span class="text-md font-medium text-slate-300">{$job.source_display_name}</span>
                                <span class="text-xs text-slate-500 ml-2">
                                    {if $job.source_type eq 'local_agent' && $job.agent_hostname}
                                        ({$job.agent_hostname|escape:'html'})
                                    {elseif $job.source_type eq 'local_agent' && $job.agent_id}
                                        (Agent #{$job.agent_id})
                                    {else}
                                        ({$job.source_type})
                                    {/if}
                                </span>
                            </div>
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Destination</h6>
                                <span class="text-md font-medium text-slate-300">
                                    {if $job.dest_bucket_name}
                                        {$job.dest_bucket_name}
                                    {else}
                                        Bucket #{$job.dest_bucket_id}
                                    {/if}
                                </span>
                                {if $job.dest_prefix}
                                    <span class="text-xs text-slate-500 ml-2">/{$job.dest_prefix}</span>
                                {/if}
                            </div>
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Mode</h6>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {if $job.backup_mode eq 'archive'}bg-purple-700 text-purple-200
                                    {else}bg-blue-700 text-blue-200{/if}">
                                    {if $job.backup_mode eq 'archive'}Archive{else}Sync{/if}
                                </span>
                                {if $job.encryption_enabled}
                                    <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-700 text-green-200" title="Encrypted">
                                        🔒
                                    </span>
                                {/if}
                                {if $job.retention_mode neq 'none'}
                                    <div class="mt-1 text-xs text-slate-500">
                                        Retention: {if $job.retention_mode eq 'keep_last_n'}Last {$job.retention_value} runs
                                        {elseif $job.retention_mode eq 'keep_days'}{$job.retention_value} days
                                        {else}{$job.retention_mode}{/if}
                                    </div>
                                {/if}
                            </div>
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Schedule</h6>
                                <span class="text-md font-medium text-slate-300">
                                    {if $job.schedule_type eq 'manual'}Manual
                                    {elseif $job.schedule_type eq 'daily'}Daily
                                    {elseif $job.schedule_type eq 'weekly'}Weekly
                                    {else}{$job.schedule_type}{/if}
                                </span>
                            </div>
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Next Run</h6>
                                {if $job.schedule_type eq 'manual'}
                                    <span class="text-md font-medium text-slate-500">-</span>
                                {else}
                                    <span
                                        class="text-md font-medium text-slate-300"
                                        data-next-run="1"
                                        data-job-id="{$job.id}"
                                        data-type="{$job.schedule_type}"
                                        data-time="{$job.schedule_time}"
                                        data-weekday="{$job.schedule_weekday}"
                                    >Calculating...</span>
                                {/if}
                            </div>
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Last Run</h6>
                                {if $job.last_run}
                                    <span class="text-md font-medium text-slate-300">
                                        {$job.last_run.started_at|date_format:"%d %b %Y %H:%M"}
                                    </span>
                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {if $job.last_run.status eq 'success'}bg-green-700 text-green-200
                                        {elseif $job.last_run.status eq 'failed'}bg-red-700 text-red-200
                                        {elseif $job.last_run.status eq 'running'}bg-blue-700 text-blue-200
                                        {else}bg-gray-700 text-gray-200{/if}">
                                        {$job.last_run.status|ucfirst}
                                    </span>
                                {else}
                                    <span class="text-md font-medium text-slate-500">Never</span>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/foreach}
            {else}
                <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 shadow-sm px-6 py-8 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-800/90">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-sky-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 15.75a4.5 4.5 0 004.5 4.5h9a4.5 4.5 0 004.5-4.5 4.5 4.5 0 00-3.75-4.415A5.25 5.25 0 006.75 9.75a5.25 5.25 0 00-.518 2.25M9 12l3-3m0 0l3 3m-3-3v7.5" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white">No backup jobs yet</h3>
                    <p class="mt-1 text-sm text-slate-400">Create your first cloud-to-cloud backup job to start protecting your data.</p>
                    <ul class="mt-3 text-left text-xs text-slate-400 list-disc list-inside space-y-1 max-w-md mx-auto">
                        <li>Connect sources like AWS, S3-compatible, SFTP, Google Drive, Dropbox</li>
                        <li>Choose Sync or Archive modes with optional encryption</li>
                        <li>Schedule daily or weekly runs and keep last N runs</li>
                    </ul>
                    <div class="mt-5">
                        <button type="button" class="btn-run-now" onclick="openCreateJobModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6" />
                            </svg>
                            <span>Create your first job</span>
                        </button>
                    </div>
                </div>
            {/if}
        </div>
        </div>
    </div>
</div>

<!-- Restore Wizard Modal -->
<div id="restoreWizardModal" class="fixed inset-0 z-[2100] hidden">
    <div class="absolute inset-0 bg-black/75" onclick="closeRestoreModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-2xl border border-slate-800 bg-slate-950 shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800">
                <div>
                    <p class="text-xs uppercase text-slate-400 tracking-wide">Restore</p>
                    <h3 class="text-xl font-semibold text-white">Restore Snapshot</h3>
                    <p class="text-[11px] text-slate-400 mt-1">Select a snapshot (recent run), choose a target path, and optionally request a mount. File-tree browse is not yet available; restore uses the selected snapshot manifest.</p>
                </div>
                <button class="icon-btn" onclick="closeRestoreModal()" aria-label="Close wizard">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                            <p class="text-xs text-slate-400 mt-1">Pick a run whose snapshot you want to restore. Uses the manifest ID stored on the run.</p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="restore-step hidden" data-step="2">
                        <label class="block text-sm font-medium text-slate-200 mb-2">Restore Target</label>
                        <div class="space-y-3">
                            <input id="restoreTargetPath" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100" placeholder="Destination path on agent (e.g., C:\Restores\job123)">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                                <input id="restoreMount" type="checkbox" class="rounded border-slate-600 bg-slate-800">
                                <span>Request mount instead of copy (not yet implemented in agent; will report failure)</span>
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
                    <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100" onclick="restorePrev()">Back</button>
                    <div class="flex gap-2">
                        <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100" onclick="closeRestoreModal()">Cancel</button>
                        <button type="button" class="px-4 py-2 rounded-lg bg-sky-600 text-white" onclick="restoreNext()">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Job Slide-Over (dynamically populated) -->
<div id="createJobSlideover" x-data="{ isOpen: false }" x-show="isOpen" class="fixed inset-0 z-50" style="display: none;">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/75"
         x-show="isOpen"
         x-transition.opacity
         onclick="closeCreateSlideover()"></div>
    <!-- Panel -->
    <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-slate-950 border-l border-slate-800/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] overflow-y-auto"
         x-show="isOpen"
         x-transition:enter="transform transition ease-in-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in-out duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">
        <div class="flex items-center justify-between p-4 border-b border-slate-700">
            <h3 class="text-lg font-semibold text-white">Create Backup Job</h3>
            <button class="text-slate-300 hover:text-white" onclick="closeCreateSlideover()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-4">
        <style>
        #createJobSlideover ::placeholder { color: #94a3b8; opacity: 1; }
        /* Panel + form controls - dark slate theme */
        #createJobSlideover .border-slate-700 { border-color: rgba(51,65,85,1); }
        #createJobSlideover input[type="text"],
        #createJobSlideover input[type="password"],
        #createJobSlideover input[type="number"],
        #createJobSlideover input[type="time"],
        #createJobSlideover select,
        #createJobSlideover textarea {
            background-color: rgb(15 23 42) !important; /* bg-slate-900 */
            border-color: rgba(51,65,85,1) !important;        /* border-slate-700 */
            color: #e2e8f0 !important;                        /* text-slate-200 */
        }
        #createJobSlideover input:focus,
        #createJobSlideover select:focus,
        #createJobSlideover textarea:focus {
            outline: none !important;
            border-color: rgb(14 165 233 / 1) !important;     /* border-sky-500 */
            
        }
        /* Common dropdown panels */
        #createJobSlideover .dropdown-surface {
            background-color: rgb(2 6 23);                    /* bg-slate-950 */
            border-color: rgba(51,65,85,1);
        }
        </style>
        <div id="jobCreationMessage" class="bg-red-600 text-white px-4 py-2 rounded-md mb-4 hidden"></div>
        <form id="createJobForm">
            
            <input type="hidden" name="client_id" value="{$client_id}">
            <input type="hidden" name="s3_user_id" value="{$s3_user_id}">
            
            <!-- Step 1: Source Type -->
            <div class="mb-4" x-data="{
                isOpen: false,
                selected: 's3_compatible',
                options: [
                    { value: 's3_compatible', label: 'S3-Compatible Storage' },
                    { value: 'aws', label: 'Amazon S3 (AWS)' },
                    { value: 'sftp', label: 'SFTP/SSH Server' },
                    { value: 'google_drive', label: 'Google Drive' },
                    { value: 'dropbox', label: 'Dropbox' },
                    { value: 'local_agent', label: 'Local Agent (Windows)' }
                ],
                labelFor(val) {
                    const o = this.options.find(opt => opt.value === val);
                    return o ? o.label : val;
                }
            }" x-init="
                selected = $refs.nativeSelect.value || selected;
                $refs.nativeSelect.addEventListener('change', () => { selected = $refs.nativeSelect.value; });
            ">
                <label class="block text-sm font-medium text-slate-300 mb-2">Source Type</label>
                <!-- Hidden native select preserved for existing JS listeners and form submission -->
                <select name="source_type" id="sourceType" x-ref="nativeSelect" class="hidden" required>
                    <option value="s3_compatible">S3-Compatible Storage</option>
                    <option value="aws">Amazon S3 (AWS)</option>
                    <option value="sftp">SFTP/SSH Server</option>
                    <option value="google_drive">Google Drive</option>
                    <option value="dropbox">Dropbox</option>
                    <option value="local_agent">Local Agent (Windows)</option>
                </select>
                <!-- Alpine-powered dropdown UI -->
                <div class="relative">
                    <button type="button"
                            @click="isOpen = !isOpen"
                            class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                        <span class="block truncate" x-text="labelFor(selected)"></span>
                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </button>
                    <div x-show="isOpen" class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg" style="display:none;">
                        <ul class="py-1 overflow-auto text-base max-h-60 rounded-md border border-slate-600 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin">
                            <template x-for="opt in options" :key="opt.value">
                                <li @click="selected = opt.value; $refs.nativeSelect.value = opt.value; $refs.nativeSelect.dispatchEvent(new Event('change')); isOpen = false"
                                    class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700"
                                    :class="{ 'bg-gray-700 text-white': selected === opt.value }"
                                    x-text="opt.label">
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Step 2: Source Details -->
            <div id="sourceDetails">
                <!-- Security Warning: Read-Only Access Keys -->
                <div id="sourceAccessWarning" class="mb-4 p-4 bg-slate-800 border border-gray-600 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-bold text-gray-200 mb-1">Use READ-ONLY Access Keys</h3>
                            
                            <ul class="text-xs text-gray-300 list-disc list-inside space-y-1">
                                <li>Do not use access keys with write, delete, or modify permissions</li>
                                <li>Create dedicated read-only access keys specifically for backups</li>
                                <li>Using write-enabled keys is not required and is not recommended</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- S3-Compatible fields -->
                <div id="s3Fields" class="source-type-fields">
                    <input type="hidden" name="source_display_name" id="sourceDisplayName">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Endpoint URL</label>
                        <input type="text" name="s3_endpoint" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="https://s3.storageprovider.com" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Region</label>
                        <input type="text" name="s3_region" value="ca-central-1" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="ca-central-1" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Key ID</label>
                        <input type="text" name="s3_access_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Secret Access Key</label>
                        <input type="password" name="s3_secret_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bucket Name</label>
                        <input type="text" name="s3_bucket" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path/Prefix (optional)</label>
                        <input type="text" name="s3_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="backups/">
                    </div>
                </div>

                <!-- AWS fields -->
                <div id="awsFields" class="source-type-fields hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Display Name</label>
                        <input type="text" name="aws_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="e.g., AWS S3 Production" required>
                    </div>
                    <div class="mb-4" x-data="{
                        isOpen: false,
                        search: '',
                        selected: 'ca-central-1',
                        regions: [
                            { code: 'us-east-1', name: 'US East (N. Virginia)' },
                            { code: 'us-east-2', name: 'US East (Ohio)' },
                            { code: 'us-west-1', name: 'US West (N. California)' },
                            { code: 'us-west-2', name: 'US West (Oregon)' },
                            { code: 'ca-central-1', name: 'Canada (Central)' },
                            { code: 'eu-central-1', name: 'Europe (Frankfurt)' },
                            { code: 'eu-west-1', name: 'Europe (Ireland)' },
                            { code: 'eu-west-2', name: 'Europe (London)' },
                            { code: 'eu-west-3', name: 'Europe (Paris)' },
                            { code: 'eu-north-1', name: 'Europe (Stockholm)' },
                            { code: 'eu-south-1', name: 'Europe (Milan)' },
                            { code: 'ap-south-1', name: 'Asia Pacific (Mumbai)' },
                            { code: 'ap-south-2', name: 'Asia Pacific (Hyderabad)' },
                            { code: 'ap-southeast-1', name: 'Asia Pacific (Singapore)' },
                            { code: 'ap-southeast-2', name: 'Asia Pacific (Sydney)' },
                            { code: 'ap-southeast-3', name: 'Asia Pacific (Jakarta)' },
                            { code: 'ap-southeast-4', name: 'Asia Pacific (Melbourne)' },
                            { code: 'ap-northeast-1', name: 'Asia Pacific (Tokyo)' },
                            { code: 'ap-northeast-2', name: 'Asia Pacific (Seoul)' },
                            { code: 'ap-northeast-3', name: 'Asia Pacific (Osaka)' },
                            { code: 'sa-east-1', name: 'South America (São Paulo)' },
                            { code: 'me-south-1', name: 'Middle East (Bahrain)' },
                            { code: 'me-central-1', name: 'Middle East (UAE)' },
                            { code: 'af-south-1', name: 'Africa (Cape Town)' }
                        ],
                        get filtered() {
                            if (!this.search) return this.regions;
                            const q = this.search.toLowerCase();
                            return this.regions.filter(r => r.code.toLowerCase().includes(q) || r.name.toLowerCase().includes(q));
                        }
                    }" @click.away="isOpen=false">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Region</label>
                        <input type="hidden" name="aws_region" :value="selected">
                        <div class="relative">
                            <button type="button" @click="isOpen=!isOpen" class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                <span class="block truncate" x-text="selected"></span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </span>
                            </button>
                            <div x-show="isOpen" class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg" style="display:none;">
                                <div class="p-2">
                                    <input type="text" x-model="search" placeholder="Search regions..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 overflow-auto text-base max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin">
                                    <template x-for="r in filtered" :key="r.code">
                                        <li @click="selected=r.code; isOpen=false" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700">
                                            <span x-text="r.code + ' — ' + r.name"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Key ID</label>
                        <input type="text" name="aws_access_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Secret Access Key</label>
                        <input type="password" name="aws_secret_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4" x-data="{
                        isOpen:false, loading:false, search:'', buckets:[], selected:'',
                        async load() {
                            const ak = (document.querySelector('input[name=aws_access_key]')?.value || '').trim();
                            const sk = (document.querySelector('input[name=aws_secret_key]')?.value || '').trim();
                            const rg = (document.querySelector('input[name=aws_region]')?.value || '').trim();
                            if (!ak || !sk || !rg) { if (window.toast) window.toast.error('Enter Access Key, Secret, and Region'); return; }
                            this.loading = true;
                            try {
                                const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_list_aws_buckets.php', {
                                    method:'POST',
                                    headers: new Headers([['Content-Type','application/x-www-form-urlencoded']]),
                                    body: new URLSearchParams([['access_key', ak], ['secret_key', sk], ['region', rg], ['filter_region', '1']])
                                });
                                const data = await resp.json();
                                if (data.status === 'success') {
                                    this.buckets = (data.buckets || []).map(b => b.name);
                                    this.isOpen = true;
                                    if (!this.buckets.length && window.toast) window.toast.info('No buckets found in this region');
                                } else {
                                    if (window.toast) window.toast.error(data.message || 'Failed to load buckets');
                                }
                            } catch (e) {
                                if (window.toast) window.toast.error('Error loading buckets');
                            } finally {
                                this.loading = false;
                            }
                        },
                        get filtered() {
                            if (!this.search) return this.buckets;
                            const q = this.search.toLowerCase();
                            return this.buckets.filter(n => n.toLowerCase().includes(q));
                        }
                    }" @click.away="isOpen=false">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bucket Name</label>
                        <div class="flex gap-2">
                            <input type="text" name="aws_bucket" x-model="selected" class="flex-1 bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="Select or type a bucket..." required>
                            <button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500" @click="load()" :disabled="loading">
                                <span x-show="!loading">Load buckets</span>
                                <span x-show="loading">Loading…</span>
                            </button>
                        </div>
                        <div x-show="isOpen" class="relative mt-2">
                            <div class="absolute z-10 w-full bg-slate-900 border border-gray-600 rounded-md shadow-lg">
                                <div class="p-2">
                                    <input type="text" x-model="search" placeholder="Search buckets..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 overflow-auto text-base max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin">
                                    <template x-for="b in filtered" :key="b">
                                        <li @click="selected=b; isOpen=false" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="b"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path/Prefix (optional)</label>
                        <input type="text" name="aws_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="backups/">
                    </div>
                </div>

                <!-- SFTP fields -->
                <div id="sftpFields" class="source-type-fields hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Display Name</label>
                        <input type="text" name="sftp_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="e.g., Customer NAS" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Hostname</label>
                        <input type="text" name="sftp_host" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Port</label>
                        <input type="number" name="sftp_port" value="22" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Username</label>
                        <input type="text" name="sftp_username" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                        <input type="password" name="sftp_password" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Remote Path</label>
                        <input type="text" name="sftp_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="/backups/" required>
                    </div>
                </div>

                <!-- Local Agent fields -->
                <div id="localAgentFields" class="source-type-fields hidden">
                    <div class="mb-4" x-data="{
                        options: [],
                        loading: false,
                        async load() {
                            this.loading = true;
                            try {
                                const resp = await fetch('modules/addons/cloudstorage/api/agent_list.php');
                                const data = await resp.json();
                                if (data.status === 'success') {
                                    this.options = (data.agents || []).filter(a => a.status === 'active');
                                }
                            } catch (e) {
                            } finally {
                                this.loading = false;
                            }
                        }
                    }" x-init="load()">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Agent</label>
                        <select name="agent_id" id="agent_id" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
                            <option value="">Select an agent</option>
                            <template x-for="a in options" :key="a.id">
                                <option :value="a.id" x-text="a.hostname ? (a.hostname + ' (ID ' + a.id + ')') : ('Agent #' + a.id)"></option>
                            </template>
                        </select>
                        <p class="text-xs text-slate-400 mt-1" x-show="!loading && options.length === 0">No active agents found. Create an agent first.</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Local Source Path</label>
                        <input type="text" name="local_source_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="C:\Data" />
                    </div>
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Include (glob, optional)</label>
                            <input type="text" name="local_include_glob" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="**\\*.docx" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Exclude (glob, optional)</label>
                            <input type="text" name="local_exclude_glob" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="**\\node_modules\\**" />
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bandwidth Limit (KB/s, optional)</label>
                        <input type="number" name="local_bandwidth_limit_kbps" min="0" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="0 = unlimited" />
                    </div>
                    <p class="text-xs text-slate-400">Local Agent jobs run on your Windows agent. Ensure the path exists on that machine.</p>
                </div>

                <!-- Google Drive fields -->
                <div id="gdriveFields" class="source-type-fields hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Google Drive Connection</label>
                        <div x-data="{
                            loading:false, open:false, search:'', selected:null, options:[],
                            async load() {
                                this.loading = true;
                                try {
                                    const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_list_sources.php?provider=google_drive');
                                    const data = await resp.json();
                                    if (data.status === 'success') {
                                        this.options = (data.sources || []);
                                        this.open = true;
                                        if (!this.options.length && window.toast) window.toast.info('No Google Drive connections yet');
                                    } else {
                                        if (window.toast) window.toast.error(data.message || 'Failed to load connections');
                                    }
                                } catch (e) {
                                    if (window.toast) window.toast.error('Error loading connections');
                                } finally {
                                    this.loading = false;
                                }
                            },
                            choose(opt) {
                                this.selected = opt;
                                this.open = false;
                                // Scope to the create form's Google Drive section so we don't hit the edit field
                                const input = document.querySelector('#gdriveFields input[name=source_connection_id]');
                                if (input) input.value = opt.id;
                                const disp = document.querySelector('input[name=source_display_name]');
                                if (disp && !disp.value) disp.value = opt.display_name || (opt.account_email || 'Google Drive');
                            },
                            get filtered() {
                                if (!this.search) return this.options;
                                const q = this.search.toLowerCase();
                                return this.options.filter(o => (o.display_name || '').toLowerCase().includes(q) || (o.account_email || '').toLowerCase().includes(q));
                            }
                        }" @click.away="open=false">
                            <input type="hidden" name="source_connection_id" value="">
                            <input type="hidden" name="gdrive_team_drive" value="">
                            <div class="flex gap-2 mb-2">
                                <button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500" @click="load()" :disabled="loading">
                                    <span x-show="!loading">Load connections</span>
                                    <span x-show="loading">Loading…</span>
                                </button>
                                <a href="index.php?m=cloudstorage&page=oauth_google_start" class="px-3 py-2 rounded-md bg-sky-600 text-white hover:bg-sky-700">Connect Google Drive</a>
                            </div>
                            <p class="text-xs text-slate-400">
                                By connecting, you allow eazyBackup to view and download your Google Drive files for backup and restore. We don’t use this data for ads or resale, and you can disconnect at any time in Settings or your Google Account.
                            </p>
                            <div>
                                <div class="relative">
                                    <button type="button" @click="open=!open" class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                        <span class="block truncate" x-text="selected ? ((selected.display_name || selected.account_email) || ('ID '+selected.id)) : 'Select a connection'"></span>
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                            <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        </span>
                                    </button>
                                    <div x-show="open" class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg" style="display:none;">
                                        <div class="p-2">
                                            <input type="text" x-model="search" placeholder="Search connections..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                        </div>
                                        <ul class="py-1 overflow-auto text-base max-h-60 focus:outline-none sm:text-sm scrollbar_thin">
                                            <template x-for="opt in filtered" :key="opt.id">
                                                <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700">
                                                    <span x-text="(opt.display_name || opt.account_email) || ('ID '+opt.id)"></span>
                                                    <span class="ml-2 text-[11px] text-slate-400" x-text="opt.account_email"></span>
                                                </li>
                                            </template>
                                            <template x-if="filtered.length === 0">
                                                <li class="px-4 py-2 text-gray-400">No connections.</li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400">Select a saved Google Drive connection or click Connect to add one.</p>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <!-- Hidden fields populated by the Drive picker -->
                        <input type="hidden" name="gdrive_root_folder_id" value="">
                        <input type="hidden" name="gdrive_selected_id" value="">
                        <input type="hidden" name="gdrive_selected_type" value="">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-300">Select from Google Drive</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Choose a folder or a single file to back up.</p>
                            </div>
                            <button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500"
                                    onclick="openDrivePicker('create')">
                                Browse Drive
                            </button>
                        </div>
                    </div>
                    <!-- Path is not used for Google Drive selection via picker -->
                </div>

                <!-- Dropbox fields -->
                <div id="dropboxFields" class="source-type-fields hidden">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Display Name</label>
                        <input type="text" name="dropbox_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="e.g., Dropbox Team" />
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Token</label>
                        <input type="text" name="dropbox_token" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Root Path (optional)</label>
                        <input type="text" name="dropbox_root" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="/folder/" />
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path (optional)</label>
                        <input type="text" name="dropbox_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="/folder/sub/" />
                    </div>
                </div>
            </div>

            <!-- Step 3: Destination -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Destination Bucket</label>
                <div
                    x-data="{
                        isOpen: false,
                        search: '',
                        selectedId: '',
                        selectedName: '',
                        options: [
                            {foreach from=$buckets item=bucket name=bloop}
                                { id: '{$bucket->id}', name: '{$bucket->name|escape:'javascript'}' }{if !$smarty.foreach.bloop.last},{/if}
                            {/foreach}
                        ],
                        get filtered() {
                            const q = (this.search || '').toLowerCase();
                            if (!q) return this.options;
                            return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                        },
                        choose(opt) {
                            if (!opt) return;
                            this.selectedId = String(opt.id || '');
                            this.selectedName = opt.name || '';
                            const sel = this.$root.querySelector('select[data-dest-bucket-src]');
                            if (sel) {
                                sel.value = this.selectedId;
                                sel.dispatchEvent(new Event('change'));
                            }
                            this.isOpen = false;
                        }
                    }"
                    @click.away="isOpen=false"
                >
                    <!-- Hidden input used by form submit -->
                    <input type="hidden" name="dest_bucket_id" :value="selectedId" required>

                    <!-- Hidden/disabled select kept for internal syncing and for edit panel population -->
                    <select data-dest-bucket-src name="dest_bucket_id" class="hidden" disabled>
                        {foreach from=$buckets item=bucket}
                            <option value="{$bucket->id}">{$bucket->name}</option>
                        {/foreach}
                    </select>

                    <div class="relative">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                            <span class="block truncate" x-text="selectedName || 'Select a bucket'"></span>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>
                        <!-- Dropdown panel -->
                        <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-600 rounded-md shadow-lg">
                            <div class="p-2">
                                <input type="text" x-model="search" placeholder="Search buckets..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                            </div>
                            <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                <template x-for="opt in filtered" :key="opt.id">
                                    <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                </template>
                                <template x-if="filtered.length === 0">
                                    <li class="px-4 py-2 text-gray-400">No buckets found.</li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <!-- Sync Alpine state with hidden select on init and when changed externally -->
                    <div x-init="
                        (function() {
                            const sel = $root.querySelector('select[data-dest-bucket-src]');
                            if (sel) {
                                sel.addEventListener('change', () => {
                                    const o = sel.options[sel.selectedIndex];
                                    selectedId = o ? String(o.value) : '';
                                    selectedName = o ? o.text : '';
                                });
                                if (sel.options.length) {
                                    sel.selectedIndex = 0;
                                    sel.dispatchEvent(new Event('change'));
                                }
                            }
                        })()
                    "></div>
                </div>
            </div>
            <!-- Create Bucket Button (opens modal) -->
            <div class="mb-4">
                <button type="button"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-dashed border-slate-600 text-slate-400 hover:text-sky-400 hover:border-sky-500/50 transition text-sm"
                        onclick="openBucketCreateModal(onCloudBackupBucketCreated)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Create new bucket
                </button>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Destination Prefix</label>
                <input type="text" name="dest_prefix" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="backups/source-name/" required>
            </div>

            <!-- Step 4: Backup Mode -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Backup Mode</label>
                <div
                    x-data="{
                        isOpen:false,
                        search:'',
                        selectedId:'sync',
                        selectedName:'Sync (Incremental)',
                        options:[
                            { id:'sync', name:'Sync (Incremental)' },
                            { id:'archive', name:'Archive (Compressed)' }
                        ],
                        get filtered() {
                            const q = (this.search || '').toLowerCase();
                            if (!q) return this.options;
                            return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                        },
                        choose(opt) {
                            if (!opt) return;
                            this.selectedId = String(opt.id || '');
                            this.selectedName = opt.name || '';
                            const sel = this.$refs.real;
                            if (sel) {
                                sel.value = this.selectedId;
                                try { sel.dispatchEvent(new Event('change')); } catch (e) {}
                            }
                            this.isOpen = false;
                        }
                    }"
                    @click.away="isOpen=false"
                >
                    <!-- Real form control kept hidden for FormData compatibility -->
                    <select id="backupMode" name="backup_mode" class="hidden" x-ref="real">
                        <option value="sync" selected>Sync (Incremental)</option>
                        <option value="archive">Archive (Compressed)</option>
                    </select>
                    <div class="relative">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                            <span class="block truncate" x-text="selectedName"></span>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>
                        <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-600 rounded-md shadow-lg">
                            <div class="p-2">
                                <input type="text" x-model="search" placeholder="Search…" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                            </div>
                            <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                <template x-for="opt in filtered" :key="opt.id">
                                    <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                </template>
                                <template x-if="filtered.length === 0">
                                    <li class="px-4 py-2 text-gray-400">No options.</li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <div x-init="
                        (function(){
                            const sel = $refs.real;
                            if (sel) {
                                const o = sel.options[sel.selectedIndex];
                                selectedId = o ? String(o.value) : 'sync';
                                selectedName = o ? o.text : 'Sync (Incremental)';
                            }
                        })()
                    "></div>
                </div>
                <p class="mt-1 text-xs text-slate-400">
                    <strong>Sync:</strong> Transfers files incrementally, preserving structure. <strong>Archive:</strong> Creates a compressed archive file per run.
                </p>
            </div>

            <!-- Step 4b: Encryption -->
            {* <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="encryption_enabled" value="1" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                    <span class="ml-2 text-sm font-medium text-slate-300">Enable Encryption</span>
                </label>
                <p class="mt-1 ml-6 text-xs text-slate-400">
                    Encrypts backup data.<strong>Warning:</strong> Encryption cannot be disabled after enabling. Ensure you keep your encryption password secure.
                </p>
            </div> *}

            <!-- Step 4c: Validation -->
            {* <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="validation_enabled" value="1" id="validationEnabled" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                    <span class="ml-2 text-sm font-medium text-slate-300">Enable Post-Run Validation</span>
                </label>
                <p class="mt-1 ml-6 text-xs text-slate-400">
                    Runs check after each backup to verify data integrity. This may increase backup time but ensures data consistency.
                </p>
            </div> *}

            <!-- Step 4d: Retention Policy -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Retention Policy</label>
                <div
                    x-data="{
                        isOpen:false,
                        search:'',
                        selectedId:'none',
                        selectedName:'No Retention',
                        options:[
                            { id:'none', name:'No Retention' },
                            { id:'keep_last_n', name:'Keep Last N Runs' },
                            { id:'keep_days', name:'Keep for N Days' }
                        ],
                        get filtered() {
                            const q = (this.search || '').toLowerCase();
                            if (!q) return this.options;
                            return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                        },
                        choose(opt) {
                            if (!opt) return;
                            this.selectedId = String(opt.id || '');
                            this.selectedName = opt.name || '';
                            const sel = this.$refs.real;
                            if (sel) {
                                sel.value = this.selectedId;
                                try { sel.dispatchEvent(new Event('change')); } catch (e) {}
                            }
                            this.isOpen = false;
                        }
                    }"
                    @click.away="isOpen=false"
                >
                    <!-- Hidden native select preserved for JS and payload -->
                    <select name="retention_mode" id="retentionMode" class="hidden" x-ref="real" onchange="onRetentionModeChange()">
                        <option value="none">No Retention</option>
                        <option value="keep_last_n">Keep Last N Runs</option>
                        <option value="keep_days">Keep for N Days</option>
                    </select>
                    <div class="relative">
                        <button type="button"
                                @click="selectedId = ($refs.real?.value || selectedId); selectedName = ($refs.real?.options[$refs.real.selectedIndex]?.text || selectedName); isOpen = !isOpen"
                                class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                            <span class="block truncate" x-text="selectedName"></span>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>
                        <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-gray-600 rounded-md shadow-lg">
                            <div class="p-2">
                                <input type="text" x-model="search" placeholder="Search…" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                            </div>
                            <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                <template x-for="opt in filtered" :key="opt.id">
                                    <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                </template>
                                <template x-if="filtered.length === 0">
                                    <li class="px-4 py-2 text-gray-400">No options.</li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <div x-init="
                        (function(){
                            const sel = $refs.real;
                            if (sel) {
                                const o = sel.options[sel.selectedIndex];
                                selectedId = o ? String(o.value) : 'none';
                                selectedName = o ? o.text : 'No Retention';
                                sel.addEventListener('change', () => {
                                    const oc = sel.options[sel.selectedIndex];
                                    selectedId = oc ? String(oc.value) : 'none';
                                    selectedName = oc ? oc.text : 'No Retention';
                                });
                            }
                        })()
                    "></div>
                </div>
                <div id="retentionValueContainer" class="mt-2 hidden">
                    <input type="number" name="retention_value" id="retentionValue" min="1" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="Enter number">
                    <p class="mt-1 text-xs text-slate-400" id="retentionHelp"></p>
                </div>
            </div>

            <!-- Step 5: Schedule -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Schedule</label>
                <div
                    x-data="{
                        isOpen:false,
                        search:'',
                        selectedId:'manual',
                        selectedName:'Manual Only',
                        options:[
                            { id:'manual', name:'Manual Only' },
                            { id:'daily', name:'Daily' },
                            { id:'weekly', name:'Weekly' }
                        ],
                        get filtered() {
                            const q = (this.search || '').toLowerCase();
                            if (!q) return this.options;
                            return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                        },
                        choose(opt) {
                            if (!opt) return;
                            this.selectedId = String(opt.id || '');
                            this.selectedName = opt.name || '';
                            const sel = this.$refs.real;
                            if (sel) {
                                sel.value = this.selectedId;
                                try { sel.dispatchEvent(new Event('change')); } catch (e) {}
                            }
                            this.isOpen = false;
                        }
                    }"
                    @click.away="isOpen=false"
                >
                    <!-- Real form control kept hidden for FormData + existing JS listeners -->
                    <select id="scheduleType" name="schedule_type" class="hidden" x-ref="real">
                        <option value="manual" selected>Manual Only</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                    <div class="relative">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                            <span class="block truncate" x-text="selectedName"></span>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </button>
                        <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-gray-600 rounded-md shadow-lg">
                            <div class="p-2">
                                <input type="text" x-model="search" placeholder="Search…" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                            </div>
                            <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                <template x-for="opt in filtered" :key="opt.id">
                                    <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                </template>
                                <template x-if="filtered.length === 0">
                                    <li class="px-4 py-2 text-gray-400">No options.</li>
                                </template>
                            </ul>
                        </div>
                    </div>
                    <div x-init="
                        (function(){
                            const sel = $refs.real;
                            if (sel) {
                                const o = sel.options[sel.selectedIndex];
                                selectedId = o ? String(o.value) : 'manual';
                                selectedName = o ? o.text : 'Manual Only';
                            }
                        })()
                    "></div>
                </div>
            </div>
            <div id="scheduleOptions" class="mb-4 hidden">
                <div class="mb-2">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Time</label>
                    <input type="time" name="schedule_time" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                </div>
                <div id="weeklyOption" class="mb-2 hidden">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Weekday</label>
                    <select name="schedule_weekday" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600">
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                        <option value="7">Sunday</option>
                    </select>
                </div>
            </div>

            <!-- Job Name -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-300 mb-2">Job Name</label>
                <input type="text" name="name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" required>
            </div>

            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeCreateSlideover()" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-md">Cancel</button>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">Create Job</button>
            </div>
        </form>

        <!-- No Schedule confirmation modal -->
        <div id="noScheduleModal" x-data="{ open:false }" x-show="open" class="fixed inset-0 z-[9998] flex items-center justify-center" style="display:none;">
            <div class="absolute inset-0 bg-black/60" @click="open=false" onclick="hideNoScheduleModal()"></div>
            <div class="relative w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900/90 shadow-2xl p-5">
                <div class="flex items-start gap-3">
                    <div class="mt-1 flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/15 text-amber-300 border border-amber-400/30">
                        !
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold text-white">Create without a schedule?</h3>
                        <p class="mt-1 text-sm text-slate-300">
                            This backup will not run automatically. You can add a schedule later from the job settings.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 rounded-md border border-slate-600 text-slate-200 hover:border-slate-500" @click="open=false" onclick="hideNoScheduleModal()">Cancel</button>
                    <button type="button" class="px-4 py-2 rounded-md bg-emerald-600 text-white hover:bg-emerald-700" onclick="confirmNoScheduleCreate()">Create without schedule</button>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<!-- Edit Job Slide-Over (dynamically populated) -->
<div id="editJobSlideover" x-data="{ isOpen: false }" x-show="isOpen" class="fixed inset-0 z-50" style="display: none;">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/75"
         x-show="isOpen"
         x-transition.opacity
         onclick="closeEditSlideover()"></div>
    <!-- Panel -->
    <div class="absolute right-0 top-0 h-full w-full max-w-xl bg-slate-950 border-l border-slate-800/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] overflow-y-auto"
         x-show="isOpen"
         x-transition:enter="transform transition ease-in-out duration-300"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transform transition ease-in-out duration-300"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">
        <div class="flex items-center justify-between p-4 border-b border-slate-700">
            <h3 class="text-lg font-semibold text-white">Edit Backup Job</h3>
            <button class="text-slate-300 hover:text-white" onclick="closeEditSlideover()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-4">
            <style>
            /* Panel + form controls - dark slate theme */
            #editJobSlideover ::placeholder { color: #94a3b8; opacity: 1; }
            #editJobSlideover .border-slate-700 { border-color: rgba(51,65,85,1); }
            #editJobSlideover input[type="text"],
            #editJobSlideover input[type="password"],
            #editJobSlideover input[type="number"],
            #editJobSlideover input[type="time"],
            #editJobSlideover select,
            #editJobSlideover textarea {
                background-color: rgb(15 23 42) !important; /* bg-slate-900 */
                border-color: rgba(51,65,85,1) !important;        /* border-slate-700 */
                color: #e2e8f0 !important;                        /* text-slate-200 */
            }
            #editJobSlideover input:focus,
            #editJobSlideover select:focus,
            #editJobSlideover textarea:focus {
                outline: none !important;
                border-color: rgb(14 165 233 / 1) !important;     /* border-sky-500 */                
            }
            /* Common dropdown panels */
            #editJobSlideover .dropdown-surface {
                background-color: rgb(2 6 23);                    /* bg-slate-950 */
                border-color: rgba(51,65,85,1);
            }
            </style>
            <div id="editJobMessage" class="bg-red-600 text-white px-4 py-2 rounded-md mb-4 hidden"></div>
            <form id="editJobForm" onsubmit="return false;">
                <input type="hidden" id="edit_job_id" name="job_id" />
                <!-- Defaults to ensure predictable values even if checkboxes are not rendered -->
                <input type="hidden" name="encryption_enabled" value="0" />
                <input type="hidden" name="validation_mode" value="none" />

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Job Name</label>
                    <input type="text" id="edit_name" name="name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" required />
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Source Type</label>
                    <select id="edit_source_type" name="source_type" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" onchange="onEditSourceTypeChange()">
                        <option value="s3_compatible">S3-Compatible Storage</option>
                        <option value="aws">Amazon S3 (AWS)</option>
                        <option value="sftp">SFTP/SSH Server</option>
                        <option value="google_drive">Google Drive</option>
                        <option value="dropbox">Dropbox</option>
                        <option value="local_agent">Local Agent (Windows)</option>
                    </select>
                </div>

                {* <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Source Display Name</label>
                    <input type="text" id="edit_source_display_name" name="source_display_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" required />
                </div> *}

                <!-- Security Warning: Read-Only Access Keys -->
                <div id="sourceAccessWarning" class="mb-4 p-4 bg-slate-800 border border-gray-600 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-bold text-gray-200 mb-1">Use READ-ONLY Access Keys</h3>
                            
                            <ul class="text-xs text-gray-300 list-disc list-inside space-y-1">
                                <li>Do not use access keys with write, delete, or modify permissions</li>
                                <li>Create dedicated read-only access keys specifically for backups</li>
                                <li>Using write-enabled keys is not required and is not recommended</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div id="edit_s3_fields" class="mb-4">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Endpoint URL</label>
                        <input type="text" id="edit_s3_endpoint" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="https://s3.storageprovider.com" />
                    </div>
                    <div class="mb-3" x-data="{
                        isOpen: false,
                        search: '',
                        selected: 'us-east-1',
                        regions: [
                            { code: 'us-east-1', name: 'US East (N. Virginia)' },
                            { code: 'us-east-2', name: 'US East (Ohio)' },
                            { code: 'us-west-1', name: 'US West (N. California)' },
                            { code: 'us-west-2', name: 'US West (Oregon)' },
                            { code: 'ca-central-1', name: 'Canada (Central)' },
                            { code: 'eu-central-1', name: 'Europe (Frankfurt)' },
                            { code: 'eu-west-1', name: 'Europe (Ireland)' },
                            { code: 'eu-west-2', name: 'Europe (London)' },
                            { code: 'eu-west-3', name: 'Europe (Paris)' },
                            { code: 'eu-north-1', name: 'Europe (Stockholm)' },
                            { code: 'eu-south-1', name: 'Europe (Milan)' },
                            { code: 'ap-south-1', name: 'Asia Pacific (Mumbai)' },
                            { code: 'ap-south-2', name: 'Asia Pacific (Hyderabad)' },
                            { code: 'ap-southeast-1', name: 'Asia Pacific (Singapore)' },
                            { code: 'ap-southeast-2', name: 'Asia Pacific (Sydney)' },
                            { code: 'ap-southeast-3', name: 'Asia Pacific (Jakarta)' },
                            { code: 'ap-southeast-4', name: 'Asia Pacific (Melbourne)' },
                            { code: 'ap-northeast-1', name: 'Asia Pacific (Tokyo)' },
                            { code: 'ap-northeast-2', name: 'Asia Pacific (Seoul)' },
                            { code: 'ap-northeast-3', name: 'Asia Pacific (Osaka)' },
                            { code: 'sa-east-1', name: 'South America (São Paulo)' },
                            { code: 'me-south-1', name: 'Middle East (Bahrain)' },
                            { code: 'me-central-1', name: 'Middle East (UAE)' },
                            { code: 'af-south-1', name: 'Africa (Cape Town)' }
                        ],
                        get filtered() {
                            if (!this.search) return this.regions;
                            const q = this.search.toLowerCase();
                            return this.regions.filter(r => r.code.toLowerCase().includes(q) || r.name.toLowerCase().includes(q));
                        }
                    }" @click.away="isOpen=false">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Region</label>
                        <input type="hidden" id="edit_s3_region" :value="selected" x-ref="real">
                        <div class="relative">
                            <button type="button" @click="
                                    // sync from hidden if pre-set by JS when opening panel
                                    selected = ($refs.real?.value || selected);
                                    isOpen = !isOpen
                                " class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                <span class="block truncate" x-text="selected"></span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </span>
                            </button>
                            <div x-show="isOpen" class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg" style="display:none;">
                                <div class="p-2">
                                    <input type="text" x-model="search" placeholder="Search regions..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 overflow-auto text-base max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin">
                                    <template x-for="r in filtered" :key="r.code">
                                        <li @click="selected=r.code; if ($refs.real) { $refs.real.value = selected; try { $refs.real.dispatchEvent(new Event('change')); } catch(e) {} } ; isOpen=false" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700">
                                            <span x-text="r.code + ' — ' + r.name"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <div x-init="
                            (function(){
                                const sel = $refs.real;
                                if (sel) {
                                    // initialize from hidden input (populated when opening panel)
                                    selected = sel.value || selected;
                                    sel.addEventListener('change', () => {
                                        selected = sel.value || selected;
                                    });
                                }
                            })()
                        "></div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Key ID</label>
                        <input type="text" id="edit_s3_access_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Leave blank to keep existing" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Secret Access Key</label>
                        <input type="password" id="edit_s3_secret_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Leave blank to keep existing" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Source Bucket Name</label>
                        <input type="text" id="edit_s3_bucket" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path/Prefix (optional)</label>
                        <input type="text" id="edit_s3_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="backups/" />
                    </div>
                </div>

                <div id="edit_aws_fields" class="mb-4 hidden">
                    <!-- Region combobox (mirrors Create UI) -->
                    <div class="mb-3" x-data="{
                        isOpen: false,
                        search: '',
                        selected: 'us-east-1',
                        regions: [
                            { code: 'us-east-1', name: 'US East (N. Virginia)' },
                            { code: 'us-east-2', name: 'US East (Ohio)' },
                            { code: 'us-west-1', name: 'US West (N. California)' },
                            { code: 'us-west-2', name: 'US West (Oregon)' },
                            { code: 'ca-central-1', name: 'Canada (Central)' },
                            { code: 'eu-central-1', name: 'Europe (Frankfurt)' },
                            { code: 'eu-west-1', name: 'Europe (Ireland)' },
                            { code: 'eu-west-2', name: 'Europe (London)' },
                            { code: 'eu-west-3', name: 'Europe (Paris)' },
                            { code: 'eu-north-1', name: 'Europe (Stockholm)' },
                            { code: 'eu-south-1', name: 'Europe (Milan)' },
                            { code: 'ap-south-1', name: 'Asia Pacific (Mumbai)' },
                            { code: 'ap-south-2', name: 'Asia Pacific (Hyderabad)' },
                            { code: 'ap-southeast-1', name: 'Asia Pacific (Singapore)' },
                            { code: 'ap-southeast-2', name: 'Asia Pacific (Sydney)' },
                            { code: 'ap-southeast-3', name: 'Asia Pacific (Jakarta)' },
                            { code: 'ap-southeast-4', name: 'Asia Pacific (Melbourne)' },
                            { code: 'ap-northeast-1', name: 'Asia Pacific (Tokyo)' },
                            { code: 'ap-northeast-2', name: 'Asia Pacific (Seoul)' },
                            { code: 'ap-northeast-3', name: 'Asia Pacific (Osaka)' },
                            { code: 'sa-east-1', name: 'South America (São Paulo)' },
                            { code: 'me-south-1', name: 'Middle East (Bahrain)' },
                            { code: 'me-central-1', name: 'Middle East (UAE)' },
                            { code: 'af-south-1', name: 'Africa (Cape Town)' }
                        ],
                        get filtered() {
                            if (!this.search) return this.regions;
                            const q = this.search.toLowerCase();
                            return this.regions.filter(r => r.code.toLowerCase().includes(q) || r.name.toLowerCase().includes(q));
                        }
                    }" @click.away="isOpen=false">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Region</label>
                        <input type="hidden" id="edit_aws_region" :value="selected">
                        <div class="relative">
                            <button type="button" @click="isOpen=!isOpen" class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                <span class="block truncate" x-text="selected"></span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                </span>
                            </button>
                            <div x-show="isOpen" class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg" style="display:none;">
                                <div class="p-2">
                                    <input type="text" x-model="search" placeholder="Search regions..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 overflow-auto text-base max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin">
                                    <template x-for="r in filtered" :key="r.code">
                                        <li @click="selected=r.code; isOpen=false" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700">
                                            <span x-text="r.code + ' — ' + r.name"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Key ID</label>
                        <input type="text" id="edit_aws_access_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Leave blank to keep existing" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Secret Access Key</label>
                        <input type="password" id="edit_aws_secret_key" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Leave blank to keep existing" />
                    </div>
                    <!-- Bucket selector with Load buckets (mirrors Create UI, adapted for edit_) -->
                    <div class="mb-3" x-data="{
                        isOpen:false, loading:false, search:'', buckets:[], selected:'',
                        async load() {
                            const ak = (document.getElementById('edit_aws_access_key')?.value || '').trim();
                            const sk = (document.getElementById('edit_aws_secret_key')?.value || '').trim();
                            const rg = (document.getElementById('edit_aws_region')?.value || '').trim();
                            if (!ak || !sk || !rg) { if (window.toast) window.toast.error('Enter Access Key, Secret, and Region'); return; }
                            this.loading = true;
                            try {
                                const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_list_aws_buckets.php', {
                                    method:'POST',
                                    headers: new Headers([['Content-Type','application/x-www-form-urlencoded']]),
                                    body: new URLSearchParams([['access_key', ak], ['secret_key', sk], ['region', rg], ['filter_region', '1']])
                                });
                                const data = await resp.json();
                                if (data.status === 'success') {
                                    this.buckets = (data.buckets || []).map(b => b.name);
                                    this.isOpen = true;
                                    if (!this.buckets.length && window.toast) window.toast.info('No buckets found in this region');
                                } else {
                                    if (window.toast) window.toast.error(data.message || 'Failed to load buckets');
                                }
                            } catch (e) {
                                if (window.toast) window.toast.error('Error loading buckets');
                            } finally {
                                this.loading = false;
                            }
                        },
                        get filtered() {
                            if (!this.search) return this.buckets;
                            const q = this.search.toLowerCase();
                            return this.buckets.filter(n => n.toLowerCase().includes(q));
                        }
                    }" @click.away="isOpen=false">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bucket Name</label>
                        <div class="flex gap-2">
                            <input type="text" id="edit_aws_bucket" x-model="selected" class="flex-1 bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="Select or type a bucket..." />
                            <button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500" @click="load()" :disabled="loading">
                                <span x-show="!loading">Load buckets</span>
                                <span x-show="loading">Loading…</span>
                            </button>
                        </div>
                        <div x-show="isOpen" class="relative mt-2">
                            <div class="absolute z-10 w-full bg-slate-900 border border-gray-600 rounded-md shadow-lg">
                                <div class="p-2">
                                    <input type="text" x-model="search" placeholder="Search buckets..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 overflow-auto text-base max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin">
                                    <template x-for="b in filtered" :key="b">
                                        <li @click="selected=b; isOpen=false" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="b"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path/Prefix (optional)</label>
                        <input type="text" id="edit_aws_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="backups/" />
                    </div>
                </div>

                <div id="edit_sftp_fields" class="mb-4 hidden">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Hostname</label>
                        <input type="text" id="edit_sftp_host" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Port</label>
                        <input type="number" id="edit_sftp_port" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" value="22" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Username</label>
                        <input type="text" id="edit_sftp_username" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                        <input type="password" id="edit_sftp_password" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Leave blank to keep existing" />
                    </div>
                </div>

                <div id="edit_local_fields" class="mb-4 hidden">
                    <div class="mb-3" x-data="{
                        options: [],
                        loading: false,
                        async load(selectedId) {
                            this.loading = true;
                            try {
                                const resp = await fetch('modules/addons/cloudstorage/api/agent_list.php');
                                const data = await resp.json();
                                if (data.status === 'success') {
                                    this.options = (data.agents || []).filter(a => a.status === 'active');
                                    const sel = document.getElementById('edit_agent_id');
                                    if (sel && selectedId) {
                                        sel.value = String(selectedId);
                                    }
                                }
                            } catch (e) {
                            } finally {
                                this.loading = false;
                            }
                        }
                    }" x-init="load(document.getElementById('edit_agent_id') ? document.getElementById('edit_agent_id').value : '')">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Agent</label>
                        <select id="edit_agent_id" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" required>
                            <option value="">Select an agent</option>
                            <template x-for="a in options" :key="a.id">
                                <option :value="a.id" x-text="a.hostname ? (a.hostname + ' (ID ' + a.id + ')') : ('Agent #' + a.id)"></option>
                            </template>
                        </select>
                        <p class="text-xs text-slate-400 mt-1" x-show="!loading && options.length === 0">No active agents found. Create an agent first.</p>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Local Source Path</label>
                        <input type="text" id="edit_local_source_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="C:\Data" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Include (glob, optional)</label>
                            <input type="text" id="edit_local_include_glob" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="**\\*.docx" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-300 mb-2">Exclude (glob, optional)</label>
                            <input type="text" id="edit_local_exclude_glob" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="**\\node_modules\\**" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Bandwidth Limit (KB/s, optional)</label>
                        <input type="number" id="edit_local_bandwidth_limit_kbps" min="0" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="0 = unlimited" />
                    </div>
                </div>

                <div id="edit_gdrive_fields" class="mb-4 hidden">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Google Drive Connection</label>
                        <div x-data="{
                            loading:false, open:false, search:'', selected:null, options:[],
                            async load() {
                                this.loading = true;
                                try {
                                    const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_list_sources.php?provider=google_drive');
                                    const data = await resp.json();
                                    if (data.status === 'success') {
                                        this.options = (data.sources || []);
                                        this.open = true;
                                        if (!this.options.length && window.toast) window.toast.info('No Google Drive connections yet');
                                    } else {
                                        if (window.toast) window.toast.error(data.message || 'Failed to load connections');
                                    }
                                } catch (e) {
                                    if (window.toast) window.toast.error('Error loading connections');
                                } finally {
                                    this.loading = false;
                                }
                            },
                            choose(opt) {
                                this.selected = opt;
                                this.open = false;
                                const input = document.getElementById('edit_source_connection_id');
                                if (input) input.value = opt.id;
                                const disp = document.getElementById('edit_source_display_name');
                                if (disp && !disp.value) disp.value = opt.display_name || (opt.account_email || 'Google Drive');
                            },
                            get filtered() {
                                if (!this.search) return this.options;
                                const q = this.search.toLowerCase();
                                return this.options.filter(o => (o.display_name || '').toLowerCase().includes(q) || (o.account_email || '').toLowerCase().includes(q));
                            }
                        }" @click.away="open=false" x-init="(function(){ const id = document.getElementById('edit_source_connection_id') ? document.getElementById('edit_source_connection_id').value : ''; if (id) { selected = { id: id }; } })()">
                            <input type="hidden" id="edit_source_connection_id" name="source_connection_id" value="">
                            <input type="hidden" id="edit_gdrive_team_drive" name="gdrive_team_drive" value="">
                            <div class="flex gap-2 mb-2">
                                <button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500" @click="load()" :disabled="loading">
                                    <span x-show="!loading">Load connections</span>
                                    <span x-show="loading">Loading…</span>
                                </button>
                                <a href="index.php?m=cloudstorage&page=oauth_google_start" class="px-3 py-2 rounded-md bg-sky-600 text-white hover:bg-sky-700">Connect Google Drive</a>
                            </div>
                            <div class="relative">
                                <button type="button" @click="open=!open" class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                    <span class="block truncate" x-text="selected ? ((selected.display_name || selected.account_email) || ('ID '+selected.id)) : 'Select a connection'"></span>
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                        <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                    </span>
                                </button>
                                <div x-show="open" class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg" style="display:none;">
                                    <div class="p-2">
                                        <input type="text" x-model="search" placeholder="Search connections..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                    </div>
                                    <ul class="py-1 overflow-auto text-base max-h-60 focus:outline-none sm:text-sm scrollbar_thin">
                                        <template x-for="opt in filtered" :key="opt.id">
                                            <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700">
                                                <span x-text="(opt.display_name || opt.account_email) || ('ID '+opt.id)"></span>
                                                <span class="ml-2 text-[11px] text-slate-400" x-text="opt.account_email"></span>
                                            </li>
                                        </template>
                                        <template x-if="filtered.length === 0">
                                            <li class="px-4 py-2 text-gray-400">No connections.</li>
                                        </template>
                                    </ul>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400">Select a saved Google Drive connection or click Connect to add one.</p>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <!-- Hidden fields populated by the Drive picker -->
                        <input type="hidden" id="edit_gdrive_root_folder_id" name="gdrive_root_folder_id" value="">
                        <input type="hidden" name="gdrive_selected_id" value="">
                        <input type="hidden" name="gdrive_selected_type" value="">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-slate-300">Select from Google Drive</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">Choose a folder or a single file to back up.</p>
                            </div>
                            <button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500"
                                    onclick="openDrivePicker('edit')">
                                Browse Drive
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path (optional)</label>
                        <input type="text" id="edit_gdrive_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="folder/subfolder/" />
                    </div>
                </div>

                <div id="edit_dropbox_fields" class="mb-4 hidden">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Access Token</label>
                        <input type="text" id="edit_dropbox_token" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Root Path (optional)</label>
                        <input type="text" id="edit_dropbox_root" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="/folder/" />
                    </div>
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Path (optional)</label>
                        <input type="text" id="edit_dropbox_path" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="/folder/sub/" />
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Destination Bucket</label>
                    <div
                        x-data="{
                            isOpen: false,
                            search: '',
                            selectedId: '',
                            selectedName: '',
                            options: [
                                {foreach from=$buckets item=bucket name=bloop2}
                                    { id: '{$bucket->id}', name: '{$bucket->name|escape:'javascript'}' }{if !$smarty.foreach.bloop2.last},{/if}
                                {/foreach}
                            ],
                            get filtered() {
                                const q = (this.search || '').toLowerCase();
                                if (!q) return this.options;
                                return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                            },
                            choose(opt) {
                                if (!opt) return;
                                this.selectedId = String(opt.id || '');
                                this.selectedName = opt.name || '';
                                const sel = this.$root.querySelector('select[data-edit-dest-bucket-src]');
                                if (sel) {
                                    sel.value = this.selectedId;
                                    sel.dispatchEvent(new Event('change'));
                                }
                                this.isOpen = false;
                            }
                        }"
                        @click.away="isOpen=false"
                    >
                        <!-- Hidden input used by update payload builder -->
                        <input type="hidden" id="edit_dest_bucket_id" :value="selectedId">

                        <!-- Hidden/disabled select kept for internal syncing -->
                        <select id="edit_dest_bucket_src" data-edit-dest-bucket-src class="hidden" disabled>
                            {foreach from=$buckets item=bucket}
                                <option value="{$bucket->id}">{$bucket->name}</option>
                            {/foreach}
                        </select>

                        <div class="relative">
                            <button type="button"
                                    @click="isOpen = !isOpen"
                                    class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                <span class="block truncate" x-text="selectedName || 'Select a bucket'"></span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </span>
                            </button>
                            <!-- Dropdown panel -->
                            <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-600 rounded-md shadow-lg">
                                <div class="p-2">
                                    <input type="text" x-model="search" placeholder="Search buckets..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                    <template x-for="opt in filtered" :key="opt.id">
                                        <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                    </template>
                                    <template x-if="filtered.length === 0">
                                        <li class="px-4 py-2 text-gray-400">No buckets found.</li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                        <!-- Sync Alpine state with hidden select on init and when changed externally -->
                        <div x-init="
                            (function() {
                                const sel = $root.querySelector('select[data-edit-dest-bucket-src]');
                                if (sel) {
                                    sel.addEventListener('change', () => {
                                        const o = sel.options[sel.selectedIndex];
                                        selectedId = o ? String(o.value) : '';
                                        selectedName = o ? o.text : '';
                                    });
                                    if (sel.options.length) {
                                        sel.selectedIndex = 0;
                                        sel.dispatchEvent(new Event('change'));
                                    }
                                }
                            })()
                        "></div>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Destination Prefix</label>
                    <input type="text" id="edit_dest_prefix" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="backups/source-name/" required />
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Backup Mode</label>
                    <div
                        x-data="{
                            isOpen:false,
                            search:'',
                            selectedId:'sync',
                            selectedName:'Sync (Incremental)',
                            options:[
                                { id:'sync', name:'Sync (Incremental)' },
                                { id:'archive', name:'Archive (Compressed)' }
                            ],
                            get filtered() {
                                const q = (this.search || '').toLowerCase();
                                if (!q) return this.options;
                                return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                            },
                            choose(opt) {
                                if (!opt) return;
                                this.selectedId = String(opt.id || '');
                                this.selectedName = opt.name || '';
                                const sel = this.$refs.real;
                                if (sel) {
                                    sel.value = this.selectedId;
                                    try { sel.dispatchEvent(new Event('change')); } catch (e) {}
                                }
                                this.isOpen = false;
                            }
                        }"
                        @click.away="isOpen=false"
                    >
                        <!-- Real form control kept hidden for compatibility with existing JS and payload building -->
                        <select id="edit_backup_mode" name="backup_mode" class="hidden" x-ref="real">
                            <option value="sync">Sync (Incremental)</option>
                            <option value="archive">Archive (Compressed)</option>
                        </select>
                        <div class="relative">
                            <button type="button"
                                    @click="selectedId = ($refs.real?.value || selectedId); selectedName = ($refs.real?.options[$refs.real.selectedIndex]?.text || selectedName); isOpen = !isOpen"
                                    class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                <span class="block truncate" x-text="selectedName"></span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </span>
                            </button>
                            <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-600 rounded-md shadow-lg">
                                <div class="p-2">
                                    <input type="text" x-model="search" placeholder="Search…" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                    <template x-for="opt in filtered" :key="opt.id">
                                        <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                    </template>
                                    <template x-if="filtered.length === 0">
                                        <li class="px-4 py-2 text-gray-400">No options.</li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <div x-init="
                            (function(){
                                const sel = $refs.real;
                                if (sel) {
                                    const o = sel.options[sel.selectedIndex];
                                    selectedId = o ? String(o.value) : 'sync';
                                    selectedName = o ? o.text : 'Sync (Incremental)';
                                }
                            })()
                        "></div>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">
                        <strong>Sync:</strong> Transfers files incrementally, preserving structure. <strong>Archive:</strong> Creates a compressed archive file per run.
                    </p>
                </div>

                {* <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="edit_encryption_enabled" value="1" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                        <span class="ml-2 text-sm font-medium text-slate-300">Enable Encryption</span>
                    </label>
                    <p class="mt-1 ml-6 text-xs text-slate-400">
                        Encrypts backup data <strong>Warning:</strong> Encryption cannot be disabled after enabling. Ensure you keep your encryption password secure.
                    </p>
                </div>

                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" id="edit_validation_enabled" value="1" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                        <span class="ml-2 text-sm font-medium text-slate-300">Enable Post-Run Validation</span>
                    </label>
                    <p class="mt-1 ml-6 text-xs text-slate-400">
                        Runs a check after each backup to verify data integrity. This may increase backup time but ensures data consistency.
                    </p>
                </div> *}

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Retention Policy</label>
                    <div
                        x-data="{
                            isOpen:false,
                            search:'',
                            selectedId:'none',
                            selectedName:'No Retention',
                            options:[
                                { id:'none', name:'No Retention' },
                                { id:'keep_last_n', name:'Keep Last N Runs' },
                                { id:'keep_days', name:'Keep for N Days' }
                            ],
                            get filtered() {
                                const q = (this.search || '').toLowerCase();
                                if (!q) return this.options;
                                return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                            },
                            choose(opt) {
                                if (!opt) return;
                                this.selectedId = String(opt.id || '');
                                this.selectedName = opt.name || '';
                                const sel = this.$refs.real;
                                if (sel) {
                                    sel.value = this.selectedId;
                                    try { sel.dispatchEvent(new Event('change')); } catch (e) {}
                                }
                                this.isOpen = false;
                            }
                        }"
                        @click.away="isOpen=false"
                    >
                        <!-- Hidden native select preserved for JS and payload usage -->
                        <select id="edit_retention_mode" class="hidden" x-ref="real" onchange="onEditRetentionModeChange()">
                            <option value="none">No Retention</option>
                            <option value="keep_last_n">Keep Last N Runs</option>
                            <option value="keep_days">Keep for N Days</option>
                        </select>
                        <div class="relative">
                            <button type="button"
                                    @click="selectedId = ($refs.real?.value || selectedId); selectedName = ($refs.real?.options[$refs.real.selectedIndex]?.text || selectedName); isOpen = !isOpen"
                                    class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                <span class="block truncate" x-text="selectedName"></span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </span>
                            </button>
                            <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-gray-600 rounded-md shadow-lg">
                                <div class="p-2">
                                    <input type="text" x-model="search" placeholder="Search…" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                    <template x-for="opt in filtered" :key="opt.id">
                                        <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                    </template>
                                    <template x-if="filtered.length === 0">
                                        <li class="px-4 py-2 text-gray-400">No options.</li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                        <div x-init="
                            (function(){
                                const sel = $refs.real;
                                if (sel) {
                                    const o = sel.options[sel.selectedIndex];
                                    selectedId = o ? String(o.value) : 'none';
                                    selectedName = o ? o.text : 'No Retention';
                                    sel.addEventListener('change', () => {
                                        const oc = sel.options[sel.selectedIndex];
                                        selectedId = oc ? String(oc.value) : 'none';
                                        selectedName = oc ? oc.text : 'No Retention';
                                    });
                                }
                            })()
                        "></div>
                    </div>
                    <div id="edit_retention_value_container" class="mt-2 hidden">
                        <input type="number" id="edit_retention_value" min="1" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" placeholder="Enter number">
                        <p class="mt-1 text-xs text-slate-400" id="edit_retention_help"></p>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-300 mb-2">Schedule</label>
                    <select id="edit_schedule_type" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" onchange="onEditScheduleChange()">
                        <option value="manual">Manual</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
                <div id="edit_schedule_options" class="mb-4 hidden">
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Time</label>
                        <input type="time" id="edit_schedule_time" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2" />
                    </div>
                    <div id="edit_weekly_option">
                        <label class="block text-sm font-medium text-slate-300 mb-2">Weekday</label>
                        <select id="edit_schedule_weekday" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-4 py-2">
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                            <option value="7">Sunday</option>
                        </select>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2 rounded-md" onclick="closeEditSlideover()">Cancel</button>
                    <button type="button" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded-md" onclick="saveEditedJob()">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Local Agent Job Wizard Modal -->
<div id="localJobWizardModal" class="fixed inset-0 z-[2000] hidden">
    <div class="absolute inset-0 bg-black/75" onclick="closeLocalJobWizard()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="w-full max-w-5xl h-[85vh] rounded-2xl border border-slate-800 bg-slate-950 shadow-2xl overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-800 shrink-0">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs uppercase text-slate-400 tracking-wide">Local Agent</p>
                        <h3 class="text-xl font-semibold text-white">Backup Job Wizard</h3>
                    </div>
                    <button class="icon-btn" onclick="closeLocalJobWizard()" aria-label="Close wizard">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <!-- Breadcrumb Navigation -->
                <nav id="localWizardBreadcrumb" class="flex items-center gap-1">
                    <button type="button" data-wizard-step="1" onclick="localWizardGoToStep(1)" class="wizard-crumb group flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all" data-active="true">
                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold bg-cyan-500 text-white group-[.is-active]:bg-cyan-500 group-[.is-locked]:bg-slate-700 group-[.is-complete]:bg-emerald-500">1</span>
                        <span class="hidden sm:inline">Setup</span>
                    </button>
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <button type="button" data-wizard-step="2" onclick="localWizardGoToStep(2)" class="wizard-crumb group flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all" data-locked="true">
                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold bg-slate-700 text-slate-400">2</span>
                        <span class="hidden sm:inline">Source</span>
                    </button>
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <button type="button" data-wizard-step="3" onclick="localWizardGoToStep(3)" class="wizard-crumb group flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all" data-locked="true">
                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold bg-slate-700 text-slate-400">3</span>
                        <span class="hidden sm:inline">Schedule</span>
                    </button>
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <button type="button" data-wizard-step="4" onclick="localWizardGoToStep(4)" class="wizard-crumb group flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all" data-locked="true">
                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold bg-slate-700 text-slate-400">4</span>
                        <span class="hidden sm:inline">Policy</span>
                    </button>
                    <svg class="w-4 h-4 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    <button type="button" data-wizard-step="5" onclick="localWizardGoToStep(5)" class="wizard-crumb group flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-all" data-locked="true">
                        <span class="w-5 h-5 flex items-center justify-center rounded-full text-[10px] font-bold bg-slate-700 text-slate-400">5</span>
                        <span class="hidden sm:inline">Review</span>
                    </button>
                </nav>
            </div>

            <div class="px-6 py-4 overflow-y-auto flex-1 scrollbar-thin-dark">

                <div class="space-y-6">
                    <!-- Step 1 -->
                    <div class="wizard-step" data-step="1">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-200 mb-2">Job Name</label>
                                <input id="localWizardName" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="My local backup">
                            </div>
                            <div class="md:col-span-2" x-data="{ showAdvanced: false }">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-slate-200">Backup Engine</label>
                                    <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer">
                                        <span>Advanced</span>
                                        <button @click="showAdvanced = !showAdvanced" type="button" 
                                                class="relative w-9 h-5 rounded-full transition-colors"
                                                :class="showAdvanced ? 'bg-cyan-600' : 'bg-slate-700'">
                                            <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                                  :class="showAdvanced ? 'translate-x-4' : 'translate-x-0'"></span>
                                        </button>
                                    </label>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <!-- File Backup (Archive) - Primary option -->
                                    <button type="button" data-engine-btn="kopia" 
                                            class="engine-card group flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-700 bg-slate-800/50 hover:bg-slate-800 hover:border-slate-600 transition-all text-center"
                                            onclick="localWizardSet('engine','kopia')">
                                        <div class="w-10 h-10 rounded-lg bg-slate-700/50 flex items-center justify-center group-[.selected]:bg-cyan-500/20">
                                            <svg class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-200">File Backup</p>
                                            <p class="text-[10px] text-slate-500">(Archive)</p>
                                        </div>
                                    </button>

                                    <!-- Disk Image -->
                                    <button type="button" data-engine-btn="disk_image" 
                                            class="engine-card group flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-700 bg-slate-800/50 hover:bg-slate-800 hover:border-slate-600 transition-all text-center"
                                            onclick="localWizardSet('engine','disk_image')">
                                        <div class="w-10 h-10 rounded-lg bg-slate-700/50 flex items-center justify-center group-[.selected]:bg-cyan-500/20">
                                            <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12a3 3 0 106 0 3 3 0 00-6 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-200">Disk Image</p>
                                            <p class="text-[10px] text-slate-500">(Full Disk)</p>
                                        </div>
                                    </button>

                                    <!-- Hyper-V (Coming Soon) -->
                                    <button type="button" disabled
                                            class="engine-card group flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-700/50 bg-slate-800/30 text-center opacity-50 cursor-not-allowed">
                                        <div class="w-10 h-10 rounded-lg bg-slate-700/30 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-400">Hyper-V</p>
                                            <p class="text-[10px] text-slate-600">(Coming Soon)</p>
                                        </div>
                                    </button>

                                    <!-- File Backup (Sync) - Advanced option -->
                                    <button type="button" data-engine-btn="sync" 
                                            x-show="showAdvanced"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            class="engine-card group flex flex-col items-center gap-2 p-4 rounded-xl border border-slate-700 bg-slate-800/50 hover:bg-slate-800 hover:border-slate-600 transition-all text-center"
                                            onclick="localWizardSet('engine','sync')">
                                        <div class="w-10 h-10 rounded-lg bg-slate-700/50 flex items-center justify-center group-[.selected]:bg-cyan-500/20">
                                            <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-slate-200">File Backup</p>
                                            <p class="text-[10px] text-slate-500">(Sync)</p>
                                        </div>
                                    </button>
                                </div>
                            </div>
                            <div x-data="{
                                options: [],
                                loading: false,
                                isOpen: false,
                                selectedId: '',
                                selectedName: '',
                                async load() {
                                    this.loading = true;
                                    try {
                                        const resp = await fetch('modules/addons/cloudstorage/api/agent_list.php');
                                        const data = await resp.json();
                                        if (data.status === 'success') {
                                            this.options = (data.agents || []).filter(a => a.status === 'active');
                                        }
                                    } catch (e) {} finally { this.loading = false; }
                                },
                                choose(opt) {
                                    this.selectedId = opt.id;
                                    this.selectedName = opt.hostname ? (opt.hostname + ' (ID ' + opt.id + ')') : ('Agent #' + opt.id);
                                    const hid = document.getElementById('localWizardAgentId');
                                    if (hid) hid.value = this.selectedId;
                                    if (window.localWizardState?.data) {
                                        window.localWizardState.data.agent_id = this.selectedId;
                                    }
                                    localWizardOnAgentSelected(this.selectedId);
                                    this.isOpen = false;
                                }
                            }" x-init="load()">
                                <label class="block text-sm font-medium text-slate-200 mb-2">Agent</label>
                                <input type="hidden" id="localWizardAgentId">
                                <div class="relative">
                                    <button type="button" class="w-full px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 flex justify-between items-center"
                                            @click="isOpen = !isOpen">
                                        <span x-text="selectedName || (loading ? 'Loading agents…' : 'Select agent')"></span>
                                        <svg class="w-4 h-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                    <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-700 rounded-md shadow-lg max-h-60 overflow-auto" style="display:none;">
                                        <template x-for="opt in options" :key="opt.id">
                                            <div class="px-3 py-2 text-slate-200 hover:bg-slate-800 cursor-pointer" @click="choose(opt)">
                                                <span x-text="opt.hostname ? (opt.hostname + ' (ID ' + opt.id + ')') : ('Agent #' + opt.id)"></span>
                                            </div>
                                        </template>
                                        <div class="px-3 py-2 text-slate-500 text-xs" x-show="!loading && options.length===0">No active agents found.</div>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">Select your registered local agent.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-200 mb-2">Destination</label>
                                <div class="flex gap-2">
                                    <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 opacity-100" disabled>S3 (only)</button>
                                    <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-500 cursor-not-allowed" disabled>Local (disabled)</button>
                                </div>
                            </div>
                            <!-- Disk image fields moved to Step 2 -->
                            <div id="localWizardS3Fields">
                                <label class="block text-sm font-medium text-slate-200 mb-2">Bucket</label>
                                <div
                                    x-data="{
                                        isOpen: false,
                                        search: '',
                                        selectedId: '',
                                        selectedName: '',
                                        options: [
                                            {foreach from=$buckets item=bucket name=bloopWizard}
                                                { id: '{$bucket->id}', name: '{$bucket->name|escape:'javascript'}' }{if !$smarty.foreach.bloopWizard.last},{/if}
                                            {/foreach}
                                        ],
                                        get filtered() {
                                            const q = (this.search || '').toLowerCase();
                                            if (!q) return this.options;
                                            return this.options.filter(o => (o.name || '').toLowerCase().includes(q));
                                        },
                                        choose(opt) {
                                            if (!opt) return;
                                            this.selectedId = String(opt.id || '');
                                            this.selectedName = opt.name || '';
                                            const hid = document.getElementById('localWizardBucketId');
                                            if (hid) hid.value = this.selectedId;
                                            this.isOpen = false;
                                            // Update breadcrumb state when bucket changes
                                            if (typeof localWizardUpdateView === 'function') localWizardUpdateView();
                                        }
                                    }"
                                    @click.away="isOpen=false"
                                >
                                    <input type="hidden" id="localWizardBucketId">
                                    <div class="relative">
                                        <button type="button"
                                                @click="isOpen = !isOpen"
                                                class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                            <span class="block truncate" x-text="selectedName || 'Select a bucket'"></span>
                                            <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                                <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                </svg>
                                            </span>
                                        </button>
                                        <div x-show="isOpen" class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-600 rounded-md shadow-lg">
                                            <div class="p-2">
                                                <input type="text" x-model="search" placeholder="Search buckets..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                            </div>
                                            <ul class="py-1 max-h-60 overflow-auto text-sm scrollbar_thin">
                                                <template x-for="opt in filtered" :key="opt.id">
                                                    <li @click="choose(opt)" class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700" x-text="opt.name"></li>
                                                </template>
                                                <template x-if="filtered.length === 0">
                                                    <li class="px-4 py-2 text-gray-400">No buckets found.</li>
                                                </template>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <label class="block text-sm font-medium text-slate-200 mb-2">Prefix (optional)</label>
                                    <input id="localWizardPrefix" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="backups/job123/">
                                </div>
                                <div class="mt-3">
                                    <button type="button" class="px-3 py-2 rounded-md border border-slate-700 text-slate-200 hover:border-slate-500" onclick="openInlineBucketCreate()">
                                        Create new bucket
                                    </button>
                                </div>
                            </div>
                            <div id="localWizardLocalFields" class="hidden">
                                <label class="block text-sm font-medium text-slate-200 mb-2">Local Destination Path</label>
                                <input id="localWizardLocalPath" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="E.g. D:\Backups">
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="wizard-step hidden" data-step="2" x-data="{ get isDiskImage() { return window.localWizardState?.data?.engine === 'disk_image'; } }">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <label class="block text-sm font-medium text-slate-200" x-text="isDiskImage ? 'Volume Selection' : 'Source selection'"></label>
                                <p class="text-xs text-slate-500" x-text="isDiskImage ? 'Select a local disk volume to create an image backup' : 'Browse your agent and select folders to back up'"></p>
                            </div>
                            <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:bg-slate-800 transition" @click="$dispatch('refresh-browser')">Refresh</button>
                        </div>

                        <div x-data="fileBrowser()" x-init="init()" class="grid lg:grid-cols-3 gap-4">
                            <input type="hidden" id="localWizardSource" />
                            <input type="hidden" id="localWizardSourcePaths" />

                            <!-- File/Volume browser -->
                            <div class="lg:col-span-2 rounded-xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                                <!-- Breadcrumb - hidden in disk image mode -->
                                <div x-show="!isDiskImageMode" class="flex items-center gap-1 px-4 py-2 bg-slate-800/60 border-b border-slate-800 overflow-x-auto text-xs text-slate-300">
                                    <button type="button" class="px-2 py-1 rounded hover:bg-slate-700 transition" @click="navigateTo('')">This PC</button>
                                    <template x-for="(segment, idx) in pathSegments" :key="idx">
                                        <div class="flex items-center shrink-0">
                                            <svg class="w-4 h-4 text-slate-600 mx-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                            <button type="button" class="px-2 py-1 rounded hover:bg-slate-700 transition truncate max-w-[120px]" x-text="segment.name" @click="navigateTo(segment.path)"></button>
                                        </div>
                                    </template>
                                </div>

                                <!-- Header for disk image mode -->
                                <div x-show="isDiskImageMode" class="flex items-center gap-2 px-4 py-3 bg-slate-800/60 border-b border-slate-800">
                                    <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12a3 3 0 106 0 3 3 0 00-6 0z" />
                                    </svg>
                                    <span class="text-sm text-slate-300">Local Disk Volumes</span>
                                    <span class="text-xs text-slate-500 ml-auto">Select one volume for disk image backup</span>
                                </div>

                                <div class="h-[420px] overflow-y-auto scrollbar_thin">
                                    <div x-show="loading" class="flex items-center justify-center h-full py-12">
                                        <div class="text-center">
                                            <svg class="animate-spin h-8 w-8 text-cyan-500 mx-auto mb-2" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            <p class="text-sm text-slate-400">Loading...</p>
                                        </div>
                                    </div>

                                    <div x-show="error && !loading" class="px-4 py-6 text-center">
                                        <p class="text-sm text-red-400" x-text="error"></p>
                                        <button type="button" class="mt-3 px-3 py-2 rounded-lg bg-slate-800 text-slate-200 text-xs" @click="retry()">Retry</button>
                                    </div>

                                    <!-- DISK IMAGE MODE: Volume cards grid -->
                                    <div x-show="!loading && !error && isDiskImageMode" class="p-4">
                                        <div class="grid grid-cols-2 gap-3">
                                            <template x-for="entry in localVolumes" :key="entry.path">
                                                <button type="button" 
                                                        class="volume-card group p-4 rounded-xl border text-left transition-all"
                                                        :class="selectedVolume === entry.path 
                                                            ? 'border-cyan-500 bg-cyan-500/10 ring-2 ring-cyan-500/40' 
                                                            : 'border-slate-700 bg-slate-800/50 hover:border-slate-600 hover:bg-slate-800'"
                                                        @click="selectVolume(entry)">
                                                    <div class="flex items-start gap-3">
                                                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                                                             :class="selectedVolume === entry.path ? 'bg-cyan-500/20' : 'bg-slate-700/50'">
                                                            <svg class="w-6 h-6" :class="selectedVolume === entry.path ? 'text-cyan-400' : 'text-blue-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12a3 3 0 106 0 3 3 0 00-6 0z" />
                                                            </svg>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-base font-semibold text-slate-100" x-text="entry.path || entry.name"></p>
                                                            <p class="text-sm text-slate-400 truncate" x-text="entry.label || 'Local Disk'"></p>
                                                            <div class="flex items-center gap-2 mt-1">
                                                                <span class="text-xs text-slate-500" x-text="entry.filesystem || ''"></span>
                                                                <span x-show="entry.size_bytes" class="text-xs text-slate-500">•</span>
                                                                <span x-show="entry.size_bytes" class="text-xs text-slate-500" x-text="formatBytes(entry.size_bytes)"></span>
                                                            </div>
                                                        </div>
                                                        <div class="shrink-0">
                                                            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center"
                                                                 :class="selectedVolume === entry.path ? 'border-cyan-500 bg-cyan-500' : 'border-slate-600'">
                                                                <svg x-show="selectedVolume === entry.path" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>
                                        <div x-show="localVolumes.length === 0" class="text-center py-12 text-sm text-slate-500">
                                            No local disk volumes found
                                        </div>
                                    </div>

                                    <!-- FILE BACKUP MODE: Standard folder browser -->
                                    <div x-show="!loading && !error && !isDiskImageMode" class="p-2 space-y-1">
                                        <button x-show="parentPath || currentPath" type="button" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-800/60 text-left transition" @click="navigateTo(parentPath || '')">
                                            <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center">
                                                <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                                </svg>
                                            </div>
                                            <span class="text-sm text-slate-400" x-text="parentPath ? '..' : 'This PC'"></span>
                                        </button>

                                        <template x-for="entry in entries" :key="entry.path">
                                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-800/60 transition" :class="isSelected(entry.path) ? 'bg-cyan-500/10 ring-1 ring-cyan-500/40' : ''">
                                                <label class="w-5 h-5 flex items-center justify-center rounded border cursor-pointer" :class="isSelected(entry.path) ? 'bg-cyan-500 border-cyan-500' : 'border-slate-600'">
                                                    <input type="checkbox" class="hidden" :checked="isSelected(entry.path)" @change="toggleSelection(entry)">
                                                    <svg x-show="isSelected(entry.path)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </label>
                                                <button type="button" class="flex-1 flex items-center gap-3 text-left" @click="entry.is_dir ? navigateTo(entry.path) : null">
                                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-slate-800">
                                                        <template x-if="entry.icon === 'drive' && !entry.is_network">
                                                            <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" />
                                                            </svg>
                                                        </template>
                                                        <template x-if="entry.is_network">
                                                            <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                                            </svg>
                                                        </template>
                                                        <template x-if="entry.is_dir && entry.icon !== 'drive' && !entry.is_network">
                                                            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                                            </svg>
                                                        </template>
                                                        <template x-if="!entry.is_dir && entry.icon !== 'drive'">
                                                            <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                                            </svg>
                                                        </template>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm text-slate-100 truncate" x-text="entry.name"></p>
                                                        <p class="text-xs text-slate-500" x-text="entry.is_network ? (entry.unc_path || 'Network Drive') : (entry.is_dir ? 'Folder' : formatBytes(entry.size))"></p>
                                                    </div>
                                                    <svg x-show="entry.is_dir" class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>

                                        <div x-show="entries.length === 0 && !loading && !error" class="text-center py-12 text-sm text-slate-500">
                                            This folder is empty
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sidebar -->
                            <div class="space-y-4">
                                <!-- DISK IMAGE MODE: Volume Selection Summary & Options -->
                                <template x-if="isDiskImageMode">
                                    <div class="space-y-4">
                                        <!-- Selected Volume -->
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                                            <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Selected Volume</h4>
                                            <div x-show="selectedVolume" class="flex items-center gap-3 p-3 rounded-lg bg-cyan-500/10 border border-cyan-500/30">
                                                <div class="w-10 h-10 rounded-lg bg-cyan-500/20 flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12a3 3 0 106 0 3 3 0 00-6 0z" />
                                                    </svg>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-cyan-100" x-text="selectedVolume"></p>
                                                    <p class="text-xs text-cyan-300/70" x-text="selectedVolumeInfo?.label || 'Local Disk'"></p>
                                                </div>
                                            </div>
                                            <div x-show="!selectedVolume" class="text-center text-xs text-slate-500 py-4">
                                                Select a volume from the list
                                            </div>
                                        </div>

                                        <!-- Hidden inputs for disk image data -->
                                        <input type="hidden" id="localWizardDiskVolume" x-model="selectedVolume">
                                        <input type="hidden" id="localWizardDiskVolumeSelect" value="">

                                        <!-- Image Format -->
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                                            <label class="block text-xs uppercase tracking-wide text-slate-400 mb-2">Image Format</label>
                                            <select id="localWizardDiskFormat" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                                <option value="vhdx">VHDX (Windows)</option>
                                                <option value="raw">Raw (Linux)</option>
                                            </select>
                                            <p class="text-xs text-slate-500 mt-2">VHDX recommended for Windows, Raw for Linux systems.</p>
                                        </div>

                                        <!-- Temp Directory -->
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                                            <label class="block text-xs uppercase tracking-wide text-slate-400 mb-2">Temp Directory (optional)</label>
                                            <input id="localWizardDiskTemp" type="text" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="C:\ProgramData\E3Backup\runs\tmp">
                                            <p class="text-xs text-slate-500 mt-2">Temporary storage for the disk image. Ensure enough free space.</p>
                                        </div>
                                    </div>
                                </template>

                                <!-- FILE BACKUP MODE: Folder Selection Summary -->
                                <template x-if="!isDiskImageMode">
                                    <div class="space-y-4">
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Selected</h4>
                                                <span class="text-xs text-cyan-300" x-text="selectedPaths.length"></span>
                                            </div>
                                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                                <template x-for="path in selectedPaths" :key="path">
                                                    <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-slate-800/60">
                                                        <svg class="w-3 h-3 text-cyan-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                                        </svg>
                                                        <span class="text-xs text-slate-200 truncate flex-1" x-text="path"></span>
                                                        <button type="button" class="p-1 hover:bg-slate-700 rounded" @click="removeSelection(path)">
                                                            <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </template>
                                                <div x-show="selectedPaths.length === 0" class="text-center text-xs text-slate-500 py-4">
                                                    No folders selected yet
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-2">
                                            <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Add manually</label>
                                            <div class="flex gap-2">
                                                <input type="text" x-model="manualPath" class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="C:\Data or /path" @keyup.enter="addManualPath()">
                                                <button type="button" class="px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-sm" @click="addManualPath()">Add</button>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-slate-800 bg-slate-900/60 p-4 space-y-3">
                                            <div>
                                                <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Include globs</label>
                                                <textarea id="localWizardInclude" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" rows="2" placeholder="**"></textarea>
                                            </div>
                                            <div>
                                                <label class="block text-xs uppercase tracking-wide text-slate-400 mb-1">Exclude globs</label>
                                                <textarea id="localWizardExclude" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" rows="2" placeholder="**/temp/**"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Network Credentials (only for file backup mode) -->
                                <div x-show="hasNetworkPaths && !isDiskImageMode" class="rounded-xl border border-purple-500/30 bg-purple-900/20 p-4 space-y-3">
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                        </svg>
                                        <h4 class="text-sm font-medium text-purple-200">Network Share Credentials</h4>
                                    </div>
                                    <p class="text-xs text-purple-300/70">Your selection includes network paths. The agent will need credentials to access these locations when running as a service.</p>
                                    <div class="space-y-2">
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Username</label>
                                            <input type="text" x-model="networkUsername" id="localWizardNetworkUsername" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="DOMAIN\username or user@domain.com" @input="syncCredentials()">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Password</label>
                                            <input type="password" x-model="networkPassword" id="localWizardNetworkPassword" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="Network password" @input="syncCredentials()">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Domain (optional)</label>
                                            <input type="text" x-model="networkDomain" id="localWizardNetworkDomain" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="MYDOMAIN" @input="syncCredentials()">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="wizard-step hidden" data-step="3">
                        <label class="block text-sm font-medium text-slate-200 mb-2">Schedule</label>
                        <div class="grid md:grid-cols-2 gap-4">
                            <select id="localWizardScheduleType" class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                <option value="manual">Manual</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="cron">Cron</option>
                            </select>
                            <input id="localWizardTime" type="time" class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" />
                            <select id="localWizardWeekday" class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="7">Sunday</option>
                            </select>
                            <input id="localWizardCron" type="text" class="bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-slate-100 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="*/30 * * * *" />
                        </div>
                    </div>

                    <!-- Step 4: Retention & Policy -->
                    <div class="wizard-step hidden" data-step="4" x-data="localWizardRetentionUI()" x-init="init()">
                        <!-- Hidden input for form compatibility -->
                        <input type="hidden" id="localWizardRetention" x-model="retentionJson">
                        <input type="hidden" id="localWizardPolicy">
                        
                        <div class="space-y-6">
                            <!-- Retention Policy Builder -->
                            <div>
                                <label class="block text-sm font-medium text-slate-200 mb-3">Retention Policy</label>
                                
                                <!-- Retention Mode Dropdown -->
                                <div @click.away="retentionDropdownOpen = false" class="mb-4">
                                    <div class="relative">
                                        <button type="button"
                                                @click="retentionDropdownOpen = !retentionDropdownOpen"
                                                class="relative w-full px-4 py-2.5 text-left bg-slate-800 border border-slate-700 rounded-lg shadow-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50 transition-colors hover:border-slate-600">
                                            <span class="flex items-center gap-3">
                                                <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                                      :class="{
                                                          'bg-slate-700/50 text-slate-400': mode === 'none',
                                                          'bg-sky-500/20 text-sky-400': mode === 'keep_last',
                                                          'bg-violet-500/20 text-violet-400': mode === 'keep_within',
                                                          'bg-amber-500/20 text-amber-400': mode === 'keep_daily'
                                                      }">
                                                    <template x-if="mode === 'none'">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="mode === 'keep_last'">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="mode === 'keep_within'">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </template>
                                                    <template x-if="mode === 'keep_daily'">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </template>
                                                </span>
                                                <span class="block truncate text-slate-100" x-text="modeLabels[mode] || 'Select retention policy'"></span>
                                            </span>
                                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                                <svg class="w-5 h-5 text-slate-400 transition-transform duration-200" :class="retentionDropdownOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </span>
                                        </button>
                                        
                                        <!-- Dropdown Panel -->
                                        <div x-show="retentionDropdownOpen"
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                             x-transition:leave="transition ease-in duration-150"
                                             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                             x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                                             class="absolute z-20 mt-2 w-full bg-slate-900 border border-slate-700 rounded-xl shadow-xl overflow-hidden"
                                             style="display: none;">
                                            <ul class="py-1">
                                                <!-- No Retention -->
                                                <li @click="selectMode('none')"
                                                    class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                    :class="mode === 'none' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                    <span class="w-8 h-8 rounded-lg bg-slate-700/50 flex items-center justify-center shrink-0">
                                                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                        </svg>
                                                    </span>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium">No Retention</p>
                                                        <p class="text-xs text-slate-500">Keep all backups indefinitely</p>
                                                    </div>
                                                    <svg x-show="mode === 'none'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </li>
                                                <!-- Keep Last N -->
                                                <li @click="selectMode('keep_last')"
                                                    class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                    :class="mode === 'keep_last' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                    <span class="w-8 h-8 rounded-lg bg-sky-500/20 flex items-center justify-center shrink-0">
                                                        <svg class="w-4 h-4 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                                        </svg>
                                                    </span>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium">Keep last ... Backups</p>
                                                        <p class="text-xs text-slate-500">Keep a fixed number of most recent backups</p>
                                                    </div>
                                                    <svg x-show="mode === 'keep_last'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </li>
                                                <!-- Keep Within -->
                                                <li @click="selectMode('keep_within')"
                                                    class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                    :class="mode === 'keep_within' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                    <span class="w-8 h-8 rounded-lg bg-violet-500/20 flex items-center justify-center shrink-0">
                                                        <svg class="w-4 h-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </span>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium">Keep all backups in the last...</p>
                                                        <p class="text-xs text-slate-500">Keep backups within a time window</p>
                                                    </div>
                                                    <svg x-show="mode === 'keep_within'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </li>
                                                <!-- Keep Daily -->
                                                <li @click="selectMode('keep_daily')"
                                                    class="flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors"
                                                    :class="mode === 'keep_daily' ? 'bg-cyan-500/10 text-cyan-200' : 'text-slate-200 hover:bg-slate-800'">
                                                    <span class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center shrink-0">
                                                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </span>
                                                    <div class="flex-1">
                                                        <p class="text-sm font-medium">Keep last ... backups at most one per day</p>
                                                        <p class="text-xs text-slate-500">Thin backups to one per day, keeping N days</p>
                                                    </div>
                                                    <svg x-show="mode === 'keep_daily'" class="w-5 h-5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Keep Last N Panel -->
                                <div x-show="mode === 'keep_last'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                                    <label class="block text-xs text-slate-400 mb-2">Number of backups to keep</label>
                                    <div class="flex items-center gap-3">
                                        <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800 w-36">
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Decrease" @click="keepLast = Math.max(1, keepLast - 1); syncToState()">−</button>
                                            <input x-model.number="keepLast" @input="syncToState()" type="number" min="1" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-2 py-2.5 w-12" />
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Increase" @click="keepLast++; syncToState()">+</button>
                                        </div>
                                        <span class="text-sm text-slate-400">backups</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2">Older backups beyond this count will be automatically removed.</p>
                                </div>
                                
                                <!-- Keep Within Panel -->
                                <div x-show="mode === 'keep_within'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                                    <label class="block text-xs text-slate-400 mb-3">Keep all backups within (choose one)</label>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                        <!-- Days -->
                                        <div class="space-y-1.5">
                                            <label class="block text-xs text-slate-500">Days</label>
                                            <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Decrease days" @click="setWithinUnit('d', Math.max(0, withinDays - 1))">−</button>
                                                <input x-model.number="withinDays" @input="setWithinUnit('d', withinDays)" type="number" min="0" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-1 py-2 w-10 text-sm" :disabled="withinUnit && withinUnit !== 'd'" :class="withinUnit && withinUnit !== 'd' ? 'opacity-40' : ''" />
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Increase days" @click="setWithinUnit('d', withinDays + 1)">+</button>
                                            </div>
                                        </div>
                                        <!-- Weeks -->
                                        <div class="space-y-1.5">
                                            <label class="block text-xs text-slate-500">Weeks</label>
                                            <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Decrease weeks" @click="setWithinUnit('w', Math.max(0, withinWeeks - 1))">−</button>
                                                <input x-model.number="withinWeeks" @input="setWithinUnit('w', withinWeeks)" type="number" min="0" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-1 py-2 w-10 text-sm" :disabled="withinUnit && withinUnit !== 'w'" :class="withinUnit && withinUnit !== 'w' ? 'opacity-40' : ''" />
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Increase weeks" @click="setWithinUnit('w', withinWeeks + 1)">+</button>
                                            </div>
                                        </div>
                                        <!-- Months -->
                                        <div class="space-y-1.5">
                                            <label class="block text-xs text-slate-500">Months</label>
                                            <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Decrease months" @click="setWithinUnit('m', Math.max(0, withinMonths - 1))">−</button>
                                                <input x-model.number="withinMonths" @input="setWithinUnit('m', withinMonths)" type="number" min="0" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-1 py-2 w-10 text-sm" :disabled="withinUnit && withinUnit !== 'm'" :class="withinUnit && withinUnit !== 'm' ? 'opacity-40' : ''" />
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Increase months" @click="setWithinUnit('m', withinMonths + 1)">+</button>
                                            </div>
                                        </div>
                                        <!-- Years -->
                                        <div class="space-y-1.5">
                                            <label class="block text-xs text-slate-500">Years</label>
                                            <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800">
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Decrease years" @click="setWithinUnit('y', Math.max(0, withinYears - 1))">−</button>
                                                <input x-model.number="withinYears" @input="setWithinUnit('y', withinYears)" type="number" min="0" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-1 py-2 w-10 text-sm" :disabled="withinUnit && withinUnit !== 'y'" :class="withinUnit && withinUnit !== 'y' ? 'opacity-40' : ''" />
                                                <button type="button" class="px-2 py-2 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 text-sm" aria-label="Increase years" @click="setWithinUnit('y', withinYears + 1)">+</button>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-3">All backups within this time window will be kept. Only one unit can be used at a time.</p>
                                </div>
                                
                                <!-- Keep Daily Panel -->
                                <div x-show="mode === 'keep_daily'" x-transition class="rounded-xl border border-slate-700 bg-slate-900/50 p-4">
                                    <label class="block text-xs text-slate-400 mb-2">Number of daily backups to keep</label>
                                    <div class="flex items-center gap-3">
                                        <div class="flex rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-800 w-36">
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Decrease" @click="keepDaily = Math.max(1, keepDaily - 1); syncToState()">−</button>
                                            <input x-model.number="keepDaily" @input="syncToState()" type="number" min="1" step="1" class="eb-no-spinner flex-1 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-2 py-2.5 w-12" />
                                            <button type="button" class="px-3 py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500" aria-label="Increase" @click="keepDaily++; syncToState()">+</button>
                                        </div>
                                        <span class="text-sm text-slate-400">daily backups</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-2">Keeps one backup per day for the specified number of days. Multiple backups on the same day are thinned.</p>
                                </div>
                            </div>
                            
                            <!-- Advanced Settings (unchanged structure) -->
                            <div x-data="{ showAdvancedPolicy: false }">
                                <div class="flex items-center justify-between mb-3">
                                    <label class="block text-sm font-medium text-slate-200">Advanced Settings</label>
                                    <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer select-none">
                                        <span>Show advanced</span>
                                        <button @click="showAdvancedPolicy = !showAdvancedPolicy" type="button"
                                                class="relative w-9 h-5 rounded-full transition-colors"
                                                :class="showAdvancedPolicy ? 'bg-cyan-600' : 'bg-slate-700'">
                                            <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                                  :class="showAdvancedPolicy ? 'translate-x-4' : 'translate-x-0'"></span>
                                        </button>
                                    </label>
                                </div>
                                <div x-show="showAdvancedPolicy" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                                     class="rounded-xl border border-slate-700 bg-slate-900/50 p-4 space-y-4" style="display:none;">
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Bandwidth (KB/s)</label>
                                            <input id="localWizardBandwidth" type="number" value="0" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="0 = unlimited">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Parallel uploads</label>
                                            <input id="localWizardParallelism" type="number" value="8" min="1" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50" placeholder="8">
                                        </div>
                                        <div>
                                            <label class="block text-xs text-slate-400 mb-1">Compression</label>
                                            <select id="localWizardCompression" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500/50">
                                                <option value="none" selected>None</option>
                                                <option value="zstd-default">zstd-default</option>
                                                <option value="pgzip">pgzip</option>
                                                <option value="s2">s2</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="inline-flex items-center gap-2">
                                            <input id="localWizardDebugLogs" type="checkbox" class="rounded border-slate-600 bg-slate-800">
                                            <span class="text-sm text-slate-200">Enable detailed eazyBackup debug logs</span>
                                        </label>
                                        <p class="text-xs text-slate-500 mt-1">Adds more step-level events to the live progress view for troubleshooting.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5 -->
                    <div class="wizard-step hidden" data-step="5">
                        <div class="rounded-xl border border-slate-800 bg-slate-900 px-4 py-3 text-slate-100">
                            <p class="text-sm font-semibold mb-2">Review</p>
                            <pre id="localWizardReview" class="text-xs whitespace-pre-wrap leading-5 bg-slate-950 border border-slate-800 rounded-lg p-3 overflow-auto max-h-64"></pre>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex justify-between items-center px-6 py-4 border-t border-slate-800 shrink-0 bg-slate-950">
                <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100" onclick="localWizardPrev()">Back</button>
                <div class="flex gap-2">
                    <button type="button" class="px-4 py-2 rounded-lg border border-slate-700 bg-slate-800 text-slate-100" onclick="closeLocalJobWizard()">Cancel</button>
                        <button type="button" id="localWizardNextBtn" data-local-wizard-next class="px-4 py-2 rounded-lg bg-sky-600 text-white" onclick="localWizardNext()">Next</button>
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

/* Dark thin scrollbar for wizard modal */
.scrollbar-thin-dark {
    scrollbar-width: thin;
    scrollbar-color: #475569 #1e293b;
}
.scrollbar-thin-dark::-webkit-scrollbar {
    width: 6px;
}
.scrollbar-thin-dark::-webkit-scrollbar-track {
    background: #1e293b;
    border-radius: 3px;
}
.scrollbar-thin-dark::-webkit-scrollbar-thumb {
    background: #475569;
    border-radius: 3px;
}
.scrollbar-thin-dark::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

/* Breadcrumb navigation styles */
#localWizardBreadcrumb .wizard-crumb {
    transition: all 0.15s ease;
}
#localWizardBreadcrumb .wizard-crumb:not(:disabled):hover {
    background: rgba(51, 65, 85, 0.5);
}

/* Engine card styles */
.engine-card.selected .w-10 {
    background: rgba(6, 182, 212, 0.15);
}
.engine-card.selected svg {
    color: #22d3ee;
}
</style>

<!-- Include the Bucket Creation Modal -->
{include file="modules/addons/cloudstorage/templates/partials/bucket_create_modal.tpl"}

<script>
{literal}
// Build an API base that works whether WHMCS is installed at domain root or a subdirectory (e.g. /accounts).
// {$WEB_ROOT} is the WHMCS install web root. Example values: "" or "/accounts".
const CLOUDSTORAGE_WEB_ROOT = (String('{/literal}{$WEB_ROOT}{literal}') || '').replace(/\/$/, '');
const CLOUDSTORAGE_API_BASE = ((CLOUDSTORAGE_WEB_ROOT && CLOUDSTORAGE_WEB_ROOT !== '/') ? CLOUDSTORAGE_WEB_ROOT : '') + '/modules/addons/cloudstorage/api';

// Cloud Wizard edit mode state
window.cloudWizardState = { editMode: false, jobId: null };

function openCreateJobModal() {
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

    // Force show backdrop and panel for immediate visibility
    panel.style.setProperty('display', 'block', 'important');
    const backdrop = panel.querySelector('.absolute.inset-0.bg-black.bg-opacity-50') || panel.querySelector('.absolute.inset-0');
    if (backdrop) backdrop.style.setProperty('display', 'block', 'important');
    const panelContent = panel.querySelector('.absolute.right-0.top-0') || panel.querySelector('.absolute.right-0');
    if (panelContent) panelContent.style.setProperty('display', 'block', 'important');

    // Initialize Alpine if available and open
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

    // Apply initial state for source type groups
    applyInitialSourceState();
}

// Open Cloud Backup Wizard in edit mode
function openCloudBackupWizardForEdit(jobId) {
    if (!jobId) return;
    window.cloudWizardState = { editMode: true, jobId: jobId, loading: true };
    
    // Open the panel first
    openCreateJobModal();
    
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
        if (typeof onRetentionModeChange === 'function') {
            onRetentionModeChange();
        }
    }
    const retentionValueEl = document.getElementById('retentionValue');
    if (retentionValueEl) retentionValueEl.value = job.retention_value || '';
    
    // Backup mode
    const backupModeEl = document.getElementById('backupMode');
    if (backupModeEl) backupModeEl.value = job.backup_mode || 'sync';
}

// Local Agent Wizard helpers
window.localWizardState = {
    step: 1,
    totalSteps: 5,
    data: {
        engine: 'kopia',
        dest_type: 's3',
        bucket_auto_create: true,
    },
    editMode: false,
    jobId: '',
    loading: false,
};

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

function resetLocalWizardFields() {
    window.localWizardState.data = {
        engine: 'kopia',
        dest_type: 's3',
        bucket_auto_create: true,
        source_paths: [],
        retention_json: null, // Reset retention data for new jobs
    };
    // Reset inputs to defaults
    const idsToClear = [
        'localWizardName','localWizardAgentId','localWizardBucketId','localWizardPrefix',
        'localWizardLocalPath','localWizardSource','localWizardSourcePaths','localWizardInclude','localWizardExclude',
        'localWizardTime','localWizardCron','localWizardRetention','localWizardPolicy',
        'localWizardDiskVolume','localWizardDiskTemp'
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
    const diskVolume = document.getElementById('localWizardDiskVolume');
    if (diskVolume) diskVolume.value = '';
    const diskTemp = document.getElementById('localWizardDiskTemp');
    if (diskTemp) diskTemp.value = '';
    // Reset Agent dropdown via Alpine v3 state
    const agentRoot = document.querySelector('#localWizardAgentId')?.closest('[x-data]');
    if (agentRoot && agentRoot._x_dataStack) {
        const data = agentRoot._x_dataStack[0];
        if (data) {
            data.selectedId = '';
            data.selectedAgent = null;
        }
    }
    // Reset button labels
    const bucketBtn = document.getElementById('localWizardBucketId')?.parentElement?.querySelector('button .block');
    if (bucketBtn) bucketBtn.textContent = 'Choose where to store your backups';
    // Reset engine button styles
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
    if (!modal) {
        // Fall back to existing slide-over if wizard markup missing
        ensureEditPanel();
        return openEditSlideover(jobId);
    }
    window.localWizardState.loading = true;
    openLocalJobWizard({ editMode: true, jobId, loading: true });
    fetch('modules/addons/cloudstorage/api/cloudbackup_get_job.php?job_id=' + encodeURIComponent(jobId))
        .then((r) => r.json())
        .then((data) => {
            if (data.status !== 'success' || !data.job) {
                toast?.error?.(data.message || 'Failed to load job');
                closeLocalJobWizard();
                return;
            }
            const j = data.job;
            const s = data.source || {};
            if ((j.source_type || '').toLowerCase() !== 'local_agent') {
                // Not a local agent job; route to slide-over
                closeLocalJobWizard();
                ensureEditPanel();
                openEditSlideover(jobId);
                return;
            }
            localWizardFillFromJob(j, s);
        })
        .catch((err) => {
            toast?.error?.('Failed to load job: ' + err);
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
    if (diskVolEl) diskVolEl.value = job.disk_source_volume || '';
    const diskFmtEl = document.getElementById('localWizardDiskFormat');
    if (diskFmtEl) diskFmtEl.value = (job.disk_image_format || 'vhdx');
    const diskTempEl = document.getElementById('localWizardDiskTemp');
    if (diskTempEl) diskTempEl.value = job.disk_temp_dir || '';
    const incEl = document.getElementById('localWizardInclude');
    if (incEl) incEl.value = source.include_glob || job.local_include_glob || '';
    const excEl = document.getElementById('localWizardExclude');
    if (excEl) excEl.value = source.exclude_glob || job.local_exclude_glob || '';
    const bwEl = document.getElementById('localWizardBandwidth');
    if (bwEl) bwEl.value = source.bandwidth_limit_kbps || job.local_bandwidth_limit_kbps || job.bandwidth_limit_kbps || '0';
    const policyObj = job.policy_json ? (safeParseJSON(job.policy_json) || {}) : {};
    const parEl = document.getElementById('localWizardParallelism');
    if (parEl) parEl.value = job.parallelism || policyObj.parallel_uploads || '8';
    const compEl = document.getElementById('localWizardCompression');
    if (compEl) compEl.value = policyObj.compression || 'none';
    const dbgEl = document.getElementById('localWizardDebugLogs');
    if (dbgEl) dbgEl.checked = !!policyObj.debug_logs;

    // Schedule
    const schedType = document.getElementById('localWizardScheduleType');
    if (schedType) schedType.value = job.schedule_type || (job.schedule_json?.type) || 'manual';
    const schedTime = document.getElementById('localWizardTime');
    if (schedTime) schedTime.value = job.schedule_time || (job.schedule_json?.time) || '';
    const schedWeek = document.getElementById('localWizardWeekday');
    if (schedWeek) schedWeek.value = job.schedule_weekday || (job.schedule_json?.weekday) || '1';
    const schedCron = document.getElementById('localWizardCron');
    if (schedCron) schedCron.value = job.schedule_cron || (job.schedule_json?.cron) || '';

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

    // Trigger agent selected event to load volumes/directories
    if (job.agent_id) {
        localWizardOnAgentSelected(job.agent_id);
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
        // Dispatch engine-changed event for the file browser to react
        window.dispatchEvent(new CustomEvent('engine-changed', { detail: { engine: val } }));
        // Update breadcrumb state when engine changes
        localWizardUpdateView();
    }
}

const localWizardVolumeState = {
    volumes: [],
    updatedAt: '',
    loading: false,
    lastAgentId: '',
};

function localWizardOnAgentSelected(agentId) {
    if (!agentId) return;
    localWizardVolumeState.lastAgentId = agentId;
    // Dispatch event for fileBrowser to load volumes/directories
    window.dispatchEvent(new CustomEvent('local-agent-selected', { detail: { agentId } }));
    // Update breadcrumb state when agent changes
    localWizardUpdateView();
}

// Volume loading for disk image mode is now handled by the fileBrowser component in Step 2
// The fileBrowser loads volumes via the browse_directory API with empty path

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
        selectedPaths: [],
        networkPathsInfo: [], // Track which paths are network paths with their UNC info
        manualPath: '',
        agentId: '',
        networkUsername: '',
        networkPassword: '',
        networkDomain: '',
        // Disk image mode properties
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
            // Filter to only show local (non-network) drives at root level
            // Exclude network drives, UNC paths, and drives with network type
            if (this.currentPath !== '') return [];
            return this.entries.filter(e => {
                // Must be a drive
                if (e.icon !== 'drive') return false;
                // Exclude if flagged as network
                if (e.is_network) return false;
                // Exclude if type is network
                if (e.type === 'network') return false;
                // Exclude UNC paths (\\server\share)
                if (e.path && e.path.startsWith('\\\\')) return false;
                // Exclude if has UNC path set
                if (e.unc_path && e.unc_path !== '') return false;
                return true;
            });
        },

        get hasNetworkPaths() {
            return this.selectedPaths.some(path => this.isNetworkPath(path));
        },

        isNetworkPath(path) {
            // Check if path is a UNC path or a network drive we've tracked
            if (path && path.startsWith('\\\\')) return true;
            return this.networkPathsInfo.some(info => info.path === path && info.is_network);
        },

        selectVolume(entry) {
            this.selectedVolume = entry.path;
            this.selectedVolumeInfo = entry;
            this.syncDiskVolumeToWizard();
        },

        syncDiskVolumeToWizard() {
            const input = document.getElementById('localWizardDiskVolume');
            if (input) input.value = this.selectedVolume || '';
            if (window.localWizardState?.data) {
                window.localWizardState.data.disk_source_volume = this.selectedVolume || '';
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
            // Load preset disk volume if in disk image mode
            const diskVolume = document.getElementById('localWizardDiskVolume')?.value || '';
            if (diskVolume) {
                this.selectedVolume = diskVolume;
            }
            if (this.agentId) {
                this.loadDirectory('');
            } else {
                this.error = 'Select an agent to browse.';
            }
            window.addEventListener('local-agent-selected', (e) => {
                this.agentId = e.detail?.agentId || '';
                this.selectedPaths = [];
                this.selectedVolume = '';
                this.selectedVolumeInfo = null;
                this.syncToWizard();
                this.syncDiskVolumeToWizard();
                if (this.agentId) {
                    this.loadDirectory('');
                } else {
                    this.error = 'Select an agent to browse.';
                }
            });
            window.addEventListener('refresh-browser', () => {
                // For disk image mode, always reload root to get volumes
                const path = this.isDiskImageMode ? '' : (this.currentPath || '');
                this.loadDirectory(path);
            });
            // Listen for engine changes to reload view
            window.addEventListener('engine-changed', () => {
                // Reset selections when engine changes
                if (this.isDiskImageMode) {
                    this.selectedPaths = [];
                    this.syncToWizard();
                } else {
                    this.selectedVolume = '';
                    this.selectedVolumeInfo = null;
                    this.syncDiskVolumeToWizard();
                }
                // Reload directory (volumes for disk image, folders for file backup)
                this.loadDirectory('');
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
            });
        },

        async loadDirectory(path) {
            if (!this.agentId) {
                this.error = 'Select an agent to browse.';
                return;
            }
            // Optimistically reflect target path to avoid flicker back to root
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
                // Track network path info if applicable
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
                // Clear credentials if no network paths
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

document.addEventListener('DOMContentLoaded', () => {
    // Add event listener for Job Name input to update breadcrumb state
    const nameInput = document.getElementById('localWizardName');
    if (nameInput) {
        nameInput.addEventListener('input', () => {
            localWizardUpdateView();
        });
    }

    // Add MutationObserver for bucket selection (since it's Alpine-managed)
    const bucketInput = document.getElementById('localWizardBucketId');
    if (bucketInput) {
        const observer = new MutationObserver(() => {
            localWizardUpdateView();
        });
        observer.observe(bucketInput, { attributes: true, attributeFilter: ['value'] });
        // Also listen for direct value changes
        bucketInput.addEventListener('change', () => localWizardUpdateView());
    }
});

function localWizardNext() {
    const state = window.localWizardState;
    if (state.loading) return;

    // Validate Step 1 before proceeding
    if (state.step === 1 && !localWizardIsStep1Valid()) {
        toast?.error?.('Please fill in Job Name, select an Agent, Engine, and Bucket before proceeding');
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

    // Update breadcrumb navigation
    const crumbs = document.querySelectorAll('#localWizardBreadcrumb .wizard-crumb');
    const step1Valid = localWizardIsStep1Valid();
    crumbs.forEach((crumb) => {
        const stepNum = parseInt(crumb.getAttribute('data-wizard-step'), 10);
        const numBadge = crumb.querySelector('span:first-child');
        const isActive = stepNum === state.step;
        const isComplete = stepNum < state.step;
        const isLocked = stepNum > 1 && !step1Valid;
        const isAccessible = stepNum === 1 || (step1Valid && stepNum <= state.step + 1);

        // Reset classes
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
            // Check if Step 1 validation should block Next
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

    // Can always go back
    if (stepNum < state.step) {
        state.step = stepNum;
        localWizardUpdateView();
        return;
    }

    // Going forward - validate Step 1 first
    if (stepNum > 1 && !localWizardIsStep1Valid()) {
        toast?.error?.('Please complete all required fields in Setup before proceeding');
        return;
    }

    // Can go forward to adjacent step or already visited
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
    s.dest_prefix = document.getElementById('localWizardPrefix')?.value || '';
    s.dest_local_path = document.getElementById('localWizardLocalPath')?.value || '';
    s.dest_bucket_id = document.getElementById('localWizardBucketId')?.value || '';
    s.source_path = document.getElementById('localWizardSource')?.value || '';
    const srcPathsRaw = document.getElementById('localWizardSourcePaths')?.value || '[]';
    const srcPathsParsed = safeParseJSON(srcPathsRaw);
    s.source_paths = Array.isArray(srcPathsParsed) ? srcPathsParsed : [];
    s.disk_source_volume = document.getElementById('localWizardDiskVolume')?.value || '';
    s.disk_image_format = document.getElementById('localWizardDiskFormat')?.value || 'vhdx';
    s.disk_temp_dir = document.getElementById('localWizardDiskTemp')?.value || '';
    if ((s.engine || '') === 'disk_image' && !s.source_path) {
        s.source_path = s.disk_source_volume;
    }
    s.include = document.getElementById('localWizardInclude')?.value || '';
    s.exclude = document.getElementById('localWizardExclude')?.value || '';
    s.schedule_type = document.getElementById('localWizardScheduleType')?.value || 'manual';
    s.schedule_time = document.getElementById('localWizardTime')?.value || '';
    s.schedule_weekday = document.getElementById('localWizardWeekday')?.value || '';
    s.schedule_cron = document.getElementById('localWizardCron')?.value || '';
    s.schedule_json = {
        type: s.schedule_type,
        time: s.schedule_time,
        weekday: s.schedule_weekday,
        cron: s.schedule_cron,
    };
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
    const compVal = document.getElementById('localWizardCompression')?.value || 'none';
    const dbgVal = !!document.getElementById('localWizardDebugLogs')?.checked;
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
    s.policy_json = policyObj;

    const review = document.getElementById('localWizardReview');
    if (review) {
        // Create user-friendly review object
        const displayData = { ...s };
        // Replace engine names with eazyBackup display names
        if (displayData.engine === 'kopia') {
            displayData.engine = 'eazyBackup (Archive)';
        } else if (displayData.engine === 'sync') {
            displayData.engine = 'eazyBackup (Sync)';
        } else if (displayData.engine === 'disk_image') {
            displayData.engine = 'eazyBackup (Disk Image)';
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

function localWizardSubmit() {
    const s = window.localWizardState.data;
    const isEdit = !!window.localWizardState.editMode;
    if (!s.name) {
        return toast?.error?.('Job name is required');
    }
    if (!s.agent_id) {
        return toast?.error?.('Agent ID is required');
    }
    if (!s.dest_bucket_id) {
        return toast?.error?.('Bucket ID is required');
    }
    if ((s.engine || '') === 'disk_image' && !s.disk_source_volume) {
        return toast?.error?.('Disk volume is required for disk image backups');
    }
    const payload = {
        name: s.name,
        source_type: 'local_agent',
        source_display_name: 'Local Agent',
        source_path: (s.engine === 'disk_image') ? (s.disk_source_volume || s.source_path || '') : (s.source_path || ''),
        source_paths: Array.isArray(s.source_paths) ? s.source_paths : (s.source_path ? [s.source_path] : []),
        dest_bucket_id: s.dest_bucket_id,
        dest_prefix: s.dest_prefix || '',
        backup_mode: s.engine === 'sync' ? 'sync' : 'archive',
        engine: s.engine || 'kopia',
        agent_id: s.agent_id,
        dest_type: 's3',
        // JSON fields must be stringified
        schedule_json: s.schedule_json && typeof s.schedule_json === 'object' ? JSON.stringify(s.schedule_json) : '',
        retention_json: (s.retention_json && typeof s.retention_json === 'object') ? JSON.stringify(s.retention_json) : (typeof s.retention_json === 'string' ? s.retention_json : ''),
        policy_json: (s.policy_json && typeof s.policy_json === 'object') ? JSON.stringify(s.policy_json) : (typeof s.policy_json === 'string' ? s.policy_json : ''),
        bandwidth_limit_kbps: s.bandwidth_limit_kbps || '',
        parallelism: s.parallelism || '',
        encryption_mode: s.encryption_mode || 'repokey',
        compression: s.compression || '',
        compression_enabled: s.compression_enabled || 0,
        // legacy retention fields kept for compatibility
        retention_mode: 'none',
        retention_value: '',
        schedule_type: s.schedule_type || 'manual',
        schedule_time: s.schedule_time || '',
        schedule_weekday: s.schedule_weekday || '',
        schedule_cron: s.schedule_cron || '',
        // include/exclude hints for local agent; server currently stores local_* fields
        local_include_glob: s.include || '',
        local_exclude_glob: s.exclude || '',
        disk_source_volume: s.disk_source_volume || '',
        disk_image_format: s.disk_image_format || '',
        disk_temp_dir: s.disk_temp_dir || '',
        // Network share credentials (encrypted on server)
        network_username: s.network_username || '',
        network_password: s.network_password || '',
        network_domain: s.network_domain || '',
    };
    if (isEdit) {
        payload.job_id = window.localWizardState.jobId;
    }

    const opts = {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload),
    };
    const endpoint = isEdit
        ? (CLOUDSTORAGE_API_BASE + '/cloudbackup_update_job.php')
        : (CLOUDSTORAGE_API_BASE + '/cloudbackup_create_job.php');
    if (isEdit && !payload.job_id) {
        return toast?.error?.('Missing job ID for update');
    }
    fetch(endpoint, opts)
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                toast?.success?.(isEdit ? 'Local agent job updated' : 'Local agent job created');
                closeLocalJobWizard();
                setTimeout(() => location.reload(), 800);
            } else {
                toast?.error?.(data.message || (isEdit ? 'Failed to update job' : 'Failed to create job'));
            }
        })
        .catch(err => toast?.error?.('Error ' + (isEdit ? 'updating' : 'creating') + ' job: ' + err));
}

// Open inline bucket create (reuse existing create panel helper)
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

// Restore wizard state/helpers
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
        sel.innerHTML = '<option value=\"\">Loading runs…</option>';
    }
    fetch('modules/addons/cloudstorage/api/cloudbackup_list_runs.php?job_id=' + encodeURIComponent(jobId))
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success') {
                if (sel) sel.innerHTML = '<option value=\"\">Failed to load runs</option>';
                return;
            }
            window.restoreState.runs = data.runs || [];
            if (sel) {
                sel.innerHTML = '';
                if (!window.restoreState.runs.length) {
                    sel.innerHTML = '<option value=\"\">No runs available</option>';
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
            if (sel) sel.innerHTML = '<option value=\"\">Failed to load runs</option>';
        });
}

function restoreNext() {
    const st = window.restoreState;
    if (st.step === 1) {
        const sel = document.getElementById('restoreRunSelect');
        st.selectedRunId = sel ? sel.value : '';
        if (!st.selectedRunId) {
            return toast?.error?.('Select a run/snapshot to restore');
        }
    } else if (st.step === 2) {
        const tp = document.getElementById('restoreTargetPath');
        st.targetPath = tp ? (tp.value || '') : '';
        st.mount = document.getElementById('restoreMount')?.checked || false;
        if (!st.targetPath) {
            return toast?.error?.('Target path is required');
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
        return toast?.error?.('Selected run has no manifest (log_ref). Cannot restore.');
    }
    
    // Use the new restore API that creates a trackable restore run
    const payload = {
        backup_run_id: st.selectedRunId,
        target_path: st.targetPath,
        mount: st.mount ? 'true' : 'false',
    };
    
    // Show loading state
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
            toast?.success?.('Restore started! Redirecting to progress view...');
            closeRestoreModal();
            
            // Redirect to live progress page for the restore run
            // Use the backup run's job_id for the live view
            const restoreRunParam = data.restore_run_uuid || data.restore_run_id;
            if (restoreRunParam) {
                setTimeout(() => {
                    window.location.href = 'index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_live&job_id=' + 
                        encodeURIComponent(data.job_id) + '&run_id=' + encodeURIComponent(restoreRunParam);
                }, 1000);
            }
        } else {
            toast?.error?.(data.message || 'Failed to start restore');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    })
    .catch(err => {
        toast?.error?.('Error starting restore: ' + err);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
}

function viewLogs(jobId) {
    window.location.href = 'index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs&job_id=' + encodeURIComponent(jobId);
}

document.getElementById('sourceType').addEventListener('change', function() {
    const s3Fields = document.getElementById('s3Fields');
    const awsFields = document.getElementById('awsFields');
    const sftpFields = document.getElementById('sftpFields');
    const gdriveFields = document.getElementById('gdriveFields');
    const dropboxFields = document.getElementById('dropboxFields');
    const localFields = document.getElementById('localAgentFields');
    // Helper to enable/disable inputs in a group
    function setGroupEnabled(groupEl, enabled) {
        if (!groupEl) return;
        const controls = groupEl.querySelectorAll('input, select, textarea, button');
        controls.forEach(el => {
            if (enabled) {
                el.removeAttribute('disabled');
            } else {
                el.setAttribute('disabled', 'disabled');
            }
        });
    }
    if (this.value === 's3_compatible') {
        s3Fields.classList.remove('hidden');
        setGroupEnabled(s3Fields, true);
        awsFields.classList.add('hidden');
        setGroupEnabled(awsFields, false);
        sftpFields.classList.add('hidden');
        setGroupEnabled(sftpFields, false);
        gdriveFields.classList.add('hidden'); setGroupEnabled(gdriveFields, false);
        dropboxFields.classList.add('hidden'); setGroupEnabled(dropboxFields, false);
        if (localFields) { localFields.classList.add('hidden'); setGroupEnabled(localFields, false); }
    } else if (this.value === 'aws') {
        s3Fields.classList.add('hidden');
        setGroupEnabled(s3Fields, false);
        awsFields.classList.remove('hidden');
        setGroupEnabled(awsFields, true);
        sftpFields.classList.add('hidden');
        setGroupEnabled(sftpFields, false);
        gdriveFields.classList.add('hidden'); setGroupEnabled(gdriveFields, false);
        dropboxFields.classList.add('hidden'); setGroupEnabled(dropboxFields, false);
        if (localFields) { localFields.classList.add('hidden'); setGroupEnabled(localFields, false); }
    } else if (this.value === 'google_drive') {
        s3Fields.classList.add('hidden'); setGroupEnabled(s3Fields, false);
        awsFields.classList.add('hidden'); setGroupEnabled(awsFields, false);
        sftpFields.classList.add('hidden'); setGroupEnabled(sftpFields, false);
        gdriveFields.classList.remove('hidden'); setGroupEnabled(gdriveFields, true);
        dropboxFields.classList.add('hidden'); setGroupEnabled(dropboxFields, false);
        if (localFields) { localFields.classList.add('hidden'); setGroupEnabled(localFields, false); }
    } else if (this.value === 'dropbox') {
        s3Fields.classList.add('hidden'); setGroupEnabled(s3Fields, false);
        awsFields.classList.add('hidden'); setGroupEnabled(awsFields, false);
        sftpFields.classList.add('hidden'); setGroupEnabled(sftpFields, false);
        gdriveFields.classList.add('hidden'); setGroupEnabled(gdriveFields, false);
        dropboxFields.classList.remove('hidden'); setGroupEnabled(dropboxFields, true);
        if (localFields) { localFields.classList.add('hidden'); setGroupEnabled(localFields, false); }
    } else if (this.value === 'local_agent') {
        if (localFields) { localFields.classList.remove('hidden'); setGroupEnabled(localFields, true); }
        s3Fields.classList.add('hidden'); setGroupEnabled(s3Fields, false);
        awsFields.classList.add('hidden'); setGroupEnabled(awsFields, false);
        sftpFields.classList.add('hidden'); setGroupEnabled(sftpFields, false);
        gdriveFields.classList.add('hidden'); setGroupEnabled(gdriveFields, false);
        dropboxFields.classList.add('hidden'); setGroupEnabled(dropboxFields, false);
    } else {
        s3Fields.classList.add('hidden');
        setGroupEnabled(s3Fields, false);
        awsFields.classList.add('hidden');
        setGroupEnabled(awsFields, false);
        sftpFields.classList.remove('hidden');
        setGroupEnabled(sftpFields, true);
        gdriveFields.classList.add('hidden'); setGroupEnabled(gdriveFields, false);
        dropboxFields.classList.add('hidden'); setGroupEnabled(dropboxFields, false);
        if (localFields) { localFields.classList.add('hidden'); setGroupEnabled(localFields, false); }
    }
    // Auto-populate hidden Source Display Name from selected source type (for S3-Compatible only)
    try {
        const dispEl = document.getElementById('sourceDisplayName');
        if (dispEl) {
            if (this.value === 's3_compatible') {
                const opt = this.options[this.selectedIndex];
                dispEl.value = (opt && opt.text) ? opt.text.trim() : 'S3-Compatible Storage';
            } else {
                // Clear for other types to allow type-specific logic to set their own display names
                dispEl.value = '';
            }
        }
    } catch (e) {}
    // Toggle read-only access keys warning for S3-Compatible and AWS only
    try {
        const warn = document.getElementById('sourceAccessWarning');
        if (warn) {
            if (this.value === 's3_compatible' || this.value === 'aws') {
                warn.classList.remove('hidden');
            } else {
                warn.classList.add('hidden');
            }
        }
    } catch (e) {}
});

// Themed no-schedule confirmation modal utilities
function showNoScheduleModal(onConfirm) {
    const msg = 'Create this backup without a schedule? You can add one later. Continue?';
    try {
        window._onNoScheduleConfirm = function() {
            try { if (typeof onConfirm === 'function') onConfirm(); } finally {
                const m = document.getElementById('noScheduleModal');
                if (m && m.__x && m.__x.$data) m.__x.$data.open = false;
            }
        };
        const m = document.getElementById('noScheduleModal');
        if (m && m.__x && m.__x.$data) {
            m.__x.$data.open = true;
            return;
        }
    } catch (e) {
    }
    // Fallback (no Alpine instance yet)
    if (confirm(msg)) {
        if (typeof onConfirm === 'function') onConfirm();
    }
}
function confirmNoScheduleCreate() {
    try { if (window._onNoScheduleConfirm) window._onNoScheduleConfirm(); } catch (e) {}
}

// Dedicated submit routine so modal can call it
function doCreateJobSubmit(formEl) {
    const formData = new FormData(formEl);
    const sourceType = formData.get('source_type');
    // Require destination prefix
    try {
        const dp = (formData.get('dest_prefix') || '').trim();
        if (!dp) {
            const el = document.getElementById('jobCreationMessage');
            const msg = 'Destination Prefix is required.';
            if (el) { el.textContent = msg; el.classList.remove('hidden'); }
            if (window.toast) window.toast.error(msg);
            return;
        }
    } catch (e) {}
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
        sourceDisplayName = formData.get('source_display_name');
        const s3Bucket = formData.get('s3_bucket') || '';
        const s3Prefix = formData.get('s3_path') || '';
        sourcePath = s3Prefix ? (s3Bucket + '/' + s3Prefix) : s3Bucket;
    } else if (sourceType === 'aws') {
        sourceConfig = {
            access_key: formData.get('aws_access_key'),
            secret_key: formData.get('aws_secret_key'),
            bucket: formData.get('aws_bucket'),
            region: formData.get('aws_region') || 'us-east-1'
        };
        sourceDisplayName = formData.get('aws_display_name');
        const awsBucket = formData.get('aws_bucket') || '';
        const awsPrefix = formData.get('aws_path') || '';
        sourcePath = awsPrefix ? (awsBucket + '/' + awsPrefix) : awsBucket;
    } else if (sourceType === 'sftp') {
        sourceConfig = {
            host: formData.get('sftp_host'),
            port: parseInt(formData.get('sftp_port')) || 22,
            user: formData.get('sftp_username'),
            pass: formData.get('sftp_password')
        };
        sourceDisplayName = formData.get('sftp_display_name');
        sourcePath = formData.get('sftp_path');
    } else if (sourceType === 'local_agent') {
        sourceConfig = {
            include_glob: formData.get('local_include_glob'),
            exclude_glob: formData.get('local_exclude_glob'),
            bandwidth_limit_kbps: formData.get('local_bandwidth_limit_kbps')
        };
        sourceDisplayName = 'Local Agent';
        sourcePath = formData.get('local_source_path');
    } else if (sourceType === 'google_drive') {
		sourceConfig = {
			root_folder_id: formData.get('gdrive_root_folder_id')
		};
		// Include team_drive when present (Shared Drive root or folder)
		const teamDrive = formData.get('gdrive_team_drive');
		if (teamDrive) sourceConfig.team_drive = teamDrive;
        // Persist selection (file or folder/root)
        const selType = formData.get('gdrive_selected_type') || '';
        const selId = formData.get('gdrive_selected_id') || '';
        if (selType) sourceConfig.selected_type = selType;
        if (selId) sourceConfig.selected_id = selId;
        // Display name: default to 'Google Drive' when not provided
        sourceDisplayName = formData.get('gdrive_display_name') || 'Google Drive';
        // No path for Drive; selection is ID-based
        sourcePath = '';
    } else if (sourceType === 'dropbox') {
        sourceConfig = {
            token: formData.get('dropbox_token'),
            root: formData.get('dropbox_root')
        };
        sourceDisplayName = formData.get('dropbox_display_name');
        sourcePath = formData.get('dropbox_path') || '';
    }
    const jobData = {
        name: formData.get('name'),
        source_type: sourceType,
        source_display_name: sourceDisplayName,
        source_config: JSON.stringify(sourceConfig),
        source_path: sourcePath,
        dest_bucket_id: formData.get('dest_bucket_id'),
        dest_prefix: formData.get('dest_prefix'),
        backup_mode: formData.get('backup_mode') || 'sync',
        encryption_enabled: formData.get('encryption_enabled') ? '1' : '0',
        validation_mode: formData.get('validation_enabled') ? 'post_run' : 'none',
        retention_mode: formData.get('retention_mode') || 'none',
        retention_value: formData.get('retention_value') || null,
        schedule_type: formData.get('schedule_type'),
        schedule_time: formData.get('schedule_time') || null,
        schedule_weekday: formData.get('schedule_weekday') || null,
        client_id: formData.get('client_id'),
        s3_user_id: formData.get('s3_user_id')
    };
    if (sourceType === 'local_agent') {
        jobData.agent_id = formData.get('agent_id') || '';
    }
    // Include Google Drive connection id when applicable
    if (sourceType === 'google_drive') {
        const connId = formData.get('source_connection_id') || '';
        if (connId) {
            jobData.source_connection_id = connId;
        }
    }
    fetch(CLOUDSTORAGE_API_BASE + '/cloudbackup_create_job.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(jobData)
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            try { if (window.toast) window.toast.success('Backup job created successfully'); } catch (e) {}
            try { closeCreateSlideover(); } catch (e) {}
            setTimeout(() => { location.reload(); }, 1200);
        } else {
            const msg = data.message || 'Failed to create job';
            const el = document.getElementById('jobCreationMessage');
            if (el) { el.textContent = msg; el.classList.remove('hidden'); }
            if (window.toast) window.toast.error(msg);
        }
    })
    .catch(() => {
        const el = document.getElementById('jobCreationMessage');
        if (el) { el.textContent = 'An error occurred'; el.classList.remove('hidden'); }
    });
}

function hideNoScheduleModal() {
    try {
        const m = document.getElementById('noScheduleModal');
        if (!m) return;
        if (m.__x && m.__x.$data) {
            m.__x.$data.open = false;
        }
        // Ensure hidden even if Alpine isn't available
        m.style.display = 'none';
    } catch (e) {}
}

document.getElementById('scheduleType').addEventListener('change', function() {
    const scheduleOptions = document.getElementById('scheduleOptions');
    const weeklyOption = document.getElementById('weeklyOption');
    if (this.value === 'manual') {
        scheduleOptions.classList.add('hidden');
    } else {
        scheduleOptions.classList.remove('hidden');
        if (this.value === 'weekly') {
            weeklyOption.classList.remove('hidden');
        } else {
            weeklyOption.classList.add('hidden');
        }
    }
});
document.getElementById('createJobForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // Themed modal check for schedule
    try {
        const scheduleTypeEl = document.getElementById('scheduleType');
        const stype = scheduleTypeEl ? (scheduleTypeEl.value || '').toLowerCase() : 'manual';
        if (stype === 'manual') {
            const formEl = this;
            return showNoScheduleModal(() => doCreateJobSubmit(formEl));
        }
    } catch (e) {}
    const formData = new FormData(this);
    const sourceType = formData.get('source_type');
    
    // Build source_config JSON
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
        sourceDisplayName = formData.get('source_display_name');
        const s3Bucket = formData.get('s3_bucket') || '';
        const s3Prefix = formData.get('s3_path') || '';
        sourcePath = s3Prefix ? (s3Bucket + '/' + s3Prefix) : s3Bucket;
    } else if (sourceType === 'aws') {
        sourceConfig = {
            access_key: formData.get('aws_access_key'),
            secret_key: formData.get('aws_secret_key'),
            bucket: formData.get('aws_bucket'),
            region: formData.get('aws_region') || 'us-east-1'
        };
        sourceDisplayName = formData.get('aws_display_name');
        const awsBucket = formData.get('aws_bucket') || '';
        const awsPrefix = formData.get('aws_path') || '';
        sourcePath = awsPrefix ? (awsBucket + '/' + awsPrefix) : awsBucket;
    } else if (sourceType === 'sftp') {
        sourceConfig = {
            host: formData.get('sftp_host'),
            port: parseInt(formData.get('sftp_port')) || 22,
            user: formData.get('sftp_username'),
            pass: formData.get('sftp_password')
        };
        sourceDisplayName = formData.get('sftp_display_name');
        sourcePath = formData.get('sftp_path');
    } else if (sourceType === 'google_drive') {
        sourceConfig = {
            root_folder_id: formData.get('gdrive_root_folder_id')
        };
		const teamDrive2 = formData.get('gdrive_team_drive');
		if (teamDrive2) sourceConfig.team_drive = teamDrive2;
        // Persist selection (file or folder/root)
        const selType2 = formData.get('gdrive_selected_type') || '';
        const selId2 = formData.get('gdrive_selected_id') || '';
        if (selType2) sourceConfig.selected_type = selType2;
        if (selId2) sourceConfig.selected_id = selId2;
        sourceDisplayName = formData.get('gdrive_display_name') || 'Google Drive';
        // No path for Drive; selection is ID-based
        sourcePath = '';
    } else if (sourceType === 'dropbox') {
        sourceConfig = {
            token: formData.get('dropbox_token'),
            root: formData.get('dropbox_root')
        };
        sourceDisplayName = formData.get('dropbox_display_name');
        sourcePath = formData.get('dropbox_path') || '';
    }
    
    const jobData = {
        name: formData.get('name'),
        source_type: sourceType,
        source_display_name: sourceDisplayName,
        source_config: JSON.stringify(sourceConfig),
        source_path: sourcePath,
        dest_bucket_id: formData.get('dest_bucket_id'),
        dest_prefix: formData.get('dest_prefix'),
        backup_mode: formData.get('backup_mode') || 'sync',
        encryption_enabled: formData.get('encryption_enabled') ? '1' : '0',
        validation_mode: formData.get('validation_enabled') ? 'post_run' : 'none',
        retention_mode: formData.get('retention_mode') || 'none',
        retention_value: formData.get('retention_value') || null,
        schedule_type: formData.get('schedule_type'),
        schedule_time: formData.get('schedule_time') || null,
        schedule_weekday: formData.get('schedule_weekday') || null,
        client_id: formData.get('client_id'),
        s3_user_id: formData.get('s3_user_id')
    };
    
    // Check if we're in edit mode
    const isEdit = window.cloudWizardState?.editMode && window.cloudWizardState?.jobId;
    if (isEdit) {
        jobData.job_id = window.cloudWizardState.jobId;
    }
    
    const endpoint = isEdit 
        ? (CLOUDSTORAGE_API_BASE + '/cloudbackup_update_job.php')
        : (CLOUDSTORAGE_API_BASE + '/cloudbackup_create_job.php');
    
    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(jobData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Success toast + close panel, then refresh after a brief delay so the toast is visible
            try {
                if (window.toast && typeof window.toast.success === 'function') {
                    window.toast.success(isEdit ? 'Backup job updated successfully' : 'Backup job created successfully');
                }
            } catch (e) {}
            try { closeCreateSlideover(); } catch (e) {}
            setTimeout(() => { location.reload(); }, 1200);
        } else {
            const msg = data.message || (isEdit ? 'Failed to update job' : 'Failed to create job');
            const el = document.getElementById('jobCreationMessage');
            if (el) { el.textContent = msg; el.classList.remove('hidden'); }
            if (window.toast && typeof window.toast.error === 'function') { window.toast.error(msg); }
        }
    })
    .catch(error => {
        document.getElementById('jobCreationMessage').textContent = 'An error occurred';
        document.getElementById('jobCreationMessage').classList.remove('hidden');
    });
});

// Ensure correct enabled/disabled state when modal opens and on initial load
function applyInitialSourceState() {
    const sourceTypeEl = document.getElementById('sourceType');
    if (sourceTypeEl) {
        const event = new Event('change');
        sourceTypeEl.dispatchEvent(event);
    }
}

// (applyInitialSourceState is called within openCreateJobModal for the slideover)

// Apply on initial page load
document.addEventListener('DOMContentLoaded', applyInitialSourceState);

// Compute Next Run times on load
document.addEventListener('DOMContentLoaded', function() {
    const nodes = document.querySelectorAll('[data-next-run="1"]');
    nodes.forEach(function(el) {
        const type = (el.getAttribute('data-type') || '').toLowerCase();
        const t = el.getAttribute('data-time') || '';
        const weekday = parseInt(el.getAttribute('data-weekday')) || null; // 1=Mon..7=Sun
        try {
            const txt = computeNextRunText(type, t, weekday);
            if (txt) el.textContent = txt;
        } catch (e) {
            el.textContent = '-';
        }
    });
});

// Auto-open Create panel after OAuth return to resume flow
document.addEventListener('DOMContentLoaded', function() {
    try {
        const params = new URLSearchParams(window.location.search || '');
        if (params.get('open_create') === '1') {
            openCreateJobModal();
            const pre = params.get('prefill_source') || 'google_drive';
            const sel = document.getElementById('sourceType');
            if (sel) {
                sel.value = pre;
                sel.dispatchEvent(new Event('change'));
            }
            // If Google Drive, automatically load available connections
            setTimeout(() => {
                const gf = document.getElementById('gdriveFields');
                if (gf && gf.__x && gf.__x.$data && typeof gf.__x.$data.load === 'function') {
                    gf.__x.$data.load();
                }
            }, 150);
            // Clean query params to avoid reopening on refresh
            const url = new URL(window.location.href);
            url.searchParams.delete('open_create');
            url.searchParams.delete('prefill_source');
            window.history.replaceState({}, '', url.toString());
        }
    } catch (e) {}
});

function computeNextRunText(type, timeStr, weekday) {
    if (!type || type === 'manual') return '-';
    const now = new Date();
    const [hh, mm, ss] = (timeStr || '00:00:00').split(':').map(x => parseInt(x, 10) || 0);
    let next = new Date();
    next.setSeconds(0); // reset seconds before setting hours/minutes
    next.setHours(hh, mm, ss || 0, 0);

    if (type === 'daily') {
        if (next <= now) {
            next.setDate(next.getDate() + 1);
        }
        return fmtDateTime(next);
    }
    if (type === 'weekly') {
        // Convert 1-7 (Mon..Sun) to JS 0-6 (Sun..Sat)
        let jsTarget = 0;
        if (weekday && weekday >= 1 && weekday <= 7) {
            jsTarget = (weekday % 7); // 7->0 (Sun)
        }
        next.setDate(now.getDate() + ((7 + jsTarget - now.getDay()) % 7));
        if (next <= now) {
            next.setDate(next.getDate() + 7);
        }
        return fmtDateTime(next);
    }
    return '-';
}

function fmtDateTime(d) {
    // e.g., 24 Feb 2025 13:05
    try {
        const opts = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        return new Intl.DateTimeFormat(undefined, opts).format(d);
    } catch (e) {
        // Fallback
        const pad = (n) => (n < 10 ? '0' + n : '' + n);
        return pad(d.getDate()) + ' ' + (d.toLocaleString('default',{month:'short'})) + ' ' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
}

function startRun(jobId) {
	return fetch('modules/addons/cloudstorage/api/cloudbackup_start_run.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({job_id: jobId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            const runParam = data.run_uuid || data.run_id;
            window.location.href = 'index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_live&run_id=' + runParam;
        } else {
            alert(data.message || 'Failed to start run');
        }
	});
}

function toggleJobStatus(jobId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'paused' : 'active';
    fetch('modules/addons/cloudstorage/api/cloudbackup_update_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams([['job_id', jobId], ['status', newStatus]])
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert(data.message || 'Failed to update job');
        }
    });
}

function deleteJob(jobId) {
    if (!confirm('Are you sure you want to delete this job?')) {
        return;
    }
    fetch('modules/addons/cloudstorage/api/cloudbackup_delete_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams([['job_id', jobId]])
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            alert(data.message || 'Failed to delete job');
        }
    });
}

function editJob(jobId, sourceType) {
    // If explicitly a local agent job, open the wizard for edit; otherwise use slide-over.
    if (sourceType && sourceType.toLowerCase() === 'local_agent') {
        openLocalJobWizardForEdit(jobId);
        return;
    }
    ensureEditPanel();
    openEditSlideover(jobId);
}

function ensureEditPanel() {
    const panel = document.getElementById('editJobSlideover');
    if (!panel) return; // markup already in DOM
    // copy dest bucket options from create form if available (sync hidden select)
    var srcSel = document.querySelector('select[name="dest_bucket_id"]');
    var dstSel = document.getElementById('edit_dest_bucket_src');
    if (srcSel && dstSel) {
        dstSel.innerHTML = srcSel.innerHTML;
    }
}

function openEditSlideover(jobId) {
    const msg = document.getElementById('editJobMessage');
    msg.classList.add('hidden');
    msg.textContent = '';

    const panel = document.getElementById('editJobSlideover');
    if (!panel) {
        console.error('Edit panel not found');
        return;
    }
    
    // Always force show the panel and its children first (override Alpine's x-show)
    panel.style.setProperty('display', 'block', 'important');
    
    // Show backdrop
    const backdrop = panel.querySelector('.absolute.inset-0.bg-black.bg-opacity-50') || 
                     panel.querySelector('.absolute.inset-0');
    if (backdrop) backdrop.style.setProperty('display', 'block', 'important');
    
    // Show panel content
    const panelContent = panel.querySelector('.absolute.right-0.top-0') || 
                         panel.querySelector('.absolute.right-0');
    if (panelContent) panelContent.style.setProperty('display', 'block', 'important');
    
    // Then set Alpine state for transitions (if Alpine is available)
    if (window.Alpine) {
        // Initialize Alpine on this element if needed
        if (!panel.__x) {
            if (typeof Alpine.initTree === 'function') {
                Alpine.initTree(panel);
            }
        }
        
        // Set the reactive state after a brief delay to ensure Alpine is ready
        setTimeout(() => {
            if (panel.__x && panel.__x.$data) {
                panel.__x.$data.isOpen = true;
            }
        }, 0);
    }

    // Reset form and set job ID
    document.getElementById('edit_job_id').value = jobId;
    const form = document.getElementById('editJobForm');
    form.reset();
    document.getElementById('edit_job_id').value = jobId;

    // Populate hidden select options from existing create form select
    const srcSel = document.querySelector('select[name="dest_bucket_id"]');
    const dstSel = document.getElementById('edit_dest_bucket_src');
    if (srcSel && dstSel) {
        dstSel.innerHTML = srcSel.innerHTML;
    }

    // Fetch job data and populate form
    fetch('modules/addons/cloudstorage/api/cloudbackup_get_job.php?job_id=' + encodeURIComponent(jobId))
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success' || !data.job) {
                msg.textContent = data.message || 'Failed to load job details';
                msg.classList.remove('hidden');
                if (window.toast) window.toast.error(data.message || 'Failed to load job details');
                return;
            }
            const j = data.job; const s = data.source || {};
            document.getElementById('edit_name').value = j.name || '';
            const sdnEl = document.getElementById('edit_source_display_name');
            if (sdnEl) { sdnEl.value = j.source_display_name || ''; }
            const sourceTypeValue = (j.source_type || 's3_compatible').toString().toLowerCase();
            document.getElementById('edit_source_type').value = sourceTypeValue;
            onEditSourceTypeChange();

            if (sourceTypeValue === 's3_compatible') {
                document.getElementById('edit_s3_endpoint').value = s.endpoint || '';
                document.getElementById('edit_s3_region').value = s.region || 'ca-central-1';
                try { document.getElementById('edit_s3_region').dispatchEvent(new Event('change')); } catch (e) {}
                document.getElementById('edit_s3_bucket').value = s.bucket || '';
                const parts = (j.source_path || '').split('/');
                const b = parts.shift() || '';
                const p = parts.join('/');
                if (!document.getElementById('edit_s3_bucket').value) document.getElementById('edit_s3_bucket').value = b;
                document.getElementById('edit_s3_path').value = p || '';
            } else if (sourceTypeValue === 'aws') {
                document.getElementById('edit_aws_region').value = s.region || 'us-east-1';
                try { document.getElementById('edit_aws_region').dispatchEvent(new Event('change')); } catch (e) {}
                document.getElementById('edit_aws_bucket').value = s.bucket || '';
                const parts2 = (j.source_path || '').split('/');
                const b2 = parts2.shift() || '';
                const p2 = parts2.join('/');
                if (!document.getElementById('edit_aws_bucket').value) document.getElementById('edit_aws_bucket').value = b2;
                document.getElementById('edit_aws_path').value = p2 || '';
            } else if (sourceTypeValue === 'sftp') {
                document.getElementById('edit_sftp_host').value = s.host || '';
                document.getElementById('edit_sftp_port').value = s.port || 22;
                document.getElementById('edit_sftp_username').value = s.user || '';
                const sftpPathEl = document.getElementById('edit_sftp_path');
                if (sftpPathEl) { sftpPathEl.value = j.source_path || ''; }
            } else if (sourceTypeValue === 'local_agent') {
                const lp = document.getElementById('edit_local_source_path');
                if (lp) lp.value = j.source_path || '';
                const inc = document.getElementById('edit_local_include_glob');
                if (inc) inc.value = s.include_glob || '';
                const exc = document.getElementById('edit_local_exclude_glob');
                if (exc) exc.value = s.exclude_glob || '';
                const bw = document.getElementById('edit_local_bandwidth_limit_kbps');
                if (bw) bw.value = s.bandwidth_limit_kbps || '';
                const agentSel = document.getElementById('edit_agent_id');
                if (agentSel) {
                    agentSel.value = j.agent_id || '';
                    try { agentSel.dispatchEvent(new Event('change')); } catch (e) {}
                }
            } else if (sourceTypeValue === 'google_drive') {
                const root = document.getElementById('edit_gdrive_root_folder_id');
                if (root) root.value = (s.root_folder_id || '');
                const team = document.getElementById('edit_gdrive_team_drive');
                if (team) team.value = (s.team_drive || '');
                const gpath = document.getElementById('edit_gdrive_path');
                if (gpath) gpath.value = j.source_path || '';
                const conn = document.getElementById('edit_source_connection_id');
                if (conn) {
                    const cid = (j.source_connection_id || s.source_connection_id || s.connection_id || '');
                    conn.value = cid;
                    try { conn.dispatchEvent(new Event('change')); } catch (e) {}
                }
            } else if (j.source_type === 'dropbox') {
                document.getElementById('edit_dropbox_token').value = s.token || '';
                document.getElementById('edit_dropbox_root').value = s.root || '';
                document.getElementById('edit_dropbox_path').value = j.source_path || '';
            }

            // destination (set hidden select and sync Alpine state)
            if (dstSel) {
                for (let i = 0; i < dstSel.options.length; i++) {
                    if (String(dstSel.options[i].value) === String(j.dest_bucket_id)) {
                        dstSel.selectedIndex = i;
                        try { dstSel.dispatchEvent(new Event('change')); } catch (e) {}
                        break;
                    }
                }
            }
            document.getElementById('edit_dest_prefix').value = j.dest_prefix || '';

            // backup mode
            const bm = document.getElementById('edit_backup_mode');
            if (bm) {
                bm.value = j.backup_mode || 'sync';
                try { bm.dispatchEvent(new Event('change')); } catch (e) {}
            }

            // encryption
            const enc = document.getElementById('edit_encryption_enabled');
            if (enc) {
                enc.checked = (j.encryption_enabled == 1 || j.encryption_enabled === true);
            }

            // validation
            const val = document.getElementById('edit_validation_enabled');
            if (val) {
                val.checked = (j.validation_mode == 'post_run' || j.validation_mode === 'post_run');
            }

            // retention
            const retMode = document.getElementById('edit_retention_mode');
            if (retMode) {
                retMode.value = j.retention_mode || 'none';
                try { retMode.dispatchEvent(new Event('change')); } catch (e) {}
                onEditRetentionModeChange();
            }
            const retVal = document.getElementById('edit_retention_value');
            if (retVal && j.retention_value) {
                retVal.value = j.retention_value;
            }

            // schedule
            const scheduleTypeEl = document.getElementById('edit_schedule_type');
            if (scheduleTypeEl) {
                scheduleTypeEl.value = j.schedule_type || 'manual';
                onEditScheduleChange();
            }
            if (j.schedule_time) document.getElementById('edit_schedule_time').value = j.schedule_time;
            if (j.schedule_weekday) document.getElementById('edit_schedule_weekday').value = j.schedule_weekday;
        })
        .catch(() => {
            if (window.toast) {
                window.toast.error('Error loading job details');
            } else {
                document.getElementById('globalMessage').textContent = 'Error loading job details';
                document.getElementById('globalMessage').classList.remove('hidden');
                setTimeout(()=>{ document.getElementById('globalMessage').classList.add('hidden'); }, 2500);
            }
        });
}

function closeEditSlideover() {
    const panel = document.getElementById('editJobSlideover');
    if (!panel) return;
    
    // Force hide the panel and its children using !important (override any inline styles)
    panel.style.setProperty('display', 'none', 'important');
    
    // Hide backdrop (the div with bg-black bg-opacity-50)
    const backdrop = panel.querySelector('.absolute.inset-0.bg-black.bg-opacity-50') || 
                     panel.querySelector('.absolute.inset-0');
    if (backdrop) backdrop.style.setProperty('display', 'none', 'important');
    
    // Hide panel content (the slide-over panel itself)
    const panelContent = panel.querySelector('.absolute.right-0.top-0') || 
                         panel.querySelector('.absolute.right-0');
    if (panelContent) panelContent.style.setProperty('display', 'none', 'important');
    
    // Also set Alpine state for consistency (if Alpine is available)
    if (panel.__x && panel.__x.$data) {
        panel.__x.$data.isOpen = false;
    }
}

function closeCreateSlideover() {
    const panel = document.getElementById('createJobSlideover');
    if (!panel) return;

    // Force hide panel and backdrop
    panel.style.setProperty('display', 'none', 'important');
    const backdrop = panel.querySelector('.absolute.inset-0.bg-black.bg-opacity-50') || panel.querySelector('.absolute.inset-0');
    if (backdrop) backdrop.style.setProperty('display', 'none', 'important');
    const panelContent = panel.querySelector('.absolute.right-0.top-0') || panel.querySelector('.absolute.right-0');
    if (panelContent) panelContent.style.setProperty('display', 'none', 'important');

    if (panel.__x && panel.__x.$data) {
        panel.__x.$data.isOpen = false;
    }
    
    // Reset edit mode state when closing
    window.cloudWizardState = { editMode: false, jobId: null };
}

function onEditSourceTypeChange() {
    const t = document.getElementById('edit_source_type').value;
    const s3 = document.getElementById('edit_s3_fields');
    const aws = document.getElementById('edit_aws_fields');
    const sftp = document.getElementById('edit_sftp_fields');
    const gdr = document.getElementById('edit_gdrive_fields');
    const drp = document.getElementById('edit_dropbox_fields');
    const lcl = document.getElementById('edit_local_fields');
    const setEnabled = (el, on) => { if (!el) return; el.querySelectorAll('input,select,textarea,button').forEach(e => on ? e.removeAttribute('disabled') : e.setAttribute('disabled','disabled')); };
    if (t === 's3_compatible') { s3.classList.remove('hidden'); setEnabled(s3,true); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.add('hidden'); setEnabled(sftp,false); gdr.classList.add('hidden'); setEnabled(gdr,false); drp.classList.add('hidden'); setEnabled(drp,false); if (lcl) { lcl.classList.add('hidden'); setEnabled(lcl,false);} }
    else if (t === 'aws') { s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.remove('hidden'); setEnabled(aws,true); sftp.classList.add('hidden'); setEnabled(sftp,false); gdr.classList.add('hidden'); setEnabled(gdr,false); drp.classList.add('hidden'); setEnabled(drp,false); if (lcl) { lcl.classList.add('hidden'); setEnabled(lcl,false);} }
    else if (t === 'sftp') { s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.remove('hidden'); setEnabled(sftp,true); gdr.classList.add('hidden'); setEnabled(gdr,false); drp.classList.add('hidden'); setEnabled(drp,false); if (lcl) { lcl.classList.add('hidden'); setEnabled(lcl,false);} }
    else if (t === 'google_drive') { s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.add('hidden'); setEnabled(sftp,false); gdr.classList.remove('hidden'); setEnabled(gdr,true); drp.classList.add('hidden'); setEnabled(drp,false); if (lcl) { lcl.classList.add('hidden'); setEnabled(lcl,false);} }
    else if (t === 'dropbox') { s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.add('hidden'); setEnabled(sftp,false); gdr.classList.add('hidden'); setEnabled(gdr,false); drp.classList.remove('hidden'); setEnabled(drp,true); if (lcl) { lcl.classList.add('hidden'); setEnabled(lcl,false);} }
    else if (t === 'local_agent') { if (lcl) { lcl.classList.remove('hidden'); setEnabled(lcl,true); } s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.add('hidden'); setEnabled(sftp,false); gdr.classList.add('hidden'); setEnabled(gdr,false); drp.classList.add('hidden'); setEnabled(drp,false); }
}

function onEditScheduleChange() {
    const t = document.getElementById('edit_schedule_type').value;
    const opts = document.getElementById('edit_schedule_options');
    const weekly = document.getElementById('edit_weekly_option');
    if (t === 'manual') { opts.classList.add('hidden'); }
    else { opts.classList.remove('hidden'); if (t === 'weekly') { weekly.classList.remove('hidden'); } else { weekly.classList.add('hidden'); } }
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
                help.textContent = 'Keep only the N most recent successful backup runs. Older runs will be automatically pruned.';
            } else if (mode === 'keep_days') {
                help.textContent = 'Keep backup data for N days. Historical versions older than this will be automatically removed.';
            } else {
                help.textContent = '';
            }
        }
    }
}

function onEditRetentionModeChange() {
    const mode = document.getElementById('edit_retention_mode').value;
    const container = document.getElementById('edit_retention_value_container');
    const help = document.getElementById('edit_retention_help');
    if (mode === 'none') {
        container.classList.add('hidden');
    } else {
        container.classList.remove('hidden');
        if (mode === 'keep_last_n') {
            help.textContent = 'Keep only the N most recent successful backup runs. Older runs will be automatically deleted.';
        } else if (mode === 'keep_days') {
            help.textContent = 'Keep backup data for N days. Data older than this will be automatically deleted.';
        }
    }
}

function saveEditedJob() {
    const panel = document.getElementById('editJobSlideover');
    const msg = document.getElementById('editJobMessage');
    msg.classList.add('hidden'); msg.textContent='';

    // Require destination prefix
    try {
        const dp = (document.getElementById('edit_dest_prefix')?.value || '').trim();
        if (!dp) {
            const m = 'Destination Prefix is required.';
            if (window.toast) window.toast.error(m);
            msg.textContent = m;
            msg.classList.remove('hidden');
            return;
        }
    } catch (e) {}

    const jobId = document.getElementById('edit_job_id').value;
    const payload = new URLSearchParams();
    payload.set('job_id', jobId);
    payload.set('name', (document.getElementById('edit_name').value || '').trim());
    const stype = document.getElementById('edit_source_type').value;
    payload.set('source_type', stype);
    (function(){
        const el = document.getElementById('edit_source_display_name');
        payload.set('source_display_name', ((el && el.value) ? el.value : '').trim());
    })();

    if (stype === 's3_compatible') {
        const bucket = (document.getElementById('edit_s3_bucket').value || '').trim();
        const prefix = (document.getElementById('edit_s3_path').value || '').trim();
        payload.set('source_path', prefix ? (bucket + '/' + prefix) : bucket);
        const ep = (document.getElementById('edit_s3_endpoint').value || '').trim();
        const rg = (document.getElementById('edit_s3_region').value || 'ca-central-1').trim();
        const ak = (document.getElementById('edit_s3_access_key').value || '').trim();
        const sk = (document.getElementById('edit_s3_secret_key').value || '').trim();
        if (ep) payload.set('s3_endpoint', ep);
        if (rg) payload.set('s3_region', rg);
        if (bucket) payload.set('s3_bucket', bucket);
        if (ak) payload.set('s3_access_key', ak);
        if (sk) payload.set('s3_secret_key', sk);
    } else if (stype === 'aws') {
        const bucket2 = (document.getElementById('edit_s3_bucket').value || document.getElementById('edit_aws_bucket').value || '').trim();
        const prefix2 = (document.getElementById('edit_aws_path').value || '').trim();
        payload.set('source_path', prefix2 ? (bucket2 + '/' + prefix2) : bucket2);
        const rg2 = (document.getElementById('edit_aws_region').value || 'us-east-1').trim();
        const ak2 = (document.getElementById('edit_aws_access_key').value || '').trim();
        const sk2 = (document.getElementById('edit_aws_secret_key').value || '').trim();
        if (rg2) payload.set('aws_region', rg2);
        if (bucket2) payload.set('aws_bucket', bucket2);
        if (ak2) payload.set('aws_access_key', ak2);
        if (sk2) payload.set('aws_secret_key', sk2);
    } else if (stype === 'sftp') {
        const host = (document.getElementById('edit_sftp_host').value || '').trim();
        const port = parseInt(document.getElementById('edit_sftp_port').value) || 22;
        const user = (document.getElementById('edit_sftp_username').value || '').trim();
        const pass = (document.getElementById('edit_sftp_password').value || '').trim();
        if (host) payload.set('sftp_host', host);
        if (port) payload.set('sftp_port', port);
        if (user) payload.set('sftp_username', user);
        if (pass) payload.set('sftp_password', pass);
    } else if (stype === 'google_drive') {
        const connId = (document.getElementById('edit_source_connection_id')?.value || '').trim();
        const rootId = (document.getElementById('edit_gdrive_root_folder_id')?.value || '').trim();
		const teamId = (document.getElementById('edit_gdrive_team_drive')?.value || '').trim();
        const path = (document.getElementById('edit_gdrive_path')?.value || '').trim();
		// Persist selection (file or folder/root) from hidden fields
		const selId2 = (document.querySelector('#edit_gdrive_fields input[name=\"gdrive_selected_id\"]')?.value || '').trim();
		const selType2 = (document.querySelector('#edit_gdrive_fields input[name=\"gdrive_selected_type\"]')?.value || '').trim();
        if (connId) payload.set('source_connection_id', connId);
        if (rootId) payload.set('gdrive_root_folder_id', rootId);
		// Allow empty root (meaning entire drive); still send team drive if present
		if (!rootId) payload.set('gdrive_root_folder_id', '');
		if (teamId) payload.set('gdrive_team_drive', teamId);
		if (selType2) payload.set('gdrive_selected_type', selType2);
		if (selId2) payload.set('gdrive_selected_id', selId2);
        if (path) payload.set('source_path', path);
    } else if (stype === 'dropbox') {
        const token = (document.getElementById('edit_dropbox_token').value || '').trim();
        const root = (document.getElementById('edit_dropbox_root').value || '').trim();
        const path = (document.getElementById('edit_dropbox_path').value || '').trim();
        if (token) payload.set('dropbox_token', token);
        if (root) payload.set('dropbox_root', root);
        if (path) payload.set('source_path', path);
    } else if (stype === 'local_agent') {
        const lp = (document.getElementById('edit_local_source_path').value || '').trim();
        const inc = (document.getElementById('edit_local_include_glob').value || '').trim();
        const exc = (document.getElementById('edit_local_exclude_glob').value || '').trim();
        const bw = (document.getElementById('edit_local_bandwidth_limit_kbps').value || '').trim();
        const agentId = (document.getElementById('edit_agent_id').value || '').trim();
        if (lp) payload.set('source_path', lp);
        if (inc) payload.set('local_include_glob', inc);
        if (exc) payload.set('local_exclude_glob', exc);
        if (bw) payload.set('local_bandwidth_limit_kbps', bw);
        if (agentId) payload.set('agent_id', agentId);
    }

    // destination
    const destBucketId = document.getElementById('edit_dest_bucket_id').value;
    const destPrefix = document.getElementById('edit_dest_prefix').value;
    if (destBucketId) payload.set('dest_bucket_id', destBucketId);
    if (destPrefix) payload.set('dest_prefix', destPrefix);

    // backup mode
    const backupMode = document.getElementById('edit_backup_mode').value;
    if (backupMode) payload.set('backup_mode', backupMode);

    // encryption (optional checkbox may be hidden)
    (function(){
        const el = document.getElementById('edit_encryption_enabled');
        const enabled = el ? !!el.checked : false;
        payload.set('encryption_enabled', enabled ? '1' : '0');
    })();

    // validation (optional checkbox may be hidden)
    (function(){
        const el = document.getElementById('edit_validation_enabled');
        const enabled = el ? !!el.checked : false;
        payload.set('validation_mode', enabled ? 'post_run' : 'none');
    })();

    // retention
    const retentionMode = document.getElementById('edit_retention_mode').value;
    const retentionValue = document.getElementById('edit_retention_value').value;
    payload.set('retention_mode', retentionMode);
    if (retentionMode !== 'none' && retentionValue) {
        payload.set('retention_value', retentionValue);
    }

    // schedule
    const scheduleType = document.getElementById('edit_schedule_type').value;
    payload.set('schedule_type', scheduleType);
    if (scheduleType === 'daily' || scheduleType === 'weekly') {
        const time = document.getElementById('edit_schedule_time').value;
        const weekday = document.getElementById('edit_schedule_weekday').value;
        if (time) payload.set('schedule_time', time);
        if (weekday) payload.set('schedule_weekday', weekday);
    }

    fetch('modules/addons/cloudstorage/api/cloudbackup_update_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: payload
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Show success toast
            if (window.toast) {
                window.toast.success('Job updated successfully');
            }
            // Close the slide-over
            closeEditSlideover();
            // Update the job row in place (optional - could also just reload)
            updateJobRowInPlace(jobId, data.job);
        } else {
            // Show error toast
            const errorMsg = data.message || 'Failed to save changes';
            if (window.toast) {
                window.toast.error(errorMsg);
            } else {
                msg.textContent = errorMsg;
                msg.classList.remove('hidden');
            }
        }
    })
    .catch(error => {
        const errorMsg = 'An error occurred while saving';
        if (window.toast) {
            window.toast.error(errorMsg);
        } else {
            msg.textContent = errorMsg;
            msg.classList.remove('hidden');
        }
    });
}

function updateJobRowInPlace(jobId, updatedJob) {
    // Optionally update the job row without full page reload
    // For now, we'll just close the panel and show toast
    // Full update would require fetching the updated job and updating the DOM
    // This is a placeholder for future enhancement
}

// Alpine store for job filters and filtering logic
document.addEventListener('alpine:init', () => {
	try {
		if (window.Alpine && !Alpine.store('jobFilters')) {
			Alpine.store('jobFilters', { q: '', status: 'all', sourceType: 'all' });
		}
	} catch (e) {}
});

function jobListFilter() {
	return {
		init() { this.apply(); },
		apply() {
			try {
				const store = (window.Alpine && Alpine.store('jobFilters')) ? Alpine.store('jobFilters') : { q:'', status:'all', sourceType:'all' };
				const q = (store.q || '').toLowerCase();
				const status = (store.status || 'all').toLowerCase();
                const sourceFilter = (store.sourceType || 'all').toLowerCase();
				const now = Date.now();
				const cards = this.$root.querySelectorAll('[data-job-card]');
				cards.forEach((card) => {
					const name = (card.getAttribute('data-name') || '').toLowerCase();
					const jobStatus = (card.getAttribute('data-status') || '').toLowerCase();
					const lastStatus = (card.getAttribute('data-last-status') || '').toLowerCase();
					const lastStarted = card.getAttribute('data-last-started');
					const source = (card.getAttribute('data-source') || '').toLowerCase();
					const sourceType = (card.getAttribute('data-source-type') || '').toLowerCase();
					const dest = (card.getAttribute('data-dest') || '').toLowerCase();
					let failedRecent = false;
					if (lastStatus === 'failed' && lastStarted) {
						const t = Date.parse(lastStarted);
						if (!isNaN(t)) {
							failedRecent = (now - t) <= (24 * 3600 * 1000);
						}
					}
					// Text search across name, job status, last run status, source, source type, and destination
					const hay = (name + ' ' + jobStatus + ' ' + lastStatus + ' ' + source + ' ' + sourceType + ' ' + dest).trim();
					let ok = (!q || hay.indexOf(q) !== -1);
					if (ok && sourceFilter !== 'all') {
                        if (sourceFilter === 'cloud') {
                            ok = (sourceType !== 'local_agent');
                        } else {
                            ok = (sourceType === sourceFilter);
                        }
                    }
					if (ok && status !== 'all') {
						if (status === 'failed_recent') {
							ok = failedRecent;
						} else if (status === 'running') {
							ok = (lastStatus === 'running' || lastStatus === 'starting' || lastStatus === 'queued');
						} else {
							ok = (lastStatus === status);
						}
					}
					card.style.display = ok ? '' : 'none';
				});
			} catch (e) {}
		}
	};
}

// Run-now convenience wrapper returning a promise for Alpine
function runJob(jobId) {
	try {
		return startRun(jobId);
	} catch (e) {
		return Promise.reject(e);
	}
}

// Callback for when a bucket is created from the modal (cloud backup wizard)
function onCloudBackupBucketCreated(bucket) {
    if (!bucket || !bucket.id) return;
    
    // Find the bucket dropdown select element
    const selectEl = document.querySelector('select[name="dest_bucket_id"]');
    if (selectEl) {
        // Add the new bucket as an option
        const opt = document.createElement('option');
        opt.value = bucket.id;
        opt.textContent = bucket.name;
        selectEl.appendChild(opt);
        
        // Select the new bucket
        selectEl.value = bucket.id;
        selectEl.dispatchEvent(new Event('change'));
    }
    
    // Show success notification
    if (window.toast) {
        window.toast.success('Bucket "' + bucket.name + '" created and selected');
    }
}

// Inline bucket create helper for Create Job slide-over (legacy, kept for compatibility)
async function createBucketInline() {
	const nameEl = document.getElementById('inline_bucket_name');
	const verEl = document.getElementById('inline_bucket_versioning');
	const daysEl = document.getElementById('inline_bucket_retention_days');
	const msgEl = document.getElementById('inlineCreateBucketMsg');
	const userEl = document.getElementById('inline_username');
	const selectEl = document.querySelector('select[name="dest_bucket_id"]');

	if (!nameEl || !selectEl) return Promise.resolve();
	const bucket_name = (nameEl.value || '').trim();
	const versioning_enabled = verEl && verEl.checked ? '1' : '0';
	const retention_days = (versioning_enabled === '1' && daysEl) ? (parseInt(daysEl.value, 10) || 0) : 0;
	const username = (userEl && userEl.value ? userEl.value : '').trim();

	// Client-side validation
	try {
		if (username === '') {
			if (msgEl) { msgEl.className = 'text-xs text-rose-300'; msgEl.textContent = 'Please select a tenant.'; msgEl.classList.remove('hidden'); }
			return;
		}
		const validation = validateBucketName(bucket_name);
		if (!validation.isValid) {
			if (msgEl) { msgEl.className = 'text-xs text-rose-300'; msgEl.textContent = validation.message || 'Invalid bucket name.'; msgEl.classList.remove('hidden'); }
			return;
		}
	} catch (e) {
		// fallthrough
	}

	if (msgEl) { msgEl.className = 'text-xs text-slate-300'; msgEl.textContent = 'Creating bucket...'; msgEl.classList.remove('hidden'); }

	try {
		const resp = await fetch('modules/addons/cloudstorage/api/cloudbackup_create_bucket.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ bucket_name, username, versioning_enabled, retention_days })
		});
		const data = await resp.json();
		if (data.status === 'success' && data.bucket) {
			// Insert option and select it
			const opt = document.createElement('option');
			opt.value = data.bucket.id || '';
			opt.textContent = data.bucket.name || bucket_name;
			selectEl.appendChild(opt);
			selectEl.value = opt.value;
			try { selectEl.dispatchEvent(new Event('change')); } catch (e) {}
			try {
				const hidden = document.querySelector('input[name="dest_bucket_id"]');
				if (hidden) hidden.value = opt.value;
			} catch (e) {}

			// Success toast/message
			if (window.toast) window.toast.success(data.message || 'Bucket created');
			if (msgEl) { msgEl.className = 'text-xs text-emerald-300'; msgEl.textContent = data.message || 'Bucket created.'; }
		} else {
			const err = data.message || 'Failed to create bucket';
			if (window.toast) window.toast.error(err);
			if (msgEl) { msgEl.className = 'text-xs text-rose-300'; msgEl.textContent = err; }
		}
	} catch (e) {
		if (window.toast) window.toast.error('An error occurred while creating bucket');
		if (msgEl) { msgEl.className = 'text-xs text-rose-300'; msgEl.textContent = 'An error occurred while creating bucket'; }
	}
}

// Shared bucket name validation (mirrors buckets page)
function validateBucketName(bucketName) {
	// Regular expression for validating bucket name
	var isValid = /^(?!-)(?!.*--)(?!.*\.\.)(?!.*\.-)(?!.*-\.)[a-z0-9-.]*[a-z0-9]$/.test(bucketName) && !(/^\.|\.$/.test(bucketName));
	if (!isValid) {
		return {
			isValid: false,
			message: 'Bucket names can only contain lowercase letters, numbers, and hyphens, and must not start or end with a hyphen or period, or contain two consecutive periods or period-hyphen(-) or hyphen-period(-.).'
		};
	}
	if (bucketName.length < 3 || bucketName.length > 63) {
		return {
			isValid: false,
			message: 'Bucket names must be between 3 and 63 characters long.'
		};
	}
	return { isValid: true };
}

// Cascade-in animation for job cards
document.addEventListener('DOMContentLoaded', () => {
	try {
		const cards = document.querySelectorAll('[data-job-card]');
		cards.forEach((card, i) => {
			card.style.transition = 'transform .35s ease, opacity .35s ease';
			card.style.transitionDelay = (i * 40) + 'ms';
			card.style.opacity = '0';
			card.style.transform = 'translateY(8px)';
		});
		requestAnimationFrame(() => {
			cards.forEach((card) => {
				card.style.opacity = '1';
				card.style.transform = 'translateY(0)';
			});
		});

		// Prefill Destination Prefix from Source Display Name (slug) if user hasn't typed
		try {
			const srcName = document.querySelector('input[name="source_display_name"]');
			const destPrefix = document.querySelector('input[name="dest_prefix"]');
			let userTouchedPrefix = false;
			if (destPrefix) {
				destPrefix.addEventListener('input', () => { userTouchedPrefix = true; }, { passive: true });
			}
			if (srcName && destPrefix) {
				const slug = (v) => (v || '').toString().trim().toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'') + '/';
				srcName.addEventListener('input', () => {
					if (!userTouchedPrefix) {
						destPrefix.value = slug(srcName.value || 'backup');
					}
				}, { passive: true });
			}
		} catch (e) {}
	} catch (e) {}
});
{/literal}
</script>


<!-- Google Drive Folder Picker Modal -->
<div id="gdrivePickerModal" x-data="gdrivePicker()" x-cloak x-show="open" x-init="init && init()" class="fixed inset-0 z-[1000]" style="display:none;">
	<!-- Backdrop -->
	<div class="absolute inset-0 bg-black/75" @click="close()"></div>
	<!-- Slide-over panel -->
	<div class="fixed right-0 top-0 h-full w-full sm:w-[520px] bg-slate-900 border-l border-slate-700 shadow-xl flex flex-col">
		<!-- Header -->
		<div class="px-4 py-3 border-b border-slate-700 flex items-center justify-between">
			<div class="flex items-center gap-2">
				<h3 class="text-slate-100 font-medium">Browse Google Drive</h3>
				<span class="text-[11px] text-slate-400" x-text="target==='edit' ? '(Edit job)' : '(New job)'"></span>
			</div>
			<button type="button" class="text-slate-300 hover:text-white" @click="close()">&times;</button>
		</div>
		<!-- Tabs -->
		<div class="px-4 pt-3">
			<nav class="flex items-center gap-2">
				<button type="button" class="px-3 py-1.5 rounded-md text-sm"
						:class="tab==='my' ? 'bg-slate-800 text-slate-100' : 'text-slate-300 hover:text-white'"
						@click="switchTab('my')">
					My Drive
				</button>
				<button type="button" class="px-3 py-1.5 rounded-md text-sm"
						:class="tab==='shared' ? 'bg-slate-800 text-slate-100' : 'text-slate-300 hover:text-white'"
						@click="switchTab('shared')">
					Shared Drives
				</button>
			</nav>
		</div>
		<!-- Search -->
		<div class="px-4 pt-3 flex items-center gap-2">
			<input type="text" x-model="search" placeholder="Search current folder…"
				   class="w-full bg-slate-800 text-slate-200 border border-slate-700 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-sky-500" />
			<button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500"
					@click="reload()">
				Search
			</button>
		</div>
		<!-- Body -->
		<div class="px-2 py-2 overflow-y-auto grow scrollbar_thin">
			<!-- Shared Drives list -->
			<div x-show="tab==='shared' && !selectedDrive" class="px-2" style="display:none;">
				<div class="text-xs text-slate-400 mb-2">Select a shared drive</div>
				<template x-for="d in drives" :key="d.id">
					<div class="flex items-center justify-between px-3 py-2 rounded hover:bg-slate-800 cursor-pointer"
						 @click="chooseDrive(d)">
                        <div class="text-slate-200" x-text="d.name"></div>
						<div class="text-[11px] text-slate-500" x-text="d.id"></div>
					</div>
				</template>
				<div class="py-2" x-show="drivesNext">
					<button type="button" class="text-sky-400 hover:text-sky-300 text-sm" @click="loadDrives(drivesNext)">Load more…</button>
				</div>
				<div class="py-4 text-[12px] text-slate-500" x-show="!loading && drives.length===0">No shared drives found.</div>
			</div>

            <!-- Folder tree (My Drive or selected Shared Drive) -->
			<div x-show="(tab==='my') || (tab==='shared' && selectedDrive)" class="px-1" style="display:none;">
				<div class="text-xs text-slate-400 mb-2" x-text="tab==='my' ? 'My Drive' : ('Drive: ' + (selectedDrive?.name || ''))"></div>
                <!-- Root selection option -->
                <div class="flex items-center justify-between px-2 py-1.5 rounded hover:bg-slate-800">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="gdpick" class="accent-sky-500 focus:ring-sky-500 rounded-sm"
                               :checked="selected && selected.id==='root' && ((tab==='my' && !selected.driveId) || (tab==='shared' && selected.driveId=== (selectedDrive?.id || null)))"
                               @change="selected = { id: 'root', name: (tab==='my' ? 'All of My Drive' : ('All of ' + (selectedDrive?.name || 'Drive'))), driveId: (tab==='shared' ? (selectedDrive?.id || null) : null) }" />
                        <span class="text-slate-200" x-text="tab==='my' ? 'All of My Drive (root)' : ('All of ' + (selectedDrive?.name || 'Drive') + ' (root)')"></span>
                    </label>
				<div>
					<span class="text-[10px] font-mono bg-slate-800/70 text-slate-300 border border-slate-700 rounded-full px-2 py-0.5"
						  x-text="tab==='my' ? 'root' : (selectedDrive?.id || '')"></span>
				</div>
                </div>
				<!-- Root node listing -->
				<div>
					<template x-for="(n, idx) in nodes" :key="n.id + ':' + idx">
						<div>
						<div class="flex items-center justify-between px-2 py-1.5 rounded hover:bg-slate-800/70 transition-colors"
							 @click="(!n.mimeType || n.mimeType==='application/vnd.google-apps.folder') ? toggleExpand(n) : null">
                            <div class="flex items-center gap-2">
                                <button type="button" class="w-6 text-slate-300 hover:text-white"
                                        :class="(n.mimeType && n.mimeType!=='application/vnd.google-apps.folder') ? 'opacity-40 cursor-not-allowed' : ''"
                                        @click="(n.mimeType && n.mimeType!=='application/vnd.google-apps.folder') ? null : toggleExpand(n)">
									<span x-show="n.expanded">▾</span>
									<span x-show="!n.expanded">▸</span>
								</button>
								<label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="gdpick" @click.stop class="accent-sky-500 focus:ring-sky-500 rounded-sm" :value="n.id" :checked="selected && selected.id===n.id"
										   @change="selected=n" />
									<!-- Icon -->
									<span class="text-slate-400" aria-hidden="true">
										<template x-if="n.mimeType && n.mimeType!=='application/vnd.google-apps.folder'">
											<!-- file icon -->
											<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a2 2 0 012-2h5l5 5v10a2 2 0 01-2 2H6a2 2 0 01-2-2V3z"/><path d="M13 3v4h4"/></svg>
										</template>
										<template x-if="!n.mimeType || n.mimeType==='application/vnd.google-apps.folder'">
											<!-- folder icon -->
											<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
										</template>
									</span>
									<span class="text-slate-200" x-text="n.name"></span>
								</label>
							</div>
						<div>
							<span class="text-[10px] font-mono bg-slate-800/70 text-slate-300 border border-slate-700 rounded-full px-2 py-0.5" x-text="n.id"></span>
						</div>
						</div>
						<!-- children -->
						<div class="ml-8 border-l border-slate-700/50 pl-3" x-show="n.expanded">
							<template x-for="(c, cidx) in (n.children || [])" :key="c.id + ':' + cidx">
								<div>
								<div class="flex items-center justify-between px-2 py-1 rounded hover:bg-slate-800/70 transition-colors"
									 @click="(!c.mimeType || c.mimeType==='application/vnd.google-apps.folder') ? toggleExpand(c) : null">
                                    <div class="flex items-center gap-2">
                                        <button type="button" class="w-6 text-slate-300 hover:text-white"
                                                :class="(c.mimeType && c.mimeType!=='application/vnd.google-apps.folder') ? 'opacity-40 cursor-not-allowed' : ''"
                                                @click="(c.mimeType && c.mimeType!=='application/vnd.google-apps.folder') ? null : toggleExpand(c)">
											<span x-show="c.expanded">▾</span>
											<span x-show="!c.expanded">▸</span>
										</button>
										<label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" name="gdpick" @click.stop class="accent-sky-500 focus:ring-sky-500 rounded-sm" :value="c.id" :checked="selected && selected.id===c.id"
												   @change="selected=c" />
											<!-- Icon -->
											<span class="text-slate-400" aria-hidden="true">
												<template x-if="c.mimeType && c.mimeType!=='application/vnd.google-apps.folder'">
													<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a2 2 0 012-2h5l5 5v10a2 2 0 01-2 2H6a2 2 0 01-2-2V3z"/><path d="M13 3v4h4"/></svg>
												</template>
												<template x-if="!c.mimeType || c.mimeType==='application/vnd.google-apps.folder'">
													<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
												</template>
											</span>
											<span class="text-slate-200" x-text="c.name"></span>
										</label>
									</div>
									<div>
										<span class="text-[10px] font-mono bg-slate-800/70 text-slate-300 border border-slate-700 rounded-full px-2 py-0.5" x-text="c.id"></span>
									</div>
								</div>
								<!-- grandchildren -->
								<div class="ml-8 border-l border-slate-700/50 pl-3" x-show="c.expanded">
									<template x-for="(g, gidx) in (c.children || [])" :key="g.id + ':' + gidx">
										<div class="flex items-center justify-between px-2 py-1 rounded hover:bg-slate-800/70 transition-colors">
											<div class="flex items-center gap-2">
												<label class="inline-flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" name="gdpick" class="accent-sky-500 focus:ring-sky-500 rounded-sm" :value="g.id" :checked="selected && selected.id===g.id"
														   @change="selected=g" />
													<!-- Icon -->
													<span class="text-slate-400" aria-hidden="true">
														<template x-if="g.mimeType && g.mimeType!=='application/vnd.google-apps.folder'">
															<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M4 3a2 2 0 012-2h5l5 5v10a2 2 0 01-2 2H6a2 2 0 01-2-2V3z"/><path d="M13 3v4h4"/></svg>
														</template>
														<template x-if="!g.mimeType || g.mimeType==='application/vnd.google-apps.folder'">
															<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>
														</template>
													</span>
													<span class="text-slate-200" x-text="g.name"></span>
												</label>
											</div>
											<div>
												<span class="text-[10px] font-mono bg-slate-800/70 text-slate-300 border border-slate-700 rounded-full px-2 py-0.5" x-text="g.id"></span>
											</div>
										</div>
									</template>
									<!-- Load more for nested folder -->
									<div class="py-1" x-show="c.nextPageToken">
										<button type="button" class="text-sky-400 hover:text-sky-300 text-sm" @click="loadMore(c)">Load more…</button>
									</div>
								</div>
								</div>
							</template>
							<div class="py-1" x-show="n.nextPageToken">
								<button type="button" class="text-sky-400 hover:text-sky-300 text-sm" @click="loadMore(n)">Load more…</button>
							</div>
						</div>
						</div>
					</template>
					<div class="py-4 text-[12px] text-slate-500" x-show="!loading && nodes.length===0">No folders found.</div>
				</div>
			</div>
		</div>
		<!-- Footer -->
		<div class="px-4 py-3 border-t border-slate-700 flex items-center justify-between">
			<div class="text-[12px] text-slate-400">
				<span x-show="selected">
					Selected: <span class="text-slate-200" x-text="selected?.name || ''"></span>
					<span class="text-slate-500 ml-2" x-text="selected?.id || ''"></span>
				</span>
			</div>
			<div class="flex items-center gap-2">
				<button type="button" class="px-3 py-2 rounded-md border border-slate-600 text-slate-300 hover:text-white hover:border-slate-500" @click="close()">Cancel</button>
				<button type="button" class="px-3 py-2 rounded-md bg-sky-600 text-white hover:bg-sky-700 disabled:opacity-50" :disabled="!selected" @click="apply()">Confirm</button>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
// Drive picker Alpine component + opener
function gdrivePicker() {
	return {
		init() {
			// Auto-refresh on search input with debounce
			try {
				let _deb = null;
				if (typeof this.$watch === 'function') {
					this.$watch('search', (v) => {
						if (_deb) clearTimeout(_deb);
						_deb = setTimeout(() => { this.reload(); }, 350);
					});
					// Auto-load when panel opens
					this.$watch('open', (v) => {
						if (v) {
							// Ensure initial load triggers even if openWith is bypassed
							this.reload();
						}
					});
				}
			} catch (e) {}
		},
		open: false,
		target: 'create', // 'create' | 'edit'
		connId: '',
		tab: 'my', // 'my' | 'shared'
		search: '',
		nodes: [],
		drives: [],
		drivesNext: null,
		selectedDrive: null,
		selected: null,
		loading: false,
		switchTab(t) {
			if (this.tab === t) return;
			this.tab = t;
			this.search = '';
			this.selected = null;
			if (t === 'my') {
				this.loadRoot();
			} else {
				this.selectedDrive = null;
				this.loadDrives();
			}
		},
		openWith(target) {
			this.target = target || 'create';
			// resolve connection id
			if (this.target === 'edit') {
				const scoped = document.querySelector('#edit_gdrive_fields input[name="source_connection_id"]');
				this.connId = (scoped ? scoped.value : (document.getElementById('edit_source_connection_id')?.value || '')).trim();
			} else {
				const scoped = document.querySelector('#gdriveFields input[name=\"source_connection_id\"]');
				const fallback = document.querySelector('input[name=\"source_connection_id\"]');
				this.connId = (scoped ? scoped.value : (fallback ? fallback.value : '')).trim();
			}
			// Allow opener to pass a connection id via data- attribute for reliability
			try {
				if (!this.connId) {
					const modal = document.getElementById('gdrivePickerModal');
					const ds = (modal && modal.getAttribute('data-conn-id')) ? modal.getAttribute('data-conn-id') : '';
					if (ds) this.connId = ds;
				}
			} catch (e) {}
			// Proceed even if no explicit connection id is set; server will fall back to latest active
			if (!this.connId) { try { if (window.toast) window.toast.info('Using your latest Google Drive connection'); } catch(e) {} }
			this.open = true;
			this.tab = 'my';
			this.search = '';
			this.selected = null;
			this.nodes = [];
			this.drives = [];
			this.drivesNext = null;
			this.selectedDrive = null;
			this.loading = true;
			// Ensure initial load fires reliably after reactive updates
			try {
				if (typeof this.$nextTick === 'function') {
					this.$nextTick(() => { this.reload(); });
				} else {
					setTimeout(() => { this.reload(); }, 0);
				}
			} catch (e) {
				// Fallback to direct load
				this.reload();
			}
			// Extra safety nudges in case the first call races Alpine render
			try { setTimeout(() => { this.reload(); }, 150); } catch (e) {}
			try { setTimeout(() => { this.reload(); }, 350); } catch (e) {}
		},
		close() {
			this.open = false;
			// Ensure forced fallback visibility is undone as well
			try {
				const modal = document.getElementById('gdrivePickerModal');
				if (modal) {
					modal.style.setProperty('display', 'none', 'important');
					const backdrop = modal.querySelector('.absolute.inset-0.bg-black.bg-opacity-60');
					if (backdrop) backdrop.style.setProperty('display', 'none', 'important');
				}
			} catch (e) {}
		},
		reload() {
			if (this.tab === 'shared' && this.selectedDrive) {
				this.loadRoot(this.selectedDrive);
			} else {
				this.loadRoot();
			}
		},
		apiUrl(mode, params) {
			const usp = new URLSearchParams(params || {});
			usp.set('mode', mode);
			usp.set('source_connection_id', this.connId);
			usp.set('includeFiles', '1');
			return 'modules/addons/cloudstorage/api/cloudbackup_gdrive_list.php?' + usp.toString();
		},
		async loadDrives(pageToken) {
			this.loading = true;
			try {
				const url = this.apiUrl('drives', { pageToken: pageToken || '', pageSize: 100 });
				const resp = await fetch(url);
				const data = await resp.json();
				if (data.status === 'success') {
					if (pageToken) this.drives.push(...(data.items || []));
					else this.drives = (data.items || []);
					this.drivesNext = data.nextPageToken || null;
				}
			} catch (e) {} finally { this.loading = false; }
		},
		async loadRoot(drive) {
			this.loading = true;
			try {
				const params = { parentId: 'root', pageSize: 100 };
				if (drive && drive.id) params.driveId = drive.id;
				if (this.search) params.q = this.search;
				const url = this.apiUrl('children', params);
				const resp = await fetch(url);
				const data = await resp.json();
				if (data.status === 'success') {
					this.nodes = (data.items || []).map(i => ({ ...i, expanded: false, children: [], nextPageToken: null }));
					// track next page token on a synthetic root holder
					this._rootNext = data.nextPageToken || null;
				} else {
					this.nodes = [];
				}
			} catch (e) { this.nodes = []; } finally { this.loading = false; }
		},
		async toggleExpand(node) {
			if (node.expanded && (node.children || []).length > 0) {
				node.expanded = false;
				return;
			}
			node.expanded = true;
			if ((node.children || []).length > 0) return;
			this.loading = true;
			try {
				const params = { parentId: node.id, pageSize: 100 };
				if (node.driveId) params.driveId = node.driveId;
				if (this.search) params.q = this.search;
				const url = this.apiUrl('children', params);
				const resp = await fetch(url);
				const data = await resp.json();
				if (data.status === 'success') {
					const newItems = (data.items || []).map(i => ({ ...i, expanded: false, children: [], nextPageToken: null }));
					if (!node.children) node.children = [];
					node.children.splice(0, node.children.length, ...newItems);
					node.nextPageToken = data.nextPageToken || null;
				} else {
					node.children = [];
				}
			} catch (e) { node.children = []; } finally { this.loading = false; }
		},
		async loadMore(node) {
			if (!node.nextPageToken) return;
			this.loading = true;
			try {
				const params = { parentId: node.id, pageSize: 100, pageToken: node.nextPageToken };
				if (node.driveId) params.driveId = node.driveId;
				if (this.search) params.q = this.search;
				const url = this.apiUrl('children', params);
				const resp = await fetch(url);
				const data = await resp.json();
				if (data.status === 'success') {
					const more = (data.items || []).map(i => ({ ...i, expanded: false, children: [], nextPageToken: null }));
					if (!node.children) node.children = [];
					node.children.push(...more);
					node.nextPageToken = data.nextPageToken || null;
				}
			} catch (e) {} finally { this.loading = false; }
		},
		chooseDrive(d) {
			this.selectedDrive = d;
			this.loadRoot(d);
		},
		apply() {
			if (!this.selected) return;
			const id = this.selected.id || '';
			const isFolder = (this.selected.mimeType === 'application/vnd.google-apps.folder') || (id === 'root');
			const isRoot = (id === 'root');
			const selType = isRoot ? 'drive_root' : (isFolder ? 'folder' : 'file');
			if (this.target === 'edit') {
				const rootEl = document.getElementById('edit_gdrive_root_folder_id');
				if (rootEl) rootEl.value = (isRoot ? '' : (isFolder ? id : ''));
				const teamEl = document.getElementById('edit_gdrive_team_drive');
				if (teamEl) teamEl.value = (this.selected.driveId || '');
				const pathEl = document.getElementById('edit_gdrive_path');
				if (pathEl) pathEl.value = '';
				// Persist selection id/type (edit form)
				try {
					const selIdElEdit = document.querySelector('#edit_gdrive_fields input[name="gdrive_selected_id"]');
					if (selIdElEdit) selIdElEdit.value = id;
					const selTypeElEdit = document.querySelector('#edit_gdrive_fields input[name="gdrive_selected_type"]');
					if (selTypeElEdit) selTypeElEdit.value = selType;
				} catch (e) {}
			} else {
				const rootEl2 = document.querySelector('input[name=\"gdrive_root_folder_id\"]');
				if (rootEl2) rootEl2.value = (isRoot ? '' : (isFolder ? id : ''));
				const teamEl2 = document.querySelector('input[name=\"gdrive_team_drive\"]');
				if (teamEl2) teamEl2.value = (this.selected.driveId || '');
				// Persist selection id/type (create form)
				const selIdEl = document.querySelector('input[name=\"gdrive_selected_id\"]');
				if (selIdEl) selIdEl.value = id;
				const selTypeEl = document.querySelector('input[name=\"gdrive_selected_type\"]');
				if (selTypeEl) selTypeEl.value = selType;
			}
			try { if (window.toast) window.toast.success('Drive selection applied'); } catch (e) {}
			this.close();
		}
	};
}
// Global opener
window.openDrivePicker = function(target) {
	try {
		const modal = document.getElementById('gdrivePickerModal');
		if (!modal) return;
		// Pre-check a selected Drive connection so users get feedback if missing
			let hasConn = false;
		let connVal = '';
		if ((target || 'create') === 'edit') {
			const scoped = document.querySelector('#edit_gdrive_fields input[name="source_connection_id"]');
			const v = (scoped ? scoped.value : (document.getElementById('edit_source_connection_id')?.value || '')).trim();
			connVal = v;
			hasConn = !!connVal;
		} else {
			const scoped = document.querySelector('#gdriveFields input[name="source_connection_id"]');
			const fallback = document.querySelector('input[name="source_connection_id"]');
			const v2 = (scoped ? scoped.value : (fallback ? fallback.value : '')).trim();
			connVal = v2;
			hasConn = !!connVal;
		}
			// If no explicit connection picked, proceed anyway and let server fall back to latest
			if (!hasConn && window.toast && typeof window.toast.info === 'function') {
				window.toast.info('Using your latest Google Drive connection');
			}
		// Stash conn id on modal so the component can pick it up reliably
			try { if (connVal) modal.setAttribute('data-conn-id', connVal); } catch(e) {}
		// Ensure Alpine has initialized this component; if not, attempt to initialize on demand
				if (!modal.__x) {
			if (window.Alpine && typeof Alpine.initTree === 'function') {
				try { Alpine.initTree(modal); } catch(e) { console.warn('Drive picker initTree failed', e); }
			}
		}
		// If Alpine is available and component initialized, use component API
		if (modal.__x && modal.__x.$data && typeof modal.__x.$data.openWith === 'function') {
			modal.__x.$data.openWith(target || 'create');
			// Nudge a reload shortly after open to guarantee first render has data
			try { setTimeout(() => { if (modal.__x && modal.__x.$data && typeof modal.__x.$data.reload === 'function') modal.__x.$data.reload(); }, 80); } catch(e) {}
			// Prime with a direct fetch as a backstop so XHR happens immediately
			try {
				const base = 'modules/addons/cloudstorage/api/cloudbackup_gdrive_list.php';
				const usp = new URLSearchParams({ mode: 'children', parentId: 'root', pageSize: '100', includeFiles: '1' });
				if (connVal) usp.set('source_connection_id', connVal);
				fetch(base + '?' + usp.toString())
					.then(r => r.json())
					.then(d => {
						if ((d.status || '') === 'success' && modal.__x && modal.__x.$data) {
							const items = (d.items || []).map(i => Object.assign({ expanded:false, children:[], nextPageToken:null }, i));
							modal.__x.$data.nodes = items;
							modal.__x.$data.loading = false;
							modal.__x.$data._rootNext = d.nextPageToken || null;
						}
					})
					.catch(() => {});
			} catch (e) {}
			return;
		}
		// Fallback: forcibly show the modal so the user gets feedback even if Alpine didn't bind
		try {
			modal.style.setProperty('display', 'block', 'important');
			const backdrop = modal.querySelector('.absolute.inset-0.bg-black.bg-opacity-60');
			if (backdrop) backdrop.style.setProperty('display', 'block', 'important');
		} catch (e) {
			console.warn('Drive picker fallback display failed', e);
		}
		// Retry initialization shortly after showing (to catch late Alpine init) and auto-open
		try {
			setTimeout(() => {
				if (!modal.__x && window.Alpine && typeof Alpine.initTree === 'function') {
					try { Alpine.initTree(modal); } catch(e) {}
				}
				if (modal.__x && modal.__x.$data && typeof modal.__x.$data.openWith === 'function') {
					modal.__x.$data.openWith(target || 'create');
					try { setTimeout(() => { if (modal.__x && modal.__x.$data && typeof modal.__x.$data.reload === 'function') modal.__x.$data.reload(); }, 80); } catch(e) {}
					// Prime with a direct fetch as a backstop so XHR happens immediately
					try {
						const base = 'modules/addons/cloudstorage/api/cloudbackup_gdrive_list.php';
						const usp = new URLSearchParams({ mode: 'children', parentId: 'root', pageSize: '100', includeFiles: '1' });
						if (connVal) usp.set('source_connection_id', connVal);
						fetch(base + '?' + usp.toString())
							.then(r => r.json())
							.then(d => {
								if ((d.status || '') === 'success' && modal.__x && modal.__x.$data) {
									const items = (d.items || []).map(i => Object.assign({ expanded:false, children:[], nextPageToken:null }, i));
									modal.__x.$data.nodes = items;
									modal.__x.$data.loading = false;
									modal.__x.$data._rootNext = d.nextPageToken || null;
								}
							})
							.catch(() => {});
					} catch (e) {}
				}
			}, 120);
		} catch (e) {}
	} catch (e) {}
};
</script>

