{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhContent}
          <div class="flex flex-col gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Settings — Tax &amp; Invoicing</h1>
              <p class="eb-page-description mt-1">Tax mode, registrations, and invoice presentation.</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3 text-sm">
              <button type="button" id="tax-btn-preview" class="eb-btn eb-btn-outline eb-btn-sm">Preview sample invoice</button>
              <button type="button" id="tax-btn-save" class="eb-btn eb-btn-primary eb-btn-sm disabled:opacity-50" disabled>Save</button>
            </div>
          </div>
          <div class="p-6">

    <input type="hidden" id="eb-token" value="{$token}" />
    {assign var=txTaxBehavior value=$settings.tax_mode.default_tax_behavior|default:'exclusive'}
    {assign var=txTerms value=$settings.invoice_presentation.payment_terms|default:'due_immediately'}
    {assign var=txCreditReason value=$settings.credit_notes.default_reason|default:'customer_request'}
    {assign var=txRoundingMode value=$settings.rounding.rounding_mode|default:'bankers_rounding'}

    <section class="eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Tax Mode</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="tx-stripe-tax" type="checkbox" class="eb-check-input shrink-0" {if $settings.tax_mode.stripe_tax_enabled}checked{/if} />
          <span>Enable Stripe Tax</span>
        </label>
        <label class="block"><span class="eb-field-label !mb-0">Default tax behavior</span>
          <div
            x-data="{
              open: false,
              value: '{$txTaxBehavior|escape:'javascript'}',
              options: [
                { value: 'exclusive', label: 'Exclusive' },
                { value: 'inclusive', label: 'Inclusive' }
              ],
              currentLabel() {
                const o = this.options.find((o) => o.value === this.value);
                return o ? o.label : this.value;
              }
            }"
            @keydown.escape.window="open = false"
            class="relative z-10 mt-2"
          >
            <input type="hidden" id="tx-tax-behavior" :value="value" />
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
          <input id="tx-respect-exemption" type="checkbox" class="eb-check-input shrink-0" {if $settings.tax_mode.respect_exemption}checked{/if} />
          <span>Customer tax exemption respected</span>
        </label>
      </div>
    </section>

    <section class="mt-6 eb-card-raised !p-0">
      <div class="flex flex-col gap-3 border-b border-[var(--eb-border-subtle)] px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="eb-app-card-title">Registrations</h2>
        <button type="button" id="tx-btn-add-reg" class="eb-btn eb-btn-outline eb-btn-sm shrink-0">Add registration</button>
      </div>
      <div class="px-6 py-6">
        <div class="eb-table-shell min-w-0 max-w-full">
          <table class="eb-table min-w-full text-sm">
            <thead>
              <tr>
                <th>Country</th>
                <th>Region</th>
                <th>Registration #</th>
                <th>Legal name</th>
                <th class="!text-right">Actions</th>
              </tr>
            </thead>
            <tbody id="tx-reg-tbody">
              {foreach from=$registrations item=r}
              <tr data-id="{$r.id}"><td>{$r.country|escape}</td><td>{$r.region|default:'-'|escape}</td><td>{$r.registration_number|escape}</td><td>{$r.legal_name|default:'-'|escape}</td><td class="!text-right"><button type="button" class="tx-del eb-btn eb-btn-outline eb-btn-xs">Delete</button></td></tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="mt-6 eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Invoice Presentation</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="block"><span class="eb-field-label !mb-0">Invoice prefix</span><input id="tx-prefix" value="{$settings.invoice_presentation.invoice_prefix|escape}" class="eb-input mt-2 w-full" /></label>
        <label class="block"><span class="eb-field-label !mb-0">Payment terms</span>
          <div
            x-data="{
              open: false,
              value: '{$txTerms|escape:'javascript'}',
              options: [
                { value: 'due_immediately', label: 'Due immediately' },
                { value: 'net_7', label: 'Net 7' },
                { value: 'net_15', label: 'Net 15' },
                { value: 'net_30', label: 'Net 30' }
              ],
              currentLabel() {
                const o = this.options.find((o) => o.value === this.value);
                return o ? o.label : this.value;
              }
            }"
            @keydown.escape.window="open = false"
            class="relative z-10 mt-2"
          >
            <input type="hidden" id="tx-terms" :value="value" />
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
          <input id="tx-show-qtyxprice" type="checkbox" class="eb-check-input shrink-0" {if $settings.invoice_presentation.show_qty_x_price}checked{/if} />
          <span>Show quantity × unit price on line items</span>
        </label>
        <label class="block"><span class="eb-field-label !mb-0">Invoice memo/footer (Markdown)</span>
          <textarea id="tx-footer-md" rows="4" class="eb-textarea mt-2 w-full">{$settings.invoice_presentation.footer_md|escape}</textarea>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="tx-show-logo" type="checkbox" class="eb-check-input shrink-0" {if $settings.invoice_presentation.show_logo}checked{/if} />
          <span>Display company logo on invoices</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="tx-show-legal" type="checkbox" class="eb-check-input shrink-0" {if $settings.invoice_presentation.show_legal_override}checked{/if} />
          <span>Display legal business name override</span>
        </label>
        <label class="block"><span class="eb-field-label !mb-0">Legal name override</span><input id="tx-legal-name" value="{$settings.invoice_presentation.legal_name_override|escape}" placeholder="Legal business name" class="eb-input mt-2 w-full" /></label>
      </div>
    </section>

    <section class="mt-6 eb-card-raised !p-0">
      <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-app-card-title">Credit Notes &amp; Rounding</h2></div>
      <div class="px-6 py-6 space-y-4">
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="tx-allow-partial" type="checkbox" class="eb-check-input shrink-0" {if $settings.credit_notes.allow_partial}checked{/if} />
          <span>Allow partial credit notes</span>
        </label>
        <label class="eb-inline-choice cursor-pointer !text-[var(--eb-text-primary)]">
          <input id="tx-allow-negative" type="checkbox" class="eb-check-input shrink-0" {if $settings.credit_notes.allow_negative_lines}checked{/if} />
          <span>Allow negative line items</span>
        </label>
        <label class="block"><span class="eb-field-label !mb-0">Default credit note reason</span>
          <div
            x-data="{
              open: false,
              value: '{$txCreditReason|escape:'javascript'}',
              options: [
                { value: 'customer_request', label: 'Customer request' },
                { value: 'service_issue', label: 'Service issue' },
                { value: 'promotion', label: 'Promotion' }
              ],
              currentLabel() {
                const o = this.options.find((o) => o.value === this.value);
                return o ? o.label : this.value;
              }
            }"
            @keydown.escape.window="open = false"
            class="relative z-10 mt-2"
          >
            <input type="hidden" id="tx-credit-reason" :value="value" />
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
        <label class="block"><span class="eb-field-label !mb-0">Rounding mode</span>
          <div
            x-data="{
              open: false,
              value: '{$txRoundingMode|escape:'javascript'}',
              options: [
                { value: 'bankers_rounding', label: 'Bankers rounding' },
                { value: 'round_half_up', label: 'Round half up' }
              ],
              currentLabel() {
                const o = this.options.find((o) => o.value === this.value);
                return o ? o.label : this.value;
              }
            }"
            @keydown.escape.window="open = false"
            class="relative z-10 mt-2"
          >
            <input type="hidden" id="tx-rounding" :value="value" />
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
        <label class="block"><span class="eb-field-label !mb-0">Small balance write‑off threshold</span>
          <input id="tx-writeoff" type="number" min="0" step="0.01" value="{if isset($settings.rounding.writeoff_threshold_cents)}{$settings.rounding.writeoff_threshold_cents/100}{else}0.00{/if}" class="eb-input mt-2 w-full" />
        </label>
      </div>
    </section>

    <div id="tx-reg-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 eb-modal-backdrop backdrop-blur-sm" data-tx-close></div>
      <div class="eb-modal eb-modal--confirm relative z-10 w-full max-w-lg">
        <div class="eb-modal-header">
          <h3 class="eb-modal-title">Registration</h3>
          <button type="button" class="eb-modal-close" data-tx-close aria-label="Close">✕</button>
        </div>
        <div class="eb-modal-body space-y-4 text-sm">
          <input type="hidden" id="tx-reg-id" value="" />
          <label class="block"><span class="eb-field-label !mb-0">Country</span><input id="tx-reg-country" placeholder="CA" class="eb-input mt-2 w-full uppercase" /></label>
          <label class="block"><span class="eb-field-label !mb-0">Region</span><input id="tx-reg-region" placeholder="ON" class="eb-input mt-2 w-full uppercase" /></label>
          <label class="block"><span class="eb-field-label !mb-0">Registration #</span><input id="tx-reg-number" class="eb-input mt-2 w-full" /></label>
          <label class="block"><span class="eb-field-label !mb-0">Legal name (optional)</span><input id="tx-reg-legal" class="eb-input mt-2 w-full" /></label>
        </div>
        <div class="eb-modal-footer !justify-end !gap-3">
          <button type="button" class="eb-btn eb-btn-outline eb-btn-sm" data-tx-close>Cancel</button>
          <button type="button" id="tx-reg-save" class="eb-btn eb-btn-primary eb-btn-sm">Save</button>
        </div>
      </div>
    </div>

    <div id="tx-preview-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 eb-modal-backdrop backdrop-blur-sm" data-tx-prev-close></div>
      <div class="eb-modal relative z-10 w-full max-w-3xl">
        <div class="eb-modal-header">
          <h3 class="eb-modal-title">Invoice preview</h3>
          <button type="button" class="eb-modal-close" data-tx-prev-close aria-label="Close">✕</button>
        </div>
        <div class="eb-modal-body text-sm text-[var(--eb-text-secondary)]">
          <div id="tx-preview-body" class="prose prose-invert max-w-none">
            <p>This is a sample invoice preview using your current settings. Logo/footer and prefix are applied for demonstration.</p>
          </div>
        </div>
        <div class="eb-modal-footer !justify-end">
          <button type="button" class="eb-btn eb-btn-outline eb-btn-sm" data-tx-prev-close>Close</button>
        </div>
      </div>
    </div>
          </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='settings-tax'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}

<script src="modules/addons/eazybackup/assets/js/settings-tax.js"></script>
