{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='settings-tax'}
        <main class="flex-1 min-w-0 overflow-x-auto">
          <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
            <div>
              <h1 class="text-2xl font-semibold tracking-tight">Settings - Tax &amp; Invoicing</h1>
              <p class="mt-1 text-sm text-slate-400">Tax mode, registrations, and invoice presentation.</p>
            </div>
            <div class="flex items-center gap-3 text-sm shrink-0">
              <button type="button" id="tax-btn-preview" class="inline-flex items-center rounded-xl px-4 py-2 text-slate-300 ring-1 ring-white/10 hover:bg-white/5">Preview sample invoice</button>
              <button type="button" id="tax-btn-save" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90 disabled:opacity-50" disabled>Save</button>
            </div>
          </div>
          <div class="p-6">

    <input type="hidden" id="eb-token" value="{$token}" />

    <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Tax Mode</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="tx-stripe-tax" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.tax_mode.stripe_tax_enabled}checked{/if}> Enable Stripe Tax</label>
        <label class="block"><span class="text-sm text-slate-400">Default tax behavior</span>
          <select id="tx-tax-behavior" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition">
            <option value="exclusive" {if $settings.tax_mode.default_tax_behavior=='exclusive'}selected{/if}>Exclusive</option>
            <option value="inclusive" {if $settings.tax_mode.default_tax_behavior=='inclusive'}selected{/if}>Inclusive</option>
          </select>
        </label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="tx-respect-exemption" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.tax_mode.respect_exemption}checked{/if}> Customer tax exemption respected</label>
      </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800 flex items-center justify-between">
        <h2 class="text-lg font-medium text-slate-100">Registrations</h2>
        <button type="button" id="tx-btn-add-reg" class="inline-flex items-center px-3 py-2 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-800 text-sm font-medium">Add registration</button>
      </div>
      <div class="px-6 py-6">
        <div class="w-full max-w-full min-w-0 rounded-xl border border-slate-800 overflow-hidden">
          <table class="w-full text-sm text-slate-300">
            <thead class="bg-slate-900/80 text-slate-300">
              <tr class="text-left">
                <th class="px-4 py-3 font-medium">Country</th>
                <th class="px-4 py-3 font-medium">Region</th>
                <th class="px-4 py-3 font-medium">Stripe Type</th>
                <th class="px-4 py-3 font-medium">Registration #</th>
                <th class="px-4 py-3 font-medium">Legal name</th>
                <th class="px-4 py-3 text-right font-medium">Actions</th>
              </tr>
            </thead>
            <tbody id="tx-reg-tbody" class="divide-y divide-slate-800">
              {foreach from=$registrations item=r}
              <tr class="hover:bg-slate-800/50" data-id="{$r.id}"><td class="px-4 py-3">{$r.country|escape}</td><td class="px-4 py-3">{$r.region|default:'-'|escape}</td><td class="px-4 py-3">{$r.stripe_registration_type_label|default:'Auto'|escape}</td><td class="px-4 py-3">{$r.registration_number|escape}</td><td class="px-4 py-3">{$r.legal_name|default:'-'|escape}</td><td class="px-4 py-3 text-right"><button type="button" class="tx-del inline-flex items-center rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-700">Delete</button></td></tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Invoice Presentation</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="text-sm text-slate-400">Invoice prefix</span><input id="tx-prefix" value="{$settings.invoice_presentation.invoice_prefix|escape}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" /></label>
        <label class="block"><span class="text-sm text-slate-400">Payment terms</span>
          <select id="tx-terms" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition">
            <option value="due_immediately" {if $settings.invoice_presentation.payment_terms=='due_immediately'}selected{/if}>Due immediately</option>
            <option value="net_7" {if $settings.invoice_presentation.payment_terms=='net_7'}selected{/if}>Net 7</option>
            <option value="net_15" {if $settings.invoice_presentation.payment_terms=='net_15'}selected{/if}>Net 15</option>
            <option value="net_30" {if $settings.invoice_presentation.payment_terms=='net_30'}selected{/if}>Net 30</option>
          </select>
        </label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="tx-show-qtyxprice" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.invoice_presentation.show_qty_x_price}checked{/if}> Show quantity × unit price on line items</label>
        <label class="block"><span class="text-sm text-slate-400">Invoice memo/footer (Markdown)</span>
          <textarea id="tx-footer-md" rows="4" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition">{$settings.invoice_presentation.footer_md|escape}</textarea>
        </label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="tx-show-logo" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.invoice_presentation.show_logo}checked{/if}> Display company logo on invoices</label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="tx-show-legal" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.invoice_presentation.show_legal_override}checked{/if}> Display legal business name override</label>
        <label class="block"><span class="text-sm text-slate-400">Legal name override</span><input id="tx-legal-name" value="{$settings.invoice_presentation.legal_name_override|escape}" placeholder="Legal business name" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" /></label>
      </div>
    </section>

    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Credit Notes &amp; Rounding</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="tx-allow-partial" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.credit_notes.allow_partial}checked{/if}> Allow partial credit notes</label>
        <label class="flex items-center gap-3 text-sm text-slate-300"><input id="tx-allow-negative" type="checkbox" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $settings.credit_notes.allow_negative_lines}checked{/if}> Allow negative line items</label>
        <label class="block"><span class="text-sm text-slate-400">Default credit note reason</span>
          <select id="tx-credit-reason" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition">
            <option value="customer_request" {if $settings.credit_notes.default_reason=='customer_request'}selected{/if}>Customer request</option>
            <option value="service_issue" {if $settings.credit_notes.default_reason=='service_issue'}selected{/if}>Service issue</option>
            <option value="promotion" {if $settings.credit_notes.default_reason=='promotion'}selected{/if}>Promotion</option>
          </select>
        </label>
        <label class="block"><span class="text-sm text-slate-400">Rounding mode</span>
          <select id="tx-rounding" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition">
            <option value="bankers_rounding" {if $settings.rounding.rounding_mode=='bankers_rounding'}selected{/if}>Bankers rounding</option>
            <option value="round_half_up" {if $settings.rounding.rounding_mode=='round_half_up'}selected{/if}>Round half up</option>
          </select>
        </label>
        <label class="block"><span class="text-sm text-slate-400">Small balance write‑off threshold</span>
          <input id="tx-writeoff" type="number" min="0" step="0.01" value="{if isset($settings.rounding.writeoff_threshold_cents)}{$settings.rounding.writeoff_threshold_cents/100}{else}0.00{/if}" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" />
        </label>
      </div>
    </section>

    <div id="tx-reg-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-xs">
      <div class="absolute inset-0" data-tx-close></div>
      <div class="relative w-full max-w-lg rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-800">
          <h3 class="text-lg font-medium text-slate-100">Registration</h3>
          <button type="button" class="text-slate-400 hover:text-white" data-tx-close>✕</button>
        </div>
        <div class="px-6 py-6 space-y-4 text-sm">
          <input type="hidden" id="tx-reg-id" value="" />
          <label class="block"><span class="text-sm text-slate-400">Country</span><input id="tx-reg-country" placeholder="CA" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 uppercase" /></label>
          <label class="block"><span class="text-sm text-slate-400">Region</span><input id="tx-reg-region" placeholder="ON" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 uppercase" /></label>
          <label class="block"><span class="text-sm text-slate-400">Stripe registration type</span>
            <select id="tx-reg-stripe-type" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition">
              <option value="">Auto (by country / region)</option>
              <option value="standard">Standard</option>
              <option value="simplified">Simplified</option>
              <option value="province_standard">Canada — provincial (PST / RST / QST)</option>
              <option value="state_sales_tax">United States — state sales tax</option>
              <option value="ioss">EU — IOSS</option>
              <option value="oss_union">EU — OSS (union scheme)</option>
              <option value="oss_non_union">EU — OSS (non-union scheme)</option>
            </select>
            <span class="mt-1 block text-xs text-slate-500">Auto now defaults to Standard for Canada and requires a state for US registrations. Wrong types are rejected by Stripe and the row is still saved locally.</span>
          </label>
          <label class="block"><span class="text-sm text-slate-400">Registration #</span><input id="tx-reg-number" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" /></label>
          <label class="block"><span class="text-sm text-slate-400">Legal name (optional)</span><input id="tx-reg-legal" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" /></label>
        </div>
        <div class="px-6 pb-6 flex justify-end gap-3"><button type="button" class="rounded-lg px-4 py-2 border border-slate-600 text-slate-300 hover:bg-slate-800" data-tx-close>Cancel</button><button type="button" id="tx-reg-save" class="rounded-lg px-4 py-2 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500">Save</button></div>
      </div>
    </div>

    <div id="tx-preview-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-xs">
      <div class="absolute inset-0" data-tx-prev-close></div>
      <div class="relative w-full max-w-3xl rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-800"><h3 class="text-lg font-medium text-slate-100">Invoice preview</h3><button type="button" class="text-slate-400 hover:text-white" data-tx-prev-close>✕</button></div>
        <div class="px-6 py-6 text-sm text-slate-300">
          <div id="tx-preview-body" class="prose prose-invert max-w-none">
            <p>This is a sample invoice preview using your current settings. Logo/footer and prefix are applied for demonstration.</p>
          </div>
        </div>
        <div class="px-6 pb-6 flex justify-end"><button type="button" class="rounded-lg px-4 py-2 border border-slate-600 text-slate-300 hover:bg-slate-800" data-tx-prev-close>Close</button></div>
      </div>
    </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</div>

<script src="modules/addons/eazybackup/assets/js/settings-tax.js"></script>
