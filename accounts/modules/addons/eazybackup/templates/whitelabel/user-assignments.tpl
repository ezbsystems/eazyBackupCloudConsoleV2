{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
{capture assign=ebPhContent}
  <section class="eb-card-raised">
    <form method="get" action="{$modulelink}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
      <input type="hidden" name="m" value="eazybackup" />
      <input type="hidden" name="a" value="ph-user-assignments" />
      <label class="block text-sm">
        <span class="mb-1 block text-[var(--eb-text-muted)]">Search</span>
        <input type="text" name="q" value="{$q|default:''|escape}" placeholder="Backup user, tenant, or plan" class="eb-input" />
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-[var(--eb-text-muted)]">Sort By</span>
        <select name="sort" class="eb-input">
          <option value="username" {if $sort|default:'' eq 'username'}selected{/if}>Username</option>
          <option value="tenant" {if $sort|default:'' eq 'tenant'}selected{/if}>Tenant</option>
          <option value="plan" {if $sort|default:'' eq 'plan'}selected{/if}>Plan</option>
          <option value="status" {if $sort|default:'' eq 'status'}selected{/if}>Status</option>
          <option value="since" {if $sort|default:'' eq 'since'}selected{/if}>Since</option>
        </select>
      </label>
      <label class="block text-sm">
        <span class="mb-1 block text-[var(--eb-text-muted)]">Direction</span>
        <select name="dir" class="eb-input">
          <option value="asc" {if $dir|default:'' eq 'asc'}selected{/if}>Asc</option>
          <option value="desc" {if $dir|default:'' eq 'desc'}selected{/if}>Desc</option>
        </select>
      </label>
      <div class="self-end">
        <button type="submit" class="eb-btn eb-btn-primary">Apply</button>
      </div>
    </form>
  </section>

  <section class="eb-card-raised mt-5">
    <div class="mb-5 flex flex-col gap-1 border-b border-[var(--eb-border-subtle)] pb-4">
      <h2 class="eb-type-h4 text-[var(--eb-text-primary)]">Assigned Backup Users</h2>
      <p class="eb-page-description">Backup usernames currently mapped to active plan instances.</p>
    </div>

    {if $assigned_rows|@count > 0}
      <div class="eb-table-shell">
        <table class="eb-table">
          <thead>
            <tr>
              <th class="px-4 py-3 text-left font-medium">Username</th>
              <th class="px-4 py-3 text-left font-medium">Tenant Name</th>
              <th class="px-4 py-3 text-left font-medium">Plan Name</th>
              <th class="px-4 py-3 text-left font-medium">Status</th>
              <th class="px-4 py-3 text-left font-medium">Since</th>
              <th class="px-4 py-3 text-left font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$assigned_rows item=row}
              <tr>
                <td class="px-4 py-3 font-mono text-[var(--eb-text-primary)]">{$row.comet_user_id|default:'-'|escape}</td>
                <td class="px-4 py-3">
                  <a href="{$row.tenant_url|escape}" class="font-medium text-[var(--eb-text-primary)] hover:underline">{$row.tenant_name|default:'Unknown Tenant'|escape}</a>
                </td>
                <td class="px-4 py-3 text-[var(--eb-text-secondary)]">{$row.plan_name|default:'Unknown Plan'|escape}</td>
                <td class="px-4 py-3">
                  <span class="eb-badge {if $row.status eq 'active'}eb-badge--success{elseif $row.status eq 'trialing'}eb-badge--default{elseif $row.status eq 'past_due'}eb-badge--warning{else}eb-badge--danger{/if}">{$row.status|default:'-'|escape}</span>
                </td>
                <td class="px-4 py-3 text-[var(--eb-text-secondary)]">{if $row.created_at|default:'' neq ''}{$row.created_at|date_format:'%Y-%m-%d'}{else}-{/if}</td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap items-center gap-2">
                    <a href="{$row.tenant_url|escape}" class="eb-btn eb-btn-secondary eb-btn-xs">Tenant</a>
                    <a href="{$row.plans_url|escape}" class="eb-btn eb-btn-secondary eb-btn-xs">Plans</a>
                  </div>
                </td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    {else}
      <div class="rounded-xl border border-dashed border-slate-700 px-6 py-10 text-center text-sm text-[var(--eb-text-muted)]">
        No assigned backup users found.
      </div>
    {/if}
  </section>

  <section class="eb-card-raised mt-5" x-data="{ open: true }">
    <div class="mb-5 flex items-center justify-between gap-3 border-b border-[var(--eb-border-subtle)] pb-4">
      <div>
        <h2 class="eb-type-h4 text-[var(--eb-text-primary)]">Unassigned Backup Users</h2>
        <p class="eb-page-description">Backup usernames linked to tenants that are not currently attached to an active billing plan.</p>
      </div>
      <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="open = !open" x-text="open ? 'Hide' : 'Show'"></button>
    </div>

    <div x-show="open" x-cloak>
      {if $unassigned_rows|@count > 0}
        <div class="eb-table-shell">
          <table class="eb-table">
            <thead>
              <tr>
                <th class="px-4 py-3 text-left font-medium">Username</th>
                <th class="px-4 py-3 text-left font-medium">Tenant Name</th>
                <th class="px-4 py-3 text-left font-medium">Actions</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$unassigned_rows item=row}
                <tr>
                  <td class="px-4 py-3 font-mono text-[var(--eb-text-primary)]">{$row.comet_user_id|default:$row.comet_username|default:'-'|escape}</td>
                  <td class="px-4 py-3">
                    <a href="{$row.tenant_url|escape}" class="font-medium text-[var(--eb-text-primary)] hover:underline">{$row.tenant_name|default:'Unknown Tenant'|escape}</a>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                      <a href="{$row.tenant_url|escape}" class="eb-btn eb-btn-secondary eb-btn-xs">Tenant</a>
                      <a href="{$row.plans_url|escape}" class="eb-btn eb-btn-primary eb-btn-xs">Assign Plan</a>
                    </div>
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      {else}
        <div class="rounded-xl border border-dashed border-slate-700 px-6 py-10 text-center text-sm text-[var(--eb-text-muted)]">
          No unassigned backup users found.
        </div>
      {/if}
    </div>
  </section>
{/capture}
{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='user-assignments'
  ebPhTitle='User Assignments'
  ebPhDescription='Review which backup usernames are attached to active plans and which tenant-linked users still need a billing assignment.'
  ebPhContent=$ebPhContent
}
