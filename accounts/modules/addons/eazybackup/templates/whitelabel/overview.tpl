{* Partner Hub — Overview dashboard *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{capture assign=ebPhContent}
  <div x-data="{
      setupDismissed: localStorage.getItem('eb_ph_setup_dismissed') === 'true',
      dismissSetup() {
        this.setupDismissed = true;
        localStorage.setItem('eb_ph_setup_dismissed', 'true');
      }
    }">
    <div class="space-y-6">

            {* --- 1. Setup Wizard --- *}
            {if !isset($setup.all_complete) || !$setup.all_complete}
            <section class="eb-card-raised overflow-hidden !p-0">
              <div class="border-b border-[var(--eb-border-subtle)] px-6 py-4">
                <h2 class="eb-app-card-title">Get started</h2>
                <p class="eb-field-help mt-1 !text-[var(--eb-text-secondary)]">Complete these steps to set up your Partner Hub.</p>
              </div>
              <ul class="divide-y divide-[var(--eb-border-subtle)]">
                <li class="flex flex-wrap items-center gap-4 px-6 py-4">
                  {if isset($setup.stripe_connected) && $setup.stripe_connected}
                    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                  {else}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full border border-[var(--eb-border-orange)] bg-[var(--eb-primary-soft)] text-sm font-semibold text-[var(--eb-primary)]">1</span>
                    <div class="min-w-0 flex-1">
                      <span class="font-medium text-[var(--eb-text-primary)]">Connect Stripe</span>
                      <p class="text-sm text-[var(--eb-text-muted)]">Required to accept payments.</p>
                    </div>
                    <a href="{$modulelink}&a=ph-stripe-onboard" class="eb-btn eb-btn-warning eb-btn-sm shrink-0">Connect Stripe</a>
                  {/if}
                  {if isset($setup.stripe_connected) && $setup.stripe_connected}
                    <span class="text-sm text-[var(--eb-text-muted)]">Stripe connected</span>
                  {/if}
                </li>
                <li class="flex flex-wrap items-center gap-4 px-6 py-4">
                  {if isset($setup.has_products) && $setup.has_products}
                    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                    <span class="text-sm text-[var(--eb-text-muted)]">Product created</span>
                  {else}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full border border-[var(--eb-border-orange)] bg-[var(--eb-primary-soft)] text-sm font-semibold text-[var(--eb-primary)]">2</span>
                    <div class="min-w-0 flex-1">
                      <span class="font-medium text-[var(--eb-text-primary)]">Create a Product</span>
                      <p class="text-sm text-[var(--eb-text-muted)]">Add at least one catalog product.</p>
                    </div>
                    <a href="{$modulelink}&a=ph-catalog-products" class="eb-btn eb-btn-outline eb-btn-sm shrink-0">Create Product</a>
                  {/if}
                </li>
                <li class="flex flex-wrap items-center gap-4 px-6 py-4">
                  {if isset($setup.has_plans) && $setup.has_plans}
                    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                    <span class="text-sm text-[var(--eb-text-muted)]">Plan created</span>
                  {else}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full border border-[var(--eb-border-orange)] bg-[var(--eb-primary-soft)] text-sm font-semibold text-[var(--eb-primary)]">3</span>
                    <div class="min-w-0 flex-1">
                      <span class="font-medium text-[var(--eb-text-primary)]">Create a Plan</span>
                      <p class="text-sm text-[var(--eb-text-muted)]">Define a pricing plan for subscriptions.</p>
                    </div>
                    <a href="{$modulelink}&a=ph-catalog-plans" class="eb-btn eb-btn-outline eb-btn-sm shrink-0">Create Plan</a>
                  {/if}
                </li>
                <li class="flex flex-wrap items-center gap-4 px-6 py-4">
                  {if isset($setup.has_tenants) && $setup.has_tenants}
                    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                    <span class="text-sm text-[var(--eb-text-muted)]">Tenant added</span>
                  {else}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full border border-[var(--eb-border-orange)] bg-[var(--eb-primary-soft)] text-sm font-semibold text-[var(--eb-primary)]">4</span>
                    <div class="min-w-0 flex-1">
                      <span class="font-medium text-[var(--eb-text-primary)]">Add a Tenant</span>
                      <p class="text-sm text-[var(--eb-text-muted)]">Create your first customer tenant.</p>
                    </div>
                    <a href="{$modulelink}&a=ph-tenants-manage" class="eb-btn eb-btn-outline eb-btn-sm shrink-0">Add Tenant</a>
                  {/if}
                </li>
                <li class="flex flex-wrap items-center gap-4 px-6 py-4">
                  {if isset($setup.has_subscriptions) && $setup.has_subscriptions}
                    <span class="eb-icon-box eb-icon-box--sm eb-icon-box--orange"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></span>
                    <span class="text-sm text-[var(--eb-text-muted)]">Subscription active</span>
                  {else}
                    <span class="flex h-8 w-8 items-center justify-center rounded-full border border-[var(--eb-border-orange)] bg-[var(--eb-primary-soft)] text-sm font-semibold text-[var(--eb-primary)]">5</span>
                    <div class="min-w-0 flex-1">
                      <span class="font-medium text-[var(--eb-text-primary)]">Create a Subscription</span>
                      <p class="text-sm text-[var(--eb-text-muted)]">Attach a plan to a tenant.</p>
                    </div>
                    <a href="{$modulelink}&a=ph-billing-subscriptions" class="eb-btn eb-btn-outline eb-btn-sm shrink-0">Subscriptions</a>
                  {/if}
                </li>
              </ul>
            </section>
            {else}
            <section x-show="!setupDismissed" class="eb-alert eb-alert--success flex flex-row items-center justify-between gap-3">
              <span class="flex items-center gap-2">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Setup complete
              </span>
              <button type="button" @click="dismissSetup()" class="eb-btn eb-btn-ghost eb-btn-xs shrink-0">Dismiss</button>
            </section>
            {/if}

            {* Stripe onboarding banners *}
            {if !isset($connect.chargesEnabled) || !$connect.chargesEnabled}
              <div class="eb-alert eb-alert--warning text-sm">
                To accept payments, finish Stripe onboarding for this MSP. Click Connect Stripe to get started.
              </div>
            {/if}
            {if isset($connect_due) && $connect_due|@count > 0}
              <div class="eb-alert eb-alert--warning text-sm">
                Stripe requires additional information. <a href="{$modulelink}&a=ph-stripe-connect" class="underline">View details</a> or <a href="{$modulelink}&a=ph-stripe-onboard" class="underline">Resume onboarding</a>.
              </div>
            {/if}
            {if isset($onboardError) && $onboardError}
              <div class="eb-alert eb-alert--danger text-sm">
                We couldn't start Stripe onboarding. Please try again.
              </div>
            {/if}
            {if isset($onboardSuccess) && $onboardSuccess}
              <div class="eb-alert eb-alert--success text-sm">
                Stripe onboarding complete. What's next: connect status may take a moment to update; you can review <a class="underline" href="{$modulelink}&a=ph-stripe-connect">Connect &amp; Status</a> or proceed to <a class="underline" href="{$modulelink}&a=ph-stripe-manage">Manage Account</a>.
              </div>
            {/if}
            {if isset($onboardRefresh) && $onboardRefresh}
              <div class="eb-alert eb-alert--info text-sm">
                You can resume Stripe onboarding at any time. If setup is complete, continue to <a class="underline" href="{$modulelink}&a=ph-stripe-manage">Manage Account</a>.
              </div>
            {/if}

            {* --- 2. Stripe Connect Status --- *}
            <section class="eb-card-raised flex flex-wrap items-center justify-between gap-4 !p-6">
              <div class="flex flex-wrap items-center gap-3">
                <span class="font-medium text-[var(--eb-text-primary)]">Stripe Connect</span>
                {if isset($connect_id_masked) && $connect_id_masked neq ''}
                  <span class="eb-type-mono text-sm text-[var(--eb-text-muted)]">{$connect_id_masked|escape}</span>
                {/if}
              </div>
              <div class="flex flex-wrap items-center gap-2">
                {if !isset($connect.hasAccount) || !$connect.hasAccount}
                  <span class="eb-badge eb-badge--danger">Not Connected</span>
                  <a href="{$modulelink}&a=ph-stripe-onboard" class="eb-btn eb-btn-warning eb-btn-sm">Connect Stripe</a>
                {else}
                  {if isset($connect.chargesEnabled) && $connect.chargesEnabled}
                    <span class="eb-badge eb-badge--success">Charges Enabled</span>
                  {else}
                    <span class="eb-badge eb-badge--warning">Pending</span>
                  {/if}
                  {if isset($connect.payoutsEnabled) && $connect.payoutsEnabled}
                    <span class="eb-badge eb-badge--success">Payouts Enabled</span>
                  {/if}
                  {if isset($connect_due) && $connect_due|@count > 0}
                    <a href="{$modulelink}&a=ph-stripe-connect" class="eb-badge eb-badge--warning no-underline hover:opacity-90">Action Required</a>
                  {/if}
                  <a href="{$modulelink}&a=ph-stripe-connect" class="eb-btn eb-btn-outline eb-btn-sm">Connect &amp; Status</a>
                {/if}
              </div>
            </section>

            {* --- 3. Key Metrics Grid --- *}
            <section class="grid grid-cols-2 gap-4 md:grid-cols-3">
              <a href="{$modulelink}&a=ph-tenants-manage" class="eb-card-raised block !p-6 transition-shadow hover:shadow-[var(--eb-shadow-md)]">
                <div class="text-3xl font-semibold tabular-nums text-[var(--eb-text-primary)]">{$counts.tenants|default:0}</div>
                <div class="eb-stat-label mt-1">Tenants</div>
              </a>
              <a href="{$modulelink}&a=ph-billing-subscriptions" class="eb-card-raised block !p-6 transition-shadow hover:shadow-[var(--eb-shadow-md)]">
                <div class="text-3xl font-semibold tabular-nums text-[var(--eb-text-primary)]">{$counts.active_subscriptions|default:0}</div>
                <div class="eb-stat-label mt-1">Active Subscriptions</div>
              </a>
              <a href="{$modulelink}&a=ph-catalog-products" class="eb-card-raised block !p-6 transition-shadow hover:shadow-[var(--eb-shadow-md)]">
                <div class="text-3xl font-semibold tabular-nums text-[var(--eb-text-primary)]">{$counts.products|default:0}</div>
                <div class="eb-stat-label mt-1">Products</div>
              </a>
              <a href="{$modulelink}&a=ph-catalog-plans" class="eb-card-raised block !p-6 transition-shadow hover:shadow-[var(--eb-shadow-md)]">
                <div class="text-3xl font-semibold tabular-nums text-[var(--eb-text-primary)]">{$counts.plans|default:0}</div>
                <div class="eb-stat-label mt-1">Plans</div>
              </a>
              <a href="{$modulelink}&a=ph-signup-approvals" class="eb-card-raised block !p-6 transition-shadow hover:shadow-[var(--eb-shadow-md)] {if isset($counts.pending_approvals) && $counts.pending_approvals > 0}ring-2 ring-[var(--eb-border-orange)]{/if}">
                <div class="flex items-center gap-2 text-3xl font-semibold tabular-nums text-[var(--eb-text-primary)]">
                  {$counts.pending_approvals|default:0}
                  {if isset($counts.pending_approvals) && $counts.pending_approvals > 0}
                    <span class="eb-status-dot eb-status-dot--warning" title="Pending"></span>
                  {/if}
                </div>
                <div class="eb-stat-label mt-1">Pending Approvals</div>
              </a>
              <a href="{$WEB_ROOT}/index.php?m=eazybackup&a=whitelabel-branding" class="eb-card-raised block !p-6 transition-shadow hover:shadow-[var(--eb-shadow-md)]">
                <div class="text-3xl font-semibold tabular-nums text-[var(--eb-text-primary)]">{$counts.whitelabel_tenants|default:0}</div>
                <div class="eb-stat-label mt-1">White-Label Tenants</div>
              </a>
            </section>

            {* --- 4. Billing Snapshot --- *}
            {if isset($billing) && $billing !== null}
            <section class="eb-card-raised overflow-hidden !p-0">
              <div class="flex items-center justify-between border-b border-[var(--eb-border-subtle)] px-6 py-4">
                <h2 class="eb-app-card-title">Billing</h2>
                <a href="{$modulelink}&a=ph-billing-invoices" class="eb-btn eb-btn-ghost eb-btn-xs">View All</a>
              </div>
              <div class="grid grid-cols-2 gap-4 p-6 md:grid-cols-4">
                <div>
                  <div class="text-2xl font-semibold tabular-nums text-[var(--eb-text-primary)]">
                    {if $billing.currency eq 'USD' || $billing.currency eq 'CAD'}$ {/if}{($billing.revenue_this_month / 100)|string_format:"%.2f"}{if $billing.currency neq 'USD' && $billing.currency neq 'CAD'} {$billing.currency|escape}{/if}
                  </div>
                  <div class="eb-stat-label mt-1">Revenue this month</div>
                </div>
                <div>
                  <div class="text-2xl font-semibold tabular-nums text-[var(--eb-text-primary)]">{$billing.invoices_this_month|default:0}</div>
                  <div class="eb-stat-label mt-1">Invoices this month</div>
                </div>
                <div>
                  <div class="text-2xl font-semibold tabular-nums {if $billing.outstanding_invoices > 0}text-[var(--eb-warning-text)]{else}text-[var(--eb-text-primary)]{/if}">{$billing.outstanding_invoices|default:0}</div>
                  <div class="eb-stat-label mt-1">Outstanding</div>
                </div>
                <div>
                  <div class="text-2xl font-semibold tabular-nums {if $billing.failed_payments > 0}text-[var(--eb-danger-text)]{else}text-[var(--eb-text-primary)]{/if}">{$billing.failed_payments|default:0}</div>
                  <div class="eb-stat-label mt-1">Failed payments</div>
                </div>
              </div>
            </section>
            {elseif isset($billing_unavailable) && $billing_unavailable}
            <section class="eb-card-raised !p-6">
              <p class="eb-field-help !text-[var(--eb-text-secondary)]">Billing data temporarily unavailable.</p>
            </section>
            {/if}

            {* --- 5. Recent Tenants + Quick Actions --- *}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
              <section class="eb-card-raised overflow-hidden !p-0">
                <div class="flex items-center justify-between border-b border-[var(--eb-border-subtle)] px-6 py-4">
                  <h2 class="eb-app-card-title">Recent Tenants</h2>
                  <a href="{$modulelink}&a=ph-tenants-manage" class="eb-btn eb-btn-ghost eb-btn-xs">View All</a>
                </div>
                <div class="p-4">
                  {if isset($recent_tenants) && $recent_tenants|@count > 0}
                    <ul class="divide-y divide-[var(--eb-border-subtle)]">
                      {foreach $recent_tenants as $tenant}
                        <li class="flex items-center justify-between gap-4 py-3">
                          <a href="{$modulelink}&a=ph-tenant&id={$tenant.public_id|escape:'url'}" class="font-medium text-[var(--eb-text-primary)] hover:underline">{$tenant.name|default:'—'|escape}</a>
                          <span class="flex items-center gap-2">
                            {if isset($tenant.status) && $tenant.status eq 'active'}
                              <span class="eb-badge eb-badge--success">active</span>
                            {elseif isset($tenant.status) && $tenant.status eq 'suspended'}
                              <span class="eb-badge eb-badge--warning">suspended</span>
                            {/if}
                            <span class="text-sm text-[var(--eb-text-muted)]">{$tenant.created_at|date_format:'%b %e, %Y'}</span>
                          </span>
                        </li>
                      {/foreach}
                    </ul>
                  {else}
                    <p class="eb-field-help py-4 !text-[var(--eb-text-secondary)]">No tenants yet. <a href="{$modulelink}&a=ph-tenants-manage" class="text-[var(--eb-primary)] underline">Create your first tenant</a>.</p>
                  {/if}
                </div>
              </section>
              <section class="eb-card-raised overflow-hidden !p-0">
                <div class="border-b border-[var(--eb-border-subtle)] px-6 py-4">
                  <h2 class="eb-app-card-title">Quick Actions</h2>
                </div>
                <div class="flex flex-col gap-2 p-4">
                  <a href="{$modulelink}&a=ph-tenants-manage" class="eb-btn eb-btn-primary eb-btn-md text-center">Create New Tenant</a>
                  <a href="{$modulelink}&a=ph-catalog-products" class="eb-btn eb-btn-outline eb-btn-md text-center">Create Product</a>
                  <a href="{$modulelink}&a=ph-stripe-manage" class="eb-btn eb-btn-outline eb-btn-md text-center">Manage Stripe</a>
                  <a href="{$modulelink}&a=ph-signup-approvals" class="eb-btn eb-btn-outline eb-btn-md flex items-center justify-center gap-2 text-center">
                    Signup Approvals
                    {if isset($counts.pending_approvals) && $counts.pending_approvals > 0}
                      <span class="eb-badge eb-badge--warning">{$counts.pending_approvals}</span>
                    {/if}
                  </a>
                </div>
              </section>
            </div>

    </div>
  </div>
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='overview'
  ebPhTitle='Partner Hub'
  ebPhDescription='Monitor setup progress, billing readiness, and recent customer activity from one place.'
  ebPhContent=$ebPhContent
}
