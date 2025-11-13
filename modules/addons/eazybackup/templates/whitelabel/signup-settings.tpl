<div class="bg-gray-800 text-slate-200">
  <div class="container mx-auto px-4 py-6">
    <h2 class="text-xl font-semibold text-white mb-4">Client Registration Page</h2>
    {if $smarty.get.saved eq '1'}
      <div class="mb-4 rounded border border-emerald-700 bg-emerald-600/20 text-emerald-200 px-4 py-3 text-sm">Settings saved.</div>
    {/if}

    <form method="post" class="space-y-6">
      <div class="bg-gray-900/50 p-4 rounded border border-gray-700">
        <h3 class="text-white font-semibold mb-3">Flow Configuration</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div class="md:col-span-2 flex items-center gap-2">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_enabled" value="1" {if $flow.is_enabled}checked{/if}/> Enable public signup</label>
          </div>
          <div>
            {if $products|@count > 0}
              <label class="block text-slate-300 mb-1">Product</label>
              <div x-data="{ldelim}
                open:false,
                items: [{foreach from=$products item=p name=prods}{ldelim}id:{$p.id},name:'{$p.name|escape:'javascript'}'{rdelim}{if not $smarty.foreach.prods.last},{/if}{/foreach}],
                selectedId: {$flow.product_pid|default:0},
                get selected(){ldelim} return this.items.find(i=>i.id==this.selectedId) {rdelim}
              {rdelim}" class="relative">
                <input type="hidden" name="product_pid" :value="selectedId">
                <button type="button" @click="open=!open" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100 flex items-center justify-between">
                  <span x-text="selected ? selected.name : 'Select a product'"></span>
                  <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20"><path fill="currentColor" d="M5.5 7.5l4.5 4.5 4.5-4.5z"/></svg>
                </button>
                <div x-show="open" @click.outside="open=false" x-transition class="absolute z-10 mt-1 w-full rounded border border-slate-700 bg-slate-900 shadow">
                  <ul class="max-h-60 overflow-auto py-1 text-sm">
                    <template x-for="item in items" :key="item.id">
                      <li>
                        <button type="button" @click="selectedId=item.id; open=false" class="w-full text-left px-3 py-2 hover:bg-slate-800" x-text="item.name"></button>
                      </li>
                    </template>
                  </ul>
                </div>
              </div>
            {else}
              <label class="block text-slate-300 mb-1">Product (enter PID)</label>
              <input name="product_pid" value="{$flow.product_pid|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
            {/if}
          </div>
          <div>
            <label class="block text-slate-300 mb-1">Promo code (optional)</label>
            <input name="promo_code" value="{$flow.promo_code|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div>
            <label class="block text-slate-300 mb-1">Payment method</label>
            <input name="payment_method" value="{$flow.payment_method|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div>
            <label class="block text-slate-300 mb-1">Plan / Price</label>
            <select name="plan_price_id" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100">
              <option value="">— Select —</option>
              {foreach from=$prices item=pr}
                <option value="{$pr.id}" {if $flow.plan_price_id==$pr.id}selected{/if}>{$pr.nickname|default:'Standard'|escape} ({$pr.billing_cycle|escape})</option>
              {/foreach}
            </select>
          </div>
          <div class="flex items-center gap-2">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="require_card" value="1" {if $flow.require_card}checked{/if}/> Require card at signup</label>
          </div>
          <div class="flex items-center gap-2 mt-6">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="require_email_verify" value="1" {if $flow.require_email_verify}checked{/if}/> Require email verification</label>
          </div>
          <div class="flex items-center gap-2">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="send_customer_welcome" value="1" {if $flow.send_customer_welcome|default:1}checked{/if}/> Send Customer Welcome</label>
          </div>
          <div class="flex items-center gap-2">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="send_msp_notice" value="1" {if $flow.send_msp_notice|default:1}checked{/if}/> Send MSP Order Notice</label>
          </div>
        </div>
      </div>

      <div class="bg-gray-900/50 p-4 rounded border border-gray-700">
        <h3 class="text-white font-semibold mb-3">Content</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div>
            <label class="block text-slate-300 mb-1">Hero title</label>
            <input name="hero_title" value="{$flow.hero_title|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div>
            <label class="block text-slate-300 mb-1">Hero subtitle</label>
            <input name="hero_subtitle" value="{$flow.hero_subtitle|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-slate-300 mb-1">Feature bullets (one per line)</label>
            <textarea name="feature_bullets" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100 h-24">{$flow.feature_bullets|default:''}</textarea>
          </div>
          <div>
            <label class="block text-slate-300 mb-1">Terms of Service URL</label>
            <input name="tos_url" value="{$flow.tos_url|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div>
            <label class="block text-slate-300 mb-1">Privacy Policy URL</label>
            <input name="privacy_url" value="{$flow.privacy_url|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div>
            <label class="block text-slate-300 mb-1">Support URL</label>
            <input name="support_url" value="{$flow.support_url|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div>
            <label class="block text-slate-300 mb-1">Accent override (#RRGGBB)</label>
            <input name="accent_override" value="{$flow.accent_override|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
        </div>
      </div>

      {* <div class="bg-gray-900/50 p-4 rounded border border-gray-700">
        <h3 class="text-white font-semibold mb-3">Abuse Controls</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div class="md:col-span-2">
            <label class="block text-slate-300 mb-1">Allow email domains (comma separated)</label>
            <input name="allow_domains" value="{$flow.allow_domains|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-slate-300 mb-1">Deny email domains (comma separated)</label>
            <input name="deny_domains" value="{$flow.deny_domains|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div>
            <label class="block text-slate-300 mb-1">Rate limit by IP (requests/hour)</label>
            <input name="rate_ip" value="{$flow.rate_ip|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div>
            <label class="block text-slate-300 mb-1">Rate limit by Email (requests/hour)</label>
            <input name="rate_email" value="{$flow.rate_email|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-slate-300 mb-1">Turnstile Site Key Override (optional)</label>
            <input name="turnstile_sitekey_override" value="{$flow.turnstile_sitekey_override|default:''}" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100" />
          </div>
        </div>
      </div> *}

      <div class="bg-gray-900/50 p-4 rounded border border-gray-700">
        <h3 class="text-white font-semibold mb-3">Signup Domain</h3>
        <div class="text-sm text-slate-300">Current: <span class="font-mono">{$signup_domain_row.hostname|default:'Not set'}</span></div>
        {if $signup_domain_row.status}
          <div class="mt-2 text-xs"><span class="px-2 py-1 rounded {if $signup_domain_row.status=='verified'}bg-emerald-100 text-emerald-800{elseif $signup_domain_row.status=='dns_ok'}bg-blue-100 text-blue-800{elseif $signup_domain_row.status=='failed'}bg-red-100 text-red-800{else}bg-gray-100 text-gray-700{/if}">{$signup_domain_row.status}</span></div>
          {if $signup_domain_row.cert_expires_at}<div class="text-xs text-slate-400 mt-1">Cert expires: {$signup_domain_row.cert_expires_at}</div>{/if}
        {/if}
        <div class="mt-3 flex gap-2 items-center">
          <input id="su-host" type="text" class="w-full rounded border border-slate-600 bg-slate-900 px-2 py-2 text-slate-100 text-sm" placeholder="signup.acme.com" />
          <button id="su-check" type="button" class="rounded bg-slate-700 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-600">Check DNS</button>
          <button id="su-attach" type="button" class="rounded bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Attach Domain</button>
        </div>
        <div id="su-loader" class="hidden mt-2 text-xs text-slate-300 flex items-center gap-2">
          <svg class="animate-spin h-4 w-4 text-slate-300" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
          <span id="su-loader-text">Checking…</span>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="submit" class="rounded bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Save changes</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  function xhr(url, data, cb){ var x=new XMLHttpRequest(); x.open('POST', url, true); x.setRequestHeader('Content-Type','application/x-www-form-urlencoded'); x.onreadystatechange=function(){ if (x.readyState===4){ try{ cb(null, JSON.parse(x.responseText||'{}')); }catch(e){ cb(e); } } }; x.send(data); }
  var btnC=document.getElementById('su-check'); var btnA=document.getElementById('su-attach'); var hostI=document.getElementById('su-host'); var loader=document.getElementById('su-loader'); var loaderText=document.getElementById('su-loader-text');
  if (!btnC || !btnA || !hostI) return;
  var tenantTid = '{$tenant.public_id|default:""|escape:"javascript"}';
  var token = '{$csrf_token|default:''}';
  function enc(s){ return encodeURIComponent(s); }
  function setBusy(b){ btnC.disabled=b; btnA.disabled=b; btnC.classList.toggle('opacity-50', b); btnA.classList.toggle('opacity-50', b); if (loader) loader.classList.toggle('hidden', !b); }
  btnC.addEventListener('click', function(){ var h=(hostI.value||'').trim(); if (!h){ alert('Enter a hostname'); return; } if (loaderText) loaderText.textContent='Checking DNS…'; setBusy(true); xhr('{$modulelink}&a=whitelabel-signup-checkdns', 'tenant_tid='+enc(tenantTid)+'&hostname='+enc(h)+'&token='+enc(token), function(err,res){ setBusy(false); if (err||!res){ alert('Check failed'); return; } if (res.ok){ alert('DNS '+(res.status==='dns_ok'?'OK':'pending')); } else { alert(res.error||'DNS check failed'); } }); });
  btnA.addEventListener('click', function(){ var h=(hostI.value||'').trim(); if (!h){ alert('Enter a hostname'); return; } if (loaderText) loaderText.textContent='Attaching domain…'; setBusy(true); xhr('{$modulelink}&a=whitelabel-signup-attachdomain', 'tenant_tid='+enc(tenantTid)+'&hostname='+enc(h)+'&token='+enc(token), function(err,res){ setBusy(false); if (err||!res){ alert('Attach failed'); return; } if (res.ok){ alert(res.message||'Attached'); location.reload(); } else { alert(res.error||'Attach failed'); } }); });
})();
</script>


