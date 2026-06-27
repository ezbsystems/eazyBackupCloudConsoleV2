<div class="eb-subpanel">
    <div class="eb-app-toolbar">
        <div class="relative" x-data="{ entriesOpen: false }" @click.away="entriesOpen = false">
            <button type="button"
                    @click="entriesOpen = !entriesOpen"
                    class="eb-app-toolbar-button">
                <span x-text="'Show ' + vaultEntriesPerPage"></span>
                <svg class="w-4 h-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
            <div x-show="entriesOpen"
                 x-transition
                 class="eb-menu absolute left-0 mt-2 w-40 z-50 overflow-hidden"
                 style="display: none;">
                <template x-for="size in [10,25,50,100]" :key="'vault-entries-' + size">
                    <button type="button"
                            class="eb-menu-item"
                            :class="vaultEntriesPerPage === size ? 'is-active' : ''"
                            @click="setVaultEntries(size); entriesOpen = false;">
                        <span x-text="size"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="relative shrink-0" @click.away="vaultColsOpen = false">
            <button type="button" class="eb-app-toolbar-button" @click="vaultColsOpen = !vaultColsOpen">
                Columns
                <svg class="w-4 h-4 transition-transform" :class="vaultColsOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
            <div x-show="vaultColsOpen" x-cloak x-transition class="eb-menu absolute left-0 mt-2 w-72 z-50 overflow-hidden p-2" style="display:none;">
                <div class="eb-menu-checklist two-col">
                    {if $ebE3VaultsShowUserCol|default:false}
                    <label class="eb-menu-checklist-item"><span>User</span><input type="checkbox" class="eb-checkbox" x-model="cols.user"></label>
                    {/if}
                    <label class="eb-menu-checklist-item"><span>Retention</span><input type="checkbox" class="eb-checkbox" x-model="cols.retention"></label>
                    <label class="eb-menu-checklist-item"><span>Protection</span><input type="checkbox" class="eb-checkbox" x-model="cols.protection"></label>
                    <label class="eb-menu-checklist-item"><span>Stored</span><input type="checkbox" class="eb-checkbox" x-model="cols.stored"></label>
                    <label class="eb-menu-checklist-item"><span>Source job</span><input type="checkbox" class="eb-checkbox" x-model="cols.source_job"></label>
                    <label class="eb-menu-checklist-item"><span>Bucket path</span><input type="checkbox" class="eb-checkbox" x-model="cols.bucket_path"></label>
                    <label class="eb-menu-checklist-item"><span>Jobs using</span><input type="checkbox" class="eb-checkbox" x-model="cols.jobs_using"></label>
                    <label class="eb-menu-checklist-item"><span>Created</span><input type="checkbox" class="eb-checkbox" x-model="cols.created"></label>
                    <label class="eb-menu-checklist-item" x-show="vaultSubTab === 'recycle'"><span>Days left</span><input type="checkbox" class="eb-checkbox" x-model="cols.days_left"></label>
                </div>
            </div>
        </div>

        <div class="flex-1"></div>

        <input type="search"
               :placeholder="vaultSubTab === 'recycle' ? 'Search recycle bin' : 'Search vaults'"
               x-model.debounce.200ms="vaultSearchQuery"
               @input="vaultCurrentPage = 1"
               class="eb-input eb-app-toolbar-search">
    </div>

    <template x-if="pagedVaults().length === 0">
        <div class="eb-app-empty">
            <div class="eb-app-empty-title" x-text="vaultSearchQuery.trim() ? 'No matching vaults found' : (vaultSubTab === 'recycle' ? 'Recycle bin is empty' : 'No active vaults yet')"></div>
            <p class="eb-app-empty-copy" x-text="vaultSearchQuery.trim() ? 'Try a different search term or clear your filters.' : (vaultSubTab === 'recycle' ? 'Deleted Microsoft 365 backup vaults appear here during the grace period.' : vaultEmptyCopy())"></p>
        </div>
    </template>

    <template x-if="pagedVaults().length > 0">
        <div class="eb-table-shell overflow-x-auto">
            <table class="eb-table min-w-full text-sm">
                <thead>
                    <tr>
                        {if $ebE3VaultsShowUserCol|default:false}
                        <th x-show="cols.user" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="vaultSortBy('username')">
                                User <span class="eb-sort-indicator" x-text="vaultSortIndicator('username')"></span>
                            </button>
                        </th>
                        {/if}
                        <th class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="vaultSortBy('name')">
                                Vault name <span class="eb-sort-indicator" x-text="vaultSortIndicator('name')"></span>
                            </button>
                        </th>
                        <th x-show="cols.retention" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="vaultSortBy('retention_tier')">
                                Retention <span class="eb-sort-indicator" x-text="vaultSortIndicator('retention_tier')"></span>
                            </button>
                        </th>
                        <th x-show="cols.protection" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="vaultSortBy('protection_label')">
                                Protection <span class="eb-sort-indicator" x-text="vaultSortIndicator('protection_label')"></span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left font-medium" x-show="cols.stored">
                            <button type="button" class="eb-table-sort-button" @click="vaultSortBy('storage')">
                                Stored <span class="eb-sort-indicator" x-text="vaultSortIndicator('storage')"></span>
                            </button>
                        </th>
                        <th x-show="cols.source_job" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="vaultSortBy('job_name')">
                                Source job <span class="eb-sort-indicator" x-text="vaultSortIndicator('job_name')"></span>
                            </button>
                        </th>
                        <th x-show="cols.bucket_path" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="vaultSortBy('bucket_path')">
                                Bucket path <span class="eb-sort-indicator" x-text="vaultSortIndicator('bucket_path')"></span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left font-medium" x-show="cols.jobs_using">
                            <button type="button" class="eb-table-sort-button" @click="vaultSortBy('jobs_using')">
                                Jobs using <span class="eb-sort-indicator" x-text="vaultSortIndicator('jobs_using')"></span>
                            </button>
                        </th>
                        <th x-show="cols.created" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="vaultSortBy('created')">
                                Created <span class="eb-sort-indicator" x-text="vaultSortIndicator('created')"></span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left font-medium" x-show="vaultSubTab === 'recycle' && cols.days_left" x-cloak>
                            <button type="button" class="eb-table-sort-button" @click="vaultSortBy('days_remaining')">
                                Days left <span class="eb-sort-indicator" x-text="vaultSortIndicator('days_remaining')"></span>
                            </button>
                        </th>
                        <th x-show="vaultSubTab === 'recycle'" x-cloak class="px-4 py-3 text-left font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(vault, vIdx) in pagedVaults()" :key="'vault-row-' + vaultSubTab + '-' + (vault.id || vIdx)">
                        <tr>
                            {if $ebE3VaultsShowUserCol|default:false}
                            <td x-show="cols.user" class="px-4 py-3 whitespace-nowrap text-sm text-slate-300">
                                <a class="text-slate-300 hover:text-slate-200"
                                   :href="userVaultsUrl(vault)"
                                   x-text="vault.username || '—'"></a>
                                <div class="text-xs text-slate-400 mt-0.5" x-show="vault.tenant_name" x-text="vault.tenant_name"></div>
                            </td>
                            {/if}
                            <td class="px-4 py-3 text-sm text-slate-300">
                                <template x-if="vault.is_ms365">
                                    <div class="break-all" x-text="vault.name"></div>
                                </template>
                                <template x-if="!vault.is_ms365">
                                    <a class="text-slate-300 hover:text-slate-200 break-all"
                                       :href="'index.php?m=cloudstorage&page=buckets#bucketRow' + encodeURIComponent(vault.id)"
                                       x-text="vault.name"></a>
                                </template>
                                <div class="text-xs text-slate-400 mt-0.5" x-text="vault.provider_label || 'Microsoft 365 Backup'"></div>
                                <span class="eb-badge eb-badge--warning mt-1" x-show="vaultSubTab === 'recycle'">In recycle bin</span>
                            </td>
                            <td x-show="cols.retention" class="px-4 py-3 whitespace-nowrap text-sm text-slate-300" x-text="vault.retention_tier || '—'"></td>
                            <td x-show="cols.protection" class="px-4 py-3 whitespace-nowrap text-sm text-slate-300" x-text="vault.protection_label || 'Versioning enabled'"></td>
                            <td x-show="cols.stored" class="px-4 py-3 whitespace-nowrap text-sm text-slate-300" x-text="vault.storage_used_display || '—'"></td>
                            <td x-show="cols.source_job" class="px-4 py-3 whitespace-nowrap text-sm text-slate-300" x-text="vault.job_name || '—'"></td>
                            <td x-show="cols.bucket_path" class="px-4 py-3 whitespace-nowrap text-sm text-slate-300" x-text="vault.is_ms365 ? '—' : (vault.bucket_path || '—')"></td>
                            <td x-show="cols.jobs_using" class="px-4 py-3 whitespace-nowrap text-sm text-slate-300" x-text="vaultJobsUsingDisplay(vault)"></td>
                            <td x-show="cols.created" class="px-4 py-3 whitespace-nowrap text-sm text-slate-300" x-text="vault.created ? formatDateShort(vault.created) : '—'"></td>
                            <td x-show="vaultSubTab === 'recycle' && cols.days_left" x-cloak class="px-4 py-3 whitespace-nowrap text-sm text-slate-300">
                                <div x-text="vault.days_remaining != null ? (vault.days_remaining + ' days') : '—'"></div>
                                <div class="text-xs text-slate-400" x-show="vault.recycle_teardown_at" x-text="'on ' + formatDateShort(vault.recycle_teardown_at)"></div>
                            </td>
                            <td x-show="vaultSubTab === 'recycle'" x-cloak class="px-4 py-3 whitespace-nowrap text-sm">
                                <button type="button"
                                        class="eb-btn eb-btn-secondary eb-btn-sm"
                                        :disabled="vault.early_delete_request_status === 'pending' || earlyDeleteInProgress"
                                        @click="openEarlyDeleteModal(vault)">
                                    <span x-text="vault.early_delete_request_status === 'pending' ? 'Requested' : 'Request early deletion'"></span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-slate-400">
            <div x-text="vaultPageSummary()"></div>
            <div class="flex items-center gap-2">
                <button type="button"
                        class="eb-table-pagination-button"
                        :disabled="vaultCurrentPage <= 1"
                        @click="vaultCurrentPage = Math.max(1, vaultCurrentPage - 1)">
                    Prev
                </button>
                <span class="text-slate-300" x-text="'Page ' + vaultCurrentPage + ' / ' + vaultTotalPages()"></span>
                <button type="button"
                        class="eb-table-pagination-button"
                        :disabled="vaultCurrentPage >= vaultTotalPages()"
                        @click="vaultCurrentPage = Math.min(vaultTotalPages(), vaultCurrentPage + 1)">
                    Next
                </button>
            </div>
        </div>
    </template>
</div>
