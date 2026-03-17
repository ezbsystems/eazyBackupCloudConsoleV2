{* Partner Hub — Canonical tenant detail tabs *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{assign var=activeTab value=$active_tab|default:'profile'}

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
      }
    }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='tenants'}
        <main class="flex-1 min-w-0 overflow-x-auto">
      <div class="w-full max-w-full min-w-0 overflow-hidden px-6 py-6">
      <div class="-mx-6 -mt-6 mb-6 rounded-t-r-3xl border-b border-slate-800/80 bg-slate-900/50 px-6 py-3">
        <nav class="flex flex-wrap items-center gap-1" aria-label="Tenant detail tabs">
          <a href="{$tab_links.profile|default:'#'|escape}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $activeTab eq 'profile'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998-0A4.5 4.5 0 0 0 12 16.5h-1.5a4.5 4.5 0 0 0-4.499 3.618Z" /></svg>
            <span class="text-sm font-medium">Profile</span>
          </a>
          <a href="{$tab_links.members|default:'#'|escape}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $activeTab eq 'members'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
            <span class="text-sm font-medium">Members</span>
          </a>
          <a href="{$tab_links.storage_users|default:'#'|escape}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $activeTab eq 'storage_users'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>
            <span class="text-sm font-medium">Storage Users</span>
          </a>
          <a href="{$tab_links.billing|default:'#'|escape}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $activeTab eq 'billing'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
            <span class="text-sm font-medium">Billing</span>
          </a>
          <a href="{$tab_links.white_label|default:'#'|escape}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 {if $activeTab eq 'white_label'}bg-white/10 text-white ring-1 ring-white/20{else}text-slate-400 hover:text-white hover:bg-white/5{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.38 1.677a15.995 15.995 0 0 1 4.764-4.764m3.38 1.677a6.004 6.004 0 0 0-4.764-4.764m3.38 1.677a6.004 6.004 0 0 1 4.764 4.764" /></svg>
            <span class="text-sm font-medium">White Label</span>
          </a>
        </nav>
      </div>

      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <a href="{$modulelink}&a=ph-tenants-manage" class="text-slate-400 hover:text-white text-sm">Customer Tenants</a>
            <span class="text-slate-600">/</span>
            <span class="text-white text-sm font-medium">Tenant ID {$tenant.public_id|escape}</span>
          </div>
          <h2 class="text-2xl font-semibold text-white">{$tenant.name|default:'Unnamed'|escape}</h2>
          <p class="text-xs text-slate-400 mt-1">View and edit this customer tenant's profile, members, storage users, and billing.</p>
        </div>
        <div class="shrink-0">
          <a href="{$modulelink}&a=ph-tenants-manage" class="inline-flex items-center px-4 py-2 rounded-md border border-slate-700 bg-slate-900/70 text-slate-200 text-sm font-medium hover:bg-slate-800">Back to Customer Tenants</a>
        </div>
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
    {if isset($legacy_notice) && $legacy_notice neq ''}
      <div class="mt-4 rounded-xl bg-amber-500/10 ring-1 ring-amber-400/30 px-4 py-3 text-sm text-amber-100">
        You were redirected here from a legacy e3 tenant URL ({$legacy_notice|escape}). This Partner Hub page is the canonical tenant view.
      </div>
    {/if}

    {if $activeTab eq 'profile'}
      <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-800">
          <h2 class="text-lg font-medium text-slate-100">Edit Customer Tenant</h2>
        </div>
        <form method="post" action="{$modulelink}&a=ph-tenant&id={$tenant.public_id|escape:'url'}" class="px-6 py-6" x-data="{ portalAdminPasswordMode: '{if $portal_admin.exists}keep{else}manual{/if}' }">
          <input type="hidden" name="tenant_id" value="{$tenant.public_id|escape}" />
          <input type="hidden" name="eb_save_tenant" value="1" />
          {if isset($token) && $token ne ''}
            <input type="hidden" name="token" value="{$token}" />
          {/if}

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-6">
              <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-5">
                <div class="mb-4">
                  <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Organization</h3>
                  <p class="mt-1 text-sm text-slate-400">Core tenant identity and Partner Hub status.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label class="md:col-span-2 block">
                    <span class="text-sm text-slate-400">Company Name</span>
                    <input name="name" value="{$tenant.name|escape}" required class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">Slug</span>
                    <input name="slug" value="{$tenant.slug|escape}" required class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 font-mono text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">Status</span>
                    <select name="status" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700">
                      {foreach from=$statuses item=s}
                        <option value="{$s|escape}" {if $tenant.status == $s}selected{/if}>{$s|escape}</option>
                      {/foreach}
                    </select>
                  </label>
                </div>
              </div>

              <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-5">
                <div class="mb-4">
                  <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Contact Details</h3>
                  <p class="mt-1 text-sm text-slate-400">Primary billing and customer contact information.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label class="md:col-span-2 block">
                    <span class="text-sm text-slate-400">Contact Email</span>
                    <input type="email" name="contact_email" value="{$tenant.contact_email|default:''|escape}" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">Contact Name</span>
                    <input type="text" name="contact_name" value="{$tenant.contact_name|default:''|escape}" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">Phone Number</span>
                    <input type="text" name="contact_phone" value="{$tenant.contact_phone|default:''|escape}" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                  </label>
                </div>
              </div>

              <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-5">
                <div class="mb-4 flex items-start justify-between gap-4">
                  <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Portal Admin</h3>
                    <p class="mt-1 text-sm text-slate-400">Manage the primary tenant admin who can sign in to the portal.</p>
                  </div>
                  {if $portal_admin.exists}
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {if $portal_admin.status eq 'active'}bg-emerald-500/15 text-emerald-200 ring-1 ring-emerald-400/30{else}bg-slate-800 text-slate-300 ring-1 ring-slate-700{/if}">{$portal_admin.status|escape}</span>
                  {else}
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-amber-500/15 text-amber-100 ring-1 ring-amber-400/30">Not yet created</span>
                  {/if}
                </div>

                {if !$portal_admin.available}
                  <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                    Portal admin editing is unavailable because the tenant members table is not present in this environment.
                  </div>
                {else}
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="block">
                      <span class="text-sm text-slate-400">Admin Email</span>
                      <input type="email" name="portal_admin_email" value="{$portal_admin.email|default:''|escape}" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                    </label>
                    <label class="block">
                      <span class="text-sm text-slate-400">Admin Name</span>
                      <input type="text" name="portal_admin_name" value="{$portal_admin.name|default:''|escape}" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                    </label>
                    <label class="block">
                      <span class="text-sm text-slate-400">Admin Status</span>
                      <select name="portal_admin_status" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700">
                        <option value="active" {if $portal_admin.status|default:'active' eq 'active'}selected{/if}>active</option>
                        <option value="disabled" {if $portal_admin.status eq 'disabled'}selected{/if}>disabled</option>
                      </select>
                    </label>
                    <div class="block">
                      <span class="text-sm text-slate-400">Password</span>
                      <div class="mt-2 space-y-2 rounded-lg border border-slate-700 bg-slate-800/60 p-3">
                        {if $portal_admin.exists}
                          <label class="flex items-center gap-2 text-sm text-slate-300">
                            <input type="radio" name="portal_admin_password_mode" value="keep" x-model="portalAdminPasswordMode" class="border-slate-600 bg-slate-900 text-sky-500 focus:ring-sky-500" checked />
                            Keep existing password
                          </label>
                        {/if}
                        <label class="flex items-center gap-2 text-sm text-slate-300">
                          <input type="radio" name="portal_admin_password_mode" value="manual" x-model="portalAdminPasswordMode" class="border-slate-600 bg-slate-900 text-sky-500 focus:ring-sky-500" {if !$portal_admin.exists}checked{/if} />
                          {if $portal_admin.exists}Set a new password manually{else}Set initial password manually{/if}
                        </label>
                      </div>
                    </div>
                    <label class="md:col-span-2 block" x-show="portalAdminPasswordMode === 'manual'" style="display: none;">
                      <span class="text-sm text-slate-400">Manual Password</span>
                      <input type="password" name="portal_admin_password" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" placeholder="Minimum 8 characters" />
                    </label>
                  </div>
                  <div class="mt-4 grid grid-cols-1 gap-2 text-sm text-slate-400 sm:grid-cols-2">
                    <div>Last login: <span class="text-slate-200">{$portal_admin.last_login_at|default:'-'|escape}</span></div>
                    <div>Record updated: <span class="text-slate-200">{$portal_admin.updated_at|default:'-'|escape}</span></div>
                  </div>
                {/if}
              </div>
            </div>

            <div class="space-y-6">
              <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-5">
                <div class="mb-4">
                  <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Billing Address</h3>
                  <p class="mt-1 text-sm text-slate-400">Street address and regional details used for billing records.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label class="md:col-span-2 block">
                    <span class="text-sm text-slate-400">Address Line 1</span>
                    <input type="text" name="address_line1" value="{$tenant.address_line1|default:''|escape}" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                  </label>
                  <label class="md:col-span-2 block">
                    <span class="text-sm text-slate-400">Address Line 2</span>
                    <input type="text" name="address_line2" value="{$tenant.address_line2|default:''|escape}" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">City</span>
                    <input type="text" name="city" value="{$tenant.city|default:''|escape}" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">State / Province</span>
                    <input type="text" name="state" value="{$tenant.state|default:''|escape}" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">Postal Code</span>
                    <input type="text" name="postal_code" value="{$tenant.postal_code|default:''|escape}" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">Country Code</span>
                    <input type="text" name="country" value="{$tenant.country|default:''|escape}" maxlength="2" class="mt-2 w-full rounded-lg bg-slate-800 px-3 py-2.5 uppercase text-sm text-white outline-1 -outline-offset-1 outline-white/10 transition focus-within:-outline-offset-2 focus-within:outline-2 focus-within:outline-sky-700" placeholder="CA" />
                    <p class="mt-2 text-xs text-slate-500">Use a 2-letter ISO country code such as CA or US.</p>
                  </label>
                </div>
              </div>

              <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-5">
                <div class="mb-4">
                  <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Record Details</h3>
                  <p class="mt-1 text-sm text-slate-400">Canonical tenant metadata for quick reference.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label class="block">
                    <span class="text-sm text-slate-400">Tenant ID</span>
                    <input value="{$tenant.public_id|escape}" disabled class="mt-2 w-full cursor-not-allowed rounded-lg border border-slate-700 bg-slate-800/70 px-3 py-2.5 font-mono text-sm text-slate-400 opacity-90" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">Canonical Status</span>
                    <input value="{$tenant.status|default:'active'|escape}" disabled class="mt-2 w-full cursor-not-allowed rounded-lg border border-slate-700 bg-slate-800/70 px-3 py-2.5 text-sm text-slate-400 opacity-90" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">Created</span>
                    <input value="{$tenant.created_at|default:'-'|escape}" disabled class="mt-2 w-full cursor-not-allowed rounded-lg border border-slate-700 bg-slate-800/70 px-3 py-2.5 text-sm text-slate-400 opacity-90" />
                  </label>
                  <label class="block">
                    <span class="text-sm text-slate-400">Last Updated</span>
                    <input value="{$tenant.updated_at|default:'-'|escape}" disabled class="mt-2 w-full cursor-not-allowed rounded-lg border border-slate-700 bg-slate-800/70 px-3 py-2.5 text-sm text-slate-400 opacity-90" />
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end border-t border-slate-800 pt-5">
            <button type="submit" class="rounded-lg bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">Save Customer Tenant</button>
          </div>
        </form>
      </section>

      <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-800">
          <h2 class="text-lg font-medium text-slate-100">Canonical Tenant Status</h2>
        </div>
        <div class="px-6 py-5">
          <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-slate-800 text-slate-200 ring-1 ring-slate-700">{$tenant.status|default:'active'|escape}</span>
        </div>
      </section>

      <section class="mt-6 rounded-2xl border border-rose-500/30 bg-rose-500/5 overflow-hidden">
        <div class="px-6 py-5 border-b border-rose-500/20">
          <h2 class="text-lg font-medium text-rose-200">Danger Zone</h2>
        </div>
        <form method="post" action="{$modulelink}&a=ph-tenant&id={$tenant.public_id|escape:'url'}" class="px-6 py-6 flex items-center justify-between gap-4">
          <input type="hidden" name="tenant_id" value="{$tenant.public_id|escape}" />
          <input type="hidden" name="eb_delete_tenant" value="1" />
          {if isset($token) && $token ne ''}
            <input type="hidden" name="token" value="{$token}" />
          {/if}
          <p class="text-sm text-slate-300">Delete this customer tenant (marks canonical status as deleted when safe).</p>
          <button type="submit" class="rounded-lg px-4 py-2.5 text-sm font-medium text-white bg-rose-600 hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 focus:ring-offset-slate-900" onclick="return confirm('Delete this customer tenant?');">Delete Customer Tenant</button>
        </form>
      </section>
    {elseif $activeTab eq 'members'}
      <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-800 flex items-center justify-between">
          <h2 class="text-lg font-medium text-slate-100">Tenant Members</h2>
          <span class="text-sm text-slate-400">{$members|@count} total</span>
        </div>
        {if $members_error|default:'' neq ''}
          <div class="px-6 py-5 text-sm text-rose-200">Unable to load tenant members ({$members_error|escape}).</div>
        {elseif $members|@count eq 0}
          <div class="px-6 py-5 text-sm text-slate-400">No tenant members found.</div>
        {else}
          <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
              <thead class="bg-slate-900/80 text-slate-300">
                <tr>
                  <th class="px-4 py-3 text-left font-medium">Name</th>
                  <th class="px-4 py-3 text-left font-medium">Email</th>
                  <th class="px-4 py-3 text-left font-medium">Role</th>
                  <th class="px-4 py-3 text-left font-medium">Status</th>
                  <th class="px-4 py-3 text-left font-medium">Last Login</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                {foreach from=$members item=member}
                  <tr class="hover:bg-slate-800/50">
                    <td class="px-4 py-3 text-left font-medium text-slate-100">{$member.name|default:'-'|escape}</td>
                    <td class="px-4 py-3 text-left text-slate-300">{$member.email|default:'-'|escape}</td>
                    <td class="px-4 py-3 text-left"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-slate-700 text-slate-300">{$member.role|default:'user'|escape}</span></td>
                    <td class="px-4 py-3 text-left"><span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $member.status eq 'active'}bg-emerald-500/15 text-emerald-200{else}bg-slate-700 text-slate-300{/if}"><span class="h-1.5 w-1.5 rounded-full {if $member.status eq 'active'}bg-emerald-400{else}bg-slate-500{/if}"></span><span>{$member.status|default:'disabled'|escape}</span></span></td>
                    <td class="px-4 py-3 text-left text-slate-300">{$member.last_login_at|default:'-'|escape}</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        {/if}
      </section>
    {elseif $activeTab eq 'storage_users'}
      <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-800 flex items-center justify-between">
          <h2 class="text-lg font-medium text-slate-100">Storage Users</h2>
          <span class="text-sm text-slate-400">{$storage_users|@count} total</span>
        </div>
        {if $storage_users_error|default:'' neq ''}
          <div class="px-6 py-5 text-sm text-rose-200">Unable to load storage users ({$storage_users_error|escape}).</div>
        {elseif $storage_users|@count eq 0}
          <div class="px-6 py-5 text-sm text-slate-400">No storage users linked to this tenant.</div>
        {else}
          <div class="overflow-x-auto rounded-lg border border-slate-800">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
              <thead class="bg-slate-900/80 text-slate-300">
                <tr>
                  <th class="px-4 py-3 text-left font-medium">Username</th>
                  <th class="px-4 py-3 text-left font-medium">Email</th>
                  <th class="px-4 py-3 text-left font-medium">Status</th>
                  <th class="px-4 py-3 text-left font-medium">Updated</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                {foreach from=$storage_users item=user}
                  <tr class="hover:bg-slate-800/50">
                    <td class="px-4 py-3 text-left font-medium text-slate-100 font-mono">{$user.username|default:'-'|escape}</td>
                    <td class="px-4 py-3 text-left text-slate-300">{$user.email|default:'-'|escape}</td>
                    <td class="px-4 py-3 text-left"><span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold {if $user.status eq 'active'}bg-emerald-500/15 text-emerald-200{else}bg-slate-700 text-slate-300{/if}"><span class="h-1.5 w-1.5 rounded-full {if $user.status eq 'active'}bg-emerald-400{else}bg-slate-500{/if}"></span><span>{$user.status|default:'disabled'|escape}</span></span></td>
                    <td class="px-4 py-3 text-left text-slate-300">{$user.updated_at|default:'-'|escape}</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        {/if}
      </section>
    {elseif $activeTab eq 'billing'}
      <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">Billing Overview</h2></div>
        <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="text-slate-400">Tenant Billing</div>
            {if $billing_tenant}
              <div class="mt-2 font-mono text-slate-200">Tenant ID {$billing_tenant.public_id|escape}</div>
              <div class="mt-1 text-slate-300">Stripe Customer: {$billing_tenant.stripe_customer_id|default:'-'|escape}</div>
              <div class="mt-1 text-slate-300">Status: {$billing_tenant.status|default:'-'|escape}</div>
            {else}
              <div class="mt-2 text-slate-400">No billing data for this tenant.</div>
            {/if}
          </div>
          <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
            <div class="text-slate-400">Quick Totals</div>
            <div class="mt-2 text-slate-200">Subscriptions: {$billing_subscriptions_count|default:0|escape}</div>
            <div class="mt-1 text-slate-300">Usage Metrics: {$billing_usage_metrics_count|default:0|escape}</div>
            <div class="mt-1 text-slate-300">Invoices Cached: {$billing_invoices_count|default:0|escape}</div>
          </div>
        </div>
        {if $billing_error|default:'' neq ''}
          <div class="px-6 pb-5 text-sm text-rose-200">Unable to load some billing data ({$billing_error|escape}).</div>
        {/if}
      </section>
    {elseif $activeTab eq 'white_label'}
      <section class="mt-6 rounded-2xl border border-slate-800/80 bg-slate-900/70 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-800"><h2 class="text-lg font-medium text-slate-100">White Label Mapping</h2></div>
        <div class="px-6 py-5 text-sm">
          {if $whitelabel_error|default:'' neq ''}
            <div class="text-rose-200">Unable to load white-label data ({$whitelabel_error|escape}).</div>
          {elseif !$whitelabel_tenant}
            <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
              <div class="font-medium text-slate-100">Status: Not enabled</div>
              <div class="mt-1 text-slate-400">No white-label tenant is mapped to this canonical tenant yet.</div>
              <div class="mt-1 text-slate-400">Mapping State: {$whitelabel_mapping_state|default:'not_mapped'|escape}</div>
            </div>
            <form method="post" action="{$whitelabel_enable_action|escape}" class="mt-4">
              <input type="hidden" name="tenant_id" value="{$tenant.public_id|escape}" />
              <input type="hidden" name="enable_whitelabel" value="1" />
              {if isset($token) && $token ne ''}
                <input type="hidden" name="token" value="{$token}" />
              {/if}
              <button type="submit" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white bg-sky-600 hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 focus:ring-offset-slate-900">
                Enable White Label
              </button>
            </form>
          {else}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
                <div class="text-slate-200">Status: {$whitelabel_tenant.status|default:'-'|escape}</div>
                <div class="mt-1 text-slate-300">FQDN: {$whitelabel_tenant.fqdn|default:'-'|escape}</div>
                <div class="mt-1 text-slate-300">Subdomain: {$whitelabel_tenant.subdomain|default:'-'|escape}</div>
                <div class="mt-1 text-slate-300">Custom Domain: {$whitelabel_tenant.custom_domain|default:'-'|escape}</div>
                <div class="mt-1 text-slate-300">Custom Domain State: {$whitelabel_tenant.custom_domain_status|default:'-'|escape}</div>
              </div>
              <div class="rounded-xl border border-slate-700 bg-slate-800/50 p-4">
                <div class="text-slate-200">Enabled: Yes</div>
                <div class="mt-1 text-slate-300">Mapping State: {$whitelabel_mapping_state|default:'mapped'|escape}</div>
                <div class="text-slate-300">Custom Domains: {$whitelabel_custom_domains|@count}</div>
                <div class="mt-1 text-slate-300">Asset Types: {$whitelabel_assets_by_type|@count}</div>
                <div class="mt-1 text-slate-300">Org ID: {$whitelabel_tenant.org_id|default:'-'|escape}</div>
              </div>
            </div>
            <div class="mt-4 text-slate-400">White-label infrastructure IDs are managed internally and are not editable here.</div>
          {/if}
        </div>
      </section>
    {/if}
    </div>
        </main>
      </div>
    </div>
  </div>
</div>

