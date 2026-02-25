<div x-show="showCreateModal"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60"
     @click.self="closeCreateModal()">
    <div class="w-full max-w-lg rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
            <h3 class="text-lg font-semibold text-white">{$modalTitle|default:'Add User'}</h3>
            <button type="button" @click="closeCreateModal()" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <form @submit.prevent="createUser()" class="p-6 space-y-4">
            <div x-show="formErrorMessage" class="rounded-md border border-rose-500/40 bg-rose-900/20 px-3 py-2 text-sm text-rose-200" x-text="formErrorMessage"></div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Username <span class="text-rose-400">*</span></label>
                <input type="text" x-model.trim="form.username"
                       placeholder="username"
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                <p class="text-xs text-rose-300 mt-1" x-show="fieldErrors.username" x-text="fieldErrors.username"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Encryption Mode</label>
                <div class="rounded-lg border border-slate-700/70 bg-slate-800/40 p-4 space-y-3">
                    <label class="flex items-start gap-2 text-sm text-slate-200 cursor-pointer">
                        <input type="radio" x-model="form.encryption_mode" value="managed" class="mt-0.5 bg-slate-800 border-slate-600 text-amber-600 focus:ring-amber-500">
                        <span>
                            <span class="font-medium">Password - Managed Recovery</span>
                            <span class="block text-xs text-slate-400 mt-0.5">Reset always possible.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-2 text-sm text-slate-200 cursor-pointer">
                        <input type="radio" x-model="form.encryption_mode" value="strict" class="mt-0.5 bg-slate-800 border-slate-600 text-amber-600 focus:ring-amber-500">
                        <span class="font-medium">Strict Customer-Managed Encryption (Zero-Knowledge)</span>
                    </label>
                </div>
            </div>

            <div x-show="form.encryption_mode === 'managed'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Password <span class="text-rose-400">*</span></label>
                    <input type="password" x-model="form.password"
                           placeholder="Minimum 8 characters"
                           class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <p class="text-xs text-rose-300 mt-1" x-show="fieldErrors.password" x-text="fieldErrors.password"></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Confirm Password <span class="text-rose-400">*</span></label>
                    <input type="password" x-model="form.password_confirm"
                           placeholder="Repeat password"
                           class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    <p class="text-xs text-rose-300 mt-1" x-show="fieldErrors.password_confirm" x-text="fieldErrors.password_confirm"></p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Email (for reports) <span class="text-rose-400">*</span></label>
                <input type="email" x-model.trim="form.email"
                       placeholder="alerts@example.com"
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500">
                <p class="text-xs text-rose-300 mt-1" x-show="fieldErrors.email" x-text="fieldErrors.email"></p>
            </div>

            {if $showTenantSelector|default:true && $isMspClient}
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1">Tenant</label>
                {if $lockTenantField|default:false}
                    <input type="text"
                           :value="createTenantLabel()"
                           disabled
                           class="w-full rounded-lg border border-slate-700 bg-slate-800/70 px-3 py-2 text-sm text-slate-300 opacity-90 cursor-not-allowed">
                    <p class="text-xs text-slate-500 mt-1">This user will be scoped to the selected tenant.</p>
                {else}
                    <div class="relative" x-data="{ isOpen: false }" @click.away="isOpen = false">
                        <button type="button"
                                @click="isOpen = !isOpen"
                                class="w-full inline-flex items-center justify-between gap-2 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-500">
                            <span class="truncate" x-text="createTenantLabel()"></span>
                            <svg class="w-4 h-4 transition-transform" :class="isOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div x-show="isOpen"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute left-0 mt-2 w-full rounded-xl border border-slate-700 bg-slate-900 shadow-2xl z-50 overflow-hidden"
                             style="display: none;">
                            <div class="px-3 py-2 border-b border-slate-800">
                                <input type="text" x-model="tenantAssignSearch" placeholder="Search tenants"
                                       class="w-full rounded-md bg-slate-950 border border-slate-700 px-3 py-2 text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-500">
                            </div>
                            <div class="py-1 max-h-64 overflow-auto">
                                <button type="button"
                                        class="w-full px-4 py-2 text-left text-sm transition"
                                        :class="form.tenant_id === '' ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                        @click="form.tenant_id=''; isOpen=false;">
                                    Direct (No Tenant)
                                </button>
                                <template x-for="tenant in filteredAssignTenants" :key="'assign-' + tenant.id">
                                    <button type="button"
                                            class="w-full px-4 py-2 text-left text-sm transition"
                                            :class="String(form.tenant_id) === String(tenant.id) ? 'bg-slate-800/70 text-white' : 'text-slate-200 hover:bg-slate-800/60'"
                                            @click="form.tenant_id = String(tenant.id); isOpen=false;">
                                        <span x-text="tenant.name"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-1">Optional. Leave blank for direct scope.</p>
                {/if}
                <p class="text-xs text-rose-300 mt-1" x-show="fieldErrors.tenant_id" x-text="fieldErrors.tenant_id"></p>
            </div>
            {/if}

            <div class="rounded-lg border border-slate-700/70 bg-slate-800/40 p-4 space-y-3">
                <h4 class="text-sm font-semibold text-slate-200">Acknowledgement</h4>

                <div x-show="form.encryption_mode === 'managed'" x-cloak>
                    <label class="flex items-start gap-2 text-sm text-slate-300 cursor-pointer">
                        <input type="checkbox" x-model="form.managed_acknowledged" class="mt-0.5 rounded bg-slate-800 border-slate-600 text-amber-600 focus:ring-amber-500">
                        <span>I understand authorized account owners can reset encryption password for this User.</span>
                    </label>
                    <p class="text-xs text-rose-300 mt-1" x-show="fieldErrors.managed_acknowledged" x-text="fieldErrors.managed_acknowledged"></p>
                </div>

                <div x-show="form.encryption_mode === 'strict'" x-cloak class="space-y-3 border-t border-slate-700/70 pt-3">
                    <p class="text-xs text-amber-300">Warning: Your recovery key will be shown once and can only be downloaded one time.</p>
                    <p class="text-xs text-slate-300">We do not store this key on eazyBackup, and admin reset is disabled.</p>
                    <p class="text-xs text-slate-300">If the recovery key is lost, encrypted data cannot be recovered.</p>
                    <p class="text-xs text-slate-300">Save the recovery key somewhere extremely safe (password manager, secure vault, offline backup).</p>
                    <p class="text-xs text-slate-300">Recommended only for customers with legal/compliance requirements.</p>

                    <div>
                        <p class="text-sm font-medium text-slate-200 mb-2">Download Recovery Key (one-time)</p>
                        <button type="button"
                                @click="downloadRecoveryKey()"
                                :disabled="form.recovery_key_downloaded || saving"
                                class="px-3 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500 disabled:opacity-60 disabled:cursor-not-allowed">
                            Download Recovery Key
                        </button>
                        <p class="text-xs text-slate-400 mt-1">One-time download only</p>
                        <p class="text-xs text-emerald-300 mt-1" x-show="form.recovery_key_downloaded">Downloaded. This key will not be shown again.</p>
                        <p class="text-xs text-rose-300 mt-1" x-show="fieldErrors.recovery_key_downloaded" x-text="fieldErrors.recovery_key_downloaded"></p>
                    </div>

                    <label class="flex items-start gap-2 text-sm text-slate-300 cursor-pointer">
                        <input type="checkbox" x-model="form.strict_acknowledged" class="mt-0.5 rounded bg-slate-800 border-slate-600 text-amber-600 focus:ring-amber-500">
                        <span>"I understand admin reset is disabled in Strict mode, the recovery key is shown once and not stored by eazyBackup, and data recovery is not possible without it."</span>
                    </label>
                    <p class="text-xs text-rose-300 mt-1" x-show="fieldErrors.strict_acknowledged" x-text="fieldErrors.strict_acknowledged"></p>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button"
                        @click="closeCreateModal()"
                        class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-medium hover:bg-slate-600">
                    Cancel
                </button>
                <button type="submit"
                        :disabled="saving"
                        class="px-4 py-2 rounded-md bg-amber-600 text-white text-sm font-semibold hover:bg-amber-500 disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!saving">{$submitLabel|default:'Create User'}</span>
                    <span x-show="saving">{$submittingLabel|default:'Creating...'}</span>
                </button>
            </div>
        </form>
    </div>
</div>
