<div class="p-6">
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold text-slate-50 tracking-tight">Payments</h1>
        <p class="mt-1 text-sm text-slate-400">View and manage one-time charges for setup fees and project work.</p>
      </div>
      <a href="{$modulelink}&a=ph-billing-payment-new"
         class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-xl bg-gradient-to-r from-sky-500 to-indigo-500 text-slate-950 shadow-md shadow-sky-900/60 hover:brightness-110 transition">New Payment</a>
    </div>

    <div class="rounded-2xl bg-slate-900/80 border border-slate-800 shadow-[0_18px_20px_-24px_rgba(0,0,0,0.9)] overflow-hidden">
      <form method="get" action="{$modulelink}" class="px-4 py-3 border-b border-slate-800 flex items-center gap-3">
        <input type="hidden" name="m" value="eazybackup"/>
        <input type="hidden" name="a" value="ph-billing-payments"/>
        <div class="relative flex-1">
          <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
              <path d="M15.5 15.5L20 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              <circle cx="11" cy="11" r="5" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </span>
          <input type="text" name="q" value="{$q|escape}" placeholder="Search customer, payment intent, status"
                 class="w-full pl-9 pr-3 py-2 text-sm rounded-xl bg-slate-950/70 border border-slate-700 text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/80 focus:border-sky-500/80"/>
        </div>
        <button type="submit" class="px-3 py-2 text-xs font-medium rounded-lg bg-slate-800 text-slate-100 border border-slate-700 hover:bg-slate-700">Search</button>
      </form>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left">
          <thead class="bg-slate-950/60 text-slate-400 border-b border-slate-800">
            <tr>
              <th class="px-4 py-2 font-medium">Customer</th>
              <th class="px-4 py-2 font-medium">Amount</th>
              <th class="px-4 py-2 font-medium">Currency</th>
              <th class="px-4 py-2 font-medium">Status</th>
              <th class="px-4 py-2 font-medium">Created</th>
              <th class="px-4 py-2 font-medium">Payment Intent</th>
              <th class="px-4 py-2 font-medium">Stripe</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            {if $rows|@count > 0}
              {foreach from=$rows item=row}
                <tr class="hover:bg-slate-900/70 transition-colors">
                  <td class="px-4 py-3 text-slate-200">{$row.customer_name|default:'-'}</td>
                  <td class="px-4 py-3 text-slate-200">{$row.amount/100|string_format:'%.2f'}</td>
                  <td class="px-4 py-3">{$row.currency|upper|default:'USD'}</td>
                  <td class="px-4 py-3">
                    {assign var=st value=$row.status|default:'-'}
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs ring-1 {if $st=='succeeded'}bg-emerald-500/15 ring-emerald-400/20 text-emerald-400{elseif $st=='requires_payment_method' || $st=='requires_action' || $st=='processing'}bg-amber-500/10 ring-amber-400/20 text-amber-200{elseif $st=='canceled'}bg-white/5 ring-white/10 text-white/70{else}bg-rose-500/15 ring-rose-400/20 text-rose-400{/if}">{$st}</span>
                  </td>
                  <td class="px-4 py-3">{$row.created|date_format:'%Y-%m-%d %H:%M'}</td>
                  <td class="px-4 py-3">{$row.stripe_payment_intent_id|default:'-'}</td>
                  <td class="px-4 py-3">
                    {assign var=acct value=$msp.stripe_connect_id|default:''}
                    {if $acct}
                      <a href="https://dashboard.stripe.com/connect/accounts/{$acct}/payments/{$row.stripe_payment_intent_id}" target="_blank" rel="noopener" class="text-sky-300 hover:underline">Open</a>
                    {else}-{/if}
                  </td>
                </tr>
              {/foreach}
            {else}
              <tr>
                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                  <div class="flex flex-col items-center gap-2">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-800/80">
                      <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none">
                        <path d="M5 12h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M12 5v14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                      </svg>
                    </div>
                    <p>No payments found yet.</p>
                    <a href="{$modulelink}&a=ph-billing-payment-new" class="mt-1 inline-flex items-center gap-2 text-xs font-medium text-sky-300 hover:text-sky-200">New one-time payment</a>
                  </div>
                </td>
              </tr>
            {/if}
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


