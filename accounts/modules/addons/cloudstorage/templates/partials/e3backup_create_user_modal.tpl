<div x-show="showCreateModal"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="eb-modal-backdrop absolute inset-0" @click="closeCreateModal()"></div>
    <div class="eb-modal relative z-10 w-full max-w-lg !p-0 overflow-hidden flex flex-col max-h-[min(90vh,720px)]" @click.stop>
        <div class="eb-modal-header !mb-0 shrink-0">
            <div class="min-w-0">
                <h2 class="eb-modal-title">{$modalTitle|default:'Add User'}</h2>
            </div>
            <button type="button" class="eb-modal-close" @click="closeCreateModal()" aria-label="Close">&times;</button>
        </div>

        <form @submit.prevent="createUser()" class="flex min-h-0 flex-1 flex-col">
            <div class="eb-modal-body !pt-4 space-y-4 overflow-y-auto min-h-0">
                <div x-show="formErrorMessage"
                     class="eb-alert eb-alert--danger !mb-0"
                     role="alert"
                     style="display: none;">
                    <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <div x-text="formErrorMessage"></div>
                </div>

                <div>
                    <label class="eb-field-label" for="e3-create-user-username">Username <span style="color: var(--eb-danger-text)">*</span></label>
                    <input id="e3-create-user-username"
                           type="text"
                           x-model.trim="form.username"
                           placeholder="Username"
                           class="eb-input"
                           :class="fieldErrors.username && 'is-error'">
                    <p class="eb-field-error" x-show="fieldErrors.username" x-cloak style="display: none;">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span x-text="fieldErrors.username"></span>
                    </p>
                </div>

                <div>
                    <div class="eb-field-label">Backup Type</div>
                    <div class="eb-subpanel !mb-0 space-y-3 !p-4">
                        <label class="eb-inline-choice cursor-pointer">
                            <input type="radio" x-model="form.backup_type" value="cloud_only" class="eb-radio-input" name="e3_create_backup_type">
                            <span>
                                <span class="font-semibold text-[var(--eb-text-primary)]">Cloud Backup Only</span>
                                <span class="mt-0.5 block eb-type-caption">S3, AWS, SFTP, Google Drive, Dropbox.</span>
                            </span>
                        </label>
                        <label class="eb-inline-choice cursor-pointer">
                            <input type="radio" x-model="form.backup_type" value="local" class="eb-radio-input" name="e3_create_backup_type">
                            <span>
                                <span class="font-semibold text-[var(--eb-text-primary)]">Local Agent Backup</span>
                                <span class="mt-0.5 block eb-type-caption">File, Disk Image, Windows Agent.</span>
                            </span>
                        </label>
                        <label class="eb-inline-choice cursor-pointer">
                            <input type="radio" x-model="form.backup_type" value="both" class="eb-radio-input" name="e3_create_backup_type">
                            <span>
                                <span class="font-semibold text-[var(--eb-text-primary)]">Both (Cloud + Local Agent)</span>
                                <span class="mt-0.5 block eb-type-caption">Full access to all backup types.</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div x-show="form.backup_type !== 'cloud_only'" x-cloak style="display: none;">
                    <div class="eb-field-label">Encryption Mode</div>
                    <div class="eb-subpanel !mb-0 space-y-3 !p-4">
                        <label class="eb-inline-choice cursor-pointer">
                            <input type="radio" x-model="form.encryption_mode" value="managed" class="eb-radio-input" name="e3_create_encryption_mode">
                            <span>
                                <span class="font-semibold text-[var(--eb-text-primary)]">Password - Managed Recovery</span>
                                <span class="mt-0.5 block eb-type-caption">Reset always possible.</span>
                            </span>
                        </label>

                        <label class="eb-inline-choice cursor-pointer">
                            <input type="radio" x-model="form.encryption_mode" value="strict" class="eb-radio-input" name="e3_create_encryption_mode">
                            <span class="font-semibold text-[var(--eb-text-primary)]">Strict Customer-Managed Encryption (Zero-Knowledge)</span>
                        </label>
                    </div>
                </div>

                <div x-show="form.backup_type !== 'cloud_only' && form.encryption_mode === 'managed'" x-cloak class="grid grid-cols-1 gap-4 sm:grid-cols-2" style="display: none;">
                    <div>
                        <label class="eb-field-label" for="e3-create-user-password">Password <span style="color: var(--eb-danger-text)">*</span></label>
                        <input id="e3-create-user-password"
                               type="password"
                               x-model="form.password"
                               placeholder="Minimum 8 characters"
                               class="eb-input"
                               :class="fieldErrors.password && 'is-error'">
                        <p class="eb-field-error" x-show="fieldErrors.password" x-cloak style="display: none;">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span x-text="fieldErrors.password"></span>
                        </p>
                    </div>
                    <div>
                        <label class="eb-field-label" for="e3-create-user-password-confirm">Confirm Password <span style="color: var(--eb-danger-text)">*</span></label>
                        <input id="e3-create-user-password-confirm"
                               type="password"
                               x-model="form.password_confirm"
                               placeholder="Repeat password"
                               class="eb-input"
                               :class="fieldErrors.password_confirm && 'is-error'">
                        <p class="eb-field-error" x-show="fieldErrors.password_confirm" x-cloak style="display: none;">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span x-text="fieldErrors.password_confirm"></span>
                        </p>
                    </div>
                </div>

                <div>
                    <label class="eb-field-label" for="e3-create-user-email">Email (for reports) <span style="color: var(--eb-danger-text)">*</span></label>
                    <input id="e3-create-user-email"
                           type="email"
                           x-model.trim="form.email"
                           placeholder="alerts@example.com"
                           class="eb-input"
                           :class="fieldErrors.email && 'is-error'">
                    <p class="eb-field-error" x-show="fieldErrors.email" x-cloak style="display: none;">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span x-text="fieldErrors.email"></span>
                    </p>
                </div>

                {if $showTenantSelector|default:true && $isMspClient}
                <div>
                    <label class="eb-field-label" for="e3-create-user-tenant-locked">Tenant</label>
                    {if $lockTenantField|default:false}
                        <input id="e3-create-user-tenant-locked"
                               type="text"
                               :value="createTenantLabel()"
                               disabled
                               class="eb-input">
                        <p class="eb-field-help">This user will be scoped to the selected tenant.</p>
                    {else}
                        <div class="relative"
                             x-data="{
                                 isOpen: false,
                                 menuTop: 0,
                                 menuLeft: 0,
                                 menuWidth: 0,
                                 positionTenantMenu() {
                                     const el = this.$refs.e3TenantTrigger;
                                     if (!el) return;
                                     const r = el.getBoundingClientRect();
                                     this.menuTop = r.bottom + 6;
                                     this.menuLeft = r.left;
                                     this.menuWidth = r.width;
                                 },
                                 toggleTenantMenu() {
                                     this.isOpen = !this.isOpen;
                                     if (this.isOpen) {
                                         this.$nextTick(() => this.positionTenantMenu());
                                     }
                                 },
                                 bindTenantMenuScrollParent() {
                                     const body = this.$el.closest('.eb-modal-body');
                                     if (body && !this._e3TenantMenuScrollBound) {
                                         this._e3TenantMenuScrollBound = true;
                                         body.addEventListener('scroll', () => {
                                             if (this.isOpen) this.positionTenantMenu();
                                         }, { passive: true });
                                     }
                                 }
                             }"
                             x-init="bindTenantMenuScrollParent()"
                             @resize.window="isOpen && positionTenantMenu()"
                             @click.away="isOpen = false">
                            <button type="button"
                                    x-ref="e3TenantTrigger"
                                    class="eb-menu-trigger"
                                    @click="toggleTenantMenu()"
                                    aria-haspopup="listbox"
                                    :aria-expanded="isOpen">
                                <span class="min-w-0 truncate text-left" x-text="createTenantLabel()"></span>
                                <svg class="h-4 w-4 shrink-0 text-[var(--eb-text-muted)] transition-transform" :class="isOpen && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="isOpen"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="opacity-0 scale-95 origin-top"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95 origin-top"
                                 class="eb-dropdown-menu fixed !max-h-72 !min-w-0 !p-0 overflow-hidden flex flex-col"
                                 :style="isOpen ? { top: menuTop + 'px', left: menuLeft + 'px', width: menuWidth + 'px', zIndex: 200 } : {}"
                                 role="listbox">
                                <div class="border-b border-[var(--eb-border-subtle)] p-2 shrink-0">
                                    <input type="text"
                                           x-model="tenantAssignSearch"
                                           placeholder="Search tenants"
                                           class="eb-input !py-2 text-xs">
                                </div>
                                <div class="max-h-64 overflow-y-auto p-1">
                                    <button type="button"
                                            class="eb-menu-option"
                                            :class="form.tenant_id === '' && 'is-active'"
                                            role="option"
                                            @click="form.tenant_id=''; isOpen=false;">
                                        Direct (No Tenant)
                                    </button>
                                    <template x-for="tenant in filteredAssignTenants" :key="'assign-' + (tenant.public_id || tenant.id)">
                                        <button type="button"
                                                class="eb-menu-option"
                                                :class="String(form.tenant_id) === String(tenant.public_id || tenant.id) && 'is-active'"
                                                role="option"
                                                @click="form.tenant_id = String(tenant.public_id || tenant.id); isOpen=false;">
                                            <span x-text="tenant.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <p class="eb-field-help">Optional. Leave blank for direct scope.</p>
                    {/if}
                    <p class="eb-field-error" x-show="fieldErrors.tenant_id" x-cloak style="display: none;">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <span x-text="fieldErrors.tenant_id"></span>
                    </p>
                </div>
                {/if}

                <div x-show="form.backup_type !== 'cloud_only'" x-cloak class="eb-subpanel !mb-0 space-y-3 !p-4" style="display: none;">
                    <h3 class="eb-type-h4 text-[var(--eb-text-primary)]">Acknowledgement</h3>

                    <div x-show="form.encryption_mode === 'managed'" x-cloak style="display: none;">
                        <label class="eb-inline-choice cursor-pointer">
                            <input type="checkbox" x-model="form.managed_acknowledged" class="eb-check-input">
                            <span>I understand authorized account owners can reset encryption password for this User.</span>
                        </label>
                        <p class="eb-field-error" x-show="fieldErrors.managed_acknowledged" x-cloak style="display: none;">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span x-text="fieldErrors.managed_acknowledged"></span>
                        </p>
                    </div>

                    <div x-show="form.encryption_mode === 'strict'" x-cloak class="flex flex-col gap-4 border-t border-[var(--eb-border-subtle)] pt-4" style="display: none;">
                        <div class="eb-alert eb-alert--warning !mb-0">
                            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                            <div>Your recovery key will be shown once and can only be downloaded one time.</div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <p class="eb-type-caption text-[var(--eb-text-secondary)]">We do not store this key on eazyBackup, and admin reset is disabled.</p>
                            <p class="eb-type-caption text-[var(--eb-text-secondary)]">If the recovery key is lost, encrypted data cannot be recovered.</p>
                            <p class="eb-type-caption text-[var(--eb-text-secondary)]">Save the recovery key somewhere extremely safe (password manager, secure vault, offline backup).</p>
                            <p class="eb-type-caption text-[var(--eb-text-secondary)]">Recommended only for customers with legal/compliance requirements.</p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <p class="eb-field-label !mb-0">Download Recovery Key (one-time)</p>
                            <button type="button"
                                    @click="downloadRecoveryKey()"
                                    :disabled="form.recovery_key_downloaded || saving"
                                    class="eb-btn eb-btn-warning eb-btn-sm self-start">
                                Download Recovery Key
                            </button>
                            <p class="eb-field-help !mt-0">One-time download only</p>
                            <p class="eb-type-caption text-[var(--eb-success-text)]" x-show="form.recovery_key_downloaded" x-cloak style="display: none;">Downloaded. This key will not be shown again.</p>
                            <p class="eb-field-error !mt-0" x-show="fieldErrors.recovery_key_downloaded" x-cloak style="display: none;">
                                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                </svg>
                                <span x-text="fieldErrors.recovery_key_downloaded"></span>
                            </p>
                        </div>

                        <label class="eb-inline-choice cursor-pointer">
                            <input type="checkbox" x-model="form.strict_acknowledged" class="eb-check-input">
                            <span>&quot;I understand admin reset is disabled in Strict mode, the recovery key is shown once and not stored by eazyBackup, and data recovery is not possible without it.&quot;</span>
                        </label>
                        <p class="eb-field-error" x-show="fieldErrors.strict_acknowledged" x-cloak style="display: none;">
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <span x-text="fieldErrors.strict_acknowledged"></span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="eb-modal-footer shrink-0 !mt-0">
                <button type="button"
                        @click="closeCreateModal()"
                        class="eb-btn eb-btn-secondary eb-btn-sm">
                    Cancel
                </button>
                <button type="submit"
                        :disabled="saving"
                        class="eb-btn eb-btn-primary eb-btn-sm">
                    <span x-show="!saving">{$submitLabel|default:'Create User'}</span>
                    <span x-show="saving" x-cloak style="display: none;">{$submittingLabel|default:'Creating...'}</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div x-show="showRecoveryKeyCloseWarning"
     x-cloak
     class="fixed inset-0 z-[60] flex items-center justify-center p-4"
     role="presentation">
    <div class="eb-modal-backdrop absolute inset-0" @click="cancelRecoveryKeyCloseWarning()"></div>
    <div class="eb-modal eb-modal--confirm relative z-10 w-full max-w-md !p-0 overflow-hidden"
         @click.stop
         role="dialog"
         aria-modal="true"
         aria-labelledby="e3-recovery-close-warning-title">
        <div class="eb-modal-header">
            <div class="min-w-0">
                <h2 id="e3-recovery-close-warning-title" class="eb-modal-title">Close without recovery key?</h2>
                <p class="eb-modal-subtitle">Strict mode requires a one-time recovery key download.</p>
            </div>
            <button type="button" class="eb-modal-close" @click="cancelRecoveryKeyCloseWarning()" aria-label="Dismiss">&times;</button>
        </div>
        <div class="eb-modal-body !pt-4">
            <div class="eb-alert eb-alert--warning !mb-0">
                <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div>You haven&apos;t downloaded the recovery key. If you continue, you will not be able to recover encrypted data later.</div>
            </div>
        </div>
        <div class="eb-modal-footer !mt-0">
            <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="cancelRecoveryKeyCloseWarning()">Go back</button>
            <button type="button" class="eb-btn eb-btn-danger-solid eb-btn-sm" @click="confirmCloseCreateWithoutRecoveryKey()">Close anyway</button>
        </div>
    </div>
</div>
