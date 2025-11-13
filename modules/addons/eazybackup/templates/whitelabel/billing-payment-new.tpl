<div class="p-6" x-data="{ paying: false }">
  <div class="space-y-6">
    <div>
      <h1 class="text-xl font-semibold text-slate-50 tracking-tight">New One-time Payment</h1>
      <p class="mt-1 text-sm text-slate-400">Charge a saved card for setup fees, project work, or ad‑hoc adjustments.</p>
    </div>

    <form id="new-payment-form" class="rounded-2xl bg-slate-900/80 border border-slate-800 shadow-[0_18px_20px_-24px_rgba(0,0,0,0.9)] px-6 py-5 space-y-6" @submit.prevent="">
      <div class="space-y-2">
        <label class="block text-sm font-medium text-slate-200">Customer</label>
        <p class="text-xs text-slate-400">Choose an existing client with a Stripe customer profile.</p>
        <select id="np-customer" class="w-full mt-1 rounded-xl bg-slate-950/70 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/80 focus:border-sky-500/80">
          <option value="">-- None --</option>
          {foreach from=$customers item=c}
            <option value="{$c.id}">{$c.name|escape}</option>
          {/foreach}
        </select>
      </div>

      <div class="space-y-3 border-t border-slate-800 pt-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-medium text-slate-200">Amount &amp; fees</h2>
          <span class="text-xs text-slate-500">Application fee will be created on the connected account.</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-300 mb-1">Amount</label>
            <input id="np-amount" type="hidden" value="0" />
            <div class="mt-1 flex items-center rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-950/70 border border-slate-700 px-2"
                 x-data="ebPriceStepper(0.01)"
                 x-init="value = Number(document.getElementById('np-amount').value||0)"
                 x-effect="document.getElementById('np-amount').value = Number(value||0)">
              <button type="button" class="shrink-0 w-8 flex items-center justify-center py-2 text-white/80" :class="hovered==='dec' ? 'bg-white/10' : ''" @mouseenter="hovered='dec'" @mouseleave="hovered=''" @click.stop="dec">−</button>
              <span class="px-1 text-white/70">$</span>
              <input x-ref="input" x-model.number="value" type="number" step="0.01" class="eb-no-spinner flex-1 min-w-0 text-center bg-transparent text-slate-100 focus:outline-none focus:ring-0 py-2" />
              <button type="button" class="shrink-0 w-8 flex items-center justify-center py-2 text-white/80" :class="hovered==='inc' ? 'bg-white/10' : ''" @mouseenter="hovered='inc'" @mouseleave="hovered=''" @click.stop="inc">+</button>
            </div>
          </div>
          <div x-data="{ open:false, opts:['USD','CAD','EUR'], value:'USD' }" x-init="document.getElementById('np-currency').value=value">
            <label class="block text-xs font-medium text-slate-300 mb-1">Currency</label>
            <input id="np-currency" type="hidden" value="USD" />
            <div class="relative mt-1">
              <button type="button" @click="open=!open" class="w-full text-left rounded-xl bg-slate-950/70 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/80 focus:border-sky-500/80 flex items-center justify-between">
                <span x-text="value"></span>
                <span class="text-slate-400">▾</span>
              </button>
              <div x-show="open" @click.outside="open=false" class="absolute z-10 mt-1 w-full rounded-xl bg-slate-900/90 border border-slate-700 shadow-xl p-1">
                <template x-for="opt in opts" :key="opt">
                  <button type="button" class="w-full text-left px-3 py-2 rounded-lg hover:bg-slate-800 text-slate-200"
                          @click="value=opt; document.getElementById('np-currency').value=opt; open=false" x-text="opt"></button>
                </template>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-300 mb-1">Application fee</label>
            <input id="np-fee" type="hidden" value="0" />
            <div class="mt-1 flex items-center rounded-xl overflow-hidden ring-1 ring-white/10 bg-slate-950/70 border border-slate-700 px-2"
                 x-data="ebPriceStepper(0.01)"
                 x-init="value = Number(document.getElementById('np-fee').value||0)"
                 x-effect="document.getElementById('np-fee').value = Number(value||0)">
              <button type="button" class="shrink-0 w-8 flex items-center justify-center py-2 text-white/80" :class="hovered==='dec' ? 'bg-white/10' : ''" @mouseenter="hovered='dec'" @mouseleave="hovered=''" @click.stop="dec">−</button>
              <span class="px-1 text-white/70">$</span>
              <input x-ref="input" x-model.number="value" type="number" step="0.01" class="eb-no-spinner flex-1 min-w-0 text-center bg-transparent text-slate-100 focus:outline-none focus:ring-0 py-2" />
              <button type="button" class="shrink-0 w-8 flex items-center justify-center py-2 text-white/80" :class="hovered==='inc' ? 'bg-white/10' : ''" @mouseenter="hovered='inc'" @mouseleave="hovered=''" @click.stop="inc">+</button>
            </div>
            <p class="mt-1 text-[11px] text-slate-500">Optional fee retained by your platform.</p>
          </div>
        </div>
      </div>

      <div class="space-y-2 border-t border-slate-800 pt-4">
        <label class="block text-sm font-medium text-slate-200">Card</label>
        <p class="text-xs text-slate-400">Select a saved card or enter new details. Stripe will create a Payment Intent.</p>
        <div id="card-element" class="rounded-xl bg-slate-950/70 border border-slate-700 px-3 py-3"></div>
      </div>

      <div class="flex items-center justify-between border-t border-slate-800 pt-4 mt-2">
        <div class="flex gap-3 text-xs text-slate-500">
          <span>Charges are processed via Stripe on the connected account.</span>
        </div>
        <div class="flex gap-2">
          <a href="{$modulelink}&a=ph-billing-payments" class="px-4 py-2 text-sm rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800">Cancel</a>
          <button id="np-submit" type="button" class="px-4 py-2 text-sm font-semibold rounded-xl bg-gradient-to-r from-emerald-500 to-sky-500 text-slate-950 shadow-md shadow-emerald-900/60 hover:brightness-110 transition" @click="paying=true">Create and Pay</button>
        </div>
      </div>
    </form>
  </div>

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
        const customer_id = document.getElementById('np-customer').value;
        const amount = document.getElementById('np-amount').value;
        const currency = document.getElementById('np-currency').value;
        const application_fee = document.getElementById('np-fee').value;
        const data = await createPaymentIntent({ customer_id, amount, currency, application_fee });
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
</div>


