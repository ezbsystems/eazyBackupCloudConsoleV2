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
  /* Dark slim scrollbar for tables */
  .table-scroll::-webkit-scrollbar {
    height: 6px;
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

<div class="min-h-screen bg-slate-950 text-gray-300">
  <!-- Global nebula background -->
  <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>

  <div class="container mx-auto px-4 py-8 overflow-x-hidden">
    <!-- Glass panel container -->
    <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6 overflow-hidden">
      <!-- Header & Breadcrumb -->
      <div class="flex flex-col mb-4 px-2 space-y-3">
        <nav aria-label="breadcrumb">
          <ol class="flex space-x-2 items-center">
            <li class="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
              </svg>
              <h2 class="text-2xl font-semibold text-white mr-2">Dashboard</h2>
              <h2 class="text-md font-medium text-white">
                / <a href="{$modulelink}&a=dashboard" class="text-sky-400 hover:text-sky-500">Users</a> / <span>{$username}</span>
              </h2>
            </li>
          </ol>
        </nav>

        <!-- Pill nav -->
        <div class="mt-4 sm:mt-0">
          <nav class="inline-flex space-x-1 rounded-full bg-slate-900/80 p-1 text-sm font-medium text-slate-400" role="tablist" aria-label="Dashboard navigation">
            <a href="{$modulelink}&a=dashboard" class="flex items-center px-4 py-2 rounded-full text-slate-400 hover:text-white hover:bg-slate-800 transition">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-1">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
              </svg>
              Backup Status
            </a>
            <a href="{$modulelink}&a=dashboard&tab=users" class="flex items-center px-4 py-2 rounded-full bg-slate-800 text-white shadow-sm">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-1">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0  0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
              </svg>
              Users
            </a>
            <a href="{$modulelink}&a=vaults" class="flex items-center px-4 py-2 rounded-full text-slate-400 hover:text-white hover:bg-slate-800 transition">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-1">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
              </svg>
              Vaults
            </a>
          </nav>
        </div>
      </div>

      <!-- Sub-tab content -->
      <div x-data="{ activeSubTab: 'profile' }" class="mt-4">
        <div class="border-b border-slate-700 mb-6">
        <div class="flex items-center justify-between">
          <nav class="-mb-px flex space-x-6" aria-label="Tabs">
              <a href="#" @click.prevent="activeSubTab = 'profile'" :class="activeSubTab === 'profile' ? 'border-sky-500 text-sky-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Profile</a>
              <a href="#" @click.prevent="activeSubTab = 'protectedItems'" :class="activeSubTab === 'protectedItems' ? 'border-sky-500 text-sky-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Protected Items</a>
              <a href="#" @click.prevent="activeSubTab = 'storage'" :class="activeSubTab === 'storage' ? 'border-sky-500 text-sky-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Storage Vaults</a>
              <a href="#" @click.prevent="activeSubTab = 'devices'" :class="activeSubTab === 'devices' ? 'border-sky-500 text-sky-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Devices</a>
              <a href="#" @click.prevent="activeSubTab = 'jobLogs'" :class="activeSubTab === 'jobLogs' ? 'border-sky-500 text-sky-400' : 'border-transparent text-slate-400 hover:text-slate-200 hover:border-slate-500'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Job Logs</a>
          </nav>
          <div class="relative" x-data="{ open:false }" @keydown.escape.window="open=false" @click.away="open=false">
            <button type="button" class="inline-flex items-center px-3 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded text-white" @click="open = !open">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-1">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.094c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
              </svg>
              Actions
              <svg class="ml-1 h-4 w-4 opacity-70" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
            </button>
            <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-56 bg-slate-800 border border-slate-700 rounded-md shadow-lg z-10">
              <ul class="py-1 text-sm text-slate-200">
                <li>
                  <a href="#" class="flex items-center px-3 py-2 hover:bg-slate-700" data-action="reset-password" data-username="{$username}" data-serviceid="{$serviceid}" @click.prevent="open=false; $dispatch('eb-reset-password', { username: '{$username}', serviceid: '{$serviceid}' })">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 mr-2 text-slate-300">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 21v-2.25a2.25 2.25 0 0 0-2.25-2.25h-10.5A2.25 2.25 0 0 0 4.5 18.75V21" />
                    </svg>
                    <span>Reset password</span>
                  </a>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>       

      <div x-show="activeSubTab === 'profile'" x-transition>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
          <div class="xl:col-span-2 bg-slate-900 p-6 rounded-lg">
            <h3 class="text-lg font-semibold text-white mb-4">User Details</h3>
            <div class="space-y-3 text-sm">
              <div class="flex justify-between">
                  <span class="text-gray-400">Username:</span>
                  <span class="text-white font-mono">{$username}</span>
              </div>
              <!-- Account Name editable row -->
              <div x-data="accNameCtrl()" x-init="await init()" class="flex items-center justify-between gap-3">
                <label for="eb-account-name" class="text-gray-400 shrink-0">Account Name:</label>
                <div class="flex grow items-center gap-2">
                  <input
                    id="eb-account-name"
                    type="text"
                    x-model="name"
                    placeholder="Optional descriptive name"
                    class="h-8 w-full rounded-md border border-slate-600/70 bg-slate-900 px-2 text-slate-100 focus:outline-none focus:ring-0 focus:border-sky-600"
                  />
                  <button type="button" @click="await save()" :disabled="saving" class="shrink-0 rounded bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 disabled:opacity-60">Update</button>
                </div>
              </div>
              <div class="flex justify-between">
                  <span class="text-gray-400">Password:</span>
                  <span class="text-white">Hashed with 448-bit bcrypt</span>
              </div>
              <div class="flex justify-between">
                  <span class="text-gray-400">Created:</span>
                  <span class="text-white font-mono">{$createdDate}</span>
              </div>
              <div class="flex justify-between items-center">
                <div>
                  <span class="text-gray-400 mr-2">TOTP:</span>
                  {if $totpStatus == 'Active'}
                      <span class="text-green-400">{$totpStatus}</span>
                  {else}
                      <span class="text-red-400">{$totpStatus}</span>
                  {/if}
                </div>
                <div class="flex items-center space-x-2">
                    <button id="totp-regenerate" class="bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-1.5 px-3 rounded">{if $totpStatus == 'Active'}Regenerate QR{else}Enable TOTP{/if}</button>
                    {if $totpStatus == 'Active'}
                    <button id="totp-disable" class="bg-slate-700 hover:bg-red-700 text-white text-xs font-semibold py-1.5 px-3 rounded">Disable</button>
                    {/if}
                </div>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">Number of devices:</span>
                <span class="text-white">{if $devices}{$devices|count}{else}0{/if}</span>
              </div>
              <div class="flex justify-between">
                  <span class="text-gray-400">Office 365 protected accounts:</span>
                  <span class="text-white">{$msAccountCount}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">Number of Hyper-V VMs:</span>
                <span class="text-white">{$hvGuestCount|default:0}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-400">Number of VMware VMs:</span>
                <span class="text-white">{$vmwGuestCount|default:0}</span>
              </div>
              <!-- BEGIN: Quota Controls -->
              <div 
                x-data="quotaCtrl()" 
                x-init="await init()"
                class="mt-6 rounded-xl border border-slate-700 bg-slate-800/60"
              >
                <div class="px-4 py-3 border-b border-slate-700">
                  <h3 class="text-sm font-semibold text-slate-200">Quotas</h3>
                  <p class="mt-1 text-xs text-slate-400">Set limits for devices and protected workloads. Turn off to allow unlimited.</p>
                </div>

                <div class="divide-y divide-slate-700">
                  <!-- Maximum devices -->
                  <div class="flex items-center justify-between px-4 py-3 gap-4">
                    <div class="min-w-0">
                      <label for="q-devices" class="block text-sm font-medium text-slate-200">Maximum devices</label>
                      <p class="text-xs text-slate-400">Limit the number of devices that can register under this backup account.</p>
                    </div>
                    <div class="flex items-center gap-4">
                      <button 
                        type="button"
                        @click="dev.enabled = !dev.enabled; if (!dev.enabled) { dev.count = 0 } else if (dev.count < 1) { dev.count = 1 }"
                        :class="dev.enabled ? 'bg-emerald-600' : 'bg-slate-600'"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                        :aria-pressed="dev.enabled ? 'true' : 'false'"
                        aria-label="Toggle maximum devices"
                      >
                        <span 
                          :class="dev.enabled ? 'translate-x-6' : 'translate-x-1'"
                          class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                        ></span>
                      </button>

                      <!-- number input with custom stepper -->                    
                      <div class="relative group" :class="!dev.enabled && 'opacity-50 cursor-not-allowed pointer-events-none'">
                        <input
                          id="q-devices"
                          type="number"
                          :min="dev.enabled ? 1 : null"
                          inputmode="numeric"
                          min="1" step="1"
                          x-model.number="dev.count"
                          :disabled="!dev.enabled"
                          :tabindex="dev.enabled ? '0' : '-1'"
                          class="peer no-native-spin h-9 w-28 rounded-lg border border-slate-600/70 bg-slate-900 pr-10 pl-3
                                text-left text-slate-100 outline-none transition
                                focus:border-slate-500 focus:ring-2 focus:ring-emerald-500/80"
                          @blur="if (dev.enabled) dev.count = Math.max(1, parseInt(dev.count || 1))"
                        />

                        <!-- Compact custom stepper -->
                        <div
                          class="pointer-events-auto absolute right-1.5 top-1/2 -translate-y-1/2
                                h-7 w-7 overflow-hidden rounded-md border border-slate-600/70 bg-slate-800/90 shadow-sm
                                opacity-0 transition
                                group-hover:opacity-100 peer-focus:opacity-100"
                          :class="!dev.enabled && 'opacity-60'"
                        >
                          <!-- Equal halves -->
                          <div class="flex h-full w-full flex-col">
                            <!-- + button -->
                            <button
                              type="button"
                              class="flex h-1/2 items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700/60"
                              @click="dev.count = Math.max(1, parseInt((dev.count ?? 1), 10) + 1)"
                              :disabled="!dev.enabled"
                              aria-label="Increase"
                            >
                              <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                              </svg>
                            </button>

                            <!-- − button -->
                            <button
                              type="button"
                              class="flex h-1/2 items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700/60"
                              @click="dev.count = Math.max(1, parseInt((dev.count ?? 1), 10) - 1)"
                              :disabled="!dev.enabled"
                              aria-label="Decrease"
                            >
                              <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M5 12h14"/>
                              </svg>
                            </button>
                          </div>

                          <!-- Visual divider that doesn't take space -->
                          <span class="pointer-events-none absolute inset-x-1 top-1/2 -translate-y-1/2 h-px bg-slate-600/60"></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Microsoft 365 protected accounts -->
                  <div class="flex items-center justify-between px-4 py-3 gap-4">
                    <div class="min-w-0">
                      <label for="q-m365" class="block text-sm font-medium text-slate-200">Microsoft 365 protected accounts</label>
                      <p class="text-xs text-slate-400">Limit the number of Microsoft 365 accounts protected by this user.</p>
                    </div>
                    <div class="flex items-center gap-4">
                      <button 
                        type="button"
                        @click="m365.enabled = !m365.enabled; if (!m365.enabled) { m365.count = 0 } else if (m365.count < 1) { m365.count = 1 }"
                        :class="m365.enabled ? 'bg-emerald-600' : 'bg-slate-600'"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                        :aria-pressed="m365.enabled ? 'true' : 'false'"
                        aria-label="Toggle Microsoft 365 accounts"
                      >
                        <span 
                          :class="m365.enabled ? 'translate-x-6' : 'translate-x-1'"
                          class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                        ></span>
                      </button>
                      <!-- number input with custom stepper -->                    
                      <div class="relative group" :class="!m365.enabled && 'opacity-50 cursor-not-allowed pointer-events-none'">
                        <input
                          id="q-m365"
                          type="number"
                          inputmode="numeric"
                          min="1" step="1"
                          x-model.number="m365.count"
                          :disabled="!m365.enabled"
                          :tabindex="m365.enabled ? '0' : '-1'"
                          class="peer no-native-spin h-9 w-28 rounded-lg border border-slate-600/70 bg-slate-900 pr-10 pl-3
                                text-left text-slate-100 outline-none transition
                                focus:border-slate-500 focus:ring-2 focus:ring-emerald-500/80"
                          @blur="if (m365.enabled) m365.count = Math.max(1, parseInt(m365.count || 1))"
                        />

                        <!-- Compact custom stepper -->
                        <div
                          class="pointer-events-auto absolute right-1.5 top-1/2 -translate-y-1/2
                                h-7 w-7 overflow-hidden rounded-md border border-slate-600/70 bg-slate-800/90 shadow-sm
                                opacity-0 transition
                                group-hover:opacity-100 peer-focus:opacity-100"
                          :class="!m365.enabled && 'opacity-60'"
                        >
                          <!-- Equal halves -->
                          <div class="flex h-full w-full flex-col">
                            <!-- + button -->
                            <button
                              type="button"
                              class="flex h-1/2 items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700/60"
                              @click="m365.count = Math.max(1, parseInt((m365.count ?? 1), 10) + 1)"
                              :disabled="!m365.enabled"
                              aria-label="Increase"
                            >
                              <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                              </svg>
                            </button>

                            <!-- − button -->
                            <button
                              type="button"
                              class="flex h-1/2 items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700/60"
                              @click="m365.count = Math.max(1, parseInt((m365.count ?? 1), 10) - 1)"
                              :disabled="!m365.enabled"
                              aria-label="Decrease"
                            >
                              <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M5 12h14"/>
                              </svg>
                            </button>
                          </div>

                          <!-- Visual divider that doesn't take space -->
                          <span class="pointer-events-none absolute inset-x-1 top-1/2 -translate-y-1/2 h-px bg-slate-600/60"></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Hyper-V guests -->
                  <div class="flex items-center justify-between px-4 py-3 gap-4">
                    <div class="min-w-0">
                      <label for="q-hv" class="block text-sm font-medium text-slate-200">Hyper-V guests</label>
                      <p class="text-xs text-slate-400">Limit the number of Hyper-V virtual machines protected by this user.</p>
                    </div>
                    <div class="flex items-center gap-4">
                      <button 
                        type="button"
                        @click="hv.enabled = !hv.enabled; if (!hv.enabled) { hv.count = 0 } else if (hv.count < 1) { hv.count = 1 }"
                        :class="hv.enabled ? 'bg-emerald-600' : 'bg-slate-600'"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                        :aria-pressed="hv.enabled ? 'true' : 'false'"
                        aria-label="Toggle Hyper-V guests"
                      >
                        <span 
                          :class="hv.enabled ? 'translate-x-6' : 'translate-x-1'"
                          class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                        ></span>
                      </button>
                      <!-- number input with custom stepper -->                    
                      <div class="relative group" :class="!hv.enabled && 'opacity-50 cursor-not-allowed pointer-events-none'">
                        <input
                          id="q-hv"
                          type="number"
                          inputmode="numeric"
                          min="1" step="1"
                          x-model.number="hv.count"
                          :disabled="!hv.enabled"
                          :tabindex="hv.enabled ? '0' : '-1'"
                          class="peer no-native-spin h-9 w-28 rounded-lg border border-slate-600/70 bg-slate-900 pr-10 pl-3
                                text-left text-slate-100 outline-none transition
                                focus:border-slate-500 focus:ring-2 focus:ring-emerald-500/80"
                          @blur="if (hv.enabled) hv.count = Math.max(1, parseInt(hv.count || 1))"
                        />

                        <!-- Compact custom stepper -->
                        <div
                          class="pointer-events-auto absolute right-1.5 top-1/2 -translate-y-1/2
                                h-7 w-7 overflow-hidden rounded-md border border-slate-600/70 bg-slate-800/90 shadow-sm
                                opacity-0 transition
                                group-hover:opacity-100 peer-focus:opacity-100"
                          :class="!hv.enabled && 'opacity-60'"
                        >
                          <!-- Equal halves -->
                          <div class="flex h-full w-full flex-col">
                            <!-- + button -->
                            <button
                              type="button"
                              class="flex h-1/2 items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700/60"
                              @click="hv.count = Math.max(1, parseInt((hv.count ?? 1), 10) + 1)"
                              :disabled="!hv.enabled"
                              aria-label="Increase"
                            >
                              <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                              </svg>
                            </button>

                            <!-- − button -->
                            <button
                              type="button"
                              class="flex h-1/2 items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700/60"
                              @click="hv.count = Math.max(1, parseInt((hv.count ?? 1), 10) - 1)"
                              :disabled="!hv.enabled"
                              aria-label="Decrease"
                            >
                              <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M5 12h14"/>
                              </svg>
                            </button>
                          </div>

                          <!-- Visual divider that doesn't take space -->
                          <span class="pointer-events-none absolute inset-x-1 top-1/2 -translate-y-1/2 h-px bg-slate-600/60"></span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- VMware guests -->
                  <div class="flex items-center justify-between px-4 py-3 gap-4">
                    <div class="min-w-0">
                      <label for="q-vmw" class="block text-sm font-medium text-slate-200">VMware guests</label>
                      <p class="text-xs text-slate-400">Limit the number of VMware virtual machines protected by this user.</p>
                    </div>
                    <div class="flex items-center gap-4">
                      <button 
                        type="button"
                        @click="vmw.enabled = !vmw.enabled; if (!vmw.enabled) { vmw.count = 0 } else if (vmw.count < 1) { vmw.count = 1 }"
                        :class="vmw.enabled ? 'bg-emerald-600' : 'bg-slate-600'"
                        class="relative inline-flex h-6 w-11 items-center rounded-full transition"
                        :aria-pressed="vmw.enabled ? 'true' : 'false'"
                        aria-label="Toggle VMware guests"
                      >
                        <span 
                          :class="vmw.enabled ? 'translate-x-6' : 'translate-x-1'"
                          class="inline-block h-4 w-4 transform rounded-full bg-white transition"
                        ></span>
                      </button>
                      <!-- number input with custom stepper -->                    
                      <div class="relative group" :class="!vmw.enabled && 'opacity-50 cursor-not-allowed pointer-events-none'">
                        <input
                          id="q-vmw"
                          type="number"
                          inputmode="numeric"
                          min="1" step="1"
                          x-model.number="vmw.count"
                          :disabled="!vmw.enabled"
                          :tabindex="vmw.enabled ? '0' : '-1'"
                          class="peer no-native-spin h-9 w-28 rounded-lg border border-slate-600/70 bg-slate-900 pr-10 pl-3
                                text-left text-slate-100 outline-none transition
                                focus:border-slate-500 focus:ring-2 focus:ring-emerald-500/80"
                          @blur="if (vmw.enabled) vmw.count = Math.max(1, parseInt(vmw.count || 1))"
                        />

                        <!-- Compact custom stepper -->
                        <div
                          class="pointer-events-auto absolute right-1.5 top-1/2 -translate-y-1/2
                                h-7 w-7 overflow-hidden rounded-md border border-slate-600/70 bg-slate-800/90 shadow-sm
                                opacity-0 transition
                                group-hover:opacity-100 peer-focus:opacity-100"
                          :class="!vmw.enabled && 'opacity-60'"
                        >
                          <!-- Equal halves -->
                          <div class="flex h-full w-full flex-col">
                            <!-- + button -->
                            <button
                              type="button"
                              class="flex h-1/2 items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700/60"
                              @click="vmw.count = Math.max(1, parseInt((vmw.count ?? 1), 10) + 1)"
                              :disabled="!vmw.enabled"
                              aria-label="Increase"
                            >
                              <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
                              </svg>
                            </button>

                            <!-- − button -->
                            <button
                              type="button"
                              class="flex h-1/2 items-center justify-center text-slate-300 hover:text-white hover:bg-slate-700/60"
                              @click="vmw.count = Math.max(1, parseInt((vmw.count ?? 1), 10) - 1)"
                              :disabled="!vmw.enabled"
                              aria-label="Decrease"
                            >
                              <svg viewBox="0 0 24 24" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M5 12h14"/>
                              </svg>
                            </button>
                          </div>

                          <!-- Visual divider that doesn't take space -->
                          <span class="pointer-events-none absolute inset-x-1 top-1/2 -translate-y-1/2 h-px bg-slate-600/60"></span>
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
                    class="rounded-md bg-emerald-600 px-3 py-1.5 text-sm text-white hover:bg-emerald-500"
                    @click="await save()"
                  >Save quotas</button>
                </div>
              </div>
              <!-- END: Quota Controls -->
            </div>

            <!-- Storage usage (daily maxima) -->
            <div id="eb-storage-card" class="mt-6 rounded-xl border border-slate-700 bg-slate-800/60">
              <div class="px-4 py-3 border-b border-slate-700 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-200">Storage usage</h3>
                <div class="text-xs text-slate-400">Daily max, last 180 days</div>
              </div>
              <div class="px-4 py-3">
                <div class="flex items-center gap-3 mb-2 text-xs">
                  <span class="inline-flex items-center gap-1 text-slate-300"><span class="inline-block w-3 h-1.5 bg-blue-500"></span>Total</span>
                  <span class="inline-flex items-center gap-1 text-slate-300"><span class="inline-block w-3 h-1.5 bg-emerald-500"></span>S3-compatible</span>
                  <span class="inline-flex items-center gap-1 text-slate-300"><span class="inline-block w-3 h-1.5 bg-sky-500"></span>eazyBackup</span>
                </div>
                <div id="eb-storage-chart" style="width: 100%; height: 160px;"></div>
              </div>
            </div>
          </div>

          <div class="space-y-6">
            <div
              class="bg-slate-900 p-6 rounded-lg"
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
              <h3 class="text-lg font-semibold text-white mb-4">Email reporting</h3>
              <div class="space-y-4 text-sm">
                <div class="flex items-center justify-between">
                  <label for="er-enabled" class="text-gray-300">Enable reporting</label>
                  <input id="er-enabled" type="checkbox" class="h-5 w-5 rounded border-slate-600 bg-slate-700 text-sky-600" :checked="enabled" @change="enabled = $event.target.checked" aria-describedby="er-enabled-help">
                </div>
                <div id="er-enabled-help" class="text-xs text-slate-400">Turn on to receive email updates after backups.</div>

                <div>
                  <label class="block text-sm text-gray-300 mb-1">Recipients</label>
                  <div class="rounded border border-slate-700 bg-slate-800/50 p-2">
                    <div class="flex flex-wrap gap-2 mb-2">
                      <template x-for="(em,i) in recipients" :key="em">
                        <span class="inline-flex items-center gap-1 rounded-full bg-sky-400/10 border border-sky-400/30 px-2 py-1 text-slate-200">
                          <span class="font-mono text-xs" x-text="em"></span>
                          <button type="button" class="hover:text-rose-400" @click="remove(i)" aria-label="Remove recipient">&times;</button>
                        </span>
                      </template>
                    </div>
                    <div class="flex items-center gap-2">
                      <input type="email" class="flex-1 px-3 py-2 rounded border border-slate-600 bg-slate-700 focus:outline-none focus:ring-0 focus:border-sky-600 text-slate-200" placeholder="name@example.com" x-model.trim="emailInput" @keydown.enter.prevent="add()" :disabled="!enabled">
                      <button type="button" class="px-3 py-2 rounded bg-slate-700 hover:bg-slate-600 text-white disabled:opacity-50" @click="add()" :disabled="!enabled">Add</button>
                    </div>
                    <div class="mt-1 text-xs" :class="emailError ? 'text-rose-400' : 'text-slate-400'" x-text="emailError || 'Add one or more email addresses to receive reports.'"></div>
                  </div>
                </div>

                <fieldset>
                  <legend class="block text-sm text-gray-300 mb-1">Report rules</legend>
                  <div class="space-y-2">
                    <label class="flex items-center gap-2 text-slate-200 cursor-pointer">
                      <input type="radio" name="er-mode" value="default" x-model="mode" class="rounded border-slate-600 bg-slate-700 text-sky-600">
                      <span>Use system default</span>
                    </label>
                    <label class="flex items-center gap-2 text-slate-200 cursor-pointer">
                      <input type="radio" name="er-mode" value="custom" x-model="mode" class="rounded border-slate-600 bg-slate-700 text-sky-600">
                      <span>Customize for this user</span>
                    </label>
                  </div>
                </fieldset>

                <div x-show="mode === 'custom'" x-cloak>
                  <label class="block text-sm text-gray-300 mb-1">Preset</label>
                  <div class="relative" x-data="{ open:false, options:[
                      { value: 'errors', label: 'Errors only' },
                      { value: 'warn_error', label: 'Warnings and Errors' },
                      { value: 'warn_error_missed', label: 'Warnings, Errors, and Missed' },
                      { value: 'success', label: 'Success only' },
                    ] }" @click.away="open=false">
                    <button type="button" @click="open = !open" class="relative w-full px-3 py-2 text-left bg-slate-700 border border-slate-600 rounded-md shadow-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition">
                      <span class="block truncate text-slate-200" x-text="(options.find(o=>o.value===preset)||{}).label || 'Select preset'"></span>
                      <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                        <svg class="h-5 w-5 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                      </span>
                    </button>
                    <div x-show="open" x-transition class="absolute z-10 mt-1 w-full bg-slate-700 shadow-lg rounded-md border border-slate-600">
                      <ul class="py-1 max-h-64 overflow-auto">
                        <template x-for="opt in options" :key="opt.value">
                          <li>
                            <a href="#" @click.prevent="preset = opt.value; open=false" class="block px-4 py-2 text-sm text-slate-200 hover:bg-sky-600 hover:text-white" :class="{ 'bg-sky-600 text-white': preset === opt.value }" x-text="opt.label"></a>
                          </li>
                        </template>
                      </ul>
                    </div>
                  </div>
                  <div class="mt-2 text-xs text-slate-400">Immediate emails will be sent when a backup matches the selected statuses.</div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                  <button type="button" class="px-3 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded text-white disabled:opacity-50" x-show="mode === 'custom'" @click="preview()" :disabled="saving">Preview</button>
                  <button type="button" class="px-4 py-2 text-sm bg-sky-600 hover:bg-sky-700 rounded text-white disabled:opacity-50" @click="save()" :disabled="saving">Save</button>
                </div>

                <div class="text-xs mt-1" :class="error ? 'text-rose-400' : 'text-emerald-400'" x-text="error || (ok ? 'Saved.' : '')"></div>
              </div>
            </div>
            <div class="bg-slate-900 p-6 rounded-lg">                          
              <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                  <h3 class="text-lg font-semibold text-white mb-4">Storage Vaults</h3>
                  <a href="#" @click.prevent="activeSubTab = 'storage'" class="text-sky-500 hover:underline">Configure...</a>
                </div>
                  {if $vaults}
                      {foreach from=$vaults item=vault}
                          <div class="flex justify-between">
                              <span class="text-gray-300">{$vault.Description}</span>
                          </div>
                      {/foreach}
                  {else}
                      <p class="text-gray-400">No storage vaults found.</p>
                  {/if}
              </div>
            </div>
          </div>
        </div> 
      </div>

      <div x-show="activeSubTab === 'protectedItems'" x-cloak x-transition>
        <div class="bg-slate-900 rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-slate-700">
              <thead class="bg-slate-800/50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Size</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-700">
                {foreach from=$protectedItems item=item}
                  <tr class="hover:bg-slate-800/60">
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$item.name}</td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$item.type}</td>
                      <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($item.total_bytes)}</td>
                  </tr>
                {foreachelse}
                  <tr>
                      <td colspan="3" class="text-center py-6 text-sm text-gray-400">No protected items found for this user.</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
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
          
          {* Account-Level Billing Summary Card *}
          <div class="mb-4 p-4 bg-gradient-to-r from-slate-800/80 to-slate-900/80 rounded-xl border border-slate-700/60 shadow-lg">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                  <div class="flex items-center gap-3">
                      <div class="p-2 rounded-lg bg-sky-500/10 border border-sky-500/20">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                          </svg>
                      </div>
                      <div>
                          <h3 class="text-sm font-semibold text-white">Account Storage Summary</h3>
                          <p class="text-xs text-slate-400">{$vaultCount} vault{if $vaultCount != 1}s{/if}{if $quotaEnabledCount > 0}, {$quotaEnabledCount} with quota enabled{/if}</p>
                      </div>
                  </div>
                  <div class="flex flex-wrap items-center gap-4 sm:gap-6 text-sm">
                      <div class="flex flex-col">
                          <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium">Total Used</span>
                          <span class="text-slate-200 font-semibold">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($totalUsedBytes, 2)}</span>
                      </div>
                      <div class="flex flex-col">
                          <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium">Total Quota</span>
                          <span class="text-slate-200 font-semibold">{if $totalQuotaBytes > 0}{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($totalQuotaBytes, 2)}{else}<span class="text-slate-400">No quotas set</span>{/if}</span>
                      </div>
                      <div class="flex flex-col border-l border-slate-600/50 pl-4 sm:pl-6">
                          <span class="text-[10px] uppercase tracking-wider text-slate-500 font-medium flex items-center gap-1">
                              Billable Tier
                              <span class="cursor-help text-slate-400" title="Billing is based on the sum of all vault quotas, rounded up to the nearest 1TB tier.">
                                  <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                  </svg>
                              </span>
                          </span>
                          <span class="text-emerald-400 font-bold text-lg">{if $billableTB > 0}{$billableTB} TB{else}<span class="text-slate-400 text-sm font-normal">—</span>{/if}</span>
                      </div>
                  </div>
              </div>
              {if $totalQuotaBytes > 0}
              <div class="mt-3 pt-3 border-t border-slate-700/50">
                  <p class="text-[11px] text-slate-400 leading-relaxed">
                      <svg class="inline h-3.5 w-3.5 mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                      </svg>
                      Your total quota across all vaults is <strong class="text-slate-300">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($totalQuotaBytes, 2)}</strong>. 
                      Billing is calculated by summing all vault quotas, then rounding up to the nearest 1TB tier 
                      (<strong class="text-emerald-400">{$billableTB} TB</strong>).
                  </p>
              </div>
              {/if}
          </div>
          
          <div class="bg-slate-900 rounded-lg overflow-hidden" x-data="{
              open:false,
              search:'',
              cols:{ name:true, id:true, type:true, init:true, stored:true, quota:true, usage:true, actions:true },
              matchesSearch(el){ const q=this.search.trim().toLowerCase(); if(!q) return true; return (el.textContent||'').toLowerCase().includes(q); },
              pctColor(p){ if(p===null) return 'bg-slate-700'; if(p<70) return 'bg-emerald-500'; if(p<90) return 'bg-amber-500'; return 'bg-rose-500'; }
          }">
              <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 pt-4 pb-2">
                  <div class="relative shrink-0" @click.away="open=false">
                      <button type="button" class="inline-flex items-center px-3 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded text-white" @click="open=!open">
                          View
                          <svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                      </button>
                      <div x-show="open" x-transition class="absolute mt-2 w-56 bg-slate-800 border border-slate-700 rounded shadow-lg z-10">
                          <div class="p-3 space-y-2 text-slate-200 text-sm">
                              <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.name"> Storage Vault</label>
                              <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.id"> Storage Vault ID</label>
                              <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.type"> Type</label>
                              <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.init"> Initialized</label>
                              <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.stored"> Stored</label>
                              <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.quota"> Quota</label>
                              <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.usage"> Usage</label>
                              <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.actions"> Actions</label>
                          </div>
                      </div>
                  </div>
                  <div class="w-full sm:w-72 sm:max-w-xs">
                      <input type="text" x-model.debounce.200ms="search" placeholder="Search vaults..." class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-700 text-slate-200 focus:outline-none focus:ring-0 focus:border-sky-600">
                  </div>
              </div>
              <div class="table-scroll overflow-x-auto">
              <table class="min-w-full divide-y divide-slate-700">
                  <thead class="bg-slate-800/50">
                      <tr>
                          <th x-show="cols.name" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Storage Vault</th>
                          <th x-show="cols.id" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Storage Vault ID</th>
                          <th x-show="cols.type" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                          <th x-show="cols.init" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Initialized</th>
                          <th x-show="cols.stored" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Stored</th>
                          <th x-show="cols.quota" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">
                              <span class="inline-flex items-center gap-1">
                                  Quota
                                  <span class="cursor-help text-slate-500 hover:text-slate-300" title="Per-vault quota. Billing is based on the sum of all vault quotas, rounded up to the nearest 1TB tier.">
                                      <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                      </svg>
                                  </span>
                              </span>
                          </th>
                          <th x-show="cols.usage" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Usage</th>
                          <th x-show="cols.actions" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
                  <tbody class="divide-y divide-slate-700">
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
                          <tr class="hover:bg-slate-800/60" x-show="matchesSearch($el)" x-cloak
                              data-used-bytes="{$usedBytes}"
                              data-quota-bytes="{$quotaBytes}">
                              <td x-show="cols.name" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$vault.Description|default:'-'}</td>
                              <td x-show="cols.id" class="px-4 py-4 whitespace-nowrap text-xs font-mono text-gray-400">{$vaultId}</td>
                              <td x-show="cols.type" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                  {assign var=destType value=$vault.DestinationType|default:''}
                                  {if $destType ne ''}
                                      <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-700 text-slate-200 text-xs">{\WHMCS\Module\Addon\Eazybackup\Helper::vaultTypeLabel($destType)}</span>
                                  {else}
                                      <span class="text-slate-400">-</span>
                                  {/if}
                              </td>
                              <td x-show="cols.init" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                  {assign var=ri value=$vault.RepoInitTimestamp|default:0}
                                  {if $ri>0}
                                      <span class="font-mono text-xs">{\WHMCS\Module\Addon\Eazybackup\Helper::formatDateTime($ri)}</span>
                                  {else}
                                      <span class="text-slate-400">-</span>
                                  {/if}
                              </td>
                              <td x-show="cols.stored" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
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
                                  <button type="button" class="eb-stats-btn inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 hover:bg-slate-600 text-slate-200"
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
                              <td x-show="cols.quota" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                  {if not $hasQuota}
                                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-300">Unlimited</span>
                                  {else}
                                      <div class="flex flex-col gap-1">
                                          <span class="inline-flex items-center gap-2">
                                              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-200" title="Exact quota: {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($quotaBytes, 2)}">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($quotaBytes, 2)}</span>
                                              {if $quotaEnabled}
                                                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-900/40 text-emerald-300">On</span>
                                              {else}
                                                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-700 text-slate-300">Off</span>
                                              {/if}
                                              <button type="button" class="configure-vault-button ml-1 p-1.5 rounded hover:bg-slate-700 text-slate-300"
                                                  title="Edit quota"
                                                  data-vault-id="{$vaultId}"
                                                  data-vault-name="{$vault.Description}"
                                                  data-vault-quota-enabled="{$quotaEnabled}"
                                                  data-vault-quota-bytes="{$quotaBytes}">
                                                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232a2.5 2.5 0 113.536 3.536L7.5 20.036 3 21l.964-4.5L15.232 5.232z"/></svg>
                                              </button>
                                          </span>
                                          <span class="text-[10px] text-slate-500">Per-vault limit</span>
                                      </div>
                                  {/if}
                              </td>
                              <td x-show="cols.usage" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                  {if $hasQuota}
                                      {assign var=pctClamped value=$pct}
                                      {if $pctClamped > 100}
                                          {assign var=pctClamped value=100}
                                      {elseif $pctClamped < 0}
                                          {assign var=pctClamped value=0}
                                      {/if}
                                      <div class="w-56">
                                          <div class="h-2.5 w-full rounded bg-slate-800/70 overflow-hidden" title="{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($usedBytes, 2)} of {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($quotaBytes, 2)} ({$pctClamped|string_format:'%.1f'}%) — measured {\WHMCS\Module\Addon\Eazybackup\Helper::formatDateTime($usedMeasuredEnd)}">
                                              <div class="h-full transition-[width] duration-500" :class="pctColor({$pctClamped})" style="width: {$pctClamped}%;"></div>
                                          </div>
                                          <div class="mt-1 text-xs text-slate-400">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($usedBytes, 2)} / {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($quotaBytes, 2)} ({$pctClamped|string_format:'%.1f'}%)</div>
                                      </div>
                                  {else}
                                      <div class="w-56">
                                          <div class="h-2.5 w-full rounded bg-slate-800/70 overflow-hidden">
                                              <div class="h-full w-1/3 bg-gradient-to-r from-slate-600/40 via-slate-500/40 to-slate-600/40 animate-pulse"></div>
                                          </div>
                                          <div class="mt-1 text-xs text-slate-500">Usage unavailable (no quota)</div>
                                      </div>
                                  {/if}
                              </td>
                              <td x-show="cols.actions" class="px-4 py-4 whitespace-nowrap text-sm">
                                  <button class="open-vault-panel px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 rounded text-white"
                                          data-vault-id="{$vaultId}"
                                          data-vault-name="{$vault.Description}"
                                          data-vault-quota-enabled="{$quotaEnabled}"
                                          data-vault-quota-bytes="{$quotaBytes}">Manage</button>
                              </td>
                          </tr>
                      {foreachelse}
                          <tr>
                              <td colspan="8" class="text-center py-6 text-sm text-gray-400">No storage vaults found for this user.</td>
                </tr>
              {/foreach}
            </tbody>
          </table>
              </div>
        </div>
      </div>

      <div x-show="activeSubTab === 'devices'" x-cloak x-transition>
        <div class="bg-slate-900 rounded-lg overflow-hidden" x-data="{
            open:false,
            search:'',
            cols:{ status:true, name:true, id:true, reg:true, ver:true, plat:true, rfa:true, items:true, actions:true },
            matchesSearch(el){ const q=this.search.trim().toLowerCase(); if(!q) return true; return (el.textContent||'').toLowerCase().includes(q); }
        }">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 pt-4 pb-2">
                <div class="relative shrink-0" @click.away="open=false">
                    <button type="button" class="inline-flex items-center px-3 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded text-white" @click="open=!open">
                        View
                        <svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-transition class="absolute mt-2 w-56 bg-slate-800 border border-slate-700 rounded shadow-lg z-10">
                        <div class="p-3 space-y-2 text-slate-200 text-sm">
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.status"> Status</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.name"> Device Name</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.id"> Device ID</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.reg"> Registered</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.ver"> Version</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.plat"> Platform</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.rfa"> Remote File Access</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.items"> Protected Items</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.actions"> Actions</label>
                        </div>
                    </div>
                </div>
                <div class="w-full sm:w-72 sm:max-w-xs">
                    <input type="text" x-model.debounce.200ms="search" placeholder="Search devices..." class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-700 text-slate-200 focus:outline-none focus:ring-0 focus:border-sky-600">
                </div>
            </div>
            <div class="table-scroll overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-700">
                <thead class="bg-slate-800/50">
                    <tr>
                        <th x-show="cols.status" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                        <th x-show="cols.name" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Device Name</th>
                        <th x-show="cols.id" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Device ID</th>
                        <th x-show="cols.reg" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Registered</th>
                        <th x-show="cols.ver" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Version</th>
                        <th x-show="cols.plat" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Platform</th>
                        <th x-show="cols.rfa" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Remote File Access</th>
                        <th x-show="cols.items" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Protected Items</th>
                        <th x-show="cols.actions" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
                <tbody class="divide-y divide-slate-700">
                    {foreach from=$devices item=device}
                        <tr class="hover:bg-slate-800/60" x-show="matchesSearch($el)" x-cloak>
                            <td x-show="cols.status" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                {if $device.status == 'Online'}
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/50 text-green-300">Online</span>
                                {else}
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-gray-300">Offline</span>
                                {/if}
                            </td>
                            <td x-show="cols.name" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$device.device_name}</td>
                            <td x-show="cols.id" class="px-4 py-4 whitespace-nowrap text-xs font-mono text-gray-400">{$device.device_id}</td>
                            <td x-show="cols.reg" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$device.registered}</td>
                            <td x-show="cols.ver" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$device.version}</td>
                            <td x-show="cols.plat" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$device.platform}</td>
                            <td x-show="cols.rfa" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$device.remote_file_access}</td>
                            <td x-show="cols.items" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$device.protected_items}</td>
                            <td x-show="cols.actions" class="px-4 py-4 whitespace-nowrap text-sm">
                                <button type="button" class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 rounded text-white" data-action="open-device-panel" data-device-id="{$device.device_id}" data-device-name="{$device.device_name}" data-device-online="{if $device.status == 'Online'}1{else}0{/if}">Manage</button>
                            </td>
                        </tr>
                    {foreachelse}
                        <tr>
                            <td colspan="9" class="text-center py-6 text-sm text-gray-400">No devices found for this user.</td>
                </tr>
              {/foreach}          
            </tbody>
          </table>
            </div>
        </div>
      </div>
      
      <div x-show="activeSubTab === 'jobLogs'" x-cloak x-transition>
        <div class="bg-slate-900 rounded-lg overflow-hidden" x-data="{ open:false, search:'', cols:{ user:true, id:false, device:true, item:true, vault:false, ver:false, type:true, status:true, dirs:false, files:false, size:true, vsize:true, up:false, down:false, started:true, ended:true, dur:true } }">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 pt-4 pb-2">
                <div class="relative shrink-0" @click.away="open=false">
                    <button type="button" class="inline-flex items-center px-3 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded text-white" @click="open=!open">
                        View
                        <svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-transition class="absolute mt-2 w-72 bg-slate-800 border border-slate-700 rounded shadow-lg z-10">
                        <div class="p-3 grid grid-cols-2 gap-2 text-slate-200 text-sm">
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.user"> Username</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.id"> Job ID</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.device"> Device</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.item"> Protected Item</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.vault"> Storage Vault</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.ver"> Version</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.type"> Type</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.status"> Status</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.dirs"> Directories</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.files"> Files</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.size"> Size</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.vsize"> Storage Vault Size</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.up"> Uploaded</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.down"> Downloaded</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.started"> Started</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.ended"> Ended</label>
                            <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.dur"> Duration</label>
                        </div>
                    </div>
                </div>
                <div class="w-full sm:w-72 sm:max-w-xs">
                    <input id="jobs-search" type="text" placeholder="Search jobs..." class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-700 text-slate-200 focus:outline-none focus:ring-0 focus:border-sky-600">
                </div>
            </div>
            <div class="px-4 text-xs text-slate-400 mb-1">Total: <span id="jobs-total">0</span></div>

            <!-- scroll wrapper: horizontal scroll for column overflow -->
            <div class="px-4 pb-2">
              <div class="table-scroll overflow-x-auto rounded-md border border-slate-800">
                <table id="jobs-table" class="min-w-full divide-y divide-slate-700" data-job-table>
                    <thead class="bg-slate-800/50">
                        <tr>
                            <th x-show="cols.user"   data-sort="Username"    class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Username</th>
                            <th x-show="cols.id" x-cloak    data-sort="JobID"       class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Job ID</th>
                            <th x-show="cols.device" data-sort="Device"      class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Device</th>
                            <th x-show="cols.item"   data-sort="ProtectedItem" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Protected Item</th>
                            <th x-show="cols.vault"  data-sort="StorageVault" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Storage Vault</th>
                            <th x-show="cols.ver"    data-sort="Version"     class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Version</th>
                            <th x-show="cols.type"   data-sort="Type"        class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Type</th>
                            <th x-show="cols.status" data-sort="Status"      class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Status</th>
                            <th x-show="cols.dirs"   data-sort="Directories" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Directories</th>
                            <th x-show="cols.files"  data-sort="Files"       class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Files</th>
                            <th x-show="cols.size"   data-sort="Size"        class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Size</th>
                            <th x-show="cols.vsize"  data-sort="VaultSize"   class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Storage Vault Size</th>
                            <th x-show="cols.up"     data-sort="Uploaded"    class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Uploaded</th>
                            <th x-show="cols.down"   data-sort="Downloaded"  class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Downloaded</th>
                            <th x-show="cols.started" data-sort="Started"    class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Started</th>
                            <th x-show="cols.ended"   data-sort="Ended"      class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Ended</th>
                            <th x-show="cols.dur"     data-sort="Duration"   class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer">Duration</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700"></tbody>
                </table>
              </div>
            </div>
            <div class="flex items-center justify-between px-4 py-2">
                <div id="jobs-pager" class="space-x-2 text-small font-medium text-slate-400"></div>
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
       class="absolute inset-0 bg-black/50 pointer-events-auto"></div>
  
  {* Panel *}
  <div id="vault-slide-panel" 
       x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="translate-x-full opacity-0"
       x-transition:enter-end="translate-x-0 opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="translate-x-0 opacity-100"
       x-transition:leave-end="translate-x-full opacity-80"
       class="fixed inset-y-0 right-0 w-full max-w-2xl bg-slate-950/95 border-l border-slate-800 shadow-2xl pointer-events-auto">
    <div class="h-full flex flex-col"
        data-modulelink="{$modulelink}" data-serviceid="{$serviceid}" data-username="{$username}">
    
    {* Header with staggered fade-in *}
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800"
         x-show="open"
         x-transition:enter="transition ease-out duration-300 delay-100"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
      <div>
        <h3 class="text-slate-100 text-lg font-semibold">Manage Storage Vault</h3>
        <div class="text-xs text-slate-400 mt-0.5">Vault: <span id="vault-panel-name" class="text-sky-400 font-mono"></span></div>
      </div>
      <button id="vault-panel-close" 
              @click="open = false; window.dispatchEvent(new CustomEvent('vault-panel:closed'))"
              class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-800 bg-slate-900/40 text-slate-300 hover:bg-slate-900/70 hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50"
              aria-label="Close">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <input type="hidden" id="vault-mgr-id" value="" />

    {* Content with staggered fade-in *}
    <div class="flex-1 overflow-y-auto" x-data="{ tab: 'general' }">
      <div class="px-5 pt-3 border-b border-slate-800"
           x-show="open"
           x-transition:enter="transition ease-out duration-300 delay-150"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-100"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0">
        <nav class="flex space-x-4" aria-label="Tabs">
          <a href="#" @click.prevent="tab='general'" :class="tab==='general' ? 'text-sky-400 border-sky-500' : 'text-slate-300 border-transparent hover:text-slate-100'" class="px-1 pb-2 border-b-2 text-sm font-medium transition">General</a>
          <a href="#" @click.prevent="tab='retention'" :class="tab==='retention' ? 'text-sky-400 border-sky-500' : 'text-slate-300 border-transparent hover:text-slate-100'" class="px-1 pb-2 border-b-2 text-sm font-medium transition">Retention</a>
          <a href="#" @click.prevent="tab='danger'" :class="tab==='danger' ? 'text-rose-400 border-rose-500' : 'text-slate-300 border-transparent hover:text-slate-100'" class="px-1 pb-2 border-b-2 text-sm font-medium transition">Danger zone</a>
        </nav>
      </div>

      <!-- General Tab -->
      <div x-show="tab==='general'" x-transition class="px-5 py-5 space-y-6">
        <!-- Name -->
        <div>
          <label class="block text-sm text-slate-300 mb-1">Vault name</label>
          <input id="vault-mgr-name" type="text" class="w-full px-3 py-2 rounded-lg border border-slate-700 bg-slate-900/60 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/40 focus:border-sky-600 placeholder:text-slate-500 transition" placeholder="Vault name" />
        </div>
        <!-- Quota -->
        <div class="space-y-3">
          <label class="block text-sm text-slate-300">Quota</label>
          <div class="flex items-center gap-2">
            <input id="vault-quota-unlimited2" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500/40 focus:ring-offset-0">
            <span class="text-slate-300 text-sm">Unlimited</span>
          </div>
          <div class="flex items-center gap-2">
            <input id="vault-quota-size2" type="number" class="w-40 px-3 py-2 rounded-lg border border-slate-700 bg-slate-900/60 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/40 focus:border-sky-600 placeholder:text-slate-500 transition" placeholder="0" />
            <!-- Alpine unit dropdown -->
            <div class="relative" x-data="{ open:false, unit:'GB' }" @click.away="open=false">
              <input type="hidden" id="vault-quota-unit2" :value="unit">
              <button type="button" @click="open=!open" class="w-28 text-left px-3 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-slate-200 text-sm pr-8 hover:bg-slate-900/80 transition">
                <span x-text="unit"></span>
                <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-slate-400">
                  <svg class="h-4 w-4 ml-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                </span>
              </button>
              <div x-show="open" x-transition class="absolute z-10 mt-1 w-full bg-slate-900 border border-slate-700 rounded-lg shadow-lg">
                <ul class="py-1 text-sm text-slate-200">
                  <li><a href="#" class="block px-3 py-2 hover:bg-slate-800 transition" @click.prevent="unit='GB'; open=false">GB</a></li>
                  <li><a href="#" class="block px-3 py-2 hover:bg-slate-800 transition" @click.prevent="unit='TB'; open=false">TB</a></li>
                </ul>
              </div>
            </div>
          </div>
          <div class="text-xs text-slate-500">Changes apply to this vault only.</div>
        </div>
        <div class="pt-4 border-t border-slate-800 flex justify-end">
          <button id="vault-save-all" class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-emerald-500/40 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white transition hover:from-emerald-700 hover:to-emerald-600">Save</button>
        </div>
      </div>
              
        <!-- Retention Tab -->
        <div x-show="tab==='retention'" x-transition id="vault-retention-tab" class="px-5 py-5" x-data="retention()" @retention:update.window="state.override=$event.detail.override; state.mode=$event.detail.mode; state.ranges=$event.detail.ranges; state.defaultMode=$event.detail.defaultMode; state.defaultRanges=$event.detail.defaultRanges">
          <h4 class="text-slate-100 font-semibold mb-3">Retention</h4>

          <!-- Status callout -->
          <div class="mb-3">
            <div x-show="!state.override" class="inline-flex items-center gap-2 rounded-full bg-slate-700/70 text-slate-100 px-3 py-1.5 text-sm" title="This vault follows the account's default retention policy.">
              <svg class="size-4 opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
              <span>Using account-level policy</span>
            </div>
            <!-- Account policy preview -->
            <div x-show="showAccountPolicy" class="rounded-xl border border-slate-700 bg-slate-800/70 p-3 mb-3">
              <div class="flex items-center justify-between mb-2">
                <div class="text-slate-200 font-medium">Account-level policy</div>
                <button class="text-slate-300 hover:text-white text-sm" @click="showAccountPolicy=false">Close</button>
              </div>
              <ul class="list-disc pl-5 text-slate-200 text-sm space-y-1" x-html="formattedDefaultPolicyLines().join('')"></ul>
            </div>
            <div x-show="state.override" class="inline-flex items-center gap-2 rounded-full bg-amber-900/40 text-amber-100 px-3 py-1.5 text-sm" title="This vault uses its own retention rules instead of the account default.">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
              </svg>          
              <span>This will have a custom policy (overrides default)</span>
            </div>
          </div>

          <!-- Override toggle -->
          <div class="flex items-center gap-2 mb-3">
            <input id="ret-override" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-600 text-sky-600" x-model="state.override">
            <label for="ret-override" class="text-sm text-slate-300">Override default retention for this vault</label>
          </div>

          <!-- Builder when override ON -->
          <template x-if="state.override">
            
            <!-- Mode select with helper text -->
            <div class="mb-2">
              <label class="block text-sm text-slate-300 mb-1">Mode</label>
              <select x-model.number="state.mode" class="w-96 px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm">
                <option value="801">Keep everything</option>
                <option value="802">Keep only backups that match these rules</option>
              </select>
              <p class="mt-1 text-xs text-slate-400" x-show="state.mode===802">Backups are kept if they match any rule below. Backups that match none of the rules will be deleted.</p>
            </div>
          
            <!-- Warning for keep everything -->
            <div x-show="state.mode===801" class="rounded-xl border border-red-500/50 bg-red-950/50 p-4 mb-3">
              <div class="flex gap-3">
                <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                <div class="text-red-100">
                  <p class="font-semibold">Warning: "Keep everything" keeps every backup forever.</p>
                  <p class="text-sm opacity-90">No data is ever removed from this vault. Storage usage—and your bill—will grow without limit. Choose this only if you fully understand the cost.</p>
                </div>
              </div>
            </div>

            <!-- Rules card editor -->
            <div class="space-y-3" x-data="{ editing:null }">
              <template x-for="(r,i) in state.ranges" :key="i">
                <div :class="['rounded-xl border bg-slate-800/60 p-3 shadow-sm', editing===i ? 'border-sky-500 ring-1 ring-sky-500/30' : 'border-slate-700']">
                  <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-2">
                      <span class="inline-flex items-center rounded-md bg-slate-700 px-2 py-0.5 text-xs font-medium" x-text="labelFor(r.Type)"></span>
                      <span class="text-slate-200" x-text="summaryFor(r)"></span>
                    </div>
                    <div class="flex gap-2">
                      <button class="text-sky-300 hover:text-sky-200 text-sm" @click="editing = (editing===i?null:i)"><span x-text="editing===i ? 'Close' : 'Edit'"></span></button>
                      <button class="text-rose-300 hover:text-rose-200 text-sm" @click="removeRange(i)">Remove</button>
                    </div>
                  </div>
                  <div x-show="editing===i" x-transition class="mt-3 border-t border-slate-700 pt-3">
                    <div class="grid grid-cols-2 gap-3">
                      <!-- Type select -->
                      <div>
                        <label class="block text-xs text-slate-400 mb-1">Type</label>
                        <select class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm" x-model.number="r.Type">
                          <option value="900">Most recent X jobs</option>
                          <option value="901">Newer than date</option>
                          <option value="902">Jobs since (relative)</option>
                          <option value="903">First job for last X days</option>
                          <option value="905">First job for last X months</option>
                          <option value="906">First job for last X weeks</option>
                          <option value="907">At most one per day (last X jobs)</option>
                          <option value="908">At most one per week (last X jobs)</option>
                          <option value="909">At most one per month (last X jobs)</option>
                          <option value="910">At most one per year (last X jobs)</option>
                          <option value="911">First job for last X years</option>
                        </select>
                      </div>
                      <!-- Jobs -->
                      <div x-show="[900,907,908,909,910].includes(r.Type)">
                        <label class="block text-xs text-slate-400 mb-1">Jobs</label>
                        <input type="number" x-model.number="r.Jobs" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm" placeholder="e.g., 7">
                      </div>
                      <!-- Timestamp -->
                      <div x-show="r.Type===901">
                        <label class="block text-xs text-slate-400 mb-1">Date</label>
                        <input type="datetime-local" @change="r.Timestamp=(Date.parse($event.target.value)/1000)|0" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm">
                      </div>
                      <!-- Relative fields -->
                      <template x-if="r.Type===902">
                        <div class="grid grid-cols-2 gap-3 col-span-2">
                          <div><label class="block text-xs text-slate-400 mb-1">Days</label><input type="number" x-model.number="r.Days" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                          <div><label class="block text-xs text-slate-400 mb-1">Weeks</label><input type="number" x-model.number="r.Weeks" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                          <div><label class="block text-xs text-slate-400 mb-1">Months</label><input type="number" x-model.number="r.Months" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                          <div><label class="block text-xs text-slate-400 mb-1">Years</label><input type="number" x-model.number="r.Years" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                          <div><label class="block text-xs text-slate-400 mb-1">Week Offset</label><input type="number" min="0" max="6" x-model.number="r.WeekOffset" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                          <div><label class="block text-xs text-slate-400 mb-1">Month Offset</label><input type="number" min="1" max="31" x-model.number="r.MonthOffset" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                          <div><label class="block text-xs text-slate-400 mb-1">Year Offset</label><input type="number" min="0" x-model.number="r.YearOffset" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                        </div>
                      </template>
                      <!-- Days/Weeks/Months/Years singular fields -->
                      <div x-show="r.Type===903"><label class="block text-xs text-slate-400 mb-1">Days</label><input type="number" x-model.number="r.Days" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                      <div x-show="r.Type===905"><label class="block text-xs text-slate-400 mb-1">Months</label><input type="number" x-model.number="r.Months" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"><label class="block text-xs text-slate-400 mt-1">Month Offset</label><input type="number" x-model.number="r.MonthOffset" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                      <div x-show="r.Type===906"><label class="block text-xs text-slate-400 mb-1">Weeks</label><input type="number" x-model.number="r.Weeks" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"><label class="block text-xs text-slate-400 mt-1">Week Offset</label><input type="number" x-model.number="r.WeekOffset" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                      <div x-show="r.Type===911"><label class="block text-xs text-slate-400 mb-1">Years</label><input type="number" x-model.number="r.Years" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"><label class="block text-xs text-slate-400 mt-1">Year Offset</label><input type="number" x-model.number="r.YearOffset" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                    </div>
                  </div>
                </div>
              </template>

              <!-- New rule composer -->
              <div x-show="editing===null" class="rounded-xl border border-dashed border-slate-700 p-3">
                <p class="text-slate-300 mb-2 font-medium">Add a rule</p>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs text-slate-400 mb-1">Type</label>
                    <select class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm" x-model.number="newRange.Type">
                      <option value="900">Most recent X jobs</option>
                      <option value="901">Newer than date</option>
                      <option value="902">Jobs since (relative)</option>
                      <option value="903">First job for last X days</option>
                      <option value="905">First job for last X months</option>
                      <option value="906">First job for last X weeks</option>
                      <option value="907">At most one per day (last X jobs)</option>
                      <option value="908">At most one per week (last X jobs)</option>
                      <option value="909">At most one per month (last X jobs)</option>
                      <option value="910">At most one per year (last X jobs)</option>
                      <option value="911">First job for last X years</option>
                    </select>
                  </div>
                  <div x-show="[900,907,908,909,910].includes(newRange.Type)"><label class="block text-xs text-slate-400 mb-1">Jobs</label><input type="number" x-model.number="newRange.Jobs" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm" placeholder="e.g., 7"></div>
                    <div x-show="newRange.Type===901"><label class="block text-xs text-slate-400 mb-1">Date</label><input type="datetime-local" @change="newRange.Timestamp=(Date.parse($event.target.value)/1000)|0" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                      <template x-if="newRange.Type===902">
                        <div class="grid grid-cols-2 gap-3 col-span-2">
                          <div><label class="block text-xs text-slate-400 mb-1">Days</label><input type="number" x-model.number="newRange.Days" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                          <div><label class="block text-xs text-slate-400 mb-1">Weeks</label><input type="number" x-model.number="newRange.Weeks" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                          <div><label class="block text-xs text-slate-400 mb-1">Months</label><input type="number" x-model.number="newRange.Months" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                          <div><label class="block text-xs text-slate-400 mb-1">Years</label><input type="number" x-model.number="newRange.Years" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm"></div>
                        </div>
                      </template>
                    </div>
                    <div class="mt-3">
                    <button class="rounded-lg bg-sky-600 hover:bg-sky-500 px-3 py-1.5 text-sm font-medium" @click="addRangeFromNew()">Add rule</button>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- When override OFF, show compact summary of inherited policy -->
          <template x-if="!state.override">
            <div class="text-sm text-slate-300">This vault follows the account default policy.</div>
          </template>

          <!-- Sticky summary -->
          <div class="sticky bottom-0 mt-4 rounded-xl border border-slate-700 bg-slate-800/80 backdrop-blur p-3">
            <p class="text-slate-300 font-medium mb-1">Effective policy:</p>
            <ul class="list-disc pl-5 text-slate-200 text-sm space-y-1" x-html="formattedEffectivePolicyLines().join('')"></ul>
          </div>
          <!-- Save button for retention -->
          <div class="mt-4 flex justify-end">
            <button id="vault-retention-save" class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-emerald-500/40 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white transition hover:from-emerald-700 hover:to-emerald-600">Save</button>
          </div>
        </div>

        <!-- Danger Tab -->
        <div x-show="tab==='danger'" x-transition class="px-5 py-5 space-y-4">
          <h4 class="text-rose-400 font-semibold">Danger zone</h4>
          <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4">
            <div class="flex items-start gap-3">
              <svg class="h-5 w-5 shrink-0 text-rose-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
              </svg>
              <div>
                <div class="font-medium text-rose-300">Delete this vault</div>
                <p class="mt-1 text-sm text-slate-300">Deleting a vault cannot be undone. All data will be permanently lost.</p>
              </div>
            </div>
          </div>
          <button id="vault-delete" class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-rose-500/40 bg-gradient-to-r from-rose-600 to-rose-500 text-white transition hover:from-rose-700 hover:to-rose-600">Delete Vault</button>
          <div id="vault-delete-confirm" class="hidden rounded-xl border border-slate-700 p-4 bg-slate-900/60 space-y-3">
            <div class="text-slate-100 text-sm font-semibold">Confirm your account password</div>
            <div class="text-slate-400 text-xs">This is the password you use to sign in to your eazyBackup Client Area.</div>
            <input id="vault-delete-password" type="password" class="w-full px-3 py-2 rounded-lg border border-slate-700 bg-slate-900/60 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500/40 focus:border-rose-600 placeholder:text-slate-500 transition" placeholder="Account password" />
            <div class="flex justify-end gap-3 pt-2">
              <button id="vault-delete-cancel" class="px-4 py-2.5 rounded-lg border border-slate-800 bg-transparent hover:bg-slate-900/60 text-slate-200 text-sm transition">Cancel</button>
              <button id="vault-delete-confirm-btn" class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-rose-500/40 bg-gradient-to-r from-rose-600 to-rose-500 text-white transition hover:from-rose-700 hover:to-rose-600">Confirm delete</button>
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
       class="absolute inset-0 bg-black/50 pointer-events-auto"></div>
  
  {* Drawer Panel *}
  <div x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="translate-x-full opacity-0"
       x-transition:enter-end="translate-x-0 opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="translate-x-0 opacity-100"
       x-transition:leave-end="translate-x-full opacity-80"
       class="fixed inset-y-0 right-0 z-[10060] w-full sm:max-w-[440px] bg-slate-950/95 border-l border-slate-800 shadow-2xl pointer-events-auto">
    
    <div class="h-full flex flex-col">
      {* Header *}
      <div class="px-5 py-4 border-b border-slate-800"
           x-show="open"
           x-transition:enter="transition ease-out duration-300 delay-100"
           x-transition:enter-start="opacity-0 -translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-lg font-semibold text-slate-100">Reset Password</div>
            <div class="text-xs text-slate-400 mt-0.5">Set a new password for <span class="text-sky-400 font-mono" x-text="username"></span></div>
          </div>
          <button type="button"
                  class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-800 bg-slate-900/40 text-slate-300 hover:bg-slate-900/70 hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50"
                  @click="closeDrawer()"
                  aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </div>
      
      {* Content *}
      <div class="flex-1 overflow-y-auto px-5 py-5">
        <div class="space-y-5"
             x-show="open"
             x-transition:enter="transition ease-out duration-300 delay-150"
             x-transition:enter-start="opacity-0 translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
          
          {* Info text *}
          <div class="text-sm text-slate-300">
            Enter a new password below, or generate a secure password automatically.
          </div>
          
          {* New Password Field *}
          <div class="space-y-2"
               x-show="open"
               x-transition:enter="transition ease-out duration-300 delay-200"
               x-transition:enter-start="opacity-0 translate-y-2"
               x-transition:enter-end="opacity-100 translate-y-0">
            <label for="rp-new-password" class="block text-sm font-medium text-slate-200">New Password</label>
            <div class="flex items-stretch gap-2">
              <div class="relative flex-1">
                <input id="rp-new-password"
                       :type="showPassword ? 'text' : 'password'"
                       x-model="password"
                       @input="checkMatch()"
                       placeholder="Enter new password (min 8 chars)"
                       class="w-full px-3 py-2.5 pr-10 rounded-lg border border-slate-700 bg-slate-900/60 text-slate-200 text-sm focus:outline-none focus:ring-0 focus:border-sky-600 placeholder:text-slate-500" />
                <button type="button"
                        @click="showPassword = !showPassword"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200"
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
                      class="inline-flex items-center gap-2 px-3 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-100 text-sm transition">
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
            <label for="rp-confirm-password" class="block text-sm font-medium text-slate-200">Confirm Password</label>
            <input id="rp-confirm-password"
                   :type="showPassword ? 'text' : 'password'"
                   x-model="confirmPassword"
                   @input="checkMatch()"
                   placeholder="Re-enter the password"
                   class="w-full px-3 py-2.5 rounded-lg border text-sm focus:outline-none focus:ring-0 placeholder:text-slate-500"
                   :class="confirmPassword && !passwordsMatch ? 'border-rose-500/60 bg-rose-500/10 text-rose-200' : 'border-slate-700 bg-slate-900/60 text-slate-200 focus:border-sky-600'" />
            
            {* Match indicator *}
            <div class="flex items-center gap-2 text-xs h-5">
              <template x-if="confirmPassword && passwordsMatch">
                <div class="flex items-center gap-1.5 text-emerald-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                  </svg>
                  Passwords match
                </div>
              </template>
              <template x-if="confirmPassword && !passwordsMatch">
                <div class="flex items-center gap-1.5 text-rose-400">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                  </svg>
                  Passwords do not match
                </div>
              </template>
            </div>
          </div>
          
          {* Password requirements *}
          <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-3"
               x-show="open"
               x-transition:enter="transition ease-out duration-300 delay-300"
               x-transition:enter-start="opacity-0 translate-y-2"
               x-transition:enter-end="opacity-100 translate-y-0">
            <div class="text-xs font-medium text-slate-300 mb-2">Password requirements</div>
            <ul class="space-y-1 text-xs text-slate-400">
              <li class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" :class="password.length >= 8 ? 'text-emerald-400' : 'text-slate-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span :class="password.length >= 8 ? 'text-slate-200' : ''">Minimum 8 characters</span>
              </li>
              <li class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                Use a unique password not used elsewhere
              </li>
            </ul>
          </div>
          
        </div>
      </div>
      
      {* Footer with action buttons *}
      <div class="px-5 py-4 border-t border-slate-800"
           x-show="open"
           x-transition:enter="transition ease-out duration-300 delay-[350ms]"
           x-transition:enter-start="opacity-0 translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-100"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-end gap-3">
          <button type="button"
                  class="px-4 py-2.5 rounded-lg border border-slate-800 bg-transparent hover:bg-slate-900/60 text-slate-200 text-sm transition"
                  @click="closeDrawer()">
            Cancel
          </button>
          <button type="button"
                  class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-sky-500/40 bg-gradient-to-r from-sky-500 to-sky-400 text-white transition hover:from-sky-600 hover:to-sky-500 disabled:opacity-50 disabled:cursor-not-allowed"
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
      overlay.className = 'fixed inset-0 z-[10070] flex items-center justify-center bg-black/60';
      overlay.innerHTML = `
        <div class="bg-slate-900/95 border border-slate-700 rounded-xl shadow-2xl w-full max-w-md mx-4">
          <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
            <h3 class="text-slate-100 text-lg font-semibold">Password Reset Successful</h3>
            <button class="text-slate-400 hover:text-slate-200" data-cmd="ok" title="Close">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="px-5 py-5 space-y-4">
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
              <div class="text-xs uppercase tracking-wide text-emerald-400 mb-2">New Password</div>
              <div class="flex items-center gap-3">
                <div id="success-password-text" class="select-all font-mono text-lg text-slate-100 flex-1">${escapeHtml(newPassword)}</div>
                <button id="success-copy-btn" type="button" class="inline-flex items-center p-2 rounded-lg border border-slate-600 hover:bg-slate-700 text-slate-300 hover:text-white transition" title="Copy">
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
                  <p class="mt-1 text-sm text-slate-300">On each computer, <strong>close and reopen the eazyBackup client</strong>, then sign in with your <strong>new backup account password</strong> so future backups do not fail.</p>
                </div>
              </div>
            </div>
          </div>
          <div class="px-5 py-4 border-t border-slate-700 flex justify-end">
            <button class="px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium transition" data-cmd="ok">Done</button>
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
<div id="totp-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 hidden">
  <div class="bg-slate-800/90 backdrop-blur-sm border; border-slate-700 rounded-lg shadow-lg w-full max-w-md">
    <div class="p-6">
      <div class="flex justify-between items-center pb-4">
        <h2 class="text-lg font-semibold text-slate-200">Two-Factor Authentication (TOTP)</h2>
        <button id="totp-modal-close" type="button" class="text-slate-500 hover:text-slate-300">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="space-y-4">
        <p id="totp-status" class="text-slate-300 text-sm"></p>
        <div class="flex justify-center">
          <img id="totp-qr-img" src="" alt="TOTP QR" class="rounded border border-slate-700 max-h-56" />
        </div>
        <div class="text-xs text-slate-400 break-words">
          <a id="totp-otp-url" href="#" target="_blank" class="text-sky-400 hover:text-sky-300"></a>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1">Enter 6-digit code</label>
          <input id="totp-code" type="text" inputmode="numeric" autocomplete="one-time-code" class="block w-full px-3 py-2 border border-slate-600 bg-slate-700 text-slate-200 rounded focus:outline-none focus:ring-2 focus:ring-sky-500" placeholder="123456" />
        </div>
        <div id="totp-error" class="hidden text-red-500 text-sm"></div>
      </div>
    </div>
    <div class="flex justify-end items-center mt-2 bg-slate-800/80 px-6 py-4 rounded-b-lg border-t border-slate-700">
      <button id="totp-confirm" class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700">Confirm</button>
    </div>
  </div>
</div>

{* Expose endpoint + context for TOTP JS *}
<script>
window.EB_TOTP_ENDPOINT = '{$modulelink}&a=totp';
</script>
<!-- Vault Storage Breakdown Modal -->
<div id="vault-stats-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/60"></div>
  <div class="relative mx-auto my-8 w-full max-w-3xl bg-slate-900 border border-slate-700 rounded-lg shadow-xl">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
      <h3 id="vsm-title" class="text-slate-200 text-lg font-semibold">Vault usage</h3>
      <button id="vsm-close" class="text-slate-400 hover:text-slate-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-5 py-4 space-y-3">
      <div id="vsm-summary" class="text-sm text-slate-300"></div>
      <div class="border border-slate-700 rounded overflow-hidden">
        <div class="grid grid-cols-12 gap-0 bg-slate-800/60 px-3 py-2 text-xs text-slate-300">
          <div class="col-span-4">Size</div>
          <div class="col-span-8">Used by</div>
        </div>
        <div id="vsm-rows" class="max-h-80 overflow-y-auto divide-y divide-slate-800"></div>
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
       class="absolute inset-0 bg-black/50 pointer-events-auto"></div>
  
  {* Panel *}
  <div id="device-slide-panel"
       x-show="open"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="translate-x-full opacity-0"
       x-transition:enter-end="translate-x-0 opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="translate-x-0 opacity-100"
       x-transition:leave-end="translate-x-full opacity-80"
       class="fixed inset-y-0 right-0 w-full max-w-xl bg-slate-950/95 border-l border-slate-800 shadow-2xl pointer-events-auto">
    <div class="h-full flex flex-col">
      
      {* Header with staggered fade-in *}
      <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800"
           x-show="open"
           x-transition:enter="transition ease-out duration-300 delay-100"
           x-transition:enter-start="opacity-0 -translate-y-2"
           x-transition:enter-end="opacity-100 translate-y-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100"
           x-transition:leave-end="opacity-0">
        <div>
          <h3 id="device-panel-title" class="text-slate-100 text-lg font-semibold">Manage Device</h3>
          <div class="text-xs text-slate-400 mt-0.5">Device: <span id="device-panel-name" class="text-sky-400 font-mono"></span></div>
        </div>
        <button id="device-panel-close"
                @click="open = false; window.dispatchEvent(new CustomEvent('device-panel:closed'))"
                class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-800 bg-slate-900/40 text-slate-300 hover:bg-slate-900/70 hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500/50"
                aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      
      {* Content with tabs *}
      <div x-data="{ tab: 'device', vaultOpen:false }" class="flex-1 overflow-y-auto">
        <div class="px-5 pt-3 border-b border-slate-800"
             x-show="open"
             x-transition:enter="transition ease-out duration-300 delay-150"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
          <nav class="flex space-x-4" aria-label="Tabs">
            <a href="#" @click.prevent="tab='device'" :class="tab==='device' ? 'text-sky-400 border-sky-500' : 'text-slate-300 border-transparent hover:text-slate-100'" class="px-1 pb-2 border-b-2 text-sm font-medium transition">Device</a>
            <a href="#" @click.prevent="tab='vault'"  :class="tab==='vault'  ? 'text-sky-400 border-sky-500' : 'text-slate-300 border-transparent hover:text-slate-100'" class="px-1 pb-2 border-b-2 text-sm font-medium transition">Storage Vault</a>
          </nav>
        </div>
        <div x-show="tab==='device'" x-transition class="px-5 py-5 space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <button id="btn-run-backup" class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-sky-500/40 bg-gradient-to-r from-sky-600 to-sky-500 text-white transition hover:from-sky-700 hover:to-sky-600">Run Backup…</button>
            <button id="open-restore" class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-sky-500/40 bg-gradient-to-r from-sky-600 to-sky-500 text-white transition hover:from-sky-700 hover:to-sky-600">Restore…</button>
            <button id="btn-update-software" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 hover:bg-slate-900/80 text-slate-200 text-sm font-medium transition">Update Software</button>
            <div class="flex items-center gap-2">
              <input id="inp-rename-device" type="text" placeholder="New device name" class="flex-1 px-3 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/40 focus:border-sky-600 placeholder:text-slate-500 transition"/>
              <button id="btn-rename-device" class="px-3 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 hover:bg-slate-900/80 text-slate-200 text-sm font-medium transition">Rename</button>
            </div>
            <button id="btn-revoke-device" class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-rose-500/40 bg-gradient-to-r from-rose-600 to-rose-500 text-white transition hover:from-rose-700 hover:to-rose-600">Revoke</button>
            <button id="btn-uninstall-software" class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-rose-500/40 bg-gradient-to-r from-rose-600/80 to-rose-500/80 text-white transition hover:from-rose-700 hover:to-rose-600">Uninstall Software</button>
          </div>
          <div class="text-xs text-slate-500">Note: Some actions require the device to be online.</div>

          <div class="mt-4 border-t border-slate-800 pt-4 hidden" x-data="{ piOpen:false, piLabel:'Choose a protected item…', piId:'', vOpen:false }">
            <h4 class="text-slate-100 font-semibold mb-3">Run Backup</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div class="relative">
                <label class="block text-sm text-slate-300 mb-1">Protected Item</label>
                <button type="button" id="pi-menu-button" @click="piOpen=!piOpen" class="w-full text-left px-3 py-2.5 bg-slate-900/60 border border-slate-700 rounded-lg text-slate-200 hover:bg-slate-900/80 transition">
                  <span id="pi-selected" x-text="piLabel"></span>
                </button>
                <div id="pi-menu" x-show="piOpen" x-transition class="absolute mt-1 w-full bg-slate-900 border border-slate-700 rounded-lg shadow-lg max-h-56 overflow-y-auto z-10">
                  <ul id="pi-list" class="py-1 text-sm text-slate-200">
                    <li><span class="block px-3 py-2 text-slate-400">Loading…</span></li>
                  </ul>
                </div>
              </div>
              <div class="relative">
                <label class="block text-sm text-slate-300 mb-1">Storage Vault</label>
                <button id="vault-menu-button-2" type="button" @click="vOpen=!vOpen" class="w-full text-left px-3 py-2.5 bg-slate-900/60 border border-slate-700 rounded-lg text-slate-200 hover:bg-slate-900/80 transition">
                  <span id="vault-selected-2">Choose a vault…</span>
                </button>
                <div id="vault-menu-2" x-show="vOpen" x-transition class="absolute mt-1 w-full bg-slate-900 border border-slate-700 rounded-lg shadow-lg max-h-56 overflow-y-auto z-10">
                  <ul class="py-1 text-sm text-slate-200">
                    {foreach from=$vaults item=vault key=vaultId}
                      <li><a href="#" class="block px-3 py-2 hover:bg-slate-800 transition" data-vault-id="{$vaultId}" data-vault-name="{$vault.Description}">{$vault.Description}</a></li>
                    {/foreach}
                  </ul>
                </div>
              </div>
            </div>
            <div class="mt-4 flex justify-end">
              <button id="btn-run-backup-exec" class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-sky-500/40 bg-gradient-to-r from-sky-600 to-sky-500 text-white transition hover:from-sky-700 hover:to-sky-600">Run Backup</button>
            </div>
          </div>
        </div>
        <div x-show="tab==='vault'" x-transition class="px-5 py-5 space-y-4">
          <div class="relative">
            <label class="block text-sm text-slate-300 mb-1">Select Storage Vault</label>
            <button id="vault-menu-button" type="button" class="w-full text-left px-3 py-2.5 bg-slate-900/60 border border-slate-700 rounded-lg text-slate-200 hover:bg-slate-900/80 transition">
              <span id="vault-selected">Choose a vault…</span>
            </button>
            <div id="vault-menu" class="absolute mt-1 w-full bg-slate-900 border border-slate-700 rounded-lg shadow-lg max-h-56 overflow-y-auto hidden z-10">
              <ul class="py-1 text-sm text-slate-200">
                {foreach from=$vaults item=vault key=vaultId}
                  <li><a href="#" class="block px-3 py-2 hover:bg-slate-800 transition" data-vault-id="{$vaultId}" data-vault-name="{$vault.Description}">{$vault.Description}</a></li>
                {/foreach}
              </ul>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <button id="btn-apply-retention" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 hover:bg-slate-900/80 text-slate-200 text-sm font-medium transition">Apply retention rules now</button>
            <button id="btn-reindex-vault" class="px-4 py-2.5 rounded-lg border border-slate-700 bg-slate-900/60 hover:bg-slate-900/80 text-slate-200 text-sm font-medium transition">Reindex (locks vault)</button>
          </div>
          <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3">
            <div class="flex items-start gap-3">
              <svg class="h-5 w-5 shrink-0 text-amber-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
              </svg>
              <p class="text-sm text-slate-300">Reindex may take many hours and locks the vault during the operation.</p>
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

<!-- Restore Wizard Modal -->
<div id="restore-wizard" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/75"></div>
  <div class="relative mx-auto my-6 w-full max-w-4xl bg-slate-900 border border-slate-700 rounded-lg shadow-xl">
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
      <h3 class="flex items-center gap-2 text-slate-200 text-lg font-semibold">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v6.75m0 0-3-3m3 3 3-3m-8.25 6a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0  0 1 18 19.5H6.75Z" />
        </svg>
        Restore Wizard
      </h3>
      <button id="restore-close" class="text-slate-400 hover:text-slate-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-5 py-4">
      <div id="restore-step1">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="text-sm text-slate-300 mb-1">Select an online device to control</div>
            <div class="text-xs text-slate-400">Using the device selected in the Devices panel.</div>
          </div>
          <div x-data="{ open:false }" class="relative">
            <label class="block text-sm text-slate-300 mb-1">Select a Storage Vault to restore from</label>
            <button id="rs-vault-menu-btn" type="button" @click="open=!open" class="w-full text-left px-3 py-2 bg-slate-800 border border-slate-600 rounded text-slate-200">
              <span id="rs-vault-selected-label">Choose a vault…</span>
            </button>
            <div id="rs-vault-menu-list" x-show="open" x-transition class="absolute mt-1 w-full bg-slate-800 border border-slate-700 rounded shadow-lg max-h-56 overflow-y-auto z-10">
              <ul class="py-1 text-sm text-slate-200">
                {foreach from=$vaults item=vault key=vaultId}
                  <li>
                    <a href="#" class="block px-3 py-2 hover:bg-slate-700" data-rs-vault-id="{$vaultId}" data-rs-vault-name="{$vault.Description}" @click.prevent="open=false">{$vault.Description}</a>
                  </li>
                {/foreach}
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div id="restore-step2" class="hidden">
        <div class="space-y-3">
          <div class="text-sm text-slate-300">Select a Protected Item to restore:</div>
          <div class="relative" x-data="{ open:false }">
            <label class="block text-sm text-slate-300 mb-1">Protected Item</label>
            <button id="rs-item-menu-btn" type="button" @click="open=!open" class="w-full text-left px-3 py-2 bg-slate-800 border border-slate-600 rounded text-slate-200">
              <span id="rs-item-selected-label">Choose a protected item…</span>
            </button>
            <div id="rs-item-menu-list" x-show="open" x-transition class="absolute mt-1 w-full bg-slate-800 border border-slate-700 rounded shadow-lg max-h-60 overflow-y-auto z-10">
              <ul class="py-1 text-sm text-slate-200"></ul>
            </div>
          </div>
          <div>
            <div class="text-sm text-slate-300 mb-1">Snapshots</div>
            <div id="rs-engine-friendly" class="text-xs text-slate-400 mb-1"></div>
            <div id="rs-snapshots" class="border border-slate-700 rounded bg-slate-900/40 max-h-60 overflow-y-auto text-sm text-slate-200"></div>
          </div>
        </div>
        <div id="rs-engine-hint" class="mt-3 text-xs text-slate-400"></div>
      </div>

      <div id="restore-step3" class="hidden">
        <div id="rs-methods" class="mt-1">
          <div id="rs-method-title" class="text-sm text-slate-300 mb-2"></div>
          <div id="rs-method-options" class="space-y-2 text-sm text-slate-200"></div>
          <div class="mt-3 space-y-3">
            <div>
              <label class="block text-sm text-slate-300 mb-1">Destination path</label>
              <div class="flex gap-2">
                <input id="rs-dest" type="text" class="flex-1 px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="e.g. C:\\Restore">
                <button id="rs-browse" type="button" class="px-3 py-2 rounded bg-slate-700 hover:bg-slate-600 text-white">Browse…</button>
              </div>
            </div>
            <div id="rs-archive-name-wrap" class="hidden">
              <label class="block text-sm text-slate-300 mb-1">Archive file name</label>
              <input id="rs-archive-name" type="text" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 focus:outline-none focus:ring-0 focus:border-sky-600" placeholder="backup.zip">
              <div class="text-xs text-slate-400 mt-1">Enter the output archive filename (e.g., backup.zip).</div>
            </div>
            <div>
              <label class="block text-sm text-slate-300 mb-1">Overwrite</label>
              <select id="rs-overwrite" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200">
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
          <div class="text-sm text-slate-300">Restore scope</div>
          <div class="text-xs text-slate-400">Choose whether to restore everything from the snapshot, or pick specific files/folders.</div>
          <div id="rs-scope-options" class="space-y-2 text-sm text-slate-200"></div>

          <div id="rs-scope-select-wrap" class="hidden mt-3">
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm text-slate-300">Select items from snapshot</div>
              <button id="rs-snap-browse" type="button" class="px-3 py-2 rounded bg-slate-700 hover:bg-slate-600 text-white text-sm">Browse snapshot…</button>
            </div>
            <div class="mt-2 border border-slate-700 rounded bg-slate-900/40">
              <div class="px-3 py-2 border-b border-slate-800 text-xs text-slate-400">
                Selected items (<span id="rs-selected-count">0</span>)
              </div>
              <div id="rs-selected-items" class="max-h-48 overflow-y-auto divide-y divide-slate-800 text-sm text-slate-200"></div>
              <div id="rs-selected-empty" class="px-3 py-3 text-sm text-slate-400">No items selected yet. Click “Browse snapshot…” to choose files and folders.</div>
            </div>
            <div class="text-xs text-slate-400">Tip: Selecting a folder restores the entire folder contents.</div>
          </div>
        </div>
      </div>
      <div class="flex justify-between items-center mt-4 border-t border-slate-800 pt-3">
        <button id="restore-back" class="px-4 py-2 text-slate-300">Back</button>
        <div class="ml-auto space-x-2">
          <button id="restore-next" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded">Next</button>
          <button id="restore-start" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded hidden">Start Restore</button>
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
  <div class="absolute inset-0 bg-black/60"></div>
  <div class="relative mx-auto my-6 w-full max-w-3xl bg-slate-900 border border-slate-700 rounded-lg shadow-xl">
    <div class="flex items-center justify-between px-5 py-3 border-b border-slate-700">
      <h3 class="text-slate-200 text-lg font-semibold">Browse Destination</h3>
      <button id="fsb-close" class="text-slate-400 hover:text-slate-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-5 py-4">
      <div class="flex items-center gap-2 mb-3">
        <button id="fsb-up" class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 text-white rounded">Up</button>
        <div id="fsb-path" class="flex-1 text-xs px-2 py-1 rounded bg-slate-800 border border-slate-700 text-slate-300 overflow-x-auto"></div>
        <button id="fsb-refresh" class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 text-white rounded">Refresh</button>
      </div>
      <div class="border border-slate-700 rounded overflow-hidden">
        <div class="grid grid-cols-12 gap-0 bg-slate-800/60 px-3 py-2 text-xs text-slate-300">
          <div class="col-span-7">Name</div>
          <div class="col-span-2">Type</div>
          <div class="col-span-3 text-right">Modified</div>
        </div>
        <div id="fsb-list" class="max-h-80 overflow-y-auto divide-y divide-slate-800"></div>
      </div>
      <div class="mt-3 flex items-center gap-2">
        <input id="fsb-selected" type="text" class="flex-1 px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200" placeholder="Selected path" readonly>
        <button id="fsb-select" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded">Select</button>
      </div>
      <div class="text-xs text-slate-400 mt-2">Double-click folders to open. Click a folder, then Select to choose it.</div>
    </div>
  </div>
</div>

<!-- Snapshot Browser Modal (Select items to restore) -->
<div id="snap-browser" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/60"></div>
  <div class="relative mx-auto my-6 w-full max-w-4xl bg-slate-900 border border-slate-700 rounded-lg shadow-xl">
    <div class="flex items-center justify-between px-5 py-3 border-b border-slate-700">
      <h3 class="text-slate-200 text-lg font-semibold">Browse Snapshot</h3>
      <button id="ssb-close" class="text-slate-400 hover:text-slate-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="px-5 py-4">
      <div class="flex items-center gap-2 mb-3">
        <button id="ssb-up" class="px-3 py-1.5 text-xs bg-sky-600 hover:bg-sky-700 text-white rounded inline-flex items-center gap-1">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19V7m0 0 4 4m-4-4-4 4" />
          </svg>
          Up
        </button>
        <div id="ssb-path" class="flex-1 text-xs px-2 py-1 rounded bg-slate-800 border border-slate-700 text-slate-300 overflow-x-auto"></div>
        <button id="ssb-refresh" class="px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 text-white rounded">Refresh</button>
      </div>
      <div class="border border-slate-700 rounded overflow-hidden">
        <div class="grid grid-cols-12 gap-0 bg-slate-800/60 px-3 py-2 text-xs text-slate-300">
          <div class="col-span-1"></div>
          <div class="col-span-7">Name</div>
          <div class="col-span-1">Type</div>
          <div class="col-span-3 text-right">Modified</div>
        </div>
        <div id="ssb-list" class="max-h-80 overflow-y-auto divide-y divide-slate-800"></div>
      </div>
      <div class="mt-3 flex items-center gap-2">
        <input id="ssb-selected" type="text" class="flex-1 px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200" placeholder="Selected items" readonly>
        <button id="ssb-clear" class="px-3 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded">Clear</button>
        <button id="ssb-select" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded">Select</button>
      </div>
      <div class="text-xs text-slate-400 mt-2">Click folders to open. Use checkboxes to select files and folders, then click Select.</div>
    </div>
  </div>
</div>
</div>

{literal}
<style>
 [x-cloak] { display: none !important; }
</style>
{/literal}