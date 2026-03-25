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

  <section class="eb-card-raised mt-5"
    x-data="{
      open: true,
      modalOpen: false,
      saving: false,
      message: '',
      selectedUser: '',
      selectedTenantId: '',
      selectedPlanId: '',
      tenantDropOpen: false,
      tenantSearch: '',
      planDropOpen: false,
      planSearch: '',
      tenants: {$assign_tenants|default:array()|@json_encode|escape:'html'},
      plans: {$assign_plans|default:array()|@json_encode|escape:'html'},
      token: '{$token|default:''|escape:'javascript'}',
      modulelink: '{$modulelink|escape:'javascript'}',

      openModal(cometUserId, tenantPublicId) {
        this.selectedUser = String(cometUserId || '');
        this.selectedTenantId = String(tenantPublicId || '');
        this.selectedPlanId = this.plans.length ? String(this.plans[0].id || '') : '';
        this.message = '';
        this.saving = false;
        this.tenantDropOpen = false;
        this.tenantSearch = '';
        this.planDropOpen = false;
        this.planSearch = '';
        this.modalOpen = true;
      },

      selectedPlan() {
        return this.plans.find(p => String(p.id || '') === String(this.selectedPlanId || '')) || null;
      },
      requiresCometUser() {
        const plan = this.selectedPlan();
        return plan ? !!plan.requires_comet_user : true;
      },
      selectedPlanLabel() {
        const plan = this.selectedPlan();
        if (!plan) return 'Select a plan';
        return String(plan.name || '').trim() || ('Plan #' + String(plan.id || ''));
      },
      selectedTenantLabel() {
        if (!this.selectedTenantId) return 'Select a customer';
        const t = this.tenants.find(t => String(t.public_id || '') === this.selectedTenantId);
        return t ? String(t.name || '').trim() || t.public_id : this.selectedTenantId;
      },
      filteredTenants() {
        const q = String(this.tenantSearch || '').trim().toLowerCase();
        if (!q) return this.tenants;
        return this.tenants.filter(t =>
          String(t.name || '').toLowerCase().includes(q) ||
          String(t.public_id || '').toLowerCase().includes(q)
        );
      },
      filteredPlans() {
        const q = String(this.planSearch || '').trim().toLowerCase();
        if (!q) return this.plans;
        return this.plans.filter(p =>
          String(p.name || '').toLowerCase().includes(q) ||
          String(p.description || '').toLowerCase().includes(q)
        );
      },
      selectTenant(publicId) {
        this.selectedTenantId = String(publicId || '');
        this.tenantDropOpen = false;
        this.tenantSearch = '';
      },
      selectPlan(planId) {
        this.selectedPlanId = String(planId || '');
        this.planDropOpen = false;
        this.planSearch = '';
      },

      async submit() {
        if (!this.selectedTenantId) { this.message = 'Select a customer first.'; return; }
        if (!this.selectedPlanId) { this.message = 'Select a plan first.'; return; }
        if (this.requiresCometUser() && !this.selectedUser) { this.message = 'No backup user selected.'; return; }
        this.saving = true;
        this.message = '';
        try {
          const payload = new URLSearchParams({
            plan_id: this.selectedPlanId,
            tenant_id: this.selectedTenantId,
            comet_user_id: this.requiresCometUser() ? this.selectedUser : '',
            token: this.token
          });
          const res = await fetch(this.modulelink + '&a=ph-plan-assign', {
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
          let msg = data.message || 'Unable to assign plan.';
          if (msg.toLowerCase().includes('no attached payment source') || msg.toLowerCase().includes('default payment method')) {
            msg = 'This customer does not have a payment method on file. Add one on the tenant\u2019s Billing tab, then try again.';
          }
          this.message = msg;
        } catch (error) {
          this.message = 'Unable to assign plan.';
        } finally {
          this.saving = false;
        }
      }
    }"
  >
    <div class="mb-5 flex items-center justify-between gap-3 border-b border-[var(--eb-border-subtle)] pb-4">
      <div>
        <h2 class="eb-type-h4 text-[var(--eb-text-primary)]">Unassigned Backup Users</h2>
        <p class="eb-page-description">Backup usernames not currently attached to an active billing plan.</p>
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
                    {if $row.tenant_name|default:'' neq ''}
                      <a href="{$row.tenant_url|escape}" class="font-medium text-[var(--eb-text-primary)] hover:underline">{$row.tenant_name|escape}</a>
                    {else}
                      <span class="text-[var(--eb-text-muted)]">Not linked to a tenant</span>
                    {/if}
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                      {if $row.tenant_public_id|default:'' neq ''}
                        <a href="{$row.tenant_url|escape}" class="eb-btn eb-btn-secondary eb-btn-xs">Tenant</a>
                      {/if}
                      <button type="button" class="eb-btn eb-btn-primary eb-btn-xs" @click="openModal('{$row.comet_user_id|default:$row.comet_username|default:''|escape:'javascript'}', '{$row.tenant_public_id|default:''|escape:'javascript'}')">Assign Plan</button>
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

    {* ── Assign Plan Modal ── *}
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/70 px-4" @click.self="modalOpen = false">
      <div class="w-full max-w-lg rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl" @click.stop>
        <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h3 class="text-lg font-semibold text-slate-100">Assign Plan</h3>
            <p class="mt-1 text-sm text-slate-400">Create a billing subscription for this backup user.</p>
          </div>
          <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="modalOpen = false">Close</button>
        </div>

        <div class="space-y-4 px-6 py-5">

          {* Backup User (read-only) *}
          <label class="block text-sm">
            <span class="mb-1 block text-slate-300">Backup User</span>
            <input type="text" :value="selectedUser" readonly class="eb-input w-full cursor-default font-mono opacity-90" />
          </label>

          {* Customer Tenant picker *}
          <label class="block text-sm">
            <span class="mb-1 block text-slate-300">Customer</span>
            <div class="relative" @click.outside="tenantDropOpen = false">
              <button
                type="button"
                class="eb-input relative flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left"
                @click="tenantDropOpen = !tenantDropOpen; if (tenantDropOpen) planDropOpen = false;"
                :aria-expanded="tenantDropOpen"
              >
                <span class="min-w-0 truncate" :class="selectedTenantId ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="selectedTenantLabel()"></span>
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="tenantDropOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
              </button>
              <div
                x-show="tenantDropOpen"
                x-transition
                class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                style="display: none;"
              >
                <div class="border-b border-[var(--eb-border-subtle)] p-2">
                  <input type="search" x-model="tenantSearch" placeholder="Search customers..." class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm" @click.stop />
                </div>
                <div class="max-h-52 overflow-y-auto p-1">
                  <template x-for="t in filteredTenants()" :key="'ua-tenant-' + t.public_id">
                    <button
                      type="button"
                      class="eb-menu-item w-full"
                      :class="selectedTenantId === String(t.public_id || '') ? 'is-active' : ''"
                      @click="selectTenant(t.public_id)"
                    >
                      <span class="min-w-0 flex-1 truncate text-left" x-text="(t.name && t.name.trim()) ? t.name : t.public_id"></span>
                    </button>
                  </template>
                  <div x-show="filteredTenants().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No matching customers.</div>
                </div>
              </div>
            </div>
          </label>

          {* Plan picker *}
          <label class="block text-sm">
            <span class="mb-1 block text-slate-300">Plan</span>
            <div class="relative" @click.outside="planDropOpen = false">
              <button
                type="button"
                class="eb-input relative flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left"
                @click="planDropOpen = !planDropOpen; if (planDropOpen) tenantDropOpen = false;"
                :aria-expanded="planDropOpen"
              >
                <span class="min-w-0 truncate" :class="selectedPlanId ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="selectedPlanLabel()"></span>
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="planDropOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
              </button>
              <div
                x-show="planDropOpen"
                x-transition
                class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                style="display: none;"
              >
                <div class="border-b border-[var(--eb-border-subtle)] p-2">
                  <input type="search" x-model="planSearch" placeholder="Search plans..." class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm" @click.stop />
                </div>
                <div class="max-h-52 overflow-y-auto p-1">
                  <template x-for="p in filteredPlans()" :key="'ua-plan-' + p.id">
                    <button
                      type="button"
                      class="eb-menu-item w-full flex-col !items-stretch gap-0.5"
                      :class="String(selectedPlanId || '') === String(p.id || '') ? 'is-active' : ''"
                      @click="selectPlan(p.id)"
                    >
                      <span class="min-w-0 truncate text-left font-medium" x-text="p.name || ('Plan #' + p.id)"></span>
                      <span class="truncate text-left text-xs text-[var(--eb-text-muted)]" x-text="p.description || ''"></span>
                    </button>
                  </template>
                  <div x-show="filteredPlans().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No matching plans.</div>
                </div>
              </div>
            </div>
          </label>

          {* Plan summary *}
          <template x-if="selectedPlan()">
            <div class="eb-card !p-4">
              <div class="font-medium text-slate-100" x-text="selectedPlan().name || 'Selected plan'"></div>
              <div class="mt-1 text-sm text-slate-400" x-text="selectedPlan().description || 'No description provided.'"></div>
            </div>
          </template>

          {* Error message *}
          <template x-if="message">
            <div class="eb-alert eb-alert--danger !mb-0" x-text="message"></div>
          </template>

        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-800 px-6 py-4">
          <button type="button" class="eb-btn eb-btn-secondary" @click="modalOpen = false">Cancel</button>
          <button type="button" class="eb-btn eb-btn-primary" :disabled="saving" @click="submit()" x-text="saving ? 'Assigning...' : 'Assign Plan'"></button>
        </div>
      </div>
    </div>

  </section>
{/capture}
{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='user-assignments'
  ebPhTitle='User Assignments'
  ebPhDescription='Review which backup usernames are attached to active plans and which tenant-linked users still need a billing assignment.'
  ebPhContent=$ebPhContent
}
