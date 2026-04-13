{assign var=activeNav value=$activeNav|default:''}
{assign var=isMspClient value=$isMspClient|default:false}

<aside
    :class="sidebarCollapsed ? 'w-20' : 'w-56'"
    class="eb-sidebar relative flex-shrink-0 overflow-hidden rounded-tl-[var(--eb-radius-xl)] rounded-bl-[var(--eb-radius-xl)] transition-all duration-300 ease-in-out"
>
    <div class="flex h-full flex-col">
        <div class="border-b border-[var(--eb-border-subtle)] p-4">
            <div class="flex items-center gap-3" :class="sidebarCollapsed && 'justify-center'">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 15A2.25 2.25 0 0 0 6 17.25h12A2.25 2.25 0 0 0 20.25 15M3.75 15V9A2.25 2.25 0 0 1 6 6.75h12A2.25 2.25 0 0 1 20.25 9v6M3.75 15v2.25A2.25 2.25 0 0 0 6 19.5h12a2.25 2.25 0 0 0 2.25-2.25V15M9 12h6" />
                    </svg>
                </span>
                <span x-show="!sidebarCollapsed" x-transition.opacity class="eb-type-h4 text-sm">e3 Cloud Backup</span>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-3">
            <a href="index.php?m=cloudstorage&page=e3backup" class="eb-sidebar-link {if $activeNav eq 'dashboard'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Dashboard' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h7.5v7.5h-7.5v-7.5Zm9 0h7.5v4.5h-7.5v-4.5Zm0 6h7.5v10.5h-7.5V9.75Zm-9 3h7.5v7.5h-7.5v-7.5Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Dashboard</span>
            </a>

            <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-sidebar-link {if $activeNav eq 'users'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Users' : ''">                
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>

                <span x-show="!sidebarCollapsed" x-transition.opacity>Users</span>
            </a>

            <a href="index.php?m=cloudstorage&page=e3backup&view=agents" class="eb-sidebar-link {if $activeNav eq 'agents'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Agents' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Agents</span>
            </a>

            <a href="index.php?m=cloudstorage&page=e3backup&view=tokens" class="eb-sidebar-link {if $activeNav eq 'tokens'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Tokens' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" class="eb-sidebar-link-icon--filled" aria-hidden="true">
                    <path fill="currentColor" d="M480-80 120-280v-400l360-200 360 200v400L480-80ZM364-590q23-24 53-37t63-13q33 0 63 13t53 37l120-67-236-131-236 131 120 67Zm76 396v-131q-54-14-87-57t-33-98q0-11 1-20.5t4-19.5l-125-70v263l240 133Zm96.5-229.5Q560-447 560-480t-23.5-56.5Q513-560 480-560t-56.5 23.5Q400-513 400-480t23.5 56.5Q447-400 480-400t56.5-23.5ZM520-194l240-133v-263l-125 70q3 10 4 19.5t1 20.5q0 55-33 98t-87 57v131Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Tokens</span>
            </a>

            {if $isMspClient}
            <a href="index.php?m=cloudstorage&page=e3backup&view=tenants" class="eb-sidebar-link {if $activeNav eq 'tenants'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Tenants' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Tenants</span>
            </a>

            {* <a href="index.php?m=cloudstorage&page=e3backup&view=tenant_members" class="eb-sidebar-link {if $activeNav eq 'tenant_members'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Tenant Members' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Tenant Members</span>
            </a> *}
            {/if}

            <a href="index.php?m=cloudstorage&page=e3backup&view=disk_image_restore" class="eb-sidebar-link {if $activeNav eq 'disk_restore'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Recovery' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M3.75 4.5h16.5M4.5 4.5v15a.75.75 0 0 0 .75.75h13.5a.75.75 0 0 0 .75-.75v-15" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Recovery</span>
            </a>

            <a href="index.php?m=cloudstorage&page=e3backup&view=recovery_media" class="eb-sidebar-link {if $activeNav eq 'recovery_media'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Media Builder' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5h10.5m-10.5 4.5h10.5m-10.5 4.5h6m-9-12h12A2.25 2.25 0 0 1 18 6.75v10.5A2.25 2.25 0 0 1 15.75 19.5h-7.5A2.25 2.25 0 0 1 6 17.25V6.75A2.25 2.25 0 0 1 8.25 4.5Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Media Builder</span>
            </a>

            <a href="index.php?m=cloudstorage&page=e3backup&view=cloudnas" class="eb-sidebar-link {if $activeNav eq 'cloudnas'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Cloud NAS' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25A2.25 2.25 0 0 1 6 3h12a2.25 2.25 0 0 1 2.25 2.25v2.25A2.25 2.25 0 0 1 18 9.75H6A2.25 2.25 0 0 1 3.75 7.5V5.25Zm0 9A2.25 2.25 0 0 1 6 12h12a2.25 2.25 0 0 1 2.25 2.25v2.25A2.25 2.25 0 0 1 18 18.75H6a2.25 2.25 0 0 1-2.25-2.25v-2.25ZM7.5 6.75h.008v.008H7.5V6.75Zm0 9h.008v.008H7.5v-.008Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Cloud NAS</span>
            </a>

        </nav>

        <div class="border-t border-[var(--eb-border-subtle)] px-3 py-3">
            <button type="button" onclick="window.dispatchEvent(new Event('open-e3-download-flyout'))" class="eb-sidebar-link w-full cursor-pointer text-left" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Download Agent' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Download Agent</span>
            </button>

            <div class="eb-sidebar-divider"></div>

            <button type="button" @click="toggleCollapse()" class="eb-sidebar-link w-full cursor-pointer text-left" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="transition-transform duration-300" :class="sidebarCollapsed && 'rotate-180'">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Collapse</span>
            </button>
        </div>
    </div>
</aside>
