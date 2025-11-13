{* Stripe-style Products list *}
<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-7xl px-6 py-8" x-data="{ q:'', matches(n){ if(!this.q) return true; try{ return String(n||'').toLowerCase().indexOf(String(this.q).toLowerCase())>=0; }catch(_){ return true; } } }">
  <input type="hidden" id="eb-token" value="{$token}" />
  <div class="flex items-center justify-between">
    <div class="flex-1 pr-4">
      <input x-model="q" placeholder="Search" class="w-1/2 rounded-xl eb-bg-input text-white/90 placeholder-white/30 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
    </div>
    <div class="flex items-center gap-2">
      <button id="eb-open-create-product" class="px-3 py-2 rounded-lg bg-[rgb(var(--accent))] text-white">Create product</button>
    </div>
  </div>

  <div class="mt-4 flex items-center gap-3">
    <div class="text-2xl font-semibold tracking-tight">{$company|default:'Connected account'|escape}</div>
    <span class="text-xs px-2 py-0.5 rounded-full {if $msp_ready}bg-emerald-500/10 text-emerald-300 ring-emerald-400/20{else}bg-amber-500/10 text-amber-300 ring-amber-400/20{/if} ring-1">{if $msp_ready}Enabled{else}Disabled{/if}</span>
  </div>
  <div class="mt-2 flex items-center gap-4 text-sm">
    <span class="inline-flex items-center gap-1 text-white/70">Payouts {if $acct_info.payouts_enabled}<span class="text-emerald-300">active</span>{else}<span class="text-amber-300">disabled</span>{/if}</span>
    <span class="inline-flex items-center gap-1 text-white/70">Payments {if $acct_info.charges_enabled}<span class="text-emerald-300">active</span>{else}<span class="text-amber-300">disabled</span>{/if}</span>
  </div>

  <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="rounded-xl ring-1 ring-white/10 bg-white/5 px-4 py-3 w-full">
      <div class="text-xs text-white/60">All</div>
      <div class="text-lg font-semibold">{$count_all|default:0}</div>
    </div>
    <div class="rounded-xl ring-1 ring-white/10 bg-white/5 px-4 py-3 w-full">
      <div class="text-xs text-white/60">Active</div>
      <div class="text-lg font-semibold">{$count_active|default:0}</div>
    </div>
    <div class="rounded-xl ring-1 ring-white/10 bg-white/5 px-4 py-3 w-full">
      <div class="text-xs text-white/60">Archived</div>
      <div class="text-lg font-semibold">{$count_archived|default:0}</div>
    </div>
  </div>

  <div class="mt-4 flex items-center justify-end gap-2">
    <a class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" href="{$modulelink}&a=ph-catalog-export-prices" target="_blank">Export prices</a>
    <a class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" href="{$modulelink}&a=ph-catalog-export-products" target="_blank">Export products</a>
  </div>

  <div class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-white/5 text-white/70">
        <tr class="text-left">
          <th class="px-4 py-3 font-medium">Name</th>
          <th class="px-4 py-3 font-medium">Pricing</th>
          <th class="px-4 py-3 font-medium">Created</th>
          <th class="px-4 py-3 font-medium">Updated</th>
          <th class="px-4 py-3 font-medium"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/10">
        {foreach from=$stripe_products item=sp}
        <tr x-show="matches('{$sp.name|escape}')">
          <td class="px-4 py-3"><a class="text-sky-300 hover:underline" href="{$modulelink}&a=ph-catalog-product&id={$sp.id|escape}">{$sp.name|escape}</a> {if !$sp.active}<span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-white/5 text-white/70 ring-1 ring-white/10">Archived</span>{/if}</td>
          <td class="px-4 py-3">{$sp.pricing_summary|escape}</td>
          <td class="px-4 py-3">{if $sp.created}{$sp.created|date_format:"%b %e"}{else}—{/if}</td>
          <td class="px-4 py-3">{if $sp.updated}{$sp.updated|date_format:"%b %e"}{else}—{/if}</td>
          <td class="px-4 py-3">
            <div class="relative" x-data="{ldelim}o:false{rdelim}">
              <button type="button" class="px-2 py-1.5 text-sm rounded-lg ring-1 ring-white/10 hover:bg-white/10" @click="o=!o">⋯</button>
              <div x-show="o" @click.outside="o=false" class="absolute right-0 z-10 mt-1 w-48 rounded-xl bg-slate-900 ring-1 ring-white/10 shadow-xl p-1">
                {if $sp.active}
                <button type="button" class="w-full text-left px-3 py-2 rounded-lg hover:bg-white/10" @click="o=false; window.ebStripeActions && window.ebStripeActions.archiveProduct && window.ebStripeActions.archiveProduct('{$sp.id|escape}')">Archive product</button>
                {else}
                <button type="button" class="w-full text-left px-3 py-2 rounded-lg hover:bg-white/10" @click="o=false; window.ebStripeActions && window.ebStripeActions.unarchiveProduct && window.ebStripeActions.unarchiveProduct('{$sp.id|escape}')">Unarchive product</button>
                {/if}
                <button type="button" class="w-full text-left px-3 py-2 rounded-lg hover:bg-white/10 text-rose-300" @click="o=false; window.ebStripeActions && window.ebStripeActions.deleteProduct && window.ebStripeActions.deleteProduct('{$sp.id|escape}')">Delete product</button>
              </div>
            </div>
          </td>
        </tr>
        {/foreach}
      </tbody>
    </table>
  </div>

  {* Include product slide-over to support Create product *}
  <div id="eb-product-panel" class="hidden fixed inset-0 z-50" x-data="productPanelFactory({ currency: '{$msp.default_currency|default:'CAD'}', ready: {$msp_ready|default:0} })">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close()"></div>
    <div class="absolute inset-y-0 right-0 w-full max-w-3xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 shadow-2xl shadow-black/40 flex flex-col">
      <div class="px-6 py-5 flex items-center justify-between">
        <h3 class="text-lg font-medium" x-text="mode==='create'?'Create a product':(mode==='editStripe'?'Update a product':'Update a product')"></h3>
        <button class="text-white/70 hover:text-white" @click="close()">✕</button>
      </div>
      <div class="border-t border-white/10"></div>
      <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6 text-sm">
        <div>
          <label class="block"><span class="text-sm text-white/70">Name (required)</span><input x-model="product.name" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5" /></label>
        </div>
        <div>
          <label class="block"><span class="text-sm text-white/70">Description</span><textarea x-model="product.description" rows="3" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 focus:ring-2 focus:outline-none px-3.5 py-2.5"></textarea></label>
        </div>
        <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-4">
          <div class="mb-2">
            <h4 class="text-sm font-medium">Product type</h4>
            <p class="text-xs text-white/60">Choose the resource this product represents. Prices will be variants of this resource.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <template x-for="opt in ['STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC']" :key="opt">
              <button type="button" @click="selectProductType(opt)" :class="baseMetric===opt ? 'bg-gradient-to-r from-sky-500 to-indigo-500 text-white shadow-sm shadow-sky-900/40 ring-1 ring-sky-400/60' : 'bg-slate-900/60 text-slate-300 border border-slate-700 hover:bg-slate-800 hover:border-slate-500'" class="px-4 py-2 text-xs font-medium rounded-xl transition-all duration-150" x-text="metricLabel(opt)"></button>
            </template>
          </div>
        </div>
        <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-4">
          <div class="mb-2 flex items-center justify-between">
            <h4 class="text-sm font-medium">Pricing</h4>
            <div class="flex items-center gap-3">
              <button type="button" class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" @click="addEmptyItem()">Add price</button>
            </div>
          </div>
          <template x-if="items.length===0"><div class="text-xs text-white/60">No prices yet.</div></template>
          <div class="space-y-3">
            <template x-for="(it, i) in items" :key="'pr-'+i">
              <div class="rounded-xl ring-1 ring-white/10 p-3">
                <div class="flex items-center justify-between">
                  <div class="font-medium" x-text="it.label||'Price'"></div>
                  <div class="flex items-center gap-2">
                    <button type="button" class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" @click="openPrice(i)">Edit</button>
                  </div>
                </div>
                <div class="mt-2 text-xs text-white/70">
                  <span x-text="billingLabel(it.billingType)"></span>
                  · <span x-text="metricLabel(it.metric)"></span>
                  · <span x-text="(it.billingType==='one_time' ? 'one-time' : (it.interval||'month'))"></span>
                  · <span x-text="currency + ' ' + Number(it.amount||0).toFixed(2)"></span>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-5 flex items-center justify-end gap-3">
        <button type="button" class="px-4 py-2 rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800" @click="close()">Cancel</button>
        <button type="button" class="px-4 py-2 rounded-xl bg-[rgb(var(--accent))] text-white" @click="save()">Save</button>
      </div>
    </div>
  </div>
</div>

