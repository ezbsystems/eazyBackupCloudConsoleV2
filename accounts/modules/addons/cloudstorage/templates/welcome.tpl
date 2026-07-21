<div class="eb-page">
    <div class="eb-page-inner py-8">
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="eb-panel">
                <div class="eb-page-header">
                    <div>
                        {if $ebWelcomeExistingClient}
                        <div class="eb-type-eyebrow">Add a service</div>
                        <h1 class="eb-page-title mt-2">Welcome to eazyBackup</h1>
                        <p class="eb-page-description">
                            Choose an additional product to activate on your account. You can manage all services from your client area.
                        </p>
                        {else}
                        <div class="eb-type-eyebrow">Getting Started</div>
                        <h1 class="eb-page-title mt-2">Welcome to eazyBackup</h1>
                        <p class="eb-page-description">
                            Choose the first product you want to activate. You can add more services later from your client area.
                        </p>
                        {/if}
                    </div>
                    {if $ebWelcomeExistingClient}
                    <div class="eb-badge eb-badge--default">Existing account</div>
                    {else}
                    <div class="eb-badge eb-badge--orange">New Account Setup</div>
                    {/if}
                </div>

                <div class="space-y-6">
                    <section class="space-y-6">
                        {* <div class="eb-card-raised">
                            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="eb-type-h3">Choose your first product</div>
                                </div>
                                <div class="w-full max-w-sm space-y-3">
                                    <div class="flex items-start gap-3">
                                        <div class="eb-badge eb-badge--default">1</div>
                                        <div>
                                            <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Pick a product</div>
                                            <p class="eb-type-caption mt-1">Select the service you want to activate first.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="eb-badge eb-badge--default">2</div>
                                        <div>
                                            <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Set your password</div>
                                            <p class="eb-type-caption mt-1">Cloud Backup and Microsoft 365 Backup also collect an application username.</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="eb-badge eb-badge--default">3</div>
                                        <div>
                                            <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Provision the service</div>
                                            <p class="eb-type-caption mt-1">The platform creates the order and redirects you into the correct next step.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> *}

                        <div class="eb-service-grid-shell">
                        {if $ebMustSetPortalPassword}
                            <div id="eb-product-lock-notice" class="eb-alert eb-alert--info mb-4">
                                <div>
                                    <div class="eb-alert-title">Set your portal password to continue</div>
                                    <div>The product picker unlocks once you create the password you will use to sign in to your client portal.</div>
                                </div>
                            </div>
                        {/if}
                        <div id="eb-product-select"
                             data-must-set-password="{if $ebMustSetPortalPassword}1{else}0{/if}"
                             class="grid grid-cols-1 gap-4 md:grid-cols-2 {if $ebHideLegacyCloudBackupCard}{if $EB_SHOW_E3_BACKUP}xl:grid-cols-4{else}xl:grid-cols-3{/if}{else}{if $EB_SHOW_E3_BACKUP}xl:grid-cols-5{else}xl:grid-cols-4{/if}{/if} {if $ebMustSetPortalPassword}is-locked{/if}">
                            {if !$ebHideLegacyCloudBackupCard}
                            <button
                                type="button"
                                class="eb-service-option is-popular text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--eb-primary)]"
                                data-choice="backup"
                                onclick="ebChooseProduct(this)"
                            >
                                <span class="eb-service-option-badge">Most Popular</span>
                                <div class="eb-service-option-inner">
                                    <div class="eb-service-option-icon">
                                        <img src="{$WEB_ROOT}/templates/eazyBackup/assets/icon/e-cloud-logo-orange.svg" alt="" class="h-7 w-7" loading="lazy" />
                                    </div>
                                    <div class="eb-service-option-copy">
                                        <h2 class="eb-service-option-title">Cloud Backup</h2>
                                        <p class="eb-service-option-body">
                                            Back up Windows, macOS, and servers directly to eazyBackup's Canadian cloud with encryption, retention policies, and fast restores.
                                        </p>
                                        <p class="eb-service-option-note">
                                            Best for end users, MSPs, and IT teams protecting PCs, laptops, and servers with the eazyBackup app.
                                        </p>
                                    </div>
                                </div>
                            </button>
                            {/if}

                            <button
                                type="button"
                                class="eb-service-option text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--eb-info-icon)]"
                                data-choice="storage"
                                onclick="ebChooseProduct(this)"
                            >
                                <div class="eb-service-option-inner">
                                    <div class="eb-service-option-icon">
                                        <img src="{$WEB_ROOT}/templates/eazyBackup/assets/icon/e3.svg" alt="" class="h-7 w-7" loading="lazy" />
                                    </div>
                                    <div class="eb-service-option-copy">
                                        <h2 class="eb-service-option-title">e3 Object Storage</h2>
                                        <p class="eb-service-option-body">
                                            S3-compatible e3 object storage for backups, archives, media, and application data stored in Canada.
                                        </p>
                                        <p class="eb-service-option-note">
                                            Best for teams that already have backup software or need a secure object storage target for their own tooling.
                                        </p>
                                    </div>
                                </div>
                            </button>

                            {if $EB_SHOW_E3_BACKUP}
                            <button
                                type="button"
                                class="eb-service-option is-beta text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--eb-primary)]"
                                data-choice="e3backup"
                                onclick="ebChooseE3Backup(this)"
                            >
                                <span class="eb-service-option-badge" style="background:#7c3aed;">Beta &mdash; Early Access</span>
                                <div class="eb-service-option-inner">
                                    <div class="eb-service-option-icon">
                                        <img src="{$WEB_ROOT}/templates/eazyBackup/assets/icon/e3.svg" alt="" class="h-7 w-7" loading="lazy" />
                                    </div>
                                    <div class="eb-service-option-copy">
                                        <h2 class="eb-service-option-title">e3 Cloud Backup</h2>
                                        <p class="eb-service-option-body">
                                            Next-generation eazyBackup agent powered by e3. Files, disk images, and Hyper-V VMs backed up directly to your e3 object storage bucket.
                                        </p>
                                        <p class="eb-service-option-note">
                                            Best for early-access customers and internal testing. Some workflows are still under active development.
                                        </p>
                                    </div>
                                </div>
                            </button>
                            {/if}

                            <button
                                type="button"
                                class="eb-service-option text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--eb-premium-icon)]"
                                data-choice="ms365"
                                onclick="ebChooseProduct(this)"
                            >
                                <div class="eb-service-option-inner">
                                    <div class="eb-service-option-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" fill="currentColor" class="h-7 w-7" aria-hidden="true">
                                            <path d="M20.13,32.5c-2.79-1.69-4.53-4.77-4.53-8.04V8.9c0-1.63,0.39-3.19,1.11-4.57L7.54,9.88C4.74,11.57,3,14.65,3,17.92v14.15 c0,1.59,0.42,3.14,1.16,4.5c0.69,1.12,1.67,2.06,2.88,2.74c2.53,1.42,5.51,1.36,7.98-0.15l8.02-4.9L20.13,32.5z M42.84,27.14 l-8.44-5.05v2.29c0,3.25-1.72,6.33-4.49,8.02l-13.84,8.47c-1.52,0.93-3.19,1.42-4.87,1.46l8.93,5.41c1.5,0.91,3.19,1.36,4.87,1.36 s3.37-0.45,4.87-1.36l9.08-5.5l3.52-2.13c0.27-0.16,0.53-0.34,0.78-0.54c0.08-0.05,0.16-0.11,0.23-0.16 c0.65-0.53,1.23-1.13,1.71-1.79c0.02-0.03,0.04-0.06,0.06-0.09c0.77-1.19,1.2-2.59,1.19-4.06C46.43,30.85,45.09,28.48,42.84,27.14z M42.46,9.88l-9.57-5.79l-3.02-1.83C29.45,2,29.01,1.79,28.56,1.61c-0.49-0.21-1-0.37-1.51-0.47c-1.84-0.38-3.76-0.08-5.46,0.89 c-2.5,1.43-3.99,3.99-3.99,6.87v9.6l2.8-1.65c2.84-1.67,6.36-1.66,9.19,0.03l14.28,8.54c1.29,0.78,2.35,1.81,3.12,3.02L47,17.92 C47,14.65,45.26,11.57,42.46,9.88z"/>
                                        </svg>
                                    </div>
                                    <div class="eb-service-option-copy">
                                        <h2 class="eb-service-option-title">Microsoft 365 Backup</h2>
                                        <p class="eb-service-option-body">
                                            Protect Exchange, OneDrive, SharePoint, and Teams with automated backups, retention controls, and point-in-time restore workflows.
                                        </p>
                                        <p class="eb-service-option-note">
                                            Cloud-native Microsoft 365 protection — connect your tenant and run backups without installing an agent.
                                        </p>
                                    </div>
                                </div>
                            </button>

                            <button
                                type="button"
                                class="eb-service-option text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--eb-warning-icon)]"
                                data-choice="cloud2cloud"
                                onclick="ebChooseProduct(this)"
                            >
                                <div class="eb-service-option-inner">
                                    <div class="eb-service-option-icon">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                        </svg>
                                    </div>
                                    <div class="eb-service-option-copy">
                                        <h2 class="eb-service-option-title">Cloud-to-Cloud Backup</h2>
                                        <p class="eb-service-option-body">
                                            Replicate Google Drive, Dropbox, and S3-provider data into secure Canadian e3 buckets on a schedule.
                                        </p>
                                        <p class="eb-service-option-note">
                                            Best for organizations consolidating cloud data into one protected recovery vault.
                                        </p>
                                    </div>
                                </div>
                            </button>
                        </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="toast-container" class="pointer-events-none fixed right-4 top-4 z-[120] space-y-2"></div>

<script src="{$WEB_ROOT}/modules/addons/eazybackup/templates/assets/js/ui.js"></script>

<style>
    .eb-service-grid-shell {
        position: relative;
        /* Cancel the parent .eb-panel horizontal padding (px-6 = 1.5rem) so the
           shell's background fills edge-to-edge inside the panel border. */
        margin-inline: -1.5rem;
        padding: 1.5rem;
        border-top: 1px solid var(--eb-border-subtle);
        border-bottom: 1px solid var(--eb-border-subtle);
        background: var(--eb-bg-base);
    }

    @media (min-width: 640px) {
        .eb-service-grid-shell {
            /* Inner padding can grow while the outer negative margin stays
               matched to the panel's 1.5rem horizontal padding. */
            padding: 2rem;
        }
    }

    .eb-service-option {
        position: relative;
        overflow: visible;
        display: block;
        height: 100%;
        cursor: pointer;
        border: 1px solid var(--eb-border-default);
        border-radius: 20px;
        background: var(--eb-bg-card);
        padding: 1.5rem;
        color: inherit;
        box-shadow: 0 2px 8px rgba(213, 93, 29, 0.05);
        transition: transform 200ms ease-out, border-color 200ms ease-out, box-shadow 200ms ease-out;
    }

    .eb-service-option:hover,
    .eb-service-option:focus-visible {
        transform: translateY(-2px);
        border-color: var(--eb-accent);
        box-shadow: 0 12px 36px rgba(213, 93, 29, 0.12);
    }

    .eb-service-option.is-popular {
        border: 2px solid var(--eb-primary);
        box-shadow: 0 8px 32px rgba(213, 93, 29, 0.15);
    }

    .eb-service-option.is-beta {
        border: 2px solid #7c3aed;
        box-shadow: 0 8px 32px rgba(124, 58, 237, 0.18);
    }
    .eb-service-option.is-beta .eb-service-option-icon {
        color: #7c3aed;
    }

    .eb-service-option-badge {
        position: absolute;
        top: -12px;
        right: 16px;
        z-index: 2;
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        background: var(--eb-primary);
        padding: 4px 12px;
        color: #fff;
        font-size: 9.5px;
        font-weight: 800;
        letter-spacing: 0.1em;
        text-transform: uppercase;
    }

    .eb-service-option-inner {
        display: flex;
        height: 100%;
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }

    .eb-service-option-icon {
        display: inline-flex;
        height: 52px;
        width: 52px;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        border: 1px solid var(--eb-border-default);
        background: var(--eb-bg-raised);
        color: var(--eb-primary);
        flex-shrink: 0;
    }

    .eb-service-option-icon img {
        display: block;
        max-width: 32px;
        max-height: 32px;
        width: auto;
    }

    .eb-service-option-icon svg {
        height: 32px;
        width: 32px;
        max-width: 32px;
        max-height: 32px;
    }

    .eb-service-option-copy {
        display: flex;
        min-width: 0;
        flex: 1;
        flex-direction: column;
        gap: 12px;
    }

    .eb-service-option-title {
        font-family: var(--eb-font-display);
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.15;
        color: var(--eb-text-primary);
    }

    .eb-service-option-body {
        font-size: 14px;
        line-height: 1.65;
        color: var(--eb-text-secondary);
    }

    .eb-service-option-note {
        margin-top: auto;
        border-top: 1px solid var(--eb-border-subtle);
        padding-top: 14px;
        font-size: 12px;
        line-height: 1.55;
        font-weight: 700;
        color: var(--eb-text-secondary);
    }

    #eb-card-overlay #stripeElements .StripeElement {
        display: block;
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--eb-border-default);
        color: var(--eb-text-primary);
        background: var(--eb-bg-input);
        border-radius: var(--eb-radius-md);
        outline: none;
    }

    #eb-card-overlay #stripeElements .form-group,
    #eb-card-overlay #stripeElements .row {
        margin-bottom: 12px;
    }

    #eb-card-overlay #stripeElements label {
        display: block;
        margin-bottom: 6px;
        color: var(--eb-text-secondary);
        font-size: 12.5px;
        font-weight: 600;
    }

    #eb-card-overlay #stripeElements .StripeElement--focus {
        border-color: var(--eb-primary);
        box-shadow: 0 0 0 1px var(--eb-ring);
    }

    #eb-card-overlay #stripeElements .StripeElement--invalid {
        border-color: var(--eb-warning-strong);
    }

    /* Round 2: portal-password gate locks product cards until password is set. */
    #eb-product-select.is-locked {
        position: relative;
        opacity: 0.55;
        filter: saturate(0.6);
        pointer-events: none;
        user-select: none;
    }

    #eb-product-select.is-locked::after {
        content: '';
        position: absolute;
        inset: -8px;
        border-radius: 14px;
        background: transparent;
        pointer-events: auto;
        cursor: not-allowed;
    }

    /* Round 2 fix: bulletproof the portal-password modal's hidden state so
       it can never intercept clicks meant for the beta drawer / product
       cards below it. The Tailwind .hidden utility usually does this for
       us, but a custom utility or layering rule could otherwise leave the
       modal claiming pointer events even while it's display:none. */
    #eb-portalpw-overlay.hidden {
        display: none !important;
        pointer-events: none !important;
    }
    #eb-portalpw-overlay:not(.hidden) {
        display: flex !important;
        pointer-events: auto;
    }
</style>

{include file="modules/addons/cloudstorage/templates/partials/e3_onboarding_drawers.tpl" ebExistingClientOnboarding=$ebWelcomeExistingClient|default:false}

<div id="eb-portalpw-overlay"
     class="fixed inset-0 z-[100] hidden items-center justify-center p-4"
     hidden
     role="dialog"
     aria-labelledby="eb-portalpw-title"
     aria-modal="true">
    <div class="eb-modal-backdrop fixed inset-0"></div>
    <div id="eb-portalpw-panel" class="eb-modal relative z-[101] flex flex-col !p-0">
        <div class="eb-modal-header">
            <div>
                <div id="eb-portalpw-title" class="eb-modal-title">Create your portal password</div>
                <p class="eb-modal-subtitle">
                    This is the password you will use to sign in to your eazyBackup client portal.
                </p>                
            </div>
        </div>
        <div class="eb-modal-body">
            <div id="eb-portalpw-general-error" class="eb-alert eb-alert--danger hidden">
                <div>
                    <div class="eb-alert-title">Unable to save password</div>
                    <div id="eb-portalpw-general-error-body"></div>
                </div>
            </div>
            <form id="eb-portalpw-form" class="space-y-5" onsubmit="return ebPortalPwSubmit(event);">
                <div>
                    <label for="eb-portalpw-new" class="eb-field-label">New password</label>
                    <input
                        id="eb-portalpw-new"
                        name="new_password"
                        type="password"
                        autocomplete="new-password"
                        class="eb-input w-full"
                        placeholder="At least 10 characters"
                        required
                        minlength="10"
                    />
                    <p class="eb-field-help">Minimum 10 characters. Pick something you can remember.</p>
                    <p id="eb-portalpw-err-new" class="eb-field-error hidden"></p>
                </div>
                <div>
                    <label for="eb-portalpw-confirm" class="eb-field-label">Confirm password</label>
                    <input
                        id="eb-portalpw-confirm"
                        name="new_password_confirm"
                        type="password"
                        autocomplete="new-password"
                        class="eb-input w-full"
                        placeholder="Re-enter your password"
                        required
                        minlength="10"
                    />
                    <p id="eb-portalpw-err-confirm" class="eb-field-error hidden"></p>
                </div>
            </form>
        </div>
        <div class="eb-modal-footer justify-end">
            <button id="eb-portalpw-submit" type="submit" form="eb-portalpw-form" class="eb-btn eb-btn-orange eb-btn-md">
                Save password and continue
            </button>
        </div>
    </div>
</div>

<div id="eb-storage-plan-overlay" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 eb-drawer-backdrop" onclick="ebStoragePlanClose()"></div>
    <div
        id="eb-storage-plan-panel"
        class="absolute right-0 top-0 h-full eb-drawer w-full max-w-2xl translate-x-full overflow-y-auto transition-transform duration-300 ease-out"
    >
        <div class="eb-drawer-header">
            <div>
                <div class="eb-drawer-title">Choose Your Cloud Storage Plan</div>
                <p class="mt-1 text-sm text-[var(--eb-text-muted)]">
                    Pick how you want to start with e3 Cloud Storage.
                </p>
            </div>
            <button type="button" class="eb-modal-close" onclick="ebStoragePlanClose()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="eb-drawer-body">
            <input type="hidden" id="eb-storage-tier" value="">
            <div class="space-y-4">
                <button
                    type="button"
                    onclick="ebSelectStorageTier('trial_limited')"
                    class="group eb-card w-full cursor-pointer text-left transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--eb-info-border)] hover:bg-[var(--eb-bg-hover)] hover:shadow-[var(--eb-shadow-md)]"
                >
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] text-[var(--eb-text-secondary)] transition-colors group-hover:text-[var(--eb-info-icon)]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="eb-card-title text-base">Free Trial</div>
                                <span class="eb-badge eb-badge--info">No Card Required</span>
                            </div>
                            <p class="eb-type-body mt-3 text-sm">Try e3 Cloud Storage risk-free for 30 days with a 1 TiB quota.</p>
                            <ul class="mt-4 space-y-2 pl-5 text-sm text-[var(--eb-text-secondary)]">
                                <li class="list-disc">30 days free</li>
                                <li class="list-disc">1 TiB storage limit</li>
                                <li class="list-disc">No credit card required</li>
                            </ul>
                        </div>
                    </div>
                </button>

                <button
                    type="button"
                    onclick="ebSelectStorageTier('trial_unlimited')"
                    class="group eb-card-orange relative w-full cursor-pointer text-left transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--eb-primary)] hover:shadow-[var(--eb-shadow-md)]"
                >
                    <span class="absolute right-4 top-4 eb-badge eb-badge--orange">Recommended</span>
                    <div class="flex items-start gap-4 pr-16">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl border border-[var(--eb-primary-border)] bg-[var(--eb-brand-orange-soft)] text-[var(--eb-brand-orange)]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.746 3.746 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12Z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="eb-card-title text-base">Ready to Purchase?</div>
                            <p class="eb-type-body mt-3 text-sm">Start the same 30-day trial with unlimited storage after you save a payment method.</p>
                            <ul class="mt-4 space-y-2 pl-5 text-sm text-[var(--eb-text-secondary)]">
                                <li class="list-disc">30 days free</li>
                                <li class="list-disc">Unlimited storage during onboarding</li>
                                <li class="list-disc">Pay only for what you use after the trial</li>
                            </ul>
                        </div>
                    </div>
                </button>
            </div>

            <p class="eb-type-caption mt-6 text-center">
                You can upgrade or add a payment method at any time from your dashboard.
            </p>
        </div>
    </div>
</div>

<div id="eb-card-overlay" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 eb-drawer-backdrop" onclick="ebCardClose()"></div>
    <div
        id="eb-card-panel"
        class="absolute right-0 top-0 h-full eb-drawer w-full max-w-2xl translate-x-full overflow-y-auto transition-transform duration-300 ease-out"
    >
        <div class="eb-drawer-header">
            <div>
                <div class="eb-drawer-title">Add a Payment Method</div>
                <p class="mt-1 text-sm text-[var(--eb-text-muted)]">
                    Save a card to start the unlimited e3 Cloud Storage trial.
                </p>
            </div>
            <button type="button" class="eb-modal-close" onclick="ebCardClose()" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="eb-drawer-body">
            <div id="eb-card-error" class="eb-alert eb-alert--danger hidden">
                <div>
                    <div class="eb-alert-title">Unable to save payment method</div>
                    <div id="eb-card-error-body"></div>
                </div>
            </div>

            <form
                id="eb-addcard-form"
                class="frm-credit-card-input space-y-4"
                method="post"
                action="{routePath('account-paymentmethods-add')}"
                onsubmit="return ebCardSubmit(event);"
            >
                <input type="hidden" name="token" value="{$token|default:$csrfToken}" />
                <input type="radio" name="type" value="token_stripe" data-tokenised="true" data-gateway="stripe" checked class="hidden" aria-hidden="true" />
                <input type="hidden" name="paymentmethod" id="eb-paymentmethod" value="stripe" />
                <input type="hidden" name="billingcontact" id="ebBillingContact" value="0" />
                <div class="gateway-errors assisted-cc-input-feedback hidden rounded-md border border-rose-500/60 bg-rose-500/10 px-3 py-2 text-center text-xs text-rose-100"></div>
                <div class="cc-details"></div>
                <input type="hidden" name="billing_name" id="ebBillingName" value="" />
                <input type="hidden" name="billing_address_1" id="ebBillingAddress1" value="" />
                <input type="hidden" name="billing_address_2" id="ebBillingAddress2" value="" />
                <input type="hidden" name="billing_city" id="ebBillingCity" value="" />
                <input type="hidden" name="billing_state" id="ebBillingState" value="" />
                <input type="hidden" name="billing_postcode" id="ebBillingPostcode" value="" />
                <input type="hidden" name="billing_country" id="ebBillingCountry" value="" />
            </form>
        </div>
        <div class="eb-drawer-footer justify-between">
            <button type="button" class="eb-btn eb-btn-secondary eb-btn-md" onclick="ebCardClose()">Back</button>
            <button id="eb-card-submit" type="submit" form="eb-addcard-form" class="eb-btn eb-btn-orange eb-btn-md">
                Save Card and Continue
            </button>
        </div>
    </div>
</div>

<script>
window.EB_WEB_ROOT = '{$WEB_ROOT}';
window.EB_CSRF_TOKEN = '{$token|default:$csrfToken}';
window.EB_WELCOME_EXISTING_CLIENT = {if $ebWelcomeExistingClient}true{else}false{/if};
window.EB_WELCOME_UNIFIED_ENABLED = {if $ebWelcomeUnifiedEnabled|default:false}true{else}false{/if};
if (window.csrfToken && !window.EB_CSRF_TOKEN) {
    window.EB_CSRF_TOKEN = window.csrfToken;
}
</script>

<script>
{literal}
    function ebDrawerState(overlayId, panelId, isOpen) {
        var overlay = document.getElementById(overlayId);
        var panel = document.getElementById(panelId);
        if (!overlay || !panel) {
            return;
        }

        if (isOpen) {
            overlay.classList.remove('hidden');
            requestAnimationFrame(function () {
                panel.classList.remove('translate-x-full');
                panel.classList.add('translate-x-0');
            });
            document.body.classList.add('overflow-hidden');
            return;
        }

        panel.classList.add('translate-x-full');
        panel.classList.remove('translate-x-0');
        setTimeout(function () {
            overlay.classList.add('hidden');
            var portalPw = document.getElementById('eb-portalpw-overlay');
            if (
                document.getElementById('eb-setpw-overlay').classList.contains('hidden') &&
                document.getElementById('eb-storage-plan-overlay').classList.contains('hidden') &&
                document.getElementById('eb-card-overlay').classList.contains('hidden') &&
                (!portalPw || portalPw.classList.contains('hidden'))
            ) {
                document.body.classList.remove('overflow-hidden');
            }
        }, 300);
    }

    function ebPwOpen() {
        ebDrawerState('eb-setpw-overlay', 'eb-setpw-panel', true);
    }

    function ebPwClose() {
        ebDrawerState('eb-setpw-overlay', 'eb-setpw-panel', false);
    }

    function ebStoragePlanOpen() {
        ebDrawerState('eb-storage-plan-overlay', 'eb-storage-plan-panel', true);
    }

    function ebStoragePlanClose() {
        ebDrawerState('eb-storage-plan-overlay', 'eb-storage-plan-panel', false);
    }

    function ebChooseE3Backup(btn) {
        document.getElementById('eb-product-choice').value = 'e3backup';
        fetch(ebJoinRoot('/modules/addons/cloudstorage/api/selectproduct.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ product_choice: 'e3backup' })
        }).then(function(r){ return r.json(); }).then(function(data){
            if (data && data.status === 'success') {
                ebPreparePasswordUi('e3backup');
                setTimeout(ebPwOpen, 250);
            } else if (data && data.message === 'beta_unavailable') {
                ebShowToast('e3 Cloud Backup is not currently available for your account.', 'error');
            } else {
                ebShowToast('Unable to save selection. Please try again.', 'error');
            }
        }).catch(function(){
            ebShowToast('Unable to save selection. Please try again.', 'error');
        });
    }

    function ebCardOpen() {
        ebDrawerState('eb-card-overlay', 'eb-card-panel', true);
    }

    function ebCardClose() {
        ebDrawerState('eb-card-overlay', 'eb-card-panel', false);
    }

    function ebShowToast(message, type) {
        var container = document.getElementById('toast-container');
        if (!container || !message) {
            return;
        }

        var state = 'eb-toast--info';
        if (type === 'success') {
            state = 'eb-toast--success';
        } else if (type === 'error' || type === 'danger') {
            state = 'eb-toast--danger';
        } else if (type === 'warning') {
            state = 'eb-toast--warning';
        }

        var toast = document.createElement('div');
        toast.className = 'pointer-events-auto eb-toast ' + state;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }

    window.showToast = function (message, type) {
        ebShowToast(message, type);
    };

    function ebSetFieldError(id, message) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        if (message) {
            el.textContent = message;
            el.classList.remove('hidden');
        } else {
            el.textContent = '';
            el.classList.add('hidden');
        }
    }

    function ebSetGeneralAlert(wrapperId, bodyId, message) {
        var wrapper = document.getElementById(wrapperId);
        var body = document.getElementById(bodyId);
        if (!wrapper || !body) {
            return;
        }
        if (message) {
            body.textContent = message;
            wrapper.classList.remove('hidden');
        } else {
            body.textContent = '';
            wrapper.classList.add('hidden');
        }
    }

    function ebDisableSubmit(disabled) {
        var button = document.getElementById('eb-pw-submit');
        if (button) {
            button.disabled = !!disabled;
        }
    }

    var ebCardSubmitting = false;
    var ebStripeInitDone = false;
    var ebStripeKey = '';
    var ebStripeInitInFlight = false;

    function ebSetCardError(message) {
        ebSetGeneralAlert('eb-card-error', 'eb-card-error-body', message || '');
    }

    function ebSyncCsrfToken() {
        try {
            var token = window.csrfToken || window.EB_CSRF_TOKEN || '';
            var input = document.querySelector('#eb-addcard-form input[name="token"]');
            if (input && token) {
                input.value = token;
            }
        } catch (_) {}
    }

    function ebJoinRoot(path) {
        var root = (window.EB_WEB_ROOT || '').replace(/\/+$/, '');
        return root + path;
    }

    function ebApplyBillingDefaults(billing) {
        if (!billing) {
            return;
        }
        try { document.getElementById('ebBillingName').value = billing.billing_name || ''; } catch (_) {}
        try { document.getElementById('ebBillingAddress1').value = billing.billing_address_1 || ''; } catch (_) {}
        try { document.getElementById('ebBillingAddress2').value = billing.billing_address_2 || ''; } catch (_) {}
        try { document.getElementById('ebBillingCity').value = billing.billing_city || ''; } catch (_) {}
        try { document.getElementById('ebBillingState').value = billing.billing_state || ''; } catch (_) {}
        try { document.getElementById('ebBillingPostcode').value = billing.billing_postcode || ''; } catch (_) {}
        try { document.getElementById('ebBillingCountry').value = billing.billing_country || ''; } catch (_) {}
    }

    function ebPatchStripeElement(el) {
        if (!el || typeof el.hasRegisteredListener === 'function') {
            return;
        }
        var listeners = {};
        var originalAdd = el.addEventListener ? el.addEventListener.bind(el) : null;
        var originalRemove = el.removeEventListener ? el.removeEventListener.bind(el) : null;

        el.addEventListener = function(type, handler) {
            listeners[type] = true;
            if (originalAdd) {
                return originalAdd(type, handler);
            }
        };

        el.removeEventListener = function(type, handler) {
            listeners[type] = false;
            if (originalRemove) {
                return originalRemove(type, handler);
            }
        };

        el.hasRegisteredListener = function(type) {
            return !!listeners[type];
        };
    }

    function ebApplyStripeDarkTheme() {
        var styledAnything = false;

        if (window.card && typeof card.update === 'function') {
            card.update({
                style: {
                    base: {
                        color: '#eef2f9',
                        iconColor: '#fe5000',
                        '::placeholder': {
                            color: '#6d88a8'
                        },
                        fontFamily: 'DM Sans, system-ui, sans-serif',
                        fontSize: '14px'
                    },
                    invalid: {
                        color: '#fca5a5',
                        iconColor: '#fca5a5'
                    }
                }
            });
            styledAnything = true;
        }

        if (window.cardExpiryElements && typeof cardExpiryElements.update === 'function') {
            cardExpiryElements.update({
                style: {
                    base: {
                        color: '#eef2f9',
                        '::placeholder': {
                            color: '#6d88a8'
                        }
                    },
                    invalid: {
                        color: '#fca5a5'
                    }
                }
            });
            styledAnything = true;
        }

        if (window.cardCvcElements && typeof cardCvcElements.update === 'function') {
            cardCvcElements.update({
                style: {
                    base: {
                        color: '#eef2f9',
                        '::placeholder': {
                            color: '#6d88a8'
                        }
                    },
                    invalid: {
                        color: '#fca5a5'
                    }
                }
            });
            styledAnything = true;
        }

        return styledAnything;
    }

    function ebEnsureStripeCss() {
        if (document.getElementById('eb-stripe-css')) {
            return;
        }
        var link = document.createElement('link');
        link.id = 'eb-stripe-css';
        link.rel = 'stylesheet';
        link.href = ebJoinRoot('/modules/gateways/stripe/stripe.css');
        document.head.appendChild(link);
    }

    function ebLoadScriptOnce(src) {
        return new Promise(function(resolve, reject) {
            var existing = document.querySelector('script[src="' + src + '"]');
            if (existing) {
                if (existing.getAttribute('data-loaded') === '1') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', function() {
                    existing.setAttribute('data-loaded', '1');
                    resolve();
                });
                existing.addEventListener('error', function() {
                    reject(new Error('load_failed'));
                });
                return;
            }

            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = function() {
                script.setAttribute('data-loaded', '1');
                resolve();
            };
            script.onerror = function() {
                reject(new Error('load_failed'));
            };
            document.head.appendChild(script);
        });
    }

    async function ebEnsureWhmcsScripts() {
        if (!window.jQuery) {
            await ebLoadScriptOnce(ebJoinRoot('/assets/js/jquery.min.js'));
        }
        if (!window.WHMCS || !window.WHMCS.utils) {
            await ebLoadScriptOnce(ebJoinRoot('/assets/js/whmcs.js'));
        }
        if (typeof window.whmcsBaseUrl === 'undefined' || window.whmcsBaseUrl === '') {
            window.whmcsBaseUrl = (window.EB_WEB_ROOT || '').replace(/\/+$/, '');
        }
    }

    async function ebEnsureStripeInit(status) {
        if (ebStripeInitInFlight) {
            return false;
        }
        if (ebStripeInitDone && ebStripeKey && ebStripeKey === (status && status.stripe_publishable_key)) {
            return true;
        }

        var publishableKey = (status && status.stripe_publishable_key) ? String(status.stripe_publishable_key) : '';
        if (!publishableKey) {
            ebSetCardError('Stripe is unavailable. Please contact support.');
            return false;
        }

        ebStripeInitInFlight = true;
        try {
            await ebEnsureWhmcsScripts();
            ebEnsureStripeCss();
            await ebLoadScriptOnce('https://js.stripe.com/v3/');
            await ebLoadScriptOnce(ebJoinRoot('/modules/gateways/stripe/stripe.js'));

            if (!window.Stripe) {
                ebSetCardError('Stripe failed to load. Please try again.');
                ebStripeInitInFlight = false;
                return false;
            }

            if (!window.stripe || ebStripeKey !== publishableKey) {
                window.stripe = Stripe(publishableKey);
                window.elements = stripe.elements();
                window.card = elements.create('cardNumber');
                window.cardExpiryElements = elements.create('cardExpiry');
                window.cardCvcElements = elements.create('cardCvc');
                ebPatchStripeElement(window.card);
                ebPatchStripeElement(window.cardExpiryElements);
                ebPatchStripeElement(window.cardCvcElements);
            }

            ebStripeKey = publishableKey;
            window.lang = window.lang || {
                creditCardInput: 'Card number',
                creditCardExpiry: 'Expiry',
                creditCardCvc: 'CVC'
            };
            window.csrfToken = window.EB_CSRF_TOKEN || window.csrfToken || '';
            window.amount = '000';
            window.paymentRequestButtonEnabled = true;
            window.paymentRequestAmountDue = 0;
            window.paymentRequestDescription = 'e3 Cloud Storage Trial';
            window.paymentRequestCurrency = (status && status.currency_code) ? String(status.currency_code).toLowerCase() : 'usd';
            if (typeof window.defaultErrorMessage === 'undefined') {
                window.defaultErrorMessage = 'Payment method could not be saved. Please try again.';
            }

            if (typeof initStripe === 'function') {
                initStripe();
                try {
                    if (window.jQuery) {
                        jQuery('#eb-addcard-form').off('submit.stripe');
                    }
                } catch (_) {}
                if (typeof enablePaymentRequestButton === 'function') {
                    enablePaymentRequestButton();
                }
            }

            if (typeof window.handlePaymentRequestAsSetupIntent === 'function' && !window.ebStripePRBWrapped) {
                window.ebStripePRBWrapped = true;
                var originalPRB = window.handlePaymentRequestAsSetupIntent;
                window.handlePaymentRequestAsSetupIntent = function(event) {
                    try {
                        if (event && event.paymentMethod && event.paymentMethod.id) {
                            window.ebStripeLastPaymentMethodId = event.paymentMethod.id;
                        }
                    } catch (_) {}
                    return originalPRB(event);
                };
            }

            if (typeof window.stripeResponseHandler === 'function' && !window.ebStripeResponseWrapped) {
                window.ebStripeResponseWrapped = true;
                var originalResponse = window.stripeResponseHandler;
                window.stripeResponseHandler = function(token) {
                    if (window.ebStripeLastPaymentMethodId) {
                        var paymentMethodId = window.ebStripeLastPaymentMethodId;
                        window.ebStripeLastPaymentMethodId = '';
                        ebFinalizePaymentMethod(paymentMethodId, null).then(function(res) {
                            if (res && res.status !== 'success' && res.message) {
                                ebSetCardError(res.message);
                            }
                        }).catch(function() {
                            ebSetCardError('Payment method could not be saved. Please try again.');
                        });
                        return;
                    }
                    return originalResponse(token);
                };
            }

            if (!ebApplyStripeDarkTheme()) {
                var attempts = 0;
                var interval = setInterval(function() {
                    attempts++;
                    if (ebApplyStripeDarkTheme() || attempts > 40) {
                        clearInterval(interval);
                    }
                }, 150);
            }

            ebStripeInitDone = true;
            ebStripeInitInFlight = false;
            return true;
        } catch (e) {
            ebStripeInitInFlight = false;
            ebSetCardError('Stripe failed to load. Please try again.');
            return false;
        }
    }

    async function ebFetchPaymentStatus() {
        try {
            var response = await fetch(ebJoinRoot('/modules/addons/cloudstorage/api/paymentmethod_status.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: ''
            });
            return await response.json();
        } catch (e) {
            return { status: 'error', message: 'network' };
        }
    }

    function ebE3EncryptionModeChanged(mode) {
        var hidden = document.getElementById('eb-e3-encryption-mode');
        var strictWarning = document.getElementById('eb-e3-strict-warning');
        var managedAckRow = document.getElementById('eb-e3-managed-ack-row');
        var strictAckRow = document.getElementById('eb-e3-strict-ack-row');
        var managedAck = document.getElementById('eb-e3-managed-ack');
        var strictAck = document.getElementById('eb-e3-strict-ack');
        var resolved = (mode === 'strict') ? 'strict' : 'managed';
        if (hidden) {
            hidden.value = resolved;
        }
        if (strictWarning) {
            if (resolved === 'strict') {
                strictWarning.classList.remove('hidden');
            } else {
                strictWarning.classList.add('hidden');
            }
        }
        if (managedAckRow) {
            if (resolved === 'managed') {
                managedAckRow.classList.remove('hidden');
            } else {
                managedAckRow.classList.add('hidden');
            }
        }
        if (strictAckRow) {
            if (resolved === 'strict') {
                strictAckRow.classList.remove('hidden');
            } else {
                strictAckRow.classList.add('hidden');
            }
        }
        if (managedAck) {
            managedAck.checked = false;
        }
        if (strictAck) {
            strictAck.checked = false;
        }
        ebSetFieldError('eb-err-e3-managed-ack', '');
        ebSetFieldError('eb-err-e3-strict-ack', '');
    }

    function ebPreparePasswordUi(choice) {
        var unified = window.EB_WELCOME_UNIFIED_ENABLED;
        var showUser = (choice === 'backup' || choice === 'ms365' || choice === 'e3backup' || (unified && choice === 'cloud2cloud'));
        var usernameRow = document.getElementById('eb-username-row');
        var noUsernameRow = document.getElementById('eb-no-username-row');
        var existingPwRow = document.getElementById('eb-existing-pw-row');
        var hint = document.getElementById('eb-username-hint');
        var subtitle = document.getElementById('eb-setpw-subtitle');
        var title = document.getElementById('eb-setpw-title');
        var usernameInput = document.getElementById('eb-username');
        var usernameLabel = document.getElementById('eb-username-label');
        var submitBtn = document.getElementById('eb-pw-submit');
        var isExistingClientMs365OrE3 = window.EB_WELCOME_EXISTING_CLIENT && (choice === 'ms365' || choice === 'e3backup');
        var needsBackupPassword = (choice === 'e3backup' && !isExistingClientMs365OrE3)
            || (unified && (choice === 'ms365' || choice === 'cloud2cloud') && !isExistingClientMs365OrE3);
        var e3EncryptionRow = document.getElementById('eb-e3-encryption-row');
        var e3NewPasswordRow = document.getElementById('eb-e3-new-password-row');
        var e3BackupPassword = document.getElementById('eb-e3-backup-password');
        var e3BackupPasswordConfirm = document.getElementById('eb-e3-backup-password-confirm');

        if (usernameRow) {
            if (showUser) {
                usernameRow.classList.remove('hidden');
            } else {
                usernameRow.classList.add('hidden');
            }
        }
        if (noUsernameRow) {
            if (!showUser) {
                noUsernameRow.classList.remove('hidden');
            } else {
                noUsernameRow.classList.add('hidden');
            }
        }
        if (existingPwRow) {
            if (isExistingClientMs365OrE3) {
                existingPwRow.classList.remove('hidden');
            } else {
                existingPwRow.classList.add('hidden');
            }
        }
        if (hint) {
            hint.textContent = '';
            hint.classList.add('hidden');
        }
        if (usernameInput) {
            usernameInput.value = '';
        }
        ebSetFieldError('eb-err-existing-pw', '');

        ebSetFieldError('eb-err-existing-pw', '');
        ebSetFieldError('eb-err-e3-password', '');
        ebSetFieldError('eb-err-e3-password-confirm', '');
        ebSetFieldError('eb-err-e3-managed-ack', '');
        ebSetFieldError('eb-err-e3-strict-ack', '');

        if (e3EncryptionRow) {
            if (choice === 'e3backup') {
                e3EncryptionRow.classList.remove('hidden');
                ebE3EncryptionModeChanged('managed');
                var managedRadio = document.querySelector('input[name="eb_e3_encryption_mode"][value="managed"]');
                if (managedRadio) {
                    managedRadio.checked = true;
                }
            } else {
                e3EncryptionRow.classList.add('hidden');
            }
        }
        if (e3NewPasswordRow) {
            if (needsBackupPassword) {
                e3NewPasswordRow.classList.remove('hidden');
            } else {
                e3NewPasswordRow.classList.add('hidden');
            }
        }
        if (e3BackupPassword) {
            e3BackupPassword.value = '';
        }
        if (e3BackupPasswordConfirm) {
            e3BackupPasswordConfirm.value = '';
        }

        if (choice === 'e3backup') {
            title.textContent = 'Create your first e3 Backup User';
            if (isExistingClientMs365OrE3) {
                subtitle.textContent = 'Choose a username and encryption mode for your first Backup User. Confirm your portal password below to provision the service.';
            } else {
                subtitle.textContent = 'Choose a username, encryption mode, and backup user password. Each Backup User is a separate billable unit with its own jobs and encryption.';
            }
            if (usernameLabel) { usernameLabel.textContent = 'Backup user username'; }
        } else if (choice === 'backup') {
            title.textContent = 'Pick your Cloud Backup agent username';
            subtitle.textContent = 'Choose the username your Cloud Backup agent will use to sign in. Your portal password (set earlier) is also the password for this backup agent.';
            if (usernameLabel) { usernameLabel.textContent = 'Backup agent username'; }
        } else if (choice === 'ms365') {
            title.textContent = 'Pick your Microsoft 365 Backup username';
            if (isExistingClientMs365OrE3) {
                subtitle.textContent = 'Choose the username for your Microsoft 365 Backup service. Confirm your portal password below to provision the service.';
            } else if (unified) {
                subtitle.textContent = 'Choose a username and backup user password for your Microsoft 365 Backup service. After provisioning, you will connect your tenant and create your first backup job.';
            } else {
                subtitle.textContent = 'Choose the username for your Microsoft 365 Backup service. After provisioning, you will connect your tenant and create your first backup job.';
            }
            if (usernameLabel) { usernameLabel.textContent = unified && !isExistingClientMs365OrE3 ? 'Backup user username' : 'Backup account username'; }
        } else if (choice === 'cloud2cloud' && unified) {
            title.textContent = 'Create your backup user for SaaS Backup';
            subtitle.textContent = 'Choose a username and backup user password. After provisioning, we will guide you through the cloud backup wizard.';
            if (usernameLabel) { usernameLabel.textContent = 'Backup user username'; }
        } else {
            title.textContent = 'Ready to provision';
            subtitle.textContent = 'We will use the portal password you set earlier. Click Continue to finish provisioning.';
        }

        if (submitBtn) {
            submitBtn.textContent = showUser ? 'Save username and continue' : 'Continue';
        }
    }

    async function ebRequireCardForStorage() {
        var status = await ebFetchPaymentStatus();
        if (status && status.status === 'success' && status.has_card) {
            ebPwOpen();
            return true;
        }
        if (!status || status.status !== 'success') {
            ebSetCardError('Unable to load payment form. Please try again.');
        } else {
            ebSetCardError('');
            ebApplyBillingDefaults(status.billing || {});
        }
        ebPwClose();
        ebCardOpen();
        await ebEnsureStripeInit(status);
        return false;
    }

    function ebDisableCardSubmit(disabled) {
        var button = document.getElementById('eb-card-submit');
        if (button) {
            button.disabled = !!disabled;
        }
    }

    async function ebGetStripeRemoteToken(paymentMethodId) {
        var form = document.getElementById('eb-addcard-form');
        if (!form) {
            return { status: 'error', message: 'form_missing' };
        }
        ebSyncCsrfToken();
        var data = new URLSearchParams(new FormData(form));
        data.append('payment_method_id', paymentMethodId);
        var addUrl = (window.WHMCS && WHMCS.utils && WHMCS.utils.getRouteUrl)
            ? WHMCS.utils.getRouteUrl('/stripe/payment/add')
            : ebJoinRoot('/index.php?rp=/stripe/payment/add');
        var response = await fetch(addUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: data.toString()
        });
        var json = await response.json();
        if (json && json.success && json.token) {
            return { status: 'success', token: json.token };
        }
        if (json && json.validation_feedback) {
            return { status: 'error', message: json.validation_feedback };
        }
        return { status: 'error', message: 'stripe_payment_add_failed' };
    }

    async function ebSaveCardViaApi(remoteToken, cardDetails) {
        var params = { remote_storage_token: remoteToken };
        if (cardDetails) {
            params.card_last_four = cardDetails.last4 || '';
            params.card_exp_month = cardDetails.exp_month || '';
            params.card_exp_year = cardDetails.exp_year || '';
            params.card_brand = cardDetails.brand || '';
        }
        var response = await fetch(ebJoinRoot('/modules/addons/cloudstorage/api/add_paymentmethod.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params).toString()
        });
        var json = await response.json();
        if (json && json.status === 'success') {
            return { status: 'success', paymethodid: json.paymethodid };
        }
        return { status: 'error', message: (json && json.message) ? json.message : 'add_paymentmethod_failed' };
    }

    async function ebFinalizePaymentMethod(paymentMethodId, existingCardDetails) {
        var cardDetails = existingCardDetails;
        if (!cardDetails || !cardDetails.last4) {
            return { status: 'error', message: 'card_details_missing' };
        }
        var tokenRes = await ebGetStripeRemoteToken(paymentMethodId);
        if (tokenRes.status !== 'success' || !tokenRes.token) {
            return { status: 'error', message: tokenRes.message || 'stripe_payment_add_failed' };
        }
        var saveResult = await ebSaveCardViaApi(tokenRes.token, cardDetails);
        if (saveResult.status !== 'success') {
            return { status: 'error', message: saveResult.message || 'save_failed' };
        }
        await ebSleep(500);
        var status = await ebFetchPaymentStatus();
        if (!status || status.status !== 'success' || !status.has_card) {
            await ebSleep(800);
            status = await ebFetchPaymentStatus();
        }
        if (status && status.status === 'success' && status.has_card) {
            ebCardClose();
            ebPwOpen();
            return { status: 'success' };
        }
        return { status: 'error', message: 'verify_failed' };
    }

    function ebCardDetailsFromPaymentMethod(paymentMethod) {
        if (!paymentMethod || !paymentMethod.card) {
            return null;
        }
        var cardInfo = paymentMethod.card;
        if (!cardInfo.last4) {
            return null;
        }
        return {
            last4: cardInfo.last4,
            exp_month: cardInfo.exp_month || '',
            exp_year: cardInfo.exp_year || '',
            brand: cardInfo.brand || ''
        };
    }

    async function ebConfirmPaymentMethodWithSetupIntent(formData) {
        var setupUrl = (window.WHMCS && WHMCS.utils && WHMCS.utils.getRouteUrl)
            ? WHMCS.utils.getRouteUrl('/stripe/setup/intent')
            : ebJoinRoot('/index.php?rp=/stripe/setup/intent');
        var setupResponse = await fetch(setupUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        var setupJson = await setupResponse.json();
        if (!setupJson || !setupJson.success || !setupJson.setup_intent) {
            return {
                status: 'error',
                message: setupJson && setupJson.validation_feedback ? setupJson.validation_feedback : 'setup_intent_failed'
            };
        }
        var setupResult = await stripe.handleCardSetup(setupJson.setup_intent, card);
        if (setupResult.error) {
            return { status: 'error', message: setupResult.error.message || 'card_setup_failed' };
        }
        var paymentMethod = setupResult.setupIntent ? setupResult.setupIntent.payment_method : null;
        var paymentMethodId = '';
        if (typeof paymentMethod === 'object' && paymentMethod !== null) {
            paymentMethodId = paymentMethod.id || '';
        } else if (typeof paymentMethod === 'string') {
            paymentMethodId = paymentMethod;
        }
        if (!paymentMethodId) {
            return { status: 'error', message: 'payment_method_missing' };
        }
        var cardDetails = (typeof paymentMethod === 'object' && paymentMethod !== null)
            ? ebCardDetailsFromPaymentMethod(paymentMethod)
            : null;
        if (!cardDetails && window.stripe) {
            try {
                var retrieved = await stripe.retrievePaymentMethod(paymentMethodId);
                cardDetails = ebCardDetailsFromPaymentMethod(retrieved && retrieved.paymentMethod);
            } catch (e) {}
        }
        if (!cardDetails) {
            return { status: 'error', message: 'card_details_missing' };
        }
        return await ebFinalizePaymentMethod(paymentMethodId, cardDetails);
    }

    async function ebSubmitCardViaApi() {
        var form = document.getElementById('eb-addcard-form');
        if (!form) {
            return { status: 'error', message: 'form_missing' };
        }
        ebSyncCsrfToken();
        if (!window.stripe || !window.card) {
            return { status: 'error', message: 'stripe_unavailable' };
        }
        var data = new URLSearchParams(new FormData(form));
        var pmResult = await stripe.createPaymentMethod('card', card);
        if (pmResult.error) {
            var authRequired = pmResult.error.code === 'authentication_required'
                || (pmResult.error.payment_intent && pmResult.error.payment_intent.status === 'requires_action')
                || (pmResult.error.setup_intent && pmResult.error.setup_intent.status === 'requires_action');
            if (authRequired) {
                return await ebConfirmPaymentMethodWithSetupIntent(data);
            }
            return { status: 'error', message: pmResult.error.message || 'card_setup_failed' };
        }

        var paymentMethod = pmResult.paymentMethod;
        if (!paymentMethod || !paymentMethod.id) {
            return { status: 'error', message: 'payment_method_missing' };
        }

        var cardDetails = ebCardDetailsFromPaymentMethod(paymentMethod);
        if (!cardDetails) {
            return { status: 'error', message: 'card_details_missing' };
        }

        if (paymentMethod.status === 'requires_action') {
            return await ebConfirmPaymentMethodWithSetupIntent(data);
        }

        return await ebFinalizePaymentMethod(paymentMethod.id, cardDetails);
    }

    async function ebCardSubmit(ev) {
        if (ev && ev.preventDefault) {
            ev.preventDefault();
        }
        if (ebCardSubmitting) {
            return false;
        }

        ebCardSubmitting = true;
        ebSetCardError('');
        ebDisableCardSubmit(true);
        try {
            await ebEnsureWhmcsScripts();
            await ebEnsureStripeInit(await ebFetchPaymentStatus());
            var result = await ebSubmitCardViaApi();
            if (result && result.status === 'success') {
                var status = await ebFetchPaymentStatus();
                if (status && status.status === 'success' && status.has_card) {
                    ebCardClose();
                    ebPwOpen();
                } else {
                    ebSetCardError('We saved your card but could not verify it yet. Please refresh and try again.');
                }
            } else {
                var message = (result && (result.message || result.error)) ? String(result.message || result.error) : '';
                ebSetCardError(message && message !== 'setup_intent_failed' ? message : 'We could not save your card. Please try again.');
            }
        } catch (e) {
            ebSetCardError('We could not save your card. Please try again.');
        } finally {
            ebCardSubmitting = false;
            ebDisableCardSubmit(false);
        }
        return false;
    }

    function ebSleep(ms) {
        return new Promise(function(resolve) {
            setTimeout(resolve, ms);
        });
    }

    function ebSelectStorageTier(tier) {
        var tierInput = document.getElementById('eb-storage-tier');
        if (tierInput) {
            tierInput.value = tier;
        }
        ebStoragePlanClose();
        if (tier === 'trial_unlimited') {
            setTimeout(function() {
                ebRequireCardForStorage();
            }, 350);
            return;
        }
        setTimeout(function() {
            ebPwOpen();
        }, 350);
    }

    async function ebChooseProduct(btn) {
        var choice = (btn && btn.getAttribute('data-choice')) || '';
        if (!choice) {
            return;
        }
        try {
            var response = await fetch(ebJoinRoot('/modules/addons/cloudstorage/api/selectproduct.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({ product_choice: choice })
            });
            var data = await response.json();
            if (data && data.status === 'success') {
                document.getElementById('eb-product-choice').value = data.product_choice || choice;
                ebPreparePasswordUi(data.product_choice);
                if (data.product_choice === 'storage') {
                    ebStoragePlanOpen();
                } else {
                    ebPwOpen();
                }
            } else {
                ebShowToast('Unable to save selection. Please try again.', 'error');
            }
        } catch (e) {
            ebShowToast('Unable to save selection. Please try again.', 'error');
        }
    }

    async function ebPwSubmit(ev) {
        ev.preventDefault();
        ebSetGeneralAlert('eb-pw-general-error', 'eb-pw-general-error-body', '');
        ebSetFieldError('eb-err-username', '');
        ebSetFieldError('eb-err-existing-pw', '');
        ebSetFieldError('eb-err-e3-password', '');
        ebSetFieldError('eb-err-e3-password-confirm', '');
        ebSetFieldError('eb-err-e3-managed-ack', '');
        ebSetFieldError('eb-err-e3-strict-ack', '');
        ebDisableSubmit(true);

        try {
            var choice = document.getElementById('eb-product-choice').value || '';
            var unified = window.EB_WELCOME_UNIFIED_ENABLED;
            var username = (document.getElementById('eb-username') || { value: '' }).value || '';
            var storageTierEl = document.getElementById('eb-storage-tier');
            var storageTier = storageTierEl ? storageTierEl.value : '';
            var needsUser = (choice === 'backup' || choice === 'ms365' || choice === 'e3backup' || (unified && choice === 'cloud2cloud'));
            var isExistingClientProvision = window.EB_WELCOME_EXISTING_CLIENT && (choice === 'ms365' || choice === 'e3backup');
            var isNewE3Backup = (choice === 'e3backup' && !isExistingClientProvision);
            var needsBackupPassword = isNewE3Backup
                || (unified && (choice === 'ms365' || choice === 'cloud2cloud') && !isExistingClientProvision);

            if (needsUser) {
                var reUser = /^[A-Za-z0-9_.-]{8,}$/;
                if (!reUser.test(username)) {
                    var usernameMessage = 'Backup username must be at least 8 characters and may contain only a-z, A-Z, 0-9, _, ., -';
                    ebSetFieldError('eb-err-username', usernameMessage);
                    ebShowToast(usernameMessage, 'error');
                    ebDisableSubmit(false);
                    return false;
                }
            }

            if (choice === 'e3backup') {
                var encryptionMode = (document.getElementById('eb-e3-encryption-mode') || {}).value || 'managed';
                if (encryptionMode === 'managed') {
                    var managedAck = document.getElementById('eb-e3-managed-ack');
                    if (!managedAck || !managedAck.checked) {
                        var managedAckMessage = 'Please acknowledge managed recovery.';
                        ebSetFieldError('eb-err-e3-managed-ack', managedAckMessage);
                        ebShowToast(managedAckMessage, 'error');
                        ebDisableSubmit(false);
                        return false;
                    }
                } else if (encryptionMode === 'strict') {
                    var strictAck = document.getElementById('eb-e3-strict-ack');
                    if (!strictAck || !strictAck.checked) {
                        var strictAckMessage = 'Please acknowledge strict mode requirements.';
                        ebSetFieldError('eb-err-e3-strict-ack', strictAckMessage);
                        ebShowToast(strictAckMessage, 'error');
                        ebDisableSubmit(false);
                        return false;
                    }
                }
            }

            var backupPassword = '';
            var backupPasswordConfirm = '';
            if (needsBackupPassword) {
                backupPassword = (document.getElementById('eb-e3-backup-password') || {}).value || '';
                backupPasswordConfirm = (document.getElementById('eb-e3-backup-password-confirm') || {}).value || '';
                if (!backupPassword || backupPassword.length < 8) {
                    var pwMessage = 'Backup user password must be at least 8 characters.';
                    ebSetFieldError('eb-err-e3-password', pwMessage);
                    ebShowToast(pwMessage, 'error');
                    ebDisableSubmit(false);
                    return false;
                }
                if (backupPassword !== backupPasswordConfirm) {
                    var confirmMessage = 'Password confirmation does not match.';
                    ebSetFieldError('eb-err-e3-password-confirm', confirmMessage);
                    ebShowToast(confirmMessage, 'error');
                    ebDisableSubmit(false);
                    return false;
                }
            }

            var existingPortalPassword = '';
            if (isExistingClientProvision) {
                existingPortalPassword = (document.getElementById('eb-existing-portal-password') || {}).value || '';
                if (!existingPortalPassword) {
                    var pwMessage = 'Please enter your portal password to continue.';
                    ebSetFieldError('eb-err-existing-pw', pwMessage);
                    ebShowToast(pwMessage, 'error');
                    ebDisableSubmit(false);
                    return false;
                }
            }

            var loaderText = isExistingClientProvision ? 'Provisioning your service...' : 'Creating your account...';
            try {
                if (window.ebShowLoader) {
                    // The global loader overlay is z-index:50, which sits behind
                    // the open product drawer (z-60). Lift it above the drawers
                    // (but below the z-120 toast container) so it's visible while
                    // provisioning runs.
                    var ebLoader = window.ebShowLoader(document.body, loaderText);
                    if (ebLoader && ebLoader.style) {
                        ebLoader.style.zIndex = '110';
                    }
                }
            } catch (_) {}

            var provisionBody = {
                product_choice: choice,
                username: username,
                storage_tier: storageTier
            };
            if (unified && (choice === 'e3backup' || choice === 'ms365' || choice === 'cloud2cloud')) {
                if (choice === 'e3backup') {
                    provisionBody.intent = 'local';
                } else if (choice === 'ms365') {
                    provisionBody.intent = 'ms365';
                } else if (choice === 'cloud2cloud') {
                    provisionBody.intent = 'saas';
                }
            }
            if (choice === 'e3backup') {
                provisionBody.encryption_mode = (document.getElementById('eb-e3-encryption-mode') || {}).value || 'managed';
                provisionBody.managed_acknowledged = document.getElementById('eb-e3-managed-ack') && document.getElementById('eb-e3-managed-ack').checked ? '1' : '0';
                provisionBody.strict_acknowledged = document.getElementById('eb-e3-strict-ack') && document.getElementById('eb-e3-strict-ack').checked ? '1' : '0';
            } else if (unified && (choice === 'ms365' || choice === 'cloud2cloud')) {
                provisionBody.encryption_mode = 'managed';
            }
            if (needsBackupPassword) {
                provisionBody.password = backupPassword;
                provisionBody.password_confirm = backupPasswordConfirm;
            }
            if (isExistingClientProvision) {
                provisionBody.existing_client = '1';
                provisionBody.new_password = existingPortalPassword;
            }

            var provisionResponse = await fetch(ebJoinRoot('/modules/addons/cloudstorage/api/setpassword_and_provision.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(provisionBody)
            });
            var data = await provisionResponse.json();

            if (data && data.status === 'success' && data.redirectUrl) {
                ebPwClose();
                if (data.admin_portal_bypass && data.backup_user_password) {
                    window.alert(
                        'Admin bypass used.\n\n'
                        + 'A random backup-user password was generated for this provision:\n\n'
                        + data.backup_user_password
                        + '\n\nSave it before continuing — it will not be shown again.'
                    );
                }
                window.location.href = data.redirectUrl;
                return false;
            }

            if (data && data.requires_payment_method) {
                ebPwClose();
                ebPreparePasswordUi(choice);
                await ebRequireCardForStorage();
                ebDisableSubmit(false);
                return false;
            }

            var errors = (data && data.errors) ? data.errors : {};
            if (errors.username) {
                ebSetFieldError('eb-err-username', errors.username);
            }
            if (errors.new_password) {
                ebSetFieldError('eb-err-existing-pw', errors.new_password);
            }
            if (errors.password) {
                ebSetFieldError('eb-err-e3-password', errors.password);
            }
            if (errors.password_confirm) {
                ebSetFieldError('eb-err-e3-password-confirm', errors.password_confirm);
            }
            if (errors.managed_acknowledged) {
                ebSetFieldError('eb-err-e3-managed-ack', errors.managed_acknowledged);
            }
            if (errors.strict_acknowledged) {
                ebSetFieldError('eb-err-e3-strict-ack', errors.strict_acknowledged);
            }
            if (errors.general) {
                ebSetGeneralAlert('eb-pw-general-error', 'eb-pw-general-error-body', errors.general);
            }
            if (!errors.general && !errors.username && !errors.new_password) {
                ebSetGeneralAlert(
                    'eb-pw-general-error',
                    'eb-pw-general-error-body',
                    (data && data.message) ? String(data.message) : 'Failed to provision the service.'
                );
            }
        } catch (e) {
            ebSetGeneralAlert('eb-pw-general-error', 'eb-pw-general-error-body', 'Request failed. Please try again.');
        } finally {
            try {
                if (window.ebHideLoader) {
                    window.ebHideLoader(document.body);
                }
            } catch (_) {}
            ebDisableSubmit(false);
        }
        return false;
    }

    // Round 2: portal-password modal handlers. This is a centered eb-modal
    // (not a side drawer) so it uses simple show/hide rather than the slide
    // transition helper used by the other welcome overlays.
    function ebPortalPwOpen() {
        var overlay = document.getElementById('eb-portalpw-overlay');
        if (!overlay) return;
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        overlay.removeAttribute('hidden');
        document.body.classList.add('overflow-hidden');
        try { document.getElementById('eb-portalpw-new').focus(); } catch (_) {}
    }

    function ebPortalPwClose() {
        // Closing only allowed once the password has been set (or it wasn't required).
        var grid = document.getElementById('eb-product-select');
        if (grid && grid.classList.contains('is-locked')) {
            ebShowToast('Please set your portal password to continue.', 'warning');
            return;
        }
        var overlay = document.getElementById('eb-portalpw-overlay');
        if (!overlay) return;
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        overlay.setAttribute('hidden', '');
        // Only release body scroll if no other overlay is open.
        if (
            document.getElementById('eb-setpw-overlay').classList.contains('hidden') &&
            document.getElementById('eb-storage-plan-overlay').classList.contains('hidden') &&
            document.getElementById('eb-card-overlay').classList.contains('hidden')
        ) {
            document.body.classList.remove('overflow-hidden');
        }
    }

    function ebUnlockProductPicker() {
        var grid = document.getElementById('eb-product-select');
        if (grid) {
            grid.classList.remove('is-locked');
            grid.setAttribute('data-must-set-password', '0');
        }
        var notice = document.getElementById('eb-product-lock-notice');
        if (notice) {
            notice.classList.add('hidden');
        }
    }

    async function ebPortalPwSubmit(ev) {
        if (ev && ev.preventDefault) { ev.preventDefault(); }
        ebSetGeneralAlert('eb-portalpw-general-error', 'eb-portalpw-general-error-body', '');
        ebSetFieldError('eb-portalpw-err-new', '');
        ebSetFieldError('eb-portalpw-err-confirm', '');
        var submit = document.getElementById('eb-portalpw-submit');
        if (submit) { submit.disabled = true; }

        try {
            var newPw = document.getElementById('eb-portalpw-new').value || '';
            var confirmPw = document.getElementById('eb-portalpw-confirm').value || '';

            if (newPw.length < 10) {
                var msg = 'Password must be at least 10 characters long.';
                ebSetFieldError('eb-portalpw-err-new', msg);
                ebShowToast(msg, 'error');
                if (submit) { submit.disabled = false; }
                return false;
            }
            if (newPw !== confirmPw) {
                var cmsg = 'Passwords do not match.';
                ebSetFieldError('eb-portalpw-err-confirm', cmsg);
                ebShowToast(cmsg, 'error');
                if (submit) { submit.disabled = false; }
                return false;
            }

            var response = await fetch(ebJoinRoot('/modules/addons/cloudstorage/api/set_portal_password.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    new_password: newPw,
                    new_password_confirm: confirmPw,
                    // Let the server SSO us back to exactly where we are so any
                    // context (e.g. eb_beta=1) is preserved.
                    return_path: window.location.pathname + window.location.search
                })
            });
            var data = await response.json();

            if (data && data.status === 'success') {
                // Changing the WHMCS password invalidated this client session, so
                // continuing with in-page AJAX (selectproduct.php) would fail auth
                // until a reload. Navigate through the SSO URL the server minted to
                // re-establish a valid session. On reload the must-set-password
                // gate is cleared, so the product picker is already unlocked.
                ebShowToast('Portal password saved. One moment…', 'success');
                if (data.redirectUrl) {
                    window.location.href = data.redirectUrl;
                } else {
                    window.location.reload();
                }
                return false;
            }

            var errors = (data && data.errors) ? data.errors : {};
            if (errors.new_password) {
                ebSetFieldError('eb-portalpw-err-new', errors.new_password);
            }
            if (errors.new_password_confirm) {
                ebSetFieldError('eb-portalpw-err-confirm', errors.new_password_confirm);
            }
            if (errors.general) {
                ebSetGeneralAlert('eb-portalpw-general-error', 'eb-portalpw-general-error-body', errors.general);
            }
            if (!errors.new_password && !errors.new_password_confirm && !errors.general) {
                ebSetGeneralAlert(
                    'eb-portalpw-general-error',
                    'eb-portalpw-general-error-body',
                    (data && data.message) ? String(data.message) : 'Failed to save password.'
                );
            }
        } catch (e) {
            ebSetGeneralAlert('eb-portalpw-general-error', 'eb-portalpw-general-error-body', 'Request failed. Please try again.');
        } finally {
            if (submit) { submit.disabled = false; }
        }
        return false;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Round 2: portal-password gate. Auto-open the modal when the welcome
        // page reports the customer still owes us a password.
        try {
            var grid = document.getElementById('eb-product-select');
            if (grid && grid.getAttribute('data-must-set-password') === '1') {
                setTimeout(ebPortalPwOpen, 50);
            } else {
                var choose = new URLSearchParams(window.location.search).get('choose');
                if (choose === 'ms365') {
                    var ms365Btn = document.querySelector('[data-choice="ms365"]');
                    if (ms365Btn) {
                        setTimeout(function() { ebChooseProduct(ms365Btn); }, 50);
                    }
                }
            }
        } catch (_) {}

        document.addEventListener('keydown', function(event) {
            if (event.key !== 'Escape') {
                return;
            }
            // The portal-password modal is a hard gate; ignore Escape so the
            // customer cannot accidentally dismiss it.
            var portalPw = document.getElementById('eb-portalpw-overlay');
            if (portalPw && !portalPw.classList.contains('hidden')) {
                return;
            }
            if (!document.getElementById('eb-card-overlay').classList.contains('hidden')) {
                ebCardClose();
                return;
            }
            if (!document.getElementById('eb-storage-plan-overlay').classList.contains('hidden')) {
                ebStoragePlanClose();
                return;
            }
            if (!document.getElementById('eb-setpw-overlay').classList.contains('hidden')) {
                ebPwClose();
            }
        });
    });
{/literal}
</script>
