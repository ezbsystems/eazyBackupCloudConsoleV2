{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='settings-checkout'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
      <div>
        <h2 class="text-2xl font-semibold text-white">Settings — Checkout &amp; Dunning</h2>
        <p class="text-xs text-slate-400 mt-1">Configure checkout experience, payment methods, trials, and dunning.</p>
      </div>
      <div class="flex items-center gap-3 text-sm shrink-0">
        <a href="{$modulelink}&a=ph-stripe-manage" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-800">Manage Stripe Account</a>
        <button type="button" id="sc-btn-save" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 disabled:opacity-50" disabled>Save</button>
      </div>
    </div>

    {assign var=capCards value=$capabilities.cards|default:1}
    {assign var=capBank value=$capabilities.bank_debits|default:0}
    <input type="hidden" id="eb-token" value="{$token}" />

    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Checkout Experience</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="text-sm text-slate-400">Require billing address</span>
          <select id="sc-address-require" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition">
            <option value="none" {if $settings.checkout_experience.require_billing_address=='none'}selected{/if}>None</option>
            <option value="postal_code" {if $settings.checkout_experience.require_billing_address=='postal_code'}selected{/if}>Postal code</option>
            <option value="full_address" {if $settings.checkout_experience.require_billing_address=='full_address'}selected{/if}>Full address</option>
          </select>
        </label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-collect-tax-id" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.checkout_experience.collect_tax_id}checked{/if}> Collect company tax number on checkout</label>

        <label class="block"><span class="text-sm text-slate-400">Statement descriptor (max 22 chars; auto‑uppercased)</span>
          <input id="sc-descriptor" value="{$settings.checkout_experience.statement_descriptor|escape}" maxlength="22" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" />
          <span id="sc-descriptor-help" class="mt-1 block text-xs text-slate-400">Appears on card statements. A–Z, 0–9 and spaces only.</span>
        </label>
        <label class="block"><span class="text-sm text-slate-400">Support information URL</span>
          <input id="sc-support-url" value="{$settings.checkout_experience.support_url|escape}" placeholder="https://" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" />
        </label>

        <label class="block"><span class="text-sm text-slate-400">Default currency</span>
          {assign var=cur value=$settings.checkout_experience.default_currency|upper|default:'CAD'}
          <div x-data="{ open:false, value: '{$cur|escape}', options: ['CAD','USD','EUR','GBP','AUD','NZD','SEK','NOK','DKK','CHF','JPY','ZAR','MXN','BRL','INR','SGD','HKD'] }" class="mt-2 relative">
            <input type="hidden" id="sc-default-currency" :value="value" />
            <button type="button" @click="open=!open" class="w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition">
              <span class="truncate" x-text="value"></span>
              <svg class="w-4 h-4 shrink-0 opacity-70" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
            </button>
            <div x-show="open" @click.away="open=false" class="absolute z-10 mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 shadow-xl overflow-hidden">
              <ul class="max-h-60 overflow-y-auto divide-y divide-slate-800">
                <template x-for="opt in options" :key="opt">
                  <li>
                    <button type="button" class="w-full text-left px-3 py-2.5 text-slate-200 hover:bg-slate-800" @click="value=opt; open=false; try{ document.getElementById('sc-default-currency').dispatchEvent(new Event('input')); }catch(_){}" x-text="opt"></button>
                  </li>
                </template>
              </ul>
            </div>
          </div>
        </label>
      </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Payment Methods</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-accept-cards" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.payment_methods.cards}checked{/if}> Accept cards</label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-apple-google-pay" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.payment_methods.apple_google_pay}checked{/if}> Allow Apple Pay / Google Pay</label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-accept-bank-debits" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.payment_methods.bank_debits}checked{/if}> Accept bank debits / ACH / PAD</label>
        <div id="sc-warn-bank" data-cap-bank="{$capBank}" class="{if $settings.payment_methods.bank_debits && !$capBank}block{else}hidden{/if} mt-1 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">Bank debit capability is not enabled on Stripe; click Manage Account to enable.</div>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-retry-mandate-bank-debits" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.payment_methods.retry_mandate_bank_debits}checked{/if}> Retry unpaid bank debits (mandate)</label>
      </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Trials &amp; Proration</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="text-sm text-slate-400">Default trial days</span>
          <input id="sc-trial-days" type="number" min="0" step="1" value="{$settings.trials_proration.default_trial_days|escape}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" />
        </label>
        <label class="block"><span class="text-sm text-slate-400">Proration behavior</span>
          <select id="sc-proration" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition">
            <option value="prorate_now" {if $settings.trials_proration.proration_behavior=='prorate_now'}selected{/if}>Prorate now</option>
            <option value="prorate_on_next_invoice" {if $settings.trials_proration.proration_behavior=='prorate_on_next_invoice'}selected{/if}>Prorate on next invoice</option>
            <option value="no_proration" {if $settings.trials_proration.proration_behavior=='no_proration'}selected{/if}>No proration</option>
          </select>
        </label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-end-trial-on-usage" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.trials_proration.end_trial_on_usage}checked{/if}> End trial early when usage &gt; 0</label>
      </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Dunning &amp; Collections</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="text-sm text-slate-400">Retry schedule (days; comma‑separated, 3–5 steps)</span>
          {assign var=retryStr value=","|implode:$settings.dunning_collections.retry_schedule_days}
          <input id="sc-retry-schedule" value="{$retryStr|default:'0,3,7,14'|escape}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" />
          <span class="mt-1 block text-xs text-slate-400">Example: 0,3,7,14</span>
        </label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-send-failed-email" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.dunning_collections.send_payment_failed_email}checked{/if}> Send “payment failed” email</label>
        <label class="block"><span class="text-sm text-slate-400">Auto‑pause after N failed attempts (blank = never)</span><input id="sc-auto-pause-attempts" type="number" min="0" step="1" value="{$settings.dunning_collections.auto_pause_after_attempts|escape}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" /></label>
        <label class="block"><span class="text-sm text-slate-400">Auto‑cancel after N days past due (blank = never)</span><input id="sc-auto-cancel-days" type="number" min="0" step="1" value="{$settings.dunning_collections.auto_cancel_after_days|escape}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" /></label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-take-past-due-on-success" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.dunning_collections.take_past_due_on_next_success}checked{/if}> Take past due balance on next successful payment</label>
      </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Customer Portal (optional)</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-portal-enabled" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.customer_portal.enabled}checked{/if}> Enable Stripe Customer Portal</label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-portal-update-payment" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.customer_portal.allow_update_payment}checked{/if}> Update payment method</label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-portal-view-invoices" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.customer_portal.allow_view_invoices}checked{/if}> View invoices</label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-portal-cancel" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.customer_portal.allow_cancel}checked{/if}> Cancel subscription</label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="sc-portal-resume" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.customer_portal.allow_resume}checked{/if}> Resume subscription</label>
        <label class="block"><span class="text-sm text-slate-400">Return URL</span><input id="sc-portal-return-url" value="{$settings.customer_portal.return_url|escape}" placeholder="https://" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" /></label>
      </div>
    </section>

    <div id="sc-currency-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-xs">
      <div class="absolute inset-0" data-sc-modal-close></div>
      <div class="relative w-full max-w-lg rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-800">
          <h3 class="text-lg font-medium text-slate-100">Change default currency?</h3>
          <button type="button" class="text-slate-400 hover:text-white" data-sc-modal-close>✕</button>
        </div>
        <div class="px-6 py-6 text-sm text-slate-300 space-y-3">
          <p>Existing published Prices remain unchanged. Only newly created Prices will use the new default currency.</p>
          <p>Proceed?</p>
        </div>
        <div class="px-6 pb-6 flex justify-end gap-3">
          <button type="button" class="rounded-lg px-4 py-2 border border-slate-600 text-slate-300 hover:bg-slate-800" data-sc-modal-close>Cancel</button>
          <button type="button" id="sc-currency-confirm" class="rounded-lg px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500">Confirm</button>
        </div>
      </div>
    </div>

    </div>
    </div>
        </main>
      </div>
    </div>
  </div>
</div>

<script src="modules/addons/eazybackup/assets/js/settings-checkout.js"></script>
