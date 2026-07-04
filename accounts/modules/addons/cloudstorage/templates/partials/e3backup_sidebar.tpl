{assign var=activeNav value=$activeNav|default:''}
{assign var=ebE3SidebarUsername value=$ebE3SidebarUsername|default:''}
{assign var=ebE3SidebarUserRouteId value=$ebE3SidebarUserRouteId|default:''}
{assign var=ebE3UserSubnavActive value=$ebE3UserSubnavActive|default:''}
{assign var=ebE3ShowUserSubnav value=$ebE3ShowUserSubnav|default:false}
{assign var=isMspClient value=$isMspClient|default:false}
{assign var=ebE3HasAgents value=$ebE3HasAgents|default:false}
{assign var=ebE3OnboardingComplete value=$ebE3OnboardingComplete|default:false}
{assign var=ebE3OnboardingCompleted value=$ebE3OnboardingCompleted|default:0}
{assign var=ebE3OnboardingTotal value=$ebE3OnboardingTotal|default:4}
{assign var=ebE3OnboardingHidden value=$ebE3OnboardingHidden|default:false}
{assign var=ebMs365Only value=$ebMs365Only|default:false}
{assign var=ebMs365ShowGettingStarted value=$ebMs365ShowGettingStarted|default:false}
{assign var=ebMs365OnboardingCompleted value=$ebMs365OnboardingCompleted|default:0}
{assign var=ebMs365OnboardingTotal value=$ebMs365OnboardingTotal|default:3}
{assign var=ebGsHidden value=$ebGsHidden|default:true}
{assign var=ebGsCompleted value=$ebGsCompleted|default:0}
{assign var=ebGsTotal value=$ebGsTotal|default:4}
{assign var=ebGsUserId value=$ebGsUserId|default:''}
{assign var=ebGsIntent value=$ebGsIntent|default:'local'}
{assign var=ebGsGettingStartedHref value='index.php?m=cloudstorage&page=e3backup&view=getting_started'}
{if $ebGsUserId neq ''}
    {capture assign=ebGsGettingStartedHref}index.php?m=cloudstorage&page=e3backup&view=getting_started&user_id={$ebGsUserId|escape:'url'}&intent={$ebGsIntent|escape:'url'}{/capture}
{/if}
{assign var=ebMs365OnboardingHidden value=$ebMs365OnboardingHidden|default:true}
{assign var=ebHasE3AgentProduct value=$ebHasE3AgentProduct|default:false}
{assign var=ebEnableAgentUrl value='index.php?m=cloudstorage&page=e3backup&view=enable_agent_backup'}

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
            {if not $ebGsHidden}
            <a href="{$ebGsGettingStartedHref|escape:'html'}"
               data-tour="sidebar-getting-started"
               class="eb-sidebar-link {if $activeNav eq 'getting_started'}is-active{/if}"
               :class="sidebarCollapsed && 'justify-center px-4'"
               :title="sidebarCollapsed ? 'Getting Started' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Getting Started</span>
                <span x-show="!sidebarCollapsed && {$ebGsTotal|intval} > 0" x-transition.opacity class="eb-sidebar-badge">{$ebGsCompleted}/{$ebGsTotal}</span>
            </a>
            {/if}

            <a href="index.php?m=cloudstorage&page=e3backup"
               data-tour="sidebar-dashboard"
               class="eb-sidebar-link {if $activeNav eq 'dashboard'}is-active{/if}"
               :class="sidebarCollapsed && 'justify-center px-4'"
               :title="sidebarCollapsed ? 'Dashboard' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h7.5v7.5h-7.5v-7.5Zm9 0h7.5v4.5h-7.5v-4.5Zm0 6h7.5v10.5h-7.5V9.75Zm-9 3h7.5v7.5h-7.5v-7.5Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Dashboard</span>
            </a>

            {if $activeNav eq 'user_detail' || $ebE3ShowUserSubnav}
                <div class="space-y-1">
                    <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-sidebar-link is-active" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Users' : ''">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <span x-show="!sidebarCollapsed" x-transition.opacity>Users</span>
                    </a>
                    {include file="modules/addons/cloudstorage/templates/partials/e3backup_sidebar_user_subnav.tpl"
                        ebE3SidebarUsername=$ebE3SidebarUsername
                        ebE3SidebarUserRouteId=$ebE3SidebarUserRouteId
                        ebE3UserSubnavActive=$ebE3UserSubnavActive
                    }
                </div>
            {else}
                <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-sidebar-link {if $activeNav eq 'users'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Users' : ''">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" x-transition.opacity>Users</span>
                </a>
            {/if}

            <a href="{if not $ebHasE3AgentProduct}{$ebEnableAgentUrl}{elseif $ebE3HasAgents}index.php?m=cloudstorage&page=e3backup&view=agents{else}#{/if}"
               class="eb-sidebar-link {if not $ebHasE3AgentProduct}{elseif not $ebE3HasAgents}is-disabled{elseif $activeNav eq 'agents'}is-active{/if}"
               {if not $ebHasE3AgentProduct}title="Enable workstation & server backup to use this"
               {elseif not $ebE3HasAgents}aria-disabled="true" onclick="return false;" tabindex="-1" title="Available after you enroll an agent"{/if}
               :class="sidebarCollapsed && 'justify-center px-4'"
               :title="sidebarCollapsed ? 'Agents' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Agents</span>
            </a>

            <a href="index.php?m=cloudstorage&page=e3backup&view=vaults" class="eb-sidebar-link {if $activeNav eq 'vaults'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Vaults' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Vaults</span>
            </a>

            <a href="{if not $ebHasE3AgentProduct}{$ebEnableAgentUrl}{elseif $ebE3HasAgents}index.php?m=cloudstorage&page=e3backup&view=job_logs{else}#{/if}"
               class="eb-sidebar-link {if not $ebHasE3AgentProduct}{elseif not $ebE3HasAgents}is-disabled{elseif $activeNav eq 'job_logs'}is-active{/if}"
               {if not $ebHasE3AgentProduct}title="Enable workstation & server backup to use this"
               {elseif not $ebE3HasAgents}aria-disabled="true" onclick="return false;" tabindex="-1" title="Available after you enroll an agent"{/if}
               :class="sidebarCollapsed && 'justify-center px-4'"
               :title="sidebarCollapsed ? 'Job Logs' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Job Logs</span>
            </a>

            {if $isMspClient}
            <a href="index.php?m=eazybackup&a=ph-tenants-manage" class="eb-sidebar-link {if $activeNav eq 'tenants'}is-active{/if}" :class="sidebarCollapsed && 'justify-center px-4'" :title="sidebarCollapsed ? 'Tenants' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Tenants</span>
            </a>
            {/if}

            <a href="{if not $ebHasE3AgentProduct}{$ebEnableAgentUrl}{elseif $ebE3HasAgents}index.php?m=cloudstorage&page=e3backup&view=cloudnas{else}#{/if}"
               class="eb-sidebar-link {if not $ebHasE3AgentProduct}{elseif not $ebE3HasAgents}is-disabled{elseif $activeNav eq 'cloudnas'}is-active{/if}"
               {if not $ebHasE3AgentProduct}title="Enable workstation & server backup to use this"
               {elseif not $ebE3HasAgents}aria-disabled="true" onclick="return false;" tabindex="-1" title="Available after you enroll an agent"{/if}
               :class="sidebarCollapsed && 'justify-center px-4'"
               :title="sidebarCollapsed ? 'Cloud NAS' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25A2.25 2.25 0 0 1 6 3h12a2.25 2.25 0 0 1 2.25 2.25v2.25A2.25 2.25 0 0 1 18 9.75H6A2.25 2.25 0 0 1 3.75 7.5V5.25Zm0 9A2.25 2.25 0 0 1 6 12h12a2.25 2.25 0 0 1 2.25 2.25v2.25A2.25 2.25 0 0 1 18 18.75H6a2.25 2.25 0 0 1-2.25-2.25v-2.25ZM7.5 6.75h.008v.008H7.5V6.75Zm0 9h.008v.008H7.5v-.008Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Cloud NAS</span>
            </a>

            {* Advanced: lower-prominence tools that only apply to disk-image /
               bare-metal recovery. Disk Image Recovery itself is reached
               contextually from User detail -> Restore (the per-snapshot
               "Disk Recovery" action), so it is intentionally not a top-level
               nav item. *}
            <div x-show="!sidebarCollapsed" x-transition.opacity class="eb-type-eyebrow mb-1 mt-4 px-1">Advanced</div>
            <div x-show="sidebarCollapsed" class="eb-sidebar-divider"></div>

            <a href="{if not $ebHasE3AgentProduct}{$ebEnableAgentUrl}{elseif $ebE3HasAgents}index.php?m=cloudstorage&page=e3backup&view=recovery_media{else}#{/if}"
               class="eb-sidebar-link {if not $ebHasE3AgentProduct}{elseif not $ebE3HasAgents}is-disabled{elseif $activeNav eq 'recovery_media'}is-active{/if}"
               {if not $ebHasE3AgentProduct}title="Enable workstation & server backup to use this"
               {elseif not $ebE3HasAgents}aria-disabled="true" onclick="return false;" tabindex="-1" title="Available after you enroll an agent"{/if}
               :class="sidebarCollapsed && 'justify-center px-4'"
               :title="sidebarCollapsed ? 'Media Builder' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5h10.5m-10.5 4.5h10.5m-10.5 4.5h6m-9-12h12A2.25 2.25 0 0 1 18 6.75v10.5A2.25 2.25 0 0 1 15.75 19.5h-7.5A2.25 2.25 0 0 1 6 17.25V6.75A2.25 2.25 0 0 1 8.25 4.5Z" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Media Builder</span>
            </a>

        </nav>

        <div class="border-t border-[var(--eb-border-subtle)] px-3 py-3">
            <div x-show="!sidebarCollapsed" x-transition.opacity class="eb-type-eyebrow mb-2 px-1">Install agent</div>
            {if $ebHasE3AgentProduct}
            <button type="button"
                    data-tour="sidebar-download"
                    onclick="ebE3OpenDownload();"
                    class="eb-btn eb-btn-primary eb-btn-md w-full justify-center gap-2"
                    :class="sidebarCollapsed && '!px-2'"
                    :title="sidebarCollapsed ? 'Download Agent' : ''">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Download Agent</span>
            </button>
            {else}
            <a href="{$ebEnableAgentUrl|escape:'html'}"
               title="Enable workstation & server backup to use this"
               class="eb-btn eb-btn-primary eb-btn-md w-full justify-center gap-2"
               :class="sidebarCollapsed && '!px-2'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span x-show="!sidebarCollapsed" x-transition.opacity>Enable Agent Backup</span>
            </a>
            {/if}

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
