{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='billing-subscriptions'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
      <div>
        <h2 class="text-2xl font-semibold text-white">Subscriptions</h2>
        <p class="text-xs text-slate-400 mt-1">Search and view customer subscriptions linked to your Stripe Connect account.</p>
      </div>
    </div>

    <form method="get" action="{$modulelink}" class="mb-4">
      <input type="hidden" name="m" value="eazybackup"/>
      <input type="hidden" name="a" value="ph-billing-subscriptions"/>
      <div class="flex gap-3">
        <input type="text" name="q" value="{$q|escape}" placeholder="Search client, plan, subscription id" class="flex-1 max-w-md w-full px-3 py-2.5 rounded-lg bg-slate-800 text-sm text-slate-100 placeholder:text-gray-400 text-white outline-1 -outline-offset-1 outline-white/10 focus:outline-2 focus:-outline-offset-2 focus:outline-sky-700 transition"/>
        <button type="submit" class="px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Search</button>
      </div>
    </form>

    <div class="w-full max-w-full min-w-0 rounded-2xl border border-slate-800/80 bg-slate-900/70 p-5 shadow-lg">
      <div class="overflow-x-auto rounded-lg border border-slate-800">
        <table class="min-w-full divide-y divide-slate-800 text-sm">
          <thead class="bg-slate-900/80 text-slate-300">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Client</th>
              <th class="px-4 py-3 text-left font-medium">Plan</th>
              <th class="px-4 py-3 text-left font-medium">Price</th>
              <th class="px-4 py-3 text-left font-medium">Status</th>
              <th class="px-4 py-3 text-left font-medium">Started</th>
              <th class="px-4 py-3 text-left font-medium">Stripe ID</th>
              <th class="px-4 py-3 text-left font-medium">Stripe</th>
              <th class="px-4 py-3 text-left font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            {if $rows|@count > 0}
              {foreach from=$rows item=row}
                <tr class="hover:bg-slate-800/50">
                  <td class="px-4 py-3 text-left font-medium text-slate-100">{$row.tenant_name|default:'-'}</td>
                  <td class="px-4 py-3 text-left text-slate-300">{$row.plan_name|default:'-'}</td>
                  <td class="px-4 py-3 text-left text-slate-300">{$row.price_nickname|default:'-'}{if $row.price_cycle} ({$row.price_cycle}){/if}</td>
                  <td class="px-4 py-3 text-left">
                    {assign var=st value=$row.stripe_status|default:'-'}
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $st=='active'}bg-emerald-500/15 text-emerald-200{elseif $st=='past_due' || $st=='incomplete' || $st=='trialing'}bg-amber-500/15 text-amber-200{elseif $st=='canceled'}bg-slate-700 text-slate-300{else}bg-rose-500/15 text-rose-200{/if}"><span class="h-1.5 w-1.5 rounded-full {if $st=='active'}bg-emerald-400{elseif $st=='past_due' || $st=='incomplete' || $st=='trialing'}bg-amber-400{else}bg-slate-500{/if}"></span>{$st}</span>
                  </td>
                  <td class="px-4 py-3 text-left text-slate-300">{$row.started_at|default:'-'}</td>
                  <td class="px-4 py-3 text-left text-slate-300">{$row.stripe_subscription_id|default:'-'}</td>
                  <td class="px-4 py-3 text-left">
                    {assign var=acct value=$msp.stripe_connect_id|default:''}
                    {if $acct}
                      <a href="https://dashboard.stripe.com/connect/accounts/{$acct}/subscriptions/{$row.stripe_subscription_id}" target="_blank" rel="noopener" class="text-sky-400 hover:underline">Open</a>
                    {else}-{/if}
                  </td>
                  <td class="px-4 py-3 text-left">
                    <a href="{$modulelink}&a=ph-client&id={$row.tenant_row_id}" class="px-3 py-1.5 text-xs bg-slate-700 rounded text-white hover:bg-slate-600 cursor-pointer inline-block">View Client</a>
                  </td>
                </tr>
              {/foreach}
            {else}
              <tr>
                <td colspan="8" class="px-4 py-8 text-center text-slate-400">No subscriptions found.</td>
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
