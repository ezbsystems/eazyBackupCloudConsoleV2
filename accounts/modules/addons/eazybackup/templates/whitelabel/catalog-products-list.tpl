{* Stripe-style Products list *}
<script src="modules/addons/eazybackup/assets/js/catalog-products.js"></script>
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div id="toast-container" x-data="catalogToastManager()" x-init="init()" @catalog:toast.window="push($event.detail)" class="fixed top-4 right-4 z-[9999] space-y-2 pointer-events-none">
    <template x-for="toast in toasts" :key="toast.id">
      <div x-show="toast.visible" x-transition.opacity.duration.200ms class="pointer-events-auto px-4 py-2 rounded shadow text-sm text-white" :class="toast.type==='success' ? 'bg-green-600' : (toast.type==='error' ? 'bg-red-600' : (toast.type==='warning' ? 'bg-yellow-600' : 'bg-slate-700'))" x-text="toast.message"></div>
    </template>
  </div>
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6" x-data="{ q:'', statusFilter:'all', typeFilter:'all', matches(n, status, type){ if(this.statusFilter!=='all' && status!==this.statusFilter) return false; if(this.typeFilter!=='all' && type!==this.typeFilter) return false; if(!this.q) return true; try{ return String(n||'').toLowerCase().indexOf(String(this.q).toLowerCase())>=0; }catch(_){ return true; } } }">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='catalog-products'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Catalog - Products</h1>
        <p class="mt-1 text-sm text-slate-400">{$company|default:'Connected account'|escape} · Stripe products and prices.</p>
        <div class="mt-2 flex items-center gap-3">
          <span class="text-xs px-2 py-0.5 rounded-full font-medium {if $msp_ready}bg-emerald-500/15 text-emerald-200 ring-1 ring-emerald-400/30{else}bg-amber-500/15 text-amber-200 ring-1 ring-amber-400/30{/if}">{if $msp_ready}Stripe enabled{else}Stripe disabled{/if}</span>
          <span class="text-xs text-slate-500">Payouts {if $acct_info.payouts_enabled}<span class="text-emerald-300">active</span>{else}<span class="text-amber-300">disabled</span>{/if}</span>
          <span class="text-xs text-slate-500">Payments {if $acct_info.charges_enabled}<span class="text-emerald-300">active</span>{else}<span class="text-amber-300">disabled</span>{/if}</span>
        </div>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <a class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-900/70 text-slate-300 hover:bg-slate-800" href="{$modulelink}&a=ph-catalog-export-prices" target="_blank">Export prices</a>
        <a class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-900/70 text-slate-300 hover:bg-slate-800" href="{$modulelink}&a=ph-catalog-export-products" target="_blank">Export products</a>
        <button type="button" id="eb-open-create-product" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Create product</button>
      </div>
    </div>
    <div class="p-6">
    <input type="hidden" id="eb-token" value="{$token}" />

    <div class="mb-4 flex items-center justify-end">
      <input x-model="q" placeholder="Search products" class="w-full sm:w-64 px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs text-slate-400">All</div>
        <div class="text-lg font-semibold text-slate-100">{$count_all|default:0}</div>
      </div>
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs text-slate-400">Active</div>
        <div class="text-lg font-semibold text-slate-100">{$count_active|default:0}</div>
      </div>
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs text-slate-400">Draft</div>
        <div class="text-lg font-semibold text-slate-100">{$count_draft|default:0}</div>
      </div>
      <div class="rounded-xl border border-slate-700 bg-slate-800/50 px-4 py-3">
        <div class="text-xs text-slate-400">Archived</div>
        <div class="text-lg font-semibold text-slate-100">{$count_archived|default:0}</div>
      </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
      <div class="flex items-center rounded-lg border border-slate-700 bg-slate-800/50 p-0.5">
        <button type="button" @click="statusFilter='all'" :class="statusFilter==='all' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'" class="px-3 py-1.5 text-xs font-medium rounded-md transition">All</button>
        <button type="button" @click="statusFilter='active'" :class="statusFilter==='active' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'" class="px-3 py-1.5 text-xs font-medium rounded-md transition">Active</button>
        <button type="button" @click="statusFilter='draft'" :class="statusFilter==='draft' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'" class="px-3 py-1.5 text-xs font-medium rounded-md transition">Draft</button>
        <button type="button" @click="statusFilter='archived'" :class="statusFilter==='archived' ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'" class="px-3 py-1.5 text-xs font-medium rounded-md transition">Archived</button>
      </div>
      <select x-model="typeFilter" class="px-3 py-1.5 rounded-lg bg-slate-800 text-xs text-slate-300 border border-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-600">
        <option value="all">All Types</option>
        <option value="STORAGE_TB">Storage</option>
        <option value="DEVICE_COUNT">Device Count</option>
        <option value="DISK_IMAGE">Disk Image</option>
        <option value="HYPERV_VM">Hyper-V VM</option>
        <option value="PROXMOX_VM">Proxmox VM</option>
        <option value="VMWARE_VM">VMware VM</option>
        <option value="M365_USER">Microsoft 365 User</option>
        <option value="GENERIC">Generic</option>
      </select>
    </div>

    {if $products|default:[]|@count > 0}
    <section class="mb-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800">
        <h2 class="text-lg font-medium text-slate-100">Products</h2>
        <p class="text-sm text-slate-400 mt-1">Local catalog products. Publish to Stripe when ready.</p>
      </div>
      <div class="px-6 py-6 space-y-6">
        {foreach from=$products item=p}
        {assign var=list value=$priceMap[$p.id]|default:[]}
        <div x-show="matches('{$p.name|escape}', '{if $p.stripe_product_id}active{elseif $p.active}draft{else}archived{/if}', '{$p.base_metric_code|default:'GENERIC'|escape}')" class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="h-10 w-10 rounded-lg bg-white/10 flex items-center justify-center text-lg">📦</div>
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
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-amber-500/15 text-amber-200">Draft</span>
              {/if}
              <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-slate-600 bg-slate-800/80 text-slate-200 hover:bg-slate-700" data-eb-edit-product="{$p.id|escape}">Edit</button>
              {if !$p.stripe_product_id}
              <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-300 hover:bg-rose-500/20" onclick="window.ebStripeActions && window.ebStripeActions.deleteDraft && window.ebStripeActions.deleteDraft({$p.id})">Delete</button>
              {/if}
            </div>
          </div>
          {if $list|@count > 0}
          <div class="mt-4">
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
                    {if $pr.kind ne 'one_time'} / {$pr.interval|default:'month'}{else} / one-time{/if}
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
          {/if}
        </div>
        {/foreach}
      </div>
    </section>
    {/if}

    {if $stripe_products|default:[]|@count > 0}
    <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
      <div class="px-6 py-1 border-b border-slate-800 mb-4">
        <h2 class="text-lg font-medium text-slate-100">Stripe — Connected Products</h2>
        <p class="text-sm text-slate-400 mt-1 mb-4">Products on your connected Stripe account.</p>
      </div>
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
            <tr x-show="matches('{$sp.name|escape}', '{if $sp.active}active{else}archived{/if}', 'all')" class="hover:bg-slate-800/50">
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
    {/if}

    {* Product slide-over panel *}
    <div id="eb-product-panel" class="hidden fixed inset-0 z-50" x-data="productPanelFactory({ currency: '{$msp.default_currency|default:'CAD'}', ready: {$msp_ready|default:0} })">
      <div class="absolute inset-0 bg-gray-950/75 backdrop-blur-sm" @click="close()"></div>
      <div class="absolute inset-y-0 right-0 w-full max-w-3xl bg-slate-900 border-l border-slate-800 shadow-2xl flex flex-col">
        <div class="px-6 py-5 flex items-center justify-between border-b border-slate-800">
          <h3 class="text-lg font-semibold text-slate-100" x-text="mode==='create'?'Create a product':(mode==='editStripe'?'Update a product':'Update a product')"></h3>
          <button type="button" class="text-slate-400 hover:text-white" @click="close()">✕</button>
        </div>
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-6 text-sm">
          <div x-show="mode==='create'" class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <h4 class="text-sm font-medium text-slate-100 mb-3">Start from template</h4>
            <div class="grid grid-cols-2 gap-2">
              <button type="button" @click="applyPreset('eazybackup_cloud_backup')" :class="preset==='eazybackup_cloud_backup' ? 'ring-2 ring-sky-500' : ''" class="rounded-lg border border-slate-700 bg-slate-900/60 p-3 text-left hover:bg-slate-800 transition">
                <div class="text-sm font-medium text-slate-100">eazyBackup Cloud Backup</div>
                <div class="text-xs text-slate-400 mt-1">Storage (metered, GiB, monthly)</div>
              </button>
              <button type="button" @click="applyPreset('e3_object_storage')" :class="preset==='e3_object_storage' ? 'ring-2 ring-sky-500' : ''" class="rounded-lg border border-slate-700 bg-slate-900/60 p-3 text-left hover:bg-slate-800 transition">
                <div class="text-sm font-medium text-slate-100">e3 Object Storage</div>
                <div class="text-xs text-slate-400 mt-1">Storage (metered, GiB, 1 TiB min)</div>
              </button>
              <button type="button" @click="applyPreset('workstation_seat')" :class="preset==='workstation_seat' ? 'ring-2 ring-sky-500' : ''" class="rounded-lg border border-slate-700 bg-slate-900/60 p-3 text-left hover:bg-slate-800 transition">
                <div class="text-sm font-medium text-slate-100">Workstation Backup Seat</div>
                <div class="text-xs text-slate-400 mt-1">Device count (per-unit, monthly)</div>
              </button>
              <button type="button" @click="applyPreset('custom_service')" :class="preset==='custom_service' ? 'ring-2 ring-sky-500' : ''" class="rounded-lg border border-slate-700 bg-slate-900/60 p-3 text-left hover:bg-slate-800 transition">
                <div class="text-sm font-medium text-slate-100">Custom Service</div>
                <div class="text-xs text-slate-400 mt-1">Generic (per-unit, monthly)</div>
              </button>
            </div>
            <template x-if="preset">
              <div class="mt-2 flex items-center gap-2">
                <span class="text-xs text-sky-400">Using template: <span x-text="preset.replace(/_/g, ' ')"></span></span>
                <button type="button" @click="clearPreset()" class="text-xs text-slate-500 hover:text-white underline">Clear</button>
              </div>
            </template>
          </div>
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
            <template x-if="baseMetric">
              <p class="mt-3 text-xs text-slate-400 bg-slate-900/50 rounded-lg px-3 py-2" x-text="metricDescription(baseMetric)"></p>
            </template>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="mb-2 flex items-center justify-between">
              <h4 class="text-sm font-medium text-slate-100">Pricing</h4>
              <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700" @click="addEmptyItem()">Add price</button>
            </div>
            <template x-if="items.length===0"><div class="text-xs text-slate-400">No prices yet.</div></template>
            <div class="space-y-3">
              <template x-for="(it, i) in items" :key="'pr-'+i">
                <div class="rounded-lg border border-slate-700 bg-slate-950/40 p-4 space-y-4">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <div class="font-medium text-slate-100" x-text="it.label || ('Price ' + (i + 1))"></div>
                      <div class="mt-2 text-xs text-slate-400">
                        <span x-text="billingLabel(it.billingType)"></span>
                        · <span x-text="metricLabel(it.metric)"></span>
                        · <span x-text="it.billingType==='one_time' ? 'one-time' : (it.interval || 'month')"></span>
                        · <span x-text="currency + ' ' + Number(it.amount || 0).toFixed(2)"></span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <button type="button" class="text-xs px-3 py-1.5 rounded" :class="it.active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-300'" @click="togglePriceActive(i)" x-text="it.active ? 'Active' : 'Inactive'"></button>
                      <button type="button" class="text-xs px-3 py-1.5 rounded bg-slate-700 text-white hover:bg-slate-600" @click="duplicatePrice(i)">Duplicate</button>
                      <button type="button" class="text-xs px-3 py-1.5 rounded bg-rose-500/15 text-rose-200 hover:bg-rose-500/25" @click="removeItem(i)">Remove</button>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="block">
                      <span class="text-sm text-slate-400">Label</span>
                      <input x-model="it.label" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" />
                    </label>
                    <label class="block">
                      <span class="text-sm text-slate-400">Amount</span>
                      <div class="mt-2 flex items-stretch rounded-lg overflow-hidden bg-slate-800 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:outline-sky-700">
                        <span class="shrink-0 px-3 py-2.5 text-slate-400 select-none">$</span>
                        <input x-model.number="it.amount" type="text" inputmode="decimal" @blur="normalizePriceRow(i)" class="flex-1 min-w-0 text-left bg-transparent text-slate-100 placeholder-gray-400 focus:outline-none px-3 py-2.5" />
                        <select x-model="it.currency" class="shrink-0 px-2 py-2.5 bg-slate-800 text-slate-400 border-l border-slate-700 text-xs focus:outline-none cursor-pointer">
                          <option value="CAD">CAD</option>
                          <option value="USD">USD</option>
                          <option value="EUR">EUR</option>
                          <option value="GBP">GBP</option>
                          <option value="AUD">AUD</option>
                        </select>
                      </div>
                    </label>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="block">
                      <span class="text-sm text-slate-400">Interval</span>
                      <select x-model="it.interval" :disabled="it.billingType==='one_time'" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 disabled:bg-slate-800/60 disabled:text-slate-500">
                        <option value="month">Month</option>
                        <option value="year">Year</option>
                        <option value="none">None</option>
                      </select>
                    </label>

                    <template x-if="baseMetric==='GENERIC'">
                      <label class="block">
                        <span class="text-sm text-slate-400">Billing type</span>
                        <select x-model="it.billingType" @change="onInlineBillingTypeChange(i)" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
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

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="block">
                      <span class="text-sm text-slate-400">Pricing model</span>
                      <select x-model="it.pricingScheme" @change="onPricingSchemeChange(i)" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
                        <option value="per_unit">Flat rate</option>
                        <option value="tiered_graduated">Graduated tiers</option>
                        <option value="tiered_volume">Volume tiers</option>
                      </select>
                    </label>
                  </div>
                  <template x-if="it.pricingScheme && it.pricingScheme.startsWith('tiered')">
                    <div class="col-span-full mt-1 rounded-lg border border-slate-700 bg-slate-950/40 p-3">
                      <div class="text-xs text-slate-400 mb-2" x-text="it.pricingScheme==='tiered_graduated' ? 'Each tier is billed independently (graduated pricing)' : 'All units use the price of the tier that matches total quantity (volume pricing)'"></div>
                      <table class="w-full text-xs">
                        <thead><tr class="text-slate-400"><th class="px-2 py-1 text-left">First unit</th><th class="px-2 py-1 text-left">Last unit</th><th class="px-2 py-1 text-left">Per unit ($)</th><th class="px-2 py-1 text-left">Flat fee ($)</th><th class="px-2 py-1 w-8"></th></tr></thead>
                        <tbody>
                          <template x-for="(tier, ti) in (it.tiers || [])" :key="'tier-'+i+'-'+ti">
                            <tr>
                              <td class="px-2 py-1 text-slate-300" x-text="ti===0 ? '1' : String(Number(it.tiers[ti-1]?.up_to||0)+1)"></td>
                              <td class="px-2 py-1"><input x-model.number="tier.up_to" type="number" min="1" :placeholder="ti===(it.tiers||[]).length-1 ? '\u221e' : ''" class="w-20 px-2 py-1 rounded bg-slate-800 text-slate-100 border border-slate-700 outline-none focus:border-sky-600 text-xs" /></td>
                              <td class="px-2 py-1"><input x-model.number="tier.unit_amount_display" type="number" step="0.01" min="0" class="w-20 px-2 py-1 rounded bg-slate-800 text-slate-100 border border-slate-700 outline-none focus:border-sky-600 text-xs" /></td>
                              <td class="px-2 py-1"><input x-model.number="tier.flat_amount_display" type="number" step="0.01" min="0" class="w-20 px-2 py-1 rounded bg-slate-800 text-slate-100 border border-slate-700 outline-none focus:border-sky-600 text-xs" /></td>
                              <td class="px-2 py-1"><button type="button" @click="removeTier(i, ti)" class="text-rose-400 hover:text-rose-300 text-xs" x-show="(it.tiers||[]).length > 2">&times;</button></td>
                            </tr>
                          </template>
                        </tbody>
                      </table>
                      <button type="button" @click="addTier(i)" class="mt-2 text-xs text-sky-400 hover:text-sky-300">+ Add tier</button>
                    </div>
                  </template>

                  <template x-if="baseMetric==='STORAGE_TB'">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <label class="block">
                        <span class="text-sm text-slate-400">Unit</span>
                        <select x-model="it.unitLabel" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700">
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
                      <input x-model="it.unitLabel" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" />
                    </label>
                  </template>
                </div>
              </template>
            </div>
          </div>
        </div>
        <div class="border-t border-slate-800 px-6 py-5 flex items-center justify-end gap-3">
          <button type="button" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm" @click="close()">Cancel</button>
          <template x-if="mode==='create' || mode==='edit'">
            <div class="flex items-center gap-3">
              <button type="button" class="px-4 py-2.5 rounded-lg border border-slate-600 bg-slate-800/80 text-slate-200 text-sm hover:bg-slate-700" @click="save('draft')" :disabled="isSaving">Save Draft</button>
              <button type="button" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900" @click="save('publish')" :disabled="isSaving">Publish to Stripe</button>
            </div>
          </template>
          <template x-if="mode==='editStripe'">
            <button type="button" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900" @click="save()" :disabled="isSaving">Save</button>
          </template>
        </div>
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

