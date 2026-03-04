<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='signup-settings'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
      <div class="mb-6">
        <h2 class="text-2xl font-semibold text-white">Client Registration Page</h2>
        <p class="text-xs text-slate-400 mt-1">Configure public signup flow, content, and signup domain.</p>
      </div>
      {if $smarty.get.saved eq '1'}
        <div class="mb-4 rounded-lg border border-emerald-700/50 bg-emerald-600/20 text-emerald-200 px-4 py-3 text-sm">Settings saved.</div>
      {/if}

      <form method="post" class="space-y-6">
        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
          <div class="px-6 py-5 border-b border-slate-800">
            <h3 class="text-lg font-medium text-slate-100">Flow Configuration</h3>
          </div>
          <div class="px-6 py-6 space-y-4">
            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="is_enabled" value="1" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $flow.is_enabled}checked{/if}/> Enable public signup</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              {if $products|@count > 0}
                <div>
                  <label class="block text-sm text-slate-400 mb-1">Product</label>
                  <div x-data="{ldelim}
                    open:false,
                    items: [{foreach from=$products item=p name=prods}{ldelim}id:{$p.id},name:'{$p.name|escape:'javascript'}'{rdelim}{if not $smarty.foreach.prods.last},{/if}{/foreach}],
                    selectedId: {$flow.product_pid|default:0},
                    get selected(){ldelim} return this.items.find(i=>i.id==this.selectedId) {rdelim}
                  {rdelim}" class="relative">
                    <input type="hidden" name="product_pid" :value="selectedId">
                    <button type="button" @click="open=!open" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 flex items-center justify-between gap-2">
                      <span class="truncate" x-text="selected ? selected.name : 'Select a product'"></span>
                      <svg class="w-4 h-4 shrink-0 text-slate-400" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20"><path fill="currentColor" d="M5.5 7.5l4.5 4.5 4.5-4.5z"/></svg>
                    </button>
                    <div x-show="open" @click.outside="open=false" x-transition class="absolute z-10 mt-1 w-full rounded-lg border border-slate-700 bg-slate-900 shadow-xl overflow-hidden">
                      <ul class="max-h-60 overflow-auto py-1 text-sm divide-y divide-slate-800">
                        <template x-for="item in items" :key="item.id">
                          <li>
                            <button type="button" @click="selectedId=item.id; open=false" class="w-full text-left px-3 py-2.5 text-slate-200 hover:bg-slate-800" x-text="item.name"></button>
                          </li>
                        </template>
                      </ul>
                    </div>
                  </div>
                </div>
              {else}
                <div>
                  <label class="block text-sm text-slate-400 mb-1">Product (enter PID)</label>
                  <input name="product_pid" value="{$flow.product_pid|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
                </div>
              {/if}
              <div>
                <label class="block text-sm text-slate-400 mb-1">Promo code (optional)</label>
                <input name="promo_code" value="{$flow.promo_code|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
              </div>
              <div>
                <label class="block text-sm text-slate-400 mb-1">Payment method</label>
                <input name="payment_method" value="{$flow.payment_method|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
              </div>
              <div>
                <label class="block text-sm text-slate-400 mb-1">Plan / Price</label>
                <select name="plan_price_id" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition">
                  <option value="">— Select —</option>
                  {foreach from=$prices item=pr}
                    <option value="{$pr.id}" {if $flow.plan_price_id==$pr.id}selected{/if}>{$pr.nickname|default:'Standard'|escape} ({$pr.billing_cycle|escape})</option>
                  {/foreach}
                </select>
              </div>
            </div>
            <div class="flex flex-wrap items-center gap-4 mt-4 text-sm text-slate-300">
              <label class="inline-flex items-center gap-2"><input type="checkbox" name="require_card" value="1" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $flow.require_card}checked{/if}/> Require card at signup</label>
              <label class="inline-flex items-center gap-2"><input type="checkbox" name="require_email_verify" value="1" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $flow.require_email_verify}checked{/if}/> Require email verification</label>
              <label class="inline-flex items-center gap-2"><input type="checkbox" name="send_customer_welcome" value="1" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $flow.send_customer_welcome|default:1}checked{/if}/> Send Customer Welcome</label>
              <label class="inline-flex items-center gap-2"><input type="checkbox" name="send_msp_notice" value="1" class="rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500" {if $flow.send_msp_notice|default:1}checked{/if}/> Send MSP Order Notice</label>
            </div>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
          <div class="px-6 py-5 border-b border-slate-800">
            <h3 class="text-lg font-medium text-slate-100">Content</h3>
          </div>
          <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm text-slate-400 mb-1">Hero title</label>
              <input name="hero_title" value="{$flow.hero_title|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
            </div>
            <div>
              <label class="block text-sm text-slate-400 mb-1">Hero subtitle</label>
              <input name="hero_subtitle" value="{$flow.hero_subtitle|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm text-slate-400 mb-1">Feature bullets (one per line)</label>
              <textarea name="feature_bullets" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition h-24">{$flow.feature_bullets|default:''}</textarea>
            </div>
            <div>
              <label class="block text-sm text-slate-400 mb-1">Terms of Service URL</label>
              <input name="tos_url" value="{$flow.tos_url|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
            </div>
            <div>
              <label class="block text-sm text-slate-400 mb-1">Privacy Policy URL</label>
              <input name="privacy_url" value="{$flow.privacy_url|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
            </div>
            <div>
              <label class="block text-sm text-slate-400 mb-1">Support URL</label>
              <input name="support_url" value="{$flow.support_url|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
            </div>
            <div>
              <label class="block text-sm text-slate-400 mb-1">Accent override (#RRGGBB)</label>
              <input name="accent_override" value="{$flow.accent_override|default:''}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition"/>
            </div>
          </div>
        </section>

        <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
          <div class="px-6 py-5 border-b border-slate-800">
            <h3 class="text-lg font-medium text-slate-100">Signup Domain</h3>
          </div>
          <div class="px-6 py-6 space-y-3">
            <div class="text-sm text-slate-300">Current: <span class="font-mono">{$signup_domain_row.hostname|default:'Not set'}</span></div>
            {if $signup_domain_row.status}
              <div class="text-xs"><span class="px-2 py-1 rounded {if $signup_domain_row.status=='verified'}bg-emerald-500/15 text-emerald-200{elseif $signup_domain_row.status=='dns_ok'}bg-sky-500/15 text-sky-200{elseif $signup_domain_row.status=='failed'}bg-rose-500/15 text-rose-200{else}bg-slate-700 text-slate-300{/if}">{$signup_domain_row.status}</span></div>
              {if $signup_domain_row.cert_expires_at}<div class="text-xs text-slate-400">Cert expires: {$signup_domain_row.cert_expires_at}</div>{/if}
            {/if}
            <div class="flex gap-2 items-center flex-wrap">
              <input id="su-host" type="text" class="flex-1 min-w-0 max-w-sm px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" placeholder="signup.acme.com" />
              <button id="su-check" type="button" class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700">Check DNS</button>
              <button id="su-attach" type="button" class="rounded-lg px-3 py-2 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500">Attach Domain</button>
            </div>
            <div id="su-loader" class="hidden mt-2 text-xs text-slate-300 flex items-center gap-2">
              <svg class="animate-spin h-4 w-4 text-slate-300" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
              <span id="su-loader-text">Checking…</span>
            </div>
          </div>
        </section>

        <div class="flex justify-end">
          <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Save changes</button>
        </div>
      </form>
    </div>
        </main>
      </div>
    </div>
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
