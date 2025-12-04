<div class="min-h-screen bg-slate-950 text-gray-300">
    <!-- Global nebula background -->
    <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div>
    <div class="relative container mx-auto px-4 pb-8">
        <div class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)] px-6 py-6">
        <!-- Header & Breadcrumb -->
        <div class="flex flex-col mb-4 px-2 space-y-3">
            <nav aria-label="breadcrumb">
                <ol class="flex space-x-2 text-gray-300">
                    <li class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg> 
                        
                        <h2 class="text-2xl font-semibold text-white mr-2">Dashboard</h2><h2 class="text-md font-medium text-white"> / Vaults</h2>
                          
                    </li>
                </ol>
            </nav>
        </div>
        <div class="">
            <!-- Tabs Navigation -->
            <div class="px-2">
                <nav class="inline-flex space-x-1 rounded-full bg-slate-900/80 p-1 text-sm font-medium text-slate-400" role="tablist" aria-label="Vault navigation">
                    <a href="{$modulelink}&a=dashboard&tab=dashboard"
                       class="inline-flex items-center rounded-full px-3 py-1.5 text-slate-300 hover:text-slate-100 hover:bg-slate-800/60 transition"
                       role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                        </svg>
                        Backup Status
                    </a>
                    <a href="{$modulelink}&a=dashboard&tab=users"
                       class="inline-flex items-center rounded-full px-3 py-1.5 text-slate-300 hover:text-slate-100 hover:bg-slate-800/60 transition"
                       role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        <i class="bi bi-person mr-1"></i> Users
                    </a>
                    <a href="{$modulelink}&a=vaults"
                       class="inline-flex items-center rounded-full px-3 py-1.5 bg-slate-800 text-slate-50 shadow-sm"
                       role="tab" aria-selected="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        <i class="bi bi-box mr-1"></i> Vaults
                    </a>
                </nav>
            </div>

            <!-- Legacy hidden tabs (kept for compatibility) -->
            <ul class="flex border-b border-gray-700 hidden" role="tablist">
                <li class="mr-2 hidden" role="presentation">
                    <a href="{$modulelink}&a=activedevices"
                        class="flex items-center py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold"
                        type="button" role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                        </svg>
                        <i class="bi bi-laptop mr-1"></i> Active Devices
                    </a>
                </li>
                <li class="mr-2 hidden" role="presentation">
                    <a href="{$modulelink}&a=devicehistory"
                        class="flex items-center py-2 px-4 text-gray-600 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold"
                        type="button" role="tab" aria-selected="false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <i class="bi bi-clock-history mr-1"></i> Device History
                    </a>
                </li>
                <li class="mr-2 hidden" role="presentation">
                    <a href="{$modulelink}&a=logs"
                        class="py-2 px-4  text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold"
                        type="button" role="tab" aria-selected="false">
                        <i class="bi bi-list-check mr-1"></i> Job Logs
                    </a>
                </li>
                <li role="presentation" class="hidden">
                    <a href="{$modulelink}&a=items"
                        class="py-2 px-4 text-gray-300 hover:text-sky-400 border-b-2 border-transparent hover:border-gray-500 font-semibold"
                        type="button" role="tab" aria-selected="false">
                        <i class="bi bi-shield-lock mr-1"></i> Protected Items
                    </a>
                </li>
            </ul>

            <!-- Tabs Content -->
            <div class="mt-4">
                <!-- Vaults Tab Content -->
                <div x-transition>                   
                    <div class="bg-gray-900/50 rounded-lg overflow-visible" x-data="{ open:false, search:'', cols:{ acct:true, name:true, id:false, type:false, init:false, stored:true, quota:true, usage:true, actions:true }, matchesSearch(el){ const q=this.search.trim().toLowerCase(); if(!q) return true; return (el.textContent||'').toLowerCase().includes(q); }, pctColor(p){ if(p===null||p==='') return 'bg-slate-700'; if(p<70) return 'bg-emerald-500'; if(p<90) return 'bg-amber-500'; return 'bg-rose-500'; } }">
                        <div class="flex items-center justify-between px-4 pt-4 pb-2">
                            <div class="relative" @click.away="open=false">
                                <button type="button" class="inline-flex items-center px-3 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded text-white" @click="open=!open">
                                    View
                                    <svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="open" x-transition class="absolute mt-2 w-56 bg-slate-800 border border-slate-700 rounded shadow-lg z-10">
                                    <div class="p-3 space-y-2 text-slate-200 text-sm">
                                        <label class="flex items-center"><input type="checkbox" class="mr-2" x-model="cols.acct"> Account Name</label>
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
                            <div class="w-72">
                                <input type="text" x-model.debounce.200ms="search" placeholder="Search vaults..." class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-700 text-slate-200 focus:outline-none focus:ring-0 focus:border-sky-600">
                            </div>
                        </div>
                        <table id="vaults-table" class="min-w-full divide-y divide-gray-700" x-init="
                          (()=>{ try{ document.addEventListener('vaults:hydrate-start', ()=> window.ebShowLoader && window.ebShowLoader($el.closest('.bg-gray-900/50')) ); }catch(_){ }
                                 try{ document.addEventListener('vaults:hydrate-end',   ()=> window.ebHideLoader && window.ebHideLoader($el.closest('.bg-gray-900/50')) ); }catch(_){ }
                          })()
                        ">
                            <thead class="bg-gray-800/50">
                                <tr>
                                    <th x-show="cols.acct" data-sort="acct" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none">Account Name</th>
                                    <th x-show="cols.name" data-sort="name" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none">Storage Vault</th>
                                    <th x-show="cols.id" data-sort="id" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none">Storage Vault ID</th>
                                    <th x-show="cols.type" data-sort="type" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none">Type</th>
                                    <th x-show="cols.init" data-sort="init" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none">Initialized</th>
                                    <th x-show="cols.stored" data-sort="stored" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none">Stored</th>
                                    <th x-show="cols.quota" data-sort="quota" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none">Quota</th>
                                    <th x-show="cols.usage" data-sort="usage" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider cursor-pointer select-none">Usage</th>
                                    <th x-show="cols.actions" class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                {foreach from=$vaults item=vault}
                                    {assign var=acctName value=$vault->username|default:'-'}
                                    {assign var=vaultName value=$vault->name|default:$vault->Description|default:'-'}
                                    {assign var=vaultId value=$vault->vault_id|default:$vault->id|default:$vault->GUID|default:''}
                                    {assign var=usedBytes value=0}
                                    {if isset($vault->total_bytes)}
                                        {assign var=usedBytes value=$vault->total_bytes}
                                    {elseif isset($vault->Statistics->ClientProvidedSize->Size)}
                                        {assign var=usedBytes value=$vault->Statistics->ClientProvidedSize->Size}
                                    {elseif isset($vault->ClientProvidedSize->Size)}
                                        {assign var=usedBytes value=$vault->ClientProvidedSize->Size}
                                    {elseif isset($vault->Size->Size)}
                                        {assign var=usedBytes value=$vault->Size->Size}
                                    {elseif isset($vault->Size)}
                                        {assign var=usedBytes value=$vault->Size}
                                    {/if}
                                    {assign var=usedMeasuredEnd value=0}
                                    {if isset($vault->Statistics->ClientProvidedSize->MeasureCompleted)}
                                        {assign var=usedMeasuredEnd value=$vault->Statistics->ClientProvidedSize->MeasureCompleted}
                                    {elseif isset($vault->ClientProvidedSize->MeasureCompleted)}
                                        {assign var=usedMeasuredEnd value=$vault->ClientProvidedSize->MeasureCompleted}
                                    {/if}
                                    {assign var=quotaEnabled value=false}
                                    {if isset($vault->StorageLimitEnabled)}
                                        {assign var=quotaEnabled value=$vault->StorageLimitEnabled}
                                    {elseif isset($vault->QuotaEnabled)}
                                        {assign var=quotaEnabled value=$vault->QuotaEnabled}
                                    {elseif isset($vault->LimitEnabled)}
                                        {assign var=quotaEnabled value=$vault->LimitEnabled}
                                    {elseif isset($vault->Destination) && isset($vault->Destination->StorageLimitEnabled)}
                                        {assign var=quotaEnabled value=$vault->Destination->StorageLimitEnabled}
                                    {elseif isset($vault->quota_enabled)}
                                        {assign var=quotaEnabled value=$vault->quota_enabled}
                                    {/if}
                                    {assign var=quotaBytes value=0}
                                    {if isset($vault->StorageLimitBytes)}
                                        {assign var=quotaBytes value=$vault->StorageLimitBytes}
                                    {elseif isset($vault->QuotaBytes)}
                                        {assign var=quotaBytes value=$vault->QuotaBytes}
                                    {elseif isset($vault->LimitBytes)}
                                        {assign var=quotaBytes value=$vault->LimitBytes}
                                    {elseif isset($vault->Destination) && isset($vault->Destination->StorageLimitBytes)}
                                        {assign var=quotaBytes value=$vault->Destination->StorageLimitBytes}
                                    {elseif isset($vault->quota_bytes)}
                                        {assign var=quotaBytes value=$vault->quota_bytes}
                                    {/if}
                                    {assign var=hasQuota value=($quotaEnabled && $quotaBytes>0)}
                                    {if $hasQuota}
                                        {assign var=pct value=(100*$usedBytes/$quotaBytes)}
                                    {else}
                                        {assign var=pct value=''}
                                    {/if}
                                    {assign var=destType value=$vault->DestinationType|default:$vault->Destination->Type|default:$vault->Type|default:''}
                                    {assign var=repoInit value=$vault->RepoInitTimestamp|default:0}
                                    <tr class="hover:bg-gray-800/60" x-show="matchesSearch($el)" x-cloak data-acct="{$acctName}" data-name="{$vaultName}" data-id="{if $vaultId}{$vaultId}{else}{$vaultName}{/if}" data-type="{$destType}" data-init-ts="{$repoInit}" data-stored-bytes="{$usedBytes}" data-used-bytes="{$usedBytes}" data-quota-bytes="{$quotaBytes}" data-usage-pct="{if $hasQuota}{$pct}{else}0{/if}" data-service-id="{$vault->serviceid|default:$vault->service_id|default:''}" data-username="{$vault->username|default:''}" data-vault-id="{if $vaultId}{$vaultId}{else}{$vaultName}{/if}" data-vault-name="{$vaultName}">
                                        <td x-show="cols.acct" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$acctName}</td>
                                        <td x-show="cols.name" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$vaultName}</td>
                                        <td x-show="cols.id" class="px-4 py-4 whitespace-nowrap text-xs font-mono text-gray-400">{if $vaultId}{$vaultId}{else}-{/if}</td>
                                        <td x-show="cols.type" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                            {if $destType ne ''}
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-700 text-slate-200 text-xs">{\WHMCS\Module\Addon\Eazybackup\Helper::vaultTypeLabel($destType)}</span>
                                            {else}
                                                <span class="text-slate-400">-</span>
                                            {/if}
                                        </td>
                                        <td x-show="cols.init" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                            {if $repoInit>0}
                                                <span class="font-mono text-xs">{\WHMCS\Module\Addon\Eazybackup\Helper::formatDateTime($repoInit)}</span>
                                            {else}
                                                <span class="text-slate-400">-</span>
                                            {/if}
                                        </td>
                                        <td x-show="cols.stored" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                            {assign var=cpSize value=$usedBytes}
                                            {assign var=cpStart value=0}
                                            {assign var=cpEnd value=$usedMeasuredEnd}
                                            <button type="button" class="eb-stats-btn inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 hover:bg-slate-600 text-slate-200" title="View vault usage breakdown" data-vault-id="{if $vaultId}{$vaultId}{else}{$vaultName}{/if}" data-vault-name="{$vaultName|escape}" data-size-bytes="{$cpSize}" data-measure-start="{$cpStart}" data-measure-end="{$cpEnd}" data-service-id="{$vault->serviceid|default:''}" data-username="{$vault->username|default:''}">
                                                {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($cpSize, 2)}
                                            </button>
                                            <script type="application/json" class="eb-components">{if isset($vault->ClientProvidedContent->Components)}{$vault->ClientProvidedContent->Components|@json_encode}{elseif isset($vault->Statistics->ClientProvidedContent->Components)}{$vault->Statistics->ClientProvidedContent->Components|@json_encode}{else}[]{/if}</script>
                                        </td>
                                        <td x-show="cols.quota" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300" data-cell="quota">
                                            {if not $hasQuota}
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-300">Unlimited</span>
                                            {else}
                                                <span class="inline-flex items-center gap-2">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-200" title="Storage quota">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($quotaBytes)}</span>
                                                    {if $quotaEnabled}
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-900/40 text-emerald-300">On</span>
                                                    {else}
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-700 text-slate-300">Off</span>
                                                    {/if}
                                                </span>
                                            {/if}
                                        </td>
                                        <td x-show="cols.usage" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300" data-cell="usage">
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
                                            <button class="open-vault-panel px-3 py-1.5 text-xs bg-slate-700 hover:bg-slate-600 rounded text-white" data-vault-id="{if $vaultId}{$vaultId}{else}{$vaultName}{/if}" data-vault-name="{$vaultName}" data-vault-quota-enabled="{$quotaEnabled}" data-vault-quota-bytes="{$quotaBytes}" data-service-id="{$vault->serviceid|default:$vault->service_id|default:''}" data-username="{$vault->username|default:''}">Manage</button>
                                        </td>
                                    </tr>
                                {foreachelse}
                                    <tr>
                                        <td colspan="9" class="text-center py-6 text-sm text-gray-400">No storage vaults found.</td>
                                    </tr>
                                {/foreach}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shared slide-over and storage stats modal reused from user-profile -->
<div id="vault-slide-panel" class="fixed inset-y-0 right-0 z-50 w-full max-w-2xl transform translate-x-full transition-transform duration-200 ease-out">
  <div class="h-full bg-slate-900 border-l border-slate-700 shadow-xl flex flex-col" data-modulelink="{$modulelink}">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700">
      <div>
        <h3 class="text-slate-200 text-lg font-semibold">Manage Storage Vault</h3>
        <div class="text-slate-400 text-sm">Vault: <span id="vault-panel-name" class="text-slate-300 font-mono"></span></div>
      </div>
      <button id="vault-panel-close" class="text-slate-400 hover:text-slate-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <input type="hidden" id="vault-mgr-id" value="" />

    <div class="flex-1 overflow-y-auto" x-data="{ tab: 'general' }">
      <div class="px-4 pt-3 border-b border-slate-800">
        <nav class="flex space-x-4" aria-label="Tabs">
          <a href="#" @click.prevent="tab='general'" :class="tab==='general' ? 'text-sky-400 border-sky-500' : 'text-slate-300 border-transparent hover:text-slate-100'" class="px-1 pb-2 border-b-2 text-sm font-medium">General</a>
          <a href="#" @click.prevent="tab='retention'" :class="tab==='retention' ? 'text-sky-400 border-sky-500' : 'text-slate-300 border-transparent hover:text-slate-100'" class="px-1 pb-2 border-b-2 text-sm font-medium">Retention</a>
          <a href="#" @click.prevent="tab='danger'" :class="tab==='danger' ? 'text-rose-400 border-rose-500' : 'text-slate-300 border-transparent hover:text-slate-100'" class="px-1 pb-2 border-b-2 text-sm font-medium">Danger zone</a>
        </nav>
      </div>

      <div x-show="tab==='general'" class="px-4 py-4 space-y-6">
        <div>
          <label class="block text-sm text-slate-300 mb-1">Vault name</label>
          <input id="vault-mgr-name" type="text" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm" placeholder="Vault name" />
        </div>
        <div class="space-y-2">
          <label class="block text-sm text-slate-300">Quota</label>
          <div class="flex items-center gap-2">
            <input id="vault-quota-unlimited2" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-600 text-sky-600">
            <span class="text-slate-300 text-sm">Unlimited</span>
          </div>
          <div class="flex items-center gap-2">
            <input id="vault-quota-size2" type="number" class="w-40 px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm" placeholder="0" />
            <div class="relative" x-data="{ open:false, unit:'GB' }" @click.away="open=false">
              <input type="hidden" id="vault-quota-unit2" :value="unit">
              <button type="button" @click="open=!open" class="w-28 text-left px-3 py-2 bg-slate-800 border border-slate-600 rounded text-slate-200 text-sm pr-8">
                <span x-text="unit"></span>
                <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-slate-400">
                  <svg class="h-4 w-4 ml-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                </span>
              </button>
              <div x-show="open" x-transition class="absolute z-10 mt-1 w-full bg-slate-800 border border-slate-700 rounded shadow-lg">
                <ul class="py-1 text-sm text-slate-200">
                  <li><a href="#" class="block px-3 py-2 hover:bg-slate-700" @click.prevent="unit='GB'; open=false">GB</a></li>
                  <li><a href="#" class="block px-3 py-2 hover:bg-slate-700" @click.prevent="unit='TB'; open=false">TB</a></li>
                </ul>
              </div>
            </div>
          </div>
          <div class="text-xs text-slate-400">Changes apply to this vault only.</div>
        </div>
        <div class="pt-2 border-t border-slate-800 flex justify-end">
          <button id="vault-save-all" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Save</button>
        </div>
      </div>

      <div x-show="tab==='retention'" id="vault-retention-tab" class="px-4 py-4" x-data="retention()" @retention:update.window="state.override=$event.detail.override; state.mode=$event.detail.mode; state.ranges=$event.detail.ranges; state.defaultMode=$event.detail.defaultMode; state.defaultRanges=$event.detail.defaultRanges">
        <h4 class="text-slate-200 font-semibold mb-2">Retention</h4>
        <div class="mb-3">
          <div x-show="!state.override" class="inline-flex items-center gap-2 rounded-full bg-slate-700/70 text-slate-100 px-3 py-1.5 text-sm" title="This vault follows the account's default retention policy.">
            <svg class="size-4 opacity-80" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            <span>Using account-level policy</span>
          </div>
          <div x-show="state.override" class="inline-flex items-center gap-2 rounded-full bg-amber-900/40 text-amber-100 px-3 py-1.5 text-sm" title="This vault uses its own retention rules instead of the account default.">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 1l3 5 5 1-4 4 1 5-5-3-5 3 1-5-4-4 5-1z"/></svg>
            <span>This vault has its own policy (overrides account)</span>
          </div>
        </div>
        <div class="flex items-center gap-2 mb-3">
          <input id="ret-override" type="checkbox" class="h-4 w-4 rounded border-slate-500 bg-slate-600 text-sky-600" x-model="state.override">
          <label for="ret-override" class="text-sm text-slate-300">Override account retention for this vault</label>
        </div>
        <template x-if="!state.override">
          <div class="text-sm text-slate-300">This vault follows the account default policy.</div>
        </template>
        <div class="sticky bottom-0 mt-4 rounded-xl border border-slate-700 bg-slate-800/80 backdrop-blur p-3">
          <p class="text-slate-300 font-medium mb-1">Effective policy:</p>
          <ul class="list-disc pl-5 text-slate-200 text-sm space-y-1" x-html="formattedEffectivePolicyLines().join('')"></ul>
        </div>
        <div class="mt-3 flex justify-end">
          <button id="vault-retention-save" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-sm">Save</button>
        </div>
      </div>

      <div x-show="tab==='danger'" class="px-4 py-4 space-y-3">
        <h4 class="text-rose-400 font-semibold">Danger zone</h4>
        <div class="text-sm text-slate-300">Deleting a vault cannot be undone.</div>
        <button id="vault-delete" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded text-sm">Delete</button>
        <div id="vault-delete-confirm" class="hidden border border-slate-700 rounded p-3 bg-slate-900/60">
          <div class="text-slate-200 text-sm font-semibold mb-1">Confirm your account password</div>
          <div class="text-slate-400 text-xs mb-2">This is the password you use to sign in to your eazyBackup Client Area.</div>
          <input id="vault-delete-password" type="password" class="w-full px-3 py-2 rounded border border-slate-600 bg-slate-800 text-slate-200 text-sm mb-2" placeholder="Account password" />
          <div class="flex justify-end gap-2">
            <button id="vault-delete-cancel" class="px-3 py-2 text-slate-300 hover:text-white text-sm">Cancel</button>
            <button id="vault-delete-confirm-btn" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded text-sm">Confirm delete</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Storage Breakdown Modal (shared) -->
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
  <input type="hidden" id="vsm-items-json" value="" />
</div>

<script>
window.EB_DEVICE_ENDPOINT = '{$modulelink}&a=device-actions';
// On aggregated view, rows may carry service/user; ensure Manage click sets them
document.addEventListener('click', function(e){
  var btn = e.target && e.target.closest && e.target.closest('.open-vault-panel');
  if (!btn) return;
  var sid = btn.getAttribute('data-service-id') || '';
  var un  = btn.getAttribute('data-username') || '';
  try {
    if (sid) document.body.setAttribute('data-eb-serviceid', sid);
    if (un) document.body.setAttribute('data-eb-username', un);
  } catch(_){}
});
</script>
<div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>
<script src="modules/addons/eazybackup/templates/assets/js/ui.js"></script>