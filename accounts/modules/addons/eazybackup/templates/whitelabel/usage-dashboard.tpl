{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
{capture assign=ebPhActions}
  <div class="flex flex-wrap items-center justify-end gap-2">
    <a href="{$modulelink}&a=ph-usage-dashboard" class="eb-btn eb-btn-secondary eb-btn-sm">Refresh</a>
  </div>
{/capture}
{capture assign=ebPhContent}
  <div x-data="{
        metricOpen: false,
        statusOpen: false,
        sortOpen: false,
        dirOpen: false,
        metricOptions: {$metric_options|default:array()|@json_encode|escape:'html'},
        metricValue: '{$metric|default:''|escape:'javascript'}',
        statusValue: '{$status|default:''|escape:'javascript'}',
        sortValue: '{$sort|default:'last_push'|escape:'javascript'}',
        dirValue: '{$dir|default:'desc'|escape:'javascript'}',
        liveMessage: '',
        metricLabel() {
          return this.metricOptions[this.metricValue] || 'Metric';
        },
        statusLabel() {
          return {
            '': 'All Statuses',
            over_quota: 'Over Quota',
            at_risk: 'At Risk',
            capped: 'Capped',
            healthy: 'Healthy'
          }[this.statusValue] || 'All Statuses';
        },
        sortLabel() {
          return {
            last_push: 'Last Push',
            tenant: 'Tenant',
            plan: 'Plan',
            metric: 'Metric',
            included: 'Included Qty',
            used: 'Used Qty',
            status: 'Status'
          }[this.sortValue] || 'Last Push';
        },
        dirLabel() {
          return {
            desc: 'Desc',
            asc: 'Asc'
          }[this.dirValue] || 'Desc';
        },
        async fetchStripeUsage(itemId) {
          this.liveMessage = 'Checking Stripe usage...';
          const url = '{$modulelink}&a=ph-usage-dashboard-stripe-live&plan_instance_item_id=' + encodeURIComponent(String(itemId)) + '&token={$token|escape:'javascript'}';
          const res = await fetch(url, { credentials: 'same-origin' });
          const data = await res.json();
          this.liveMessage = data.status === 'success'
            ? 'Stripe returned ' + ((data.summaries || []).length) + ' summary rows.'
            : (data.message || 'Stripe usage check failed.');
        },
        async pushUsageNow(itemId) {
          this.liveMessage = 'Pushing usage to Stripe...';
          const body = new URLSearchParams({ plan_instance_item_id: String(itemId), token: '{$token|escape:'javascript'}' });
          const res = await fetch('{$modulelink}&a=ph-usage-dashboard-push-now', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
          });
          const data = await res.json();
          this.liveMessage = data.status === 'success'
            ? 'Pushed usage successfully. Billable quantity: ' + String(data.billable_qty || 0)
            : (data.message || 'Usage push failed.');
        }
      }">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <section class="eb-card-raised">
        <div class="text-xs uppercase tracking-[0.2em] text-[var(--eb-text-muted)]">Active Metered Subscriptions</div>
        <div class="mt-2 text-3xl font-semibold text-[var(--eb-text-primary)]">{$active_metered_subscriptions|default:0}</div>
        <p class="mt-2 text-sm text-[var(--eb-text-muted)]">Active metered plan items visible across this MSP.</p>
      </section>
      <section class="eb-card-raised">
        <div class="text-xs uppercase tracking-[0.2em] text-[var(--eb-text-muted)]">Tenants Over Included Quota</div>
        <div class="mt-2 text-3xl font-semibold text-[var(--eb-text-primary)]">{$tenants_over_included_quota|default:0}</div>
        <p class="mt-2 text-sm text-[var(--eb-text-muted)]">Distinct tenants currently above the included quantity on at least one metered line.</p>
      </section>
      <section class="eb-card-raised">
        <div class="flex items-center justify-between gap-2">
          <div class="text-xs uppercase tracking-[0.2em] text-[var(--eb-text-muted)]">Last Usage Push</div>
          {if $last_usage_push_stale|default:false}
            <span class="eb-badge eb-badge--warning">Stale</span>
          {/if}
        </div>
        <div class="mt-2 text-lg font-semibold text-[var(--eb-text-primary)]">
          {if $last_usage_push|default:'' neq ''}{$last_usage_push|date_format:'%Y-%m-%d %H:%M'}{else}Never{/if}
        </div>
        <p class="mt-2 text-sm text-[var(--eb-text-muted)]">Most recent Stripe push recorded in the local usage ledger.</p>
      </section>
    </div>

    <section class="eb-subpanel mt-5">
      <form method="get" action="{$modulelink}" class="grid grid-cols-1 gap-3 md:grid-cols-5">
        <input type="hidden" name="m" value="eazybackup" />
        <input type="hidden" name="a" value="ph-usage-dashboard" />
        <input type="hidden" name="metric" :value="metricValue" />
        <input type="hidden" name="status" :value="statusValue" />
        <input type="hidden" name="sort" :value="sortValue" />
        <input type="hidden" name="dir" :value="dirValue" />
        <label class="block text-sm">
          <span class="mb-1 block text-[var(--eb-text-muted)]">Metric</span>
          <div class="relative" @click.away="metricOpen = false">
            <button type="button" @click="metricOpen = !metricOpen" class="eb-btn eb-btn-secondary eb-btn-sm w-full justify-between">
              <span x-text="'Metric: ' + metricLabel()"></span>
              <svg class="h-4 w-4 transition-transform" :class="metricOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
            <div x-show="metricOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 max-h-72 w-full overflow-hidden" style="display: none;">
              <div class="max-h-72 overflow-y-auto">
                {foreach from=$metric_options item=label key=value}
                  <button type="button" class="eb-menu-option" :class="metricValue === '{$value|escape:'javascript'}' ? 'is-active' : ''" @click="metricValue = '{$value|escape:'javascript'}'; metricOpen = false;">{$label|escape}</button>
                {/foreach}
              </div>
            </div>
          </div>
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-[var(--eb-text-muted)]">Status</span>
          <div class="relative" @click.away="statusOpen = false">
            <button type="button" @click="statusOpen = !statusOpen" class="eb-btn eb-btn-secondary eb-btn-sm w-full justify-between">
              <span x-text="'Status: ' + statusLabel()"></span>
              <svg class="h-4 w-4 transition-transform" :class="statusOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
            <div x-show="statusOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-full overflow-hidden" style="display: none;">
              <button type="button" class="eb-menu-option" :class="statusValue === '' ? 'is-active' : ''" @click="statusValue = ''; statusOpen = false;">All Statuses</button>
              <button type="button" class="eb-menu-option" :class="statusValue === 'over_quota' ? 'is-active' : ''" @click="statusValue = 'over_quota'; statusOpen = false;">Over Quota</button>
              <button type="button" class="eb-menu-option" :class="statusValue === 'at_risk' ? 'is-active' : ''" @click="statusValue = 'at_risk'; statusOpen = false;">At Risk</button>
              <button type="button" class="eb-menu-option" :class="statusValue === 'capped' ? 'is-active' : ''" @click="statusValue = 'capped'; statusOpen = false;">Capped</button>
              <button type="button" class="eb-menu-option" :class="statusValue === 'healthy' ? 'is-active' : ''" @click="statusValue = 'healthy'; statusOpen = false;">Healthy</button>
            </div>
          </div>
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-[var(--eb-text-muted)]">Search</span>
          <input type="text" name="q" value="{$q|default:''|escape}" placeholder="Tenant, plan, or backup user" class="eb-input" />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block text-[var(--eb-text-muted)]">Sort By</span>
          <div class="relative" @click.away="sortOpen = false">
            <button type="button" @click="sortOpen = !sortOpen" class="eb-btn eb-btn-secondary eb-btn-sm w-full justify-between">
              <span x-text="'Sort By: ' + sortLabel()"></span>
              <svg class="h-4 w-4 transition-transform" :class="sortOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
            </button>
            <div x-show="sortOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-full overflow-hidden" style="display: none;">
              <button type="button" class="eb-menu-option" :class="sortValue === 'last_push' ? 'is-active' : ''" @click="sortValue = 'last_push'; sortOpen = false;">Last Push</button>
              <button type="button" class="eb-menu-option" :class="sortValue === 'tenant' ? 'is-active' : ''" @click="sortValue = 'tenant'; sortOpen = false;">Tenant</button>
              <button type="button" class="eb-menu-option" :class="sortValue === 'plan' ? 'is-active' : ''" @click="sortValue = 'plan'; sortOpen = false;">Plan</button>
              <button type="button" class="eb-menu-option" :class="sortValue === 'metric' ? 'is-active' : ''" @click="sortValue = 'metric'; sortOpen = false;">Metric</button>
              <button type="button" class="eb-menu-option" :class="sortValue === 'included' ? 'is-active' : ''" @click="sortValue = 'included'; sortOpen = false;">Included Qty</button>
              <button type="button" class="eb-menu-option" :class="sortValue === 'used' ? 'is-active' : ''" @click="sortValue = 'used'; sortOpen = false;">Used Qty</button>
              <button type="button" class="eb-menu-option" :class="sortValue === 'status' ? 'is-active' : ''" @click="sortValue = 'status'; sortOpen = false;">Status</button>
            </div>
          </div>
        </label>
        <div class="grid grid-cols-2 gap-2 self-end">
          <label class="block text-sm">
            <span class="mb-1 block text-[var(--eb-text-muted)]">Direction</span>
            <div class="relative" @click.away="dirOpen = false">
              <button type="button" @click="dirOpen = !dirOpen" class="eb-btn eb-btn-secondary eb-btn-sm w-full justify-between">
                <span x-text="'Direction: ' + dirLabel()"></span>
                <svg class="h-4 w-4 transition-transform" :class="dirOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
              <div x-show="dirOpen" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="eb-dropdown-menu absolute left-0 z-50 mt-2 w-full overflow-hidden" style="display: none;">
                <button type="button" class="eb-menu-option" :class="dirValue === 'desc' ? 'is-active' : ''" @click="dirValue = 'desc'; dirOpen = false;">Desc</button>
                <button type="button" class="eb-menu-option" :class="dirValue === 'asc' ? 'is-active' : ''" @click="dirValue = 'asc'; dirOpen = false;">Asc</button>
              </div>
            </div>
          </label>
          <button type="submit" class="eb-btn eb-btn-primary self-end">Apply</button>
        </div>
      </form>
      <template x-if="liveMessage">
        <div class="mt-3 rounded-lg border border-slate-700 bg-slate-900/70 px-4 py-3 text-sm text-[var(--eb-text-secondary)]" x-text="liveMessage"></div>
      </template>
    </section>

    <div class="eb-subpanel mt-5">
      <div class="mb-5 flex flex-col gap-1 border-b border-[var(--eb-border-subtle)] pb-4">
        <h2 class="eb-type-h4 text-[var(--eb-text-primary)]">Latest Metered Usage</h2>
        <p class="eb-page-description">One row per active metered plan item, with the latest local usage and the most recent 10 ledger entries.</p>
      </div>

      {if $usage_rows|@count > 0}
        <div class="eb-table-shell">
          <table class="eb-table">
            <thead>
              <tr>
                <th class="px-4 py-3 text-left font-medium">Tenant</th>
                <th class="px-4 py-3 text-left font-medium">Plan</th>
                <th class="px-4 py-3 text-left font-medium">Metric</th>
                <th class="px-4 py-3 text-left font-medium">Included Qty</th>
                <th class="px-4 py-3 text-left font-medium">Used</th>
                <th class="px-4 py-3 text-left font-medium">Status</th>
                <th class="px-4 py-3 text-left font-medium">Last Push</th>
                <th class="px-4 py-3 text-left font-medium">Details</th>
              </tr>
            </thead>
            {foreach from=$usage_rows item=row}
              <tbody x-data="{ open: false }">
                <tr class="align-top">
                  <td class="px-4 py-3">
                    <a href="{$row.tenant_url|escape}" class="eb-table-primary hover:underline">{$row.tenant_name|escape}</a>
                    <div class="mt-1 font-mono text-xs text-[var(--eb-text-muted)]">{$row.comet_user_id|default:'-'|escape}</div>
                  </td>
                  <td class="px-4 py-3 text-[var(--eb-text-secondary)]">{$row.plan_name|escape}</td>
                  <td class="px-4 py-3 text-[var(--eb-text-secondary)]">{$row.metric_label|escape}</td>
                  <td class="px-4 py-3 text-[var(--eb-text-secondary)]">{$row.included_qty|escape}</td>
                  <td class="px-4 py-3">
                    <div class="font-medium text-[var(--eb-text-primary)]">{$row.used_qty|escape}</div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-800">
                      <div class="h-full {if $row.usage_pct >= 100}bg-rose-500{elseif $row.usage_pct >= 80}bg-amber-400{else}bg-emerald-400{/if}" style="width: {if $row.usage_pct > 100}100{else}{$row.usage_pct|default:0}{/if}%"></div>
                    </div>
                    <div class="mt-2 text-xs text-[var(--eb-text-muted)]">
                      {if $row.overage_qty > 0}Overage: {$row.overage_qty|escape}{else}Within included quantity{/if}
                    </div>
                  </td>
                  <td class="px-4 py-3">
                    <span class="eb-badge {if $row.status eq 'over_quota'}eb-badge--danger{elseif $row.status eq 'at_risk'}eb-badge--warning{elseif $row.status eq 'capped'}eb-badge--default{else}eb-badge--success{/if}">{$row.status_label|escape}</span>
                    <div class="mt-2 text-xs text-[var(--eb-text-muted)]">Mode: {$row.overage_mode|default:'bill_all'|escape}</div>
                  </td>
                  <td class="px-4 py-3 text-[var(--eb-text-secondary)]">
                    {if $row.last_push_at|default:'' neq ''}{$row.last_push_at|date_format:'%Y-%m-%d %H:%M'}{else}Never{/if}
                  </td>
                  <td class="px-4 py-3">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="open = !open" x-text="open ? 'Hide' : 'Show'"></button>
                  </td>
                </tr>
                <tr x-show="open" x-cloak>
                  <td colspan="8" class="px-4 py-4 bg-slate-950/30">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                      <div class="text-sm text-[var(--eb-text-secondary)]">
                        <span class="font-medium text-[var(--eb-text-primary)]">Recent usage log</span>
                        for <span class="font-mono">{$row.comet_user_id|default:'-'|escape}</span>
                      </div>
                      <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="$root.fetchStripeUsage({$row.plan_instance_item_id|escape:'javascript'})">Check Stripe</button>
                        <button type="button" class="eb-btn eb-btn-primary eb-btn-xs" @click="$root.pushUsageNow({$row.plan_instance_item_id|escape:'javascript'})">Push Usage Now</button>
                        <a href="{$row.plan_url|escape}" class="eb-btn eb-btn-secondary eb-btn-xs">Manage Plans</a>
                      </div>
                    </div>
                    {if $row.detail_logs|@count > 0}
                      <div class="mt-4 overflow-x-auto rounded-lg border border-slate-800">
                        <table class="min-w-full divide-y divide-slate-800 text-sm">
                          <thead class="bg-slate-900/80 text-slate-300">
                            <tr>
                              <th class="px-4 py-3 text-left font-medium">Window</th>
                              <th class="px-4 py-3 text-left font-medium">Qty</th>
                              <th class="px-4 py-3 text-left font-medium">Source</th>
                              <th class="px-4 py-3 text-left font-medium">Pushed</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-slate-800">
                            {foreach from=$row.detail_logs item=log}
                              <tr class="hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-[var(--eb-text-secondary)]">{$log.period_start|date_format:'%Y-%m-%d %H:%M'} to {$log.period_end|date_format:'%Y-%m-%d %H:%M'}</td>
                                <td class="px-4 py-3 font-mono text-[var(--eb-text-primary)]">{$log.qty|default:0|escape}</td>
                                <td class="px-4 py-3 text-[var(--eb-text-secondary)]">{$log.source|default:'-'|escape}</td>
                                <td class="px-4 py-3 text-[var(--eb-text-secondary)]">{if $log.pushed_to_stripe_at|default:'' neq ''}{$log.pushed_to_stripe_at|date_format:'%Y-%m-%d %H:%M'}{else}Not pushed{/if}</td>
                              </tr>
                            {/foreach}
                          </tbody>
                        </table>
                      </div>
                    {else}
                      <div class="mt-4 rounded-lg border border-dashed border-slate-700 px-4 py-6 text-sm text-[var(--eb-text-muted)]">
                        No ledger rows found yet for this metered item.
                      </div>
                    {/if}
                  </td>
                </tr>
              </tbody>
            {/foreach}
          </table>
        </div>
      {else}
        <div class="rounded-xl border border-dashed border-slate-700 px-6 py-10 text-center text-sm text-[var(--eb-text-muted)]">
          No metered usage rows found. Usage appears here after metered plans are assigned and at least one usage sample is recorded.
        </div>
      {/if}
    </div>
  </div>
{/capture}
{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='usage-dashboard'
  ebPhTitle='Usage Dashboard'
  ebPhDescription='Review metered usage, quota pressure, and the latest Stripe push activity across all tenant plans.'
  ebPhActions=$ebPhActions
  ebPhContent=$ebPhContent
}
