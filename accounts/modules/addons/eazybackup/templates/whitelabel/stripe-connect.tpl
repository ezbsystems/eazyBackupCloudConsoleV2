{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
{capture assign=ebPhContent}
          <div class="flex flex-col gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <h1 class="eb-type-h2 tracking-tight text-[var(--eb-text-primary)]">Stripe Connect — Status</h1>
              <p class="eb-page-description mt-1">Connect your Stripe Express account to accept payments and receive payouts.</p>
              <p class="eb-field-help mt-1">Last checked: {if $msp && $msp->last_verification_check}{$msp->last_verification_check|escape}{else}—{/if}</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3">
              <a href="{$modulelink}&a=ph-stripe-connect" class="eb-btn eb-btn-outline eb-btn-sm">Refresh</a>
              {if !$status.hasAccount}
                <a href="{$modulelink}&a=ph-stripe-onboard" class="eb-btn eb-btn-primary eb-btn-sm">Connect Stripe</a>
              {elseif $status.actionRequired}
                <a href="{$modulelink}&a=ph-stripe-onboard" class="eb-btn eb-btn-primary eb-btn-sm">Resume Onboarding</a>
              {else}
                <a href="{$modulelink}&a=ph-stripe-manage" class="eb-btn eb-btn-primary eb-btn-sm">Manage Account</a>
              {/if}
            </div>
          </div>
          <div class="p-6">

    {if !$status.hasAccount}
      <div class="eb-alert eb-alert--info mb-4">
        Connect your Stripe Express account to start accepting payments and receiving payouts.
      </div>
    {elseif $status.actionRequired}
      <div class="eb-alert eb-alert--warning mb-4">
        Your Stripe account setup requires additional information to enable payments and payouts.
      </div>
    {elseif $status.underReview}
      <div class="eb-alert eb-alert--info mb-4">
        Your account is connected and no additional information is currently due. Some capabilities are still pending Stripe review.
      </div>
    {/if}

    {assign var=reqCount value=$status.currentlyDue|@count}
    {assign var=pastDueCount value=$status.pastDue|@count}
    {assign var=pendingVerificationCount value=$status.pendingVerification|@count}

<div class="eb-subpanel mb-4 !p-4">
  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
    <div class="eb-stat-card">
      <div class="flex items-start justify-between gap-3">
        <div class="eb-stat-label">Charges</div>
        <span class="eb-badge eb-badge--dot {if $status.chargesEnabled}eb-badge--success{else}eb-badge--danger{/if}">
          {if $status.chargesEnabled}Enabled{else}Disabled{/if}
        </span>
      </div>
      <div class="mt-2 text-lg font-semibold text-[var(--eb-text-primary)]">
        {if $status.chargesEnabled}Live{else}Blocked{/if}
      </div>
      <div class="mt-1 text-sm leading-snug text-[var(--eb-text-secondary)]">
        {if $status.chargesEnabled}Customers can pay you{else}Payments are currently off{/if}
      </div>
    </div>

    <div class="eb-stat-card">
      <div class="flex items-start justify-between gap-3">
        <div class="eb-stat-label">Payouts</div>
        <span class="eb-badge eb-badge--dot {if $status.payoutsEnabled}eb-badge--success{else}eb-badge--danger{/if}">
          {if $status.payoutsEnabled}Enabled{else}Disabled{/if}
        </span>
      </div>
      <div class="mt-2 text-base font-semibold text-[var(--eb-text-primary)] sm:text-lg">
        {if $status.payoutsEnabled}Active{else}Not paying out{/if}
      </div>
      <div class="mt-1 text-sm leading-snug text-[var(--eb-text-secondary)]">
        {if $status.payoutsEnabled}Funds will be deposited to your bank{else}Finish Stripe setup to enable{/if}
      </div>
    </div>

    <div class="eb-stat-card">
      <div class="flex items-start justify-between gap-3">
        <div class="eb-stat-label">Account</div>
        <span class="eb-badge eb-badge--dot {if $status.hasAccount}eb-badge--success{else}eb-badge--danger{/if}">
          {if $status.hasAccount}Connected{else}Not connected{/if}
        </span>
      </div>
      <div class="mt-2 text-lg font-semibold text-[var(--eb-text-primary)]">
        {if $status.hasAccount}Ready{else}Connect required{/if}
      </div>
      <div class="mt-1 text-sm leading-snug text-[var(--eb-text-secondary)]">
        {if $status.hasAccount}Stripe account link is active{else}Start onboarding to connect{/if}
      </div>
    </div>

    <div class="eb-stat-card {if $reqCount > 0}ring-2 ring-[var(--eb-warning-border)]{/if}">
      <div class="flex items-start justify-between gap-3">
        <div class="eb-stat-label">Requirements</div>
        <span class="eb-badge eb-badge--dot {if $reqCount > 0}eb-badge--warning{else}eb-badge--success{/if}">
          {if $reqCount > 0}Action needed{else}All set{/if}
        </span>
      </div>
      <div class="mt-2 flex items-baseline gap-2">
        <div class="text-2xl font-semibold tabular-nums text-[var(--eb-text-primary)]">{$reqCount}</div>
        <div class="text-sm text-[var(--eb-text-muted)]">due</div>
      </div>
      <div class="mt-1 text-sm leading-snug text-[var(--eb-text-secondary)]">
        {if $reqCount > 0}Stripe needs more info to unlock capabilities{else}No outstanding items right now{/if}
      </div>
    </div>

    <div class="eb-stat-card">
      <div class="flex items-start justify-between gap-3">
        <div class="eb-stat-label">Verification</div>
        <span class="eb-badge eb-badge--dot {if $pendingVerificationCount > 0}eb-badge--info{else}eb-badge--success{/if}">
          {if $pendingVerificationCount > 0}In review{else}Clear{/if}
        </span>
      </div>
      <div class="mt-2 flex items-baseline gap-2">
        <div class="text-2xl font-semibold tabular-nums text-[var(--eb-text-primary)]">{$pendingVerificationCount}</div>
        <div class="text-sm text-[var(--eb-text-muted)]">pending</div>
      </div>
      <div class="mt-1 text-sm leading-snug text-[var(--eb-text-secondary)]">
        {if $pendingVerificationCount > 0}Stripe is reviewing submitted details{else}No pending verification items{/if}
      </div>
    </div>
  </div>
</div>

    <div class="eb-card-raised mb-4 p-4">
      <h3 class="eb-app-card-title mb-2">What's missing (currently due)</h3>
      {if $reqCount > 0 || $pastDueCount > 0}
        <p class="eb-field-help mb-3">Complete these items in Stripe to enable payments and payouts.</p>
        <ul class="space-y-2">
          {foreach from=$status.currentlyDue item=item}
            <li class="flex items-center gap-2 text-sm text-[var(--eb-text-primary)]">
              <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--eb-warning-icon)]"></span>
              <span>{$item|escape}</span>
            </li>
          {/foreach}
          {foreach from=$status.pastDue item=item}
            <li class="flex items-center gap-2 text-sm text-[var(--eb-text-primary)]">
              <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--eb-danger-icon)]"></span>
              <span>{$item|escape}</span>
            </li>
          {/foreach}
        </ul>
      {elseif $pendingVerificationCount > 0}
        <p class="eb-field-help mb-3">Stripe is reviewing these items. No action is required right now.</p>
        <ul class="space-y-2">
          {foreach from=$status.pendingVerification item=item}
            <li class="flex items-center gap-2 text-sm text-[var(--eb-text-primary)]">
              <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--eb-info-icon)]"></span>
              <span>{$item|escape}</span>
            </li>
          {/foreach}
        </ul>
      {else}
        <div class="text-sm text-[var(--eb-text-muted)]">No outstanding requirements. {if !$status.chargesEnabled || !$status.payoutsEnabled}Capability changes may still be processing in Stripe.{/if}</div>
      {/if}
    </div>

    <div class="eb-card-raised p-4">
      <h3 class="eb-app-card-title mb-2">Manage Account</h3>
      <p class="mb-3 text-sm text-[var(--eb-text-secondary)]">Use the embedded account management to update business, people, and payouts.</p>
      <a href="{$modulelink}&a=ph-stripe-manage" class="eb-btn eb-btn-primary eb-btn-sm">Open Account Management</a>
    </div>
          </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='stripe-connect'
  ebPhBodyClass='!p-0'
  ebPhContent=$ebPhContent
}
