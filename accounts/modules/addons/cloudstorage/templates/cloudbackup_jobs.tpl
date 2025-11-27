<div class="min-h-screen bg-slate-950 text-gray-300">
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="container mx-auto px-4 pb-10 pt-6 relative pointer-events-relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
        <!-- Navigation Tabs (pill style) -->
        <div class="mb-6">
            <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="Cloud Backup Navigation">
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_jobs' || empty($smarty.get.view)}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Jobs
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_runs'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Run History
                </a>
                <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_settings"
                   class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_settings'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
                    Settings
                </a>
            </nav>
        </div>
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
            <button
                type="button"
                onclick="openCreateJobModal()"
                class="btn-run-now"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6" />
                </svg>
                <span>Create Job</span>
            </button>
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
                <div x-show="selected === 'google_drive'" x-cloak class="mt-2">
                    <div class="text-sm font-semibold text-slate-200">Google Drive (read-only backup)</div>
                    <p class="mt-1 text-xs text-slate-400">
                        We use read-only access to list and copy your selected Google Drive files into your chosen e3 bucket.
                        We do not modify or delete anything in Google Drive.
                    </p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-col sm:flex-row gap-3 justify-between items-start sm:items-center">
            <input type="text" placeholder="Search jobs" class="w-full sm:w-80 rounded-full bg-slate-900/70 border border-slate-700 px-4 py-2 text-sm text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
                   x-model="$store.jobFilters.q" @input="$dispatch('jobs-filter-apply')">
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
                                    onclick="editJob({$job.id})"
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
                            </div>
                        </div>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-6 gap-4 text-xs text-slate-400">
                            <div>
                                <h6 class="text-sm font-medium text-slate-400">Source</h6>
                                <span class="text-md font-medium text-slate-300">{$job.source_display_name}</span>
                                <span class="text-xs text-slate-500 ml-2">({$job.source_type})</span>
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
                    { value: 'dropbox', label: 'Dropbox' }
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
            <!-- Inline Bucket Creation -->
            <div class="mb-4" x-data="{ open:false, creating:false }">
                <button type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-700 px-3 py-1.5 text-xs text-slate-300 hover:text-white hover:border-slate-500 transition"
                        @click="open = !open">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v12m6-6H6" />
                    </svg>
                    <span x-text="open ? `Hide create bucket` : `Don't have a bucket? Create one`"></span>
                </button>
                <div class="mt-3 rounded-lg border border-slate-700 bg-slate-900/50 p-3 space-y-3" x-show="open" x-cloak>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Bucket Name</label>
                        <input type="text" id="inline_bucket_name" class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:border-sky-600" placeholder="e.g., backups-company-prod">
                        <p class="mt-1 text-[11px] text-slate-400">Lowercase letters, numbers, and hyphens only.</p>
                    </div>
                    <!-- Tenant selection combobox (Alpine) -->
                    <div
                        x-data="{
                            isOpen: false,
                            selectedUsername: '',
                            searchTerm: '',
                            usernames: [
                                {foreach from=$usernames item=username name=userloop}
                                    '{$username|escape:'javascript'}'{if !$smarty.foreach.userloop.last},{/if}
                                {/foreach}
                            ],
                            get filteredUsernames() {
                                if (this.searchTerm === '') return this.usernames;
                                return this.usernames.filter(u => u.toLowerCase().includes(this.searchTerm.toLowerCase()));
                            }
                        }"
                        @click.away="isOpen = false"
                    >
                        <label for="inline_username" class="block text-xs font-medium text-slate-300 mb-1">Select Tenant</label>
                        <input type="hidden" id="inline_username" x-model="selectedUsername">
                        <div class="relative">
                            <button @click="isOpen = !isOpen" type="button" class="relative w-full px-3 py-2 text-left text-slate-300 bg-slate-900 border border-gray-600 rounded-md shadow-sm cursor-default focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                                <span class="block truncate" x-text="selectedUsername || 'Select a tenant'"></span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                            <div x-show="isOpen"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="absolute z-10 w-full mt-1 bg-slate-900 border border-gray-600 rounded-md shadow-lg"
                                 style="display: none;">
                                <div class="p-2">
                                    <input type="text" x-model="searchTerm" placeholder="Search tenants..." class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-md text-gray-300 focus:outline-none focus:ring-sky-500 focus:border-sky-500">
                                </div>
                                <ul class="py-1 overflow-auto text-base max-h-60 ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm scrollbar_thin" role="listbox">
                                    <template x-if="filteredUsernames.length === 0">
                                        <li class="px-4 py-2 text-gray-400">No tenants found.</li>
                                    </template>
                                    <template x-for="u in filteredUsernames" :key="u">
                                        <li @click="selectedUsername = u; isOpen = false"
                                            class="px-4 py-2 text-gray-300 cursor-pointer select-none hover:bg-gray-700"
                                            x-text="u">
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" id="inline_bucket_versioning" class="w-4 h-4 text-sky-600 bg-gray-700 border-gray-600 rounded focus:ring-sky-500 focus:ring-2">
                            <span class="text-sm text-slate-300">Enable versioning</span>
                        </label>
                        <div class="mt-2" x-data="{ v:false }" x-init="$watch(() => { const el = document.getElementById('inline_bucket_versioning'); return el ? !!el.checked : false; }, val => v = !!val)">
                            <div x-show="v" x-cloak>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Keep previous versions for (days)</label>
                                <input type="number" min="1" value="30" id="inline_bucket_retention_days" class="w-40 bg-gray-700 text-gray-300 border border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:border-sky-600">
                                <p class="mt-1 text-[11px] text-amber-300/90">Each stored version increases usage and may impact monthly billing.</p>
                            </div>
                        </div>
                    </div>
                    <div id="inlineCreateBucketMsg" class="hidden text-xs"></div>
                    <div class="flex justify-end">
                        <button type="button" class="btn-run-now"
                                :disabled="creating"
                                @click="creating=true; createBucketInline().finally(() => creating=false)">
                            <span x-show="!creating">Create bucket</span>
                            <span x-show="creating">Creating…</span>
                        </button>
                    </div>
                </div>
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

<script>
{literal}
function openCreateJobModal() {
    const panel = document.getElementById('createJobSlideover');
    if (!panel) return;

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
    } else if (this.value === 'aws') {
        s3Fields.classList.add('hidden');
        setGroupEnabled(s3Fields, false);
        awsFields.classList.remove('hidden');
        setGroupEnabled(awsFields, true);
        sftpFields.classList.add('hidden');
        setGroupEnabled(sftpFields, false);
        gdriveFields.classList.add('hidden'); setGroupEnabled(gdriveFields, false);
        dropboxFields.classList.add('hidden'); setGroupEnabled(dropboxFields, false);
    } else if (this.value === 'google_drive') {
        s3Fields.classList.add('hidden'); setGroupEnabled(s3Fields, false);
        awsFields.classList.add('hidden'); setGroupEnabled(awsFields, false);
        sftpFields.classList.add('hidden'); setGroupEnabled(sftpFields, false);
        gdriveFields.classList.remove('hidden'); setGroupEnabled(gdriveFields, true);
        dropboxFields.classList.add('hidden'); setGroupEnabled(dropboxFields, false);
    } else if (this.value === 'dropbox') {
        s3Fields.classList.add('hidden'); setGroupEnabled(s3Fields, false);
        awsFields.classList.add('hidden'); setGroupEnabled(awsFields, false);
        sftpFields.classList.add('hidden'); setGroupEnabled(sftpFields, false);
        gdriveFields.classList.add('hidden'); setGroupEnabled(gdriveFields, false);
        dropboxFields.classList.remove('hidden'); setGroupEnabled(dropboxFields, true);
    } else {
        s3Fields.classList.add('hidden');
        setGroupEnabled(s3Fields, false);
        awsFields.classList.add('hidden');
        setGroupEnabled(awsFields, false);
        sftpFields.classList.remove('hidden');
        setGroupEnabled(sftpFields, true);
        gdriveFields.classList.add('hidden'); setGroupEnabled(gdriveFields, false);
        dropboxFields.classList.add('hidden'); setGroupEnabled(dropboxFields, false);
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
    // Include Google Drive connection id when applicable
    if (sourceType === 'google_drive') {
        const connId = formData.get('source_connection_id') || '';
        if (connId) {
            jobData.source_connection_id = connId;
        }
    }
    fetch('modules/addons/cloudstorage/api/cloudbackup_create_job.php', {
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
    
    fetch('modules/addons/cloudstorage/api/cloudbackup_create_job.php', {
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
                    window.toast.success('Backup job created successfully');
                }
            } catch (e) {}
            try { closeCreateSlideover(); } catch (e) {}
            setTimeout(() => { location.reload(); }, 1200);
        } else {
            const msg = data.message || 'Failed to create job';
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
            window.location.href = 'index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_live&run_id=' + data.run_id;
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

function editJob(jobId) {
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
}

function onEditSourceTypeChange() {
    const t = document.getElementById('edit_source_type').value;
    const s3 = document.getElementById('edit_s3_fields');
    const aws = document.getElementById('edit_aws_fields');
    const sftp = document.getElementById('edit_sftp_fields');
    const gdr = document.getElementById('edit_gdrive_fields');
    const drp = document.getElementById('edit_dropbox_fields');
    const setEnabled = (el, on) => { if (!el) return; el.querySelectorAll('input,select,textarea,button').forEach(e => on ? e.removeAttribute('disabled') : e.setAttribute('disabled','disabled')); };
    if (t === 's3_compatible') { s3.classList.remove('hidden'); setEnabled(s3,true); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.add('hidden'); setEnabled(sftp,false); gdr.classList.add('hidden'); setEnabled(gdr,false); drp.classList.add('hidden'); setEnabled(drp,false); }
    else if (t === 'aws') { s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.remove('hidden'); setEnabled(aws,true); sftp.classList.add('hidden'); setEnabled(sftp,false); gdr.classList.add('hidden'); setEnabled(gdr,false); drp.classList.add('hidden'); setEnabled(drp,false); }
    else if (t === 'sftp') { s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.remove('hidden'); setEnabled(sftp,true); gdr.classList.add('hidden'); setEnabled(gdr,false); drp.classList.add('hidden'); setEnabled(drp,false); }
    else if (t === 'google_drive') { s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.add('hidden'); setEnabled(sftp,false); gdr.classList.remove('hidden'); setEnabled(gdr,true); drp.classList.add('hidden'); setEnabled(drp,false); }
    else if (t === 'dropbox') { s3.classList.add('hidden'); setEnabled(s3,false); aws.classList.add('hidden'); setEnabled(aws,false); sftp.classList.add('hidden'); setEnabled(sftp,false); gdr.classList.add('hidden'); setEnabled(gdr,false); drp.classList.remove('hidden'); setEnabled(drp,true); }
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
			Alpine.store('jobFilters', { q: '', status: 'all' });
		}
	} catch (e) {}
});

function jobListFilter() {
	return {
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
					// Text search across name, job status, last run status, source, source type, and destination
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

// Inline bucket create helper for Create Job slide-over
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

