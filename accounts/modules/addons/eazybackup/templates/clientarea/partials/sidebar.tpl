{assign var=ebSidebarPage value=$ebSidebarPage|default:'dashboard'}
{assign var=ebIsDashboard value=$ebSidebarPage eq 'dashboard'}
{assign var=ebIsUserProfile value=$ebSidebarPage eq 'user-profile'}

<aside :class="sidebarCollapsed ? 'w-20' : 'w-64'" class="relative flex-shrink-0 border-r border-slate-800/80 bg-slate-900/50 rounded-tl-3xl rounded-bl-3xl transition-all duration-300 ease-in-out">
    <div class="rounded-tl-3xl flex flex-col h-full">
        <div class="rounded-tl-3xl p-4 border-b border-slate-800/60">
            <div class="flex items-center gap-3" :class="sidebarCollapsed && 'justify-center'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0 text-slate-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity class="font-semibold text-white text-sm">Backup Dashboard</span>
            </div>
        </div>

        <nav class="rounded-bl-3xl flex-1 p-3 space-y-1 overflow-y-auto">
            {if $ebIsDashboard}
                <a href="#" @click.prevent="switchTab('dashboard')" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200" :class="[activeTab === 'dashboard' ? 'bg-white/10 text-white ring-1 ring-white/20' : 'text-slate-400 hover:text-white hover:bg-white/5', sidebarCollapsed && 'justify-center']" :title="sidebarCollapsed ? 'Backup Status' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity class="text-sm font-medium">Backup Status</span>
                </a>
            {else}
                <a href="{$modulelink}&a=dashboard&tab=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $ebSidebarPage eq 'backup-status'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}" :class="sidebarCollapsed && 'justify-center'" :title="sidebarCollapsed ? 'Backup Status' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity class="text-sm font-medium">Backup Status</span>
                </a>
            {/if}

            {if $ebIsDashboard}
                <a href="#" @click.prevent="switchTab('users')" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200" :class="[activeTab === 'users' ? 'bg-white/10 text-white ring-1 ring-white/20' : 'text-slate-400 hover:text-white hover:bg-white/5', sidebarCollapsed && 'justify-center']" :title="sidebarCollapsed ? 'Users' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity class="text-sm font-medium">Users</span>
                </a>
            {elseif $ebIsUserProfile}
                <div class="space-y-1">
                    <a href="{$modulelink}&a=dashboard&tab=users" class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-white/5 text-white transition-all duration-200" :class="sidebarCollapsed && 'justify-center'" :title="sidebarCollapsed ? 'Users' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity class="text-sm font-medium">Users</span>
                    </a>

                    <div class="space-y-0.5 transition-all duration-300" :class="sidebarCollapsed ? 'px-0' : 'ml-4 pl-4 border-l border-slate-700/50'">
                        <div x-show="!sidebarCollapsed" x-transition.opacity class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-white uppercase tracking-wider">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                            </svg>
                            {$username}
                        </div>

                        <a href="#" @click.prevent="activeSubTab = 'profile'" :class="[activeSubTab === 'profile' ? (sidebarCollapsed ? 'bg-sky-500/20 text-sky-400' : 'bg-sky-500/10 text-sky-400 border-sky-400 -ml-[1px]') : 'text-slate-400 hover:text-white hover:bg-white/5', sidebarCollapsed && 'justify-center px-0 py-2']" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-all duration-150" :title="sidebarCollapsed ? 'Profile' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>Profile</span>
                        </a>
                        <a href="#" @click.prevent="activeSubTab = 'protectedItems'" :class="[activeSubTab === 'protectedItems' ? (sidebarCollapsed ? 'bg-sky-500/20 text-sky-400' : 'bg-sky-500/10 text-sky-400 border-sky-400 -ml-[1px]') : 'text-slate-400 hover:text-white hover:bg-white/5', sidebarCollapsed && 'justify-center px-0 py-2']" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-all duration-150" :title="sidebarCollapsed ? 'Protected Items' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>Protected Items</span>
                        </a>
                        <a href="#" @click.prevent="activeSubTab = 'storage'" :class="[activeSubTab === 'storage' ? (sidebarCollapsed ? 'bg-sky-500/20 text-sky-400' : 'bg-sky-500/10 text-sky-400 border-sky-400 -ml-[1px]') : 'text-slate-400 hover:text-white hover:bg-white/5', sidebarCollapsed && 'justify-center px-0 py-2']" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-all duration-150" :title="sidebarCollapsed ? 'Storage Vaults' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>Storage Vaults</span>
                        </a>
                        <a href="#" @click.prevent="activeSubTab = 'devices'" :class="[activeSubTab === 'devices' ? (sidebarCollapsed ? 'bg-sky-500/20 text-sky-400' : 'bg-sky-500/10 text-sky-400 border-sky-400 -ml-[1px]') : 'text-slate-400 hover:text-white hover:bg-white/5', sidebarCollapsed && 'justify-center px-0 py-2']" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-all duration-150" :title="sidebarCollapsed ? 'Devices' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>Devices</span>
                        </a>
                        <a href="#" @click.prevent="activeSubTab = 'jobLogs'" :class="[activeSubTab === 'jobLogs' ? (sidebarCollapsed ? 'bg-sky-500/20 text-sky-400' : 'bg-sky-500/10 text-sky-400 border-sky-400 -ml-[1px]') : 'text-slate-400 hover:text-white hover:bg-white/5', sidebarCollapsed && 'justify-center px-0 py-2']" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-all duration-150" :title="sidebarCollapsed ? 'Job Logs' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 flex-shrink-0">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>Job Logs</span>
                        </a>
                    </div>
                </div>
            {else}
                <a href="{$modulelink}&a=dashboard&tab=users" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $ebSidebarPage eq 'users'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}" :class="sidebarCollapsed && 'justify-center'" :title="sidebarCollapsed ? 'Users' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity class="text-sm font-medium">Users</span>
                </a>
            {/if}

            <a href="{$modulelink}&a=vaults" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $ebSidebarPage eq 'vaults'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}" :class="sidebarCollapsed && 'justify-center'" :title="sidebarCollapsed ? 'Vaults' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity class="text-sm font-medium">Vaults</span>
            </a>

            <a href="{$modulelink}&a=job-logs" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $ebSidebarPage eq 'job-logs'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}" :class="sidebarCollapsed && 'justify-center'" :title="sidebarCollapsed ? 'Job Logs' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity class="text-sm font-medium">Job Logs</span>
            </a>

            <button @click="toggleCollapse()" class="flex items-center gap-3 px-3 py-2.5 mt-2 rounded-lg text-slate-500 hover:text-slate-300 hover:bg-white/5 transition-all duration-200 w-full" :class="sidebarCollapsed && 'justify-center'" :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0 transition-transform duration-300" :class="sidebarCollapsed && 'rotate-180'">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity class="text-sm font-medium">Collapse</span>
            </button>
        </nav>
    </div>
</aside>
