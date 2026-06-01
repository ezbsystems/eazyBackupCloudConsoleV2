{**
 * eazyBackup Protected Item create/edit wizard modal.
 *
 * Mounted once per user-profile page; opened via window event 'pi-wizard:open'
 * or window.openProtectedItemWizard(mode, { itemId?, deviceId? }).
 *}
<div x-data="protectedItemWizard()" x-init="init()" x-cloak>
  <div x-show="open"
       class="fixed inset-0 z-50 flex items-start justify-center p-4 sm:p-6 overflow-y-auto"
       @click.self="close()"
       x-transition.opacity>
    <div class="eb-modal-backdrop fixed inset-0 pointer-events-none" aria-hidden="true"></div>
    <div class="eb-modal relative z-10 w-full max-w-4xl my-6 !overflow-visible" @click.stop>
      <div class="eb-modal-header">
        <div>
          <h2 class="eb-modal-title flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25a2.25 2.25 0 0 1 2.25 2.25v2.25A2.25 2.25 0 0 1 8.25 10.5H6A2.25 2.25 0 0 1 3.75 8.25V6Zm9.75 0A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25A2.25 2.25 0 0 1 13.5 8.25V6Zm-9.75 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 8.25 20.25H6A2.25 2.25 0 0 1 3.75 18v-2.25Zm9.75 0A2.25 2.25 0 0 1 15.75 13.5H18A2.25 2.25 0 0 1 20.25 15.75V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
            </svg>
            <span x-text="mode === 'edit' ? 'Edit Protected Item' : 'New Protected Item'"></span>
          </h2>
          <p class="eb-modal-subtitle">Configure what to back up, when, and for how long.</p>
        </div>
        <button class="eb-modal-close" @click="close()" aria-label="Close">&times;</button>
      </div>

      <div class="eb-modal-body !overflow-visible">
        <div class="flex flex-wrap items-center gap-2 mb-5">
          <template x-for="n in [1,2,3,4,5,6]" :key="'pi-step-'+n">
            <button type="button"
                    class="eb-pill"
                    :class="step === n ? 'is-active' : (stepEnabled(n) ? '' : 'opacity-50 cursor-not-allowed')"
                    :disabled="!stepEnabled(n)"
                    @click="if (stepEnabled(n)) goto(n)">
              <span x-text="n + '. ' + stepLabel(n)"></span>
            </button>
          </template>
        </div>

        <div x-show="banner.message" class="eb-alert mb-4"
             :class="banner.type === 'error' ? 'eb-alert--danger' : 'eb-alert--info'">
          <svg class="eb-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
          </svg>
          <div><span x-text="banner.message"></span></div>
        </div>

        {* ------- Step 1: Device ------- *}
        <div x-show="step === 1" x-cloak>
          <div class="mb-3">
            <h3 class="eb-type-h3">Choose a device</h3>
            <p class="eb-type-caption">Select the device that will back up this Protected Item.</p>
          </div>
          <div x-show="!devices.length" class="eb-app-empty">
            <div class="eb-app-empty-title">No devices found</div>
            <p class="eb-app-empty-copy">Install the Comet Backup client on a device, log in with this account, then return here.</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <template x-for="d in devices" :key="d.id">
              <div class="eb-choice-card" :class="deviceId === d.id ? 'is-selected' : ''" @click="deviceId = d.id">
                <div class="eb-choice-card-control">
                  <input type="radio" name="pi-device" class="eb-radio-input" :checked="deviceId === d.id" @change="deviceId = d.id">
                </div>
                <div>
                  <div class="eb-choice-card-title flex items-center gap-2">
                    <span x-text="d.friendlyName"></span>
                    <span class="eb-status-dot" :class="d.online ? 'eb-status-dot--active' : 'eb-status-dot--inactive'"></span>
                  </div>
                  <div class="eb-choice-card-description" x-text="(d.osInfo || '') + (d.online ? ' · Online' : ' · Offline')"></div>
                </div>
              </div>
            </template>
          </div>
        </div>

        {* ------- Step 2: Engine ------- *}
        <div x-show="step === 2" x-cloak>
          <div class="mb-3">
            <h3 class="eb-type-h3">What do you want to back up?</h3>
            <p class="eb-type-caption">
              <span x-show="engineRestricted">Some types are unavailable for your account based on your service policy.</span>
              <span x-show="!engineRestricted">Choose the type of data this Protected Item will cover.</span>
            </p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <template x-for="e in engines" :key="e.id">
              <div class="eb-choice-card"
                   :class="[engine === e.id ? 'is-selected' : '', !e.enabled ? 'opacity-50 cursor-not-allowed' : '']"
                   @click="if (e.enabled) pickEngine(e.id)">
                <div class="eb-choice-card-control">
                  <input type="radio" name="pi-engine" class="eb-radio-input" :checked="engine === e.id" :disabled="!e.enabled">
                </div>
                <div>
                  <div class="eb-choice-card-title flex items-center gap-2">
                    <span x-text="e.label"></span>
                    <span class="eb-badge eb-badge--neutral" x-show="e.comingSoon">Coming soon</span>
                    <span class="eb-badge eb-badge--warning" x-show="!e.allowedByPolicy">Not in plan</span>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>

        {* ------- Step 3: Items ------- *}
        <div x-show="step === 3" x-cloak>
          <div class="mb-4">
            <label class="eb-field-label">Description (display name)</label>
            <input type="text" class="eb-input" placeholder="e.g. Workstation files" x-model="description">
          </div>

          {* File/Folder engine *}
          <div x-show="isFileEngine()" x-cloak>
            <div class="mb-2 flex items-center justify-between">
              <h3 class="eb-type-h3">Files and folders</h3>
              <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="openBrowser()" :disabled="!deviceId">
                Browse device&hellip;
              </button>
            </div>

            <div class="eb-table-shell mb-3">
              <table class="eb-table">
                <thead><tr><th>Type</th><th>Path</th><th class="w-12"></th></tr></thead>
                <tbody>
                  <template x-for="(p, i) in file.includes" :key="'inc-'+i+'-'+p">
                    <tr>
                      <td><span class="eb-badge eb-badge--success">Include</span></td>
                      <td class="eb-table-mono" x-text="p"></td>
                      <td><button type="button" class="eb-btn eb-btn-icon eb-btn-sm is-danger" @click="removeInclude(i)" aria-label="Remove">&times;</button></td>
                    </tr>
                  </template>
                  <template x-for="(p, i) in file.excludes" :key="'exc-'+i+'-'+p">
                    <tr>
                      <td><span class="eb-badge eb-badge--danger">Exclude</span></td>
                      <td class="eb-table-mono" x-text="p"></td>
                      <td><button type="button" class="eb-btn eb-btn-icon eb-btn-sm is-danger" @click="removeExclude(i)" aria-label="Remove">&times;</button></td>
                    </tr>
                  </template>
                  <tr x-show="!file.includes.length && !file.excludes.length">
                    <td colspan="3" class="text-center py-4" style="color: var(--eb-text-muted)">No paths configured yet. Add an include path below.</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
              <div class="flex items-end gap-2">
                <div class="flex-1">
                  <label class="eb-field-label">Add include path</label>
                  <input type="text" class="eb-input" placeholder="C:\Users\..." x-model="file.newInclude" @keydown.enter.prevent="addInclude()">
                </div>
                <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="addInclude()">Add</button>
              </div>
              <div class="flex items-end gap-2">
                <div class="flex-1">
                  <label class="eb-field-label">Add exclude path / pattern</label>
                  <input type="text" class="eb-input" placeholder="*.tmp" x-model="file.newExclude" @keydown.enter.prevent="addExclude()">
                </div>
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="addExclude()">Add</button>
              </div>
            </div>

            <div class="eb-toggle mb-3" @click="file.showAdvanced = !file.showAdvanced">
              <div class="eb-toggle-track" :class="file.showAdvanced && 'is-on'"><div class="eb-toggle-thumb"></div></div>
              <span class="eb-toggle-label">Show advanced options</span>
            </div>
            <div x-show="file.showAdvanced" x-cloak class="eb-subpanel">
              <h4 class="eb-type-h4 mb-3">Advanced options</h4>
              <div class="space-y-2">
                <div class="eb-toggle" @click="file.opts.takeFilesystemSnapshot = !file.opts.takeFilesystemSnapshot">
                  <div class="eb-toggle-track" :class="file.opts.takeFilesystemSnapshot && 'is-on'"><div class="eb-toggle-thumb"></div></div>
                  <span class="eb-toggle-label">Take filesystem snapshot</span>
                </div>
                <div class="eb-toggle" @click="file.opts.rescanUnchanged = !file.opts.rescanUnchanged">
                  <div class="eb-toggle-track" :class="file.opts.rescanUnchanged && 'is-on'"><div class="eb-toggle-thumb"></div></div>
                  <span class="eb-toggle-label">Always re-scan unchanged files</span>
                </div>
                <div class="eb-toggle" @click="file.opts.dismissEFS = !file.opts.dismissEFS">
                  <div class="eb-toggle-track" :class="file.opts.dismissEFS && 'is-on'"><div class="eb-toggle-thumb"></div></div>
                  <span class="eb-toggle-label">Dismiss EFS warning</span>
                </div>
                <div class="eb-toggle" @click="file.opts.extraAttributes = !file.opts.extraAttributes">
                  <div class="eb-toggle-track" :class="file.opts.extraAttributes && 'is-on'"><div class="eb-toggle-thumb"></div></div>
                  <span class="eb-toggle-label">Back up extra system permissions and attributes</span>
                </div>
              </div>
            </div>
          </div>

          {* VM engines *}
          <div x-show="isVMEngine()" x-cloak>
            <h3 class="eb-type-h3 mb-2">Select virtual machines</h3>

            <div x-show="engine === 'engine1/vmware'" class="eb-subpanel mb-3">
              <h4 class="eb-type-h4 mb-3">vSphere connection</h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                  <label class="eb-field-label">Host</label>
                  <input type="text" class="eb-input" placeholder="vcenter.example.com" x-model="vm.credentials.host">
                </div>
                <div>
                  <label class="eb-field-label">Username</label>
                  <input type="text" class="eb-input" x-model="vm.credentials.user">
                </div>
                <div>
                  <label class="eb-field-label">Password</label>
                  <input type="password" class="eb-input" x-model="vm.credentials.password">
                </div>
              </div>
              <label class="eb-inline-choice mt-3">
                <input type="checkbox" class="eb-check-input" x-model="vm.credentials.allowInvalidCert">
                <span>Allow invalid certificate</span>
              </label>
              <div class="mt-3">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="loadVMs()" :disabled="vm.loading">
                  <span x-show="!vm.loading">Browse VMs</span>
                  <span x-show="vm.loading">Loading&hellip;</span>
                </button>
              </div>
            </div>

            <div x-show="engine === 'engine1/hyperv' && !vm.vmsLoaded && !vm.loading" class="mb-3">
              <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="loadVMs()">Browse Hyper-V guests</button>
            </div>

            <div x-show="vm.loading" class="eb-loader-pill">Loading VMs&hellip;</div>
            <div x-show="vm.error" class="eb-alert eb-alert--danger mb-3"><div x-text="vm.error"></div></div>

            <div x-show="vm.vms.length" class="eb-table-shell mb-3">
              <table class="eb-table">
                <thead><tr><th class="w-10"></th><th>Name</th></tr></thead>
                <tbody>
                  <template x-for="v in vm.vms" :key="v.id">
                    <tr @click="toggleVM(v.id)" class="cursor-pointer">
                      <td><input type="checkbox" class="eb-check-input" :checked="vm.selected.includes(v.id)" @click.stop="toggleVM(v.id)"></td>
                      <td x-text="v.name"></td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>

            <div class="eb-subpanel mb-3">
              <h4 class="eb-type-h4 mb-2">Selected VMs</h4>
              <div x-show="!vm.selected.length" class="eb-type-caption">None selected.</div>
              <div class="flex flex-wrap gap-2 mb-3">
                <template x-for="(s, i) in vm.selected" :key="'sel-'+i+'-'+s">
                  <span class="eb-order-chip">
                    <span x-text="s"></span>
                    <button type="button" @click="removeVM(s)" aria-label="Remove">&times;</button>
                  </span>
                </template>
              </div>
              <div x-show="engine === 'engine1/proxmox' || vm.manualOnly || !vm.vms.length" class="flex items-end gap-2">
                <div class="flex-1">
                  <label class="eb-field-label">Add VM ID manually</label>
                  <input type="text" class="eb-input" placeholder="vm-id" x-model="vm.manualEntry" @keydown.enter.prevent="addManualVM()">
                </div>
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="addManualVM()">Add</button>
              </div>
            </div>

            <div>
              <label class="eb-field-label">Backup type</label>
              <div class="eb-select-field-mount"
                   x-data="piSelectVmBackupType()"
                   x-init="init()"
                   @keydown.escape.prevent="close()">
                {include file="modules/addons/eazybackup/templates/console/partials/eb-select-menu.tpl"}
              </div>
            </div>
          </div>
        </div>

        {* ------- Step 4: Schedule ------- *}
        <div x-show="step === 4" x-cloak>
          <div class="mb-3 flex items-center justify-between">
            <div>
              <h3 class="eb-type-h3">Schedules</h3>
              <p class="eb-type-caption">Add one or more schedules. Each schedule pairs this Protected Item with a Storage Vault and runs at the configured times.</p>
            </div>
            <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="openScheduleEditor(-1)">+ Add Schedule</button>
          </div>

          <div x-show="!schedules.length" class="eb-app-empty">
            <div class="eb-app-empty-title">No schedules yet</div>
            <p class="eb-app-empty-copy">A Protected Item without a schedule will only run when triggered manually.</p>
          </div>

          <div class="space-y-2" x-show="schedules.length">
            <template x-for="(r, i) in schedules" :key="'r-'+i+'-'+(r.ruleId || r.name)">
              <div class="eb-card">
                <div class="eb-card-header">
                  <div>
                    <div class="eb-card-title" x-text="r.name || 'Untitled schedule'"></div>
                    <p class="eb-card-subtitle">
                      <span x-text="(allVaults.find(v => v.id === r.vaultId) || { name: r.vaultId }).name"></span>
                      &middot; <span x-text="summarizeRule(r)"></span>
                    </p>
                  </div>
                  <div class="flex items-center gap-2">
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="openScheduleEditor(i)">Edit</button>
                    <button type="button" class="eb-btn eb-btn-icon eb-btn-sm is-danger" @click="removeSchedule(i)" aria-label="Remove">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                </div>
              </div>
            </template>
          </div>

          {* schedule editor sub-modal *}
          <div x-show="scheduleEditor.open" class="fixed inset-0 z-[60] flex items-start justify-center p-4 overflow-y-auto" x-cloak x-transition.opacity @click.self="closeScheduleEditor()">
            <div class="eb-modal-backdrop fixed inset-0 pointer-events-none" aria-hidden="true"></div>
            <div class="eb-modal relative z-10 w-full max-w-2xl my-6 !overflow-visible" @click.stop>
              <div class="eb-modal-header">
                <div>
                  <h2 class="eb-modal-title">Schedule</h2>
                  <p class="eb-modal-subtitle">Define when this Protected Item runs.</p>
                </div>
                <button class="eb-modal-close" @click="closeScheduleEditor()">&times;</button>
              </div>
              <div class="eb-modal-body !overflow-visible" x-show="scheduleEditor.draft">
               <template x-if="scheduleEditor.draft">
                <div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                  <div>
                    <label class="eb-field-label">Name</label>
                    <input type="text" class="eb-input" x-model="scheduleEditor.draft.name" placeholder="Daily Backup">
                  </div>
                  <div>
                    <label class="eb-field-label">Protected Item</label>
                    <input type="text" class="eb-input" :value="description || (mode==='edit' ? '(this item)' : '(new item)')" disabled>
                  </div>
                </div>
                <div class="mb-3 eb-select-field-mount"
                     x-data="piSelectScheduleVault()"
                     x-init="init()"
                     @keydown.escape.prevent="close()">
                  <label class="eb-field-label">Storage Vault</label>
                  {include file="modules/addons/eazybackup/templates/console/partials/eb-select-menu.tpl"}
                  <label class="eb-inline-choice mt-2">
                    <input type="checkbox" class="eb-check-input" x-model="scheduleEditor.draft.showOtherVaults">
                    <span>Show Storage Vaults in use by other devices</span>
                  </label>
                </div>

                <div class="mb-3">
                  <div class="flex items-center justify-between mb-2">
                    <label class="eb-field-label !mb-0">Schedule times</label>
                    <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="openTimeEditor(-1)">+ Add time</button>
                  </div>
                  <div x-show="!scheduleEditor.draft.schedules.length" class="eb-type-caption">No times configured. Click "Add time" to define when this schedule runs.</div>
                  <div class="space-y-2" x-show="scheduleEditor.draft.schedules.length">
                    <template x-for="(s, i) in scheduleEditor.draft.schedules" :key="'st-'+i">
                      <div class="eb-card flex items-center justify-between">
                        <span x-text="describeSchedule(s)"></span>
                        <div class="flex items-center gap-2">
                          <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="openTimeEditor(i)">Edit</button>
                          <button type="button" class="eb-btn eb-btn-icon eb-btn-sm is-danger" @click="removeTimeFromDraft(i)" aria-label="Remove">&times;</button>
                        </div>
                      </div>
                    </template>
                  </div>
                </div>

                <div class="eb-subpanel">
                  <h4 class="eb-type-h4 mb-3">Also run</h4>
                  <div class="space-y-2">
                    <label class="eb-inline-choice"><input type="checkbox" class="eb-check-input" x-model="scheduleEditor.draft.triggers.onPCBoot"> <span>When PC starts</span></label>
                    <label class="eb-inline-choice"><input type="checkbox" class="eb-check-input" x-model="scheduleEditor.draft.triggers.ifLastMissed"> <span>If the last job was missed</span></label>
                    <label class="eb-inline-choice"><input type="checkbox" class="eb-check-input" x-model="scheduleEditor.draft.triggers.retryOnFail"> <span>On error, retry</span></label>
                    <div class="flex items-center gap-2 ml-6" x-show="scheduleEditor.draft.triggers.retryOnFail">
                      <span class="eb-type-caption">every</span>
                      <input type="number" min="1" class="eb-input" style="width:90px" x-model.number="scheduleEditor.draft.triggers.retryMinutes">
                      <span class="eb-type-caption">minute(s), up to</span>
                      <input type="number" min="1" class="eb-input" style="width:90px" x-model.number="scheduleEditor.draft.triggers.retryCount">
                      <span class="eb-type-caption">time(s)</span>
                    </div>
                  </div>
                </div>
                </div>
               </template>
              </div>
              <div class="eb-modal-footer">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="closeScheduleEditor()">Cancel</button>
                <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="saveScheduleDraft()">Save schedule</button>
              </div>
            </div>
          </div>

          {* schedule time sub-sub-modal *}
          <div x-show="scheduleTimeEditor.open" class="fixed inset-0 z-[70] flex items-start justify-center p-4 overflow-y-auto" x-cloak x-transition.opacity @click.self="scheduleTimeEditor.open = false">
            <div class="eb-modal-backdrop fixed inset-0 pointer-events-none" aria-hidden="true"></div>
            <div class="eb-modal eb-modal--confirm relative z-10 w-full my-6 !overflow-visible" @click.stop>
              <div class="eb-modal-header">
                <h2 class="eb-modal-title">Scheduled time</h2>
                <button class="eb-modal-close" @click="scheduleTimeEditor.open=false">&times;</button>
              </div>
              <div class="eb-modal-body !overflow-visible" x-show="scheduleTimeEditor.draft">
                <template x-if="scheduleTimeEditor.draft">
                  <div>
                    <div class="mb-3">
                      <label class="eb-field-label">Schedule type</label>
                      <div class="eb-select-field-mount"
                           x-data="piSelectScheduleFrequency()"
                           x-init="init()"
                           @keydown.escape.prevent="close()">
                        {include file="modules/addons/eazybackup/templates/console/partials/eb-select-menu.tpl"}
                      </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3" x-show="scheduleTimeEditor.draft.FrequencyType !== 8012">
                      <div>
                        <label class="eb-field-label">Hour (0-23)</label>
                        <input type="number" min="0" max="23" class="eb-input" :value="timeHour()" @input="setTimeHour($event.target.value)">
                      </div>
                      <div>
                        <label class="eb-field-label">Minute (0-59)</label>
                        <input type="number" min="0" max="59" class="eb-input" :value="timeMinute()" @input="setTimeMinute($event.target.value)">
                      </div>
                    </div>
                    <div class="mb-3" x-show="scheduleTimeEditor.draft.FrequencyType === 8012">
                      <label class="eb-field-label">Minute past hour</label>
                      <input type="number" min="0" max="59" class="eb-input" :value="timeMinute()" @input="setTimeMinute($event.target.value)">
                    </div>
                    <div class="mb-3" x-show="scheduleTimeEditor.draft.FrequencyType === 8013">
                      <label class="eb-field-label">Days of week</label>
                      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <template x-for="d in ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']" :key="'dow-'+d">
                          <label class="eb-inline-choice"><input type="checkbox" class="eb-check-input" :checked="scheduleTimeEditor.draft.DaysSelect && scheduleTimeEditor.draft.DaysSelect[d]" @change="scheduleTimeEditor.draft.DaysSelect = scheduleTimeEditor.draft.DaysSelect || {}; scheduleTimeEditor.draft.DaysSelect[d] = $event.target.checked"> <span x-text="d"></span></label>
                        </template>
                      </div>
                    </div>
                    <div class="mb-3" x-show="scheduleTimeEditor.draft.FrequencyType === 8014">
                      <label class="eb-field-label">Day of month (1-31)</label>
                      <input type="number" min="1" max="31" class="eb-input" x-model.number="scheduleTimeEditor.draft.SelectedDay">
                    </div>
                    <div>
                      <label class="eb-field-label">Random job delay (seconds)</label>
                      <input type="number" min="0" class="eb-input" x-model.number="scheduleTimeEditor.draft.RandomDelaySecs">
                    </div>
                  </div>
                </template>
              </div>
              <div class="eb-modal-footer">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="scheduleTimeEditor.open=false">Cancel</button>
                <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="saveTimeDraft()">Save time</button>
              </div>
            </div>
          </div>
        </div>

        {* ------- Step 5: Retention ------- *}
        <div x-show="step === 5" x-cloak>
          <div class="mb-3">
            <h3 class="eb-type-h3">Retention policy</h3>
            <p class="eb-type-caption">By default, this Protected Item inherits the retention policy from its Storage Vault. You can override the policy here for one Storage Vault.</p>
          </div>

          <div class="mb-3">
            <label class="eb-field-label">Apply override to vault</label>
            <div class="eb-select-field-mount"
                 x-data="piSelectRetentionVault()"
                 x-init="init()"
                 @keydown.escape.prevent="close()">
              {include file="modules/addons/eazybackup/templates/console/partials/eb-select-menu.tpl"}
            </div>
          </div>

          <div class="eb-toggle mb-3" @click="retention.override = !retention.override" x-show="retention.activeVaultId">
            <div class="eb-toggle-track" :class="retention.override && 'is-on'"><div class="eb-toggle-thumb"></div></div>
            <span class="eb-toggle-label">Override account retention for this Protected Item</span>
          </div>

          <div x-show="retention.override && retention.activeVaultId" class="eb-subpanel">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
              <div>
                <label class="eb-field-label">Mode</label>
                <div class="eb-select-field-mount"
                     x-data="piSelectRetentionMode()"
                     x-init="init()"
                     @keydown.escape.prevent="close()">
                  {include file="modules/addons/eazybackup/templates/console/partials/eb-select-menu.tpl"}
                </div>
              </div>
            </div>
            <div x-show="retention.mode === 802">
              <div class="mb-2 eb-field-label">Add a rule</div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3">
                <div class="eb-select-field-mount"
                     x-data="piSelectRetentionRuleType()"
                     x-init="init()"
                     @keydown.escape.prevent="close()">
                  {include file="modules/addons/eazybackup/templates/console/partials/eb-select-menu.tpl"}
                </div>
                <input type="number" min="1" class="eb-input" placeholder="Count" x-model.number="retention.newCount">
                <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="addRetentionRange()">Add rule</button>
              </div>
              <div class="space-y-1">
                <template x-for="(r, i) in retention.ranges" :key="'rg-'+i">
                  <div class="eb-card flex items-center justify-between">
                    <span class="eb-type-mono"
                          x-text="(r.Type===900?'Most recent '+(r.Jobs||1)+' jobs':r.Type===903?'First job for last '+(r.Days||1)+' days':r.Type===906?'First job for last '+(r.Weeks||1)+' weeks':r.Type===905?'First job for last '+(r.Months||1)+' months':r.Type===911?'First job for last '+(r.Years||1)+' years':'Type '+r.Type)"></span>
                    <button type="button" class="eb-btn eb-btn-icon eb-btn-sm is-danger" @click="retention.ranges.splice(i,1)">&times;</button>
                  </div>
                </template>
                <div x-show="!retention.ranges.length" class="eb-type-caption">No rules added yet.</div>
              </div>
            </div>
          </div>
        </div>

        {* ------- Step 6: Review ------- *}
        <div x-show="step === 6" x-cloak>
          <h3 class="eb-type-h3 mb-3">Review</h3>
          <div class="eb-kv-list">
            <div class="eb-kv-row"><span class="eb-kv-label">Device</span><span class="eb-kv-value" x-text="(devices.find(d=>d.id===deviceId)||{ friendlyName: deviceId }).friendlyName"></span></div>
            <div class="eb-kv-row"><span class="eb-kv-label">Type</span><span class="eb-kv-value" x-text="(engines.find(e=>e.id===engine)||{ label: engine }).label"></span></div>
            <div class="eb-kv-row"><span class="eb-kv-label">Description</span><span class="eb-kv-value" x-text="description"></span></div>
            <div class="eb-kv-row" x-show="isFileEngine()"><span class="eb-kv-label">Includes</span><span class="eb-kv-value" x-text="file.includes.length + ' path(s)'"></span></div>
            <div class="eb-kv-row" x-show="isFileEngine()"><span class="eb-kv-label">Excludes</span><span class="eb-kv-value" x-text="file.excludes.length + ' path(s)'"></span></div>
            <div class="eb-kv-row" x-show="isVMEngine()"><span class="eb-kv-label">Selected VMs</span><span class="eb-kv-value" x-text="vm.selected.length"></span></div>
            <div class="eb-kv-row" x-show="isVMEngine()"><span class="eb-kv-label">Backup type</span><span class="eb-kv-value" x-text="vm.backupType"></span></div>
            <div class="eb-kv-row"><span class="eb-kv-label">Schedules</span><span class="eb-kv-value" x-text="schedules.length"></span></div>
            <div class="eb-kv-row"><span class="eb-kv-label">Retention override</span><span class="eb-kv-value" x-text="retention.override ? 'Enabled (' + (retention.activeVaultId || '-') + ')' : 'Inherits from vault'"></span></div>
          </div>
        </div>
      </div>

      <div class="eb-modal-footer gap-2">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="back()" x-show="step > 1" :disabled="submitting">&lt; Back</button>
        <div class="flex-1"></div>
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="next()" x-show="step < 6" :disabled="!canAdvance()">Next &gt;</button>
        <button type="button" class="eb-btn eb-btn-ghost eb-btn-sm" @click="close()">Cancel</button>
        <button type="button" class="eb-btn eb-btn-primary eb-btn-sm" @click="save()" x-show="step === 6 || mode === 'edit'" :disabled="submitting || !itemsValid()">
          <span x-show="!submitting">Save</span>
          <span x-show="submitting">Saving&hellip;</span>
        </button>
      </div>
    </div>
  </div>

  {* File browser sub-modal (two-pane explorer) *}
  <div x-show="browser.open" class="fixed inset-0 z-[55] flex items-start justify-center p-4 overflow-y-auto" x-cloak x-transition.opacity @click.self="browser.open = false">
    <div class="eb-modal-backdrop fixed inset-0 pointer-events-none" aria-hidden="true"></div>
    <div class="eb-modal relative z-10 w-full max-w-4xl my-6 !overflow-visible" @click.stop>
      <div class="eb-modal-header">
        <div>
          <h2 class="eb-modal-title">Browse device filesystem</h2>
          <p class="eb-modal-subtitle">Select volumes, folders, or files, then click Add.</p>
        </div>
        <button type="button" class="eb-modal-close" @click="browser.open=false" aria-label="Close">&times;</button>
      </div>
      <div class="eb-modal-body">
        <div class="flex flex-wrap items-center gap-2 mb-3">
          <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="browseUp()" :disabled="!browser.activeVolume || !browser.breadcrumb.length">&uarr; Up</button>
          <button type="button" class="eb-btn eb-btn-secondary eb-btn-xs" @click="browseRefresh()" :disabled="browser.loading">Refresh</button>
          <div class="eb-breadcrumb min-w-0 flex-1">
            <template x-if="browser.activeVolume">
              <span>
                <span class="eb-breadcrumb-current" x-text="browser.activeVolume.name"></span>
                <template x-for="(b, i) in browser.breadcrumb" :key="'bc-'+i">
                  <span><span class="eb-breadcrumb-separator">/</span><span class="eb-breadcrumb-current" x-text="b.name"></span></span>
                </template>
              </span>
            </template>
            <span x-show="!browser.activeVolume" class="eb-breadcrumb-current" style="color: var(--eb-text-muted)">No volume selected</span>
          </div>
        </div>

        <div x-show="browser.loading" class="eb-loader-pill mb-3">Loading&hellip;</div>
        <div x-show="browser.error" class="eb-alert eb-alert--danger mb-3"><div x-text="browser.error"></div></div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 min-h-[320px] max-h-[420px]">
          {* Left pane: volumes *}
          <div class="md:col-span-1 flex flex-col min-h-0">
            <div class="eb-type-caption mb-2 font-medium">Volumes</div>
            <div class="eb-table-shell flex-1 min-h-0 overflow-y-auto">
              <table class="eb-table">
                <tbody>
                  <template x-for="v in browser.volumes" :key="'vol-'+browsePathKey(v)">
                    <tr class="cursor-pointer"
                        :class="browser.activeVolume && browsePathKey(browser.activeVolume) === browsePathKey(v) ? 'is-selected' : ''"
                        @click="browseSelectVolume(v)">
                      <td class="w-10" @click.stop>
                        <input type="checkbox" class="eb-check-input" :checked="browseIsSelected(v)" @change="browseToggleSelect(v)">
                      </td>
                      <td class="w-8">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15a2.25 2.25 0 0 1 2.25 2.25v.75m-19.5 0v6A2.25 2.25 0 0 0 4.5 21h15a2.25 2.25 0 0 0 2.25-2.25v-6m-19.5 0V8.25A2.25 2.25 0 0 1 4.5 6h3l1.5 1.5h10.5A2.25 2.25 0 0 1 21.75 9.75v3"/></svg>
                      </td>
                      <td class="truncate" x-text="v.name"></td>
                    </tr>
                  </template>
                  <tr x-show="!browser.loading && !browser.volumes.length && !browser.error">
                    <td colspan="3" class="text-center py-6" style="color: var(--eb-text-muted)">No volumes found.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          {* Right pane: folder contents *}
          <div class="md:col-span-2 flex flex-col min-h-0">
            <div class="eb-type-caption mb-2 font-medium">Contents</div>
            <div class="eb-table-shell flex-1 min-h-0 overflow-y-auto">
              <div x-show="!browser.activeVolume" class="eb-app-empty py-8">
                <p class="eb-app-empty-copy">Select a volume to browse its folders and files.</p>
              </div>
              <table class="eb-table" x-show="browser.activeVolume">
                <tbody>
                  <template x-for="e in browser.entries" :key="'ent-'+browsePathKey(e)">
                    <tr :class="e.isDir ? 'cursor-pointer' : ''" @click="e.isDir && browseInto(e)">
                      <td class="w-10" @click.stop>
                        <input type="checkbox" class="eb-check-input" :checked="browseIsSelected(e)" @change="browseToggleSelect(e)">
                      </td>
                      <td class="w-8">
                        <svg x-show="e.isDir" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15a2.25 2.25 0 0 1 2.25 2.25v.75m-19.5 0v6A2.25 2.25 0 0 0 4.5 21h15a2.25 2.25 0 0 0 2.25-2.25v-6m-19.5 0V8.25A2.25 2.25 0 0 1 4.5 6h3l1.5 1.5h10.5A2.25 2.25 0 0 1 21.75 9.75v3"/></svg>
                        <svg x-show="!e.isDir" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M9 16.5v.75m3-3v3M15 12v5.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                      </td>
                      <td class="truncate" :class="!e.isDir && 'opacity-80'" x-text="e.name"></td>
                    </tr>
                  </template>
                  <tr x-show="browser.activeVolume && !browser.loading && !browser.entries.length && !browser.error">
                    <td colspan="3" class="text-center py-6" style="color: var(--eb-text-muted)">This folder is empty.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <div class="eb-modal-footer gap-2">
        <button type="button" class="eb-btn eb-btn-secondary eb-btn-sm" @click="browser.open=false">Close</button>
        <div class="flex-1"></div>
        <button type="button"
                class="eb-btn eb-btn-primary eb-btn-sm"
                @click="browseCommitSelections()"
                :disabled="browseSelectedCount() === 0">
          <span x-show="browseSelectedCount() === 0">Add</span>
          <span x-show="browseSelectedCount() > 0" x-text="'Add (' + browseSelectedCount() + ')'"></span>
        </button>
      </div>
    </div>
  </div>
</div>
