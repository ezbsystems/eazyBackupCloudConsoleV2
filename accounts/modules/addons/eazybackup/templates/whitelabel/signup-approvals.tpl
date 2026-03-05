{* Partner Hub - Signup approvals queue *}
{include file="$template/includes/head.tpl"}
{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}

<div class="min-h-screen bg-[rgb(var(--bg-page))] text-[rgb(var(--text-primary))]">
  <div class="container mx-auto max-w-full px-4 pb-8 pt-6">
    <div x-data="{ sidebarCollapsed: localStorage.getItem('eb_ph_sidebar_collapsed') === 'true' || window.innerWidth < 1360, toggleCollapse() { this.sidebarCollapsed = !this.sidebarCollapsed; localStorage.setItem('eb_ph_sidebar_collapsed', this.sidebarCollapsed); }, handleResize() { if (window.innerWidth < 1360 && !this.sidebarCollapsed) this.sidebarCollapsed = true; } }" x-init="window.addEventListener('resize', () => handleResize())" class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
      <div class="flex">
        {include file="modules/addons/eazybackup/templates/whitelabel/partials/sidebar_partner_hub.tpl" ebPhSidebarPage='signup-approvals'}
        <main class="flex-1 min-w-0 overflow-x-auto">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold tracking-tight">Pending Signup Approvals</h1>
      <a href="{$modulelink}&a=ph-tenants-manage" class="rounded-lg px-3 py-1.5 ring-1 ring-white/10 hover:bg-white/10 text-sm">Back to Tenants</a>
    </div>

    {if isset($notice) && $notice == 'approved'}
      <div class="mt-4 rounded-xl bg-emerald-500/20 ring-1 ring-emerald-400/20 px-4 py-3 text-sm text-white">
        Signup approved and order accepted.
      </div>
    {/if}
    {if isset($notice) && $notice == 'rejected'}
      <div class="mt-4 rounded-xl bg-emerald-500/20 ring-1 ring-emerald-400/20 px-4 py-3 text-sm text-white">
        Signup rejected and order cancellation path attempted.
      </div>
    {/if}
    {if isset($error) && $error ne ''}
      <div class="mt-4 rounded-xl bg-rose-500/10 ring-1 ring-rose-400/20 px-4 py-3 text-sm text-rose-200">
        Action could not be completed: {$error|escape}
      </div>
    {/if}

    <div class="mt-6 rounded-2xl overflow-hidden ring-1 ring-white/10">
      <table class="w-full text-sm">
        <thead class="bg-white/5 text-white/70">
          <tr class="text-left">
            <th class="px-4 py-3 font-medium">Tenant</th>
            <th class="px-4 py-3 font-medium">Email</th>
            <th class="px-4 py-3 font-medium">WHMCS Client</th>
            <th class="px-4 py-3 font-medium">Order</th>
            <th class="px-4 py-3 font-medium">Submitted</th>
            <th class="px-4 py-3 font-medium text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/10">
          {if $rows|@count > 0}
            {foreach from=$rows item=row}
              <tr class="hover:bg-white/5">
                <td class="px-4 py-3">
                  {if $row.subdomain}{$row.subdomain|escape}{else}{$row.fqdn|default:'-'|escape}{/if}
                </td>
                <td class="px-4 py-3">{$row.email|escape}</td>
                <td class="px-4 py-3">{$row.whmcs_client_id|default:'-'|escape}</td>
                <td class="px-4 py-3">{$row.whmcs_order_id|default:'-'|escape}</td>
                <td class="px-4 py-3">{$row.created_at|escape}</td>
                <td class="px-4 py-3 text-right">
                  {if $row.status == 'pending_approval'}
                    <div class="flex items-center justify-end gap-2">
                      <form method="post" action="{$modulelink}&a=ph-signup-approve" class="inline-block">
                        <input type="hidden" name="event_id" value="{$row.id|escape}" />
                        <input type="hidden" name="token" value="{$token|escape}" />
                        <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-500">Approve</button>
                      </form>
                      <form method="post" action="{$modulelink}&a=ph-signup-reject" class="inline-block">
                        <input type="hidden" name="event_id" value="{$row.id|escape}" />
                        <input type="hidden" name="approval_notes" value="Rejected from Partner Hub queue" />
                        <input type="hidden" name="token" value="{$token|escape}" />
                        <button type="submit" class="rounded-lg px-3 py-1.5 text-sm font-medium text-white bg-rose-600 hover:bg-rose-500">Reject</button>
                      </form>
                    </div>
                  {elseif $row.status == 'approving'}
                    <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-amber-500/20 text-amber-200 ring-1 ring-amber-300/20">Approving in progress</span>
                  {elseif $row.status == 'rejecting'}
                    <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-amber-500/20 text-amber-200 ring-1 ring-amber-300/20">Rejecting in progress</span>
                  {else}
                    <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-medium bg-white/10 text-white/70 ring-1 ring-white/15">{$row.status|escape}</span>
                  {/if}
                </td>
              </tr>
            {/foreach}
          {else}
            <tr>
              <td colspan="6" class="px-4 py-8 text-center text-white/60">No signups are waiting for approval.</td>
            </tr>
          {/if}
        </tbody>
      </table>
    </div>
        </main>
      </div>
    </div>
  </div>
</div>
