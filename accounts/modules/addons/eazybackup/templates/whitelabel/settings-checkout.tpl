{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhContent}
          <div class="flex flex-col gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Settings — Checkout &amp; Dunning</h1>
              <p class="eb-page-description mt-1">Configure checkout experience, payment methods, trials, and dunning.</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3 text-sm">
              <a href="{$modulelink}&a=ph-stripe-manage" class="eb-btn eb-btn-outline eb-btn-sm">Manage Stripe Account</a>
              <button type="button" id="sc-btn-save" class="eb-btn eb-btn-primary eb-btn-sm disabled:opacity-50" disabled>Save</button>
            </div>
          </div>
          <div class="p-6">
    {assign var=capCards value=$capabilities.cards|default:1}
    {assign var=capBank value=$capabilities.bank_debits|default:0}
    <input type="hidden" id="eb-token" value="{$token}" />
    {assign var=scAddrReq value=$settings.checkout_experience.require_billing_address|default:'postal_code'}
    {assign var=scProration value=$settings.trials_proration.proration_behavior|default:'prorate_now'}

    <section class="eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Checkout Experience</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="eb-field-label !mb-0">Require billing address</span>
          <div
            x-data="{
              open: false,
              value: '{$scAddrReq|escape:'javascript'}',
              options: [
                { value: 'none', label: 'None' },
                { value: 'postal_code', label: 'Postal code' },
                { value: 'full_address', label: 'Full address' }
              ],
              currentLabel() {
                const o = this.options.find((o) => o.value === this.value);
                return o ? o.label : this.value;
              }
            }"
            @keydown.escape.window="open = false"
            class="relative z-10 mt-2"
          >
            <input type="hidden" id="sc-address-require" :value="value" />
            <button type="button" @click="open = !open" class="eb-input flex w-full cursor-pointer items-center justify-between gap-2 text-left" :aria-expanded="open">
              <span class="truncate" x-text="currentLabel()"></span>
              <svg class="h-4 w-4 shrink-0 opacity-70" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
            </button>
            <div x-show="open" x-cloak @click.outside="open = false" class="eb-dropdown-menu absolute z-[80] mt-1 w-full overflow-hidden !min-w-0 shadow-[var(--eb-shadow-lg)]">
              <ul class="max-h-60 divide-y divide-[var(--eb-border-subtle)] overflow-y-auto">
                <template x-for="opt in options" :key="opt.value">
                  <li>
                    <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" @click="value = opt.value; open = false" x-text="opt.label"></button>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-collect-tax-id" type="checkbox" class="eb-check-input shrink-0" {if $settings.checkout_experience.collect_tax_id}checked{/if} />
          <span>Collect company tax number on checkout</span>
        </label>

        <label class="block"><span class="eb-field-label !mb-0">Statement descriptor (max 22 chars; auto‑uppercased)</span>
          <input id="sc-descriptor" value="{$settings.checkout_experience.statement_descriptor|escape}" maxlength="22" class="eb-input mt-2 w-full" />
          <span id="sc-descriptor-help" class="eb-field-help mt-1 block">Appears on card statements. A–Z, 0–9 and spaces only.</span>
        </label>
        <label class="block"><span class="eb-field-label !mb-0">Support information URL</span>
          <input id="sc-support-url" value="{$settings.checkout_experience.support_url|escape}" placeholder="https://" class="eb-input mt-2 w-full" />
        </label>

        <label class="block"><span class="eb-field-label !mb-0">Default currency</span>
          {assign var=cur value=$settings.checkout_experience.default_currency|upper|default:'CAD'}
          <div x-data="{ open:false, value: '{$cur|escape}', options: ['CAD','USD','EUR','GBP','AUD','NZD','SEK','NOK','DKK','CHF','JPY','ZAR','MXN','BRL','INR','SGD','HKD'] }" @keydown.escape.window="open = false" class="relative z-10 mt-2">
            <input type="hidden" id="sc-default-currency" :value="value" />
            <button type="button" @click="open=!open" class="eb-input flex w-full cursor-pointer items-center justify-between gap-2 text-left" :aria-expanded="open">
              <span class="truncate" x-text="value"></span>
              <svg class="h-4 w-4 shrink-0 opacity-70" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
            </button>
            <div x-show="open" x-cloak @click.outside="open=false" class="eb-dropdown-menu absolute z-[80] mt-1 w-full overflow-hidden !min-w-0 shadow-[var(--eb-shadow-lg)]">
              <ul class="max-h-60 divide-y divide-[var(--eb-border-subtle)] overflow-y-auto">
                <template x-for="opt in options" :key="opt">
                  <li>
                    <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" @click="value=opt; open=false; try{ document.getElementById('sc-default-currency').dispatchEvent(new Event('input')); }catch(_){}" x-text="opt"></button>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </label>
      </div>
    </section>

    <section class="mt-6 eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Payment Methods</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-accept-cards" type="checkbox" class="eb-check-input shrink-0" {if $settings.payment_methods.cards}checked{/if} />
          <span>Accept cards</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-apple-google-pay" type="checkbox" class="eb-check-input shrink-0" {if $settings.payment_methods.apple_google_pay}checked{/if} />
          <span>Allow Apple Pay / Google Pay</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-accept-bank-debits" type="checkbox" class="eb-check-input shrink-0" {if $settings.payment_methods.bank_debits}checked{/if} />
          <span>Accept bank debits / ACH / PAD</span>
        </label>
        <div id="sc-warn-bank" data-cap-bank="{$capBank}" class="{if $settings.payment_methods.bank_debits && !$capBank}block{else}hidden{/if} eb-alert eb-alert--warning !mb-0 !mt-1">Bank debit capability is not enabled on Stripe; click Manage Account to enable.</div>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-retry-mandate-bank-debits" type="checkbox" class="eb-check-input shrink-0" {if $settings.payment_methods.retry_mandate_bank_debits}checked{/if} />
          <span>Retry unpaid bank debits (mandate)</span>
        </label>
      </div>
    </section>

    <section class="mt-6 eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Trials &amp; Proration</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="eb-field-label !mb-0">Default trial days</span>
          <input id="sc-trial-days" type="number" min="0" step="1" value="{$settings.trials_proration.default_trial_days|escape}" class="eb-input mt-2 w-full" />
        </label>
        <label class="block"><span class="eb-field-label !mb-0">Proration behavior</span>
          <div
            x-data="{
              open: false,
              value: '{$scProration|escape:'javascript'}',
              options: [
                { value: 'prorate_now', label: 'Prorate now' },
                { value: 'prorate_on_next_invoice', label: 'Prorate on next invoice' },
                { value: 'no_proration', label: 'No proration' }
              ],
              currentLabel() {
                const o = this.options.find((o) => o.value === this.value);
                return o ? o.label : this.value;
              }
            }"
            @keydown.escape.window="open = false"
            class="relative z-10 mt-2"
          >
            <input type="hidden" id="sc-proration" :value="value" />
            <button type="button" @click="open = !open" class="eb-input flex w-full cursor-pointer items-center justify-between gap-2 text-left" :aria-expanded="open">
              <span class="truncate" x-text="currentLabel()"></span>
              <svg class="h-4 w-4 shrink-0 opacity-70" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
            </button>
            <div x-show="open" x-cloak @click.outside="open = false" class="eb-dropdown-menu absolute z-[80] mt-1 w-full overflow-hidden !min-w-0 shadow-[var(--eb-shadow-lg)]">
              <ul class="max-h-60 divide-y divide-[var(--eb-border-subtle)] overflow-y-auto">
                <template x-for="opt in options" :key="opt.value">
                  <li>
                    <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]" @click="value = opt.value; open = false" x-text="opt.label"></button>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-end-trial-on-usage" type="checkbox" class="eb-check-input shrink-0" {if $settings.trials_proration.end_trial_on_usage}checked{/if} />
          <span>End trial early when usage &gt; 0</span>
        </label>
      </div>
    </section>

    <section class="mt-6 eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Dunning &amp; Collections</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="eb-field-label !mb-0">Retry schedule (days; comma‑separated, 3–5 steps)</span>
          {assign var=retryStr value=","|implode:$settings.dunning_collections.retry_schedule_days}
          <input id="sc-retry-schedule" value="{$retryStr|default:'0,3,7,14'|escape}" class="eb-input mt-2 w-full" />
          <span class="eb-field-help mt-1 block">Example: 0,3,7,14</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-send-failed-email" type="checkbox" class="eb-check-input shrink-0" {if $settings.dunning_collections.send_payment_failed_email}checked{/if} />
          <span>Send “payment failed” email</span>
        </label>
        <label class="block"><span class="eb-field-label !mb-0">Auto‑pause after N failed attempts (blank = never)</span><input id="sc-auto-pause-attempts" type="number" min="0" step="1" value="{$settings.dunning_collections.auto_pause_after_attempts|escape}" class="eb-input mt-2 w-full" /></label>
        <label class="block"><span class="eb-field-label !mb-0">Auto‑cancel after N days past due (blank = never)</span><input id="sc-auto-cancel-days" type="number" min="0" step="1" value="{$settings.dunning_collections.auto_cancel_after_days|escape}" class="eb-input mt-2 w-full" /></label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-take-past-due-on-success" type="checkbox" class="eb-check-input shrink-0" {if $settings.dunning_collections.take_past_due_on_next_success}checked{/if} />
          <span>Take past due balance on next successful payment</span>
        </label>
      </div>
    </section>

    <section class="mt-6 eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Customer Portal (optional)</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-portal-enabled" type="checkbox" class="eb-check-input shrink-0" {if $settings.customer_portal.enabled}checked{/if} />
          <span>Enable Stripe Customer Portal</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-portal-update-payment" type="checkbox" class="eb-check-input shrink-0" {if $settings.customer_portal.allow_update_payment}checked{/if} />
          <span>Update payment method</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-portal-view-invoices" type="checkbox" class="eb-check-input shrink-0" {if $settings.customer_portal.allow_view_invoices}checked{/if} />
          <span>View invoices</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-portal-cancel" type="checkbox" class="eb-check-input shrink-0" {if $settings.customer_portal.allow_cancel}checked{/if} />
          <span>Cancel subscription</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="sc-portal-resume" type="checkbox" class="eb-check-input shrink-0" {if $settings.customer_portal.allow_resume}checked{/if} />
          <span>Resume subscription</span>
        </label>
        <label class="block"><span class="eb-field-label !mb-0">Return URL</span><input id="sc-portal-return-url" value="{$settings.customer_portal.return_url|escape}" placeholder="https://" class="eb-input mt-2 w-full" /></label>
      </div>
    </section>

    <div id="sc-currency-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 eb-modal-backdrop backdrop-blur-sm" data-sc-modal-close></div>
      <div class="eb-modal eb-modal--confirm relative z-10 w-full max-w-lg">
        <div class="eb-modal-header">
          <h3 class="eb-modal-title">Change default currency?</h3>
          <button type="button" class="eb-modal-close" data-sc-modal-close aria-label="Close">✕</button>
        </div>
        <div class="eb-modal-body space-y-3 text-sm text-[var(--eb-text-secondary)]">
          <p>Existing published Prices remain unchanged. Only newly created Prices will use the new default currency.</p>
          <p>Proceed?</p>
        </div>
        <div class="eb-modal-footer !justify-end !gap-3">
          <button type="button" class="eb-btn eb-btn-outline eb-btn-sm" data-sc-modal-close>Cancel</button>
          <button type="button" id="sc-currency-confirm" class="eb-btn eb-btn-primary eb-btn-sm">Confirm</button>
        </div>
      </div>
    </div>

          </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='settings-checkout'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}

<script src="modules/addons/eazybackup/assets/js/settings-checkout.js"></script>
