<div class="min-h-screen bg-gray-800 text-gray-200">
  <div class="container mx-auto max-w-2xl px-4 py-10">
    <h1 class="text-2xl font-semibold text-white mb-4">Start your trial</h1>
    <p class="text-sm text-gray-300 mb-6">Create your account to begin using our backup service.</p>
    <form method="post" class="space-y-4" id="pub-signup">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm text-gray-300 mb-1">First name</label>
          <input name="first_name" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
        <div>
          <label class="block text-sm text-gray-300 mb-1">Last name</label>
          <input name="last_name" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
      </div>
      <div>
        <label class="block text-sm text-gray-300 mb-1">Company (optional)</label>
        <input name="company" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm text-gray-300 mb-1">Email</label>
          <input type="email" name="email" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
        <div>
          <label class="block text-sm text-gray-300 mb-1">Phone (optional)</label>
          <input name="phone" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-sm text-gray-300 mb-1">Username</label>
          <input name="username" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
        <div>
          <label class="block text-sm text-gray-300 mb-1">Password</label>
          <input type="password" name="password" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
        </div>
      </div>
      <div>
        <label class="block text-sm text-gray-300 mb-1">Confirm Password</label>
        <input type="password" name="confirm_password" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
      </div>
      <div>
        <label class="block text-sm text-gray-300 mb-1">Promo code (optional)</label>
        <input name="promo_code" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
      </div>
      {if $prices|@count}
      <div>
        <label class="block text-sm text-gray-300 mb-1">Plan / Price</label>
        <select name="plan_price_id" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100">
          {foreach from=$prices item=pr}
            <option value="{$pr.id}">{$pr.nickname|default:'Standard'|escape} ({$pr.billing_cycle|escape})</option>
          {/foreach}
        </select>
      </div>
      {/if}
      <div class="flex items-center gap-2">
        <input type="checkbox" name="agree" value="1" class="rounded" />
        <span class="text-xs text-gray-300">I agree to the Terms of Service and Privacy Policy.</span>
      </div>
      {if $flow.require_card}
      <div>
        <label class="block text-sm text-gray-300 mb-1">Payment method</label>
        <div id="pub-card" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-3 text-slate-100"></div>
        <input type="hidden" name="payment_method_id" id="pub-pm-id" />
      </div>
      {/if}
      <div class="flex justify-end">
        <button type="submit" class="rounded bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Create account</button>
      </div>
    </form>
    {if $turnstile_site_key}
      <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
      <div class="mt-4"><div class="cf-turnstile" data-sitekey="{$turnstile_site_key|escape}"></div></div>
    {/if}
    <script src="https://js.stripe.com/v3"></script>
    <script>
      (function(){
        var needCard = {$flow.require_card|default:0};
        if(!needCard) return;
        var form=document.getElementById('pub-signup'); if(!form) return;
        var stripe=null, elements=null, card=null, clientSecret=null;
        async function init(){
          // Fetch anonymous setupintent for capture
          const r = await fetch('index.php?m=eazybackup&a=public-setupintent', { method:'POST' });
          const j = await r.json();
          if(j.status!=='success') return;
          stripe = Stripe(j.publishable);
          elements = stripe.elements({appearance:{theme:'night'}});
          card = elements.create('card');
          card.mount('#pub-card');
          clientSecret = j.client_secret;
        }
        init();
        form.addEventListener('submit', async function(e){
          if(!card || !stripe || !clientSecret) return;
          e.preventDefault();
          const res = await stripe.confirmCardSetup(clientSecret, { payment_method: { card } });
          if(res.error){ alert(res.error.message||'Card error'); return; }
          document.getElementById('pub-pm-id').value = res.setupIntent.payment_method;
          form.submit();
        });
      })();
    </script>
  </div>
</div>


