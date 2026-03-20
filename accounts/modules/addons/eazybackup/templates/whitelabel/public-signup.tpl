{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{if $signup_state == 'pending_approval'}
  {capture assign=ebAuthContent}
    <div class="text-center">
      <h1 class="eb-auth-title">Signup Received</h1>
      <p class="mt-3 text-sm text-emerald-300">Your signup is pending MSP approval.</p>
      <p class="eb-auth-description">We will email you when approval is complete and provisioning begins.</p>
    </div>
  {/capture}

  {include file="$template/includes/ui/auth-shell.tpl"
    ebAuthContent=$ebAuthContent
  }
{else}
  {capture assign=ebAuthContent}
    <div class="space-y-6">
      <div class="text-center">
        <h1 class="eb-auth-title">Start Your Trial</h1>
        <p class="eb-auth-description">Create your account to begin using the backup service.</p>
      </div>

      <form method="post" class="space-y-4" id="pub-signup">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="eb-field-label">First Name</label>
            <input name="first_name" class="eb-input" />
          </div>
          <div>
            <label class="eb-field-label">Last Name</label>
            <input name="last_name" class="eb-input" />
          </div>
        </div>
        <div>
          <label class="eb-field-label">Company (optional)</label>
          <input name="company" class="eb-input" />
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="eb-field-label">Email</label>
            <input type="email" name="email" class="eb-input" />
          </div>
          <div>
            <label class="eb-field-label">Phone (optional)</label>
            <input name="phone" class="eb-input" />
          </div>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="eb-field-label">Username</label>
            <input name="username" class="eb-input" />
          </div>
          <div>
            <label class="eb-field-label">Password</label>
            <input type="password" name="password" class="eb-input" />
          </div>
        </div>
        <div>
          <label class="eb-field-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="eb-input" />
        </div>
        <div>
          <label class="eb-field-label">Promo Code (optional)</label>
          <input name="promo_code" class="eb-input" />
        </div>
        {if $prices|@count}
        <div>
          <label class="eb-field-label">Plan / Price</label>
          <select name="plan_price_id" class="eb-select">
            {foreach from=$prices item=pr}
              <option value="{$pr.id}">{$pr.nickname|default:'Standard'|escape} ({$pr.billing_cycle|escape})</option>
            {/foreach}
          </select>
        </div>
        {/if}
        <label class="flex items-center gap-2 text-xs text-slate-300">
          <input type="checkbox" name="agree" value="1" class="h-4 w-4 rounded border border-slate-600 bg-slate-900 text-orange-500 focus:ring-2 focus:ring-orange-500/40" />
          <span>I agree to the Terms of Service and Privacy Policy.</span>
        </label>
        {if $flow.require_card}
        <div>
          <label class="eb-field-label">Payment Method</label>
          <div id="pub-card" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-3 text-slate-100"></div>
          <input type="hidden" name="payment_method_id" id="pub-pm-id" />
        </div>
        {/if}
        <div class="flex justify-end">
          <button type="submit" class="eb-btn eb-btn-primary eb-btn-sm">Create Account</button>
        </div>
      </form>

      {if $turnstile_site_key}
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <div class="flex justify-center"><div class="cf-turnstile" data-sitekey="{$turnstile_site_key|escape}"></div></div>
      {/if}
    </div>
  {/capture}

  {include file="$template/includes/ui/auth-shell.tpl"
    ebAuthWrapClass='!max-w-3xl'
    ebAuthCardClass='!px-8 !py-8'
    ebAuthContent=$ebAuthContent
  }

  <script src="https://js.stripe.com/v3"></script>
  <script>
    (function(){
      var needCard = {$flow.require_card|default:0};
      if(!needCard) return;
      var form=document.getElementById('pub-signup'); if(!form) return;
      var stripe=null, elements=null, card=null, clientSecret=null;
      async function init(){
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
{/if}


