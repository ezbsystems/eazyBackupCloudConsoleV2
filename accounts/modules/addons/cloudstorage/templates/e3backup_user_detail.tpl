{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">        
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
        </svg>

    </span>
{/capture}

{capture assign=ebE3Actions}{/capture}

{capture assign=ebE3UserDetailBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-breadcrumb-link">Users</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current" x-text="user.username || 'User Detail'"></span>
    </div>
{/capture}

{capture assign=ebE3UserDetailPageActions}{/capture}

{capture assign=ebE3Content}
<div x-data="backupUserDetailApp()" x-init="init()" data-e3backup-user-detail-app class="eb-section-stack">
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3UserDetailBreadcrumb
        ebPageTitle="{$user->username|escape:'html'}"
        ebPageDescription='Manage quotas, agents, jobs, vaults, and billing context for this backup user.'
        ebPageActions=$ebE3UserDetailPageActions
    }

    <template x-if="loading">
        <div class="eb-card">
            <div class="eb-loading-inline">
                <div class="eb-loading-spinner--compact" role="status" aria-label="Loading"></div>
                <span class="eb-type-caption">Loading user…</span>
            </div>
        </div>
    </template>

    <template x-if="!loading">
        <div class="eb-section-stack">
            <div class="eb-user-summary">
                <div class="eb-user-summary-header">
                    <div class="eb-user-summary-identity">
                        <div class="eb-user-avatar" x-text="userInitials(user)"></div>
                        <div>
                            <div class="eb-user-name" x-text="user.username || '—'"></div>
                            <div class="eb-user-meta-line">
                                <span x-text="user.email || '—'"></span>
                                <span class="sep" aria-hidden="true"></span>
                                <span x-text="tenantSummaryLabel()"></span>
                                <span class="sep" aria-hidden="true"></span>
                                <span class="eb-badge eb-badge--table eb-badge--dot"
                                      :class="isUserSuspended() ? 'eb-badge--warning' : 'eb-badge--success'"
                                      x-text="userStatusLabel()"></span>
                            </div>
                        </div>
                    </div>
                    <div class="eb-user-summary-actions">
                        <div x-data="{ isOpen: false }" class="relative shrink-0" @click.away="isOpen = false">
                            <button type="button" @click="isOpen = !isOpen" class="eb-btn eb-btn-secondary eb-btn-sm">
                                Actions
                                <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 0 1 1.414 0L10 10.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 0-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="isOpen"
                                 x-transition
                                 class="eb-dropdown-menu absolute right-0 z-50 mt-2 w-56 overflow-hidden"
                                 style="display: none;">
                                <button type="button" class="eb-menu-item" @click="isOpen = false; handleLoginAsUser()">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                        <polyline points="10 17 15 12 10 7"></polyline>
                                        <line x1="15" y1="12" x2="3" y2="12"></line>
                                    </svg>
                                    Login as User
                                </button>
                                <button type="button" class="eb-menu-item" @click="isOpen = false; openResetPasswordModal()">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                    Reset Password
                                </button>
                                <div class="eb-menu-divider"></div>
                                <template x-if="!isUserSuspended()">
                                    <button type="button" class="eb-menu-item is-warning" @click="isOpen = false; openSuspendModal()">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <rect x="6" y="4" width="4" height="16"></rect>
                                            <rect x="14" y="4" width="4" height="16"></rect>
                                        </svg>
                                        Suspend User
                                    </button>
                                </template>
                                <template x-if="isUserSuspended()">
                                    <button type="button"
                                            class="eb-menu-item"
                                            style="color: var(--eb-success-text);"
                                            @click="isOpen = false; reactivateUserFromAction()">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <polygon points="5 3 19 12 5 21"></polygon>
                                        </svg>
                                        Reactivate User
                                    </button>
                                </template>
                                <button type="button" class="eb-menu-item is-danger" @click="isOpen = false; openDeleteModal()">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                    Delete User
                                </button>
                            </div>
                        </div>
                        <div x-data="{ isOpen: false }" class="relative shrink-0" @click.away="isOpen = false">
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
                                    <template x-if="(user.backup_type || 'both') !== 'local'">
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
                                    </template>
                                    <template x-if="(user.backup_type || 'both') !== 'cloud_only'">
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
                                    </template>
                                </div>
                            </div>
                        </div>                       
                    </div>
                </div>
                <div class="eb-user-summary-stats">
                    <button type="button" class="eb-user-stat is-clickable" @click="selectUserDetailTab('vaults')">
                        <div class="eb-user-stat-value" x-text="user.vaults_count ?? 0"></div>
                        <div class="eb-user-stat-label">Vaults</div>
                    </button>
                    <button type="button" class="eb-user-stat is-clickable" @click="selectUserDetailTab('jobs')">
                        <div class="eb-user-stat-value" x-text="user.jobs_count ?? 0"></div>
                        <div class="eb-user-stat-label">Jobs</div>
                    </button>
                    <button type="button" class="eb-user-stat is-clickable" @click="selectUserDetailTab('agents')">
                        <div class="eb-user-stat-value" x-text="user.agents_count ?? 0"></div>
                        <div class="eb-user-stat-label">Agents</div>
                    </button>
                    <div class="eb-user-stat">
                        <div class="eb-user-stat-value" x-text="user.online_devices ?? 0"></div>
                        <div class="eb-user-stat-label">Online</div>
                    </div>
                    <div class="eb-user-stat">
                        <div class="eb-user-stat-value eb-user-stat-value--compact" x-text="formatDateShort(user.last_backup_at)"></div>
                        <div class="eb-user-stat-label">Last Backup</div>
                    </div>
                </div>
            </div>

            <div class="eb-tab-stack eb-tab-stack--responsive">
                <div class="eb-tab-mobile-switcher">
                    <div class="eb-menu-label eb-tab-mobile-switcher-label">Section</div>
                    <button type="button"
                            class="eb-btn eb-btn-secondary eb-btn-sm eb-btn-block"
                            @click="tabMenuOpen = !tabMenuOpen"
                            :aria-expanded="tabMenuOpen"
                            aria-haspopup="listbox"
                            aria-label="Choose section">
                        <span class="truncate" x-text="userDetailTabTriggerLabel()"></span>
                        <svg class="eb-dropdown-chevron" :class="tabMenuOpen ? 'is-open' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="tabMenuOpen"
                         x-transition
                         x-cloak
                         @click.away="tabMenuOpen = false"
                         @keydown.escape.window="tabMenuOpen = false"
                         class="eb-menu eb-tab-mobile-menu"
                         style="display: none;"
                         role="listbox"
                         aria-label="User sections">
                        <button type="button" role="option" class="eb-menu-option" :class="activeTab === 'overview' ? 'is-active' : ''" @click="selectUserDetailTab('overview')">Overview</button>
                        <button x-show="(user.backup_type || 'both') !== 'cloud_only'" type="button" role="option" class="eb-menu-option" :class="activeTab === 'agents' ? 'is-active' : ''" @click="selectUserDetailTab('agents')">
                            <span>Agents</span>
                            <span class="eb-tab-count" x-text="user.agents_count ?? 0"></span>
                        </button>
                        <button type="button" role="option" class="eb-menu-option" :class="activeTab === 'jobs' ? 'is-active' : ''" @click="selectUserDetailTab('jobs')">
                            <span>Jobs</span>
                            <span class="eb-tab-count" x-text="user.jobs_count ?? 0"></span>
                        </button>
                        <button type="button" role="option" class="eb-menu-option" :class="activeTab === 'restore' ? 'is-active' : ''" @click="selectUserDetailTab('restore')">Restore</button>
                        <button type="button" role="option" class="eb-menu-option" :class="activeTab === 'vaults' ? 'is-active' : ''" @click="selectUserDetailTab('vaults')">
                            <span>Vaults</span>
                            <span class="eb-tab-count" x-text="user.vaults_count ?? 0"></span>
                        </button>
                        <button x-show="(user.hyperv_jobs_count ?? 0) > 0" type="button" role="option" class="eb-menu-option" :class="activeTab === 'hyperv' ? 'is-active' : ''" @click="selectUserDetailTab('hyperv')">
                            <span>Hyper-V</span>
                            <span class="eb-tab-count" x-text="user.hyperv_vms?.length ?? 0"></span>
                        </button>
                        <button type="button" role="option" class="eb-menu-option" :class="activeTab === 'billing' ? 'is-active' : ''" @click="selectUserDetailTab('billing')">Billing</button>
                    </div>
                </div>

                <div class="eb-tab-bar eb-tab-bar--user-detail eb-tab-bar--user-detail-desktop" role="tablist" aria-label="User sections">
                    <button type="button" role="tab" class="eb-tab" :class="activeTab === 'overview' ? 'is-active' : ''" @click="selectUserDetailTab('overview')" :aria-selected="activeTab === 'overview'">
                        <svg class="eb-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        Overview
                    </button>
                    <button x-show="(user.backup_type || 'both') !== 'cloud_only'" type="button" role="tab" class="eb-tab" :class="activeTab === 'agents' ? 'is-active' : ''" @click="selectUserDetailTab('agents')" :aria-selected="activeTab === 'agents'">
                        <svg class="eb-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        Agents
                        <span class="eb-tab-count" x-text="user.agents_count ?? 0"></span>
                    </button>
                    <button type="button" role="tab" class="eb-tab" :class="activeTab === 'jobs' ? 'is-active' : ''" @click="selectUserDetailTab('jobs')" :aria-selected="activeTab === 'jobs'">
                        <svg class="eb-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        Jobs
                        <span class="eb-tab-count" x-text="user.jobs_count ?? 0"></span>
                    </button>
                    <button type="button" role="tab" class="eb-tab" :class="activeTab === 'restore' ? 'is-active' : ''" @click="selectUserDetailTab('restore')" :aria-selected="activeTab === 'restore'">
                        <svg class="eb-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline fill="none" points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Restore
                    </button>
                    <button type="button" role="tab" class="eb-tab" :class="activeTab === 'vaults' ? 'is-active' : ''" @click="selectUserDetailTab('vaults')" :aria-selected="activeTab === 'vaults'">
                        <svg class="eb-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"/></svg>
                        Vaults
                        <span class="eb-tab-count" x-text="user.vaults_count ?? 0"></span>
                    </button>
                    <button x-show="(user.hyperv_jobs_count ?? 0) > 0" type="button" role="tab" class="eb-tab" :class="activeTab === 'hyperv' ? 'is-active' : ''" @click="selectUserDetailTab('hyperv')" :aria-selected="activeTab === 'hyperv'">
                        <svg class="eb-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2M5 12a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2"/></svg>
                        Hyper-V
                        <span class="eb-tab-count" x-text="user.hyperv_vms?.length ?? 0"></span>
                    </button>
                    <button type="button" role="tab" class="eb-tab" :class="activeTab === 'billing' ? 'is-active' : ''" @click="selectUserDetailTab('billing')" :aria-selected="activeTab === 'billing'">
                        <svg class="eb-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        Billing
                    </button>
                </div>

                <div class="eb-tab-body" x-show="activeTab === 'overview'" x-cloak>
                    <div class="eb-section-intro" style="margin-bottom: 20px;">
                        <div class="eb-section-title">User quotas</div>
                        <p class="eb-section-description">Set resource limits for this backup user. Limits are not enforced by this screen yet; values are shown as unlimited until policy APIs are connected.</p>
                    </div>
                    <div class="eb-quota-grid" style="margin-bottom: 28px;">
                        <div class="eb-quota-card">
                            <div class="eb-quota-card-header">
                                <span class="eb-quota-label">Agents</span>
                                <span class="eb-quota-badge unlimited">Unlimited</span>
                            </div>
                            <div class="eb-quota-usage">
                                <span class="eb-quota-current" x-text="user.agents_count ?? 0"></span>
                                <span class="eb-quota-limit">/ ∞</span>
                            </div>
                            <div class="eb-quota-bar"><div class="eb-quota-bar-fill" style="width:0%;"></div></div>
                            <div class="eb-quota-input-row">
                                <label class="eb-field-label" for="e3-quota-agents">Limit</label>
                                <input id="e3-quota-agents" type="text" class="eb-input eb-quota-input-narrow" value="∞" disabled aria-disabled="true">
                                <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" disabled>Save</button>
                            </div>
                        </div>
                        <div class="eb-quota-card">
                            <div class="eb-quota-card-header">
                                <span class="eb-quota-label">Storage</span>
                                <span class="eb-quota-badge unlimited">Unlimited</span>
                            </div>
                            <div class="eb-quota-usage">
                                <span class="eb-quota-current">—</span>
                                <span class="eb-quota-limit">GB / ∞</span>
                            </div>
                            <div class="eb-quota-bar"><div class="eb-quota-bar-fill" style="width:0%;"></div></div>
                            <div class="eb-quota-input-row">
                                <label class="eb-field-label" for="e3-quota-storage">Limit (GB)</label>
                                <input id="e3-quota-storage" type="text" class="eb-input eb-quota-input-narrow" placeholder="∞" disabled aria-disabled="true">
                                <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" disabled>Save</button>
                            </div>
                        </div>
                        <div class="eb-quota-card">
                            <div class="eb-quota-card-header">
                                <span class="eb-quota-label">Guest VMs</span>
                                <span class="eb-quota-badge unlimited">Unlimited</span>
                            </div>
                            <div class="eb-quota-usage">
                                <span class="eb-quota-current" x-text="billingKpis().hyperv_guests ?? 0"></span>
                                <span class="eb-quota-limit">/ ∞</span>
                            </div>
                            <div class="eb-quota-bar"><div class="eb-quota-bar-fill" style="width:0%;"></div></div>
                            <div class="eb-quota-input-row">
                                <label class="eb-field-label" for="e3-quota-vms">Limit</label>
                                <input id="e3-quota-vms" type="text" class="eb-input eb-quota-input-narrow" placeholder="∞" disabled aria-disabled="true">
                                <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" disabled>Save</button>
                            </div>
                        </div>
                    </div>

                    <div class="eb-subpanel" style="margin-bottom: 20px;">
                        <div class="flex items-center justify-between" style="margin-bottom: 8px;">
                            <h2 class="eb-section-title !mb-0">Backup Type</h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="eb-badge eb-badge--table"
                                  :class="{
                                      'eb-badge--info': (user.backup_type || 'both') === 'cloud_only',
                                      'eb-badge--premium': (user.backup_type || 'both') === 'local',
                                      'eb-badge--success': (user.backup_type || 'both') === 'both'
                                  }"
                                  x-text="(user.backup_type || 'both') === 'cloud_only' ? 'Cloud Only' : ((user.backup_type || 'both') === 'local' ? 'Local Agent' : 'Both (Cloud + Local)')"></span>
                            <template x-if="(user.backup_type || 'both') === 'cloud_only'">
                                <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="upgradeBackupType('both')">
                                    Enable Local Agent
                                </button>
                            </template>
                            <template x-if="(user.backup_type || 'both') === 'local'">
                                <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="upgradeBackupType('both')">
                                    Enable Cloud Backup
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="eb-subpanel eb-subpanel--overflow-visible">
                        <h2 class="eb-section-title" style="margin-bottom: 12px;">Update user</h2>
                        <div x-show="updateMessage" x-cloak class="eb-alert eb-alert--success" style="margin-bottom: 12px;" role="status">
                            <div x-text="updateMessage"></div>
                        </div>
                        <div x-show="updateError" x-cloak class="eb-alert eb-alert--danger" style="margin-bottom: 12px;" role="alert">
                            <div x-text="updateError"></div>
                        </div>
                        <form @submit.prevent="updateUser()" class="eb-overview-form-stack">
                            <div>
                                <label class="eb-field-label" for="e3-user-detail-username">Username</label>
                                <input id="e3-user-detail-username" type="text" x-model.trim="updateForm.username" class="eb-input" :class="updateErrors.username ? 'is-error' : ''">
                                <p class="eb-field-error" x-show="updateErrors.username" x-text="updateErrors.username"></p>
                            </div>
                            <div>
                                <label class="eb-field-label" for="e3-user-detail-email">Email</label>
                                <input id="e3-user-detail-email" type="email" x-model.trim="updateForm.email" class="eb-input" :class="updateErrors.email ? 'is-error' : ''">
                                <p class="eb-field-error" x-show="updateErrors.email" x-text="updateErrors.email"></p>
                            </div>

                            {if $isMspClient}
                            <div class="eb-subpanel--overflow-visible">
                                <label class="eb-field-label">Tenant</label>
                                <div class="relative eb-subpanel--overflow-visible" x-data="{ isOpen: false }" @click.away="isOpen = false">
                                    <button type="button"
                                            @click="isOpen = !isOpen"
                                            class="eb-btn eb-btn-secondary eb-btn-sm eb-btn-block">
                                        <span class="min-w-0 truncate text-left" x-text="updateTenantLabel()"></span>
                                        <svg class="eb-dropdown-chevron" :class="isOpen ? 'is-open' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <div x-show="isOpen"
                                         x-transition
                                         class="eb-menu absolute left-0 z-50 mt-2 w-full overflow-hidden"
                                         style="display: none;">
                                        <div class="eb-menu-search-panel">
                                            <input type="text" x-model="tenantSearch" placeholder="Search tenants"
                                                   class="eb-toolbar-search eb-toolbar-search--menu"
                                                   @click.stop>
                                        </div>
                                        <div class="max-h-64 overflow-auto p-1">
                                            <button type="button"
                                                    class="eb-menu-option"
                                                    :class="updateForm.tenant_id === '' ? 'is-active' : ''"
                                                    @click="updateForm.tenant_id=''; isOpen=false;">
                                                Direct (No Tenant)
                                            </button>
                                            <template x-for="tenant in filteredTenants" :key="'detail-tenant-' + (tenant.public_id || tenant.id)">
                                                <button type="button"
                                                        class="eb-menu-option"
                                                        :class="String(updateForm.tenant_id) === String(tenant.public_id || tenant.id) ? 'is-active' : ''"
                                                        @click="updateForm.tenant_id = String(tenant.public_id || tenant.id); isOpen=false;">
                                                    <span x-text="tenant.name"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <p class="eb-field-error" x-show="updateErrors.tenant_id" x-text="updateErrors.tenant_id"></p>
                            </div>
                            {/if}

                            <div class="eb-subpanel--overflow-visible">
                                <label class="eb-field-label">Status</label>
                                <div class="relative eb-subpanel--overflow-visible" x-data="{ isOpen: false }" @click.away="isOpen = false">
                                    <button type="button"
                                            @click="isOpen = !isOpen"
                                            class="eb-btn eb-btn-secondary eb-btn-sm eb-btn-block">
                                        <span x-text="updateForm.status === 'disabled' ? 'Suspended' : 'Active'"></span>
                                        <svg class="eb-dropdown-chevron" :class="isOpen ? 'is-open' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <div x-show="isOpen"
                                         x-transition
                                         class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-full overflow-hidden"
                                         style="display: none;">
                                        <button type="button"
                                                class="eb-menu-option"
                                                :class="updateForm.status === 'active' ? 'is-active' : ''"
                                                @click="updateForm.status='active'; isOpen=false;">
                                            Active
                                        </button>
                                        <button type="button"
                                                class="eb-menu-option"
                                                :class="updateForm.status === 'disabled' ? 'is-active' : ''"
                                                @click="updateForm.status='disabled'; isOpen=false;">
                                            Suspended
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <button type="submit"
                                        :disabled="updating"
                                        class="eb-btn eb-btn-primary eb-btn-sm">
                                    <span x-show="!updating">Save changes</span>
                                    <span x-show="updating">Saving…</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="eb-tab-body" x-show="activeTab === 'agents'" x-cloak>
                    <template x-if="!agentsDetail().length">
                        <div class="eb-app-empty">
                            <div class="eb-app-empty-title">No agents in this scope</div>
                            <p class="eb-app-empty-copy">Enroll agents for this tenant or direct scope to see them listed here.</p>
                        </div>
                    </template>
                    <template x-if="agentsDetail().length">
                        <div class="eb-table-shell">
                            <table class="eb-table">
                                <thead>
                                    <tr>
                                        <th class="eb-table-chevron-col" aria-hidden="true"></th>
                                        <th>Hostname</th>
                                        <th>Agent UUID</th>
                                        <th>Type</th>
                                        <th>Connection</th>
                                        <th>Last seen</th>
                                        <th class="eb-table-cell-numeric">Jobs</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <template x-for="agent in agentsDetail()" :key="'agent-' + agent.agent_uuid">
                                    <tbody x-data="{ open: false }">
                                        <tr class="eb-expand-row" :class="open ? 'is-open' : ''" @click="open = !open">
                                            <td class="eb-table-chevron-cell">
                                                <svg class="eb-expand-chevron" viewBox="0 0 24 24" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
                                            </td>
                                            <td class="eb-table-primary" x-text="agent.hostname"></td>
                                            <td class="eb-table-mono" x-text="agent.uuid_short"></td>
                                            <td><span class="eb-badge eb-badge--table eb-badge--default" x-text="agent.agent_type"></span></td>
                                            <td>
                                                <span class="eb-connection-status" :class="agent.online ? 'eb-connection-status--online' : 'eb-connection-status--offline'">
                                                    <span class="eb-status-dot" :class="agent.online ? 'eb-status-dot--active' : 'eb-status-dot--error'"></span>
                                                    <span x-text="agent.online ? 'Online' : 'Offline'"></span>
                                                    <span class="eb-connection-age" x-show="!agent.online && agent.offline_days !== null" x-text="'(' + agent.offline_days + 'd)'"></span>
                                                </span>
                                            </td>
                                            <td class="eb-table-mono" x-text="formatDate(agent.last_seen_at)"></td>
                                            <td class="eb-table-cell-numeric" x-text="agent.jobs_count"></td>
                                            <td>
                                                <span class="eb-badge eb-badge--table"
                                                      :class="(agent.status || '').toLowerCase() === 'active' ? 'eb-badge--success' : 'eb-badge--neutral'"
                                                      x-text="agent.status"></span>
                                            </td>
                                        </tr>
                                        <tr class="eb-expand-detail" x-show="open" x-cloak>
                                            <td colspan="8">
                                                <div class="eb-expand-detail-inner">
                                                    <div class="eb-expand-detail-header">
                                                        <svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                                        <span x-text="'Jobs on this agent (' + (agent.jobs ? agent.jobs.length : 0) + ')'"></span>
                                                    </div>
                                                    <template x-if="!agent.jobs || agent.jobs.length === 0">
                                                        <div class="eb-app-empty" style="padding: 12px 0;">
                                                            <div class="eb-app-empty-title">No jobs configured on this agent</div>
                                                        </div>
                                                    </template>
                                                    <template x-for="(job, jIdx) in (agent.jobs || [])" :key="agent.agent_uuid + '-job-' + jIdx">
                                                        <div class="eb-mini-job">
                                                            <div class="eb-mini-job-name" x-text="job.name"></div>
                                                            <div class="eb-mini-job-meta" x-text="job.dest_label"></div>
                                                            <div class="eb-mini-job-meta" x-text="job.mode"></div>
                                                            <div class="eb-mini-job-meta" x-text="job.schedule"></div>
                                                            <div class="eb-mini-job-meta" x-text="job.last_run"></div>
                                                            <div class="eb-mini-job-status">
                                                                <span class="eb-status-dot" :class="miniJobDotClass(job.status_tone)"></span>
                                                                <span :class="miniJobLabelClass(job.status_tone)" x-text="job.status_label"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </template>
                            </table>
                        </div>
                    </template>
                </div>

                <div class="eb-tab-body" x-show="activeTab === 'jobs'" x-cloak>
                    <div x-data="userDetailJobsApp()" data-e3backup-jobs-app class="space-y-6">
                        <div class="eb-subpanel overflow-visible">
                            <div class="space-y-3">
                                <div class="eb-table-toolbar eb-jobs-toolbar">
                                    <div class="eb-input-wrap eb-jobs-toolbar-search w-full xl:min-w-[18rem] xl:flex-1">
                                        <div class="eb-input-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                                            </svg>
                                        </div>
                                        <input type="text" placeholder="Search jobs…" class="eb-input eb-input-has-icon" x-model="searchQuery">
                                    </div>

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
                                                       placeholder="Search sources…"
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
                                    <button type="button" class="eb-pill" :class="statusFilter === 'all' ? 'is-active' : ''" @click="statusFilter='all'">All</button>
                                    <button type="button" class="eb-pill" :class="statusFilter === 'success' ? 'is-active' : ''" @click="statusFilter='success'">Success</button>
                                    <button type="button" class="eb-pill" :class="statusFilter === 'warning' ? 'is-active' : ''" @click="statusFilter='warning'">Warning</button>
                                    <button type="button" class="eb-pill" :class="statusFilter === 'failed' ? 'is-active' : ''" @click="statusFilter='failed'">Failed</button>
                                    <button type="button" class="eb-pill" :class="statusFilter === 'running' ? 'is-active' : ''" @click="statusFilter='running'">Running</button>
                                    <button type="button" class="eb-pill" :class="statusFilter === 'failed_recent' ? 'is-active' : ''" @click="statusFilter='failed_recent'">Failed Recently</button>
                                </div>

                                <div class="grid grid-cols-1 gap-6">
                                    <template x-if="loading">
                                        <div class="eb-card !p-8 text-center">
                                            <div class="inline-flex items-center gap-3 text-sm text-[var(--eb-text-muted)]">
                                                <span class="h-4 w-4 animate-spin rounded-full border-2 border-[color:var(--eb-info-border)] border-t-[color:var(--eb-info-icon)]"></span>
                                                Loading jobs…
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="!loading && filteredJobs.length === 0">
                                        <div class="eb-app-empty">
                                            <div class="eb-app-empty-title">No jobs for this user</div>
                                            <p class="eb-app-empty-copy">Use Create Job above to add a cloud or local agent backup. Enroll agents if you use local backups.</p>
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
                                                                    <span>Running…</span>
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
                                        <span x-text="deleteInProgress ? 'Deleting…' : 'Delete'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="eb-tab-body" x-show="activeTab === 'restore'" x-cloak>
                    {include file="{$smarty.const.ROOTDIR}/modules/addons/cloudstorage/templates/partials/e3backup_user_restore_tab.tpl"}
                </div>

                <div class="eb-tab-body" x-show="activeTab === 'vaults'" x-cloak>
                    <template x-if="!vaultsDetail().length">
                        <div class="eb-app-empty">
                            <div class="eb-app-empty-title">No vaults yet</div>
                            <p class="eb-app-empty-copy">Vaults appear when jobs target destination buckets in this user scope.</p>
                        </div>
                    </template>
                    <div class="eb-vault-grid" x-show="vaultsDetail().length">
                        <template x-for="vault in vaultsDetail()" :key="'vault-' + vault.id">
                            <div class="eb-vault-card">
                                <div class="eb-vault-card-header">
                                    <div class="eb-vault-icon">
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"/></svg>
                                    </div>
                                    <div>
                                        <a class="eb-vault-name eb-link"
                                           :href="'index.php?m=cloudstorage&page=buckets#bucketRow' + encodeURIComponent(vault.id)"
                                           x-text="vault.name"></a>
                                        <div class="eb-vault-provider" x-text="vault.provider_label"></div>
                                    </div>
                                </div>
                                <div class="eb-vault-stats">
                                    <div class="eb-vault-stat">
                                        <div class="eb-vault-stat-label">Storage used</div>
                                        <div class="eb-vault-stat-value" x-text="vault.storage_used_display"></div>
                                    </div>
                                    <div class="eb-vault-stat">
                                        <div class="eb-vault-stat-label">Bucket path</div>
                                        <div class="eb-vault-stat-value" x-text="vault.bucket_path"></div>
                                    </div>
                                    <div class="eb-vault-stat">
                                        <div class="eb-vault-stat-label">Created</div>
                                        <div class="eb-vault-stat-value" x-text="vault.created || '—'"></div>
                                    </div>
                                    <div class="eb-vault-stat">
                                        <div class="eb-vault-stat-label">Jobs using</div>
                                        <div class="eb-vault-stat-value" x-text="vault.jobs_using"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="eb-tab-body" x-show="activeTab === 'hyperv'" x-cloak>
                    <div class="space-y-6">
                        <template x-if="hypervMessage">
                            <div class="eb-alert eb-alert--success">
                                <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <div x-text="hypervMessage"></div>
                            </div>
                        </template>
                        <template x-if="hypervError">
                            <div class="eb-alert eb-alert--danger">
                                <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <div x-text="hypervError"></div>
                            </div>
                        </template>

                        <div class="eb-table-toolbar">
                            <div class="flex min-w-0 flex-1 flex-col gap-3 md:flex-row md:items-end">
                                <div x-show="(user.hyperv_jobs_count ?? 0) > 1" class="relative w-full md:max-w-sm" @click.away="hypervJobFilterMenuOpen = false">
                                    <label class="eb-field-label" id="e3-user-detail-hyperv-job-filter-label" for="e3-user-detail-hyperv-job-filter">Hyper-V job</label>
                                    <button type="button"
                                            id="e3-user-detail-hyperv-job-filter"
                                            class="eb-menu-trigger w-full"
                                            @click="hypervJobFilterMenuOpen = !hypervJobFilterMenuOpen"
                                            :aria-expanded="hypervJobFilterMenuOpen ? 'true' : 'false'"
                                            aria-haspopup="listbox"
                                            aria-controls="e3-user-detail-hyperv-job-filter-list"
                                            aria-labelledby="e3-user-detail-hyperv-job-filter-label">
                                        <span class="min-w-0 flex-1 truncate text-left" x-text="hypervJobFilterLabel()"></span>
                                        <svg class="h-4 w-4 shrink-0 transition-transform" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" :class="hypervJobFilterMenuOpen ? 'rotate-180' : ''">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <div x-show="hypervJobFilterMenuOpen"
                                         x-transition
                                         x-cloak
                                         id="e3-user-detail-hyperv-job-filter-list"
                                         role="listbox"
                                         aria-labelledby="e3-user-detail-hyperv-job-filter-label"
                                         class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-full overflow-hidden"
                                         style="display: none;">
                                        <div class="eb-menu-label">Filter by job</div>
                                        <div class="max-h-64 overflow-auto p-1">
                                            <button type="button"
                                                    role="option"
                                                    class="eb-menu-option"
                                                    :class="!hypervSelectedJobId ? 'is-active' : ''"
                                                    :aria-selected="!hypervSelectedJobId ? 'true' : 'false'"
                                                    @click="hypervSelectedJobId = ''; hypervJobFilterMenuOpen = false">
                                                All Hyper-V jobs
                                            </button>
                                            <template x-for="job in hypervJobs()" :key="'hyperv-job-filter-opt-' + job.job_id">
                                                <button type="button"
                                                        role="option"
                                                        class="eb-menu-option"
                                                        :class="String(hypervSelectedJobId) === String(job.job_id) ? 'is-active' : ''"
                                                        :aria-selected="String(hypervSelectedJobId) === String(job.job_id) ? 'true' : 'false'"
                                                        @click="hypervSelectedJobId = String(job.job_id || ''); hypervJobFilterMenuOpen = false">
                                                    <span x-text="job.name || 'Hyper-V Job'"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="eb-btn eb-btn-info eb-btn-sm" @click="refreshVMDiscovery()" :disabled="hypervRefreshing || !hypervJobs().length || ((user.hyperv_jobs_count ?? 0) > 1 && !hypervSelectedJobId)">
                                    <svg x-show="!hypervRefreshing" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <svg x-show="hypervRefreshing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span x-text="hypervRefreshing ? 'Refreshing…' : 'Refresh VM Discovery'"></span>
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="eb-stat-card">
                                <div class="eb-stat-label">Tracked VMs</div>
                                <div class="eb-stat-value" x-text="hypervFilteredVms().length"></div>
                            </div>
                            <div class="eb-stat-card">
                                <div class="eb-stat-label">Active VMs</div>
                                <div class="eb-stat-value" x-text="hypervActiveVmCount()"></div>
                            </div>
                            <div class="eb-stat-card">
                                <div class="eb-stat-label">RCT Enabled</div>
                                <div class="eb-stat-value" x-text="hypervRctCount()"></div>
                            </div>
                        </div>

                        <template x-if="hypervFilteredVms().length">
                            <div class="eb-table-shell">
                                <div class="eb-card-header eb-card-header--divided !mb-0">
                                    <div>
                                        <h2 class="eb-card-title">Virtual Machines</h2>
                                        <p class="eb-card-subtitle">Review discovered VMs, restore disks, and control whether each machine is included in backups.</p>
                                    </div>
                                </div>
                                <table class="eb-table">
                                    <thead>
                                        <tr>
                                            <th>VM Name</th>
                                            <th>Type</th>
                                            <th>Disks</th>
                                            <th>RCT</th>
                                            <th>Last Backup</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="vm in hypervFilteredVms()" :key="'hyperv-vm-' + vm.id">
                                            <tr>
                                                <td class="eb-table-primary">
                                                    <div class="flex items-center gap-3">
                                                        <span class="eb-icon-box eb-icon-box--sm" :class="vm.is_linux ? 'eb-icon-box--orange' : 'eb-icon-box--info'">
                                                            <svg x-show="vm.is_linux" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path d="M12.503 18.668s-.218.125-.492.125c-.274 0-.493-.125-.493-.125-.562 0-.887.875-.887 1.5 0 .413.35.82.713 1.083.413.3 1.113.625 1.167.625l.167-.625c.054 0 .754-.325 1.167-.625.363-.263.713-.67.713-1.083 0-.625-.325-1.5-.887-1.5z"/>
                                                            </svg>
                                                            <svg x-show="!vm.is_linux" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path d="M0 3.449L9.75 2.1v9.451H0m10.949-9.602L24 0v11.4H10.949M0 12.6h9.75v9.451L0 20.699M10.949 12.6H24V24l-12.9-1.801"/>
                                                            </svg>
                                                        </span>
                                                        <div class="min-w-0">
                                                            <div class="font-medium text-[var(--eb-text-primary)]" x-text="vm.vm_name || 'Unnamed VM'"></div>
                                                            <div x-show="(user.hyperv_jobs_count ?? 0) > 1" class="eb-type-caption text-[var(--eb-text-secondary)]" x-text="vm.job_name || 'Hyper-V Job'"></div>
                                                            <div class="eb-table-mono mt-1" x-text="(vm.vm_guid || '').length > 20 ? ((vm.vm_guid || '').slice(0, 20) + '...') : (vm.vm_guid || '—')"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-[var(--eb-text-secondary)]" x-text="(vm.is_linux ? 'Linux' : 'Windows') + ' Gen' + (vm.generation || '—')"></td>
                                                <td class="text-[var(--eb-text-secondary)]" x-text="vm.disk_count ?? 0"></td>
                                                <td>
                                                    <span class="eb-badge" :class="vm.rct_enabled ? 'eb-badge--success' : 'eb-badge--neutral'" x-text="vm.rct_enabled ? 'Enabled' : 'Disabled'"></span>
                                                </td>
                                                <td>
                                                    <template x-if="vm.last_backup">
                                                        <div class="space-y-1">
                                                            <span class="eb-badge" :class="vm.last_backup && vm.last_backup.type === 'Full' ? 'eb-badge--info' : 'eb-badge--warning'" x-text="vm.last_backup ? vm.last_backup.type : ''"></span>
                                                            <div class="eb-type-caption text-[var(--eb-text-muted)]" x-text="vm.last_backup ? formatDate(vm.last_backup.created_at) : ''"></div>
                                                        </div>
                                                    </template>
                                                    <template x-if="!vm.last_backup">
                                                        <span class="eb-type-caption text-[var(--eb-text-muted)]">Never</span>
                                                    </template>
                                                </td>
                                                <td>
                                                    <span class="eb-badge" :class="vm.backup_enabled ? 'eb-badge--success eb-badge--dot' : 'eb-badge--neutral'" x-text="vm.backup_enabled ? 'Active' : 'Excluded'"></span>
                                                </td>
                                                <td>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <a :href="hypervRestoreHref(vm)" class="eb-btn eb-btn-info eb-btn-xs" title="Restore VM Disks">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/>
                                                            </svg>
                                                            <span>Restore</span>
                                                        </a>
                                                        <button type="button" class="eb-btn eb-btn-xs" :class="vm.backup_enabled ? 'eb-btn-secondary' : 'eb-btn-success'" @click="toggleVMBackup(vm.id, !vm.backup_enabled)">
                                                            <span x-text="vm.backup_enabled ? 'Exclude' : 'Include'"></span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <template x-if="!hypervFilteredVms().length">
                            <div class="eb-app-empty !py-12">
                                <span class="eb-icon-box eb-icon-box--lg eb-icon-box--default mx-auto">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"/>
                                    </svg>
                                </span>
                                <div class="eb-app-empty-title mt-4">No VMs discovered yet</div>
                                <p class="eb-app-empty-copy">Refresh VM discovery to scan the assigned Hyper-V host and sync tracked virtual machines.</p>
                                <div class="mt-6">
                                    <button type="button" class="eb-btn eb-btn-info eb-btn-sm" @click="refreshVMDiscovery()" :disabled="hypervRefreshing || !hypervJobs().length || ((user.hyperv_jobs_count ?? 0) > 1 && !hypervSelectedJobId)">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 0 0 4.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 0 1-15.357-2m15.357 2H15"/>
                                        </svg>
                                        <span>Refresh VM Discovery</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="eb-tab-body" x-show="activeTab === 'billing'" x-cloak>
                    <div class="eb-subpanel eb-billing-tenant-card">
                        <div class="eb-billing-tenant-header">Linked tenant</div>
                        <div class="eb-billing-tenant-name" x-text="billingTenantName()"></div>
                        <p class="eb-billing-tenant-meta" x-text="billingTenantSubtitle()"></p>
                        <div class="eb-billing-tenant-actions" style="margin-top: 12px;">
                            {if $isMspClient}
                            <a href="index.php?m=eazybackup&a=ph-tenants-manage" class="eb-btn eb-btn-secondary eb-btn-xs">View tenants</a>
                            {/if}
                            <a href="index.php?m=cloudstorage&page=e3backup&view=users" class="eb-btn eb-btn-ghost eb-btn-xs">User directory</a>
                        </div>
                    </div>
                    <div style="font-size: 10.5px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--eb-text-muted); margin-bottom: 10px;">Billable resources</div>
                    <div class="eb-billing-kpi-grid">
                        <div class="eb-billing-kpi">
                            <div class="eb-billing-kpi-value" x-text="billingKpis().agents"></div>
                            <div class="eb-billing-kpi-label">Agents</div>
                        </div>
                        <div class="eb-billing-kpi">
                            <div class="eb-billing-kpi-value">
                                <template x-if="billingKpis().storage_display === '—'">
                                    <span>—</span>
                                </template>
                                <template x-if="billingKpis().storage_display !== '—'">
                                    <span>
                                        <span x-text="billingKpis().storage_display"></span><span class="eb-billing-kpi-unit"> GB</span>
                                    </span>
                                </template>
                            </div>
                            <div class="eb-billing-kpi-label">Storage used</div>
                        </div>
                        <div class="eb-billing-kpi">
                            <div class="eb-billing-kpi-value" x-text="billingKpis().disk_image_jobs"></div>
                            <div class="eb-billing-kpi-label">Disk image jobs</div>
                        </div>
                        <div class="eb-billing-kpi">
                            <div class="eb-billing-kpi-value" x-text="billingKpis().hyperv_guests"></div>
                            <div class="eb-billing-kpi-label">Hyper-V guests</div>
                        </div>
                        <div class="eb-billing-kpi">
                            <div class="eb-billing-kpi-value" x-text="billingKpis().proxmox_guests"></div>
                            <div class="eb-billing-kpi-label">Proxmox guests</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

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
                                    <option value="">Loading runs…</option>
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

    <div x-show="showResetPasswordModal"
         x-cloak
         class="fixed inset-0 z-[2200] flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="closeResetPasswordModal()">
        <div class="eb-modal-backdrop absolute inset-0" @click="closeResetPasswordModal()"></div>
        <div class="eb-modal eb-modal--confirm relative z-10"
             @click.stop
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <div class="eb-modal-header">
                <div>
                    <h3 class="eb-modal-title">Reset Password</h3>
                    <p class="eb-modal-subtitle" x-text="user.username || 'Current user'"></p>
                </div>
                <button type="button" class="eb-modal-close" @click="closeResetPasswordModal()">&times;</button>
            </div>
            <div class="eb-modal-body">
                <form @submit.prevent="resetPassword()" class="eb-overview-form-stack">
                    <div>
                        <label class="eb-field-label" for="e3-user-detail-modal-pw">New password</label>
                        <input id="e3-user-detail-modal-pw" type="password" x-model="passwordForm.password" class="eb-input" :class="passwordErrors.password ? 'is-error' : ''">
                        <p class="eb-field-error" x-show="passwordErrors.password" x-text="passwordErrors.password"></p>
                    </div>
                    <div>
                        <label class="eb-field-label" for="e3-user-detail-modal-pw2">Confirm password</label>
                        <input id="e3-user-detail-modal-pw2" type="password" x-model="passwordForm.password_confirm" class="eb-input" :class="passwordErrors.password_confirm ? 'is-error' : ''">
                        <p class="eb-field-error" x-show="passwordErrors.password_confirm" x-text="passwordErrors.password_confirm"></p>
                    </div>
                </form>
            </div>
            <div class="eb-modal-footer">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeResetPasswordModal()">Cancel</button>
                <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="resetPassword()">Reset Password</button>
            </div>
        </div>
    </div>

    <div x-show="showSuspendModal"
         x-cloak
         class="fixed inset-0 z-[2200] flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="closeSuspendModal()">
        <div class="eb-modal-backdrop absolute inset-0" @click="closeSuspendModal()"></div>
        <div class="eb-modal eb-modal--confirm relative z-10"
             @click.stop
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <div class="eb-modal-header">
                <div class="flex items-start gap-3">
                    <div class="eb-icon-box eb-icon-box--warning eb-icon-box--sm">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <rect x="6" y="4" width="4" height="16"></rect>
                            <rect x="14" y="4" width="4" height="16"></rect>
                        </svg>
                    </div>
                    <div>
                        <h3 class="eb-modal-title">Suspend User?</h3>
                        <p class="eb-modal-subtitle" x-text="user.username || 'Current user'"></p>
                    </div>
                </div>
                <button type="button" class="eb-modal-close" @click="closeSuspendModal()">&times;</button>
            </div>
            <div class="eb-modal-body">
                <div class="eb-alert eb-alert--warning">
                    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <div>Suspending this user will stop scheduled backups and deny manual backup runs. The agent will remain connected and the user can still log in.</div>
                </div>
                <p class="eb-type-body mt-4">This is reversible and is typically used to pause backup activity without removing the account.</p>
            </div>
            <div class="eb-modal-footer">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeSuspendModal()">Cancel</button>
                <button type="button" class="eb-btn eb-btn-warning eb-btn-sm" @click="confirmSuspendUser()">Suspend User</button>
            </div>
        </div>
    </div>

    <div x-show="showDeleteModal"
         x-cloak
         class="fixed inset-0 z-[2200] flex items-center justify-center p-4"
         style="display: none;"
         @keydown.escape.window="closeDeleteModal()">
        <div class="eb-modal-backdrop absolute inset-0" @click="closeDeleteModal()"></div>
        <div class="eb-modal eb-modal--confirm relative z-10"
             @click.stop
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">
            <div class="eb-modal-header">
                <div class="flex items-start gap-3">
                    <div class="eb-icon-box eb-icon-box--danger eb-icon-box--sm">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="eb-modal-title">Delete User Permanently?</h3>
                        <p class="eb-modal-subtitle" x-text="user.username || 'Current user'"></p>
                    </div>
                </div>
                <button type="button" class="eb-modal-close" @click="closeDeleteModal()">&times;</button>
            </div>
            <div class="eb-modal-body">
                <div class="eb-alert eb-alert--danger">
                    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                    <div>This action is permanent and cannot be undone. All associated data for this user will be destroyed.</div>
                </div>

                <div class="mt-4 text-[10.5px] font-bold uppercase tracking-[0.12em] text-[var(--eb-text-muted)]">Impact Summary</div>
                <div class="eb-impact-list">
                    <div class="eb-impact-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                        <span class="count" x-text="deleteImpactSummary().agents"></span>
                        <span>Agents</span>
                    </div>
                    <div class="eb-impact-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                        <span class="count" x-text="deleteImpactSummary().jobs"></span>
                        <span>Backup Jobs</span>
                    </div>
                    <div class="eb-impact-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"></path>
                        </svg>
                        <span class="count" x-text="deleteImpactSummary().vaults"></span>
                        <span>Storage Vaults</span>
                    </div>
                    <div class="eb-impact-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                            <line x1="9" y1="9" x2="9.01" y2="9"></line>
                            <line x1="15" y1="9" x2="15.01" y2="9"></line>
                        </svg>
                        <span class="count" x-text="deleteImpactSummary().storage"></span>
                        <span>GB backup data</span>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="eb-type-body">Type <span class="font-semibold text-[var(--eb-text-primary)]" x-text="user.username || ''"></span> to confirm deletion:</div>
                    <input type="text"
                           class="eb-confirm-input"
                           x-model="deleteConfirmText"
                           placeholder="Type username to confirm...">
                </div>
            </div>
            <div class="eb-modal-footer">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeDeleteModal()">Cancel</button>
                <button type="button"
                        class="eb-btn eb-btn-danger-solid eb-btn-sm"
                        :disabled="!canConfirmDelete()"
                        @click="confirmDeleteUser()">
                    Delete User
                </button>
            </div>
        </div>
    </div>
</div>
{/capture}

<style>
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

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='users'
    ebE3Title="{$user->username|escape:'html'}"
    ebE3Description='Account details, usage, and administration for this username.'
    ebE3Icon=$ebE3Icon
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}

{literal}
<script>
function e3backupGetUserDetailApp() {
    const root = document.querySelector('[data-e3backup-user-detail-app]');
    if (!root) return null;
    try {
        if (window.Alpine && typeof window.Alpine.$data === 'function') {
            return window.Alpine.$data(root);
        }
    } catch (e) {}
    if (root.__x && root.__x.$data) return root.__x.$data;
    if (root._x_dataStack && root._x_dataStack.length) return root._x_dataStack[0];
    return null;
}

function e3backupReloadUserDetail() {
    const app = e3backupGetUserDetailApp();
    if (app && typeof app.loadUser === 'function') {
        return app.loadUser();
    }
    return null;
}

function backupUserDetailApp() {
    return {
        userId: {/literal}{if $user->public_id}{$user->public_id|@json_encode nofilter}{else}{$user->id|intval}{/if}{literal},
        isMspClient: {/literal}{if $isMspClient}true{else}false{/if}{literal},
        canonicalTenants: {/literal}{$canonicalTenants|@json_encode nofilter}{literal} || [],
        csrfToken: {/literal}{$csrfToken|@json_encode nofilter}{literal} || '',
        loading: true,
        activeTab: 'overview',
        initialRestoreJobId: {/literal}{$initial_restore_job_id|default:''|@json_encode nofilter}{literal},
        tabMenuOpen: false,
        updating: false,
        showResetPasswordModal: false,
        showSuspendModal: false,
        showDeleteModal: false,
        deleteConfirmText: '',
        tenantSearch: '',
        user: {
            id: {/literal}{$user->id|intval}{literal},
            username: {/literal}{$user->username|@json_encode nofilter}{literal},
            email: {/literal}{$user->email|@json_encode nofilter}{literal},
            tenant_id: {/literal}{$user->tenant_public_id|@json_encode nofilter}{literal},
            tenant_public_id: {/literal}{$user->tenant_public_id|@json_encode nofilter}{literal},
            tenant_name: {/literal}{$user->tenant_name|@json_encode nofilter}{literal},
            canonical_tenant_id: null,
            canonical_tenant_name: null,
            is_canonical_managed: false,
            status: {/literal}{$user->status|@json_encode nofilter}{literal},
            backup_type: {/literal}{if $user->backup_type}{$user->backup_type|@json_encode nofilter}{else}'both'{/if}{literal},
            agents_detail: [],
            vaults_detail: [],
            hyperv_jobs_count: 0,
            hyperv_jobs: [],
            hyperv_vms: [],
            billing_kpis: { agents: 0, storage_display: '—', disk_image_jobs: 0, hyperv_guests: 0, proxmox_guests: 0 },
            vaults_count: 0,
            jobs_count: 0,
            agents_count: 0,
            online_devices: 0,
            last_backup_at: null
        },
        updateForm: {
            username: '',
            email: '',
            tenant_id: '',
            status: 'active'
        },
        updateErrors: {},
        updateMessage: '',
        updateError: '',
        passwordForm: {
            password: '',
            password_confirm: ''
        },
        passwordErrors: {},
        passwordMessage: '',
        passwordError: '',
        deleteError: '',
        hypervSelectedJobId: '',
        hypervJobFilterMenuOpen: false,
        hypervRefreshing: false,
        hypervMessage: '',
        hypervError: '',

        init() {
            this.activeTab = this.resolveInitialTab();
            this.loadUser();
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 922) {
                    this.tabMenuOpen = false;
                }
            });
            window.addEventListener('hashchange', () => {
                this.activeTab = this.resolveInitialTab();
            });
            window.addEventListener('eb-e3-user-restore-requested', (event) => {
                const detail = event && event.detail ? event.detail : {};
                const targetUserId = detail.backupUserRouteId !== undefined && detail.backupUserRouteId !== null
                    ? String(detail.backupUserRouteId)
                    : '';
                if (targetUserId && String(targetUserId) !== String(this.userId)) {
                    return;
                }
                this.openRestoreTab(detail.jobId || '');
            });
        },

        selectUserDetailTab(tab) {
            this.activeTab = tab;
            this.tabMenuOpen = false;
            this.hypervJobFilterMenuOpen = false;
            try {
                const url = new URL(window.location.href);
                url.hash = tab;
                window.history.replaceState({}, '', url.toString());
            } catch (e) {
            }
        },

        resolveInitialTab() {
            const allowed = ['overview', 'agents', 'jobs', 'restore', 'vaults', 'hyperv', 'billing'];
            const hash = String(window.location.hash || '').replace(/^#/, '').toLowerCase();
            if (allowed.includes(hash)) {
                if (hash === 'agents' && String(this.user.backup_type || 'both') === 'cloud_only') {
                    return 'overview';
                }
                if (hash === 'hyperv' && !this.hasHypervTab()) {
                    return 'overview';
                }
                return hash;
            }
            if (this.initialRestoreJobId) {
                return 'restore';
            }
            return 'overview';
        },

        openRestoreTab(jobId = '') {
            this.selectUserDetailTab('restore');
            window.dispatchEvent(new CustomEvent('eb-e3-restore-filter-job', {
                detail: {
                    jobId: jobId ? String(jobId) : '',
                    backupUserRouteId: String(this.userId || '')
                }
            }));
        },

        normalizeUserStatus(status) {
            return String(status || '').toLowerCase() === 'active' ? 'active' : 'disabled';
        },

        isUserSuspended() {
            return this.normalizeUserStatus(this.user.status) !== 'active';
        },

        userStatusLabel() {
            return this.isUserSuspended() ? 'Suspended' : 'Active';
        },

        handleLoginAsUser() {
            window.dispatchEvent(new CustomEvent('eb-e3-user-login-requested', {
                detail: {
                    userId: this.userId,
                    username: this.user.username || ''
                }
            }));
        },

        openResetPasswordModal() {
            this.passwordErrors = {};
            this.passwordForm.password = '';
            this.passwordForm.password_confirm = '';
            this.showResetPasswordModal = true;
        },

        closeResetPasswordModal() {
            this.showResetPasswordModal = false;
            this.passwordErrors = {};
        },

        openSuspendModal() {
            this.showSuspendModal = true;
        },

        closeSuspendModal() {
            this.showSuspendModal = false;
        },

        openDeleteModal() {
            this.deleteConfirmText = '';
            this.showDeleteModal = true;
        },

        closeDeleteModal() {
            this.deleteConfirmText = '';
            this.showDeleteModal = false;
        },

        deleteImpactSummary() {
            return {
                agents: Number(this.user.agents_count || 0),
                jobs: Number(this.user.jobs_count || 0),
                vaults: Number(this.user.vaults_count || 0),
                storage: this.billingKpis().storage_display || '—'
            };
        },

        canConfirmDelete() {
            return this.deleteConfirmText.trim() === (this.user.username || '');
        },

        confirmSuspendUser() {
            this.user.status = 'disabled';
            this.updateForm.status = 'disabled';
            this.closeSuspendModal();
            window.dispatchEvent(new CustomEvent('eb-e3-user-suspend-requested', {
                detail: {
                    userId: this.userId,
                    username: this.user.username || ''
                }
            }));
        },

        reactivateUserFromAction() {
            this.user.status = 'active';
            this.updateForm.status = 'active';
            window.dispatchEvent(new CustomEvent('eb-e3-user-reactivate-requested', {
                detail: {
                    userId: this.userId,
                    username: this.user.username || ''
                }
            }));
        },

        confirmDeleteUser() {
            if (!this.canConfirmDelete()) {
                return;
            }
            this.closeDeleteModal();
            window.dispatchEvent(new CustomEvent('eb-e3-user-delete-requested', {
                detail: {
                    userId: this.userId,
                    username: this.user.username || ''
                }
            }));
        },

        userDetailTabTriggerLabel() {
            const map = {
                overview: 'Overview',
                agents: 'Agents',
                jobs: 'Jobs',
                restore: 'Restore',
                vaults: 'Vaults',
                hyperv: 'Hyper-V',
                billing: 'Billing'
            };
            const id = this.activeTab;
            const name = map[id] || 'Overview';
            if (id === 'agents') {
                return `${name} (${this.user.agents_count ?? 0})`;
            }
            if (id === 'jobs') {
                return `${name} (${this.user.jobs_count ?? 0})`;
            }
            if (id === 'vaults') {
                return `${name} (${this.user.vaults_count ?? 0})`;
            }
            if (id === 'hyperv') {
                return `${name} (${this.hypervFilteredVms().length})`;
            }
            return name;
        },

        agentsDetail() {
            return Array.isArray(this.user.agents_detail) ? this.user.agents_detail : [];
        },

        vaultsDetail() {
            return Array.isArray(this.user.vaults_detail) ? this.user.vaults_detail : [];
        },

        hasHypervTab() {
            return Number(this.user.hyperv_jobs_count || 0) > 0;
        },

        hypervJobs() {
            return Array.isArray(this.user.hyperv_jobs) ? this.user.hyperv_jobs : [];
        },

        hypervJobFilterLabel() {
            const id = String(this.hypervSelectedJobId || '');
            if (!id) {
                return 'All Hyper-V jobs';
            }
            const job = this.hypervJobs().find((j) => String(j.job_id || '') === id);
            return job && job.name ? job.name : 'Hyper-V Job';
        },

        hypervFilteredVms() {
            const vms = Array.isArray(this.user.hyperv_vms) ? this.user.hyperv_vms : [];
            const selectedJobId = String(this.hypervSelectedJobId || '');
            if (!selectedJobId) {
                return vms;
            }
            return vms.filter((vm) => String(vm.job_id || '') === selectedJobId);
        },

        hypervActiveVmCount() {
            return this.hypervFilteredVms().filter((vm) => !!vm.backup_enabled).length;
        },

        hypervRctCount() {
            return this.hypervFilteredVms().filter((vm) => !!vm.rct_enabled).length;
        },

        hypervRestoreHref(vm) {
            const vmId = vm && vm.id ? encodeURIComponent(vm.id) : '';
            return 'index.php?m=cloudstorage&page=e3backup&view=hyperv_restore&vm_id=' + vmId;
        },

        sleep(ms) {
            return new Promise((resolve) => window.setTimeout(resolve, ms));
        },

        async pollHypervCommand(commandId, timeoutMs = 60000) {
            const started = Date.now();
            let delay = 600;
            while (Date.now() - started < timeoutMs) {
                const response = await fetch('modules/addons/cloudstorage/api/agent_poll_hyperv_vms.php?command_id=' + encodeURIComponent(commandId));
                const data = await response.json();
                if (data.status !== 'pending') {
                    return data;
                }
                await this.sleep(delay);
                delay = Math.min(Math.round(delay * 1.6), 4000);
            }
            return { status: 'timeout', message: 'VM discovery timed out.' };
        },

        async refreshVMDiscovery(jobId = '') {
            this.hypervMessage = '';
            this.hypervError = '';

            const explicitJobId = String(jobId || this.hypervSelectedJobId || '');
            if (!explicitJobId && this.hypervJobs().length > 1) {
                this.hypervError = 'Select a Hyper-V job before refreshing discovery.';
                return;
            }

            const targetJob = explicitJobId
                ? this.hypervJobs().find((job) => String(job.job_id || '') === explicitJobId)
                : (this.hypervJobs()[0] || null);

            if (!targetJob) {
                this.hypervError = 'No Hyper-V job is available for discovery refresh.';
                return;
            }

            if (!String(targetJob.agent_uuid || '').trim()) {
                this.hypervError = 'This Hyper-V job does not have an assigned agent.';
                return;
            }

            this.hypervRefreshing = true;

            try {
                const queueResponse = await fetch('modules/addons/cloudstorage/api/agent_list_hyperv_vms.php?agent_uuid=' + encodeURIComponent(targetJob.agent_uuid));
                const queueData = await queueResponse.json();

                let liveVmData = Array.isArray(queueData.vms) ? queueData.vms : [];
                if (queueData.status === 'pending' && queueData.command_id) {
                    const pollData = await this.pollHypervCommand(queueData.command_id);
                    if (pollData.status === 'timeout') {
                        throw new Error(pollData.message || 'VM discovery is taking longer than expected.');
                    }
                    if (pollData.status !== 'success') {
                        throw new Error(pollData.message || 'Failed to refresh VM discovery.');
                    }
                    liveVmData = Array.isArray(pollData.vms) ? pollData.vms : [];
                } else if (queueData.status !== 'success') {
                    throw new Error(queueData.message || 'Failed to queue VM discovery.');
                }

                const persistBody = new URLSearchParams({
                    job_id: String(targetJob.job_id || ''),
                    vms_json: JSON.stringify(liveVmData),
                });
                const persistResponse = await fetch('modules/addons/cloudstorage/api/cloudbackup_hyperv_refresh_vm_discovery.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: persistBody,
                });
                const persistData = await persistResponse.json();
                if (persistData.status !== 'success') {
                    throw new Error(persistData.message || 'Failed to sync discovered VMs.');
                }

                await this.loadUser();
                this.hypervMessage = `${targetJob.name || 'Hyper-V job'} refreshed (${Number(persistData.created || 0)} new, ${Number(persistData.updated || 0)} updated).`;
            } catch (error) {
                this.hypervError = error && error.message ? error.message : 'Failed to refresh VM discovery.';
            }

            this.hypervRefreshing = false;
        },

        async toggleVMBackup(vmId, enable) {
            this.hypervMessage = '';
            this.hypervError = '';

            try {
                const response = await fetch('modules/addons/cloudstorage/api/cloudbackup_hyperv_toggle_vm.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        vm_id: String(vmId),
                        enabled: enable ? '1' : '0',
                    }),
                });
                const data = await response.json();
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Failed to update VM backup status.');
                }

                await this.loadUser();
                this.hypervMessage = enable ? 'VM included in backups.' : 'VM excluded from backups.';
            } catch (error) {
                this.hypervError = error && error.message ? error.message : 'Failed to update VM backup status.';
            }
        },

        billingKpis() {
            return this.user.billing_kpis || { agents: 0, storage_display: '—', disk_image_jobs: 0, hyperv_guests: 0, proxmox_guests: 0 };
        },

        get filteredTenants() {
            const query = this.tenantSearch.trim().toLowerCase();
            if (!query) return this.canonicalTenants;
            return this.canonicalTenants.filter((tenant) => (tenant.name || '').toLowerCase().includes(query));
        },

        tenantSummaryLabel() {
            if (this.isMspClient) {
                const t = this.user.canonical_tenant_name || this.user.tenant_name;
                return 'Tenant: ' + (t || 'Direct');
            }
            return 'Tenant: Direct';
        },

        billingTenantName() {
            if (this.isMspClient) {
                return this.user.canonical_tenant_name || this.user.tenant_name || 'Direct (no linked tenant)';
            }
            return 'Direct';
        },

        billingTenantSubtitle() {
            if (this.isMspClient && this.user.canonical_tenant_name) {
                return 'MSP billing tenant — usage shown for this backup user scope';
            }
            return 'End-user scope — billing KPIs reflect agents and jobs tied to this username.';
        },

        updateTenantLabel() {
            if (!this.updateForm.tenant_id) return 'Direct (No Tenant)';
            const tenant = this.canonicalTenants.find((item) => String(item.public_id || item.id) === String(this.updateForm.tenant_id));
            return tenant ? tenant.name : 'Select tenant';
        },

        userInitials(row) {
            const raw = row && row.username ? String(row.username).trim() : '';
            if (!raw) return '?';
            const cleaned = raw.replace(/[^a-zA-Z0-9]+/g, ' ').trim();
            const parts = cleaned.split(/\s+/).filter(Boolean);
            if (parts.length >= 2) {
                return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase().slice(0, 2);
            }
            return raw.slice(0, 2).toUpperCase();
        },

        formatDate(value) {
            if (!value) return 'Never';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString();
        },

        formatDateShort(value) {
            if (!value) return 'Never';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleDateString();
        },

        miniJobDotClass(tone) {
            const t = (tone || '').toLowerCase();
            if (t === 'active' || t === 'success' || t === 'running') return 'eb-status-dot--active';
            if (t === 'paused' || t === 'warning') return 'eb-status-dot--warning';
            if (t === 'deleted' || t === 'disabled') return 'eb-status-dot--inactive';
            return 'eb-status-dot--error';
        },

        miniJobLabelClass(tone) {
            const t = (tone || '').toLowerCase();
            if (t === 'active' || t === 'success' || t === 'running') return 'eb-connection-status--online';
            if (t === 'paused' || t === 'warning') return 'eb-type-caption';
            if (t === 'deleted' || t === 'disabled') return 'eb-type-disabled';
            return 'eb-connection-status--offline';
        },

        assignFormsFromUser() {
            this.updateForm.username = this.user.username || '';
            this.updateForm.email = this.user.email || '';
            this.updateForm.tenant_id = this.user.canonical_tenant_id ? String(this.user.canonical_tenant_id) : '';
            this.updateForm.status = this.normalizeUserStatus(this.user.status);
        },

        shouldSendCanonicalTenantOnUpdate() {
            if (!this.isMspClient) return false;
            if (this.updateForm.tenant_id) return true;
            return !!this.user.is_canonical_managed;
        },

        async loadUser() {
            this.loading = true;
            try {
                const response = await fetch('modules/addons/cloudstorage/api/e3backup_user_get.php?user_id=' + encodeURIComponent(this.userId));
                const data = await response.json();
                if (data.status === 'success' && data.user) {
                    this.user = data.user;
                    if (this.hypervSelectedJobId && !this.hypervJobs().some((job) => String(job.job_id || '') === String(this.hypervSelectedJobId))) {
                        this.hypervSelectedJobId = '';
                    }
                    this.assignFormsFromUser();
                    if (this.activeTab === 'hyperv' && !this.hasHypervTab()) {
                        this.selectUserDetailTab('overview');
                    }
                    if (typeof e3backupReloadJobs === 'function') {
                        e3backupReloadJobs();
                    }
                    window.dispatchEvent(new CustomEvent('eb-e3-user-detail-loaded', {
                        detail: {
                            userId: String(this.userId || '')
                        }
                    }));
                } else {
                    this.updateError = data.message || 'Failed to load user.';
                }
            } catch (error) {
                this.updateError = 'Failed to load user.';
            }
            this.loading = false;
        },

        validateUpdate() {
            this.updateErrors = {};
            if (!this.updateForm.username) {
                this.updateErrors.username = 'Username is required.';
            } else if (!/^[A-Za-z0-9._-]{3,64}$/.test(this.updateForm.username)) {
                this.updateErrors.username = 'Use 3-64 characters with letters, numbers, dots, underscores, or hyphens.';
            }
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!this.updateForm.email) {
                this.updateErrors.email = 'Email is required.';
            } else if (!emailPattern.test(this.updateForm.email)) {
                this.updateErrors.email = 'Please enter a valid email address.';
            }
            return Object.keys(this.updateErrors).length === 0;
        },

        async updateUser() {
            this.updateMessage = '';
            this.updateError = '';
            if (!this.validateUpdate()) return;

            this.updating = true;
            try {
                const body = new URLSearchParams({
                    user_id: String(this.userId),
                    username: this.updateForm.username,
                    email: this.updateForm.email,
                    status: this.updateForm.status
                });
                body.set('token', this.csrfToken);
                if (this.isMspClient && this.shouldSendCanonicalTenantOnUpdate()) {
                    body.set('canonical_tenant_id', this.updateForm.tenant_id ? this.updateForm.tenant_id : 'direct');
                }

                const response = await fetch('modules/addons/cloudstorage/api/e3backup_user_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.updateMessage = data.message || 'User updated successfully.';
                    this.updateErrors = {};
                    await this.loadUser();
                } else {
                    this.updateError = data.message || 'Failed to update user.';
                    this.updateErrors = data.errors || {};
                    if (this.updateErrors.canonical_tenant_id && !this.updateErrors.tenant_id) {
                        this.updateErrors.tenant_id = this.updateErrors.canonical_tenant_id;
                    }
                }
            } catch (error) {
                this.updateError = 'Failed to update user.';
            }
            this.updating = false;
        },

        async upgradeBackupType(newType) {
            this.updateMessage = '';
            this.updateError = '';
            this.updating = true;
            try {
                const body = new URLSearchParams({
                    user_id: String(this.userId),
                    backup_type: newType
                });
                body.set('token', this.csrfToken);
                const response = await fetch('modules/addons/cloudstorage/api/e3backup_user_update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.updateMessage = data.message || 'Backup type updated.';
                    await this.loadUser();
                } else {
                    this.updateError = data.message || 'Failed to update backup type.';
                }
            } catch (error) {
                this.updateError = 'Failed to update backup type.';
            }
            this.updating = false;
        },

        validatePassword() {
            this.passwordErrors = {};
            if (!this.passwordForm.password) {
                this.passwordErrors.password = 'Password is required.';
            } else if (this.passwordForm.password.length < 8) {
                this.passwordErrors.password = 'Password must be at least 8 characters.';
            }
            if (!this.passwordForm.password_confirm) {
                this.passwordErrors.password_confirm = 'Please confirm the password.';
            } else if (this.passwordForm.password !== this.passwordForm.password_confirm) {
                this.passwordErrors.password_confirm = 'Password confirmation does not match.';
            }
            return Object.keys(this.passwordErrors).length === 0;
        },

        resetPassword() {
            this.passwordMessage = '';
            this.passwordError = '';
            if (!this.validatePassword()) return;
            this.closeResetPasswordModal();
            window.dispatchEvent(new CustomEvent('eb-e3-user-reset-password-requested', {
                detail: {
                    userId: this.userId,
                    username: this.user.username || ''
                }
            }));
        }
    };
}
</script>
{/literal}

{include file="{$smarty.const.ROOTDIR}/modules/addons/cloudstorage/templates/partials/e3backup_jobs_client_script.tpl" userDetailJobsScopeId=$user->public_id|default:$user->id}
