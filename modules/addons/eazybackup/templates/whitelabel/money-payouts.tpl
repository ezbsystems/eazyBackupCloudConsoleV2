<div class="p-6">
  <h2 class="text-xl font-semibold text-gray-100 mb-4">Payouts</h2>
  <form method="get" action="{$modulelink}" class="mb-4">
    <input type="hidden" name="m" value="eazybackup"/>
    <input type="hidden" name="a" value="ph-money-payouts"/>
    <div class="flex gap-2">
      <input type="text" name="q" value="{$q|escape}" placeholder="Search payout id, status, currency" class="w-full px-3 py-2 rounded bg-[rgb(var(--bg-input))] text-gray-100"/>
      <button type="submit" class="px-4 py-2 rounded bg-[#1B2C50] text-white">Search</button>
    </div>
  </form>
  <div class="mb-3">
    <button id="eb-refresh-payouts" class="px-4 py-2 rounded bg-[#1B2C50] text-white">Refresh last 30 days</button>
  </div>
  <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
    <table class="min-w-full text-sm text-gray-200">
      <thead class="bg-gray-900/60">
        <tr>
          <th class="px-4 py-3 text-left">Payout ID</th>
          <th class="px-4 py-3 text-left">Amount</th>
          <th class="px-4 py-3 text-left">Currency</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Arrival</th>
          <th class="px-4 py-3 text-left">Created</th>
          <th class="px-4 py-3 text-left">Stripe</th>
        </tr>
      </thead>
      <tbody>
        {if $rows|@count > 0}
          {foreach from=$rows item=row}
            <tr class="border-t border-white/10">
              <td class="px-4 py-3">{$row.stripe_payout_id|default:'-'}</td>
              <td class="px-4 py-3">{$row.amount/100|string_format:'%.2f'}</td>
              <td class="px-4 py-3">{$row.currency|upper|default:'USD'}</td>
              <td class="px-4 py-3">
                {assign var=st value=$row.status|default:'-'}
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs ring-1 {if $st=='paid'}bg-emerald-500/10 ring-emerald-400/20 text-emerald-300{elseif $st=='pending'}bg-amber-500/10 ring-amber-400/20 text-amber-200{else}bg-white/5 ring-white/10 text-white/70{/if}">{$st}</span>
              </td>
              <td class="px-4 py-3">{$row.arrival_date|date_format:'%Y-%m-%d'}</td>
              <td class="px-4 py-3">{$row.created|date_format:'%Y-%m-%d %H:%M'}</td>
              <td class="px-4 py-3">
                {assign var=acct value=$msp.stripe_connect_id|default:''}
                {if $acct}
                  <a href="https://dashboard.stripe.com/connect/accounts/{$acct}/payouts/{$row.stripe_payout_id}" target="_blank" rel="noopener" class="text-blue-300 hover:underline">Open</a>
                {else}-{/if}
              </td>
            </tr>
          {/foreach}
        {else}
          <tr>
            <td colspan="6" class="px-4 py-6 text-center text-gray-400">No payouts found.</td>
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


