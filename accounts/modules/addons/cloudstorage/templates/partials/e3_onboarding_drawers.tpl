{*
    Shared e3 Cloud Backup onboarding drawer.

    Renders the Username drawer (#eb-setpw-overlay). Reused by:
      - templates/welcome.tpl (new-customer trial flow)

    The IDs and inline onclick handler names are intentionally identical in
    both contexts; each host page defines its own copy of ebPwClose /
    ebPwSubmit in a {literal} block.

    Pass ebExistingClientOnboarding=true to surface the existing-client
    storage-billing notice and the re-enter portal password field. Defaults
    to false (welcome / trial flow).
*}
{assign var=ebExistingClientOnboarding value=$ebExistingClientOnboarding|default:false}

<div id="eb-setpw-overlay" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 eb-drawer-backdrop" onclick="ebPwClose()"></div>
    <div
        id="eb-setpw-panel"
        class="absolute right-0 top-0 h-full eb-drawer w-full max-w-2xl translate-x-full overflow-y-auto transition-transform duration-300 ease-out"
    >
        <div class="eb-drawer-header">
            <div>
                <div id="eb-setpw-title" class="eb-drawer-title">Pick your backup agent username</div>
                <p id="eb-setpw-subtitle" class="mt-1 text-sm text-[var(--eb-text-muted)]">
                    Choose the username your backup agent will use to sign in. Your portal password (set earlier) will also be the password for this backup agent.
                </p>
                <p id="eb-username-hint" class="mt-2 hidden text-sm text-[var(--eb-text-muted)]"></p>
            </div>
            <button type="button" class="eb-modal-close" onclick="ebPwClose()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="eb-drawer-body">
            <div id="eb-pw-general-error" class="eb-alert eb-alert--danger hidden">
                <div>
                    <div class="eb-alert-title">Provisioning failed</div>
                    <div id="eb-pw-general-error-body"></div>
                </div>
            </div>
            <form id="eb-setpw-form" class="space-y-5" onsubmit="return ebPwSubmit(event);">
                <input type="hidden" name="product_choice" id="eb-product-choice" value="">
                <input type="hidden" name="encryption_mode" id="eb-e3-encryption-mode" value="managed">
                <div id="eb-username-row" class="hidden">
                    <label for="eb-username" id="eb-username-label" class="eb-field-label">Backup agent username</label>
                    <input
                        id="eb-username"
                        name="username"
                        type="text"
                        autocomplete="username"
                        class="eb-input w-full"
                        placeholder="Choose a username (a-z, 0-9, ., _, -)"
                    />
                    <p class="eb-field-help">Allowed characters: letters, numbers, period, underscore, dash. Minimum 8 characters. You will sign in from the backup agent using this username and your portal password.</p>
                    <p id="eb-err-username" class="eb-field-error hidden"></p>
                </div>

                <div id="eb-e3-new-password-row" class="hidden grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="eb-e3-backup-password" class="eb-field-label">Backup user password</label>
                        <input
                            id="eb-e3-backup-password"
                            name="backup_password"
                            type="password"
                            autocomplete="new-password"
                            class="eb-input w-full"
                            placeholder="Minimum 8 characters"
                        />
                        <p id="eb-err-e3-password" class="eb-field-error hidden"></p>
                    </div>
                    <div>
                        <label for="eb-e3-backup-password-confirm" class="eb-field-label">Confirm password</label>
                        <input
                            id="eb-e3-backup-password-confirm"
                            name="backup_password_confirm"
                            type="password"
                            autocomplete="new-password"
                            class="eb-input w-full"
                            placeholder="Repeat password"
                        />
                        <p id="eb-err-e3-password-confirm" class="eb-field-error hidden"></p>
                    </div>
                </div>

                <div id="eb-e3-encryption-row" class="hidden space-y-4">
                    <div>
                        <div class="eb-field-label">Encryption Mode</div>
                        <div id="eb-e3-strict-warning" class="eb-alert eb-alert--warning hidden !mb-3">
                            <div>This User will be restricted to Local Agent backups only. MS365 and SaaS require managed encryption.</div>
                        </div>
                        <div class="eb-subpanel !mb-0 space-y-3 !p-4">
                            <label class="eb-inline-choice cursor-pointer">
                                <input type="radio" name="eb_e3_encryption_mode" value="managed" class="eb-radio-input" checked onchange="ebE3EncryptionModeChanged('managed')">
                                <span>
                                    <span class="font-semibold text-[var(--eb-text-primary)]">Password - Managed Recovery</span>
                                    <span class="mt-0.5 block eb-type-caption">Reset always possible. Local Agent, MS365, and SaaS backups.</span>
                                </span>
                            </label>
                            <label class="eb-inline-choice cursor-pointer">
                                <input type="radio" name="eb_e3_encryption_mode" value="strict" class="eb-radio-input" onchange="ebE3EncryptionModeChanged('strict')">
                                <span class="font-semibold text-[var(--eb-text-primary)]">Strict Customer-Managed Encryption (Zero-Knowledge)</span>
                            </label>
                        </div>
                    </div>

                    <div id="eb-e3-managed-ack-row" class="eb-subpanel !mb-0 space-y-3 !p-4">
                        <h3 class="eb-type-h4 text-[var(--eb-text-primary)]">Acknowledgement</h3>
                        <label class="eb-inline-choice cursor-pointer">
                            <input type="checkbox" id="eb-e3-managed-ack" class="eb-check-input">
                            <span>I understand authorized account owners can reset encryption password for this User.</span>
                        </label>
                        <p id="eb-err-e3-managed-ack" class="eb-field-error hidden"></p>
                    </div>

                    <div id="eb-e3-strict-ack-row" class="hidden eb-subpanel !mb-0 space-y-3 !p-4">
                        <h3 class="eb-type-h4 text-[var(--eb-text-primary)]">Zero-Knowledge acknowledgement</h3>
                        <div class="eb-alert eb-alert--warning !mb-0">
                            <div>Admin reset is disabled in Strict mode. If the recovery key is lost, encrypted data cannot be recovered.</div>
                        </div>
                        <label class="eb-inline-choice cursor-pointer">
                            <input type="checkbox" id="eb-e3-strict-ack" class="eb-check-input">
                            <span>I understand admin reset is disabled, the recovery key is shown once and not stored by eazyBackup, and data recovery is not possible without it.</span>
                        </label>
                        <p id="eb-err-e3-strict-ack" class="eb-field-error hidden"></p>
                    </div>
                </div>

                {if $ebExistingClientOnboarding}
                <div id="eb-existing-pw-row">
                    <label for="eb-existing-portal-password" class="eb-field-label">Confirm your portal password</label>
                    <input
                        id="eb-existing-portal-password"
                        name="new_password"
                        type="password"
                        autocomplete="current-password"
                        class="eb-input w-full"
                        placeholder="Your eazyBackup portal password"
                    />
                    <p class="eb-field-help">Re-enter the password you use to sign in to this portal. Your backup agent will use the same password to sign in.</p>
                    <p id="eb-err-existing-pw" class="eb-field-error hidden"></p>
                </div>
                {/if}
                <div id="eb-no-username-row" class="hidden">
                    <div class="eb-alert eb-alert--info">
                        <div>
                            <div class="eb-alert-title">Ready to provision</div>
                            <div>We will use the portal password you just set. Click Continue to finish provisioning this product.</div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="eb-drawer-footer justify-between">
            <button type="button" class="eb-btn eb-btn-secondary eb-btn-md" onclick="ebPwClose()">Cancel</button>
            <button id="eb-pw-submit" type="submit" form="eb-setpw-form" class="eb-btn eb-btn-orange eb-btn-md">
                Continue
            </button>
        </div>
    </div>
</div>
