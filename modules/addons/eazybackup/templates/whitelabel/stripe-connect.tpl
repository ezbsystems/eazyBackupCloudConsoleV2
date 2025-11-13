<div class="p-6">
  <h2 class="text-xl font-semibold text-gray-100 mb-4">Stripe Connect — Status</h2>
  <div class="mb-3 flex items-center justify-between text-xs text-gray-400">
    <div>Last checked: {if $msp && $msp->last_verification_check}{$msp->last_verification_check|escape}{else}—{/if}</div>
    <a href="{$modulelink}&a=ph-stripe-connect" class="underline hover:text-gray-200">Refresh</a>
  </div>

  {if !$status.hasAccount}
    <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 p-4 mb-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="text-gray-300">Connect your Stripe Express account to start accepting payments and receiving payouts.</div>
        <a href="{$modulelink}&a=ph-stripe-onboard" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-[#1B2C50] text-white hover:bg-[#254074] transition">Connect Stripe</a>
      </div>
    </div>
  {elseif !$status.chargesEnabled || !$status.payoutsEnabled || ($status.currentlyDue|@count > 0)}
    <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 p-4 mb-4">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div class="text-gray-300">Your Stripe account setup requires additional information to enable payments and payouts.</div>
        <a href="{$modulelink}&a=ph-stripe-onboard" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-[#1B2C50] text-white hover:bg-[#254074] transition">Resume Onboarding</a>
      </div>
    </div>
  {/if}

  <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 p-4 mb-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>ddd
        <div class="text-gray-400 text-sm">Charges</div>
        <div class="text-gray-100 text-lg font-semibold">{if $status.chargesEnabled}Enabled{else}Disabled{/if}</div>
      </div>
      <div>
        <div class="text-gray-400 text-sm">Payouts</div>
        <div class="text-gray-100 text-lg font-semibold">{if $status.payoutsEnabled}Enabled{else}Disabled{/if}</div>
      </div>
      <div>
        <div class="text-gray-400 text-sm">Account</div>
        <div class="text-gray-100 text-lg font-semibold">{if $status.hasAccount}Connected{else}Not connected{/if}</div>
      </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
      <span class="inline-flex items-center px-2 py-0.5 rounded text-xs ring-1 {if $status.chargesEnabled}bg-emerald-500/10 ring-emerald-400/20 text-emerald-300{else}bg-rose-500/10 ring-rose-400/20 text-rose-300{/if}">
        Payments {if $status.chargesEnabled}Enabled{else}Disabled{/if}
      </span>
      <span class="inline-flex items-center px-2 py-0.5 rounded text-xs ring-1 {if $status.payoutsEnabled}bg-emerald-500/10 ring-emerald-400/20 text-emerald-300{else}bg-rose-500/10 ring-rose-400/20 text-rose-300{/if}">
        Payouts {if $status.payoutsEnabled}Enabled{else}Disabled{/if}
      </span>
      {assign var=reqCount value=$status.currentlyDue|@count}
      <span class="inline-flex items-center px-2 py-0.5 rounded text-xs ring-1 {if $reqCount>0}bg-amber-500/10 ring-amber-400/20 text-amber-200{else}bg-emerald-500/10 ring-emerald-400/20 text-emerald-300{/if}">
        Requirements Due ({$reqCount})
      </span>
    </div>
  </div>

  <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 p-4 mb-4">
    <h3 class="text-lg font-semibold text-gray-100 mb-2">What's missing (currently due)</h3>
    {if $status.currentlyDue|@count > 0}
      <p class="text-sm text-gray-400 mb-3">Complete these items in Stripe to enable payments and payouts.</p>
      <ul class="space-y-2">
        {foreach from=$status.currentlyDue item=item}
          <li class="flex items-center gap-2 text-gray-200">
            <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
            <span>{$item|escape}</span>
          </li>
        {/foreach}
      </ul>
    {else}
      <div class="text-gray-400">No outstanding requirements.</div>
    {/if}
  </div>

  <div class="rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 p-4">
    <h3 class="text-lg font-semibold text-gray-100 mb-2">Manage Account</h3>
    <p class="text-gray-300 mb-3">Use the embedded account management to update business, people, and payouts.</p>
    <a href="{$modulelink}&a=ph-stripe-manage" class="inline-flex items-center px-4 py-2 rounded bg-[#1B2C50] text-white">Open Account Management</a>
  </div>
</div>


