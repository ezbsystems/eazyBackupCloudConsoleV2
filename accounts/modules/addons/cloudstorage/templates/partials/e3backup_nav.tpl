<!-- e3 Cloud Backup Navigation with Download Flyout -->
<div class="mb-6" x-data="{ downloadFlyoutOpen: false }">
    <nav class="inline-flex rounded-full bg-slate-900/80 p-1 text-xs font-medium text-slate-400" aria-label="e3 Cloud Backup Navigation">
        <a href="index.php?m=cloudstorage&page=e3backup"
           class="px-4 py-1.5 rounded-full transition {if $activeNav == 'dashboard'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
            Dashboard
        </a>
        <a href="index.php?m=cloudstorage&page=e3backup&view=jobs"
           class="px-4 py-1.5 rounded-full transition {if $activeNav == 'jobs'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
            Jobs
        </a>
        <a href="index.php?m=cloudstorage&page=e3backup&view=hyperv"
           class="px-4 py-1.5 rounded-full transition {if $activeNav == 'hyperv'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
            Hyper-V
        </a>
        <a href="index.php?m=cloudstorage&page=e3backup&view=agents"
           class="px-4 py-1.5 rounded-full transition {if $activeNav == 'agents'}bg-slate-800 text-slate-50 shadow-sm{else}hover:text-slate-200{/if}">
            Agents
        </a>
        <button @click="downloadFlyoutOpen = true"
           class="px-4 py-1.5 rounded-full transition hover:text-slate-200 flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            Download
        </button>
    </nav>

    <!-- Download Flyout Backdrop -->
    <div x-show="downloadFlyoutOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="downloadFlyoutOpen = false"
         class="fixed inset-0 bg-black/50 z-40"
         style="display: none;"></div>

    <!-- Download Flyout Panel -->
    <div x-show="downloadFlyoutOpen"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         @keydown.escape.window="downloadFlyoutOpen = false"
         class="fixed top-0 left-0 h-screen w-80 bg-slate-900 shadow-2xl z-50 flex flex-col"
         style="display: none;">
        
        <!-- Flyout Header -->
        <div class="bg-slate-950 h-16 flex items-center justify-between px-4 py-3 border-b border-slate-700">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Download Agent
            </h2>
            <button @click="downloadFlyoutOpen = false" class="text-slate-400 hover:text-white focus:outline-none" aria-label="Close menu">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Flyout Content -->
        <div class="flex-1 overflow-y-auto p-6">
            <p class="text-sm text-slate-400 mb-6">Download the e3 Backup Agent for your operating system.</p>
            
            <div class="space-y-3">
                <!-- Windows Download Button -->
                <a href="/client_installer/e3-backup-agent-setup.exe" 
                   target="_blank" 
                   rel="noopener"
                   @click="downloadFlyoutOpen = false"
                   class="flex items-center justify-center gap-3 w-full bg-orange-600 hover:bg-orange-500 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                    <i class="fa-brands fa-windows text-lg"></i>
                    <span>Windows</span>
                </a>

                <!-- Linux Download Button -->
                <a href="/client_installer/e3-backup-agent-linux" 
                   target="_blank" 
                   rel="noopener"
                   @click="downloadFlyoutOpen = false"
                   class="flex items-center justify-center gap-3 w-full bg-orange-600 hover:bg-orange-500 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                    <i class="fa-brands fa-linux text-lg"></i>
                    <span>Linux</span>
                </a>
            </div>

            <div class="mt-8 p-4 rounded-lg bg-slate-800/50 border border-slate-700">
                <p class="text-xs text-slate-400">
                    <strong class="text-slate-300">Need help?</strong><br>
                    After downloading, you'll need an <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="text-orange-400 hover:text-orange-300 underline">enrollment token</a> to register your agent.
                </p>
            </div>
        </div>
    </div>
</div>
