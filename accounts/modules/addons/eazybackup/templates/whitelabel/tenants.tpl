{* Partner Hub — Tenants list *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-slate-950 text-gray-100 overflow-x-hidden">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <main class="min-w-0">
        <div class="flex items-center justify-between border-b border-slate-800/60 px-6 py-4">
          <h1 class="text-2xl font-semibold tracking-tight">Customer Tenants</h1>
          <a href="{$modulelink}&a=ph-clients" class="rounded-xl px-4 py-2 text-white/80 ring-1 ring-white/10 hover:bg-white/5">Back to Clients</a>
        </div>
        <div class="p-6">

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
    {if isset($legacy_notice) && $legacy_notice neq ''}
      <div class="mt-4 rounded-xl bg-amber-500/10 ring-1 ring-amber-400/30 px-4 py-3 text-sm text-amber-100">
        You were redirected here from a legacy e3 tenants URL ({$legacy_notice|escape}). Customer tenant management now lives in Partner Hub.
      </div>
    {/if}

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium">Create Customer Tenant</h2>
      </div>
      <div class="border-t border-white/10"></div>
      <form method="post" action="{$modulelink}&a=ph-tenants" class="px-6 py-6 grid grid-cols-1 md:grid-cols-12 gap-4">
        <input type="hidden" name="eb_create_tenant" value="1" />
        {if isset($token) && $token ne ''}
          <input type="hidden" name="token" value="{$token}" />
        {/if}
        <label class="md:col-span-4 block">
          <span class="text-sm text-white/70">Tenant Name</span>
          <input name="name" required class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <label class="md:col-span-3 block">
          <span class="text-sm text-white/70">Slug (optional)</span>
          <input name="slug" placeholder="auto-from-name" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <label class="md:col-span-3 block">
          <span class="text-sm text-white/70">Contact Email (optional)</span>
          <input name="contact_email" type="email" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5" />
        </label>
        <label class="md:col-span-2 block">
          <span class="text-sm text-white/70">Status</span>
          <select name="status" class="mt-2 w-full rounded-xl bg-[rgb(var(--bg-input))] text-white/90 ring-1 ring-white/10 focus:ring-2 focus:ring-[rgb(var(--accent))] focus:outline-none px-3.5 py-2.5">
            {foreach from=$statuses item=s}
              <option value="{$s|escape}">{$s|escape}</option>
            {/foreach}
          </select>
        </label>
        <div class="md:col-span-2 flex items-end justify-end">
          <button type="submit" class="rounded-xl px-4 py-2 font-medium text-white bg-[rgb(var(--accent))] hover:bg-[rgb(var(--accent))]/90">Create</button>
        </div>
      </form>
    </section>

    <section class="mt-6 rounded-2xl bg-[rgb(var(--bg-card))] ring-1 ring-white/10 overflow-hidden">
      <div class="px-6 py-5">
        <h2 class="text-lg font-medium">Existing Customer Tenants</h2>
      </div>
      <div class="border-t border-white/10"></div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-white/5 text-white/70">
            <tr class="text-left">
              <th class="px-4 py-3 font-medium">ID</th>
              <th class="px-4 py-3 font-medium">Name</th>
              <th class="px-4 py-3 font-medium">Slug</th>
              <th class="px-4 py-3 font-medium">Contact Email</th>
              <th class="px-4 py-3 font-medium">Status</th>
              <th class="px-4 py-3 font-medium">Updated</th>
              <th class="px-4 py-3 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/10">
            {foreach from=$tenants item=tenant}
              <tr class="hover:bg-white/5">
                <td class="px-4 py-3">{$tenant.id|escape}</td>
                <td class="px-4 py-3">{$tenant.name|escape}</td>
                <td class="px-4 py-3">{$tenant.slug|escape}</td>
                <td class="px-4 py-3">{$tenant.contact_email|default:'-'|escape}</td>
                <td class="px-4 py-3">{$tenant.status|escape}</td>
                <td class="px-4 py-3">{$tenant.updated_at|default:'-'|escape}</td>
                <td class="px-4 py-3 text-right">
                  <a class="rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10" href="{$modulelink}&a=ph-tenant&id={$tenant.id}">Manage</a>
                </td>
              </tr>
            {foreachelse}
              <tr>
                <td colspan="7" class="px-4 py-6 text-center text-white/50">No customer tenants yet.</td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    </section>
        </div>
      </main>
    </div>
  </div>
</div>

