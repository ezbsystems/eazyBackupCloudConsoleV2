{* Partner Hub — Client view *}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
<div class="min-h-screen eb-bg-page eb-text-primary">
  <div class="mx-auto max-w-5xl px-6 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">{$customer.name|escape}</h1>
      <a class="btn btn-secondary" href="{$modulelink}&a=ph-clients">Back</a>
    </div>

    <section class="mt-6 rounded-2xl eb-bg-card shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Services</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6">
        <form id="eb-link-service" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
          <input type="hidden" name="customer_id" value="{$customer.id}" />
          <label class="md:col-span-6 block">
            <span class="text-sm eb-text-secondary">WHMCS Service</span>
            <select name="whmcs_service_id" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5">
              {foreach from=$services item=s}
                <option value="{$s.id}">#{$s.id} — {$s.domain|default:$s.product|escape} ({$s.regdate|escape})</option>
              {/foreach}
            </select>
          </label>
          <label class="md:col-span-6 block">
            <span class="text-sm eb-text-secondary">Comet User</span>
            <select name="comet_user" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5">
              {foreach from=$cometUsers item=u}
                <option value="{$u.username|escape}">{$u.username|escape}</option>
              {/foreach}
            </select>
          </label>
          <div class="md:col-span-12 flex justify-end">
            <button type="submit" class="btn btn-affirm">Link</button>
          </div>
        </form>
      </div>
    </section>

    <script>
      {literal}
      (function(){
        // Alpine helper: numeric stepper with min/max/step and safe coercion
        window.ebStepper = function(opts){
          return {
            value: 0,
            min: isFinite(opts && opts.min) ? Number(opts.min) : -Infinity,
            max: isFinite(opts && opts.max) ? Number(opts.max) : Infinity,
            step: isFinite(opts && opts.step) && Number(opts.step) > 0 ? Number(opts.step) : 1,
            dec(){
              var v = Number(this.value)||0; v -= this.step;
              if (isFinite(this.min) && v < this.min) v = this.min;
              this.value = v;
              if (this.$refs && this.$refs.input) this.$refs.input.dispatchEvent(new Event('input'));
            },
            inc(){
              var v = Number(this.value)||0; v += this.step;
              if (isFinite(this.max) && v > this.max) v = this.max;
              this.value = v;
              if (this.$refs && this.$refs.input) this.$refs.input.dispatchEvent(new Event('input'));
            }
          };
        };
        var form=document.getElementById('eb-link-service');
        if(!form) return;
        form.addEventListener('submit', function(e){
          e.preventDefault();
          var url = '{/literal}{$modulelink|escape:'javascript'}{literal}&a=ph-services-link';
          fetch(url, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(new FormData(form)) })
            .then(function(r){ return r.json(); }).then(function(j){
              var t=document.getElementById('toast-container');
              if(!t){ t=document.createElement('div'); t.id='toast-container'; t.style.position='fixed'; t.style.top='1rem'; t.style.right='1rem'; t.style.zIndex='9999'; document.body.appendChild(t); }
              var toast=document.createElement('div');
              toast.className='mt-2 rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/80';
              toast.textContent = (j.status==='success') ? 'Service linked.' : (j.message||'Failed');
              t.appendChild(toast); setTimeout(function(){ toast.remove(); }, 3000);
            });
        });
      })();
      {/literal}
    </script>

    <!-- External: profile save handler (CSP-safe) -->
    <script src="modules/addons/eazybackup/assets/js/client-profile.js" defer></script>
    <div class="mt-6 grid grid-cols-1 md:grid-cols-12 gap-6">
      <section class="md:col-span-6 rounded-2xl eb-bg-card shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
        <div class="px-6 py-5"><h2 class="text-lg font-medium">Client Information</h2></div>
        <div class="border-t border-white/10"></div>
        <form id="eb-profile" class="px-6 py-6 text-sm space-y-3">
          <input type="hidden" name="customer_id" value="{$customer.id}" />
          <div class="grid grid-cols-2 gap-3">
            <label class="block"><span class="text-sm eb-text-secondary">First Name</span><input name="firstname" value="{$wc.firstname|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
            <label class="block"><span class="text-sm eb-text-secondary">Last Name</span><input name="lastname" value="{$wc.lastname|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
          </div>
          <label class="block"><span class="text-sm eb-text-secondary">Company</span><input name="companyname" value="{$wc.companyname|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
          <label class="block"><span class="text-sm eb-text-secondary">Email</span><input name="email" value="{$wc.email|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
          <label class="block"><span class="text-sm eb-text-secondary">Address 1</span><input name="address1" value="{$wc.address1|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
          <label class="block"><span class="text-sm eb-text-secondary">Address 2</span><input name="address2" value="{$wc.address2|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
          <div class="grid grid-cols-3 gap-3">
            <label class="block"><span class="text-sm eb-text-secondary">City</span><input name="city" value="{$wc.city|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
            <label class="block"><span class="text-sm eb-text-secondary">State/Region</span><input name="state" value="{$wc.state|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
            <label class="block"><span class="text-sm eb-text-secondary">Postcode</span><input name="postcode" value="{$wc.postcode|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <label class="block"><span class="text-sm eb-text-secondary">Country</span><input name="country" value="{$wc.country|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
            <label class="block"><span class="text-sm eb-text-secondary">Phone</span><input name="phonenumber" value="{$wc.phonenumber|escape}" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
          </div>
          <div class="flex justify-end">
            <button type="button" id="eb-profile-save" class="btn btn-affirm">Save profile</button>
          </div>
        </form>
      </section>

      <section class="md:col-span-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
        <div class="px-6 py-5"><h2 class="text-lg font-medium">Billing</h2></div>
        <div class="border-t border-white/10"></div>
        <div class="px-6 py-6 grid grid-cols-2 gap-4 text-sm">
          <div><div class="text-white/60">Paid</div><div class="font-medium">{$kpis.paid}</div></div>
          <div><div class="text-white/60">Unpaid/Due</div><div class="font-medium">{$kpis.unpaid}</div></div>
          <div><div class="text-white/60">Cancelled</div><div class="font-medium">{$kpis.cancelled}</div></div>
          <div><div class="text-white/60">Refunded</div><div class="font-medium">{$kpis.refunded}</div></div>
          <div><div class="text-white/60">Collections</div><div class="font-medium">{$kpis.collections}</div></div>
          <div><div class="text-white/60">Gross Revenue</div><div class="font-medium">{$kpis.gross}</div></div>
          <div><div class="text-white/60">Net Income</div><div class="font-medium">{$kpis.net}</div></div>
        </div>
      </section>
    </div>

    <section class="mt-6 rounded-2xl eb-bg-card shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5 flex items-center justify-between">
        <h2 class="text-lg font-medium">Subscriptions</h2>
        <a class="btn btn-primary" href="{$modulelink}&a=ph-subscriptions&customer_id={$customer.id}">New subscription</a>
      </div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6">
        <table class="w-full text-sm">
          <thead class="bg-white/5 text-white/70">
            <tr class="text-left">
              <th class="px-4 py-3 font-medium">Stripe ID</th>
              <th class="px-4 py-3 font-medium">Status</th>
              <th class="px-4 py-3 font-medium">Started</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            {foreach from=$subscriptions item=s}
            <tr class="hover:bg-white/5">
              <td class="px-4 py-3">{$s.stripe_subscription_id|default:'-'|escape}</td>
              <td class="px-4 py-3">{$s.stripe_status|escape}</td>
              <td class="px-4 py-3">{$s.started_at|escape}</td>
            </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    </section>

    <section class="mt-6 rounded-2xl eb-bg-card shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Invoices</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6">
        <div class="mb-4 flex justify-end">
          <form id="eb-refresh-invoices" class="inline">
            <input type="hidden" name="customer_id" value="{$customer.id}" />
            <button type="submit" class="btn btn-secondary">Refresh</button>
          </form>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-white/5 text-white/70">
            <tr class="text-left">
              <th class="px-4 py-3 font-medium">ID</th>
              <th class="px-4 py-3 font-medium">Amount</th>
              <th class="px-4 py-3 font-medium">Status</th>
              <th class="px-4 py-3 font-medium">Created</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            {foreach from=$invoices item=iv}
            <tr class="hover:bg-white/5">
              <td class="px-4 py-3"><a class="text-sky-400 hover:underline" href="{$iv.hosted_invoice_url|escape}" target="_blank" rel="noopener">{$iv.stripe_invoice_id|escape}</a></td>
              <td class="px-4 py-3">{$iv.amount_total}</td>
              <td class="px-4 py-3">{$iv.status|escape}</td>
              <td class="px-4 py-3">{$iv.created}</td>
            </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    </section>

    <section class="mt-6 rounded-2xl eb-bg-card shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5"><h2 class="text-lg font-medium">Transactions</h2></div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6">
        <div class="mb-4">
          <form id="eb-usage" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <input type="hidden" name="customer_id" value="{$customer.id}" />
            <label class="md:col-span-4 block">
              <span class="text-sm eb-text-secondary">Metric</span>
              <select name="metric" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5">
                <option value="storage_gb">Storage (GiB)</option>
                <option value="devices">Devices</option>
              </select>
            </label>
            <label class="md:col-span-3 block">
              <span class="text-sm eb-text-secondary">Quantity</span>
              <div class="mt-2 flex items-stretch rounded-xl overflow-hidden ring-1 ring-white/10 eb-bg-input" x-data="ebStepper({ min: 0, step: 1 })" x-init="if($refs && $refs.input){ value = Number($refs.input.value||0) }">
                <button type="button" class="w-10 shrink-0 flex items-center justify-center py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgb(var(--accent))] select-none" aria-label="Decrease" @click="dec">−</button>
                <input x-ref="input" x-model.number="value" type="number" name="qty" min="0" step="1" class="flex-auto basis-0 min-w-0 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 py-2.5 appearance-none caret-white/80" />
                <button type="button" class="w-10 shrink-0 flex items-center justify-center py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgb(var(--accent))] select-none" aria-label="Increase" @click="inc">+</button>
              </div>
            </label>
            <label class="md:col-span-3 block">
              <span class="text-sm eb-text-secondary">Period End (epoch)</span>
              <div class="mt-2 flex items-stretch rounded-xl overflow-hidden ring-1 ring-white/10 eb-bg-input" x-data="ebStepper({ min: 0, step: 1 })" x-init="if($refs && $refs.input){ value = Number($refs.input.value||0) }">
                <button type="button" class="w-10 shrink-0 flex items-center justify-center py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgb(var(--accent))] select-none" aria-label="Decrease" @click="dec">−</button>
                <input x-ref="input" x-model.number="value" type="number" name="period_end" min="0" step="1" value="{time()}" class="flex-auto basis-0 min-w-0 text-center bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 py-2.5 appearance-none caret-white/80" />
                <button type="button" class="w-10 shrink-0 flex items-center justify-center py-2.5 text-white/80 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[rgb(var(--accent))] select-none" aria-label="Increase" @click="inc">+</button>
              </div>
            </label>
            <div class="md:col-span-2 flex justify-end">
              <button type="submit" class="btn btn-affirm">Push usage</button>
            </div>
          </form>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-white/5 text-white/70">
            <tr class="text-left">
              <th class="px-4 py-3 font-medium">Payment Intent</th>
              <th class="px-4 py-3 font-medium">Amount</th>
              <th class="px-4 py-3 font-medium">Status</th>
              <th class="px-4 py-3 font-medium">Created</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            {foreach from=$payments item=pm}
            <tr class="hover:bg-white/5">
              <td class="px-4 py-3">{$pm.stripe_payment_intent_id|escape}</td>
              <td class="px-4 py-3">{$pm.amount}</td>
              <td class="px-4 py-3">{$pm.status|escape}</td>
              <td class="px-4 py-3">{$pm.created}</td>
            </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    </section>

    <script>
      {literal}
      (function(){
        var base = '{/literal}{$modulelink|escape:'javascript'}{literal}';
        var uform=document.getElementById('eb-usage'); if(!uform) return;
        uform.addEventListener('submit', function(e){
          e.preventDefault();
          fetch(base + '&a=ph-usage-push', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(new FormData(uform)) })
            .then(function(r){ return r.json(); }).then(function(j){
              var t=document.getElementById('toast-container');
              if(!t){ t=document.createElement('div'); t.id='toast-container'; t.style.position='fixed'; t.style.top='1rem'; t.style.right='1rem'; t.style.zIndex='9999'; document.body.appendChild(t); }
              var toast=document.createElement('div'); toast.className='mt-2 rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/80';
              toast.textContent = (j.status==='success') ? 'Usage recorded.' : (j.message||'Failed');
              t.appendChild(toast); setTimeout(function(){ toast.remove(); }, 3000);
            });
        });
      })();

      (function(){
        var base = '{/literal}{$modulelink|escape:'javascript'}{literal}';
        var rform=document.getElementById('eb-refresh-invoices'); if(!rform) return;
        rform.addEventListener('submit', function(e){
          e.preventDefault();
          fetch(base + '&a=ph-invoices-refresh', { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams(new FormData(rform)) })
            .then(function(r){ return r.json(); }).then(function(j){
              var t=document.getElementById('toast-container');
              if(!t){ t=document.createElement('div'); t.id='toast-container'; t.style.position='fixed'; t.style.top='1rem'; t.style.right='1rem'; t.style.zIndex='9999'; document.body.appendChild(t); }
              var toast=document.createElement('div');
              toast.className='mt-2 rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/80';
              toast.textContent = (j.status==='success') ? 'Invoices refreshed.' : (j.message||'Refresh failed');
              t.appendChild(toast);
              setTimeout(function(){ toast.remove(); }, 3000);
            });
        });
      })();
      {/literal}
    </script>
    <section class="mt-6 rounded-2xl eb-bg-card ring-1 ring-white/10 shadow-xl shadow-black/20 overflow-hidden">
      <div class="px-6 py-5 flex items-center justify-between">
        <h2 class="text-lg font-medium">Pay Methods</h2>
        <button id="eb-open-add-card" class="btn btn-secondary">Add card</button>
      </div>
    </section>

    <!-- Add Card Modal -->
    <div id="eb-add-card-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" id="eb-add-card-close"></div>
      <div class="relative w-full max-w-lg rounded-2xl eb-bg-card ring-1 ring-white/10 shadow-xl shadow-black/30 overflow-hidden">
        <div class="px-6 py-5 flex items-center justify-between">
          <h3 class="text-lg font-medium">Add card</h3>
          <button id="eb-add-card-x" class="text-white/70 hover:text-white">✕</button>
        </div>
        <div class="border-t border-white/10"></div>
        <form id="eb-add-card-form" class="px-6 py-6 space-y-4">
          <div>
            <div id="eb-card" class="rounded-xl eb-bg-input ring-1 ring-white/10 px-3.5 py-3"></div>
          </div>
          <div class="flex justify-end gap-3">
            <button type="button" id="eb-add-card-cancel" class="btn btn-secondary">Cancel</button>
            <button type="submit" class="btn btn-affirm">Save card</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Stripe.js + helper -->
    <script src="https://js.stripe.com/v3"></script>
    <script src="modules/addons/eazybackup/assets/js/stripe-elements.js"></script>
    <script>
      {literal}
      (function(){
        var openBtn = document.getElementById('eb-open-add-card');
        var modal = document.getElementById('eb-add-card-modal');
        var closeEls = [document.getElementById('eb-add-card-close'), document.getElementById('eb-add-card-x'), document.getElementById('eb-add-card-cancel')];
        function show(){ modal.classList.remove('hidden'); }
        function hide(){ modal.classList.add('hidden'); }
        if (openBtn) openBtn.addEventListener('click', function(e){ e.preventDefault(); show(); });
        closeEls.forEach(function(el){ if(el){ el.addEventListener('click', function(e){ e.preventDefault(); hide(); }); }});
        var form = document.getElementById('eb-add-card-form');
        if (window.EBStripe && form) {
          window.EBStripe.addCard({
            form: form,
            customerId: {/literal}{$customer.id|escape:'javascript'}{literal},
            endpoint: '{/literal}{$modulelink|escape:'javascript'}{literal}&a=ph-stripe-setupintent'
          });
        }
      })();
      {/literal}
    </script>
  </div>
</div>


