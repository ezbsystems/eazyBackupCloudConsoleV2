{* Stripe-style Products list *}
<script src="modules/addons/eazybackup/assets/js/catalog-products.js"></script>
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div id="toast-container" x-data="catalogToastManager()" x-init="init()" @catalog:toast.window="push($event.detail)" class="fixed top-4 right-4 z-[9999] space-y-2 pointer-events-none">
    <template x-for="toast in toasts" :key="toast.id">
      <div x-show="toast.visible" x-transition.opacity.duration.200ms class="pointer-events-auto px-4 py-2 rounded shadow text-sm text-white" :class="toast.type==='success' ? 'bg-green-600' : (toast.type==='error' ? 'bg-red-600' : (toast.type==='warning' ? 'bg-yellow-600' : 'bg-slate-700'))" x-text="toast.message"></div>
    </template>
  </div>
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6" x-data="{ q:'', statusFilter:'all', typeFilter:'all', showArchivedPrices:false, matches(n, status, type){ if(this.statusFilter!=='all' && status!==this.statusFilter) return false; if(this.typeFilter!=='all' && type!==this.typeFilter) return false; if(!this.q) return true; try{ return String(n||'').toLowerCase().indexOf(String(this.q).toLowerCase())>=0; }catch(_){ return true; } }, shouldShowPrice(isStripe, isActive){ return this.showArchivedPrices || !isStripe || isActive; } }">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='catalog-products'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Catalog - Products</h1>
        <p class="mt-1 text-sm text-slate-400">{$company|default:'Connected account'|escape} · Catalog products and prices.</p>
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
      <label class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800/50 px-3 py-1.5 text-xs text-slate-300">
        <input type="checkbox" x-model="showArchivedPrices" class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-sky-500 focus:ring-sky-500" />
        <span>Show archived prices</span>
      </label>
    </div>

    {if $products|default:[]|@count > 0}
    <section class="mb-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800">
        <h2 class="text-lg font-medium text-slate-100">Products</h2>        
      </div>
      <div class="px-6 py-6 space-y-6">
        {foreach from=$products item=p}
        {assign var=list value=$priceMap[$p.id]|default:[]}
        <div x-show="matches('{$p.name|escape}', '{if $p.stripe_product_id}{if $p.active}active{else}archived{/if}{elseif $p.active}draft{else}archived{/if}', '{$p.base_metric_code|default:'GENERIC'|escape}')" class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="h-10 w-10 rounded-lg bg-white/10 flex items-center justify-center">
                {if $p.base_metric_code eq 'STORAGE_TB'}
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="h-5 w-5 text-slate-400" aria-hidden="true"><path d="M141.58-173.31v-139.04h677.03v139.04H141.58Zm66.73-35.38h68.27v-68.27h-68.27v68.27Zm-66.73-438.16v-139.03h677.03v139.03H141.58Zm66.73-35.38h68.27v-68.27h-68.27v68.27Zm-66.73 271.34v-137.42h677.03v137.42H141.58Zm66.73-34.57h68.27v-68.27h-68.27v68.27Z"/></svg>
                {elseif $p.base_metric_code eq 'DEVICE_COUNT'}
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="h-5 w-5 text-slate-400" aria-hidden="true"><path d="M63.46-160.46v-55.96h833.08v55.96H63.46Zm112.8-95.16q-28.35 0-48.27-19.91-19.91-19.92-19.91-48.28v-409.88q0-28.36 19.91-48.28 19.92-19.91 48.27-19.91h607.48q28.35 0 48.27 19.91 19.91 19.92 19.91 48.28v409.88q0 28.36-19.91 48.28-19.92 19.91-48.27 19.91H176.26Zm.09-55.96h607.3q4.62 0 8.47-3.84 3.84-3.85 3.84-8.46v-409.73q0-4.62-3.84-8.47-3.85-3.84-8.47-3.84h-607.3q-4.62 0-8.47 3.84-3.84 3.85-3.84 8.47v409.73q0 4.61 3.84 8.46 3.85 3.84 8.47 3.84Zm-12.31 0V-745.92v434.34Z"/></svg>
                {elseif $p.base_metric_code eq 'DISK_IMAGE'}
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 text-slate-400" aria-hidden="true"><path d="M6,2H18A2,2 0 0,1 20,4V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V4A2,2 0 0,1 6,2M12,4A6,6 0 0,0 6,10C6,13.31 8.69,16 12.1,16L11.22,13.77C10.95,13.29 11.11,12.68 11.59,12.4L12.45,11.9C12.93,11.63 13.54,11.79 13.82,12.27L15.74,14.69C17.12,13.59 18,11.9 18,10A6,6 0 0,0 12,4M12,9A1,1 0 0,1 13,10A1,1 0 0,1 12,11A1,1 0 0,1 11,10A1,1 0 0,1 12,9M7,18A1,1 0 0,0 6,19A1,1 0 0,0 7,20A1,1 0 0,0 8,19A1,1 0 0,0 7,18M12.09,13.27L14.58,19.58L17.17,18.08L12.95,12.77L12.09,13.27Z"/></svg>
                {elseif $p.base_metric_code eq 'HYPERV_VM'}
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-5 w-5 text-slate-400" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" /></svg>
                {elseif $p.base_metric_code eq 'PROXMOX_VM'}
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-slate-400" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>
                {elseif $p.base_metric_code eq 'VMWARE_VM'}
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 195.203 79.3" fill="none" class="h-5 w-5 text-slate-400" aria-hidden="true"><path d="M20.5032 6.39999C18.0032 1 12.0032 -1.39999 6.30322 1.10001C0.60321 3.60001 -1.49677 9.89999 1.10321 15.3C1.10321 15.3 24.9032 67 24.9032 67C28.6032 75.1 32.6032 79.3 40.0032 79.3C47.9032 79.3 51.4032 74.7 55.1032 67C55.1032 67 75.8032 21.8 76.1032 21.3C76.3032 20.8 77.0032 19.4 79.1032 19.4C80.9032 19.4 82.4032 20.8 82.4032 22.7C82.4032 22.7 82.4032 66.9 82.4032 66.9C82.4032 73.7 86.2032 79.3 93.4032 79.3C100.703 79.3 104.603 73.7 104.603 66.9C104.603 66.9 104.603 30.7 104.603 30.7C104.603 23.7 109.603 19.2 116.403 19.2C123.203 19.2 127.703 23.9 127.703 30.7C127.703 30.7 127.703 66.9 127.703 66.9C127.703 73.7 131.503 79.3 138.703 79.3C146.003 79.3 149.903 73.7 149.903 66.9C149.903 66.9 149.903 30.7 149.903 30.7C149.903 23.7 154.903 19.2 161.703 19.2C168.503 19.2 173.003 23.9 173.003 30.7C173.003 30.7 173.003 66.9 173.003 66.9C173.003 73.7 176.803 79.3 184.003 79.3C191.303 79.3 195.203 73.7 195.203 66.9C195.203 66.9 195.203 25.7 195.203 25.7C195.203 10.6 183.003 0 168.403 0C153.803 0 144.603 10.1 144.603 10.1C139.703 3.80002 133.003 0 121.703 0C109.703 0 99.2032 10.1 99.2032 10.1C94.3032 3.80002 86.0032 0 79.2032 0C68.6032 0 60.2032 4.70001 55.0032 16.4C55.0032 16.4 39.8032 52.2 39.8032 52.2L20.5032 6.39999C20.5032 6.39999 20.5032 6.39999 20.5032 6.39999Z" fill="currentColor"/></svg>
                {elseif $p.base_metric_code eq 'M365_USER'}
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" fill="currentColor" class="h-5 w-5 text-slate-400" aria-hidden="true"><path d="M20.13,32.5c-2.79-1.69-4.53-4.77-4.53-8.04V8.9c0-1.63,0.39-3.19,1.11-4.57L7.54,9.88C4.74,11.57,3,14.65,3,17.92v14.15 c0,1.59,0.42,3.14,1.16,4.5c0.69,1.12,1.67,2.06,2.88,2.74c2.53,1.42,5.51,1.36,7.98-0.15l8.02-4.9L20.13,32.5z M42.84,27.14 l-8.44-5.05v2.29c0,3.25-1.72,6.33-4.49,8.02l-13.84,8.47c-1.52,0.93-3.19,1.42-4.87,1.46l8.93,5.41c1.5,0.91,3.19,1.36,4.87,1.36 s3.37-0.45,4.87-1.36l9.08-5.5l3.52-2.13c0.27-0.16,0.53-0.34,0.78-0.54c0.08-0.05,0.16-0.11,0.23-0.16 c0.65-0.53,1.23-1.13,1.71-1.79c0.02-0.03,0.04-0.06,0.06-0.09c0.77-1.19,1.2-2.59,1.19-4.06C46.43,30.85,45.09,28.48,42.84,27.14z M42.46,9.88l-9.57-5.79l-3.02-1.83C29.45,2,29.01,1.79,28.56,1.61c-0.49-0.21-1-0.37-1.51-0.47c-1.84-0.38-3.76-0.08-5.46,0.89 c-2.5,1.43-3.99,3.99-3.99,6.87v9.6l2.8-1.65c2.84-1.67,6.36-1.66,9.19,0.03l14.28,8.54c1.29,0.78,2.35,1.81,3.12,3.02L47,17.92 C47,14.65,45.26,11.57,42.46,9.88z"/></svg>
                {else}
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="h-5 w-5 text-slate-400" aria-hidden="true"><path d="M452.12-180.15v-283.12L203.23-607.54v276.19q0 3.08 1.54 5.77 1.54 2.7 4.62 4.62l242.73 140.81Zm55.96 0 242.53-140.81q3.08-1.92 4.62-4.62 1.54-2.69 1.54-5.77v-275.88L508.08-463.58v283.43Zm-62.12 61.11L181.5-271.5q-16.46-9.54-25.34-25.18-8.89-15.64-8.89-34.05v-299.04q0-18.34 8.89-34.17 8.88-15.83 25.34-25.06l264.46-151.96q16.26-9.23 34.03-9.23 17.78 0 34.24 9.23L778.69-689q16.27 9.23 25.15 25.06 8.89 15.83 8.89 34.17v299.35q0 18.57-8.89 34.38-8.88 15.81-25.15 24.85L514.23-119.04q-16.46 9.23-34.24 9.23-17.77 0-34.03-9.23ZM631-598.85l94.89-54.84L486.15-792.5q-3.07-1.92-6.15-1.92-3.08 0-6.15 1.92l-88.47 51.12L631-598.85Zm-151 87.62 94.62-55.15-245.7-142.27-94.61 54.96L480-511.23Z"/></svg>
                {/if}
              </div>
              <div>
                <div class="text-base font-semibold text-slate-100">{$p.name|escape}</div>
                <div class="text-xs text-slate-400">{if $p.description}{$p.description|escape}{else}No description{/if}</div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              {if $p.stripe_product_id && $p.active}
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-emerald-500/15 text-emerald-200">Published</span>
              {elseif $p.stripe_product_id}
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-slate-700 text-slate-200">Archived</span>
              {else}
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-amber-500/15 text-amber-200">Draft</span>
              {/if}
              <button type="button" class="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-lg border border-slate-600 bg-slate-800/80 text-slate-200 hover:bg-slate-700" data-eb-edit-product="{$p.id|escape}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true">
                  <path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/>
                </svg>
                <span>Edit</span>
              </button>
              {if $p.stripe_product_id && $p.active}
              <button type="button" class="cursor-pointer rounded-lg border border-slate-700 bg-slate-800/50 p-2 text-slate-300 hover:bg-slate-700" onclick="window.ebStripeActions && window.ebStripeActions.archiveProduct && window.ebStripeActions.archiveProduct('{$p.stripe_product_id|escape}')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true">
                  <path d="m480-240 160-160-56-56-64 64v-168h-80v168l-64-64-56 56 160 160ZM200-640v440h560v-440H200Zm0 520q-33 0-56.5-23.5T120-200v-499q0-14 4.5-27t13.5-24l50-61q11-14 27.5-21.5T250-840h460q18 0 34.5 7.5T772-811l50 61q9 11 13.5 24t4.5 27v499q0 33-23.5 56.5T760-120H200Zm16-600h528l-34-40H250l-34 40Zm264 300Z"/>
                </svg>
              </button>
              {elseif $p.stripe_product_id}
              <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-slate-600 bg-slate-800/80 text-slate-200 hover:bg-slate-700" onclick="window.ebStripeActions && window.ebStripeActions.unarchiveProduct && window.ebStripeActions.unarchiveProduct('{$p.stripe_product_id|escape}')">Unarchive</button>
              {else}
              <button type="button" class="text-xs px-3 py-1.5 rounded-lg border border-rose-500/30 bg-rose-500/10 text-rose-300 hover:bg-rose-500/20" onclick="window.ebStripeActions && window.ebStripeActions.deleteDraft && window.ebStripeActions.deleteDraft({$p.id})">Delete</button>
              {/if}
            </div>
          </div>
          {if $list|@count > 0}
          <div class="mt-4">
            <div class="mb-2 text-sm font-medium text-slate-300">Pricing</div>
            <div class="overflow-x-auto rounded-lg border border-slate-800">
              <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-300">
                  <tr class="text-left">
                    <th class="px-4 py-3 font-medium">Price</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                  {foreach from=$list item=pr}
                  <tr x-show="shouldShowPrice({if $pr.stripe_price_id}true{else}false{/if}, {if $pr.active}true{else}false{/if})" class="hover:bg-slate-800/50">
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-300">
                      <span class="text-slate-100">{$pr.currency|escape} {($pr.unit_amount/100)|string_format:"%.2f"}
                      {if $pr.unit_label} / {$pr.unit_label|escape}{/if}
                      {if $pr.kind ne 'one_time'} / {$pr.interval|default:'month'}{else} / one-time{/if}</span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-300">
                      {if $pr.active}
                        <span class="text-slate-100">Active</span>
                      {else}
                        <span class="text-slate-100">Archived</span>
                      {/if}
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-300 text-right">
                      <div class="inline-flex items-center gap-2">
                        <button
                          type="button"
                          class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white"
                          title="Edit price"
                          aria-label="Edit price"
                          data-eb-edit-price
                          data-eb-edit-price-product="{$p.id|escape}"
                          data-eb-edit-price-local="{$pr.id|escape}"
                          data-eb-edit-price-stripe="{$pr.stripe_price_id|default:''|escape}"
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true">
                            <path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/>
                          </svg>
                        </button>
                        {if !$pr.stripe_price_id || $pr.active}
                        <button
                          type="button"
                          class="inline-flex h-8 w-8 items-center justify-center rounded-lg {if $pr.stripe_price_id}border border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 {else}border border-rose-500/30 bg-rose-500/10 text-rose-300 hover:bg-rose-500/20{/if}"
                          title="{if $pr.stripe_price_id}Archive price{else}Delete price{/if}"
                          aria-label="{if $pr.stripe_price_id}Archive price{else}Delete price{/if}"
                          data-eb-delete-price="{$pr.id|escape}"
                          data-eb-delete-price-stripe="{$pr.stripe_price_id|default:''|escape}"
                        >
                          {if $pr.stripe_price_id}
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-300" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true">
                            <path d="m480-240 160-160-56-56-64 64v-168h-80v168l-64-64-56 56 160 160ZM200-640v440h560v-440H200Zm0 520q-33 0-56.5-23.5T120-200v-499q0-14 4.5-27t13.5-24l50-61q11-14 27.5-21.5T250-840h460q18 0 34.5 7.5T772-811l50 61q9 11 13.5 24t4.5 27v499q0 33-23.5 56.5T760-120H200Zm16-600h528l-34-40H250l-34 40Zm264 300Z"/>
                          </svg>
                          {else}
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 7.5h12m-10.5 0v10.125a1.125 1.125 0 0 0 1.125 1.125h6.75A1.125 1.125 0 0 0 16.5 17.625V7.5M9.75 7.5v-1.125A1.125 1.125 0 0 1 10.875 5.25h2.25A1.125 1.125 0 0 1 14.25 6.375V7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                          </svg>
                          {/if}
                        </button>
                        {/if}
                      </div>
                    </td>
                  </tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
          </div>
          {/if}
        </div>
        {/foreach}
      </div>
    </section>
    {/if}

    {if $show_stripe_debug|default:0 && $stripe_products|default:[]|@count > 0}
    <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
      <div class="px-6 py-1 border-b border-slate-800 mb-4">
        <h2 class="text-lg font-medium text-slate-100">Stripe Debug — Connected Products</h2>
        <p class="text-sm text-slate-400 mt-1 mb-4">Internal admin/debug view of raw Stripe account products.</p>
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
      <div class="absolute inset-0 bg-gray-950/65 backdrop-blur-sm" @click="close()"></div>
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
            <label class="block"><span class="text-sm text-slate-400">Product Name (required)</span><input x-model="product.name" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition" /></label>
          </div>
          <div>
            <label class="block"><span class="text-sm text-slate-400">Product Description</span><textarea x-model="product.description" rows="3" class="mt-2 w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition"></textarea></label>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="mb-2">
              <h4 class="text-sm font-medium text-slate-100">Product type</h4>
              <p class="text-xs text-slate-400">Choose the resource this product represents. Prices will be variants of this resource.</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <template x-for="opt in ['STORAGE_TB','DEVICE_COUNT','DISK_IMAGE','HYPERV_VM','PROXMOX_VM','VMWARE_VM','M365_USER','GENERIC']" :key="opt">
                <button type="button" @click="selectProductType(opt)" :class="baseMetric===opt ? 'bg-sky-600 text-white ring-1 ring-sky-400/50' : 'bg-slate-800 text-slate-300 border border-slate-700 hover:bg-slate-700'" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-medium rounded-lg transition">
                  <template x-if="opt==='STORAGE_TB'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="h-4 w-4 text-slate-400 shrink-0" aria-hidden="true"><path d="M141.58-173.31v-139.04h677.03v139.04H141.58Zm66.73-35.38h68.27v-68.27h-68.27v68.27Zm-66.73-438.16v-139.03h677.03v139.03H141.58Zm66.73-35.38h68.27v-68.27h-68.27v68.27Zm-66.73 271.34v-137.42h677.03v137.42H141.58Zm66.73-34.57h68.27v-68.27h-68.27v68.27Z"/></svg>
                  </template>
                  <template x-if="opt==='DEVICE_COUNT'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="h-4 w-4 text-slate-400 shrink-0" aria-hidden="true"><path d="M63.46-160.46v-55.96h833.08v55.96H63.46Zm112.8-95.16q-28.35 0-48.27-19.91-19.91-19.92-19.91-48.28v-409.88q0-28.36 19.91-48.28 19.92-19.91 48.27-19.91h607.48q28.35 0 48.27 19.91 19.91 19.92 19.91 48.28v409.88q0 28.36-19.91 48.28-19.92 19.91-48.27 19.91H176.26Zm.09-55.96h607.3q4.62 0 8.47-3.84 3.84-3.85 3.84-8.46v-409.73q0-4.62-3.84-8.47-3.85-3.84-8.47-3.84h-607.3q-4.62 0-8.47 3.84-3.84 3.85-3.84 8.47v409.73q0 4.61 3.84 8.46 3.85 3.84 8.47 3.84Zm-12.31 0V-745.92v434.34Z"/></svg>
                  </template>
                  <template x-if="opt==='DISK_IMAGE'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4 text-slate-400 shrink-0" aria-hidden="true"><path d="M6,2H18A2,2 0 0,1 20,4V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V4A2,2 0 0,1 6,2M12,4A6,6 0 0,0 6,10C6,13.31 8.69,16 12.1,16L11.22,13.77C10.95,13.29 11.11,12.68 11.59,12.4L12.45,11.9C12.93,11.63 13.54,11.79 13.82,12.27L15.74,14.69C17.12,13.59 18,11.9 18,10A6,6 0 0,0 12,4M12,9A1,1 0 0,1 13,10A1,1 0 0,1 12,11A1,1 0 0,1 11,10A1,1 0 0,1 12,9M7,18A1,1 0 0,0 6,19A1,1 0 0,0 7,20A1,1 0 0,0 8,19A1,1 0 0,0 7,18M12.09,13.27L14.58,19.58L17.17,18.08L12.95,12.77L12.09,13.27Z"/></svg>
                  </template>
                  <template x-if="opt==='HYPERV_VM'">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="h-4 w-4 text-slate-400 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" /></svg>
                  </template>
                  <template x-if="opt==='PROXMOX_VM'">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 text-slate-400 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>
                  </template>
                  <template x-if="opt==='VMWARE_VM'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 195.203 79.3" fill="none" class="h-4 w-4 text-slate-400 shrink-0" aria-hidden="true"><path d="M20.5032 6.39999C18.0032 1 12.0032 -1.39999 6.30322 1.10001C0.60321 3.60001 -1.49677 9.89999 1.10321 15.3C1.10321 15.3 24.9032 67 24.9032 67C28.6032 75.1 32.6032 79.3 40.0032 79.3C47.9032 79.3 51.4032 74.7 55.1032 67C55.1032 67 75.8032 21.8 76.1032 21.3C76.3032 20.8 77.0032 19.4 79.1032 19.4C80.9032 19.4 82.4032 20.8 82.4032 22.7C82.4032 22.7 82.4032 66.9 82.4032 66.9C82.4032 73.7 86.2032 79.3 93.4032 79.3C100.703 79.3 104.603 73.7 104.603 66.9C104.603 66.9 104.603 30.7 104.603 30.7C104.603 23.7 109.603 19.2 116.403 19.2C123.203 19.2 127.703 23.9 127.703 30.7C127.703 30.7 127.703 66.9 127.703 66.9C127.703 73.7 131.503 79.3 138.703 79.3C146.003 79.3 149.903 73.7 149.903 66.9C149.903 66.9 149.903 30.7 149.903 30.7C149.903 23.7 154.903 19.2 161.703 19.2C168.503 19.2 173.003 23.9 173.003 30.7C173.003 30.7 173.003 66.9 173.003 66.9C173.003 73.7 176.803 79.3 184.003 79.3C191.303 79.3 195.203 73.7 195.203 66.9C195.203 66.9 195.203 25.7 195.203 25.7C195.203 10.6 183.003 0 168.403 0C153.803 0 144.603 10.1 144.603 10.1C139.703 3.80002 133.003 0 121.703 0C109.703 0 99.2032 10.1 99.2032 10.1C94.3032 3.80002 86.0032 0 79.2032 0C68.6032 0 60.2032 4.70001 55.0032 16.4C55.0032 16.4 39.8032 52.2 39.8032 52.2L20.5032 6.39999C20.5032 6.39999 20.5032 6.39999 20.5032 6.39999Z" fill="currentColor"/></svg>
                  </template>
                  <template x-if="opt==='M365_USER'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50" fill="currentColor" class="h-4 w-4 text-slate-400 shrink-0" aria-hidden="true"><path d="M20.13,32.5c-2.79-1.69-4.53-4.77-4.53-8.04V8.9c0-1.63,0.39-3.19,1.11-4.57L7.54,9.88C4.74,11.57,3,14.65,3,17.92v14.15 c0,1.59,0.42,3.14,1.16,4.5c0.69,1.12,1.67,2.06,2.88,2.74c2.53,1.42,5.51,1.36,7.98-0.15l8.02-4.9L20.13,32.5z M42.84,27.14 l-8.44-5.05v2.29c0,3.25-1.72,6.33-4.49,8.02l-13.84,8.47c-1.52,0.93-3.19,1.42-4.87,1.46l8.93,5.41c1.5,0.91,3.19,1.36,4.87,1.36 s3.37-0.45,4.87-1.36l9.08-5.5l3.52-2.13c0.27-0.16,0.53-0.34,0.78-0.54c0.08-0.05,0.16-0.11,0.23-0.16 c0.65-0.53,1.23-1.13,1.71-1.79c0.02-0.03,0.04-0.06,0.06-0.09c0.77-1.19,1.2-2.59,1.19-4.06C46.43,30.85,45.09,28.48,42.84,27.14z M42.46,9.88l-9.57-5.79l-3.02-1.83C29.45,2,29.01,1.79,28.56,1.61c-0.49-0.21-1-0.37-1.51-0.47c-1.84-0.38-3.76-0.08-5.46,0.89 c-2.5,1.43-3.99,3.99-3.99,6.87v9.6l2.8-1.65c2.84-1.67,6.36-1.66,9.19,0.03l14.28,8.54c1.29,0.78,2.35,1.81,3.12,3.02L47,17.92 C47,14.65,45.26,11.57,42.46,9.88z"/></svg>
                  </template>
                  <template x-if="opt==='GENERIC'">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960" fill="currentColor" class="h-4 w-4 text-slate-400 shrink-0" aria-hidden="true"><path d="M452.12-180.15v-283.12L203.23-607.54v276.19q0 3.08 1.54 5.77 1.54 2.7 4.62 4.62l242.73 140.81Zm55.96 0 242.53-140.81q3.08-1.92 4.62-4.62 1.54-2.69 1.54-5.77v-275.88L508.08-463.58v283.43Zm-62.12 61.11L181.5-271.5q-16.46-9.54-25.34-25.18-8.89-15.64-8.89-34.05v-299.04q0-18.34 8.89-34.17 8.88-15.83 25.34-25.06l264.46-151.96q16.26-9.23 34.03-9.23 17.78 0 34.24 9.23L778.69-689q16.27 9.23 25.15 25.06 8.89 15.83 8.89 34.17v299.35q0 18.57-8.89 34.38-8.88 15.81-25.15 24.85L514.23-119.04q-16.46 9.23-34.24 9.23-17.77 0-34.03-9.23ZM631-598.85l94.89-54.84L486.15-792.5q-3.07-1.92-6.15-1.92-3.08 0-6.15 1.92l-88.47 51.12L631-598.85Zm-151 87.62 94.62-55.15-245.7-142.27-94.61 54.96L480-511.23Z"/></svg>
                  </template>
                  <span x-text="metricLabel(opt)"></span>
                </button>
              </template>
            </div>
            <template x-if="baseMetric">
              <p class="mt-3 text-xs text-slate-400 bg-slate-900/50 rounded-lg px-3 py-2" x-text="metricDescription(baseMetric)"></p>
            </template>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="mb-2 flex items-center justify-between">
              <div>
                <h4 class="text-sm font-medium text-slate-100">Pricing</h4>
                <p class="mt-1 text-xs text-slate-400">Each price needs a unique billing setup: currency, billing type, interval, and pricing model.</p>
                <template x-if="focusPriceId || focusPriceKey">
                  <div class="mt-2 flex items-center gap-3">
                    <span class="text-xs text-sky-300">Showing only the selected price.</span>
                    <button type="button" class="text-xs text-slate-400 underline hover:text-white" @click="clearPriceFocus()">Show all prices</button>
                  </div>
                </template>
              </div>
              <button type="button" class="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700" @click="addEmptyItem()">
                <img src="{$WEB_ROOT}/templates/{$template}/assets/icon/add.svg" alt="" class="h-4 w-4" aria-hidden="true" />
                <span>Add price</span>
              </button>
            </div>
            <template x-if="items.length===0"><div class="text-xs text-slate-400">No prices yet.</div></template>
            <div class="space-y-3">
              <template x-for="(it, i) in items" :key="'pr-'+i">
                <div x-show="(!focusPriceId && !focusPriceKey) || String(it.id || '') === String(focusPriceId) || priceSlotKey(it) === String(focusPriceKey)" class="rounded-lg border border-slate-700 bg-slate-950/40 p-4 space-y-4">
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
                    <div class="flex items-center gap-3">
                      <button
                        type="button"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent transition focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                        :class="it.active ? 'bg-emerald-500/80' : 'bg-slate-700'"
                        :aria-pressed="it.active ? 'true' : 'false'"
                        @click="togglePriceActive(i)"
                      >
                        <span class="sr-only">Toggle price status</span>
                        <span
                          class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                          :class="it.active ? 'translate-x-6' : 'translate-x-1'"
                        ></span>
                      </button>
                      <span class="text-xs font-medium" :class="it.active ? 'text-emerald-300' : 'text-amber-300'" x-text="it.active ? 'Active' : 'Inactive'"></span>
                      <button type="button" class="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded bg-slate-700 text-white hover:bg-slate-600" @click="duplicatePrice(i)">
                        <img src="{$WEB_ROOT}/templates/{$template}/assets/icon/content_copy.svg" alt="" class="h-4 w-4" aria-hidden="true" />
                        <span>Duplicate</span>
                      </button>
                      <button type="button" class="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded bg-rose-500/15 text-rose-200 hover:bg-rose-500/25" @click="removeItem(i)">
                        <img src="{$WEB_ROOT}/templates/{$template}/assets/icon/delete.svg" alt="" class="h-4 w-4" aria-hidden="true" />
                        <span>Remove</span>
                      </button>
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
              <button type="button" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-70 disabled:cursor-not-allowed" @click="save('publish')" :disabled="isSaving">
                <svg x-cloak x-show="isSaving" class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-4a6 6 0 0 0-6-6V2z"></path>
                </svg>
                <span x-text="isSaving ? 'Publishing...' : 'Publish to Stripe'"></span>
              </button>
            </div>
          </template>
          <template x-if="mode==='editStripe'">
            <button type="button" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900 disabled:opacity-70 disabled:cursor-not-allowed" @click="save()" :disabled="isSaving">
              <svg x-cloak x-show="isSaving" class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-4a6 6 0 0 0-6-6V2z"></path>
              </svg>
              <span x-text="isSaving ? 'Saving...' : 'Save Changes'"></span>
            </button>
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

<div id="eb-confirm-modal" class="hidden fixed inset-0 z-[110]">
  <div id="eb-confirm-backdrop" class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm"></div>
  <div class="relative flex min-h-full items-center justify-center p-4">
    <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
      <div class="border-b border-slate-800 px-6 py-5">
        <div class="text-base font-semibold text-slate-100" id="eb-confirm-title">Confirm action</div>
        <p class="mt-1 text-sm text-slate-400" id="eb-confirm-message">Are you sure you want to continue?</p>
      </div>
      <div class="flex items-center justify-end gap-3 px-6 py-5">
        <button type="button" id="eb-confirm-cancel" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-transparent hover:bg-slate-800 text-slate-200 text-sm">Cancel</button>
        <button type="button" id="eb-confirm-submit" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-rose-600 hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 focus:ring-offset-slate-900">
          <svg id="eb-confirm-spinner" class="hidden h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-4a6 6 0 0 0-6-6V2z"></path>
          </svg>
          <span id="eb-confirm-submit-label">Delete</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script src="modules/addons/eazybackup/templates/assets/js/ui.js"></script>
