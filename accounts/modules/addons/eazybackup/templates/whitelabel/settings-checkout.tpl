{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-5xl px-6 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Settings — Checkout &amp; Dunning</h1>
      <div class="flex items-center gap-3 text-sm">
        <a href="{$modulelink}&a=ph-stripe-manage" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5">Manage Stripe Account</a>
        <button id="sc-btn-save" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90 disabled:opacity-50" disabled>Save</button>
      </div>
    </div>

    {assign var=capCards value=$capabilities.cards|default:1}
    {assign var=capBank value=$capabilities.bank_debits|default:0}
    <input type="hidden" id="eb-token" value="{$token}" />

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-visible">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Checkout Experience</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-5">
        <label class="md:col-span-6 block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Require billing address</span>
          <select id="sc-address-require" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            <option value="none" {if $settings.checkout_experience.require_billing_address=='none'}selected{/if}>None</option>
            <option value="postal_code" {if $settings.checkout_experience.require_billing_address=='postal_code'}selected{/if}>Postal code</option>
            <option value="full_address" {if $settings.checkout_experience.require_billing_address=='full_address'}selected{/if}>Full address</option>
          </select>
        </label>
        <label class="md:col-span-6 flex items-end gap-3">
          <input id="sc-collect-tax-id" type="checkbox" class="rounded text-[rgb(var(--accent))]" {if $settings.checkout_experience.collect_tax_id}checked{/if}>
          <span class="text-sm">Collect company tax number on checkout</span>
        </label>

        <label class="md:col-span-6 block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Statement descriptor (max 22 chars; auto‑uppercased)</span>
          <input id="sc-descriptor" value="{$settings.checkout_experience.statement_descriptor|escape}" maxlength="22" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
          <span id="sc-descriptor-help" class="mt-1 block text-xs text-white/50">Appears on card statements. A–Z, 0–9 and spaces only.</span>
        </label>
        <label class="md:col-span-6 block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Support information URL</span>
          <input id="sc-support-url" value="{$settings.checkout_experience.support_url|escape}" placeholder="https://" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>

        <label class="md:col-span-6 block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Default currency</span>
          {assign var=cur value=$settings.checkout_experience.default_currency|upper|default:'CAD'}
          <div x-data="{ open:false, value: '{$cur|escape}', options: ['CAD','USD','EUR','GBP','AUD','NZD','SEK','NOK','DKK','CHF','JPY','ZAR','MXN','BRL','INR','SGD','HKD'] }" class="mt-2 relative">
            <input type="hidden" id="sc-default-currency" :value="value" />
            <button type="button" @click="open=!open" class="w-full flex items-center justify-between rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
              <span x-text="value"></span>
              <svg class="w-4 h-4 ml-2 opacity-70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
            </button>
            <div x-show="open" @click.away="open=false" class="absolute z-10 mt-1 w-full rounded-xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 shadow-xl overflow-hidden">
              <ul class="max-h-60 overflow-y-auto divide-y divide-white/10">
                <template x-for="opt in options" :key="opt">
                  <li>
                    <button type="button" class="w-full text-left px-3.5 py-2.5 hover:bg-white/5" @click="value=opt; open=false; try{ document.getElementById('sc-default-currency').dispatchEvent(new Event('input')); }catch(_){}" x-text="opt"></button>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </label>
      </div>
    </section>

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Payment Methods</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 space-y-4">
        <label class="flex items-center gap-3 text-sm"><input id="sc-accept-cards" type="checkbox" class="rounded" {if $settings.payment_methods.cards}checked{/if}> Accept cards</label>
        <label class="flex items-center gap-3 text-sm"><input id="sc-apple-google-pay" type="checkbox" class="rounded" {if $settings.payment_methods.apple_google_pay}checked{/if}> Allow Apple Pay / Google Pay</label>
        <label class="flex items-center gap-3 text-sm"><input id="sc-accept-bank-debits" type="checkbox" class="rounded" {if $settings.payment_methods.bank_debits}checked{/if}> Accept bank debits / ACH / PAD</label>
        <div id="sc-warn-bank" data-cap-bank="{$capBank}" class="{if $settings.payment_methods.bank_debits && !$capBank}block{else}hidden{/if} mt-1 rounded-xl bg-amber-500/10 ring-1 ring-amber-400/20 px-4 py-3 text-sm text-amber-200">
          Bank debit capability is not enabled on Stripe; click Manage Account to enable.
        </div>
        <label class="flex items-center gap-3 text-sm"><input id="sc-retry-mandate-bank-debits" type="checkbox" class="rounded" {if $settings.payment_methods.retry_mandate_bank_debits}checked{/if}> Retry unpaid bank debits (mandate)</label>
      </div>
    </section>

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Trials &amp; Proration</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-5">
        <label class="md:col-span-4 block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Default trial days</span>
          <input id="sc-trial-days" type="number" min="0" step="1" value="{$settings.trials_proration.default_trial_days|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <label class="md:col-span-4 block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Proration behavior</span>
          <select id="sc-proration" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            <option value="prorate_now" {if $settings.trials_proration.proration_behavior=='prorate_now'}selected{/if}>Prorate now</option>
            <option value="prorate_on_next_invoice" {if $settings.trials_proration.proration_behavior=='prorate_on_next_invoice'}selected{/if}>Prorate on next invoice</option>
            <option value="no_proration" {if $settings.trials_proration.proration_behavior=='no_proration'}selected{/if}>No proration</option>
          </select>
        </label>
        <label class="md:col-span-4 flex items-end gap-3">
          <input id="sc-end-trial-on-usage" type="checkbox" class="rounded" {if $settings.trials_proration.end_trial_on_usage}checked{/if}>
          <span class="text-sm">End trial early when usage &gt; 0</span>
        </label>
      </div>
    </section>

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Dunning &amp; Collections</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-5">
        <label class="md:col-span-12 block">
          <span class="text-sm text-[rgb(var(--text-secondary))]">Retry schedule (days; comma‑separated, 3–5 steps)</span>
          {assign var=retryStr value=","|implode:$settings.dunning_collections.retry_schedule_days}
          <input id="sc-retry-schedule" value="{$retryStr|default:'0,3,7,14'|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
          <span class="mt-1 block text-xs text-white/50">Example: 0,3,7,14</span>
        </label>
        <label class="md:col-span-6 flex items-center gap-3 text-sm"><input id="sc-send-failed-email" type="checkbox" class="rounded" {if $settings.dunning_collections.send_payment_failed_email}checked{/if}> Send “payment failed” email</label>
        <label class="md:col-span-3 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Auto‑pause after N failed attempts (blank = never)</span><input id="sc-auto-pause-attempts" type="number" min="0" step="1" value="{$settings.dunning_collections.auto_pause_after_attempts|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-3 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Auto‑cancel after N days past due (blank = never)</span><input id="sc-auto-cancel-days" type="number" min="0" step="1" value="{$settings.dunning_collections.auto_cancel_after_days|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-6 flex items-center gap-3 text-sm"><input id="sc-take-past-due-on-success" type="checkbox" class="rounded" {if $settings.dunning_collections.take_past_due_on_next_success}checked{/if}> Take past due balance on next successful payment</label>
      </div>
    </section>

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Customer Portal (optional)</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 space-y-4">
        <label class="flex items-center gap-3 text-sm"><input id="sc-portal-enabled" type="checkbox" class="rounded" {if $settings.customer_portal.enabled}checked{/if}> Enable Stripe Customer Portal</label>
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
          <label class="md:col-span-3 flex items-center gap-3 text-sm"><input id="sc-portal-update-payment" type="checkbox" class="rounded" {if $settings.customer_portal.allow_update_payment}checked{/if}> Update payment method</label>
          <label class="md:col-span-3 flex items-center gap-3 text-sm"><input id="sc-portal-view-invoices" type="checkbox" class="rounded" {if $settings.customer_portal.allow_view_invoices}checked{/if}> View invoices</label>
          <label class="md:col-span-3 flex items-center gap-3 text-sm"><input id="sc-portal-cancel" type="checkbox" class="rounded" {if $settings.customer_portal.allow_cancel}checked{/if}> Cancel subscription</label>
          <label class="md:col-span-3 flex items-center gap-3 text-sm"><input id="sc-portal-resume" type="checkbox" class="rounded" {if $settings.customer_portal.allow_resume}checked{/if}> Resume subscription</label>
          <label class="md:col-span-12 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Return URL</span><input id="sc-portal-return-url" value="{$settings.customer_portal.return_url|escape}" placeholder="https://" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        </div>
      </div>
    </section>

    <!-- Currency change confirm modal (static HTML per user preference) -->
    <div id="sc-currency-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" data-sc-modal-close></div>
      <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 ring-1 ring-white/10 shadow-xl">
        <div class="px-6 py-5 flex items-center justify-between">
          <h3 class="text-lg font-medium">Change default currency?</h3>
          <button class="text-white/70 hover:text-white" data-sc-modal-close>✕</button>
        </div>
        <div class="border-t border-white/10"></div>
        <div class="px-6 py-6 text-sm text-white/80 space-y-3">
          <p>Existing published Prices remain unchanged. Only newly created Prices will use the new default currency.</p>
          <p>Proceed?</p>
        </div>
        <div class="px-6 pb-6 flex justify-end gap-3">
          <button class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5" data-sc-modal-close>Cancel</button>
          <button id="sc-currency-confirm" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Confirm</button>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="modules/addons/eazybackup/assets/js/settings-checkout.js"></script>


