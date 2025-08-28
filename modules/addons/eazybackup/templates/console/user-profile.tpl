{**
 * eazyBackup User Profile
 *
 * @copyright Copyright (c) eazyBackup Systems Ltd. 2024
 * @license https://www.eazybackup.com/terms/eula
 *}

 <div class="bg-gray-800">
 <div class="min-h-screen bg-gray-800 container mx-auto pb-8">

     <div class="flex justify-between items-center h-16 space-y-12 px-2">
    <nav aria-label="breadcrumb">
             <ol class="flex space-x-2 text-gray-300 items-center">
                 <li>
                     <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                         <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                     </svg>
                 </li>
                 <li>
                     <h2 class="text-2xl font-semibold text-white">Dashboard</h2>
                 </li>
                 <li>
                     <span class="text-md font-medium text-white mx-2">/</span>
                 </li>
                 <li>
                     <a href="{$modulelink}&a=dashboard" class="text-md font-medium text-sky-400 hover:text-sky-500">Users</a>
                 </li>
                 <li>
                     <span class="text-md font-medium text-white mx-2">/</span>
        </li>
                 <li class="text-md font-medium text-white" aria-current="page">
                     {$username}
        </li>
      </ol>
    </nav>
     </div>

     <ul class="flex border-b border-gray-700" role="tablist">
         <li class="mr-2" role="presentation">
             <a href="{$modulelink}&a=dashboard" class="flex items-center py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold" type="button" role="tab" aria-selected="false">
                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-1">
                     <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                 </svg>
                 Backup Status
             </a>
         </li>
         <li class="mr-2" role="presentation">
             <a href="{$modulelink}&a=dashboard" class="flex items-center py-2 px-4 border-b-2 text-sky-400 border-sky-400 font-semibold" type="button" role="tab" aria-selected="true">
                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-1">
                     <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
          </svg>
                 Users
            </a>
          </li>
         <li class="mr-2" role="presentation">
             <a href="{$modulelink}&a=vaults" class="flex items-center py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold" type="button" role="tab" aria-selected="false">
                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-1">
                     <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                 </svg>
                 Vaults
            </a>
          </li>
        </ul>

     <div x-data="{ activeSubTab: 'profile' }" class="mt-4 px-2">
         <div class="border-b border-gray-700 mb-6">
             <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                 <a href="#" @click.prevent="activeSubTab = 'profile'" :class="activeSubTab === 'profile' ? 'border-sky-500 text-sky-400' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Profile</a>
                 <a href="#" @click.prevent="activeSubTab = 'protectedItems'" :class="activeSubTab === 'protectedItems' ? 'border-sky-500 text-sky-400' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Protected Items</a>
                 <a href="#" @click.prevent="activeSubTab = 'storage'" :class="activeSubTab === 'storage' ? 'border-sky-500 text-sky-400' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Storage Vaults</a>
                 <a href="#" @click.prevent="activeSubTab = 'devices'" :class="activeSubTab === 'devices' ? 'border-sky-500 text-sky-400' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Devices</a>
                 <a href="#" @click.prevent="activeSubTab = 'jobLogs'" :class="activeSubTab === 'jobLogs' ? 'border-sky-500 text-sky-400' : 'border-transparent text-gray-400 hover:text-gray-200 hover:border-gray-500'" class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm">Job Logs</a>
             </nav>
      </div>

         <div x-show="activeSubTab === 'profile'" x-transition>
             <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                 <div class="md:col-span-2 bg-gray-900/50 p-6 rounded-lg">
                     <h3 class="text-lg font-semibold text-white mb-4">User Details</h3>
                     <div class="space-y-3 text-sm">
                         <div class="flex justify-between">
                             <span class="text-gray-400">Username:</span>
                             <span class="text-white font-mono">{$username}</span>
    </div>
                         <div class="flex justify-between">
                             <span class="text-gray-400">Password:</span>
                             <span class="text-white">Hashed with 448-bit bcrypt</span>
  </div>
                         <div class="flex justify-between">
                             <span class="text-gray-400">Created:</span>
                             <span class="text-white font-mono">{$createdDate}</span>
              </div>
                         <div class="flex justify-between">
                             <span class="text-gray-400">TOTP:</span>
                             {if $totpStatus == 'Active'}
                                 <span class="text-green-400">{$totpStatus}</span>
                             {else}
                                 <span class="text-red-400">{$totpStatus}</span>
                             {/if}
              </div>
                          <div class="flex justify-between">
                             <span class="text-gray-400">Number of devices:</span>
                             <span class="text-white">{if $devices}{$devices|count}{else}0{/if}</span>
              </div>
                         <div class="flex justify-between">
                             <span class="text-gray-400">Office 365 protected accounts:</span>
                             <span class="text-white">{$msAccountCount}</span>
              </div>
            </div>
                </div>

                 <div class="space-y-6">
                     <div class="bg-gray-900/50 p-6 rounded-lg">
                          <h3 class="text-lg font-semibold text-white mb-4">Reporting</h3>
                          <div class="flex justify-between text-sm">
                             <span class="text-gray-400">Email Reports:</span>
                             <span class="text-red-400">Disabled</span>
                </div>
              </div>
                     <div class="bg-gray-900/50 p-6 rounded-lg">
                          <h3 class="text-lg font-semibold text-white mb-4">Storage Vaults</h3>
                          <div class="space-y-2 text-sm">
                             {if $vaults}
                                 {foreach from=$vaults item=vault}
                                     <div class="flex justify-between">
                                         <span class="text-gray-300">{$vault.Description}</span>
                                         <a href="#" class="text-sky-500 hover:underline">Configure...</a>
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
              <div class="bg-gray-900/50 rounded-lg overflow-hidden">
                 <table class="min-w-full divide-y divide-gray-700">
                     <thead class="bg-gray-800/50">
                         <tr>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Name</th>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Size</th>
                         </tr>
                     </thead>
                     <tbody class="divide-y divide-gray-700">
              {foreach from=$protectedItems item=item}
                             <tr class="hover:bg-gray-800/60">
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
             <div class="bg-gray-900/50 rounded-lg overflow-hidden">
                 <table class="min-w-full divide-y divide-gray-700">
                     <thead class="bg-gray-800/50">
                         <tr>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Vault Name</th>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Size</th>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
                     <tbody class="divide-y divide-gray-700">
                         {foreach from=$vaults item=vault key=vaultId}
                             <tr class="hover:bg-gray-800/60">
                                 <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$vault.Description}</td>
                                 <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($vault.Size.Size)}</td>
                                 <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                     <button class="configure-vault-button bg-sky-600 hover:bg-sky-700 text-white font-bold py-2 px-4 rounded"
                                             data-vault-id="{$vaultId}"
                                             data-vault-name="{$vault.Description}"
                                             data-vault-quota-enabled="{$vault.StorageLimitEnabled}"
                                             data-vault-quota-bytes="{$vault.StorageLimitBytes}">
                                         Configure
                                     </button>
                                 </td>
                             </tr>
                         {foreachelse}
                             <tr>
                                 <td colspan="3" class="text-center py-6 text-sm text-gray-400">No storage vaults found for this user.</td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
         </div>

         <div x-show="activeSubTab === 'devices'" x-cloak x-transition>
             <div class="bg-gray-900/50 rounded-lg overflow-hidden">
                 <table class="min-w-full divide-y divide-gray-700">
                     <thead class="bg-gray-800/50">
                         <tr>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Device Name</th>
                  </tr>
                </thead>
                     <tbody class="divide-y divide-gray-700">
                         {foreach from=$devices item=device}
                             <tr class="hover:bg-gray-800/60">
                                 <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                     {if $device.is_active}
                                         <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-900/50 text-green-300">Online</span>
                                     {else}
                                         <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-700 text-gray-300">Offline</span>
                                     {/if}
                                 </td>
                                 <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$device.name}</td>
                             </tr>
                         {foreachelse}
                             <tr>
                                 <td colspan="2" class="text-center py-6 text-sm text-gray-400">No devices found for this user.</td>
                    </tr>
                  {/foreach}          
                </tbody>
              </table>
            </div>
          </div>
        
         <div x-show="activeSubTab === 'jobLogs'" x-cloak x-transition>
             <div class="bg-gray-900/50 rounded-lg overflow-hidden">
                  <table class="min-w-full divide-y divide-gray-700">
                     <thead class="bg-gray-800/50">
                         <tr>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Started</th>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Finished</th>
                             <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Data Uploaded</th>
                         </tr>
                     </thead>
                     <tbody class="divide-y divide-gray-700">
                         {foreach from=$jobLogs item=job}
                             <tr class="hover:bg-gray-800/60">
                                 <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$job.Class}</td>
                                 <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$job.StartTime|date_format:"%Y-%m-%d %H:%M:%S"}</td>
                                 <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$job.EndTime|date_format:"%Y-%m-%d %H:%M:%S"}</td>
                                 <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($job.UploadSize)}</td>
                             </tr>
                         {foreachelse}
                             <tr>
                                 <td colspan="4" class="text-center py-6 text-sm text-gray-400">No recent job logs found for this user.</td>
                             </tr>
                         {/foreach}
                     </tbody>
                 </table>
             </div>
        </div>
    </div>
  </div>
</div>

<div id="configure-vault-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-70 hidden">
  <div class="bg-slate-800/90 backdrop-blur-sm border border-slate-700 rounded-lg shadow-lg w-full max-w-md" role="document">
    <form id="configure-vault-form" x-data="{ isUnlimited: false }">
      <div class="p-6">
        <div class="flex justify-between items-center pb-4">
          <h2 class="text-lg font-semibold text-slate-200">Configure Storage Vault</h2>
          <button id="close-configure-modal" type="button" class="text-slate-500 hover:text-slate-300 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="space-y-6">
          <input type="hidden" id="configure-vault-id" name="vaultId">
          
          <div>
            <label for="vault-name" class="block text-sm font-medium text-slate-300 mb-1">Vault Name</label>
            <input type="text" id="vault-name" name="vaultName" class="block w-full px-3 py-2 border border-slate-600 bg-slate-700 text-slate-200 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition" required>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-300 mb-1">Quota</label>
            <div class="flex items-center space-x-4">
              <div class="flex items-center flex-grow">
                <input type="number" id="vault-quota-size" name="vaultQuotaSize" 
                       :disabled="isUnlimited"
                       :class="{ 'bg-slate-800 text-slate-500 cursor-not-allowed': isUnlimited, 'bg-slate-700 text-slate-200': !isUnlimited }"
                       class="w-2/3 px-3 py-2 border border-slate-600 rounded-l-md shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition">
                
                <div x-data="{ open: false, selectedUnit: 'GB', units: ['GB', 'TB'] }" class="relative w-1/3" @click.away="open = false">
                    <input type="hidden" id="vault-quota-unit" name="vaultQuotaUnit" :value="selectedUnit">
                    <button type="button" @click="open = !open" :disabled="isUnlimited"
                            :class="{ 'border-slate-800 cursor-not-allowed': isUnlimited, 'border-slate-600': !isUnlimited }"
                            class="relative w-full px-3 py-2 text-left bg-slate-700 border-t border-b border-r rounded-r-md shadow-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 transition">
                        <span class="block truncate" :class="{ 'text-slate-500': isUnlimited, 'text-slate-200': !isUnlimited }" x-text="selectedUnit"></span>
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                            <svg class="h-5 w-5 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 3a.75.75 0 01.53.22l3.5 3.5a.75.75 0 01-1.06 1.06L10 4.81 7.03 7.78a.75.75 0 01-1.06-1.06l3.5-3.5A.75.75 0 0110 3zM10 17a.75.75 0 01-.53-.22l-3.5-3.5a.75.75 0 011.06-1.06L10 15.19l2.97-2.97a.75.75 0 011.06 1.06l-3.5 3.5A.75.75 0 0110 17z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </button>
                    <div x-show="open" x-transition class="absolute z-10 mt-1 w-full bg-slate-700 shadow-lg rounded-md border border-slate-600">
                        <ul class="py-1">
                            <template x-for="unit in units" :key="unit">
                                <li>
                                    <a href="#" @click.prevent="selectedUnit = unit; open = false" class="block px-4 py-2 text-sm text-slate-200 hover:bg-sky-600 hover:text-white">
                                        <span x-text="unit"></span>
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
              </div>
              
              <div class="flex items-center">
                <input type="checkbox" id="vault-quota-unlimited" name="vaultQuotaUnlimited" x-model="isUnlimited" class="h-4 w-4 rounded border-slate-500 bg-slate-600 text-sky-600 focus:ring-sky-500">
                <label for="vault-quota-unlimited" class="ml-2 text-sm text-slate-300">Unlimited</label>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-300">Retention</label>
            <p class="text-slate-400 text-sm mt-1">Retention policy management is not yet available.</p>
          </div>
          
          <div id="configure-vault-error-message" class="mt-2 text-red-500 text-sm hidden"></div>
        </div>
      </div>

      <div class="flex justify-between items-center mt-2 bg-slate-800/80 px-6 py-4 rounded-b-lg border-t border-slate-700">
        <button type="button" id="delete-vault-button" title="Delete Vault" class="p-2 text-slate-400 hover:bg-red-500/20 hover:text-red-400 rounded-full transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
          </svg>
        </button>
        <div class="flex items-center space-x-4">
          <button type="button" id="cancel-configure-modal-button" class="text-sm font-semibold text-slate-300 hover:text-white transition-colors">Cancel</button>
          <button type="submit" id="save-vault-changes" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-sky-600 hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-800 focus:ring-sky-500 transition">
            Save Changes
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Delete Vault Confirmation Modal -->
<div id="delete-vault-confirmation-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
    <div class="bg-gray-800 rounded-lg shadow-lg w-full max-w-md p-6" role="document">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg text-red-400">Delete Storage Vault</h2>
            <button id="close-delete-confirmation-modal" class="text-gray-500 hover:text-gray-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="mb-6">
            <p class="text-gray-300">Are you sure you want to delete the vault "<span id="delete-vault-name-confirmation" class="font-bold"></span>"?</p>
            <p class="text-gray-400 text-sm mt-1">This action cannot be undone.</p>
        </div>
        <div id="delete-vault-confirmation-error-message" class="mt-2 text-red-500 text-sm hidden"></div>
        <div class="flex justify-end space-x-2 mt-4">
            <button type="button" id="cancel-delete-vault" class="text-sm/6 font-semibold text-gray-300 mr-2">Cancel</button>
            <button type="button" id="confirm-delete-vault" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-700">Delete</button>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const configureVaultModal = document.getElementById('configure-vault-modal');
    const deleteVaultConfirmationModal = document.getElementById('delete-vault-confirmation-modal');
    let currentVaultId = null;

    // Open Configure Modal
    document.querySelectorAll('.configure-vault-button').forEach(button => {
        button.addEventListener('click', function () {
            currentVaultId = this.dataset.vaultId;
            document.getElementById('configure-vault-id').value = currentVaultId;
            document.getElementById('vault-name').value = this.dataset.vaultName;
            
            const quotaEnabled = this.dataset.vaultQuotaEnabled === 'true';
            const quotaBytes = parseInt(this.dataset.vaultQuotaBytes, 10);

            const unlimitedCheckbox = document.getElementById('vault-quota-unlimited');
            const quotaSizeInput = document.getElementById('vault-quota-size');
            const quotaUnitSelect = document.getElementById('vault-quota-unit');

            unlimitedCheckbox.checked = !quotaEnabled;
            quotaSizeInput.disabled = !quotaEnabled;
            quotaUnitSelect.disabled = !quotaEnabled;

            if (quotaEnabled && quotaBytes > 0) {
                if (quotaBytes % (1024**4) === 0) {
                    quotaSizeInput.value = quotaBytes / (1024**4);
                    quotaUnitSelect.value = 'TB';
                } else {
                    quotaSizeInput.value = quotaBytes / (1024**3);
                    quotaUnitSelect.value = 'GB';
                }
            } else {
                quotaSizeInput.value = '';
            }

            configureVaultModal.classList.remove('hidden');
        });
    });

    // Close Configure Modal
    document.getElementById('close-configure-modal').addEventListener('click', () => {
        configureVaultModal.classList.add('hidden');
    });
    document.getElementById('close-configure-modal-button').addEventListener('click', () => {
        configureVaultModal.classList.add('hidden');
    });

    // Handle Quota Unlimited Checkbox
    document.getElementById('vault-quota-unlimited').addEventListener('change', function () {
        const quotaSizeInput = document.getElementById('vault-quota-size');
        const quotaUnitSelect = document.getElementById('vault-quota-unit');
        quotaSizeInput.disabled = this.checked;
        quotaUnitSelect.disabled = this.checked;
        if (this.checked) {
            quotaSizeInput.value = '';
        }
    });

    // Save Vault Changes
    document.getElementById('configure-vault-form').addEventListener('submit', function (e) {
        e.preventDefault();
        const vaultId = document.getElementById('configure-vault-id').value;
        const vaultName = document.getElementById('vault-name').value;
        const vaultQuota = {
            unlimited: document.getElementById('vault-quota-unlimited').checked,
            size: document.getElementById('vault-quota-size').value,
            unit: document.getElementById('vault-quota-unit').value
        };

        const data = {
            action: 'updateVault',
            serviceId: '{$serviceid}',
            username: '{$username}',
            vaultId: vaultId,
            vaultName: vaultName,
            vaultQuota: vaultQuota
        };

        fetch('{$modulelink}&a=api', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                document.getElementById('configure-vault-error-message').innerText = data.message;
                document.getElementById('configure-vault-error-message').classList.remove('hidden');
            }
        });
    });

    // Open Delete Confirmation Modal
    document.getElementById('delete-vault-button').addEventListener('click', function () {
        const vaultName = document.getElementById('vault-name').value;
        document.getElementById('delete-vault-name-confirmation').innerText = vaultName;
        deleteVaultConfirmationModal.classList.remove('hidden');
        configureVaultModal.classList.add('hidden');
    });

    // Close Delete Confirmation Modal
    document.getElementById('close-delete-confirmation-modal').addEventListener('click', () => {
        deleteVaultConfirmationModal.classList.add('hidden');
    });
    document.getElementById('cancel-delete-vault').addEventListener('click', () => {
        deleteVaultConfirmationModal.classList.add('hidden');
    });
    
    // Confirm and Delete Vault
    document.getElementById('confirm-delete-vault').addEventListener('click', function () {
        const data = {
            action: 'deleteVault',
            serviceId: '{$serviceid}',
            username: '{$username}',
            vaultId: currentVaultId
        };

        fetch('{$modulelink}&a=api', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                document.getElementById('delete-vault-confirmation-error-message').innerText = data.message;
                document.getElementById('delete-vault-confirmation-error-message').classList.remove('hidden');
            }
        });
    });
});
</script>

{literal}
<style>
 [x-cloak] { display: none !important; }
</style>
{/literal}