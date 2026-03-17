{* Partner Hub — Overview dashboard *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{
      sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360,
      toggleCollapse() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed);
      },
      handleResize() {
        if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true;
      },
      setupDismissed: localStorage.getItem('eb_ph_setup_dismissed') === 'true',
      dismissSetup() {
        this.setupDismissed = true;
        localStorage.setItem('eb_ph_setup_dismissed', 'true');
      }
    }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='overview'}
        <main class="flex-1 min-w-0 overflow-x-auto">
          <div class="border-b border-white/10 px-6 py-4">
            <h1 class="text-2xl font-semibold tracking-tight">Partner Hub</h1>
          </div>
          <div class="p-6 space-y-6">

            {* --- 1. Setup Wizard --- *}
            {if !isset($setup.all_complete) || !$setup.all_complete}
            <section class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-white/10">
                <h2 class="text-white text-lg font-medium">Get started</h2>
                <p class="text-sm text-white/80 mt-1">Complete these steps to set up your Partner Hub.</p>
              </div>
              <ul class="divide-y divide-white/10">
                <li class="flex items-center gap-4 px-6 py-4">
                  {if isset($setup.stripe_connected) && $setup.stripe_connected}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                  {else}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/20 text-amber-400 text-sm font-medium">1</span>
                    <div class="flex-1">
                      <span class="font-medium">Connect Stripe</span>
                      <p class="text-sm text-white/70">Required to accept payments.</p>
                    </div>
                    <a href="{$modulelink}&a=ph-stripe-onboard" class="rounded-xl px-4 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-500">Connect Stripe</a>
                  {/if}
                  {if isset($setup.stripe_connected) && $setup.stripe_connected}
                    <span class="text-sm text-white/70">Stripe connected</span>
                  {/if}
                </li>
                <li class="flex items-center gap-4 px-6 py-4">
                  {if isset($setup.has_products) && $setup.has_products}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                    <span class="text-sm text-white/70">Product created</span>
                  {else}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/20 text-amber-400 text-sm font-medium">2</span>
                    <div class="flex-1">
                      <span class="font-medium">Create a Product</span>
                      <p class="text-sm text-white/70">Add at least one catalog product.</p>
                    </div>
                    <a href="{$modulelink}&a=ph-catalog-products" class="rounded-xl px-4 py-2 text-sm font-medium ring-1 ring-white/20 text-white hover:bg-white/5">Create Product</a>
                  {/if}
                </li>
                <li class="flex items-center gap-4 px-6 py-4">
                  {if isset($setup.has_plans) && $setup.has_plans}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                    <span class="text-sm text-white/70">Plan created</span>
                  {else}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/20 text-amber-400 text-sm font-medium">3</span>
                    <div class="flex-1">
                      <span class="font-medium">Create a Plan</span>
                      <p class="text-sm text-white/70">Define a pricing plan for subscriptions.</p>
                    </div>
                    <a href="{$modulelink}&a=ph-catalog-plans" class="rounded-xl px-4 py-2 text-sm font-medium ring-1 ring-white/20 text-white hover:bg-white/5">Create Plan</a>
                  {/if}
                </li>
                <li class="flex items-center gap-4 px-6 py-4">
                  {if isset($setup.has_tenants) && $setup.has_tenants}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                    <span class="text-sm text-white/70">Tenant added</span>
                  {else}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/20 text-amber-400 text-sm font-medium">4</span>
                    <div class="flex-1">
                      <span class="font-medium">Add a Tenant</span>
                      <p class="text-sm text-white/70">Create your first customer tenant.</p>
                    </div>
                    <a href="{$modulelink}&a=ph-tenants-manage" class="rounded-xl px-4 py-2 text-sm font-medium ring-1 ring-white/20 text-white hover:bg-white/5">Add Tenant</a>
                  {/if}
                </li>
                <li class="flex items-center gap-4 px-6 py-4">
                  {if isset($setup.has_subscriptions) && $setup.has_subscriptions}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                    <span class="text-sm text-white/70">Subscription active</span>
                  {else}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500/20 text-amber-400 text-sm font-medium">5</span>
                    <div class="flex-1">
                      <span class="font-medium">Create a Subscription</span>
                      <p class="text-sm text-white/70">Attach a plan to a tenant.</p>
                    </div>
                    <a href="{$modulelink}&a=ph-billing-subscriptions" class="rounded-xl px-4 py-2 text-sm font-medium ring-1 ring-white/20 text-white hover:bg-white/5">Subscriptions</a>
                  {/if}
                </li>
              </ul>
            </section>
            {else}
            <section x-show="!setupDismissed" class="rounded-2xl bg-emerald-500/10 ring-1 ring-emerald-400/20 px-4 py-3 flex items-center justify-between">
              <span class="flex items-center gap-2 text-emerald-200">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Setup complete
              </span>
              <button type="button" @click="dismissSetup()" class="text-sm text-white/70 hover:text-white">Dismiss</button>
            </section>
            {/if}

            {* Stripe onboarding banners *}
            {if !isset($connect.chargesEnabled) || !$connect.chargesEnabled}
              <div class="rounded-xl bg-amber-500/10 ring-1 ring-amber-400/20 px-4 py-3 text-sm text-amber-200">
                To accept payments, finish Stripe onboarding for this MSP. Click Connect Stripe to get started.
              </div>
            {/if}
            {if isset($connect_due) && $connect_due|@count > 0}
              <div class="rounded-xl bg-amber-500/10 ring-1 ring-amber-400/20 px-4 py-3 text-sm text-amber-200">
                Stripe requires additional information. <a href="{$modulelink}&a=ph-stripe-connect" class="underline">View details</a> or <a href="{$modulelink}&a=ph-stripe-onboard" class="underline">Resume onboarding</a>.
              </div>
            {/if}
            {if isset($onboardError) && $onboardError}
              <div class="rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-200">
                We couldn't start Stripe onboarding. Please try again.
              </div>
            {/if}
            {if isset($onboardSuccess) && $onboardSuccess}
              <div class="rounded-xl bg-emerald-500/20 ring-1 ring-emerald-400/20 px-4 py-3 text-sm text-white">
                Stripe onboarding complete. What's next: connect status may take a moment to update; you can review <a class="underline" href="{$modulelink}&a=ph-stripe-connect">Connect &amp; Status</a> or proceed to <a class="underline" href="{$modulelink}&a=ph-stripe-manage">Manage Account</a>.
              </div>
            {/if}
            {if isset($onboardRefresh) && $onboardRefresh}
              <div class="rounded-xl bg-white/5 ring-1 ring-white/10 px-4 py-3 text-sm text-white/70">
                You can resume Stripe onboarding at any time. If setup is complete, continue to <a class="underline" href="{$modulelink}&a=ph-stripe-manage">Manage Account</a>.
              </div>
            {/if}

            {* --- 2. Stripe Connect Status --- *}
            <section class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 px-6 py-4 flex flex-wrap items-center justify-between gap-4">
              <div class="flex items-center gap-3">
                <span class="font-medium">Stripe Connect</span>
                {if isset($connect_id_masked) && $connect_id_masked neq ''}
                  <span class="text-sm text-white/60 font-mono">{$connect_id_masked|escape}</span>
                {/if}
              </div>
              <div class="flex flex-wrap items-center gap-2">
                {if !isset($connect.hasAccount) || !$connect.hasAccount}
                  <span class="rounded-lg px-3 py-1 text-sm font-medium bg-rose-500/20 text-rose-200 ring-1 ring-rose-400/30">Not Connected</span>
                  <a href="{$modulelink}&a=ph-stripe-onboard" class="rounded-lg px-3 py-1 text-sm font-medium bg-amber-600 text-white hover:bg-amber-500">Connect Stripe</a>
                {else}
                  {if isset($connect.chargesEnabled) && $connect.chargesEnabled}
                    <span class="rounded-lg px-3 py-1 text-sm font-medium bg-emerald-500/20 text-emerald-200 ring-1 ring-emerald-400/30">Charges Enabled</span>
                  {else}
                    <span class="rounded-lg px-3 py-1 text-sm font-medium bg-amber-500/20 text-amber-200 ring-1 ring-amber-400/30">Pending</span>
                  {/if}
                  {if isset($connect.payoutsEnabled) && $connect.payoutsEnabled}
                    <span class="rounded-lg px-3 py-1 text-sm font-medium bg-emerald-500/20 text-emerald-200 ring-1 ring-emerald-400/30">Payouts Enabled</span>
                  {/if}
                  {if isset($connect_due) && $connect_due|@count > 0}
                    <a href="{$modulelink}&a=ph-stripe-connect" class="rounded-lg px-3 py-1 text-sm font-medium bg-amber-500/20 text-amber-200 ring-1 ring-amber-400/30">Action Required</a>
                  {/if}
                  <a href="{$modulelink}&a=ph-stripe-connect" class="rounded-xl px-4 py-2 text-sm font-medium ring-1 ring-white/20 text-white hover:bg-white/5">Connect &amp; Status</a>
                {/if}
              </div>
            </section>

            {* --- 3. Key Metrics Grid --- *}
            <section class="grid grid-cols-2 md:grid-cols-3 gap-4">
              <a href="{$modulelink}&a=ph-tenants-manage" class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 p-6 hover:ring-white/20 transition-shadow">
                <div class="text-3xl font-semibold tabular-nums">{$counts.tenants|default:0}</div>
                <div class="text-sm text-white/70 mt-1">Tenants</div>
              </a>
              <a href="{$modulelink}&a=ph-billing-subscriptions" class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 p-6 hover:ring-white/20 transition-shadow">
                <div class="text-3xl font-semibold tabular-nums">{$counts.active_subscriptions|default:0}</div>
                <div class="text-sm text-white/70 mt-1">Active Subscriptions</div>
              </a>
              <a href="{$modulelink}&a=ph-catalog-products" class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 p-6 hover:ring-white/20 transition-shadow">
                <div class="text-3xl font-semibold tabular-nums">{$counts.products|default:0}</div>
                <div class="text-sm text-white/70 mt-1">Products</div>
              </a>
              <a href="{$modulelink}&a=ph-catalog-plans" class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 p-6 hover:ring-white/20 transition-shadow">
                <div class="text-3xl font-semibold tabular-nums">{$counts.plans|default:0}</div>
                <div class="text-sm text-white/70 mt-1">Plans</div>
              </a>
              <a href="{$modulelink}&a=ph-signup-approvals" class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 p-6 hover:ring-white/20 transition-shadow {if isset($counts.pending_approvals) && $counts.pending_approvals > 0}ring-2 ring-amber-400/50{/if}">
                <div class="text-3xl font-semibold tabular-nums flex items-center gap-2">
                  {$counts.pending_approvals|default:0}
                  {if isset($counts.pending_approvals) && $counts.pending_approvals > 0}
                    <span class="flex h-2 w-2 rounded-full bg-amber-400" title="Pending"></span>
                  {/if}
                </div>
                <div class="text-sm text-white/70 mt-1">Pending Approvals</div>
              </a>
              <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=whitelabel-branding" class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 p-6 hover:ring-white/20 transition-shadow">
                <div class="text-3xl font-semibold tabular-nums">{$counts.whitelabel_tenants|default:0}</div>
                <div class="text-sm text-white/70 mt-1">White-Label Tenants</div>
              </a>
            </section>

            {* --- 4. Billing Snapshot --- *}
            {if isset($billing) && $billing !== null}
            <section class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 overflow-hidden">
              <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                <h2 class="text-lg font-medium">Billing</h2>
                <a href="{$modulelink}&a=ph-billing-invoices" class="text-sm text-white/70 hover:text-white">View All</a>
              </div>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6">
                <div>
                  <div class="text-2xl font-semibold tabular-nums">
                    {if $billing.currency eq 'USD' || $billing.currency eq 'CAD'}$ {/if}{($billing.revenue_this_month / 100)|string_format:"%.2f"}{if $billing.currency neq 'USD' && $billing.currency neq 'CAD'} {$billing.currency|escape}{/if}
                  </div>
                  <div class="text-sm text-white/70 mt-1">Revenue this month</div>
                </div>
                <div>
                  <div class="text-2xl font-semibold tabular-nums">{$billing.invoices_this_month|default:0}</div>
                  <div class="text-sm text-white/70 mt-1">Invoices this month</div>
                </div>
                <div>
                  <div class="text-2xl font-semibold tabular-nums {if $billing.outstanding_invoices > 0}text-amber-400{/if}">{$billing.outstanding_invoices|default:0}</div>
                  <div class="text-sm text-white/70 mt-1">Outstanding</div>
                </div>
                <div>
                  <div class="text-2xl font-semibold tabular-nums {if $billing.failed_payments > 0}text-rose-400{/if}">{$billing.failed_payments|default:0}</div>
                  <div class="text-sm text-white/70 mt-1">Failed payments</div>
                </div>
              </div>
            </section>
            {elseif isset($billing_unavailable) && $billing_unavailable}
            <section class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 px-6 py-4">
              <p class="text-sm text-white/70">Billing data temporarily unavailable.</p>
            </section>
            {/if}

            {* --- 5. Recent Tenants + Quick Actions --- *}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <section class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 overflow-hidden">
                <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                  <h2 class="text-lg font-medium">Recent Tenants</h2>
                  <a href="{$modulelink}&a=ph-tenants-manage" class="text-sm text-white/70 hover:text-white">View All</a>
                </div>
                <div class="p-4">
                  {if isset($recent_tenants) && $recent_tenants|@count > 0}
                    <ul class="divide-y divide-slate-800/60">
                      {foreach $recent_tenants as $tenant}
                        <li class="py-3 flex items-center justify-between gap-4">
                          <a href="{$modulelink}&a=ph-tenant&id={$tenant.public_id|escape:'url'}" class="font-medium text-white hover:underline">{$tenant.name|default:'—'|escape}</a>
                          <span class="flex items-center gap-2">
                            {if isset($tenant.status) && $tenant.status eq 'active'}
                              <span class="rounded px-2 py-0.5 text-xs font-medium bg-emerald-500/20 text-emerald-200">active</span>
                            {elseif isset($tenant.status) && $tenant.status eq 'suspended'}
                              <span class="rounded px-2 py-0.5 text-xs font-medium bg-amber-500/20 text-amber-200">suspended</span>
                            {/if}
                            <span class="text-sm text-white/60">{$tenant.created_at|date_format:'%b %e, %Y'}</span>
                          </span>
                        </li>
                      {/foreach}
                    </ul>
                  {else}
                    <p class="text-sm text-white/70 py-4">No tenants yet. <a href="{$modulelink}&a=ph-tenants-manage" class="text-white underline">Create your first tenant</a>.</p>
                  {/if}
                </div>
              </section>
              <section class="rounded-2xl bg-slate-900 shadow-xl ring-1 ring-white/10 overflow-hidden">
                <div class="px-6 py-4 border-b border-white/10">
                  <h2 class="text-lg font-medium">Quick Actions</h2>
                </div>
                <div class="p-4 flex flex-col gap-2">
                  <a href="{$modulelink}&a=ph-tenants-manage" class="rounded-xl px-4 py-3 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90 text-center">Create New Tenant</a>
                  <a href="{$modulelink}&a=ph-catalog-products" class="rounded-xl px-4 py-3 font-medium text-white/80 ring-1 ring-white/20 hover:bg-white/5 text-center">Create Product</a>
                  <a href="{$modulelink}&a=ph-stripe-manage" class="rounded-xl px-4 py-3 font-medium text-white/80 ring-1 ring-white/20 hover:bg-white/5 text-center">Manage Stripe</a>
                  <a href="{$modulelink}&a=ph-signup-approvals" class="rounded-xl px-4 py-3 font-medium text-white/80 ring-1 ring-white/20 hover:bg-white/5 text-center flex items-center justify-center gap-2">
                    Signup Approvals
                    {if isset($counts.pending_approvals) && $counts.pending_approvals > 0}
                      <span class="rounded-full bg-amber-500/30 text-amber-200 text-xs px-2 py-0.5">{$counts.pending_approvals}</span>
                    {/if}
                  </a>
                </div>
              </section>
            </div>

          </div>
        </main>
      </div>
    </div>
  </div>
</div>
