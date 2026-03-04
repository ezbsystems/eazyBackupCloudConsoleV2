{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='stripe-connect'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="w-full max-w-full min-w-0 overflow-hidden rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
    <div class="mb-6">
      <h2 class="text-2xl font-semibold text-white">Stripe Connect — Status</h2>
      <p class="text-xs text-slate-400 mt-1">Connect your Stripe Express account to accept payments and receive payouts.</p>
      <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
        <span>Last checked: {if $msp && $msp->last_verification_check}{$msp->last_verification_check|escape}{else}—{/if}</span>
        <a href="{$modulelink}&a=ph-stripe-connect" class="underline hover:text-slate-200">Refresh</a>
      </div>
    </div>

    {if !$status.hasAccount}
      <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4 mb-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div class="text-slate-300">Connect your Stripe Express account to start accepting payments and receiving payouts.</div>
          <a href="{$modulelink}&a=ph-stripe-onboard" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900 shrink-0">Connect Stripe</a>
        </div>
      </div>
    {elseif !$status.chargesEnabled || !$status.payoutsEnabled || ($status.currentlyDue|@count > 0)}
      <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4 mb-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
          <div class="text-slate-300">Your Stripe account setup requires additional information to enable payments and payouts.</div>
          <a href="{$modulelink}&a=ph-stripe-onboard" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900 shrink-0">Resume Onboarding</a>
        </div>
      </div>
    {/if}

    <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4 mb-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <div class="text-slate-400 text-sm">Charges</div>
          <div class="text-slate-100 text-lg font-semibold">{if $status.chargesEnabled}Enabled{else}Disabled{/if}</div>
        </div>
        <div>
          <div class="text-slate-400 text-sm">Payouts</div>
          <div class="text-slate-100 text-lg font-semibold">{if $status.payoutsEnabled}Enabled{else}Disabled{/if}</div>
        </div>
        <div>
          <div class="text-slate-400 text-sm">Account</div>
          <div class="text-slate-100 text-lg font-semibold">{if $status.hasAccount}Connected{else}Not connected{/if}</div>
        </div>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $status.chargesEnabled}bg-emerald-500/15 text-emerald-200{else}bg-rose-500/15 text-rose-200{/if}"><span class="h-1.5 w-1.5 rounded-full {if $status.chargesEnabled}bg-emerald-400{else}bg-rose-400{/if}"></span>Payments {if $status.chargesEnabled}Enabled{else}Disabled{/if}</span>
        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $status.payoutsEnabled}bg-emerald-500/15 text-emerald-200{else}bg-rose-500/15 text-rose-200{/if}"><span class="h-1.5 w-1.5 rounded-full {if $status.payoutsEnabled}bg-emerald-400{else}bg-rose-400{/if}"></span>Payouts {if $status.payoutsEnabled}Enabled{else}Disabled{/if}</span>
        {assign var=reqCount value=$status.currentlyDue|@count}
        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $reqCount>0}bg-amber-500/15 text-amber-200{else}bg-emerald-500/15 text-emerald-200{/if}"><span class="h-1.5 w-1.5 rounded-full {if $reqCount>0}bg-amber-400{else}bg-emerald-400{/if}"></span>Requirements Due ({$reqCount})</span>
      </div>
    </div>

    <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4 mb-4">
      <h3 class="text-lg font-semibold text-slate-100 mb-2">What's missing (currently due)</h3>
      {if $status.currentlyDue|@count > 0}
        <p class="text-sm text-slate-400 mb-3">Complete these items in Stripe to enable payments and payouts.</p>
        <ul class="space-y-2">
          {foreach from=$status.currentlyDue item=item}
            <li class="flex items-center gap-2 text-slate-200">
              <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
              <span>{$item|escape}</span>
            </li>
          {/foreach}
        </ul>
      {else}
        <div class="text-slate-400">No outstanding requirements.</div>
      {/if}
    </div>

    <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4">
      <h3 class="text-lg font-semibold text-slate-100 mb-2">Manage Account</h3>
      <p class="text-slate-300 mb-3">Use the embedded account management to update business, people, and payouts.</p>
      <a href="{$modulelink}&a=ph-stripe-manage" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Open Account Management</a>
    </div>
    </div>
        </main>
      </div>
    </div>
  </div>
</div>
