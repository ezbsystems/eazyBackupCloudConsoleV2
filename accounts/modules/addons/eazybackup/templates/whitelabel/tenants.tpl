{* Partner Hub — Tenants list *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="mx-auto max-w-6xl px-6 py-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Customer Tenants</h1>
      <a href="{$modulelink}&a=ph-clients" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5">Back to Clients</a>
    </div>

    {if $notice neq ''}
      <div class="mt-4 rounded-xl bg-emerald-500/20 ring-1 ring-emerald-400/30 px-4 py-3 text-sm text-white">
        Saved successfully.
      </div>
    {/if}
    {if $error neq ''}
      <div class="mt-4 rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-200">
        Unable to process the request ({$error|escape}).
      </div>
    {/if}

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium">Create Tenant</h2>
      </div>
      <div class="border-t border-white/10"></div>
      <form method="post" action="{$modulelink}&a=ph-tenants" class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-4">
        <input type="hidden" name="eb_create_tenant" value="1" />
        {if isset($token) && $token ne ''}
          <input type="hidden" name="token" value="{$token}" />
        {/if}
        <label class="md:col-span-3 block">
          <span class="text-sm text-white/70">Subdomain</span>
          <input name="subdomain" required class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <label class="md:col-span-4 block">
          <span class="text-sm text-white/70">FQDN</span>
          <input name="fqdn" required class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <label class="md:col-span-2 block">
          <span class="text-sm text-white/70">Status</span>
          <select name="status" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            {foreach from=$statuses item=s}
              <option value="{$s|escape}">{$s|escape}</option>
            {/foreach}
          </select>
        </label>
        <label class="md:col-span-3 block">
          <span class="text-sm text-white/70">Org ID (optional)</span>
          <input name="org_id" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>

        <label class="md:col-span-4 block">
          <span class="text-sm text-white/70">Custom Domain (optional)</span>
          <input name="custom_domain" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <div class="md:col-span-2 flex items-end justify-end">
          <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Create</button>
        </div>
      </form>
    </section>

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium">Existing Tenants</h2>
      </div>
      <div class="border-t border-white/10"></div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-white/5 text-white/70">
            <tr class="text-left">
              <th class="px-4 py-3 font-medium">ID</th>
              <th class="px-4 py-3 font-medium">FQDN</th>
              <th class="px-4 py-3 font-medium">Status</th>
              <th class="px-4 py-3 font-medium">White-label</th>
              <th class="px-4 py-3 font-medium">Org ID</th>
              <th class="px-4 py-3 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            {foreach from=$tenants item=tenant}
              <tr class="hover:bg-white/5">
                <td class="px-4 py-3">{$tenant.id|escape}</td>
                <td class="px-4 py-3">{$tenant.fqdn|escape}</td>
                <td class="px-4 py-3">{$tenant.status|escape}</td>
                <td class="px-4 py-3">
                  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs ring-1 ring-white/15 text-white/70">Pending</span>
                </td>
                <td class="px-4 py-3">{$tenant.org_id|default:'-'|escape}</td>
                <td class="px-4 py-3 text-right">
                  <a class="rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10" href="{$modulelink}&a=ph-tenant&id={$tenant.id}">Manage</a>
                </td>
              </tr>
            {foreachelse}
              <tr>
                <td colspan="6" class="px-4 py-6 text-center text-white/50">No tenants yet.</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>

