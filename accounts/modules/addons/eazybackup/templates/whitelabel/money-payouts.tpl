<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='money-payouts'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
    <div class="mb-6">
      <h2 class="text-2xl font-semibold text-white">Payouts</h2>
      <p class="text-xs text-slate-400 mt-1">View and search Stripe Connect payouts.</p>
    </div>
    <form method="get" action="{$modulelink}" class="mb-4">
      <input type="hidden" name="m" value="eazybackup"/>
      <input type="hidden" name="a" value="ph-money-payouts"/>
      <div class="flex gap-2">
        <input type="text" name="q" value="{$q|escape}" placeholder="Search payout id, status, currency" class="w-full max-w-md px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 outline-1 -outline-offset-1 outline-white/10 focus-within:outline-2 focus-within:-outline-offset-2 focus-within:outline-sky-700 transition"/>
        <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Search</button>
      </div>
    </form>
    <div class="mb-4">
      <button type="button" id="eb-refresh-payouts" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Refresh last 30 days</button>
    </div>
    <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg overflow-hidden">
      <table class="min-w-full text-sm text-slate-300">
        <thead class="bg-slate-900/80 text-slate-300">
          <tr>
            <th class="px-4 py-3 text-left font-medium">Payout ID</th>
            <th class="px-4 py-3 text-left font-medium">Amount</th>
            <th class="px-4 py-3 text-left font-medium">Currency</th>
            <th class="px-4 py-3 text-left font-medium">Status</th>
            <th class="px-4 py-3 text-left font-medium">Arrival</th>
            <th class="px-4 py-3 text-left font-medium">Created</th>
            <th class="px-4 py-3 text-left font-medium">Stripe</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800">
          {if $rows|@count > 0}
            {foreach from=$rows item=row}
              <tr class="hover:bg-slate-800/50">
                <td class="px-4 py-3">{$row.stripe_payout_id|default:'-'}</td>
                <td class="px-4 py-3">{$row.amount/100|string_format:'%.2f'}</td>
                <td class="px-4 py-3">{$row.currency|upper|default:'USD'}</td>
                <td class="px-4 py-3">
                  {assign var=st value=$row.status|default:'-'}
                  <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $st=='paid'}bg-emerald-500/15 text-emerald-200{elseif $st=='pending'}bg-amber-500/15 text-amber-200{else}bg-slate-700 text-slate-300{/if}"><span class="h-1.5 w-1.5 rounded-full {if $st=='paid'}bg-emerald-400{elseif $st=='pending'}bg-amber-400{else}bg-slate-500{/if}"></span>{$st}</span>
                </td>
                <td class="px-4 py-3">{$row.arrival_date|date_format:'%Y-%m-%d'}</td>
                <td class="px-4 py-3">{$row.created|date_format:'%Y-%m-%d %H:%M'}</td>
                <td class="px-4 py-3">
                  {assign var=acct value=$msp.stripe_connect_id|default:''}
                  {if $acct}
                    <a href="https://dashboard.stripe.com/connect/accounts/{$acct}/payouts/{$row.stripe_payout_id}" target="_blank" rel="noopener" class="text-sky-400 hover:underline">Open</a>
                  {else}-{/if}
                </td>
              </tr>
            {/foreach}
          {else}
            <tr>
              <td colspan="7" class="px-4 py-6 text-center text-slate-400">No payouts found.</td>
            </tr>
          {/if}
        </tbody>
      </table>
    </div>
    <script>
      (function(){
        var btn = document.getElementById('eb-refresh-payouts');
        if (!btn) return;
        btn.addEventListener('click', async function(){
          btn.disabled = true;
          try {
            const res = await fetch('{$modulelink}&a=ph-payouts-refresh', { method: 'POST' });
            const data = await res.json();
            if (!data || data.status !== 'success') { alert((data && data.message) || 'Refresh failed'); btn.disabled=false; return; }
            location.reload();
          } catch (e) { alert('Error: ' + e.message); btn.disabled=false; }
        });
    })();
  </script>
    </div>
        </main>
      </div>
    </div>
  </div>
</div>
