<!-- Navigation Tabs (pill style) -->
<div class="mb-6">
    <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="Cloud Backup Navigation">
        <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_jobs"
           class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_jobs' || empty($smarty.get.view)}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
            Jobs
        </a>
        <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_runs{if isset($job) && $job.id}&job_id={$job.id}{/if}"
           class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_runs' || $smarty.get.view == 'cloudbackup_live'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
            Run History
        </a>
        <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudnas"
           class="px-4 py-1.5 rounded-full transition flex items-center gap-1.5 {if $smarty.get.view == 'cloudnas'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 0 0-.12-1.03l-2.268-9.64a3.375 3.375 0 0 0-3.285-2.602H7.923a3.375 3.375 0 0 0-3.285 2.602l-2.268 9.64a4.5 4.5 0 0 0-.12 1.03v.228m19.5 0a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3m19.5 0a3 3 0 0 0-3-3H5.25a3 3 0 0 0-3 3m16.5 0h.008v.008h-.008v-.008Zm-3 0h.008v.008h-.008v-.008Z" />
            </svg>
            Cloud NAS
        </a>
        <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_settings"
           class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_settings'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
            Settings
        </a>
        <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_agents"
           class="px-4 py-1.5 rounded-full transition {if $smarty.get.view == 'cloudbackup_agents'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
            Agents
        </a>
    </nav>
</div>

