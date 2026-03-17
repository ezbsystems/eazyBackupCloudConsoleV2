<div x-show="paymentModalOpen" @keydown.escape.window="closePaymentModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-6" style="display: none;">
  <div class="absolute inset-0 bg-slate-950/75 backdrop-blur-sm" @click="closePaymentModal()"></div>
  <div class="relative my-auto w-full max-w-6xl max-h-[85vh] flex flex-col overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950 shadow-[0_24px_80px_rgba(0,0,0,0.65)]"
       x-data='ebBillingPaymentModal({ tenants: {$tenants|json_encode|escape:"html"}, modulelink: "{$modulelink|escape:'javascript'}", token: "{$token|escape:'javascript'}" })'
       x-init="init()"
       @click.stop>
    <div class="flex items-start justify-between border-b border-slate-800/80 px-6 py-5">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-sky-300/80">One-time payment</p>
        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-white">Create a manual charge</h2>
        <p class="mt-2 max-w-2xl text-sm text-slate-400">Charge setup work, migration fees, or one-off services without leaving the Payments page.</p>
      </div>
      <button type="button" @click="closePaymentModal()" class="rounded-xl border border-slate-700/70 px-3 py-2 text-sm text-slate-300 transition hover:border-slate-600 hover:bg-slate-900/80 hover:text-white">Close</button>
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto">
    <div class="grid gap-0 lg:grid-cols-[minmax(0,1.7fr)_minmax(320px,0.9fr)]">
      <div class="space-y-6 px-6 py-6">
        <template x-if="errorMessage">
          <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100" x-text="errorMessage"></div>
        </template>

        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/50 p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-sm font-semibold text-white">Customer</h3>
              <p class="mt-1 text-xs text-slate-400">Choose the tenant that should own this payment and saved card list.</p>
            </div>
            <template x-if="selectedTenant">
              <button type="button" @click="clearTenant()" class="text-xs font-medium text-slate-400 transition hover:text-white">Clear</button>
            </template>
          </div>

          <div class="mt-4 space-y-3">
            <label class="block text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Search tenants by name</label>
            <div class="rounded-2xl border border-slate-700/80 bg-slate-950/70 p-3">
              <div class="flex items-center gap-3 rounded-xl border border-slate-700/70 bg-slate-900/70 px-3 py-2.5">
                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                  <path d="M14.167 14.166L17.5 17.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  <circle cx="8.75" cy="8.75" r="5.625" stroke="currentColor" stroke-width="1.5"/>
                </svg>
                <input x-ref="tenantSearchInput" type="text" x-model.debounce.150ms="tenantSearch" placeholder="Start typing a tenant name or email" class="w-full bg-transparent text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none">
              </div>

              <template x-if="selectedTenant">
                <div class="mt-3 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3">
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <div class="text-sm font-semibold text-white" x-text="selectedTenant.name"></div>
                      <div class="mt-1 text-xs text-slate-300" x-text="selectedTenant.contact_email || 'No contact email on file'"></div>
                    </div>
                    <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-200">Selected</span>
                  </div>
                </div>
              </template>

              <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
                <template x-for="tenant in filteredTenants()" :key="'payment-tenant-' + tenant.public_id">
                  <button type="button" @click="selectTenant(tenant)" class="flex w-full items-start justify-between gap-4 rounded-2xl border px-4 py-3 text-left transition"
                          :class="selectedTenant && String(selectedTenant.public_id) === String(tenant.public_id) ? 'border-sky-400/40 bg-sky-500/10' : 'border-slate-800 bg-slate-950/80 hover:border-slate-700 hover:bg-slate-900/80'">
                    <div>
                      <div class="text-sm font-semibold text-white" x-text="tenant.name"></div>
                      <div class="mt-1 text-xs text-slate-400" x-text="tenant.contact_email || 'No contact email on file'"></div>
                    </div>
                    <div class="text-right">
                      <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500" x-text="tenant.stripe_customer_id ? 'Customer ready' : 'Customer on demand'"></div>
                    </div>
                  </button>
                </template>
              </div>

              <template x-if="filteredTenants().length === 0">
                <div class="mt-3 rounded-2xl border border-dashed border-slate-700/70 px-4 py-5 text-sm text-slate-400">No tenants match your search.</div>
              </template>
            </div>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/50 p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-sm font-semibold text-white">Charge details</h3>
              <p class="mt-1 text-xs text-slate-400">Set the amount, currency, and optional platform fee.</p>
            </div>
            <div class="rounded-full border border-slate-700/70 bg-slate-950/70 px-3 py-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">Processed on Stripe</div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-3">
            <label class="block">
              <span class="mb-2 block text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Amount</span>
              <div class="flex items-center rounded-xl border border-slate-700/80 bg-slate-950/80 px-3">
                <span class="text-sm text-slate-400">$</span>
                <input type="number" min="0" step="0.01" x-model="amount" placeholder="0.00" class="w-full bg-transparent px-2 py-3 text-sm text-white placeholder:text-slate-500 focus:outline-none">
              </div>
            </label>

            <label class="block">
              <span class="mb-2 block text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Currency</span>
              <select x-model="currency" class="w-full rounded-xl border border-slate-700/80 bg-slate-950/80 px-3 py-3 text-sm text-white focus:outline-none">
                <option value="USD">USD</option>
                <option value="CAD">CAD</option>
                <option value="EUR">EUR</option>
              </select>
            </label>

            <label class="block">
              <span class="mb-2 block text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Application fee</span>
              <div class="flex items-center rounded-xl border border-slate-700/80 bg-slate-950/80 px-3">
                <span class="text-sm text-slate-400">$</span>
                <input type="number" min="0" step="0.01" x-model="applicationFee" placeholder="0.00" class="w-full bg-transparent px-2 py-3 text-sm text-white placeholder:text-slate-500 focus:outline-none">
              </div>
            </label>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/50 p-5">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-sm font-semibold text-white">Payment method</h3>
              <p class="mt-1 text-xs text-slate-400">Pick a saved card for the selected tenant or enter a new card inside the modal.</p>
            </div>
            <template x-if="loadingMethods">
              <span class="text-xs text-slate-400">Loading saved cards...</span>
            </template>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" @click="setPaymentMode('saved')" class="rounded-full px-4 py-2 text-sm font-medium transition"
                    :class="paymentMode === 'saved' ? 'bg-sky-500 text-slate-950' : 'border border-slate-700/80 bg-slate-950/80 text-slate-300 hover:border-slate-600 hover:text-white'"
                    :disabled="!paymentMethods.length">
              Saved payment methods
            </button>
            <button type="button" @click="setPaymentMode('new')" class="rounded-full px-4 py-2 text-sm font-medium transition"
                    :class="paymentMode === 'new' ? 'bg-sky-500 text-slate-950' : 'border border-slate-700/80 bg-slate-950/80 text-slate-300 hover:border-slate-600 hover:text-white'">
              Use a new card
            </button>
          </div>

          <div class="mt-4 space-y-3" x-show="paymentMode === 'saved'" style="display: none;">
            <template x-if="paymentMethods.length === 0 && !loadingMethods">
              <div class="rounded-2xl border border-dashed border-slate-700/70 px-4 py-5 text-sm text-slate-400">No saved cards are available for this tenant yet. Switch to a new card to continue.</div>
            </template>

            <template x-for="method in paymentMethods" :key="method.id">
              <label class="flex cursor-pointer items-center gap-4 rounded-2xl border px-4 py-3 transition"
                     :class="selectedPaymentMethodId === method.id ? 'border-sky-400/40 bg-sky-500/10' : 'border-slate-800 bg-slate-950/80 hover:border-slate-700 hover:bg-slate-900/80'">
                <input type="radio" name="saved-payment-method" class="h-4 w-4 border-slate-600 bg-slate-900 text-sky-500 focus:ring-sky-500" :value="method.id" x-model="selectedPaymentMethodId">
                <div class="flex-1">
                  <div class="text-sm font-semibold capitalize text-white">
                    <span x-text="method.brand"></span>
                    <span class="text-slate-400">ending in</span>
                    <span x-text="method.last4"></span>
                  </div>
                  <div class="mt-1 text-xs text-slate-400">
                    Expires <span x-text="method.exp_month"></span>/<span x-text="method.exp_year"></span>
                  </div>
                </div>
                <template x-if="method.is_default">
                  <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-200">Default</span>
                </template>
              </label>
            </template>
          </div>

          <div class="mt-4 space-y-3" x-show="paymentMode === 'new'" style="display: none;">
            <div class="rounded-2xl border border-slate-700/80 bg-slate-950/80 p-4">
              <div class="mb-3 text-sm font-medium text-white">Use a new card</div>
              <div id="eb-billing-payment-card-element" class="rounded-xl border border-slate-700/80 bg-slate-900/80 px-3 py-3"></div>
              <p class="mt-3 text-xs text-slate-400">The new card is collected securely with Stripe Elements during payment confirmation.</p>
            </div>
          </div>
        </section>
      </div>

      <aside class="border-t border-slate-800/80 bg-slate-900/40 px-6 py-6 lg:border-l lg:border-t-0">
        <div class="rounded-2xl border border-slate-800/80 bg-slate-950/70 p-5">
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Payment summary</p>
          <dl class="mt-4 space-y-4">
            <div>
              <dt class="text-xs uppercase tracking-[0.18em] text-slate-500">Tenant</dt>
              <dd class="mt-1 text-sm font-medium text-white" x-text="selectedTenant ? selectedTenant.name : 'No tenant selected'"></dd>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-[0.18em] text-slate-500">Charge amount</dt>
              <dd class="mt-1 text-2xl font-semibold text-white" x-text="formattedMoney(amount)"></dd>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <dt class="text-xs uppercase tracking-[0.18em] text-slate-500">Currency</dt>
                <dd class="mt-1 text-sm text-slate-200" x-text="currency"></dd>
              </div>
              <div>
                <dt class="text-xs uppercase tracking-[0.18em] text-slate-500">Platform fee</dt>
                <dd class="mt-1 text-sm text-slate-200" x-text="formattedMoney(applicationFee)"></dd>
              </div>
            </div>
            <div>
              <dt class="text-xs uppercase tracking-[0.18em] text-slate-500">Payment path</dt>
              <dd class="mt-1 text-sm text-slate-200" x-text="paymentMode === 'saved' ? savedPaymentLabel() : 'New card via Stripe Elements'"></dd>
            </div>
          </dl>
        </div>

        <div class="mt-5 rounded-2xl border border-slate-800/80 bg-slate-950/70 p-5 text-sm text-slate-400">
          Charges are created on the MSP's connected Stripe account. The modal keeps payment creation and payment history in the same workspace for faster billing operations.
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row lg:flex-col">
          <button type="button" @click="submitPayment()" :disabled="submitting || !selectedTenant" class="inline-flex items-center justify-center rounded-2xl bg-[rgb(var(--accent))] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[rgb(var(--accent))]/90 disabled:cursor-not-allowed disabled:opacity-60">
            <span x-text="submitting ? 'Processing payment...' : 'Create and Pay'"></span>
          </button>
          <button type="button" @click="closePaymentModal()" class="inline-flex items-center justify-center rounded-2xl border border-slate-700/80 px-4 py-3 text-sm font-medium text-slate-300 transition hover:border-slate-600 hover:bg-slate-900/80 hover:text-white">Cancel</button>
        </div>
      </aside>
    </div>
    </div>
  </div>
</div>

<script src="https://js.stripe.com/v3"></script>
<script>
window.ebBillingPaymentModal = window.ebBillingPaymentModal || function(config) {
  return {
    modulelink: config.modulelink || '',
    token: config.token || '',
    tenants: Array.isArray(config.tenants) ? config.tenants : [],
    tenantSearch: '',
    selectedTenant: null,
    amount: '0.00',
    currency: 'USD',
    applicationFee: '0.00',
    paymentMode: 'new',
    paymentMethods: [],
    selectedPaymentMethodId: '',
    loadingMethods: false,
    errorMessage: '',
    submitting: false,
    stripe: null,
    stripePublishable: '',
    elements: null,
    card: null,
    init() {
      var self = this;
      window.addEventListener('eb-payment-modal-opened', function() {
        self.resetForm();
        self.focusTenantSearch();
      });
    },
    resetForm() {
      this.destroyCardElement();
      this.tenantSearch = '';
      this.selectedTenant = null;
      this.amount = '0.00';
      this.currency = 'USD';
      this.applicationFee = '0.00';
      this.paymentMode = 'new';
      this.paymentMethods = [];
      this.selectedPaymentMethodId = '';
      this.loadingMethods = false;
      this.errorMessage = '';
      this.submitting = false;
    },
    focusTenantSearch() {
      var self = this;
      this.$nextTick(function() {
        if (self.$refs && self.$refs.tenantSearchInput) {
          self.$refs.tenantSearchInput.focus();
        }
      });
    },
    filteredTenants() {
      var query = String(this.tenantSearch || '').trim().toLowerCase();
      if (!query) {
        return this.tenants.slice(0, 8);
      }
      return this.tenants.filter(function(tenant) {
        var haystack = [tenant.name || '', tenant.contact_email || ''].join(' ').toLowerCase();
        return haystack.indexOf(query) !== -1;
      }).slice(0, 12);
    },
    selectTenant(tenant) {
      this.selectedTenant = tenant;
      this.errorMessage = '';
      this.loadPaymentMethods();
    },
    clearTenant() {
      this.selectedTenant = null;
      this.paymentMethods = [];
      this.selectedPaymentMethodId = '';
      this.paymentMode = 'new';
      this.errorMessage = '';
      this.focusTenantSearch();
    },
    setPaymentMode(mode) {
      this.paymentMode = mode;
      if (mode === 'new') {
        this.mountCardElement();
      } else {
        this.destroyCardElement();
      }
    },
    formattedMoney(value) {
      var numeric = parseFloat(value || '0');
      if (!isFinite(numeric)) {
        numeric = 0;
      }
      return '$' + numeric.toFixed(2);
    },
    savedPaymentLabel() {
      var match = this.paymentMethods.find(function(method) {
        return method.id === this.selectedPaymentMethodId;
      }, this);
      if (!match) {
        return 'Saved card';
      }
      return (match.brand || 'Card') + ' ending in ' + (match.last4 || '----');
    },
    async loadPaymentMethods() {
      if (!this.selectedTenant || !this.selectedTenant.public_id) {
        this.paymentMethods = [];
        this.selectedPaymentMethodId = '';
        this.paymentMode = 'new';
        return;
      }

      this.loadingMethods = true;
      this.paymentMethods = [];
      this.selectedPaymentMethodId = '';
      this.errorMessage = '';

      try {
        var response = await fetch(this.modulelink + '&a=ph-billing-payment-methods&tenant_id=' + encodeURIComponent(this.selectedTenant.public_id), {
          credentials: 'same-origin'
        });
        var data = await response.json();
        if (!data || data.status !== 'success') {
          throw new Error((data && data.message) ? data.message : 'Unable to load saved payment methods.');
        }
        this.paymentMethods = Array.isArray(data.methods) ? data.methods : [];
        if (this.paymentMethods.length > 0) {
          var defaultMethod = this.paymentMethods.find(function(method) { return !!method.is_default; }) || this.paymentMethods[0];
          this.selectedPaymentMethodId = defaultMethod.id || '';
          this.paymentMode = 'saved';
          this.destroyCardElement();
        } else {
          this.paymentMode = 'new';
          this.$nextTick(() => this.mountCardElement());
        }
      } catch (error) {
        this.paymentMode = 'new';
        this.errorMessage = error && error.message ? error.message : 'Unable to load saved payment methods.';
      } finally {
        this.loadingMethods = false;
      }
    },
    ensureStripe(publishable) {
      if (!this.stripe || this.stripePublishable !== publishable) {
        this.stripe = Stripe(publishable);
        this.stripePublishable = publishable;
        this.elements = this.stripe.elements();
        this.card = null;
      }
    },
    destroyCardElement() {
      if (this.card && typeof this.card.unmount === 'function') {
        this.card.unmount();
      }
      this.card = null;
    },
    mountCardElement() {
      if (!this.elements || this.card) {
        return;
      }
      var mountTarget = document.getElementById('eb-billing-payment-card-element');
      if (!mountTarget) {
        return;
      }
      this.card = this.elements.create('card');
      this.card.mount('#eb-billing-payment-card-element');
    },
    async submitPayment() {
      this.errorMessage = '';
      if (!this.selectedTenant || !this.selectedTenant.public_id) {
        this.errorMessage = 'Select a tenant before creating a payment.';
        return;
      }

      var amountNumber = parseFloat(this.amount || '0');
      if (!isFinite(amountNumber) || amountNumber <= 0) {
        this.errorMessage = 'Enter an amount greater than zero.';
        return;
      }

      if (this.paymentMode === 'saved' && !this.selectedPaymentMethodId) {
        this.errorMessage = 'Choose a saved payment method or switch to a new card.';
        return;
      }

      this.submitting = true;

      try {
        var payload = {
          tenant_id: String(this.selectedTenant.public_id),
          amount: String(this.amount),
          currency: String(this.currency),
          application_fee: String(this.applicationFee || '0')
        };
        if (this.paymentMode === 'saved' && this.selectedPaymentMethodId) {
          payload.payment_method_id = this.selectedPaymentMethodId;
        }
        payload.token = this.token;

        var response = await fetch(this.modulelink + '&a=ph-billing-create-payment', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          credentials: 'same-origin',
          body: new URLSearchParams(payload).toString()
        });
        var data = await response.json();
        if (!data || data.status !== 'success' || !data.client_secret || !data.publishable) {
          throw new Error((data && data.message) ? data.message : 'Unable to create the payment.');
        }

        this.ensureStripe(data.publishable);

        var result;
        if (this.paymentMode === 'saved' && this.selectedPaymentMethodId) {
          result = await this.stripe.confirmCardPayment(data.client_secret, {
            payment_method: this.selectedPaymentMethodId
          });
        } else {
          this.mountCardElement();
          result = await this.stripe.confirmCardPayment(data.client_secret, {
            payment_method: {
              card: this.card
            }
          });
        }

        if (result && result.error) {
          throw new Error(result.error.message || 'Payment confirmation failed.');
        }

        window.location.href = this.modulelink + '&a=ph-billing-payments';
      } catch (error) {
        this.errorMessage = error && error.message ? error.message : 'Unable to complete the payment.';
      } finally {
        this.submitting = false;
      }
    }
  };
};
</script>
