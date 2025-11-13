{* Partner Hub — Catalog: Products *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
  <div class="mx-auto max-w-5xl px-6 py-8">
    <input type="hidden" id="eb-token" value="{$token}" />
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Catalog — Products</h1>
      <div class="flex items-center gap-3">
        <button id="eb-open-create-product" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">New Product</button>
      </div>
    </div>

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium">Products</h2>
      </div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 space-y-6">
          {foreach from=$products item=p}
          {assign var=list value=$priceMap[$p.id]|default:[]}
          <div class="rounded-2xl ring-1 ring-white/10 p-4 bg-white/5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-white/10 flex items-center justify-center">📦</div>
                <div>
                  <div class="text-base font-semibold">{$p.name|escape}</div>
                  <div class="text-xs text-white/60">{if $p.description}{$p.description|escape}{else}No description{/if}</div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {if $p.active}bg-emerald-500/10 text-emerald-300 ring-emerald-400/20{else}bg-white/5 text-white/70 ring-white/10{/if} ring-1">{if $p.active}Active{else}Inactive{/if}</span>
                {if $p.stripe_product_id}
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-300 ring-1 ring-emerald-400/20">Published</span>
                {else}
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white/5 text-white/70 ring-1 ring-white/10">Draft</span>
                {/if}
              </div>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-6">
              <div class="md:col-span-8">
                <div class="text-sm font-medium mb-2">Pricing</div>
                <table class="w-full text-sm">
                  <thead class="bg-white/5 text-white/70">
                    <tr class="text-left">
                      <th class="px-3 py-2 font-medium">Price</th>
                      <th class="px-3 py-2 font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-white/10">
                  {foreach from=$list item=pr}
                      <tr>
                        <td class="px-3 py-2">
                          {$pr.currency|escape} {($pr.unit_amount/100)|string_format:"%.2f"}
                          {if $pr.unit_label} / {$pr.unit_label|escape}{/if}
                          {if $pr.kind ne 'one_time'} / month{else} / one-time{/if}
                        </td>
                        <td class="px-3 py-2">
                          {if $pr.active}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-500/10 text-emerald-300 ring-1 ring-emerald-400/20">Active</span>
                          {else}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-white/5 text-white/70 ring-1 ring-white/10">Archived</span>
                          {/if}
                        </td>
                      </tr>
                  {/foreach}
                  </tbody>
                </table>
              </div>
              <div class="md:col-span-4">
                <div class="text-sm font-medium mb-2">Details</div>
                <div class="space-y-3 text-sm">
                  <div>
                    <div class="text-white/60">Product ID</div>
                    <div class="font-mono text-white/80">{if $p.stripe_product_id}{$p.stripe_product_id|escape}{else}—{/if}</div>
                  </div>
                  <div>
                    <div class="text-white/60">Marketing feature list</div>
                    {assign var=__features value=$p.features|default:[]}
                    {if $__features|@count > 0}
                      <ul class="list-disc ml-5 mt-1 space-y-0.5">{foreach from=$__features item=f}<li>{$f|escape}</li>{/foreach}</ul>
                    {else}
                      <div>—</div>
                    {/if}
                  </div>
                  <div>
                    <div class="text-white/60">Description</div>
                    <div>{if $p.description}{$p.description|escape}{else}—{/if}</div>
                  </div>
                  <div>
                    <div class="text-white/60">Attributes</div>
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
    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium">Stripe — Connected Products</h2>
        <div class="text-sm text-white/60 mt-1">Products currently on your connected Stripe account.</div>
        </div>
        <div class="border-t border-white/10"></div>
      <div class="px-6 py-6 space-y-6">
        {foreach from=$stripe_products item=sp}
          {assign var=list value=$sp.prices|default:[]}
          <div class="rounded-2xl ring-1 ring-white/10 p-4 bg-white/5">
              <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-lg bg-white/10 flex items-center justify-center">📦</div>
                <div>
                  <div class="text-base font-semibold">{$sp.name|escape}</div>
                  <div class="text-xs text-white/60">{if $sp.description}{$sp.description|escape}{else}No description{/if}</div>
                </div>
                    </div>
              <div class="flex items-center gap-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {if $sp.active}bg-emerald-500/10 text-emerald-300 ring-emerald-400/20{else}bg-white/5 text-white/70 ring-white/10{/if} ring-1">{if $sp.active}Active{else}Inactive{/if}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-sky-500/10 text-sky-300 ring-1 ring-sky-400/20">Stripe</span>
                <button type="button" class="ml-2 px-3 py-1.5 text-xs rounded-lg ring-1 ring-white/10 hover:bg-white/10" data-eb-open-edit-stripe="{$sp.id|escape}">Edit product</button>
                <div class="relative" x-data="{ldelim}o:false{rdelim}">
                  <button type="button" class="px-2 py-1.5 text-sm rounded-lg ring-1 ring-white/10 hover:bg-white/10" @click.stop="o=!o">⋯</button>
                  <div x-show="o" @click.outside="o=false" class="absolute right-0 z-10 mt-1 w-48 rounded-xl bg-slate-900 ring-1 ring-white/10 shadow-xl p-1">
                    <button type="button" class="w-full text-left px-3 py-2 rounded-lg hover:bg-white/10" @click.stop="o=false; window.ebStripeActions && window.ebStripeActions.archiveProduct && window.ebStripeActions.archiveProduct('{$sp.id|escape}')">Archive product</button>
                    <button type="button" class="w-full text-left px-3 py-2 rounded-lg hover:bg-white/10 text-rose-300" @click.stop="o=false; window.ebStripeActions && window.ebStripeActions.deleteProduct && window.ebStripeActions.deleteProduct('{$sp.id|escape}')">Delete product</button>
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-6">
              <div class="md:col-span-8">
                <div class="text-sm font-medium mb-2">Pricing</div>
                <table class="w-full text-sm">
                  <thead class="bg-white/5 text-white/70">
                    <tr class="text-left">
                      <th class="px-3 py-2 font-medium">Price</th>
                      <th class="px-3 py-2 font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-white/10">
                    {foreach from=$list item=pr}
                      <tr>
                        <td class="px-3 py-2">
                          {$pr.currency|escape} {($pr.unit_amount/100)|string_format:"%.2f"}
                          {if $pr.unit_label} / {$pr.unit_label|escape}{/if}
                          {if $pr.kind ne 'one_time'} / month{else} / one-time{/if}
                        </td>
                        <td class="px-3 py-2">
                          {if $pr.active}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-500/10 text-emerald-300 ring-1 ring-emerald-400/20">Active</span>
                          {else}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-white/5 text-white/70 ring-1 ring-white/10">Archived</span>
                          {/if}
                        </td>
                      </tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>
              <div class="md:col-span-4">
                <div class="text-sm font-medium mb-2">Details</div>
                <div class="space-y-3 text-sm">
                  <div>
                    <div class="text-white/60">Product ID</div>
                    <div class="font-mono text-white/80">{$sp.id|escape}</div>
                  </div>
                  <div>
                    <div class="text-white/60">Marketing feature list</div>
                    {assign var=_spf value=$sp.features|default:[]}
                    {if $_spf|@count>0}
                      <ul class="list-disc ml-5 mt-1 space-y-0.5">{foreach from=$_spf item=f}<li>{$f|escape}</li>{/foreach}</ul>
                    {else}
                      <div>—</div>
                    {/if}
                  </div>
                  <div>
                    <div class="text-white/60">Description</div>
                    <div>{if $sp.description}{$sp.description|escape}{else}—{/if}</div>
          </div>
                  <div>
                    <div class="text-white/60">Attributes</div>
                    <div>—</div>
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
    <div id="eb-product-panel" class="hidden fixed inset-0 z-50" x-data="productPanelFactory({ currency: '{$msp_currency|default:'CAD'}', ready: {$msp_ready|default:0} })">
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
                <label class="text-xs text-white/70 flex items-center gap-1" x-show="mode==='editStripe'">
                  <input type="checkbox" class="align-middle" x-model="showInactive" @change="refreshStripePrices()"> Show inactive
                </label>
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
                      <div class="relative" x-data="{ldelim}o:false{rdelim}">
                        <button type="button" class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" @click="o=!o">⋯</button>
                        <div x-show="o" @click.outside="o=false" class="absolute right-0 z-10 mt-1 w-36 rounded bg-slate-900 ring-1 ring-white/10 p-1">
                          <button type="button" class="w-full text-left px-3 py-1.5 rounded hover:bg-white/10" @click="duplicatePrice(i); o=false">Duplicate price</button>
                          <button type="button" class="w-full text-left px-3 py-1.5 rounded hover:bg-white/10 text-rose-300" @click="removeItem(i); o=false">Remove</button>
                        </div>
                      </div>
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
          <div class="rounded-2xl bg-slate-900/60 border border-slate-800 p-4">
            <div class="mb-2">
              <h4 class="text-sm font-medium">Marketing feature list</h4>
              <p class="text-xs text-white/60">Displayed in pricing tables.</p>
            </div>
            <div class="space-y-2">
              <template x-for="(f, idx) in features" :key="'f-'+idx">
                <div class="flex items-center gap-2">
                  <input x-model="features[idx]" class="flex-1 rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 px-3 py-2" />
                  <button type="button" class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" @click="features.splice(idx,1)">Remove</button>
                </div>
              </template>
              <button type="button" class="text-xs px-2 py-1 rounded bg-white/10 hover:bg-white/20" @click="features.push('')">+ Add line</button>
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

    {* Price Slide-Over Panel *}
    <div id="eb-price-panel" class="hidden fixed inset-0 z-50" x-data="pricePanelFactory()">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close()"></div>
      <div class="absolute inset-y-0 right-0 w-full max-w-lg bg-[rgb(var(--bg-card))] ring-1 ring-white/10 shadow-2xl shadow-black/40 flex flex-col">
        <div class="px-6 py-5 flex items-center justify-between">
          <h3 class="text-lg font-medium">Edit price</h3>
          <button class="text-white/70 hover:text-white" @click="close()">✕</button>
        </div>
        <div class="border-t border-white/10"></div>
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-4 text-sm">
          <label class="block"><span class="text-sm text-white/70">Label</span><input x-model="row.label" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 px-3 py-2.5" /></label>
          <div class="grid grid-cols-2 gap-3">
            <label class="block"><span class="text-sm text-white/70">Amount (required)</span>
              <div class="mt-2 flex items-stretch rounded-xl overflow-hidden ring-1 ring-white/10 eb-bg-input">
                <span class="shrink-0 px-3 py-2.5 text-white/80 select-none">$</span>
                <input x-model.number="row.amount" type="text" inputmode="decimal" @blur="row.amount = Number(row.amount||0).toFixed(2)" class="flex-1 min-w-0 text-left bg-transparent text-white/90 placeholder-white/30 focus:outline-none focus:ring-0 px-3 py-2.5" />
                <span class="shrink-0 px-3 py-2.5 bg-slate-800 text-white/80 border-l border-white/10 select-none" x-text="(row.currency || (owner && owner.currency) || 'CAD')"></span>
              </div>
            </label>
            <label class="block"><span class="text-sm text-white/70">Interval</span>
              <select x-model="row.interval" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 px-3 py-2.5"><option value="month">Month</option><option value="year">Year</option><option value="none">None</option></select>
            </label>
          </div>
          <template x-if="owner && owner.baseMetric==='STORAGE_TB'">
            <div class="grid grid-cols-2 gap-3">
              <label class="block"><span class="text-sm text-white/70">Billing type</span><input disabled value="Metered" class="mt-2 w-full rounded-xl eb-bg-input text-white/50 ring-1 ring-white/10 px-3 py-2.5" /></label>
              <label class="block"><span class="text-sm text-white/70">Unit</span>
                <select x-model="row.unitLabel" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 px-3 py-2.5"><option value="GiB">GiB</option><option value="TiB">TiB</option></select>
              </label>
              <div class="col-span-2 text-xs text-white/60" x-text="row.unitLabel==='GiB' ? ('≈ '+(Number(row.amount||0)*1024).toFixed(2)+' per TiB') : ('≈ '+(Number(row.amount||0)/1024).toFixed(4)+' per GiB')"></div>
            </div>
          </template>
          <template x-if="owner && owner.baseMetric!=='STORAGE_TB' && owner.baseMetric!=='GENERIC'">
            <div class="grid grid-cols-2 gap-3">
              <label class="block"><span class="text-sm text-white/70">Billing type</span><input disabled value="Per-unit" class="mt-2 w-full rounded-xl eb-bg-input text-white/50 ring-1 ring-white/10 px-3 py-2.5" /></label>
              <label class="block"><span class="text-sm text-white/70">Unit label</span><input x-model="row.unitLabel" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 px-3 py-2.5" /></label>
            </div>
          </template>
          <template x-if="owner && owner.baseMetric==='GENERIC'">
            <div class="grid grid-cols-2 gap-3">
              <label class="block"><span class="text-sm text-white/70">Billing type</span>
                <select x-model="row.billingType" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 px-3 py-2.5"><option value="per_unit">Per-unit</option><option value="one_time">One-time</option></select>
          </label>
              <label class="block"><span class="text-sm text-white/70">Unit label</span><input x-model="row.unitLabel" class="mt-2 w-full rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 px-3 py-2.5" /></label>
            </div>
          </template>
          <div class="text-xs text-white/60">Currency: <span class="font-mono" x-text="(row.currency || (owner && owner.currency) || 'CAD')"></span></div>

          <div class="mt-6 rounded-2xl bg-slate-900/60 border border-slate-800 p-4">
            <h4 class="text-sm font-medium">Preview</h4>
            <p class="text-xs text-white/60">Estimate totals based on pricing model, unit quantity, and tax.</p>
            <div class="mt-3">
              <label class="block"><span class="text-sm text-white/70">Unit quantity</span>
                <input x-model.number="qty" type="number" min="0" step="1" class="mt-2 w-40 rounded-xl eb-bg-input text-white/90 ring-1 ring-white/10 px-3 py-2" />
          </label>
              <div class="my-3 h-px bg-white/10"></div>
              <div class="text-sm text-white/80">
                <span x-text="qty + ' ' + unitLabelDisplay()"></span>
                <span> × </span>
                <span x-text="fmtMoney(row.amount)"></span>
                <span> = </span>
                <span class="font-semibold" x-text="fmtMoney(calcSubtotal())"></span>
              </div>
              <div class="my-3 h-px bg-white/10"></div>
              <div class="grid grid-cols-2 text-sm">
                <div class="text-white/70">Subtotal</div>
                <div class="text-right" x-text="fmtMoney(calcSubtotal())"></div>
                <div class="text-white/70 flex items-center gap-2">Tax <a href="#" class="text-sky-400 hover:underline">Start collecting tax</a></div>
                <div class="text-right">—</div>
                <div class="text-white/70">Total</div>
                <div class="text-right font-medium" x-text="fmtMoney(calcSubtotal())"></div>
              </div>
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
</div>

<script src="modules/addons/eazybackup/templates/assets/js/ui.js"></script>
<script src="modules/addons/eazybackup/assets/js/catalog-products.js"></script>

<style>
/* Hide native number spinners inside the product builder stepper */
.eb-no-spinner::-webkit-outer-spin-button,
.eb-no-spinner::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.eb-no-spinner { -moz-appearance: textfield; appearance: textfield; }
</style>


