<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='billing-payments'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
      <div>
        <h2 class="text-2xl font-semibold text-white">Payments</h2>
        <p class="text-xs text-slate-400 mt-1">View and manage one-time charges for setup fees and project work.</p>
      </div>
      <div class="shrink-0">
        <a href="{$modulelink}&a=ph-billing-payment-new" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">New Payment</a>
      </div>
    </div>

    <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
      <form method="get" action="{$modulelink}" class="mb-4 flex flex-wrap items-center gap-3">
        <input type="hidden" name="m" value="eazybackup"/>
        <input type="hidden" name="a" value="ph-billing-payments"/>
        <div class="relative flex-1 min-w-0 max-w-md">
          <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
              <path d="M15.5 15.5L20 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              <circle cx="11" cy="11" r="5" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </span>
          <input type="text" name="q" value="{$q|escape}" placeholder="Search client, payment intent, status" class="w-full pl-9 pr-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition"/>
        </div>
        <button type="submit" class="px-4 py-2.5 rounded-lg text-sm font-medium border border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Search</button>
      </form>

      <div class="overflow-x-auto rounded-lg border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/80 text-slate-300">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Client</th>
              <th class="px-4 py-3 text-left font-medium">Amount</th>
              <th class="px-4 py-3 text-left font-medium">Currency</th>
              <th class="px-4 py-3 text-left font-medium">Status</th>
              <th class="px-4 py-3 text-left font-medium">Created</th>
              <th class="px-4 py-3 text-left font-medium">Payment Intent</th>
              <th class="px-4 py-3 text-left font-medium">Stripe</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            {if $rows|@count > 0}
              {foreach from=$rows item=row}
                <tr class="hover:bg-slate-800/50">
                  <td class="px-4 py-3 text-left font-medium text-slate-100">{$row.tenant_name|default:'-'}</td>
                  <td class="px-4 py-3 text-left text-slate-300">{$row.amount/100|string_format:'%.2f'}</td>
                  <td class="px-4 py-3 text-left text-slate-300">{$row.currency|upper|default:'USD'}</td>
                  <td class="px-4 py-3 text-left">
                    {assign var=st value=$row.status|default:'-'}
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $st=='succeeded'}bg-emerald-500/15 text-emerald-200{elseif $st=='requires_payment_method' || $st=='requires_action' || $st=='processing'}bg-amber-500/15 text-amber-200{elseif $st=='canceled'}bg-slate-700 text-slate-300{else}bg-rose-500/15 text-rose-200{/if}"><span class="h-1.5 w-1.5 rounded-full {if $st=='succeeded'}bg-emerald-400{elseif $st=='requires_payment_method' || $st=='requires_action' || $st=='processing'}bg-amber-400{else}bg-slate-500{/if}"></span>{$st}</span>
                  </td>
                  <td class="px-4 py-3 text-left text-slate-300">{$row.created|date_format:'%Y-%m-%d %H:%M'}</td>
                  <td class="px-4 py-3 text-left text-slate-300">{$row.stripe_payment_intent_id|default:'-'}</td>
                  <td class="px-4 py-3 text-left">
                    {assign var=acct value=$msp.stripe_connect_id|default:''}
                    {if $acct}
                      <a href="https://dashboard.stripe.com/connect/accounts/{$acct}/payments/{$row.stripe_payment_intent_id}" target="_blank" rel="noopener" class="text-sky-400 hover:underline">Open</a>
                    {else}-{/if}
                  </td>
                </tr>
              {/foreach}
            {else}
              <tr>
                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-400">
                  <div class="flex flex-col items-center gap-2">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-800/80">
                      <svg class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="none">
                        <path d="M5 12h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        <path d="M12 5v14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                      </svg>
                    </div>
                    <p>No payments found yet.</p>
                    <a href="{$modulelink}&a=ph-billing-payment-new" class="mt-1 inline-flex items-center gap-2 text-xs font-medium text-sky-400 hover:text-sky-300">New one-time payment</a>
                  </div>
                </td>
              </tr>
            {/if}
          </tbody>
        </table>
      </div>
    </div>
    </div>
        </main>
      </div>
    </div>
  </div>
</div>


