{* Stripe-style Products list *}
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6" x-data="{ q:'', matches(n){ if(!this.q) return true; try{ return String(n||'').toLowerCase().indexOf(String(this.q).toLowerCase())>=0; }catch(_){ return true; } } }">
    <input type="hidden" id="eb-token" value="{$token}" />
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
      <div>
        <h2 class="text-2xl font-semibold text-white">Catalog — Products</h2>
        <p class="text-xs text-slate-400 mt-1">{$company|default:'Connected account'|escape} · Stripe products and prices.</p>
        <div class="mt-2 flex items-center gap-3">
          <span class="text-xs px-2 py-0.5 rounded-full font-medium {if $msp_ready}bg-emerald-500/15 text-emerald-200 ring-1 ring-emerald-400/30{else}bg-amber-500/15 text-amber-200 ring-1 ring-amber-400/30{/if}">{if $msp_ready}Stripe enabled{else}Stripe disabled{/if}</span>
          <span class="text-xs text-slate-500">Payouts {if $acct_info.payouts_enabled}<span class="text-emerald-300">active</span>{else}<span class="text-amber-300">disabled</span>{/if}</span>
          <span class="text-xs text-slate-500">Payments {if $acct_info.charges_enabled}<span class="text-emerald-300">active</span>{else}<span class="text-amber-300">disabled</span>{/if}</span>
        </div>
      </div>
      <div class="flex items-center gap-3 shrink-0">
        <input x-model="q" placeholder="Search products" class="w-full sm:w-64 px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" />
        <button type="button" id="eb-open-create-product" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Create product</button>
      </div>
    </div>

    <div class="mb-4 flex items-center justify-end gap-2">
      <a class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-900/70 text-slate-300 hover:bg-slate-800" href="{$modulelink}&a=ph-catalog-export-prices" target="_blank">Export prices</a>
      <a class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-900/70 text-slate-300 hover:bg-slate-800" href="{$modulelink}&a=ph-catalog-export-products" target="_blank">Export products</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs text-slate-400">All</div>
        <div class="text-lg font-semibold text-slate-100">{$count_all|default:0}</div>
      </div>
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs text-slate-400">Active</div>
        <div class="text-lg font-semibold text-slate-100">{$count_active|default:0}</div>
      </div>
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs text-slate-400">Archived</div>
        <div class="text-lg font-semibold text-slate-100">{$count_archived|default:0}</div>
      </div>
    </div>

    <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
      <div class="overflow-x-auto rounded-lg border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/80 text-slate-300">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Name</th>
              <th class="px-4 py-3 text-left font-medium">Pricing</th>
              <th class="px-4 py-3 text-left font-medium">Created</th>
              <th class="px-4 py-3 text-left font-medium">Updated</th>
              <th class="px-4 py-3 text-left font-medium"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            {foreach from=$stripe_products item=sp}
            <tr x-show="matches('{$sp.name|escape}')" class="hover:bg-slate-800/50">
              <td class="px-4 py-3 text-left"><a class="text-sky-400 hover:underline font-medium text-slate-100" href="{$modulelink}&a=ph-catalog-product&id={$sp.id|escape}">{$sp.name|escape}</a> {if !$sp.active}<span class="ml-2 text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-300">Archived</span>{/if}</td>
              <td class="px-4 py-3 text-left text-slate-300">{$sp.pricing_summary|escape}</td>
              <td class="px-4 py-3 text-left text-slate-300">{if $sp.created}{$sp.created|date_format:"%b %e"}{else}—{/if}</td>
              <td class="px-4 py-3 text-left text-slate-300">{if $sp.updated}{$sp.updated|date_format:"%b %e"}{else}—{/if}</td>
              <td class="px-4 py-3 text-left">
                <div class="relative" x-data="{ldelim}o:false{rdelim}">
                  <button type="button" class="px-3 py-1.5 text-xs bg-slate-700 rounded text-white hover:bg-slate-600 cursor-pointer" @click="o=!o">⋯</button>
                  <div x-show="o" @click.outside="o=false" class="absolute right-0 z-50 mt-2 w-48 rounded-xl border border-slate-700 bg-slate-900 shadow-2xl overflow-hidden p-1">
                    {if $sp.active}
                    <button type="button" class="w-full text-left px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/60 rounded-lg" @click="o=false; window.ebStripeActions && window.ebStripeActions.archiveProduct && window.ebStripeActions.archiveProduct('{$sp.id|escape}')">Archive product</button>
                    {else}
                    <button type="button" class="w-full text-left px-4 py-2 text-sm text-slate-200 hover:bg-slate-800/60 rounded-lg" @click="o=false; window.ebStripeActions && window.ebStripeActions.unarchiveProduct && window.ebStripeActions.unarchiveProduct('{$sp.id|escape}')">Unarchive product</button>
                    {/if}
                    <button type="button" class="w-full text-left px-4 py-2 text-sm text-rose-300 hover:bg-slate-800/60 rounded-lg" @click="o=false; window.ebStripeActions && window.ebStripeActions.deleteProduct && window.ebStripeActions.deleteProduct('{$sp.id|escape}')">Delete product</button>
                  </div>
                </div>
              </td>
            </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    </div>

    {* Product slide-over panel *}
    <div id="eb-product-panel" class="hidden fixed inset-0 z-50" x-data="productPanelFactory({ currency: '{$msp.default_currency|default:'CAD'}', ready: {$msp_ready|default:0} })">
      <div class="absolute inset-0 bg-gray-950/70 backdrop-blur-sm" @click="close()"></div>
      <div class="absolute inset-y-0 right-0 w-full max-w-3xl bg-slate-900 border-l border-slate-800 shadow-2xl flex flex-col">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-800">
          <h3 class="text-lg font-semibold text-slate-100" x-text="mode==='create'?'Create a product':(mode==='editStripe'?'Update a product':'Update a product')"></h3>
          <button type="button" class="text-slate-400 hover:text-white" @click="close()">✕</button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6 text-sm">
          <div>
            <label class="block"><span class="text-sm text-slate-400">Name (required)</span><input x-model="product.name" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" /></label>
          </div>
          <div>
            <label class="block"><span class="text-sm text-slate-400">Description</span><textarea x-model="product.description" rows="3" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition"></textarea></label>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="mb-2">
              <h4 class="text-sm font-medium text-slate-100">Product type</h4>
              <p class="text-xs text-slate-400">Choose the resource this product represents. Prices will be variants of this resource.</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <template x-for="opt in ['STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC']" :key="opt">
                <button type="button" @click="selectProductType(opt)" :class="baseMetric===opt ? 'bg-sky-600 text-white ring-1 ring-sky-400/50' : 'bg-slate-800 text-slate-300 border border-slate-700 hover:bg-slate-700'" class="px-4 py-2 text-xs font-medium rounded-lg transition" x-text="metricLabel(opt)"></button>
              </template>
            </div>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="mb-2 flex items-center justify-between">
              <h4 class="text-sm font-medium text-slate-100">Pricing</h4>
              <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700" @click="addEmptyItem()">Add price</button>
            </div>
            <template x-if="items.length===0"><div class="text-xs text-slate-400">No prices yet.</div></template>
            <div class="space-y-3">
              <template x-for="(it, i) in items" :key="'pr-'+i">
                <div class="rounded-lg border border-slate-700 p-3">
                  <div class="flex items-center justify-between">
                    <div class="font-medium text-slate-100" x-text="it.label||'Price'"></div>
                    <button type="button" class="text-xs px-3 py-1.5 rounded bg-slate-700 text-white hover:bg-slate-600" @click="openPrice(i)">Edit</button>
                  </div>
                  <div class="mt-2 text-xs text-slate-400">
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
        <div class="border-t border-slate-800 px-6 py-5 flex items-center justify-end gap-3">
          <button type="button" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm" @click="close()">Cancel</button>
          <button type="button" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900" @click="save()">Save</button>
        </div>
      </div>
    </div>
  </div>
</div>

