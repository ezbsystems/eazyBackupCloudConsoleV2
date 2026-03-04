{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='money-balance'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
    <div class="mb-6">
      <h2 class="text-2xl font-semibold text-white">Balance & Reports</h2>
      <p class="text-xs text-slate-400 mt-1">Stripe Connect balance and transaction history.</p>
    </div>
    <section class="rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
      <div class="px-6 py-5 border-b border-slate-800">
        <h2 class="text-lg font-medium text-slate-100">Balance & Reports</h2>
      </div>
      <div class="px-6 py-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="text-slate-400 text-sm">Available</div>
            <div class="text-slate-100 text-2xl font-semibold mt-1">
              {assign var=avail value=$balance.available|default:[]}
              {if $avail|@count > 0}
                {$avail.0.amount|default:0} {$avail.0.currency|upper|default:'USD'}
              {else}0{/if}
            </div>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="text-slate-400 text-sm">Pending</div>
            <div class="text-slate-100 text-2xl font-semibold mt-1">
              {assign var=pending value=$balance.pending|default:[]}
              {if $pending|@count > 0}
                {$pending.0.amount|default:0} {$pending.0.currency|upper|default:'USD'}
              {else}0{/if}
            </div>
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="text-slate-400 text-sm">Quick Links</div>
            <div class="text-slate-100 mt-1">
              {if $dashboardUrl}
                <a href="{$dashboardUrl}" target="_blank" rel="noopener" class="text-sky-400 hover:underline">Open in Stripe Dashboard</a>
              {else}-{/if}
            </div>
          </div>
        </div>

        <form method="get" action="{$modulelink}" class="mb-6">
          <input type="hidden" name="m" value="eazybackup"/>
          <input type="hidden" name="a" value="ph-money-balance"/>
          <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <div>
              <label class="block text-slate-400 text-sm mb-1">From (YYYY-MM-DD)</label>
              <input name="from" type="text" value="{$filters.from|escape}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" />
            </div>
            <div>
              <label class="block text-slate-400 text-sm mb-1">To (YYYY-MM-DD)</label>
              <input name="to" type="text" value="{$filters.to|escape}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" />
            </div>
            <div>
              <label class="block text-slate-400 text-sm mb-1">Type</label>
              <input name="type" type="text" value="{$filters.type|escape}" placeholder="(optional)" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" />
            </div>
            <div>
              <label class="block text-slate-400 text-sm mb-1">Limit</label>
              <input name="limit" type="number" min="1" max="100" value="{$filters.limit|default:50}" class="w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition" />
            </div>
            <div class="flex gap-2">
              <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Filter</button>
              <a href="{$modulelink}&a=ph-money-balance&from={$filters.from|escape}&to={$filters.to|escape}&type={$filters.type|escape}&limit={$filters.limit|default:50}&export=csv" class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Export CSV</a>
            </div>
          </div>
        </form>

        <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg overflow-hidden">
          <table class="min-w-full text-sm text-slate-300">
            <thead class="bg-slate-900/80 text-slate-300">
              <tr>
                <th class="px-4 py-3 text-left font-medium">ID</th>
                <th class="px-4 py-3 text-left font-medium">Amount</th>
                <th class="px-4 py-3 text-left font-medium">Currency</th>
                <th class="px-4 py-3 text-left font-medium">Type</th>
                <th class="px-4 py-3 text-left font-medium">Description</th>
                <th class="px-4 py-3 text-left font-medium">Created</th>
                <th class="px-4 py-3 text-left font-medium">Available On</th>
                <th class="px-4 py-3 text-left font-medium">Fee</th>
                <th class="px-4 py-3 text-left font-medium">Net</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
              {if $transactions|@count > 0}
                {foreach from=$transactions item=row}
                  <tr class="hover:bg-slate-800/50">
                    <td class="px-4 py-3">{$row.id|default:'-'}</td>
                    <td class="px-4 py-3">{$row.amount|default:0}</td>
                    <td class="px-4 py-3">{$row.currency|upper|default:'USD'}</td>
                    <td class="px-4 py-3">{$row.type|default:'-'}</td>
                    <td class="px-4 py-3">{$row.description|default:'-'}</td>
                    <td class="px-4 py-3">{$row.created|default:0}</td>
                    <td class="px-4 py-3">{$row.available_on|default:0}</td>
                    <td class="px-4 py-3">{$row.fee|default:0}</td>
                    <td class="px-4 py-3">{$row.net|default:0}</td>
                  </tr>
                {/foreach}
              {else}
                <tr>
                  <td colspan="9" class="px-4 py-6 text-center text-slate-400">No transactions found.</td>
                </tr>
              {/if}
            </tbody>
          </table>
        </div>

      </div>
    </section>
    </div>
        </main>
      </div>
    </div>
  </div>
</div>
