{* Cloud NAS - My Drives Tab *}

{* Agent prerequisite check *}
<template x-if="!hasAgent">
    <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-5 py-4 mb-6">
        <div class="flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-amber-400 mt-0.5 flex-shrink-0">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
                <p class="font-semibold text-amber-100">Agent Required</p>
                <p class="text-sm text-amber-200/80 mt-1">
                    Cloud NAS requires the Windows backup agent to be installed and connected. 
                    <a href="index.php?m=cloudstorage&page=cloudbackup&view=cloudbackup_agents" class="underline hover:text-white">Set up an agent</a> to get started.
                </p>
            </div>
        </div>
    </div>
</template>

{* Summary Cards *}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="rounded-2xl px-4 py-3 bg-slate-900/70 border border-slate-800/80">
        <p class="text-[0.65rem] font-medium tracking-wide uppercase text-slate-400">Active Mounts</p>
        <p class="mt-1 text-2xl font-semibold text-white" x-text="mounts.filter(m => m.status === 'mounted').length">0</p>
    </div>
    <div class="rounded-2xl px-4 py-3 bg-slate-900/70 border border-slate-800/80">
        <p class="text-[0.65rem] font-medium tracking-wide uppercase text-slate-400">Configured Drives</p>
        <p class="mt-1 text-2xl font-semibold text-white" x-text="mounts.length">0</p>
    </div>
    <div class="rounded-2xl px-4 py-3 bg-slate-900/70 border border-slate-800/80">
        <p class="text-[0.65rem] font-medium tracking-wide uppercase text-slate-400">Cache Used</p>
        <p class="mt-1 text-2xl font-semibold text-white" x-text="cacheUsed">0 GB</p>
    </div>
    <div class="rounded-2xl px-4 py-3 bg-slate-900/70 border" 
         :class="agentOnline ? 'border-emerald-500/30' : 'border-slate-800/80'">
        <p class="text-[0.65rem] font-medium tracking-wide uppercase" 
           :class="agentOnline ? 'text-emerald-300' : 'text-slate-400'">Agent Status</p>
        <p class="mt-1 text-lg font-semibold flex items-center gap-2" 
           :class="agentOnline ? 'text-emerald-400' : 'text-slate-500'">
            <span class="relative flex h-2.5 w-2.5">
                <span x-show="agentOnline" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5" 
                      :class="agentOnline ? 'bg-emerald-500' : 'bg-slate-600'"></span>
            </span>
            <span x-text="agentOnline ? 'Online' : 'Offline'"></span>
        </p>
    </div>
</div>

{* Loading state *}
<template x-if="loadingMounts">
    <div class="flex items-center justify-center py-12">
        <svg class="animate-spin h-8 w-8 text-cyan-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </div>
</template>

{* Mounted Drives Grid *}
<template x-if="!loadingMounts">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <template x-for="mount in mounts" :key="mount.id">
            <div class="group relative rounded-2xl border bg-slate-900/50 p-5 transition hover:border-cyan-500/40"
                 :class="{
                    'border-emerald-500/40 shadow-[0_0_20px_rgba(16,185,129,0.1)]': mount.status === 'mounted',
                    'border-amber-500/40': mount.status === 'mounting' || mount.status === 'unmounting',
                    'border-slate-800': mount.status === 'unmounted' || mount.status === 'error'
                 }">
                
                {* Drive Letter Badge *}
                <div class="absolute -top-3 -left-2 w-12 h-12 rounded-xl flex items-center justify-center text-lg font-bold shadow-lg"
                     :class="{
                        'bg-gradient-to-br from-emerald-500 to-emerald-600 text-white': mount.status === 'mounted',
                        'bg-gradient-to-br from-amber-500 to-amber-600 text-white': mount.status === 'mounting' || mount.status === 'unmounting',
                        'bg-slate-700 text-slate-300': mount.status === 'unmounted' || mount.status === 'error'
                     }">
                    <span x-text="mount.drive_letter + ':'"></span>
                </div>
                
                {* Status indicator *}
                <div class="absolute top-4 right-4">
                    <span class="relative flex h-3 w-3">
                        <span x-show="mount.status === 'mounted'" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span x-show="mount.status === 'mounting' || mount.status === 'unmounting'" class="animate-pulse absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3" 
                              :class="{
                                'bg-emerald-500': mount.status === 'mounted',
                                'bg-amber-500': mount.status === 'mounting' || mount.status === 'unmounting',
                                'bg-slate-600': mount.status === 'unmounted',
                                'bg-rose-500': mount.status === 'error'
                              }"></span>
                    </span>
                </div>
                
                <div class="mt-6">
                    <h3 class="text-lg font-semibold text-white truncate" x-text="mount.bucket_name" :title="mount.bucket_name"></h3>
                    <p class="text-xs text-slate-400 mt-1 flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                        </svg>
                        <span x-text="mount.prefix || '/ (root)'"></span>
                    </p>
                </div>
                
                {* Status text *}
                <p class="text-xs mt-2" 
                   :class="{
                      'text-emerald-400': mount.status === 'mounted',
                      'text-amber-400': mount.status === 'mounting' || mount.status === 'unmounting',
                      'text-slate-500': mount.status === 'unmounted',
                      'text-rose-400': mount.status === 'error'
                   }">
                    <span x-show="mount.status === 'mounted'">Mounted</span>
                    <span x-show="mount.status === 'mounting'">Mounting...</span>
                    <span x-show="mount.status === 'unmounting'">Unmounting...</span>
                    <span x-show="mount.status === 'unmounted'">Not mounted</span>
                    <span x-show="mount.status === 'error'" x-text="'Error: ' + (mount.error || 'Unknown')"></span>
                </p>
                
                {* Mount config pills *}
                <div class="flex flex-wrap gap-2 mt-4">
                    <span x-show="mount.read_only" class="inline-flex items-center rounded-full bg-amber-500/15 px-2 py-0.5 text-[0.65rem] font-medium text-amber-200 border border-amber-500/30">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                        Read-only
                    </span>
                    <span x-show="mount.cache_mode && mount.cache_mode !== 'off'" class="inline-flex items-center rounded-full bg-sky-500/15 px-2 py-0.5 text-[0.65rem] font-medium text-sky-200 border border-sky-500/30">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        VFS Cache
                    </span>
                    <span x-show="mount.persistent" class="inline-flex items-center rounded-full bg-violet-500/15 px-2 py-0.5 text-[0.65rem] font-medium text-violet-200 border border-violet-500/30">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Auto-mount
                    </span>
                </div>
                
                {* Actions *}
                <div class="flex gap-2 mt-5">
                    <template x-if="mount.status !== 'mounted' && mount.status !== 'mounting'">
                        <button @click="mountDrive(mount.id)" 
                                :disabled="!agentOnline"
                                class="flex-1 px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium flex items-center justify-center gap-2 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                            </svg>
                            Mount
                        </button>
                    </template>
                    <template x-if="mount.status === 'mounted'">
                        <button @click="unmountDrive(mount.id)"
                                class="flex-1 px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium flex items-center justify-center gap-2 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
                            </svg>
                            Unmount
                        </button>
                    </template>
                    <template x-if="mount.status === 'mounting' || mount.status === 'unmounting'">
                        <button disabled class="flex-1 px-3 py-2 rounded-lg bg-slate-800 text-slate-400 text-sm font-medium flex items-center justify-center gap-2 cursor-not-allowed">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span x-text="mount.status === 'mounting' ? 'Mounting...' : 'Unmounting...'"></span>
                        </button>
                    </template>
                    <button @click="editMount(mount.id)" 
                            class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm transition"
                            title="Edit mount settings">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                    </button>
                    <button @click="deleteMount(mount.id)" 
                            class="px-3 py-2 rounded-lg bg-slate-800 hover:bg-rose-600/80 text-slate-300 hover:text-white text-sm transition"
                            title="Delete mount configuration">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                    </button>
                </div>
            </div>
        </template>
        
        {* Empty state / Add New Card *}
        <div @click="hasAgent && openMountWizard()" 
             class="rounded-2xl border-2 border-dashed p-5 flex flex-col items-center justify-center min-h-[240px] transition group"
             :class="hasAgent ? 'border-slate-700 hover:border-cyan-500/50 cursor-pointer' : 'border-slate-800 cursor-not-allowed opacity-50'">
            <div class="w-14 h-14 rounded-full bg-slate-800 group-hover:bg-cyan-500/20 flex items-center justify-center mb-3 transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 text-slate-500 group-hover:text-cyan-400 transition">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
            </div>
            <p class="text-sm text-slate-400 group-hover:text-cyan-300 transition font-medium">Mount a new drive</p>
            <p class="text-xs text-slate-500 mt-1">Connect a bucket as a local drive</p>
        </div>
    </div>
</template>

