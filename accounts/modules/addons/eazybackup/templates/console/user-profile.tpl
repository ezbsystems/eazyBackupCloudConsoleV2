{**
 * eazyBackup User Profile
 *
 * @copyright Copyright (c) eazyBackup Systems Ltd. 2024
 * @license https://www.eazybackup.com/terms/eula
 *}

{literal}
<style>
  [x-cloak] { display: none !important; }
  /* Hide native spinners (Chrome/Edge/Safari) */
  input[type="number"].no-native-spin::-webkit-outer-spin-button,
  input[type="number"].no-native-spin::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }
  /* Hide native spinners (Firefox) */
  input[type="number"].no-native-spin {
    -moz-appearance: textfield;
  }
  
  /* Global dark slim scrollbar (Chrome/Edge/Safari) */
  ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }
  ::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.6);
  }
  ::-webkit-scrollbar-thumb {
    background: rgba(51, 65, 85, 0.8);
    border-radius: 4px;
  }
  ::-webkit-scrollbar-thumb:hover {
    background: rgba(71, 85, 105, 0.9);
  }
  ::-webkit-scrollbar-corner {
    background: rgba(15, 23, 42, 0.6);
  }
  
  /* Firefox global scrollbar */
  * {
    scrollbar-width: thin;
    scrollbar-color: rgba(51, 65, 85, 0.8) rgba(15, 23, 42, 0.6);
  }
  
  /* Dark slim scrollbar for tables (narrower) */
  .table-scroll::-webkit-scrollbar {
    height: 6px;
    width: 6px;
  }
  .table-scroll::-webkit-scrollbar-track {
    background: rgba(30, 41, 59, 0.5);
    border-radius: 3px;
  }
  .table-scroll::-webkit-scrollbar-thumb {
    background: rgba(71, 85, 105, 0.8);
    border-radius: 3px;
  }
  .table-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 116, 139, 0.9);
  }
  /* Firefox */
  .table-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(71, 85, 105, 0.8) rgba(30, 41, 59, 0.5);
  }
</style>
{/literal}

<div class="eb-page">
  <!-- Global nebula background -->
  {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}

  <div class="eb-page-inner">
    <!-- App Shell with Sidebar -->
    <div x-data="{ 
      activeSubTab: 'profile', 
      sidebarOpen: true,
      sidebarCollapsed: localStorage.getItem('eb_sidebar_collapsed') === 'true' || window.innerWidth < 1360,
      toggleCollapse() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('eb_sidebar_collapsed', this.sidebarCollapsed);
      },
      handleResize() {
        if (window.innerWidth < 1360 && !this.sidebarCollapsed) {
          this.sidebarCollapsed = true;
        }
      }
    }" 
    x-init="window.addEventListener('resize', () => handleResize())"
    class="eb-panel !p-0">
      
      <div class="eb-app-shell">
        {include file="modules/addons/eazybackup/templates/clientarea/partials/sidebar.tpl" ebSidebarPage='user-profile'}
        
        <!-- Main Content Area -->
        <main class="eb-app-main">
          <!-- Content Header -->
          <div class="eb-app-header">
            <div class="eb-app-header-copy">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="eb-app-header-icon h-6 w-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
              </svg>
              <h1 class="eb-app-header-title">{$username}</h1>
            </div>
            
            <!-- Actions dropdown -->
            <div class="relative" x-data="{ open:false }" @keydown.escape.window="open=false" @click.away="open=false">
              <button type="button" class="eb-app-toolbar-button" @click="open = !open">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                Actions
                <svg class="ml-1.5 h-4 w-4 opacity-60" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
              </button>
              <div x-show="open" x-cloak x-transition class="eb-menu absolute right-0 z-10 mt-2 w-56 overflow-hidden">
                <ul class="p-1 text-sm text-[var(--eb-text-primary)]">
                  <li>
                    <a href="#" class="eb-menu-item" data-action="reset-password" data-username="{$username}" data-serviceid="{$serviceid}" @click.prevent="open=false; $dispatch('eb-reset-password', { username: '{$username}', serviceid: '{$serviceid}' })">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="mr-2 h-4 w-4 text-[var(--eb-text-muted)]">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                      </svg>
                      <span>Reset password</span>
                    </a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          
          <!-- Tab Content -->
          <div class="eb-app-body">
      
      <div x-show="activeSubTab === 'profile'" x-transition>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
          <div class="eb-app-card xl:col-span-2">
            <h3 class="eb-app-card-title mb-4">User Details</h3>
            <div class="eb-kv-list text-sm">
              <div class="eb-kv-row">
                  <span class="eb-kv-label">Username:</span>
                  <span class="eb-kv-value font-mono">{$username}</span>
              </div>
              <!-- Account Name editable row -->
              <div x-data="accNameCtrl()" x-init="await init()" class="flex items-center justify-between gap-3">
                <label for="eb-account-name" class="eb-kv-label shrink-0">Account Name:</label>
                <div class="flex grow items-center gap-2">
                  <input
                    id="eb-account-name"
                    type="text"
                    x-model="name"
                    placeholder="Optional descriptive name"
                    class="eb-input h-8 w-full px-2"
                  />
                  <button type="button" @click="await save()" :disabled="saving" class="eb-btn eb-btn-primary eb-btn-xs shrink-0">Update</button>
                </div>
              </div>
              <div class="eb-kv-row">
                  <span class="eb-kv-label">Password:</span>
                  <span class="eb-kv-value">Hashed with 448-bit bcrypt</span>
              </div>
              <div class="eb-kv-row">
                  <span class="eb-kv-label">Created:</span>
                  <span class="eb-kv-value font-mono">{$createdDate}</span>
              </div>
              <div class="eb-kv-row items-center">
                <div>
                  <span class="eb-kv-label mr-2">TOTP:</span>
                  {if $totpStatus == 'Active'}
                      <span class="inline-flex items-center gap-2 text-[var(--eb-success-text)]"><span class="eb-status-dot eb-status-dot--active"></span>{$totpStatus}</span>
                  {else}
                      <span class="inline-flex items-center gap-2 text-[var(--eb-danger-text)]"><span class="eb-status-dot eb-status-dot--error"></span>{$totpStatus}</span>
                  {/if}
                </div>
                <div class="flex items-center space-x-2">
                    <button id="totp-regenerate" class="eb-btn eb-btn-primary eb-btn-xs">{if $totpStatus == 'Active'}Regenerate QR{else}Enable TOTP{/if}</button>
                    {if $totpStatus == 'Active'}
                    <button id="totp-disable" class="eb-btn eb-btn-danger eb-btn-xs">Disable</button>
                    {/if}
                </div>
              </div>
              <div class="eb-kv-row">
                <span class="eb-kv-label">Number of devices:</span>
                <span class="eb-kv-value">{if $devices}{$devices|count}{else}0{/if}</span>
              </div>
              <div class="eb-kv-row">
                  <span class="eb-kv-label">Office 365 protected accounts:</span>
                  <span class="eb-kv-value">{$msAccountCount}</span>
              </div>
              <div class="eb-kv-row">
                <span class="eb-kv-label">Number of Hyper-V VMs:</span>
                <span class="eb-kv-value">{$hvGuestCount|default:0}</span>
              </div>
              <div class="eb-kv-row">
                <span class="eb-kv-label">Number of VMware VMs:</span>
                <span class="eb-kv-value">{$vmwGuestCount|default:0}</span>
              </div>
              <!-- BEGIN: Quota Controls -->
              <div 
                x-data="quotaCtrl()" 
                x-init="await init()"
                class="eb-card-raised mt-6"
              >
                <div class="eb-card-header eb-card-header--divided">
                  <div>
                    <h3 class="eb-card-title">Quotas</h3>
                    <p class="eb-card-subtitle">Set limits for devices and protected workloads. Turn off to allow unlimited.</p>
                  </div>
                </div>

                <div class="divide-y divide-[var(--eb-border-default)]">
                  <!-- Maximum devices -->
                  <div class="flex items-center justify-between px-4 py-3 gap-4">
                    <div class="min-w-0">
                      <label for="q-devices" class="eb-field-label !mb-1">Maximum devices</label>
                      <p class="eb-field-help !mt-0">Limit the number of devices that can register under this backup account.</p>
                    </div>
                    <div class="flex items-center gap-4">
                      <button type="button" class="eb-toggle" @click="dev.enabled = !dev.enabled; if (!dev.enabled) { dev.count = 0 } else if (dev.count < 1) { dev.count = 1 }" :aria-pressed="dev.enabled ? 'true' : 'false'" aria-label="Toggle maximum devices">
                        <span class="eb-toggle-track" :class="{ 'is-on': dev.enabled }"><span class="eb-toggle-thumb"></span></span>
                      </button>

                      <div class="eb-stepper group" :class="{ 'is-disabled': !dev.enabled }">
                        <input
                          id="q-devices"
                          type="number"
                          :min="dev.enabled ? 1 : null"
                          inputmode="numeric"
                          min="1" step="1"
                          x-model.number="dev.count"
                          :disabled="!dev.enabled"
                          :tabindex="dev.enabled ? '0' : '-1'"
                          class="eb-input eb-stepper-input no-native-spin"
                          @blur="if (dev.enabled) dev.count = Math.max(1, parseInt(dev.count || 1))"
                        />
                        <div class="eb-stepper-buttons">
                          <button type="button" class="eb-stepper-button" @click="dev.count = Math.max(1, parseInt((dev.count ?? 1), 10) + 1)" :disabled="!dev.enabled" aria-label="Increase">
                            <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                          </button>
                          <button type="button" class="eb-stepper-button" @click="dev.count = Math.max(1, parseInt((dev.count ?? 1), 10) - 1)" :disabled="!dev.enabled" aria-label="Decrease">
                            <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M5 12h14"/></svg>
                          </button>
                          <span class="eb-stepper-divider"></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Microsoft 365 protected accounts -->
                  <div class="flex items-center justify-between px-4 py-3 gap-4">
                    <div class="min-w-0">
                      <label for="q-m365" class="eb-field-label !mb-1">Microsoft 365 protected accounts</label>
                      <p class="eb-field-help !mt-0">Limit the number of Microsoft 365 accounts protected by this user.</p>
                    </div>
                    <div class="flex items-center gap-4">
                      <button type="button" class="eb-toggle" @click="m365.enabled = !m365.enabled; if (!m365.enabled) { m365.count = 0 } else if (m365.count < 1) { m365.count = 1 }" :aria-pressed="m365.enabled ? 'true' : 'false'" aria-label="Toggle Microsoft 365 accounts">
                        <span class="eb-toggle-track" :class="{ 'is-on': m365.enabled }"><span class="eb-toggle-thumb"></span></span>
                      </button>
                      <div class="eb-stepper group" :class="{ 'is-disabled': !m365.enabled }">
                        <input
                          id="q-m365"
                          type="number"
                          inputmode="numeric"
                          min="1" step="1"
                          x-model.number="m365.count"
                          :disabled="!m365.enabled"
                          :tabindex="m365.enabled ? '0' : '-1'"
                          class="eb-input eb-stepper-input no-native-spin"
                          @blur="if (m365.enabled) m365.count = Math.max(1, parseInt(m365.count || 1))"
                        />
                        <div class="eb-stepper-buttons">
                          <button type="button" class="eb-stepper-button" @click="m365.count = Math.max(1, parseInt((m365.count ?? 1), 10) + 1)" :disabled="!m365.enabled" aria-label="Increase"><svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg></button>
                          <button type="button" class="eb-stepper-button" @click="m365.count = Math.max(1, parseInt((m365.count ?? 1), 10) - 1)" :disabled="!m365.enabled" aria-label="Decrease"><svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M5 12h14"/></svg></button>
                          <span class="eb-stepper-divider"></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Hyper-V guests -->
                  <div class="flex items-center justify-between px-4 py-3 gap-4">
                    <div class="min-w-0">
                      <label for="q-hv" class="eb-field-label !mb-1">Hyper-V guests</label>
                      <p class="eb-field-help !mt-0">Limit the number of Hyper-V virtual machines protected by this user.</p>
                    </div>
                    <div class="flex items-center gap-4">
                      <button type="button" class="eb-toggle" @click="hv.enabled = !hv.enabled; if (!hv.enabled) { hv.count = 0 } else if (hv.count < 1) { hv.count = 1 }" :aria-pressed="hv.enabled ? 'true' : 'false'" aria-label="Toggle Hyper-V guests">
                        <span class="eb-toggle-track" :class="{ 'is-on': hv.enabled }"><span class="eb-toggle-thumb"></span></span>
                      </button>
                      <div class="eb-stepper group" :class="{ 'is-disabled': !hv.enabled }">
                        <input
                          id="q-hv"
                          type="number"
                          inputmode="numeric"
                          min="1" step="1"
                          x-model.number="hv.count"
                          :disabled="!hv.enabled"
                          :tabindex="hv.enabled ? '0' : '-1'"
                          class="eb-input eb-stepper-input no-native-spin"
                          @blur="if (hv.enabled) hv.count = Math.max(1, parseInt(hv.count || 1))"
                        />
                        <div class="eb-stepper-buttons">
                          <button type="button" class="eb-stepper-button" @click="hv.count = Math.max(1, parseInt((hv.count ?? 1), 10) + 1)" :disabled="!hv.enabled" aria-label="Increase"><svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg></button>
                          <button type="button" class="eb-stepper-button" @click="hv.count = Math.max(1, parseInt((hv.count ?? 1), 10) - 1)" :disabled="!hv.enabled" aria-label="Decrease"><svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M5 12h14"/></svg></button>
                          <span class="eb-stepper-divider"></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- VMware guests -->
                  <div class="flex items-center justify-between px-4 py-3 gap-4">
                    <div class="min-w-0">
                      <label for="q-vmw" class="eb-field-label !mb-1">VMware guests</label>
                      <p class="eb-field-help !mt-0">Limit the number of VMware virtual machines protected by this user.</p>
                    </div>
                    <div class="flex items-center gap-4">
                      <button type="button" class="eb-toggle" @click="vmw.enabled = !vmw.enabled; if (!vmw.enabled) { vmw.count = 0 } else if (vmw.count < 1) { vmw.count = 1 }" :aria-pressed="vmw.enabled ? 'true' : 'false'" aria-label="Toggle VMware guests">
                        <span class="eb-toggle-track" :class="{ 'is-on': vmw.enabled }"><span class="eb-toggle-thumb"></span></span>
                      </button>
                      <div class="eb-stepper group" :class="{ 'is-disabled': !vmw.enabled }">
                        <input
                          id="q-vmw"
                          type="number"
                          inputmode="numeric"
                          min="1" step="1"
                          x-model.number="vmw.count"
                          :disabled="!vmw.enabled"
                          :tabindex="vmw.enabled ? '0' : '-1'"
                          class="eb-input eb-stepper-input no-native-spin"
                          @blur="if (vmw.enabled) vmw.count = Math.max(1, parseInt(vmw.count || 1))"
                        />
                        <div class="eb-stepper-buttons">
                          <button type="button" class="eb-stepper-button" @click="vmw.count = Math.max(1, parseInt((vmw.count ?? 1), 10) + 1)" :disabled="!vmw.enabled" aria-label="Increase"><svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg></button>
                          <button type="button" class="eb-stepper-button" @click="vmw.count = Math.max(1, parseInt((vmw.count ?? 1), 10) - 1)" :disabled="!vmw.enabled" aria-label="Decrease"><svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M5 12h14"/></svg></button>
                          <span class="eb-stepper-divider"></span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="flex items-center justify-end gap-2 px-4 py-3">
                  {* <button 
                    type="button"
                    class="rounded-md border border-slate-600 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-700"
                    @click="resetFromProfile()"
                  >Reset</button> *}
                  <button 
                    type="button"
                    class="eb-btn eb-btn-success eb-btn-sm"
                    @click="await save()"
                  >Save quotas</button>
                </div>
              </div>
              <!-- END: Quota Controls -->
            </div>

            <!-- Storage usage (daily maxima) -->
            <div id="eb-storage-card" class="eb-card-raised mt-6">
              <div class="eb-card-header eb-card-header--divided">
                <h3 class="eb-card-title">Storage usage</h3>
                <div class="text-xs text-[var(--eb-text-muted)]">Daily max, last 180 days</div>
              </div>
              <div class="px-4 py-3">
                <div class="eb-legend mb-2">
                  <span class="eb-legend-item"><span class="eb-legend-swatch bg-blue-500"></span>Total</span>
                  <span class="eb-legend-item"><span class="eb-legend-swatch bg-emerald-500"></span>S3-compatible</span>
                  <span class="eb-legend-item"><span class="eb-legend-swatch bg-sky-500"></span>eazyBackup</span>
                </div>
                <div id="eb-storage-chart" style="width: 100%; height: 160px;"></div>
              </div>
            </div>
          </div>

          <div class="space-y-6">
            <div
              class="eb-app-card"
              x-data="{
                modulelink: '',
                serviceid: '',
                username: '',
                enabled: false,
                recipients: [],
                emailInput: '',
                emailError: '',
                mode: 'default',
                preset: 'warn_error',
                saving: false,
                ok: false,
                error: '',
                hash: null
              }"
              x-init="(() => {
                const opts = {
                  modulelink: ($el.dataset.modulelink || '').replace(/&amp;/g, '&'),
                  serviceid:  $el.dataset.serviceid || '',
                  username:   $el.dataset.username || ''
                };
                const attach = () => {
                  try {
                    const make = window.emailReportsFactory || (window.emailReports && ((o)=>window.emailReports(o)));
                    if (!make) return;
                    const obj = make(opts);
                    for (const k in obj) { $data[k] = obj[k]; }
                    if (typeof $data.init === 'function') $data.init(opts);
                  } catch (e) {}
                };
                if (window.emailReportsFactory || window.emailReports) attach();
                else document.addEventListener('emailReports:ready', attach, { once: true });
              })()"
              data-modulelink="{$modulelink}"
              data-serviceid="{$serviceid}"
              data-username="{$username}"
            >
              <h3 class="eb-app-card-title mb-4">Email reporting</h3>
              <div class="space-y-4 text-sm">
                <div class="flex items-center justify-between">
                  <label for="er-enabled" class="eb-field-label !mb-0">Enable reporting</label>
                  <button id="er-enabled" type="button" class="eb-toggle" :aria-pressed="enabled ? 'true' : 'false'" @click="enabled = !enabled" aria-describedby="er-enabled-help">
                    <span class="eb-toggle-track" :class="{ 'is-on': enabled }"><span class="eb-toggle-thumb"></span></span>
                  </button>
                </div>
                <div id="er-enabled-help" class="eb-field-help">Turn on to receive email updates after backups.</div>

                <div>
                  <label class="eb-field-label !mb-1">Recipients</label>
                  <div class="rounded-lg border border-[var(--eb-border-default)] bg-[var(--eb-bg-overlay)] p-2">
                    <div class="flex flex-wrap gap-2 mb-2">
                      <template x-for="(em,i) in recipients" :key="em">
                        <span class="eb-badge eb-badge--info inline-flex items-center gap-1 px-2 py-1">
                          <span class="font-mono text-xs" x-text="em"></span>
                          <button type="button" class="text-[var(--eb-text-secondary)] transition hover:text-[var(--eb-danger-text)]" @click="remove(i)" aria-label="Remove recipient">&times;</button>
                        </span>
                      </template>
                    </div>
                    <div class="flex items-center gap-2">
                      <input type="email" class="eb-input flex-1" placeholder="name@example.com" x-model.trim="emailInput" @keydown.enter.prevent="add()" :disabled="!enabled">
                      <button type="button" class="eb-btn eb-btn-secondary" @click="add()" :disabled="!enabled">Add</button>
                    </div>
                    <div class="mt-1 text-xs" :class="emailError ? 'text-[var(--eb-danger-text)]' : 'text-[var(--eb-text-muted)]'" x-text="emailError || 'Add one or more email addresses to receive reports.'"></div>
                  </div>
                </div>

                <fieldset>
                  <legend class="eb-field-label !mb-1">Report rules</legend>
                  <div class="space-y-2">
                    <label class="flex cursor-pointer items-center gap-2 text-[var(--eb-text-primary)]">
                      <input type="radio" name="er-mode" value="default" x-model="mode" class="eb-checkbox">
                      <span>Use system default</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-[var(--eb-text-primary)]">
                      <input type="radio" name="er-mode" value="custom" x-model="mode" class="eb-checkbox">
                      <span>Customize for this user</span>
                    </label>
                  </div>
                </fieldset>

                <div x-show="mode === 'custom'" x-cloak>
                  <label class="eb-field-label !mb-1">Preset</label>
                  <div class="relative" x-data="{ open:false, options:[
                      { value: 'errors', label: 'Errors only' },
                      { value: 'warn_error', label: 'Warnings and Errors' },
                      { value: 'warn_error_missed', label: 'Warnings, Errors, and Missed' },
                      { value: 'success', label: 'Success only' },
                    ] }" @click.away="open=false">
                    <button type="button" @click="open = !open" class="eb-menu-trigger">
                      <span class="block truncate text-[var(--eb-text-primary)]" x-text="(options.find(o=>o.value===preset)||{}).label || 'Select preset'"></span>
                      <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                        <svg class="h-5 w-5 text-[var(--eb-text-muted)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                      </span>
                    </button>
                    <div x-show="open" x-transition class="eb-menu absolute z-10 mt-1 w-full">
                      <ul class="py-1 max-h-64 overflow-auto">
                        <template x-for="opt in options" :key="opt.value">
                          <li>
                            <a href="#" @click.prevent="preset = opt.value; open=false" class="eb-menu-option" :class="{ 'is-active': preset === opt.value }" x-text="opt.label"></a>
                          </li>
                        </template>
                      </ul>
                    </div>
                  </div>
                  <div class="mt-2 text-xs text-[var(--eb-text-muted)]">Immediate emails will be sent when a backup matches the selected statuses.</div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                  <button type="button" class="eb-btn eb-btn-secondary" x-show="mode === 'custom'" @click="preview()" :disabled="saving">Preview</button>
                  <button type="button" class="eb-btn eb-btn-primary" @click="save()" :disabled="saving">Save</button>
                </div>

                <div class="mt-1 text-xs" :class="error ? 'text-[var(--eb-danger-text)]' : 'text-[var(--eb-success-text)]'" x-text="error || (ok ? 'Saved.' : '')"></div>
              </div>
            </div>
            <div class="eb-app-card">                          
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <h3 class="eb-app-card-title mb-4">Storage Vaults</h3>
                  <a href="#" @click.prevent="activeSubTab = 'storage'" class="text-[var(--eb-primary)] transition hover:underline">Configure...</a>
                </div>
                  {if $vaults}
                      {foreach from=$vaults item=vault}
                          <div class="eb-kv-row">
                              <span class="eb-kv-label">{$vault.Description}</span>
                          </div>
                      {/foreach}
                  {else}
                      <p class="text-[var(--eb-text-primary)]">No storage vaults found.</p>
                  {/if}
              </div>
            </div>
          </div>
        </div> 
      </div>

      <div x-show="activeSubTab === 'protectedItems'" x-cloak x-transition>
        <div class="eb-subpanel"
             x-data="{
                columnsOpen: false,
                entriesOpen: false,
                search: '',
                entriesPerPage: 25,
                currentPage: 1,
                sortKey: 'name',
                sortDirection: 'asc',
                filteredCount: 0,
                rows: [],
                cols: { name: true, type: true, size: true },
                init() {
                  this.rows = Array.from(this.$refs.tbody.querySelectorAll('tr[data-protected-row]'));
                  this.$watch('search', () => {
                    this.currentPage = 1;
                    this.refreshRows();
                  });
                  this.refreshRows();
                },
                setEntries(size) {
                  this.entriesPerPage = Number(size) || 25;
                  this.currentPage = 1;
                  this.refreshRows();
                },
                setSort(key) {
                  if (this.sortKey === key) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                  } else {
                    this.sortKey = key;
                    this.sortDirection = 'asc';
                  }
                  this.refreshRows();
                },
                sortIndicator(key) {
                  if (this.sortKey !== key) return '';
                  return this.sortDirection === 'asc' ? '↑' : '↓';
                },
                sortValue(row, key) {
                  const raw = row.getAttribute('data-sort-' + key) || '';
                  if (key === 'size') return Number(raw) || 0;
                  return String(raw).toLowerCase();
                },
                compareRows(left, right) {
                  const a = this.sortValue(left, this.sortKey);
                  const b = this.sortValue(right, this.sortKey);
                  if (a < b) return this.sortDirection === 'asc' ? -1 : 1;
                  if (a > b) return this.sortDirection === 'asc' ? 1 : -1;
                  return 0;
                },
                refreshRows() {
                  const query = this.search.trim().toLowerCase();
                  const filtered = this.rows.filter((row) => {
                    if (!query) return true;
                    return (row.textContent || '').toLowerCase().includes(query);
                  });
                  filtered.sort((a, b) => this.compareRows(a, b));
                  filtered.forEach((row) => this.$refs.tbody.appendChild(row));

                  this.filteredCount = filtered.length;
                  const pages = this.totalPages();
                  if (this.currentPage > pages) this.currentPage = pages;
                  const start = (this.currentPage - 1) * this.entriesPerPage;
                  const end = start + this.entriesPerPage;
                  const visibleRows = new Set(filtered.slice(start, end));

                  this.rows.forEach((row) => {
                    row.style.display = visibleRows.has(row) ? '' : 'none';
                  });

                  if (this.$refs.noResults) {
                    this.$refs.noResults.style.display = filtered.length === 0 ? '' : 'none';
                  }
                },
                totalPages() {
                  return Math.max(1, Math.ceil(this.filteredCount / this.entriesPerPage));
                },
                pageSummary() {
                  if (this.filteredCount === 0) return 'Showing 0 of 0 protected items';
                  const start = (this.currentPage - 1) * this.entriesPerPage + 1;
                  const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
                  return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' protected items';
                },
                prevPage() {
                  if (this.currentPage <= 1) return;
                  this.currentPage -= 1;
                  this.refreshRows();
                },
                nextPage() {
                  if (this.currentPage >= this.totalPages()) return;
                  this.currentPage += 1;
                  this.refreshRows();
                }
             }"
             x-init="init()">
          <div class="eb-table-toolbar mb-4 flex flex-col gap-3 xl:flex-row xl:items-center">
            <div class="relative" @click.away="entriesOpen=false">
              <button type="button"
                      @click="entriesOpen=!entriesOpen"
                      class="eb-app-toolbar-button">
                <span x-text="'Show ' + entriesPerPage"></span>
                <svg class="w-4 h-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
              <div x-show="entriesOpen"
                   x-transition:enter="transition ease-out duration-100"
                   x-transition:enter-start="opacity-0 scale-95"
                   x-transition:enter-end="opacity-100 scale-100"
                   x-transition:leave="transition ease-in duration-75"
                   x-transition:leave-start="opacity-100 scale-100"
                   x-transition:leave-end="opacity-0 scale-95"
                   class="eb-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden"
                   style="display:none;">
                <template x-for="size in [10,25,50,100]" :key="'protected-items-entries-' + size">
                  <button type="button"
                          class="eb-menu-item block w-full px-4 py-2 text-left text-sm transition"
                          :class="entriesPerPage === size ? 'is-active' : ''"
                          @click="setEntries(size); entriesOpen=false;">
                    <span x-text="size"></span>
                  </button>
                </template>
              </div>
            </div>

            <div class="relative" @click.away="columnsOpen=false">
              <button type="button"
                      @click="columnsOpen=!columnsOpen"
                      class="eb-app-toolbar-button">
                Columns
                <svg class="w-4 h-4 transition-transform" :class="columnsOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
              <div x-show="columnsOpen"
                   x-transition:enter="transition ease-out duration-100"
                   x-transition:enter-start="opacity-0 scale-95"
                   x-transition:enter-end="opacity-100 scale-100"
                   x-transition:leave="transition ease-in duration-75"
                   x-transition:leave-start="opacity-100 scale-100"
                   x-transition:leave-end="opacity-0 scale-95"
                   class="eb-menu absolute left-0 z-50 mt-2 w-56 overflow-hidden p-2"
                   style="display:none;">
                <label class="eb-menu-checklist-item"><span>Name</span><input type="checkbox" class="eb-checkbox" x-model="cols.name"></label>
                <label class="eb-menu-checklist-item"><span>Type</span><input type="checkbox" class="eb-checkbox" x-model="cols.type"></label>
                <label class="eb-menu-checklist-item"><span>Size</span><input type="checkbox" class="eb-checkbox" x-model="cols.size"></label>
              </div>
            </div>

            <div class="flex-1"></div>
            <input type="text"
                   x-model.debounce.200ms="search"
                   placeholder="Search protected items..."
                   class="eb-input eb-app-toolbar-search">
          </div>

          <div class="eb-table-shell overflow-x-auto">
            <table class="eb-table min-w-full text-sm">
              <thead>
                <tr>
                    <th x-show="cols.name" class="px-4 py-3 text-left font-medium">
                      <button type="button" class="eb-table-sort-button" @click="setSort('name')">
                        Name
                        <span x-text="sortIndicator('name')"></span>
                      </button>
                    </th>
                    <th x-show="cols.type" class="px-4 py-3 text-left font-medium">
                      <button type="button" class="eb-table-sort-button" @click="setSort('type')">
                        Type
                        <span x-text="sortIndicator('type')"></span>
                      </button>
                    </th>
                    <th x-show="cols.size" class="px-4 py-3 text-left font-medium">
                      <button type="button" class="eb-table-sort-button" @click="setSort('size')">
                        Size
                        <span x-text="sortIndicator('size')"></span>
                      </button>
                    </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[var(--eb-border-default)]" x-ref="tbody">
                {if $protectedItems|@count > 0}
                  {foreach from=$protectedItems item=item}
                    <tr class="hover:bg-[color:var(--eb-bg-overlay)]" data-protected-row="1"
                        data-sort-name="{$item.name|default:''|escape:'html'}"
                        data-sort-type="{$item.type|default:''|escape:'html'}"
                        data-sort-size="{$item.total_bytes|default:0|escape:'html'}">
                      <td x-show="cols.name" class="eb-table-primary px-4 py-3 whitespace-nowrap text-sm">{$item.name}</td>
                      <td x-show="cols.type" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">{$item.type}</td>
                      <td x-show="cols.size" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($item.total_bytes)}</td>
                    </tr>
                  {/foreach}
                {/if}
                <tr x-ref="noResults" {if $protectedItems|@count > 0}style="display:none;"{/if}>
                  <td colspan="3" class="py-8 text-center text-sm text-[var(--eb-text-muted)]">No protected items found for this user.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="eb-table-pagination mt-4 flex flex-col justify-between gap-3 text-xs text-[var(--eb-text-muted)] sm:flex-row sm:items-center">
            <div x-text="pageSummary()"></div>
            <div class="flex items-center gap-2">
              <button type="button"
                      @click="prevPage()"
                      :disabled="currentPage <= 1"
                      class="eb-table-pagination-button">
                Prev
              </button>
              <span class="text-[var(--eb-text-secondary)]" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
              <button type="button"
                      @click="nextPage()"
                      :disabled="currentPage >= totalPages()"
                      class="eb-table-pagination-button">
                Next
              </button>
            </div>
          </div>
        </div>
      </div>

      <div x-show="activeSubTab === 'storage'" x-cloak x-transition>
          {* Calculate totals across all vaults for billing summary *}
          {assign var=totalQuotaBytes value=0}
          {assign var=totalUsedBytes value=0}
          {assign var=vaultCount value=0}
          {assign var=quotaEnabledCount value=0}
          {foreach from=$vaults item=v}
              {assign var=vaultCount value=$vaultCount+1}
              {* Sum used bytes *}
              {if isset($v.Statistics.ClientProvidedSize.Size)}
                  {assign var=totalUsedBytes value=$totalUsedBytes+$v.Statistics.ClientProvidedSize.Size}
              {elseif isset($v.ClientProvidedSize.Size)}
                  {assign var=totalUsedBytes value=$totalUsedBytes+$v.ClientProvidedSize.Size}
              {elseif isset($v.Size.Size)}
                  {assign var=totalUsedBytes value=$totalUsedBytes+$v.Size.Size}
              {elseif isset($v.Size)}
                  {assign var=totalUsedBytes value=$totalUsedBytes+$v.Size}
              {/if}
              {* Sum quota bytes (only if enabled) *}
              {if $v.StorageLimitEnabled|default:false && $v.StorageLimitBytes|default:0 > 0}
                  {assign var=totalQuotaBytes value=$totalQuotaBytes+$v.StorageLimitBytes}
                  {assign var=quotaEnabledCount value=$quotaEnabledCount+1}
              {/if}
          {/foreach}
          {* Calculate billable TB tier (round up to nearest 1TB) *}
          {assign var=tbBytes value=1099511627776}
          {if $totalQuotaBytes > 0}
              {assign var=billableTB value=ceil($totalQuotaBytes/$tbBytes)}
          {else}
              {assign var=billableTB value=0}
          {/if}
          {assign var=isMicrosoft365Service value=($packageid == 52 || $packageid == 57)}
          
          {* Account-Level Billing Summary Card *}
          <div class="eb-card-raised mb-4">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div class="flex items-center gap-3">
                      <div class="eb-icon-box eb-icon-box--info">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                          </svg>
                      </div>
                      <div>
                          <h3 class="eb-card-title">Account Storage Summary</h3>
                          <p class="eb-card-subtitle">{$vaultCount} vault{if $vaultCount != 1}s{/if}{if $quotaEnabledCount > 0}, {$quotaEnabledCount} with quota enabled{/if}</p>
                      </div>
                  </div>
                  <div class="flex flex-wrap items-center gap-4 sm:gap-6 text-sm">
                      <div class="flex flex-col">
                          <span class="eb-stat-label">Total Used</span>
                          <span class="text-[var(--eb-text-primary)] font-semibold">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($totalUsedBytes, 2)}</span>
                      </div>
                      <div class="flex flex-col">
                          <span class="eb-stat-label">Total Quota</span>
                          <span class="text-[var(--eb-text-primary)] font-semibold">{if $totalQuotaBytes > 0}{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($totalQuotaBytes, 2)}{else}<span class="text-[var(--eb-text-muted)]">No quotas set</span>{/if}</span>
                      </div>
                      <div class="flex flex-col border-l pl-4 sm:pl-6" style="border-color: var(--eb-border-default);">
                          <span class="eb-stat-label flex items-center gap-1">
                              Billable Tier
                              {* <span class="cursor-help text-slate-400" title="Billing is based on the sum of all vault quotas, rounded up to the nearest 1TB tier.">
                                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                  </svg>
                              </span> *}
                          </span>
                          <span class="text-lg font-bold text-[var(--eb-success-text)]">{if $billableTB > 0}{$billableTB} TB{else}<span class="text-sm font-normal text-[var(--eb-text-muted)]">—</span>{/if}</span>
                      </div>
                  </div>
              </div>
              {if $totalQuotaBytes > 0}
              <div class="mt-3 border-t pt-3" style="border-color: var(--eb-border-default);">
                  {* <p class="text-[11px] text-slate-400 leading-relaxed">
                      <svg class="inline h-3.5 w-3.5 mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      Your total quota across all vaults is <strong class="text-slate-100">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($totalQuotaBytes, 2)}</strong>. 
                      Billing is calculated by summing all vault quotas, then rounding up to the nearest 1TB tier 
                      (<strong class="text-emerald-400">{$billableTB} TB</strong>).
                  </p> *}
              </div>
              {/if}
          </div>
          
          <div class="eb-subpanel" x-data="{
              columnsOpen: false,
              entriesOpen: false,
              search: '',
              entriesPerPage: 25,
              currentPage: 1,
              sortKey: 'name',
              sortDirection: 'asc',
              filteredCount: 0,
              rows: [],
              cols: { name:true, stored:true, quota:true, usage:true, billing:true, actions:true },
              pctColor(p){ if(p===null||p==='') return 'bg-slate-700'; if(p<70) return 'bg-emerald-500'; if(p<90) return 'bg-amber-500'; return 'bg-rose-500'; },
              init() {
                this.rows = Array.from(this.$refs.tbody.querySelectorAll('tr[data-vault-row]'));
                this.$watch('search', () => {
                  this.currentPage = 1;
                  this.refreshRows();
                });
                this.refreshRows();
              },
              setEntries(size) {
                this.entriesPerPage = Number(size) || 25;
                this.currentPage = 1;
                this.refreshRows();
              },
              setSort(key) {
                if (this.sortKey === key) {
                  this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                  this.sortKey = key;
                  this.sortDirection = 'asc';
                }
                this.refreshRows();
              },
              sortIndicator(key) {
                if (this.sortKey !== key) return '';
                return this.sortDirection === 'asc' ? '↑' : '↓';
              },
              sortValue(row, key) {
                if (key === 'name') return String(row.getAttribute('data-sort-name') || '').toLowerCase();
                if (key === 'stored') return Number(row.getAttribute('data-used-bytes') || 0);
                if (key === 'quota') return Number(row.getAttribute('data-quota-bytes') || 0);
                if (key === 'usage') return Number(row.getAttribute('data-usage-pct') || 0);
                if (key === 'billing') return Number(row.getAttribute('data-billing-tb') || 0);
                return String(row.getAttribute('data-sort-' + key) || '').toLowerCase();
              },
              compareRows(left, right) {
                const a = this.sortValue(left, this.sortKey);
                const b = this.sortValue(right, this.sortKey);
                if (a < b) return this.sortDirection === 'asc' ? -1 : 1;
                if (a > b) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
              },
              refreshRows() {
                const query = this.search.trim().toLowerCase();
                const filtered = this.rows.filter((row) => {
                  if (!query) return true;
                  return (row.textContent || '').toLowerCase().includes(query);
                });
                filtered.sort((a, b) => this.compareRows(a, b));
                filtered.forEach((row) => this.$refs.tbody.appendChild(row));

                this.filteredCount = filtered.length;
                const pages = this.totalPages();
                if (this.currentPage > pages) this.currentPage = pages;
                const start = (this.currentPage - 1) * this.entriesPerPage;
                const end = start + this.entriesPerPage;
                const visibleRows = new Set(filtered.slice(start, end));

                this.rows.forEach((row) => {
                  row.style.display = visibleRows.has(row) ? '' : 'none';
                });

                if (this.$refs.noResults) {
                  this.$refs.noResults.style.display = filtered.length === 0 ? '' : 'none';
                }
              },
              totalPages() {
                return Math.max(1, Math.ceil(this.filteredCount / this.entriesPerPage));
              },
              pageSummary() {
                if (this.filteredCount === 0) return 'Showing 0 of 0 vaults';
                const start = (this.currentPage - 1) * this.entriesPerPage + 1;
                const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
                return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' vaults';
              },
              prevPage() {
                if (this.currentPage <= 1) return;
                this.currentPage -= 1;
                this.refreshRows();
              },
              nextPage() {
                if (this.currentPage >= this.totalPages()) return;
                this.currentPage += 1;
                this.refreshRows();
              }
          }" x-init="init()">
              <div class="eb-table-toolbar mb-4 flex flex-col gap-3 xl:flex-row xl:items-center">
                  <div class="relative" @click.away="entriesOpen=false">
                      <button type="button"
                              @click="entriesOpen=!entriesOpen"
                              class="eb-app-toolbar-button">
                          <span x-text="'Show ' + entriesPerPage"></span>
                          <svg class="w-4 h-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                          </svg>
                      </button>
                      <div x-show="entriesOpen"
                           x-transition:enter="transition ease-out duration-100"
                           x-transition:enter-start="opacity-0 scale-95"
                           x-transition:enter-end="opacity-100 scale-100"
                           x-transition:leave="transition ease-in duration-75"
                           x-transition:leave-start="opacity-100 scale-100"
                           x-transition:leave-end="opacity-0 scale-95"
                           class="eb-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden"
                           style="display:none;">
                          <template x-for="size in [10,25,50,100]" :key="'user-vaults-entries-' + size">
                              <button type="button"
                                      class="eb-menu-item block w-full px-4 py-2 text-left text-sm transition"
                                      :class="entriesPerPage === size ? 'is-active' : ''"
                                      @click="setEntries(size); entriesOpen=false;">
                                  <span x-text="size"></span>
                              </button>
                          </template>
                      </div>
                  </div>

                  <div class="relative" @click.away="columnsOpen=false">
                      <button type="button"
                              @click="columnsOpen=!columnsOpen"
                              class="eb-app-toolbar-button">
                          Columns
                          <svg class="w-4 h-4 transition-transform" :class="columnsOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                              <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                          </svg>
                      </button>
                      <div x-show="columnsOpen"
                           x-transition:enter="transition ease-out duration-100"
                           x-transition:enter-start="opacity-0 scale-95"
                           x-transition:enter-end="opacity-100 scale-100"
                           x-transition:leave="transition ease-in duration-75"
                           x-transition:leave-start="opacity-100 scale-100"
                           x-transition:leave-end="opacity-0 scale-95"
                           class="eb-menu absolute left-0 z-50 mt-2 w-56 overflow-hidden p-2"
                           style="display:none;">
                          <label class="eb-menu-checklist-item"><span>Storage Vault</span><input type="checkbox" class="eb-checkbox" x-model="cols.name"></label>
                          <label class="eb-menu-checklist-item"><span>Stored</span><input type="checkbox" class="eb-checkbox" x-model="cols.stored"></label>
                          <label class="eb-menu-checklist-item"><span>Quota</span><input type="checkbox" class="eb-checkbox" x-model="cols.quota"></label>
                          <label class="eb-menu-checklist-item"><span>Usage</span><input type="checkbox" class="eb-checkbox" x-model="cols.usage"></label>
                          <label class="eb-menu-checklist-item"><span>Billing</span><input type="checkbox" class="eb-checkbox" x-model="cols.billing"></label>
                          <label class="eb-menu-checklist-item"><span>Actions</span><input type="checkbox" class="eb-checkbox" x-model="cols.actions"></label>
                      </div>
                  </div>

                  <div class="flex-1"></div>
                  <input type="text"
                         x-model.debounce.200ms="search"
                         placeholder="Search vaults..."
                         class="eb-input eb-app-toolbar-search">
              </div>

              <div class="table-scroll eb-table-shell overflow-x-auto">
              <table class="eb-table w-full min-w-max text-sm">
                  <thead>
                      <tr>
                          <th x-show="cols.name" class="px-4 py-3 text-left font-medium">
                              <button type="button" class="eb-table-sort-button" @click="setSort('name')">
                                  Storage Vault
                                  <span x-text="sortIndicator('name')"></span>
                              </button>
                          </th>
                          <th x-show="cols.stored" class="px-4 py-3 text-left font-medium">
                              <button type="button" class="eb-table-sort-button" @click="setSort('stored')">
                                  Stored
                                  <span x-text="sortIndicator('stored')"></span>
                              </button>
                          </th>
                          <th x-show="cols.quota" class="px-4 py-3 text-left font-medium">
                              <button type="button" class="eb-table-sort-button" @click="setSort('quota')">
                                  Quota
                                  <span x-text="sortIndicator('quota')"></span>
                              </button>
                          </th>
                          <th x-show="cols.usage" class="px-4 py-3 text-left font-medium">
                              <button type="button" class="eb-table-sort-button" @click="setSort('usage')">
                                  Usage
                                  <span x-text="sortIndicator('usage')"></span>
                              </button>
                          </th>
                          <th x-show="cols.billing" class="px-4 py-3 text-left font-medium">
                              <button type="button" class="eb-table-sort-button" @click="setSort('billing')">
                                  Billing
                                  <span x-text="sortIndicator('billing')"></span>
                              </button>
                          </th>
                          <th x-show="cols.actions" class="px-4 py-3 text-left font-medium">Actions</th>
              </tr>
            </thead>
                  <tbody class="divide-y divide-[var(--eb-border-default)]" x-ref="tbody">
                      {if $vaults|@count > 0}
                        {foreach from=$vaults item=vault key=vaultId}
                            {assign var=usedBytes value=0}
                            {if isset($vault.Statistics.ClientProvidedSize.Size)}
                                {assign var=usedBytes value=$vault.Statistics.ClientProvidedSize.Size}
                            {elseif isset($vault.ClientProvidedSize.Size)}
                                {assign var=usedBytes value=$vault.ClientProvidedSize.Size}
                            {elseif isset($vault.Size.Size)}
                                {assign var=usedBytes value=$vault.Size.Size}
                            {elseif isset($vault.Size)}
                                {assign var=usedBytes value=$vault.Size}
                            {/if}
                            {assign var=usedMeasuredEnd value=0}
                            {if isset($vault.Statistics.ClientProvidedSize.MeasureCompleted)}
                                {assign var=usedMeasuredEnd value=$vault.Statistics.ClientProvidedSize.MeasureCompleted}
                            {elseif isset($vault.ClientProvidedSize.MeasureCompleted)}
                                {assign var=usedMeasuredEnd value=$vault.ClientProvidedSize.MeasureCompleted}
                            {/if}
                            {assign var=quotaEnabled value=$vault.StorageLimitEnabled|default:false}
                            {assign var=quotaBytes value=$vault.StorageLimitBytes|default:0}
                            {assign var=hasQuota value=($quotaEnabled && $quotaBytes>0)}
                            {if $hasQuota}
                                {assign var=pct value=(100*$usedBytes/$quotaBytes)}
                            {else}
                                {assign var=pct value=''}
                            {/if}
                            {assign var=typeCode value=$vault.Destination.Type|default:$vault.Type|default:''}
                            {assign var=typeLabel value=$vault.TypeFriendly|default:''}
                            <tr class="hover:bg-[var(--eb-bg-overlay)]"
                                data-vault-row="1"
                                data-sort-name="{$vault.Description|default:'-'|escape:'html'}"
                                data-used-bytes="{$usedBytes}"
                                data-quota-bytes="{$quotaBytes}"
                                data-usage-pct="{if $hasQuota}{$pct}{else}0{/if}"
                                data-billing-tb="{$billableTB}"
                                data-vault-locked="{if $isMicrosoft365Service}1{else}0{/if}">
                                <td x-show="cols.name" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">{$vault.Description|default:'-'}</td>
                                <td x-show="cols.stored" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">
                                    {assign var=cpSize value=0}
                                    {if isset($vault.Statistics.ClientProvidedSize.Size)}
                                        {assign var=cpSize value=$vault.Statistics.ClientProvidedSize.Size}
                                    {elseif isset($vault.ClientProvidedSize.Size)}
                                        {assign var=cpSize value=$vault.ClientProvidedSize.Size}
                                    {elseif isset($vault.Size.Size)}
                                        {assign var=cpSize value=$vault.Size.Size}
                                    {elseif isset($vault.Size)}
                                        {assign var=cpSize value=$vault.Size}
                                    {/if}
                                    {assign var=cpStart value=0}
                                    {assign var=cpEnd value=0}
                                    {if isset($vault.Statistics.ClientProvidedSize.MeasureStarted)}
                                        {assign var=cpStart value=$vault.Statistics.ClientProvidedSize.MeasureStarted}
                                    {elseif isset($vault.ClientProvidedSize.MeasureStarted)}
                                        {assign var=cpStart value=$vault.ClientProvidedSize.MeasureStarted}
                                    {/if}
                                    {if isset($vault.Statistics.ClientProvidedSize.MeasureCompleted)}
                                        {assign var=cpEnd value=$vault.Statistics.ClientProvidedSize.MeasureCompleted}
                                    {elseif isset($vault.ClientProvidedSize.MeasureCompleted)}
                                        {assign var=cpEnd value=$vault.ClientProvidedSize.MeasureCompleted}
                                    {/if}
                                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs"
                                        title="View vault usage breakdown"
                                        data-vault-id="{$vaultId}"
                                        data-vault-name="{$vault.Description|escape}"
                                        data-size-bytes="{$cpSize}"
                                        data-measure-start="{$cpStart}"
                                        data-measure-end="{$cpEnd}">
                                        {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($cpSize, 2)}
                                    </button>
                                    <script type="application/json" class="eb-components">{if isset($vault.ClientProvidedContent.Components)}{$vault.ClientProvidedContent.Components|@json_encode}{elseif isset($vault.Statistics.ClientProvidedContent.Components)}{$vault.Statistics.ClientProvidedContent.Components|@json_encode}{else}[]{/if}</script>
                                </td>
                                <td x-show="cols.quota" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">
                                    {if not $hasQuota}
                                        <span class="eb-badge eb-badge--neutral px-2.5 py-0.5 text-sm">Unlimited</span>
                                    {else}
                                        <div class="flex flex-col gap-1">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="eb-badge eb-badge--neutral px-2.5 py-0.5 text-sm" title="Exact quota: {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($quotaBytes, 2)}">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($quotaBytes, 2)}</span>
                                                {if $quotaEnabled}
                                                    <span class="eb-badge eb-badge--success px-2 py-0.5 text-[10px]">On</span>
                                                {else}
                                                    <span class="eb-badge eb-badge--neutral px-2 py-0.5 text-[10px]">Off</span>
                                                {/if}
                                                <button type="button" class="configure-vault-button ml-1 rounded p-1.5 {if $isMicrosoft365Service}cursor-not-allowed opacity-50 text-[var(--eb-text-muted)]{else}cursor-pointer text-[var(--eb-text-primary)] hover:bg-[var(--eb-bg-overlay)]{/if}"
                                                    title="{if $isMicrosoft365Service}Locked for Microsoft 365 services{else}Edit quota{/if}"
                                                    {if $isMicrosoft365Service}disabled="disabled" aria-disabled="true"{/if}
                                                    data-vault-id="{$vaultId}"
                                                    data-vault-name="{$vault.Description}"
                                                    data-vault-quota-enabled="{$quotaEnabled}"
                                                    data-vault-quota-bytes="{$quotaBytes}">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232a2.5 2.5 0 113.536 3.536L7.5 20.036 3 21l.964-4.5L15.232 5.232z"/></svg>
                                                </button>
                                            </span>
                                        </div>
                                    {/if}
                                </td>
                                <td x-show="cols.usage" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">
                                    {if $hasQuota}
                                        {assign var=pctClamped value=$pct}
                                        {if $pctClamped > 100}
                                            {assign var=pctClamped value=100}
                                        {elseif $pctClamped < 0}
                                            {assign var=pctClamped value=0}
                                        {/if}
                                        <div class="w-56">
                                            <div class="eb-progress-track h-2.5 w-full" title="{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($usedBytes, 2)} of {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($quotaBytes, 2)} ({$pctClamped|string_format:'%.1f'}%) — measured {\WHMCS\Module\Addon\Eazybackup\Helper::formatDateTime($usedMeasuredEnd)}">
                                                <div class="h-full transition-[width] duration-500" :class="pctColor({$pctClamped})" style="width: {$pctClamped}%;"></div>
                                            </div>
                                            <div class="mt-1 text-xs text-[var(--eb-text-muted)]">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($usedBytes, 2)} / {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($quotaBytes, 2)} ({$pctClamped|string_format:'%.1f'}%)</div>
                                        </div>
                                    {else}
                                        <div class="w-56">
                                            <div class="eb-progress-track h-2.5 w-full">
                                                <div class="h-full w-1/3 animate-pulse rounded-full bg-[var(--eb-border-subtle)]"></div>
                                            </div>
                                            <div class="mt-1 text-xs text-[var(--eb-text-muted)]">Usage unavailable (no quota)</div>
                                        </div>
                                    {/if}
                                </td>
                                <td x-show="cols.billing" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">
                                    {if $billableTB > 0}{$billableTB} TB{else}—{/if}
                                </td>
                                <td x-show="cols.actions" class="px-4 py-3 whitespace-nowrap text-sm">
                                    <button class="open-vault-panel eb-btn eb-btn-secondary eb-btn-xs {if $isMicrosoft365Service}cursor-not-allowed opacity-50{else}cursor-pointer{/if}"
                                            title="{if $isMicrosoft365Service}Locked for Microsoft 365 services{else}Manage{/if}"
                                            {if $isMicrosoft365Service}disabled="disabled" aria-disabled="true"{/if}
                                            data-vault-id="{$vaultId}"
                                            data-vault-name="{$vault.Description}"
                                            data-vault-quota-enabled="{$quotaEnabled}"
                                            data-vault-quota-bytes="{$quotaBytes}">Manage</button>
                                </td>
                            </tr>
                        {/foreach}
                      {/if}
                      <tr x-ref="noResults" {if $vaults|@count > 0}style="display:none;"{/if}>
                          <td colspan="6" class="py-8 text-center text-sm text-[var(--eb-text-muted)]">No storage vaults found for this user.</td>
                      </tr>
            </tbody>
          </table>
              </div>
              <div class="eb-table-pagination mt-4 flex flex-col justify-between gap-3 text-xs text-[var(--eb-text-muted)] sm:flex-row sm:items-center">
                <div x-text="pageSummary()"></div>
                <div class="flex items-center gap-2">
                  <button type="button"
                          @click="prevPage()"
                          :disabled="currentPage <= 1"
                          class="eb-table-pagination-button">
                    Prev
                  </button>
                  <span class="text-[var(--eb-text-secondary)]" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
                  <button type="button"
                          @click="nextPage()"
                          :disabled="currentPage >= totalPages()"
                          class="eb-table-pagination-button">
                    Next
                  </button>
                </div>
              </div>
        </div>
      </div>

      <div x-show="activeSubTab === 'devices'" x-cloak x-transition>
        <div class="eb-subpanel" x-data="{
            columnsOpen: false,
            entriesOpen: false,
            search: '',
            entriesPerPage: 25,
            currentPage: 1,
            sortKey: 'name',
            sortDirection: 'asc',
            filteredCount: 0,
            rows: [],
            cols:{ status:true, name:true, id:false, reg:true, ver:true, plat:true, items:true, actions:true },
            init() {
              this.rows = Array.from(this.$refs.tbody.querySelectorAll('tr[data-device-row]'));
              this.$watch('search', () => {
                this.currentPage = 1;
                this.refreshRows();
              });
              this.refreshRows();
            },
            setEntries(size) {
              this.entriesPerPage = Number(size) || 25;
              this.currentPage = 1;
              this.refreshRows();
            },
            setSort(key) {
              if (this.sortKey === key) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
              } else {
                this.sortKey = key;
                this.sortDirection = 'asc';
              }
              this.refreshRows();
            },
            sortIndicator(key) {
              if (this.sortKey !== key) return '';
              return this.sortDirection === 'asc' ? '↑' : '↓';
            },
            sortValue(row, key) {
              const raw = row.getAttribute('data-sort-' + key) || '';
              if (key === 'items') return Number(raw) || 0;
              if (key === 'status') return String(raw).toLowerCase() === 'online' ? 1 : 0;
              return String(raw).toLowerCase();
            },
            compareRows(left, right) {
              const a = this.sortValue(left, this.sortKey);
              const b = this.sortValue(right, this.sortKey);
              if (a < b) return this.sortDirection === 'asc' ? -1 : 1;
              if (a > b) return this.sortDirection === 'asc' ? 1 : -1;
              return 0;
            },
            refreshRows() {
              const query = this.search.trim().toLowerCase();
              const filtered = this.rows.filter((row) => {
                if (!query) return true;
                return (row.textContent || '').toLowerCase().includes(query);
              });
              filtered.sort((a, b) => this.compareRows(a, b));
              filtered.forEach((row) => this.$refs.tbody.appendChild(row));

              this.filteredCount = filtered.length;
              const pages = this.totalPages();
              if (this.currentPage > pages) this.currentPage = pages;
              const start = (this.currentPage - 1) * this.entriesPerPage;
              const end = start + this.entriesPerPage;
              const visibleRows = new Set(filtered.slice(start, end));

              this.rows.forEach((row) => {
                row.style.display = visibleRows.has(row) ? '' : 'none';
              });

              if (this.$refs.noResults) {
                this.$refs.noResults.style.display = filtered.length === 0 ? '' : 'none';
              }
            },
            totalPages() {
              return Math.max(1, Math.ceil(this.filteredCount / this.entriesPerPage));
            },
            pageSummary() {
              if (this.filteredCount === 0) return 'Showing 0 of 0 devices';
              const start = (this.currentPage - 1) * this.entriesPerPage + 1;
              const end = Math.min(start + this.entriesPerPage - 1, this.filteredCount);
              return 'Showing ' + start + '-' + end + ' of ' + this.filteredCount + ' devices';
            },
            prevPage() {
              if (this.currentPage <= 1) return;
              this.currentPage -= 1;
              this.refreshRows();
            },
            nextPage() {
              if (this.currentPage >= this.totalPages()) return;
              this.currentPage += 1;
              this.refreshRows();
            }
        }" x-init="init()">
            <div class="eb-table-toolbar mb-4 flex flex-col gap-3 xl:flex-row xl:items-center">
                <div class="relative" @click.away="entriesOpen=false">
                    <button type="button"
                            @click="entriesOpen=!entriesOpen"
                            class="eb-app-toolbar-button">
                        <span x-text="'Show ' + entriesPerPage"></span>
                        <svg class="w-4 h-4 transition-transform" :class="entriesOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="entriesOpen"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="eb-menu absolute left-0 z-50 mt-2 w-40 overflow-hidden"
                         style="display:none;">
                        <template x-for="size in [10,25,50,100]" :key="'user-devices-entries-' + size">
                            <button type="button"
                                    class="eb-menu-item block w-full px-4 py-2 text-left text-sm transition"
                                    :class="entriesPerPage === size ? 'is-active' : ''"
                                    @click="setEntries(size); entriesOpen=false;">
                                <span x-text="size"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="relative" @click.away="columnsOpen=false">
                    <button type="button"
                            @click="columnsOpen=!columnsOpen"
                            class="eb-app-toolbar-button">
                        Columns
                        <svg class="w-4 h-4 transition-transform" :class="columnsOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div x-show="columnsOpen"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="eb-menu absolute left-0 z-50 mt-2 w-56 overflow-hidden p-2"
                         style="display:none;">
                        <label class="eb-menu-checklist-item"><span>Status</span><input type="checkbox" class="eb-checkbox" x-model="cols.status"></label>
                        <label class="eb-menu-checklist-item"><span>Device Name</span><input type="checkbox" class="eb-checkbox" x-model="cols.name"></label>
                        <label class="eb-menu-checklist-item"><span>Device ID</span><input type="checkbox" class="eb-checkbox" x-model="cols.id"></label>
                        <label class="eb-menu-checklist-item"><span>Registered</span><input type="checkbox" class="eb-checkbox" x-model="cols.reg"></label>
                        <label class="eb-menu-checklist-item"><span>Version</span><input type="checkbox" class="eb-checkbox" x-model="cols.ver"></label>
                        <label class="eb-menu-checklist-item"><span>Platform</span><input type="checkbox" class="eb-checkbox" x-model="cols.plat"></label>
                        <label class="eb-menu-checklist-item"><span>Protected Items</span><input type="checkbox" class="eb-checkbox" x-model="cols.items"></label>
                        <label class="eb-menu-checklist-item"><span>Actions</span><input type="checkbox" class="eb-checkbox" x-model="cols.actions"></label>
                    </div>
                </div>

                <div class="flex-1"></div>
                <input type="text"
                       x-model.debounce.200ms="search"
                       placeholder="Search devices..."
                       class="eb-input eb-app-toolbar-search">
            </div>
            <div class="table-scroll eb-table-shell overflow-x-auto">
            <table class="eb-table w-full min-w-max text-sm">
                <thead>
                    <tr>
                        <th x-show="cols.status" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="setSort('status')">Status<span x-text="sortIndicator('status')"></span></button>
                        </th>
                        <th x-show="cols.name" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="setSort('name')">Device Name<span x-text="sortIndicator('name')"></span></button>
                        </th>
                        <th x-show="cols.id" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="setSort('id')">Device ID<span x-text="sortIndicator('id')"></span></button>
                        </th>
                        <th x-show="cols.reg" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="setSort('reg')">Registered<span x-text="sortIndicator('reg')"></span></button>
                        </th>
                        <th x-show="cols.ver" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="setSort('ver')">Version<span x-text="sortIndicator('ver')"></span></button>
                        </th>
                        <th x-show="cols.plat" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="setSort('plat')">Platform<span x-text="sortIndicator('plat')"></span></button>
                        </th>
                        <th x-show="cols.items" class="px-4 py-3 text-left font-medium">
                            <button type="button" class="eb-table-sort-button" @click="setSort('items')">Protected Items<span x-text="sortIndicator('items')"></span></button>
                        </th>
                        <th x-show="cols.actions" class="px-4 py-3 text-left font-medium">Actions</th>
              </tr>
            </thead>
                <tbody class="divide-y divide-[var(--eb-border-default)]" x-ref="tbody">
                    {if $devices|@count > 0}
                      {foreach from=$devices item=device}
                        <tr class="hover:bg-[var(--eb-bg-overlay)]" data-device-row="1"
                            data-sort-status="{$device.status|default:''|escape:'html'}"
                            data-sort-name="{$device.device_name|default:''|escape:'html'}"
                            data-sort-id="{$device.device_id|default:''|escape:'html'}"
                            data-sort-reg="{$device.registered|default:''|escape:'html'}"
                            data-sort-ver="{$device.version|default:''|escape:'html'}"
                            data-sort-plat="{$device.platform|default:''|escape:'html'}"
                            data-sort-items="{$device.protected_items|default:0|escape:'html'}">
                            <td x-show="cols.status" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">
                                {if $device.status == 'Online'}
                                    <span class="eb-badge eb-badge--success px-2.5 py-0.5 text-xs">Online</span>
                                {else}
                                    <span class="eb-badge eb-badge--neutral px-2.5 py-0.5 text-xs">Offline</span>
                                {/if}
                            </td>
                            <td x-show="cols.name" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">{$device.device_name}</td>
                            <td x-show="cols.id" class="px-4 py-3 whitespace-nowrap text-xs font-mono text-[var(--eb-text-primary)]">{$device.device_id}</td>
                            <td x-show="cols.reg" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">{$device.registered}</td>
                            <td x-show="cols.ver" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">{$device.version}</td>
                            <td x-show="cols.plat" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">{$device.platform}</td>
                            <td x-show="cols.items" class="px-4 py-3 whitespace-nowrap text-sm text-[var(--eb-text-secondary)]">{$device.protected_items}</td>
                            <td x-show="cols.actions" class="px-4 py-3 whitespace-nowrap text-sm">
                                <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" data-action="open-device-panel" data-device-id="{$device.device_id}" data-device-name="{$device.device_name}" data-device-online="{if $device.status == 'Online'}1{else}0{/if}">Manage</button>
                            </td>
                        </tr>
                      {/foreach}
                    {/if}
                    <tr x-ref="noResults" {if $devices|@count > 0}style="display:none;"{/if}>
                        <td colspan="8" class="py-8 text-center text-sm text-[var(--eb-text-muted)]">No devices found for this user.</td>
                    </tr>
            </tbody>
          </table>
            </div>
            <div class="eb-table-pagination mt-4 flex flex-col justify-between gap-3 text-xs text-[var(--eb-text-muted)] sm:flex-row sm:items-center">
              <div x-text="pageSummary()"></div>
              <div class="flex items-center gap-2">
                <button type="button"
                        @click="prevPage()"
                        :disabled="currentPage <= 1"
                        class="eb-table-pagination-button">
                  Prev
                </button>
                <span class="text-[var(--eb-text-secondary)]" x-text="'Page ' + currentPage + ' / ' + totalPages()"></span>
                <button type="button"
                        @click="nextPage()"
                        :disabled="currentPage >= totalPages()"
                        class="eb-table-pagination-button">
                  Next
                </button>
              </div>
            </div>
        </div>
      </div>
      
      <div x-show="activeSubTab === 'jobLogs'" x-cloak x-transition>
        <div class="eb-subpanel" x-data="{ open:false, search:'', cols:{ user:true, id:false, device:true, item:true, vault:false, ver:false, type:true, status:true, dirs:false, files:false, size:true, vsize:true, up:false, down:false, started:true, ended:true, dur:true } }">
            <div class="border-b px-4 pt-4 pb-3" style="border-color: var(--eb-border-default);">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap gap-2">
                        <button type="button" data-jobs-status-chip data-status="Error" class="eb-badge eb-badge--danger eb-job-chip disabled:cursor-not-allowed">
                            <span class="eb-job-chip-dot" style="background: var(--eb-danger-icon);"></span><span>Error</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
                        </button>
                        <button type="button" data-jobs-status-chip data-status="Missed" class="eb-badge eb-badge--neutral eb-job-chip disabled:cursor-not-allowed">
                            <span class="eb-job-chip-dot eb-job-chip-dot--empty"></span><span>Missed</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
                        </button>
                        <button type="button" data-jobs-status-chip data-status="Warning" class="eb-badge eb-badge--warning eb-job-chip disabled:cursor-not-allowed">
                            <span class="eb-job-chip-dot" style="background: var(--eb-warning-icon);"></span><span>Warning</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
                        </button>
                        <button type="button" data-jobs-status-chip data-status="Timeout" class="eb-badge eb-badge--warning eb-job-chip disabled:cursor-not-allowed">
                            <span class="eb-job-chip-dot" style="background: var(--eb-warning-icon);"></span><span>Timeout</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
                        </button>
                        <button type="button" data-jobs-status-chip data-status="Cancelled" class="eb-badge eb-badge--danger eb-job-chip disabled:cursor-not-allowed">
                            <span class="eb-job-chip-dot" style="background: var(--eb-danger-icon);"></span><span>Cancelled</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
                        </button>
                        <button type="button" data-jobs-status-chip data-status="Running" class="eb-badge eb-badge--info eb-job-chip disabled:cursor-not-allowed">
                            <span class="eb-job-chip-dot" style="background: var(--eb-info-icon);"></span><span>Running</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
                        </button>
                        <button type="button" data-jobs-status-chip data-status="Skipped" class="eb-badge eb-badge--premium eb-job-chip disabled:cursor-not-allowed">
                            <span class="eb-job-chip-dot" style="background: var(--eb-premium-text);"></span><span>Skipped</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
                        </button>
                        <button type="button" data-jobs-status-chip data-status="Success" class="eb-badge eb-badge--success eb-job-chip disabled:cursor-not-allowed">
                            <span class="eb-job-chip-dot" style="background: var(--eb-success-icon);"></span><span>Success</span><span data-jobs-status-count class="font-semibold tabular-nums">0</span>
                        </button>
                    </div>

                    <button id="jobs-clear-filters" type="button" class="hidden eb-btn eb-btn-ghost eb-btn-xs shrink-0">
                        Clear
                    </button>
                </div>
                <div id="jobs-active-filters" class="mt-2 hidden text-xs text-[var(--eb-text-muted)]"></div>
            </div>

            <div class="eb-table-toolbar mb-4 flex flex-col gap-3 px-4 pt-3 lg:flex-row lg:items-center">
                <div class="flex items-center gap-3">
                    <!-- View columns dropdown -->
                    <div class="relative shrink-0" @click.away="open=false">
                        <button type="button" class="eb-app-toolbar-button" @click="open=!open">
                            <span class="font-medium">Columns</span>
                            <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="eb-menu absolute z-10 mt-2 w-72 p-2">
                            <div class="eb-menu-checklist two-col">
                                <label class="eb-menu-checklist-item"><span>Username</span><input type="checkbox" class="eb-checkbox" x-model="cols.user"></label>
                                <label class="eb-menu-checklist-item"><span>Job ID</span><input type="checkbox" class="eb-checkbox" x-model="cols.id"></label>
                                <label class="eb-menu-checklist-item"><span>Device</span><input type="checkbox" class="eb-checkbox" x-model="cols.device"></label>
                                <label class="eb-menu-checklist-item"><span>Protected Item</span><input type="checkbox" class="eb-checkbox" x-model="cols.item"></label>
                                <label class="eb-menu-checklist-item"><span>Storage Vault</span><input type="checkbox" class="eb-checkbox" x-model="cols.vault"></label>
                                <label class="eb-menu-checklist-item"><span>Version</span><input type="checkbox" class="eb-checkbox" x-model="cols.ver"></label>
                                <label class="eb-menu-checklist-item"><span>Type</span><input type="checkbox" class="eb-checkbox" x-model="cols.type"></label>
                                <label class="eb-menu-checklist-item"><span>Status</span><input type="checkbox" class="eb-checkbox" x-model="cols.status"></label>
                                <label class="eb-menu-checklist-item"><span>Directories</span><input type="checkbox" class="eb-checkbox" x-model="cols.dirs"></label>
                                <label class="eb-menu-checklist-item"><span>Files</span><input type="checkbox" class="eb-checkbox" x-model="cols.files"></label>
                                <label class="eb-menu-checklist-item"><span>Size</span><input type="checkbox" class="eb-checkbox" x-model="cols.size"></label>
                                <label class="eb-menu-checklist-item"><span>Storage Vault Size</span><input type="checkbox" class="eb-checkbox" x-model="cols.vsize"></label>
                                <label class="eb-menu-checklist-item"><span>Uploaded</span><input type="checkbox" class="eb-checkbox" x-model="cols.up"></label>
                                <label class="eb-menu-checklist-item"><span>Downloaded</span><input type="checkbox" class="eb-checkbox" x-model="cols.down"></label>
                                <label class="eb-menu-checklist-item"><span>Started</span><input type="checkbox" class="eb-checkbox" x-model="cols.started"></label>
                                <label class="eb-menu-checklist-item"><span>Ended</span><input type="checkbox" class="eb-checkbox" x-model="cols.ended"></label>
                                <label class="eb-menu-checklist-item"><span>Duration</span><input type="checkbox" class="eb-checkbox" x-model="cols.dur"></label>
                            </div>
                        </div>
                    </div>

                    <!-- Page size dropdown -->
                    <div x-data="{ 
                        sizeOpen: false, 
                        pageSize: 10,
                        sizes: [10, 25, 50, 100],
                        setSize(size) {
                            this.pageSize = size;
                            this.sizeOpen = false;
                            window.dispatchEvent(new CustomEvent('jobs:pagesize', { detail: size }));
                        }
                    }" class="relative">
                        <button @click="sizeOpen = !sizeOpen" @click.away="sizeOpen = false" type="button" 
                                class="eb-app-toolbar-button">
                            <span class="text-[var(--eb-text-muted)]">Show</span>
                            <span x-text="pageSize" class="font-medium"></span>
                            <svg class="h-4 w-4 transition-transform" :class="sizeOpen ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div x-show="sizeOpen" x-cloak x-transition 
                             class="eb-menu absolute left-0 z-10 mt-2 w-24 overflow-hidden">
                            <template x-for="size in sizes" :key="size">
                                <button @click="setSize(size)" type="button"
                                        :class="pageSize === size ? 'is-active' : ''"
                                        class="eb-menu-item block w-full px-3 py-2 text-left text-sm transition-colors">
                                    <span x-text="size"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="w-full lg:w-80 lg:ml-auto">
                    <input id="jobs-search" type="text" placeholder="Search jobs..." class="eb-input eb-app-toolbar-search w-full xl:w-80">
                </div>
            </div>

            <!-- scroll wrapper: horizontal scroll for column overflow -->
            <div class="px-4 pb-2">
              <div class="table-scroll eb-table-shell overflow-x-auto">
                <table id="jobs-table" class="eb-table min-w-full text-sm" data-job-table>
                    <thead>
                        <tr>
                            <th x-show="cols.user" data-sort="Username"><button type="button" class="eb-table-sort-button">Username<span data-sort-indicator></span></button></th>
                            <th x-show="cols.id" x-cloak data-sort="JobID"><button type="button" class="eb-table-sort-button">Job ID<span data-sort-indicator></span></button></th>
                            <th x-show="cols.device" data-sort="Device"><button type="button" class="eb-table-sort-button">Device<span data-sort-indicator></span></button></th>
                            <th x-show="cols.item" data-sort="ProtectedItem"><button type="button" class="eb-table-sort-button">Protected Item<span data-sort-indicator></span></button></th>
                            <th x-show="cols.vault" data-sort="StorageVault"><button type="button" class="eb-table-sort-button">Storage Vault<span data-sort-indicator></span></button></th>
                            <th x-show="cols.ver" data-sort="Version"><button type="button" class="eb-table-sort-button">Version<span data-sort-indicator></span></button></th>
                            <th x-show="cols.type" data-sort="Type"><button type="button" class="eb-table-sort-button">Type<span data-sort-indicator></span></button></th>
                            <th x-show="cols.status" data-sort="Status"><button type="button" class="eb-table-sort-button">Status<span data-sort-indicator></span></button></th>
                            <th x-show="cols.dirs" data-sort="Directories"><button type="button" class="eb-table-sort-button">Directories<span data-sort-indicator></span></button></th>
                            <th x-show="cols.files" data-sort="Files"><button type="button" class="eb-table-sort-button">Files<span data-sort-indicator></span></button></th>
                            <th x-show="cols.size" data-sort="Size"><button type="button" class="eb-table-sort-button">Size<span data-sort-indicator></span></button></th>
                            <th x-show="cols.vsize" data-sort="VaultSize"><button type="button" class="eb-table-sort-button">Storage Vault Size<span data-sort-indicator></span></button></th>
                            <th x-show="cols.up" data-sort="Uploaded"><button type="button" class="eb-table-sort-button">Uploaded<span data-sort-indicator></span></button></th>
                            <th x-show="cols.down" data-sort="Downloaded"><button type="button" class="eb-table-sort-button">Downloaded<span data-sort-indicator></span></button></th>
                            <th x-show="cols.started" data-sort="Started"><button type="button" class="eb-table-sort-button">Started<span data-sort-indicator></span></button></th>
                            <th x-show="cols.ended" data-sort="Ended"><button type="button" class="eb-table-sort-button">Ended<span data-sort-indicator></span></button></th>
                            <th x-show="cols.dur" data-sort="Duration"><button type="button" class="eb-table-sort-button">Duration<span data-sort-indicator></span></button></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--eb-border-default)]"></tbody>
                </table>
              </div>
            </div>
            <div class="px-4 py-2">
                <div id="jobs-pager" class="eb-table-pagination flex items-center gap-2 text-xs font-medium text-[var(--eb-text-muted)]"></div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

{* Vault slide-over panel *}
<div id="vault-slide-panel-container" 
     x-data="{ open: false }"
     @vault-panel:open.window="open = true"
     @vault-panel:close.window="open = false"
     class="fixed inset-0 z-[10060] pointer-events-none">
  
  {* Backdrop overlay - closes panel when clicked *}
  <div id="vault-panel-backdrop" 
       x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @click="open = false; window.dispatchEvent(new CustomEvent('vault-panel:closed'))"
       class="eb-drawer-backdrop absolute inset-0 pointer-events-auto"></div>
  
  {* Panel *}
  <div id="vault-slide-panel" 
       x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="translate-x-full opacity-0"
       x-transition:enter-end="translate-x-0 opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="translate-x-0 opacity-100"
       x-transition:leave-end="translate-x-full opacity-80"
       class="fixed inset-y-0 right-0 w-full max-w-2xl eb-drawer eb-drawer--wide pointer-events-auto">
    <div class="h-full flex flex-col"
        data-modulelink="{$modulelink}" data-serviceid="{$serviceid}" data-username="{$username}">
    
    {* Header with staggered fade-in *}
    <div class="eb-drawer-header"
         x-show="open"
         x-transition:enter="transition ease-out duration-300 delay-100"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
      <div>
        <h3 class="eb-drawer-title">Manage Storage Vault</h3>
        <div class="mt-0.5 text-xs text-[var(--eb-text-muted)]">Vault: <span id="vault-panel-name" class="font-mono text-[var(--eb-primary)]"></span></div>
      </div>
      <button id="vault-panel-close" 
              @click="open = false; window.dispatchEvent(new CustomEvent('vault-panel:closed'))"
              class="eb-modal-close"
              aria-label="Close">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <input type="hidden" id="vault-mgr-id" value="" />

    {* Content with staggered fade-in *}
    <div class="flex-1 overflow-y-auto" x-data="{ tab: 'general' }">
      <div class="border-b px-5 pt-3"
           style="border-color: var(--eb-border-default);"
           x-show="open"
           x-transition:enter="transition ease-out duration-300 delay-150"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-100"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0">
        <nav class="flex space-x-4" aria-label="Tabs">
          <a href="#" @click.prevent="tab='general'" :class="tab==='general' ? 'text-[var(--eb-primary)] border-[var(--eb-primary)]' : 'text-[var(--eb-text-primary)] border-transparent hover:text-[var(--eb-text-primary)]'" class="px-1 pb-2 border-b-2 text-sm font-medium transition">General</a>
          <a href="#" @click.prevent="tab='retention'" :class="tab==='retention' ? 'text-[var(--eb-primary)] border-[var(--eb-primary)]' : 'text-[var(--eb-text-primary)] border-transparent hover:text-[var(--eb-text-primary)]'" class="px-1 pb-2 border-b-2 text-sm font-medium transition">Retention</a>
          <a href="#" @click.prevent="tab='danger'" :class="tab==='danger' ? 'text-[var(--eb-danger-text)] border-[var(--eb-danger-strong)]' : 'text-[var(--eb-text-primary)] border-transparent hover:text-[var(--eb-text-primary)]'" class="px-1 pb-2 border-b-2 text-sm font-medium transition">Danger zone</a>
        </nav>
      </div>

      <div
        class="pointer-events-none"
        style="position: fixed; right: 16px; bottom: 16px; z-index: 12010;"
        x-data="{
          open:false,
          type:'success',
          message:'',
          timer:null,
          show(detail){
            if (this.timer) { clearTimeout(this.timer); this.timer = null; }
            this.type = (detail && detail.type) ? String(detail.type) : 'success';
            this.message = (detail && detail.message) ? String(detail.message) : 'Saved.';
            this.open = true;
            this.timer = setTimeout(() => { this.open = false; this.timer = null; }, 2600);
          }
        }"
        @retention:toast.window="show($event.detail)"
        @vault:toast.window="show($event.detail)"
      >
        <div
          x-show="open"
          x-transition.opacity.duration.200ms
          class="pointer-events-auto rounded px-4 py-2 text-sm shadow"
          style="color: #fff;"
          :class="type === 'success'
            ? 'bg-[var(--eb-success-strong)]'
            : (type === 'warning'
              ? 'bg-[var(--eb-warning-text)]'
              : 'bg-[var(--eb-danger-strong)]')"
          x-text="message"
        ></div>
      </div>

      <!-- General Tab -->
      <div x-show="tab==='general'" x-transition class="px-5 py-5 space-y-6">
        <!-- Name -->
        <div>
          <label class="eb-field-label">Vault name</label>
          <input id="vault-mgr-name" type="text" class="eb-input w-full" placeholder="Vault name" />
        </div>
        <!-- Quota -->
        <div class="space-y-3">
          <label class="eb-field-label !mb-0">Quota</label>
          <div class="flex items-center gap-2">
            <input id="vault-quota-unlimited2" type="checkbox" class="eb-checkbox">
            <span class="text-sm text-[var(--eb-text-primary)]">Unlimited</span>
          </div>
          <div class="flex items-center gap-2">
            <input id="vault-quota-size2" type="number" class="eb-input w-40" placeholder="0" />
            <!-- Alpine unit dropdown -->
            <div class="relative" x-data="{ open:false, unit:'GB' }" @click.away="open=false">
              <input type="hidden" id="vault-quota-unit2" :value="unit">
              <button type="button" @click="open=!open" class="eb-menu-trigger w-28 pr-8">
                <span x-text="unit"></span>
                <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-[var(--eb-text-muted)]">
                  <svg class="h-4 w-4 ml-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                </span>
              </button>
              <div x-show="open" x-transition class="eb-menu absolute z-10 mt-1 w-full">
                <ul class="py-1 text-sm text-[var(--eb-text-primary)]">
                  <li><a href="#" class="eb-menu-option" @click.prevent="unit='GB'; open=false">GB</a></li>
                  <li><a href="#" class="eb-menu-option" @click.prevent="unit='TB'; open=false">TB</a></li>
                </ul>
              </div>
            </div>
          </div>
          <div class="text-xs text-[var(--eb-text-muted)]">Changes apply to this vault only.</div>
        </div>
        <div class="flex justify-end border-t pt-4" style="border-color: var(--eb-border-default);">
          <button id="vault-save-all" class="eb-btn eb-btn-success">Save</button>
        </div>
      </div>
              
        <!-- Retention Tab -->
        <div x-show="tab==='retention'" x-transition id="vault-retention-tab" class="px-5 py-5" x-data="retention()" @retention:update.window="state.override=$event.detail.override; state.mode=$event.detail.mode; state.ranges=$event.detail.ranges; state.defaultMode=$event.detail.defaultMode; state.defaultRanges=$event.detail.defaultRanges">
          <h4 class="mb-3 text-[var(--eb-text-primary)] font-semibold">Retention</h4>

          <!-- Status callout -->
          <div class="mb-3">
            <div x-show="!state.override" class="eb-badge eb-badge--neutral gap-2 px-3 py-1.5 text-sm" title="This vault follows the account's default retention policy.">
              <svg class="size-4 opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
              <span>Using account-level policy</span>
            </div>
            <!-- Account policy preview -->
            <div x-show="showAccountPolicy" class="mb-3 rounded-xl border p-3" style="border-color: var(--eb-border-default); background: var(--eb-bg-overlay);">
              <div class="flex items-center justify-between mb-2">
                <div class="font-medium text-[var(--eb-text-primary)]">Account-level policy</div>
                <button class="text-sm text-[var(--eb-text-primary)] transition hover:opacity-80" @click="showAccountPolicy=false">Close</button>
              </div>
              <ul class="list-disc space-y-1 pl-5 text-sm text-[var(--eb-text-primary)]" x-html="formattedDefaultPolicyLines().join('')"></ul>
            </div>
            <div x-show="state.override" class="eb-badge eb-badge--warning gap-2 px-3 py-1.5 text-sm" title="This vault uses its own retention rules instead of the account default.">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
              </svg>          
              <span>This will have a custom policy (overrides default)</span>
            </div>
          </div>

          <!-- Override toggle -->
          <div class="flex items-center gap-2 mb-3">
            <input id="ret-override" type="checkbox" class="eb-checkbox" x-model="state.override">
            <label for="ret-override" class="text-sm text-[var(--eb-text-secondary)]">Override default retention for this vault</label>
          </div>

          <!-- Builder when override ON -->
          <template x-if="state.override">
            <div class="space-y-3">
            <!-- Mode select with helper text -->
            <div class="mb-2">
              <label class="eb-field-label">Mode</label>
              <select x-model.number="state.mode" class="eb-select w-96">
                <option value="801">Keep everything</option>
                <option value="802">Keep only backups that match these rules</option>
              </select>
              <p class="mt-1 text-xs text-[var(--eb-text-muted)]" x-show="state.mode===802">Backups are kept if they match any rule below. Backups that match none of the rules will be deleted.</p>
            </div>
          
            <!-- Warning for keep everything -->
            <div x-show="state.mode===801" class="mb-3 rounded-xl border p-4" style="border-color: var(--eb-danger-border); background: var(--eb-danger-soft);">
              <div class="flex gap-3">
                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                <div class="text-[var(--eb-danger-text)]">
                  <p class="font-semibold">Warning: "Keep everything" keeps every backup forever.</p>
                  <p class="text-sm opacity-90">No data is ever removed from this vault. Storage usage—and your bill—will grow without limit. Choose this only if you fully understand the cost.</p>
                </div>
              </div>
            </div>

            <!-- Rules card editor -->
            <div class="space-y-3" x-data="{ editing:null }">
              <!-- New rule composer -->
              <div x-show="editing===null" class="rounded-xl border border-dashed p-3" style="border-color: var(--eb-border-default);">
                <p class="mb-2 font-medium text-[var(--eb-text-primary)]">Add a rule</p>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Type</label>
                    <select class="eb-select w-full" x-model.number="newRange.Type">
                      <option value="900">Most recent X jobs</option>
                      <option value="901">Newer than date</option>
                      <option value="902">Jobs since (relative)</option>
                      <option value="903">First job for last X days</option>
                      <option value="905">First job for last X months</option>
                      <option value="906">First job for last X weeks</option>
                      <option value="911">First job for last X years</option>
                    </select>
                  </div>
                  <div x-show="[900,907,908,909,910].includes(newRange.Type)"><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Jobs</label><input type="number" x-model.number="newRange.Jobs" class="eb-input w-full" placeholder="e.g., 7"></div>
                  <div x-show="newRange.Type===901"><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Date</label><input type="datetime-local" @change="newRange.Timestamp=(Date.parse($event.target.value)/1000)|0" class="eb-input w-full"></div>
                  <template x-if="newRange.Type===902">
                    <div class="grid grid-cols-2 gap-3 col-span-2">
                      <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Days</label><input type="number" x-model.number="newRange.Days" class="eb-input w-full"></div>
                      <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Weeks</label><input type="number" x-model.number="newRange.Weeks" class="eb-input w-full"></div>
                      <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Months</label><input type="number" x-model.number="newRange.Months" class="eb-input w-full"></div>
                      <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Years</label><input type="number" x-model.number="newRange.Years" class="eb-input w-full"></div>
                    </div>
                  </template>
                  <div x-show="newRange.Type===903"><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Days</label><input type="number" x-model.number="newRange.Days" class="eb-input w-full"></div>
                  <div x-show="newRange.Type===905"><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Months</label><input type="number" min="1" x-model.number="newRange.Months" class="eb-input w-full"><label class="mt-1 block text-xs text-[var(--eb-text-muted)]">On day</label><input type="number" min="1" max="31" x-model.number="newRange.MonthOffset" class="eb-input w-full"></div>
                  <div x-show="newRange.Type===906" class="grid grid-cols-2 gap-3 col-span-2">
                    <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Weeks</label><input type="number" min="1" x-model.number="newRange.Weeks" class="eb-input w-full"></div>
                    <div x-data="{ open:false }" class="relative">
                      <label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Week day</label>
                      <button type="button" @click="open=!open" @keydown.escape.prevent.stop="open=false" class="eb-menu-trigger flex w-full items-center justify-between">
                        <span x-text="weekdayLabel(newRange.WeekOffset)"></span>
                        <svg class="size-4 opacity-70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.937a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                      </button>
                      <div x-show="open" @click.outside="open=false" class="eb-menu absolute z-20 mt-1 w-full overflow-hidden">
                        <template x-for="(dayLabel, dayValue) in weekDays" :key="'new-weekday-'+dayValue">
                          <button type="button" @click="newRange.WeekOffset=dayValue; open=false" class="eb-menu-item w-full text-left text-sm" x-text="dayLabel"></button>
                        </template>
                      </div>
                    </div>
                  </div>
                  <div x-show="newRange.Type===911"><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Years</label><input type="number" min="1" x-model.number="newRange.Years" class="eb-input w-full"><label class="mt-1 block text-xs text-[var(--eb-text-muted)]">On month</label><input type="number" min="1" max="12" x-model.number="newRange.YearOffset" class="eb-input w-full"></div>
                </div>
                <div class="mt-3 flex justify-end">
                  <button class="eb-btn eb-btn-success eb-btn-sm" @click="addRangeFromNew()">Add rule</button>
                </div>
              </div>

              <template x-if="(state.ranges || []).length===0">
                <div class="rounded-xl border p-3 text-sm text-[var(--eb-text-muted)]" style="border-color: var(--eb-border-default); background: color-mix(in srgb, var(--eb-bg-overlay) 60%, transparent);">
                  No rules yet. Add a rule above to define this vault's custom retention behavior.
                </div>
              </template>

              <template x-for="(r,i) in state.ranges" :key="i">
                <div :class="editing===i ? 'ring-1 ring-[var(--eb-ring)]' : ''" class="rounded-xl border p-3 shadow-sm" style="border-color: var(--eb-border-default); background: var(--eb-bg-overlay);">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="mb-1 text-[11px] uppercase tracking-wide text-[var(--eb-text-muted)]">Retention type</div>
                      <span class="eb-badge eb-badge--neutral rounded-md px-2 py-0.5 text-xs" x-text="labelFor(r.Type)"></span>
                      <p class="mt-2 text-sm leading-5 text-[var(--eb-text-primary)]" x-text="summaryFor(r).replace('[' + labelFor(r.Type) + '] ', '')"></p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                      <button class="cursor-pointer text-sm text-[var(--eb-primary)] transition hover:opacity-80" @click="editing = (editing===i?null:i)"><span x-text="editing===i ? 'Close' : 'Edit'"></span></button>
                      <button class="cursor-pointer text-sm text-[var(--eb-danger-text)] transition hover:opacity-80" @click="removeRange(i)">Remove</button>
                    </div>
                  </div>
                  <div x-show="editing===i" x-transition class="mt-3 border-t pt-3" style="border-color: var(--eb-border-default);">
                    <div class="grid grid-cols-2 gap-3">
                      <!-- Type select -->
                      <div>
                        <label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Type</label>
                        <select class="eb-select w-full" x-model.number="r.Type">
                          <option value="900">Most recent X jobs</option>
                          <option value="901">Newer than date</option>
                          <option value="902">Jobs since (relative)</option>
                          <option value="903">First job for last X days</option>
                          <option value="905">First job for last X months</option>
                          <option value="906">First job for last X weeks</option>
                          <option value="911">First job for last X years</option>
                        </select>
                      </div>
                      <!-- Jobs -->
                      <div x-show="[900,907,908,909,910].includes(r.Type)">
                        <label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Jobs</label>
                        <input type="number" x-model.number="r.Jobs" class="eb-input w-full" placeholder="e.g., 7">
                      </div>
                      <!-- Timestamp -->
                      <div x-show="r.Type===901">
                        <label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Date</label>
                        <input type="datetime-local" @change="r.Timestamp=(Date.parse($event.target.value)/1000)|0" class="eb-input w-full">
                      </div>
                      <!-- Relative fields -->
                      <template x-if="r.Type===902">
                        <div class="grid grid-cols-2 gap-3 col-span-2">
                          <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Days</label><input type="number" x-model.number="r.Days" class="eb-input w-full"></div>
                          <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Weeks</label><input type="number" x-model.number="r.Weeks" class="eb-input w-full"></div>
                          <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Months</label><input type="number" x-model.number="r.Months" class="eb-input w-full"></div>
                          <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Years</label><input type="number" x-model.number="r.Years" class="eb-input w-full"></div>
                          <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Week Offset</label><input type="number" min="0" max="6" x-model.number="r.WeekOffset" class="eb-input w-full"></div>
                          <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Month Offset</label><input type="number" min="1" max="31" x-model.number="r.MonthOffset" class="eb-input w-full"></div>
                          <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Year Offset</label><input type="number" min="0" x-model.number="r.YearOffset" class="eb-input w-full"></div>
                        </div>
                      </template>
                      <!-- Days/Weeks/Months/Years singular fields -->
                      <div x-show="r.Type===903"><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Days</label><input type="number" x-model.number="r.Days" class="eb-input w-full"></div>
                      <div x-show="r.Type===905"><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Months</label><input type="number" min="1" x-model.number="r.Months" class="eb-input w-full"><label class="mt-1 block text-xs text-[var(--eb-text-muted)]">On day</label><input type="number" min="1" max="31" x-model.number="r.MonthOffset" class="eb-input w-full"></div>
                      <div x-show="r.Type===906" class="grid grid-cols-2 gap-3 col-span-2">
                        <div><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Weeks</label><input type="number" min="1" x-model.number="r.Weeks" class="eb-input w-full"></div>
                        <div x-data="{ open:false }" class="relative">
                          <label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Week day</label>
                          <button type="button" @click="open=!open" @keydown.escape.prevent.stop="open=false" class="eb-menu-trigger flex w-full items-center justify-between">
                            <span x-text="weekdayLabel(r.WeekOffset)"></span>
                            <svg class="size-4 opacity-70" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.937a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
                          </button>
                          <div x-show="open" @click.outside="open=false" class="eb-menu absolute z-20 mt-1 w-full overflow-hidden">
                            <template x-for="(dayLabel, dayValue) in weekDays" :key="'edit-weekday-'+i+'-'+dayValue">
                              <button type="button" @click="r.WeekOffset=dayValue; open=false" class="eb-menu-item w-full text-left text-sm" x-text="dayLabel"></button>
                            </template>
                          </div>
                        </div>
                      </div>
                      <div x-show="r.Type===911"><label class="mb-1 block text-xs text-[var(--eb-text-muted)]">Years</label><input type="number" min="1" x-model.number="r.Years" class="eb-input w-full"><label class="mt-1 block text-xs text-[var(--eb-text-muted)]">On month</label><input type="number" min="1" max="12" x-model.number="r.YearOffset" class="eb-input w-full"></div>
                    </div>
                  </div>
                </div>
              </template>
            </div>
            </div>
          </template>

          <!-- When override OFF, show compact summary of inherited policy -->
          <template x-if="!state.override">
            <div class="text-sm text-[var(--eb-text-secondary)]">This vault follows the account default policy.</div>
          </template>

          <!-- Sticky summary -->
          <div class="sticky bottom-0 mt-4 rounded-xl border p-3 backdrop-blur" style="border-color: var(--eb-border-default); background: color-mix(in srgb, var(--eb-bg-overlay) 88%, transparent);">
            <p class="mb-1 font-medium text-[var(--eb-text-primary)]">Effective policy:</p>
            <div class="mt-2 space-y-2">
              <template x-if="(state.override ? state.mode : state.defaultMode) === 801">
                <div class="space-y-1">
                  <span class="eb-badge eb-badge--neutral rounded-md px-2 py-0.5 text-xs">Mode</span>
                  <p class="text-sm leading-5 text-[var(--eb-text-primary)]">Keep everything (no deletions)</p>
                </div>
              </template>
              <template x-if="(state.override ? state.mode : state.defaultMode) !== 801">
                <template x-for="(r,i) in ((state.override ? state.ranges : state.defaultRanges) || [])" :key="'effective-rule-'+i">
                  <div class="space-y-1">
                    <span class="eb-badge eb-badge--neutral rounded-md px-2 py-0.5 text-xs" x-text="labelFor(r.Type)"></span>
                    <p class="text-sm leading-5 text-[var(--eb-text-primary)]" x-text="summaryFor(r).replace('[' + labelFor(r.Type) + '] ', '')"></p>
                  </div>
                </template>
              </template>
              <template x-if="(state.override ? state.mode : state.defaultMode) !== 801 && (((state.override ? state.ranges : state.defaultRanges) || []).length === 0)">
                <p class="text-sm text-[var(--eb-text-muted)]">No retention rules configured.</p>
              </template>
            </div>
          </div>
          <!-- Save button for retention -->
          <div class="mt-4 flex justify-end">
            <button id="vault-retention-save" class="eb-btn eb-btn-success">Save</button>
          </div>

        </div>

        <!-- Danger Tab -->
        <div x-show="tab==='danger'" x-transition class="px-5 py-5 space-y-4">
          <h4 class="font-semibold text-[var(--eb-danger-text)]">Danger zone</h4>
          <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4">
            <div class="flex items-start gap-3">
              <svg class="mt-0.5 h-5 w-5 shrink-0 text-[var(--eb-danger-text)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
              </svg>
              <div>
                <div class="font-medium text-[var(--eb-danger-text)]">Delete this vault</div>
                <p class="mt-1 text-sm text-[var(--eb-text-primary)]">Deleting a vault cannot be undone. All data will be permanently lost.</p>
              </div>
            </div>
          </div>
          <button id="vault-delete" class="eb-btn eb-btn-danger">Delete Vault</button>
          <div id="vault-delete-confirm" class="hidden space-y-3 rounded-xl border p-4" style="border-color: var(--eb-border-default); background: color-mix(in srgb, var(--eb-bg-overlay) 70%, transparent);">
            <div class="text-sm font-semibold text-[var(--eb-text-primary)]">Confirm your account password</div>
            <div class="text-xs text-[var(--eb-text-muted)]">This is the password you use to sign in to your eazyBackup Client Area.</div>
            <input id="vault-delete-password" type="password" class="eb-input is-error w-full" placeholder="Account password" />
            <div class="flex justify-end gap-3 pt-2">
              <button id="vault-delete-cancel" class="eb-btn eb-btn-secondary">Cancel</button>
              <button id="vault-delete-confirm-btn" class="eb-btn eb-btn-danger">Confirm delete</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{* Reset Password Slide Drawer *}
<div x-data="resetPasswordDrawer()" 
     @eb-reset-password.window="openDrawer($event.detail)"
     class="fixed inset-0 z-[10060] pointer-events-none">
  
  {* Backdrop overlay *}
  <div x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @click="closeDrawer()"
       class="eb-drawer-backdrop absolute inset-0 pointer-events-auto"></div>
  
  {* Drawer Panel *}
  <div x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="translate-x-full opacity-0"
       x-transition:enter-end="translate-x-0 opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="translate-x-0 opacity-100"
       x-transition:leave-end="translate-x-full opacity-80"
       class="fixed inset-y-0 right-0 z-[10060] w-full sm:max-w-[440px] eb-drawer eb-drawer--narrow pointer-events-auto">
    
    <div class="h-full flex flex-col">
      {* Header *}
      <div class="eb-drawer-header"
           x-show="open"
           x-transition:enter="transition ease-out duration-300 delay-100"
           x-transition:enter-start="opacity-0 -translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="eb-drawer-title">Reset Password</div>
            <div class="mt-0.5 text-xs text-[var(--eb-text-muted)]">Set a new password for <span class="font-mono text-[var(--eb-primary)]" x-text="username"></span></div>
          </div>
          <button type="button"
                  class="eb-modal-close"
                  @click="closeDrawer()"
                  aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
      
      {* Content *}
      <div class="eb-drawer-body">
        <div class="space-y-5"
             x-show="open"
             x-transition:enter="transition ease-out duration-300 delay-150"
             x-transition:enter-start="opacity-0 translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
          
          {* Info text *}
          <div class="text-sm text-[var(--eb-text-primary)]">
            Enter a new password below, or generate a secure password automatically.
          </div>
          
          {* New Password Field *}
          <div class="space-y-2"
               x-show="open"
               x-transition:enter="transition ease-out duration-300 delay-200"
               x-transition:enter-start="opacity-0 translate-y-2"
               x-transition:enter-end="opacity-100 translate-y-0">
            <label for="rp-new-password" class="eb-field-label">New Password</label>
            <div class="flex items-stretch gap-2">
              <div class="relative flex-1">
                <input id="rp-new-password"
                       :type="showPassword ? 'text' : 'password'"
                       x-model="password"
                       @input="checkMatch()"
                       placeholder="Enter new password (min 8 chars)"
                       class="eb-input w-full pr-10" />
                <button type="button"
                        @click="showPassword = !showPassword"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--eb-text-muted)] transition hover:text-[var(--eb-text-primary)]"
                        :title="showPassword ? 'Hide password' : 'Show password'">
                  <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                  </svg>
                  <svg x-show="showPassword" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                  </svg>
                </button>
              </div>
              <button type="button"
                      @click="generatePassword()"
                      class="eb-btn eb-btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                </svg>
                Generate
              </button>
            </div>
          </div>
          
          {* Confirm Password Field *}
          <div class="space-y-2"
               x-show="open"
               x-transition:enter="transition ease-out duration-300 delay-[250ms]"
               x-transition:enter-start="opacity-0 translate-y-2"
               x-transition:enter-end="opacity-100 translate-y-0">
            <label for="rp-confirm-password" class="eb-field-label">Confirm Password</label>
            <input id="rp-confirm-password"
                   :type="showPassword ? 'text' : 'password'"
                   x-model="confirmPassword"
                   @input="checkMatch()"
                   placeholder="Re-enter the password"
                   class="eb-input w-full"
                   :class="confirmPassword && !passwordsMatch ? 'is-error text-[var(--eb-danger-text)]' : ''" />
            
            {* Match indicator *}
            <div class="flex items-center gap-2 text-xs h-5">
              <template x-if="confirmPassword && passwordsMatch">
                <div class="flex items-center gap-1.5 text-[var(--eb-success-text)]">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                  </svg>
                  Passwords match
                </div>
              </template>
              <template x-if="confirmPassword && !passwordsMatch">
                <div class="flex items-center gap-1.5 text-[var(--eb-danger-text)]">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                  </svg>
                  Passwords do not match
                </div>
              </template>
            </div>
          </div>
          
          {* Password requirements *}
          <div class="rounded-xl border p-3"
               style="border-color: var(--eb-border-default); background: color-mix(in srgb, var(--eb-bg-overlay) 60%, transparent);"
               x-show="open"
               x-transition:enter="transition ease-out duration-300 delay-300"
               x-transition:enter-start="opacity-0 translate-y-2"
               x-transition:enter-end="opacity-100 translate-y-0">
            <div class="mb-2 text-xs font-medium text-[var(--eb-text-primary)]">Password requirements</div>
            <ul class="space-y-1 text-xs text-[var(--eb-text-muted)]">
              <li class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" :class="password.length >= 8 ? 'text-[var(--eb-success-text)]' : 'text-[var(--eb-text-muted)]'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span :class="password.length >= 8 ? 'text-[var(--eb-text-primary)]' : ''">Minimum 8 characters</span>
              </li>              
            </ul>
          </div>
          
        </div>
      </div>
      
      {* Footer with action buttons *}
      <div class="eb-drawer-footer"
           x-show="open"
           x-transition:enter="transition ease-out duration-300 delay-[350ms]"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-100"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-end gap-3">
          <button type="button"
                  class="eb-btn eb-btn-secondary"
                  @click="closeDrawer()">
            Cancel
          </button>
          <button type="button"
                  class="eb-btn eb-btn-primary"
                  :disabled="saving || !canSubmit"
                  @click="submitReset()">
            <svg x-show="saving" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span x-text="saving ? 'Resetting…' : 'Reset Password'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

{literal}
<script>
function resetPasswordDrawer() {
  return {
    open: false,
    username: '',
    serviceid: '',
    password: '',
    confirmPassword: '',
    showPassword: false,
    passwordsMatch: false,
    saving: false,
    
    get canSubmit() {
      return this.password.length >= 8 && this.passwordsMatch;
    },
    
    openDrawer(detail) {
      this.username = (detail && detail.username) || '';
      this.serviceid = (detail && (detail.serviceid || detail.serviceId)) || '';
      this.password = '';
      this.confirmPassword = '';
      this.showPassword = false;
      this.passwordsMatch = false;
      this.saving = false;
      this.open = true;
      this.$nextTick(() => {
        document.getElementById('rp-new-password')?.focus();
      });
    },
    
    closeDrawer() {
      this.open = false;
    },
    
    checkMatch() {
      this.passwordsMatch = this.password.length > 0 && this.password === this.confirmPassword;
    },
    
    generatePassword() {
      const lowers = 'abcdefghijkmnopqrstuvwxyz';
      const uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
      const digits = '23456789';
      const all = lowers + uppers + digits;
      const pick = (set) => set[Math.floor(Math.random() * set.length)];
      let out = pick(lowers) + pick(uppers) + pick(digits);
      for (let i = 0; i < 13; i++) out += pick(all);
      this.password = out;
      this.confirmPassword = out;
      this.showPassword = true;
      this.checkMatch();
    },
    
    async submitReset() {
      // Check passwords match
      if (!this.passwordsMatch) {
        window.showToast?.('Passwords do not match. Please try again.', 'error');
        return;
      }
      
      if (this.password.length < 8) {
        window.showToast?.('Password must be at least 8 characters.', 'warning');
        return;
      }
      
      this.saving = true;
      
      try {
        const form = new URLSearchParams();
        form.append('serviceId', String(this.serviceid));
        form.append('newpassword', String(this.password));
        
        const res = await fetch('modules/servers/comet/ajax/changepassword.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: form.toString()
        });
        const r = await res.json();
        
        if (r && r.result === 'success') {
          this.closeDrawer();
          // Show success modal with the new password
          this.showSuccessModal(this.password);
        } else {
          const msg = (r && (r.message || r.error)) || 'Password reset failed.';
          window.showToast?.(msg, 'error');
        }
      } catch (e) {
        window.showToast?.('Network error while resetting password.', 'error');
      } finally {
        this.saving = false;
      }
    },
    
    showSuccessModal(newPassword) {
      const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
      
      const overlay = document.createElement('div');
      overlay.className = 'fixed inset-0 z-[10070]';
      overlay.innerHTML = `
        <div class="eb-modal-backdrop absolute inset-0" data-cmd="ok"></div>
        <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="eb-modal eb-modal--confirm">
          <div class="eb-modal-header">
            <h3 class="eb-modal-title">Password Reset Successful</h3>
            <button class="eb-modal-close" data-cmd="ok" title="Close">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="eb-modal-body space-y-4">
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
              <div class="mb-2 text-xs uppercase tracking-wide text-[var(--eb-success-text)]">New Password</div>
              <div class="flex items-center gap-3">
                <div id="success-password-text" class="flex-1 select-all font-mono text-lg text-[var(--eb-text-primary)]">${escapeHtml(newPassword)}</div>
                <button id="success-copy-btn" type="button" class="eb-btn eb-btn-secondary eb-btn-icon" title="Copy">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                  </svg>
                </button>
              </div>
            </div>
            <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
              <div class="flex items-start gap-3">
                <svg class="h-5 w-5 shrink-0 text-amber-400 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M5.07 19h13.86A2 2 0 0021 17.2L13.93 4.8a2 2 0 00-3.86 0L3 17.2A2 2 0 005.07 19z"/>
                </svg>
                <div>
                  <div class="font-medium text-amber-300">Action needed</div>
                  <p class="mt-1 text-sm text-[var(--eb-text-primary)]">On each computer, <strong>close and reopen the eazyBackup client</strong>, then sign in with your <strong>new backup account password</strong> so future backups do not fail.</p>
                </div>
              </div>
            </div>
          </div>
          <div class="eb-modal-footer">
            <button class="eb-btn eb-btn-primary" data-cmd="ok">Done</button>
          </div>
        </div>
        </div>`;
      
      const cleanup = () => { try { overlay.remove(); } catch (_) {} };
      
      overlay.addEventListener('click', (e) => {
        const cmd = e.target.getAttribute && e.target.getAttribute('data-cmd');
        if (cmd === 'ok' || e.target === overlay) { cleanup(); }
      });
      
      document.body.appendChild(overlay);
      
      // Wire copy button
      setTimeout(() => {
        const btn = overlay.querySelector('#success-copy-btn');
        const tgt = overlay.querySelector('#success-password-text');
        if (btn && tgt) {
          btn.addEventListener('click', async () => {
            try {
              await navigator.clipboard.writeText(tgt.textContent || '');
              window.showToast?.('Password copied to clipboard.', 'success');
            } catch (_) {
              window.showToast?.('Copy failed.', 'error');
            }
          });
        }
      }, 0);
    }
  };
}
</script>
{/literal}

{* TOTP Modal *}
<div id="totp-modal" class="fixed inset-0 z-50 hidden">
  <div class="eb-modal-backdrop absolute inset-0"></div>
  <div class="relative flex min-h-full items-center justify-center p-4">
  <div class="eb-modal eb-modal--confirm">
    <div class="eb-modal-header">
      <h2 class="eb-modal-title">Two-Factor Authentication (TOTP)</h2>
      <button id="totp-modal-close" type="button" class="eb-modal-close">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="eb-modal-body space-y-4">
        <p id="totp-status" class="text-sm text-[var(--eb-text-primary)]"></p>
        <div class="flex justify-center">
          <img id="totp-qr-img" src="" alt="TOTP QR" class="max-h-56 rounded border" style="border-color: var(--eb-border-default);" />
        </div>
        <div class="break-words text-xs text-[var(--eb-text-muted)]">
          <a id="totp-otp-url" href="#" target="_blank" class="text-[var(--eb-primary)] transition hover:opacity-80"></a>
        </div>
        <div>
          <label class="eb-field-label">Enter 6-digit code</label>
          <input id="totp-code" type="text" inputmode="numeric" autocomplete="one-time-code" class="eb-input block w-full" placeholder="123456" />
        </div>
        <div id="totp-error" class="hidden text-sm text-[var(--eb-danger-text)]"></div>
      </div>
    <div class="eb-modal-footer">
      <button id="totp-confirm" class="eb-btn eb-btn-primary">Confirm</button>
    </div>
  </div>
  </div>
</div>

{* Expose endpoint + context for TOTP JS *}
<script>
window.EB_TOTP_ENDPOINT = '{$modulelink}&a=totp';
</script>
<!-- Vault Storage Breakdown Modal -->
<div id="vault-stats-modal" class="fixed inset-0 z-50 hidden">
  <div class="eb-modal-backdrop absolute inset-0"></div>
  <div class="relative flex min-h-full items-start justify-center p-6">
  <div class="eb-modal max-w-3xl">
    <div class="eb-modal-header">
      <h3 id="vsm-title" class="eb-modal-title">Vault usage</h3>
      <button id="vsm-close" class="eb-modal-close">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="eb-modal-body space-y-3">
      <div id="vsm-summary" class="text-sm text-[var(--eb-text-primary)]"></div>
      <div class="eb-table-shell overflow-hidden">
        <div class="grid grid-cols-12 gap-0 px-3 py-2 text-xs text-[var(--eb-text-primary)]" style="background: color-mix(in srgb, var(--eb-bg-overlay) 75%, transparent);">
          <div class="col-span-4">Size</div>
          <div class="col-span-8">Used by</div>
        </div>
        <div id="vsm-rows" class="max-h-80 overflow-y-auto divide-y divide-[var(--eb-border-default)]"></div>
      </div>
    </div>
  </div>
  </div>
  <input type="hidden" id="vsm-vault-id" value="" />
  <input type="hidden" id="vsm-size" value="" />
  <input type="hidden" id="vsm-ms" value="" />
  <input type="hidden" id="vsm-me" value="" />
  <input type="hidden" id="vsm-components" value="" />
  <input type="hidden" id="vsm-items-json" value='{if $protectedItems}{json_encode($protectedItems)}{/if}' />
</div>
<script>
// annotate body for JS context
try {
  document.body.setAttribute('data-eb-serviceid', '{$serviceid}');
  document.body.setAttribute('data-eb-username', '{$username}');
} catch (e) {}
</script>
<script>
window.EB_USER_ENDPOINT = '{$modulelink}&a=user-actions';
</script>
<script src="modules/addons/eazybackup/assets/js/userProfileTotp.js"></script>
<script src="modules/addons/eazybackup/assets/js/user-actions.js"></script>

{* Device slide-over panel *}
<div id="device-slide-panel-container"
     x-data="{ open: false }"
     @device-panel:open.window="open = true"
     @device-panel:close.window="open = false"
     class="fixed inset-0 z-[10060] pointer-events-none">
  
  {* Backdrop overlay *}
  <div id="device-panel-backdrop"
       x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       @click="open = false; window.dispatchEvent(new CustomEvent('device-panel:closed'))"
       class="eb-drawer-backdrop absolute inset-0 pointer-events-auto"></div>
  
  {* Panel *}
  <div id="device-slide-panel"
       x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="translate-x-full opacity-0"
       x-transition:enter-end="translate-x-0 opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="translate-x-0 opacity-100"
       x-transition:leave-end="translate-x-full opacity-80"
       class="fixed inset-y-0 right-0 w-full max-w-xl eb-drawer eb-drawer--wide pointer-events-auto">
    <div class="h-full flex flex-col">
      
      {* Header with staggered fade-in *}
      <div class="eb-drawer-header"
           x-show="open"
           x-transition:enter="transition ease-out duration-300 delay-100"
           x-transition:enter-start="opacity-0 -translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0">
        <div>
          <h3 id="device-panel-title" class="eb-drawer-title">Manage Device</h3>
          <div class="mt-0.5 text-xs text-[var(--eb-text-muted)]">Device: <span id="device-panel-name" class="font-mono text-[var(--eb-primary)]"></span></div>
        </div>
        <button id="device-panel-close"
                @click="open = false; window.dispatchEvent(new CustomEvent('device-panel:closed'))"
                class="eb-modal-close"
                aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      
      {* Content with tabs *}
      <div x-data="{ tab: 'device', vaultOpen:false }" class="flex-1 overflow-y-auto">
        <div class="border-b px-5 pt-3"
             style="border-color: var(--eb-border-default);"
             x-show="open"
             x-transition:enter="transition ease-out duration-300 delay-150"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
          <nav class="flex space-x-4" aria-label="Tabs">
            <a href="#" @click.prevent="tab='device'" :class="tab==='device' ? 'text-[var(--eb-primary)] border-[var(--eb-primary)]' : 'text-[var(--eb-text-primary)] border-transparent hover:text-[var(--eb-text-primary)]'" class="px-1 pb-2 border-b-2 text-sm font-medium transition">Device</a>
            <a href="#" @click.prevent="tab='vault'"  :class="tab==='vault'  ? 'text-[var(--eb-primary)] border-[var(--eb-primary)]' : 'text-[var(--eb-text-primary)] border-transparent hover:text-[var(--eb-text-primary)]'" class="px-1 pb-2 border-b-2 text-sm font-medium transition">Storage Vault</a>
          </nav>
        </div>
        <div x-show="tab==='device'" x-transition class="px-5 py-5 space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <button id="btn-run-backup" class="eb-btn eb-btn-primary">Run Backup...</button>
            <button id="open-restore" class="eb-btn eb-btn-primary">Restore...</button>
            <button id="btn-update-software" class="eb-btn eb-btn-secondary">Update Software</button>
            <div class="flex items-center gap-2">
              <input id="inp-rename-device" type="text" placeholder="New device name" class="eb-input flex-1"/>
              <button id="btn-rename-device" class="eb-btn eb-btn-secondary">Rename</button>
            </div>
            <button id="btn-revoke-device" class="eb-btn eb-btn-danger">Revoke</button>
            <button id="btn-uninstall-software" class="eb-btn eb-btn-danger">Uninstall Software</button>
          </div>
          <div class="text-xs text-[var(--eb-text-muted)]">Note: Some actions require the device to be online.</div>

          <div class="mt-4 hidden border-t pt-4" style="border-color: var(--eb-border-default);" x-data="{ piOpen:false, piLabel:'Choose a protected item…', piId:'', vOpen:false }">
            <h4 class="mb-3 font-semibold text-[var(--eb-text-primary)]">Run Backup</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div class="relative">
                <label class="eb-field-label">Protected Item</label>
                <button type="button" id="pi-menu-button" @click="piOpen=!piOpen" class="eb-menu-trigger">
                  <span id="pi-selected" x-text="piLabel"></span>
                </button>
                <div id="pi-menu" x-show="piOpen" x-transition class="eb-menu absolute mt-1 max-h-56 w-full overflow-y-auto z-10">
                  <ul id="pi-list" class="py-1 text-sm text-[var(--eb-text-primary)]">
                    <li><span class="block px-3 py-2 text-[var(--eb-text-muted)]">Loading…</span></li>
                  </ul>
                </div>
              </div>
              <div class="relative">
                <label class="eb-field-label">Storage Vault</label>
                <button id="vault-menu-button-2" type="button" @click="vOpen=!vOpen" class="eb-menu-trigger">
                  <span id="vault-selected-2">Choose a vault…</span>
                </button>
                <div id="vault-menu-2" x-show="vOpen" x-transition class="eb-menu absolute mt-1 max-h-56 w-full overflow-y-auto z-10">
                  <ul class="py-1 text-sm text-[var(--eb-text-primary)]">
                    {foreach from=$vaults item=vault key=vaultId}
                      <li><a href="#" class="eb-menu-option" data-vault-id="{$vaultId}" data-vault-name="{$vault.Description}">{$vault.Description}</a></li>
                    {/foreach}
                  </ul>
                </div>
              </div>
            </div>
            <div class="mt-4 flex justify-end">
              <button id="btn-run-backup-exec" class="eb-btn eb-btn-primary">Run Backup</button>
            </div>
          </div>
        </div>
        <div x-show="tab==='vault'" x-transition class="px-5 py-5 space-y-4">
          <div class="relative">
            <label class="eb-field-label">Select Storage Vault</label>
            <button id="vault-menu-button" type="button" class="eb-menu-trigger">
              <span id="vault-selected">Choose a vault…</span>
            </button>
            <div id="vault-menu" class="eb-menu absolute mt-1 hidden max-h-56 w-full overflow-y-auto z-10">
              <ul class="py-1 text-sm text-[var(--eb-text-primary)]">
                {foreach from=$vaults item=vault key=vaultId}
                  <li><a href="#" class="eb-menu-option" data-vault-id="{$vaultId}" data-vault-name="{$vault.Description}">{$vault.Description}</a></li>
                {/foreach}
              </ul>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <button id="btn-apply-retention" class="eb-btn eb-btn-secondary">Apply retention rules now</button>
            <button id="btn-reindex-vault" class="eb-btn eb-btn-secondary">Reindex (locks vault)</button>
          </div>
          <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3">
            <div class="flex items-start gap-3">
              <svg class="h-5 w-5 shrink-0 text-amber-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
              </svg>
              <p class="text-sm text-[var(--eb-text-primary)]">Reindex may take many hours and locks the vault during the operation.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.EB_DEVICE_ENDPOINT = '{$modulelink}&a=device-actions';
</script>
<script src="modules/addons/eazybackup/templates/assets/js/ui.js"></script>
<script src="modules/addons/eazybackup/assets/js/device-actions.js"></script>
<script>
// Lightweight caller for quota controls; reuses the same endpoint as device-actions
async function call(action, extra){
  try {
    const body = Object.assign({ action, serviceId: '{$serviceid}', username: '{$username}' }, (extra||{}));
    const res = await fetch(window.EB_DEVICE_ENDPOINT, { method:'POST', headers:{ 'Content-Type':'application/json' }, body: JSON.stringify(body) });
    return await res.json();
  } catch(e){ return { status:'error', message:'Network error' }; }
}
// Account Name inline controller
function accNameCtrl(){
  return {
    name:'',
    saving:false,
    async init(){
      const r = await call('piProfileGet', { username: '{$username}' });
      if (r && r.status === 'success' && r.profile) {
        this.name = (r.profile.AccountName ?? '');
      }
    },
    async save(){
      if (this.saving) return;
      this.saving = true;
      try {
        const r = await call('piProfileUpdate', { AccountName: this.name });
        if (r && r.status === 'success') {
          try { window.showToast?.('Account Name updated', 'success'); } catch(_){}
        } else {
          try { window.showToast?.((r && r.message) || 'Failed to update Account Name', 'error'); } catch(_){}
        }
      } finally {
        this.saving = false;
      }
    }
  }
}
function quotaCtrl(){
  return {
    dev:{ enabled:false, count:1 },
    m365:{ enabled:false, count:1 },
    hv:{ enabled:false, count:1 },
    vmw:{ enabled:false, count:1 },
    _profile:null,
    async init(){
      const r = await call('piProfileGet', { username: '{$username}' });
      if (!r || r.status !== 'success' || !r.profile) { try { window.showToast?.('Could not load profile.', 'error'); } catch(_){} return; }
      this._profile = r.profile;
      this.resetFromProfile();
    },
    resetFromProfile(){
      const p = this._profile || {};
      const map = (v) => ({ enabled: !!(v && v > 0), count: (v && v > 0) ? v : 0 });
      this.dev = map(p.MaximumDevices ?? 0);
      this.m365 = map(p.QuotaOffice365ProtectedAccounts ?? 0);
      this.hv = map(p.QuotaHyperVGuests ?? 0);
      this.vmw = map(p.QuotaVMwareGuests ?? 0);
    },    
    async save(){
      const num = (en, c) => en ? Math.max(1, parseInt(c || 1)) : 0;
      const payload = {
        username: '{$username}',
        MaximumDevices: num(this.dev.enabled, this.dev.count),
        QuotaOffice365ProtectedAccounts: num(this.m365.enabled, this.m365.count),
        QuotaHyperVGuests: num(this.hv.enabled, this.hv.count),
        QuotaVMwareGuests: num(this.vmw.enabled, this.vmw.count),
      };
      const res = await call('piProfileUpdate', payload);
      if (res && res.status === 'success') { try { window.showToast?.('Quota settings updated.', 'success'); } catch(_){}; }
      else { try { window.showToast?.((res && res.message) || 'Failed to update quotas.', 'error'); } catch(_){} }
      // refresh profile to ensure UI matches persisted state
      const r = await call('piProfileGet', { username: '{$username}' });
      if (r && r.status === 'success' && r.profile) { this._profile = r.profile; this.resetFromProfile(); }
    }
  }
}
</script>
<script src="modules/addons/eazybackup/assets/js/eazybackup-ui-helpers.js" defer></script>
<script src="modules/addons/eazybackup/assets/js/job-reports.js" defer></script>
<script>
  try { window.EB_MODULE_LINK = '{$modulelink}'; } catch(e) {}
  try {
    var elCard = document.getElementById('eb-storage-card');
    if (elCard) { elCard.setAttribute('data-username', '{$username}'); }
  } catch(e) {}
  try {
    var sc = document.createElement('script');
    sc.src = 'modules/addons/eazybackup/assets/js/storage-history.js';
    sc.defer = true;
    document.currentScript?.parentNode?.insertBefore(sc, document.currentScript.nextSibling);
  } catch(e) {}
</script>
{include file="modules/addons/eazybackup/templates/console/partials/job-report-modal.tpl"}
<script>
try {
  window.EB_JOBREPORTS_ENDPOINT = '{$modulelink}&a=job-reports';
  const attachJobs = () => {
    try {
      const f = window.jobReportsFactory && window.jobReportsFactory({});
      if (!f || !f.makeJobsTable) return;
      const serviceId = '{$serviceid}';
      const username = '{$username}';
      const table = document.getElementById('jobs-table');
      if (!table) return;
      const api = f.makeJobsTable(table, {
        serviceId: serviceId,
        username: username,
        totalEl: document.getElementById('jobs-total'),
        pagerEl: document.getElementById('jobs-pager'),
        searchInput: document.getElementById('jobs-search'),
      });
      api && api.reload && api.reload();
    } catch (e) {}
  };
  if (window.jobReportsFactory) attachJobs();
  else document.addEventListener('jobReports:ready', attachJobs, { once: true });
} catch (e) {}
</script>

          </div><!-- /Tab Content wrapper -->
        </main><!-- /Main Content Area -->
      </div><!-- /Flex container -->
    </div><!-- /App Shell -->

<!-- Restore Wizard Modal -->
<div id="restore-wizard" class="fixed inset-0 z-50 hidden">
  <div class="eb-modal-backdrop absolute inset-0"></div>
  <div class="relative flex min-h-full items-start justify-center p-6">
  <div class="eb-modal max-w-4xl">
    <div class="eb-modal-header">
      <h3 class="eb-modal-title flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v6.75m0 0-3-3m3 3 3-3m-8.25 6a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0  0 1 18 19.5H6.75Z" />
        </svg>
        Restore Wizard
      </h3>
      <button id="restore-close" class="eb-modal-close">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="eb-modal-body">
      <div id="restore-step1">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="mb-1 text-sm text-[var(--eb-text-primary)]">Select an online device to control</div>
            <div class="text-xs text-[var(--eb-text-muted)]">Using the device selected in the Devices panel.</div>
          </div>
          <div x-data="{ open:false }" class="relative">
            <label class="eb-field-label">Select a Storage Vault to restore from</label>
            <button id="rs-vault-menu-btn" type="button" @click="open=!open" class="eb-menu-trigger">
              <span id="rs-vault-selected-label">Choose a vault…</span>
            </button>
            <div id="rs-vault-menu-list" x-show="open" x-transition class="eb-menu absolute mt-1 max-h-56 w-full overflow-y-auto z-10">
              <ul class="py-1 text-sm text-[var(--eb-text-primary)]">
                {foreach from=$vaults item=vault key=vaultId}
                  <li>
                    <a href="#" class="eb-menu-option" data-rs-vault-id="{$vaultId}" data-rs-vault-name="{$vault.Description}" @click.prevent="open=false">{$vault.Description}</a>
                  </li>
                {/foreach}
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div id="restore-step2" class="hidden">
        <div class="space-y-3">
          <div class="text-sm text-[var(--eb-text-primary)]">Select a Protected Item to restore:</div>
          <div class="relative" x-data="{ open:false }">
            <label class="eb-field-label">Protected Item</label>
            <button id="rs-item-menu-btn" type="button" @click="open=!open" class="eb-menu-trigger">
              <span id="rs-item-selected-label">Choose a protected item…</span>
            </button>
            <div id="rs-item-menu-list" x-show="open" x-transition class="eb-menu absolute mt-1 max-h-60 w-full overflow-y-auto z-10">
              <ul class="py-1 text-sm text-[var(--eb-text-primary)]"></ul>
            </div>
          </div>
          <div>
            <div class="mb-1 text-sm text-[var(--eb-text-primary)]">Snapshots</div>
            <div id="rs-engine-friendly" class="mb-1 text-xs text-[var(--eb-text-muted)]"></div>
            <div id="rs-snapshots" class="max-h-60 overflow-y-auto rounded border bg-[var(--eb-bg-overlay)] text-sm text-[var(--eb-text-primary)]" style="border-color: var(--eb-border-default);"></div>
          </div>
        </div>
        <div id="rs-engine-hint" class="mt-3 text-xs text-[var(--eb-text-muted)]"></div>
      </div>

      <div id="restore-step3" class="hidden">
        <div id="rs-methods" class="mt-1">
          <div id="rs-method-title" class="mb-2 text-sm text-[var(--eb-text-primary)]"></div>
          <div id="rs-method-options" class="space-y-2 text-sm text-[var(--eb-text-primary)]"></div>
          <div class="mt-3 space-y-3">
            <div>
              <label class="eb-field-label">Destination path</label>
              <div class="flex gap-2">
                <input id="rs-dest" type="text" class="eb-input flex-1" placeholder="e.g. C:\\Restore">
                <button id="rs-browse" type="button" class="eb-btn eb-btn-secondary">Browse...</button>
              </div>
            </div>
            <div id="rs-archive-name-wrap" class="hidden">
              <label class="eb-field-label">Archive file name</label>
              <input id="rs-archive-name" type="text" class="eb-input w-full" placeholder="backup.zip">
              <div class="mt-1 text-xs text-[var(--eb-text-muted)]">Enter the output archive filename (e.g., backup.zip).</div>
            </div>
            <div>
              <label class="eb-field-label">Overwrite</label>
              <select id="rs-overwrite" class="eb-select w-full">
                <option value="none">Do not overwrite</option>
                <option value="ifNewer">If the restored file is newer</option>
                <option value="ifDifferent">If the restored file is different</option>
                <option value="always">Always overwrite</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div id="restore-step4" class="hidden">
        <div class="space-y-3">
          <div class="text-sm text-[var(--eb-text-primary)]">Restore scope</div>
          <div class="text-xs text-[var(--eb-text-muted)]">Choose whether to restore everything from the snapshot, or pick specific files/folders.</div>
          <div id="rs-scope-options" class="space-y-2 text-sm text-[var(--eb-text-primary)]"></div>

          <div id="rs-scope-select-wrap" class="hidden mt-3">
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm text-[var(--eb-text-primary)]">Select items from snapshot</div>
              <button id="rs-snap-browse" type="button" class="eb-btn eb-btn-secondary eb-btn-sm">Browse snapshot...</button>
            </div>
            <div class="mt-2 rounded border bg-[var(--eb-bg-overlay)]" style="border-color: var(--eb-border-default);">
              <div class="border-b px-3 py-2 text-xs text-[var(--eb-text-muted)]" style="border-color: var(--eb-border-default);">
                Selected items (<span id="rs-selected-count">0</span>)
              </div>
              <div id="rs-selected-items" class="max-h-48 overflow-y-auto divide-y divide-[var(--eb-border-default)] text-sm text-[var(--eb-text-primary)]"></div>
              <div id="rs-selected-empty" class="px-3 py-3 text-sm text-[var(--eb-text-muted)]">No items selected yet. Click “Browse snapshot…” to choose files and folders.</div>
            </div>
            <div class="text-xs text-[var(--eb-text-muted)]">Tip: Selecting a folder restores the entire folder contents.</div>
          </div>
        </div>
      </div>
      <div class="eb-modal-footer justify-between mt-4">
        <button id="restore-back" class="eb-btn eb-btn-secondary">Back</button>
        <div class="ml-auto space-x-2">
          <button id="restore-next" class="eb-btn eb-btn-secondary">Next</button>
          <button id="restore-start" class="eb-btn eb-btn-primary hidden">Start Restore</button>
        </div>
      </div>
    </div>
  </div>
  </div>
  <input type="hidden" id="rs-selected-vault" value="" />
  <input type="hidden" id="rs-selected-item" value="" />
  <input type="hidden" id="rs-selected-snapshot" value="" />
  <input type="hidden" id="rs-selected-engine" value="" />
  <input type="hidden" id="rs-device-id" value="" />
  <input type="hidden" id="rs-scope-hidden" value="all" />
  <input type="hidden" id="rs-paths-hidden" value="[]" />
</div>

<!-- Toast container -->
<div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

<!-- Remote Filesystem Browser Modal -->
<div id="fs-browser" class="fixed inset-0 z-50 hidden">
  <div class="eb-modal-backdrop absolute inset-0"></div>
  <div class="relative flex min-h-full items-start justify-center p-6">
  <div class="eb-modal max-w-3xl">
    <div class="eb-modal-header">
      <h3 class="eb-modal-title">Browse Destination</h3>
      <button id="fsb-close" class="eb-modal-close">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="eb-modal-body">
      <div class="flex items-center gap-2 mb-3">
        <button id="fsb-up" class="eb-btn eb-btn-secondary eb-btn-xs">Up</button>
        <div id="fsb-path" class="flex-1 overflow-x-auto rounded border px-2 py-1 text-xs text-[var(--eb-text-primary)]" style="border-color: var(--eb-border-default); background: var(--eb-bg-overlay);"></div>
        <button id="fsb-refresh" class="eb-btn eb-btn-secondary eb-btn-xs">Refresh</button>
      </div>
      <div class="eb-table-shell overflow-hidden">
        <div class="grid grid-cols-12 gap-0 px-3 py-2 text-xs text-[var(--eb-text-primary)]" style="background: color-mix(in srgb, var(--eb-bg-overlay) 75%, transparent);">
          <div class="col-span-7">Name</div>
          <div class="col-span-2">Type</div>
          <div class="col-span-3 text-right">Modified</div>
        </div>
        <div id="fsb-list" class="max-h-80 overflow-y-auto divide-y divide-[var(--eb-border-default)]"></div>
      </div>
      <div class="mt-3 flex items-center gap-2">
        <input id="fsb-selected" type="text" class="eb-input flex-1" placeholder="Selected path" readonly>
        <button id="fsb-select" class="eb-btn eb-btn-primary">Select</button>
      </div>
      <div class="mt-2 text-xs text-[var(--eb-text-muted)]">Double-click folders to open. Click a folder, then Select to choose it.</div>
    </div>
  </div>
  </div>
</div>

<!-- Snapshot Browser Modal (Select items to restore) -->
<div id="snap-browser" class="fixed inset-0 z-50 hidden">
  <div class="eb-modal-backdrop absolute inset-0"></div>
  <div class="relative flex min-h-full items-start justify-center p-6">
  <div class="eb-modal max-w-4xl">
    <div class="eb-modal-header">
      <h3 class="eb-modal-title">Browse Snapshot</h3>
      <button id="ssb-close" class="eb-modal-close">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="eb-modal-body">
      <div class="flex items-center gap-2 mb-3">
        <button id="ssb-up" class="eb-btn eb-btn-primary eb-btn-xs inline-flex items-center gap-1">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19V7m0 0 4 4m-4-4-4 4" />
          </svg>
          Up
        </button>
        <div id="ssb-path" class="flex-1 overflow-x-auto rounded border px-2 py-1 text-xs text-[var(--eb-text-primary)]" style="border-color: var(--eb-border-default); background: var(--eb-bg-overlay);"></div>
        <button id="ssb-refresh" class="eb-btn eb-btn-secondary eb-btn-xs">Refresh</button>
      </div>
      <div class="eb-table-shell overflow-hidden">
        <div class="grid grid-cols-12 gap-0 px-3 py-2 text-xs text-[var(--eb-text-primary)]" style="background: color-mix(in srgb, var(--eb-bg-overlay) 75%, transparent);">
          <div class="col-span-1"></div>
          <div class="col-span-7">Name</div>
          <div class="col-span-1">Type</div>
          <div class="col-span-3 text-right">Modified</div>
        </div>
        <div id="ssb-list" class="max-h-80 overflow-y-auto divide-y divide-[var(--eb-border-default)]"></div>
      </div>
      <div class="mt-3 flex items-center gap-2">
        <input id="ssb-selected" type="text" class="eb-input flex-1" placeholder="Selected items" readonly>
        <button id="ssb-clear" class="eb-btn eb-btn-secondary">Clear</button>
        <button id="ssb-select" class="eb-btn eb-btn-primary">Select</button>
      </div>
      <div class="mt-2 text-xs text-[var(--eb-text-muted)]">Click folders to open. Use checkboxes to select files and folders, then click Select.</div>
    </div>
  </div>
  </div>
</div>
</div>

{literal}
<style>
 [x-cloak] { display: none !important; }
</style>
{/literal}
