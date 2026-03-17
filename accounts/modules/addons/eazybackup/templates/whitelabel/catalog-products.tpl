{* Partner Hub — Catalog: Products *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
<script src="modules/addons/eazybackup/assets/js/catalog-products.js"></script>

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div id="toast-container" x-data="catalogToastManager()" x-init="init()" @catalog:toast.window="push($event.detail)" class="fixed top-4 right-4 z-[9999] space-y-2 pointer-events-none">
    <template x-for="toast in toasts" :key="toast.id">
      <div x-show="toast.visible" x-transition.opacity.duration.200ms class="pointer-events-auto px-4 py-2 rounded shadow text-sm text-white" :class="toast.type==='success' ? 'bg-green-600' : (toast.type==='error' ? 'bg-red-600' : (toast.type==='warning' ? 'bg-yellow-600' : 'bg-slate-700'))" x-text="toast.message"></div>
    </template>
  </div>
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='catalog-products'}
        <main class="flex-1 min-w-0 overflow-x-auto">
          <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
            <div>
              <h1 class="text-2xl font-semibold tracking-tight">Catalog - Products</h1>
              <p class="mt-1 text-sm text-slate-400">Create and manage products and prices.</p>
            </div>
            <div class="shrink-0">
              <button type="button" id="eb-open-create-product" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">New Product</button>
            </div>
          </div>
          <div class="p-6">
    <input type="hidden" id="eb-token" value="{$token}" />

    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800">
        <h2 class="text-lg font-medium text-slate-100">Products</h2>
      </div>
      <div class="px-6 py-6 space-y-6">
          {foreach from=$products item=p}
          {assign var=list value=$priceMap[$p.id]|default:[]}
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-white/10 flex items-center justify-center">📦</div>
                <div>
                  <div class="text-base font-semibold text-slate-100">{$p.name|escape}</div>
                  <div class="text-xs text-slate-400">{if $p.description}{$p.description|escape}{else}No description{/if}</div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $p.active}bg-emerald-500/15 text-emerald-200{else}bg-slate-700 text-slate-300{/if}"><span class="h-1.5 w-1.5 rounded-full {if $p.active}bg-emerald-400{else}bg-slate-500{/if}"></span>{if $p.active}Active{else}Inactive{/if}</span>
                {if $p.stripe_product_id}
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-500/15 text-emerald-200">Published</span>
                {else}
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-700 text-slate-300">Draft</span>
                {/if}
              </div>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-6">
              <div class="md:col-span-8">
                <div class="text-sm font-medium text-slate-300 mb-2">Pricing</div>
                <table class="w-full text-sm divide-y divide-slate-800">
                  <thead class="bg-slate-900/80 text-slate-300">
                    <tr class="text-left">
                      <th class="px-4 py-3 font-medium">Price</th>
                      <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-800">
                  {foreach from=$list item=pr}
                      <tr class="hover:bg-slate-800/50">
                        <td class="px-4 py-3 text-left text-slate-300">
                          {$pr.currency|escape} {($pr.unit_amount/100)|string_format:"%.2f"}
                          {if $pr.unit_label} / {$pr.unit_label|escape}{/if}
                          {if $pr.kind ne 'one_time'} / month{else} / one-time{/if}
                        </td>
                        <td class="px-4 py-3 text-left">
                          {if $pr.active}
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-500/15 text-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Active</span>
                          {else}
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-700 text-slate-300">Archived</span>
                          {/if}
                        </td>
                      </tr>
                  {/foreach}
                  </tbody>
                </table>
              </div>
              <div class="md:col-span-4">
                <div class="text-sm font-medium text-slate-300 mb-2">Details</div>
                <div class="space-y-3 text-sm">
                  <div>
                    <div class="text-slate-400">Product ID</div>
                    <div class="font-mono text-slate-100">{if $p.stripe_product_id}{$p.stripe_product_id|escape}{else}—{/if}</div>
                  </div>
                  <div>
                    <div class="text-slate-400">Marketing feature list</div>
                    {assign var=__features value=$p.features|default:[]}
                    {if $__features|@count > 0}
                      <ul class="list-disc ml-5 mt-1 space-y-0.5">{foreach from=$__features item=f}<li>{$f|escape}</li>{/foreach}</ul>
                    {else}
                      <div>—</div>
                    {/if}
                  </div>
                  <div>
                    <div class="text-slate-400">Description</div>
                    <div class="text-slate-300">{if $p.description}{$p.description|escape}{else}—{/if}</div>
                  </div>
                  <div>
                    <div class="text-slate-400">Attributes</div>
                    <div>—</div>
                  </div>
                </div>
              </div>
            </div>
            </div>
          {/foreach}
      </div>
    </section>

    {if $stripe_products|default:[]|@count > 0}
    <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800">
        <h2 class="text-lg font-medium text-slate-100">Stripe — Connected Products</h2>
        <p class="text-sm text-slate-400 mt-1">Products currently on your connected Stripe account.</p>
      </div>
      <div class="px-6 py-6 space-y-6">
        {foreach from=$stripe_products item=sp}
          {assign var=list value=$sp.prices|default:[]}
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
              <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-slate-700 flex items-center justify-center">📦</div>
                <div>
                  <div class="text-base font-semibold text-slate-100">{$sp.name|escape}</div>
                  <div class="text-xs text-slate-400">{if $sp.description}{$sp.description|escape}{else}No description{/if}</div>
                </div>
                    </div>
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $sp.active}bg-emerald-500/15 text-emerald-200{else}bg-slate-700 text-slate-300{/if}"><span class="h-1.5 w-1.5 rounded-full {if $sp.active}bg-emerald-400{else}bg-slate-500{/if}"></span>{if $sp.active}Active{else}Inactive{/if}</span>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-sky-500/15 text-sky-200">Stripe</span>
                <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-600 bg-slate-800/80 px-3 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900" data-eb-open-edit-stripe="{$sp.id|escape}">Edit product</button>
                <div class="relative" x-data="{ldelim}o:false{rdelim}">
                  <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-600 bg-slate-800/80 px-2 py-1.5 text-xs font-medium text-slate-200 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900" @click.stop="o=!o">⋯</button>
                  <div x-show="o" @click.outside="o=false" class="absolute right-0 z-10 mt-1 w-48 rounded-xl border border-slate-700 bg-slate-900 shadow-xl p-1">
                    <button type="button" class="w-full text-left px-3 py-2 rounded-lg text-slate-200 hover:bg-slate-800" @click.stop="o=false; window.ebStripeActions && window.ebStripeActions.archiveProduct && window.ebStripeActions.archiveProduct('{$sp.id|escape}')">Archive product</button>
                    <button type="button" class="w-full text-left px-3 py-2 rounded-lg text-rose-300 hover:bg-slate-800" @click.stop="o=false; window.ebStripeActions && window.ebStripeActions.deleteProduct && window.ebStripeActions.deleteProduct('{$sp.id|escape}')">Delete product</button>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-6">
              <div class="md:col-span-8">
                <div class="text-sm font-medium text-slate-300 mb-2">Pricing</div>
                <table class="w-full text-sm divide-y divide-slate-800">
                  <thead class="bg-slate-900/80 text-slate-300">
                    <tr class="text-left">
                      <th class="px-4 py-3 font-medium">Price</th>
                      <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-800">
                    {foreach from=$list item=pr}
                      <tr class="hover:bg-slate-800/50">
                        <td class="px-4 py-3 text-left text-slate-300">
                          {$pr.currency|escape} {($pr.unit_amount/100)|string_format:"%.2f"}
                          {if $pr.unit_label} / {$pr.unit_label|escape}{/if}
                          {if $pr.kind ne 'one_time'} / month{else} / one-time{/if}
                        </td>
                        <td class="px-4 py-3 text-left">
                          {if $pr.active}
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold bg-emerald-500/15 text-emerald-200"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>Active</span>
                          {else}
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-700 text-slate-300">Archived</span>
                          {/if}
                        </td>
                      </tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>
              <div class="md:col-span-4">
                <div class="text-sm font-medium text-slate-300 mb-2">Details</div>
                <div class="space-y-3 text-sm">
                  <div>
                    <div class="text-slate-400">Product ID</div>
                    <div class="font-mono text-slate-100">{$sp.id|escape}</div>
                  </div>
                  <div>
                    <div class="text-slate-400">Marketing feature list</div>
                    {assign var=_spf value=$sp.features|default:[]}
                    {if $_spf|@count>0}
                      <ul class="list-disc ml-5 mt-1 space-y-0.5 text-slate-300">{foreach from=$_spf item=f}<li>{$f|escape}</li>{/foreach}</ul>
                    {else}
                      <div class="text-slate-400">—</div>
                    {/if}
                  </div>
                  <div>
                    <div class="text-slate-400">Description</div>
                    <div class="text-slate-300">{if $sp.description}{$sp.description|escape}{else}—{/if}</div>
          </div>
                  <div>
                    <div class="text-slate-400">Attributes</div>
                    <div class="text-slate-300">—</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        {/foreach}
      </div>
    </section>
    {/if}


    {* Product Slide-Over Panel (Create / Edit) *}
    <div id="eb-product-panel" class="hidden fixed inset-0 z-50" x-data="(window.productPanelFactory||function(){ return { items: [], baseMetric: '', metricLabel: function() { return ''; } }; })({ currency: '{$msp_currency|default:'CAD'}', ready: {$msp_ready|default:0} })">
      <div class="absolute inset-0 bg-gray-950/70 backdrop-blur-xs" @click="close()"></div>
      <div class="absolute inset-y-0 right-0 w-full max-w-3xl rounded-l-xl border border-slate-700 bg-slate-900 shadow-2xl flex flex-col">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-800">
          <h3 class="text-lg font-medium text-slate-100" x-text="mode==='create'?'Create a product':(mode==='editStripe'?'Update a product':'Update a product')"></h3>
          <button type="button" class="text-slate-400 hover:text-white" @click="close()">✕</button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6 text-sm">
          <div>
            <label class="block"><span class="text-sm text-slate-400">Name (required)</span><input x-model="product.name" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" /></label>
                    </div>
          <div>
            <label class="block"><span class="text-sm text-slate-400">Description</span><textarea x-model="product.description" rows="3" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition"></textarea></label>
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
                <label class="text-xs text-slate-400 flex items-center gap-1" x-show="mode==='editStripe'">
                  <input type="checkbox" class="align-middle" x-model="showInactive" @change="refreshStripePrices()"> Show inactive
                </label>
                <button type="button" class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" @click="addEmptyItem()">Add price</button>
              </div>
            </div>
            <template x-if="items.length===0"><div class="text-xs text-white/60">No prices yet.</div></template>
            <div class="space-y-3">
              <template x-for="(it, i) in items" :key="'pr-'+i">
                <div class="rounded-xl border border-slate-700 bg-slate-950/40 p-4 space-y-4">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <div class="font-medium text-slate-100" x-text="it.label || ('Price ' + (i + 1))"></div>
                      <div class="mt-1 text-xs text-slate-400">
                        <span x-text="billingLabel(it.billingType)"></span>
                        · <span x-text="metricLabel(it.metric)"></span>
                        · <span x-text="it.billingType==='one_time' ? 'one-time' : (it.interval || 'month')"></span>
                        · <span x-text="currency + ' ' + Number(it.amount || 0).toFixed(2)"></span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <button type="button" class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" @click="duplicatePrice(i)">Duplicate</button>
                      <button type="button" class="text-xs px-2 py-1 rounded bg-rose-500/15 text-rose-200 hover:bg-rose-500/25" @click="removeItem(i)">Remove</button>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="block">
                      <span class="text-sm text-slate-400">Label</span>
                      <input x-model="it.label" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" />
                    </label>
                    <label class="block">
                      <span class="text-sm text-slate-400">Amount</span>
                      <div class="mt-2 flex items-stretch rounded-lg overflow-hidden bg-slate-800 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700">
                        <span class="shrink-0 px-3 py-2.5 text-slate-400 select-none">$</span>
                        <input x-model.number="it.amount" type="text" inputmode="decimal" @blur="normalizePriceRow(i)" class="flex-1 min-w-0 text-left bg-transparent text-slate-100 placeholder-gray-400 focus:outline-none px-3 py-2.5" />
                        <span class="shrink-0 px-3 py-2.5 bg-slate-800 text-slate-400 border-l border-slate-700 select-none" x-text="currency"></span>
                      </div>
                    </label>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="block">
                      <span class="text-sm text-slate-400">Interval</span>
                      <select x-model="it.interval" :disabled="it.billingType==='one_time'" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 disabled:bg-slate-800/60 disabled:text-slate-500">
                        <option value="month">Month</option>
                        <option value="year">Year</option>
                        <option value="none">None</option>
                      </select>
                    </label>

                    <template x-if="baseMetric==='GENERIC'">
                      <label class="block">
                        <span class="text-sm text-slate-400">Billing type</span>
                        <select x-model="it.billingType" @change="onInlineBillingTypeChange(i)" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700">
                          <option value="per_unit">Per-unit</option>
                          <option value="one_time">One-time</option>
                        </select>
                      </label>
                    </template>

                    <template x-if="baseMetric!=='GENERIC'">
                      <label class="block">
                        <span class="text-sm text-slate-400">Billing type</span>
                        <input :value="billingLabel(it.billingType)" disabled class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800/70 text-sm text-slate-500" />
                      </label>
                    </template>
                  </div>

                  <template x-if="baseMetric==='STORAGE_TB'">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <label class="block">
                        <span class="text-sm text-slate-400">Unit</span>
                        <select x-model="it.unitLabel" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700">
                          <option value="GiB">GiB</option>
                          <option value="TiB">TiB</option>
                        </select>
                      </label>
                      <div class="rounded-lg border border-slate-800 bg-slate-900/70 px-3 py-2.5 text-xs text-slate-400 self-end">
                        <span x-text="it.unitLabel==='GiB' ? ('≈ ' + (Number(it.amount || 0) * 1024).toFixed(2) + ' per TiB') : ('≈ ' + (Number(it.amount || 0) / 1024).toFixed(4) + ' per GiB')"></span>
                      </div>
                    </div>
                  </template>

                  <template x-if="baseMetric!=='STORAGE_TB'">
                    <label class="block">
                      <span class="text-sm text-slate-400">Unit label</span>
                      <input x-model="it.unitLabel" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" />
                    </label>
                  </template>
                </div>
              </template>
            </div>
          </div>
          <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-4">
            <div class="mb-2">
              <h4 class="text-sm font-medium">Marketing feature list</h4>
              <p class="text-xs text-white/60">Displayed in pricing tables.</p>
            </div>
            <div class="space-y-2">
              <template x-for="(f, idx) in features" :key="'f-'+idx">
                <div class="flex items-center gap-2">
                  <input x-model="features[idx]" class="flex-1 rounded-lg bg-slate-800 text-sm text-slate-100 px-3 py-2 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700 transition" />
                  <button type="button" class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" @click="features.splice(idx,1)">Remove</button>
                </div>
              </template>
              <button type="button" class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" @click="features.push('')">+ Add line</button>
            </div>
          </div>
        </div>
        <div class="border-t border-slate-800"></div>
        <div class="px-6 py-5 flex items-center justify-end gap-3">
          <button type="button" class="px-4 py-2 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-800" @click="close()">Cancel</button>
          <button type="button" class="px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500" @click="save()">Save</button>
        </div>
      </div>
    </div>

          </div>
        </main>
      </div>
    </div>
  </div>
</div>

<script src="modules/addons/eazybackup/templates/assets/js/ui.js"></script>

<style>
/* Hide native number spinners inside the product builder stepper */
.eb-no-spinner::-webkit-outer-spin-button,
.eb-no-spinner::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.eb-no-spinner { -moz-appearance: textfield; appearance: textfield; }
</style>


