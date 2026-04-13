{capture assign=ebE3Actions}
    <button type="button"
            onclick="window.dispatchEvent(new CustomEvent('eb-e3-user-create-open'))"
            class="eb-btn eb-btn-primary eb-btn-sm">
        Add User
    </button>
{/capture}

{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">       
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
        </svg>

    </span>
{/capture}

{capture assign=ebE3UsersBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Users</span>
    </div>
{/capture}

{capture assign=ebE3UsersHeaderActions}
    <span class="eb-badge eb-badge--neutral" x-text="loading ? 'Loading users' : (filteredUsers().length + ' users')"></span>
{/capture}

{capture assign=ebE3Content}
<div x-data="backupUsersApp()" @eb-e3-user-create-open.window="openCreateModal()" class="eb-section-stack">
    {include file="$template/includes/ui/page-header.tpl"
        ebBreadcrumb=$ebE3UsersBreadcrumb
        ebPageTitle='User Directory'
        ebPageDescription='Manage backup usernames, tenant scope, and account activity.'
        ebPageActions=$ebE3UsersHeaderActions
    }

    <div class="eb-subpanel eb-subpanel--overflow-visible">
        <div class="eb-table-toolbar">
            {if $isMspClient}
            <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                <button type="button"
                        @click="isOpen = !isOpen"
                        class="eb-btn eb-btn-secondary eb-btn-sm eb-btn-tenant-filter">
                    <span class="truncate" x-text="'Tenant: ' + tenantFilterLabel()"></span>
                    <svg class="eb-dropdown-chevron" :class="isOpen ? 'is-open' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="isOpen"
                     x-transition
                     class="eb-menu absolute left-0 z-20 mt-2 w-72 overflow-hidden"
                     style="display: none;">
                    <div class="eb-menu-label">Filter by tenant</div>
                    <div class="eb-menu-search-panel">
                        <input type="search"
                               x-model="tenantSearch"
                               placeholder="Search tenants"
                               class="eb-toolbar-search eb-toolbar-search--menu"
                               @click.stop>
                    </div>
                    <div class="max-h-72 overflow-auto p-1">
                        <button type="button"
                                class="eb-menu-option"
                                :class="tenantFilter === '' ? 'is-active' : ''"
                                @click="setTenantFilter(''); isOpen=false;">
                            All Tenants
                        </button>
                        <button type="button"
                                class="eb-menu-option"
                                :class="tenantFilter === 'direct' ? 'is-active' : ''"
                                @click="setTenantFilter('direct'); isOpen=false;">
                            Direct (No Tenant)
                        </button>
                        <template x-for="tenant in filteredTenants" :key="'tenant-filter-' + (tenant.public_id || tenant.id)">
                            <button type="button"
                                    class="eb-menu-option"
                                    :class="String(tenantFilter) === String(tenant.public_id || tenant.id) ? 'is-active' : ''"
                                    @click="setTenantFilter(String(tenant.public_id || tenant.id)); isOpen=false;">
                                <span x-text="tenant.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            {/if}

            <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                <button type="button"
                        @click="isOpen = !isOpen"
                        class="eb-btn eb-btn-secondary eb-btn-sm">
                    <span x-text="'Show ' + entriesPerPage"></span>
                    <svg class="eb-dropdown-chevron" :class="isOpen ? 'is-open' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="isOpen"
                     x-transition
                     class="eb-dropdown-menu absolute left-0 z-20 mt-2 w-40 overflow-hidden"
                     style="display: none;">
                    <template x-for="size in [10,25,50,100]" :key="'entries-' + size">
                        <button type="button"
                                class="eb-menu-option"
                                :class="entriesPerPage === size ? 'is-active' : ''"
                                @click="setEntries(size); isOpen=false;">
                            <span x-text="size"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                <button type="button"
                        @click="isOpen = !isOpen"
                        class="eb-btn eb-btn-secondary eb-btn-sm">
                    <span>Columns</span>
                    <svg class="eb-dropdown-chevron" :class="isOpen ? 'is-open' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="isOpen"
                     x-transition
                     class="eb-menu absolute left-0 z-20 mt-2 w-64 p-3"
                     style="display: none;">
                    <div class="eb-menu-label">Visible columns</div>
                    <div class="eb-menu-checklist mt-2">
                        <template x-for="col in availableColumns" :key="'col-' + col.key">
                            <label class="eb-menu-checklist-item">
                                <span x-text="col.label"></span>
                                <input type="checkbox"
                                       class="eb-check-input"
                                       :checked="columnState[col.key]"
                                       @change="toggleColumn(col.key)">
                            </label>
                        </template>
                    </div>
                </div>
            </div>

            <div class="eb-table-toolbar-grow" aria-hidden="true"></div>

            <input type="search"
                   placeholder="Search username, email, or tenant"
                   x-model.debounce.200ms="searchQuery"
                   class="eb-toolbar-search eb-toolbar-search--wide">
        </div>

        <div x-show="canonicalTenantLoadError"
             x-cloak
             class="eb-alert eb-alert--warning"
             role="status">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM10.29 3.86l-7.5 13A1 1 0 0 0 3.66 18.5h16.68a1 1 0 0 0 .87-1.64l-7.5-13a1 1 0 0 0-1.74 0Z" />
            </svg>
            <div x-text="canonicalTenantLoadError"></div>
        </div>

        <template x-if="loading">
            <div class="eb-card">
                <div class="eb-loading-inline">
                    <div class="eb-loading-spinner--compact" role="status" aria-label="Loading"></div>
                    <span class="eb-type-caption">Loading users…</span>
                </div>
            </div>
        </template>

        <template x-if="!loading && pagedUsers().length === 0">
            <div class="eb-app-empty">
                <div class="eb-app-empty-title" x-text="searchQuery.trim() ? 'No matching users found' : 'No users found'"></div>
                <p class="eb-app-empty-copy" x-text="searchQuery.trim() ? 'Try a different search term or clear your filters.' : 'Create your first backup user to get started.'"></p>
            </div>
        </template>

        <template x-if="!loading && pagedUsers().length > 0">
            <div>
                <div x-show="selectedUserIds.length > 0" x-cloak class="eb-bulk-bar">
                    <button type="button"
                            class="eb-check"
                            :class="{
                                'is-checked': allVisibleSelected(),
                                'is-indeterminate': someVisibleSelected() && !allVisibleSelected()
                            }"
                            @click="toggleSelectAllVisible()"
                            aria-label="Toggle selected users">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                    <div class="eb-bulk-bar-count" x-text="selectedUsersLabel()"></div>
                    <div class="eb-bulk-bar-actions">
                        <button type="button" class="eb-btn eb-btn-warning eb-btn-xs" @click="openSuspendModal(selectedUsers())">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 viewBox="0 0 24 24"
                                 style="width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"
                                 aria-hidden="true">
                                <rect x="6" y="4" width="4" height="16"></rect>
                                <rect x="14" y="4" width="4" height="16"></rect>
                            </svg>
                            Suspend Selected
                        </button>
                        <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-xs" @click="openDeleteModal(selectedUsers())">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 viewBox="0 0 24 24"
                                 style="width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;"
                                 aria-hidden="true">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                            </svg>
                            Delete Selected
                        </button>
                        <button type="button" class="eb-btn eb-btn-ghost eb-btn-xs" @click="clearSelectedUsers()">Clear</button>
                    </div>
                </div>

                <div class="eb-table-shell">
                    <table class="eb-table">
                        <thead>
                            <tr>
                                <th class="w-10">
                                    <button type="button"
                                            class="eb-check"
                                            :class="{
                                                'is-checked': allVisibleSelected(),
                                                'is-indeterminate': someVisibleSelected() && !allVisibleSelected()
                                            }"
                                            @click="toggleSelectAllVisible()"
                                            aria-label="Select all visible users">
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <polyline points="20 6 9 17 4 12"></polyline>
                                        </svg>
                                        <svg viewBox="0 0 24 24" aria-hidden="true">
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="eb-table-sort-button" @click="sortBy('username')">
                                        Username
                                        <span class="eb-sort-indicator" x-text="sortIndicator('username')"></span>
                                    </button>
                                </th>
                                <template x-if="columnState.tenant && isMspClient">
                                    <th>
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('tenant_name')">
                                            Tenant
                                            <span class="eb-sort-indicator" x-text="sortIndicator('tenant_name')"></span>
                                        </button>
                                    </th>
                                </template>
                                <template x-if="columnState.vaults_count">
                                    <th class="eb-table-cell-numeric">
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('vaults_count')">
                                            # Vaults
                                            <span class="eb-sort-indicator" x-text="sortIndicator('vaults_count')"></span>
                                        </button>
                                    </th>
                                </template>
                                <template x-if="columnState.jobs_count">
                                    <th class="eb-table-cell-numeric">
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('jobs_count')">
                                            # Jobs
                                            <span class="eb-sort-indicator" x-text="sortIndicator('jobs_count')"></span>
                                        </button>
                                    </th>
                                </template>
                                <template x-if="columnState.agents_count">
                                    <th class="eb-table-cell-numeric">
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('agents_count')">
                                            # Agents
                                            <span class="eb-sort-indicator" x-text="sortIndicator('agents_count')"></span>
                                        </button>
                                    </th>
                                </template>
                                <template x-if="columnState.last_backup_at">
                                    <th>
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('last_backup_at')">
                                            Last Backup
                                            <span class="eb-sort-indicator" x-text="sortIndicator('last_backup_at')"></span>
                                        </button>
                                    </th>
                                </template>
                                <template x-if="columnState.online_devices">
                                    <th class="eb-table-cell-numeric">
                                        <button type="button" class="eb-table-sort-button" @click="sortBy('online_devices')">
                                            Online
                                            <span class="eb-sort-indicator" x-text="sortIndicator('online_devices')"></span>
                                        </button>
                                    </th>
                                </template>
                                <th class="text-center w-14">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="user in pagedUsers()" :key="'user-row-' + userKey(user)">
                                <tr class="eb-table-row-clickable"
                                    :class="{
                                        'is-selected': isUserSelected(user),
                                        'is-suspended': isUserSuspended(user)
                                    }"
                                    @click="goToDetail(user.public_id || user.id)">
                                    <td @click.stop>
                                        <button type="button"
                                                class="eb-check"
                                                :class="{ 'is-checked': isUserSelected(user) }"
                                                @click="toggleUserSelection(user)"
                                                :aria-label="'Select ' + (user.username || 'user')">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        </button>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="eb-user-avatar-sm" x-text="userInitials(user)"></div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <a class="eb-link eb-user-row-name truncate"
                                                       :href="'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id=' + encodeURIComponent(user.public_id || user.id)"
                                                       @click.stop
                                                       x-text="user.username"></a>
                                                    <template x-if="isUserSuspended(user)">
                                                        <span class="eb-badge eb-badge--warning">Suspended</span>
                                                    </template>
                                                </div>
                                                <div class="eb-user-meta-line">
                                                    <span x-text="user.email || '—'"></span>
                                                    <template x-if="!(columnState.tenant && isMspClient)">
                                                        <span>
                                                            <span class="sep" aria-hidden="true"></span>
                                                            <span x-text="user.tenant_name || 'Direct'"></span>
                                                        </span>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <template x-if="columnState.tenant && isMspClient">
                                        <td x-text="user.tenant_name || 'Direct'"></td>
                                    </template>
                                    <template x-if="columnState.vaults_count">
                                        <td class="eb-table-cell-numeric" x-text="user.vaults_count"></td>
                                    </template>
                                    <template x-if="columnState.jobs_count">
                                        <td class="eb-table-cell-numeric" x-text="user.jobs_count"></td>
                                    </template>
                                    <template x-if="columnState.agents_count">
                                        <td class="eb-table-cell-numeric" x-text="user.agents_count"></td>
                                    </template>
                                    <template x-if="columnState.last_backup_at">
                                        <td class="eb-table-mono" x-text="formatDate(user.last_backup_at)"></td>
                                    </template>
                                    <template x-if="columnState.online_devices">
                                        <td class="eb-table-cell-numeric">
                                            <span class="eb-connection-status"
                                                  :class="Number(user.online_devices) > 0 ? 'eb-connection-status--online' : 'eb-connection-status--offline'">
                                                <span class="eb-status-dot"
                                                      :class="Number(user.online_devices) > 0 ? 'eb-status-dot--active' : 'eb-status-dot--error'"></span>
                                                <span x-text="user.online_devices"></span>
                                            </span>
                                        </td>
                                    </template>
                                    <td class="text-center" @click.stop>
                                        <div class="inline-flex" x-data="userRowActionsDropdown()">
                                            <button type="button"
                                                    x-ref="trigger"
                                                    class="eb-btn eb-btn-icon eb-btn-sm"
                                                    @click.stop="toggle($refs.trigger)"
                                                    :aria-expanded="isOpen"
                                                    aria-haspopup="menu"
                                                    :aria-label="'Actions for ' + (user.username || 'user')">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                                    <circle cx="12" cy="5" r="1"></circle>
                                                    <circle cx="12" cy="12" r="1"></circle>
                                                    <circle cx="12" cy="19" r="1"></circle>
                                                </svg>
                                            </button>
                                            <template x-teleport="body">
                                                <div x-show="isOpen"
                                                     x-cloak
                                                     x-transition
                                                     class="eb-dropdown-menu fixed z-[2100] w-52 overflow-hidden"
                                                     style="display: none;"
                                                     :style="menuPositionStyle"
                                                     role="menu"
                                                     @click.outside="if (!$refs.trigger || !$refs.trigger.contains($event.target)) closeMenu()"
                                                     @keydown.escape.window="if (isOpen) closeMenu()">
                                                    <button type="button"
                                                            role="menuitem"
                                                            class="eb-menu-item"
                                                            @click="closeMenu(); goToDetail(user.public_id || user.id)">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                            <circle cx="9" cy="7" r="4"></circle>
                                                        </svg>
                                                        Manage User
                                                    </button>
                                                    <div class="eb-menu-divider"></div>
                                                    <template x-if="!isUserSuspended(user)">
                                                        <button type="button"
                                                                role="menuitem"
                                                                class="eb-menu-item is-warning"
                                                                @click="closeMenu(); openSuspendModal([user])">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                                <rect x="6" y="4" width="4" height="16"></rect>
                                                                <rect x="14" y="4" width="4" height="16"></rect>
                                                            </svg>
                                                            Suspend User
                                                        </button>
                                                    </template>
                                                    <template x-if="isUserSuspended(user)">
                                                        <button type="button"
                                                                role="menuitem"
                                                                class="eb-menu-item"
                                                                style="color: var(--eb-success-text);"
                                                                @click="closeMenu(); reactivateUser(user)">
                                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                                <polygon points="5 3 19 12 5 21"></polygon>
                                                            </svg>
                                                            Reactivate User
                                                        </button>
                                                    </template>
                                                    <button type="button"
                                                            role="menuitem"
                                                            class="eb-menu-item is-danger"
                                                            @click="closeMenu(); openDeleteModal([user])">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                        </svg>
                                                        Delete User
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="eb-table-pagination">
                    <span x-text="pageSummary()"></span>
                    <div class="eb-table-pagination-actions">
                        <button type="button"
                                @click="prevPage()"
                                :disabled="currentPage <= 1"
                                class="eb-table-pagination-button">
                            Prev
                        </button>
                        <span class="eb-table-pagination-page" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
                        <button type="button"
                                @click="nextPage()"
                                :disabled="currentPage >= totalPages()"
                                class="eb-table-pagination-button">
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="suspendModal.open"
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
                            <div class="eb-modal-title" x-text="suspendModalTitle()"></div>
                            <div class="eb-modal-subtitle" x-text="suspendModalSubtitle()"></div>
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
                        <div>Suspending these users will stop scheduled backups and deny manual backup runs. Agents remain connected and users can still log in.</div>
                    </div>
                    <p class="eb-type-body mt-4">This action is reversible. You can reactivate any suspended user later from the same menu.</p>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeSuspendModal()">Cancel</button>
                    <button type="button"
                            class="eb-btn eb-btn-warning eb-btn-sm"
                            @click="confirmSuspendUsers()"
                            x-text="suspendModal.users.length > 1 ? 'Suspend Users' : 'Suspend User'"></button>
                </div>
            </div>
        </div>

        <div x-show="deleteModal.open"
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
                            <div class="eb-modal-title" x-text="deleteModalTitle()"></div>
                            <div class="eb-modal-subtitle" x-text="deleteModalSubtitle()"></div>
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
                        <div>This action is permanent and cannot be undone. All associated data for the selected users will be destroyed.</div>
                    </div>

                    <div class="mt-4 text-[10.5px] font-bold uppercase tracking-[0.12em] text-[var(--eb-text-muted)]">Impact Summary</div>
                    <div class="eb-impact-list">
                        <div class="eb-impact-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                            <span class="count" x-text="deleteImpactMetrics().agents"></span>
                            <span>Agents</span>
                        </div>
                        <div class="eb-impact-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                            </svg>
                            <span class="count" x-text="deleteImpactMetrics().jobs"></span>
                            <span>Backup Jobs</span>
                        </div>
                        <div class="eb-impact-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"></path>
                            </svg>
                            <span class="count" x-text="deleteImpactMetrics().vaults"></span>
                            <span>Storage Vaults</span>
                        </div>
                        <div class="eb-impact-item">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                <line x1="15" y1="9" x2="15.01" y2="9"></line>
                            </svg>
                            <span class="count" x-text="deleteImpactMetrics().storageCount"></span>
                            <span x-text="deleteImpactMetrics().storageLabel"></span>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="eb-type-body" x-text="deleteConfirmInstruction()"></div>
                        <input type="text"
                               class="eb-confirm-input"
                               x-model="deleteModal.confirmText"
                               :placeholder="deleteConfirmPlaceholder()">
                    </div>
                </div>
                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeDeleteModal()">Cancel</button>
                    <button type="button"
                            class="eb-btn eb-btn-danger-solid eb-btn-sm"
                            :disabled="!canConfirmDelete()"
                            @click="confirmDeleteUsers()"
                            x-text="deleteActionLabel()"></button>
                </div>
            </div>
        </div>
    </div>

    {include file="modules/addons/cloudstorage/templates/partials/e3backup_create_user_modal.tpl"
        modalTitle="Add User"
        submitLabel="Create User"
        submittingLabel="Creating..."
        showTenantSelector=true}
</div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='users'
    ebE3Title='Users'
    ebE3Description='Manage backup usernames and monitor scoped activity.'
    ebE3Icon=$ebE3Icon
    ebE3Actions=$ebE3Actions
    ebE3Content=$ebE3Content
}

{literal}
<script>
/**
 * Row actions menu: teleports to body + fixed positioning so it is not clipped by .eb-table-shell overflow-x-auto.
 */
function userRowActionsDropdown() {
    return {
        isOpen: false,
        menuPositionStyle: '',
        MENU_WIDTH_PX: 208,
        GAP_PX: 8,
        closeMenu() {
            this.isOpen = false;
            this.menuPositionStyle = '';
        },
        toggle(triggerEl) {
            if (this.isOpen) {
                this.closeMenu();
                return;
            }
            if (!triggerEl) {
                return;
            }
            const rect = triggerEl.getBoundingClientRect();
            const margin = 8;
            let left = rect.right - this.MENU_WIDTH_PX;
            if (left < margin) {
                left = margin;
            }
            const maxLeft = window.innerWidth - this.MENU_WIDTH_PX - margin;
            if (left > maxLeft) {
                left = Math.max(margin, maxLeft);
            }
            let top = rect.bottom + this.GAP_PX;
            const estH = 260;
            const vwH = window.innerHeight;
            if (top + estH > vwH - margin) {
                top = Math.max(margin, rect.top - estH - this.GAP_PX);
            }
            this.menuPositionStyle =
                'top:' + Math.round(top) + 'px;left:' + Math.round(left) + 'px;width:' + this.MENU_WIDTH_PX + 'px';
            this.isOpen = true;
        }
    };
}

function backupUsersApp() {
    return {
        users: [],
        loading: true,
        saving: false,
        isMspClient: {/literal}{if $isMspClient}true{else}false{/if}{literal},
        csrfToken: {/literal}{$csrfToken|@json_encode nofilter}{literal} || '',
        tenants: {/literal}{$tenants|@json_encode nofilter}{literal} || [],
        canonicalTenants: [],
        tenantFilter: '',
        tenantSearch: '',
        tenantAssignSearch: '',
        searchQuery: '',
        entriesPerPage: 25,
        currentPage: 1,
        sortKey: 'username',
        sortDirection: 'asc',
        selectedUserIds: [],
        suspendModal: {
            open: false,
            users: []
        },
        deleteModal: {
            open: false,
            users: [],
            confirmText: ''
        },
        showCreateModal: false,
        showRecoveryKeyCloseWarning: false,
        formErrorMessage: '',
        canonicalTenantLoadError: '',
        fieldErrors: {},
        form: {
            username: '',
            backup_type: 'both',
            password: '',
            password_confirm: '',
            email: '',
            tenant_id: '',
            encryption_mode: 'managed',
            managed_acknowledged: false,
            strict_acknowledged: false,
            recovery_key_downloaded: false
        },
        availableColumns: [
            { key: 'tenant', label: 'Tenant' },
            { key: 'vaults_count', label: '# Vaults' },
            { key: 'jobs_count', label: '# Jobs' },
            { key: 'agents_count', label: '# Agents' },
            { key: 'last_backup_at', label: 'Last Backup' },
            { key: 'online_devices', label: 'Online Devices' }
        ],
        columnState: {
            tenant: true,
            vaults_count: true,
            jobs_count: true,
            agents_count: true,
            last_backup_at: true,
            online_devices: true
        },

        init() {
            if (!this.isMspClient) {
                this.columnState.tenant = false;
            } else {
                this.loadCanonicalTenants();
            }
            this.loadUsers();
        },

        userKey(user) {
            return String((user && (user.public_id || user.id)) || '');
        },

        isUserSuspended(user) {
            return String((user && user.status) || '').toLowerCase() !== 'active';
        },

        isUserSelected(user) {
            const id = this.userKey(user);
            return id ? this.selectedUserIds.includes(id) : false;
        },

        visibleUserIds() {
            return this.pagedUsers().map((user) => this.userKey(user)).filter(Boolean);
        },

        allVisibleSelected() {
            const ids = this.visibleUserIds();
            return ids.length > 0 && ids.every((id) => this.selectedUserIds.includes(id));
        },

        someVisibleSelected() {
            return this.visibleUserIds().some((id) => this.selectedUserIds.includes(id));
        },

        toggleUserSelection(user) {
            const id = this.userKey(user);
            if (!id) {
                return;
            }
            if (this.selectedUserIds.includes(id)) {
                this.selectedUserIds = this.selectedUserIds.filter((selectedId) => selectedId !== id);
                return;
            }
            this.selectedUserIds = this.selectedUserIds.concat(id);
        },

        toggleSelectAllVisible() {
            const visibleIds = this.visibleUserIds();
            if (!visibleIds.length) {
                return;
            }
            if (this.allVisibleSelected()) {
                const visibleIdSet = new Set(visibleIds);
                this.selectedUserIds = this.selectedUserIds.filter((id) => !visibleIdSet.has(id));
                return;
            }
            const next = new Set(this.selectedUserIds);
            visibleIds.forEach((id) => next.add(id));
            this.selectedUserIds = Array.from(next);
        },

        clearSelectedUsers() {
            this.selectedUserIds = [];
        },

        selectedUsers() {
            const selectedSet = new Set(this.selectedUserIds);
            return this.users.filter((user) => selectedSet.has(this.userKey(user)));
        },

        selectedUsersLabel() {
            const count = this.selectedUserIds.length;
            return `${count} user${count === 1 ? '' : 's'} selected`;
        },

        selectionSubtitle(users) {
            if (!users.length) {
                return 'No users selected';
            }
            if (users.length === 1) {
                return users[0].username || 'Selected user';
            }
            const names = users
                .map((user) => user.username || 'User')
                .slice(0, 3)
                .join(', ');
            return users.length > 3 ? `${names} +${users.length - 3} more` : names;
        },

        openSuspendModal(users) {
            const modalUsers = Array.isArray(users) ? users.filter(Boolean) : [];
            if (!modalUsers.length) {
                return;
            }
            this.suspendModal = {
                open: true,
                users: modalUsers
            };
        },

        closeSuspendModal() {
            this.suspendModal = {
                open: false,
                users: []
            };
        },

        suspendModalTitle() {
            const count = this.suspendModal.users.length;
            return count > 1 ? `Suspend ${count} Users?` : 'Suspend User?';
        },

        suspendModalSubtitle() {
            return this.selectionSubtitle(this.suspendModal.users);
        },

        confirmSuspendUsers() {
            this.suspendModal.users.forEach((user) => {
                user.status = 'disabled';
            });
            this.closeSuspendModal();
        },

        reactivateUser(user) {
            if (!user) {
                return;
            }
            user.status = 'active';
        },

        openDeleteModal(users) {
            const modalUsers = Array.isArray(users) ? users.filter(Boolean) : [];
            if (!modalUsers.length) {
                return;
            }
            this.deleteModal = {
                open: true,
                users: modalUsers,
                confirmText: ''
            };
        },

        closeDeleteModal() {
            this.deleteModal = {
                open: false,
                users: [],
                confirmText: ''
            };
        },

        deleteModalTitle() {
            const count = this.deleteModal.users.length;
            return count > 1 ? `Delete ${count} Users Permanently?` : 'Delete User Permanently?';
        },

        deleteModalSubtitle() {
            return this.selectionSubtitle(this.deleteModal.users);
        },

        deleteImpactMetrics() {
            const users = this.deleteModal.users;
            return {
                agents: users.reduce((sum, user) => sum + Number(user.agents_count || 0), 0),
                jobs: users.reduce((sum, user) => sum + Number(user.jobs_count || 0), 0),
                vaults: users.reduce((sum, user) => sum + Number(user.vaults_count || 0), 0),
                storageCount: 'Pending',
                storageLabel: 'backup data calculation'
            };
        },

        deleteConfirmTarget() {
            if (this.deleteModal.users.length > 1) {
                return 'DELETE';
            }
            return (this.deleteModal.users[0] && this.deleteModal.users[0].username) || '';
        },

        deleteConfirmInstruction() {
            if (this.deleteModal.users.length > 1) {
                return 'Type DELETE to confirm this bulk deletion:';
            }
            return `Type ${this.deleteConfirmTarget()} to confirm deletion:`;
        },

        deleteConfirmPlaceholder() {
            if (this.deleteModal.users.length > 1) {
                return 'Type DELETE to confirm...';
            }
            return 'Type username to confirm...';
        },

        canConfirmDelete() {
            return this.deleteModal.confirmText.trim() === this.deleteConfirmTarget();
        },

        deleteActionLabel() {
            const count = this.deleteModal.users.length;
            return count > 1 ? `Delete ${count} Users` : 'Delete User';
        },

        confirmDeleteUsers() {
            if (!this.canConfirmDelete()) {
                return;
            }
            this.closeDeleteModal();
            this.clearSelectedUsers();
        },

        get filteredTenants() {
            const search = this.tenantSearch.trim().toLowerCase();
            if (!search) return this.tenants;
            return this.tenants.filter((tenant) => (tenant.name || '').toLowerCase().includes(search));
        },

        get filteredAssignTenants() {
            const search = this.tenantAssignSearch.trim().toLowerCase();
            if (!search) return this.canonicalTenants;
            return this.canonicalTenants.filter((tenant) => (tenant.name || '').toLowerCase().includes(search));
        },

        tenantFilterLabel() {
            if (this.tenantFilter === '') return 'All Tenants';
            if (this.tenantFilter === 'direct') return 'Direct (No Tenant)';
            const tenant = this.tenants.find((item) => String(item.public_id || item.id) === String(this.tenantFilter));
            return tenant ? tenant.name : 'Tenant';
        },

        createTenantLabel() {
            if (!this.form.tenant_id) return 'Direct (No Tenant)';
            const tenant = this.canonicalTenants.find((item) => String(item.public_id || item.id) === String(this.form.tenant_id));
            return tenant ? tenant.name : 'Select tenant';
        },

        generateRecoveryKey() {
            if (window.crypto && window.crypto.getRandomValues) {
                const bytes = new Uint8Array(32);
                window.crypto.getRandomValues(bytes);
                return Array.from(bytes).map((b) => b.toString(16).padStart(2, '0')).join('');
            }
            return Math.random().toString(36).slice(2) + Date.now().toString(36);
        },

        downloadRecoveryKey() {
            if (this.form.encryption_mode !== 'strict' || this.form.recovery_key_downloaded) {
                return;
            }

            const keyValue = this.generateRecoveryKey();
            const content = [
                'eazyBackup Recovery Key',
                '',
                'User: ' + (this.form.username || ''),
                'Email: ' + (this.form.email || ''),
                'Generated At: ' + new Date().toISOString(),
                '',
                'Recovery Key:',
                keyValue,
                '',
                'Important: This key is shown once and not stored by eazyBackup.'
            ].join('\n');

            const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            const filenameSafeUser = (this.form.username || 'backup-user').replace(/[^a-zA-Z0-9._-]+/g, '-');
            anchor.href = url;
            anchor.download = filenameSafeUser + '-recovery-key.txt';
            document.body.appendChild(anchor);
            anchor.click();
            document.body.removeChild(anchor);
            URL.revokeObjectURL(url);

            this.form.recovery_key_downloaded = true;
            if (this.fieldErrors.recovery_key_downloaded) {
                delete this.fieldErrors.recovery_key_downloaded;
            }
        },

        setTenantFilter(value) {
            this.tenantFilter = value;
            this.currentPage = 1;
            this.loadUsers();
        },

        setEntries(value) {
            this.entriesPerPage = value;
            this.currentPage = 1;
        },

        toggleColumn(key) {
            this.columnState[key] = !this.columnState[key];
        },

        sortBy(key) {
            if (this.sortKey === key) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKey = key;
                this.sortDirection = 'asc';
            }
        },

        sortIndicator(key) {
            if (this.sortKey !== key) return '';
            return this.sortDirection === 'asc' ? '↑' : '↓';
        },

        filteredUsers() {
            const query = this.searchQuery.trim().toLowerCase();
            let list = this.users.slice();
            if (query) {
                list = list.filter((user) => {
                    const username = (user.username || '').toLowerCase();
                    const email = (user.email || '').toLowerCase();
                    const tenantName = (user.tenant_name || '').toLowerCase();
                    return username.includes(query) || email.includes(query) || tenantName.includes(query);
                });
            }

            list.sort((a, b) => {
                let left = a[this.sortKey];
                let right = b[this.sortKey];

                if (this.sortKey === 'last_backup_at') {
                    left = left ? new Date(left).getTime() : 0;
                    right = right ? new Date(right).getTime() : 0;
                }

                if (left === null || left === undefined) left = '';
                if (right === null || right === undefined) right = '';
                if (typeof left === 'string') left = left.toLowerCase();
                if (typeof right === 'string') right = right.toLowerCase();

                if (left < right) return this.sortDirection === 'asc' ? -1 : 1;
                if (left > right) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });

            return list;
        },

        totalPages() {
            const count = this.filteredUsers().length;
            return Math.max(1, Math.ceil(count / this.entriesPerPage));
        },

        pagedUsers() {
            const list = this.filteredUsers();
            const pages = this.totalPages();
            if (this.currentPage > pages) this.currentPage = pages;
            const start = (this.currentPage - 1) * this.entriesPerPage;
            return list.slice(start, start + this.entriesPerPage);
        },

        pageSummary() {
            const total = this.filteredUsers().length;
            if (total === 0) return 'Showing 0 of 0 users';
            const start = (this.currentPage - 1) * this.entriesPerPage + 1;
            const end = Math.min(start + this.entriesPerPage - 1, total);
            return `Showing ${start}-${end} of ${total} users`;
        },

        visibleColumnCount() {
            let count = 3; // checkbox + username + actions
            if (this.isMspClient && this.columnState.tenant) count += 1;
            if (this.columnState.vaults_count) count += 1;
            if (this.columnState.jobs_count) count += 1;
            if (this.columnState.agents_count) count += 1;
            if (this.columnState.last_backup_at) count += 1;
            if (this.columnState.online_devices) count += 1;
            return count;
        },

        prevPage() {
            if (this.currentPage > 1) this.currentPage -= 1;
        },

        nextPage() {
            if (this.currentPage < this.totalPages()) this.currentPage += 1;
        },

        formatDate(value) {
            if (!value) return 'Never';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return value;
            return date.toLocaleString();
        },

        userInitials(user) {
            const raw = user && user.username ? String(user.username).trim() : '';
            if (!raw) return '?';
            const cleaned = raw.replace(/[^a-zA-Z0-9]+/g, ' ').trim();
            const parts = cleaned.split(/\s+/).filter(Boolean);
            if (parts.length >= 2) {
                return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase().slice(0, 2);
            }
            return raw.slice(0, 2).toUpperCase();
        },

        goToDetail(publicId) {
            window.location.href = 'index.php?m=cloudstorage&page=e3backup&view=user_detail&user_id=' + encodeURIComponent(publicId);
        },

        openCreateModal() {
            this.form = {
                username: '',
                backup_type: 'both',
                password: '',
                password_confirm: '',
                email: '',
                tenant_id: '',
                encryption_mode: 'managed',
                managed_acknowledged: false,
                strict_acknowledged: false,
                recovery_key_downloaded: false
            };
            this.formErrorMessage = '';
            this.fieldErrors = {};
            this.tenantAssignSearch = '';
            this.showRecoveryKeyCloseWarning = false;
            this.showCreateModal = true;
        },

        closeCreateModal(force = false) {
            if (!force && this.form.backup_type !== 'cloud_only' && this.form.encryption_mode === 'strict' && !this.form.recovery_key_downloaded) {
                this.showRecoveryKeyCloseWarning = true;
                return;
            }
            this.showRecoveryKeyCloseWarning = false;
            this.showCreateModal = false;
            this.saving = false;
        },

        cancelRecoveryKeyCloseWarning() {
            this.showRecoveryKeyCloseWarning = false;
        },

        confirmCloseCreateWithoutRecoveryKey() {
            this.closeCreateModal(true);
        },

        validateCreateForm() {
            this.fieldErrors = {};

            if (!this.form.username) {
                this.fieldErrors.username = 'Username is required.';
            } else if (!/^[A-Za-z0-9._-]{3,64}$/.test(this.form.username)) {
                this.fieldErrors.username = 'Use 3-64 characters with letters, numbers, dots, underscores, or hyphens.';
            }

            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!this.form.email) {
                this.fieldErrors.email = 'Email is required.';
            } else if (!emailPattern.test(this.form.email)) {
                this.fieldErrors.email = 'Please enter a valid email address.';
            }

            if (this.form.backup_type !== 'cloud_only') {
                if (this.form.encryption_mode === 'managed') {
                    if (!this.form.password) {
                        this.fieldErrors.password = 'Password is required.';
                    } else if (this.form.password.length < 8) {
                        this.fieldErrors.password = 'Password must be at least 8 characters.';
                    }

                    if (!this.form.password_confirm) {
                        this.fieldErrors.password_confirm = 'Please confirm your password.';
                    } else if (this.form.password !== this.form.password_confirm) {
                        this.fieldErrors.password_confirm = 'Password confirmation does not match.';
                    }
                }

                if (this.form.encryption_mode === 'managed') {
                    if (!this.form.managed_acknowledged) {
                        this.fieldErrors.managed_acknowledged = 'Please acknowledge managed recovery.';
                    }
                } else if (this.form.encryption_mode === 'strict') {
                    if (!this.form.recovery_key_downloaded) {
                        this.fieldErrors.recovery_key_downloaded = 'Download the recovery key before creating this user.';
                    }
                    if (!this.form.strict_acknowledged) {
                        this.fieldErrors.strict_acknowledged = 'Please acknowledge strict mode requirements.';
                    }
                }
            }

            return Object.keys(this.fieldErrors).length === 0;
        },

        async loadUsers() {
            this.loading = true;
            try {
                let url = 'modules/addons/cloudstorage/api/e3backup_user_list.php';
                const params = new URLSearchParams();
                if (this.isMspClient && this.tenantFilter !== '') {
                    params.set('tenant_id', this.tenantFilter);
                }
                const query = params.toString();
                if (query) {
                    url += '?' + query;
                }

                const response = await fetch(url);
                const data = await response.json();
                if (data.status === 'success') {
                    this.users = Array.isArray(data.users) ? data.users : [];
                    const validIds = new Set(this.users.map((user) => this.userKey(user)));
                    this.selectedUserIds = this.selectedUserIds.filter((id) => validIds.has(id));
                    this.currentPage = 1;
                } else {
                    this.users = [];
                    this.selectedUserIds = [];
                    this.formErrorMessage = data.message || 'Failed to load users.';
                }
            } catch (error) {
                this.users = [];
                this.selectedUserIds = [];
                this.formErrorMessage = 'Failed to load users.';
            }
            this.loading = false;
        },

        async loadCanonicalTenants() {
            if (!this.isMspClient) {
                this.canonicalTenants = [];
                this.canonicalTenantLoadError = '';
                return;
            }
            try {
                const response = await fetch('index.php?m=eazybackup&a=ph-tenant-storage-links');
                const data = await response.json();
                if (data.status === 'success' && Array.isArray(data.tenants)) {
                    this.canonicalTenants = data.tenants.map((tenant) => ({
                        id: tenant.public_id || tenant.id,
                        public_id: tenant.public_id || tenant.id,
                        name: tenant.name || tenant.subdomain || tenant.fqdn || 'Tenant'
                    }));
                    this.canonicalTenantLoadError = '';
                    return;
                }
            } catch (error) {}
            this.canonicalTenants = [];
            this.canonicalTenantLoadError = 'Canonical tenant list unavailable. Only direct assignment is currently available.';
        },

        async createUser() {
            this.formErrorMessage = '';
            if (!this.validateCreateForm()) {
                return;
            }

            this.saving = true;
            try {
                const body = new URLSearchParams({
                    username: this.form.username,
                    email: this.form.email,
                    status: 'active',
                    backup_type: this.form.backup_type
                });
                body.set('token', this.csrfToken);

                if (this.form.backup_type !== 'cloud_only') {
                    let passwordToSend = this.form.password;
                    let passwordConfirmToSend = this.form.password_confirm;
                    if (this.form.encryption_mode === 'strict') {
                        const generated = this.generateRecoveryKey().slice(0, 24);
                        passwordToSend = generated;
                        passwordConfirmToSend = generated;
                    }
                    body.set('password', passwordToSend);
                    body.set('password_confirm', passwordConfirmToSend);
                    body.set('encryption_mode', this.form.encryption_mode);
                    body.set('managed_acknowledged', this.form.managed_acknowledged ? '1' : '0');
                    body.set('strict_acknowledged', this.form.strict_acknowledged ? '1' : '0');
                    body.set('recovery_key_downloaded', this.form.recovery_key_downloaded ? '1' : '0');
                }

                if (this.isMspClient) {
                    body.set('canonical_tenant_id', this.form.tenant_id ? this.form.tenant_id : 'direct');
                }

                const response = await fetch('modules/addons/cloudstorage/api/e3backup_user_create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body
                });
                const data = await response.json();

                if (data.status === 'success') {
                    this.closeCreateModal(true);
                    await this.loadUsers();
                } else {
                    this.formErrorMessage = data.message || 'Failed to create user.';
                    this.fieldErrors = data.errors || {};
                    if (this.fieldErrors.canonical_tenant_id && !this.fieldErrors.tenant_id) {
                        this.fieldErrors.tenant_id = this.fieldErrors.canonical_tenant_id;
                    }
                }
            } catch (error) {
                this.formErrorMessage = 'Failed to create user.';
            }
            this.saving = false;
        }
    };
}
</script>
{/literal}

