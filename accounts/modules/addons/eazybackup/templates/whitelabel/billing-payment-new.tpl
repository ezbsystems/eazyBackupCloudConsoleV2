{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhActions}
  <a href="{$modulelink}&a=ph-billing-payments" class="eb-btn eb-btn-secondary eb-btn-sm">Back to Payments</a>
{/capture}

{capture assign=ebPhContent}
  <div class="space-y-6" x-data="{ paying: false }">
    <form id="new-payment-form" class="eb-subpanel space-y-6" @submit.prevent="">
      <input id="np-token" type="hidden" value="{$token|escape}" />
      <div class="space-y-2" x-data='{
        open: false,
        tenantSearch: "",
        selectedTenantPublicId: "",
        tenants: {$tenants|json_encode|escape:"html"},
        filteredTenants() {
          const query = String(this.tenantSearch || "").trim().toLowerCase();
          if (!query) return this.tenants;
          return this.tenants.filter((tenant) => {
            const name = String(tenant.name || "").toLowerCase();
            const email = String(tenant.contact_email || "").toLowerCase();
            return name.includes(query) || email.includes(query);
          });
        },
        selectedTenant() {
          return this.tenants.find((tenant) => String(tenant.public_id) === String(this.selectedTenantPublicId)) || null;
        },
        selectTenant(tenant) {
          this.selectedTenantPublicId = String(tenant.public_id || "");
          this.tenantSearch = "";
          this.open = false;
        },
        clearTenant() {
          this.selectedTenantPublicId = "";
          this.tenantSearch = "";
        }
      }'>
        <label class="eb-field-label">Client</label>
        <p class="eb-field-help">Choose a tenant with a Stripe customer profile.</p>
        <input id="np-tenant" type="hidden" x-model="selectedTenantPublicId" />
        <div class="relative mt-1" @keydown.escape.window="open = false">
          <button type="button"
                  @click="open = !open; if (open) { $nextTick(() => $refs.tenantSearchInput.focus()); }"
                  class="flex w-full items-start justify-between rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] px-3 py-3 pr-20 text-left text-sm text-[var(--eb-text-primary)] transition focus:outline-none focus-visible:border-[var(--eb-border-emphasis)] focus-visible:ring-2 focus-visible:ring-[var(--eb-ring)]">
            <div class="min-w-0 pr-3">
              <template x-if="selectedTenant()">
                <div class="truncate whitespace-nowrap font-medium text-[var(--eb-text-primary)]" x-text="selectedTenant().name"></div>
              </template>
              <template x-if="!selectedTenant()">
                <div class="truncate whitespace-nowrap font-medium text-[var(--eb-text-muted)]">Select a client</div>
              </template>
            </div>
            <span class="text-[var(--eb-text-muted)]" x-text="open ? '▴' : '▾'"></span>
          </button>
          <button type="button"
                  x-show="selectedTenantPublicId"
                  @click.stop="clearTenant()"
                  class="eb-btn eb-btn-ghost eb-btn-xs absolute right-9 top-3"
                  style="display: none;">Clear</button>

          <div x-show="open"
               x-cloak
               @click.outside="open = false"
               class="absolute z-20 mt-2 w-full rounded-2xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] p-3 shadow-xl">
            <div class="flex items-center gap-3 rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-card)] px-3 py-2.5">
              <svg class="h-4 w-4 text-[var(--eb-text-muted)]" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M14.167 14.166L17.5 17.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="8.75" cy="8.75" r="5.625" stroke="currentColor" stroke-width="1.5"/>
              </svg>
              <input x-ref="tenantSearchInput"
                     type="text"
                     x-model.debounce.150ms="tenantSearch"
                     placeholder="Start typing a tenant name or email"
                     class="w-full border-0 bg-transparent text-sm text-[var(--eb-text-primary)] placeholder:text-[var(--eb-text-muted)] focus:outline-none">
            </div>

            <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
              <template x-for="tenant in filteredTenants()" :key="'standalone-payment-tenant-' + tenant.public_id">
                <button type="button"
                        @click="selectTenant(tenant)"
                        class="flex w-full items-start justify-between gap-4 rounded-2xl border px-4 py-3 text-left transition"
                        :class="selectedTenantPublicId === String(tenant.public_id) ? 'border-[var(--eb-primary-border)] bg-[var(--eb-primary-soft)]' : 'border-[var(--eb-border-subtle)] bg-[var(--eb-bg-card)] hover:border-[var(--eb-border-default)] hover:bg-[var(--eb-bg-hover)]'">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-[var(--eb-text-primary)]" x-text="tenant.name"></div>
                    <div class="mt-1 truncate text-xs text-[var(--eb-text-muted)]" x-text="tenant.contact_email || 'No contact email on file'"></div>
                  </div>
                  <span class="eb-badge eb-badge--success shrink-0 !text-[10px] !uppercase !tracking-widest"
                        x-show="selectedTenantPublicId === String(tenant.public_id)"
                        style="display: none;">Selected</span>
                </button>
              </template>
            </div>

            <template x-if="filteredTenants().length === 0">
              <div class="mt-3 rounded-2xl border border-dashed border-[var(--eb-border-default)] px-4 py-5 text-sm text-[var(--eb-text-muted)]">No tenants match your search.</div>
            </template>
          </div>
        </div>
      </div>

      <div class="space-y-3 border-t border-[var(--eb-border-subtle)] pt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="eb-app-card-title !text-sm">Amount &amp; Fees</h2>
          <span class="eb-field-help">Application fee will be created on the connected account.</span>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div>
            <label class="eb-field-label !text-xs">Amount</label>
            <input id="np-amount" type="hidden" value="0" />
            <div class="mt-1 flex items-center overflow-hidden rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] px-2"
                 x-data="ebPriceStepper(0.01)"
                 x-init="value = Number(document.getElementById('np-amount').value||0)"
                 x-effect="document.getElementById('np-amount').value = Number(value||0)">
              <button type="button" class="flex w-8 shrink-0 items-center justify-center py-2 text-[var(--eb-text-secondary)]" :class="hovered==='dec' ? 'bg-[var(--eb-bg-hover)]' : ''" @mouseenter="hovered='dec'" @mouseleave="hovered=''" @click.stop="dec">−</button>
              <span class="px-1 text-[var(--eb-text-muted)]">$</span>
              <input x-ref="input" x-model.number="value" type="number" step="0.01" class="eb-no-spinner min-w-0 flex-1 border-0 bg-transparent py-2 text-center text-[var(--eb-text-primary)] focus:outline-none focus:ring-0" />
              <button type="button" class="flex w-8 shrink-0 items-center justify-center py-2 text-[var(--eb-text-secondary)]" :class="hovered==='inc' ? 'bg-[var(--eb-bg-hover)]' : ''" @mouseenter="hovered='inc'" @mouseleave="hovered=''" @click.stop="inc">+</button>
            </div>
          </div>
          <div x-data="{ open:false, opts:['USD','CAD','EUR'], value:'USD' }" x-init="document.getElementById('np-currency').value=value">
            <label class="eb-field-label !text-xs">Currency</label>
            <input id="np-currency" type="hidden" value="USD" />
            <div class="relative mt-1">
              <button type="button" @click="open=!open" class="flex w-full items-center justify-between rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] px-3 py-2 text-left text-sm text-[var(--eb-text-primary)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--eb-ring)]">
                <span x-text="value"></span>
                <span class="text-[var(--eb-text-muted)]">▾</span>
              </button>
              <div x-show="open" @click.outside="open=false" class="eb-dropdown-menu absolute z-10 mt-1 w-full overflow-hidden !min-w-0 p-1" style="display: none;">
                <template x-for="opt in opts" :key="opt">
                  <button type="button" class="eb-menu-item w-full justify-start !rounded-[var(--eb-radius-md)]"
                          @click="value=opt; document.getElementById('np-currency').value=opt; open=false" x-text="opt"></button>
                </template>
              </div>
            </div>
          </div>
          <div>
            <label class="eb-field-label !text-xs">Application fee</label>
            <input id="np-fee" type="hidden" value="0" />
            <div class="mt-1 flex items-center overflow-hidden rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] px-2"
                 x-data="ebPriceStepper(0.01)"
                 x-init="value = Number(document.getElementById('np-fee').value||0)"
                 x-effect="document.getElementById('np-fee').value = Number(value||0)">
              <button type="button" class="flex w-8 shrink-0 items-center justify-center py-2 text-[var(--eb-text-secondary)]" :class="hovered==='dec' ? 'bg-[var(--eb-bg-hover)]' : ''" @mouseenter="hovered='dec'" @mouseleave="hovered=''" @click.stop="dec">−</button>
              <span class="px-1 text-[var(--eb-text-muted)]">$</span>
              <input x-ref="input" x-model.number="value" type="number" step="0.01" class="eb-no-spinner min-w-0 flex-1 border-0 bg-transparent py-2 text-center text-[var(--eb-text-primary)] focus:outline-none focus:ring-0" />
              <button type="button" class="flex w-8 shrink-0 items-center justify-center py-2 text-[var(--eb-text-secondary)]" :class="hovered==='inc' ? 'bg-[var(--eb-bg-hover)]' : ''" @mouseenter="hovered='inc'" @mouseleave="hovered=''" @click.stop="inc">+</button>
            </div>
            <p class="eb-field-help mt-1 !text-[11px]">Optional fee retained by your platform.</p>
          </div>
        </div>
      </div>

      <div class="space-y-2 border-t border-[var(--eb-border-subtle)] pt-4">
        <label class="eb-field-label">Card</label>
        <p class="eb-field-help">Enter card details. Stripe will create a Payment Intent.</p>
        <div id="card-element" class="rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] px-3 py-3"></div>
      </div>

      <div class="mt-2 flex flex-wrap items-center justify-between gap-3 border-t border-[var(--eb-border-subtle)] pt-4">
        <div class="eb-type-caption max-w-xl">
          <span>Charges are processed via Stripe on the connected account.</span>
        </div>
        <div class="flex gap-2">
          <a href="{$modulelink}&a=ph-billing-payments" class="eb-btn eb-btn-secondary eb-btn-sm">Cancel</a>
          <button id="np-submit" type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="paying=true">Create and Pay</button>
        </div>
      </div>
    </form>
  </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='billing-payments'
  ebPhTitle='New One-time Payment'
  ebPhDescription='Charge a card for setup fees, project work, or ad-hoc adjustments.'
  ebPhActions=$ebPhActions
  ebPhContent=$ebPhContent
}

  <script src="https://js.stripe.com/v3"></script>
  <script>
  // Stepper helpers (scoped to this page)
  window.ebStepper = window.ebStepper || function(opts){
    return {
      value: 0,
      min: isFinite(opts && opts.min) ? Number(opts.min) : -Infinity,
      max: isFinite(opts && opts.max) ? Number(opts.max) : Infinity,
      step: isFinite(opts && opts.step) && Number(opts.step) > 0 ? Number(opts.step) : 1,
      hovered: '',
      dec(){ var v = parseFloat(this.value); if (!isFinite(v)) v = 0; v = v - this.step; if (isFinite(this.min) && v < this.min) v = this.min; this.value = Number(v.toFixed(2)); if (this.$refs && this.$refs.input) { this.$refs.input.value = this.value; this.$refs.input.dispatchEvent(new Event('input', { bubbles: true })); } },
      inc(){ var v = parseFloat(this.value); if (!isFinite(v)) v = 0; v = v + this.step; if (isFinite(this.max) && v > this.max) v = this.max; this.value = Number(v.toFixed(2)); if (this.$refs && this.$refs.input) { this.$refs.input.value = this.value; this.$refs.input.dispatchEvent(new Event('input', { bubbles: true })); } }
    };
  };
  window.ebPriceStepper = window.ebPriceStepper || function(step){ return window.ebStepper({ min:0, step: (isFinite(step)?Number(step):0.01)||0.01 }); };

  document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('np-submit');
    const form = document.getElementById('new-payment-form');
    let stripe = null;
    let elements = null;
    let card = null;

    function mountElements(publishable) {
      if (!stripe) { stripe = Stripe(publishable); }
      if (!elements) { elements = stripe.elements(); }
      if (!card) {
        card = elements.create('card');
        card.mount('#card-element');
      }
    }

    async function createPaymentIntent(payload) {
      const resp = await fetch('{$modulelink}&a=ph-billing-create-payment', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload).toString()
      });
      return await resp.json();
    }

    btn.addEventListener('click', async function(){
      btn.disabled = true;
      try {
        const tenant_id = document.getElementById('np-tenant').value;
        const amount = document.getElementById('np-amount').value;
        const currency = document.getElementById('np-currency').value;
        const application_fee = document.getElementById('np-fee').value;
        const token = document.getElementById('np-token').value;
        const data = await createPaymentIntent({ tenant_id, amount, currency, application_fee, token });
        if (!data || data.status !== 'success' || !data.client_secret || !data.publishable) {
          alert(data && data.message ? data.message : 'Failed to create payment');
          btn.disabled = false; return;
        }
        mountElements(data.publishable);
        const res = await stripe.confirmCardPayment(data.client_secret, {
          payment_method: {
            card: card
          }
        });
        if (res.error) {
          alert(res.error.message || 'Payment failed');
          btn.disabled = false; return;
        }
        window.location.href = '{$modulelink}&a=ph-billing-payments';
      } catch (e) {
        console.error(e);
        alert('Error: ' + e.message);
        btn.disabled = false;
      }
    });
  });
  </script>

