{include file="modules/addons/eazybackup/templates/partials/_ui-tokens.tpl"}
{capture assign=ebPhContent}
  <section
    class="eb-subpanel"
    x-data="{
      sortOpen: false,
      dirOpen: false,
      sortValue: '{$sort|default:'username'|escape:'javascript'}',
      dirValue: '{$dir|default:'asc'|escape:'javascript'}',
      sortLabel() {
        return {
          username: 'Username',
          tenant: 'Tenant',
          plan: 'Plan',
          status: 'Status',
          since: 'Since'
        }[this.sortValue] || 'Username';
      },
      dirLabel() {
        return {
          asc: 'Asc',
          desc: 'Desc'
        }[this.dirValue] || 'Asc';
      }
    }"
  >
    <form method="get" action="{$modulelink}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
      <input type="hidden" name="m" value="eazybackup" />
      <input type="hidden" name="a" value="ph-user-assignments" />
      <input type="hidden" name="sort" :value="sortValue" />
      <input type="hidden" name="dir" :value="dirValue" />
      <label class="block text-sm">
        <span class="mb-1 block text-[var(--eb-text-muted)]">Search</span>
        <input type="text" name="q" value="{$q|default:''|escape}" placeholder="Backup user, tenant, or plan" class="eb-input" />
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
            <button type="button" class="eb-menu-option" :class="sortValue === 'username' ? 'is-active' : ''" @click="sortValue = 'username'; sortOpen = false;">Username</button>
            <button type="button" class="eb-menu-option" :class="sortValue === 'tenant' ? 'is-active' : ''" @click="sortValue = 'tenant'; sortOpen = false;">Tenant</button>
            <button type="button" class="eb-menu-option" :class="sortValue === 'plan' ? 'is-active' : ''" @click="sortValue = 'plan'; sortOpen = false;">Plan</button>
            <button type="button" class="eb-menu-option" :class="sortValue === 'status' ? 'is-active' : ''" @click="sortValue = 'status'; sortOpen = false;">Status</button>
            <button type="button" class="eb-menu-option" :class="sortValue === 'since' ? 'is-active' : ''" @click="sortValue = 'since'; sortOpen = false;">Since</button>
          </div>
        </div>
      </label>
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
            <button type="button" class="eb-menu-option" :class="dirValue === 'asc' ? 'is-active' : ''" @click="dirValue = 'asc'; dirOpen = false;">Asc</button>
            <button type="button" class="eb-menu-option" :class="dirValue === 'desc' ? 'is-active' : ''" @click="dirValue = 'desc'; dirOpen = false;">Desc</button>
          </div>
        </div>
      </label>
      <div class="self-end">
        <button type="submit" class="eb-btn eb-btn-primary">Apply</button>
      </div>
    </form>
  </section>

  <div class="eb-subpanel mt-5">
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
                <td class="px-4 py-3 font-mono text-[var(--eb-text-primary)]">{$row.comet_user_display|default:$row.comet_user_id|default:'-'|escape}</td>
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
  </div>

  <div
    class="eb-subpanel mt-5"
    x-data="ebUserAssignments(
      {$assign_plans|default:array()|@json_encode|escape:'html'},
      {$assign_tenants|default:array()|@json_encode|escape:'html'},
      {$unassigned_rows|default:array()|@json_encode|escape:'html'},
      {$s3_users|default:array()|@json_encode|escape:'html'},
      '{$modulelink|escape:'javascript'}',
      '{$token|escape:'javascript'}'
    )"
  >
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3 border-b border-[var(--eb-border-subtle)] pb-4">
      <div>
        <h2 class="eb-type-h4 text-[var(--eb-text-primary)]">Unassigned Backup Users</h2>
        <p class="eb-page-description">Backup usernames linked to tenants that are not currently attached to an active billing plan.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="eb-btn eb-btn-primary eb-btn-sm"
          x-show="hasS3Plans()"
          x-cloak
          @click="openAssignModal('', '', 's3')"
          :disabled="assignPlans.length === 0 || tenantOptions().length === 0"
        >
          Assign e3 Storage Plan
        </button>
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="tableOpen = !tableOpen" x-text="tableOpen ? 'Hide' : 'Show'"></button>
      </div>
    </div>

    <div x-show="tableOpen" x-cloak>
      <div x-show="assignPlans.length === 0" class="eb-alert eb-alert--info mb-4">
        No active plans are available to assign from this screen yet.
      </div>
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
                      <button
                        type="button"
                        class="eb-btn eb-btn-primary eb-btn-xs"
                        @click="openAssignModal('{$row.tenant_public_id|escape:'javascript'}', '{$row.comet_user_id|default:$row.comet_username|escape:'javascript'}', 'comet')"
                        :disabled="assignPlans.length === 0"
                      >
                        Assign Plan
                      </button>
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

    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/70 px-4" @click.self="closeModal()">
      <div class="w-full max-w-2xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
          <div>
            <h3 class="text-lg font-semibold text-slate-100">Assign Plan</h3>
            <p class="mt-1 text-sm text-slate-400">Create a plan assignment without leaving User Assignments.</p>
          </div>
          <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="closeModal()">Close</button>
        </div>
        <div class="space-y-4 px-6 py-5">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <label class="block text-sm">
              <span class="mb-1 block text-slate-300">Customer</span>
              <div class="relative" @click.outside="tenantDropOpen = false">
                <button
                  type="button"
                  class="eb-input relative flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left"
                  @click="toggleTenantDropdown()"
                  :aria-expanded="tenantDropOpen"
                >
                  <span class="min-w-0 truncate" :class="selectedTenantId ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="selectedTenantLabel() || 'Select a customer'"></span>
                  <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="tenantDropOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                </button>
                <div
                  x-show="tenantDropOpen"
                  x-transition
                  class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                  style="display: none;"
                >
                  <div class="border-b border-[var(--eb-border-subtle)] p-2">
                    <input
                      type="search"
                      x-ref="tenantSearchInput"
                      x-model="tenantSearch"
                      placeholder="Search customers..."
                      class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm"
                      @click.stop
                    />
                  </div>
                  <div class="max-h-52 overflow-y-auto p-1">
                    <template x-for="tenant in filteredTenants()" :key="'assign-tenant-' + tenant.public_id">
                      <button
                        type="button"
                        class="eb-menu-item w-full flex-col !items-stretch gap-0.5"
                        :class="String(selectedTenantId || '') === String(tenant.public_id || '') ? 'is-active' : ''"
                        @click="selectTenant(tenant.public_id)"
                      >
                        <span class="truncate text-left font-medium" x-text="tenant.name || tenant.public_id"></span>
                        <span class="truncate text-left text-xs text-[var(--eb-text-muted)]" x-text="tenant.public_id"></span>
                      </button>
                    </template>
                    <div x-show="filteredTenants().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]" x-text="tenantSearch ? 'No customers match your search.' : 'No customers are available from the unassigned list.'"></div>
                  </div>
                </div>
              </div>
            </label>

            <label class="block text-sm">
              <span class="mb-1 block text-slate-300">Plan</span>
              <div class="relative" @click.outside="planDropOpen = false">
                <button
                  type="button"
                  class="eb-input relative flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left"
                  @click="togglePlanDropdown()"
                  :aria-expanded="planDropOpen"
                  x-ref="planTrigger"
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
                    <input
                      type="search"
                      x-ref="planSearchInput"
                      x-model="planSearch"
                      placeholder="Search plans..."
                      class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm"
                      @click.stop
                    />
                  </div>
                  <div class="max-h-52 overflow-y-auto p-1">
                    <template x-for="plan in filteredPlans()" :key="'assign-plan-' + String(plan.id || '')">
                      <button
                        type="button"
                        class="eb-menu-item w-full flex-col !items-stretch gap-0.5"
                        :class="String(selectedPlanId || '') === String(plan.id || '') ? 'is-active' : ''"
                        @click="selectPlan(plan.id)"
                      >
                        <span class="truncate text-left font-medium" x-text="plan.name || ('Plan #' + String(plan.id || ''))"></span>
                        <span class="truncate text-left text-xs text-[var(--eb-text-muted)]" x-text="planAssignmentHint(plan)"></span>
                      </button>
                    </template>
                    <div x-show="filteredPlans().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No matching plans.</div>
                  </div>
                </div>
              </div>
            </label>
          </div>

          <template x-if="selectedPlan()">
            <div class="eb-card !p-4">
              <div class="font-medium text-slate-100" x-text="selectedPlan().name || 'Selected plan'"></div>
              <div class="mt-1 text-sm text-[var(--eb-text-muted)]" x-text="planAssignmentHint(selectedPlan())"></div>
            </div>
          </template>

          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div x-show="requiresCometUser()">
              <label class="block text-sm">
                <span class="mb-1 block text-slate-300">eazyBackup User</span>
                <div class="relative" @click.outside="cometDropOpen = false">
                  <button
                    type="button"
                    class="eb-input relative flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left disabled:cursor-not-allowed disabled:opacity-50"
                    @click="selectedTenantId && toggleCometDropdown()"
                    :disabled="!selectedTenantId"
                    :aria-expanded="cometDropOpen"
                  >
                    <span class="min-w-0 truncate" :class="selectedCometUserId ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="selectedCometUserLabel()"></span>
                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="cometDropOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                  </button>
                  <div
                    x-show="cometDropOpen"
                    x-transition
                    class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                    style="display: none;"
                  >
                    <div class="border-b border-[var(--eb-border-subtle)] p-2">
                      <input
                        type="search"
                        x-ref="cometSearchInput"
                        x-model="cometSearch"
                        placeholder="Search users..."
                        class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm"
                        @click.stop
                      />
                    </div>
                    <div class="max-h-52 overflow-y-auto p-1">
                      <template x-for="user in filteredCometUsers()" :key="'assign-comet-user-' + String(user.identifier || '')">
                        <button
                          type="button"
                          class="eb-menu-item w-full flex-col !items-stretch gap-0.5"
                          :class="String(selectedCometUserId || '') === String(user.identifier || '') ? 'is-active' : ''"
                          @click="selectCometUser(user.identifier)"
                        >
                          <span class="truncate text-left font-medium" x-text="user.label || user.identifier"></span>
                          <span class="truncate text-left text-xs text-[var(--eb-text-muted)]" x-text="user.tenant_name || user.tenant_public_id || ''"></span>
                        </button>
                      </template>
                      <div x-show="!selectedTenantId" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">Select a customer to see users.</div>
                      <div x-show="selectedTenantId && availableCometUsers().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No eazyBackup users are linked to this customer.</div>
                      <div x-show="selectedTenantId && availableCometUsers().length > 0 && filteredCometUsers().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]">No users match your search.</div>
                    </div>
                  </div>
                </div>
              </label>
            </div>

            <div class="relative min-w-0" x-show="requiresS3User()" @click.outside="s3DropOpen = false">
              <span class="mb-1 block text-sm text-slate-300">S3 User</span>
              <button
                type="button"
                class="eb-input relative flex w-full cursor-pointer items-center justify-between gap-2 pr-10 text-left"
                @click="toggleS3Dropdown()"
                :aria-expanded="s3DropOpen"
              >
                <span class="min-w-0 truncate" :class="selectedS3UserId ? 'text-[var(--eb-text-primary)]' : 'text-[var(--eb-text-muted)]'" x-text="selectedS3UserLabel()"></span>
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 shrink-0 -translate-y-1/2 text-[var(--eb-text-muted)] transition-transform" :class="s3DropOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
              </button>
              <p class="mt-2 text-xs text-[var(--eb-text-muted)]">Choose the MSP-owned S3 user that should back this storage subscription.</p>
              <div
                x-show="s3DropOpen"
                x-transition
                class="absolute left-0 right-0 z-[100] mt-2 max-h-72 overflow-hidden rounded-[var(--eb-radius-lg)] border border-[var(--eb-border-default)] bg-[var(--eb-bg-raised)] shadow-[var(--eb-shadow-lg)]"
                style="display: none;"
              >
                <div class="border-b border-[var(--eb-border-subtle)] p-2">
                  <input
                    type="search"
                    x-ref="s3SearchInput"
                    x-model="s3Search"
                    placeholder="Search S3 users..."
                    class="eb-toolbar-search w-full rounded-[var(--eb-radius-md)] !py-2 text-sm"
                    @click.stop
                  />
                </div>
                <div class="max-h-52 overflow-y-auto p-1">
                  <template x-for="user in filteredS3Users()" :key="'assign-s3-user-' + user.id">
                    <button
                      type="button"
                      class="eb-menu-item w-full flex-col !items-stretch gap-0.5"
                      :class="String(selectedS3UserId || '') === String(user.id || '') ? 'is-active' : ''"
                      @click="selectS3User(user.id)"
                    >
                      <span class="truncate text-left font-medium" x-text="user.display_label || user.name || user.username || ('S3 user #' + user.id)"></span>
                      <span class="truncate text-left text-xs text-[var(--eb-text-muted)]" x-text="'ID ' + user.id"></span>
                    </button>
                  </template>
                  <div x-show="filteredS3Users().length === 0" class="px-3 py-4 text-center text-xs text-[var(--eb-text-muted)]" x-text="s3Search ? 'No S3 users match your search.' : 'No S3 users are available for this MSP.'"></div>
                </div>
              </div>
            </div>
          </div>

          <template x-if="message">
            <div class="eb-alert eb-alert--danger !mb-0" x-text="message"></div>
          </template>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-slate-800 px-6 py-4">
          <button type="button" class="eb-btn eb-btn-secondary" @click="closeModal()">Cancel</button>
          <button type="button" class="eb-btn eb-btn-primary" :disabled="saving || assignPlans.length === 0" @click="submit()" x-text="saving ? 'Assigning...' : 'Assign Plan'"></button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function ebUserAssignments(assignPlans, assignTenants, unassignedRows, s3Users, modulelink, token) {
      return {
        assignPlans: Array.isArray(assignPlans) ? assignPlans : [],
        assignTenants: Array.isArray(assignTenants) ? assignTenants : [],
        unassignedRows: Array.isArray(unassignedRows) ? unassignedRows : [],
        s3Users: Array.isArray(s3Users) ? s3Users : [],
        modulelink: modulelink || '',
        token: token || '',
        tableOpen: true,
        modalOpen: false,
        saving: false,
        message: '',
        selectedTenantId: '',
        selectedPlanId: '',
        selectedCometUserId: '',
        selectedS3UserId: '',
        tenantDropOpen: false,
        planDropOpen: false,
        cometDropOpen: false,
        s3DropOpen: false,
        tenantSearch: '',
        planSearch: '',
        cometSearch: '',
        s3Search: '',

        assignmentMode(plan) {
          return plan && plan.assignment_mode ? plan.assignment_mode : {};
        },

        selectedPlan() {
          var planId = String(this.selectedPlanId || '');
          for (var i = 0; i < this.assignPlans.length; i++) {
            if (String(this.assignPlans[i].id || '') === planId) {
              return this.assignPlans[i];
            }
          }
          return null;
        },

        hasS3Plans() {
          for (var i = 0; i < this.assignPlans.length; i++) {
            if (this.assignmentMode(this.assignPlans[i]).requires_s3_user) {
              return true;
            }
          }
          return false;
        },

        requiresCometUser() {
          var mode = this.assignmentMode(this.selectedPlan());
          return mode.requires_comet_user === undefined ? true : !!mode.requires_comet_user;
        },

        requiresS3User() {
          var mode = this.assignmentMode(this.selectedPlan());
          return !!mode.requires_s3_user;
        },

        planAssignmentHint(plan) {
          var mode = this.assignmentMode(plan);
          if (mode.requires_comet_user && mode.requires_s3_user) {
            return 'Requires both an eazyBackup user and an S3 user.';
          }
          if (mode.requires_s3_user) {
            return 'Requires an MSP-owned S3 user.';
          }
          if (mode.requires_comet_user === false) {
            return 'Tenant-level storage assignment.';
          }
          return 'Requires an eazyBackup user.';
        },

        tenantOptions() {
          var sourceTenants = Array.isArray(this.assignTenants) && this.assignTenants.length ? this.assignTenants : null;
          var seen = {};
          var tenants = [];
          var i;
          if (sourceTenants) {
            for (i = 0; i < sourceTenants.length; i++) {
              var tenant = sourceTenants[i] || {};
              var tenantPublicId = String(tenant.public_id || tenant.tenant_public_id || '').trim();
              if (!tenantPublicId || seen[tenantPublicId]) {
                continue;
              }
              seen[tenantPublicId] = true;
              tenants.push({
                public_id: tenantPublicId,
                name: String(tenant.name || tenant.tenant_name || '').trim() || tenantPublicId
              });
            }
          }
          for (i = 0; !sourceTenants && i < this.unassignedRows.length; i++) {
            var row = this.unassignedRows[i] || {};
            var publicId = String(row.tenant_public_id || '').trim();
            if (!publicId || seen[publicId]) {
              continue;
            }
            seen[publicId] = true;
            tenants.push({
              public_id: publicId,
              name: String(row.tenant_name || '').trim() || publicId
            });
          }
          tenants.sort(function (left, right) {
            return String(left.name || '').localeCompare(String(right.name || ''));
          });
          return tenants;
        },

        filteredTenants() {
          var query = String(this.tenantSearch || '').trim().toLowerCase();
          var tenants = this.tenantOptions();
          if (!query) {
            return tenants;
          }
          return tenants.filter(function (tenant) {
            return String(tenant.name || '').toLowerCase().indexOf(query) >= 0
              || String(tenant.public_id || '').toLowerCase().indexOf(query) >= 0;
          });
        },

        selectedTenantLabel() {
          var tenantId = String(this.selectedTenantId || '');
          var tenants = this.tenantOptions();
          for (var i = 0; i < tenants.length; i++) {
            if (String(tenants[i].public_id || '') === tenantId) {
              return tenants[i].name || tenantId;
            }
          }
          return '';
        },

        availableCometUsers() {
          var tenantId = String(this.selectedTenantId || '');
          var seen = {};
          var users = [];
          for (var i = 0; i < this.unassignedRows.length; i++) {
            var row = this.unassignedRows[i] || {};
            if (tenantId && String(row.tenant_public_id || '') !== tenantId) {
              continue;
            }
            var identifier = String(row.comet_user_id || row.comet_username || '').trim();
            if (!identifier || seen[identifier]) {
              continue;
            }
            seen[identifier] = true;
            users.push({
              identifier: identifier,
              label: String(row.comet_username || row.comet_user_id || identifier).trim() || identifier,
              tenant_name: String(row.tenant_name || '').trim(),
              tenant_public_id: String(row.tenant_public_id || '').trim()
            });
          }
          return users;
        },

        filteredCometUsers() {
          var query = String(this.cometSearch || '').trim().toLowerCase();
          var users = this.availableCometUsers();
          if (!query) {
            return users;
          }
          return users.filter(function (user) {
            return String(user.label || '').toLowerCase().indexOf(query) >= 0
              || String(user.identifier || '').toLowerCase().indexOf(query) >= 0
              || String(user.tenant_name || '').toLowerCase().indexOf(query) >= 0;
          });
        },

        filteredPlans() {
          var query = String(this.planSearch || '').trim().toLowerCase();
          if (!query) {
            return this.assignPlans;
          }
          return this.assignPlans.filter(function (plan) {
            return String(plan.name || '').toLowerCase().indexOf(query) >= 0
              || String(plan.id || '').toLowerCase().indexOf(query) >= 0;
          });
        },

        filteredS3Users() {
          var query = String(this.s3Search || '').trim().toLowerCase();
          if (!query) {
            return this.s3Users;
          }
          return this.s3Users.filter(function (user) {
            return String(user.display_label || '').toLowerCase().indexOf(query) >= 0
              || String(user.username || '').toLowerCase().indexOf(query) >= 0
              || String(user.name || '').toLowerCase().indexOf(query) >= 0
              || String(user.id || '').toLowerCase().indexOf(query) >= 0;
          });
        },

        selectedPlanLabel() {
          var plan = this.selectedPlan();
          if (!plan) {
            return 'Select a plan';
          }
          return String(plan.name || '').trim() || ('Plan #' + String(plan.id || ''));
        },

        selectedCometUserLabel() {
          if (!this.selectedTenantId) {
            return 'Select a customer first';
          }
          if (!this.selectedCometUserId) {
            return 'Select eazyBackup user';
          }
          var users = this.availableCometUsers();
          var identifier = String(this.selectedCometUserId || '');
          for (var i = 0; i < users.length; i++) {
            if (String(users[i].identifier || '') === identifier) {
              return users[i].label || users[i].identifier;
            }
          }
          return identifier;
        },

        selectedS3UserLabel() {
          if (!this.selectedS3UserId) {
            return 'Select S3 user';
          }
          var selectedId = String(this.selectedS3UserId || '');
          for (var i = 0; i < this.s3Users.length; i++) {
            if (String(this.s3Users[i].id || '') === selectedId) {
              return this.s3Users[i].display_label || this.s3Users[i].name || this.s3Users[i].username || ('S3 user #' + selectedId);
            }
          }
          return 'S3 user #' + selectedId;
        },

        closeDropdowns() {
          this.tenantDropOpen = false;
          this.planDropOpen = false;
          this.cometDropOpen = false;
          this.s3DropOpen = false;
        },

        focusRef(refName) {
          var self = this;
          this.$nextTick(function () {
            var el = self.$refs[refName];
            if (el) {
              el.focus();
            }
          });
        },

        toggleTenantDropdown() {
          this.planDropOpen = false;
          this.cometDropOpen = false;
          this.s3DropOpen = false;
          this.tenantDropOpen = !this.tenantDropOpen;
          if (this.tenantDropOpen) {
            this.focusRef('tenantSearchInput');
          }
        },

        togglePlanDropdown() {
          this.tenantDropOpen = false;
          this.cometDropOpen = false;
          this.s3DropOpen = false;
          this.planDropOpen = !this.planDropOpen;
          if (this.planDropOpen) {
            this.focusRef('planSearchInput');
          }
        },

        toggleCometDropdown() {
          this.tenantDropOpen = false;
          this.planDropOpen = false;
          this.s3DropOpen = false;
          this.cometDropOpen = !this.cometDropOpen;
          if (this.cometDropOpen) {
            this.focusRef('cometSearchInput');
          }
        },

        toggleS3Dropdown() {
          this.tenantDropOpen = false;
          this.planDropOpen = false;
          this.cometDropOpen = false;
          this.s3DropOpen = !this.s3DropOpen;
          if (this.s3DropOpen) {
            this.focusRef('s3SearchInput');
          }
        },

        defaultPlanId(preferredMode) {
          var preferred = String(preferredMode || '');
          var i;
          if (preferred === 's3') {
            for (i = 0; i < this.assignPlans.length; i++) {
              if (this.assignmentMode(this.assignPlans[i]).requires_s3_user) {
                return String(this.assignPlans[i].id || '');
              }
            }
          }
          if (preferred === 'comet') {
            for (i = 0; i < this.assignPlans.length; i++) {
              if (this.assignmentMode(this.assignPlans[i]).requires_comet_user !== false) {
                return String(this.assignPlans[i].id || '');
              }
            }
          }
          return this.assignPlans.length ? String(this.assignPlans[0].id || '') : '';
        },

        syncSelectionsForPlan() {
          if (!this.requiresCometUser()) {
            this.selectedCometUserId = '';
          } else {
            var availableUsers = this.availableCometUsers();
            var hasSelectedCometUser = false;
            for (var i = 0; i < availableUsers.length; i++) {
              if (String(availableUsers[i].identifier || '') === String(this.selectedCometUserId || '')) {
                hasSelectedCometUser = true;
                break;
              }
            }
            if (!hasSelectedCometUser) {
              this.selectedCometUserId = availableUsers.length === 1 ? String(availableUsers[0].identifier || '') : '';
            }
          }
          if (!this.requiresS3User()) {
            this.selectedS3UserId = '';
          }
        },

        selectTenant(publicId) {
          this.selectedTenantId = publicId ? String(publicId) : '';
          this.tenantDropOpen = false;
          this.tenantSearch = '';
          this.syncSelectionsForPlan();
        },

        selectPlan(planId) {
          this.selectedPlanId = planId ? String(planId) : '';
          this.planDropOpen = false;
          this.planSearch = '';
          this.syncSelectionsForPlan();
        },

        selectCometUser(cometUserId) {
          this.selectedCometUserId = cometUserId ? String(cometUserId) : '';
          this.cometDropOpen = false;
          this.cometSearch = '';
        },

        selectS3User(id) {
          this.selectedS3UserId = id ? String(id) : '';
          this.s3DropOpen = false;
          this.s3Search = '';
        },

        openAssignModal(tenantPublicId, cometUserId, preferredMode) {
          this.modalOpen = true;
          this.message = '';
          this.saving = false;
          this.closeDropdowns();
          this.tenantSearch = '';
          this.planSearch = '';
          this.cometSearch = '';
          this.s3Search = '';
          this.selectedTenantId = tenantPublicId ? String(tenantPublicId) : '';
          this.selectedPlanId = this.defaultPlanId(preferredMode);
          this.selectedCometUserId = '';
          this.selectedS3UserId = '';

          if (this.requiresCometUser()) {
            var presetCometUserId = cometUserId ? String(cometUserId) : '';
            if (presetCometUserId) {
              this.selectedCometUserId = presetCometUserId;
            } else {
              this.syncSelectionsForPlan();
            }
          }

          this.focusRef('planTrigger');
        },

        closeModal() {
          this.modalOpen = false;
          this.message = '';
          this.saving = false;
          this.closeDropdowns();
        },

        async submit() {
          if (!this.selectedPlanId) {
            this.message = 'Select a plan first.';
            return;
          }
          if (!this.selectedTenantId) {
            this.message = 'Select a customer first.';
            return;
          }
          if (this.requiresCometUser() && !this.selectedCometUserId) {
            this.message = 'Select an eazyBackup user first.';
            return;
          }
          if (this.requiresS3User() && !this.selectedS3UserId) {
            this.message = 'Select an S3 user first.';
            return;
          }

          this.saving = true;
          this.message = '';

          try {
            var payload = new URLSearchParams({
              plan_id: String(this.selectedPlanId || ''),
              tenant_id: String(this.selectedTenantId || ''),
              token: this.token
            });

            if (this.requiresCometUser() && this.selectedCometUserId) {
              payload.set('comet_user_id', String(this.selectedCometUserId));
            }
            if (this.requiresS3User() && this.selectedS3UserId) {
              payload.set('s3_user_id', String(this.selectedS3UserId));
            }

            var res = await fetch(this.modulelink + '&a=ph-plan-assign', {
              method: 'POST',
              credentials: 'same-origin',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
              body: payload.toString()
            });
            var data = await res.json();
            if (data.status === 'success') {
              window.location.reload();
              return;
            }
            this.message = data.message || 'Unable to assign plan.';
          } catch (error) {
            this.message = 'Unable to assign plan.';
          } finally {
            this.saving = false;
          }
        }
      };
    }
  </script>
{/capture}
{include file="modules/addons/eazybackup/templates/whitelabel/partials/partner_hub_shell.tpl"
  ebPhSidebarPage='user-assignments'
  ebPhTitle='User Assignments'
  ebPhDescription='Review which backup usernames are attached to active plans and which tenant-linked users still need a billing assignment.'
  ebPhContent=$ebPhContent
}
