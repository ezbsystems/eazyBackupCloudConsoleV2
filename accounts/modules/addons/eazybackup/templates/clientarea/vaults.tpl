{literal}
<style>
    [x-cloak] { display: none !important; }
    
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
</style>
{/literal}

<div class="min-h-screen bg-slate-950 text-gray-300">
    <!-- Global nebula background -->
    {* <div class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top,_#1f293780,_transparent_60%)]"></div> *}

    <div class="container mx-auto px-4 py-8">
        <!-- App Shell with Sidebar -->
        <div x-data="{ 
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
        class="rounded-3xl border border-slate-800/80 bg-slate-950/80 shadow-[0_18px_60px_rgba(0,0,0,0.6)]">
          
            <div class="flex">
                {include file="modules/addons/eazybackup/templates/clientarea/partials/sidebar.tpl" ebSidebarPage='vaults'}
                
                <!-- Main Content Area -->
                <main class="flex-1 min-w-0 overflow-x-auto">
                    <!-- Content Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800/60">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-slate-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                            </svg>
                            <h1 class="text-xl font-semibold text-white">Storage Vaults</h1>
                        </div>
                    </div>
                    
                    <!-- Vaults Content -->
                    <div class="p-6">                   
                    {* Preprocess vaults into per-account groups with totals *}
                    {assign var=accountGroups value=[]}
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
                        {assign var=vaultRow value=array(
                            'acct'=>$acctName,
                            'vaultName'=>$vaultName,
                            'vaultId'=>$vaultId,
                            'usedBytes'=>$usedBytes,
                            'usedMeasuredEnd'=>$usedMeasuredEnd,
                            'quotaEnabled'=>$quotaEnabled,
                            'quotaBytes'=>$quotaBytes,
                            'hasQuota'=>$hasQuota,
                            'pct'=>$pct,
                            'destType'=>$destType,
                            'repoInit'=>$repoInit,
                            'serviceId'=>$vault->serviceid|default:$vault->service_id|default:'',
                            'username'=>$vault->username|default:''
                        )}
                        {assign var=accountGroups value=$accountGroups|@array_merge:[
                            $acctName => array(
                                'vaults'=>($accountGroups[$acctName].vaults|default:array())|@array_merge:[$vaultRow],
                                'totals'=>array(
                                    'used'=>($accountGroups[$acctName].totals.used|default:0)+$usedBytes,
                                    'quota'=>($accountGroups[$acctName].totals.quota|default:0)+$quotaBytes,
                                    'count'=>($accountGroups[$acctName].totals.count|default:0)+1
                                )
                            )
                        ]}
                    {/foreach}

                    {assign var=tbBytes value=1099511627776}

                    <div class="bg-gray-900/50 rounded-lg overflow-x-auto"
                         data-vaults-loader-host
                         x-data="{
                            open:false,
                            search:'',
                            cols:{ acct:true, name:true, id:false, type:false, init:false, stored:true, quota:true, usage:true, actions:true },
                            expandedAccounts:{},
                            toggleAccount(acct){ this.expandedAccounts[acct] = this.expandedAccounts[acct] === false; },
                            isExpanded(acct){ return this.expandedAccounts[acct] !== false; },
                            matchesSearch(el){ const q=this.search.trim().toLowerCase(); if(!q) return true; return (el.textContent||'').toLowerCase().includes(q); },
                            pctColor(p){ if(p===null||p==='') return 'bg-slate-700'; if(p<70) return 'bg-emerald-500'; if(p<90) return 'bg-amber-500'; return 'bg-rose-500'; }
                         }">
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
                          (()=>{ try{ document.addEventListener('vaults:hydrate-start', ()=> window.ebShowLoader && window.ebShowLoader($el.closest('[data-vaults-loader-host]')) ); }catch(_){ }
                                 try{ document.addEventListener('vaults:hydrate-end',   ()=> window.ebHideLoader && window.ebHideLoader($el.closest('[data-vaults-loader-host]')) ); }catch(_){ }
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
                                {if $accountGroups|@count > 0}
                                    {foreach from=$accountGroups key=acctName item=group}
                                        {assign var=acctTotals value=$group.totals}
                                        {assign var=billableTB value=($acctTotals.quota>0)?ceil($acctTotals.quota/$tbBytes):0}
                                        <tr class="account-header bg-slate-800/40 cursor-pointer"
                                            data-account-header="{$acctName}"
                                            data-total-quota-bytes="{$acctTotals.quota}"
                                            data-total-used-bytes="{$acctTotals.used}"
                                            @click="toggleAccount('{$acctName|escape:'javascript'}')">
                                            <td colspan="9" class="px-4 py-3">
                                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                    <div class="flex items-center gap-3">
                                                        <svg class="h-4 w-4 text-slate-300 transition-transform duration-150"
                                                             :class="isExpanded('{$acctName|escape:'javascript'}') ? 'rotate-90' : ''"
                                                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                        </svg>
                                                        <span class="text-white font-semibold">{$acctName}</span>
                                                        <span class="acct-count-badge inline-flex items-center px-2 py-0.5 rounded-full bg-slate-700 text-slate-200 text-xs">{$acctTotals.count|default:0} vault{if $acctTotals.count|default:0 != 1}s{/if}</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>

                                        {foreach from=$group.vaults item=row}
                                            <tr class="hover:bg-gray-800/60 vault-row"
                                                x-show="matchesSearch($el) && isExpanded('{$row.acct|escape:'javascript'}')"
                                                x-cloak
                                                data-account="{$row.acct}"
                                                data-name="{$row.vaultName}"
                                                data-id="{if $row.vaultId}{$row.vaultId}{else}{$row.vaultName}{/if}"
                                                data-type="{$row.destType}"
                                                data-init-ts="{$row.repoInit}"
                                                data-stored-bytes="{$row.usedBytes}"
                                                data-used-bytes="{$row.usedBytes}"
                                                data-quota-bytes="{$row.quotaBytes}"
                                                data-usage-pct="{if $row.hasQuota}{$row.pct}{else}0{/if}"
                                                data-service-id="{$row.serviceId}"
                                                data-username="{$row.username}"
                                                data-vault-id="{if $row.vaultId}{$row.vaultId}{else}{$row.vaultName}{/if}"
                                                data-vault-name="{$row.vaultName}">
                                                <td x-show="cols.acct" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$row.acct}</td>
                                                <td x-show="cols.name" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">{$row.vaultName}</td>
                                                <td x-show="cols.id" class="px-4 py-4 whitespace-nowrap text-xs font-mono text-gray-400">{if $row.vaultId}{$row.vaultId}{else}-{/if}</td>
                                        <td x-show="cols.type" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                                    {if $row.destType ne ''}
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-700 text-slate-200 text-xs">{\WHMCS\Module\Addon\Eazybackup\Helper::vaultTypeLabel($row.destType)}</span>
                                            {else}
                                                <span class="text-slate-400">-</span>
                                            {/if}
                                        </td>
                                        <td x-show="cols.init" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                                    {if $row.repoInit>0}
                                                        <span class="font-mono text-xs">{\WHMCS\Module\Addon\Eazybackup\Helper::formatDateTime($row.repoInit)}</span>
                                            {else}
                                                <span class="text-slate-400">-</span>
                                            {/if}
                                        </td>
                                        <td x-show="cols.stored" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300">
                                                    {assign var=cpSize value=$row.usedBytes}
                                            {assign var=cpStart value=0}
                                                    {assign var=cpEnd value=$row.usedMeasuredEnd}
                                                    <button type="button" class="eb-stats-btn inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 hover:bg-slate-600 text-slate-200" title="View vault usage breakdown" data-vault-id="{if $row.vaultId}{$row.vaultId}{else}{$row.vaultName}{/if}" data-vault-name="{$row.vaultName|escape}" data-size-bytes="{$cpSize}" data-measure-start="{$cpStart}" data-measure-end="{$cpEnd}" data-service-id="{$row.serviceId}" data-username="{$row.username}">
                                                {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($cpSize, 2)}
                                            </button>
                                            <script type="application/json" class="eb-components">{if isset($vault->ClientProvidedContent->Components)}{$vault->ClientProvidedContent->Components|@json_encode}{elseif isset($vault->Statistics->ClientProvidedContent->Components)}{$vault->Statistics->ClientProvidedContent->Components|@json_encode}{else}[]{/if}</script>
                                        </td>
                                        <td x-show="cols.quota" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300" data-cell="quota">
                                                    {if not $row.hasQuota}
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-300">Unlimited</span>
                                            {else}
                                                <span class="inline-flex items-center gap-2">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-200" title="Storage quota">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($row.quotaBytes, 2)}</span>
                                                            {if $row.quotaEnabled}
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-900/40 text-emerald-300">On</span>
                                                    {else}
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-700 text-slate-300">Off</span>
                                                    {/if}
                                                            <button type="button" class="configure-vault-button ml-1 p-1.5 rounded hover:bg-slate-700 text-slate-300"
                                                                title="Edit quota"
                                                                data-vault-id="{if $row.vaultId}{$row.vaultId}{else}{$row.vaultName}{/if}"
                                                                data-vault-name="{$row.vaultName}"
                                                                data-vault-quota-enabled="{$row.quotaEnabled}"
                                                                data-vault-quota-bytes="{$row.quotaBytes}"
                                                                data-service-id="{$row.serviceId}"
                                                                data-username="{$row.username}">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232a2.5 2.5 0 113.536 3.536L7.5 20.036 3 21l.964-4.5L15.232 5.232z"/></svg>
                                                            </button>
                                                </span>
                                            {/if}
                                        </td>
                                        <td x-show="cols.usage" class="px-4 py-4 whitespace-nowrap text-sm text-gray-300" data-cell="usage">
                                                    {if $row.hasQuota}
                                                        {assign var=pctClamped value=$row.pct}
                                                {if $pctClamped > 100}
                                                    {assign var=pctClamped value=100}
                                                {elseif $pctClamped < 0}
                                                    {assign var=pctClamped value=0}
                                                {/if}
                                                <div class="w-56">
                                                            <div class="h-2.5 w-full rounded bg-slate-800/70 overflow-hidden" title="{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($row.usedBytes, 2)} of {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($row.quotaBytes, 2)} ({$pctClamped|string_format:'%.1f'}%)">
                                                        <div class="h-full transition-[width] duration-500" :class="pctColor({$pctClamped})" style="width: {$pctClamped}%;"></div>
                                                    </div>
                                                            <div class="mt-1 text-xs text-slate-400">{\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($row.usedBytes, 2)} / {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($row.quotaBytes, 2)} ({$pctClamped|string_format:'%.1f'}%)</div>
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
                                                            data-vault-id="{if $row.vaultId}{$row.vaultId}{else}{$row.vaultName}{/if}"
                                                            data-vault-name="{$row.vaultName}"
                                                            data-vault-quota-enabled="{$row.quotaEnabled}"
                                                            data-vault-quota-bytes="{$row.quotaBytes}"
                                                            data-service-id="{$row.serviceId}"
                                                            data-username="{$row.username}">Manage</button>
                                                </td>
                                            </tr>
                                        {/foreach}

                                        <tr class="bg-slate-900/50 account-summary" data-account-summary="{$acctName}">
                                            <td colspan="9" class="px-4 py-3 text-sm text-slate-300">
                                                <div class="flex flex-wrap items-center gap-4 justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <span class="text-slate-200 font-semibold">{$acctName} summary</span>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 text-xs">{$acctTotals.count|default:0} vault{if $acctTotals.count|default:0 != 1}s{/if}</span>
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-4 text-sm">
                                                        <span class="acct-summary-used">Total Used: {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($acctTotals.used, 2)}</span>
                                                        <span class="acct-summary-quota">Total Quota: {\WHMCS\Module\Addon\Eazybackup\Helper::humanFileSize($acctTotals.quota, 2)}</span>
                                                        <span class="acct-summary-billable text-emerald-400 font-semibold">Billable: {if $billableTB>0}{$billableTB} TB{else}—{/if}</span>
                                                    </div>
                                                </div>
                                        </td>
                                    </tr>
                                    {/foreach}
                                {else}
                                    <tr>
                                        <td colspan="9" class="text-center py-6 text-sm text-gray-400">No storage vaults found.</td>
                                    </tr>
                                {/if}
                            </tbody>
                        </table>
                    </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
</div>

{* Vault slide-over panel with Alpine.js transitions *}
<div id="vault-slide-panel-container" 
     x-data="{ open: false }"
     @vault-panel:open.window="open = true"
     @vault-panel:close.window="open = false"
     class="fixed inset-0 z-[10060] pointer-events-none">
  
  {* Backdrop overlay *}
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
    <div class="h-full flex flex-col" data-modulelink="{$modulelink}">
    
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
          class="pointer-events-auto rounded-lg border px-4 py-2 text-sm shadow-lg backdrop-blur"
          :class="type === 'success'
            ? 'border-emerald-500/40 bg-emerald-500/15 text-emerald-100'
            : (type === 'warning'
              ? 'border-amber-500/40 bg-amber-500/15 text-amber-100'
              : 'border-rose-500/40 bg-rose-500/15 text-rose-100')"
          x-text="message"
        ></div>
      </div>

      <div x-show="tab==='general'" x-transition class="px-5 py-5 space-y-6">
        <div>
          <label class="block text-sm text-slate-300 mb-1">Vault name</label>
          <input id="vault-mgr-name" type="text" class="w-full px-3 py-2 rounded-lg border border-slate-700 bg-slate-900/60 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/40 focus:border-sky-600 placeholder:text-slate-500 transition" placeholder="Vault name" />
        </div>
        <div class="space-y-3">
          <label class="block text-sm text-slate-300">Quota</label>
          <div class="flex items-center gap-2">
            <input id="vault-quota-unlimited2" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500/40 focus:ring-offset-0">
            <span class="text-slate-300 text-sm">Unlimited</span>
          </div>
          <div class="flex items-center gap-2">
            <input id="vault-quota-size2" type="number" class="w-40 px-3 py-2 rounded-lg border border-slate-700 bg-slate-900/60 text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/40 focus:border-sky-600 placeholder:text-slate-500 transition" placeholder="0" />
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

      <div x-show="tab==='retention'" x-transition id="vault-retention-tab" class="px-5 py-5" x-data="retention()" @retention:update.window="state.override=$event.detail.override; state.mode=$event.detail.mode; state.ranges=$event.detail.ranges; state.defaultMode=$event.detail.defaultMode; state.defaultRanges=$event.detail.defaultRanges">
        <h4 class="text-slate-100 font-semibold mb-3">Retention</h4>
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
          <input id="ret-override" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-sky-600 focus:ring-sky-500/40 focus:ring-offset-0" x-model="state.override">
          <label for="ret-override" class="text-sm text-slate-300">Override account retention for this vault</label>
        </div>
        <!-- Builder when override ON -->
        <template x-if="state.override">
          <div class="space-y-3">
          <!-- Mode select with helper text -->
          <div class="mb-2">
            <label class="block text-sm text-slate-100 mb-1">Mode</label>
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
            <!-- New rule composer -->
            <div x-show="editing===null" class="rounded-xl border border-dashed border-slate-700 p-3">
              <p class="text-slate-100 mb-2 font-medium">Add a rule</p>
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
                <div x-show="[900,907,908,909,910].includes(newRange.Type)"><label class="block text-xs text-slate-400 mb-1">Jobs</label><input type="number" x-model.number="newRange.Jobs" class="w-full px-3 py-2 rounded border border-slate-700 bg-slate-800 text-slate-200 text-sm focus:outline-none focus:ring-1 focus:ring-sky-500" placeholder="e.g., 7"></div>
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
              <div class="mt-3 flex justify-end">
                <button class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 text-sm font-medium" @click="addRangeFromNew()">Add rule</button>
              </div>
            </div>

            <template x-if="(state.ranges || []).length===0">
              <div class="rounded-xl border border-slate-700/70 bg-slate-900/30 p-3 text-sm text-slate-400">
                No rules yet. Add a rule above to define this vault's custom retention behavior.
              </div>
            </template>

            <template x-for="(r,i) in state.ranges" :key="i">
              <div :class="['rounded-xl border bg-slate-800/60 p-3 shadow-sm', editing===i ? 'border-sky-500 ring-1 ring-sky-500/30' : 'border-slate-700']">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="mb-1 text-[11px] uppercase tracking-wide text-slate-400">Retention type</div>
                    <span class="inline-flex items-center rounded-md bg-slate-700 px-2 py-0.5 text-xs font-medium text-slate-100" x-text="labelFor(r.Type)"></span>
                    <p class="mt-2 text-sm leading-5 text-slate-200" x-text="summaryFor(r).replace('[' + labelFor(r.Type) + '] ', '')"></p>
                  </div>
                  <div class="flex shrink-0 gap-2">
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
                      <input type="number" x-model.number="r.Jobs" class="w-full px-3 py-2 rounded border border-slate-700 bg-slate-800 text-slate-200 text-sm focus:outline-none focus:ring-1 focus:ring-sky-500" placeholder="e.g., 7">
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
          </div>
          </div>
        </template>
        <template x-if="!state.override">
          <div class="text-sm text-slate-300">This vault follows the account default policy.</div>
        </template>
        <div class="sticky bottom-0 mt-4 rounded-xl border border-slate-700 bg-slate-800/80 backdrop-blur p-3">
          <p class="text-slate-300 font-medium mb-1">Effective policy:</p>
          <div class="mt-2 space-y-2">
            <template x-if="(state.override ? state.mode : state.defaultMode) === 801">
              <div class="space-y-1">
                <span class="inline-flex items-center rounded-md bg-slate-700 px-2 py-0.5 text-xs font-medium text-slate-100">Mode</span>
                <p class="text-sm leading-5 text-slate-200">Keep everything (no deletions)</p>
              </div>
            </template>
            <template x-if="(state.override ? state.mode : state.defaultMode) !== 801">
              <template x-for="(r,i) in ((state.override ? state.ranges : state.defaultRanges) || [])" :key="'effective-rule-'+i">
                <div class="space-y-1">
                  <span class="inline-flex items-center rounded-md bg-slate-700 px-2 py-0.5 text-xs font-medium text-slate-100" x-text="labelFor(r.Type)"></span>
                  <p class="text-sm leading-5 text-slate-200" x-text="summaryFor(r).replace('[' + labelFor(r.Type) + '] ', '')"></p>
                </div>
              </template>
            </template>
            <template x-if="(state.override ? state.mode : state.defaultMode) !== 801 && (((state.override ? state.ranges : state.defaultRanges) || []).length === 0)">
              <p class="text-sm text-slate-400">No retention rules configured.</p>
            </template>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button id="vault-retention-save" class="inline-flex items-center justify-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-emerald-500/40 bg-gradient-to-r from-emerald-600 to-emerald-500 text-white transition hover:from-emerald-700 hover:to-emerald-600">Save</button>
        </div>

      </div>

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