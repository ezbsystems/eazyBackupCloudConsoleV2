{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-none px-6 py-8">
    <section class="mt-0 rounded-2xl bg-[rgb(var(--bg-card))] shadow-xl shadow-black/20 ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium">Balance & Reports</h2>
      </div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 p-4">
            <div class="text-gray-400 text-sm">Available</div>
            <div class="text-gray-100 text-2xl font-semibold mt-1">
              {assign var=avail value=$balance.available|default:[]}
              {if $avail|@count > 0}
                {$avail.0.amount|default:0} {$avail.0.currency|upper|default:'USD'}
              {else}0{/if}
            </div>
          </div>
          <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 p-4">
            <div class="text-gray-400 text-sm">Pending</div>
            <div class="text-gray-100 text-2xl font-semibold mt-1">
              {assign var=pending value=$balance.pending|default:[]}
              {if $pending|@count > 0}
                {$pending.0.amount|default:0} {$pending.0.currency|upper|default:'USD'}
              {else}0{/if}
            </div>
          </div>
          <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 p-4">
            <div class="text-gray-400 text-sm">Quick Links</div>
            <div class="text-gray-100 mt-1">
              {if $dashboardUrl}
                <a href="{$dashboardUrl}" target="_blank" rel="noopener" class="text-blue-300 hover:underline">Open in Stripe Dashboard</a>
              {else}-{/if}
            </div>
          </div>
        </div>

        <form method="get" action="{$modulelink}" class="mb-6">
          <input type="hidden" name="m" value="eazybackup"/>
          <input type="hidden" name="a" value="ph-money-balance"/>
          <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <div>
              <label class="block text-[rgb(var(--text-secondary))] text-sm mb-1">From (YYYY-MM-DD)</label>
              <input name="from" type="text" value="{$filters.from|escape}" class="w-full px-3.5 py-2.5 rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none" />
            </div>
            <div>
              <label class="block text-[rgb(var(--text-secondary))] text-sm mb-1">To (YYYY-MM-DD)</label>
              <input name="to" type="text" value="{$filters.to|escape}" class="w-full px-3.5 py-2.5 rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none" />
            </div>
            <div>
              <label class="block text-[rgb(var(--text-secondary))] text-sm mb-1">Type</label>
              <input name="type" type="text" value="{$filters.type|escape}" placeholder="(optional)" class="w-full px-3.5 py-2.5 rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none" />
            </div>
            <div>
              <label class="block text-[rgb(var(--text-secondary))] text-sm mb-1">Limit</label>
              <input name="limit" type="number" min="1" max="100" value="{$filters.limit|default:50}" class="w-full px-3.5 py-2.5 rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none" />
            </div>
            <div class="flex gap-2">
              <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]">Filter</button>
              <a href="{$modulelink}&a=ph-money-balance&from={$filters.from|escape}&to={$filters.to|escape}&type={$filters.type|escape}&limit={$filters.limit|default:50}&export=csv" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--accent))]">Export CSV</a>
            </div>
          </div>
        </form>

        <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
          <table class="min-w-full text-sm text-gray-200">
            <thead class="bg-gray-900/60">
              <tr>
                <th class="px-4 py-3 text-left">ID</th>
                <th class="px-4 py-3 text-left">Amount</th>
                <th class="px-4 py-3 text-left">Currency</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-left">Description</th>
                <th class="px-4 py-3 text-left">Created</th>
                <th class="px-4 py-3 text-left">Available On</th>
                <th class="px-4 py-3 text-left">Fee</th>
                <th class="px-4 py-3 text-left">Net</th>
              </tr>
            </thead>
            <tbody>
              {if $transactions|@count > 0}
                {foreach from=$transactions item=row}
                  <tr class="border-t border-white/10">
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
                  <td colspan="9" class="px-4 py-6 text-center text-gray-400">No transactions found.</td>
                </tr>
              {/if}
            </tbody>
          </table>
        </div>

      </div>
    </section>
  </div>
</div>


