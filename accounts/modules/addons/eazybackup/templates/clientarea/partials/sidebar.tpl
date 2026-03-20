{assign var=ebSidebarPage value=$ebSidebarPage|default:'dashboard'}
{assign var=ebIsDashboard value=$ebSidebarPage eq 'dashboard'}
{assign var=ebIsUserProfile value=$ebSidebarPage eq 'user-profile'}

<aside
    :class="sidebarCollapsed ? 'w-20' : 'w-56'"
    class="eb-sidebar relative flex-shrink-0 overflow-hidden rounded-tl-[var(--eb-radius-xl)] rounded-bl-[var(--eb-radius-xl)] transition-all duration-300 ease-in-out"
>
    <div class="flex h-full flex-col">
        <div class="border-b border-[var(--eb-border-subtle)] p-4">
            <div class="flex items-center gap-3" :class="sidebarCollapsed && 'justify-center'">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                    </svg>
                </span>
                <span x-show="!sidebarCollapsed" x-transition.opacity class="eb-type-h4 text-sm">Backup Dashboard</span>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-3">
            {if $ebIsDashboard}
                <a href="#" @click.prevent="switchTab('dashboard')" class="eb-sidebar-link" :class="[activeTab === 'dashboard' ? 'is-active' : '', sidebarCollapsed && 'justify-center px-4']" :title="sidebarCollapsed ? 'Backup Status' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity>Backup Status</span>
                </a>
            {else}
                <a href="{$modulelink}&a=dashboard&tab=dashboard" class="eb-sidebar-link {if $ebSidebarPage eq 'backup-status'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Backup Status' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity>Backup Status</span>
                </a>
            {/if}

            {if $ebIsDashboard}
                <a href="#" @click.prevent="switchTab('users')" class="eb-sidebar-link" :class="[activeTab === 'users' ? 'is-active' : '', sidebarCollapsed && 'justify-center px-4']" :title="sidebarCollapsed ? 'Users' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity>Users</span>
                </a>
            {elseif $ebIsUserProfile}
                <div class="space-y-1">
                    <a href="{$modulelink}&a=dashboard&tab=users" class="eb-sidebar-link is-active" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Users' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity>Users</span>
                    </a>

                    <div class="space-y-1 transition-all duration-300" :class="sidebarCollapsed ? 'px-0' : 'ml-4 border-l border-[var(--eb-border-subtle)] pl-4'">
                        <div x-show="!sidebarCollapsed" x-transition.opacity class="eb-sidebar-section-label !px-0 !pt-1">
                            {$username}
                        </div>

                        <a href="#" @click.prevent="activeSubTab = 'profile'" class="eb-sidebar-link" :class="[activeSubTab === 'profile' ? 'is-active' : '', sidebarCollapsed && 'justify-center px-2']" :title="sidebarCollapsed ? 'Profile' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>Profile</span>
                        </a>
                        <a href="#" @click.prevent="activeSubTab = 'protectedItems'" class="eb-sidebar-link" :class="[activeSubTab === 'protectedItems' ? 'is-active' : '', sidebarCollapsed && 'justify-center px-2']" :title="sidebarCollapsed ? 'Protected Items' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>Protected Items</span>
                        </a>
                        <a href="#" @click.prevent="activeSubTab = 'storage'" class="eb-sidebar-link" :class="[activeSubTab === 'storage' ? 'is-active' : '', sidebarCollapsed && 'justify-center px-2']" :title="sidebarCollapsed ? 'Storage Vaults' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>Storage Vaults</span>
                        </a>
                        <a href="#" @click.prevent="activeSubTab = 'devices'" class="eb-sidebar-link" :class="[activeSubTab === 'devices' ? 'is-active' : '', sidebarCollapsed && 'justify-center px-2']" :title="sidebarCollapsed ? 'Devices' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>Devices</span>
                        </a>
                        <a href="#" @click.prevent="activeSubTab = 'jobLogs'" class="eb-sidebar-link" :class="[activeSubTab === 'jobLogs' ? 'is-active' : '', sidebarCollapsed && 'justify-center px-2']" :title="sidebarCollapsed ? 'Job Logs' : ''">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <span x-show="!sidebarCollapsed" x-transition.opacity>Job Logs</span>
                        </a>
                    </div>
                </div>
            {else}
                <a href="{$modulelink}&a=dashboard&tab=users" class="eb-sidebar-link {if $ebSidebarPage eq 'users'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Users' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity>Users</span>
                </a>
            {/if}

            <a href="{$modulelink}&a=vaults" class="eb-sidebar-link {if $ebSidebarPage eq 'vaults'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Vaults' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Vaults</span>
            </a>

            <a href="{$modulelink}&a=job-logs" class="eb-sidebar-link {if $ebSidebarPage eq 'job-logs'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Job Logs' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Job Logs</span>
            </a>

            <div class="eb-sidebar-divider"></div>

            <button @click="toggleCollapse()" class="eb-sidebar-link w-full" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="transition-transform duration-300" :class="sidebarCollapsed && 'rotate-180'">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Collapse</span>
            </button>
        </nav>
    </div>
</aside>
