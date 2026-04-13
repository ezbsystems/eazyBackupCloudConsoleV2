{capture assign=ebE3Description}
    Manage your backup agents, enrollment tokens{if $isMspClient}, tenants, and users{/if}.
{/capture}

{capture assign=ebE3TokensBreadcrumb}
    <div class="eb-breadcrumb">
        <a href="index.php?m=cloudstorage&page=e3backup&view=dashboard" class="eb-breadcrumb-link">e3 Cloud Backup</a>
        <span class="eb-breadcrumb-separator">/</span>
        <span class="eb-breadcrumb-current">Enrollment Tokens</span>
    </div>
{/capture}

{capture assign=ebE3Icon}
    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
        </svg>
    </span>
{/capture}

{capture assign=ebE3Content}
    <div x-data="tokensApp()"
         x-init="init()"
         @eb-e3-token-create-open.window="showCreateModal = true"
         class="space-y-6">
        <div class="eb-page-header">
            <div>
                {$ebE3TokensBreadcrumb nofilter}
                <h2 class="eb-page-title">Enrollment Tokens</h2>
                <p class="eb-page-description">Generate enrollment tokens for agent onboarding and use them for silent deployment through your preferred RMM workflow.</p>
            </div>
            <div class="shrink-0">
                <button type="button"
                        class="eb-btn eb-btn-primary eb-btn-sm"
                        onclick="window.dispatchEvent(new CustomEvent('eb-e3-token-create-open'))">
                    Generate Token
                </button>
            </div>
        </div>

        <div class="space-y-3">
            <div x-show="successMessage" x-cloak class="eb-alert eb-alert--success" role="status" aria-live="polite">
                <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <div x-text="successMessage"></div>
            </div>

            <div x-show="errorMessage" x-cloak class="eb-alert eb-alert--danger" role="alert">
                <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <div x-text="errorMessage"></div>
            </div>
        </div>

        <div class="eb-subpanel space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="eb-section-intro !mb-0 min-w-0">
                    <h3 class="eb-section-title">Enrollment Token Library</h3>
                    <p class="eb-section-description !mt-1">Create, search, copy, and revoke enrollment tokens for workstation and server rollouts.</p>
                </div>
                <span class="eb-badge eb-badge--success shrink-0" x-text="tokenCountBadge()"></span>
            </div>

            <div>
                <div class="eb-table-toolbar">
                    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="eb-btn eb-btn-secondary eb-btn-sm">
                            <span x-text="'Show ' + entriesPerPage"></span>
                            <svg class="h-4 w-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 0 1 1.414 0L10 10.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 0-1.414Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="isOpen"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden"
                             style="display: none;">
                            <template x-for="size in [10, 25, 50, 100]" :key="'token-entries-' + size">
                                <button type="button"
                                        class="eb-menu-option"
                                        :class="entriesPerPage === size ? 'is-active' : ''"
                                        @click="setEntries(size); isOpen = false">
                                    <span x-text="size"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="eb-input-wrap flex-1 xl:ml-auto xl:max-w-md">
                        <div class="eb-input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z" />
                            </svg>
                        </div>
                        <input type="search"
                               class="eb-input eb-input-has-icon"
                               x-model.debounce.200ms="searchQuery"
                               placeholder="Search token, description, tenant, or status" />
                    </div>
                </div>

                <template x-if="loading">
                    <div class="eb-app-empty">
                        <div class="eb-app-empty-title">Loading tokens</div>
                        <p class="eb-app-empty-copy">Fetching the latest enrollment token list.</p>
                    </div>
                </template>

                <template x-if="!loading && filteredTokens().length === 0">
                    <div class="eb-app-empty">
                        <div class="eb-app-empty-title" x-text="tokens.length === 0 ? 'No enrollment tokens yet' : 'No matching tokens found'"></div>
                        <p class="eb-app-empty-copy" x-text="tokens.length === 0 ? 'Generate your first token to start silent agent enrollment.' : 'Adjust the search term or clear the filter to see more results.'"></p>
                    </div>
                </template>

                <template x-if="!loading && filteredTokens().length > 0">
                    <div>
                        <div class="eb-table-shell">
                            <table class="eb-table">
                                <thead>
                                    <tr>
                                        <th>
                                            <button type="button" class="eb-table-sort-button" @click="sortBy('token')">
                                                Token
                                                <span class="eb-sort-indicator" x-text="sortIndicator('token')"></span>
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="eb-table-sort-button" @click="sortBy('description')">
                                                Description
                                                <span class="eb-sort-indicator" x-text="sortIndicator('description')"></span>
                                            </button>
                                        </th>
                                        {if $isMspClient}
                                        <th>
                                            <button type="button" class="eb-table-sort-button" @click="sortBy('tenant_name')">
                                                Tenant
                                                <span class="eb-sort-indicator" x-text="sortIndicator('tenant_name')"></span>
                                            </button>
                                        </th>
                                        {/if}
                                        <th>
                                            <button type="button" class="eb-table-sort-button" @click="sortBy('uses')">
                                                Uses
                                                <span class="eb-sort-indicator" x-text="sortIndicator('uses')"></span>
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="eb-table-sort-button" @click="sortBy('expires_at')">
                                                Expires
                                                <span class="eb-sort-indicator" x-text="sortIndicator('expires_at')"></span>
                                            </button>
                                        </th>
                                        <th>
                                            <button type="button" class="eb-table-sort-button" @click="sortBy('status')">
                                                Status
                                                <span class="eb-sort-indicator" x-text="sortIndicator('status')"></span>
                                            </button>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="tokenRow in pagedTokens()" :key="'token-row-' + tokenRow.id">
                                        <tr>
                                            <td class="eb-table-primary">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <code class="eb-type-mono text-[var(--eb-text-primary)]" x-text="tokenRow.token"></code>
                                                    <button type="button"
                                                            class="eb-btn eb-btn-secondary eb-btn-xs"
                                                            @click="copyToken(tokenRow.token)">
                                                        Copy
                                                    </button>
                                                </div>
                                            </td>
                                            <td x-text="tokenRow.description || 'No description'"></td>
                                            {if $isMspClient}
                                            <td x-text="tokenRow.tenant_name || 'All / Direct'"></td>
                                            {/if}
                                            <td x-text="usesLabel(tokenRow)"></td>
                                            <td x-text="formatExpiry(tokenRow.expires_at)"></td>
                                            <td>
                                                <span class="eb-badge" :class="statusClass(tokenRow)" x-text="statusLabel(tokenRow)"></span>
                                            </td>
                                            <td>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <button type="button"
                                                            class="eb-btn eb-btn-secondary eb-btn-xs"
                                                            @click="showInstallCmd(tokenRow)">
                                                        Install Cmd
                                                    </button>
                                                    <button type="button"
                                                            x-show="tokenRow.is_valid"
                                                            class="eb-btn eb-btn-danger-solid eb-btn-xs"
                                                            @click="openRevokeModal(tokenRow)">
                                                        Revoke
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="eb-table-pagination">
                            <span x-text="pageSummary()"></span>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        class="eb-table-pagination-button"
                                        @click="prevPage()"
                                        :disabled="currentPage <= 1">
                                    Previous
                                </button>
                                <span class="eb-type-caption text-[var(--eb-text-primary)]" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
                                <button type="button"
                                        class="eb-table-pagination-button"
                                        @click="nextPage()"
                                        :disabled="currentPage >= totalPages()">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <section class="eb-card">
            <div class="flex items-start gap-4">
                <span class="eb-icon-box eb-icon-box--info">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 9h1.5m-1.5 3h1.5m-6 9h10.5A2.25 2.25 0 0 0 19.5 18.75V5.25A2.25 2.25 0 0 0 17.25 3H6.75A2.25 2.25 0 0 0 4.5 5.25v13.5A2.25 2.25 0 0 0 6.75 21Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <h2 class="eb-card-title">Deployment Guidance</h2>
                    <p class="eb-card-subtitle !mt-2">Generate a token, download the Windows agent installer, and use the install command action to copy the silent deployment syntax for your RMM or script runner.</p>
                </div>
            </div>
        </section>

        <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="eb-modal-backdrop fixed inset-0" @click="showCreateModal = false"></div>
            <div class="eb-modal relative z-10">
                <form @submit.prevent="createToken()">
                    <div class="eb-modal-header">
                        <div>
                            <h2 class="eb-modal-title">Generate Enrollment Token</h2>
                            <p class="eb-modal-subtitle">Create a new token for agent onboarding and silent deployment.</p>
                        </div>
                        <button type="button" class="eb-modal-close" @click="showCreateModal = false">&times;</button>
                    </div>

                    <div class="eb-modal-body space-y-4">
                        <label class="block">
                            <span class="eb-field-label">Description</span>
                            <input type="text"
                                   x-model="newToken.description"
                                   class="eb-input mt-2 w-full"
                                   placeholder="e.g., April workstation rollout" />
                            <p class="eb-field-help">Optional label to help identify the rollout or team using this token.</p>
                        </label>

                        {if $isMspClient}
                        <label class="block"
                               x-data="{
                                   isOpen: false,
                                   selected: '',
                                   options: [
                                       { value: '', label: 'All / Direct' },
                                       {foreach from=$tenants item=tenant}
                                       { value: '{$tenant->public_id|escape:'javascript'}', label: '{$tenant->name|escape:'javascript'}' },
                                       {/foreach}
                                   ],
                                   labelFor(val) {
                                       const option = this.options.find(opt => String(opt.value) === String(val));
                                       return option ? option.label : 'All / Direct';
                                   }
                               }"
                               x-init="
                                   selected = newToken.tenant_id || '';
                                   $watch('newToken.tenant_id', value => { selected = value || ''; });
                               ">
                            <span class="eb-field-label">Scope to Tenant</span>
                            <select x-model="newToken.tenant_id" class="hidden">
                                <option value="">All / Direct</option>
                                {foreach from=$tenants item=tenant}
                                <option value="{$tenant->public_id|escape}">{$tenant->name|escape}</option>
                                {/foreach}
                            </select>
                            <div class="relative mt-2" @click.away="isOpen = false">
                                <button type="button"
                                        @click="isOpen = !isOpen"
                                        class="eb-menu-trigger relative">
                                    <span class="block truncate" x-text="labelFor(selected)"></span>
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                        <svg class="h-5 w-5 text-[var(--eb-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </button>
                                <div x-show="isOpen"
                                     x-transition
                                     class="eb-dropdown-menu absolute z-10 mt-1 w-full overflow-hidden"
                                     style="display:none;">
                                    <ul class="max-h-60 overflow-auto py-1 text-sm scrollbar_thin">
                                        <template x-for="opt in options" :key="opt.value || '__all_direct'">
                                            <li @click="selected = opt.value; newToken.tenant_id = opt.value; isOpen = false"
                                                class="eb-menu-option cursor-pointer select-none"
                                                :class="{ 'is-active': String(selected) === String(opt.value) }"
                                                x-text="opt.label">
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                            <p class="eb-field-help">Agents enrolled with this token will inherit the selected tenant scope.</p>
                        </label>
                        {/if}

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="eb-field-label">Max Uses</span>
                                <input type="number"
                                       x-model="newToken.max_uses"
                                       min="0"
                                       class="eb-input mt-2 w-full"
                                       placeholder="0" />
                                <p class="eb-field-help">Use `0` for unlimited enrollments.</p>
                            </label>

                            <label class="block">
                                <span class="eb-field-label">Expires After</span>
                                <select x-model="newToken.expires_in" class="eb-select mt-2 w-full">
                                    <option value="">Never</option>
                                    <option value="24h">24 hours</option>
                                    <option value="7d">7 days</option>
                                    <option value="30d">30 days</option>
                                    <option value="90d">90 days</option>
                                    <option value="1y">1 year</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="eb-modal-footer">
                        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="showCreateModal = false">Cancel</button>
                        <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm" :disabled="creating">
                            <span x-show="!creating">Generate Token</span>
                            <span x-show="creating">Generating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div x-show="showInstallModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="eb-modal-backdrop fixed inset-0" @click="showInstallModal = false"></div>
            <div class="eb-modal relative z-10 !max-w-3xl">
                <div class="eb-modal-header">
                    <div>
                        <h2 class="eb-modal-title">Silent Install Command</h2>
                        <p class="eb-modal-subtitle">Copy the command below and run it after downloading the Windows agent installer.</p>
                    </div>
                    <button type="button" class="eb-modal-close" @click="showInstallModal = false">&times;</button>
                </div>

                <div class="eb-modal-body space-y-4">
                    <label class="block">
                        <span class="eb-field-label">Windows CMD / PowerShell</span>
                        <div class="eb-card mt-2 !p-4 overflow-x-auto">
                            <pre class="eb-type-mono whitespace-pre-wrap break-all text-[var(--eb-text-primary)]" x-text="installCmd"></pre>
                        </div>
                    </label>
                    <p class="eb-field-help !mt-0">Download the agent installer from the Agents page, then run this command to enroll the device with the embedded token.</p>
                </div>

                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="showInstallModal = false">Close</button>
                    <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="copyInstallCmd()">Copy Command</button>
                </div>
            </div>
        </div>

        <div x-show="showRevokeModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="eb-modal-backdrop fixed inset-0" @click="showRevokeModal = false"></div>
            <div class="eb-modal eb-modal--confirm relative z-10">
                <div class="eb-modal-header">
                    <div>
                        <h2 class="eb-modal-title">Revoke Token</h2>
                        <p class="eb-modal-subtitle">This token will no longer be valid for new enrollments.</p>
                    </div>
                    <button type="button" class="eb-modal-close" @click="showRevokeModal = false">&times;</button>
                </div>

                <div class="eb-modal-body">
                    <p class="eb-type-body">Agents already enrolled with this token will continue to work. Continue?</p>
                </div>

                <div class="eb-modal-footer">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="showRevokeModal = false">Cancel</button>
                    <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" :disabled="revoking" @click="confirmRevoke()">
                        <span x-show="!revoking">Revoke Token</span>
                        <span x-show="revoking">Revoking...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
{/capture}

{include file="modules/addons/cloudstorage/templates/partials/e3backup_shell.tpl"
    ebE3SidebarPage='tokens'
    ebE3Title='e3 Cloud Backup'
    ebE3Description=$ebE3Description
    ebE3Icon=$ebE3Icon
    ebE3Content=$ebE3Content
}

{literal}
<script>
function tokensApp() {
    return {
        tokens: [],
        loading: true,
        creating: false,
        revoking: false,
        showCreateModal: false,
        showInstallModal: false,
        showRevokeModal: false,
        revokeTarget: null,
        installCmd: '',
        searchQuery: '',
        entriesPerPage: 25,
        currentPage: 1,
        sortKey: 'token',
        sortDirection: 'asc',
        successMessage: '',
        errorMessage: '',
        flashTimer: null,
        newToken: {
            description: '',
            tenant_id: '',
            max_uses: 0,
            expires_in: '7d'
        },

        init() {
            if (typeof this.$watch === 'function') {
                this.$watch('searchQuery', () => {
                    this.currentPage = 1;
                });
            }
            this.loadTokens();
        },

        async loadTokens() {
            this.loading = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_token_list.php');
                const data = await res.json();
                if (data.status === 'success' && Array.isArray(data.tokens)) {
                    this.tokens = data.tokens;
                    this.currentPage = 1;
                } else {
                    this.tokens = [];
                    this.setError((data && data.message) || 'Unable to load enrollment tokens.');
                }
            } catch (error) {
                this.tokens = [];
                this.setError('Unable to load enrollment tokens.');
            }
            this.loading = false;
        },

        async createToken() {
            this.creating = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_token_create.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        token: '{/literal}{$token|escape:'javascript'}{literal}',
                        description: this.newToken.description,
                        tenant_id: this.newToken.tenant_id,
                        max_uses: this.newToken.max_uses,
                        expires_in: this.newToken.expires_in
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showCreateModal = false;
                    this.newToken = {
                        description: '',
                        tenant_id: '',
                        max_uses: 0,
                        expires_in: '7d'
                    };
                    this.installCmd = 'e3-backup-agent.exe /S /TOKEN=' + data.token;
                    this.showInstallModal = true;
                    this.setSuccess('Enrollment token generated.');
                    await this.loadTokens();
                } else {
                    this.setError((data && data.message) || 'Failed to create enrollment token.');
                }
            } catch (error) {
                this.setError('Failed to create enrollment token.');
            }
            this.creating = false;
        },

        openRevokeModal(token) {
            this.revokeTarget = token;
            this.showRevokeModal = true;
        },

        async confirmRevoke() {
            if (!this.revokeTarget) {
                return;
            }

            this.revoking = true;
            try {
                const res = await fetch('modules/addons/cloudstorage/api/e3backup_token_revoke.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        token: '{/literal}{$token|escape:'javascript'}{literal}',
                        token_id: this.revokeTarget.id
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.showRevokeModal = false;
                    this.revokeTarget = null;
                    this.setSuccess('Enrollment token revoked.');
                    await this.loadTokens();
                } else {
                    this.setError((data && data.message) || 'Failed to revoke enrollment token.');
                }
            } catch (error) {
                this.setError('Failed to revoke enrollment token.');
            }
            this.revoking = false;
        },

        showInstallCmd(token) {
            this.installCmd = 'e3-backup-agent.exe /S /TOKEN=' + token.token;
            this.showInstallModal = true;
        },

        async copyToken(token) {
            await this.copyText(token, 'Enrollment token copied.');
        },

        async copyInstallCmd() {
            await this.copyText(this.installCmd, 'Install command copied.');
        },

        async copyText(value, successMessage) {
            if (!value) {
                return;
            }

            try {
                await navigator.clipboard.writeText(value);
                this.setSuccess(successMessage);
            } catch (error) {
                this.setError('Clipboard access failed.');
            }
        },

        setEntries(value) {
            this.entriesPerPage = Number(value) || 25;
            this.currentPage = 1;
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
            if (this.sortKey !== key) {
                return '';
            }
            return this.sortDirection === 'asc' ? '↑' : '↓';
        },

        filteredTokens() {
            const query = (this.searchQuery || '').trim().toLowerCase();
            const list = this.tokens.filter((token) => {
                if (!query) {
                    return true;
                }

                const haystack = [
                    token.token,
                    token.description,
                    token.tenant_name,
                    this.statusLabel(token),
                    token.expires_at
                ].map((value) => String(value || '').toLowerCase());

                return haystack.some((value) => value.includes(query));
            });

            list.sort((leftToken, rightToken) => {
                const left = this.sortValue(leftToken, this.sortKey);
                const right = this.sortValue(rightToken, this.sortKey);

                if (left < right) {
                    return this.sortDirection === 'asc' ? -1 : 1;
                }
                if (left > right) {
                    return this.sortDirection === 'asc' ? 1 : -1;
                }
                return 0;
            });

            return list;
        },

        pagedTokens() {
            const list = this.filteredTokens();
            const pages = this.totalPages();
            if (this.currentPage > pages) {
                this.currentPage = pages;
            }
            const start = (this.currentPage - 1) * this.entriesPerPage;
            return list.slice(start, start + this.entriesPerPage);
        },

        totalPages() {
            return Math.max(1, Math.ceil(this.filteredTokens().length / this.entriesPerPage));
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage -= 1;
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages()) {
                this.currentPage += 1;
            }
        },

        pageSummary() {
            const total = this.filteredTokens().length;
            if (!total) {
                return 'Showing 0 results';
            }
            const pages = this.totalPages();
            if (this.currentPage > pages) {
                this.currentPage = pages;
            }
            const start = (this.currentPage - 1) * this.entriesPerPage + 1;
            const end = Math.min(start + this.entriesPerPage - 1, total);
            return 'Showing ' + start + '-' + end + ' of ' + total + ' tokens';
        },

        tokenCountBadge() {
            const total = this.tokens.length;
            return total + ' token' + (total === 1 ? '' : 's');
        },

        usesLabel(token) {
            const maxUses = Number(token.max_uses || 0);
            return String(token.use_count || 0) + ' / ' + (maxUses > 0 ? String(maxUses) : 'Unlimited');
        },

        formatExpiry(value) {
            return value || 'Never';
        },

        statusLabel(token) {
            if (token.is_valid) {
                return 'Active';
            }
            if (token.revoked_at) {
                return 'Revoked';
            }
            return 'Expired';
        },

        statusClass(token) {
            if (token.is_valid) {
                return 'eb-badge--success';
            }
            if (token.revoked_at) {
                return 'eb-badge--danger';
            }
            return 'eb-badge--warning';
        },

        sortValue(token, key) {
            if (key === 'token') {
                return String(token.token || '').toLowerCase();
            }
            if (key === 'description') {
                return String(token.description || '').toLowerCase();
            }
            if (key === 'tenant_name') {
                return String(token.tenant_name || '').toLowerCase();
            }
            if (key === 'uses') {
                return Number(token.use_count || 0);
            }
            if (key === 'expires_at') {
                return token.expires_at ? new Date(token.expires_at).getTime() || 0 : Number.MAX_SAFE_INTEGER;
            }
            if (key === 'status') {
                return String(this.statusLabel(token) || '').toLowerCase();
            }
            return '';
        },

        clearFlash() {
            if (this.flashTimer) {
                window.clearTimeout(this.flashTimer);
                this.flashTimer = null;
            }
        },

        setSuccess(message) {
            this.clearFlash();
            this.errorMessage = '';
            this.successMessage = message;
            this.flashTimer = window.setTimeout(() => {
                this.successMessage = '';
                this.flashTimer = null;
            }, 3500);
        },

        setError(message) {
            this.clearFlash();
            this.successMessage = '';
            this.errorMessage = message;
            this.flashTimer = window.setTimeout(() => {
                this.errorMessage = '';
                this.flashTimer = null;
            }, 5000);
        }
    };
}
</script>
{/literal}
