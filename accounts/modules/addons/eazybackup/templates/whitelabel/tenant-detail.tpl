{* Partner Hub — Canonical tenant detail tabs *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

{assign var=activeTab value=$active_tab|default:'profile'}
{capture assign=ebPhTitle}
  {$tenant.name|default:'Unnamed'|escape}
{/capture}
{capture assign=ebPhDescription}
  Tenant ID {$tenant.public_id|escape} · View and manage this customer tenant's profile, members, storage users, billing, and white-label state.
{/capture}
{capture assign=ebPhActions}
  <div class="flex flex-wrap items-center justify-end gap-2">
    <a href="{$modulelink}&a=ph-tenants-manage" class="eb-btn eb-btn-secondary eb-btn-sm">Back to Customer Tenants</a>
  </div>
{/capture}

{capture assign=ebPhContent}
      <div class="eb-panel-nav">
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

    {if $notice neq '' || $error neq '' || (isset($legacy_notice) && $legacy_notice neq '')}
      <div class="mb-6 space-y-3">
        {if $notice neq ''}
          <div class="rounded-xl bg-emerald-500/20 ring-1 ring-emerald-400/30 px-4 py-3 text-sm text-white">
            Tenant updated.
          </div>
        {/if}
        {if $error eq 'stripe_sync_warning'}
          <div class="rounded-xl bg-amber-500/10 ring-1 ring-amber-400/30 px-4 py-3 text-sm text-amber-100">
            Tenant saved locally, but the Stripe customer profile could not be updated. Billing details in Stripe may be temporarily out of sync.
          </div>
        {/if}
        {if $error eq 'portal_admin_password_short'}
          <div class="rounded-xl bg-amber-500/10 ring-1 ring-amber-400/30 px-4 py-3 text-sm text-amber-100">
            Tenant created, but the portal admin was not added. Passwords must be at least 8 characters.
          </div>
        {/if}
        {if $error neq '' && $error neq 'stripe_sync_warning' && $error neq 'portal_admin_password_short'}
          <div class="rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-200">
            Unable to process the request ({$error|escape}).
          </div>
        {/if}
        {if isset($legacy_notice) && $legacy_notice neq ''}
          <div class="rounded-xl bg-amber-500/10 ring-1 ring-amber-400/30 px-4 py-3 text-sm text-amber-100">
            You were redirected here from a legacy e3 tenant URL ({$legacy_notice|escape}). This Partner Hub page is the canonical tenant view.
          </div>
        {/if}
      </div>
    {/if}

    {if $activeTab eq 'profile'}
      <section class="eb-card-raised !p-0 overflow-hidden">
        <div class="px-6 py-5 border-b border-[var(--eb-border-subtle)]">
          <h2 class="eb-card-title text-lg font-semibold">Edit Customer Tenant</h2>
        </div>
        <form method="post" action="{$modulelink}&a=ph-tenant&id={$tenant.public_id|escape:'url'}" class="px-6 py-6" x-data="{ portalAdminPasswordMode: '{if $portal_admin.exists}keep{else}manual{/if}' }">
          <input type="hidden" name="tenant_id" value="{$tenant.public_id|escape}" />
          <input type="hidden" name="eb_save_tenant" value="1" />
          {if isset($token) && $token ne ''}
            <input type="hidden" name="token" value="{$token}" />
          {/if}

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-6">
              <div class="eb-card">
                <div class="mb-4">
                  <h3 class="eb-type-eyebrow">Organization</h3>
                  <p class="mt-1 eb-type-caption">Core tenant identity and Partner Hub status.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label class="md:col-span-2 block">
                    <span class="eb-field-label">Company Name</span>
                    <input name="name" value="{$tenant.name|escape}" required class="eb-input mt-2 w-full" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Slug</span>
                    <input name="slug" value="{$tenant.slug|escape}" required class="eb-input eb-type-mono mt-2 w-full" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Status</span>
                    <select name="status" class="eb-select mt-2 w-full">
                      {foreach from=$statuses item=s}
                        <option value="{$s|escape}" {if $tenant.status == $s}selected{/if}>{$s|escape}</option>
                      {/foreach}
                    </select>
                  </label>
                </div>
              </div>

              <div class="eb-card">
                <div class="mb-4">
                  <h3 class="eb-type-eyebrow">Contact Details</h3>
                  <p class="mt-1 eb-type-caption">Primary billing and customer contact information.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label class="md:col-span-2 block">
                    <span class="eb-field-label">Contact Email</span>
                    <input type="email" name="contact_email" value="{$tenant.contact_email|default:''|escape}" class="eb-input mt-2 w-full" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Contact Name</span>
                    <input type="text" name="contact_name" value="{$tenant.contact_name|default:''|escape}" class="eb-input mt-2 w-full" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Phone Number</span>
                    <input type="text" name="contact_phone" value="{$tenant.contact_phone|default:''|escape}" class="eb-input mt-2 w-full" />
                  </label>
                </div>
              </div>

              <div class="eb-card">
                <div class="mb-4 flex items-start justify-between gap-4">
                  <div>
                    <h3 class="eb-type-eyebrow">Portal Admin</h3>
                    <p class="mt-1 eb-type-caption">Manage the primary tenant admin who can sign in to the portal.</p>
                  </div>
                  {if $portal_admin.exists}
                    <span class="eb-badge {if $portal_admin.status eq 'active'}eb-badge--success{else}eb-badge--neutral{/if}">{$portal_admin.status|escape}</span>
                  {else}
                    <span class="eb-badge eb-badge--warning">Not yet created</span>
                  {/if}
                </div>

                {if !$portal_admin.available}
                  <div class="eb-alert eb-alert--warning !mb-0">
                    Portal admin editing is unavailable because the tenant members table is not present in this environment.
                  </div>
                {else}
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="block">
                      <span class="eb-field-label">Admin Email</span>
                      <input type="email" name="portal_admin_email" value="{$portal_admin.email|default:''|escape}" class="eb-input mt-2 w-full" />
                    </label>
                    <label class="block">
                      <span class="eb-field-label">Admin Name</span>
                      <input type="text" name="portal_admin_name" value="{$portal_admin.name|default:''|escape}" class="eb-input mt-2 w-full" />
                    </label>
                    <label class="block">
                      <span class="eb-field-label">Admin Status</span>
                      <select name="portal_admin_status" class="eb-select mt-2 w-full">
                        <option value="active" {if $portal_admin.status|default:'active' eq 'active'}selected{/if}>active</option>
                        <option value="disabled" {if $portal_admin.status eq 'disabled'}selected{/if}>disabled</option>
                      </select>
                    </label>
                    <div class="block">
                      <span class="eb-field-label">Password</span>
                      <div class="mt-2 space-y-2 rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-bg-surface)] p-3">
                        {if $portal_admin.exists}
                          <label class="flex items-center gap-2 eb-type-body">
                            <input type="radio" name="portal_admin_password_mode" value="keep" x-model="portalAdminPasswordMode" class="size-4 shrink-0 rounded-full border-[var(--eb-border-emphasis)] bg-[var(--eb-bg-input)] text-[var(--eb-primary)] focus:ring-2 focus:ring-[var(--eb-primary)]" checked />
                            Keep existing password
                          </label>
                        {/if}
                        <label class="flex items-center gap-2 eb-type-body">
                          <input type="radio" name="portal_admin_password_mode" value="manual" x-model="portalAdminPasswordMode" class="size-4 shrink-0 rounded-full border-[var(--eb-border-emphasis)] bg-[var(--eb-bg-input)] text-[var(--eb-primary)] focus:ring-2 focus:ring-[var(--eb-primary)]" {if !$portal_admin.exists}checked{/if} />
                          {if $portal_admin.exists}Set a new password manually{else}Set initial password manually{/if}
                        </label>
                      </div>
                    </div>
                    <label class="md:col-span-2 block" x-show="portalAdminPasswordMode === 'manual'" style="display: none;">
                      <span class="eb-field-label">Manual Password</span>
                      <input type="password" name="portal_admin_password" class="eb-input mt-2 w-full" placeholder="Minimum 8 characters" />
                    </label>
                  </div>
                  <div class="mt-4 grid grid-cols-1 gap-2 eb-type-caption sm:grid-cols-2">
                    <div>Last login: <span class="text-[var(--eb-text-primary)]">{$portal_admin.last_login_at|default:'-'|escape}</span></div>
                    <div>Record updated: <span class="text-[var(--eb-text-primary)]">{$portal_admin.updated_at|default:'-'|escape}</span></div>
                  </div>
                {/if}
              </div>
            </div>

            <div class="space-y-6">
              <div class="eb-card">
                <div class="mb-4">
                  <h3 class="eb-type-eyebrow">Billing Address</h3>
                  <p class="mt-1 eb-type-caption">Street address and regional details used for billing records.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label class="md:col-span-2 block">
                    <span class="eb-field-label">Address Line 1</span>
                    <input type="text" name="address_line1" value="{$tenant.address_line1|default:''|escape}" class="eb-input mt-2 w-full" />
                  </label>
                  <label class="md:col-span-2 block">
                    <span class="eb-field-label">Address Line 2</span>
                    <input type="text" name="address_line2" value="{$tenant.address_line2|default:''|escape}" class="eb-input mt-2 w-full" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">City</span>
                    <input type="text" name="city" value="{$tenant.city|default:''|escape}" class="eb-input mt-2 w-full" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">State / Province</span>
                    <input type="text" name="state" value="{$tenant.state|default:''|escape}" class="eb-input mt-2 w-full" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Postal Code</span>
                    <input type="text" name="postal_code" value="{$tenant.postal_code|default:''|escape}" class="eb-input mt-2 w-full" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Country Code</span>
                    <input type="text" name="country" value="{$tenant.country|default:''|escape}" maxlength="2" class="eb-input mt-2 w-full uppercase" placeholder="CA" />
                    <p class="eb-field-help">Use a 2-letter ISO country code such as CA or US.</p>
                  </label>
                </div>
              </div>

              <div class="eb-card">
                <div class="mb-4">
                  <h3 class="eb-type-eyebrow">Record Details</h3>
                  <p class="mt-1 eb-type-caption">Canonical tenant metadata for quick reference.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label class="block">
                    <span class="eb-field-label">Tenant ID</span>
                    <input value="{$tenant.public_id|escape}" disabled class="eb-input eb-type-mono mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Canonical Status</span>
                    <input value="{$tenant.status|default:'active'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Created</span>
                    <input value="{$tenant.created_at|default:'-'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Last Updated</span>
                    <input value="{$tenant.updated_at|default:'-'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end border-t border-[var(--eb-border-subtle)] pt-5">
            <button type="submit" class="eb-btn eb-btn-primary">Save Customer Tenant</button>
          </div>
        </form>
      </section>

      <section class="mt-6 eb-card-raised !p-0 overflow-hidden">
        <div class="px-6 py-5 border-b border-[var(--eb-border-subtle)]">
          <h2 class="eb-card-title text-lg font-semibold">Canonical Tenant Status</h2>
        </div>
        <div class="px-6 py-5">
          <span class="eb-badge eb-badge--neutral">{$tenant.status|default:'active'|escape}</span>
        </div>
      </section>

      <section class="mt-6 eb-card-raised !p-0 overflow-hidden !border-[var(--eb-danger-border)] !bg-[var(--eb-danger-soft)]">
        <div class="px-6 py-5 border-b border-[var(--eb-danger-border)]">
          <h2 class="eb-card-title text-lg font-semibold text-[var(--eb-danger-text)]">Danger Zone</h2>
        </div>
        <form method="post" action="{$modulelink}&a=ph-tenant&id={$tenant.public_id|escape:'url'}" class="px-6 py-6 flex items-center justify-between gap-4">
          <input type="hidden" name="tenant_id" value="{$tenant.public_id|escape}" />
          <input type="hidden" name="eb_delete_tenant" value="1" />
          {if isset($token) && $token ne ''}
            <input type="hidden" name="token" value="{$token}" />
          {/if}
          <p class="eb-type-body">Delete this customer tenant (marks canonical status as deleted when safe).</p>
          <button type="submit" class="eb-btn eb-btn-danger-solid" onclick="return confirm('Delete this customer tenant?');">Delete Customer Tenant</button>
        </form>
      </section>
    {elseif $activeTab eq 'members'}
      <section class="eb-card-raised !p-0 overflow-hidden">
        <div class="flex items-center justify-between gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-5">
          <h2 class="eb-card-title text-lg font-semibold">Tenant Members</h2>
          <span class="eb-badge eb-badge--default">{$members|@count} total</span>
        </div>
        {if $members_error|default:'' neq ''}
          <div class="px-6 py-5">
            <div class="eb-alert eb-alert--danger !mb-0">Unable to load tenant members ({$members_error|escape}).</div>
          </div>
        {elseif $members|@count eq 0}
          <div class="px-6 py-5">
            <div class="eb-alert eb-alert--info !mb-0">No tenant members found.</div>
          </div>
        {else}
          <div class="px-6 py-5">
            <div class="eb-table-shell">
              <table class="eb-table">
                <thead>
                  <tr>
                    <th class="px-4 py-3 text-left font-medium">Name</th>
                    <th class="px-4 py-3 text-left font-medium">Email</th>
                    <th class="px-4 py-3 text-left font-medium">Role</th>
                    <th class="px-4 py-3 text-left font-medium">Status</th>
                    <th class="px-4 py-3 text-left font-medium">Last Login</th>
                  </tr>
                </thead>
                <tbody>
                  {foreach from=$members item=member}
                    <tr>
                      <td class="px-4 py-3 text-left font-medium text-[var(--eb-text-primary)]">{$member.name|default:'-'|escape}</td>
                      <td class="px-4 py-3 text-left text-[var(--eb-text-secondary)]">{$member.email|default:'-'|escape}</td>
                      <td class="px-4 py-3 text-left"><span class="eb-badge eb-badge--neutral">{$member.role|default:'user'|escape}</span></td>
                      <td class="px-4 py-3 text-left"><span class="eb-badge {if $member.status eq 'active'}eb-badge--success{else}eb-badge--neutral{/if}">{$member.status|default:'disabled'|escape}</span></td>
                      <td class="px-4 py-3 text-left text-[var(--eb-text-secondary)]">{$member.last_login_at|default:'-'|escape}</td>
                    </tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
          </div>
        {/if}
      </section>
    {elseif $activeTab eq 'storage_users'}
      <section class="eb-card-raised !p-0 overflow-hidden">
        <div class="flex items-center justify-between gap-4 border-b border-[var(--eb-border-subtle)] px-6 py-5">
          <h2 class="eb-card-title text-lg font-semibold">Storage Users</h2>
          <span class="eb-badge eb-badge--default">{$storage_users|@count} total</span>
        </div>
        {if $storage_users_error|default:'' neq ''}
          <div class="px-6 py-5">
            <div class="eb-alert eb-alert--danger !mb-0">Unable to load storage users ({$storage_users_error|escape}).</div>
          </div>
        {elseif $storage_users|@count eq 0}
          <div class="px-6 py-5">
            <div class="eb-alert eb-alert--info !mb-0">No storage users linked to this tenant.</div>
          </div>
        {else}
          <div class="px-6 py-5">
            <div class="eb-table-shell">
              <table class="eb-table">
                <thead>
                  <tr>
                    <th class="px-4 py-3 text-left font-medium">Username</th>
                    <th class="px-4 py-3 text-left font-medium">Email</th>
                    <th class="px-4 py-3 text-left font-medium">Status</th>
                    <th class="px-4 py-3 text-left font-medium">Updated</th>
                  </tr>
                </thead>
                <tbody>
                  {foreach from=$storage_users item=user}
                    <tr>
                      <td class="px-4 py-3 text-left font-medium text-[var(--eb-text-primary)] eb-type-mono">{$user.username|default:'-'|escape}</td>
                      <td class="px-4 py-3 text-left text-[var(--eb-text-secondary)]">{$user.email|default:'-'|escape}</td>
                      <td class="px-4 py-3 text-left"><span class="eb-badge {if $user.status eq 'active'}eb-badge--success{else}eb-badge--neutral{/if}">{$user.status|default:'disabled'|escape}</span></td>
                      <td class="px-4 py-3 text-left text-[var(--eb-text-secondary)]">{$user.updated_at|default:'-'|escape}</td>
                    </tr>
                  {/foreach}
                </tbody>
              </table>
            </div>
          </div>
        {/if}
      </section>
    {elseif $activeTab eq 'billing'}
      <section class="eb-card-raised !p-0 overflow-hidden">
        <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5">
          <h2 class="eb-card-title text-lg font-semibold">Billing Overview</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 px-6 py-5 text-sm md:grid-cols-2">
          <div class="eb-card !p-4">
            <h3 class="eb-card-title">Tenant Billing</h3>
            <p class="eb-card-subtitle">Current Stripe customer linkage and local billing status for this tenant.</p>
            {if $billing_tenant}
              <div class="mt-4 space-y-3">
                <label class="block">
                  <span class="eb-field-label">Tenant ID</span>
                  <input value="{$billing_tenant.public_id|escape}" disabled class="eb-input eb-type-mono mt-2 w-full cursor-not-allowed opacity-90" />
                </label>
                <label class="block">
                  <span class="eb-field-label">Stripe Customer</span>
                  <input value="{$billing_tenant.stripe_customer_id|default:'-'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                </label>
                <label class="block">
                  <span class="eb-field-label">Status</span>
                  <input value="{$billing_tenant.status|default:'-'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                </label>
              </div>
            {else}
              <div class="eb-alert eb-alert--info mt-4 !mb-0">No billing data for this tenant.</div>
            {/if}
          </div>
          <div class="eb-card !p-4">
            <h3 class="eb-card-title">Quick Totals</h3>
            <p class="eb-card-subtitle">Subscription, usage, and invoice counts cached for this tenant.</p>
            <div class="mt-4 grid grid-cols-1 gap-3">
              <label class="block">
                <span class="eb-field-label">Subscriptions</span>
                <input value="{$billing_subscriptions_count|default:0|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
              </label>
              <label class="block">
                <span class="eb-field-label">Usage Metrics</span>
                <input value="{$billing_usage_metrics_count|default:0|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
              </label>
              <label class="block">
                <span class="eb-field-label">Invoices Cached</span>
                <input value="{$billing_invoices_count|default:0|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
              </label>
            </div>
          </div>
        </div>
        {if $billing_error|default:'' neq ''}
          <div class="px-6 pb-5">
            <div class="eb-alert eb-alert--warning !mb-0">Unable to load some billing data ({$billing_error|escape}).</div>
          </div>
        {/if}
        <div x-data="{
                   assignOpen: false,
                   assignSaving: false,
                   assignMessage: '',
                   paymentMethodMessage: '',
                   selectedPlanId: '',
                   selectedCometUserId: '',
                   assignPlanOpen: false,
                   assignPlanSearch: '',
                   assignCometUserOpen: false,
                   assignCometUserSearch: '',
                   plans: {$billing_assignable_plans|default:array()|@json_encode|escape:'html'},
                   cometUsers: {$billing_tenant_comet_users|default:array()|@json_encode|escape:'html'},
                   tenantPublicId: '{$billing_tenant.public_id|default:$tenant.public_id|escape:'javascript'}',
                   token: '{$token|escape:'javascript'}',
                   selectedPlan() {
                     return this.plans.find((plan) => String(plan.id || '') === String(this.selectedPlanId || '')) || null;
                   },
                   filteredAssignablePlans() {
                     const query = String(this.assignPlanSearch || '').trim().toLowerCase();
                     if (!query) {
                       return this.plans;
                     }
                     return this.plans.filter((plan) => {
                       return String(plan.name || '').toLowerCase().includes(query)
                         || String(plan.description || '').toLowerCase().includes(query)
                         || String(plan.id || '').toLowerCase().includes(query);
                     });
                   },
                   selectedPlanLabel() {
                     const plan = this.selectedPlan();
                     if (!plan) {
                       return 'Select a plan';
                     }
                     return String(plan.name || '').trim() || ('Plan #' + String(plan.id || ''));
                   },
                   requiresCometUser() {
                     const plan = this.selectedPlan();
                     return plan ? !!plan.requires_comet_user : true;
                   },
                   filteredCometUsers() {
                     const query = String(this.assignCometUserSearch || '').trim().toLowerCase();
                     if (!query) {
                       return this.cometUsers;
                     }
                     return this.cometUsers.filter((user) => {
                       return String(user.comet_user_id || '').toLowerCase().includes(query);
                     });
                   },
                   selectedCometUserLabel() {
                     if (!this.selectedCometUserId) {
                       return this.requiresCometUser() ? 'Select a backup user' : 'Tenant-level storage assignment';
                     }
                     const user = this.cometUsers.find((row) => String(row.comet_user_id || '') === String(this.selectedCometUserId || '')) || null;
                     return user ? String(user.comet_user_id || '').trim() : String(this.selectedCometUserId || '');
                   },
                   selectAssignPlan(planId) {
                     this.selectedPlanId = String(planId || '');
                     this.assignPlanOpen = false;
                     this.assignPlanSearch = '';
                     if (!this.requiresCometUser()) {
                       this.selectedCometUserId = '';
                     } else if (!this.selectedCometUserId && this.cometUsers.length === 1) {
                       this.selectedCometUserId = String(this.cometUsers[0].comet_user_id || '');
                     }
                   },
                   selectAssignCometUser(cometUserId) {
                     this.selectedCometUserId = String(cometUserId || '');
                     this.assignCometUserOpen = false;
                     this.assignCometUserSearch = '';
                   },
                   openAssignModal() {
                     this.assignOpen = true;
                     this.assignMessage = '';
                     this.assignPlanOpen = false;
                     this.assignPlanSearch = '';
                     this.assignCometUserOpen = false;
                     this.assignCometUserSearch = '';
                     if (!this.selectedPlanId && this.plans.length) {
                       this.selectedPlanId = String(this.plans[0].id || '');
                     }
                     if (!this.selectedCometUserId && this.cometUsers.length) {
                       this.selectedCometUserId = String(this.cometUsers[0].comet_user_id || '');
                     }
                   },
                   async submitAssignPlan() {
                     if (!this.selectedPlanId) {
                       this.assignMessage = 'Select a plan first.';
                       return;
                     }
                     if (this.requiresCometUser() && !this.selectedCometUserId) {
                       this.assignMessage = 'Select a backup user first.';
                       return;
                     }
                     this.assignSaving = true;
                     this.assignMessage = '';
                     try {
                       const payload = new URLSearchParams({
                         plan_id: String(this.selectedPlanId || ''),
                         tenant_id: String(this.tenantPublicId || ''),
                         comet_user_id: this.requiresCometUser() ? String(this.selectedCometUserId || '') : '',
                         token: this.token
                       });
                       const res = await fetch('{$modulelink}&a=ph-plan-assign', {
                         method: 'POST',
                         credentials: 'same-origin',
                         headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                         body: payload.toString()
                       });
                       const data = await res.json();
                       if (data.status === 'success') {
                         window.location.reload();
                         return;
                       }
                       this.assignMessage = data.message || 'Unable to assign plan.';
                     } catch (error) {
                       this.assignMessage = 'Unable to assign plan.';
                     } finally {
                       this.assignSaving = false;
                     }
                   },
                   async removePaymentMethod(paymentMethodId) {
                     this.paymentMethodMessage = '';
                     try {
                       const payload = new URLSearchParams({
                         payment_method_id: String(paymentMethodId || ''),
                         tenant_id: String(this.tenantPublicId || ''),
                         token: this.token
                       });
                       const res = await fetch('{$modulelink}&a=ph-stripe-payment-method-detach', {
                         method: 'POST',
                         credentials: 'same-origin',
                         headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                         body: payload.toString()
                       });
                       const data = await res.json();
                       if (data.status === 'success') {
                         window.location.reload();
                         return;
                       }
                       this.paymentMethodMessage = data.message || 'Unable to remove payment method.';
                     } catch (error) {
                       this.paymentMethodMessage = 'Unable to remove payment method.';
                     }
                   }
                 }"
             class="mx-6 mb-6 mt-4">
          <section class="eb-card-raised !p-0 overflow-hidden">
          <div class="flex items-center justify-between border-b border-[var(--eb-border-subtle)] px-6 py-4">
            <h2 class="eb-card-title text-lg font-semibold">Active Plans</h2>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="openAssignModal()" {if $billing_assignable_plans|default:array()|count eq 0}disabled{/if}>Assign Plan</button>
              <a href="{$modulelink}&a=ph-catalog-plans" class="eb-btn eb-btn-secondary eb-btn-sm">Manage Plans</a>
            </div>
          </div>
          {if $billing_plan_instances|default:array()|count > 0}
            <div class="px-6 py-5">
              <div class="eb-table-shell">
                <table class="eb-table">
                  <thead>
                    <tr>
                      <th class="px-6 py-3">Plan</th>
                      <th class="px-6 py-3">Backup User</th>
                      <th class="px-6 py-3">Status</th>
                      <th class="px-6 py-3">Since</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach from=$billing_plan_instances item=pi}
                    <tr>
                      <td class="px-6 py-3 text-[var(--eb-text-primary)]">{$pi.plan_name|default:'Unknown Plan'|escape}</td>
                      <td class="px-6 py-3 eb-type-mono text-[var(--eb-text-secondary)]">{$pi.comet_user_id|default:'-'|escape}</td>
                      <td class="px-6 py-3">
                        <span class="eb-badge {if $pi.status eq 'active'}eb-badge--success{elseif $pi.status eq 'trialing'}eb-badge--info{elseif $pi.status eq 'past_due'}eb-badge--warning{elseif $pi.status eq 'paused'}eb-badge--neutral{else}eb-badge--danger{/if}">{$pi.status|default:'unknown'|escape}</span>
                      </td>
                      <td class="px-6 py-3 text-[var(--eb-text-secondary)]">{if $pi.created_at|default:'' neq ''}{$pi.created_at|date_format:'%Y-%m-%d'}{else}-{/if}</td>
                    </tr>
                    {/foreach}
                  </tbody>
                </table>
              </div>
            </div>
          {else}
            <div class="px-6 py-5">
              <div class="eb-alert eb-alert--info !mb-0 text-center">
                No active billing plans. <a href="{$modulelink}&a=ph-catalog-plans" class="text-[var(--eb-primary)] hover:underline">Assign a plan from Catalog &rarr; Plans</a>.
              </div>
            </div>
          {/if}
          <div x-show="assignOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/70 px-4" @click.self="assignOpen = false">
            <div class="w-full max-w-lg rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
              <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                <div>
                  <h3 class="text-lg font-semibold text-slate-100">Assign Plan</h3>
                  <p class="mt-1 text-sm text-slate-400">Create a plan assignment for this tenant using the existing Partner Hub billing flow.</p>
                </div>
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="assignOpen = false">Close</button>
              </div>
              <div class="space-y-4 px-6 py-5">
                <label class="block text-sm">
                  <span class="mb-1 block text-slate-300">Plan</span>
                  <div class="relative" @click.outside="assignPlanOpen = false">
                    <button
                      type="button"
                      class="eb-input relative flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left"
                      @click="assignPlanOpen = !assignPlanOpen; if (assignPlanOpen) { assignCometUserOpen = false; }"
                      :aria-expanded="assignPlanOpen"
                    >
                      <span class="min-w-0 truncate" :class="selectedPlanId ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="selectedPlanLabel()"></span>
                      <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="assignPlanOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                    <div
                      x-show="assignPlanOpen"
                      x-transition
                      class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                      style="display: none;"
                    >
                      <div class="border-b border-[var(--eb-border-subtle)] p-2">
                        <input
                          type="search"
                          x-model="assignPlanSearch"
                          placeholder="Search plans..."
                          class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm"
                          @click.stop
                        />
                      </div>
                      <div class="max-h-52 overflow-y-auto p-1">
                        <template x-for="plan in filteredAssignablePlans()" :key="'billing-plan-' + String(plan.id || '')">
                          <button
                            type="button"
                            class="eb-menu-item w-full flex-col !items-stretch gap-0.5"
                            :class="String(selectedPlanId || '') === String(plan.id || '') ? 'is-active' : ''"
                            @click="selectAssignPlan(plan.id)"
                          >
                            <span class="min-w-0 truncate text-left font-medium" x-text="plan.name || ('Plan #' + String(plan.id || ''))"></span>
                            <span class="truncate text-left text-xs text-[var(--eb-text-muted)]" x-text="plan.description || (plan.requires_comet_user ? 'Requires an eazyBackup user.' : 'Tenant-level storage assignment.')"></span>
                          </button>
                        </template>
                        <div x-show="filteredAssignablePlans().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No matching plans.</div>
                      </div>
                    </div>
                  </div>
                </label>
                <template x-if="selectedPlan()">
                  <div class="eb-card !p-4">
                    <div class="font-medium text-slate-100" x-text="selectedPlan().name || 'Selected plan'"></div>
                    <div class="mt-1" x-text="selectedPlan().description || 'No description provided.'"></div>
                  </div>
                </template>
                <div x-show="requiresCometUser()">
                  <label class="block text-sm">
                    <span class="mb-1 block text-slate-300">Backup User</span>
                    <div class="relative" @click.outside="assignCometUserOpen = false">
                      <button
                        type="button"
                        class="eb-input relative flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left disabled:cursor-not-allowed disabled:opacity-50"
                        @click="if (requiresCometUser() && cometUsers.length) { assignCometUserOpen = !assignCometUserOpen; if (assignCometUserOpen) { assignPlanOpen = false; } }"
                        :disabled="!requiresCometUser() || cometUsers.length === 0"
                        :aria-expanded="assignCometUserOpen"
                      >
                        <span class="min-w-0 truncate" :class="selectedCometUserId ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="selectedCometUserLabel()"></span>
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="assignCometUserOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                      </button>
                      <div
                        x-show="assignCometUserOpen"
                        x-transition
                        class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                        style="display: none;"
                      >
                        <div class="border-b border-[var(--eb-border-subtle)] p-2">
                          <input
                            type="search"
                            x-model="assignCometUserSearch"
                            placeholder="Search backup users..."
                            class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm"
                            @click.stop
                          />
                        </div>
                        <div class="max-h-52 overflow-y-auto p-1">
                          <template x-for="user in filteredCometUsers()" :key="'tenant-comet-user-' + String(user.comet_user_id || '')">
                            <button
                              type="button"
                              class="eb-menu-item w-full"
                              :class="String(selectedCometUserId || '') === String(user.comet_user_id || '') ? 'is-active' : ''"
                              @click="selectAssignCometUser(user.comet_user_id)"
                            >
                              <span class="min-w-0 flex-1 truncate text-left" x-text="user.comet_user_id || '-'"></span>
                            </button>
                          </template>
                          <div x-show="filteredCometUsers().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No matching backup users.</div>
                        </div>
                      </div>
                    </div>
                  </label>
                  <p class="mt-2 text-xs text-slate-500" x-show="cometUsers.length === 0">No linked backup users were found for this tenant.</p>
                </div>
                <template x-if="assignMessage">
                  <div class="eb-alert eb-alert--danger !mb-0" x-text="assignMessage"></div>
                </template>
              </div>
              <div class="flex items-center justify-end gap-3 border-t border-slate-800 px-6 py-4">
                <button type="button" class="eb-btn eb-btn-secondary" @click="assignOpen = false">Cancel</button>
                <button type="button" class="eb-btn eb-btn-primary" :disabled="assignSaving" @click="submitAssignPlan()" x-text="assignSaving ? 'Assigning...' : 'Assign Plan'"></button>
              </div>
            </div>
          </div>
          </section>
        </div>
        <section class="mx-6 mb-6 mt-4 eb-card-raised !p-0 overflow-hidden">
          <div class="flex items-center justify-between border-b border-[var(--eb-border-subtle)] px-6 py-4">
            <h2 class="eb-card-title text-lg font-semibold">Saved Payment Methods</h2>
            <span class="eb-badge eb-badge--default">{$billing_payment_methods|default:array()|count} on file</span>
          </div>
          {if $billing_tenant.stripe_customer_id|default:'' eq ''}
            <div class="px-6 py-5">
              <div class="eb-alert eb-alert--info !mb-0">This tenant does not have a Stripe customer yet, so there are no saved payment methods to manage.</div>
            </div>
          {elseif $billing_payment_methods|default:array()|count > 0}
            <div class="grid grid-cols-1 gap-4 px-6 py-5 md:grid-cols-2">
              {foreach from=$billing_payment_methods item=pm}
                <article class="eb-card !p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <div class="text-sm uppercase tracking-[0.2em] text-slate-500">{$pm.brand|default:'card'|escape}</div>
                      <div class="mt-2 text-lg font-semibold text-slate-100">•••• {$pm.last4|default:'----'|escape}</div>
                      <div class="mt-1 text-sm text-slate-400">Expires {$pm.exp_month|string_format:'%02d'}/{$pm.exp_year|escape}</div>
                    </div>
                    {if $pm.is_default|default:false}
                      <span class="eb-badge eb-badge--success">Default</span>
                    {else}
                      <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="removePaymentMethod('{$pm.id|escape:'javascript'}')">Remove</button>
                    {/if}
                  </div>
                </article>
              {/foreach}
            </div>
            <template x-if="paymentMethodMessage">
              <div class="px-6 pb-5">
                <div class="eb-alert eb-alert--danger !mb-0" x-text="paymentMethodMessage"></div>
              </div>
            </template>
          {else}
            <div class="px-6 py-5">
              <div class="eb-alert eb-alert--info !mb-0">No saved payment methods were found for this tenant.</div>
            </div>
          {/if}
        </section>
        </div>
      </section>
    {elseif $activeTab eq 'white_label'}
      <section class="eb-card-raised !p-0 overflow-hidden">
        <div class="border-b border-[var(--eb-border-subtle)] px-6 py-5"><h2 class="eb-card-title text-lg font-semibold">White Label Mapping</h2></div>
        <div class="px-6 py-5 text-sm">
          {if $whitelabel_error|default:'' neq ''}
            <div class="eb-alert eb-alert--danger !mb-0">Unable to load white-label data ({$whitelabel_error|escape}).</div>
          {elseif !$whitelabel_tenant}
            <div class="eb-card">
              <h3 class="eb-card-title">White Label Status</h3>
              <p class="eb-card-subtitle">No white-label tenant is mapped to this canonical tenant yet.</p>
              <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                <label class="block">
                  <span class="eb-field-label">Status</span>
                  <input value="Not enabled" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                </label>
                <label class="block">
                  <span class="eb-field-label">Mapping State</span>
                  <input value="{$whitelabel_mapping_state|default:'not_mapped'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                </label>
              </div>
              <div class="eb-alert eb-alert--info mt-5 !mb-0">Enable white label to provision the related mapping and infrastructure records.</div>
            </div>
            <form method="post" action="{$whitelabel_enable_action|escape}" class="mt-4">
              <input type="hidden" name="tenant_id" value="{$tenant.public_id|escape}" />
              <input type="hidden" name="enable_whitelabel" value="1" />
              {if isset($token) && $token ne ''}
                <input type="hidden" name="token" value="{$token}" />
              {/if}
              <button type="submit" class="eb-btn eb-btn-primary">
                Enable White Label
              </button>
            </form>
          {else}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="eb-card">
                <h3 class="eb-card-title">Tenant Mapping</h3>
                <p class="eb-card-subtitle">Current mapped white-label tenant identity and domain state.</p>
                <div class="mt-5 grid grid-cols-1 gap-4">
                  <label class="block">
                    <span class="eb-field-label">Status</span>
                    <input value="{$whitelabel_tenant.status|default:'-'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">FQDN</span>
                    <input value="{$whitelabel_tenant.fqdn|default:'-'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Subdomain</span>
                    <input value="{$whitelabel_tenant.subdomain|default:'-'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Custom Domain</span>
                    <input value="{$whitelabel_tenant.custom_domain|default:'-'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Custom Domain State</span>
                    <input value="{$whitelabel_tenant.custom_domain_status|default:'-'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                </div>
              </div>
              <div class="eb-card">
                <h3 class="eb-card-title">Provisioning Summary</h3>
                <p class="eb-card-subtitle">White-label mapping counts and internal organization identifiers.</p>
                <div class="mt-5 grid grid-cols-1 gap-4">
                  <label class="block">
                    <span class="eb-field-label">Enabled</span>
                    <input value="Yes" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Mapping State</span>
                    <input value="{$whitelabel_mapping_state|default:'mapped'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Custom Domains</span>
                    <input value="{$whitelabel_custom_domains|@count}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Asset Types</span>
                    <input value="{$whitelabel_assets_by_type|@count}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                  <label class="block">
                    <span class="eb-field-label">Org ID</span>
                    <input value="{$whitelabel_tenant.org_id|default:'-'|escape}" disabled class="eb-input mt-2 w-full cursor-not-allowed opacity-90" />
                  </label>
                </div>
              </div>
            </div>
            <div class="eb-alert eb-alert--info mt-4 !mb-0">White-label infrastructure IDs are managed internally and are not editable here.</div>
          {/if}
        </div>
      </section>
    {/if}
{/capture}

{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='tenants'
  ebPhTitle=$ebPhTitle
  ebPhDescription=$ebPhDescription
  ebPhActions=$ebPhActions
  ebPhContent=$ebPhContent
}
