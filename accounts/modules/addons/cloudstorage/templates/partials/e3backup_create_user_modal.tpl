<template x-teleport="body">
<div x-show="showCreateModal"
     x-cloak
     id="e3backupCreateUserModalHost"
     class="fixed inset-0 z-[2200] flex items-center justify-center p-2 sm:p-4"
     @keydown.escape.window="closeCreateModal()">
    <div class="eb-modal-backdrop absolute inset-0" @click="closeCreateModal()"></div>
    <div class="eb-modal relative z-10 w-full max-w-4xl !p-0 overflow-hidden flex flex-col max-h-[min(100dvh,720px)] sm:max-h-[min(90vh,720px)]"
         @click.stop
         role="dialog"
         aria-modal="true"
         aria-labelledby="e3-create-user-title">
        <div class="eb-modal-header !mb-0 shrink-0">
            <div class="min-w-0">
                <h2 id="e3-create-user-title" class="eb-modal-title">{$modalTitle|default:'Add User'}</h2>
            </div>
            <button type="button" class="eb-modal-close" @click="closeCreateModal()" aria-label="Close">&times;</button>
        </div>

        <form @submit.prevent="createUser()" @input="clearCreatePageError()" @change="clearCreatePageError()" class="flex min-h-0 flex-1 flex-col">
            <div class="eb-modal-body !pt-4 overflow-y-auto min-h-0">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="space-y-4 min-w-0">
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

                        <div class="eb-subpanel !p-4">
                            <p class="eb-field-label !mb-1">Managed Recovery</p>
                            <p class="eb-type-caption">Authorized account owners can reset this password. Supports Local Agent, Microsoft 365, and SaaS backups.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

                        <div class="eb-subpanel !mb-0 !p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="eb-field-label !mb-1">Email backup reports</p>
                                    <p class="eb-type-caption">Send backup completion reports for this User.</p>
                                </div>
                                <button type="button"
                                        class="eb-toggle shrink-0"
                                        @click="notificationForm.enabled = !notificationForm.enabled"
                                        aria-label="Email backup reports"
                                        :aria-pressed="notificationForm.enabled"
                                        :aria-expanded="notificationForm.enabled ? 'true' : 'false'"
                                        aria-controls="e3-create-report-settings">
                                    <div class="eb-toggle-track" :class="notificationForm.enabled && 'is-on'">
                                        <div class="eb-toggle-thumb"></div>
                                    </div>
                                    <span class="eb-toggle-label" x-text="notificationForm.enabled ? 'On' : 'Off'"></span>
                                </button>
                            </div>

                            <div id="e3-create-report-settings"
                                 x-show="notificationForm.enabled"
                                 x-cloak
                                 x-transition
                                 class="mt-4 space-y-4"
                                 style="display: none;">
                                <div>
                                    <label class="eb-field-label" for="e3-create-notify-email-input">Report recipient emails</label>
                                    <p class="eb-field-help">If empty, reports go to your account owner email.</p>
                                    <div class="mt-2 space-y-2">
                                        <div class="flex flex-wrap gap-2" x-show="notificationForm.emails.length">
                                            <template x-for="(email, index) in notificationForm.emails" :key="email + '-' + index">
                                                <span class="eb-badge eb-badge--neutral inline-flex items-center gap-1">
                                                    <span x-text="email"></span>
                                                    <button type="button"
                                                            class="eb-btn eb-btn-ghost eb-btn-xs"
                                                            style="min-width: auto; padding: 0 4px;"
                                                            @click="removeNotificationEmail(index)"
                                                            :aria-label="'Remove ' + email">&times;</button>
                                                </span>
                                            </template>
                                        </div>
                                        <div class="flex flex-wrap items-start gap-2">
                                            <input id="e3-create-notify-email-input"
                                                   type="email"
                                                   x-model.trim="newNotifyEmail"
                                                   class="eb-input"
                                                   style="min-width: 220px; flex: 1 1 220px;"
                                                   placeholder="name@example.com"
                                                   @keydown.enter.prevent="addNotificationEmail()">
                                            <button type="button"
                                                    class="eb-btn eb-btn-secondary eb-btn-sm"
                                                    @click="addNotificationEmail()">
                                                Add
                                            </button>
                                        </div>
                                        <p class="eb-field-error" x-show="fieldErrors.notify_emails" x-text="fieldErrors.notify_emails"></p>
                                    </div>
                                </div>

                                <div>
                                    <p class="eb-field-label !mb-2">Notify on</p>
                                    <div class="flex flex-wrap items-center gap-3">
                                    <label class="inline-flex items-center gap-2 text-sm text-[var(--eb-text-primary)]">
                                        <button type="button"
                                                class="eb-toggle shrink-0"
                                                @click="notificationForm.notify_on_success = !notificationForm.notify_on_success"
                                                :aria-pressed="notificationForm.notify_on_success">
                                            <div class="eb-toggle-track" :class="notificationForm.notify_on_success && 'is-on'">
                                                <div class="eb-toggle-thumb"></div>
                                            </div>
                                        </button>
                                        Success
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-[var(--eb-text-primary)]">
                                        <button type="button"
                                                class="eb-toggle shrink-0"
                                                @click="notificationForm.notify_on_warning = !notificationForm.notify_on_warning"
                                                :aria-pressed="notificationForm.notify_on_warning">
                                            <div class="eb-toggle-track" :class="notificationForm.notify_on_warning && 'is-on'">
                                                <div class="eb-toggle-thumb"></div>
                                            </div>
                                        </button>
                                        Warning
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm text-[var(--eb-text-primary)]">
                                        <button type="button"
                                                class="eb-toggle shrink-0"
                                                @click="notificationForm.notify_on_failure = !notificationForm.notify_on_failure"
                                                :aria-pressed="notificationForm.notify_on_failure">
                                            <div class="eb-toggle-track" :class="notificationForm.notify_on_failure && 'is-on'">
                                                <div class="eb-toggle-thumb"></div>
                                            </div>
                                        </button>
                                        Failure
                                    </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {if $showTenantSelector|default:true && $isMspClient}
                        <div>
                            <label class="eb-field-label" for="e3-create-user-tenant">Tenant</label>
                            {if $lockTenantField|default:false}
                                <input id="e3-create-user-tenant"
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
                                            id="e3-create-user-tenant"
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
                                         :style="isOpen ? { top: menuTop + 'px', left: menuLeft + 'px', width: menuWidth + 'px', zIndex: 2300 } : {}"
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
                    </div>

                    <aside class="hidden lg:block min-w-0 lg:sticky lg:top-0 lg:self-start">
                        {include file="{$smarty.const.ROOTDIR}/modules/addons/cloudstorage/templates/partials/e3backup_pricing_panel.tpl"}
                    </aside>
                </div>

                <div class="lg:hidden mt-4">
                    <button type="button"
                            class="flex w-full items-center justify-between gap-2 rounded-lg border border-[var(--eb-border-default)] px-3 py-2 text-sm font-medium text-[var(--eb-text-primary)]"
                            @click="showPricingDisclosure = !showPricingDisclosure"
                            :aria-expanded="showPricingDisclosure ? 'true' : 'false'"
                            aria-controls="e3-create-mobile-pricing">
                        <span>Billing &amp; pricing</span>
                        <svg class="h-4 w-4 shrink-0 text-[var(--eb-text-muted)] transition-transform" :class="showPricingDisclosure && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div id="e3-create-mobile-pricing"
                         x-show="showPricingDisclosure"
                         x-cloak
                         class="mt-3"
                         role="region"
                         aria-label="Billing and pricing">
                        {include file="{$smarty.const.ROOTDIR}/modules/addons/cloudstorage/templates/partials/e3backup_pricing_panel.tpl"}
                    </div>
                </div>
            </div>

            <div class="eb-modal-footer shrink-0 !mt-0">
                <button type="button"
                        @click="closeCreateModal()"
                        :disabled="saving"
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
</template>
