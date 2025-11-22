{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-5xl px-6 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Settings — Tax &amp; Invoicing</h1>
      <div class="flex items-center gap-3 text-sm">
        <button id="tax-btn-preview" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5">Preview sample invoice</button>
        <button id="tax-btn-save" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90 disabled:opacity-50" disabled>Save</button>
      </div>
    </div>

    <input type="hidden" id="eb-token" value="{$token}" />

    <!-- Tax Mode -->
    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Tax Mode</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-5">
        <label class="md:col-span-6 flex items-center gap-3 text-sm"><input id="tx-stripe-tax" type="checkbox" class="rounded" {if $settings.tax_mode.stripe_tax_enabled}checked{/if}> Enable Stripe Tax</label>
        <label class="md:col-span-6 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Default tax behavior</span>
          <select id="tx-tax-behavior" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            <option value="exclusive" {if $settings.tax_mode.default_tax_behavior=='exclusive'}selected{/if}>Exclusive</option>
            <option value="inclusive" {if $settings.tax_mode.default_tax_behavior=='inclusive'}selected{/if}>Inclusive</option>
          </select>
        </label>
        <label class="md:col-span-12 flex items-center gap-3 text-sm"><input id="tx-respect-exemption" type="checkbox" class="rounded" {if $settings.tax_mode.respect_exemption}checked{/if}> Customer tax exemption respected</label>
      </div>
    </section>

    <!-- Registrations -->
    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5 flex items-center justify-between">
        <h2 class="text-lg font-medium">Registrations</h2>
        <button id="tx-btn-add-reg" class="rounded-xl px-3 py-2 text-sm text-white/80 ring-1 ring-white/10 hover:bg-white/5">Add registration</button>
      </div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-white/10">
          <table class="w-full text-sm">
            <thead class="bg-white/5 text-white/70">
            <tr class="text-left"><th class="px-4 py-3 font-medium">Country</th><th class="px-4 py-3 font-medium">Region</th><th class="px-4 py-3 font-medium">Registration #</th><th class="px-4 py-3 font-medium">Legal name</th><th class="px-4 py-3 font-medium text-right">Actions</th></tr>
            </thead>
            <tbody id="tx-reg-tbody" class="divide-y divide-white/10">
              {foreach from=$registrations item=r}
              <tr class="hover:bg-white/5" data-id="{$r.id}"><td class="px-4 py-3">{$r.country|escape}</td><td class="px-4 py-3">{$r.region|default:'-'|escape}</td><td class="px-4 py-3">{$r.registration_number|escape}</td><td class="px-4 py-3">{$r.legal_name|default:'-'|escape}</td><td class="px-4 py-3 text-right"><button class="tx-del rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10">Delete</button></td></tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Invoice Presentation -->
    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Invoice Presentation</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-5">
        <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Invoice prefix</span><input id="tx-prefix" value="{$settings.invoice_presentation.invoice_prefix|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Payment terms</span>
          <select id="tx-terms" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            <option value="due_immediately" {if $settings.invoice_presentation.payment_terms=='due_immediately'}selected{/if}>Due immediately</option>
            <option value="net_7" {if $settings.invoice_presentation.payment_terms=='net_7'}selected{/if}>Net 7</option>
            <option value="net_15" {if $settings.invoice_presentation.payment_terms=='net_15'}selected{/if}>Net 15</option>
            <option value="net_30" {if $settings.invoice_presentation.payment_terms=='net_30'}selected{/if}>Net 30</option>
          </select>
        </label>
        <label class="md:col-span-4 flex items-end gap-3 text-sm"><input id="tx-show-qtyxprice" type="checkbox" class="rounded" {if $settings.invoice_presentation.show_qty_x_price}checked{/if}> Show quantity × unit price on line items</label>
        <label class="md:col-span-12 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Invoice memo/footer (Markdown)</span>
          <textarea id="tx-footer-md" rows="4" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">{$settings.invoice_presentation.footer_md|escape}</textarea>
        </label>
        <label class="md:col-span-6 flex items-center gap-3 text-sm"><input id="tx-show-logo" type="checkbox" class="rounded" {if $settings.invoice_presentation.show_logo}checked{/if}> Display company logo on invoices</label>
        <div class="md:col-span-6 grid grid-cols-1 md:grid-cols-12 gap-5 items-end">
          <label class="md:col-span-6 flex items-center gap-3 text-sm"><input id="tx-show-legal" type="checkbox" class="rounded" {if $settings.invoice_presentation.show_legal_override}checked{/if}> Display legal business name override</label>
          <label class="md:col-span-6 block"><input id="tx-legal-name" value="{$settings.invoice_presentation.legal_name_override|escape}" placeholder="Legal business name" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        </div>
      </div>
    </section>

    <!-- Credit Notes & Refunds + Rounding -->
    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Credit Notes &amp; Rounding</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-5">
        <label class="md:col-span-4 flex items-center gap-3 text-sm"><input id="tx-allow-partial" type="checkbox" class="rounded" {if $settings.credit_notes.allow_partial}checked{/if}> Allow partial credit notes</label>
        <label class="md:col-span-4 flex items-center gap-3 text-sm"><input id="tx-allow-negative" type="checkbox" class="rounded" {if $settings.credit_notes.allow_negative_lines}checked{/if}> Allow negative line items</label>
        <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Default credit note reason</span>
          <select id="tx-credit-reason" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            <option value="customer_request" {if $settings.credit_notes.default_reason=='customer_request'}selected{/if}>Customer request</option>
            <option value="service_issue" {if $settings.credit_notes.default_reason=='service_issue'}selected{/if}>Service issue</option>
            <option value="promotion" {if $settings.credit_notes.default_reason=='promotion'}selected{/if}>Promotion</option>
          </select>
        </label>
        <label class="md:col-span-6 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Rounding mode</span>
          <select id="tx-rounding" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            <option value="bankers_rounding" {if $settings.rounding.rounding_mode=='bankers_rounding'}selected{/if}>Bankers rounding</option>
            <option value="round_half_up" {if $settings.rounding.rounding_mode=='round_half_up'}selected{/if}>Round half up</option>
          </select>
        </label>
        <label class="md:col-span-6 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Small balance write‑off threshold</span>
          <input id="tx-writeoff" type="number" min="0" step="0.01" value="{if isset($settings.rounding.writeoff_threshold_cents)}{$settings.rounding.writeoff_threshold_cents/100}{else}0.00{/if}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
      </div>
    </section>

    <!-- Add/Edit Registration Modal (static HTML) -->
    <div id="tx-reg-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" data-tx-close></div>
      <div class="relative w-full max-w-lg rounded-2xl bg-slate-900 ring-1 ring-white/10 shadow-xl">
        <div class="px-6 py-5 flex items-center justify-between">
          <h3 class="text-lg font-medium">Registration</h3>
          <button class="text-white/70 hover:text-white" data-tx-close>✕</button>
        </div>
        <div class="border-t border-white/10"></div>
        <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-5 text-sm">
          <input type="hidden" id="tx-reg-id" value="" />
          <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Country</span><input id="tx-reg-country" placeholder="CA" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5 uppercase" /></label>
          <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Region</span><input id="tx-reg-region" placeholder="ON" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5 uppercase" /></label>
          <label class="md:col-span-4 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Registration #</span><input id="tx-reg-number" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
          <label class="md:col-span-12 block"><span class="text-sm text-[rgb(var(--text-secondary))]">Legal name (optional)</span><input id="tx-reg-legal" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" /></label>
        </div>
        <div class="px-6 pb-6 flex justify-end gap-3"><button class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5" data-tx-close>Cancel</button><button id="tx-reg-save" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Save</button></div>
      </div>
    </div>

    <!-- Preview Modal -->
    <div id="tx-preview-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" data-tx-prev-close></div>
      <div class="relative w-full max-w-3xl rounded-2xl bg-slate-900 ring-1 ring-white/10 shadow-xl">
        <div class="px-6 py-5 flex items-center justify-between"><h3 class="text-lg font-medium">Invoice preview</h3><button class="text-white/70 hover:text-white" data-tx-prev-close>✕</button></div>
        <div class="border-t border-white/10"></div>
        <div class="px-6 py-6 text-sm">
          <div id="tx-preview-body" class="prose prose-invert max-w-none">
            <p>This is a sample invoice preview using your current settings. Logo/footer and prefix are applied for demonstration.</p>
          </div>
        </div>
        <div class="px-6 pb-6 flex justify-end"><button class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5" data-tx-prev-close>Close</button></div>
      </div>
    </div>
  </div>
</div>

<script src="modules/addons/eazybackup/assets/js/settings-tax.js"></script>


