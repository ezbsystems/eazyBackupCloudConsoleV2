{* Partner Hub — Canonical tenant detail tabs *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{assign var=activeTab value=$active_tab|default:'profile'}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-6xl px-6 py-8">
    <div class="flex items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold tracking-tight">Customer Tenant #{$tenant.id|escape} - {$tenant.name|default:'Unnamed'|escape}</h1>
      <a href="{$modulelink}&a=ph-tenants" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5">Back to Customer Tenants</a>
    </div>

    {if $notice neq ''}
      <div class="mt-4 rounded-xl bg-emerald-500/20 ring-1 ring-emerald-400/30 px-4 py-3 text-sm text-white">
        Tenant updated.
      </div>
    {/if}
    {if $error neq ''}
      <div class="mt-4 rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-200">
        Unable to process the request ({$error|escape}).
      </div>
    {/if}

    <nav class="mt-6 inline-flex flex-wrap gap-2 rounded-2xl bg-[rgb(var(--bg-card))] p-2 ring-1 ring-white/10" aria-label="Tenant detail tabs">
      <a href="{$tab_links.profile|default:'#'|escape}" class="rounded-xl px-3 py-2 text-sm {if $activeTab eq 'profile'}bg-white/10 text-white{else}text-white/70 hover:bg-white/5{/if}">Profile</a>
      <a href="{$tab_links.members|default:'#'|escape}" class="rounded-xl px-3 py-2 text-sm {if $activeTab eq 'members'}bg-white/10 text-white{else}text-white/70 hover:bg-white/5{/if}">Members</a>
      <a href="{$tab_links.storage_users|default:'#'|escape}" class="rounded-xl px-3 py-2 text-sm {if $activeTab eq 'storage_users'}bg-white/10 text-white{else}text-white/70 hover:bg-white/5{/if}">Storage Users</a>
      <a href="{$tab_links.billing|default:'#'|escape}" class="rounded-xl px-3 py-2 text-sm {if $activeTab eq 'billing'}bg-white/10 text-white{else}text-white/70 hover:bg-white/5{/if}">Billing</a>
      <a href="{$tab_links.white_label|default:'#'|escape}" class="rounded-xl px-3 py-2 text-sm {if $activeTab eq 'white_label'}bg-white/10 text-white{else}text-white/70 hover:bg-white/5{/if}">White Label</a>
    </nav>

    {if $activeTab eq 'profile'}
      <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
        <div class="px-6 py-5">
          <h2 class="text-lg font-medium">Edit Customer Tenant</h2>
        </div>
        <div class="border-t border-white/10"></div>
        <form method="post" action="{$modulelink}&a=ph-tenant&id={$tenant.id}" class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-4">
          <input type="hidden" name="tenant_id" value="{$tenant.id}" />
          <input type="hidden" name="eb_save_tenant" value="1" />
          {if isset($token) && $token ne ''}
            <input type="hidden" name="token" value="{$token}" />
          {/if}

          <label class="md:col-span-5 block">
            <span class="text-sm text-white/70">Tenant Name</span>
            <input name="name" value="{$tenant.name|escape}" required class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
          </label>
          <label class="md:col-span-4 block">
            <span class="text-sm text-white/70">Slug</span>
            <input name="slug" value="{$tenant.slug|escape}" required class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
          </label>
          <label class="md:col-span-3 block">
            <span class="text-sm text-white/70">Status</span>
            <select name="status" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
              {foreach from=$statuses item=s}
                <option value="{$s|escape}" {if $tenant.status == $s}selected{/if}>{$s|escape}</option>
              {/foreach}
            </select>
          </label>

          <label class="md:col-span-6 block">
            <span class="text-sm text-white/70">Contact Email</span>
            <input type="email" name="contact_email" value="{$tenant.contact_email|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
          </label>
          <label class="md:col-span-3 block">
            <span class="text-sm text-white/70">Created</span>
            <input value="{$tenant.created_at|default:'-'|escape}" disabled class="mt-2 w-full rounded-xl bg-white/5 text-white/60 ring-1 ring-white/10 px-3.5 py-2.5" />
          </label>
          <label class="md:col-span-3 block">
            <span class="text-sm text-white/70">Last Updated</span>
            <input value="{$tenant.updated_at|default:'-'|escape}" disabled class="mt-2 w-full rounded-xl bg-white/5 text-white/60 ring-1 ring-white/10 px-3.5 py-2.5" />
          </label>

          <div class="md:col-span-12 flex justify-end">
            <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Save Customer Tenant</button>
          </div>
        </form>
      </section>

      <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
        <div class="px-6 py-5">
          <h2 class="text-lg font-medium">Canonical Tenant Status</h2>
        </div>
        <div class="border-t border-white/10"></div>
        <div class="px-6 py-5">
          <span class="inline-flex items-center rounded-full px-3 py-1 text-xs ring-1 ring-white/15 text-white/70">{$tenant.status|default:'active'|escape}</span>
        </div>
      </section>

      <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-rose-500/30 overflow-hidden">
        <div class="px-6 py-5">
          <h2 class="text-lg font-medium text-rose-200">Danger Zone</h2>
        </div>
        <div class="border-t border-rose-500/20"></div>
        <form method="post" action="{$modulelink}&a=ph-tenant&id={$tenant.id}" class="px-6 py-6 flex items-center justify-between gap-4">
          <input type="hidden" name="tenant_id" value="{$tenant.id}" />
          <input type="hidden" name="eb_delete_tenant" value="1" />
          {if isset($token) && $token ne ''}
            <input type="hidden" name="token" value="{$token}" />
          {/if}
          <p class="text-sm text-white/70">Delete this customer tenant (marks canonical status as deleted when safe).</p>
          <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-rose-600 hover:bg-rose-500" onclick="return confirm('Delete this customer tenant?');">Delete Customer Tenant</button>
        </form>
      </section>
    {elseif $activeTab eq 'members'}
      <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
        <div class="px-6 py-5 flex items-center justify-between">
          <h2 class="text-lg font-medium">Tenant Members</h2>
          <span class="text-sm text-white/60">{$members|@count} total</span>
        </div>
        <div class="border-t border-white/10"></div>
        {if $members_error|default:'' neq ''}
          <div class="px-6 py-5 text-sm text-rose-200">Unable to load tenant members ({$members_error|escape}).</div>
        {elseif $members|@count eq 0}
          <div class="px-6 py-5 text-sm text-white/60">No tenant members found.</div>
        {else}
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-left text-white/60">
                <tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Role</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Last Login</th></tr>
              </thead>
              <tbody class="divide-y divide-white/10">
                {foreach from=$members item=member}
                  <tr>
                    <td class="px-6 py-3">{$member.name|default:'-'|escape}</td>
                    <td class="px-6 py-3">{$member.email|default:'-'|escape}</td>
                    <td class="px-6 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs ring-1 ring-white/20">{$member.role|default:'user'|escape}</span></td>
                    <td class="px-6 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs ring-1 {if $member.status eq 'active'}ring-emerald-400/40 text-emerald-200{else}ring-rose-400/40 text-rose-200{/if}">{$member.status|default:'disabled'|escape}</span></td>
                    <td class="px-6 py-3">{$member.last_login_at|default:'-'|escape}</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        {/if}
      </section>
    {elseif $activeTab eq 'storage_users'}
      <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
        <div class="px-6 py-5 flex items-center justify-between">
          <h2 class="text-lg font-medium">Storage Users</h2>
          <span class="text-sm text-white/60">{$storage_users|@count} total</span>
        </div>
        <div class="border-t border-white/10"></div>
        {if $storage_users_error|default:'' neq ''}
          <div class="px-6 py-5 text-sm text-rose-200">Unable to load storage users ({$storage_users_error|escape}).</div>
        {elseif $storage_users|@count eq 0}
          <div class="px-6 py-5 text-sm text-white/60">No storage users linked to this tenant.</div>
        {else}
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="text-left text-white/60">
                <tr><th class="px-6 py-3">Username</th><th class="px-6 py-3">Email</th><th class="px-6 py-3">Status</th><th class="px-6 py-3">Updated</th></tr>
              </thead>
              <tbody class="divide-y divide-white/10">
                {foreach from=$storage_users item=user}
                  <tr>
                    <td class="px-6 py-3 font-mono">{$user.username|default:'-'|escape}</td>
                    <td class="px-6 py-3">{$user.email|default:'-'|escape}</td>
                    <td class="px-6 py-3"><span class="inline-flex rounded-full px-2 py-1 text-xs ring-1 {if $user.status eq 'active'}ring-emerald-400/40 text-emerald-200{else}ring-rose-400/40 text-rose-200{/if}">{$user.status|default:'disabled'|escape}</span></td>
                    <td class="px-6 py-3">{$user.updated_at|default:'-'|escape}</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        {/if}
      </section>
    {elseif $activeTab eq 'billing'}
      <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
        <div class="px-6 py-5"><h2 class="text-lg font-medium">Billing Overview</h2></div>
        <div class="border-t border-white/10"></div>
        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div class="rounded-xl bg-white/5 ring-1 ring-white/10 p-4">
            <div class="text-white/60">Customer Record</div>
            {if $billing_customer}
              <div class="mt-2">ID #{$billing_customer.id|escape} / WHMCS Client #{$billing_customer.whmcs_client_id|escape}</div>
              <div class="mt-1">Stripe Customer: {$billing_customer.stripe_customer_id|default:'-'|escape}</div>
              <div class="mt-1">Status: {$billing_customer.status|default:'-'|escape}</div>
            {else}
              <div class="mt-2 text-white/70">No mapped billing customer found for this tenant.</div>
            {/if}
          </div>
          <div class="rounded-xl bg-white/5 ring-1 ring-white/10 p-4">
            <div class="text-white/60">Quick Totals</div>
            <div class="mt-2">Subscriptions: {$billing_subscriptions_count|default:0|escape}</div>
            <div class="mt-1">Usage Metrics: {$billing_usage_metrics_count|default:0|escape}</div>
            <div class="mt-1">Invoices Cached: {$billing_invoices_count|default:0|escape}</div>
          </div>
        </div>
        {if $billing_error|default:'' neq ''}
          <div class="px-6 pb-5 text-sm text-rose-200">Unable to load some billing data ({$billing_error|escape}).</div>
        {/if}
      </section>
    {elseif $activeTab eq 'white_label'}
      <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
        <div class="px-6 py-5"><h2 class="text-lg font-medium">White Label Mapping</h2></div>
        <div class="border-t border-white/10"></div>
        <div class="px-6 py-5 text-sm">
          {if $whitelabel_error|default:'' neq ''}
            <div class="text-rose-200">Unable to load white-label data ({$whitelabel_error|escape}).</div>
          {elseif !$whitelabel_tenant}
            <div class="text-white/70">No white-label tenant is mapped to this canonical tenant.</div>
          {else}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="rounded-xl bg-white/5 ring-1 ring-white/10 p-4">
                <div>Status: {$whitelabel_tenant.status|default:'-'|escape}</div>
                <div class="mt-1">FQDN: {$whitelabel_tenant.fqdn|default:'-'|escape}</div>
                <div class="mt-1">Subdomain: {$whitelabel_tenant.subdomain|default:'-'|escape}</div>
                <div class="mt-1">Custom Domain: {$whitelabel_tenant.custom_domain|default:'-'|escape}</div>
              </div>
              <div class="rounded-xl bg-white/5 ring-1 ring-white/10 p-4">
                <div>Custom Domains: {$whitelabel_custom_domains|@count}</div>
                <div class="mt-1">Asset Types: {$whitelabel_assets_by_type|@count}</div>
                <div class="mt-1">Org ID: {$whitelabel_tenant.org_id|default:'-'|escape}</div>
              </div>
            </div>
          {/if}
        </div>
      </section>
    {/if}
  </div>
</div>

