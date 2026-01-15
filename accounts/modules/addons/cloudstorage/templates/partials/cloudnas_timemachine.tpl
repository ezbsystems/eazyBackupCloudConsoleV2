{* Cloud NAS - Time Machine Tab *}

{* No backup jobs warning *}
<template x-if="backupJobs.length === 0">
    <div class="rounded-xl border border-slate-700 bg-slate-900/50 px-6 py-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-16 h-16 text-slate-600 mx-auto mb-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <h3 class="text-lg font-semibold text-slate-300 mb-2">No Backup Jobs Found</h3>
        <p class="text-sm text-slate-500 max-w-md mx-auto">
            Time Machine allows you to browse and mount point-in-time snapshots from your eazyBackup archive backups. 
            Create a backup job with the Archive engine to get started.
        </p>
        <a href="index.php?m=cloudstorage&page=e3backup&view=jobs" 
           class="inline-flex items-center gap-2 mt-6 px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-medium transition">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Create Backup Job
        </a>
    </div>
</template>

<template x-if="backupJobs.length > 0">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {* Left: Job/Snapshot Selector *}
        <div class="lg:col-span-1 space-y-4">
            {* Job Selector - Alpine Dropdown *}
            <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-4">
                <h3 class="text-sm font-semibold text-white mb-3 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
                    </svg>
                    Select Backup Job
                </h3>
                
                {* Custom dropdown *}
                <div x-data="{ jobDropdownOpen: false }" class="relative">
                    <button @click="jobDropdownOpen = !jobDropdownOpen"
                            @click.away="jobDropdownOpen = false"
                            type="button"
                            class="w-full flex items-center justify-between rounded-lg bg-slate-800 border border-slate-700 px-3 py-2.5 text-sm text-left hover:border-slate-600 transition">
                        <span x-text="selectedJobId ? backupJobs.find(j => j.id == selectedJobId)?.name || 'Select a job...' : 'Select a job...'" 
                              :class="selectedJobId ? 'text-white' : 'text-slate-400'"></span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" 
                             class="w-4 h-4 text-slate-400 transition-transform" :class="jobDropdownOpen ? 'rotate-180' : ''">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    
                    <div x-show="jobDropdownOpen" x-transition
                         class="absolute z-20 mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 shadow-xl max-h-60 overflow-y-auto">
                        <template x-for="job in backupJobs" :key="job.id">
                            <button @click="selectedJobId = job.id; loadSnapshots(); jobDropdownOpen = false"
                                    type="button"
                                    class="w-full px-3 py-2.5 text-sm text-left hover:bg-slate-700 transition flex items-center justify-between"
                                    :class="selectedJobId == job.id ? 'bg-cyan-600/20 text-cyan-300' : 'text-slate-200'">
                                <span x-text="job.name" class="truncate"></span>
                                <svg x-show="selectedJobId == job.id" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            
            {* Snapshot List *}
            <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-4">
                <h3 class="text-sm font-semibold text-white mb-3 flex items-center justify-between">
                    <span class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        Snapshots
                    </span>
                    <span x-show="snapshots.length > 0" class="text-xs text-slate-400 font-normal" x-text="snapshots.length + ' available'"></span>
                </h3>
                
                {* Empty state *}
                <template x-if="!selectedJobId">
                    <p class="text-sm text-slate-500 text-center py-8">Select a backup job to view snapshots</p>
                </template>
                
                <template x-if="selectedJobId && snapshots.length === 0">
                    <p class="text-sm text-slate-500 text-center py-8">No snapshots found for this job</p>
                </template>
                
                {* Snapshot list *}
                <div x-show="snapshots.length > 0" class="max-h-[400px] overflow-y-auto space-y-2 pr-1 -mr-1">
                    <template x-for="(snapshot, idx) in snapshots" :key="snapshot.manifest_id">
                        <button @click="selectSnapshot(snapshot)"
                                type="button"
                                class="w-full text-left p-3 rounded-lg border transition"
                                :class="selectedSnapshot?.manifest_id === snapshot.manifest_id 
                                    ? 'border-cyan-500 bg-cyan-500/10' 
                                    : 'border-slate-700 bg-slate-800/50 hover:border-slate-600'">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-white font-medium" x-text="formatDate(snapshot.created_at)"></span>
                                <span class="text-xs text-slate-400 bg-slate-700/50 px-2 py-0.5 rounded-full" x-text="snapshot.size_human || '—'"></span>
                            </div>
                            <div class="flex items-center gap-3 mt-2 text-xs text-slate-500">
                                <span class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                    <span x-text="(snapshot.file_count || 0).toLocaleString()"></span>
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                                    </svg>
                                    <span x-text="(snapshot.dir_count || 0).toLocaleString()"></span>
                                </span>
                            </div>
                            <p class="text-[0.65rem] text-slate-600 mt-2 font-mono truncate" x-text="snapshot.manifest_id"></p>
                        </button>
                    </template>
                </div>
            </div>
        </div>
        
        {* Right: Time Slider & Actions *}
        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-6">
                
                {* Time Slider *}
                <div class="mb-6">
                    <label class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-3 block flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Browse Point in Time
                    </label>
                    <div class="relative px-1">
                        <input type="range" 
                               x-model="timeSliderValue" 
                               @input="selectSnapshotFromSlider()"
                               :max="Math.max(0, snapshots.length - 1)"
                               :disabled="snapshots.length === 0"
                               class="w-full h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-cyan-500 disabled:opacity-50 disabled:cursor-not-allowed
                                      [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-5 [&::-webkit-slider-thumb]:h-5 
                                      [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-cyan-500 [&::-webkit-slider-thumb]:shadow-lg
                                      [&::-webkit-slider-thumb]:cursor-pointer [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-cyan-400
                                      [&::-moz-range-thumb]:w-5 [&::-moz-range-thumb]:h-5 [&::-moz-range-thumb]:rounded-full 
                                      [&::-moz-range-thumb]:bg-cyan-500 [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-cyan-400">
                        <div class="flex justify-between text-xs text-slate-500 mt-2">
                            <span x-text="snapshots.length ? formatDate(snapshots[snapshots.length-1]?.created_at) : 'Oldest'"></span>
                            <span x-text="snapshots.length ? formatDate(snapshots[0]?.created_at) : 'Newest'"></span>
                        </div>
                    </div>
                </div>
                
                {* Selected Snapshot Info *}
                <template x-if="selectedSnapshot">
                    <div class="rounded-xl bg-slate-800/50 border border-slate-700/50 p-4 mb-6">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-lg bg-cyan-500/20 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-cyan-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white" x-text="formatDate(selectedSnapshot.created_at)"></p>
                                <p class="text-xs text-slate-400">Snapshot Point</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
                            <div class="rounded-lg bg-slate-900/50 p-3">
                                <p class="text-xs text-slate-400">Size</p>
                                <p class="text-sm text-white font-semibold mt-1" x-text="selectedSnapshot.size_human || '—'"></p>
                            </div>
                            <div class="rounded-lg bg-slate-900/50 p-3">
                                <p class="text-xs text-slate-400">Files</p>
                                <p class="text-sm text-white font-semibold mt-1" x-text="(selectedSnapshot.file_count || 0).toLocaleString()"></p>
                            </div>
                            <div class="rounded-lg bg-slate-900/50 p-3">
                                <p class="text-xs text-slate-400">Folders</p>
                                <p class="text-sm text-white font-semibold mt-1" x-text="(selectedSnapshot.dir_count || 0).toLocaleString()"></p>
                            </div>
                            <div class="rounded-lg bg-slate-900/50 p-3">
                                <p class="text-xs text-slate-400">Source</p>
                                <p class="text-sm text-white font-semibold mt-1 truncate" x-text="selectedSnapshot.source_path || '—'" :title="selectedSnapshot.source_path"></p>
                            </div>
                        </div>
                    </div>
                </template>
                
                {* No snapshot selected *}
                <template x-if="!selectedSnapshot">
                    <div class="rounded-xl bg-slate-800/30 border border-slate-700/30 p-8 mb-6 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-12 h-12 text-slate-600 mx-auto mb-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm text-slate-500">Select a snapshot from the list or use the time slider</p>
                    </div>
                </template>
                
                {* Action Buttons *}
                <div class="flex flex-wrap gap-3">
                    <button @click="mountSnapshot()" 
                            :disabled="!selectedSnapshot || !agentOnline"
                            class="flex items-center gap-2 px-5 py-2.5 rounded-lg bg-gradient-to-r from-cyan-600 to-blue-600 hover:from-cyan-500 hover:to-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold shadow-lg shadow-cyan-500/20 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-.12-1.03l-2.268-9.64a3.375 3.375 0 00-3.285-2.602H7.923a3.375 3.375 0 00-3.285 2.602l-2.268 9.64a4.5 4.5 0 00-.12 1.03v.228m19.5 0a3 3 0 01-3 3H5.25a3 3 0 01-3-3m19.5 0a3 3 0 00-3-3H5.25a3 3 0 00-3 3m16.5 0h.008v.008h-.008v-.008zm-3 0h.008v.008h-.008v-.008z" />
                        </svg>
                        Mount as Read-Only Drive
                    </button>
                    <button @click="$dispatch('open-file-browser', { snapshot: selectedSnapshot })" 
                            :disabled="!selectedSnapshot"
                            class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-slate-700 hover:bg-slate-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                        Browse Files
                    </button>
                    <button :disabled="snapshots.length < 2"
                            class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-slate-700 hover:bg-slate-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        Compare Snapshots
                    </button>
                </div>
            </div>
            
            {* Mounted Snapshot Status *}
            <template x-if="mountedSnapshot">
                <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-4 shadow-lg shadow-emerald-500/10">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center text-white font-bold text-lg shadow-lg">
                                <span x-text="mountedSnapshot.drive_letter + ':'"></span>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-emerald-100">Snapshot Mounted</p>
                                <p class="text-xs text-emerald-300/70" x-text="formatDate(mountedSnapshot.snapshot_date) + ' snapshot'"></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a :href="'file:///' + mountedSnapshot.drive_letter + ':/'" 
                               target="_blank"
                               class="px-3 py-1.5 rounded-lg bg-emerald-600/50 hover:bg-emerald-600 text-emerald-100 text-sm font-medium transition flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                                Open
                            </a>
                            <button @click="unmountSnapshot()" 
                                    class="px-3 py-1.5 rounded-lg bg-slate-700/50 hover:bg-slate-600 text-slate-200 text-sm font-medium transition">
                                Unmount
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            
            {* Usage hints *}
            <div class="rounded-xl border border-slate-800/50 bg-slate-900/30 p-4">
                <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Quick Tips</h4>
                <ul class="space-y-2 text-xs text-slate-500">
                    <li class="flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-cyan-500 mt-0.5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Mounted snapshots are <strong class="text-slate-400">read-only</strong> to protect your backup integrity</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-cyan-500 mt-0.5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Copy files from the mounted drive to restore specific items</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-cyan-500 mt-0.5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Use the time slider to quickly jump between backup points</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

