<div class="p-6">
  <h2 class="text-xl font-semibold text-gray-100 mb-4">Invoices</h2>
  <form method="get" action="{$modulelink}" class="mb-4">
    <input type="hidden" name="m" value="eazybackup"/>
    <input type="hidden" name="a" value="ph-billing-invoices"/>
    <div class="flex gap-2">
      <input type="text" name="q" value="{$q|escape}" placeholder="Search customer, invoice id, status" class="w-full px-3 py-2 rounded bg-[rgb(var(--bg-input))] text-gray-100"/>
      <button type="submit" class="px-4 py-2 rounded bg-[#1B2C50] text-white">Search</button>
    </div>
  </form>

  <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
    <table class="min-w-full text-sm text-gray-200">
      <thead class="bg-gray-900/60">
        <tr>
          <th class="px-4 py-3 text-left">Customer</th>
          <th class="px-4 py-3 text-left">Amount</th>
          <th class="px-4 py-3 text-left">Currency</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Created</th>
          <th class="px-4 py-3 text-left">Invoice</th>
          <th class="px-4 py-3 text-left">Stripe</th>
        </tr>
      </thead>
      <tbody>
        {if $rows|@count > 0}
          {foreach from=$rows item=row}
            <tr class="border-t border-white/10">
              <td class="px-4 py-3">{$row.customer_name|default:'-'}</td>
              <td class="px-4 py-3">{$row.amount_total/100|string_format:'%.2f'}</td>
              <td class="px-4 py-3">{$row.currency|upper|default:'USD'}</td>
              <td class="px-4 py-3">
                {assign var=st value=$row.status|default:'-'}
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs ring-1 {if $st=='paid'}bg-emerald-500/10 ring-emerald-400/20 text-emerald-300{elseif $st=='open' || $st=='uncollectible' || $st=='draft'}bg-amber-500/10 ring-amber-400/20 text-amber-200{elseif $st=='void'}bg-white/5 ring-white/10 text-white/70{else}bg-rose-500/10 ring-rose-400/20 text-rose-300{/if}">{$st}</span>
              </td>
              <td class="px-4 py-3">{$row.created|date_format:'%Y-%m-%d %H:%M'}</td>
              <td class="px-4 py-3">
                {if $row.hosted_invoice_url}
                  <a href="{$row.hosted_invoice_url}" target="_blank" rel="noopener" class="text-blue-300 hover:underline">View</a>
                {else}-{/if}
              </td>
              <td class="px-4 py-3">
                {assign var=acct value=$msp.stripe_connect_id|default:''}
                {if $acct}
                  <a href="https://dashboard.stripe.com/connect/accounts/{$acct}/invoices/{$row.stripe_invoice_id}" target="_blank" rel="noopener" class="text-blue-300 hover:underline">Open</a>
                {else}-{/if}
              </td>
            </tr>
          {/foreach}
        {else}
          <tr>
            <td colspan="6" class="px-4 py-6 text-center text-gray-400">No invoices found.</td>
          </tr>
        {/if}
      </tbody>
    </table>
  </div>
</div>


