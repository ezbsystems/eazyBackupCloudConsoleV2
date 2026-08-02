{assign var=ebE3SidebarUsername value=$ebE3SidebarUsername|default:''}
{assign var=ebE3SidebarUserRouteId value=$ebE3SidebarUserRouteId|default:''}
{assign var=ebE3UserSubnavActive value=$ebE3UserSubnavActive|default:''}
{assign var=ebE3UserLabelPrefix value=''}
{if $ebE3SidebarUsername neq ''}
    {capture assign=ebE3UserLabelPrefix}{$ebE3SidebarUsername|escape:'html'} — {/capture}
{/if}

<div class="space-y-1" x-data="e3UserDetailSidebarNav()" x-init="init()">
    <div
        class="eb-e3-sidebar-subnav space-y-1 transition-all duration-300"
        :class="isRailMode ? 'eb-e3-sidebar-subnav--rail' : 'eb-e3-sidebar-subnav--full'"
    >
        <div
            x-show="sidebarLabelsVisible"
            x-cloak
            class="eb-sidebar-section-label eb-e3-sidebar-username !px-0 !pt-1"
            title="{$ebE3SidebarUsername|escape:'html'}"
        >
            <span class="block truncate">{$ebE3SidebarUsername|escape:'html'}</span>
        </div>

        <button type="button"
                data-sidebar-label="{$ebE3UserLabelPrefix}Profile"
                class="eb-sidebar-link eb-e3-sidebar-sublink w-full cursor-pointer text-left"
                :class="[activeTab === 'overview' ? 'is-active' : '', isRailMode && 'eb-e3-sidebar-link--rail']"
                :aria-current="activeTab === 'overview' ? 'page' : false"
                :aria-label="isRailMode ? '{$ebE3UserLabelPrefix|escape:'javascript'}Profile' : false"
                @click="selectTab('overview')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <span x-show="sidebarLabelsVisible" x-cloak>Profile</span>
        </button>

        <button x-show="showAgentsTab()"
                type="button"
                data-sidebar-label="{$ebE3UserLabelPrefix}Agents"
                class="eb-sidebar-link eb-e3-sidebar-sublink w-full cursor-pointer text-left"
                :class="[activeTab === 'agents' ? 'is-active' : '', isRailMode && 'eb-e3-sidebar-link--rail']"
                :aria-current="activeTab === 'agents' ? 'page' : false"
                :aria-label="isRailMode ? '{$ebE3UserLabelPrefix|escape:'javascript'}Agents' : false"
                @click="selectTab('agents')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
            </svg>
            <span x-show="sidebarLabelsVisible" x-cloak>Agents</span>
            <span x-show="sidebarLabelsVisible && agentsCount > 0" x-cloak class="eb-sidebar-badge" x-text="agentsCount"></span>
        </button>

        <button type="button"
                data-sidebar-label="{$ebE3UserLabelPrefix}Jobs"
                :data-sidebar-label="jobsCount > 0 && isRailMode ? '{$ebE3UserLabelPrefix|escape:'javascript'}Jobs (' + jobsCount + ')' : '{$ebE3UserLabelPrefix|escape:'javascript'}Jobs'"
                class="eb-sidebar-link eb-e3-sidebar-sublink w-full cursor-pointer text-left"
                :class="[activeTab === 'jobs' ? 'is-active' : '', isRailMode && 'eb-e3-sidebar-link--rail']"
                :aria-current="activeTab === 'jobs' ? 'page' : false"
                :aria-label="isRailMode ? '{$ebE3UserLabelPrefix|escape:'javascript'}Jobs' : false"
                @click="selectTab('jobs')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <span x-show="sidebarLabelsVisible" x-cloak>Jobs</span>
            <span x-show="sidebarLabelsVisible && jobsCount > 0" x-cloak class="eb-sidebar-badge" x-text="jobsCount"></span>
        </button>

        <a href="index.php?m=cloudstorage&page=e3backup&view=job_logs&user_id={$ebE3SidebarUserRouteId|escape:'url'}"
           data-sidebar-label="{$ebE3UserLabelPrefix}Job Logs"
           class="eb-sidebar-link eb-e3-sidebar-sublink {if $ebE3UserSubnavActive eq 'job_logs'}is-active{/if}"
           :class="isRailMode && 'eb-e3-sidebar-link--rail'"
           {if $ebE3UserSubnavActive eq 'job_logs'}aria-current="page"{/if}
           :aria-label="isRailMode ? '{$ebE3UserLabelPrefix|escape:'javascript'}Job Logs' : false">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
            </svg>
            <span x-show="sidebarLabelsVisible" x-cloak>Job Logs</span>
        </a>

        <button type="button"
                data-sidebar-label="{$ebE3UserLabelPrefix}Restore"
                class="eb-sidebar-link eb-e3-sidebar-sublink w-full cursor-pointer text-left"
                :class="[activeTab === 'restore' ? 'is-active' : '', isRailMode && 'eb-e3-sidebar-link--rail']"
                :aria-current="activeTab === 'restore' ? 'page' : false"
                :aria-label="isRailMode ? '{$ebE3UserLabelPrefix|escape:'javascript'}Restore' : false"
                @click="selectTab('restore')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            <span x-show="sidebarLabelsVisible" x-cloak>Restore</span>
        </button>

        <button type="button"
                data-sidebar-label="{$ebE3UserLabelPrefix}Vaults"
                class="eb-sidebar-link eb-e3-sidebar-sublink w-full cursor-pointer text-left"
                :class="[activeTab === 'vaults' ? 'is-active' : '', isRailMode && 'eb-e3-sidebar-link--rail']"
                :aria-current="activeTab === 'vaults' ? 'page' : false"
                :aria-label="isRailMode ? '{$ebE3UserLabelPrefix|escape:'javascript'}Vaults' : false"
                @click="selectTab('vaults')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
            <span x-show="sidebarLabelsVisible" x-cloak>Vaults</span>
            <span x-show="sidebarLabelsVisible && vaultsCount > 0" x-cloak class="eb-sidebar-badge" x-text="vaultsCount"></span>
        </button>

        <button x-show="showHypervTab()"
                type="button"
                data-sidebar-label="{$ebE3UserLabelPrefix}Hyper-V"
                class="eb-sidebar-link eb-e3-sidebar-sublink w-full cursor-pointer text-left"
                :class="[activeTab === 'hyperv' ? 'is-active' : '', isRailMode && 'eb-e3-sidebar-link--rail']"
                :aria-current="activeTab === 'hyperv' ? 'page' : false"
                :aria-label="isRailMode ? '{$ebE3UserLabelPrefix|escape:'javascript'}Hyper-V' : false"
                @click="selectTab('hyperv')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2M5 12a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2" />
            </svg>
            <span x-show="sidebarLabelsVisible" x-cloak>Hyper-V</span>
            <span x-show="sidebarLabelsVisible && hypervCount > 0" x-cloak class="eb-sidebar-badge" x-text="hypervCount"></span>
        </button>

        <button type="button"
                data-sidebar-label="{$ebE3UserLabelPrefix}Billing"
                class="eb-sidebar-link eb-e3-sidebar-sublink w-full cursor-pointer text-left"
                :class="[activeTab === 'billing' ? 'is-active' : '', isRailMode && 'eb-e3-sidebar-link--rail']"
                :aria-current="activeTab === 'billing' ? 'page' : false"
                :aria-label="isRailMode ? '{$ebE3UserLabelPrefix|escape:'javascript'}Billing' : false"
                @click="selectTab('billing')">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
            </svg>
            <span x-show="sidebarLabelsVisible" x-cloak>Billing</span>
        </button>
    </div>
</div>
