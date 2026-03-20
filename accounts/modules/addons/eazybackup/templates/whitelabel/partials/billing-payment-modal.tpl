<div x-show="paymentModalOpen" @keydown.escape.window="closePaymentModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-6" style="display: none;">
  <div class="absolute inset-0 eb-modal-backdrop backdrop-blur-sm" @click="closePaymentModal()"></div>
  <div class="eb-modal relative my-auto flex max-h-[85vh] w-full max-w-6xl flex-col overflow-hidden"
       x-data='ebBillingPaymentModal({ tenants: {$tenants|json_encode|escape:"html"}, modulelink: "{$modulelink|escape:'javascript'}", token: "{$token|escape:'javascript'}" })'
       x-init="init()"
       @click.stop>
    <div class="eb-modal-header">
      <div>
        <p class="eb-stat-label">One-Time Payment</p>
        <h2 class="eb-modal-title mt-2 !text-2xl">Create a Manual Charge</h2>
        <p class="eb-modal-subtitle max-w-2xl">Charge setup work, migration fees, or one-off services without leaving the Payments page.</p>
      </div>
      <button type="button" @click="closePaymentModal()" class="eb-modal-close">✕</button>
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto">
    <div class="grid gap-0 lg:grid-cols-[minmax(0,1.7fr)_minmax(320px,0.9fr)]">
      <div class="space-y-6 px-6 py-6">
        <template x-if="errorMessage">
          <div class="eb-alert eb-alert--danger" x-text="errorMessage"></div>
        </template>

        <section class="eb-subpanel">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-sm font-semibold text-[var(--eb-text-primary)]">Customer</h3>
              <p class="eb-field-help mt-1">Choose the tenant that should own this payment and saved card list.</p>
            </div>
            <template x-if="selectedTenant">
              <button type="button" @click="clearTenant()" class="eb-type-caption font-semibold text-[var(--eb-text-muted)] transition hover:text-[var(--eb-text-primary)]">Clear</button>
            </template>
          </div>

          <div class="mt-4 space-y-3">
            <label class="eb-field-label">Search Tenants by Name</label>
            <div class="eb-card-raised p-3">
              <div class="flex items-center gap-3 rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-card)] px-3 py-2.5">
                <svg class="h-4 w-4 text-[var(--eb-text-muted)]" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                  <path d="M14.167 14.166L17.5 17.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  <circle cx="8.75" cy="8.75" r="5.625" stroke="currentColor" stroke-width="1.5"/>
                </svg>
                <input x-ref="tenantSearchInput" type="text" x-model.debounce.150ms="tenantSearch" placeholder="Start typing a tenant name or email" class="eb-input border-0 bg-transparent px-0 py-0 focus:!bg-transparent">
              </div>

              <template x-if="selectedTenant">
                <div class="mt-3 rounded-2xl border border-[var(--eb-success-border)] bg-[var(--eb-success-soft)] px-4 py-3">
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <div class="text-sm font-semibold text-[var(--eb-text-primary)]" x-text="selectedTenant.name"></div>
                      <div class="mt-1 text-xs text-[var(--eb-text-secondary)]" x-text="selectedTenant.contact_email || 'No contact email on file'"></div>
                    </div>
                    <span class="eb-badge eb-badge--success !text-[10px] !uppercase !tracking-widest">Selected</span>
                  </div>
                </div>
              </template>

              <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
                <template x-for="tenant in filteredTenants()" :key="'payment-tenant-' + tenant.public_id">
                  <button type="button" @click="selectTenant(tenant)" class="flex w-full items-start justify-between gap-4 rounded-2xl border px-4 py-3 text-left transition"
                          :class="selectedTenant && String(selectedTenant.public_id) === String(tenant.public_id) ? 'border-[var(--eb-primary-border)] bg-[var(--eb-primary-soft)]' : 'border-[var(--eb-border-subtle)] bg-[var(--eb-bg-card)] hover:border-[var(--eb-border-default)] hover:bg-[var(--eb-bg-hover)]'">
                    <div>
                      <div class="text-sm font-semibold text-[var(--eb-text-primary)]" x-text="tenant.name"></div>
                      <div class="mt-1 text-xs text-[var(--eb-text-muted)]" x-text="tenant.contact_email || 'No contact email on file'"></div>
                    </div>
                    <div class="text-right">
                      <div class="text-[11px] uppercase tracking-[0.18em] text-[var(--eb-text-muted)]" x-text="tenant.stripe_customer_id ? 'Customer ready' : 'Customer on demand'"></div>
                    </div>
                  </button>
                </template>
              </div>

              <template x-if="filteredTenants().length === 0">
                <div class="mt-3 rounded-2xl border border-dashed border-[var(--eb-border-default)] px-4 py-5 text-sm text-[var(--eb-text-muted)]">No tenants match your search.</div>
              </template>
            </div>
          </div>
        </section>

        <section class="eb-subpanel">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h3 class="text-sm font-semibold text-[var(--eb-text-primary)]">Charge details</h3>
              <p class="eb-field-help mt-1">Set the amount, currency, and optional platform fee.</p>
            </div>
            <div class="eb-badge eb-badge--info !text-[10px] !uppercase !tracking-widest">Processed on Stripe</div>
          </div>

          <div class="mt-4 grid gap-4 md:grid-cols-3">
            <label class="block">
              <span class="eb-field-label">Amount</span>
              <div class="flex items-center rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] px-3">
                <span class="text-sm text-[var(--eb-text-muted)]">$</span>
                <input type="number" min="0" step="0.01" x-model="amount" placeholder="0.00" class="eb-input border-0 bg-transparent px-2 py-3 focus:!bg-transparent">
              </div>
            </label>

            <label class="block">
              <span class="eb-field-label">Currency</span>
              <select x-model="currency" class="eb-select">
                <option value="USD">USD</option>
                <option value="CAD">CAD</option>
                <option value="EUR">EUR</option>
              </select>
            </label>

            <label class="block">
              <span class="eb-field-label">Application Fee</span>
              <div class="flex items-center rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] px-3">
                <span class="text-sm text-[var(--eb-text-muted)]">$</span>
                <input type="number" min="0" step="0.01" x-model="applicationFee" placeholder="0.00" class="eb-input border-0 bg-transparent px-2 py-3 focus:!bg-transparent">
              </div>
            </label>
          </div>
        </section>

        <section class="eb-subpanel">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h3 class="text-sm font-semibold text-[var(--eb-text-primary)]">Payment method</h3>
              <p class="eb-field-help mt-1">Pick a saved card for the selected tenant or enter a new card inside the modal.</p>
            </div>
            <template x-if="loadingMethods">
              <span class="eb-type-caption text-[var(--eb-text-muted)]">Loading saved cards...</span>
            </template>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" @click="setPaymentMode('saved')" class="eb-pill"
                    :class="paymentMode === 'saved' ? 'is-active' : ''"
                    :disabled="!paymentMethods.length">
              Saved payment methods
            </button>
            <button type="button" @click="setPaymentMode('new')" class="eb-pill"
                    :class="paymentMode === 'new' ? 'is-active' : ''">
              Use a new card
            </button>
          </div>

          <div class="mt-4 space-y-3" x-show="paymentMode === 'saved'" style="display: none;">
            <template x-if="paymentMethods.length === 0 && !loadingMethods">
              <div class="rounded-2xl border border-dashed border-[var(--eb-border-default)] px-4 py-5 text-sm text-[var(--eb-text-muted)]">No saved cards are available for this tenant yet. Switch to a new card to continue.</div>
            </template>

            <template x-for="method in paymentMethods" :key="method.id">
              <label class="flex cursor-pointer items-center gap-4 rounded-2xl border px-4 py-3 transition"
                     :class="selectedPaymentMethodId === method.id ? 'border-[var(--eb-primary-border)] bg-[var(--eb-primary-soft)]' : 'border-[var(--eb-border-subtle)] bg-[var(--eb-bg-card)] hover:border-[var(--eb-border-default)] hover:bg-[var(--eb-bg-hover)]'">
                <input type="radio" name="saved-payment-method" class="eb-radio-input" :value="method.id" x-model="selectedPaymentMethodId">
                <div class="flex-1">
                  <div class="text-sm font-semibold capitalize text-[var(--eb-text-primary)]">
                    <span x-text="method.brand"></span>
                    <span class="text-[var(--eb-text-muted)]"> ending in </span>
                    <span x-text="method.last4"></span>
                  </div>
                  <div class="mt-1 text-xs text-[var(--eb-text-muted)]">
                    Expires <span x-text="method.exp_month"></span>/<span x-text="method.exp_year"></span>
                  </div>
                </div>
                <template x-if="method.is_default">
                  <span class="eb-badge eb-badge--success !text-[10px] !uppercase !tracking-widest">Default</span>
                </template>
              </label>
            </template>
          </div>

          <div class="mt-4 space-y-3" x-show="paymentMode === 'new'" style="display: none;">
            <div class="rounded-2xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-card)] p-4">
              <div class="mb-3 text-sm font-medium text-[var(--eb-text-primary)]">Use a new card</div>
              <div id="eb-billing-payment-card-element" class="rounded-xl border border-[var(--eb-border-default)] bg-[var(--eb-bg-input)] px-3 py-3"></div>
              <p class="eb-field-help mt-3">The new card is collected securely with Stripe Elements during payment confirmation.</p>
            </div>
          </div>
        </section>
      </div>

      <aside class="border-t border-[var(--eb-border-subtle)] bg-[var(--eb-bg-surface)] px-6 py-6 lg:border-l lg:border-t-0">
        <div class="eb-card-raised">
          <p class="eb-stat-label !normal-case !tracking-[0.2em]">Payment summary</p>
          <dl class="mt-4 space-y-4">
            <div>
              <dt class="eb-type-caption !uppercase !tracking-[0.18em] text-[var(--eb-text-muted)]">Tenant</dt>
              <dd class="mt-1 text-sm font-medium text-[var(--eb-text-primary)]" x-text="selectedTenant ? selectedTenant.name : 'No tenant selected'"></dd>
            </div>
            <div>
              <dt class="eb-type-caption !uppercase !tracking-[0.18em] text-[var(--eb-text-muted)]">Charge amount</dt>
              <dd class="eb-type-stat mt-1 !text-2xl" x-text="formattedMoney(amount)"></dd>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <dt class="eb-type-caption !uppercase !tracking-[0.18em] text-[var(--eb-text-muted)]">Currency</dt>
                <dd class="mt-1 text-sm text-[var(--eb-text-secondary)]" x-text="currency"></dd>
              </div>
              <div>
                <dt class="eb-type-caption !uppercase !tracking-[0.18em] text-[var(--eb-text-muted)]">Platform fee</dt>
                <dd class="mt-1 text-sm text-[var(--eb-text-secondary)]" x-text="formattedMoney(applicationFee)"></dd>
              </div>
            </div>
            <div>
              <dt class="eb-type-caption !uppercase !tracking-[0.18em] text-[var(--eb-text-muted)]">Payment path</dt>
              <dd class="mt-1 text-sm text-[var(--eb-text-secondary)]" x-text="paymentMode === 'saved' ? savedPaymentLabel() : 'New card via Stripe Elements'"></dd>
            </div>
          </dl>
        </div>

        <div class="mt-5 eb-card-raised text-sm text-[var(--eb-text-muted)]">
          Charges are created on the MSP's connected Stripe account. The modal keeps payment creation and payment history in the same workspace for faster billing operations.
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row lg:flex-col">
          <button type="button" @click="submitPayment()" :disabled="submitting || !selectedTenant" class="eb-btn eb-btn-primary eb-btn-sm disabled:cursor-not-allowed disabled:opacity-60">
            <span x-text="submitting ? 'Processing payment...' : 'Create and Pay'"></span>
          </button>
          <button type="button" @click="closePaymentModal()" class="eb-btn eb-btn-secondary eb-btn-sm">Cancel</button>
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
