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
    {if $portal_admin.exists|default:false && $portal_admin.status|default:'' eq 'active'}
    <a href="{$modulelink}&a=ph-tenant-impersonate&tenant_id={$tenant.public_id|escape:'url'}" class="eb-btn eb-btn-info eb-btn-sm" title="Log in to the tenant portal as this tenant's admin user">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
        </svg>
        Login as Tenant
    </a>
    {/if}
    <a href="{$modulelink}&a=ph-tenants-manage" class="eb-btn eb-btn-secondary eb-btn-sm">Back to Customer Tenants</a>
  </div>
{/capture}

{capture assign=ebPhContent}
      <div class="eb-panel-nav">
        <nav class="flex flex-wrap gap-2" aria-label="Tenant detail tabs">
          <a href="{$tab_links.profile|default:'#'|escape}" class="eb-tab {if $activeTab eq 'profile'}is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998-0A4.5 4.5 0 0 0 12 16.5h-1.5a4.5 4.5 0 0 0-4.499 3.618Z" /></svg>
            <span>Profile</span>
          </a>
          <a href="{$tab_links.members|default:'#'|escape}" class="eb-tab {if $activeTab eq 'members'}is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
            <span>Members</span>
          </a>
          <a href="{$tab_links.billing|default:'#'|escape}" class="eb-tab {if $activeTab eq 'billing'}is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
            <span>Billing</span>
          </a>
          <a href="{$tab_links.storage_users|default:'#'|escape}" class="eb-tab {if $activeTab eq 'storage_users'}is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 0 0 4.5 4.5H18a3.75 3.75 0 0 0 1.332-7.257 3 3 0 0 0-3.758-3.848 5.25 5.25 0 0 0-10.233 2.33A4.502 4.502 0 0 0 2.25 15Z" /></svg>
            <span>Cloud Storage</span>
          </a>
          <a href="{$tab_links.white_label|default:'#'|escape}" class="eb-tab {if $activeTab eq 'white_label'}is-active{/if}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4 shrink-0">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
            </svg>
            <span>White Label</span>
          </a>
        </nav>
      </div>

    {if $notice neq '' || $error neq '' || (isset($legacy_notice) && $legacy_notice neq '')}
      <div class="mb-6 space-y-3">
        {if $notice neq ''}
          <div class="eb-alert eb-alert--success">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            <div>Tenant updated.</div>
          </div>
        {/if}
        {if $error eq 'stripe_sync_warning'}
          <div class="eb-alert eb-alert--warning">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
            <div>Tenant saved locally, but the Stripe customer profile could not be updated. Billing details in Stripe may be temporarily out of sync.</div>
          </div>
        {/if}
        {if $error eq 'portal_admin_password_short'}
          <div class="eb-alert eb-alert--warning">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
            <div>Tenant created, but the portal admin was not added. Passwords must be at least 8 characters.</div>
          </div>
        {/if}
        {if $error neq '' && $error neq 'stripe_sync_warning' && $error neq 'portal_admin_password_short'}
          <div class="eb-alert eb-alert--danger">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            <div>Unable to process the request ({$error|escape}).</div>
          </div>
        {/if}
        {if isset($legacy_notice) && $legacy_notice neq ''}
          <div class="eb-alert eb-alert--info">
            <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
            <div>You were redirected here from a legacy e3 tenant URL ({$legacy_notice|escape}). This Partner Hub page is the canonical tenant view.</div>
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
                  <div class="block" x-data="{literal}{ open: false, value: '{/literal}{$tenant.status|escape:'javascript'}{literal}' }{/literal}">
                    <span class="eb-field-label">Status</span>
                    <input type="hidden" name="status" :value="value" />
                    <div class="relative mt-2">
                      <button type="button" class="eb-menu-trigger w-full" @click="open = !open" @keydown.escape.window="open = false">
                        <span x-text="value"></span>
                        <svg class="w-4 h-4 transition-transform" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                      </button>
                      <div class="eb-dropdown-menu absolute left-0 right-0 z-20 mt-1" x-show="open" x-transition @click.outside="open = false" style="display:none;">
                        {foreach from=$statuses item=s}
                          <button type="button" class="eb-menu-option w-full" :class="value === '{$s|escape:'javascript'}' && 'is-active'" @click="value = '{$s|escape:'javascript'}'; open = false;">{$s|escape}</button>
                        {/foreach}
                      </div>
                    </div>
                  </div>
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
                    <div class="block" x-data="{literal}{ open: false, value: '{/literal}{$portal_admin.status|default:'active'|escape:'javascript'}{literal}' }{/literal}">
                      <span class="eb-field-label">Admin Status</span>
                      <input type="hidden" name="portal_admin_status" :value="value" />
                      <div class="relative mt-2">
                        <button type="button" class="eb-menu-trigger w-full" @click="open = !open" @keydown.escape.window="open = false">
                          <span x-text="value"></span>
                          <svg class="w-4 h-4 transition-transform" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </button>
                        <div class="eb-dropdown-menu absolute left-0 right-0 z-20 mt-1" x-show="open" x-transition @click.outside="open = false" style="display:none;">
                          <button type="button" class="eb-menu-option w-full" :class="value === 'active' && 'is-active'" @click="value = 'active'; open = false;">active</button>
                          <button type="button" class="eb-menu-option w-full" :class="value === 'disabled' && 'is-active'" @click="value = 'disabled'; open = false;">disabled</button>
                        </div>
                      </div>
                    </div>
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
      {* -- Stat cards -- *}
      <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
        <div class="eb-stat-card">
          <div class="eb-stat-label">Assigned Plans</div>
          <div class="eb-type-stat mt-1">{$storage_stats.assigned_count|default:0}</div>
        </div>
        <div class="eb-stat-card">
          <div class="eb-stat-label">S3 Accounts</div>
          <div class="eb-type-stat mt-1">{$storage_stats.msp_s3_accounts_count|default:0}</div>
        </div>
        <div class="eb-stat-card">
          <div class="eb-stat-label">Total Storage Used</div>
          <div class="eb-type-stat mt-1" x-data x-text="(() => { const b = {$storage_stats.total_usage_bytes|default:0}; if (b === 0) return '0 B'; const k = 1024; const s = ['B','KB','MB','GB','TB']; const i = Math.floor(Math.log(b) / Math.log(k)); return parseFloat((b / Math.pow(k, i)).toFixed(2)) + ' ' + s[i]; })()">
            {$storage_stats.total_usage_bytes|default:0} B
          </div>
        </div>
      </div>

      {if $storage_users_error neq ''}
        <div class="eb-alert eb-alert--warning mb-4">
          <div>
            {if $storage_users_error eq 'storage_users_table_missing'}
              The Cloud Storage module tables are not available. Ensure the Cloud Storage addon is installed and activated.
            {else}
              Could not load storage user data. Please try again later.
            {/if}
          </div>
        </div>
      {/if}

      {* -- Section 1: Assigned Storage Plans -- *}
      <section class="eb-card-raised !p-0 overflow-hidden mb-6">
        <div class="border-b border-[var(--eb-border-subtle)] px-6 py-4">
          <h2 class="eb-card-title text-base font-semibold">Assigned Storage Plans</h2>
          <p class="eb-card-subtitle mt-1">e3 Object Storage plans currently assigned to this tenant via billing.</p>
        </div>
        {if $storage_assigned_plans|@count > 0}
          <div class="eb-table-shell">
            <table class="eb-table">
              <thead>
                <tr>
                  <th>S3 User</th>
                  <th>Plan</th>
                  <th>Status</th>
                  <th>Storage Used</th>
                  <th>Assigned</th>
                </tr>
              </thead>
              <tbody>
                {foreach from=$storage_assigned_plans item=ap}
                  <tr>
                    <td class="eb-table-primary">
                      {if $ap.s3_user}
                        {$ap.s3_user.display_label|escape}
                      {else}
                        <span class="text-[var(--eb-text-muted)]">S3 user #{$ap.s3_user_id|escape}</span>
                      {/if}
                    </td>
                    <td>{$ap.plan_name|escape}</td>
                    <td>
                      {if $ap.status eq 'active'}
                        <span class="eb-badge eb-badge--success">Active</span>
                      {elseif $ap.status eq 'trialing'}
                        <span class="eb-badge eb-badge--info">Trialing</span>
                      {elseif $ap.status eq 'past_due'}
                        <span class="eb-badge eb-badge--warning">Past Due</span>
                      {elseif $ap.status eq 'canceled' || $ap.status eq 'cancelled'}
                        <span class="eb-badge eb-badge--danger">Canceled</span>
                      {else}
                        <span class="eb-badge eb-badge--neutral">{$ap.status|escape|default:'Unknown'}</span>
                      {/if}
                    </td>
                    <td>
                      <span x-data x-text="(() => { const b = {$ap.usage_bytes|default:0}; if (b === 0) return '0 B'; const k = 1024; const s = ['B','KB','MB','GB','TB']; const i = Math.floor(Math.log(b) / Math.log(k)); return parseFloat((b / Math.pow(k, i)).toFixed(2)) + ' ' + s[i]; })()">
                        {$ap.usage_bytes|default:0} B
                      </span>
                    </td>
                    <td class="text-[var(--eb-text-muted)]">{$ap.created_at|escape|truncate:10:''}</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        {else}
          <div class="px-6 py-8">
            <div class="eb-app-empty">
              <div class="eb-app-empty-title">No storage plans assigned</div>
              <p class="eb-app-empty-copy">Assign an e3 Object Storage billing plan from the <a href="{$tab_links.billing|default:'#'|escape}" class="text-[var(--eb-primary)] hover:underline">Billing</a> tab.</p>
            </div>
          </div>
        {/if}
      </section>

      {* -- Section 2: MSP S3 Accounts -- *}
      <section class="eb-card-raised !p-0 overflow-hidden">
        <div class="border-b border-[var(--eb-border-subtle)] px-6 py-4">
          <h2 class="eb-card-title text-base font-semibold">MSP Storage Accounts</h2>
          <p class="eb-card-subtitle mt-1">S3 storage accounts available from your active hosting services. These accounts are used when assigning e3 billing plans.</p>
        </div>
        {if $storage_msp_s3_users|@count > 0}
          <div class="eb-table-shell">
            <table class="eb-table">
              <thead>
                <tr>
                  <th>Account</th>
                  <th>Storage Used</th>
                  <th>Billing Status</th>
                </tr>
              </thead>
              <tbody>
                {foreach from=$storage_msp_s3_users item=s3u}
                  <tr>
                    <td class="eb-table-primary">{$s3u.short_label|escape}</td>
                    <td>
                      <span x-data x-text="(() => { const b = {$s3u.usage_bytes|default:0}; if (b === 0) return '0 B'; const k = 1024; const s = ['B','KB','MB','GB','TB']; const i = Math.floor(Math.log(b) / Math.log(k)); return parseFloat((b / Math.pow(k, i)).toFixed(2)) + ' ' + s[i]; })()">
                        {$s3u.usage_bytes|default:0} B
                      </span>
                    </td>
                    <td>
                      {if $s3u.assigned}
                        <span class="eb-badge eb-badge--success eb-badge--dot" style="gap: 6px;">Assigned to plan</span>
                      {else}
                        <span class="eb-badge eb-badge--neutral eb-badge--dot" style="gap: 6px;">Unassigned</span>
                      {/if}
                    </td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        {else}
          <div class="px-6 py-8">
            <div class="eb-app-empty">
              <div class="eb-app-empty-title">No S3 accounts found</div>
              <p class="eb-app-empty-copy">No active storage hosting services were found on your account. S3 storage accounts are required to assign e3 billing plans.</p>
            </div>
          </div>
        {/if}
      </section>

      <div class="mt-4">
        <div class="eb-alert eb-alert--info !mb-0">
          <div>To assign an e3 Object Storage billing plan to this tenant, go to the <a href="{$tab_links.billing|default:'#'|escape}" class="text-[var(--eb-primary)] hover:underline">Billing</a> tab, click Assign Plan, and select an e3 storage plan. The S3 user picker will appear automatically.</div>
        </div>
      </div>
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
                   selectedS3UserId: '',
                   assignPlanOpen: false,
                   assignPlanSearch: '',
                   assignCometUserOpen: false,
                   assignCometUserSearch: '',
                   assignS3UserOpen: false,
                   assignS3UserSearch: '',
                   plans: {$billing_assignable_plans|default:array()|@json_encode|escape:'html'},
                   cometUsers: {$billing_tenant_comet_users|default:array()|@json_encode|escape:'html'},
                   s3Users: {$billing_s3_users|default:array()|@json_encode|escape:'html'},
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
                     const mode = plan && plan.assignment_mode ? plan.assignment_mode : null;
                     return mode ? !!mode.requires_comet_user : (plan ? !!plan.requires_comet_user : true);
                   },
                   requiresS3User() {
                     const plan = this.selectedPlan();
                     const mode = plan && plan.assignment_mode ? plan.assignment_mode : null;
                     return mode ? !!mode.requires_s3_user : false;
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
                   filteredS3Users() {
                     const query = String(this.assignS3UserSearch || '').trim().toLowerCase();
                     if (!query) {
                       return this.s3Users;
                     }
                     return this.s3Users.filter((user) => {
                       return String(user.display_label || '').toLowerCase().includes(query)
                         || String(user.username || '').toLowerCase().includes(query)
                         || String(user.name || '').toLowerCase().includes(query)
                         || String(user.id || '').toLowerCase().includes(query);
                     });
                   },
                   selectAssignPlan(planId) {
                     this.selectedPlanId = String(planId || '');
                     this.assignPlanOpen = false;
                     this.assignPlanSearch = '';
                     this.assignS3UserOpen = false;
                     this.assignS3UserSearch = '';
                     if (!this.requiresCometUser()) {
                       this.selectedCometUserId = '';
                     } else if (!this.selectedCometUserId && this.cometUsers.length === 1) {
                       this.selectedCometUserId = String(this.cometUsers[0].comet_user_id || '');
                     }
                     if (!this.requiresS3User()) {
                       this.selectedS3UserId = '';
                     } else if (!this.selectedS3UserId && this.s3Users.length === 1) {
                       this.selectedS3UserId = String(this.s3Users[0].id || '');
                     }
                   },
                   selectAssignCometUser(cometUserId) {
                     this.selectedCometUserId = String(cometUserId || '');
                     this.assignCometUserOpen = false;
                     this.assignCometUserSearch = '';
                   },
                   selectAssignS3User(id) {
                     this.selectedS3UserId = String(id || '');
                     this.assignS3UserOpen = false;
                     this.assignS3UserSearch = '';
                   },
                   selectedS3UserLabel() {
                     if (!this.selectedS3UserId) {
                       return 'Select S3 user';
                     }
                     const selectedId = String(this.selectedS3UserId || '');
                     const user = this.s3Users.find((row) => String(row.id || '') === selectedId) || null;
                     return user
                       ? String(user.display_label || user.name || user.username || ('S3 user #' + selectedId))
                       : ('S3 user #' + selectedId);
                   },
                   toggleAssignS3UserDropdown() {
                     if (!this.requiresS3User() || this.s3Users.length === 0) {
                       return;
                     }
                     this.assignPlanOpen = false;
                     this.assignCometUserOpen = false;
                     this.assignS3UserOpen = !this.assignS3UserOpen;
                   },
                   openAssignModal() {
                     this.assignOpen = true;
                     this.assignMessage = '';
                     this.assignPlanOpen = false;
                     this.assignPlanSearch = '';
                     this.assignCometUserOpen = false;
                     this.assignCometUserSearch = '';
                     this.assignS3UserOpen = false;
                     this.assignS3UserSearch = '';
                     if (!this.selectedPlanId && this.plans.length) {
                       this.selectedPlanId = String(this.plans[0].id || '');
                     }
                     if (this.requiresCometUser() && !this.selectedCometUserId && this.cometUsers.length) {
                       this.selectedCometUserId = String(this.cometUsers[0].comet_user_id || '');
                     }
                     if (this.requiresS3User() && !this.selectedS3UserId && this.s3Users.length) {
                       this.selectedS3UserId = String(this.s3Users[0].id || '');
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
                     if (this.requiresS3User() && !this.selectedS3UserId) {
                       this.assignMessage = 'Select an S3 user first.';
                       return;
                     }
                     this.assignSaving = true;
                     this.assignMessage = '';
                     try {
                       const payload = new URLSearchParams({
                         plan_id: String(this.selectedPlanId || ''),
                         tenant_id: String(this.tenantPublicId || ''),
                         token: this.token
                       });
                       if (this.requiresCometUser() && this.selectedCometUserId) {
                         payload.set('comet_user_id', String(this.selectedCometUserId || ''));
                       }
                       if (this.requiresS3User() && this.selectedS3UserId) {
                         payload.set('s3_user_id', String(this.selectedS3UserId || ''));
                       }
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
                      <th class="px-6 py-3">Assigned User</th>
                      <th class="px-6 py-3">Status</th>
                      <th class="px-6 py-3">Since</th>
                    </tr>
                  </thead>
                  <tbody>
                    {foreach from=$billing_plan_instances item=pi}
                    <tr>
                      <td class="px-6 py-3 text-[var(--eb-text-primary)]">{$pi.plan_name|default:'Unknown Plan'|escape}</td>
                      <td class="px-6 py-3 eb-type-mono text-[var(--eb-text-secondary)]">{$pi.comet_user_display|default:$pi.comet_user_id|default:'-'|escape}</td>
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
                      @click="assignPlanOpen = !assignPlanOpen; if (assignPlanOpen) { assignCometUserOpen = false; assignS3UserOpen = false; }"
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
                            <span class="truncate text-left text-xs text-[var(--eb-text-muted)]" x-text="plan.description || ((plan.assignment_mode && plan.assignment_mode.requires_s3_user) ? 'Requires an MSP-owned S3 user.' : (((plan.assignment_mode && plan.assignment_mode.requires_comet_user === false) || !plan.requires_comet_user) ? 'Tenant-level storage assignment.' : 'Requires an eazyBackup user.'))"></span>
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
                        @click="if (requiresCometUser() && cometUsers.length) { assignCometUserOpen = !assignCometUserOpen; if (assignCometUserOpen) { assignPlanOpen = false; assignS3UserOpen = false; } }"
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
                <div x-show="requiresS3User()">
                  <label class="block text-sm">
                    <span class="mb-1 block text-slate-300">S3 User</span>
                    <div class="relative" @click.outside="assignS3UserOpen = false">
                      <button
                        type="button"
                        class="eb-input relative flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left disabled:cursor-not-allowed disabled:opacity-50"
                        @click="toggleAssignS3UserDropdown()"
                        :disabled="!requiresS3User() || s3Users.length === 0"
                        :aria-expanded="assignS3UserOpen"
                      >
                        <span class="min-w-0 truncate" :class="selectedS3UserId ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="selectedS3UserLabel()"></span>
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="assignS3UserOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                      </button>
                      <div
                        x-show="assignS3UserOpen"
                        x-transition
                        class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                        style="display: none;"
                      >
                        <div class="border-b border-[var(--eb-border-subtle)] p-2">
                          <input
                            type="search"
                            x-model="assignS3UserSearch"
                            placeholder="Search S3 users..."
                            class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm"
                            @click.stop
                          />
                        </div>
                        <div class="max-h-52 overflow-y-auto p-1">
                          <template x-for="user in filteredS3Users()" :key="'tenant-s3-user-' + String(user.id || '')">
                            <button
                              type="button"
                              class="eb-menu-item w-full flex-col !items-stretch gap-0.5"
                              :class="String(selectedS3UserId || '') === String(user.id || '') ? 'is-active' : ''"
                              @click="selectAssignS3User(user.id)"
                            >
                              <span class="truncate text-left font-medium" x-text="user.display_label || user.name || user.username || ('S3 user #' + user.id)"></span>
                              <span class="truncate text-left text-xs text-[var(--eb-text-muted)]" x-text="'ID ' + String(user.id || '-')"></span>
                            </button>
                          </template>
                          <div x-show="filteredS3Users().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]" x-text="assignS3UserSearch ? 'No S3 users match your search.' : 'No S3 users are available for this MSP.'"></div>
                        </div>
                      </div>
                    </div>
                  </label>
                  <p class="mt-2 text-xs text-slate-500">Choose the MSP-owned S3 user that should back this storage subscription.</p>
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
