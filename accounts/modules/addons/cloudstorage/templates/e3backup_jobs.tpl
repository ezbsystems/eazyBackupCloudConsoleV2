{capture assign=ebE3JobsBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Jobs</span>
    </div>
{/capture}

{capture assign=ebE3Content}
<div class="eb-alert eb-alert--warning" style="margin-bottom: 16px;">
    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
    </svg>
    <div>
        <strong>This page has been deprecated.</strong> Jobs are now managed from each User's detail page.
        <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-link">Go to Users &rarr;</a>
    </div>
</div>
<div x-data="jobsApp()" class="space-y-6" data-e3backup-jobs-app>
    {capture assign=ebE3JobsHeaderActions}
        <div x-data="{ isOpen: false }" class="relative" @click.away="isOpen = false">
            <button type="button" @click="isOpen = !isOpen" class="eb-btn eb-btn-success eb-btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Create Job
                <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
            <div x-show="isOpen"
                 x-transition
                 class="eb-dropdown-menu absolute right-0 z-50 mt-2 w-72 overflow-hidden"
                 style="display: none;">
                <div class="eb-menu-label">Select backup source</div>
                <div class="p-1">
                    <button type="button" @click="isOpen = false; window.openCloudBackupWizard()" class="eb-menu-item">
                        <span class="eb-icon-box eb-icon-box--sm eb-icon-box--info">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                            </svg>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-left text-sm text-[var(--eb-text-primary)]">Cloud Backup</span>
                            <span class="block text-left text-xs text-[var(--eb-text-muted)]">S3, AWS, SFTP, Google Drive, Dropbox</span>
                        </span>
                    </button>
                    <button type="button" @click="isOpen = false; window.openLocalJobWizard()" class="eb-menu-item">
                        <span class="eb-icon-box eb-icon-box--sm eb-icon-box--premium">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                            </svg>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-left text-sm text-[var(--eb-text-primary)]">Local Agent Backup</span>
                            <span class="block text-left text-xs text-[var(--eb-text-muted)]">File, Disk Image, Windows Agent</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    {/capture}

    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3JobsBreadcrumb
        ebPageTitle='Backup Jobs'
        ebPageDescription='View and filter backup jobs. MSPs can filter by tenant and agent.'
        ebPageActions=$ebE3JobsHeaderActions
    }

    <div class="eb-subpanel overflow-visible">
        <div class="space-y-3">
            <div class="eb-table-toolbar eb-jobs-toolbar">
                <div class="eb-input-wrap eb-jobs-toolbar-search w-full xl:min-w-[18rem] xl:flex-1">
                    <div class="eb-input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                        </svg>
                    </div>
                    <input type="text" placeholder="Search jobs..." class="eb-input eb-input-has-icon" x-model="searchQuery">
                </div>

                {if $isMspClient}
                <div class="relative eb-jobs-toolbar-item w-full sm:w-auto"
                     x-data="{
                         isOpen: false,
                         tenantMenuQuery: '',
                         hasVisibleTenantOptions() {
                             return Array.from(this.$refs.tenantOptions.querySelectorAll('[data-tenant-option]')).some(option => option.offsetParent !== null);
                         }
                     }"
                     @click.away="isOpen = false; tenantMenuQuery = ''">
                    <button type="button"
                            @click="isOpen = !isOpen; if (isOpen) tenantMenuQuery = ''"
                            class="eb-menu-trigger w-full sm:min-w-[14rem]">
                        <span class="truncate" x-text="tenantLabel()"></span>
                        <svg class="h-4 w-4 shrink-0 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen"
                         x-transition
                         class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-full overflow-hidden sm:w-72"
                         style="display: none;">
                        <div class="eb-menu-label">Select tenant</div>
                        <div class="p-2 pb-0">
                            <input type="text"
                                   class="eb-input"
                                   placeholder="Search tenants..."
                                   x-model="tenantMenuQuery">
                        </div>
                        <div class="max-h-72 overflow-auto p-1" x-ref="tenantOptions">
                            <button type="button" class="eb-menu-option" :class="tenantFilter === '' ? 'is-active' : ''" x-show="!tenantMenuQuery || 'all tenants'.includes(tenantMenuQuery.toLowerCase())" @click="tenantFilter=''; tenantMenuQuery=''; isOpen=false; onTenantChange()" data-tenant-option="">All Tenants</button>
                            <button type="button" class="eb-menu-option" :class="tenantFilter === 'direct' ? 'is-active' : ''" x-show="!tenantMenuQuery || 'direct (no tenant)'.includes(tenantMenuQuery.toLowerCase())" @click="tenantFilter='direct'; tenantMenuQuery=''; isOpen=false; onTenantChange()" data-tenant-option="direct">Direct (No Tenant)</button>
                            {foreach from=$tenants item=tenant}
                            <button type="button"
                                    class="eb-menu-option"
                                    x-show="!tenantMenuQuery || '{$tenant->name|escape:'javascript'}'.toLowerCase().includes(tenantMenuQuery.toLowerCase())"
                                    :class="String(tenantFilter) === String('{$tenant->public_id|escape:'javascript'}') ? 'is-active' : ''"
                                    @click="tenantFilter='{$tenant->public_id|escape:'javascript'}'; tenantMenuQuery=''; isOpen=false; onTenantChange()"
                                    data-tenant-option="{$tenant->public_id|escape}">
                                {$tenant->name|escape}
                            </button>
                            {/foreach}
                            <div x-show="tenantMenuQuery && !hasVisibleTenantOptions()" class="px-3 py-2 text-sm text-[var(--eb-text-muted)]" style="display: none;">
                                No tenants match your search
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative eb-jobs-toolbar-item w-full sm:w-auto" x-data="{ isOpen: false, agentMenuQuery: '' }" @click.away="isOpen = false; agentMenuQuery = ''">
                    <button type="button"
                            @click="isOpen = !isOpen; if (isOpen) agentMenuQuery = ''"
                            class="eb-menu-trigger w-full sm:min-w-[14rem]">
                        <span class="truncate" x-text="agentLabel()"></span>
                        <svg class="h-4 w-4 shrink-0 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen"
                         x-transition
                         class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-full overflow-hidden sm:w-72"
                         style="display: none;">
                        <div class="eb-menu-label">Select agent</div>
                        <div class="p-2 pb-0">
                            <input type="text"
                                   class="eb-input"
                                   placeholder="Search agents..."
                                   x-model="agentMenuQuery">
                        </div>
                        <div class="max-h-72 overflow-auto p-1">
                            <button type="button" class="eb-menu-option" :class="agentFilter === '' ? 'is-active' : ''" x-show="!agentMenuQuery || 'all agents'.includes(agentMenuQuery.toLowerCase())" @click="agentFilter=''; agentMenuQuery=''; isOpen=false; loadJobs()">All Agents</button>
                            <template x-for="agent in filteredAgents" :key="agent.agent_uuid || agent.id">
                                <button type="button"
                                        class="eb-menu-option"
                                        x-show="!agentMenuQuery || String(agent.hostname || agent.device_name || (agent.agent_uuid || 'Unknown agent')).toLowerCase().includes(agentMenuQuery.toLowerCase())"
                                        :class="String(agentFilter) === String(agent.agent_uuid || '') ? 'is-active' : ''"
                                        @click="agentFilter=agent.agent_uuid || ''; agentMenuQuery=''; isOpen=false; loadJobs()">
                                    <span class="truncate" x-text="agent.hostname || agent.device_name || (agent.agent_uuid || 'Unknown agent')"></span>
                                </button>
                            </template>
                            <div x-show="filteredAgents.filter(agent => !agentMenuQuery || String(agent.hostname || agent.device_name || (agent.agent_uuid || 'Unknown agent')).toLowerCase().includes(agentMenuQuery.toLowerCase())).length === 0 && !(agentMenuQuery && 'all agents'.includes(agentMenuQuery.toLowerCase()))" class="px-3 py-2 text-sm text-[var(--eb-text-muted)]" style="display: none;" x-text="agentMenuQuery ? 'No agents match your search' : 'No agents available'"></div>
                        </div>
                    </div>
                </div>
                {/if}

                <div class="relative eb-jobs-toolbar-item w-full sm:w-auto"
                     x-data="{
                         isOpen: false,
                         sourceMenuQuery: '',
                         hasVisibleSourceOptions() {
                             return Array.from(this.$refs.sourceOptions.querySelectorAll('[data-source-option]')).some(option => option.offsetParent !== null);
                         }
                     }"
                     @click.away="isOpen = false; sourceMenuQuery = ''">
                    <button type="button"
                            @click="isOpen = !isOpen; if (isOpen) sourceMenuQuery = ''"
                            class="eb-menu-trigger w-full sm:min-w-[11rem]">
                        <span class="truncate" x-text="sourceLabel()"></span>
                        <svg class="h-4 w-4 shrink-0 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="isOpen"
                         x-transition
                         class="eb-dropdown-menu absolute right-0 z-50 mt-2 w-full overflow-hidden sm:w-64"
                         style="display: none;">
                        <div class="eb-menu-label">Source type</div>
                        <div class="p-2 pb-0">
                            <input type="text"
                                   class="eb-input"
                                   placeholder="Search sources..."
                                   x-model="sourceMenuQuery">
                        </div>
                        <div class="p-1" x-ref="sourceOptions">
                            <button type="button" class="eb-menu-option" data-source-option="all" :class="sourceFilter === 'all' ? 'is-active' : ''" x-show="!sourceMenuQuery || 'all sources'.includes(sourceMenuQuery.toLowerCase())" @click="sourceFilter='all'; sourceMenuQuery=''; isOpen=false">All Sources</button>
                            <button type="button" class="eb-menu-option" data-source-option="local_agent" :class="sourceFilter === 'local_agent' ? 'is-active' : ''" x-show="!sourceMenuQuery || 'local agent'.includes(sourceMenuQuery.toLowerCase())" @click="sourceFilter='local_agent'; sourceMenuQuery=''; isOpen=false">Local Agent</button>
                            <button type="button" class="eb-menu-option" data-source-option="cloud" :class="sourceFilter === 'cloud' ? 'is-active' : ''" x-show="!sourceMenuQuery || 'cloud-to-cloud'.includes(sourceMenuQuery.toLowerCase())" @click="sourceFilter='cloud'; sourceMenuQuery=''; isOpen=false">Cloud-to-Cloud</button>
                            <button type="button" class="eb-menu-option" data-source-option="s3_compatible" :class="sourceFilter === 's3_compatible' ? 'is-active' : ''" x-show="!sourceMenuQuery || 's3-compatible'.includes(sourceMenuQuery.toLowerCase())" @click="sourceFilter='s3_compatible'; sourceMenuQuery=''; isOpen=false">S3-Compatible</button>
                            <button type="button" class="eb-menu-option" data-source-option="aws" :class="sourceFilter === 'aws' ? 'is-active' : ''" x-show="!sourceMenuQuery || 'aws'.includes(sourceMenuQuery.toLowerCase())" @click="sourceFilter='aws'; sourceMenuQuery=''; isOpen=false">AWS</button>
                            <button type="button" class="eb-menu-option" data-source-option="sftp" :class="sourceFilter === 'sftp' ? 'is-active' : ''" x-show="!sourceMenuQuery || 'sftp'.includes(sourceMenuQuery.toLowerCase())" @click="sourceFilter='sftp'; sourceMenuQuery=''; isOpen=false">SFTP</button>
                            <button type="button" class="eb-menu-option" data-source-option="google_drive" :class="sourceFilter === 'google_drive' ? 'is-active' : ''" x-show="!sourceMenuQuery || 'google drive'.includes(sourceMenuQuery.toLowerCase())" @click="sourceFilter='google_drive'; sourceMenuQuery=''; isOpen=false">Google Drive</button>
                            <button type="button" class="eb-menu-option" data-source-option="dropbox" :class="sourceFilter === 'dropbox' ? 'is-active' : ''" x-show="!sourceMenuQuery || 'dropbox'.includes(sourceMenuQuery.toLowerCase())" @click="sourceFilter='dropbox'; sourceMenuQuery=''; isOpen=false">Dropbox</button>
                            <div x-show="sourceMenuQuery && !hasVisibleSourceOptions()" class="px-3 py-2 text-sm text-[var(--eb-text-muted)]" style="display: none;">
                                No sources match your search
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button class="eb-pill" :class="statusFilter === 'all' ? 'is-active' : ''" @click="statusFilter='all'">All</button>
                <button class="eb-pill" :class="statusFilter === 'success' ? 'is-active' : ''" @click="statusFilter='success'">Success</button>
                <button class="eb-pill" :class="statusFilter === 'warning' ? 'is-active' : ''" @click="statusFilter='warning'">Warning</button>
                <button class="eb-pill" :class="statusFilter === 'failed' ? 'is-active' : ''" @click="statusFilter='failed'">Failed</button>
                <button class="eb-pill" :class="statusFilter === 'running' ? 'is-active' : ''" @click="statusFilter='running'">Running</button>
                <button class="eb-pill" :class="statusFilter === 'failed_recent' ? 'is-active' : ''" @click="statusFilter='failed_recent'">Failed Recently</button>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <template x-if="loading">
                    <div class="eb-card !p-8 text-center">
                        <div class="inline-flex items-center gap-3 text-sm text-[var(--eb-text-muted)]">
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-[color:var(--eb-info-border)] border-t-[color:var(--eb-info-icon)]"></span>
                            Loading jobs...
                        </div>
                    </div>
                </template>

                <template x-if="!loading && filteredJobs.length === 0">
                    <div class="eb-app-empty">
                        <div class="eb-app-empty-title">No jobs found</div>
                        <p class="eb-app-empty-copy">Try a different filter or create a new backup job.</p>
                    </div>
                </template>

                <template x-for="job in filteredJobs" :key="job.job_id">
                    <div class="eb-job-card">
                        <div class="eb-job-card-header">
                            <div class="eb-job-card-identity">
                                <div class="eb-job-type-icon" aria-hidden="true">
                                    <svg x-show="(job.source_type || '').toLowerCase() === 'local_agent'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>
                                    </svg>
                                    <svg x-show="(job.source_type || '').toLowerCase() !== 'local_agent'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                        <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"/>
                                    </svg>
                                </div>
                                <div class="eb-job-card-name" x-text="job.name"></div>
                                <span x-show="(job.status || '').toLowerCase() === 'paused'" class="eb-job-status-paused">
                                    <span class="eb-job-status-paused-dot" aria-hidden="true"></span>
                                    Paused
                                </span>
                            </div>
                            <div class="eb-job-card-actions">
                                <div x-data="{ running: false }">
                                    <button type="button"
                                            class="eb-btn eb-btn-primary eb-btn-sm"
                                            @click="running = true; runJob(job.job_id).finally(() => running = false)"
                                            :disabled="(job.status || '').toLowerCase() !== 'active'">
                                        <template x-if="!running">
                                            <span class="inline-flex items-center gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5 shrink-0" fill="currentColor" aria-hidden="true"><polygon points="5,3 19,12 5,21"/></svg>
                                                <span>Run now</span>
                                            </span>
                                        </template>
                                        <template x-if="running">
                                            <span class="inline-flex items-center gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                                </svg>
                                                <span>Running...</span>
                                            </span>
                                        </template>
                                    </button>
                                </div>
                                <button type="button" @click="editJob(job)" class="eb-btn eb-btn-icon eb-btn-sm" title="Edit job">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-[15px] w-[15px]">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <button type="button"
                                        @click="toggleJobStatus(job.job_id, job.status)"
                                        class="eb-btn eb-btn-icon eb-btn-sm"
                                        :class="(job.status || '').toLowerCase() === 'paused' ? 'eb-job-action-icon--paused' : ''"
                                        :title="(job.status || '').toLowerCase() === 'active' ? 'Pause job' : 'Resume job'">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-[15px] w-[15px]">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                                    </svg>
                                </button>
                                <button type="button" @click="viewLogs(job.job_id)" class="eb-btn eb-btn-icon eb-btn-sm" title="View logs">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-[15px] w-[15px]">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg>
                                </button>
                                <button type="button" @click="openDeleteModal(job)" class="eb-btn eb-btn-icon eb-btn-sm is-danger" title="Delete job">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-[15px] w-[15px]">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                                <button type="button"
                                        @click="openRestore(job)"
                                        class="eb-btn eb-btn-icon eb-btn-sm"
                                        :class="scopeUserId == null && !job.backup_user_route_id ? 'opacity-50 cursor-not-allowed' : ''"
                                        :disabled="scopeUserId == null && !job.backup_user_route_id"
                                        :title="scopeUserId == null && !job.backup_user_route_id ? 'Job is not linked to a backup user' : 'Download / restore'">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-[15px] w-[15px]">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="eb-job-card-body">
                            <div class="eb-job-meta-item">
                                <div class="eb-job-meta-label">Source</div>
                                <div class="eb-job-meta-value" x-text="sourcePrimaryLabel(job)"></div>
                                <div class="eb-job-meta-sub" x-show="formatSourceSubtitle(job)" x-text="formatSourceSubtitle(job)"></div>
                            </div>
                            <div class="eb-job-meta-item">
                                <div class="eb-job-meta-label">Destination</div>
                                <div class="eb-job-meta-value" x-text="formatDestinationName(job)"></div>
                                <div class="eb-job-meta-sub" x-show="job.dest_prefix" x-text="'/' + job.dest_prefix"></div>
                            </div>
                            <div class="eb-job-meta-item">
                                <div class="eb-job-meta-label">Mode</div>
                                <div class="eb-job-meta-value">
                                    <span x-text="formatMode(job)"></span><span x-show="job.encryption_enabled" class="text-[var(--eb-text-muted)] font-normal"> · Encrypted</span>
                                </div>
                            </div>
                            <div class="eb-job-meta-item">
                                <div class="eb-job-meta-label">Schedule</div>
                                <div class="eb-job-meta-value"
                                     :class="resolveScheduleType(job) === 'manual' ? 'eb-job-schedule-manual' : ''"
                                     x-text="formatScheduleLabel(job)"></div>
                            </div>
                            <div class="eb-job-meta-item">
                                <div class="eb-job-meta-label">Next Run</div>
                                <div class="eb-job-meta-value"
                                     :class="[
                                         (resolveScheduleType(job) === 'manual' && (job.status || '').toLowerCase() !== 'paused') ? 'eb-job-schedule-manual' : '',
                                         nextRunMuted(job) ? '!text-[var(--eb-text-muted)]' : ''
                                     ]"
                                     x-text="nextRunDisplay(job)"></div>
                            </div>
                            <div class="eb-job-meta-item">
                                <div class="eb-job-meta-label">Last Run</div>
                                <div class="eb-job-meta-value" x-text="formatLastRunTime(job)"></div>
                                <template x-if="job.last_run && job.last_run.status">
                                    <div class="eb-job-last-run-status">
                                        <span class="eb-job-last-run-dot" :class="lastRunDotClass(job.last_run.status)"></span>
                                        <span class="eb-job-last-run-label" :class="lastRunLabelClass(job.last_run.status)" x-text="capitalize(job.last_run.status)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div x-show="deleteModalOpen"
         x-cloak
         class="fixed inset-0 z-[2200] flex items-center justify-center p-4"
         style="display:none;"
         @keydown.escape.window="closeDeleteModal()">
        <div class="eb-modal-backdrop absolute inset-0" @click="closeDeleteModal()"></div>
        <div class="eb-modal eb-modal--confirm relative z-10 !p-0 overflow-hidden"
             @click.stop
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <div class="eb-modal-header">
                <div>
                    <h3 class="eb-modal-title">Delete job?</h3>
                    <p class="eb-modal-subtitle">This will remove the job configuration and stop scheduled runs.</p>
                </div>
            </div>
            <div class="eb-modal-body">
                <div class="eb-alert eb-alert--danger">
                    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>Job: <span class="font-semibold text-[var(--eb-text-primary)]" x-text="deleteJobName || 'Unnamed job'"></span></div>
                </div>
            </div>
            <div class="eb-modal-footer">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeDeleteModal()" :disabled="deleteInProgress">Cancel</button>
                <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" @click="confirmDeleteJob()" :disabled="deleteInProgress">
                    <svg x-show="deleteInProgress" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-text="deleteInProgress ? 'Deleting...' : 'Delete'"></span>
                </button>
            </div>
        </div>
    </div>

    {include file="{$smarty.const.ROOTDIR}/modules/addons/cloudstorage/templates/partials/job_create_wizard.tpl"}
    {include file="{$smarty.const.ROOTDIR}/modules/addons/cloudstorage/templates/partials/bucket_create_modal.tpl"}

    <div id="restoreWizardModal" class="fixed inset-0 z-[2100] hidden">
        <div class="eb-modal-backdrop absolute inset-0" onclick="closeRestoreModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="eb-modal relative z-10 w-full max-w-3xl !p-0 overflow-hidden">
                <div class="eb-modal-header">
                    <div>
                        <h3 class="eb-modal-title">Restore Snapshot</h3>
                        <p class="eb-modal-subtitle">Select a snapshot, choose a target path, and optionally request a mount.</p>
                    </div>
                    <button class="eb-modal-close" onclick="closeRestoreModal()" aria-label="Close wizard">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="eb-modal-body">
                    <div class="mb-4 flex items-center gap-2">
                        <span class="eb-badge eb-badge--neutral" id="restoreStepLabel">Step 1 of 3</span>
                        <span class="eb-type-caption text-[var(--eb-text-secondary)]" id="restoreStepTitle">Select Snapshot</span>
                    </div>

                    <div class="space-y-6">
                        <div class="restore-step" data-step="1">
                            <label class="eb-field-label">Snapshot (from recent runs)</label>
                            <div class="mb-3">
                                <select id="restoreRunSelect" class="eb-select">
                                    <option value="">Loading runs...</option>
                                </select>
                                <p class="eb-field-help mt-1">Pick a run whose snapshot you want to restore.</p>
                            </div>
                        </div>

                        <div class="restore-step hidden" data-step="2">
                            <label class="eb-field-label">Restore Target</label>
                            <div class="space-y-3">
                                <input id="restoreTargetPath" type="text" class="eb-input" placeholder="Destination path on agent (e.g., C:\Restores\job123)">
                                <label class="eb-inline-choice">
                                    <input id="restoreMount" type="checkbox" class="eb-check-input">
                                    <span>Request mount instead of copy</span>
                                </label>
                            </div>
                        </div>

                        <div class="restore-step hidden" data-step="3">
                            <div class="eb-card-raised">
                                <p class="eb-card-title mb-2">Review</p>
                                <div id="restoreReview" class="max-h-64 overflow-auto rounded-[var(--eb-radius-md)] border border-[var(--eb-border-subtle)] bg-[var(--eb-bg-base)] p-3 text-xs leading-5 text-[var(--eb-text-secondary)] whitespace-pre-wrap"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" onclick="restorePrev()">Back</button>
                    <div class="flex gap-2">
                        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" onclick="closeRestoreModal()">Cancel</button>
                        <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" onclick="restoreNext()">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='jobs'
    ebE3Title='Backup Jobs'
    ebE3Description='View and filter backup jobs. MSPs can filter by tenant and agent.'
    ebE3Content=$ebE3Content
}

<style>
/* Hide native number spinners for custom steppers */
.eb-no-spinner::-webkit-outer-spin-button,
.eb-no-spinner::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.eb-no-spinner { -moz-appearance: textfield; appearance: textfield; }

.eb-jobs-toolbar > * {
    min-width: 0;
}

@media (min-width: 1280px) and (max-width: 1323px) {
    .eb-jobs-toolbar {
        display: grid;
        grid-template-columns: minmax(0, 1.2fr) repeat(3, minmax(0, 0.9fr));
        align-items: start;
        gap: 0.75rem;
    }

    .eb-jobs-toolbar-search,
    .eb-jobs-toolbar-item {
        width: 100%;
        min-width: 0 !important;
    }

    .eb-jobs-toolbar-item .eb-menu-trigger {
        width: 100%;
        min-width: 0 !important;
        max-width: 100%;
    }
}
</style>

{include file="{$smarty.const.ROOTDIR}/modules/addons/cloudstorage/templates/partials/e3backup_jobs_client_script.tpl"}
