{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
<div class="min-h-screen eb-bg-page eb-text-primary overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='stripe-connect'}
        <main class="flex-1 min-w-0 overflow-x-auto">
          <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
            <div>
              <h1 class="text-2xl font-semibold tracking-tight">Stripe Connect - Status</h1>
              <p class="mt-1 text-sm text-slate-400">Connect your Stripe Express account to accept payments and receive payouts.</p>
              <p class="mt-1 text-xs text-slate-500">Last checked: {if $msp && $msp->last_verification_check}{$msp->last_verification_check|escape}{else}—{/if}</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <a href="{$modulelink}&a=ph-stripe-connect" class="inline-flex items-center rounded-xl px-4 py-2 text-slate-300 ring-1 ring-white/10 hover:bg-white/5">Refresh</a>
              {if !$status.hasAccount}
                <a href="{$modulelink}&a=ph-stripe-onboard" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Connect Stripe</a>
              {elseif $status.actionRequired}
                <a href="{$modulelink}&a=ph-stripe-onboard" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Resume Onboarding</a>
              {else}
                <a href="{$modulelink}&a=ph-stripe-manage" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Manage Account</a>
              {/if}
            </div>
          </div>
          <div class="p-6">

    {if !$status.hasAccount}
      <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4 mb-4">
        <div class="text-slate-300">Connect your Stripe Express account to start accepting payments and receiving payouts.</div>
      </div>
    {elseif $status.actionRequired}
      <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4 mb-4">
        <div class="text-slate-300">Your Stripe account setup requires additional information to enable payments and payouts.</div>
      </div>
    {elseif $status.underReview}
      <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4 mb-4">
        <div class="text-slate-300">
          Your account is connected and no additional information is currently due. Some capabilities are still pending Stripe review.
        </div>
      </div>
    {/if}

    {assign var=reqCount value=$status.currentlyDue|@count}
    {assign var=pastDueCount value=$status.pastDue|@count}
    {assign var=pendingVerificationCount value=$status.pendingVerification|@count}

<!-- Stripe Connect: Status Cards (updated typography + contrast) -->
<div class="mb-4 rounded-2xl eb-bg-card ring-1 ring-white/10 p-4">
  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
    <!-- Charges -->
    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="text-xs uppercase tracking-wide text-slate-300/80">Charges</div>
        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium whitespace-nowrap
          {if $status.chargesEnabled}bg-emerald-400/10 text-emerald-200{else}bg-rose-400/10 text-rose-200{/if}">
          {if $status.chargesEnabled}Enabled{else}Disabled{/if}
        </span>
      </div>

      <div class="mt-2 text-lg font-semibold text-white">
        {if $status.chargesEnabled}Live{else}Blocked{/if}
      </div>
      <div class="mt-1 text-sm leading-snug text-slate-300/90">
        {if $status.chargesEnabled}Customers can pay you{else}Payments are currently off{/if}
      </div>
    </div>

    <!-- Payouts -->
    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="text-xs uppercase tracking-wide text-slate-300/80">Payouts</div>
        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium whitespace-nowrap
          {if $status.payoutsEnabled}bg-emerald-400/10 text-emerald-200{else}bg-rose-400/10 text-rose-200{/if}">
          {if $status.payoutsEnabled}Enabled{else}Disabled{/if}
        </span>
      </div>

      <div class="mt-2 text-base font-semibold text-white sm:text-lg">
        {if $status.payoutsEnabled}Active{else}Not paying out{/if}
      </div>
      <div class="mt-1 text-sm leading-snug text-slate-300/90">
        {if $status.payoutsEnabled}Funds will be deposited to your bank{else}Finish Stripe setup to enable{/if}
      </div>
    </div>

    <!-- Account -->
    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="text-xs uppercase tracking-wide text-slate-300/80">Account</div>
        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium whitespace-nowrap
          {if $status.hasAccount}bg-emerald-400/10 text-emerald-200{else}bg-rose-400/10 text-rose-200{/if}">
          {if $status.hasAccount}Connected{else}Not connected{/if}
        </span>
      </div>

      <div class="mt-2 text-lg font-semibold text-white">
        {if $status.hasAccount}Ready{else}Connect required{/if}
      </div>
      <div class="mt-1 text-sm leading-snug text-slate-300/90">
        {if $status.hasAccount}Stripe account link is active{else}Start onboarding to connect{/if}
      </div>
    </div>

    <!-- Requirements (weighted) -->
    <div class="rounded-2xl border p-4
      {if $reqCount > 0}border-amber-400/30 bg-amber-400/10{else}border-white/10 bg-white/5{/if}">
      <div class="flex items-start justify-between gap-3">
        <div class="text-xs uppercase tracking-wide text-slate-300/80">Requirements</div>
        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium whitespace-nowrap
          {if $reqCount > 0}bg-amber-400/10 text-amber-200{else}bg-emerald-400/10 text-emerald-200{/if}">
          {if $reqCount > 0}Action needed{else}All set{/if}
        </span>
      </div>

      <div class="mt-2 flex items-baseline gap-2">
        <div class="text-2xl font-semibold text-white">{$reqCount}</div>
        <div class="text-sm text-slate-400">due</div>
      </div>
      <div class="mt-1 text-sm leading-snug text-slate-300/90">
        {if $reqCount > 0}Stripe needs more info to unlock capabilities{else}No outstanding items right now{/if}
      </div>
    </div>

    <!-- Verification -->
    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
      <div class="flex items-start justify-between gap-3">
        <div class="text-xs uppercase tracking-wide text-slate-300/80">Verification</div>
        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium whitespace-nowrap
          {if $pendingVerificationCount > 0}bg-sky-400/10 text-sky-200{else}bg-emerald-400/10 text-emerald-200{/if}">
          {if $pendingVerificationCount > 0}In review{else}Clear{/if}
        </span>
      </div>

      <div class="mt-2 flex items-baseline gap-2">
        <div class="text-2xl font-semibold text-white">{$pendingVerificationCount}</div>
        <div class="text-sm text-slate-400">pending</div>
      </div>
      <div class="mt-1 text-sm leading-snug text-slate-300/90">
        {if $pendingVerificationCount > 0}Stripe is reviewing submitted details{else}No pending verification items{/if}
      </div>
    </div>
  </div>
</div>

    <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4 mb-4">
      <h3 class="text-lg font-semibold text-slate-100 mb-2">What's missing (currently due)</h3>
      {if $reqCount > 0 || $pastDueCount > 0}
        <p class="text-sm text-slate-400 mb-3">Complete these items in Stripe to enable payments and payouts.</p>
        <ul class="space-y-2">
          {foreach from=$status.currentlyDue item=item}
            <li class="flex items-center gap-2 text-slate-200">
              <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
              <span>{$item|escape}</span>
            </li>
          {/foreach}
          {foreach from=$status.pastDue item=item}
            <li class="flex items-center gap-2 text-slate-200">
              <span class="h-1.5 w-1.5 rounded-full bg-rose-400"></span>
              <span>{$item|escape}</span>
            </li>
          {/foreach}
        </ul>
      {elseif $pendingVerificationCount > 0}
        <p class="text-sm text-slate-400 mb-3">Stripe is reviewing these items. No action is required right now.</p>
        <ul class="space-y-2">
          {foreach from=$status.pendingVerification item=item}
            <li class="flex items-center gap-2 text-slate-200">
              <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
              <span>{$item|escape}</span>
            </li>
          {/foreach}
        </ul>
      {else}
        <div class="text-slate-400">No outstanding requirements. {if !$status.chargesEnabled || !$status.payoutsEnabled}Capability changes may still be processing in Stripe.{/if}</div>
      {/if}
    </div>

    <div class="rounded-2xl border border-slate-800/80 bg-slate-900/70 p-4">
      <h3 class="text-lg font-semibold text-slate-100 mb-2">Manage Account</h3>
      <p class="text-slate-300 mb-3">Use the embedded account management to update business, people, and payouts.</p>
      <a href="{$modulelink}&a=ph-stripe-manage" class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Open Account Management</a>
    </div>
          </div>
        </main>
      </div>
    </div>
  </div>
</div>
