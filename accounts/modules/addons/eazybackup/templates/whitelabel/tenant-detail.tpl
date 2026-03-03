{* Partner Hub — Tenant detail *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-4xl px-6 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Tenant #{$tenant.id|escape}</h1>
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

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium">Edit Tenant</h2>
      </div>
      <div class="border-t border-white/10"></div>
      <form method="post" action="{$modulelink}&a=ph-tenant&id={$tenant.id}" class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-4">
        <input type="hidden" name="tenant_id" value="{$tenant.id}" />
        <input type="hidden" name="eb_save_tenant" value="1" />
        {if isset($token) && $token ne ''}
          <input type="hidden" name="token" value="{$token}" />
        {/if}

        <label class="md:col-span-4 block">
          <span class="text-sm text-white/70">Subdomain</span>
          <input name="subdomain" value="{$tenant.subdomain|escape}" required class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <label class="md:col-span-5 block">
          <span class="text-sm text-white/70">FQDN</span>
          <input name="fqdn" value="{$tenant.fqdn|escape}" required class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <label class="md:col-span-3 block">
          <span class="text-sm text-white/70">Status</span>
          <select name="status" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            {foreach from=$statuses item=s}
              <option value="{$s|escape}" {if $tenant.status == $s}selected{/if}>{$s|escape}</option>
            {/foreach}
          </select>
        </label>

        <label class="md:col-span-4 block">
          <span class="text-sm text-white/70">Org ID</span>
          <input name="org_id" value="{$tenant.org_id|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <label class="md:col-span-8 block">
          <span class="text-sm text-white/70">Custom Domain</span>
          <input name="custom_domain" value="{$tenant.custom_domain|escape}" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>

        <div class="md:col-span-12 flex justify-end">
          <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Save Tenant</button>
        </div>
      </form>
    </section>

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium">White-label Status</h2>
      </div>
      <div class="border-t border-white/10"></div>
      <div class="px-6 py-5">
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs ring-1 ring-white/15 text-white/70">Pending</span>
      </div>
    </section>

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-rose-500/30 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium text-rose-200">Danger Zone</h2>
      </div>
      <div class="border-t border-rose-500/20"></div>
      <form method="post" action="{$modulelink}&a=ph-tenant&id={$tenant.id}" class="px-6 py-6 flex items-center justify-between">
        <input type="hidden" name="tenant_id" value="{$tenant.id}" />
        <input type="hidden" name="eb_delete_tenant" value="1" />
        {if isset($token) && $token ne ''}
          <input type="hidden" name="token" value="{$token}" />
        {/if}
        <p class="text-sm text-white/70">Delete this tenant record from canonical tenant storage.</p>
        <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-rose-600 hover:bg-rose-500" onclick="return confirm('Delete this tenant?');">Delete Tenant</button>
      </form>
    </section>
  </div>
</div>

