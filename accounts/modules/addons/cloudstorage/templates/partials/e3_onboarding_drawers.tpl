{*
    Shared e3 Cloud Backup onboarding drawers.

    Renders the Beta confirmation drawer (#eb-beta-overlay) and the Username
    drawer (#eb-setpw-overlay). Reused by:
      - templates/welcome.tpl          (new-customer trial flow)
      - templates/e3backup_getting_started.tpl (existing-customer onboarding)

    The IDs and inline onclick handler names are intentionally identical in
    both contexts; each host page defines its own copy of ebBetaClose /
    ebBetaContinue / ebPwClose / ebPwSubmit in a {literal} block.

    Pass ebExistingClientOnboarding=true from the Getting Started page to
    surface the existing-client storage-billing notice and the re-enter
    portal password field. Defaults to false (welcome / trial flow).
*}
{assign var=ebExistingClientOnboarding value=$ebExistingClientOnboarding|default:false}

<div id="eb-beta-overlay" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 eb-drawer-backdrop" onclick="ebBetaClose()"></div>
    <div
        id="eb-beta-panel"
        class="absolute right-0 top-0 h-full eb-drawer w-full max-w-2xl translate-x-full overflow-y-auto transition-transform duration-300 ease-out"
    >
        <div class="eb-drawer-header">
            <div>
                <div class="eb-drawer-title">e3 Cloud Backup &mdash; Beta / Early Access</div>
                <p class="mt-1 text-sm text-[var(--eb-text-muted)]">
                    Thanks for trying our newest product. Please review what to expect before continuing.
                </p>
            </div>
            <button type="button" class="eb-modal-close" onclick="ebBetaClose()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="eb-drawer-body">
            <div class="rounded-md p-4 mb-4" style="background:rgba(124,58,237,0.10); border:1px solid rgba(124,58,237,0.40);">
                <p class="text-base font-semibold mb-2" style="color:#c4b5fd;">What "beta" means here</p>
                <ul class="text-sm text-[var(--eb-text-secondary)] space-y-2 pl-5 list-disc">
                    <li>Some workflows are still under active development; please expect rough edges.</li>
                    {if $ebExistingClientOnboarding}
                    <li>Support is best-effort during beta; email us with logs and we will respond as quickly as we can.</li>
                    {else}
                    <li>Your usage is free for 30 days &mdash; we will not invoice you during the trial.</li>
                    <li>If you decide not to continue, simply let the trial expire and you will not be charged.</li>
                    <li>Support is best-effort during beta; email us with logs and we will respond as quickly as we can.</li>
                    {/if}
                </ul>
            </div>

            {if $ebExistingClientOnboarding}
            {* Existing-client only: clarify what is free during beta versus the
               object storage that is still billed. Avoid the term "config
               options"; name the metered features in plain language. *}
            <div class="eb-alert eb-alert--info mb-4">
                <div>
                    <div class="eb-alert-title">What this costs during beta</div>
                    <div>
                        <p>While e3 Cloud Backup is in beta, the following are <strong>free</strong> for your account:</p>
                        <ul class="mt-2 space-y-1 pl-5 list-disc">
                            <li>Protected devices</li>
                            <li>Disk image backups</li>
                            <li>Guest VM backups (Hyper-V, Proxmox, VMware)</li>
                        </ul>
                        <p class="mt-2">
                            The e3 object storage your backups consume is still billed at your normal storage rate, since it uses your existing e3 object storage account.
                        </p>
                    </div>
                </div>
            </div>
            {/if}

            <label class="flex items-start gap-2 text-sm text-[var(--eb-text-secondary)]">
                <input type="checkbox" id="eb-beta-ack" class="mt-1">
                <span>I understand this product is in beta and I want to continue setting up e3 Cloud Backup.</span>
            </label>
        </div>
        <div class="eb-drawer-footer justify-between">
            <button type="button" class="eb-btn eb-btn-secondary eb-btn-md" onclick="ebBetaClose()">Cancel</button>
            <button id="eb-beta-continue" type="button" class="eb-btn eb-btn-orange eb-btn-md" onclick="ebBetaContinue()">Continue</button>
        </div>
    </div>
</div>

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
                <div id="eb-username-row" class="hidden">
                    <label for="eb-username" class="eb-field-label">Backup agent username</label>
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
