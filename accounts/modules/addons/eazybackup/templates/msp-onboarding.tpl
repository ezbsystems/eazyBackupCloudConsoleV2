{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{*
  MSP guided onboarding flow.
  Step 1 (products) — "What you can sell" product tour + Partner Hub callout
  Step 2 (area)     — "Your client area at a glance" — where things live
  Step 3 (terms)    — Terms of Service + Privacy Policy acceptance

  On accept the accompanying action handler records acceptance, clears the
  eb_msp_onboarding flag, and redirects to the cloudstorage product picker.
*}

<div
  x-data="{
    step: '{if $initial_step eq "terms"}terms{else}products{/if}',
    agreedTos: false,
    agreedPrivacy: false,
    showTosModal: false,
    showPrivacyModal: false,
    requireTos: {if $require_tos}true{else}false{/if},
    requirePrivacy: {if $require_privacy}true{else}false{/if},
    goto(target) { this.step = target; window.scrollTo({ top: 0, behavior: 'smooth' }); },
    canSubmit() {
      return (!this.requireTos || this.agreedTos) && (!this.requirePrivacy || this.agreedPrivacy);
    }
  }"
  x-cloak
  class="eb-page min-h-screen"
>
  <div class="eb-page-inner">
    <div class="mx-auto w-full !max-w-4xl">

      {* ───────── progress stepper (3 steps) ───────── *}
      <div class="mb-6 flex flex-wrap items-center justify-center gap-x-3 gap-y-2">
        <div class="flex items-center gap-2">
          <span
            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
            :style="step === 'products'
              ? 'background: var(--eb-brand-orange); color: white;'
              : (step === 'area' || step === 'terms'
                  ? 'background: var(--eb-success-strong); color: white;'
                  : 'background: var(--eb-bg-surface); color: var(--eb-text-muted); border: 1px solid var(--eb-border-subtle);')"
          >
            <span x-show="step === 'products'">1</span>
            <svg x-show="step === 'area' || step === 'terms'" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.58l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/>
            </svg>
          </span>
          <span class="eb-type-h4">What you can sell</span>
        </div>
        <div class="h-px w-6 sm:w-10" style="background: var(--eb-border-subtle);"></div>

        <div class="flex items-center gap-2">
          <span
            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
            :style="step === 'area'
              ? 'background: var(--eb-brand-orange); color: white;'
              : (step === 'terms'
                  ? 'background: var(--eb-success-strong); color: white;'
                  : 'background: var(--eb-bg-surface); color: var(--eb-text-muted); border: 1px solid var(--eb-border-subtle);')"
          >
            <span x-show="step !== 'terms'">2</span>
            <svg x-show="step === 'terms'" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.58l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/>
            </svg>
          </span>
          <span class="eb-type-h4">Client area</span>
        </div>
        <div class="h-px w-6 sm:w-10" style="background: var(--eb-border-subtle);"></div>

        <div class="flex items-center gap-2">
          <span
            class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
            :style="step === 'terms'
              ? 'background: var(--eb-brand-orange); color: white;'
              : 'background: var(--eb-bg-surface); color: var(--eb-text-muted); border: 1px solid var(--eb-border-subtle);'"
          >3</span>
          <span class="eb-type-h4">Terms &amp; Privacy</span>
        </div>
      </div>

      {* ══════════════════════════════════════════════════════════════════ *}
      {*                 STEP 1 — WHAT YOU CAN SELL                          *}
      {* ══════════════════════════════════════════════════════════════════ *}
      <div x-show="step === 'products'" x-transition.opacity>

        <div class="eb-panel">
          <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:gap-5">
            <span class="eb-icon-box eb-icon-box--lg eb-icon-box--orange shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z"/>
              </svg>
            </span>
            <div class="min-w-0 flex-1">
              <span class="eb-badge eb-badge--info">Partner account activated</span>
              <h1 class="eb-type-h2 mt-2">Welcome to eazyBackup.</h1>
              <p class="eb-type-body mt-2">
                Your partner account is ready. Let&rsquo;s start with a quick look at the four product lines
                you can resell &mdash; all billed through this portal and provisioned in minutes.
              </p>
            </div>
          </div>
        </div>

        <div class="mt-6">
          <div class="eb-section-intro">
            <h2 class="eb-section-title">What you can sell</h2>
            <p class="eb-section-description">Four core product lines, all billed through this portal and provisioned in minutes.</p>
          </div>

          <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">

            <div class="eb-card-raised">
              <div class="flex items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v13.5m0 0 4.5-4.5M12 16.5 7.5 12M4.5 19.5h15"/>
                  </svg>
                </span>
                <div class="min-w-0 flex-1">
                  <div class="eb-card-title">eazyBackup Cloud Backup</div>
                  <p class="eb-type-body mt-2">
                    Endpoint backup agent for Windows, macOS and Linux. Protects files &amp; folders,
                    full disk images, Hyper-V and VMware guest VMs, SQL Server, system state and more.
                    Managed from a single dashboard in the partner hub.
                  </p>
                </div>
              </div>
            </div>

            <div class="eb-card-raised">
              <div class="flex items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--info shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5V6a3 3 0 0 0-3-3H6a3 3 0 0 0-3 3v1.5m18 0v10.5A3 3 0 0 1 18 21H6a3 3 0 0 1-3-3V7.5m18 0H3"/>
                  </svg>
                </span>
                <div class="min-w-0 flex-1">
                  <div class="eb-card-title">e3 Object Storage</div>
                  <p class="eb-type-body mt-2">
                    S3-compatible object storage for customers who already have their own backup software,
                    or a NAS that speaks S3. Create buckets, access keys and sub-users
                    with per-user quotas, all under your account.
                  </p>
                </div>
              </div>
            </div>

            <div class="eb-card-raised">
              <div class="flex items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--success shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12a9.75 9.75 0 1 1 19.5 0 9.75 9.75 0 0 1-19.5 0Zm9.75-7.5v15m-7.5-7.5h15"/>
                  </svg>
                </span>
                <div class="min-w-0 flex-1">
                  <div class="eb-card-title">Microsoft 365 Cloud-to-Cloud</div>
                  <p class="eb-type-body mt-2">
                    Back up Exchange Online mail, contacts and calendars, plus SharePoint, OneDrive and Teams
                    data &mdash; agentlessly, direct from Microsoft to our cloud. Billed per protected user.
                  </p>
                </div>
              </div>
            </div>

            <div class="eb-card-raised">
              <div class="flex items-start gap-3">
                <span class="eb-icon-box eb-icon-box--sm eb-icon-box--premium shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h18M16.5 3 21 7.5m0 0L16.5 12M21 7.5H3"/>
                  </svg>
                </span>
                <div class="min-w-0 flex-1">
                  <div class="eb-card-title">Cloud-to-Cloud Replication</div>
                  <p class="eb-type-body mt-2">
                    Migrate or continuously replicate data from AWS S3 (or any S3-compatible source) into
                    e3 Object Storage. Great for exit-strategies, cross-region DR and secondary copies.
                  </p>
                </div>
              </div>
            </div>

          </div>
        </div>

        {* Partner Hub / White Label callout (no external button — keeps MSP in flow) *}
        <div class="mt-6 eb-card-orange">
          <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
            <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m4.5-6H16.5m-1.5 3H16.5m-1.5 3H16.5M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
              </svg>
            </span>
            <div class="min-w-0 flex-1">
              <div class="eb-type-h3">Partner Hub &amp; White-Label</div>
              <p class="eb-type-body mt-2">
                Run your backup business under your own brand with the Partner Hub. Configure a
                white-label storefront, set your own pricing, manage tenant signups and invoices, and
                issue sub-accounts with isolated storage &mdash; all from one console. You&rsquo;ll find it
                in the sidebar once onboarding is complete.
              </p>
            </div>
          </div>
        </div>

        <div class="mt-8 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-end">
          <button type="button" class="eb-btn eb-btn-primary eb-btn-md w-full sm:w-auto" @click="goto('area')">
            Continue &mdash; Your client area
            <svg class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.3 14.7a1 1 0 010-1.4L10.58 10 7.3 6.7a1 1 0 011.4-1.4l4 4a1 1 0 010 1.4l-4 4a1 1 0 01-1.4 0z" clip-rule="evenodd"/></svg>
          </button>
        </div>
      </div>

      {* ══════════════════════════════════════════════════════════════════ *}
      {*                 STEP 2 — CLIENT AREA AT A GLANCE                    *}
      {* ══════════════════════════════════════════════════════════════════ *}
      <div x-show="step === 'area'" x-transition.opacity>

        <div class="eb-panel">
          <div class="px-1">
            <span class="eb-badge eb-badge--info">Know your way around</span>
            <h2 class="eb-type-h2 mt-3">Your client area at a glance</h2>
            <p class="eb-type-body mt-2">
              Here&rsquo;s where everything lives once you&rsquo;re on the other side of onboarding &mdash; from
              monitoring backups to picking up invoices.
            </p>
          </div>

          <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">

            <div class="eb-card">
              <div class="eb-card-title flex items-center gap-2">
                <svg class="h-4 w-4" style="color: var(--eb-brand-orange);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/>
                </svg>
                Dashboard
              </div>
              <p class="eb-type-body mt-2">
                Recent backup job statuses, registered devices,
                storage usage and protected-item counts. Your first stop every day.
              </p>
            </div>

            <div class="eb-card">
              <div class="eb-card-title flex items-center gap-2">
                <svg class="h-4 w-4" style="color: var(--eb-brand-orange);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Order New Services
              </div>
              <p class="eb-type-body mt-2">
                Reached from the <strong>eazyBackup</strong> sidebar. Pick a product, choose a billing cycle
                and provision a new account &mdash; Cloud Backup, e3 Object Storage, MS 365 Backup, or Cloud to Cloud replication.
              </p>
            </div>

            <div class="eb-card">
              <div class="eb-card-title flex items-center gap-2">
                <svg class="h-4 w-4" style="color: var(--eb-brand-orange);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.5l9.75 6 9.75-6m-19.5 0v9A2.25 2.25 0 0 0 4.5 18.75h15A2.25 2.25 0 0 0 21.75 16.5v-9m-19.5 0A2.25 2.25 0 0 1 4.5 5.25h15A2.25 2.25 0 0 1 21.75 7.5"/>
                </svg>
                My Services
              </div>
              <p class="eb-type-body mt-2">
                Every service you&rsquo;ve purchased, with a per-service <em>Billing Report</em> tab for
                detailed usage &amp; charges. Plan cancellations are also handled here.
              </p>
            </div>

            <div class="eb-card">
              <div class="eb-card-title flex items-center gap-2">
                <svg class="h-4 w-4" style="color: var(--eb-brand-orange);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9.75h19.5M5.625 17.25h1.5m3 0h4.5"/>
                  <rect x="2.25" y="5.25" width="19.5" height="13.5" rx="2" ry="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Billing
              </div>
              <p class="eb-type-body mt-2">
                A full list of charges plus downloadable HTML and PDF invoices.
              </p>
            </div>

          </div>

          <p class="eb-type-caption mt-5">
            Need help along the way? Visit the
            <a href="https://docs.eazybackup.com/" class="eb-link" target="_blank" rel="noopener">Knowledgebase</a>
            or open a ticket from the client area at any time.
          </p>
        </div>

        <div class="mt-6 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
          <button type="button" class="eb-btn eb-btn-ghost eb-btn-md w-full sm:w-auto" @click="goto('products')">
            <svg class="mr-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.7 5.3a1 1 0 010 1.4L9.42 10l3.3 3.3a1 1 0 01-1.4 1.4l-4-4a1 1 0 010-1.4l4-4a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
            Back
          </button>
          <button type="button" class="eb-btn eb-btn-primary eb-btn-md w-full sm:w-auto" @click="goto('terms')">
            Continue to Terms &amp; Privacy
            <svg class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.3 14.7a1 1 0 010-1.4L10.58 10 7.3 6.7a1 1 0 011.4-1.4l4 4a1 1 0 010 1.4l-4 4a1 1 0 01-1.4 0z" clip-rule="evenodd"/></svg>
          </button>
        </div>
      </div>

      {* ══════════════════════════════════════════════════════════════════ *}
      {*                            STEP 3 — TERMS                           *}
      {* ══════════════════════════════════════════════════════════════════ *}
      <div x-show="step === 'terms'" x-transition.opacity>
        <div class="eb-panel">
          <div class="px-1">
            <span class="eb-badge eb-badge--info inline-flex items-center gap-1.5">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 2a10 10 0 100 20 10 10 0 000-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z"/>
              </svg>
              Legal agreements
            </span>
            <h2 class="eb-type-h2 mt-3">Accept our Terms &amp; Privacy Policy</h2>
            <p class="eb-type-body mt-2">
              Before you choose a product, please review and accept the documents that govern your use of
              eazyBackup. You can revisit them any time under
              <span class="font-semibold" style="color: var(--eb-text-secondary);">My Account &rarr; Terms</span>.
            </p>
          </div>

          <div class="mt-5 space-y-4 border-t pt-5" style="border-color: var(--eb-border-subtle);">
            {if $tos && $require_tos}
              <label class="eb-inline-choice">
                <input type="checkbox" class="eb-check-input shrink-0" x-model="agreedTos" />
                <span class="eb-type-body">
                  I agree to the
                  <button type="button" class="eb-link text-sm" @click.prevent="showTosModal = true">Terms of Service</button>
                  <span class="eb-type-caption"> (v{$tos->version|escape})</span>
                </span>
              </label>
            {/if}

            {if $privacy && $require_privacy}
              <label class="eb-inline-choice">
                <input type="checkbox" class="eb-check-input shrink-0" x-model="agreedPrivacy" />
                <span class="eb-type-body">
                  I agree to the
                  <button type="button" class="eb-link text-sm" @click.prevent="showPrivacyModal = true">Privacy Policy</button>
                  <span class="eb-type-caption"> (v{$privacy->version|escape})</span>
                </span>
              </label>
            {/if}

            {if !$tos && !$privacy}
              <div class="eb-alert eb-alert--info">
                <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                </svg>
                <div>
                  <p>No active legal agreements require acceptance at this time. You can continue.</p>
                </div>
              </div>
            {/if}
          </div>

          <form method="post" action="index.php?m=eazybackup&amp;a=msp-onboarding-accept"
                class="mt-6 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between"
                style="border-color: var(--eb-border-subtle);">
            <input type="hidden" name="tos_version"     value="{if $tos}{$tos->version|escape}{/if}"/>
            <input type="hidden" name="privacy_version" value="{if $privacy}{$privacy->version|escape}{/if}"/>
            {if isset($token)}<input type="hidden" name="token" value="{$token}"/>{/if}

            <button type="button" class="eb-btn eb-btn-ghost eb-btn-md w-full sm:w-auto" @click="goto('area')">
              <svg class="mr-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.7 5.3a1 1 0 010 1.4L9.42 10l3.3 3.3a1 1 0 01-1.4 1.4l-4-4a1 1 0 010-1.4l4-4a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
              Back
            </button>

            <button
              type="submit"
              :disabled="!canSubmit()"
              class="eb-btn eb-btn-primary eb-btn-md w-full sm:w-auto"
              :class="!canSubmit() && 'disabled'"
            >
              Accept &amp; choose my first product
              <svg class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.3 14.7a1 1 0 010-1.4L10.58 10 7.3 6.7a1 1 0 011.4-1.4l4 4a1 1 0 010 1.4l-4 4a1 1 0 01-1.4 0z" clip-rule="evenodd"/></svg>
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>

  {* ───────── Modal: full TOS content ───────── *}
  {if $tos}
    <template x-teleport="body">
      <div x-show="showTosModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="eb-modal-backdrop fixed inset-0" @click="showTosModal = false" aria-hidden="true"></div>
        <div class="eb-modal relative z-10 flex h-[85vh] w-full !max-w-3xl flex-col overflow-hidden !p-0" role="dialog" aria-modal="true" aria-labelledby="msp-tos-modal-title">
          <div class="eb-modal-header shrink-0">
            <div class="min-w-0 pr-2">
              <h2 id="msp-tos-modal-title" class="eb-modal-title">{$tos->title|default:'Terms of Service'|escape}</h2>
            </div>
            <button type="button" class="eb-modal-close shrink-0" @click="showTosModal = false" aria-label="Close">&times;</button>
          </div>
          <div class="eb-modal-body min-h-0 flex-1 overflow-y-auto">
            <div class="eb-legal-html">
              {$tos->content_html|unescape:'html' nofilter}
            </div>
          </div>
        </div>
      </div>
    </template>
  {/if}

  {* ───────── Modal: full Privacy content ───────── *}
  {if $privacy}
    <template x-teleport="body">
      <div x-show="showPrivacyModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="eb-modal-backdrop fixed inset-0" @click="showPrivacyModal = false" aria-hidden="true"></div>
        <div class="eb-modal relative z-10 flex h-[85vh] w-full !max-w-3xl flex-col overflow-hidden !p-0" role="dialog" aria-modal="true" aria-labelledby="msp-privacy-modal-title">
          <div class="eb-modal-header shrink-0">
            <div class="min-w-0 pr-2">
              <h2 id="msp-privacy-modal-title" class="eb-modal-title">{$privacy->title|default:'Privacy Policy'|escape}</h2>
            </div>
            <button type="button" class="eb-modal-close shrink-0" @click="showPrivacyModal = false" aria-label="Close">&times;</button>
          </div>
          <div class="eb-modal-body min-h-0 flex-1 overflow-y-auto">
            <div class="eb-legal-html">
              {$privacy->content_html|unescape:'html' nofilter}
            </div>
          </div>
        </div>
      </div>
    </template>
  {/if}
</div>
